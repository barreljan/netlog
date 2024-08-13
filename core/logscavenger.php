<?php
// NetLog scavenger
// For continuous, rapid searching of specific keywords (and thus events). Every found keyword
// will be pushed into a separate 'Netalert' table: HST_127_0_0_2_DATE_YYYY_MM_DD. See the
// logparser.php module where distinction is made by the PROG name.

// Including Netlog config and variables
require(dirname(__DIR__) . "/etc/global.php");

// Check if I am running from the command line
check_cli_sapi();

$lock = aquire_lock();

// Start a logging session with the appropriate PROG-name
openlog('logscavenger', LOG_PID, LOG_USER);

// Get the bogus filter, if there is any
if (file_exists(dirname(__DIR__) . "/core/scavengerfilter.inc.php")) {
    include(dirname(__DIR__) . "/core/scavengerfilter.inc.php");
}
// Double check precence
if (isset($filter)) {
    if (!is_array($filter)) {
        syslog(LOG_WARNING, "Scavenger filter preset but no '\$filter' array found");
        unset($filter);
        $filter = array();
    }
} else {
    $filter = array();
}

/**
 * It takes the $filter as an array that contains the keywords
 * that needs to be filtered. When '%any_host%' as key is used
 * the keyword is for all hosts. If a specific hostname as key
 * is used, it is only filtered for that host. The keywords
 * need to be in an array. You can make variations:
 *
 * $filter['%any_host%'] = ['foo']
 *
 * $filter['%any_host%'] = ['foo', 'bar']
 *
 * $filter['coreswitch'] = ['baz']
 *
 * $filter['coreswitch'] = ['foo', 'baz']
 *
 * Only messaged with 'baz' is filtered when it comes from
 * 'coreswitch'. 'foo' and 'bar' are, including the coreswitch,
 * filtered when a message contains one of them.
 *
 * @param string $msg The original syslog MSG where to look in
 * @param string $host The hostname as configured in Netlog
 * @return bool true if message needs to be filtered, false otherwise
 *
 */
function filter_msg(string $msg, string $host): bool
{
    global $filter;

    foreach ($filter as $k => $v) {
        if ($host == $k) {
            foreach ($v as $m) {
                if (stripos($msg, $m) !== false) {
                    // the host and needle found in haystack
                    return true;
                }
            }
        }
        if ($k == '%any_host%') {
            foreach ($v as $m) {
                if (stripos($msg, $m) !== false) {
                    // any host and needle found in haystack
                    return true;
                }
            }
        }
    }
    return false;
}

// Determine today's date in the table name format and set timing
$today = date('Y_m_d');
$adjusted_datetime = (time() - $config['global']['scavenger_history']);
$time = date('H:i:s', $adjusted_datetime);

// Clean cache
try {
    $query = "DELETE 
                FROM `{$database['DB_CONF']}`.`logcache`
               WHERE `timestamp` < DATE_ADD(NOW(), INTERVAL - " . ($config['global']['scavenger_history'] * 2) . " SECOND)";
    $cleancachequery = $db_link->prepare($query);
    $cleancachequery->execute();
} catch (Exception|Error $e) {
    syslog(LOG_ERR, "Failed to clear the scavenger logcache" . err($e));
}

// Get all today's active hosts (but not Netalert nor localhost). Localhost could be included in the future
try {
    $query = "SELECT `TABLE_NAME` AS `name`
                FROM `information_schema`.`COLUMNS`
               WHERE `COLUMN_NAME` = 'MSG'
                 AND `TABLE_NAME` RLIKE 'HST_[0-9].*$today'
                 AND `TABLE_NAME` NOT RLIKE 'HST_127_0_0_.*'";
    $hostquery = $db_link->prepare($query);
    $hostquery->execute();
    $hostresult = $hostquery->get_result();
    $hostrows = $hostresult->num_rows;
} catch (Exception|Error $e) {
    syslog(LOG_CRIT, "Failed to fetch active host tables" . err($e));
    die();
}

// Get keywords and assemble the query
try {
    $query = "SELECT `keyword`
                FROM `{$database['DB_CONF']}`.`logscavenger`
               WHERE `active` = 1";
    $kwquery = $db_link->prepare($query);
    $kwquery->execute();
    $kwresult = $kwquery->get_result();
    $kwrows = $kwresult->num_rows;
} catch (Exception|Error $e) {
    syslog(LOG_CRIT, "Failed to fetch scavenger keywords" . err($e));
    die();
}

$i = 0;
$querykw1 = "";
while ($keywords = $kwresult->fetch_assoc()) {
    $keyword = addcslashes($keywords['keyword'], '%_');
    $querykw1 .= "`MSG` LIKE '%{$keyword}%'";
    if ($kwrows > 1 && $i != ($kwrows - 1)) {
        $querykw1 .= " OR ";
    }
    $i++;
}

// Finally, loop through all host tables and send a syslog-message out if there is a new hit
while ($hosts_table = $hostresult->fetch_assoc()) {
    $host = $hosts_table['name'];

    try {
        $query = "SELECT * 
                    FROM `{$database['DB']}`.`$host`
                   WHERE `TIME` >= '$time'
                     AND ($querykw1)
                   ORDER BY `TIME` DESC";
        $msgsquery = $db_link->prepare($query);
        $msgsquery->execute();
        $msgsresult = $msgsquery->get_result();
        $msgsrows = $msgsresult->num_rows;
    } catch (Exception|Error $e) {
        syslog(LOG_ERR, "Failed to query table $host with scavenger keywords" . err($e));
        $msgsrows = 0;
    }

    if ($msgsrows != 0) {
        // Tablename to real IP address
        preg_match('/HST_(\d{1,3})_(\d{1,3})_(\d{1,3})_(\d{1,3})_/', $host, $matches);
        $hostip = sprintf("%d.%d.%d.%d", $matches[1], $matches[2], $matches[3], $matches[4]);

        // Get the user-submitted hostname
        try {
            $query = "SELECT `hostname`
                        FROM `{$database['DB_CONF']}`.`hostnames`
                       WHERE `hostip` = \"$hostip\" LIMIT 1";
            $hstnmquery = $db_link->prepare($query);
            $hstnmquery->execute();
            $hstnmresult = $hstnmquery->get_result();
            $row = $hstnmresult->fetch_assoc();
            $hostname = $row['hostname'];
            if ($hostname == '') {
                throw new Exception();
            }
        } catch (Exception|Error $e) {
            $hostname = $hostip;
        }

        // Reading the cache
        try {
            $query = "SELECT `msg` 
                        FROM `{$database['DB_CONF']}`.`logcache`
                       WHERE `HOST` = \"$hostip\"";
            $cachequery = $db_link->prepare($query);
            $cachequery->execute();
            $cacheresult = $cachequery->get_result();
            $cachenumrows = $cacheresult->num_rows;
        } catch (Exception|Error $e) {
            // Query resulted in error somehow, default to no cache
            syslog(LOG_ERR, "Failed to read the scavenger logcache" . err($e));
            $cachenumrows = 0;
        }

        $host_cache_arr = array();
        if (isset($cachenumrows) && ($cachenumrows > 0)) {
            while ($cacherow = $cacheresult->fetch_assoc()) {
                $host_cache_arr[] = $cacherow['msg'];
            }
        }
        // Loop through the found rows of the current host
        while ($row = $msgsresult->fetch_assoc()) {
            $MSG = "$hostname: {$row['MSG']}";

            // Pass to the filter to skip certain words/bogus filter.
            if (filter_msg($row['MSG'], $hostname)) {
                // Add to cache to flag it as 'seen'
                $host_cache_arr[] = $MSG;
                continue;
            }

            // Compare message with cache, if not in cache, process it
            if (!in_array($MSG, $host_cache_arr, true)) {
                // Fill the cache with new entry
                try {
                    $query = "INSERT INTO `{$database['DB_CONF']}`.`logcache` (`HOST`, `MSG`)
                              VALUES (\"$hostip\", ?)";
                    $logcachequery = $db_link->prepare($query);
                    $logcachequery->bind_param('s', $MSG);
                    $logcachequery->execute();
                } catch (Exception|Error $e) {
                    syslog(LOG_ERR, "Failed to insert syslog rule in scavenger logcache" . err($e));
                }

                // Prevent doubles from same host in this run
                $host_cache_arr[] = $MSG;

                // Push message out to the Netalert table
                $tablename = 'HST_127_0_0_2_DATE_' . $today;

                $facilty = $row['FAC'];
                $priority = $row['PRIO'];
                $level = 'warning';
                $tag = $row['TAG'];
                $day = $row['DAY'];
                $time = $row['TIME'];
                $program = '%LOGSCAVENGER%';

                $query = "INSERT INTO `$tablename` (`HOST`,`FAC`,`PRIO`,`LVL`,`TAG`,`DAY`,`TIME`,`PROG`,`MSG`)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?);";
                try {
                    if (!$db_link->prepare($query)) {
                        throw new mysqli_sql_exception();
                    }
                } catch (Exception|Error $e) {
                    // Failed to insert as the table does not exist, lets create it
                    create_table($tablename);
                }
                try {
                    $insertquery = $db_link->prepare($query);
                    $insertquery->bind_param('sssssssss', $hostip, $facilty, $priority, $level, $tag, $day, $time, $program, $MSG);
                    $insertquery->execute();
                } catch (Exception|Error $e) {
                    syslog(LOG_ERR, "Failed to insert syslog rule for $HOST" . err($e));
                }

                // If true push message as-is to remote NMS host
                if ($netalert_to_nms) {
                    remote_syslog($hostname, $hostip, $row);
                }
            }
        }
    }
}
if (isset($cacheresult)) {
    $cacheresult->free_result();
}
$hostresult->free_result();
$msgsresult->free_result();
if (isset($hstnmresult)) {
    $hstnmresult->free_result();
}
$kwresult->free_result();

closelog();
$db_link->close();

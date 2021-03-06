<?php
// NetLog scavenger
// For continuous, rapid searching of specific keywords (and thus events). Every found keyword
// will be pushed into a separate 'Netalert' table: HST_127_0_0_2_DATE_YYYY_MM_DD'. See the
// logparser.php module where distinction is made by the PROG name.

// Including Netlog config and variables
require(dirname(__DIR__) . "/etc/config.php");
if ($netalert_to_nms) {
    require("log2nms.php");
}

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
 * Creates the host table if this not exists.
 *
 * @param string $tablename A composed name for the table
 * @return void
 */
function create_table(string $tablename)
{
    global $db_link;
    global $database;

    $query = "CREATE TABLE IF NOT EXISTS `{$database['DB']}`.`$tablename` LIKE template";
    $createquery = $db_link->prepare($query);
    $result = $createquery->execute();
    if (!$result) {
        // There is a posible serious issue with SQL
        syslog(LOG_CRIT, "Failed to create table $tablename");
        die();
    }
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
                if (strpos($msg, $m) !== false) {
                    // any host and needle found in haystack
                    return true;
                }
            }
        } elseif ($k == '%any_host%') {
            foreach ($v as $m) {
                if (strpos($msg, $m) !== false) {
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
$query = "DELETE FROM `{$database['DB_CONF']}`.`logcache`
               WHERE `timestamp` < DATE_ADD(NOW(), INTERVAL - " . ($config['global']['scavenger_history'] * 2) . " SECOND)";
$cleancachequery = $db_link->prepare($query);
$cleancachequery->execute();

// Get all today's active hosts (but not Netalert nor localhost). Localhost could be included in the future
$query = "SELECT `TABLE_NAME` AS `name`
            FROM `information_schema`.`COLUMNS`
           WHERE `COLUMN_NAME` = 'MSG'
                 AND `TABLE_NAME` RLIKE 'HST_[0-9].*$today'
                 AND `TABLE_NAME` NOT RLIKE 'HST_127_0_0_.*'";
$hostquery = $db_link->prepare($query);
$hostquery->execute();
$hostresult = $hostquery->get_result();
$hostrows = $hostresult->num_rows;

// Get keywords and assemble the query
$query = "SELECT `keyword`
            FROM `{$database['DB_CONF']}`.`logscavenger`
           WHERE `active` = 1";
$kwquery = $db_link->prepare($query);
$kwquery->execute();
$kwresult = $kwquery->get_result();
$kwrows = $kwresult->num_rows;

$i = 0;
$querykw1 = "";
while ($keywords = $kwresult->fetch_assoc()) {
    $querykw1 .= "`MSG` LIKE '%{$keywords['keyword']}%'";
    if ($kwrows > 1 && $i != ($kwrows - 1)) {
        $querykw1 .= " OR ";
    }
    $i++;
}

// Finally, loop through all host tables and send a syslog-message out if there is a new hit
while ($hosts_table = $hostresult->fetch_assoc()) {
    $host = $hosts_table['name'];

    $query = "SELECT * 
                FROM `{$database['DB']}`.`$host`
               WHERE `TIME` >= '$time'
                     AND ($querykw1)
               ORDER BY `TIME` DESC";
    $msgsquery = $db_link->prepare($query);
    $msgsquery->execute();
    $msgsresult = $msgsquery->get_result();
    $msgsrows = $msgsresult->num_rows;
    if ($msgsrows != 0) {
        // Tablename to real IP address
        preg_match('/HST_(\d{1,3})_(\d{1,3})_(\d{1,3})_(\d{1,3})_/', $host, $matches);
        $hostip = sprintf("%d.%d.%d.%d", $matches[1], $matches[2], $matches[3], $matches[4]);

        // Get the user-submitted hostname
        $query = "SELECT `hostname`
                    FROM `{$database['DB_CONF']}`.`hostnames`
                   WHERE `hostip` = \"$hostip\" LIMIT 1";
        $hstnmquery = $db_link->prepare($query);
        $hstnmquery->execute();
        $hstnmresult = $hstnmquery->get_result();
        $row = $hstnmresult->fetch_assoc();
        $hostname = $row['hostname'];

        if (!isset($hostname) && $hostname == '') {
            $hostname = $hostip;
        }
        // Reading the cache
        $query = "SELECT `msg` 
                    FROM `{$database['DB_CONF']}`.`logcache`
                   WHERE `HOST` = \"$hostip\"";
        $cachequery = $db_link->prepare($query);
        $cachequery->execute();
        $cacheresult = $cachequery->get_result();
        $cachenumrows = $cacheresult->num_rows;
        $host_cache_arr = array();
        if (isset($cachenumrows) && ($cachenumrows > 0)) {
            while ($cacherow = $cacheresult->fetch_array()) {
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
                $query = "INSERT INTO `{$database['DB_CONF']}`.`logcache` (`HOST`, `MSG`)
                               VALUES (\"$hostip\", ?)";
                $logcachequery = $db_link->prepare($query);
                $logcachequery->bind_param('s', $MSG);
                $logcachequery->execute();

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
                if (!$db_link->prepare($query)) {
                    // Failed to insert as the table does not exist, lets create it
                    create_table($tablename);
                }
                $insertquery = $db_link->prepare($query);
                $insertquery->bind_param('sssssssss', $hostip, $facilty, $priority, $level, $tag, $day, $time, $program, $MSG);
                $insertquery->execute();

                // If true push message as-is to remote NMS host
                if ($netalert_to_nms) {
                    remote_syslog($hostname, $hostip, $row);
                }

                // Send email to recipient(s), for selected keywords if group is active
                // if ((strpos($hostname, "coresw01") !== false) && (strpos($row['MSG'], "PSECURE_VIOLATION") !== false)) {
                //     send_email($hostname, $from, $row['MSG']);
                // }
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

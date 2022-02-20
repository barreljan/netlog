<?php
// NetLog scavenger (for background task)
// For continuous searching of specific keywords (and thus events)

// Including Netlog config and variables
require("/usr/share/syslog-ng/etc/netlog.conf");

$lock = aquire_lock();

// Start a logging session with the appropriate PROG-name
openlog('%LOGSCAVENGER%', LOG_PID, LOG_USER);

// Determine today's date in the table name format and set timing
$today = date('Y_m_d');
$adjusted_datetime = (time() - $config['global']['scavenger_history']);
$time = date('H:i:s', $adjusted_datetime);

// Clean cache
$query = "DELETE FROM `{$database['DB_CONF']}`.`logcache`
               WHERE `timestamp` < DATE_ADD(NOW(), INTERVAL - " . ($config['global']['scavenger_history'] * 2) . " SECOND)";
$cleancachequery = $db_link->prepare($query);
$cleancachequery->execute();

// Get all today's active hosts
$query = "SELECT `TABLE_NAME` AS `name`
            FROM `information_schema`.`COLUMNS`
           WHERE `COLUMN_NAME` = 'MSG'
                 AND `TABLE_NAME` RLIKE 'HST_[0-9].*{$today}'
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

    $query = "SELECT `MSG` 
                FROM `{$database['DB']}`.`{$host}`
               WHERE `TIME` >= '{$time}'
                     AND ({$querykw1})
               ORDER BY `TIME` DESC";
    $msgsquery = $db_link->prepare($query);
    $msgsquery->execute();
    $msgsresult = $msgsquery->get_result();
    $msgsrows = $msgsresult->num_rows;
    if ($msgsrows != 0) {
        // Tablename to real IP address
        preg_match('/HST_([0-9]{1,3})_([0-9]{1,3})_([0-9]{1,3})_([0-9]{1,3})_/', $host, $matches);
        $hostip = sprintf("%d.%d.%d.%d", $matches[1], $matches[2], $matches[3], $matches[4]);

        // Get the user-submitted hostname
        $query = "SELECT `hostname`
                    FROM `{$database['DB_CONF']}`.`hostnames`
                   WHERE `hostip` = \"{$hostip}\" LIMIT 1";
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
                   WHERE `HOST` = \"{$hostip}\"";
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

            // Evil thingy to skip certain words/bogus filter
            if (
                strpos($row['MSG'], "someting_i_do_not_want") !== false ||
                (strpos($row['MSG'], "something_else") !== false && strpos($row['MSG'], "in combination with") !== false)
            ) {
                continue;
            }
            // Compare message with cache, if not in cache, process it
            if (!in_array($row['MSG'], $host_cache_arr, true)) {
                // Fill the cache with new entry
                $query = "INSERT INTO `{$database['DB_CONF']}`.`logcache` (`HOST`, `MSG`)
                          VALUES (\"{$hostip}\", ?)";
                $logcachequery = $db_link->prepare($query);
                $logcachequery->bind_param('s', $row['MSG']);
                $logcachequery->execute();

                // Push message out to system to be fetched by the logparser
                syslog(LOG_WARNING, "{$hostname}: {$row['MSG']}");

                // Send an email, for worst-case situation
                if ((strpos($hostname, "coresw01") !== false) && (strpos($row['MSG'], "PSECURE_VIOLATION") !== false)) {
                    $subject = "Network port violation on {$hostname}";
                    $msg = "There is a port violation detected on the network\n\n";
                    $msg .= "{$hostname}:\n {$row['MSG']}";
                    $msg .= "\n\n\nTake actions asap!";
                    $headers = array();
                    $headers[] = "MIME-Version: 1.0";
                    $headers[] = "Content-type: text/plain; charset=iso-8859-1";
                    $headers[] = "From: Netlog server <{$from}>";
                    $headers[] = "Reply-To: No-Reply <{$from}>";
                    $headers[] = "X-Mailer: PHP/" . phpversion();
                    mail($mail_rcpt, $subject, $msg, implode("\r\n", $headers), "-f {$mail_from}");
                }
            }
        }
    }
}
$cacheresult->free_result();
$hostresult->free_result();
$msgsresult->free_result();
$hstnmresult->free_result();
$kwresult->free_result();

closelog();
$db_link->close();

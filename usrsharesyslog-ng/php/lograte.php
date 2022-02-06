<?php
// This script will determine rates of logging for specific hosts in the system
// Hopefully this will be a quick way of identifying trouble spots

// Including Netlog config and variables
require("/usr/share/syslog-ng/etc/netlog.conf");

$lock = aquire_lock();

// Determine today's date in the table name format
$today = date('Y_m_d');

// Create list of hosts we should watch
$query = "SELECT `id`, `hostip`
            FROM `{$database['DB_CONF']}`.`hostnames`
           WHERE `lograte` = 1";
$hostquery = $db_link->prepare($query);
$hostquery->execute();
$hostresult = $hostquery->get_result();

// Loop through the hosts and run the counts
while ($host = $hostresult->fetch_assoc()) {
    // Assemble table name
    $convertedhost = str_replace('.', '_', $host['hostip']);
    $tablename = "HST_" . $convertedhost . "_DATE_" . $today;

    // Select the 1,5 and 10 min rate and insert into lograte table
    $query = "SELECT COUNT(*) AS 1min,
                     (SELECT COUNT(*) 
                        FROM `{$database['DB']}`.`$tablename` 
                       WHERE TIME > SUBTIME(CURTIME(), '00:05:00')) AS 5min,
                     (SELECT COUNT(*)
                        FROM `{$database['DB']}`.`$tablename`
                       WHERE TIME > SUBTIME(CURTIME(), '00:10:00')) as 10min 
                FROM `{$database['DB']}`.`$tablename`
               WHERE TIME > SUBTIME(CURTIME(), '00:01:00')";
    $ratequery = $db_link->prepare($query);
    // Todo: fix routine when a lograte-enabled host has not logged today...
    $ratequery->execute();
    $rateresult = $ratequery->get_result();

    while ($rates = $rateresult->fetch_assoc()) {
        $query = "INSERT INTO `{$database['DB_CONF']}`.`lograte` (hostnameid, 1min, 5min, 10min)
                       VALUES (?, ?, ?, ?)";
        $logratequery = $db_link->prepare($query);
        $logratequery->bind_param('iiii', $host['id'], $rates['1min'], $rates['5min'], $rates['10min']);
        $logratequery->execute();
    }
}

unlock($lock);

<?php
// This script will prune the syslog tables, or in other words drop tables older than x

// Including Netlog config and variables
require(dirname(__DIR__) . "/etc/global.php");

// Check if I am running from the command line
check_cli_sapi();

$lock = aquire_lock();

// Start a logging session with the appropriate PROG-name
openlog('prunesyslog', LOG_PID, LOG_USER);

// Add 1, so you actually keep the months you configured
$retention = $config['global']['retention'] + 1;
$date = date("Y_M", strtotime("first day of this month 00:00:00 -$retention month"));

// Get syslog tables older than is set
try {
    $query = "SELECT `TABLE_NAME`
                FROM `information_schema`.`TABLES`
               WHERE `TABLE_SCHEMA` = 'syslog'
                 AND `TABLE_NAME` LIKE ?";
    $prunequery = $db_link->prepare($query);
    $_date = "%" . $date;
    $prunequery->bind_param('s', $_date);
    $prunequery->execute();
    $pruneresult = $prunequery->get_result();
} catch (Exception|Error $e) {
    syslog(LOG_CRIT, "Failed to fetch tables for pruning" . err($e));
    die();
}
while ($row = $pruneresult->fetch_assoc()) {
    $result[] = $row['TABLE_NAME'];
}
$cnt = $pruneresult->num_rows;

if ($cnt >= 1) {
    // Make the combined drop query from the table names
    try {
        $query = "DROP TABLE " . implode(',', $result) . ";";
        $dropquery = $db_link->prepare($query);
        $result = $dropquery->execute();
        if ($result) {
            syslog(LOG_NOTICE, "Drop of $cnt tables for '$date' successfull");
        }
    } catch (Exception|Error $e) {
        syslog(LOG_ERR, "Drop of $cnt tables for '$date' unsuccessfull" . err($e));
    }
} else {
    syslog(LOG_NOTICE, "No tables to drop for '$date'");
}

closelog();
$db_link->close();

unlock($lock);

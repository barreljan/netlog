<?php
// This script will prune the lograte table, or in other words drop rows older than x

// Including Netlog config and variables
require(dirname(__DIR__) . "/etc/global.php");

// Check if I am running from the command line
check_cli_sapi();

$lock = aquire_lock();

// Start a logging session with the appropriate PROG-name
openlog('prunelograte', LOG_PID, LOG_USER);

$days = $config['global']['lograte_days'];

// Delete lograte samples older than is set
$query = "DELETE 
            FROM `{$database['DB_CONF']}`.`lograte`
           WHERE `sample_timestamp` < (NOW()-INTERVAL $days DAY)";
try {
    $prunequery = $db_link->prepare($query);
    $prunequery->execute();
} catch (Exception|Error $e) {
    syslog(LOG_WARNING, "Failed to delete lograte rows" . err($e));
} finally {
    syslog(LOG_INFO, "Lograte samples pruned succesfully");
}

closelog();
$db_link->close();

unlock($lock);

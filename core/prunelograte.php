<?php
// This script will prune the lograte table, or in other words drop rows older than x

// Including Netlog config and variables
require(dirname(__DIR__) . "/etc/config.php");

// Check if I am running from the command line
check_cli_sapi();

$lock = aquire_lock();

// Start a logging session with the appropriate PROG-name
openlog('prunelograte', LOG_PID, LOG_USER);

$days = $config['global']['lograte_days'];

// Delete lograte samples older than is set
$query = "DELETE FROM `{$database['DB_CONF']}`.`lograte`
           WHERE `timestamp` < (NOW()-INTERVAL $days DAY)";
$prunequery = $db_link->prepare($query);
$result = $prunequery->execute();
if (!$result) {
    syslog(LOG_WARNING, "Failed to delete lograte rows: " . mysqli_connect_error());
}

closelog();
$db_link->close();

unlock($lock);

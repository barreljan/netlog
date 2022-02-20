<?php
// This script will prune the lograte table

// Including Netlog config and variables
require("/usr/share/syslog-ng/etc/netlog.conf");

$lock = aquire_lock();
$days = $config['global']['lograte_days'];


// Delete lograte samples older than is set
$query = "DELETE FROM `{$database['DB_CONF']}`.`lograte`
           WHERE `timestamp` < (NOW()-INTERVAL {$days} DAY)";
$prunequery = $db_link->prepare($query);
$prunequery->execute();

unlock($lock);

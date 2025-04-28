#!/bin/env php
<?php
/*
 * Small utility to get a hint on how many lines are logged today
 */

// Including Netlog config and variables
require(dirname(__DIR__) . "/etc/global.php");

if (count($argv) > 1) {
    foreach ($argv as $arg) {
        switch($arg) {
            case '-?':
            case '-h':
            case '--help':
                echo "stats: Show the number of logged lines\n\nUsage: stats <date>\t\t";
                echo "Where date represents YYYY-MM-DD or YYYY_MM_DD\n";
                exit;
        }
        if (preg_match('/[0-9]{4}_[0-9]{2}_[0-9]{2}/', $arg, $matches)) {
            $today = $matches[0];
            break;
        } elseif (preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $arg, $matches)) {
            $today = str_replace('-', '_', $matches[0]);
            break;
        }
    }
}
// If no input or date format is wrong, defaulting to today itself
$today = $today ?? date('Y_m_d');

// Get all today's active hosts (but not Netalert nor localhost). Localhost could be included in the future
try {
    $query = "SELECT `TABLE_NAME` AS `name`
                FROM `information_schema`.`COLUMNS`
               WHERE `COLUMN_NAME` = 'MSG'
                 AND `TABLE_NAME` RLIKE 'HST_[0-9].*$today'";
    $hostquery = $db_link->prepare($query);
    $hostquery->execute();
    $hostresult = $hostquery->get_result();
    $hostrows = $hostresult->num_rows;
} catch (Exception|Error $e) {
    syslog(LOG_CRIT, "Failed to fetch active host tables" . err($e));
    die();
}

$nr_of_lines = 0;
while ($hosts_table = $hostresult->fetch_assoc()) {
    $host = $hosts_table['name'];

    try {
        $query = "SELECT COUNT(*) AS `count`
                    FROM `{$database['DB']}`.`$host`";
        $msgsquery = $db_link->prepare($query);
        $msgsquery->execute();
        $msgsresult = $msgsquery->get_result();
    } catch (Exception|Error $e) {
        syslog(LOG_ERR, "Failed to query table $host to count" . err($e));
        $msgsrows = 0;
    }

    while ($row = $msgsresult->fetch_assoc()) {
        $c = $row['count'];
        $nr_of_lines += $c;
    }
}

$dfmt = str_replace('_', '-', $today);
echo "Total of lines logged per $dfmt: $nr_of_lines\n";


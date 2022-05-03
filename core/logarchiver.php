<?php
// This script will be called on a daily bassis to aggregate logging
// In this setup logging is divided as per following rules:
// - A table per day, per host for the first x-days (default 14) of logging,
// - A table per host, per month for the current and last months of logging.

// Including Netlog config and variables
require(dirname(__DIR__) . "/etc/config.php");

// Check if I am running from the command line
check_cli_sapi();

$lock = aquire_lock();

// Start a logging session with the appropriate PROG-name
openlog('logarchiver', LOG_PID, LOG_USER);

// Get the archiving interval (in days) from the configuration
$archinterval = date("Y_m_d", strtotime("-{$config['global']['logarchive_interval']} days"));

// Get the tablenames that matter
$query = "SELECT `TABLE_NAME`
            FROM `information_schema`.`TABLES`
           WHERE `TABLE_SCHEMA` = 'syslog' 
                 AND `TABLE_NAME` NOT IN ('template') 
                 AND `CREATE_TIME` <= '$archinterval'
        ORDER BY `CREATE_TIME`";
$tablequery = $db_link->prepare($query);
$tablequery->execute();
$tableresult = $tablequery->get_result();

while ($tables = $tableresult->fetch_assoc()) {
    $table_name = $tables['TABLE_NAME'];
    $table = explode('_DATE_', $table_name);
    if (!isset($table['1'])) {
        continue;
    }

    // If the tablename matches YYYY_MM_DD, continue
    if (preg_match('/\d{4}_\d{2}_\d{2}/', $table['1'])) {
        $host = $table['0'];

        $daysplitup = explode('_', $table['1']);
        $year = $daysplitup['0'];
        $month = $daysplitup['1'];
        $monthname = date('M', mktime(0, 0, 0, $month));

        // Make a month-table for the host if it does not exist
        $dsttable = $host . "_DATE_" . $year . "_" . $monthname;
        $query = "CREATE TABLE IF NOT EXISTS `{$database['DB']}`.`$dsttable` LIKE `template`";
        $createquery = $db_link->prepare($query);
        $result = $createquery->execute();
        if (!$result) {
            syslog(LOG_WARNING, "Failed to create table $dsttable: " . mysqli_connect_error());
        }
        unset($query, $createquery, $result);

        // Copy all records from day-tble into month-table
        $query = "INSERT INTO `{$database['DB']}`.`$dsttable` (HOST, FAC, PRIO, LVL, TAG, DAY, TIME, PROG, MSG)
                       SELECT HOST, FAC, PRIO, LVL, TAG, DAY, TIME, PROG, MSG 
                         FROM `$table_name`";
        $archive_query = $db_link->prepare($query);
        $result = $archive_query->execute();
        if (!$result) {
            syslog(LOG_WARNING, "Failed to copy to $dsttable: " . mysqli_connect_error());
        }
        unset($query, $archive_query, $result);

        // Drop old day-table
        $query = "DROP TABLE `{$database['DB']}`.`$table_name`";
        $dropquery = $db_link->prepare($query);
        $result = $dropquery->execute();
        if (!$result) {
            syslog(LOG_WARNING, "Failed to drop table $table_name: " . mysqli_connect_error());
        }
        unset($query, $dropquery, $result);
    }
}

unlock($lock);

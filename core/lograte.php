<?php
// This script will determine rates of logging for hosts enabled with lograte in the system
// It will be a quick way of identifying trouble spots and trend analyses.

// Including Netlog config and variables
require(dirname(__DIR__) . "/etc/global.php");

// Check if I am running from the command line
check_cli_sapi();

$lock = aquire_lock();

// Start a logging session with the appropriate PROG-name
openlog('lograte', LOG_PID, LOG_USER);

// Determine today's date in the table name format
$today = date('Y_m_d');

// Create list of hosts we should watch
try {
    $query = "SELECT `id`, `hostip`
                FROM `{$database['DB_CONF']}`.`hostnames`
               WHERE `lograte` = 1";
    $hostquery = $db_link->prepare($query);
    $hostquery->execute();
    $hostresult = $hostquery->get_result();
} catch (Exception|Error $e) {
    syslog(LOG_CRIT, "Failed to fetch lograte enabled hosts");
    die();
}

// Put the hosts in an array
while ($row = $hostresult->fetch_assoc()) {
    $hosts[] = $row;
}

// Loop through the hosts and run the counts
foreach ($hosts as $host) {
    // Clear array for each iteration
    unset($counted_rates);
    // Assemble table name
    $convertedhost = str_replace('.', '_', $host['hostip']);
    $tablename = "HST_" . $convertedhost . "_DATE_" . $today;

    // Select the 1,5 and 10 min rate and insert into lograte table
    try {
        $query = "SELECT COUNT(*) AS 1min,
                         (SELECT COUNT(*)
                            FROM `{$database['DB']}`.`$tablename`
                           WHERE TIME > SUBTIME(CURTIME(), '00:05:00')) AS 5min,
                         (SELECT COUNT(*)
                            FROM `{$database['DB']}`.`$tablename`
                           WHERE TIME > SUBTIME(CURTIME(), '00:10:00')) as 10min
                    FROM `{$database['DB']}`.`$tablename`
                   WHERE TIME > SUBTIME(CURTIME(), '00:01:00')";
        if (!$db_link->prepare($query)) {
            throw new mysqli_sql_exception();
        }
        $ratequery = $db_link->prepare($query);
        $ratequery->execute();
        $rateresult = $ratequery->get_result();

        while ($row = $rateresult->fetch_assoc()) {
            $counted_rates[] = $row;
        }
    } catch (Exception|Error|mysqli_sql_exception $e) {
        // No logging today for current host as the tablename fails
        syslog(LOG_WARNING, "Failed to get rates for " . $host['hostip']);
        // So host is probably not logging, defaulting to 0
        $a = array(
            '1min' => 0,
            '5min' => 0,
            '10min' => 0
        );
        $counted_rates[] = $a;
    }

    // Insert the rates into the database
    foreach ($counted_rates as $rates) {
        $query = "INSERT INTO `{$database['DB_CONF']}`.`lograte` (hostnameid, 1min, 5min, 10min)
                  VALUES (?, ?, ?, ?)";
        try {
            if (!$db_link->prepare($query)) {
                throw new mysqli_sql_exception();
            }
            $logratequery = $db_link->prepare($query);
            $logratequery->bind_param('iiii', $host['id'], $rates['1min'], $rates['5min'], $rates['10min']);
            $result = $logratequery->execute();
        } catch (Exception|Error $e) {
            syslog(LOG_WARNING, "Failed to insert rates");
        }
    }
}

closelog();
$db_link->close();

unlock($lock);


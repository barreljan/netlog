<?php
// This daemon will work together with syslog-ng to parse logging information to a MySQL database
// It will read the logging from a fifo pipe in which syslog-ng will write the syslog data.
// Standard syslog-ng - to - MySQL solutions would write all logging to one single table.
// This script will parse and divide logging per host, per day and is a efficient way
// and the most active tables will have a reasonable size, thus queryable.
declare(ticks=1);

// Including Netlog config and variables
require(dirname(__DIR__) . "/etc/global.php");

// Check if I am running from the command line
check_cli_sapi();

$lock = aquire_lock();

// Start a logging session with the appropriate PROG-name
openlog("logparser", 0, LOG_LOCAL0);

/**
 * Signal handler. No complexity, just one way out.
 * @return void
 */
function clean_exit(): void
{
    global $db_link, $lock;

    syslog(LOG_INFO, "Closing the fifo and stop processing syslog messages");
    closelog();
    $db_link->close();
    unlock($lock);
    die();
}

/**
 * Parses the lines from the fifo to the database.
 * @param array $logitems
 * @return void
 */
function parse_log(array $logitems): void
{
    global $db_link, $database;

    // Set defaults
    $FAC = $PRIO = $LVL = $TAG = $DAY = $TIME = $PROG = $MSG = '';

    // Dynamically make the variables
    foreach ($logitems as $linepart) {
        $item = explode('_:_', $linepart);
        if (!isset($item['1'])) {
            // The field has to have a value, set a blank
            $item['1'] = '';
        }
        // Make var with the value
        ${$item['0']} = trim($item['1']);
    }
    // Should the host be missing, default to 'Unidentified Host Object' table
    $HOST ??= 'UHO';

    // NetLog Scavenger to NetAlert for visability
    if (str_contains($PROG, '%LOGSCAVENGER%')) {
        $HOST = "127.0.0.2";
    }

    // Construct tablename
    $HOST_us = str_replace('.', '_', $HOST);
    $DAY_us = str_replace('-', '_', $DAY);
    $tablename = 'HST_' . $HOST_us . '_DATE_' . $DAY_us;

    // Insert the syslog message into the database
    $query = "INSERT INTO `{$database['DB']}`.`$tablename` (`HOST`, `FAC`, `PRIO`, `LVL`, `TAG`, `DAY`, `TIME`, `PROG`, `MSG`)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    try {
        if (!$db_link->prepare($query)) {
            throw new mysqli_sql_exception();
        }
    } catch (Exception|Error $e) {
        // Failed to insert as the table does not exist, lets create it
        create_table($tablename);
    }
    // Proceed with insert
    try {
        $insertquery = $db_link->prepare($query);
        $insertquery->bind_param('sssssssss', $HOST, $FAC, $PRIO, $LVL, $TAG, $DAY, $TIME, $PROG, $MSG);
        $result = $insertquery->execute();
        if (!$result) {
            throw new mysqli_sql_exception();
        }
    } catch (Exception|Error $e) {
        syslog(LOG_WARNING, "Failed to insert syslog rule for $HOST" . err($e));
    }
}

/**
 * Reads the fifo and make an array per line which passes to the parse_log function
 * @return void
 */
function read_fifo(): void
{
    global $log_fifo;

    syslog(LOG_INFO, "Opening the fifo and processing syslog messages");
    while ($fifo = fopen($log_fifo, 'r')) {
        $buffer = fgets($fifo);
        $logitems = explode(' _,_ ', $buffer);
        parse_log($logitems);
    }
}

// Setup signal handlers
pcntl_signal(SIGINT, "clean_exit");
pcntl_signal(SIGTERM, "clean_exit");
pcntl_signal(SIGHUP, "clean_exit");

// Check if fifo socket exists, otherwise create
if (!file_exists($log_fifo)) {
    umask(0);
    $mode = 0600;
    posix_mkfifo($log_fifo, $mode);
    syslog(LOG_NOTICE, "Fifo $log_fifo created");
}

// Process the incomming entries
read_fifo();

// Should we somehow missed some and reached this line
syslog(LOG_ERR, "Reached abnormal shutdown of daemon");
clean_exit();

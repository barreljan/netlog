<?php
// This script will work together with syslog-ng to parse logging information to a MySQL database
// We will read the logging from a fifo pipe in which syslog-ng will write the syslog data
// Standard syslog-ng - to - MySQL solutions would write all logging to one single table.
// This script will parse and divide logging per host, per day.
// This way the most active tables will have a reasonable size, thus queryable.

// Including Netlog config and variables
require(dirname(__DIR__) . "/etc/global.php");

// Check if I am running from the command line
check_cli_sapi();

$lock = aquire_lock();

// Start a logging session with the appropriate PROG-name
openlog("logparser", 0, LOG_LOCAL0);

// Set handlers
pcntl_async_signals(true);
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP,  "sig_handler");

/**
 * Handler function. Commit before exiting, nothing more
 * @param int $signo
 * @return void
 */
function sig_handler($signo) {
    global $db_link;

    syslog(LOG_NOTICE, "Exiting...(" . $signo . ")");
    $db_link->commit();

    switch ($signo) {
        case SIGTERM:
            exit;
            break;
        case SIGHUP:
            exit;
            break;
    }
}

/**
 * Parses the lines from the fifo to the database.
 *
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
    global $db_link, $log_fifo, $batch_size, $batch_max_age;

    /* Turn autocommit off */
    $db_link->autocommit(false);

    // Seting batch time
    $last_commit = time();

    syslog(LOG_INFO, "Opening the fifo and processing syslog messages");
    $fifo = fopen($log_fifo, 'r');

    $i = 0;
    while (true) {
        $buffer = fgets($fifo); // Blocking read
        if ($buffer !== false) {
            $logitems = explode(' _,_ ', trim($buffer));
            parse_log($logitems);
            $i++;
        }
        // Batched commit routine
        if ($i >= $batch_size || (time() - $last_commit) >= $batch_max_age) {
            $db_link->commit();
            $last_commit = time();
            $i = 0;
        }
    }
}

// Check if fifo socket exists, otherwise create
if (!file_exists($log_fifo)) {
    umask(0);
    $mode = 0600;
    posix_mkfifo($log_fifo, $mode);
    syslog(LOG_NOTICE, "Fifo $log_fifo created");
}

// Process the incomming entries
read_fifo();

closelog();
$db_link->close();

unlock($lock);

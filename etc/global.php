<?php
// Netlog: A Syslog-NG to MySQL parser with no-nonsense frontend
// Project: https://github.com/barreljan/netlog

// Versioning etc
const VERSION = 'v3.0';
const NAME = 'Syslog-ng to MySQL parser';
const AUTHOR = 'bartjan@pc-mania.nl';

/**
 * A simple check if the PHP session is started
 * @return bool Boolean (true or false)
 */
function is_session_started(): bool
{
    if (php_sapi_name() === 'cli')
        return false;
    if (version_compare(phpversion(), '5.4.0', '>='))
        return session_status() === PHP_SESSION_ACTIVE;
    return session_id() !== '';
}

/**
 * Check if I am running from the cli
 * @return void
 */
function check_cli_sapi(): void
{
    if (php_sapi_name() !== 'cli') {
        die('Run me from the command line');
    }
}

/**
 * Converts an alphabetic string into an integer.
 * @param string $a A string
 * @return string
 */
function alpha2num(string $a): string
{
    $r = 0;
    $l = strlen($a);
    for ($i = 0; $i < $l; $i++) {
        $r += pow(26, $i) * (ord($a[$l - $i - 1]) - 0x40);
    }
    return substr($r - 1, 0, 9);
}

/**
 * Aquires a semaphore lock for cli programs that do not permit multiple threads.
 * It uses the caller's filename to make an uniq integer as key.
 * @return false|void
 */
function aquire_lock()
{
    global $argv;
    // Use the origin's filename to make an uniq id
    $key = alpha2num(basename($argv[0], ".php"));
    $maxAcquire = 1;
    $permissions = 0666;
    $autoRelease = 1;

    $semaphore = sem_get($key, $maxAcquire, $permissions, $autoRelease);
    if (sem_acquire($semaphore, true)) {
        return $semaphore;
    } else {
        die("Process already running\n");
    }
}

/**
 * Unlockes a previous semaphore lock
 * @param $semaphore
 * @return void
 */
function unlock($semaphore): void
{
    sem_release($semaphore);
}

/**
 * For debugging purposes, do not use in production environments!
 * This is displaying some interesting parts on the bottom of the 'result' div.
 * Can be used in conjunction with the config parameter 'debug'
 * @return void
 */
function codedebug(): void
{
    global $debug;

    if ($debug) {
        echo "<br><br>\n<pre class='codedebug'>\n";
        echo "\$_SESSION:\n";
        print_r($_SESSION);
        echo "\$_POST:\n";
        print_r($_POST);
        // echo "constants\n";
        // print_r(get_defined_constants(true)['user']);
        echo "</pre>\n";
    }
}

/**
 * Added the error message in a formatted way when debug is set to true
 * @param $e
 * @return string
 */
function err($e): string
{
    global $debug;
    if (is_session_started()) {
        // Unlock webbrowser from faulty view/session
        session_destroy();
    }
    return $debug ? ": \n$e" : '';
}

/**
 * Send an HTML email to the admin. This function is primarily used within the Logscavenger module.
 * No current use, keep it for future use.
 *
 * @param string $hostname
 * @param string $from
 * @param string $message
 * @return void
 */
function send_email(string $hostname, string $from, string $message): void
{
    global $mail_from, $mail_rcpt;
    $subject = "Network port violation on $hostname";
    $msg = "There is a event detected by Logscavenger\n\n";
    $msg .= "$hostname:\n $message";
    $msg .= "\n\n\nTake actions asap!";
    $headers = array();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/plain; charset=iso-8859-1";
    $headers[] = "From: Netlog server <$from>";
    $headers[] = "Reply-To: No-Reply <$from>";
    $headers[] = "X-Mailer: PHP/" . phpversion();
    mail($mail_rcpt, $subject, $msg, implode("\r\n", $headers), "-f $mail_from");
}

/**
 * Creates the host table if this not exists.
 *
 * @param string $tablename A composed name for the table
 * @return void
 */
function create_table(string $tablename): void
{
    global $db_link, $database;

    try {
        $query = "CREATE TABLE IF NOT EXISTS `{$database['DB']}`.`$tablename` LIKE template";
        $createquery = $db_link->prepare($query);
        $createquery->execute();
    } catch (Exception|Error $e) {
        // There is a posible serious issue with SQL
        syslog(LOG_CRIT, "Failed to create table $tablename: $e");
        die();
    }
}

/**
 * Polyfill functions for backwards compatibility (7.4, 8.0, 8.1)
 */
if (!function_exists('str_starts_with')) {
    function str_starts_with(string $haystack, string $needle): bool
    {
        return 0 === strncmp($haystack, $needle, strlen($needle));
    }
}
if (!function_exists('str_contains')) {
    function str_contains(string $haystack, string $needle): bool
    {
        return '' === $needle || false !== strpos($haystack, $needle);
    }
}

/*
 * End functions
 */

// Start session
if (!is_session_started()) {
    session_name('PHP_NETLOG');
    if (!session_start()) {
        die("No session set or server is not allowing PHP Sessions to be stored?");
    }
}

// Set basepath for Netlog GUI
$basepath = dirname($_SERVER['SCRIPT_NAME']) . "/";

/**
 * for debugging set to true
 * @var $debug
 */
$debug = false;

// Load database settings
require(dirname(__DIR__) . '/etc/netlog.conf');
$database = $database ?? die("Database settings not found! Please copy the netlog.conf.example to the 'etc' directory and fill in settings.\n");

// Check and if not, create database link
if (!isset($db_link)) {
    try {
        $db_link = new mysqli($database['HOST'], $database['USER'], $database['PASS'], $database['DB']);
    } catch (Exception|Error $e) {
        die("Connect to the database failed!" . err($e));
    }
}

// Populate the global config settings
try {
    $query = "SELECT `setting`, `value`
                FROM `{$database['DB_CONF']}`.`global`";
    $globalquery = $db_link->prepare($query);
    $globalquery->execute();
    $globalresults = $globalquery->get_result();
    while ($global = $globalresults->fetch_assoc()) {
        $config['global'][$global['setting']] = $global['value'];
    }
} catch (Exception|Error $e) {
    die("Failed to get the global config" . err($e));
}

// Load nms module if enabled
if ($config['global']['netalert_to_nms'] === "1") {
    require(dirname(__DIR__) . "/core/log2nms.php");
    $nms_database = $nms_database ?? die("NMS Database settings not found! Please copy the netlog.conf.example to the 'etc' directory and fill in settings.\n");
}

// Fifo socket
$log_fifo = $config['global']['log_fifo'];

// Get the default view
try {
    $query = "SELECT `setting`, `hosttype`.`name` AS `value`
                FROM `{$database['DB_CONF']}`.`global`
                     INNER JOIN `{$database['DB_CONF']}`.`hosttype`
                     ON (`{$database['DB_CONF']}`.`global`.`value`=`{$database['DB_CONF']}`.`hosttype`.`id`)
               WHERE `setting` = 'default_view'";
    $default_viewquery = $db_link->prepare($query);
    $default_viewquery->execute();
    $default_viewresults = $default_viewquery->get_result();
    if ($default_viewresults->num_rows == 0) {
        throw new mysqli_sql_exception();
    }
    while ($global = $default_viewresults->fetch_assoc()) {
        $config['global'][$global['setting']] = $global['value'];
    }
} catch (Exception|Error $e) {
    die("Failed to get the default view" . err($e));
}

// Set mail variables for cron purposes
$mail_from = $config['global']['cron_mail_from'];
$mail_rcpt = $config['global']['cron_mail_rcpt'];

// Add some granularity to the debugging
if ($debug) {
    error_reporting(-1);
    ini_set('display_errors', 'On');
}

// Displayed fields
$log_fields = explode(',', $config['global']['log_fields']);
$log_levels = explode(',', $config['global']['log_levels']);

// Default category to start viewing
$default_view = $config['global']['default_view'];

// Ammount of lines we can show per page and the default to start off with
$showlines = explode(',', $config['global']['show_lines']);
$showlines_default = $config['global']['show_lines_default'];

// Page refresh options
$refresh = explode(',', $config['global']['refresh']);

// Lograte variables
$graph_height = $config['global']['lograte_graph_height'];
$graph_width = $config['global']['lograte_graph_width'];
$graph_timelimit = $config['global']['lograte_history_default'];
$graph_history = explode(',', $config['global']['lograte_history']);

// Change displayed fields and even order of fields
// Do mind this page has blank column after TIME column
$alert_fields = explode(',', $config['global']['netalert_fields']);

// Control ammount of history lines shown
$showlines_alert = $config['global']['netalert_show_lines'];

// Threshold in seconds after we normalize lines
$timethresh = $config['global']['netalert_time_threshold'];

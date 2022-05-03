<?php
// Netlog: A Syslog-NG to MySQL parser with no-nonsense frontend
// Project: https://github.com/barreljan/netlog

// Versioning etc
const VERSION = 'v3.0';
const NAME = 'Syslog-ng to MySQL parser';
const AUTHOR = 'bartjan@pc-mania.nl';

// Debug
//$debug = true;
$debug = false;

// Load database and other local settings
require('netlog.conf');
$database ?? die("Database settings not found! Please copy the netlog.conf.example to the 'etc' directory and fill in settings.\n");

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
function check_cli_sapi()
{
    if (php_sapi_name() !== 'cli') {
        die('Run me from the command line');
    }
}

/**
 * Converts an alphabetic string into an integer.
 * @param string $a A string
 * @return false|string
 */
function alpha2num(string $a)
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
 * @return false|resource|void
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
function unlock($semaphore)
{
    sem_release($semaphore);
}

/**
 * For debugging purposes, do not use in production environments!
 * This is displaying some interesting parts on the bottom of the 'result' div.
 * Can be used in conjunction with the config parameter 'debug'
 * @return void
 */
function codedebug()
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
 * Send an HTML email to the admin. This function is primarily used within the Logscavenger module
 * @param string $hostname
 * @param string $from
 * @param string $message
 * @return void
 */
function send_email(string $hostname, string $from, string $message)
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
 * Create and check database link
 * @return mysqli|void
 */
function connect_db()
{
    global $database;

    $db_link = @new mysqli($database['HOST'], $database['USER'], $database['PASS'], $database['DB']);
    if (mysqli_connect_errno()) {
        printf("Connect failed: %s\n", mysqli_connect_error());
        die;
    }
    if (!$db_link->select_db($database['DB'])) {
        printf("Unable to select DB: %s\n", mysqli_connect_error());
        die;
    }
    // All ok?
    return $db_link;
}

// Connect to database
$db_link = connect_db();

// Fifo socket
$log_fifo = "/var/log/syslog.fifo";

// Populate the global config settings
$config = array();
$query = "SELECT `setting`, `value`
            FROM `{$database['DB_CONF']}`.`global`";
$globalquery = $db_link->prepare($query);
$globalquery->execute();
$globalresults = $globalquery->get_result();
while ($global = $globalresults->fetch_assoc()) {
    $config['global'][$global['setting']] = $global['value'];
}

// Get the default view
$query = "SELECT `setting`, `hosttype`.`name` AS `value`
            FROM `{$database['DB_CONF']}`.`global`
           INNER JOIN `{$database['DB_CONF']}`.`hosttype`
                 ON (`{$database['DB_CONF']}`.`global`.`value`=`{$database['DB_CONF']}`.`hosttype`.`id`)
           WHERE `setting` = 'default_view'";
$default_viewquery = $db_link->prepare($query);
$default_viewquery->execute();
$default_viewresults = $default_viewquery->get_result();
while ($global = $default_viewresults->fetch_assoc()) {
    $config['global'][$global['setting']] = $global['value'];
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
$height = $config['global']['lograte_graph_height'];
$width = $config['global']['lograte_graph_width'];
$graphhistory = explode(',', $config['global']['lograte_history']);

// ###### Netalert variables ######

// Change displayed fields and even order of fields
// Do mind this page has blank space after TIME column
$alert_fields = explode(',', $config['global']['netalert_fields']);

// Control ammount of history lines shown
$showlines_alert = $config['global']['netalert_show_lines'];

// Threshold in seconds after we normalize lines
$timethresh = $config['global']['netalert_time_threshold'];

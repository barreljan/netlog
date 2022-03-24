<?php
// ###### NetLog ######

// Versioning etc
$VERSION = "v3.0";
$NAME = "Syslog-ng to MySQL parser";
$AUTHOR = "bartjan@pc-mania.nl";
$PROJECT = "https://github.com/barreljan/netlog";

// MySQL Database Information
$database = array();
$database['DB'] = "syslog";
$database['DB_CONF'] = "netlogconfig";
$database['USER'] = "netlog";
$database['PASS'] = "WonFaznu$(s#3nCi";
$database['HOST'] = "127.0.0.1";

function connect_db()
{
    /*
     * Create and check database link
     */
    global $database;

    $db_link = new mysqli($database['HOST'], $database['USER'], $database['PASS'], $database['DB']);
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

$db_link = connect_db();

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

// Mail
// Todo: figure out if this needs to be here
//$mail_from = $config['global']['mail_from'];
//$mail_rcpt = $config['global']['mail_rcpt'];

// Debug
$debug = True;
/** @noinspection PhpConditionAlreadyCheckedInspection */
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

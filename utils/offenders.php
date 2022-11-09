#!/bin/env php
<?php
/*
 * Get the top-n of offenders given a few criteria
 * Usefull for high volume logging of denies to find out which
 * source or destination hosts/ports/interface are responsible for
 * these.
 *
 * It could be that your php configuration limits the -d and -t
 * option due to memory contrains. Suggested memory_limit = 256M
 *
 * v1.0
 */

require(dirname(__DIR__) . "/etc/global.php");

/** Displays the help
 * @return void
 */
function show_help(): void
{
    global $argv;

    echo "$argv[0] [arguments] [optional argument(s)]\n
 required:
 -H host (IPv4 notation)
 -s part containing the ip string, where to group on
 -D delimiter of src/dst string, usually : or =
 -m messages to focus on containing -s (denies etc)

 optional:
 -d date {YYYY-MM-DD}, default is today
 -n number to present (top n), default is 10
 -t time in minutes to look back, default is 15

 -h, --help shows this help\n\n";
}

/** Parses, validates and set the options to work with
 * @return array
 */
function parse_opts(): array
{
    $shortopts = "H:";  // Required value
    $shortopts .= "s:"; // Required value
    $shortopts .= "m:"; // Required value
    $shortopts .= "D:"; // Required value
    $shortopts .= "d:"; // Optional value
    $shortopts .= "n:"; // Optional value
    $shortopts .= "t:"; // Optional value
    $shortopts .= "h:"; // Help

    $longopts = array(
        "help",
    );

    $cli_opts = getopt($shortopts, $longopts);

    // missing options
    if (sizeof($cli_opts) <= 3 || !isset($cli_opts['H']) || !isset($cli_opts['s']) || !isset($cli_opts['m']) || !isset($cli_opts['D'])) {
        show_help();
        die();
    }
    // empty values
    if ($cli_opts['H'] == "" || $cli_opts['s'] == "" || $cli_opts['m'] == "" || $cli_opts['D'] == "") {
        show_help();
        die();
    }

    $opts = array();

    // we now can more or less assume arguments are set:
    $opts['host'] = $cli_opts['H'];
    $opts['string'] = $cli_opts['s'];
    $opts['message'] = $cli_opts['m'];
    $opts['delimiter'] = $cli_opts['D'];

    // optional values in need of checks
    // date
    if (!isset($cli_opts['d'])) {
        $opts['date'] = date("Y-m-d");
    } else {
        $opts['date'] = $cli_opts['d'];
    }
    // top
    if (isset($cli_opts['n'])) {
        if (intval($cli_opts['n']) !== 0) {
            $opts['top'] = intval($cli_opts['n']);
        } else {
            $opts['top'] = 10;
        }
    } else {
        $opts['top'] = 10;
    }
    // time
    if (isset($cli_opts['t'])) {
        if (intval($cli_opts['t']) !== 0) {
            $opts['time'] = intval($cli_opts['t']);
        } else {
            $opts['time'] = 15;
        }
    } else {
        $opts['time'] = 15;
    }

    return $opts;
}

// start
$options = parse_opts();

// validate host and table
$date_us = str_replace('-', '_', $options['date']);
$host_us = str_replace('.', '_', $options['host']);
$tbl_name = "HST_" . $host_us . "_DATE_" . $date_us;
$message_param = "%" . $options['message'] . "%";

// get records
try {
    $query = "SELECT `MSG` FROM $tbl_name
               WHERE `MSG` LIKE ?
                 AND `TIME` > TIME(NOW()) - INTERVAL ? MINUTE
               ORDER BY id DESC";
    $linesquery = $db_link->prepare($query);
    $linesquery->bind_param('si', $message_param, $options['time']);
    $linesquery->execute();
    $linesresult = $linesquery->get_result();
} catch (Exception|Error $e) {
    die("No table found for " . $options['host'] . " at " . $options['date'] . "\n");
}

$offenders = array();

// process found lines
while ($row = $linesresult->fetch_assoc()) {
    // most devices log with spaces
    $fields = explode(" ", $row['MSG']);
    // try to find the field/part we need
    foreach ($fields as $field) {
        // try to find the desired field
        if (str_contains($field, $options['string'])) {
            $ip_address = explode($options['delimiter'], $field);
            if (sizeof($ip_address) == 2) {
                $ip = $ip_address[1];
                if (key_exists($ip, $offenders)) {
                    // already accounted for
                    $offenders[$ip] += 1;
                } else {
                    // new offender
                    $offenders[$ip] = 1;
                }
            }
        }
    }
}

// print out data, if there is any
if (sizeof($offenders) >= 1) {
    echo "\n" . str_pad("ip address", 30) . "hits\n\n";

    asort($offenders, SORT_NUMERIC);
    $top_offenders = array_slice($offenders, sizeof($offenders) - $options['top'], $options['top'], true);
    arsort($top_offenders);

    foreach ($top_offenders as $key => $value) {
        echo str_pad($key, 30) . $value . "\n";
    }

    echo "\n";
}

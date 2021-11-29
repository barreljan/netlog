<?php

// This script will work together with syslog-ng to parse logging information to a MySQL database
// We will read the logging from a fifo pipe in which syslog-ng with write the syslog info
// Standard syslog-ng - to - MySQL solutions would write all logging to one single table.
// This script will parse and divide logging into:
// In this setup logging is divided as per following rules:
// - A table per day per host for the first 14 days of logging
// - A table per host per month for the current and last months of logging
// - Anything beyond last month will be archived
// This way the most active tables will remain reasonable in size, thus queryable :P

// Including logparses variables
include("/usr/share/syslog-ng/etc/logparser.conf");

$DAY = "";
$HOST = "";

// Create and check database link

$db_link = mysqli_connect($db_HOST, $db_USER, $db_PASS, $db_NAME);
if (!$db_link) {
	die('Could not connect to MySQL server: ' . mysqli_error());
}

if (!mysqli_select_db($db_link, $db_NAME)) {
	die('Unable to select DB: ' . mysqli_error());
}

// Check if fifo socket exists

if ( file_exists($log_fifo)) {
	read_fifo();
} else {
	exec("mkfifo $log_fifo", $output, $ret);
	if ( $ret == "0" ) {
		read_fifo();
	} else {
		die("Unable to create fifo socket: $log_fifo");
	}
}

function read_fifo() {
	global $log_fifo;

	while($fifo = fopen("$log_fifo",'r') ) {
		$buffer = fgets($fifo);
		$logitems = explode(' _,_ ', $buffer);
		parse_log($logitems);
	}
}

function parse_log($logitems) {
	global $DAY;
	global $HOST;
	global $db_link;

	// Parse items on line
	$fields = '';
	$values = '';

	foreach($logitems as $linepart) {
		$item = explode('_:_',$linepart);
		if(!isset($item['1'])) { continue; }
		${$item['0']} = trim($item['1']);

		$fields .= $item['0'] . ", ";
		$values .= "'" . trim($item['1']) . "',";
	}

	if(isset($fields) && isset($values)) {
		$trimmedfields = trim($fields,', ');
		$trimmedvalues = trim($values,',');

		// Should hostname be empty, we will default to Unidentified Host Object table ;)
		if($HOST == '') {
			$HOST = 'UHO';
		}
		//echo "INSERT INTO $HOST ($trimmedfields) VALUES ($trimmedvalues)" . "\n";

		unset($fields, $values);

		// NetLog Scavenger to NetAlert for visability
		if (false != preg_match('/%LOGSCAVENGER%/',$trimmedvalues)) {
			 $HOST = "127.0.0.2";
		}

		// Create tablename
		$HOST_us = str_replace('.','_',$HOST);
		$DAY_us = str_replace('-','_',$DAY);
		$tablename = 'HST_' . $HOST_us . '_DATE_' . $DAY_us;

		$query = "INSERT INTO $tablename ($trimmedfields) VALUES ($trimmedvalues)";
		$result = mysqli_query($db_link, $query);

		if(!$result) {
			create_table($tablename);
			$query = "INSERT INTO $tablename ($trimmedfields) VALUES ($trimmedvalues)";
			$result = mysqli_query($db_link, $query);
		}
	}
}

function create_table($tablename) {
	global $db_link;

	$query = "CREATE TABLE IF NOT EXISTS $tablename LIKE template";
	$result = mysqli_query($db_link, $query);
	if(!$result) {
		die("Failed to create table $tablename");
	}
}

?>


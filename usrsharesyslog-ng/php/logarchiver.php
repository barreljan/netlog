<?php

// This script will be called on a daily bassis to aggergate logging
// In this setup logging is divided as per following rules:
// - A table per day per host for the first 14 days of logging
// - A table per host per month for the current and last months of logging
// - Anything beyond last month will be archived
// This way the most active tables will remain reasonable in size, thus queryable :P

// Including logparses variables

include("/usr/share/syslog-ng/etc/logparser.conf");

// Create and check database link

$db_link = mysqli_connect($db_HOST, $db_USER, $db_PASS, $db_NAME);
if (!$db_link) {
	die('Could not connect to MySQL server: ' . mysqli_error());
}

if (!mysqli_select_db($db_link, $db_NAME)) {
	die('Unable to select DB: ' . mysqli_error());
}


$today = date("Y_m_d");
$archinterval = date("Y_m_d",strtotime("-14 days"));

$query = "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='syslog' AND TABLE_NAME NOT IN ('template') AND CREATE_TIME <= '$archinterval' ORDER BY CREATE_TIME";

$result = mysqli_query($db_link, $query);

while ( $tables = mysqli_fetch_assoc($result)) {
	$table_name = $tables['TABLE_NAME'];
	$table = explode('_DATE_',$table_name);
  if(!isset($table['1'])) { continue; }

	if ( preg_match('/\d{4}_\d{2}_\d{2}/',$table['1'])) {
	$host = $table['0'];

	$daysplitup = explode('_',$table['1']);
	$year = $daysplitup['0'];
	$month = $daysplitup['1'];
	$monthname = date('M', mktime(0,0,0, $month));

	$dsttable = $host . "_DATE_" . $year . "_" . $monthname;

	$dstquery = "CREATE TABLE IF NOT EXISTS $dsttable LIKE template";
	$dstresult = mysqli_query($db_link, $dstquery);
	unset($dstquery, $dstresult);

	$archquery = "INSERT INTO $dsttable (HOST, FAC, PRIO, LVL, TAG, DAY, TIME, PROG, MSG) SELECT HOST, FAC, PRIO, LVL, TAG, DAY, TIME, PROG, MSG FROM $table_name";
	$archresult = mysqli_query($db_link, $archquery);
	unset($archquery, $archresult);

	$dropquery = "DROP TABLE $table_name";
	$dropresult = mysqli_query($db_link, $dropquery);
	unset($dropquery, $dropresult);
	}

}
?>

<?php

// This script will prune all logging tables that exist in netlog archive server, leaving those that are not in sync
// last month will be excluded by default

// Including logparses variables

$arguments = getopt("l");
$includelastmonth = "no";
if (isset($arguments['l'])) {
	$includelastmonth = "yes";
} else {
	$includelastmonth = "no";
}

include("/usr/share/syslog-ng/etc/logparser.conf");

global $mailmsg, $mailerr, $mailcrit, $tablecount, $failcount, $successcount;
$archstarttime = date("d.m.y G:i:s");
$tablecount = 0;
$failcount = 0;
$successcount = 0;


// Create and check database link to check which tables we already have
function dblogconnect()
{
global $dblog_link, $db_HOST, $db_USER, $db_PASS, $db_NAME;

if(is_null($dblog_link)) {
	$dblog_link = mysqli_connect($db_HOST, $db_USER, $db_PASS, $db_NAME);
	if (!$dblog_link) {
		die('Could not connect to Netlog server: ' . mysql_error());
	}
} elseif(!mysqli_ping($dblog_link)) {
	mysqli_close($dblog_link);

	$dblog_link = mysqli_connect($db_HOST, $db_USER, $db_PASS, $db_NAME);
	if (!$dblog_link) {
		die('Could not connect to Netlog server: ' . mysql_error());
	}
}

if (!mysqli_select_db($dblog_link, $db_NAME)) {
	die('Unable to select Netlog DB: ' . mysqli_error($dblog_link));
}
}

function dbarchconnect()
{
global $dbarch_link, $db_archHOST, $db_USER, $db_PASS, $db_archNAME;

if(is_null($dbarch_link)) {
	$dbarch_link = mysqli_connect($db_archHOST, $db_USER, $db_PASS, $db_archNAME);
	if (!$dbarch_link) {
		die('Could not connect to Archive server: ' . mysqli_error($dbarch_link));
	}
} elseif(!mysqli_ping($dbarch_link)) {
	mysqli_close($dbarch_link);

	$dbarch_link = mysqli_connect($db_archHOST, $db_USER, $db_PASS, $db_archNAME);
	if (!$dbarch_link) {
		die('Could not connect to Archive server: ' . mysql_error($dbarch_link));
	}
}
if (!mysqli_select_db($dbarch_link, $db_archNAME)) {
	die('Unable to connect to Archive DB: ' . mysqli_error($dbarch_link));
}

}


dbarchconnect();

$archquery = "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.tables WHERE TABLE_SCHEMA='syslogarchive' AND TABLE_NAME NOT IN ('template')";
$archresult = mysqli_query($dbarch_link,$archquery);
while ( $archtables = mysqli_fetch_assoc($archresult)) {
	$archtablenames[] = $archtables['TABLE_NAME'];
	$archtable = $archtables['TABLE_NAME'];
	$archtablerows["$archtable"] = $archtables['TABLE_ROWS'];
}

// Look for elligible tables to synch
$curmonth = date("Y_M");
$lastmonth = date("Y_M", mktime(0,0,0,date("m")-1, 1,date("Y")));

dblogconnect();

$query = "SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.tables WHERE TABLE_SCHEMA='syslog' AND TABLE_NAME REGEXP 'HST_.*_DATE_.*_[a-z]{3}$' AND TABLE_NAME NOT LIKE 'HST_%_DATE_$curmonth'";
if ( $includelastmonth != "yes" ) {
	$query .= " AND TABLE_NAME NOT LIKE 'HST_%_DATE_$lastmonth'";
}

$result = mysqli_query($dblog_link, $query);

if( mysqli_num_rows($archresult) > 0 ) {
	while ( $tables = mysqli_fetch_assoc($result)) {
	$tablecount += 1;
	if ( in_array($tables['TABLE_NAME'] , $archtablenames )) {
		// Table exists in archive, check rows
		$archtable = $tables['TABLE_NAME'];
		if ( $tables['TABLE_ROWS'] == $archtablerows["$archtable"] ) {
			echo date("d.m.y G:i:s : ") . "Table $archtable present in archive, row count matches:\r\n";
			echo date("d.m.y G:i:s : ") . "Netlog: " . $tables['TABLE_ROWS'] . " , Archive: " . $archtablerows["$archtable"] . "\r\n";
			$mailmsg .= date("d.m.y G:i:s : ") . "Table $archtable present in archive, row count matches:\r\n";
			$mailmsg .= date("d.m.y G:i:s : ") . "Netlog: " . $tables['TABLE_ROWS'] . " , Archive: " . $archtablerows["$archtable"] . "\r\n";

			$dropquery = "DROP TABLE $archtable";
			$dropresult = mysqli_query($dblog_link, $dropquery);

			echo date("d.m.y G:i:s : ") . "Netlog table dropped! \r\n";
			$mailmsg .= date("d.m.y G:i:s : ") . "Netlog table dropped! \r\n";
			$successcount += 1;
		} else {
			echo "Table $archtable found in archive, NO row count match!:\r\n";
			echo "Netlog has " . $tables['TABLE_ROWS'] . "\r\n";
			echo "Resync Needed!!! \r\n";
			$mailerr .= date("d.m.y G:i:s : ") . "Table $archtable found in archive, NO row count match!:\r\n";
			$mailerr .= date("d.m.y G:i:s : ") . "Netlog: " . $tables['TABLE_ROWS'] . " , Archive: " . $archtablerows["$archtable"] . "\r\n";
			$mailerr .= date("d.m.y G:i:s : ") . "Manually drop Archive table and resync!!! \r\n";
			$failcount += 1;
		}
	} else {
		echo "Table $archtable was NOT archived!!!!\r\n";
		echo "Sync table to archive server first!!!\r\n";
		$mailcrit .= date("d.m.y G:i:s : ") . "Table $archtable NOT found in archive!!! \r\n";
		$mailcrit .= date("d.m.y G:i:s : ") . "Sync table to archive first!!! \r\n";
		$failcount += 1;
	}
	}
	mail_status($mailmsg, $mailerr, $mailcrit);
} else {
	echo date("d.m.y G:i:s : ") . "No tables to drop, archive is empty!";
}
mysqli_free_result($archresult);
mysqli_free_result($result);


function mail_status($mailmsg, $mailerr, $mailcrit)
{
global $message, $tablecount, $failcount, $successcount;

$subject = 'Logarchive status';
$from = $mail_from;

$message .= "Total tables evaluated: $tablecount \r\n";
$message .= "Tables dropped: $successcount \r\n";
$message .= "Tables failed: $failcount \r\n";
$message .= "\r\n ========== \r\n";
$message .= $mailcrit;
$message .= $mailerr;
$message .= $mailmsg;

$headers = array();
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-type: text/plain; charset=iso-8859-1";
$headers[] = "From: Netlog server <{$from}>";
$headers[] = "Reply-To: No-Reply <{$from}>";
$headers[] = "X-Mailer: PHP/".phpversion();
mail($mail_rcpt,$subject,$message,implode("\r\n", $headers),"-f {$from}");

/*
// old style
$to = $mail_rcpt;
$headers = 'From: ' . $mail_from;
$subject = 'Logarchive status';

$message .= "Total tables evaluated: $tablecount \r\n";
$message .= "Tables dropped: $successcount \r\n";
$message .= "Tables failed: $failcount \r\n";
$message .= "\r\n ========== \r\n";

$message .= $mailcrit;
$message .= $mailerr;
$message .= $mailmsg;

mail($to, $subject, $message, $headers);
*/
}
?>


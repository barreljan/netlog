<?php
// This script will work together with syslog-ng to MySQL setup to determine rates of logging
// for specific hosts in the system
// Hopefully this will be a quick means of identifying trouble spots


// Including logparses variables
include("/usr/share/syslog-ng/etc/logparser.conf");

// Using lock file to make sure we run once
define( 'LOCK_FILE', "/var/run/".basename( $argv[0], ".php").".lock");
if ( isLocked() ) die( "Duplicate process started.\n");

// Create and check database link

$db_link = mysqli_connect($db_HOST, $db_USER, $db_PASS);
if (!$db_link) {
	die('Could not connect to MySQL server: ' . mysql_error());
}

if (!mysqli_select_db($db_link,$db_NAME)) {
	die('Unable to select DB: ' . mysql_error());
}

// Determine today's date in the table name format
$today = date('Y_m_d');

// Create list of hosts we should watch

$hquery = "SELECT id, hostip FROM netlogconfig.lograteconf LEFT JOIN netlogconfig.hostnames ON (netlogconfig.lograteconf.hostnameid=netlogconfig.hostnames.id) WHERE netlogconfig.lograteconf.samplerate='1'";
$hostresult = mysqli_query($db_link,$hquery);

// Loop through the hosts and run the counts

while ( $host = mysqli_fetch_assoc($hostresult) ) {
	// Assemble table name
	$convertedhost = str_replace('.','_',$host['hostip']);
	$tablename = "HST_" . $convertedhost . "_DATE_" . $today;
	// print "TBL: $tablename\n";
	// Select the 1,5 and 10 min rate and insert into lograte table
	$ratequery = "SELECT count(*) as 1min, (SELECT count(*) FROM $tablename WHERE TIME > SUBTIME(CURTIME(), '00:05:00')) as 5min, (SELECT count(*) FROM $tablename WHERE TIME > SUBTIME(CURTIME(), '00:10:00')) as 10min FROM $tablename WHERE TIME > SUBTIME(CURTIME(), '00:01:00')";
	//  print "Q: $ratequery\n";
	$rateresult = mysqli_query($db_link,$ratequery);
	while ( $rates = @mysqli_fetch_assoc($rateresult)) {
		$logratequery = "INSERT INTO netlogconfig.lograte (hostnameid, 1min, 5min, 10min) VALUES ('" . $host['id'] . "','" . $rates['1min'] . "','" . $rates['5min'] . "','" . $rates['10min'] . "')";
		$lograteresult = mysqli_query($db_link,$logratequery);
	//  echo "Res: "; var_dump($rates); echo "\n";
	}
}

function isLocked() {
	if( file_exists( LOCK_FILE ) ) {
		$lockingPID = trim( file_get_contents ( LOCK_FILE ) );
		$pids = explode( "\n", trim( `ps -C php | awk '{print $1}'` ) );
		if( in_array( $lockingPID, $pids ) ) return true;
		   echo "Stale lock file found, removing...\n";
		unlink( LOCK_FILE );
	}
	file_put_contents( LOCK_FILE, getmypid() . "\n" );
	return false;
}
// Cleanup
mysqli_close($db_link);
unlink( LOCK_FILE );
?>

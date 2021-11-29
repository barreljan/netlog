<?php
// NetLog scavenger v0.93, 25 March 2016 (for background task)
// For continuous searching of specific events
// Created, Jan 2016 | bartjan@pc-mania.nl
include '/var/www/html/config/config.php';

$conn = mysqli_connect($db_HOST,$db_USER,$db_PASS,$db_NAME);
if (!$conn) {
        die('Could not connect to MySQL server: ' . mysqli_error($conn));
}

// Clean cache
$querycleancache = "DELETE FROM `{$db_NAMECONF}`.`logcache` WHERE `timestamp` < DATE_ADD(NOW(), INTERVAL - ".($history*2)." SECOND)";
$cleancacheresult = mysqli_query($conn,$querycleancache) or die ("Error: ".mysqli_error($conn)."\nwith query {$querycleancache}\n");

// First, get all todays active hosts
$queryhosts = "SELECT TABLE_NAME as `hosts` FROM `information_schema`.`COLUMNS` WHERE COLUMN_NAME = 'MSG' AND TABLE_NAME RLIKE 'HST_[0-9].*{$DAY_us}' AND TABLE_NAME NOT RLIKE 'HST_127_0_0_1.*'";
$hstresult = mysqli_query($conn,$queryhosts) or die ("Error: ".mysqli_error($conn)."\nwith query {$queryhosts}\n");
$hstrows = mysqli_num_rows($hstresult);

// Then, get keywords and assemble the query
$querykeywords = "SELECT `keyword` FROM `{$db_NAMECONF}`.`logscavenger` WHERE `active` = 1";
$kwresult = mysqli_query($conn,$querykeywords) or die ("Error: ".mysqli_error($conn)."\nwith query {$querykeywords}\n");
$kwrows = mysqli_num_rows($kwresult);

$i=0; $querykw1 = "";
while ($i <$kwrows) {
	$keywords = mysqli_fetch_assoc($kwresult);
	$querykw1 .= "`MSG` LIKE '%{$keywords['keyword']}%'";
	if ($kwrows > 1 && $i != ($kwrows - 1)) {
		$querykw1 .= " OR ";
	}
	$i++;
}

// Finally, loop through all host tables and send a message out if there is a new hit
$l = 0;
$legerows = 0;
openlog('%LOGSCAVENGER%', LOG_PID, LOG_USER);
while ($l <$hstrows) {
	$hostsarr = mysqli_fetch_assoc($hstresult);
	$hosts = $hostsarr['hosts'];

	$querymsgs = "SELECT `MSG` FROM `{$db_NAME}`.`{$hosts}` WHERE TIME>='{$tijd}' AND ({$querykw1}) ORDER BY TIME DESC";
	$msgsresult = mysqli_query($conn,$querymsgs) or die ("Error: ".mysqli_error($conn)."\nwith query {$querymsgs}\n");
	$msgsrows = mysqli_num_rows($msgsresult);
	if ($msgsrows == 0) {
		$legerows++;
	} else {
		// Tablename to real IP address
		preg_match('/HST_([0-9]{1,3})_([0-9]{1,3})_([0-9]{1,3})_([0-9]{1,3})_/', $hosts, $matches);
		$hostip = sprintf("%d.%d.%d.%d",$matches[1],$matches[2],$matches[3],$matches[4]);

		// Get the user-submitted hostname
		$queryhstnm = "SELECT `hostname` FROM `{$db_NAMECONF}`.`hostnames` WHERE `hostip` = \"{$hostip}\"";
		$hstnmresult = mysqli_query($conn,$queryhstnm) or die ("Error: ".mysqli_error($conn)."\nwith query {$queryhstnm}\n");
		$row_result = mysqli_fetch_assoc($hstnmresult);
		$realhostname = $row_result['hostname'];
	
		if(isset($realhostname) && $realhostname != '') {
		} else {
			$realhostname	= $hostip;
		}

		// Reading the cache
		$querylogcache = "SELECT `msg` FROM `{$db_NAMECONF}`.`logcache` WHERE `host` = \"{$hostip}\"";
		$cacheresult = mysqli_query($conn,$querylogcache) or die ("Error: ".mysqli_error($conn)."\nwith query {$querylogcache}\n");
		$cachenumrows = mysqli_num_rows($cacheresult);
		$host_cache_arr = array();
		if (isset($cachenumrows) && ($cachenumrows > 0)) {
			while ($cacherow = mysqli_fetch_array($cacheresult)) {
				array_push ($host_cache_arr, $cacherow['msg']);
			}
		}
		// Loop through the found rows of the current host
		while($row = mysqli_fetch_array($msgsresult)) {

			// Evil thingy to skip certain words/ bogus filter
			if (
				strpos($row['MSG'], "someting_i_do_not_want") !== false ||
				(strpos($row['MSG'], "something_else") !== false && strpos($row['MSG'], "in combination with") !== false)
				) {
				continue;
			}
			// Search and compare with cache
			if (in_array($row['MSG'], $host_cache_arr, true)) {
				// skip
			} else {
				// Fill the cache with new entries
				$logcacheqry = "INSERT INTO `{$db_NAMECONF}`.`logcache` (host,msg) VALUES(\"{$hostip}\",\"".mysqli_real_escape_string($conn,$row['MSG'])."\")";
				$logcacheresult = mysqli_query($conn,$logcacheqry) or die ("Error: ".mysqli_error($conn)."\nwith query {$logcacheqry}\n");

				syslog(LOG_WARNING,  "{$realhostname}: {$row['MSG']}");

				if ((strpos($realhostname, "coresw01") !== false) && (strpos($row['MSG'], "PSECURE_VIOLATION") !== false)) {
					$subject = "Network port violation on {$realhostname}";
					$msg = "There is a port violation detected on the network\n\n";
					$msg .= "{$realhostname}:\n {$row['MSG']}";
					$msg .= "\n\n\nTake actions asap!";
					$headers = array();
					$headers[] = "MIME-Version: 1.0";
					$headers[] = "Content-type: text/plain; charset=iso-8859-1";
					$headers[] = "From: Netlog server <{$from}>";
					$headers[] = "Reply-To: No-Reply <{$from}>";
					$headers[] = "X-Mailer: PHP/".phpversion();
					mail($mail_rcpt,$subject,$msg,implode("\r\n", $headers),"-f {$mail_from}");
				}
			}
		}
	}
	$l++;
}
if (isset($cacheresult) && $cacheresult != null) {
	mysqli_free_result($cacheresult);
}
if (isset($hstnmresult) && $hstnmresult != null) {
	mysqli_free_result($hstnmresult);
}
if (isset($msgsresult) && $msgsresult != null) {
	mysqli_free_result($msgsresult);
}
if (isset($hstresult) && $hstresult != null) {
	mysqli_free_result($hstresult);
}
if (isset($kwresult) && $kwresult != null) {
	mysqli_free_result($kwresult);
}

closelog();
mysqli_close($conn);
?>

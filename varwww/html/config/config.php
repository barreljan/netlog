<?php
// ###### NetLog ######

// Versioning

	$VERSION = "v2";
	$NAME = "Syslog-ng to MySQL parser";

// Mysql DB Information

	$db_NAME = "syslog";
	$db_NAMECONF = "netlogconfig";
	$db_USER = "syslog";
	$db_PASS = "WonFaznu$(s#3nCi";
	$db_HOST = "127.0.0.1";


// Mail settings
	$mail_from = "no-reply@domain.tld";
        $mail_rcpt = "john_doe@domain.tld";

// Displayed fields
	$log_fields = "HOST, FAC, PRIO, LVL, TAG, DAY, TIME, PROG, MSG";
	$log_levels = array('debug','info','notice','warning','err','crit','alert','emergency','panic');

// Default category to start viewing
	$default_view = "Firewall";

// Ammount of lines we can show per page and the default to start off with
	$showlines = array('50', '100','250','500','1000');
	$showlines_default = "50";
// Page refresh options
	$refresh = array('off', 1, 2, 5, 10);


// Lograte variables
	$height = 275;
	$width = 750;
	$graphhistory = array('30', '60', '120', '240','480','1440','2880','4320','10080');

// ###### Net alert variables ######

// Change displayed fields and even order of fields
// Do mind this page has blank space after TIME column
        $alert_fields = "DAY, TIME, LVL, MSG, PROG";
// Control ammount of history lines shown
        $showlines_alert = "20";
// Threshold in seconds after we grey out lines
        $timethresh = "3600";


// ###### NetLog Scavenger ######

	// Set the searching in seconds
		$history=300;
	// others, do not touch
		$datum=(time() - $history);
		$maand=date('Y-m-d',$datum);
		$tijd=date('H:i:s',$datum);
		$realtime=date('H:i:s');
		$DAY_us = str_replace('-','_',$maand);
		$GLOBALS['scavver'] = "v0.93, 25 March 2016";

	// Scav Config
		$GLOBALS['scavconfver'] = "v0.8, 02 April 2016";

?>

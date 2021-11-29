<?php
// NetLog scavenger email 
// For continuous searching of specific events
// Created, April 2016 | bartjan@pc-mania.nl
include 'config/config.php';

$conn = mysqli_connect($db_HOST,$db_USER,$db_PASS) or die ("Unable to connect to the database");

// Get the email groups
$queryemailgroups = "SELECT * FROM `{$db_NAMECONF}`.`emailgroups` WHERE `active` = '1' ORDER BY `id`";
$emailgrpresult = mysqli_query($conn,$queryemailgroups) or die(mysql_error($conn));

// Reading the cache
$querylogcache = "SELECT `msg` FROM `{$db_NAMECONF}`.`logcache`";
$cacheresult = mysqli_query($conn,$querylogcache) or die ("Error: ".mysqli_error($conn)."\nwith query {$querylogcache}\n");
$cachenumrows = mysqli_num_rows($cacheresult);
$host_cache_arr = array();
if (isset($cachenumrows) && ($cachenumrows > 0)) {
	while ($cacherow = mysqli_fetch_array($cacheresult)) {
		array_push ($host_cache_arr, $cacherow['msg']);
	}
}
var_dump($host_cache_arr);

$emailgrps = array();
$emailgrps[] = "Groupname"; 
while ($emailgrparr = mysqli_fetch_assoc($emailgrpresult)){
	//	doe iets
}
var_dump($emailgrps);


mysqli_free_result($cacheresult);
mysqli_free_result($emailgrpresult);
mysqli_close($conn);
?>

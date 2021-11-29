<?php
// httpOnly cookie, old style for php < 5.2
header("Set-Cookie: PHPSESSID=value; httpOnly");

// Include the basics
include("./config/config.php");

session_start();

if (isset($_GET['action'])) {
		if ($_GET['action'] == 'logout') {
				// Logout
				echo "<!DOCTYPE html>\n<html>\n <head>\n";
				echo "	<title>Netlog Login</title>\n	<link rel=\"stylesheet\" type=\"text/css\" media=\"all\" href=\"./css/login.css\" />";
				echo "\n	<!-- php ldap_auth, bartjan@pc-mania.nl -->\n </head>\n <body>\n";
				session_destroy();
				echo "<br/>User logged out...";
				echo "\n </div>\n </body>\n</html>";
				header("Refresh: 2, ./index.php");
				exit();
		}
}

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
		<meta http-equiv="content-type" content="text/html; charset=iso-8859-1">
		<link rel="stylesheet" type="text/css" href="css/netlog.css">
		<script type="text/javascript" src="scripts/netlog.js"></script>
<?php

// Create and check database link
$today = date('Y_m_d');

$db_link = mysqli_connect($db_HOST, $db_USER, $db_PASS, $db_NAME);
if (!$db_link) {
		die('Could not connect to MySQL server: ' . mysqli_error());
}

if (!mysqli_select_db($db_link, $db_NAME)) {
		die('Unable to select DB: ' . mysqli_error());
}

/*
if( isset($_POST['type'])) {
	echo "POST:<br>";
	foreach( $_POST as $key => $value ) {
		echo "$key => $value<br>";
	}
}
echo "SESSION:<br>";
foreach($_SESSION as $key => $value ) {
	echo "$key => $value<br>";
}
*/

if ( isset($_POST['type']) ) {
	// Compare type
	if (isset($_SESSION['type'])) {
		if ( $_SESSION['type'] != $_POST['type'] ) {
			$_SESSION['type'] = $_POST['type'];
			unset($_SESSION['showip'],$_SESSION['day'],$_SESSION['search'],$_SESSION['refresh'], $_SESSION['showpage'], $_SESSION['filter_LVL']);
			$_SESSION['clearsearch'] = "clear";
		}
	} else {
		$_SESSION['type'] = "All";
	}

	// Compare ip
	if (isset($_SESSION['showip'])) {
		if ( $_SESSION['showip'] != $_POST['showip'] ) {
			$_SESSION['showip'] = $_POST['showip'];
			unset($_SESSION['day'],$_SESSION['search'],$_SESSION['refresh'], $_SESSION['showpage'], $_SESSION['filter_LVL']);
			$_SESSION['clearsearch'] = "clear";
		}
	}

	// Compare date
	if (isset($_SESSION['day'])) {
		if ( $_SESSION['day'] != $_POST['day'] ) {
			$_SESSION['day'] = $_POST['day'];
			unset($_SESSION['refresh'], $_SESSION['showpage']);
		} 
	}

	// Process showlines before cleansearch cause we want to reset lines to default on cleansearch
	if (isset($_POST['showlines'])) {
		if ( $_SESSION['showlines'] != $_POST['showlines'] ) {
			$_SESSION['showlines'] = $_POST['showlines'];
			unset($_SESSION['showpage']);
		}
	}

	if (isset($_SESSION['filter_LVL'])) {
		if( (isset($_SESSION['filter_LVL'])) && ($_SESSION['filter_LVL'] != $_POST['filter_LVL'])) {
			$_SESSION['filter_LVL'] = $_POST['filter_LVL'];
		} else {
			$_SESSION['filter_LVL'] = $_POST['filter_LVL'];
		}
	}

	// Compare search
	if ( (isset($_POST['clearsearch'])) || (isset($_SESSION['clearsearch'])) ) {
		unset($_SESSION['search'], $_SESSION['clearsearch'], $_SESSION['showpage']);
		$_SESSION['showlines'] = $showlines_default;
	} else {
		if (isset($_SESSION['search'])) {
			if ( $_SESSION['search'] != $_POST['search'] ) {
				$_SESSION['search'] = $_POST['search'];
				unset($_SESSION['refresh'], $_SESSION['showpage']);
			}
		} elseif ( $_POST['search'] != "" ) {
			$_SESSION['search'] = $_POST['search'];
			unset($_SESSION['showpage']);
		}
	}

	// Compare refresh
	if (isset($_POST['stoprefresh'])) {
		$_SESSION['refresh'] = "off";
	} else {
		if (isset($_SESSION['refresh']) && isset($_POST['refresh'])) {
			if ( $_SESSION['refresh'] != $_POST['refresh'] ) {
				$_SESSION['refresh'] = $_POST['refresh'];
			}
		} else {
			$_SESSION['refresh'] = "off";
		}
	}

	// Default back to page 1 after changes
	// Detect page shift
	if (isset($_SESSION['showpage'])) {
		if ( (isset($_POST['jumptopage'])) && ($_POST['jumptopage'] != "") && is_numeric($_POST['jumptopage'])) {
			//echo "SESSION showpage: " . $_SESSION['showpage'] . " POST jumptopage " . $_POST['jumptopage'] . "<br>";
			if ( $_SESSION['showpage'] != $_POST['jumptopage'] ) {
				if ( ( $_POST['jumptopage'] > 0 ) && ( $_POST['jumptopage'] <= $_SESSION['pagecount'] )) {
					$_SESSION['showpage'] = $_POST['jumptopage'];
				}
			} else {
				foreach ( $_POST as $buttonname => $buttonvalue ) {
					if ( preg_match('/showpage/', $buttonname )) {
						$bvalue = explode('_', $buttonname);
						switch ($bvalue['1']) {
							case "first":
								$_SESSION['showpage'] = "1";
								break;
							case "last":
								$_SESSION['showpage'] = $_SESSION['pagecount'];
								break;
							case "b25":
								$_SESSION['showpage'] = $_SESSION['showpage'] - 25;
								break;
							case "b10":
								$_SESSION['showpage'] = $_SESSION['showpage'] - 10;
								break;
							case "b1":
								$_SESSION['showpage'] = $_SESSION['showpage'] - 1;
								break;
							case "f1":
								$_SESSION['showpage'] = $_SESSION['showpage'] + 1;
								break;
							case "f10":
								$_SESSION['showpage'] = $_SESSION['showpage'] + 10;
								break;
							case "f25":
								$_SESSION['showpage'] = $_SESSION['showpage'] + 25;
								break;
							default:
							break;
						}	
		 			}
				}
			}
		} elseif( (preg_match('/[0-9][0-9]:[0-9][0-9]/',$_POST['jumptopage']))	|| (preg_match('/[0-9]{4}-[0-9]{2}-[0-9{2}] [0-9]{2}:[0-9]{2}/',$_POST['jumptopage'])) ) {
			$_SESSION['showpage'] = $_POST['jumptopage'];
		}
	}
}

if (!isset($_SESSION['showlines'])) {
	$_SESSION['showlines'] = $showlines_default;
}

if (!isset($_SESSION['showpage'])) {
	$_SESSION['showpage'] = 1;
}

if (!isset($_SESSION['filter_LVL'])) {
	$_SESSION['filter_LVL'] = "none";
}
if ( (isset($_SESSION['filter_LVL'])) && ($_SESSION['filter_LVL'] != "none")) {
	switch ( $_SESSION['filter_LVL'] ) {
		case "debug":
			$lvl_filter = "'debug'";
			break;
		case "info":
			$lvl_filter = "'info', 'notice', 'warning', 'err', 'crit', 'alert', 'emergency', 'panic'";
			break;
		case "notice":
			$lvl_filter = "'notice', 'warning', 'err', 'crit', 'alert', 'emergency', 'panic'";
			break;
		case "warning":
			$lvl_filter = "'warning', 'err', 'crit', 'alert', 'emergency', 'panic'";
			break;
		case "err":
			$lvl_filter = "'err', 'crit', 'alert', 'emergency', 'panic'";
			break;
		case "crit":
			$lvl_filter = "'crit', 'alert', 'emergency', 'panic'";
			break;
		case "alert":
			$lvl_filter = "'alert', 'emergency', 'panic'";
			break;
		case "emergency":
			$lvl_filter = "'emergency', 'panic'";
			break;
		case "panic":
			$lvl_filter = "'panic'";
			break;
		default:
			$lvl_filter = "'notice', 'warning', 'err', 'crit', 'alert', 'emergency', 'panic'";
	}
}

if ((isset($_SESSION['refresh'])) && ($_SESSION['refresh'] != "off")) {
	echo "\t\t<meta http-equiv=\"refresh\" content=\"{$_SESSION['refresh']}\">";
}

if (isset($_SESSION['type'])){
	if (($_SESSION['type'] == "All") || ($_SESSION['type'] == "Unnamed")) {
		$hosttypeselect = "%";
	} else {
		$hosttypeselect = $_SESSION['type'];
	}
} else {
	$_SESSION['type'] = $default_view;
	$hosttypeselect = $default_view;
}

echo "</head>\n<body>\n";

$hostnamequery = "SELECT hostip, hostname, hosttype FROM netlogconfig.hostnames LEFT JOIN netlogconfig.hosttype ON (netlogconfig.hostnames.hosttype=netlogconfig.hosttype.id) WHERE name like '$hosttypeselect'";
$hostnameresult = mysqli_query($db_link,$hostnamequery) or die (mysqli_error($db_link));
while ( $dbhostnames = mysqli_fetch_assoc($hostnameresult)) {
	$hostnameip = $dbhostnames['hostip'];
	$hostname["$hostnameip"] = $dbhostnames['hostname'];
}
mysqli_free_result($hostnameresult);

//$query = "SELECT table_name FROM information_schema.tables WHERE table_schema='$db_NAME' AND table_name NOT IN ('template') ORDER BY CREATE_TIME DESC";
$query = "SHOW TABLES IN `{$db_NAME}`";
$result = mysqli_query($db_link,$query);

// Throw all ip parts of tables in an array
//while( $lines = mysqli_fetch_assoc($result)) {
while ($lines = mysqli_fetch_array($result)) {
	//$thishost = explode('_DATE_', $lines['table_name']);
	$thishost = explode('_DATE_', $lines['0']);
	$host = trim($thishost['0'],'HST_');
	$ip = str_replace('_','.',$host);

	if(!isset($thishost['1'])) {
		continue;
	}
	if ( preg_match('/\d{4}_\d{2}_\d{2}/', $thishost['1'])) {
		$hostdaylist["$ip"][] = $thishost['1'];
	} else {
		$hostmonthlist["$ip"][] = $thishost['1'];
	}
	if ( $_SESSION['type'] == "All" ) {
		$iplist[] = $ip;
	} elseif( $_SESSION['type'] == "Unnamed" ) {
		$addtolist = "add";
		foreach ( $hostname as $hostselectip => $hostselectname ) {
			if ( $hostselectip == $ip ) {
				$addtolist = "skip";
			}
		}
		if ( $addtolist == "add" ) {
			$iplist[] = $ip;
		}
	} else {
		foreach ( $hostname as $hostselectip => $hostselectname ) {
			if ( $hostselectip == $ip ) {
				$iplist[] = $ip;
			}
		}
	}
}
mysqli_free_result($result);

// deduplicate array
$iplist = array_unique($iplist);
sort($iplist);

if (isset($_SESSION['search'])) {
	$searchstring = '%' . $_SESSION['search'] . '%';
} else {
	$searchstring = '%';
}

echo "<form name=\"settings\" method=\"post\" action=\"index.php\">\n";
echo "<table class=\"outline\" width=\"100%\">\n";
echo "<tr>\n<td>\n";
echo "\t<table class=\"none\" width=\"100%\">\n\t<tr>\n";
echo "\t<td width=\"60%\"><b>Netlog ".date('Y-m-d H:i:s')."</b></td><td align=\"center\">\n\t<span title=\"You can either enter:&#10;&#13;A page number&#10;A specific time (e.g. 14:00)&#10;Or date and time (e.g. 2011-01-01 14:00)&#10;&#13;\">Select Page:</span></td><td align=\"right\"><a href=\"scavconfig.php\">scavenger</a> | <a href=\"viewlograte.php\">lograte</a> | <a href=\"settings.php\">config</a> | <a href=\"index.php?action=logout\">logout</a></td>\n";
echo "\t</tr>\n\t<tr>\n\t<td>\n\t<table class=\"none\" width=\"100%\">\n\t<tr>\n\t<td>Device Type:\n";

$typequery = "SELECT id, name FROM netlogconfig.hosttype ORDER BY name";
$typeresult = mysqli_query($db_link,$typequery);
echo "\t<select name=\"type\" onChange=\"this.form.submit()\">\n\t";

while ( $types = mysqli_fetch_assoc($typeresult)) {
	echo "<option value=\"".$types['name']."\"";
	if ( $_SESSION['type'] == $types['name'] ) {
		echo " SELECTED";
	}
	echo ">".$types['name']."</option>\n";
}
mysqli_free_result($typeresult);

echo "\t</select>\n\tHost:\n\t<select name=\"showip\" onChange=\"this.form.submit()\">\n";
foreach( $iplist as $ip ) {
	if(!isset($_SESSION['showip'])) {
		$_SESSION['showip'] = $ip;
	}
	echo "\t\t<option value=\"".$ip."\"";
	if ( $_SESSION['showip'] == $ip ) { 
		echo " SELECTED";
	} 
	echo ">";
	if ( isset($hostname["$ip"])) {
		echo $hostname["$ip"];
	} else {
		echo $ip;
	}
	echo "</option>\n";
}

echo "\t</select>\n\tDay:\n\t<select name=\"day\" onChange=\"this.form.submit()\">\n";

function date_compare($a, $b) {
	$t1 = strtotime(str_replace('_','-',$b));
	$t2 = strtotime(str_replace('_','-',$a));
	return $t1 - $t2;
}

if (isset($hostdaylist[$_SESSION['showip']])) {
	usort($hostdaylist[$_SESSION['showip']], 'date_compare');
	foreach( $hostdaylist[$_SESSION['showip']] as $dayoption ) {
		if(!isset($_SESSION['day'])){
			$_SESSION['day'] = $dayoption;
		}
		echo "\t\t<option value=\"".$dayoption."\"";
		if ( $dayoption == $_SESSION['day'] ) {
			echo " SELECTED";
		}
		echo ">".$dayoption."</option>\n";
	}
}


if ( isset($hostmonthlist[$_SESSION['showip']]) ) {
	foreach( $hostmonthlist[$_SESSION['showip']] as $dayoption ) {
		if(!isset($_SESSION['day'])) {
			$_SESSION['day'] = $dayoption;
		}
		echo "<option value=\"".$dayoption."\"";
		if ( $dayoption == $_SESSION['day'] ) {
			echo " SELECTED";
		}
		echo ">".$dayoption."</option>\n";
	}
}

echo "\t</select>\n\t</td><td>\n\tSearch: <input name=\"search\" type=\"text\" size=\"25\" value=\"";
if (isset($_SESSION['search'])) {
	echo $_SESSION['search'];
}
echo "\" onKeyPress=\"checkEnter(event)\"><button name=\"SubmitForm\" type=\"submit\">Go</button>\n\t</td>\n\t</tr>\n\t</table>\n\t</td>\n\t";
$host = str_replace('.','_',$_SESSION['showip']);
$tablename = "HST_" . $host . "_DATE_" . $_SESSION['day'];
if ( $searchstring == '%' ) {
	$countquery = "SELECT COUNT(*) FROM $tablename";
} else {
	$countquery = "SELECT COUNT(*) FROM $tablename WHERE MSG LIKE '$searchstring'";
}
if ( (isset($_SESSION['filter_LVL'])) && ($_SESSION['filter_LVL'] != "none")) {
	if ( $searchstring == '%' ) {
		//$countquery .= " WHERE LVL='" . $_SESSION['filter_LVL'] . "'";
		$countquery .= " WHERE LVL IN (" . $lvl_filter . ") ";
	} else {
		//$countquery .= " AND LVL='" . $_SESSION['filter_LVL'] . "'";
		$countquery .= " AND LVL IN (" . $lvl_filter . ") ";
	}
}
$countresult = mysqli_query($db_link,$countquery);
if($countresult) {
	$countedlines = mysqli_fetch_row($countresult);
	mysqli_free_result($countresult);
	$linecount = $countedlines['0'];
} else {
	$linecount = 0;
}
$_SESSION['pagecount'] = ceil($linecount / $_SESSION['showlines']);

if(!is_numeric($_SESSION['showpage'])) {
	if (preg_match('/^[0-2][0-9]:[0-5][0-9]/',$_POST['jumptopage'])) {
		$timecountquery = "SELECT COUNT(*) FROM $tablename WHERE MSG LIKE '$searchstring' ";
		if ( (isset($_SESSION['filter_LVL'])) && ($_SESSION['filter_LVL'] != "none")) {
			//$timecountquery .= "AND LVL='" . $_SESSION['filter_LVL'] . "' ";
			$timecountquery .= "AND LVL IN (" . $lvl_filter . ") ";
		}
		$timecountquery .= "AND TIME <= '" . $_SESSION['showpage'] . "'";
	} elseif (preg_match('/20[0-1][0-9]-[0-1][0-9]-[0-3][0-9] [0-2][0-9]:[0-5][0-9]/',$_POST['jumptopage'])) {
			$timecountquery = "SELECT COUNT(*) FROM $tablename WHERE MSG LIKE '$searchstring' ";
			if ( (isset($_SESSION['filter_LVL'])) && ($_SESSION['filter_LVL'] != "none")) {
				//$timecountquery .= "AND LVL='" . $_SESSION['filter_LVL'] . "' ";
				$timecountquery .= "AND LVL IN (" . $lvl_filter . ") ";
			}
			$timecountquery .= "AND CONCAT(DAY,' ',TIME) <= '" . $_SESSION['showpage'] . "'";
	}
	$timecountresult = mysqli_query($timecountquery);
	$timecountedlines = mysqli_fetch_row($timecountresult);
	mysqli_free_result($timecountresult);
	$timelinecount = $timecountedlines['0'];
	$timepagecount = round($timelinecount / $_SESSION['showlines']);
	$_SESSION['showpage'] = $_SESSION['pagecount'] - $timepagecount;
}
$counttolast = $_SESSION['pagecount'] - $_SESSION['showpage'];
$offset = ( $_SESSION['showpage'] - 1 ) * $_SESSION['showlines'];

echo "\t<td align=\"center\" rowspan=\"2\">\n\t<table class=\"outline\">\n\t<tr>\n";
echo "\t<td><button name=\"showpage_b25\" type=\"submit\"";
if ( $_SESSION['showpage'] < 26 ) { echo " disabled"; } 
echo ">-25</button></td>\n";
echo "\t<td><button name=\"showpage_b10\" type=\"submit\"";
if ( $_SESSION['showpage'] < 11 ) { echo " disabled"; } 
echo ">-10</button></td>\n";
echo "\t<td><button name=\"showpage_b1\" type=\"submit\"";
if ( $_SESSION['showpage'] < 2 ) { echo " disabled"; } 
echo ">-1</button></td>\n";
echo "\t<td><b><input type=\"text\" size=\"6\" name=\"jumptopage\" value=\"".$_SESSION['showpage']."\" OnKeyPress=\"checkEnter(event)\"></b></td>\n";
echo "\t<td><button name=\"showpage_f1\" type=\"submit\"";
if ( $_SESSION['showpage'] > ( $_SESSION['pagecount'] - 1 )) { echo " disabled"; } 
echo ">+1</button></td>\n";
echo "\t<td><button name=\"showpage_f10\" type=\"submit\"";
if ( $_SESSION['showpage'] > ( $_SESSION['pagecount'] - 11 )) { echo " disabled"; }
echo ">+10</button></td>\n";
echo "\t<td><button name=\"showpage_f25\" type=\"submit\"";
if ( $_SESSION['showpage'] > ( $_SESSION['pagecount'] - 26 )) { echo " disabled"; }
echo ">+25</button></td>\n\t</tr>\n\t<tr>";
echo "\t<td colspan=\"3\" align=\"center\"><button name=\"showpage_first\" type=\"submit\"";
if ( $_SESSION['showpage'] == "1" ) { echo " disabled"; } 
echo ">first</button></td>\n\t<td><span class=\"lpp\">lpp:</span>\n";
echo "\t<select class=\"lpp\" name=\"showlines\" onChange=\"this.form.submit()\">\n";
foreach( $showlines as $log_limit ) {
 	echo "\t\t<option value=\"".$log_limit."\"";
	if ( (isset($_SESSION['showlines'])) && ($log_limit == $_SESSION['showlines']) ) {
		echo " SELECTED";
	}
	echo ">".$log_limit."</option>\n";
}

echo "\t</select>\n\t</td>\n\t";
echo "\t<td colspan=\"3\" align=\"center\"><button name=\"showpage_last\" type=\"submit\"";
if ( ($_SESSION['showpage'] == $_SESSION['pagecount']) || ( $_SESSION['pagecount'] < 2 ) ) {
	echo " disabled";
}
echo ">last</button></td>\n\t</tr>\n\t</table>\n\t</td>\n\t";
echo "<td align=\"right\"><a href=\"index.php\" onClick=\"document.location.href = this.href;return false\" title=\"click to refresh the page\">Refresh</a>:\n";

if ( (isset($_SESSION['refresh'])) && ($_SESSION['refresh'] != 'off') ) {
	echo "<button name=\"stoprefresh\" type=\"submit\">stop</button>\n";
}
if ( $_SESSION['day'] == $today ) { 
	echo "\t<select name=\"refresh\" onChange=\"this.form.submit()\">\n";
	foreach ( $refresh as $value ) { 
		echo "\t\t<option value=\"".$value."\"";
		if ( isset($_SESSION['refresh']) && $_SESSION['refresh'] == $value ) {
			echo " SELECTED";
		}
		echo ">".$value."</option>\n";
	}
	echo "\t</select>\n";
} else {
	echo "off"; 
} 

//working code till here


echo "\t</td>\n\t</tr>\n\t<tr>\n\t<td align=\"left\">\n\t<table class=\"none\" width=\"75%\">\n\t<tr>\n\t";
echo "\t<td>Total lines: ".$linecount."</td>\n\t\t<td>Filters LVL: \n\t\t";
echo "<select name=\"filter_LVL\" onChange=\"this.form.submit()\">\n\t\t";
echo "<option value=\"none\"";
if ($_SESSION['filter_LVL'] == "none") {
	echo " SELECTED";
}
echo ">none</option>\n";
foreach($log_levels as $log_level) {
	echo "\t\t\t<option value=\"".$log_level."\" class=\"".$log_level."\"";
	if ($_SESSION['filter_LVL'] == "$log_level") {
		echo " SELECTED";
	}
	echo ">".$log_level."</option>\n";
}
echo "\t\t</select>\n\t\t</td>\n\t\t</tr>\n</table>\n</td>\n<td align=\"right\">Page ".$_SESSION['showpage']." of ".$_SESSION['pagecount']."</td>\n";
echo "</tr>\n</form>\n</table>\n</td>\n</tr>\n<tr>\n<td>\n<table class=\"none\" width=\"100%\">\n";

//Display the lines

echo "\t<tr>\n";
$columns = explode(', ',$log_fields);
foreach( $columns as $column ) {
	switch ( $column ) {
		case "DAY":
			echo "\t<th width=\"75\">".$column."</th>";
			break;
		case "PROG":
			echo "\t<th width=\"105\">".$column."</th>";
			break;
		default:
			echo "\t<th>".$column."</th>";
	}
}
echo "\t</tr>\n";

$query = "SELECT $log_fields FROM $tablename WHERE MSG LIKE '$searchstring' ";
//$query .= "AND LVL IN ('" . $_SESSION['filter_LVL'] . "') ";
if ( isset ($lvl_filter) ) {
	$query .= "AND LVL IN (" . $lvl_filter . ") ";
}

$query .= "ORDER BY id DESC LIMIT " . $_SESSION['showlines'] . " OFFSET " . $offset;
$result = mysqli_query($db_link,$query);	

$linetag = "0";
if($result) {
while( $loglines = mysqli_fetch_assoc($result) ) {
	if ($linetag == "0") {
		$linetag = "1";
	} else {
		$linetag = "0";
	}
	echo "\t<tr>";
	foreach($columns as $column) {
		if ( $column == "LVL" ) {
			switch ( $loglines["$column"] ) {
				case "debug":
					echo "<td class=\"debug\">";
					break;
				case "info":
					echo "<td class=\"info\">";
					break;
				case "notice":
					echo "<td class=\"notice\">";
					break;
				case "warning":
					echo "<td class=\"warning\">";
					break;
				case "err":
					echo "<td class=\"error\">";
					break;
				case "crit":
					echo "<td class=\"critical\">";
					break;
				case "alert":
					echo "<td class=\"alert\">";
					break;
				case "emergency":
					echo "<td class=\"emergency\">";
					break;
				case "panic":
					echo "<td class=\"panic\">";
					break;
				default:
					echo "<td>";
			}
			echo $loglines["$column"]."</td>";
		} elseif ($linetag == "0") {
			echo "<td>".$loglines["$column"]."</td>";
		} else {
			echo "<td class=\"grey\">".$loglines["$column"]."</td>";
		}
	}
	echo "</tr>\n";
	}
		mysqli_free_result($result);
	}

echo "\t</table>\n\t</td>\n</tr>\n</table>\n";
echo "<table class=\"none\" width=\"100%\"><tr><td align=\"right\"><a href=\"#\">Return to Top</a></td></tr></table>\n";
echo "</form>\n</body>\n";
?>
<script>
	var inFocus = 0;
	//set focus on edit box onload
	obj = document.settings.search;
	obj.focus();
	obj.value = obj.value;
	obj.onclick = function() {
		if(inFocus == 0) {
			obj.select()
			inFocus = 1;
		} else {
			obj.focus()
			inFocus = 0;
		}
	};
</script>
</html>

<?php
mysqli_close($db_link);
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<?php
require("config/config.php");

?>
<html lang="en">
<head>
    <title>Netalert</title>
	<meta http-equiv="content-type" content="text/html; charset=iso-8859-1">
	<link rel="stylesheet" type="text/css" href="/css/netlog.css">
	<meta http-equiv="refresh" content="15">
</head>
<body style="background-color: #161616;">
<?php

// Create and check database link
$today = date('Y_m_d');

/*
 * Create and check database link
 */
$db_link = connect_db();

$tablename = 'HST_127_0_0_1_DATE_' . $today;

$fiels = implode(', ', $alert_fields);
$query = "SELECT $fields FROM $tablename ORDER BY id DESC LIMIT $showlines_alert";
$result = mysqli_query($db_link,$query);

echo "<table class=\"outline\" width=\"100%\">\n\t<tr><td>\n\t\t<table class=\"none\" width=\"100%\">\n\t\t\t<tr>";
foreach($alert_fields as $column) {
	switch ( $column ) {
		case "TIME":
			echo "<th class=\"aqua\">".$column."</th>";
			break;
		case "DAY":
			echo "<th class=\"aqua\" width=\"75\">".$column." </th>";
			break;
		case "PROG":
			echo "<th class=\"aqua\" width=\"110\">".$column." </th>";
			break;
		default:
			echo "<th class=\"aqua\">".$column." </th>";
	}
}
echo "</tr>\n\t\t\t";

$linetag = "0";
if($result) {
	while( $loglines = mysqli_fetch_assoc($result) ) {
		if ($linetag == "0") {
			$linetag = "1";
		} else {
			$linetag = "0";
		}
		$currenttime = time();
		$timestamp = strtotime($loglines["TIME"]);
		$timediff = $currenttime - $timestamp;
		if ( preg_match("/.*restored to normal.*/",$loglines["MSG"]) || preg_match('/%LOGSCAVENGER%/',$loglines["PROG"])) {
			foreach($columns as $column) {
				if ( $column == "TIME" ) {
					switch ( $timediff ) {
						case ( $timediff < 400 ):
							echo "<td class=\"panic\"><b>".$loglines["$column"]."</b></td>";
							break;
						case ( $timediff < 800 ):
							echo "<td class=\"emergency\"><b>".$loglines["$column"]."</b></td>";
							break;
						case ( $timediff < 1200 ):
							echo "<td class=\"alert\"><b>".$loglines["$column"]."</b></td>";
							break;
						case ( $timediff < 1600 ):
							echo "<td class=\"critical\"><b>".$loglines["$column"]."</b></td>";
							break;
						case ( $timediff < 2000 ):
							echo "<td class=\"error\"><b>".$loglines["$column"]."</b></td>";
							break;
						case ( $timediff < 2400 ):
							echo "<td class=\"warning\"><b>".$loglines["$column"]."</b></td>";
							break;
						case ( $timediff < 2800 ):
							echo "<td class=\"notice\">".$loglines["$column"]."</td>";
							break;
						case ( $timediff < 3200 ):
							echo "<td class=\"info\">".$loglines["$column"]."</td>";
							break;
						case ( $timediff < 3600 ):
							echo "<td class=\"debug\">".$loglines["$column"]."</td>";
							break;
						default:
							if($linetag == "0") {
								echo "<td class=\"white\">".$loglines["$column"]."</td>";
							} else {
								echo "<td class=\"darkgrey\">".$loglines["$column"]."</td>";
							}
					}
				} elseif ( $column == "LVL" || $column == "MSG" || $column == "PROG" ) {
					if($linetag == "0") {
						if ( $timediff > $timethresh ) {
							echo "<td class=\"white\">";
						} else {
							echo "<td class=\"netup\">";
						}
					} elseif($linetag == "1") {
						if ( $timediff > $timethresh ) {
							echo "<td class=\"darkgrey\">";
						} else {
							echo "<td class=\"netupd\">";
						}
					} else {
						echo "<td class=\"white\">";
					}
					echo $loglines["$column"]."</td>";
				} elseif ($linetag == "0") {
					echo "<tr><td class=\"white\">".$loglines["$column"]."</td>";
				} else {
					echo "<tr><td class=\"darkgrey\">".$loglines["$column"]."</td>";
				}
			}
		} elseif ( preg_match("/.*went above threshold.*/",$loglines["MSG"])) {
			foreach($columns as $column) {
				if ( $column == "TIME" ) {
					switch ( $timediff ) {
						case ( $timediff < 400 ):
							echo "<td class=\"panic\"><b>";
							break;
						case ( $timediff < 800 ):
							echo "<td class=\"emergency\"><b>";
							break;
						case ( $timediff < 1200 ):
							echo "<td class=\"alert\"><b>";
							break;
						case ( $timediff < 1600 ):
							echo "<td class=\"critical\"><b>";
							break;
						case ( $timediff < 2000 ):
							echo "<td class=\"error\"><b>";
							break;
						case ( $timediff < 2400 ):
							echo "<td class=\"warning\"><b>";
							break;
						case ( $timediff < 2800 ):
							echo "<td class=\"notice\">";
							break;
						case ( $timediff < 3200 ):
							echo "<td class=\"info\">";
							break;
						case ( $timediff < 3600 ):
							echo "<td class=\"debug\">";
							break;
						default:
							if($linetag == "0") {
								echo "<td class=\"white\">";
							} else {
								echo "<td class=\"darkgrey\">";
							}
					}
					echo $loglines["$column"]."</td><td></td>";
				} elseif ( $column == "LVL" || $column == "MSG" ) {
					if($linetag == "0") {
						if ( $timediff > $timethresh ) {
							echo "<td class=\"white\">";
						} else {
							echo "<td class=\"netdown\"><b>";
						}
					} elseif($linetag == "1") {
						if ( $timediff > $timethresh ) {
							echo "<td class=\"darkgrey\">";
						} else {
							echo "<td class=\"netdownd\"><b>";
						}
					} else {
						echo "<td class=\"white\"><b>";
					}
					echo $loglines["$column"]."</b></td>";
				} elseif ($linetag == "0") {
					echo "<tr><td class=\"white\">".$loglines["$column"]."</td>";
				} else {
					echo "<tr><td class=\"darkgrey\">".$loglines["$column"]."</td>";
				}
			}
		} else {
			echo "<tr>";
			foreach($alert_fields as $column) {
				if ( $column == "TIME" ) {
					if ($linetag == "0") {
						echo "<td class=\"white\">".$loglines["$column"]."</td>";
					} else {
						echo "<td class=\"darkgrey\">".$loglines["$column"]."</td>";
					}
				} elseif( $column == "LVL" || $column == "MSG" ) {
					if($linetag == "0") {
						if ( $timediff > $timethresh ) {
							echo "<td class=\"white\">";
						} else {
							echo "<td class=\"netinfo\">";
						}
					} elseif($linetag == "1") {
						if ( $timediff > $timethresh ) {
							echo "<td class=\"darkgrey\">";
						} else {
							echo "<td class=\"netinfod\">";
						}
					} else {
						echo "<td class=\"white\">";
					}
					echo $loglines["$column"]."</td>";
				} elseif ($linetag == "0") {
					echo "<td class=\"white\">".$loglines["$column"]."</td>";
				} else {
					echo "<td class=\"darkgrey\">".$loglines["$column"]."</td>";
				}
			}
		}
		echo "</tr>\n\t\t\t";
	} echo "\n"; 
	mysqli_free_result($result);
}
mysqli_close($db_link);
?>
		</table>
	</td></tr>
</table>
</body>
</html>

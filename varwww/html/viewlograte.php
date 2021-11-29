<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<?php

// Include the basics ;)
include("config/config.php");
require_once ('jpgraph/jpgraph.php');
require_once ('jpgraph/jpgraph_line.php');

session_start();

?>
<html lang="en">
<head>
	<meta http-equiv="content-type" content="text/html; charset=iso-8859-1">
	<link rel="stylesheet" type="text/css" href="css/netlog.css">
	<script type="text/javascript" src="scripts/netlog.js"></script>
</head>
<body>
<?php

// Create and check database link
$today = date('Y_m_d');

$db_link = mysqli_connect($db_HOST, $db_USER, $db_PASS, $db_NAME);
if (!$db_link) {
	die('Could not connect to MySQL server: ' . mysql_error($db_link));
}

if (!mysqli_select_db($db_link,$db_NAME)) {
	die('Unable to select DB: ' . mysql_error($db_link));
}

if (!isset($_SESSION['timelimit'])) {
	$_SESSION['timelimit'] = 30;
}

if ( isset($_POST['time']) ) {
	$_SESSION['timelimit'] = $_POST['time'];
}

$query = "SELECT hostnameid,hostname FROM netlogconfig.lograteconf LEFT JOIN netlogconfig.hostnames ON (hostnameid=id) WHERE samplerate=1 order by hostnameid";
$result = mysqli_query($db_link,$query);

echo "<table class=\"outline\" width=\"100%\">\n\t<tr>\n\t\t<th width=\"75%\">Netlog ".date('Y-m-d H:i:s')."</th><td align=\"right\"><a href=\"index.php\">view logging</a></td>\n";
echo "\t</tr>\n\t<tr>\n\t\t<td colspan=\"2\"><br></td>\n\t</tr>\n\t<tr>\n\t";
echo "\t<td colspan=\"2\">\n\t\t\t<form method=\"post\" action=\"viewlograte.php\">\n\t\t\t<table class=\"none\" width=\"100%\">\n";
echo "\t\t\t\t<tr>\n\t\t\t\t<th>Log rates for clients:</th>\n\t\t\t\t<td colspan=\"2\"> <a href=\"viewlograte.php\" onClick=\"document.location.href = this.href;return false\" title=\"click to refresh the page\">Refresh</a> \n\t\t\t\tShow last \n";
echo "\t\t\t\t<select name=\"time\" onChange=\"this.form.submit()\">\n";
foreach( $graphhistory as $timelimit ) {
	echo "\t\t\t\t\t<option value=\"".$timelimit."\"";
	if (( isset($_SESSION['timelimit'])) && ( $timelimit == $_SESSION['timelimit']) ) {
		echo " SELECTED";
	}
	echo ">".$timelimit."</option>\n";
}
echo "\t\t\t\t</select> measurements\n\t\t\t\t</td>\n\t\t\t</table>\n\t\t\t</form>\n\t\t</td>\n";
echo "\t</tr>\n\t<tr>\n";
$height = 250;
$width = 600;
$graphcount = 1;
while ( $drawhost = mysqli_fetch_assoc($result)) {
	echo "\t<td><img src=\"drawgraph.php?hostid=".$drawhost['hostnameid']."&hostname=".$drawhost['hostname']."&width=".$width."&height=".$height."&time=".$_SESSION['timelimit']."\"></td>";
	$graphcount += 1;
	if($graphcount & 1) {
		echo "</tr>\n\t<tr>";
	}
}
?>
	</tr>
</table>
</body>
</html>

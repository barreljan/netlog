<?php
// NetLog scavenger config
// For continuous searching of specific events
// Created, Jan 2016 | bartjan@pc-mania.nl

include 'config/config.php';

session_start();
function BackToHome() {;
	header('Location: /scavconfig.php');
}

$conn = mysqli_connect($db_HOST,$db_USER,$db_PASS, $db_NAME) or die ("Unable to connect to the database");

// Get the keywords
$querykeywords = "SELECT * FROM `{$db_NAMECONF}`.`logscavenger` ORDER BY `id`";
$kwresult = mysqli_query($conn,$querykeywords) or die(mysqli_error($conn));
$kwrows = mysqli_num_rows($kwresult);

// Get the email groups
$queryemailgroups = "SELECT * FROM `{$db_NAMECONF}`.`emailgroups` WHERE `active` = '1' ORDER BY `id`";
$emailgrpresult = mysqli_query($conn,$queryemailgroups) or die(mysqli_error($conn));

// Add/update/delete functions
if (isset($_POST['ADD'])) {
	if ($_POST['KEYWORD'] == "" || $_POST['KEYWORD'] == NULL) {
		BackToHome();
	} else {
		$insert_query="INSERT INTO `{$db_NAMECONF}`.`logscavenger` (keyword,dateadded,active) VALUES (\"".htmlspecialchars($_POST['KEYWORD'])."\",CURRENT_TIMESTAMP,1)";
		$insert_result=mysqli_query($conn,$insert_query) or die(mysqli_error($conn));
		BackToHome();
	}
}
if (isset($_POST['UPDATE'])) {
	foreach($_POST as $key => $value ) {
		$readkey = explode('-',$key);
		if ($readkey['0'] == "emailgroupkwid") {
    	    $update_query = "UPDATE `{$db_NAMECONF}`.`logscavenger` SET `emailgroup` = (\"{$value}\") WHERE ID = {$readkey['1']}";
			$update_result = mysqli_query($conn,$update_query) or die (mysqli_error($conn));
		}
		if ($readkey['0'] == "state") {
			$update_query = "UPDATE `{$db_NAMECONF}`.`logscavenger` SET `active` = (\"{$value}\") WHERE ID = {$readkey['1']}";
			$update_result = mysqli_query($conn,$update_query) or die (mysqli_error($conn));
		}
	}
	BackToHome();
}
if (isset($_GET['action'])) {
	if (empty($_GET['id'])) {
		BackToHome();
	} else {
		if ($_GET['action'] == 'delete') {
			$id=$_GET['id'];
			$delete_query = "DELETE FROM `{$db_NAMECONF}`.`logscavenger` WHERE `id` = {$id}";
			$delete_result=mysqli_query($conn,$delete_query) or die (mysqli_error($conn));
			BackToHome();
		}
	}
}
mysqli_close($conn);
// HTML code generation
?>
<!DOCTYPE html>
<html>
	<head>
		<title>Scavenger config</title>
		<link rel="stylesheet" type="text/css" media="all" href="./css/netlog.css"/>
		<meta http-equiv="refresh" content="300">
		<script type="text/javascript" src="scripts/netlog.js"></script>
		<!-- <?php echo "{$GLOBALS['scavconfver']}, bartjan@pc-mania.nl"; ?> -->
	</head>
<body>
<form action="./scavconfig.php" method="post">
<table class="outline" width="100%">
	<tr>
		<th width="75%">NetLog scavenger <?php echo date('Y-m-d H:i:s'); ?> </th>
		<td align="right"><a href="./">view logging</a></td>
	</tr>
	<tr>
		<td colspan="2"><br/></td>
	</tr>
<?php
echo "\t<table class=\"outline\" width=\"100%\">\n";
echo "\t\t<tr><th>Keyword</th><th>Email Group</th><th>Active?</th><th> </th></tr>";

$i=0;
$linetag = "0";
while ($i <$kwrows) {
	if ($linetag == "0") {
		$linetag = "1";
		$class = "class=\"grey\"";
	} else {
		$linetag = "0";
		$class = "";
	}
	$keywordresult = mysqli_fetch_assoc($kwresult);

	$selectlist = "\n\t\t\t<select name=\"emailgroupkwid-{$keywordresult['id']}\">\n";
	while ($emailgrparr =  mysqli_fetch_assoc($emailgrpresult)) {
		if ($emailgrparr['id'] == $keywordresult['emailgroup']){
			$selectlist .= "\t\t\t\t<option value=\"{$emailgrparr['id']}\" selected>{$emailgrparr['groupname']}</option>\n";
		}
		$selectlist .= "\t\t\t\t<option value=\"{$emailgrparr['id']}\">{$emailgrparr['groupname']}</option>\n";
	}
	mysqli_data_seek($emailgrpresult, 0);
	$selectlist .= "\t\t\t</select>\n";

	echo "\n\t\t<tr {$class}>\n\t\t\t<td {$class} width=\"25%\">{$keywordresult['keyword']} </td>\n";
	if ($keywordresult['active'] == 1){
		$kwstate = "\n\t\t\t\t<input type=\"hidden\" value=\"0\" name=\"state-{$keywordresult['id']}\">\n\t\t\t\t<input type=\"checkbox\" value=\"1\" name=\"state-{$keywordresult['id']}\" checked>";
	} else {
		$kwstate = "\n\t\t\t\t<input type=\"checkbox\" value=\"1\" name=\"state-{$keywordresult['id']}\">";
	}
	echo "\t\t\t<td {$class} width=\"15%\">{$selectlist}\t\t\t</td>\n";
	echo "\t\t\t<td {$class}>{$kwstate} \n\t\t\t</td>\n";
	echo "\t\t\t<td {$class}><a href=\"./scavconfig.php?action=delete&amp;id={$keywordresult['id']}\">Delete</a></td>\n\t\t</tr>";
	$i++;
}
echo "\n";
?>
	</table><?php //echo $kwrows; ?>
	<table class="outline" width="100%">
		<tr>
			<td width="25%">
				<br/><br/><br/>Enter a new keyword:<br/>
				<input type="text" name="KEYWORD"> <br/>
				<input type="submit" value="Add keyword" name="ADD">
			</td>
			<td width="15%">
				<br/><br/><br/>Update state or email settings:<br/><br/>
				<input type="submit" value="Update" name="UPDATE">
			</td>
			<td>
				&nbsp;
			</td>
		</tr>
	</table>
</table>
</form>

<?php
mysqli_free_result($kwresult);
mysqli_free_result($emailgrpresult);
echo "</body>\n</html>"
?>

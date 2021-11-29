<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<?php

// Include the basics ;)
include("config/config.php");

session_start();

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="en">
<head>
        <meta http-equiv="content-type" content="text/html; charset=iso-8859-1"></meta>
        <link rel="stylesheet" type="text/css" href="css/netlog.css"></link>
	<script type="text/javascript" src="scripts/netlog.js"></script>
</head>
<body>
<?php

// Create and check database link
$today = date('Y_m_d');

$db_link = mysqli_connect($db_HOST, $db_USER, $db_PASS, $db_NAME);
if (!$db_link) {
        die('Could not connect to MySQL server: ' . mysqli_error());
}

if (!mysqli_select_db($db_link,$db_NAME)) {
        die('Unable to select DB: ' . mysqli_error());
}

if (!isset($_SESSION['viewitem'])) {
	$_SESSION['viewitem'] = "Unnamed";
}

if (isset($_POST['toggleview'])) {
	$_SESSION['viewitem'] = $_POST['toggleview'];
}

foreach( $_POST as $key => $value ) {
	$seskey = str_replace('_','.',$key);
	
	// existing host
	if ( isset($_SESSION['config']["$seskey"])) {
		if ( $_POST["$key"] != $_SESSION['config']["$seskey"] ) {
			$readkey = explode('-',$seskey);
			$column = $readkey['0'];
			$hostip = $readkey['1'];
			
			if ( $column == "hosttype" ) {
				$query = "UPDATE netlogconfig.hostnames SET $column='" . $_SESSION['typelist'][$_POST[$key]] . "' WHERE hostip='$hostip'";
				$result = mysqli_query($db_link,$query);
			} else {
				$query = "UPDATE netlogconfig.hostnames SET $column='" . $_POST["$key"] . "' WHERE hostip='$hostip'";
				$result = mysqli_query($db_link,$query);
			}
		}
	// new host
	} elseif ( preg_match('/hostname-/', $key ) ) {
		if( $value != "" ) {
			$readkey = explode('-',$key);
			$hostip = str_replace('_','.',$readkey['1']);
			$hosttypekey = 'hosttype-' . $readkey['1'];
			$hosttype = $_SESSION['typelist'][$_POST[$hosttypekey]];
		
			$query = "INSERT INTO netlogconfig.hostnames (hostip, hostname, hosttype) VALUES ('$hostip','$_POST[$key]','$hosttype')";
			$result = mysqli_query($db_link,$query);
		}
	}
}

unset($query, $result);
unset($_SESSION['config']);

$hostnamequery = "SELECT hostip, hostname, name FROM netlogconfig.hostnames LEFT JOIN netlogconfig.hosttype ON (netlogconfig.hostnames.hosttype=netlogconfig.hosttype.id) ORDER BY hostip,hosttype DESC";
$hostnameresult = mysqli_query($db_link,$hostnamequery);
while ( $dbhostnames = mysqli_fetch_assoc($hostnameresult)) {
	$hostnameip = $dbhostnames['hostip'];
	$hostname["$hostnameip"] = $dbhostnames['hostname'];
	$hosttype["$hostnameip"] = $dbhostnames['name'];
	$iplist[] = $hostnameip;
	
	// if we already have an entry, don't overwrite it (in case of multiple records...)
	if(!isset($_SESSION['config']["hostname-$hostnameip"])) {
		$_SESSION['config']["hostname-$hostnameip"] = $dbhostnames['hostname'];
		$_SESSION['config']["hosttype-$hostnameip"] = $dbhostnames['name'];
	}
}

$query = "SHOW TABLES in `{$db_NAME}`";
$result = mysqli_query($db_link,$query);

// Throw all ip parts of tables in an array
while( $lines = mysqli_fetch_array($result)) {
        if (strpos($lines['0'], "template") !== false || strpos($lines['0'], "UHO") !== false || strpos($lines['0'], "criteria") !== false){
            continue;
        }
	$thishost = explode('_DATE_', $lines['0']);
        $host = trim($thishost['0'],'HST_');
        $ip = str_replace('_','.',$host);
        $hostdaylist["$ip"][] = $thishost['1'];
        $iplist2[] = $ip;
}

natsort($iplist);
$iplist = array_unique(array_merge($iplist,$iplist2));
$iplist3 = array_diff($iplist,$iplist2);

unset($_SESSION['typelist']);

$typequery = "SELECT id, name FROM netlogconfig.hosttype ORDER BY name";
$typeresult = mysqli_query($db_link,$typequery);

while ( $types = mysqli_fetch_assoc($typeresult)) {
	$_SESSION['typelist'][$types['name']] = $types['id'];
}

?>
<form name="config" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<table class="outline" width="100%">
  <tr>
    <th width="75%">Netlog <?php echo date('Y-m-d_H:i:s') ?></th><td align="right"><a href="index.php">view logging</a></td>
  </tr>
  <tr>
  <td colspan="2"><br></td>
  </tr>
  <tr>
  <td colspan="2">
	<table class="none">
	<tr>
	  <th>Log client host names:</th>
	  <th>&nbsp;</th>
	  <th>Toggle view:<input type="radio" name="toggleview" value="All" onClick="this.form.submit()" <?php if ($_SESSION['viewitem'] == "All") { echo "CHECKED"; } ?>>All <input type="radio" name="toggleview" value="Unnamed" onClick="this.form.submit()" <?php if ($_SESSION['viewitem'] == "Unnamed") { echo "CHECKED"; } ?>>Unnamed <input type="radio" name="toggleview" value="Unused" onClick="this.form.submit()" <?php if ($_SESSION['viewitem'] == "Unused") { echo "CHECKED"; } ?>>Unused</th>
	  <th>&nbsp;</th>
	</tr>
	<tr>
	  <th>IP:</th>
	  <?php if ($_SESSION['viewitem'] == "All" || $_SESSION['viewitem'] == "Unused") { echo "<th>Current Name:</th>"; } else { echo "<th>&nbsp;</th>"; } ?>
	  <th>New Name:</th>
	  <th>Type:</th>
	</tr>
	<?php
	if ($_SESSION['viewitem'] == "All") {
		foreach ($iplist as $ip) { ?>
		<tr>
		  <td><?php echo $ip; ?></td>
		  <td width=200><?php if (isset($_SESSION['config']["hostname-$ip"])) { echo $_SESSION['config']["hostname-$ip"]; } ?></td>
		  <td><input type="text" name="<?php echo "hostname-" . $ip ?>" value="<?php if (isset($_SESSION['config']["hostname-$ip"])) { echo $_SESSION['config']["hostname-$ip"]; } ?>"></td>
		  <td><select name="<?php echo "hosttype-" . $ip ?>">
			<?php foreach ( $_SESSION['typelist'] as $typename => $typeid ) {
				?><option value="<?php echo $typename ?>" <?php if ( (isset($_SESSION['config']["hosttype-$ip"])) && ( $_SESSION['config']["hosttype-$ip"] == $typename ) ) { echo "SELECTED"; } ?>><?php echo $typename ?></option><?php
			   } ?>
		      </select></td>
		</tr>
	<?php
		} 
	} elseif($_SESSION['viewitem'] == "Unused") {
		foreach ($iplist3 as $ip) { 
		?>
		<tr>
		  <td><?php echo $ip ?></td>
		  <td width=200><?php if (isset($_SESSION['config']["hostname-$ip"])) { echo $_SESSION['config']["hostname-$ip"]; } ?></td>
		  <td><input type="text" name="<?php echo "hostname-" . $ip ?>" value="<?php if (isset($_SESSION['config']["hostname-$ip"])) { echo $_SESSION['config']["hostname-$ip"]; } ?>"></td>
		  <td><select name="<?php echo "hosttype-" . $ip ?>">
			<?php foreach ( $_SESSION['typelist'] as $typename => $typeid ) {
				?><option value="<?php echo $typename ?>" <?php if ( (isset($_SESSION['config']["hosttype-$ip"])) && ( $_SESSION['config']["hosttype-$ip"] == $typename ) ) { echo "SELECTED"; } ?>><?php echo $typename ?></option><?php
			   } ?>
		      </select></td>
		</tr>
		<?php
		}
	} else {
		foreach ($iplist as $ip) { 
			if (!isset($_SESSION['config']["hostname-$ip"])) {
		?>
		<tr>
                  <td><?php echo $ip ?></td>
				<td width=200>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</td>
		  <td><input type="text" name="<?php echo "hostname-" . $ip ?>" value="<?php if (isset($_SESSION['config']["hostname-$ip"])) { echo $_SESSION['config']["hostname-$ip"]; } ?>"></td>
		  <td><select name="<?php echo "hosttype-" . $ip ?>">
                        <?php foreach ( $_SESSION['typelist'] as $typename => $typeid ) {
                                ?><option value="<?php echo $typename ?>" <?php if ( (isset($_SESSION['config']["hosttype-$ip"])) && ( $_SESSION['config']["hosttype-$ip"] == $typename ) ) { echo "SELECTED"; } ?>><?php echo $typename ?></option><?php
                           } ?>
                      </select></td>
                </tr>
		<?php
			}
		}
	}
?>
  <tr><td colspan="2"><button type="submit">submit</button></td></tr>
</table>
</form>
</body>
</html>

<?php
require("config/config.php");
$today = date('Y_m_d');

$session_name = "PHP_NETLOG";


/*
 * Start (or not) session
 */
function is_session_started(): bool
{
    if (php_sapi_name() === 'cli')
        return false;
    if (version_compare(phpversion(), '5.4.0', '>='))
        return session_status() === PHP_SESSION_ACTIVE;
    return session_id() !== '';
}

if (!is_session_started()) {
    session_name($session_name);
    session_start();
}

if (!isset($_SESSION)) {
    echo "No session set or server is not allowing PHP Sessions to be stored?";
    die;
}

/*
 * Create and check database link
 */
$db_link = connect_db();

/*
 * Some functions
 */
function gen_rows_hosts($input)
{
    foreach ($input as $ip) {
        $hostname = $_SESSION['names_config']["hostname-$ip"] ?? '';
        $hosttype = $_SESSION['names_config']["hosttype-$ip"] ?? '';
        if (isset($_SESSION['names_config']["lograte-$ip"])) {
            if ($_SESSION['names_config']["lograte-$ip"] == 1) {
                $lograte_checked = ' checked';
            } else {
                $lograte_checked = '';
            }
        } else {
            $lograte_checked = 'disabled';
        }
        $unused_disable = $_SESSION['viewitem'] == "Unused" ? ' disabled' : ''; ?>
        <tr>
            <td><?php echo $ip; ?></td>
            <td><?php echo $hostname; ?></td>
            <td>
                <input id="settings_input_hostname" type="text" name="hostname-<?php echo $ip; ?>" title="A decent name"
                       value=<?php echo "\"" . $hostname . "\"" . $unused_disable; ?>>
            </td>
            <td>
                <select title="Select the correct type"
                        name=<?php echo "\"hosttype-$ip\"" . $unused_disable; ?>> <?php
                    foreach ($_SESSION['typelist'] as $typename => $typeid) {
                        $hosttype_selected = ($hosttype == $typename ? ' selected' : '');
                        echo "\n"; ?>
                        <option value=
                        <?php echo "\"" . $typename . "\"" . $hosttype_selected; ?>><?php echo $typename; ?></option><?php
                    }
                    echo "\n"; ?>
                </select>
            </td>
            <td id="settings_checkbox">
                <input type="hidden" value="off" name="lograte-<?php echo $ip; ?>">
                <input type="checkbox" title="Disable lograte"
                       name=<?php echo "\"lograte-$ip\"" . $lograte_checked; ?>>
            </td>
            <?php if ($_SESSION['viewitem'] == "Unused") { ?>
                <td id="settings_checkbox">
                <input type="hidden" value="off" name="delete-<?php echo $ip; ?>">
                <input type="checkbox" title="Delete this entry" name="delete-<?php echo $ip; ?>">
                </td><?php
            }
            ?>
        </tr>
        <?php
    }
}


/*
 * Processing the session, maybe it's a new one
 */
if (!isset($_SESSION['view'])) {
    $_SESSION['view'] = "names";
}
if (!isset($_SESSION['updated'])) {
    $_SESSION['updated'] = null;
}

/*
 * Processing the POST parts
 */
if (isset($_POST)) {
    if (isset($_POST['names'])) {
        $_SESSION['view'] = "names";
    }
    if (isset($_POST['lograte'])) {
        $_SESSION['view'] = "lograte";
    }
    if (isset($_POST['scavenger'])) {
        $_SESSION['view'] = "scavenger";
    }
    if (isset($_POST['contacts'])) {
        $_SESSION['view'] = "contacts";
    }
    if (!isset($_SESSION['viewitem'])) {
        $_SESSION['viewitem'] = "Unnamed";
    }
    if (isset($_POST['toggleview'])) {
        $_SESSION['viewitem'] = $_POST['toggleview'];
    }
    // Update existing or insert new items

    /* Turn autocommit off */
    $db_link->autocommit(false);

    try {
        foreach ($_POST as $key => $value) {
            // Revert what PHP is doing to POST
            $seskey = str_replace('_', '.', $key);
            $readkey = explode('-', $key);
            $readseskey = explode('-', $seskey);

            if (isset($_SESSION['names_config'][$seskey])) {
                // Existing host
                if ($_POST[$key] != $_SESSION['names_config'][$seskey]) {
                    $column = $readseskey[0];
                    $hostip = $readseskey[1];

                    if ($column == "hosttype") {
                        $query = "UPDATE `{$database['DB_CONF']}`.`hostnames`
                                     SET $column = '" . $_SESSION['typelist'][$value] . "'
                                   WHERE hostip = '$hostip'";
                    } elseif ($column == "hostname") {
                        $query = "UPDATE `{$database['DB_CONF']}`.`hostnames`
                                     SET $column = ?
                                   WHERE hostip = '$hostip'";
                    } elseif ($column == "lograte") {
                        $lograte = 0;
                        if ($value == "on") {
                            $lograte = 1;
                        }
                        $query = "UPDATE `{$database['DB_CONF']}`.`hostnames`
                                     SET lograte = $lograte
                                   WHERE hostip = '$hostip'";
                    } else {
                        continue;
                    }
                    $updatequery = $db_link->prepare($query);
                    if ($column == "hostname") {
                        $updatequery->bind_param('s', $_POST[$key]);
                    }
                    $updatequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^hostname-/', $key)) {
                // New host
                if ($value != "") {
                    $hostip = str_replace('_', '.', $readkey[1]);
                    $hosttypekey = 'hosttype-' . $readkey[1];
                    $hosttype = $_SESSION['typelist'][$_POST[$hosttypekey]];

                    $query = "INSERT INTO `{$database['DB_CONF']}`.`hostnames` (`hostip`, `hostname`, `hosttype`)
                                   VALUES ('$hostip', ?, '$hosttype')";
                    $insertquery = $db_link->prepare($query);
                    $insertquery->bind_param('s', $value);
                    $insertquery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^delete-/', $key)) {
                // Deletion of (unused) configured host
                if ($value == "on") {
                    $hostip = $readseskey[1];
                    $query = "DELETE
                                FROM `{$database['DB_CONF']}`.`hostnames`
                               WHERE `hostip` = '$hostip'";
                    $deletequery = $db_link->prepare($query);
                    $deletequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (isset($_SESSION['scav_config'][$key])) {
                // Scavenger existing keyword
                if ($_POST[$key] != $_SESSION['scav_config'][$key]) {
                    // Change detected ";
                    $kwid = $readkey[1];
                    $column = str_replace('scav', '', $readkey[0]);
                    if ($column == "emailgroupid") {
                        $grpid = $_SESSION['emailgrp'][$_POST[$key]];
                        $query = "UPDATE `{$database['DB_CONF']}`.`logscavenger`
                                     SET `emailgroupid` = $grpid
                                   WHERE `id` = $kwid";
                    } elseif ($column == "active") {
                        $scavenger = 0;
                        if ($value == "on") {
                            $scavenger = 1;
                        }
                        $query = "UPDATE `{$database['DB_CONF']}`.`logscavenger`
                                     SET `active` = $scavenger
                                   WHERE `id` = $kwid";
                    }
                    $updatequery = $db_link->prepare($query);
                    $updatequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^new_keyword/', $key)) {
                // New keyword
                if ($value != "") {
                    $query = "INSERT INTO `{$database['DB_CONF']}`.`logscavenger` (keyword, active, emailgroupid)
                                   VALUES (?, 1, 1)";
                    $insertquery = $db_link->prepare($query);
                    $insertquery->bind_param('s', $value);
                    $insertquery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^scavdelete/', $key)) {
                // Deletion of (unused) configured host
                if ($value == "on") {
                    $kwid = $readkey[1];
                    $query = "DELETE
                                FROM `{$database['DB_CONF']}`.`logscavenger`
                               WHERE `id` = $kwid";
                    $deletequery = $db_link->prepare($query);
                    $deletequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            }
        }
        /* Set autocommit on (and commit) */
        mysqli_autocommit($db_link, true);
    } catch (mysqli_sql_exception $exception) {
        $db_link->rollback();

        $_SESSION['updated'] = 'false';
    }
}


/*
 * Fetch data from DB and populate vars/arrays
 */
// Clean, should do nothing, but hey
unset($_SESSION['names_config']);
unset($_SESSION['scav_config']);
unset($_SESSION['typelist']);
unset($_SESSION['emailgrp']);
unset($query, $hostnameresult, $tablesresult, $typeresult, $kwresults, $emailgrpresults);

// Set blanks (if no entry in DB exists)
$current_hosts = array();
$logging_hosts = array();
$unused_hosts = array();
$unnamed_hosts = array();

$query = "SELECT `hostip`, `hostname`, `name`, `lograte`
            FROM `{$database['DB_CONF']}`.`hostnames`
            LEFT JOIN `{$database['DB_CONF']}`.`hosttype`
                 ON (`{$database['DB_CONF']}`.`hostnames`.`hosttype`=`{$database['DB_CONF']}`.`hosttype`.`id`)
           ORDER BY `hostip`, `hosttype` DESC";
$hostnamequery = $db_link->prepare($query);
$hostnamequery->execute();
$hostnameresult = $hostnamequery->get_result();

// Throw all config parts of hosts in an array
while ($dbhostnames = $hostnameresult->fetch_assoc()) {
    $hostnameip = $dbhostnames['hostip'];
    $current_hosts[] = $hostnameip;

    // If we already have an entry, don't overwrite it (in case of multiple records...)
    if (!isset($_SESSION['names_config']["hostname-$hostnameip"])) {
        $_SESSION['names_config']["hostname-$hostnameip"] = $dbhostnames['hostname'];
        $_SESSION['names_config']["hosttype-$hostnameip"] = $dbhostnames['name'];
        $_SESSION['names_config']["lograte-$hostnameip"] = $dbhostnames['lograte'];
    }
}
$hostnameresult->free();

$query = "SELECT TABLE_NAME AS tblnm
            FROM INFORMATION_SCHEMA.TABLES
           WHERE TABLE_SCHEMA = '{$database['DB']}'";
$tablesquery = $db_link->prepare($query);
$tablesquery->execute();
$tablesresult = $tablesquery->get_result();

// Throw all ip parts of tables in an array
while ($lines = $tablesresult->fetch_array(MYSQLI_NUM)) {
    if (strpos($lines[0], "template") !== false || strpos($lines[0], "UHO") !== false || strpos($lines[0], "criteria") !== false) {
        continue;
    }
    $thishost = explode('_DATE_', $lines[0]);
    $host = trim($thishost[0], 'HST_');
    $ip = str_replace('_', '.', $host);
    $hostdaylist[$ip][] = $thishost[1];
    if (!in_array($ip, $logging_hosts)) {
        $logging_hosts[] = $ip;
    }
}
$tablesresult->free();

// Merge and diff lists
natsort($current_hosts);
$current_hosts = array_unique(array_merge($current_hosts, $logging_hosts));
$unused_hosts = array_diff($current_hosts, $logging_hosts);

foreach ($current_hosts as $ip) {
    if (!isset($_SESSION['names_config']["hostname-$ip"])) {
        $unnamed_hosts[] = $ip;
    }
}

// Make an array for the selection box
$query = "SELECT `id`, `name`
            FROM `{$database['DB_CONF']}`.`hosttype`
           ORDER BY `name`";
$typequery = $db_link->prepare($query);
$typequery->execute();
$typeresult = $typequery->get_result();

while ($types = $typeresult->fetch_assoc()) {
    $_SESSION['typelist'][$types['name']] = $types['id'];
}
$typeresult->free();

// Get the scavenger keywords
$query = "SELECT `logscavenger`.`id`, `keyword`, `logscavenger`.`active`, `emailgroupid`, `groupname`
            FROM `{$database['DB_CONF']}`.`logscavenger`
            LEFT JOIN `{$database['DB_CONF']}`.`emailgroup`
                 ON (`{$database['DB_CONF']}`.`logscavenger`.`emailgroupid`=`{$database['DB_CONF']}`.`emailgroup`.`id`)
           ORDER BY `{$database['DB_CONF']}`.`logscavenger`.`id`";
$kwquery = $db_link->prepare($query);
$kwquery->execute();
$kwresults = $kwquery->get_result();
$keywords = array();
while ($kw = $kwresults->fetch_assoc()) {
    $kwid = $kw['id'];
    $kwgrp = $kw['groupname'];
    $active = "off";
    if ($kw['active'] == 1) {
        $active = "on";
    }
    $_SESSION['scav_config']["scavemailgroupid-$kwid"] = $kwgrp;
    $_SESSION['scav_config']["scavactive-$kwid"] = $active;
    $keywords[$kwid] = $kw['keyword'];
}
$kwresults->free();

// Get the email groups and put it in a list
$query = "SELECT *
            FROM `{$database['DB_CONF']}`.`emailgroup`
           WHERE `active` = 1
           ORDER BY `id`";
$emailgrquery = $db_link->prepare($query);
$emailgrquery->execute();
$emailgrpresults = $emailgrquery->get_result();
while ($emailgrp = $emailgrpresults->fetch_assoc()) {
    $groupname = $emailgrp['groupname'];
    $_SESSION["emailgrp"][$groupname] = $emailgrp['id'];
}
$emailgrpresults->free();


/*
 * Build the page
 */

/*
var_dump($_SESSION);
echo "<br><br>";
var_dump($_POST);
var_dump($_GET);
*/

?>
<!DOCTYPE HTML>
<html lang="en">
<head>
    <title>Netlog - Configuration panel</title>
    <meta http-equiv="content-type" content="text/html; charset=iso-8859-1">
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <script type="text/javascript" src="scripts/netlog.js"></script>
    <!-- <?php echo "$NAME, $VERSION -- $AUTHOR"; ?> -->
</head>
<body>
<div class="container">
    <div class="header">
        <div class="header_title">Netlog :: <?php echo date('Y-m-d - H:i:s'); ?></div>
        <div class="header_nav">netalert | lograte | <a href="index.php" title="Back to logging">logging</a></div>
        <div class="header_settings">
            <form name="view" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                Category:
                <button type="submit" name="names">Names/Types</button>
                <button type="submit" name="scavenger">Scavenger</button>
                <button type="submit" name="contacts">Contacts</button>
            </form>
        </div>
        <div class="header_toggle">
            <form name="toggleview" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>"><?php echo "\n";
                if ((isset($_SESSION['view'])) && ($_SESSION['view'] == "names")) {
                    ?>
                    Toggle view:
                    <input type="radio" name="toggleview" value="All" title="All items"
                           onClick="this.form.submit()"<?php if ($_SESSION['viewitem'] == "All") {
                        echo " checked";
                    } ?>>All
                    <input type="radio" name="toggleview" value="Unnamed" title="Unnamed items"
                           onClick="this.form.submit()"<?php if ($_SESSION['viewitem'] == "Unnamed") {
                        echo " checked";
                    } ?>>Unnamed
                    <input type="radio" name="toggleview" value="Unused"
                           title="Unused items (no log table exists for these)"
                           onClick="this.form.submit()"<?php if ($_SESSION['viewitem'] == "Unused") {
                        echo " checked";
                    } ?>>Unused
                    <?php
                }
                echo "\n"; ?>
            </form>
        </div>
        <div class="header_sub">
            <?php if ($_SESSION['updated'] == 'true') {
                echo "<div id=\"succMsg\">Update OK!</div>";
            } elseif ($_SESSION['updated'] == 'false') {
                echo "<div id=\"failMsg\">Update failed!</div>";
            }
            unset($_SESSION['updated']) ?>
        </div>
    </div>
    <div class="results">
        <form name="config" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>"><?php
            echo "\n";
            if ((isset($_SESSION['view'])) && ($_SESSION['view'] == "scavenger")) {
                ?>
                <table class="none">
                <tr>
                    <th id="settings">Netalert Scavenger:</th>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                </tr>
                <tr>
                    <th id="settings">Keyword</th>
                    <th id="settings_hostname">Email group</th>
                    <th id="settings_checkbox">Active</th>
                    <th id="settings_checkbox">Delete?</th>
                </tr>
                <?php
                foreach ($keywords as $kwid => $keyword) { ?>
                    <tr>
                        <td>
                            <?php echo $keyword; ?>
                        </td>
                        <td>
                            <select title="Select the email group"
                                    name=<?php echo "\"scavemailgroupid-$kwid\""; ?>> <?php
                                foreach ($_SESSION['emailgrp'] as $groupname => $groupid) {
                                    $group_selected = ($_SESSION['scav_config']["scavemailgroupid-$kwid"] == $groupname ? ' selected' : '');
                                    echo "\n"; ?>
                                    <option value=
                                    <?php echo "\"" . $groupname . "\"" . $group_selected; ?>><?php echo $groupname; ?></option><?php
                                }
                                echo "\n"; ?>
                            </select>
                        </td>
                        <td id="settings_checkbox">
                            <input type="hidden" value="off" name="scavactive-<?php echo $kwid; ?>">
                            <input type="checkbox" title="Disable scavenging"
                                   name=<?php echo "\"scavactive-$kwid\"";
                            if ($_SESSION['scav_config']["scavactive-$kwid"] == 'on') {
                                echo ' checked';
                            } ?>>
                        </td>
                        <td id="settings_checkbox">
                            <input type="hidden" value="off" name="scavdelete-<?php echo $kwid; ?>">
                            <input type="checkbox" title="Delete this entry" name="scavdelete-<?php echo $kwid; ?>">
                        </td>
                    </tr>
                <?php }
                ?>
                <tr>
                    <td>&nbsp;</td>
                </tr>
                <tr>
                    <td>
                        Enter a new keyword:<br/>
                        <input title="Enter a new keyword to scavenge" type="text" name="new_keyword">
                    </td>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                </tr>
                <tr>
                    <td colspan="2">
                        <button type="submit">submit
                        </button>
                    </td>
                </tr>
                </table><?php
            } elseif ((isset($_SESSION['view'])) && ($_SESSION['view'] == "contacts")) {
                ?>
                Contacts dingen
                <?php
            } else {
                ?>
                <table class="none">
                <tr>
                    <th id="settings">Log client hostnames:</th>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                </tr>
                <tr>
                    <th id="settings">IP</th>
                    <th id="settings_hostname">Current Name</th>
                    <th id="settings_hostname">New Name</th>
                    <th id="settings_hosttype">Type</th>
                    <th id="settings_checkbox">Lograte</th><?php if ($_SESSION['viewitem'] == "Unused") {
                        echo "\n                    <th id=\"settings_checkbox\">Delete?</th>\n";
                    } else {
                        echo "\n";
                    } ?>
                </tr><?php
                if ($_SESSION['viewitem'] == "All") {
                    gen_rows_hosts($current_hosts);
                } elseif ($_SESSION['viewitem'] == "Unused") {
                    gen_rows_hosts($unused_hosts);
                } else {
                    // Unnamed
                    gen_rows_hosts($unnamed_hosts);
                }

                ?>
                <tr>
                    <td>
                        &nbsp;
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <button type="submit"<?php if (($_SESSION['viewitem'] == "Unnamed") && (sizeof($unnamed_hosts) == 0)) {
                            echo " disabled";
                        } ?>>submit
                        </button>
                    </td>
                </tr>
                </table><?php
            } ?>
        </form>
    </div>
    <div class="footer">
        <a href="#">Return to top</a>
    </div>
</div>

</body>
</html>
<?php $db_link->close() ?>

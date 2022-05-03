<?php
require(dirname(__DIR__) . "/etc/config.php");
$today = date('Y_m_d');

$session_name = "PHP_NETLOG";

/*
 * Start (or not) session
 */
if (!is_session_started()) {
    session_name($session_name);
    session_start();
}

if (!isset($_SESSION)) {
    echo "No session set or server is not allowing PHP Sessions to be stored?";
    die;
}

/*
 * Check and if not, create database link
 */
if (!isset($db_link)) {
    $db_link = connect_db();
}

/*
 * Some functions
 */
/**
 * Makes the HTML output for the 3 options - all, unnamed, unused.
 * @param array $input
 * @return void
 */
function gen_rows_hosts(array $input)
{
    foreach ($input as $ip) {
        $hostname = $_SESSION['names_config']["hostname-$ip"] ?? '';
        $hosttype = $_SESSION['names_config']["hosttype-$ip"] ?? '';
        if (isset($_SESSION['names_config']["lograte-$ip"])) {
            if ($_SESSION['names_config']["lograte-$ip"] == 'on') {
                $lograte_checked = ' checked';
            } else {
                $lograte_checked = '';
            }
        } else {
            $lograte_checked = ' disabled';
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
                        if ($_SESSION['viewitem'] == 'Unnamed' || $hostname == '') {
                            $hosttype_selected = ($typename == 'Unnamed') ? ' selected' : '';
                        } else {
                            $hosttype_selected = ($hosttype == $typename) ? ' selected' : '';
                        }
                        echo "\n"; ?>
                        <option value=
                        <?php echo "\"" . $typename . "\"" . $hosttype_selected; ?>><?php echo $typename; ?></option><?php
                    }
                    echo "\n"; ?>
                </select>
            </td>
            <td id="settings_checkbox">
                <input type="hidden" value="off" name="lograte-<?php echo $ip; ?>">
                <input type="checkbox" title="Enable or disable lograte"
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
    // Switch views
    if (isset($_POST['names'])) {
        $_SESSION['view'] = "names";
    }
    if (isset($_POST['types'])) {
        $_SESSION['view'] = "types";
    }
    if (isset($_POST['scavenger'])) {
        $_SESSION['view'] = "scavenger";
    }
    if (isset($_POST['contacts'])) {
        $_SESSION['view'] = "contacts";
    }
    if (isset($_POST['global'])) {
        $_SESSION['view'] = "global";
    }
    if (!isset($_SESSION['viewitem'])) {
        $_SESSION['viewitem'] = "Unnamed";
    }
    if (isset($_POST['toggleview'])) {
        $_SESSION['viewitem'] = $_POST['toggleview'];
    }

    /* Turn autocommit off */
    $db_link->autocommit(false);

    // Update existing or insert new items below
    // This could be improved! See GitHub issue #30
    try {
        foreach ($_POST as $key => $value) {
            // Revert what PHP is doing to POST (replace '.' with '_')
            $seskey = str_replace('_', '.', $key);  // so it matches possible $_SESSION keys
            $readkey = explode('-', $key);
            $readseskey = explode('-', $seskey);

            // for checkboxes:
            if ($value == "on") {
                $checkbox = 1;
            } elseif ($value == "off") {
                $checkbox = 0;
            }

            if (isset($_SESSION['names_config'][$seskey])) {
                // Existing host
                if ($_POST[$key] != $_SESSION['names_config'][$seskey]) {
                    $column = $readseskey[0];
                    $hostip = $readseskey[1];

                    if ($column == "hosttype") {
                        $hosttype = $_SESSION['typelist'][$value];
                        $query = "UPDATE `{$database['DB_CONF']}`.`hostnames`
                                     SET $column = ?
                                   WHERE hostip = ?";
                        $updatequery = $db_link->prepare($query);
                        $updatequery->bind_param('ss', $hosttype, $hostip);
                    } elseif ($column == "hostname") {
                        $query = "UPDATE `{$database['DB_CONF']}`.`hostnames`
                                     SET $column = ?
                                   WHERE hostip = ?";
                        $updatequery = $db_link->prepare($query);
                        $updatequery->bind_param('ss', $_POST[$key], $hostip);
                    } elseif ($column == "lograte") {
                        $query = "UPDATE `{$database['DB_CONF']}`.`hostnames`
                                     SET lograte = ?
                                   WHERE hostip = ?";
                        $updatequery = $db_link->prepare($query);
                        $updatequery->bind_param('is', $checkbox, $hostip);
                    } else {
                        continue;
                    }
                    $updatequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^hostname-/', $key)) {
                // Hostname
                if ($value != "") {
                    // A new hostname
                    $hostip = str_replace('_', '.', $readkey[1]);
                    $hosttypekey = 'hosttype-' . $readkey[1];
                    $hosttype = $_SESSION['typelist'][$_POST[$hosttypekey]];

                    $query = "INSERT INTO `{$database['DB_CONF']}`.`hostnames` (`hostip`, `hostname`, `hosttype`)
                                   VALUES (?, ?, ?)";
                    $insertquery = $db_link->prepare($query);
                    $insertquery->bind_param('sss', $hostip, $value, $hosttype);
                    $insertquery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^delete-/', $key)) {
                // Deletion of (unused) configured host
                if ($checkbox == 1) {
                    $hostip = $readseskey[1];
                    $query = "DELETE
                                FROM `{$database['DB_CONF']}`.`hostnames`
                               WHERE `hostip` = ?";
                    $deletequery = $db_link->prepare($query);
                    $deletequery->bind_param('s', $hostip);
                    $deletequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (isset($_SESSION['types_config'][$key])) {
                // Existing host type
                if ($_POST[$key] != $_SESSION['types_config'][$key]) {
                    // Change detected
                    $id = $readkey[1];
                    $query = "UPDATE `{$database['DB_CONF']}`.`hosttype`
                                 SET `name` = ?
                               WHERE `id` = ?";
                    $updatequery = $db_link->prepare($query);
                    $updatequery->bind_param('si', $value, $id);
                    $updatequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^new_hosttype/', $key)) {
                // Host type
                if ($value != "") {
                    $query = "INSERT INTO `{$database['DB_CONF']}`.`hosttype` (name)
                                   VALUES (?)";
                    $insertquery = $db_link->prepare($query);
                    $insertquery->bind_param('s', $value);
                    $insertquery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^typesdelete-/', $key)) {
                // Deletion of configured host type
                if ($checkbox == 1) {
                    $id = $readkey[1];
                    $query = "DELETE FROM `{$database['DB_CONF']}`.`hosttype`
                               WHERE `id` = ?";
                    $deletequery = $db_link->prepare($query);
                    $deletequery->bind_param('i', $id);
                    $deletequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (isset($_SESSION['email_config'][$key])) {
                // Existing email contact group
                if ($_POST[$key] != $_SESSION['email_config'][$key]) {
                    // Change detected
                    $column = str_replace('group', '', $readkey[0]);
                    $id = $readkey[1];
                    $query = "UPDATE `{$database['DB_CONF']}`.`emailgroup`
                                     SET $column = ?
                                   WHERE `id` = ?";
                    $updatequery = $db_link->prepare($query);
                    if ($column == "active") {
                        $updatequery->bind_param('si', $checkbox, $id);
                    } else {
                        $updatequery->bind_param('si', $value, $id);
                    }
                    $updatequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^new_group/', $key)) {
                // Contact group
                if ($value != "" && $_POST['new_recipients'] != "") {
                    // Add a new group
                    $name = $value;
                    $recipients = $_POST['new_recipients'];

                    $query = "INSERT INTO `{$database['DB_CONF']}`.`emailgroup` (groupname, recipients)
                                   VALUES (?, ?)";
                    $insertquery = $db_link->prepare($query);
                    $insertquery->bind_param('ss', $name, $recipients);
                    $insertquery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^groupdelete/', $key)) {
                // Deletion of configured group
                if ($checkbox == 1) {
                    $id = $readkey[1];
                    $query = "DELETE
                                FROM `{$database['DB_CONF']}`.`emailgroup`
                               WHERE `id` = ?";
                    $deletequery = $db_link->prepare($query);
                    $deletequery->bind_param('s', $id);
                    $deletequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (isset($_SESSION['scav_config'][$key])) {
                // Existing scavenger keyword
                if ($_POST[$key] != $_SESSION['scav_config'][$key]) {
                    // Change detected;
                    $kwid = $readkey[1];
                    $column = str_replace('scav', '', $readkey[0]);
                    if ($column == "emailgroupid") {
                        $grpid = $_SESSION['emailgrp'][$_POST[$key]];
                        $query = "UPDATE `{$database['DB_CONF']}`.`logscavenger`
                                     SET `emailgroupid` = ?
                                   WHERE `id` = ?";
                        $updatequery = $db_link->prepare($query);
                        $updatequery->bind_param('si', $grpid, $kwid);
                    } elseif ($column == "active") {
                        $query = "UPDATE `{$database['DB_CONF']}`.`logscavenger`
                                     SET `active` = ?
                                   WHERE `id` = ?";
                        $updatequery = $db_link->prepare($query);
                        $updatequery->bind_param('ii', $checkbox, $kwid);
                    }
                    $updatequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^new_keyword/', $key)) {
                // Scavenger keyword
                if ($value != "") {
                    // Add a new keyword
                    $query = "INSERT INTO `{$database['DB_CONF']}`.`logscavenger` (keyword, active, emailgroupid)
                                   VALUES (?, 1, 1)";
                    $insertquery = $db_link->prepare($query);
                    $insertquery->bind_param('s', $value);
                    $insertquery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^scavdelete/', $key)) {
                // Deletion of configured scavenger keyword
                if ($checkbox == 1) {
                    $kwid = $readkey[1];
                    $query = "DELETE
                                FROM `{$database['DB_CONF']}`.`logscavenger`
                               WHERE `id` = ?";
                    $deletequery = $db_link->prepare($query);
                    $deletequery->bind_param('s', $kwid);
                    $deletequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (preg_match('/^global-/', $key)) {
                // Global settings
                if ($value != $_SESSION['globalset'][$readkey[1]]) {
                    // Change in a setting found

                    $setting = $readkey[1];

                    // Fix, for now
                    if ($setting == 'default_view') {
                        $value = $_SESSION['typelist'][$value];
                    }
                    if ($value == $_SESSION['globalset'][$readkey[1]]) {
                        // no change
                        continue;
                    } elseif ($value != "") {
                        $query = "UPDATE `{$database['DB_CONF']}`.`global`
                                     SET `value` = ?
                                   WHERE `setting` = ?";
                        $updatequery = $db_link->prepare($query);
                        $updatequery->bind_param('ss', $value, $setting);

                        $updatequery->execute();

                        $_SESSION['updated'] = 'true';
                    }
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
unset($_SESSION['hosttypes']);
unset($_SESSION['emailgrp']);
unset($_SESSION['globalsetting']);
unset($query, $hostnameresult, $tablesresult, $typeresult, $kwresults, $emailgrpresults, $globalsetresult);

// Set blanks (if no entry in DB exists)
$current_hosts = array();
$logging_hosts = array();
$unused_hosts = array();
$unnamed_hosts = array();

// Get the IP-adresses and their hostname and type and lograte
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
    $lograte = ($dbhostnames['lograte'] == 1) ? 'on' : 'off';

    // If we already have an entry, don't overwrite it (in case of multiple records...)
    if (!isset($_SESSION['names_config']["hostname-$hostnameip"])) {
        $_SESSION['names_config']["hostname-$hostnameip"] = $dbhostnames['hostname'];
        $_SESSION['names_config']["hosttype-$hostnameip"] = $dbhostnames['name'];
        $_SESSION['names_config']["lograte-$hostnameip"] = $lograte;
    }
}
$hostnameresult->free_result();

// Get all the table names
$query = "SELECT TABLE_NAME AS tblnm
            FROM INFORMATION_SCHEMA.TABLES
           WHERE TABLE_SCHEMA = '{$database['DB']}'";
$tablesquery = $db_link->prepare($query);
$tablesquery->execute();
$tablesresult = $tablesquery->get_result();

// Throw all ip parts of table names in an array
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
$tablesresult->free_result();

// Merge and diff lists
natsort($current_hosts);
$current_hosts = array_unique(array_merge($current_hosts, $logging_hosts));
$unused_hosts = array_diff($current_hosts, $logging_hosts);

foreach ($current_hosts as $ip) {
    if (!isset($_SESSION['names_config']["hostname-$ip"])) {
        $unnamed_hosts[] = $ip;
    }
}

// Make arrays of hosttypes for the selection box
$query = "SELECT `id`, `name`
            FROM `{$database['DB_CONF']}`.`hosttype`
           ORDER BY `name`";
$typequery = $db_link->prepare($query);
$typequery->execute();
$typeresult = $typequery->get_result();

while ($types = $typeresult->fetch_assoc()) {
    $type_id = $types['id'];
    $_SESSION['typelist'][$types['name']] = $type_id;
    $_SESSION['hosttypes'][$type_id] = $types['name'];
    $_SESSION['types_config']["types-$type_id"] = $types['name'];
}
$typeresult->free_result();

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
    $active = ($kw['active'] == 1) ? 'on' : 'off';
    $_SESSION['scav_config']["scavemailgroupid-$kwid"] = $kwgrp;
    $_SESSION['scav_config']["scavactive-$kwid"] = $active;
    $keywords[$kwid] = $kw['keyword'];
}
$kwresults->free_result();

// Get the email groups and put it in a list
$query = "SELECT *
            FROM `{$database['DB_CONF']}`.`emailgroup`
           ORDER BY `id`";
$emailgrquery = $db_link->prepare($query);
$emailgrquery->execute();
$emailgrpresults = $emailgrquery->get_result();
$emailgroups = array();
while ($emailgrp = $emailgrpresults->fetch_assoc()) {
    $emailgroups[] = $emailgrp;
    $id = $emailgrp['id'];
    $groupname = $emailgrp['groupname'];
    $recipients = $emailgrp['recipients'];
    $active = ($emailgrp['active'] == 1) ? 'on' : 'off';
    // Make list of groupnames for the selection box
    $_SESSION['emailgrp'][$groupname] = $emailgrp['id'];
    // Make a list of configurated items
    $_SESSION['email_config']["groupname-$id"] = $groupname;
    $_SESSION['email_config']["grouprecipients-$id"] = $recipients;
    $_SESSION['email_config']["groupactive-$id"] = $active;
}
$emailgrpresults->free_result();

// Get the default (global) settings and put it in a list
$query = "SELECT *
            FROM `{$database['DB_CONF']}`.`global`
           ORDER BY `setting`";
$globalsetgrquery = $db_link->prepare($query);
$globalsetgrquery->execute();
$globalsetresult = $globalsetgrquery->get_result();
while ($row = $globalsetresult->fetch_assoc()) {
    $setting = $row['setting'];
    if ($row['setting'] == 'default_view') {
        $value = $_SESSION['hosttypes'][$row['value']];
    } else {
        $value = $row['value'];
    }
    $_SESSION['globalset'][$setting] = $value;
}
$globalsetresult->free_result();

// Set CSS id for category button
$names_view = ($_SESSION['view'] == "names") ? 'id="button_active"' : '';
$types_view = ($_SESSION['view'] == "types") ? ' id="button_active"' : '';
$scavenger_view = ($_SESSION['view'] == "scavenger") ? ' id="button_active"' : '';
$contacts_view = ($_SESSION['view'] == "contacts") ? ' id="button_active"' : '';
$global_view = ($_SESSION['view'] == "global") ? ' id="button_active"' : '';

/*
 * Build the page
 */
?>
    <!DOCTYPE HTML>
    <html lang="en">
    <head>
        <title>Netlog - Configuration panel</title>
        <meta http-equiv="content-type" content="text/html; charset=iso-8859-1">
        <link rel="stylesheet" type="text/css" href="css/style.css">
        <script type="text/javascript" src="scripts/netlog.js"></script>
        <!-- <?php echo constant('NAME') . ", " . constant('VERSION') . " -- " . constant('AUTHOR'); ?> -->
    </head>
    <body>
    <div class="container">
        <div class="header">
            <div class="header_title">Netlog :: <?php echo date('Y-m-d - H:i:s'); ?></div>
            <div class="header_nav">
                <a href="netalert.php?inline" title="NetAlert">netalert</a> |
                <a href="viewlograte.php" title="Logrates">lograte</a> |
                config |
                <a href="index.php" title="Back to logging">logging</a>
            </div>
            <div class="header_settings">
                <form name="view" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    Category:
                    <button type="submit" <?php echo $names_view; ?> name="names">Host names</button>
                    <button type="submit" <?php echo $types_view; ?> name="types">Host types</button>
                    <button type="submit" <?php echo $scavenger_view; ?> name="scavenger">Scavenger</button>
                    <button type="submit" <?php echo $contacts_view; ?> name="contacts">Contacts</button>
                    <button type="submit" <?php echo $global_view; ?> name="global">Global</button>
                </form>
            </div>
            <div class="header_toggle">
                <form name="toggleview" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>"><?php echo "\n";
                    if ($_SESSION['view'] == "names") {
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
                unset($_SESSION['updated']); ?>
            </div>
        </div>
        <div class="results">
            <form name="config" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>"><?php
                echo "\n";
                if ($_SESSION['view'] == "scavenger") {
                    //Logscavenger
                    ?>
                    <table class="none">
                        <tr>
                            <th id="settings">Netalert Scavenger:</th>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <th id="settings_keyword">Keyword</th>
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
                                    <input type="checkbox" title="Enable or disable scavenging"
                                           name=<?php echo "\"scavactive-$kwid\"";
                                    if ($_SESSION['scav_config']["scavactive-$kwid"] == 'on') {
                                        echo ' checked';
                                    } ?>>
                                </td>
                                <td id="settings_checkbox">
                                    <input type="hidden" value="off" name="scavdelete-<?php echo $kwid; ?>">
                                    <input type="checkbox" title="Delete this entry"
                                           name="scavdelete-<?php echo $kwid; ?>">
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
                    </table>
                    <?php
                } elseif ($_SESSION['view'] == "contacts") {
                    // Email contacts
                    ?>
                    <table class="none">
                        <tr>
                            <th id="settings">Contacts:</th>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <th id="settings_hostname">Groupname</th>
                            <th id="settings_hostname">Recipients</th>
                            <th id="settings_checkbox">Active</th>
                            <th id="settings_checkbox">Delete?</th>
                        </tr>
                        <?php
                        foreach ($emailgroups as $row) {
                            $id = $row['id'];
                            $rec = $row['recipients'];
                            $active = ($row['active'] == 1) ? " checked" : "";
                            if ($row['groupname'] == "None") {
                                continue;
                            }
                            ?>
                            <tr>
                                <td><?php echo $row['groupname']; ?></td>
                                <td><input id="settings_input_hostname" type="text" title="Recipients, comma separated"
                                           name="grouprecipients-<?php echo $id; ?>"
                                           value=<?php echo "\"" . $rec . "\"" . $disabled; ?>>
                                </td>
                                <td id="settings_checkbox">
                                    <input type="hidden" value="off" name="groupactive-<?php echo $row['id']; ?>">
                                    <input type="checkbox" title="Enable or disable this group"
                                           name="groupactive-<?php echo $row['id']; ?>" <?php echo $active; ?>>
                                </td>
                                <td id="settings_checkbox">
                                    <input type="hidden" value="off" name="groupdelete-<?php echo $row['id']; ?>">
                                    <input type="checkbox" title="Delete this group"
                                           name="groupdelete-<?php echo $row['id']; ?>">
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td>
                                Enter a new group:<br/>
                                <input title="Enter a new group" type="text" name="new_group">
                            </td>
                            <td>
                                Enter one or more recipients:<br/>
                                <input id="settings_input_hostname" title="Enter recipients, comma separated"
                                       type="text"
                                       name="new_recipients">
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


                    </table>
                    <?php
                } elseif ($_SESSION['view'] == "global") {
                    // Global settings
                    ?>
                    <table class="none">
                        <tr>
                            <th id="settings">Global settings:</th>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <th id="settings_hostname">Setting</th>
                            <th id="settings_hostname">Value</th>
                        </tr>
                        <?php
                        foreach ($_SESSION['globalset'] as $setting => $value) {
                            ?>
                            <tr>
                                <td><?php echo $setting; ?></td>
                                <?php
                                // Make a select box
                                if ($setting == 'default_view') { ?>
                                    <td><select id="settings_select_global" title="Select default type"
                                                name="global-<?php echo $setting; ?>">
                                        <?php
                                        foreach ($_SESSION['typelist'] as $typename => $typeid) {
                                            $hosttype_selected = ($value == $typename ? ' selected' : '');
                                            echo "\n"; ?>
                                            <option value=
                                            <?php echo "\"" . $typename . "\"" . $hosttype_selected; ?>><?php echo $typename; ?></option><?php
                                        } ?>
                                    </select></td><?php
                                } else {
                                    ?>
                                    <td><input id="settings_input_global" type="text" title="Value for setting"
                                               name="global-<?php echo $setting; ?>" value="<?php echo $value; ?>">
                                    </td>
                                <?php } ?>
                            </tr>
                            <?php
                        }
                        ?>
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td colspan="2">
                                <button type="submit">submit
                                </button>
                            </td>
                        </tr>
                    </table>
                    <?php
                } elseif ($_SESSION['view'] == "types") {
                    // Host types
                    ?>
                    <table class="none">
                        <tr>
                            <th id="settings">Host types:</th>
                        </tr>
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <th id="settings_hostname">Current Name</th>
                            <th id="settings_hostname">New Name</th>
                            <th id="settings_checkbox">Delete?</th>
                        </tr>
                        <?php
                        foreach ($_SESSION['hosttypes'] as $id => $name) {
                            $disabled = ($id == "1") ? " disabled" : "";
                            ?>
                            <tr>
                                <td><?php echo $name; ?></td>
                                <td>
                                    <input id="settings" type="text" title="Value for setting"
                                           name="types-<?php echo $id; ?>" value="<?php echo $name; ?>">
                                </td>
                                <td id="settings_checkbox">
                                    <input type="hidden" value="off" name="typesdelete-<?php echo $id; ?>">
                                    <input type="checkbox" title="Delete this host type"
                                           name="typesdelete-<?php echo $id; ?>"<?php echo $disabled; ?>>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                        <tr>
                            <td>&nbsp;</td>
                        </tr>
                        <tr>
                            <td>
                                Enter a new type:<br/>
                                <input title="Enter a new type for hosts" type="text" name="new_hosttype">
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
                    </table>
                    <?php
                } else {
                    // Host names
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
                    </table>
                    <?php
                }
                echo "\n"; ?>
            </form>
            <?php // codedebug(); ?>
        </div>
    </div>

    </body>
    </html>
<?php $db_link->close();

<?php
require(dirname(__DIR__) . "/etc/global.php");

/*
 * Some functions
 */

/**
 * Makes the HTML output for the 3 options - all, unnamed, unused.
 * @param array $input
 * @return void
 */
function gen_rows_hosts(array $input): void
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
            $checkbox = ($value == "on") ? 1 : 0;

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
            } elseif (str_starts_with($key, 'hostname-')) {
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
            } elseif (str_starts_with($key, 'delete-')) {
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
            } elseif (str_starts_with($key, 'new_hosttype')) {
                // Host type
                if ($value != "") {
                    $query = "INSERT INTO `{$database['DB_CONF']}`.`hosttype` (name)
                                   VALUES (?)";
                    $insertquery = $db_link->prepare($query);
                    $insertquery->bind_param('s', $value);
                    $insertquery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (str_starts_with($key, 'typesdelete-')) {
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
            } elseif (isset($_SESSION['scav_config'][$key])) {
                // Existing scavenger keyword
                if ($_POST[$key] != $_SESSION['scav_config'][$key]) {
                    // Change detected;
                    $kwid = $readkey[1];
                    $column = str_replace('scav', '', $readkey[0]);
                    if ($column == "active") {
                        $query = "UPDATE `{$database['DB_CONF']}`.`logscavenger`
                                     SET `active` = ?
                                   WHERE `id` = ?";
                        $updatequery = $db_link->prepare($query);
                        $updatequery->bind_param('ii', $checkbox, $kwid);
                    }
                    $updatequery->execute();

                    $_SESSION['updated'] = 'true';
                }
            } elseif (str_starts_with($key, 'new_keyword')) {
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
            } elseif (str_starts_with($key, 'scavdelete')) {
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
            } elseif (str_starts_with($key, 'global-')) {
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
                        $valid = false;
                        switch ($setting) {
                            // validate some settings

                            // These needs to be formatted like 'int,int,int' or a single 'int'
                            // or, with 'off' in it, for the refresh
                            case "lograte_history":
                            case "refresh":
                            case "show_lines":
                                if (preg_match('/^(off,)?[0-9]+(,[0-9]+)+$/', $value)) {
                                    $valid = true;
                                }
                                break;
                            // These needs to be an integer.
                            case "logarchive_interval":
                            case "lograte_days":
                            case "lograte_graph_height":
                            case "lograte_graph_width":
                            case "netalert_show_lines":
                            case "netalert_time_threshold":
                            case "netalert_to_nms":
                            case "retention":
                            case "scavenger_history":
                                if (preg_match('/^[0-9]+$/', $value)) {
                                    $valid = true;
                                }
                                break;

                            default:
                                $valid = true;
                        }
                        if ($valid) {
                            $query = "UPDATE `{$database['DB_CONF']}`.`global`
                                         SET `value` = ?
                                       WHERE `setting` = ?";
                            $updatequery = $db_link->prepare($query);
                            $updatequery->bind_param('ss', $value, $setting);

                            $updatequery->execute();

                            $_SESSION['updated'] = 'true';
                        } else {
                            $_SESSION['updated'] = 'false';
                        }
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

$today = date('Y_m_d');

/*
 * Fetch data from DB and populate vars/arrays
 */

// Clean, should do nothing, but hey
unset($_SESSION['names_config']);
unset($_SESSION['scav_config']);
unset($_SESSION['typelist']);
unset($_SESSION['hosttypes']);
unset($_SESSION['globalsetting']);
unset($query, $hostnameresult, $tablesresult, $typeresult, $kwresults, $globalsetresult);

// Set blanks (if no entry in DB exists)
$current_hosts = array();
$logging_hosts = array();
$unused_hosts = array();
$unnamed_hosts = array();

// Get the IP-adresses and their hostname and type and lograte
try {
    $query = "SELECT `hostip`, `hostname`, `name`, `lograte`
                FROM `{$database['DB_CONF']}`.`hostnames`
                LEFT JOIN `{$database['DB_CONF']}`.`hosttype`
                     ON (`{$database['DB_CONF']}`.`hostnames`.`hosttype`=`{$database['DB_CONF']}`.`hosttype`.`id`)
               ORDER BY `hostip`, `hosttype` DESC";
    $hostnamequery = $db_link->prepare($query);
    $hostnamequery->execute();
    $hostnameresult = $hostnamequery->get_result();
    if (!$hostnameresult->num_rows >= 1) {
        throw new mysqli_sql_exception();
    }
} catch (Exception|Error $e) {
    die("Could not retreive any host config" . err($e));
}

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
try {
    $query = "SELECT `TABLE_NAME` AS `tblnm`
                FROM `INFORMATION_SCHEMA`.`TABLES`
               WHERE `TABLE_SCHEMA` = '{$database['DB']}'";
    $tablesquery = $db_link->prepare($query);
    $tablesquery->execute();
    $tablesresult = $tablesquery->get_result();
    if (!$tablesresult->num_rows >= 1) {
        throw new mysqli_sql_exception();
    }
} catch (Exception|Error $e) {
    die("Could not retreive any host tables" . err($e));
}

// Throw all ip parts of table names in an array
while ($lines = $tablesresult->fetch_assoc()) {
    if (str_contains($lines['tblnm'], "template") || str_contains($lines['tblnm'], "HST_UHO")) {
        continue;
    }
    $thishost = explode('_DATE_', $lines['tblnm']);
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
try {
    $query = "SELECT `id`, `name`
                FROM `{$database['DB_CONF']}`.`hosttype`
               ORDER BY `name`";
    $typequery = $db_link->prepare($query);
    $typequery->execute();
    $typeresult = $typequery->get_result();
    if (!$typeresult->num_rows >= 1) {
        throw new mysqli_sql_exception();
    }
} catch (Exception|Error $e) {
    die("Could not retreive any host types" . err($e));
}
while ($types = $typeresult->fetch_assoc()) {
    $type_id = $types['id'];
    $_SESSION['typelist'][$types['name']] = $type_id;
    $_SESSION['hosttypes'][$type_id] = $types['name'];
    $_SESSION['types_config']["types-$type_id"] = $types['name'];
}
$typeresult->free_result();

// Get the scavenger keywords
try {
    $query = "SELECT `logscavenger`.`id`, `keyword`, `logscavenger`.`active`, `emailgroupid`, `groupname`
                FROM `{$database['DB_CONF']}`.`logscavenger`
                LEFT JOIN `{$database['DB_CONF']}`.`emailgroup`
                     ON (`{$database['DB_CONF']}`.`logscavenger`.`emailgroupid`=`{$database['DB_CONF']}`.`emailgroup`.`id`)
               ORDER BY `{$database['DB_CONF']}`.`logscavenger`.`id`";
    $kwquery = $db_link->prepare($query);
    $kwquery->execute();
    $kwresults = $kwquery->get_result();
    if (!$kwresults->num_rows >= 1) {
        throw new mysqli_sql_exception();
    }
} catch (Exception|Error $e) {
    die("Could not retreive any logscavenger keywords" . err($e));
}
$keywords = array();
while ($kw = $kwresults->fetch_assoc()) {
    $kwid = $kw['id'];
    $kwgrp = $kw['groupname'];
    $active = ($kw['active'] == 1) ? 'on' : 'off';
    $_SESSION['scav_config']["scavactive-$kwid"] = $active;
    $keywords[$kwid] = $kw['keyword'];
}
$kwresults->free_result();

// Get the default (global) settings and put it in a list
try {
    $query = "SELECT *
                FROM `{$database['DB_CONF']}`.`global`
               ORDER BY `setting`";
    $globalsetgrquery = $db_link->prepare($query);
    $globalsetgrquery->execute();
    $globalsetresult = $globalsetgrquery->get_result();
    if (!$globalsetresult->num_rows >= 1) {
        throw new mysqli_sql_exception();
    }
} catch (Exception|Error $e) {
    die("Could not retreive global configuration" . err($e));
}
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
        <link rel="stylesheet" type="text/css" href="<?php echo $basepath; ?>css/style.css">
        <script type="text/javascript" src="<?php echo $basepath; ?>scripts/netlog.js"></script>
        <!-- <?php echo constant('NAME') . ", " . constant('VERSION') . " -- " . constant('AUTHOR'); ?> -->
    </head>
    <body>
    <div class="container">
        <div class="header">
            <div class="header_title">Netlog :: <?php echo date('Y-m-d - H:i:s'); ?></div>
            <div class="header_nav">
                <a href="<?php echo $basepath; ?>netalert.php?inline" title="NetAlert">netalert</a> |
                <a href="<?php echo $basepath; ?>viewlograte.php" title="Logrates">lograte</a> |
                config |
                <a href="<?php echo $basepath; ?>index.php" title="Back to logging">logging</a>
            </div>
            <div class="header_settings">
                <form name="view" method="post" action="<?php echo $basepath; ?>settings.php">
                    Category:
                    <button type="submit" <?php echo $names_view; ?> name="names">Host names</button>
                    <button type="submit" <?php echo $types_view; ?> name="types">Host types</button>
                    <button type="submit" <?php echo $scavenger_view; ?> name="scavenger">Scavenger</button>
                    <button type="submit" <?php echo $global_view; ?> name="global">Global</button>
                </form>
            </div>
            <div class="header_toggle">
                <form name="toggleview" method="post"
                      action="<?php echo $basepath; ?>settings.php"><?php echo "\n";
                    if ($_SESSION['view'] == "names") {
                        ?>
                        Toggle view:
                        <input type="radio" name="toggleview" value="All" title="All items"
                               onClick="this.form.submit()"<?php if ($_SESSION['viewitem'] == "All") echo " checked"; ?>>All
                        <input type="radio" name="toggleview" value="Unnamed" title="Unnamed items"
                               onClick="this.form.submit()"<?php if ($_SESSION['viewitem'] == "Unnamed") echo " checked"; ?>>Unnamed
                        <input type="radio" name="toggleview" value="Unused"
                               title="Unused items (no log table exists for these)"
                               onClick="this.form.submit()"<?php if ($_SESSION['viewitem'] == "Unused") echo " checked"; ?>>Unused
                        <?php
                    }
                    echo "\n"; ?>
                </form>
            </div>
            <div class="header_sub">
                <?php if ($_SESSION['updated'] == 'true') echo "<div id=\"succMsg\">Update OK!</div>";
                elseif ($_SESSION['updated'] == 'false') echo "<div id=\"failMsg\">Update failed!</div>";
                unset($_SESSION['updated']); ?>
            </div>
        </div>
        <div class="results">
            <form name="config" method="post" action="<?php echo $basepath; ?>settings.php"><?php
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
                            <th id="settings_checkbox">Active</th>
                            <th id="settings_checkbox">Delete?</th>
                        </tr>
                        <?php
                        foreach ($keywords as $kwid => $keyword) { ?>
                            <tr>
                                <td>
                                    <?php echo $keyword; ?>
                                </td>
                                <td id="settings_checkbox">
                                    <input type="hidden" value="off" name="scavactive-<?php echo $kwid; ?>">
                                    <input type="checkbox" title="Enable or disable scavenging"
                                           name=<?php echo "\"scavactive-$kwid\"";
                                    if ($_SESSION['scav_config']["scavactive-$kwid"] == 'on') echo " checked"; ?>>
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
                                } elseif ($setting == 'lograte_history_default') { ?>
                                    <td><select id="settings_select_global" title="Select default history"
                                                name="global-<?php echo $setting; ?>">
                                        <?php
                                        foreach ($graph_history as $history) {
                                            $history_selected = ($value == $history ? ' selected' : '');
                                            echo "\n"; ?>
                                            <option value=
                                            <?php echo "\"" . $history . "\"" . $history_selected; ?>><?php echo $history; ?></option><?php
                                        } ?>
                                    </select></td><?php
                                } elseif ($setting == 'show_lines_default') { ?>
                                    <td><select id="settings_select_global" title="Select default show lines"
                                                name="global-<?php echo $setting; ?>">
                                        <?php
                                        foreach ($showlines as $lines) {
                                            $lines_selected = ($value == $lines ? ' selected' : '');
                                            echo "\n"; ?>
                                            <option value=
                                            <?php echo "\"" . $lines . "\"" . $lines_selected; ?>><?php echo $lines; ?></option><?php
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
                            <td id="settings">&nbsp;</td>
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
                                <button type="submit"<?php
                                if ($_SESSION['viewitem'] == "Unnamed") {
                                    $disabled = (sizeof($unnamed_hosts) == 0) ?? ' disabled';
                                } elseif ($_SESSION['viewitem'] == "Unused") {
                                    $disabled = (sizeof($unused_hosts) == 0) ?? ' disabled';
                                } else {
                                    $disabled = '';
                                }
                                echo $disabled;
                                ?>>submit
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

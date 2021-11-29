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
$db_link = new mysqli($db_HOST, $db_USER, $db_PASS, $db_NAME);
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    die;
}
if (!$db_link->select_db($db_NAME)) {
    printf("Unable to select DB: %s\n", mysqli_connect_error());
    die;
}


/*
 * Some functions
 */
function gen_rows_hosts($input)
{
    foreach ($input as $ip) {
        $hostname = $_SESSION['config']["hostname-$ip"] ?? '';
        $hosttype = $_SESSION['config']["hosttype-$ip"] ?? '';
        $lograte_checked = ($_SESSION['config']["lograte-$ip"] == 1 ? ' checked' : '');
        $unused_disable = ($_SESSION['viewitem'] == "Unused" ? ' disabled' : ''); ?>
        <tr>
            <td><?php echo $ip; ?></td>
            <td style="width: 200px"><?php echo $hostname; ?></td>
            <td>
                <input type="text" name="hostname-<?php echo $ip; ?>" title="A decent name"
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
            <td style="text-align: center; width: 60px">
                <input type="hidden" value=0 name="lograte-<?php echo $ip; ?>">
                <input type="checkbox" title="Disable lograte"
                       name=<?php echo "\"lograte-$ip\"" . $lograte_checked; ?>>
            </td>
            <?php if ($_SESSION['viewitem'] == "Unused") { ?>
                <td style="text-align: center; width: 60px">
                <input type="hidden" value=0 name="delete-<?php echo $ip; ?>">
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
            $seskey = str_replace('_', '.', $key);

            // Existing host
            if (isset($_SESSION['config']["$seskey"])) {
                if ($_POST["$key"] != $_SESSION['config']["$seskey"]) {
                    //FIXME: '$readykey' not used?
                    $readkey = explode('-', $seskey);
                    $column = $readkey['0'];
                    $hostip = $readkey['1'];

                    if ($column == "delete") {
                        // Delete must be on top. Other updates will be futile
                        $query = "DELETE
                                    FROM netlogconfig.hostnames
                                   WHERE hostip = '$hostip'";
                    } elseif ($column == "hosttype") {
                        $query = "UPDATE netlogconfig.hostnames
                                     SET $column = '" . $_SESSION['typelist'][$value] . "'
                                   WHERE hostip = '$hostip'";
                    } elseif ($column == "hostname") {
                        $query = "UPDATE netlogconfig.hostnames
                                     SET $column = ?
                                   WHERE hostip = '$hostip'";
                    } elseif ($column == "lograte") {
                        if ($value == "on") {
                            $lograte = 1;
                        } else {
                            $lograte = 0;
                        }
                        $query = "UPDATE netlogconfig.hostnames
                                     SET lograte = $lograte
                                   WHERE hostip = '$hostip'";
                    }
                    $updatequery = $db_link->prepare($query);
                    if ($column == "hostname") {
                        $updatequery->bind_param('s', $_POST["$key"]);
                    }
                    $updatequery->execute();

                    $_SESSION['updated'] = 'true';
                }
                // New host
            } elseif (preg_match('/hostname-/', $key)) {
                if ($value != "") {
                    $readkey = explode('-', $key);
                    $hostip = str_replace('_', '.', $readkey['1']);
                    $hosttypekey = 'hosttype-' . $readkey['1'];
                    $hosttype = $_SESSION['typelist'][$_POST[$hosttypekey]];

                    $query = "INSERT INTO netlogconfig.hostnames (hostip, hostname, hosttype)
                                   VALUES ('$hostip', ?, '$hosttype')";
                    $insertquery = $db_link->prepare($query);
                    $insertquery->bind_param('s', $value);
                    $insertquery->execute();

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
unset($query, $result);
unset($_SESSION['config']);

// Set blanks (if no entry in DB exists)
$current_hosts = array();
$logging_hosts = array();
$unused_hosts = array();
$unnamed_hosts = array();

$query = "SELECT `hostip`, `hostname`, `name`, `lograte`
            FROM netlogconfig.hostnames
            LEFT JOIN netlogconfig.hosttype
                 ON (netlogconfig.hostnames.hosttype=netlogconfig.hosttype.id)
           ORDER BY `hostip`, `hosttype` DESC";
$hostnamequery = $db_link->prepare($query);
$hostnamequery->execute();
$hostnameresult = $hostnamequery->get_result();

// Throw all config parts of hosts in an array
while ($dbhostnames = $hostnameresult->fetch_assoc()) {
    $hostnameip = $dbhostnames['hostip'];
    $current_hosts[] = $hostnameip;

    // If we already have an entry, don't overwrite it (in case of multiple records...)
    if (!isset($_SESSION['config']["hostname-$hostnameip"])) {
        $_SESSION['config']["hostname-$hostnameip"] = $dbhostnames['hostname'];
        $_SESSION['config']["hosttype-$hostnameip"] = $dbhostnames['name'];
        if (isset($dbhostnames['lograte'])) {
            $_SESSION['config']["lograte-$hostnameip"] = $dbhostnames['lograte'];
        } else {
            $_SESSION['config']["lograte-$hostnameip"] = 0;
        }
    }
}
$hostnameresult->free();

$query = "SELECT TABLE_NAME AS tblnm
            FROM INFORMATION_SCHEMA.TABLES
           WHERE TABLE_SCHEMA = '$db_NAME'";
$tablesquery = $db_link->prepare($query);
$tablesquery->execute();
$tablesresult = $tablesquery->get_result();

// Throw all ip parts of tables in an array
while ($lines = $tablesresult->fetch_array(MYSQLI_NUM)) {
    if (strpos($lines['0'], "template") !== false || strpos($lines['0'], "UHO") !== false || strpos($lines['0'], "criteria") !== false) {
        continue;
    }
    $thishost = explode('_DATE_', $lines['0']);
    $host = trim($thishost['0'], 'HST_');
    $ip = str_replace('_', '.', $host);
    $hostdaylist["$ip"][] = $thishost['1'];
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
    if (!isset($_SESSION['config']["hostname-$ip"])) {
        $unnamed_hosts[] = $ip;
    }
}

// Make an array for the selection box
unset($_SESSION['typelist']);
$query = "SELECT id, name
            FROM netlogconfig.hosttype
           ORDER BY name";
$typequery = $db_link->prepare($query);
$typequery->execute();
$typeresult = $typequery->get_result();

while ($types = $typeresult->fetch_assoc()) {
    $_SESSION['typelist'][$types['name']] = $types['id'];
}
$typeresult->free();


/*
 * Build the page
 */

/*
var_dump($_SESSION);
echo "<br>";
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
        <div class="header_device">
            <form name="view" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                Category:
                <button type="submit" name="names">Names/Types</button>
                <button type="submit" name="scavenger">Scavenger</button>
                <button type="submit" name="contacts">Contacts</button>
            </form>
        </div>
        <div class="header_search">
            <form name="toggleview" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>"><?php echo "\n";
                if ((isset($_SESSION['view'])) && ($_SESSION['view'] == "names")) {
                    ?>
                    Toggle view:
                    <input type="radio" name="toggleview" value="All" title="All items"
                           onClick="this.form.submit()"<?php if ($_SESSION['viewitem'] == "All") {
                        echo "checked";
                    } ?>>All
                    <input type="radio" name="toggleview" value="Unnamed" title="Unnamed items"
                           onClick="this.form.submit()"<?php if ($_SESSION['viewitem'] == "Unnamed") {
                        echo "checked";
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
    <div class="results"><?php
        if ((isset($_SESSION['view'])) && ($_SESSION['view'] == "contacts")) {
            ?>
            Contacts dingen
            <?php
        } elseif ((isset($_SESSION['view'])) && ($_SESSION['view'] == "scavenger")) {
            ?>
            Scavenger dingen
            <?php
        } else {
            echo "\n"; ?>
        <form name="config" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <table class="none">
                <tr>
                    <th>Log client hostnames:</th>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                </tr>
                <tr>
                    <th>IP</th>
                    <th>Current Name</th>
                    <th>New Name</th>
                    <th>Type</th>
                    <th>Lograte</th><?php if ($_SESSION['viewitem'] == "Unused") {
                        echo "\n                    <th>Delete?</th>\n";
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
                        <button type="submit">submit</button>
                    </td>
                </tr>
            </table>
            </form><?php
        } ?>
    </div>
    <div class="footer">
        <a href="#">Return to top</a>
    </div>
</div>

</body>
</html>
<?php $db_link->close() ?>


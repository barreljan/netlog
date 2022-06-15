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
    die("No session set or server is not allowing PHP Sessions to be stored?");
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
 * Compare dates. To be used as a callback function for usort
 * @param string $a
 * @param string $b
 * @return string
 */
function date_compare(string $a, string $b): string
{
    $t1 = strtotime(str_replace('_', '-', $b));
    $t2 = strtotime(str_replace('_', '-', $a));
    return $t1 - $t2;
}

/**
 * Set the basic settings to default for clearing the page.
 * Is to be used with the 'clear search' button.
 * @return void
 */
function set_defaults()
{
    global $showlines_default;

    $_SESSION['showlines'] = $showlines_default;
    $_SESSION['showpage'] = 1;
    $_SESSION['filter_LVL'] = "none";
    unset($_SESSION['day']);
    unset($_SESSION['search']);
}

/**
 * Make a dynamic html option list of days/months including selected
 * @param array $input
 * @return void
 */
function get_day_option(array $input)
{
    foreach ($input as $dayoption) {
        $dayoption_selected = ($dayoption == $_SESSION['day'] ? ' selected' : ''); ?>
        <option value="<?php echo $dayoption; ?>" <?php echo $dayoption_selected; ?>><?php echo str_replace('_', '-', $dayoption); ?></option>
        <?php
    }
}

/*
 * Processing the GET parts
 */
if (isset($_GET['action'])) {
    if ($_GET['action'] == 'clear') {
        set_defaults();
        header("Location: " . $_SERVER['PHP_SELF']);
    }
}

/*
 * Processing the POST parts
 */
if (isset($_POST['type'])) {
    // Compare type
    if (isset($_SESSION['type'])) {
        if ($_SESSION['type'] != $_POST['type']) {
            $_SESSION['type'] = $_POST['type'];
            unset($_SESSION['showip'], $_SESSION['day'], $_SESSION['search'], $_SESSION['refresh'], $_SESSION['showpage'], $_SESSION['filter_LVL']);
            $_SESSION['clearsearch'] = "clear";
        }
    } else {
        $_SESSION['type'] = "All";
    }
    // Compare ip
    if (isset($_SESSION['showip'])) {
        if ($_SESSION['showip'] != $_POST['showip']) {
            $_SESSION['showip'] = $_POST['showip'];
            unset($_SESSION['day'], $_SESSION['search'], $_SESSION['refresh'], $_SESSION['showpage'], $_SESSION['filter_LVL']);
            $_SESSION['clearsearch'] = "clear";
        }
    }
    // Compare date
    if (isset($_SESSION['day'])) {
        if ($_SESSION['day'] != $_POST['day']) {
            $_SESSION['day'] = $_POST['day'];
            unset($_SESSION['refresh'], $_SESSION['showpage']);
        }
    }
    // Process showlines before cleansearch because we want to reset lines to default on cleansearch
    if (isset($_POST['showlines'])) {
        if ($_SESSION['showlines'] != $_POST['showlines']) {
            $_SESSION['showlines'] = intval($_POST['showlines']);
            unset($_SESSION['showpage']);
        }
    }
    // Set level filter
    if (isset($_SESSION['filter_LVL'])) {
        $_SESSION['filter_LVL'] = $_POST['filter_LVL'];
    }
    // Compare search
    if ((isset($_POST['clearsearch'])) || (isset($_SESSION['clearsearch']))) {
        $_SESSION['showlines'] = $showlines_default;
        unset($_SESSION['search'], $_SESSION['clearsearch'], $_SESSION['showpage']);
    } else {
        if (isset($_SESSION['search'])) {
            if ($_SESSION['search'] != $_POST['search']) {
                $_SESSION['search'] = $_POST['search'];
                unset($_SESSION['refresh'], $_SESSION['showpage']);
            }
        } elseif ($_POST['search'] != "") {
            $_SESSION['search'] = $_POST['search'];
            unset($_SESSION['showpage']);
        }
    }
    // Compare,set or stop refresh
    $_SESSION['refresh'] = isset($_POST['stoprefresh']) ? 'off' : $_POST['refresh'];

    // Default back to page 1 after changes and detect page shift
    if (isset($_SESSION['showpage'])) {
        if ((isset($_POST['jumptopage'])) && ($_POST['jumptopage'] != "") && is_numeric($_POST['jumptopage'])) {
            if ($_SESSION['showpage'] != $_POST['jumptopage']) {
                if (($_POST['jumptopage'] > 0) && ($_POST['jumptopage'] <= $_SESSION['pagecount'])) {
                    $_SESSION['showpage'] = $_POST['jumptopage'];
                }
            } else {
                foreach ($_POST as $buttonname => $buttonvalue) {
                    if (preg_match('/showpage/', $buttonname)) {
                        $bvalue = explode('_', $buttonname);
                        switch ($bvalue['1']) {
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
                                // also 'first'
                                $_SESSION['showpage'] = "1";
                                break;
                        }
                    }
                }
            }
        } elseif ((preg_match('/[0-9][0-9]:[0-9][0-9]/', $_POST['jumptopage'])) || (preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}/', $_POST['jumptopage']))) {
            $_SESSION['showpage'] = $_POST['jumptopage'];
        }
    }
}

/*
 * Set defaults, common vars and figuring out existing settings from user
 */
$_SESSION['showlines'] = !isset($_SESSION['showlines']) ? $showlines_default : $_SESSION['showlines'];
$_SESSION['showpage'] = !isset($_SESSION['showpage']) ? 1 : $_SESSION['showpage'];
$_SESSION['filter_LVL'] = !isset($_SESSION['filter_LVL']) ? "none" : $_SESSION['filter_LVL'];

if ((isset($_SESSION['filter_LVL'])) && ($_SESSION['filter_LVL'] != "none")) {
    switch ($_SESSION['filter_LVL']) {
        case "debug":
            $lvl_filter = "'debug'";
            break;
        case "info":
            $lvl_filter = "'info', 'notice', 'warning', 'err', 'crit', 'alert', 'emergency', 'panic'";
            break;
        case "warning":
            $lvl_filter = "'warning', 'err', 'crit', 'alert', 'emergency', 'panic'";
            break;
        case "error":
            $lvl_filter = "'err', 'crit', 'alert', 'emergency', 'panic'";
            break;
        case "critical":
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
            // also 'notice'
            $lvl_filter = "'notice', 'warning', 'err', 'crit', 'alert', 'emergency', 'panic'";
    }
}

$ref = "";
if ((isset($_SESSION['refresh'])) && ($_SESSION['refresh'] != "off")) {
    $ref = "    <meta http-equiv=\"refresh\" content=\"{$_SESSION['refresh']}\">";
}

if (isset($_SESSION['type'])) {
    $hosttypeselect = ($_SESSION['type'] == "All" || $_SESSION['type'] == "Unnamed") ? "%" : $_SESSION['type'];
} else {
    $_SESSION['type'] = $default_view;
    $hosttypeselect = $default_view;
}
$searchstring = isset($_SESSION['search']) ? "%" . $_SESSION['search'] . "%" : "%";

/*
 * Fetch data from DB and populate vars/arrays
 */
// Get IP-adresses, their hostname and type
$query = "SELECT `hostip`, `hostname`
            FROM `{$database['DB_CONF']}`.`hostnames`
                 LEFT JOIN `{$database['DB_CONF']}`.`hosttype`
                 ON (`{$database['DB_CONF']}`.`hostnames`.`hosttype`=`{$database['DB_CONF']}`.`hosttype`.`id`)
           WHERE `name` LIKE '$hosttypeselect'";
$hostnamequery = $db_link->prepare($query);
$hostnamequery->execute();
$hostnameresult = $hostnamequery->get_result();

while ($dbhostnames = $hostnameresult->fetch_assoc()) {
    $hostnameip = $dbhostnames['hostip'];
    $hostname[$hostnameip] = $dbhostnames['hostname'];
}
$hostnameresult->free();

// Get all the table names
$query = "SELECT `TABLE_NAME` AS `tblnm`
            FROM `INFORMATION_SCHEMA`.`TABLES`
           WHERE `TABLE_SCHEMA` = '{$database['DB']}'";
$tablesquery = $db_link->prepare($query);
$tablesquery->execute();
$tablesresult = $tablesquery->get_result();

// Throw all ip parts of table names in an array
$iplist = array();
while ($lines = $tablesresult->fetch_array(MYSQLI_NUM)) {
    $thishost = explode('_DATE_', $lines['0']);
    $host = trim($thishost['0'], 'HST_');
    $ip = str_replace('_', '.', $host);

    if (!isset($thishost['1'])) {
        continue;
    }
    if (preg_match('/\d{4}_\d{2}_\d{2}/', $thishost['1'])) {
        $hostdaylist[$ip][] = $thishost['1'];
    } else {
        $hostmonthlist[$ip][] = $thishost['1'];
    }
    if ($_SESSION['type'] == "All") {
        $iplist[] = $ip;
    } elseif ($_SESSION['type'] == "Unnamed") {
        $addtolist = "add";
        foreach ($hostname as $hostselectip => $hostselectname) {
            if ($hostselectip == $ip) {
                $addtolist = "skip";
                break;
            }
        }
        if ($addtolist == "add") {
            $iplist[] = $ip;
        }
    } else {
        foreach ($hostname as $hostselectip => $hostselectname) {
            if ($hostselectip == $ip) {
                $iplist[] = $ip;
                break;
            }
        }
    }
}
$tablesresult->free();

// Deduplicate array
$iplist = array_unique($iplist);
sort($iplist);

// What if, an empty hosttype is selected?
if (empty($iplist)) {
    $empty_iplist = True;
    unset($_SESSION['showip']);
} else {
    $empty_iplist = False;
}

// Set the showip for the first time
foreach ($iplist as $ip) {
    if (!isset($_SESSION['showip'])) {
        $_SESSION['showip'] = $ip;
        break;
    }
}

// Get list of hosttypes
$typequery = $db_link->prepare("SELECT `id`, `name`
                                  FROM `{$database['DB_CONF']}`.`hosttype`
                                 ORDER BY `name`");
$typequery->execute();
$typeresult = $typequery->get_result();

// Set the day option, and offset
if (!$empty_iplist) {
    // Set the day correct in the session for the first time
    if (isset($hostdaylist[$_SESSION['showip']])) {
        usort($hostdaylist[$_SESSION['showip']], 'date_compare');
        foreach ($hostdaylist[$_SESSION['showip']] as $day) {
            if (!isset($_SESSION['day'])) {
                $_SESSION['day'] = $day;
                break;
            }
        }
    }
    if (isset($hostmonthlist[$_SESSION['showip']])) {
        foreach ($hostmonthlist[$_SESSION['showip']] as $day) {
            if (!isset($_SESSION['day'])) {
                $_SESSION['day'] = $day;
                break;
            }
        }
    }

    // Counts in lines, pages and figure out the offset
    $host = str_replace('.', '_', $_SESSION['showip']);
    $tablename = "HST_" . $host . "_DATE_" . $_SESSION['day'];
    if ($searchstring == '%') {
        $query = "SELECT COUNT(*) AS `cnt`
                    FROM `$tablename`";
    } else {
        $query = "SELECT COUNT(*) AS `cnt`
                    FROM `$tablename`
                   WHERE `MSG` LIKE ?";
    }
    if ((isset($_SESSION['filter_LVL'])) && ($_SESSION['filter_LVL'] != "none")) {
        if ($searchstring == '%') {
            $query .= " WHERE LVL IN (" . $lvl_filter . ") ";
        } else {
            $query .= " AND LVL IN (" . $lvl_filter . ") ";
        }
    }
    $countquery = $db_link->prepare($query);
    if ($searchstring != '%') {
        $countquery->bind_param('s', $searchstring);
    }
    $countquery->execute();
    $countresult = $countquery->get_result();
    $count = $countresult->fetch_assoc();
    $linecount = $count['cnt'];
    $countresult->free();

    $_SESSION['pagecount'] = ceil($linecount / $_SESSION['showlines']);
} else {
    $linecount = 0;

    $_SESSION['pagecount'] = 1;
}

// Set the selected page and counting where we are
if (!is_numeric($_SESSION['showpage'])) {
    if (preg_match('/^[0-2][0-9]:[0-5][0-9]/', $_POST['jumptopage'])) {
        $query = "SELECT COUNT(*) AS `cnt`
                    FROM `$tablename`
                   WHERE `MSG` LIKE ? ";
        if ((isset($_SESSION['filter_LVL'])) && ($_SESSION['filter_LVL'] != "none")) {
            $query .= "AND LVL IN (" . $lvl_filter . ") ";
        }
        $query .= "AND TIME <= '" . $_SESSION['showpage'] . "'";
    } elseif (preg_match('/20[0-1][0-9]-[0-1][0-9]-[0-3][0-9] [0-2][0-9]:[0-5][0-9]/', $_POST['jumptopage'])) {
        $query = "SELECT COUNT(*) AS `cnt`
                    FROM `$tablename`
                   WHERE `MSG` LIKE ? ";
        if ((isset($_SESSION['filter_LVL'])) && ($_SESSION['filter_LVL'] != "none")) {
            $query .= "AND LVL IN (" . $lvl_filter . ") ";
        }
        $query .= "AND CONCAT(DAY,' ',TIME) <= '" . $_SESSION['showpage'] . "'";
    }
    $timecountquery = $db_link->prepare($query);
    $timecountquery->bind_param('s', $searchstring);
    $timecountquery->execute();
    $timecountedresult = $timecountquery->get_result();
    $timecounted = $timecountedresult->fetch_assoc();
    if ($timecounted) {
        $timelinecount = $timecounted['cnt'];
        $timecountedresult->free();
    } else {
        $timelinecount = 0;
    }
    $timepagecount = round($timelinecount / $_SESSION['showlines']);
    $_SESSION['showpage'] = $_SESSION['pagecount'] - $timepagecount;
}
$counttolast = $_SESSION['pagecount'] - $_SESSION['showpage'];
$offset = ($_SESSION['showpage'] - 1) * $_SESSION['showlines'];

// Get the actual lines for the selected host on the given date with the given filter and offset
if (!$empty_iplist) {
    $fields = implode(', ', $log_fields);
    $query = "SELECT $fields
                FROM `$tablename`
               WHERE `MSG` LIKE ? ";
    if (isset ($lvl_filter)) {
        $query .= "AND `LVL` IN (" . $lvl_filter . ") ";
    }
    $query .= "ORDER BY `TIME` DESC LIMIT " . $_SESSION['showlines'] . " OFFSET " . $offset;
    $linesquery = $db_link->prepare($query);
    $linesquery->bind_param('s', $searchstring);  // $searchstring is already given the % tags
    $linesquery->execute();
    $loglines = $linesquery->get_result();
} else {
    $loglines = array();
}

/*
 * Build the page
 */

?>
    <!DOCTYPE HTML>
    <html lang="en">
    <head>
        <title>Netlog</title>
        <?php echo "$ref\n"; ?>
        <meta http-equiv="content-type" content="text/html; charset=iso-8859-1">
        <link rel="stylesheet" type="text/css" href="css/style.css">
        <script type="text/javascript" src="scripts/netlog.js"></script>
        <!-- <?php echo constant('NAME') . ", " . constant('VERSION') . " -- " . constant('AUTHOR'); ?> -->
    </head>
    <body>

    <form name="settings" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
        <div class="container">
            <div class="header">
                <div class="header_title">Netlog :: <?php echo date('Y-m-d - H:i:s'); ?></div>
                <div class="header_select">Select Page:</div>
                <div class="header_nav">
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=clear">clear search</a> |
                    <a href="netalert.php?inline" title="NetAlert">netalert</a> |
                    <a href="viewlograte.php" title="Logrates">lograte</a> |
                    <a href="settings.php" title="Configuration panel">config</a> |
                    logging
                </div>
                <div class="header_paging">

                    <table class="outline">
                        <tr>
                            <td>
                                <button name="showpage_b25" type="submit" <?php if ($_SESSION['showpage'] < 26) {
                                    echo "disabled";
                                } ?>>-25
                                </button>
                            </td>
                            <td>
                                <button name="showpage_b10" type="submit" <?php if ($_SESSION['showpage'] < 11) {
                                    echo "disabled";
                                } ?>>-10
                                </button>
                            </td>
                            <td>
                                <button name="showpage_b1" type="submit" <?php if ($_SESSION['showpage'] < 2) {
                                    echo "disabled";
                                } ?>>-1
                                </button>
                            </td>
                            <td><b><input title="Give a number of a page in range" type="text" size="6"
                                          name="jumptopage"
                                          value="<?php echo $_SESSION['showpage']; ?>"
                                          onChange="this.form.submit()"></b>
                            </td>
                            <td>
                                <button name="showpage_f1"
                                        type="submit" <?php if ($_SESSION['showpage'] > ($_SESSION['pagecount'] - 1)) {
                                    echo "disabled";
                                } ?>>+1
                                </button>
                            </td>
                            <td>
                                <button name="showpage_f10"
                                        type="submit" <?php if ($_SESSION['showpage'] > ($_SESSION['pagecount'] - 11)) {
                                    echo "disabled";
                                } ?>>+10
                                </button>
                            </td>
                            <td>
                                <button name="showpage_f25"
                                        type="submit" <?php if ($_SESSION['showpage'] > ($_SESSION['pagecount'] - 26)) {
                                    echo "disabled";
                                } ?>>+25
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3" style="text-align: center;">
                                <button name="showpage_first" type="submit" <?php if ($_SESSION['showpage'] == "1") {
                                    echo "disabled";
                                } ?>>first
                                </button>
                            </td>
                            <td><span class="lpp">lpp:</span>
                                <select title="Select a number of lines-per-page" class="lpp" name="showlines"
                                        onChange="this.form.submit()"><?php
                                    foreach ($showlines as $log_limit) {
                                        echo "\n                                <option value=\"" . $log_limit . "\"";
                                        if ((isset($_SESSION['showlines'])) && ($log_limit == $_SESSION['showlines'])) {
                                            echo " SELECTED";
                                        }
                                        echo ">" . $log_limit . "</option>";
                                    }
                                    echo "\n"; ?>
                                </select>
                            </td>
                            <td colspan="3" style="text-align: center">
                                <button name="showpage_last"
                                        type="submit" <?php if (($_SESSION['showpage'] == $_SESSION['pagecount']) || ($_SESSION['pagecount'] < 2)) {
                                    echo "disabled";
                                } ?>>last
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="header_refresh">
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>"
                       onClick="document.location.href = this.href;return false"
                       title="click to refresh the page">
                        Refresh</a>: <?php
                    echo "\n";
                    if ((isset($_SESSION['refresh'])) && ($_SESSION['refresh'] != 'off')) {
                        ?>
                        <button name="stoprefresh" type="submit">stop</button><?php
                    }
                    echo "\n";
                    if ((isset($_SESSION['day'])) && ($_SESSION['day'] == $today)) { ?>
                        <select title="Select a refreshrate" name="refresh" onChange="this.form.submit()"><?php
                        foreach ($refresh as $value) {
                            echo "\n                      <option value=\"" . $value . "\"";
                            if (isset($_SESSION['refresh']) && $_SESSION['refresh'] == $value) {
                                echo " SELECTED";
                            }
                            echo ">" . $value . "</option>";
                        }
                        echo "\n"; ?>
                        </select><?php
                    } else {
                        echo "                    <input type=\"hidden\" value=\"off\" name=\"refresh\">off";
                    }
                    echo "\n"; ?>
                </div>
                <div class="header_pagenr">
                    Page <?php echo $_SESSION['showpage'] . " of " . $_SESSION['pagecount']; ?></div>
                <div class="header_device">
                    Device Type:
                    <select title="Select a type" name="type" onChange="this.form.submit()"><?php
                        while ($types = $typeresult->fetch_assoc()) {
                            echo "\n                      <option value=\"" . $types['name'] . "\"";
                            if ($_SESSION['type'] == $types['name']) {
                                echo " SELECTED";
                            }
                            echo ">" . $types['name'] . "</option>";
                        }
                        echo "\n";
                        mysqli_free_result($typeresult); ?>
                    </select>
                    Device:
                    <select title="Select a device" name="showip" onChange="this.form.submit()"><?php
                        foreach ($iplist as $ip) {
                            if (!isset($_SESSION['showip'])) {
                                $_SESSION['showip'] = $ip;
                            }
                            echo "\n                      <option value=\"" . $ip . "\"";
                            if ($_SESSION['showip'] == $ip) {
                                echo " SELECTED";
                            }
                            echo ">";
                            if (isset($hostname[$ip])) {
                                echo $hostname[$ip];
                            } else {
                                echo $ip;
                            }
                            echo "</option>";
                        }
                        echo "\n"; ?>
                    </select>
                    Day:
                    <select title="Select a day" name="day" onChange="this.form.submit()">
                        <?php
                        if ((isset($_SESSION['showip'])) && (isset($hostdaylist[$_SESSION['showip']]))) {
                            usort($hostdaylist[$_SESSION['showip']], 'date_compare');
                            get_day_option($hostdaylist[$_SESSION['showip']]);
                        }
                        echo "\n";
                        if ((isset($_SESSION['showip'])) && (isset($hostmonthlist[$_SESSION['showip']]))) {
                            get_day_option($hostmonthlist[$_SESSION['showip']]);
                        } ?>
                    </select><br>
                    <p class="severity">&nbsp;Filter LVL:
                        <select title="Select a severity" name="filter_LVL" onChange="this.form.submit()"><?php
                            echo "\n                      <option value=\"none\"";
                            if ($_SESSION['filter_LVL'] == "none") {
                                echo " SELECTED";
                            }
                            echo ">none</option>";
                            foreach ($log_levels as $log_level) {
                                echo "\n                      <option value=\"" . $log_level . "\" class=\"" . $log_level . "\"";
                                if ($_SESSION['filter_LVL'] == $log_level) {
                                    echo " SELECTED";
                                }
                                echo ">" . $log_level . "</option>";
                            }
                            echo "\n"; ?>
                        </select></p>
                </div>
                <div class="header_search">
                    <input title="Filter based on your input" name="search" type="text" onKeyPress="checkEnter(event)"
                           value="<?php if (isset($_SESSION['search'])) {
                               echo $_SESSION['search'];
                           } ?>" autofocus placeholder="Search" style="width: 80%;">
                    <br><span class="search_button"><button style="width: 50px;" type="submit">Go</button><span>
                </div>
                <div class="header_lines">Total lines: <?php echo $linecount; ?></div>
            </div>
            <div class="results">

                <table class="none" style="width: 100%">
                    <tr><?php echo "\n";
                        foreach ($log_fields as $column) {
                            switch ($column) {
                                case "HOST":
                                case "TIME":
                                case "DAY":
                                case "PROG":
                                    echo "                    <th style=\"min-width: 100px;\">" . $column . "</th>";
                                    break;
                                case "MSG":
                                    echo "                    <th>" . $column . "</th>";
                                    break;
                                default:
                                    echo "                    <th style=\"min-width: 50px;\">" . $column . "</th>";
                            }
                            echo "\n";
                        } ?>
                    </tr>
                    <?php
                    $linetag = "0";
                    if ($loglines) {
                        while ($logline = mysqli_fetch_assoc($loglines)) {
                            if ($linetag == "0") {
                                $linetag = "1";
                            } else {
                                $linetag = "0";
                            }
                            echo "<tr>\n                  ";
                            foreach ($log_fields as $column) {
                                if ($column == "LVL") {
                                    echo "<td ";
                                    switch ($logline[$column]) {
                                        case "debug":
                                            echo "class=\"debug\">";
                                            break;
                                        case "info":
                                            echo "class=\"info\">";
                                            break;
                                        case "notice":
                                            echo "class=\"notice\">";
                                            break;
                                        case "warning":
                                            echo "class=\"warning\">";
                                            break;
                                        case "err":
                                            echo "class=\"error\">";
                                            break;
                                        case "crit":
                                            echo "class=\"critical\">";
                                            break;
                                        case "alert":
                                            echo "class=\"alert\">";
                                            break;
                                        case "emergency":
                                            echo "class=\"emergency\">";
                                            break;
                                        case "panic":
                                            echo "class=\"panic\">";
                                            break;
                                        default:
                                            echo ">";
                                    }
                                    echo $logline[$column] . "</td>";
                                } elseif ($linetag == "0") {
                                    echo "<td>" . $logline[$column] . "</td>";
                                } else {
                                    echo "<td class=\"grey\">" . $logline["$column"] . "</td>";
                                }
                            }
                            echo "\n                </tr>\n                ";
                        }
                        $loglines->free();
                    }
                    echo "\n"; ?>
                </table>

            </div>
        </div>

    </form>
    </body>

    </html>
<?php $db_link->close();
<?php
require(dirname(__DIR__) . "/etc/config.php");
$today = date('Y_m_d');

/*
 * Check and if not, create database link
 */
if (!isset($db_link)) {
    $db_link = connect_db();
}

$tablename = 'HST_127_0_0_2_DATE_' . $today;
$fields = implode(', ', $alert_fields);

// Get the Netalerts
$query = "SELECT $fields
            FROM $tablename 
           ORDER BY `TIME` DESC
           LIMIT $showlines_alert";
$loglinequery = $db_link->prepare($query);
// If there is a table for today, get results
if ($loglinequery) {
    $loglinequery->execute();
    $loglineresult = $loglinequery->get_result();
} else {
    $loglineresult = false;
}

/*
 * Build the page
 */
?>
    <!DOCTYPE HTML>
    <html lang="en">
    <head>
        <title>Netalert</title>
        <meta http-equiv="content-type" content="text/html; charset=iso-8859-1">
        <link rel="stylesheet" type="text/css" href="css/style.css">
        <script type="text/javascript" src="scripts/netlog.js"></script>
        <meta http-equiv="refresh" content="15">
        <!-- <?php echo constant('NAME') . ", " . constant('VERSION') . " -- " . constant('AUTHOR'); ?> -->
    </head><?php
    echo "\n";
    if ((sizeof($_GET) >= 0) && (isset($_GET['inline']))) { ?>
    <body style="background-color: #ffffff;">
    <div class="container">
        <div class="header">
            <div class="header_title">Netlog :: <?php echo date('Y-m-d - H:i:s'); ?></div>
            <div class="header_nav">
                netalert |
                <a href="viewlograte.php" title="Lograte">lograte</a> |
                <a href="settings.php" title="Configuration panel">config</a> |
                <a href="index.php" title="Back to logging">logging</a>
            </div>
        </div>
        <div class="results">
            <?php
            } else {
            ?>
            <body style="background-color: #161616"><?php
            }
            echo "\n"; ?>
            <table class="outline" width="100%">
                <tr>
                    <td>
                        <table class="none" width="100%">
                            <tr>
                                <?php
                                foreach ($alert_fields as $column) {
                                    switch ($column) {
                                        case "TIME":
                                        case "LVL":
                                            echo "<th class=\"aqua\" width=\"55\">$column</th>";
                                            break;
                                        case "DAY":
                                            echo "<th class=\"aqua\" width=\"75\">$column</th>";
                                            break;
                                        case "PROG":
                                            echo "<th class=\"aqua\" width=\"110\">$column</th>";
                                            break;
                                        default:
                                            echo "<th class=\"aqua\">$column</th>";
                                    }
                                }
                                echo "\n";
                                ?>
                            </tr>
                            <?php
                            $linetag = "0";
                            if ($loglineresult) {
                                while ($loglines = $loglineresult->fetch_assoc()) {
                                    if ($linetag == "0") {
                                        $linetag = "1";
                                    } else {
                                        $linetag = "0";
                                    }
                                    $currenttime = time();
                                    $timestamp = strtotime($loglines["TIME"]);
                                    $timediff = $currenttime - $timestamp;
                                    foreach ($alert_fields as $column) {
                                        if ($column == "TIME") {
                                            switch ($timediff) {
                                                case ($timediff < 400):
                                                    echo "<td class=\"panic\"><b>" . $loglines[$column] . "</b></td>";
                                                    break;
                                                case ($timediff < 800):
                                                    echo "<td class=\"emergency\"><b>" . $loglines[$column] . "</b></td>";
                                                    break;
                                                case ($timediff < 1200):
                                                    echo "<td class=\"alert\"><b>" . $loglines[$column] . "</b></td>";
                                                    break;
                                                case ($timediff < 1600):
                                                    echo "<td class=\"critical\"><b>" . $loglines[$column] . "</b></td>";
                                                    break;
                                                case ($timediff < 2000):
                                                    echo "<td class=\"error\"><b>" . $loglines[$column] . "</b></td>";
                                                    break;
                                                case ($timediff < 2400):
                                                    echo "<td class=\"warning\"><b>" . $loglines[$column] . "</b></td>";
                                                    break;
                                                case ($timediff < 2800):
                                                    echo "<td class=\"notice\">" . $loglines[$column] . "</td>";
                                                    break;
                                                case ($timediff < 3200):
                                                    echo "<td class=\"info\">" . $loglines[$column] . "</td>";
                                                    break;
                                                case ($timediff < 3600):
                                                    echo "<td class=\"debug\">" . $loglines[$column] . "</td>";
                                                    break;
                                                default:
                                                    if ($linetag == "0") {
                                                        echo "<td class=\"white\">" . $loglines[$column] . "</td>";
                                                    } else {
                                                        echo "<td class=\"darkgrey\">" . $loglines[$column] . "</td>";
                                                    }
                                            }
                                        } elseif ($column == "LVL" || $column == "MSG" || $column == "PROG") {
                                            if ($linetag == "0") {
                                                if ($timediff > $timethresh) {
                                                    echo "<td class=\"white\">";
                                                } else {
                                                    echo "<td class=\"netup\">";
                                                }
                                            } elseif ($linetag == "1") {
                                                if ($timediff > $timethresh) {
                                                    echo "<td class=\"darkgrey\">";
                                                } else {
                                                    echo "<td class=\"netupd\">";
                                                }
                                            } else {
                                                echo "<td class=\"white\">";
                                            }
                                            echo $loglines["$column"] . "</td>";
                                        } elseif ($linetag == "0") {
                                            echo "<tr><td class=\"white\">" . $loglines[$column] . "</td>";
                                        } else {
                                            echo "<tr><td class=\"darkgrey\">" . $loglines[$column] . "</td>";
                                        }
                                    }

                                    echo "</tr>\n\t\t\t";
                                }
                                echo "\n";

                            }
                            echo "\n"; ?>
                        </table>
                    </td>
                </tr>
            </table><?php
            if (isset($_GET['inline'])) { ?>
        </div>
    </div>
    <?php
    }
    echo "\n"; ?>
    </body>
    </html>

<?php
if ($loglineresult) {
    $loglineresult->free_result();
}
$db_link->close();

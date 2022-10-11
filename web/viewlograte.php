<?php
require(dirname(__DIR__) . "/etc/global.php");
require_once('jpgraph/jpgraph.php');
require_once('jpgraph/jpgraph_line.php');

$today = date('Y_m_d');

// Set the timing (history)
if (!isset($_SESSION['timelimit'])) {
    $_SESSION['timelimit'] = $graph_timelimit;
}
if (isset($_POST['time'])) {
    $_SESSION['timelimit'] = $_POST['time'];
}

// Get the lograte-enabled hostnames and their id
$query = "SELECT `id`, `hostname`
            FROM `{$database['DB_CONF']}`.`hostnames`
           WHERE lograte = 1
           ORDER BY `id`";
$logratequery = $db_link->prepare($query);
$logratequery->execute();
$lograteresult = $logratequery->get_result();

/*
 * Build the page
 */
?>
    <!DOCTYPE HTML>
    <html lang="en">
    <head>
        <title>Netlog - Lograte viewer</title>
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
                lograte |
                <a href="settings.php" title="Configuration panel">config</a> |
                <a href="index.php" title="Back to logging">logging</a>
            </div>
            <div class="header_settings">
                <form method="post" action="viewlograte.php">
                    Show last&nbsp;
                    <select title="period" name="time" onChange="this.form.submit()"><?php
                        foreach ($graph_history as $timelimit) {
                            echo "\n";
                            $timelimit_selected = ($timelimit == $_SESSION['timelimit'] ? " selected" : '') ?>
                            <option
                            value=<?php echo "\"" . $timelimit . "\"" . $timelimit_selected; ?>><?php echo $timelimit; ?></option><?php
                        }
                        echo "\n"; ?>
                    </select> minutes
                </form>
            </div>

        </div>
        <div class="results">
            <table class="none">
                <tr>
                    <th>Log rates for clients:</th>
                    <th>&nbsp;</th>
                </tr>
                <tr>
                    <td>&nbsp;</td>
                    <td>&nbsp;</td>
                </tr>

                <tr><?php
                    $graph_count = 0;
                    while ($drawhost = $lograteresult->fetch_assoc()) {
                        echo "\n";
                        $id = $drawhost['id'];
                        $name = $drawhost['hostname'];
                        $time = $_SESSION['timelimit']

                        ?>
                        <td>
                            <img src="drawgraph.php?hostid=<?php echo "$id&hostname=$name&width=$graph_width&height=$graph_height&time=$time"; ?>"
                                 alt="">
                        </td>
                        <?php
                        $graph_count += 1;
                        // 2 graphs max per row
                        if ($graph_count % 2 === 0) {
                            echo "\n\t\t\t</tr>\n\t\t\t<tr>";
                        }
                    }
                    // End row if odd and last graph
                    if (!$graph_count % 2 === 0) {
                        echo "\n\t\t\t<tr>";
                    }
                    echo "\n"; ?>
            </table>
        </div>
    </div>
    </body>
    </html>
<?php $db_link->close();

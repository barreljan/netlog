<?php
require("config/config.php");
require_once('jpgraph/jpgraph.php');
require_once('jpgraph/jpgraph_line.php');
require_once('jpgraph/jpgraph_date.php');


/*
 * Create and check database link
 */
$db_link = connect_db();

$hostid = $_GET['hostid'];
$hostname = $_GET['hostname'];
$time = $_GET['time'];
$width = $_GET['width'];
$height = $_GET['height'];

if (!isset($logratedata)) {
    global $logratedata;
}

$query = "SELECT `sample_timestamp`, 
       				ROUND(1min/60,2) as min1ps,
       				ROUND(5min/300,2) as min5ps,
       				ROUND(10min/600,2) as min10ps
			FROM `{$database['DB_CONF']}`.`lograte`
           WHERE `hostnameid` = ? 
           ORDER BY `sample_timestamp` DESC
           LIMIT ?";
$samplequery = $db_link->prepare($query);
$samplequery->bind_param('ss', $hostid, $time);
$samplequery->execute();
$sampleresult = $samplequery->get_result();

while ($hostdata = $sampleresult->fetch_assoc()) {
    $logratedata[$hostid]['timestamp'][] = strtotime($hostdata['sample_timestamp']);

    $logratedata[$hostid]['min1ps'][] = $hostdata['min1ps'];
    $logratedata[$hostid]['min5ps'][] = $hostdata['min5ps'];
    $logratedata[$hostid]['min10ps'][] = $hostdata['min10ps'];
}

$min1ps = $logratedata[$hostid]['min1ps'];
$min5ps = $logratedata[$hostid]['min5ps'];
$min10ps = $logratedata[$hostid]['min10ps'];
$timeline = $logratedata[$hostid]['timestamp'];

// Create the graph. These two calls are always required
$graph = new Graph($width, $height);
$graph->SetMargin(50, 30, 15, 95);

$graph->SetScale('datlin');
$graph->title->Set("$hostname");
$graph->title->SetFont(FF_ARIAL, FS_BOLD, 12);

$graph->legend->SetPos(0.30, 0.98, 'center', 'bottom');
$graph->legend->SetColumns(3);
$graph->legend->SetFont(FF_ARIAL, FS_NORMAL, 8);

$graph->xaxis->SetLabelAngle(60);
//$graph->xaxis->SetLabelFormatString('d M H:i',true);
$graph->xaxis->SetFont(FF_ARIAL, FS_NORMAL, 7);
$graph->yaxis->SetFont(FF_ARIAL, FS_NORMAL, 8);

// Create the linear plot
$lineplot1 = new LinePlot($min1ps, $timeline);
$lineplot1->SetColor('#AAAAAA');
$lineplot1->SetLegend('1min avg p/s');

$lineplot2 = new LinePlot($min5ps, $timeline);
$lineplot2->SetColor('#FF0000');
$lineplot2->SetLegend('5min avg p/s');
$lineplot2->SetWeight('1');

$lineplot3 = new LinePlot($min10ps, $timeline);
$lineplot3->SetColor('#00AA22');
$lineplot3->SetLegend('10min avg p/s');

// Add the plot to the graph
$graph->Add($lineplot1);
$graph->Add($lineplot2);
$graph->Add($lineplot3);

// Display the graph
$graph->Stroke();

$db_link->close();

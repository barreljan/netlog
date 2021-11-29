<?php // content="text/plain; charset=utf-8"

include("config/config.php");

session_start();

require_once ('jpgraph/jpgraph.php');
require_once ('jpgraph/jpgraph_line.php');
require_once ('jpgraph/jpgraph_date.php');

$db_link = mysqli_connect($db_HOST, $db_USER, $db_PASS, $db_NAME);
if (!$db_link) {
	die('Could not connect to MySQL server: ' . mysqli_error($db_link));
}

if (!mysqli_select_db($db_link,$db_NAME)) {
	die('Unable to select DB: ' . mysqli_error($db_link));
}
$hostid=$_GET['hostid'];
$hostname=$_GET['hostname'];
$time=$_GET['time'];
$width=$_GET['width'];
$height=$_GET['height'];

if (!isset( $logratedata ) ) {
	global $logratedata;
}

$query = "SELECT timestamp, ROUND(1min/60,2) as min1ps, ROUND(5min/300,2) as min5ps, ROUND(10min/600,2) as min10ps FROM netlogconfig.lograte WHERE hostnameid='" . $hostid . "' ORDER BY timestamp DESC LIMIT $time";
$result = mysqli_query($db_link,$query);

while ( $hostdata = mysqli_fetch_assoc($result) ) {
	//$logratedata["$hostid"]['1min'][] = $hostdata['1min'];
	//$logratedata["$hostid"]['5min'][] = $hostdata['5min'];
	//$logratedata["$hostid"]['10min'][] = $hostdata['10min'];

	$logratedata["$hostid"]['timestamp'][] = strtotime($hostdata['timestamp']);

	$logratedata["$hostid"]['min1ps'][] = $hostdata['min1ps'];
	$logratedata["$hostid"]['min5ps'][] = $hostdata['min5ps'];
	$logratedata["$hostid"]['min10ps'][] = $hostdata['min10ps'];
}

// Some data
//$ydata = array(11,3,8,12,5,1,9,13,5,7);
/*
foreach ($logratedata["$hostid"]['1min'] as $value ) {
 echo $value . "<br>";
}
*/
$min1ps = $logratedata["$hostid"]['min1ps'];
$min5ps = $logratedata["$hostid"]['min5ps'];
$min10ps = $logratedata["$hostid"]['min10ps'];
$timeline = $logratedata["$hostid"]['timestamp'];

//print_r($ydata);
// Create the graph. These two calls are always required
$graph = new Graph($width,$height);
$graph->SetMargin(50,30,15,95);

$graph->SetScale('datlin');
$graph->title->Set("$hostname");
$graph->title->SetFont(FF_ARIAL,FS_BOLD,12);

$graph->legend->SetPos(0.30,0.98,'center','bottom');
$graph->legend->SetColumns(3);
$graph->legend->SetFont(FF_ARIAL,FS_NORMAL,8);

$graph->xaxis->SetLabelAngle(60);
//$graph->xaxis->SetLabelFormatString('d M H:i',true);
$graph->xaxis->SetFont(FF_ARIAL,FS_NORMAL,7);
$graph->yaxis->SetFont(FF_ARIAL,FS_NORMAL,8);

// Create the linear plot
$lineplot1=new LinePlot($min1ps , $timeline);
$lineplot1->SetColor('#AAAAAA');
$lineplot1->SetLegend('1min avg p/s');

$lineplot2=new LinePlot($min5ps , $timeline);
$lineplot2->SetColor('#FF0000');
$lineplot2->SetLegend('5min avg p/s');
$lineplot2->SetWeight('1');

$lineplot3=new LinePlot($min10ps , $timeline);
$lineplot3->SetColor('#00AA22');
$lineplot3->SetLegend('10min avg p/s');

// Add the plot to the graph
$graph->Add($lineplot1);
$graph->Add($lineplot2);
$graph->Add($lineplot3);

// Display the graph
$graph->Stroke();
?>

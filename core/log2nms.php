<?php
// Module to enable sending the NetAlerts to your LibreNMS for monitoring and alerting.

/*
 * Create and check database link to NMS
 */
$nms_db_link = new mysqli($nms_database['HOST'], $nms_database['USER'], $nms_database['PASS'], $nms_database['DB']);
if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    die;
}
if (!$nms_db_link->select_db($nms_database['DB'])) {
    printf("Unable to select DB: %s\n", mysqli_connect_error());
    die;
}

/*
 * Inserting the message as-is into the LibreNMS database
 */
function remote_syslog($hostname, $hostip, $message_row)
{
    // $hostname = a string containing the user-submitted name
    // $hostip = the matching IP-address of the message
    // $message_row = the complete row (array) fetched by the logscavenger
    global $nms_database, $nms_db_link;

    // Get hostnames and their ID's
    $query = "SELECT `device_id`
                FROM `{$nms_database['DB']}`.`syslog`
               WHERE `hostname` LIKE ?
                  OR `ip` = ?
               LIMIT 1";
    $devquery = $nms_db_link->prepare($query);
    $hostname = "%$hostname%";
    $inet_pton = inet_pton($hostip);
    $devquery->bind_param('ss', $hostname, $inet_pton);
    $devquery->execute();
    $devresult = $devquery->get_result();

    if ($devresult->num_rows == 1) {
        $row = $devresult->fetch_assoc();
        $dev_id = $row[0]['device_id'];

        $facilty = $message_row['FAC'];
        $priority = $message_row['PRIO'];
        $level = $message_row['LVL'];
        $tag = $message_row['TAG'];
        $timestamp = $message_row['DAY'] . " " . $message_row['TIME'];
        $program = $message_row['PROG'];
        $msg = $message_row['MSG'];

        // Insert message
        $query = "INSERT INTO `{$nms_database['DB']}`.`syslog` (device_id, facility, priority, level, tag, timestamp, program, msg)
                        VALUES ($dev_id, $facilty, $priority, $level, $tag, $timestamp, $program, $msg)";
        echo $query . "\n";

    }
}
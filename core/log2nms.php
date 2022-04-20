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

/**
 * Inserting the message as-is into the LibreNMS database
 *
 * @param string $hostname A hostname matching the ip address
 * @param string $hostip An ip address matching the hostname
 * @param array $message_row
 */
function remote_syslog(string $hostname, string $hostip, array $message_row)
{
    global $nms_database, $nms_db_link;

    // Conversion table as LibreNMS does prefer integer values
    $lvl['emerg'] = $lvl['emergency'] = $lvl['panic'] = '0';
    $lvl['alert'] = '1';
    $lvl['critical'] = $lvl['crit'] = '2';
    $lvl['error'] = $lvl['err'] = '3';
    $lvl['warning'] = '4';
    $lvl['notice'] = '5';
    $lvl['informational'] = $lvl['info'] = '6';
    $lvl['debug'] = '7';

    // Get hostname and the ID
    $hostname = "%$hostname%";
    $inet_pton = inet_pton($hostip);

    $query = "SELECT `device_id`
                FROM `{$nms_database['DB']}`.`devices`
               WHERE `hostname` LIKE ?
                  OR `ip` = ?
               LIMIT 1";
    $devquery = $nms_db_link->prepare($query);
    $devquery->bind_param('ss', $hostname, $inet_pton);
    $devquery->execute();
    $devresult = $devquery->get_result();

    if ($devresult->num_rows == 1) {
        $row = $devresult->fetch_assoc();
        $dev_id = $row['device_id'];

        $facilty = $message_row['FAC'];
        $priority = $message_row['PRIO'];
        $level = $lvl[$message_row['LVL']];
        $tag = $message_row['TAG'];
        $timestamp = $message_row['DAY'] . " " . $message_row['TIME'];
        $program = $message_row['PROG'];
        $msg = $message_row['MSG'];

        // Insert message
        $query = "INSERT INTO `{$nms_database['DB']}`.`syslog` (device_id, facility, priority, level, tag, timestamp, program, msg)
                        VALUES ($dev_id, ?, ?, ?, ?, ?, ?, ?)";
        $insertquery = $nms_db_link->prepare($query);
        $insertquery->bind_param('sssssss', $facilty, $priority, $level, $tag, $timestamp, $program, $msg);
        $insertquery->execute();

        $nms_db_link->commit();
    }
}
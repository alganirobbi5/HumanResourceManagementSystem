<?php

session_start();

$_SESSION["user"] = "admin";

include '../../src/common/DBConnection.php';

$conn = new DBConnection();

$eventSubject = $_POST['eventSubject'];
$eventDate = $_POST['eventDate'];
$eventTime = $_POST['eventTime'];
$eventDesc = $_POST['eventDesc'];

// Gunakan prepared statement untuk mencegah SQL injection
$params = [$eventSubject, $eventDate, $eventTime, $eventDesc, $_SESSION["user"]];
$stmt = $conn->run('INSERT INTO live_events (subject, event_date, event_time, description, insert_by, insert_date) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)', $params);

if($stmt) {
    header("Location: " ."../../views/admin/sendLiveEvent.php?message=success");
    die();
} else {
    header("Location: " ."../../views/admin/sendLiveEvent.php?message=failed");
    die();
}

class EventStore
{

}
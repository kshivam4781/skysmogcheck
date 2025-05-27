<?php
session_start();
require_once '../config/db_connection.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

try {
    // Get events for the current user
    $stmt = $conn->prepare("
        SELECT ce.id, ce.title, ce.start_time, ce.end_time, ce.description, ce.appointment_id, ce.status 
        FROM calendar_events ce 
        WHERE ce.user_id = ?
    ");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $events = [];
    while ($row = $result->fetch_assoc()) {
        // Set color based on status
        $color = '#3788d8'; // Default color
        switch ($row['status']) {
            case 'pending':
                $color = '#ffc107'; // Yellow for pending
                break;
            case 'confirmed':
                $color = '#28a745'; // Green for confirmed
                break;
            case 'cancelled':
                $color = '#dc3545'; // Red for cancelled
                break;
        }

        $events[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'start' => $row['start_time'],
            'end' => $row['end_time'],
            'description' => $row['description'],
            'appointment_id' => $row['appointment_id'],
            'status' => $row['status'],
            'backgroundColor' => $color,
            'borderColor' => $color
        ];
    }

    echo json_encode($events);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?> 
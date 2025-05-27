<?php
session_start();
require_once '../config/db_connection.php';

// Set proper JSON header
header('Content-Type: application/json');

// Check if user is logged in and has consultant access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['accountType']) || $_SESSION['accountType'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get parameters
$date = $_GET['date'] ?? null;
$appointment_id = $_GET['appointment_id'] ?? null;

if (!$date || !$appointment_id) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit();
}

try {
    // Get booked slots for the selected date
    $stmt = $conn->prepare("
        SELECT start_time 
        FROM calendar_events 
        WHERE DATE(start_time) = ? 
        AND status = 'confirmed'
        AND appointment_id != ?
    ");
    $stmt->bind_param("si", $date, $appointment_id);
    $stmt->execute();
    $booked_slots = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $booked_times = array_map(function($slot) {
        return date('H:i', strtotime($slot['start_time']));
    }, $booked_slots);

    // Generate time slots from 9:00 AM to 5:30 PM
    $start = strtotime('09:00');
    $end = strtotime('17:30');
    $interval = 30 * 60; // 30 minutes in seconds
    $slots = [];

    // Get current appointment time
    $stmt = $conn->prepare("
        SELECT start_time 
        FROM calendar_events 
        WHERE appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $current_time = $stmt->get_result()->fetch_assoc();
    $current_time_str = $current_time ? date('H:i', strtotime($current_time['start_time'])) : null;

    // Generate time options
    for ($time = $start; $time <= $end; $time += $interval) {
        $time_str = date('h:i A', $time);
        $time_value = date('H:i', $time);
        $slots[] = [
            'value' => $time_value,
            'label' => $time_str,
            'disabled' => in_array($time_value, $booked_times),
            'selected' => ($time_value === $current_time_str)
        ];
    }

    echo json_encode([
        'success' => true,
        'slots' => $slots
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching time slots: ' . $e->getMessage()
    ]);
}
?> 
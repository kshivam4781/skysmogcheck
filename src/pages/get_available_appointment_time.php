<?php
require_once '../config/db_connection.php';

// Set header to return JSON response
header('Content-Type: application/json');

try {
    // Get the selected date from the request
    $selected_date = isset($_GET['date']) ? $_GET['date'] : null;
    
    if (!$selected_date) {
        throw new Exception('Date is required');
    }

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date)) {
        throw new Exception('Invalid date format');
    }

    // Get all booked time slots for the selected date
    $stmt = $conn->prepare("
        SELECT TIME_FORMAT(start_time, '%H:%i') as booked_time 
        FROM calendar_events 
        WHERE DATE(start_time) = ? 
        AND status = 'confirmed'
    ");
    
    if (!$stmt) {
        throw new Exception("Error preparing statement: " . $conn->error);
    }

    $stmt->bind_param("s", $selected_date);
    
    if (!$stmt->execute()) {
        throw new Exception("Error executing query: " . $stmt->error);
    }

    $result = $stmt->get_result();
    $booked_times = [];
    
    while ($row = $result->fetch_assoc()) {
        $booked_times[] = $row['booked_time'];
    }

    // Generate all possible time slots
    $start = strtotime('09:00');
    $end = strtotime('17:30');
    $interval = 30 * 60; // 30 minutes in seconds
    
    $available_slots = [];
    
    for ($time = $start; $time <= $end; $time += $interval) {
        $timeValue = date('H:i', $time);
        $timeDisplay = date('h:i A', $time);
        
        // Check if this time slot is available
        $is_available = true;
        foreach ($booked_times as $booked_time) {
            if ($timeValue === $booked_time) {
                $is_available = false;
                break;
            }
        }
        
        if ($is_available) {
            $available_slots[] = [
                'value' => $timeValue,
                'display' => $timeDisplay
            ];
        }
    }

    // Return the available time slots
    echo json_encode([
        'success' => true,
        'available_slots' => $available_slots
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} 
<?php
require_once '../config/db_connection.php';

// Set timezone to PST
date_default_timezone_set('America/Los_Angeles');

// Get the selected date from the request
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Get booked times for the selected date
$booked_times = [];
$stmt = $conn->prepare("
    SELECT start_time, TIME_FORMAT(start_time, '%H:%i') as time_slot
    FROM calendar_events 
    WHERE DATE(start_time) = ? 
    AND status = 'confirmed'
");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$result = $stmt->get_result();

// Debug: Log raw database results
error_log("=== DEBUG: Database Results ===");
while ($row = $result->fetch_assoc()) {
    error_log("Raw start_time: " . $row['start_time']);
    error_log("Formatted time_slot: " . $row['time_slot']);
    $booked_times[] = $row['time_slot'];
}
error_log("Booked times array: " . print_r($booked_times, true));

// Generate available time slots
$available_slots = [];
$start_time = strtotime('09:00');
$end_time = strtotime('17:30');
$interval = 30 * 60; // 30 minutes interval

error_log("=== DEBUG: Time Slot Generation ===");
for ($time = $start_time; $time <= $end_time; $time += $interval) {
    $time_value = date('H:i', $time);
    $time_display = date('g:i A', $time);
    $is_booked = in_array($time_value, $booked_times);
    
    error_log("Generated time value: " . $time_value);
    error_log("Generated time display: " . $time_display);
    error_log("Is booked: " . ($is_booked ? 'Yes' : 'No'));
    
    if (!$is_booked) {
        $available_slots[] = [
            'value' => $time_value,
            'display' => $time_display
        ];
    }
}

error_log("=== DEBUG: Final Results ===");
error_log("Selected Date: " . $selected_date);
error_log("Total booked times: " . count($booked_times));
error_log("Total available slots: " . count($available_slots));
error_log("Available slots: " . print_r($available_slots, true));

// Return the available slots as JSON
header('Content-Type: application/json');
echo json_encode($available_slots);
?> 
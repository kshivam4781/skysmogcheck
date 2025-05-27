<?php
require_once '../config/db_connection.php';

function saveContactMessage($name, $email, $phone, $subject, $message) {
    global $conn;
    
    // Prepare the SQL statement
    $sql = "INSERT INTO contact_messages (name, email, phone, subject, message) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    // Bind parameters
    $stmt->bind_param("sssss", $name, $email, $phone, $subject, $message);
    
    // Execute the statement
    if ($stmt->execute()) {
        return [
            'success' => true,
            'message' => 'Your message has been sent successfully. We will contact you soon.'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Error sending message. Please try again later.'
        ];
    }
}

function getAllContactMessages($limit = 10, $offset = 0) {
    global $conn;
    $sql = "SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getTotalContactMessages() {
    global $conn;
    $sql = "SELECT COUNT(*) as total FROM contact_messages";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    return $row['total'];
}

function updateContactStatus($id, $is_contacted) {
    global $conn;
    $sql = "UPDATE contact_messages SET is_contacted = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $is_contacted, $id);
    return $stmt->execute();
}

function getContactMessageById($id) {
    global $conn;
    $sql = "SELECT * FROM contact_messages WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
?> 
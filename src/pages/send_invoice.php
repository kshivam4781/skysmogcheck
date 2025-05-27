<?php
session_start();
require_once __DIR__ . '/../includes/email_helper.php';
require_once __DIR__ . '/../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['appointment_id']) || !isset($data['email_body']) || !isset($data['email_subject'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit();
}

// Fetch appointment details
$stmt = $conn->prepare("SELECT email FROM appointments WHERE id = ?");
$stmt->bind_param("i", $data['appointment_id']);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment || !isset($appointment['email'])) {
    echo json_encode(['success' => false, 'message' => 'Appointment not found or email is missing']);
    exit();
}

// Prepare attachments
$pdf_path = '../temp/Quotation_' . $data['appointment_id'] . '.pdf';
if (!file_exists($pdf_path)) {
    echo json_encode(['success' => false, 'message' => 'Invoice PDF not found']);
    exit();
}

$attachments = [
    [
        'path' => $pdf_path,
        'name' => 'Invoice_' . $data['appointment_id'] . '.pdf'
    ]
];

// Prepare CC recipients
$cc = [];
if (!empty($data['consultant_email'])) {
    $cc[] = $data['consultant_email'];
}

try {
    // Send email
    $email_sent = sendEmail(
        $appointment['email'],
        $data['email_subject'],
        $data['email_body'],
        true, // isHTML
        $attachments,
        $cc // Add CC recipients
    );

    if ($email_sent) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send email']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error sending email: ' . $e->getMessage()]);
} 
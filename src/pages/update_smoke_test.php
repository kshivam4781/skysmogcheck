<?php
// Disable error display and enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

// Create logs directory if it doesn't exist
$logDir = __DIR__ . '/../logs';
if (!file_exists($logDir)) {
    mkdir($logDir, 0777, true);
}

session_start();
require_once '../config/db_connection.php';
require_once '../includes/email_helper.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';

// Create FPDF extension class for watermark
class PDF extends FPDF {
    function RotatedText($x, $y, $txt, $angle) {
        $this->SetTextColor(240, 240, 240); // Light gray
        $this->Text($x, $y, $txt);
        $this->SetTextColor(0, 0, 0); // Reset to black
    }
}

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in and has consultant access
if (!isset($_SESSION['user_id']) || !isset($_SESSION['accountType']) || $_SESSION['accountType'] != 2) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Function to generate smoke test certificate PDF
function generateSmokeTestCertificate($vehicle, $consultant_name) {
    // Create new PDF document
    $pdf = new PDF();
    $pdf->AddPage();

    // Set document information
    $pdf->SetTitle('Smoke Test Certificate');
    $pdf->SetAuthor('Sky Smoke Check LLC');

    // Company Logo
    $pdf->Image(__DIR__ . '/../assets/images/logo.png', 20, 10, 50);

    // Title
    $pdf->SetFont('Arial', 'B', 24);
    $pdf->Cell(0, 20, 'Smoke Test Certificate', 0, 1, 'C');
    $pdf->Ln(5);

    // Certificate Number and Date
    $pdf->SetFont('Arial', '', 10);
    $certificate_number = 'STC-' . date('Ymd') . '-' . str_pad($vehicle['id'], 4, '0', STR_PAD_LEFT);
    $pdf->Cell(0, 5, 'Certificate Number: ' . $certificate_number, 0, 1, 'R');
    $pdf->Cell(0, 5, 'Issue Date: ' . date('F j, Y'), 0, 1, 'R');
    $pdf->Ln(5);

    // Certificate Content
    $pdf->SetFont('Arial', '', 14);
    $pdf->Cell(0, 10, 'This is to certify that', 0, 1, 'C');
    $pdf->Ln(5);

    // Vehicle Details
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, $vehicle['vehYear'] . ' ' . $vehicle['vehMake'], 0, 1, 'C');
    $pdf->Ln(5);

    // Vehicle Information
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(40, 8, 'VIN Number:', 0, 0);
    $pdf->Cell(0, 8, $vehicle['vin'], 0, 1);
    $pdf->Cell(40, 8, 'License Plate:', 0, 0);
    $pdf->Cell(0, 8, $vehicle['plateNo'], 0, 1);
    $pdf->Cell(40, 8, 'Company:', 0, 0);
    $pdf->Cell(0, 8, $vehicle['companyName'], 0, 1);
    $pdf->Ln(5);

    // Test Results
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Test Results', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(40, 8, 'Status:', 0, 0);
    $pdf->SetTextColor(0, 128, 0); // Green color for passed
    $pdf->Cell(0, 8, 'PASSED', 0, 1);
    $pdf->SetTextColor(0, 0, 0); // Reset text color
    $pdf->Cell(40, 8, 'Test Date:', 0, 0);
    $pdf->Cell(0, 8, date('F j, Y'), 0, 1);
    $pdf->Cell(40, 8, 'Test Time:', 0, 0);
    $pdf->Cell(0, 8, date('h:i A'), 0, 1);
    $pdf->Cell(40, 8, 'Next Due:', 0, 0);
    $pdf->Cell(0, 8, date('F j, Y', strtotime('+6 months')), 0, 1);
    $pdf->Ln(5);

    // Consultant Information
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(40, 8, 'Tested By:', 0, 0);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 8, $consultant_name, 0, 1);
    $pdf->Ln(5);

    // Certification Box
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 5, 'I certify, under penalty of perjury under the laws of the State of California, that I inspected the vehicle described above, that I performed the inspection in accordance with all bureau requirements, and that the information listed on this vehicle inspection report is true and correct.', 1, 'L');
    
    // Date and Signature
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(40, 8, 'Date:', 0, 0);
    $pdf->Cell(0, 8, date('F j, Y h:i A T'), 0, 1);
    $pdf->Cell(40, 8, 'Signature:', 0, 0);
    $pdf->SetFont('Arial', 'I', 12);
    $pdf->Cell(0, 8, $consultant_name, 0, 1);

    // Company Information
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Sky Smoke Check LLC', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, '121 E 11th St, Tracy, CA 95376', 0, 1, 'C');
    $pdf->Cell(0, 5, 'Phone: (209) 123-4567 | Email: support@skysmokecheck.com', 0, 1, 'C');

    // Disclaimer
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->MultiCell(0, 4, 'This certificate is valid for 6 months from the date of issue. The information contained in this certificate is based on the smoke test performed on the specified date. Sky Smoke Check LLC is not responsible for any changes in the vehicle\'s condition after the test date.', 0, 'C');

    // Generate unique filename
    $filename = 'smoke_test_certificate_' . $vehicle['id'] . '_' . date('YmdHis') . '.pdf';
    $filepath = '../uploads/smoke_tests/' . $filename;

    // Create directory if it doesn't exist
    if (!file_exists('../uploads/smoke_tests/')) {
        mkdir('../uploads/smoke_tests/', 0777, true);
    }

    // Save PDF
    $pdf->Output('F', $filepath);

    return 'uploads/smoke_tests/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehicle_id = $_POST['vehicle_id'] ?? null;
    $status = $_POST['status'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $send_reminder = isset($_POST['send_reminder']) ? true : false;
    $error_code = $_POST['error_code'] ?? null;
    $warmup_cycles = $_POST['warmup_cycles'] ?? null;

    if (!$vehicle_id || !$status) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit();
    }

    try {
        $conn->begin_transaction();

        // Get vehicle and appointment information
        $stmt = $conn->prepare("
            SELECT v.*, a.id as appointment_id, a.number_of_vehicles, a.status as appointment_status, a.email, a.Name, a.companyName,
                   acc.email as consultant_email, acc.firstName as consultant_first_name, acc.lastName as consultant_last_name
            FROM vehicles v
            LEFT JOIN appointments a ON v.appointment_id = a.id
            LEFT JOIN calendar_events ce ON v.id = ce.vehid
            LEFT JOIN accounts acc ON ce.user_id = acc.email
            WHERE v.id = ?
        ");
        if (!$stmt) {
            throw new Exception("Failed to prepare vehicle query: " . $conn->error);
        }
        
        $stmt->bind_param("i", $vehicle_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to execute vehicle query: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        $vehicle = $result->fetch_assoc();

        if (!$vehicle) {
            throw new Exception('Vehicle not found');
        }

        // Update calendar_events
        $stmt = $conn->prepare("
            UPDATE calendar_events 
            SET status = 'completed', 
                updated_at = NOW(),
                user_id = ?
            WHERE vehid = ?
        ");
        if (!$stmt) {
            throw new Exception("Failed to prepare calendar_events update: " . $conn->error);
        }
        
        $stmt->bind_param("si", $_SESSION['user_id'], $vehicle_id);
        if (!$stmt->execute()) {
            throw new Exception("Failed to update calendar_events: " . $stmt->error);
        }

        // Handle file upload if present
        $attachment_path = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/smoke_tests/';
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    throw new Exception("Failed to create upload directory");
                }
            }
            
            $file_extension = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
            $file_name = uniqid() . '.' . $file_extension;
            $target_path = $upload_dir . $file_name;
            
            if (!move_uploaded_file($_FILES['attachment']['tmp_name'], $target_path)) {
                throw new Exception("Failed to move uploaded file");
            }
            $attachment_path = 'uploads/smoke_tests/' . $file_name;
        }

        // Update vehicles table
        $stmt = $conn->prepare("
            UPDATE vehicles 
            SET smoke_test_status = ?,
                smoke_test_notes = ?,
                next_due_date = CASE 
                    WHEN ? = 'passed' THEN DATE_ADD(NOW(), INTERVAL 6 MONTH)
                    ELSE NULL
                END,
                attachment_path = COALESCE(?, attachment_path),
                result = CASE 
                    WHEN ? = 'passed' THEN 'pass'
                    WHEN ? = 'failed' THEN 'fail'
                    WHEN ? = 'warmup' THEN 'warmup'
                    ELSE result
                END,
                error_code = CASE 
                    WHEN ? = 'failed' THEN ?
                    ELSE NULL
                END,
                warm_up = CASE
                    WHEN ? = 'warmup' THEN ?
                    ELSE NULL
                END
            WHERE id = ?
        ");
        if (!$stmt) {
            throw new Exception("Failed to prepare vehicles update: " . $conn->error);
        }
        
        $stmt->bind_param("sssssssssssi", 
            $status, 
            $notes, 
            $status,
            $attachment_path,
            $status,
            $status,
            $status,
            $status,
            $error_code,
            $status,
            $warmup_cycles,
            $vehicle_id
        );
        if (!$stmt->execute()) {
            throw new Exception("Failed to update vehicles: " . $stmt->error);
        }

        // Handle service_id specific logic
        if ($vehicle['service_id'] == 2 || $vehicle['service_id'] == 3) {
            if ($vehicle['appointment_id']) {
                // Check if all vehicles in the appointment are completed
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as total_vehicles,
                           SUM(CASE 
                               WHEN service_id = 1 AND clean_truck_check_status != 'pending' THEN 1
                               WHEN service_id = 2 AND smoke_test_status != 'pending' THEN 1
                               WHEN service_id = 3 AND clean_truck_check_status != 'pending' AND smoke_test_status != 'pending' THEN 1
                               ELSE 0
                           END) as completed_vehicles
                    FROM vehicles
                    WHERE appointment_id = ?
                ");
                if (!$stmt) {
                    throw new Exception("Failed to prepare appointment check query: " . $conn->error);
                }
                
                $stmt->bind_param("i", $vehicle['appointment_id']);
                if (!$stmt->execute()) {
                    throw new Exception("Failed to execute appointment check query: " . $stmt->error);
                }
                
                $result = $stmt->get_result();
                $vehicle_status = $result->fetch_assoc();

                // Update appointment status if all vehicles are completed
                if ($vehicle_status['total_vehicles'] == $vehicle_status['completed_vehicles']) {
                    $stmt = $conn->prepare("
                        UPDATE appointments 
                        SET status = 'completed' 
                        WHERE id = ?
                    ");
                    if (!$stmt) {
                        throw new Exception("Failed to prepare appointment update: " . $conn->error);
                    }
                    
                    $stmt->bind_param("i", $vehicle['appointment_id']);
                    if (!$stmt->execute()) {
                        throw new Exception("Failed to update appointment status: " . $stmt->error);
                    }
                }
            }
        }

        // Handle service_id = 3 specific logic
        if ($vehicle['service_id'] == 3) {
            error_log("Starting clean_truck_checks update for service_id = 3");
            error_log("Vehicle data: " . print_r($vehicle, true));
            error_log("Input data: " . print_r($_POST, true));

            // First check if record exists in clean_truck_checks
            $stmt = $conn->prepare("
                SELECT id FROM clean_truck_checks 
                WHERE vehicle_id = ? AND appointment_id = ?
            ");
            
            if (!$stmt) {
                error_log("Failed to prepare clean_truck_checks check query: " . $conn->error);
                throw new Exception("Failed to prepare clean_truck_checks check query: " . $conn->error);
            }
            
            $stmt->bind_param("ii", $vehicle_id, $vehicle['appointment_id']);
            if (!$stmt->execute()) {
                error_log("Failed to execute clean_truck_checks check query: " . $stmt->error);
                throw new Exception("Failed to execute clean_truck_checks check query: " . $stmt->error);
            }
            
            $result = $stmt->get_result();
            $check_exists = $result->fetch_assoc();
            error_log("Check exists result: " . print_r($check_exists, true));

            if (!$check_exists) {
                error_log("No existing record found, preparing to insert new record");
                // Insert new record if it doesn't exist
                $stmt = $conn->prepare("
                    INSERT INTO clean_truck_checks 
                    (appointment_id, service_id, vin_number, plate_number, vehicle_make, 
                     vehicle_year, smog_check_completed, smog_check_verified, 
                     smog_check_status, smog_check_pending_reason, vehicle_id, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'yes', 'yes', ?, ?, ?, NOW())
                ");
                
                if (!$stmt) {
                    error_log("Failed to prepare clean_truck_checks insert: " . $conn->error);
                    throw new Exception("Failed to prepare clean_truck_checks insert: " . $conn->error);
                }
                
                $smog_status = $status === 'failed' ? 'failed' : 
                              ($status === 'warmup' ? 'pending' : 'confirmed');
                error_log("Setting smog_check_status to: " . $smog_status);
                
                // Set pending reason based on status
                $pending_reason = null;
                if ($status === 'failed' && $error_code) {
                    $pending_reason = "Error Code: " . $error_code;
                } elseif ($status === 'warmup' && $warmup_cycles) {
                    $pending_reason = "Warm up required: " . $warmup_cycles . " cycle";
                }
                error_log("Setting smog_check_pending_reason to: " . $pending_reason);
                
                $bind_params = array(
                    $vehicle['appointment_id'],
                    $vehicle['service_id'],
                    $vehicle['vin'],
                    $vehicle['plateNo'],
                    $vehicle['vehMake'],
                    $vehicle['vehYear'],
                    $smog_status,
                    $pending_reason,
                    $vehicle_id
                );
                error_log("Insert bind parameters: " . print_r($bind_params, true));
                
                $stmt->bind_param("iissssssi", 
                    $bind_params[0],
                    $bind_params[1],
                    $bind_params[2],
                    $bind_params[3],
                    $bind_params[4],
                    $bind_params[5],
                    $bind_params[6],
                    $bind_params[7],
                    $bind_params[8]
                );
                
                if (!$stmt->execute()) {
                    error_log("Failed to insert clean_truck_checks: " . $stmt->error);
                    throw new Exception("Failed to insert clean_truck_checks: " . $stmt->error);
                }
                error_log("Successfully inserted new clean_truck_checks record");
            } else {
                error_log("Existing record found, preparing to update");
                // Update existing record
                $stmt = $conn->prepare("
                    UPDATE clean_truck_checks 
                    SET smog_check_status = ?,
                        smog_check_completed = 'yes',
                        smog_check_verified = 'yes',
                        smog_check_pending_reason = ?,
                        updated_at = NOW()
                    WHERE vehicle_id = ? AND appointment_id = ?
                ");
                
                if (!$stmt) {
                    error_log("Failed to prepare clean_truck_checks update: " . $conn->error);
                    throw new Exception("Failed to prepare clean_truck_checks update: " . $conn->error);
                }
                
                $smog_status = $status === 'failed' ? 'failed' : 
                              ($status === 'warmup' ? 'pending' : 'confirmed');
                error_log("Setting smog_check_status to: " . $smog_status);
                
                // Set pending reason based on status
                $pending_reason = null;
                if ($status === 'failed' && $error_code) {
                    $pending_reason = "Error Code: " . $error_code;
                } elseif ($status === 'warmup' && $warmup_cycles) {
                    $pending_reason = "Warm up required: " . $warmup_cycles . " cycle";
                }
                error_log("Setting smog_check_pending_reason to: " . $pending_reason);
                
                $stmt->bind_param("ssii", 
                    $smog_status,
                    $pending_reason,
                    $vehicle_id, 
                    $vehicle['appointment_id']
                );
                
                if (!$stmt->execute()) {
                    error_log("Failed to update clean_truck_checks: " . $stmt->error);
                    throw new Exception("Failed to update clean_truck_checks: " . $stmt->error);
                }
                error_log("Successfully updated clean_truck_checks record");
            }
        } else {
            error_log("Skipping clean_truck_checks update - service_id is not 3. Current service_id: " . $vehicle['service_id']);
        }

        // Generate PDF certificate if test passed
        if ($status === 'passed') {
            $certificate_path = generateSmokeTestCertificate($vehicle, $vehicle['consultant_first_name'] . ' ' . $vehicle['consultant_last_name']);
            if ($certificate_path) {
                // Update attachment_path with the certificate
                $stmt = $conn->prepare("UPDATE vehicles SET attachment_path = ? WHERE id = ?");
                $stmt->bind_param("si", $certificate_path, $vehicle_id);
                $stmt->execute();
            }
        }

        // Send email notification
        $to = $vehicle['email'];
        $subject = "Smoke Test Results - Sky Smoke Check LLC";
        
        // Status colors and icons
        $status_colors = [
            'passed' => ['color' => '#28a745', 'icon' => '✓'],
            'failed' => ['color' => '#dc3545', 'icon' => '✗'],
            'warmup' => ['color' => '#ffc107', 'icon' => '⚠']
        ];
        
        $status_texts = [
            'passed' => 'Test Passed',
            'failed' => 'Test Failed',
            'warmup' => 'Warm Up Required'
        ];
        
        $status_color = $status_colors[$status]['color'];
        $status_icon = $status_colors[$status]['icon'];
        $status_text = $status_texts[$status];
        
        $consultant_name = $vehicle['consultant_first_name'] . ' ' . $vehicle['consultant_last_name'];
        
        $message = "
            <html>
            <head>
                <style>
                    body { 
                        font-family: Arial, sans-serif; 
                        line-height: 1.6;
                        color: #333;
                    }
                    .container { 
                        max-width: 600px; 
                        margin: 0 auto; 
                        padding: 20px;
                    }
                    .header { 
                        text-align: center; 
                        padding: 20px 0;
                        background-color: #f8f9fa;
                        border-radius: 5px;
                    }
                    .logo {
                        max-width: 200px;
                        margin-bottom: 20px;
                    }
                    .status-banner {
                        background-color: {$status_color};
                        color: white;
                        padding: 20px;
                        text-align: center;
                        border-radius: 5px;
                        margin: 20px 0;
                    }
                    .status-icon {
                        font-size: 2.5em;
                        margin-bottom: 10px;
                    }
                    .status-text {
                        font-size: 1.5em;
                        font-weight: bold;
                    }
                    .details-card {
                        background-color: #f8f9fa;
                        padding: 20px;
                        border-radius: 5px;
                        margin: 20px 0;
                        border: 1px solid #dee2e6;
                    }
                    .details-title {
                        color: #2c3e50;
                        margin-bottom: 15px;
                        font-weight: 600;
                        border-bottom: 2px solid #007bff;
                        padding-bottom: 10px;
                    }
                    .details-row {
                        margin-bottom: 10px;
                        display: flex;
                        align-items: flex-start;
                    }
                    .details-label {
                        font-weight: bold;
                        color: #6c757d;
                        min-width: 150px;
                    }
                    .details-value {
                        flex: 1;
                    }
                    .footer {
                        text-align: center;
                        padding: 20px;
                        color: #6c757d;
                        font-size: 0.9em;
                        border-top: 1px solid #dee2e6;
                        margin-top: 20px;
                    }
                    .attachments {
                        margin-top: 20px;
                        padding: 15px;
                        background-color: #e9ecef;
                        border-radius: 5px;
                    }
                    .attachments-title {
                        font-weight: bold;
                        margin-bottom: 10px;
                    }
                    .attachment-item {
                        margin: 5px 0;
                        color: #0056b3;
                    }
                    .failure-notice {
                        background-color: #fff3cd;
                        border: 1px solid #ffeeba;
                        color: #856404;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 20px 0;
                    }
                    .failure-notice h3 {
                        color: #856404;
                        margin-top: 0;
                    }
                    .consultant-info {
                        background-color: #e9ecef;
                        padding: 15px;
                        border-radius: 5px;
                        margin: 20px 0;
                    }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <img src='../assets/images/logo.png' alt='Sky Smoke Check LLC' class='logo'>
                        <h2>Smoke Test Results</h2>
                    </div>
                    
                    <div class='status-banner'>
                        <div class='status-icon'>{$status_icon}</div>
                        <div class='status-text'>{$status_text}</div>
                    </div>
                    
                    " . ($status === 'failed' ? "
                    <div class='failure-notice'>
                        <h3>Test Failed</h3>
                        <p>We regret to inform you that your vehicle has failed the smoke test. Please review the details below and take necessary actions to address the issues identified during the test.</p>
                    </div>
                    " : "") . "
                    
                    <div class='details-card'>
                        <div class='details-title'>Test Details</div>
                        <div class='details'>
                            <div class='details-row'>
                                <span class='details-label'>Company:</span>
                                <span class='details-value'>{$vehicle['companyName']}</span>
                            </div>
                            <div class='details-row'>
                                <span class='details-label'>Vehicle:</span>
                                <span class='details-value'>{$vehicle['vehYear']} {$vehicle['vehMake']}</span>
                            </div>
                            <div class='details-row'>
                                <span class='details-label'>VIN:</span>
                                <span class='details-value'>{$vehicle['vin']}</span>
                            </div>
                            <div class='details-row'>
                                <span class='details-label'>License Plate:</span>
                                <span class='details-value'>{$vehicle['plateNo']}</span>
                            </div>
                            " . ($notes ? "<div class='details-row'>
                                <span class='details-label'>Notes:</span>
                                <span class='details-value'>{$notes}</span>
                            </div>" : "") . "
                            " . ($error_code ? "<div class='details-row'>
                                <span class='details-label'>Error Code:</span>
                                <span class='details-value'>{$error_code}</span>
                            </div>" : "") . "
                            " . ($warmup_cycles ? "<div class='details-row'>
                                <span class='details-label'>Warm Up Cycles:</span>
                                <span class='details-value'>{$warmup_cycles}</span>
                            </div>" : "") . "
                            " . ($status === 'passed' && $next_due_date ? "<div class='details-row'>
                                <span class='details-label'>Next Test Due:</span>
                                <span class='details-value'>" . date('F j, Y', strtotime($next_due_date)) . "</span>
                            </div>" : "") . "
                        </div>
                    </div>
                    
                    " . ($attachment_path ? "
                    <div class='attachments'>
                        <div class='attachments-title'>Attachments:</div>
                        <div class='attachment-item'>✓ Test Results</div>
                    </div>
                    " : "") . "
                    
                    <div class='consultant-info'>
                        <h4>Need Help?</h4>
                        <p>Feel free to contact your consultant if you have any questions:</p>
                        <p><strong>Consultant:</strong> {$consultant_name}</p>
                        <p><strong>Email:</strong> {$vehicle['consultant_email']}</p>
                    </div>
                    
                    <div class='footer'>
                        <p>Thank you for choosing Sky Smoke Check LLC.</p>
                        <p>Best regards,<br>Sky Smoke Check LLC Team</p>
                        <p style='font-size: 0.8em; margin-top: 20px;'>
                            This is an automated message. Please do not reply to this email.<br>
                            For any questions, please contact us at support@skysmokecheck.com
                        </p>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        try {
            // Prepare attachments array
            $attachments = [];
            if ($attachment_path) {
                $attachments[] = ['path' => '../' . $attachment_path];
            }
            if ($status === 'passed' && isset($certificate_path)) {
                $attachments[] = ['path' => '../' . $certificate_path];
            }
            
            // Send email with attachments and CC
            $cc = [$vehicle['consultant_email']];
            
            if (!sendEmail($to, $subject, $message, true, $attachments, $cc)) {
                error_log("Failed to send email to: " . $to);
                // Don't throw exception for email failure, just log it
            }
        } catch (Exception $e) {
            error_log("Error sending email: " . $e->getMessage());
            // Don't throw exception for email failure, just log it
        }

        // Commit transaction
        $conn->commit();
        
        echo json_encode(['success' => true, 'message' => 'Smog test result updated successfully']);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        error_log("Error updating smoke test: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 
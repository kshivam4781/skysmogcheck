<?php
// Prevent any output before JSON response
ob_start();

// Enable error reporting but log to file instead of output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/php_errors.log');

// Ensure no whitespace or output before this point
session_start();

// Set JSON header
header('Content-Type: application/json');

try {
    // Check if required files exist
    if (!file_exists('../config/db_connection.php')) {
        throw new Exception('Database configuration file not found');
    }

    // Load required files
    require_once __DIR__ . '/../../vendor/autoload.php';
    require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';
    require_once '../config/db_connection.php';
    require_once '../includes/email_helper.php';

    // Check if this is a preview request
    $is_preview = isset($_GET['preview']) && $_GET['preview'] === 'true';
    $is_submit = isset($_GET['submit']) && $_GET['submit'] === 'true';

    if (!$is_preview && !$is_submit) {
        throw new Exception('Invalid request type');
    }

    // Start transaction only for submission
    if ($is_submit) {
        $conn->begin_transaction();
    }

    try {
        // Validate required fields
        $required_fields = ['company', 'name', 'email', 'phone', 'service_type', 'vehicle_year', 'make', 'vin', 'license_plate'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty($_POST[$field])) {
                throw new Exception("Missing required field: " . $field);
            }
        }

        // Calculate total price
        $total_price = 0;
        $services = [];
        $result = $conn->query("SELECT id, name, price FROM services");
        if (!$result) {
            throw new Exception("Database error: " . $conn->error);
        }
        while ($row = $result->fetch_assoc()) {
            $services[$row['id']] = $row;
        }

        foreach ($_POST['service_type'] as $index => $service_id) {
            if (!isset($services[$service_id])) {
                throw new Exception("Invalid service ID: " . $service_id);
            }
            $total_price += $services[$service_id]['price'];
        }

        // Only insert into database if this is a submission
        if ($is_submit) {
            // Insert into appointments table
            $stmt = $conn->prepare("
                INSERT INTO appointments (
                    companyName, Name, email, phone, test_location, test_address,
                    special_instructions, number_of_vehicles, status, created_at, total_price
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), ?)
            ");

            $number_of_vehicles = count($_POST['vehicle_year']);
            
            // Handle test location properly
            $test_location = null;
            $test_address = null;
            
            // Only set test location and address if service_id is not 1 (Clean Truck Check)
            if (isset($_POST['test_location']) && is_array($_POST['test_location'])) {
                foreach ($_POST['test_location'] as $index => $location) {
                    if ($_POST['service_type'][$index] != 1) {
                        $test_location = $location;
                        if ($location === 'your_location' && isset($_POST['custom_location'][$index])) {
                            $test_address = $_POST['custom_location'][$index];
                        } else {
                            $test_address = '121 E 11th St, Tracy, CA 95376'; // Default address
                        }
                        break;
                    }
                }
            }
            
            $special_instructions = $_POST['special_instructions'] ?? '';

            $stmt->bind_param(
                "sssssssid",
                $_POST['company'],
                $_POST['name'],
                $_POST['email'],
                $_POST['phone'],
                $test_location,
                $test_address,
                $special_instructions,
                $number_of_vehicles,
                $total_price
            );

            if (!$stmt->execute()) {
                throw new Exception("Error creating appointment: " . $stmt->error);
            }

            $appointment_id = $conn->insert_id;

            // Insert vehicles and handle service-specific tables
            foreach ($_POST['vehicle_year'] as $index => $year) {
                $service_id = $_POST['service_type'][$index];
                
                // Insert into vehicles table
                $stmt = $conn->prepare("
                    INSERT INTO vehicles (
                        appointment_id, vehYear, vehMake, vin, plateNo,
                        created_at, clean_truck_check_status, service_id
                    ) VALUES (?, ?, ?, ?, ?, NOW(), 'pending', ?)
                ");

                $stmt->bind_param(
                    "iisssi",
                    $appointment_id,
                    $year,
                    $_POST['make'][$index],
                    $_POST['vin'][$index],
                    $_POST['license_plate'][$index],
                    $service_id
                );

                if (!$stmt->execute()) {
                    throw new Exception("Error creating vehicle record: " . $stmt->error);
                }

                $vehicle_id = $conn->insert_id;

                // Handle service-specific tables based on service_id
                if ($service_id == 1 || $service_id == 3) {
                    // Get smog check completion status from radio button
                    $smog_check_completed = isset($_POST['smog_check_completed'][$index]) ? $_POST['smog_check_completed'][$index] : 'no';
                    $pending_reason = $_POST['smog_check_pending_reason'][$index] ?? null;
                    $expected_date = !empty($_POST['smog_check_expected_date'][$index]) ? $_POST['smog_check_expected_date'][$index] : null;

                    // Insert into clean_truck_checks
                    $stmt = $conn->prepare("
                        INSERT INTO clean_truck_checks (
                            appointment_id, service_id, vin_number, plate_number,
                            vehicle_make, vehicle_year, smog_check_completed,
                            smog_check_verified, smog_check_pending_reason,
                            smog_check_expected_date, smog_check_status, clean_truck_status, created_at, vehicle_id
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'no', ?, ?, 'pending', 'not confirmed', NOW(), ?)
                    ");

                    $stmt->bind_param(
                        "iisssssssi",
                        $appointment_id,
                        $service_id,
                        $_POST['vin'][$index],
                        $_POST['license_plate'][$index],
                        $_POST['make'][$index],
                        $year,
                        $smog_check_completed,
                        $pending_reason,
                        $expected_date,
                        $vehicle_id
                    );

                    if (!$stmt->execute()) {
                        throw new Exception("Error creating clean truck check record: " . $stmt->error);
                    }
                }

                if ($service_id == 2 || $service_id == 3) {
                    // Insert into calendar_events
                    $title = $_POST['company'] . " - Smog Test";
                    $start_time = $_POST['test_date'][$index] . ' ' . $_POST['test_time'][$index];
                    $end_time = date('Y-m-d H:i:s', strtotime($start_time . ' +30 minutes'));
                    $description = "Test address: " . ($test_address ?? '121 E 11th St, Tracy, CA 95376');

                    $stmt = $conn->prepare("
                        INSERT INTO calendar_events (
                            vehid, appointment_id, title, start_time, end_time,
                            description, created_at, status
                        ) VALUES (?, ?, ?, ?, ?, ?, NOW(), 'pending')
                    ");

                    $stmt->bind_param(
                        "iissss",
                        $vehicle_id,
                        $appointment_id,
                        $title,
                        $start_time,
                        $end_time,
                        $description
                    );

                    if (!$stmt->execute()) {
                        throw new Exception("Error creating calendar event: " . $stmt->error);
                    }
                }
            }

            // Commit transaction
            $conn->commit();

            // Create appointment array from POST data
            $appointment = [
                'companyName' => $_POST['company'],
                'Name' => $_POST['name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'special_instructions' => $_POST['special_instructions'] ?? '',
                'test_location' => $test_location,
                'test_address' => $test_address,
                'appointment_id' => $appointment_id
            ];

            // Create vehicles array from POST data
            $vehicles = [];
            foreach ($_POST['vehicle_year'] as $index => $year) {
                $service_id = $_POST['service_type'][$index];
                $service_name = $services[$service_id]['name'];
                if ($service_id == '3') {
                    $service_name = 'Smog Test and Clean Truck Check';
                }
                
                $vehicles[] = [
                    'vehYear' => $year,
                    'vehMake' => $_POST['make'][$index],
                    'plateNo' => $_POST['license_plate'][$index],
                    'vin' => $_POST['vin'][$index],
                    'service_name' => $service_name,
                    'service_price' => $services[$service_id]['price']
                ];
            }

            // Calculate final price
            $discount_amount = 0; // You can add discount logic here if needed
            $final_price = $total_price - $discount_amount;

            // Determine service type for email subject
            $has_smog = false;
            $has_clean_truck = false;
            foreach ($vehicles as $vehicle) {
                if (strpos($vehicle['service_name'], 'Smog Test') !== false) {
                    $has_smog = true;
                }
                if (strpos($vehicle['service_name'], 'Clean Truck Check') !== false) {
                    $has_clean_truck = true;
                }
            }

            // Generate service-specific subject line
            if ($has_smog && $has_clean_truck) {
                $email_subject = "New Combined Service Request - " . $_POST['company'];
            } elseif ($has_smog) {
                $email_subject = "New Smog Test Request - " . $_POST['company'];
            } elseif ($has_clean_truck) {
                $email_subject = "New Clean Truck Check Request - " . $_POST['company'];
            } else {
                $email_subject = "New Service Request - " . $_POST['company'];
            }

            // Generate email content
            $email_body = generateNotificationEmail($appointment, $vehicles, $total_price, $discount_amount, $final_price);

            // Create temporary file for PDF attachment
            $temp_pdf_path = sys_get_temp_dir() . '/quotation_' . $appointment_id . '.pdf';
            file_put_contents($temp_pdf_path, $pdf_content);

            // Fetch all consultant emails
            $consultant_emails = [];
            $result = $conn->query("SELECT email FROM accounts WHERE accountType = 2");
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $consultant_emails[] = $row['email'];
                }
            }

            // Send notification email to all consultants
            $notification_sent = true;
            foreach ($consultant_emails as $consultant_email) {
                $sent = sendEmail(
                    $consultant_email,
                    $email_subject,
                    $email_body,
                    true, // isHTML
                    [$temp_pdf_path], // PDF attachment
                    [] // no CC
                );
                
                if (!$sent) {
                    error_log("Failed to send notification email to consultant: " . $consultant_email . " for appointment ID: " . $appointment_id);
                    $notification_sent = false;
                }
            }

            // Clean up temporary file
            if (file_exists($temp_pdf_path)) {
                unlink($temp_pdf_path);
            }

            if (!$notification_sent) {
                error_log("Failed to send notification emails for appointment ID: " . $appointment_id);
            }
        }

        // Generate PDF for both preview and submission
        class PDF extends FPDF {
            function Header() {
                // Logo
                $logo_path = __DIR__ . '/../assets/images/logo.png';
                if (file_exists($logo_path)) {
                    $this->Image($logo_path, 10, 10, 30);
                }
                
                // Company Name
                $this->SetFont('Arial', 'B', 20);
                $this->Cell(0, 10, 'Sky Smoke Check LLC', 0, 1, 'R');
                
                // Address
                $this->SetFont('Arial', '', 10);
                $this->Cell(0, 5, '121 E 11th St, Tracy, CA 95376', 0, 1, 'R');
                $this->Cell(0, 5, 'Phone: (555) 123-4567', 0, 1, 'R');
                $this->Cell(0, 5, 'Email: info@skysmoke.com', 0, 1, 'R');
                
                // Line break
                $this->Ln(10);
            }

            function Footer() {
                $this->SetY(-15);
                $this->SetFont('Arial', 'I', 8);
                $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
            }
        }

        $pdf = new PDF();
        $pdf->AliasNbPages();
        $pdf->AddPage();

        // Quotation Title
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'QUOTATION', 0, 1, 'C');
        $pdf->Ln(5);

        // Quotation Details
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(30, 5, 'Quotation #:', 0);
        $pdf->Cell(0, 5, $appointment_id, 0, 1);
        $pdf->Cell(30, 5, 'Date:', 0);
        $pdf->Cell(0, 5, date('F d, Y'), 0, 1);
        $pdf->Ln(5);

        // Customer Information
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 5, 'Customer Information', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(30, 5, 'Company:', 0);
        $pdf->Cell(0, 5, $_POST['company'], 0, 1);
        $pdf->Cell(30, 5, 'Contact:', 0);
        $pdf->Cell(0, 5, $_POST['name'], 0, 1);
        $pdf->Cell(30, 5, 'Email:', 0);
        $pdf->Cell(0, 5, $_POST['email'], 0, 1);
        $pdf->Cell(30, 5, 'Phone:', 0);
        $pdf->Cell(0, 5, $_POST['phone'], 0, 1);
        $pdf->Ln(5);

        // Vehicles Table
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 5, 'Vehicle Details', 0, 1);
        $pdf->Ln(2);

        // Table Header
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(10, 7, '#', 1);
        $pdf->Cell(50, 7, 'Service', 1);
        $pdf->Cell(100, 7, 'Description', 1);
        $pdf->Cell(30, 7, 'Amount', 1);
        $pdf->Ln();

        // Table Data
        $pdf->SetFont('Arial', '', 10);
        $total = 0;
        $w = [10, 50, 100, 30]; // Column widths: #, Service, Description, Amount
        $lineHeight = 5;

        foreach ($_POST['vehicle_year'] as $index => $year) {
            $service_id = $_POST['service_type'][$index];
            if (!isset($services[$service_id])) {
                throw new Exception("Invalid service ID: " . $service_id);
            }
            $service = $services[$service_id];
            
            // Format service name based on service_id
            $service_name = $service['name'];
            if ($service_id == '3') {
                $service_name = 'Smog Test & Clean Truck Check';
            }
            
            // Create description with only VIN and License
            $description = sprintf(
                "VIN: %s, License: %s",
                $_POST['vin'][$index],
                $_POST['license_plate'][$index]
            );
            
            // Calculate number of lines for each MultiCell
            $serviceLines = $pdf->GetStringWidth($service_name) / ($w[1] - 2);
            $descLines = $pdf->GetStringWidth($description) / ($w[2] - 2);
            $serviceLines = ceil($serviceLines);
            $descLines = ceil($descLines);
            $maxLines = max($serviceLines, $descLines, 1);
            $rowHeight = $lineHeight * $maxLines;
            
            // Save current X and Y
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            
            // Draw #
            $pdf->Cell($w[0], $rowHeight, ($index + 1), 1, 0, 'C');
            
            // Draw Service (MultiCell)
            $pdf->SetXY($x + $w[0], $y);
            $pdf->MultiCell($w[1], $lineHeight, $service_name, 1);
            
            // Draw Description (MultiCell)
            $pdf->SetXY($x + $w[0] + $w[1], $y);
            $pdf->MultiCell($w[2], $lineHeight, $description, 1);
            
            // Draw Amount
            $pdf->SetXY($x + $w[0] + $w[1] + $w[2], $y);
            $pdf->Cell($w[3], $rowHeight, '$' . number_format($service['price'], 2), 1, 0, 'R');
            
            // Move to the next line
            $pdf->SetY($y + $rowHeight);
            
            $total += $service['price'];
        }

        // Total
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(160, 7, 'Total', 1, 0, 'R');
        $pdf->Cell(30, 7, '$' . number_format($total, 2), 1, 1);

        // Terms and Conditions
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 5, 'Terms and Conditions', 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('Arial', '', 10);
        $terms = [
            '1. Service Validity: This quotation is valid for 30 days from the date of issue.',
            '2. Payment Terms: Full payment is required upon service completion.',
            '3. Scheduling: Appointments are subject to availability and must be confirmed at least 24 hours in advance.',
            '4. Cancellation Policy: 24-hour notice required for cancellation or rescheduling.',
            '5. Vehicle Requirements:',
            '   - Vehicle must be in running condition',
            '   - All warning lights must be addressed before testing',
            '   - Vehicle must be clean and accessible for testing',
            '6. Test Results:',
            '   - SMOG Test results are immediately available',
            '   - Clean Truck Check results are typically available within 24 hours',
            '7. Warranty:',
            '   - 90-day warranty on all testing equipment',
            '   - No warranty on test results as they reflect actual vehicle condition',
            '8. Compliance: All testing is performed in accordance with state and federal regulations.',
            '9. Insurance: We maintain appropriate liability insurance for all services.',
            '10. Force Majeure: We are not liable for delays or failures due to circumstances beyond our control.'
        ];

        foreach ($terms as $term) {
            $pdf->MultiCell(0, 5, $term);
            $pdf->Ln(2);
        }

        $pdf_content = $pdf->Output('S');

        // Clear any output buffer
        ob_clean();

        // Return success response with PDF data
        echo json_encode([
            'success' => true,
            'message' => $is_submit ? 'Quotation submitted successfully' : 'Quotation preview generated',
            'pdf_data' => base64_encode($pdf_content),
            'appointment_id' => $appointment_id
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error if this was a submission
        if ($is_submit) {
            $conn->rollback();
        }
        throw $e;
    }

} catch (Exception $e) {
    // Clear any output buffer
    ob_clean();
    
    // Log the error
    error_log("Error in process_quotation.php: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while processing the quotation: ' . $e->getMessage()
    ]);
}

// End output buffering and send
ob_end_flush();

// Function to generate email content
function generateNotificationEmail($appointment, $vehicles, $total_price, $discount_amount, $final_price) {
    global $conn;
    
    // Fetch all consultant emails
    $consultant_emails = [];
    $result = $conn->query("SELECT email FROM accounts WHERE accountType = 2");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $consultant_emails[] = $row['email'];
        }
    }
    
    // Generate unique token for each consultant
    $tokens = [];
    foreach ($consultant_emails as $consultant_email) {
        $token = bin2hex(random_bytes(32));
        $tokens[$consultant_email] = $token;
        
        // Store the token in the database
        $stmt = $conn->prepare("INSERT INTO appointment_tokens (token, appointment_id, consultant_email) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $token, $appointment['appointment_id'], $consultant_email);
        $stmt->execute();
    }
    
    $base_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    
    // Determine service type and generate appropriate message
    $service_type = 0;
    $has_smog = false;
    $has_clean_truck = false;
    $test_date = '';
    $test_time = '';

    foreach ($vehicles as $vehicle) {
        if (strpos($vehicle['service_name'], 'Smog Test') !== false) {
            $has_smog = true;
            // Get test date and time from POST data
            $index = array_search($vehicle, $vehicles);
            if (isset($_POST['test_date'][$index]) && isset($_POST['test_time'][$index])) {
                $test_date = $_POST['test_date'][$index];
                $test_time = $_POST['test_time'][$index];
            }
        }
        if (strpos($vehicle['service_name'], 'Clean Truck Check') !== false) {
            $has_clean_truck = true;
        }
    }

    if ($has_smog && $has_clean_truck) {
        $service_type = 3;
    } elseif ($has_smog) {
        $service_type = 2;
    } elseif ($has_clean_truck) {
        $service_type = 1;
    }

    // Generate service-specific message
    $service_message = '';
    $action_buttons = '';

    switch ($service_type) {
        case 1: // Clean Truck Check
            $service_message = "
                <div class='service-notice'>
                    <h3>New Clean Truck Check Service Request</h3>
                    <p>A new Clean Truck Check service has been requested. Please review the details below and take appropriate action.</p>
                </div>";
            $action_buttons = "
                <div class='action-buttons'>
                    <a href='" . $base_url . "/accept_quotation.php?token=" . $token . "' class='btn btn-accept'>Accept Request</a>
                </div>
                <div class='contact-note'>
                    <p><em>If you need to make any changes to this request, please contact the client directly.</em></p>
                </div>";
            break;

        case 2: // Smog Test
            $service_message = "
                <div class='service-notice'>
                    <h3>New Smog Test Request</h3>
                    <p>A new Smog Test has been requested for:</p>
                    <p><strong>Date:</strong> " . date('F d, Y', strtotime($test_date)) . "</p>
                    <p><strong>Time:</strong> " . date('h:i A', strtotime($test_time)) . "</p>
                    <p>Please review the details and confirm the appointment or request rescheduling if needed.</p>
                </div>";
            $action_buttons = "
                <div class='action-buttons'>
                    <a href='" . $base_url . "/accept_quotation.php?token=" . $token . "' class='btn btn-accept'>Confirm Appointment</a>
                    <a href='" . $base_url . "/reschedule_quotation.php?token=" . $token . "' class='btn btn-reschedule'>Request Reschedule</a>
                </div>
                <div class='contact-note'>
                    <p><em>If you need to make any changes to this request, please contact the client directly.</em></p>
                </div>";
            break;

        case 3: // Both Services
            $service_message = "
                <div class='service-notice'>
                    <h3>New Combined Service Request</h3>
                    <p>A new request for both Smog Test and Clean Truck Check has been submitted.</p>
                    <p><strong>Smog Test Details:</strong></p>
                    <p>Date: " . date('F d, Y', strtotime($test_date)) . "</p>
                    <p>Time: " . date('h:i A', strtotime($test_time)) . "</p>
                    <p>Please review all details carefully and take appropriate action.</p>
                </div>";
            $action_buttons = "
                <div class='action-buttons'>
                    <a href='" . $base_url . "/accept_quotation.php?token=" . $token . "' class='btn btn-accept'>Confirm Request</a>
                    <a href='" . $base_url . "/reschedule_quotation.php?token=" . $token . "' class='btn btn-reschedule'>Request Reschedule</a>
                </div>
                <div class='contact-note'>
                    <p><em>If you need to make any changes to this request, please contact the client directly.</em></p>
                </div>";
            break;
    }

    $email_body = "
    <html>
    <head>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                line-height: 1.6;
                color: #333;
                max-width: 800px;
                margin: 0 auto;
                padding: 20px;
            }
            .header { 
                background-color: #17a2b8;
                color: white;
                padding: 20px;
                text-align: center;
                border-radius: 5px 5px 0 0;
            }
            .content {
                background-color: #ffffff;
                padding: 20px;
                border: 1px solid #dee2e6;
                border-top: none;
            }
            .service-notice {
                background-color: #e3f2fd;
                padding: 20px;
                border-radius: 5px;
                margin: 20px 0;
                border-left: 4px solid #17a2b8;
            }
            .details {
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .vehicle-details {
                background-color: #e9ecef;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .footer {
                background-color: #343a40;
                color: white;
                padding: 20px;
                text-align: center;
                border-radius: 0 0 5px 5px;
            }
            .price-details {
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .price-row {
                display: flex;
                justify-content: space-between;
                margin: 5px 0;
            }
            .total-row {
                font-weight: bold;
                border-top: 1px solid #dee2e6;
                padding-top: 10px;
                margin-top: 10px;
            }
            .action-buttons {
                text-align: center;
                margin: 30px 0;
            }
            .contact-note {
                text-align: center;
                margin: 20px 0;
                color: #6c757d;
                font-style: italic;
            }
            .btn {
                display: inline-block;
                padding: 12px 24px;
                margin: 0 10px;
                border-radius: 5px;
                text-decoration: none;
                font-weight: bold;
                color: white;
            }
            .btn-accept {
                background-color: #28a745;
            }
            .btn-reschedule {
                background-color: #ffc107;
                color: #000;
            }
            .attachment-note {
                background-color: #e9ecef;
                padding: 10px;
                border-radius: 5px;
                margin: 20px 0;
                text-align: center;
            }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>New Service Request</h2>
            <p>Sky Smoke Check LLC</p>
        </div>
        
        <div class='content'>
            " . $service_message . "
            
            <div class='details'>
                <h3>Customer Information</h3>
                <p><strong>Company:</strong> " . htmlspecialchars($appointment['companyName']) . "</p>
                <p><strong>Contact:</strong> " . htmlspecialchars($appointment['Name']) . "</p>
                <p><strong>Email:</strong> " . htmlspecialchars($appointment['email']) . "</p>
                <p><strong>Phone:</strong> " . htmlspecialchars($appointment['phone']) . "</p>
            </div>

            <div class='vehicle-details'>
                <h3>Vehicles and Services</h3>
                <table style='width: 100%; border-collapse: collapse;'>
                    <tr style='background-color: #f8f9fa;'>
                        <th style='padding: 10px; text-align: left;'>Vehicle</th>
                        <th style='padding: 10px; text-align: left;'>License Plate</th>
                        <th style='padding: 10px; text-align: left;'>Service</th>
                        <th style='padding: 10px; text-align: right;'>Price</th>
                    </tr>";

    foreach ($vehicles as $vehicle) {
        $email_body .= "
                    <tr>
                        <td style='padding: 10px; border-top: 1px solid #dee2e6;'>" . htmlspecialchars($vehicle['vehMake'] . ' ' . $vehicle['vehYear']) . "</td>
                        <td style='padding: 10px; border-top: 1px solid #dee2e6;'>" . htmlspecialchars($vehicle['plateNo']) . "</td>
                        <td style='padding: 10px; border-top: 1px solid #dee2e6;'>" . htmlspecialchars($vehicle['service_name']) . "</td>
                        <td style='padding: 10px; border-top: 1px solid #dee2e6; text-align: right;'>$" . number_format($vehicle['service_price'], 2) . "</td>
                    </tr>";
    }

    $email_body .= "
                </table>
            </div>

            <div class='price-details'>
                <div class='price-row'>
                    <span>Subtotal:</span>
                    <span>$" . number_format($total_price, 2) . "</span>
                </div>";

    if ($discount_amount > 0) {
        $email_body .= "
                <div class='price-row'>
                    <span>Discount:</span>
                    <span>-$" . number_format($discount_amount, 2) . "</span>
                </div>";
    }

    $email_body .= "
                <div class='price-row total-row'>
                    <span>Total:</span>
                    <span>$" . number_format($final_price, 2) . "</span>
                </div>
            </div>";

    if (!empty($appointment['special_instructions'])) {
        $email_body .= "
            <div class='details'>
                <h3>Special Instructions</h3>
                <p>" . nl2br(htmlspecialchars($appointment['special_instructions'])) . "</p>
            </div>";
    }

    $email_body .= "
            <div class='attachment-note'>
                <p><strong>Note:</strong> A detailed quotation has been attached to this email.</p>
            </div>

            " . $action_buttons . "
        </div>
        
        <div class='footer'>
            <p>Best regards,<br>Sky Smoke Check LLC</p>
            <p>121 E 11th St, Tracy, CA 95376<br>Phone: (209) 123-4567</p>
        </div>
    </body>
    </html>";

    return $email_body;
}
?> 
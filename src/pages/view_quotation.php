<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';
require_once __DIR__ . '/../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if appointment ID is provided
if (!isset($_GET['id'])) {
    header("Location: admin_welcome.php");
    exit();
}

$appointment_id = $_GET['id'];

// Fetch appointment details
$appointment_stmt = $conn->prepare("
    SELECT a.*, c.firstName as consultant_first_name, c.lastName as consultant_last_name, c.email as consultant_email
    FROM appointments a
    LEFT JOIN accounts c ON a.approved_by = c.idaccounts
    WHERE a.id = ?
");

if (!$appointment_stmt) {
    die("Prepare failed: " . $conn->error);
}

$appointment_stmt->bind_param("i", $appointment_id);
$appointment_stmt->execute();
$appointment = $appointment_stmt->get_result()->fetch_assoc();

if (!$appointment) {
    header("Location: admin_welcome.php");
    exit();
}

// Fetch appointment details with vehicle and calendar event information
$stmt = $conn->prepare("
    SELECT 
        a.*,
        v.plateNo,
        v.service_id,
        ce.start_time
    FROM appointments a
    LEFT JOIN vehicles v ON a.id = v.appointment_id
    LEFT JOIN calendar_events ce ON v.id = ce.vehid
    WHERE a.id = ?
    AND (v.service_id = 2 OR v.service_id = 3)
    ORDER BY ce.start_time ASC
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

// Check if there are any smog test vehicles
$has_smog_vehicles = $result->num_rows > 0;

// Fetch vehicles
$stmt = $conn->prepare("
    SELECT v.*, s.name as service_name, s.price as service_price
    FROM vehicles v
    LEFT JOIN services s ON v.service_id = s.id
    WHERE v.appointment_id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate total price
$total_price = 0;
foreach ($vehicles as $vehicle) {
    $total_price += $vehicle['service_price'];
}

// Apply discount if any
$discount_amount = 0;
if ($appointment['discount_type'] !== 'none') {
    if ($appointment['discount_type'] === 'custom') {
        if ($appointment['custom_discount_percentage'] > 0) {
            $discount_amount = ($total_price * $appointment['custom_discount_percentage']) / 100;
        } else {
            $discount_amount = $appointment['custom_discount_amount'];
        }
    } else {
        $discount_amount = ($total_price * $appointment['discount_amount']) / 100;
    }
}

$final_price = $total_price - $discount_amount;

// Fetch consultant details
$consultant = null;
if ($appointment['approved_by']) {
    $stmt = $conn->prepare("
        SELECT a.firstName, a.lastName, a.phone, a.email 
        FROM accounts a 
        WHERE a.idaccounts = ?
    ");
    $stmt->bind_param("i", $appointment['approved_by']);
    $stmt->execute();
    $consultant = $stmt->get_result()->fetch_assoc();
}

// Fetch service details
$service_details = [];
if ($appointment['service_id'] == 1) {
    // Clean Truck Check details
    $stmt = $conn->prepare("
        SELECT v.vin_number, v.plate_number, v.vehicle_make, v.vehicle_year
        FROM clean_truck_checks c
        JOIN vehicles v ON c.vehicle_id = v.id
        WHERE c.appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $service_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else if ($appointment['service_id'] == 2 || $appointment['service_id'] == 3) {
    // Smog Test details
    $stmt = $conn->prepare("
        SELECT ce.start_time, v.vin, v.vehYear, v.vehMake, v.plateNo
        FROM calendar_events ce
        JOIN vehicles v ON ce.vehid = v.id
        WHERE ce.appointment_id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $service_details = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Fetch consultant email for CC
$stmt = $conn->prepare("
    SELECT a.email 
    FROM clean_truck_checks c
    JOIN accounts a ON c.user_id = a.idaccounts
    WHERE c.appointment_id = ?
");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$consultant_email = $stmt->get_result()->fetch_assoc()['email'] ?? null;

// Generate PDF
class PDF extends FPDF {
    function Header() {
        // Company Information (Right-aligned)
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Sky Smoke Check LLC', 0, 1, 'R');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, '121 E 11th St, Tracy, CA 95376', 0, 1, 'R');
        $this->Cell(0, 5, 'Phone: (209) 123-4567', 0, 1, 'R');
        $this->Cell(0, 5, 'Email: info@skysmokecheck.com', 0, 1, 'R');
        
        // Logo (Left-aligned, smaller size)
        $this->Image('../assets/images/logo.png', 10, 10, 30);
        
        // Title (Centered)
        $this->SetY(10);
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 20, 'Invoice', 0, 1, 'C');
        
        // Line break
        $this->Ln(20);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Create PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Customer Information
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Customer Information', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, 'Company: ' . $appointment['companyName'], 0, 1);
$pdf->Cell(0, 5, 'Contact: ' . $appointment['Name'], 0, 1);
$pdf->Cell(0, 5, 'Email: ' . $appointment['email'], 0, 1);
$pdf->Cell(0, 5, 'Phone: ' . $appointment['phone'], 0, 1);
$pdf->Ln(10);

// Appointment Details
// $pdf->SetFont('Arial', 'B', 12);
// $pdf->Cell(0, 10, 'Appointment Details', 0, 1);

// Check if any vehicle has service_id 2 or 3
$has_smog_vehicles = false;
foreach ($vehicles as $vehicle) {
    if ($vehicle['service_id'] == 2 || $vehicle['service_id'] == 3) {
        $has_smog_vehicles = true;
        break;
    }
}

if ($has_smog_vehicles) {
    // Appointment Details
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Appointment Details', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    while ($row = $result->fetch_assoc()) {
        $pdf->Cell(0, 5, 'Vehicle Plate: ' . $row['plateNo'], 0, 1);
        $pdf->Cell(0, 5, 'Date: ' . date('F j, Y', strtotime($row['start_time'])), 0, 1);
        $pdf->Cell(0, 5, 'Time: ' . date('g:i A', strtotime($row['start_time'])), 0, 1);
        $pdf->Ln(5);
    }
} else {
    // $pdf->SetFont('Arial', 'I', 10);
    // $pdf->Cell(0, 5, 'No smog test appointments scheduled', 0, 1);
}

// Vehicles and Services
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Vehicles and Services', 0, 1);

// Table header
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 7, 'Vehicle', 1);
$pdf->Cell(40, 7, 'License Plate', 1);
$pdf->Cell(60, 7, 'Service', 1);
$pdf->Cell(40, 7, 'Price', 1);
$pdf->Ln();

// Table data
$pdf->SetFont('Arial', '', 10);
foreach ($vehicles as $vehicle) {
    $pdf->Cell(40, 7, $vehicle['vehMake'] . ' ' . $vehicle['vehYear'], 1);
    $pdf->Cell(40, 7, $vehicle['plateNo'], 1);
    $pdf->Cell(60, 7, $vehicle['service_name'], 1);
    $pdf->Cell(40, 7, '$' . number_format($vehicle['service_price'], 2), 1);
    $pdf->Ln();
}

// Totals
$pdf->Ln(5);
$pdf->Cell(140, 7, 'Subtotal:', 0, 0, 'R');
$pdf->Cell(40, 7, '$' . number_format($total_price, 2), 0, 1, 'R');

if ($discount_amount > 0) {
    $pdf->Cell(140, 7, 'Discount:', 0, 0, 'R');
    $pdf->Cell(40, 7, '-$' . number_format($discount_amount, 2), 0, 1, 'R');
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(140, 7, 'Total:', 0, 0, 'R');
$pdf->Cell(40, 7, '$' . number_format($final_price, 2), 0, 1, 'R');

// Special Instructions
if (!empty($appointment['special_instructions'])) {
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Special Instructions', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 5, $appointment['special_instructions']);
}

// Terms and Conditions
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Terms and Conditions', 0, 1);
$pdf->SetFont('Arial', '', 10);
$terms = array(
    "1. Payment is due within 30 days of the invoice date.",
    "2. All prices are subject to applicable taxes.",
    "3. Services must be completed within 90 days of the invoice date.",
    "4. Cancellations must be made at least 24 hours in advance.",
    "5. We reserve the right to reschedule appointments due to unforeseen circumstances.",
    "6. All vehicles must be in safe operating condition for testing.",
    "7. Additional charges may apply for special requirements or rush services.",
    "8. This invoice is valid for 30 days from the date of issue."
);

foreach ($terms as $term) {
    $pdf->MultiCell(0, 5, $term);
}

// Save PDF to a temporary file
$pdf_path = '../temp/Quotation_' . $appointment_id . '.pdf';
$pdf->Output('F', $pdf_path);

// Get the base URL
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$script_name = dirname($_SERVER['SCRIPT_NAME']);
$base_url = $protocol . $host . $script_name;

// Generate email content
$email_subject = "Invoice #" . $appointment_id . " - Sky Smoke Check LLC";

// Create HTML email body
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
        .button {
            display: inline-block;
            padding: 10px 20px;
            background-color: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
        .cc-notice {
            background-color: #fff3cd;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin: 20px 0;
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
    </style>
</head>
<body>
    <div class='header'>
        <h2>Invoice #" . $appointment_id . "</h2>
        <p>Sky Smoke Check LLC</p>
    </div>
    
    <div class='content'>
        <p>Dear " . htmlspecialchars($appointment['Name']) . ",</p>
        <p>Thank you for choosing Sky Smoke Check LLC. Please find below the details of your appointment:</p>
        
        <div class='details'>
            <h3>Customer Information</h3>
            <p><strong>Company:</strong> " . htmlspecialchars($appointment['companyName']) . "</p>
            <p><strong>Contact:</strong> " . htmlspecialchars($appointment['Name']) . "</p>
            <p><strong>Email:</strong> " . htmlspecialchars($appointment['email']) . "</p>
            <p><strong>Phone:</strong> " . htmlspecialchars($appointment['phone']) . "</p>
        </div>";

if ($consultant) {
    $email_body .= "
        <div class='details'>
            <h3>Consultant Information</h3>
            <p><strong>Name:</strong> " . htmlspecialchars($consultant['firstName'] . " " . $consultant['lastName']) . "</p>
            <p><strong>Phone:</strong> " . htmlspecialchars($consultant['phone']) . "</p>
            <p><strong>Email:</strong> " . htmlspecialchars($consultant['email']) . "</p>
        </div>";
}

$email_body .= "
        <div class='details'>
            <h3>Appointment Details</h3>";

if ($has_smog_vehicles) {
    $email_body .= "<ul style='list-style: none; padding: 0;'>";
    while ($row = $result->fetch_assoc()) {
        $email_body .= "
            <li style='margin-bottom: 15px; padding: 10px; background-color: #fff; border-radius: 5px;'>
                <strong>Vehicle Plate:</strong> " . htmlspecialchars($row['plateNo']) . "<br>
                <strong>Date:</strong> " . date('F j, Y', strtotime($row['start_time'])) . "<br>
                <strong>Time:</strong> " . date('g:i A', strtotime($row['start_time'])) . "
            </li>";
    }
    $email_body .= "</ul>";
} else {
    $email_body .= "<p><em>No smog test appointments scheduled</em></p>";
}

$email_body .= "
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
        <div class='cc-notice'>
            <p><strong>Note:</strong> Your consultant " . ($consultant ? htmlspecialchars($consultant['firstName'] . " " . $consultant['lastName']) : "") . " has been CC'd on this email. Feel free to contact them directly if you have any questions or concerns.</p>
        </div>

        <p>To view and confirm your invoice, please click the button below:</p>
        <div style='text-align: center;'>
            <a href='" . $base_url . "/confirm_invoice.php?id=" . $appointment_id . "' class='button'>View and Confirm Invoice</a>
        </div>
    </div>
    
    <div class='footer'>
        <p>Best regards,<br>Sky Smoke Check LLC</p>
        <p>121 E 11th St, Tracy, CA 95376<br>Phone: (209) 123-4567</p>
    </div>
</body>
</html>";

// Update the button in the HTML section
$button_class = "btn-primary";
$button_text = "<i class='fas fa-paper-plane me-2'></i> Send to Client";
$button_onclick = "sendInvoiceEmail();";

// Add JavaScript function to handle email sending
$script = "
<script>
function sendInvoiceEmail() {
    Swal.fire({
        title: 'Sending Invoice',
        text: 'Please wait while we send the invoice...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch('send_invoice.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            appointment_id: '" . $appointment_id . "',
            email_body: `" . addslashes($email_body) . "`,
            email_subject: '" . addslashes($email_subject) . "',
            consultant_email: '" . addslashes($consultant_email) . "'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Email Sent',
                text: 'The invoice has been sent to the client and CC\'d to the consultant.',
                confirmButtonColor: '#28a745'
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: data.message || 'Failed to send the email. Please try again.',
                confirmButtonColor: '#dc3545'
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'An error occurred while sending the email. Please try again.',
            confirmButtonColor: '#dc3545'
        });
    });
}
</script>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Quotation - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        main {
            flex: 1;
            margin-bottom: 20px;
            margin-left: 250px;
            transition: margin-left 0.3s ease;
        }
        .wrapper {
            display: flex;
            flex: 1;
        }
        .sidebar {
            width: 250px;
            background-color: #343a40;
            color: white;
            padding: 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
        }
        .sidebar.collapsed {
            width: 0;
            padding: 20px 0;
        }
        .sidebar-toggle {
            position: fixed;
            left: 250px;
            top: 20px;
            background: #343a40;
            color: white;
            border: none;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s;
            z-index: 1001;
        }
        .sidebar-toggle.collapsed {
            left: 0;
        }
        .sidebar-toggle i {
            transition: transform 0.3s;
        }
        .sidebar-toggle.collapsed i {
            transform: rotate(180deg);
        }
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: all 0.3s;
        }
        .main-content.expanded {
            margin-left: 0;
        }
        .sidebar-header {
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 10px;
            white-space: nowrap;
            overflow: hidden;
        }
        .sidebar-menu a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: 5px;
            transition: all 0.3s;
            margin-bottom: 5px;
        }
        .sidebar-menu a:hover {
            background-color: rgba(255,255,255,0.1);
            transform: translateX(5px);
        }
        .sidebar-menu a.active {
            background-color: #007bff;
            color: white;
            font-weight: 600;
        }
        .sidebar-menu a.active i {
            color: white;
        }
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
            color: rgba(255,255,255,0.7);
        }
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .site-footer {
            position: fixed;
            bottom: 0;
            left: 250px;
            right: 0;
            background-color: #f8f9fa;
            padding: 10px 0;
            border-top: 1px solid #dee2e6;
            z-index: 1000;
            transition: all 0.3s;
        }
        .site-footer.expanded {
            left: 0;
        }
        .footer-bottom {
            text-align: center;
        }
        .copyright p {
            margin: 0;
            color: #6c757d;
        }
        .quotation-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .quotation-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        .quotation-title {
            color: #2c3e50;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .quotation-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
        }
        .pdf-container {
            width: 100%;
            height: 800px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            overflow: hidden;
            margin: 20px 0;
        }
        .pdf-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        .button-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            gap: 10px;
        }
        .nav-buttons {
            display: flex;
            gap: 10px;
        }
        .nav-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .nav-btn:hover {
            transform: translateY(-2px);
        }
        .nav-btn i {
            font-size: 0.9em;
        }
        .nav-btn.dashboard {
            background-color: #17a2b8;
        }
        .nav-btn.dashboard:hover {
            background-color: #138496;
        }
        .nav-btn.calendar {
            background-color: #28a745;
        }
        .nav-btn.calendar:hover {
            background-color: #218838;
        }
        .nav-btn.clean-truck {
            background-color: #6c757d;
        }
        .nav-btn.clean-truck:hover {
            background-color: #5a6268;
        }
        .btn-primary {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        .btn-primary:hover {
            background-color: #138496;
            border-color: #117a8b;
        }
        .top-buttons {
            margin-bottom: 20px;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.collapsed {
                transform: translateX(0);
                width: 250px;
            }
            .main-content {
                margin-left: 0;
            }
            .sidebar-toggle {
                left: 0;
            }
            .site-footer {
                left: 0;
            }
        }
    </style>
</head>
<body>
    <button class="sidebar-toggle" id="sidebarToggle">
        <i class="fas fa-chevron-left"></i>
    </button>
    <div class="wrapper">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Admin Dashboard</h3>
                <p class="text-muted">Welcome, <?php echo htmlspecialchars($_SESSION['first_name']); ?></p>
            </div>
            <ul class="sidebar-menu">
                <li>
                    <a href="admin_welcome.php">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="calendar.php">
                        <i class="fas fa-calendar"></i> Calendar
                    </a>
                </li>
                <li>
                    <a href="all_appointments.php">
                        <i class="fas fa-list"></i> All Appointments
                    </a>
                </li>
                <li>
                    <a href="all_clients.php">
                        <i class="fas fa-users"></i> All Clients
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <ul class="sidebar-menu">
                    <li>
                        <a href="schedule_appointment.php" style="background-color: #28a745; color: white;">
                            <i class="fas fa-calendar-plus"></i> Schedule Appointment
                        </a>
                    </li>
                    <li>
                        <a href="create_quotation.php" style="background-color: #17a2b8; color: white;">
                            <i class="fas fa-file-invoice-dollar"></i> Create Quotation
                        </a>
                    </li>
                    <li>
                        <a href="view_appointments.php">
                            <i class="fas fa-calendar-check"></i> View Appointments
                        </a>
                    </li>
                    <li>
                        <a href="logout.php" class="logout-link">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="container-fluid">
                <div class="quotation-container">
                    <div class="top-buttons">
                        <button onclick="history.back()" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back
                        </button>
                    </div>
                    
                    <div class="pdf-container">
                        <iframe src="<?php echo $pdf_path; ?>" type="application/pdf"></iframe>
                    </div>

                    <div class="button-container">
                        <div class="nav-buttons">
                            <button class="nav-btn dashboard" onclick="window.location.href='admin_welcome.php'">
                                <i class="fas fa-home"></i> Dashboard
                            </button>
                            <button class="nav-btn calendar" onclick="window.location.href='calendar.php'">
                                <i class="fas fa-calendar"></i> Calendar
                            </button>
                            <button class="nav-btn clean-truck" onclick="window.location.href='clean_truck_checks.php'">
                                <i class="fas fa-truck"></i> Clean Truck
                            </button>
                        </div>
                        <div class="nav-buttons">
                            <button class="btn btn-secondary" onclick="window.history.back()">
                                <i class="fas fa-arrow-left me-2"></i> Back
                            </button>
                            <button class="btn <?php echo $button_class; ?>" onclick="<?php echo $button_onclick; ?>">
                                <?php echo $button_text; ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.32/dist/sweetalert2.all.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/script.js"></script>
    <script>
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const mainContent = document.getElementById('mainContent');

            // Check for saved state in localStorage
            const isCollapsed = localStorage.getItem('sidebarState') === 'collapsed';
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                sidebarToggle.classList.add('collapsed');
            }

            sidebarToggle.addEventListener('click', function() {
                const isCurrentlyCollapsed = sidebar.classList.contains('collapsed');
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Save state to localStorage
                localStorage.setItem('sidebarState', isCurrentlyCollapsed ? 'expanded' : 'collapsed');
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth <= 768) {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('expanded');
                    sidebarToggle.classList.add('collapsed');
                } else {
                    const isCollapsed = localStorage.getItem('sidebarState') === 'collapsed';
                    if (isCollapsed) {
                        sidebar.classList.add('collapsed');
                        mainContent.classList.add('expanded');
                        sidebarToggle.classList.add('collapsed');
                    } else {
                        sidebar.classList.remove('collapsed');
                        mainContent.classList.remove('expanded');
                        sidebarToggle.classList.remove('collapsed');
                    }
                }
            });
        });
    </script>
    <?php echo $script; ?>
</body>
</html> 
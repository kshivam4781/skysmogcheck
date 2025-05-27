<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';
require_once '../config/db_connection.php';
require_once '../includes/email_helper.php';

class AppointmentQuotationPDF extends FPDF {
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

function generateAppointmentQuotation($appointment_id, $conn) {
    // Fetch appointment details
    $stmt = $conn->prepare("
        SELECT a.*, ce.start_time, v.*
        FROM appointments a
        JOIN calendar_events ce ON a.id = ce.appointment_id
        LEFT JOIN vehicles v ON ce.vehid = v.id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $appointment = $stmt->get_result()->fetch_assoc();

    if (!$appointment) {
        throw new Exception('Appointment not found');
    }

    // Create PDF
    $pdf = new AppointmentQuotationPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Quotation Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'UPDATED APPOINTMENT QUOTATION', 0, 1, 'C');
    $pdf->Ln(5);

    // Appointment Details
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 5, 'Appointment #:', 0);
    $pdf->Cell(0, 5, $appointment_id, 0, 1);
    $pdf->Cell(30, 5, 'Date:', 0);
    $pdf->Cell(0, 5, date('F d, Y', strtotime($appointment['start_time'])), 0, 1);
    $pdf->Ln(5);

    // Customer Information
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 5, 'Customer Information', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 5, 'Company:', 0);
    $pdf->Cell(0, 5, $appointment['companyName'], 0, 1);
    $pdf->Cell(30, 5, 'Contact:', 0);
    $pdf->Cell(0, 5, $appointment['Name'], 0, 1);
    $pdf->Cell(30, 5, 'Email:', 0);
    $pdf->Cell(0, 5, $appointment['email'], 0, 1);
    $pdf->Cell(30, 5, 'Phone:', 0);
    $pdf->Cell(0, 5, $appointment['phone'], 0, 1);
    $pdf->Ln(5);

    // Vehicle Information
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 5, 'Vehicle Information', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 5, 'Year:', 0);
    $pdf->Cell(0, 5, $appointment['vehYear'], 0, 1);
    $pdf->Cell(30, 5, 'Make:', 0);
    $pdf->Cell(0, 5, $appointment['vehMake'], 0, 1);
    $pdf->Cell(30, 5, 'VIN:', 0);
    $pdf->Cell(0, 5, $appointment['vin'], 0, 1);
    $pdf->Cell(30, 5, 'Plate:', 0);
    $pdf->Cell(0, 5, $appointment['plateNo'], 0, 1);
    $pdf->Ln(5);

    // Service Information
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 5, 'Service Information', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(30, 5, 'Location:', 0);
    $pdf->Cell(0, 5, ucfirst(str_replace('_', ' ', $appointment['test_location'])), 0, 1);
    if ($appointment['test_location'] === 'client_location') {
        $pdf->Cell(30, 5, 'Address:', 0);
        $pdf->MultiCell(0, 5, $appointment['test_address'], 0, 1);
    }
    $pdf->Ln(5);

    // Price Details
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 5, 'Price Details', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    
    // Base price
    $base_price = $appointment['total_price'];
    if ($appointment['test_location'] === 'client_location') {
        $base_price -= floatval($appointment['on_site_charges'] ?? 0);
    }
    
    $pdf->Cell(30, 5, 'Base Price:', 0);
    $pdf->Cell(0, 5, '$' . number_format($base_price, 2), 0, 1);
    
    // On-site charges if applicable
    if ($appointment['test_location'] === 'client_location') {
        $pdf->Cell(30, 5, 'On-site Charges:', 0);
        $pdf->Cell(0, 5, '$' . number_format($appointment['on_site_charges'] ?? 0, 2), 0, 1);
    }
    
    // Total
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(30, 5, 'Total:', 0);
    $pdf->Cell(0, 5, '$' . number_format($appointment['total_price'], 2), 0, 1);

    // Save PDF to temporary file
    $temp_dir = __DIR__ . '/../../temp';
    if (!file_exists($temp_dir)) {
        mkdir($temp_dir, 0777, true);
    }
    
    $filename = 'quotation_' . $appointment_id . '_' . time() . '.pdf';
    $filepath = $temp_dir . '/' . $filename;
    $pdf->Output('F', $filepath);

    return [
        'filepath' => $filepath,
        'filename' => $filename
    ];
}

function sendQuotationEmail($appointment, $pdf_file) {
    $to = $appointment['email'];
    $cc = $_SESSION['email']; // CC the user who made the changes
    
    $subject = "Updated Appointment Quotation - Sky Smoke Check LLC";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .header { background-color: #17a2b8; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; }
            .footer { background-color: #343a40; color: white; padding: 20px; text-align: center; }
        </style>
    </head>
    <body>
        <div class='header'>
            <h2>Updated Appointment Quotation</h2>
        </div>
        <div class='content'>
            <p>Dear " . htmlspecialchars($appointment['Name']) . ",</p>
            <p>Your appointment has been updated. Please find the updated quotation attached.</p>
            <p>Appointment Details:</p>
            <ul>
                <li>Date: " . date('F d, Y', strtotime($appointment['start_time'])) . "</li>
                <li>Location: " . ucfirst(str_replace('_', ' ', $appointment['test_location'])) . "</li>
                " . ($appointment['test_location'] === 'client_location' ? "<li>Address: " . htmlspecialchars($appointment['test_address']) . "</li>" : "") . "
            </ul>
            <p>If you have any questions, please don't hesitate to contact us.</p>
        </div>
        <div class='footer'>
            <p>Sky Smoke Check LLC<br>121 E 11th St, Tracy, CA 95376</p>
        </div>
    </body>
    </html>";

    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Sky Smoke Check LLC <info@skysmoke.com>',
        'Cc: ' . $cc
    );

    // Attach PDF
    $attachment = chunk_split(base64_encode(file_get_contents($pdf_file['filepath'])));
    
    $boundary = md5(time());
    $headers[] = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
    
    $body = "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $message . "\r\n\r\n";
    
    $body .= "--" . $boundary . "\r\n";
    $body .= "Content-Type: application/pdf; name=\"" . $pdf_file['filename'] . "\"\r\n";
    $body .= "Content-Disposition: attachment; filename=\"" . $pdf_file['filename'] . "\"\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= $attachment . "\r\n\r\n";
    $body .= "--" . $boundary . "--";

    return mail($to, $subject, $body, implode("\r\n", $headers));
}
?> 
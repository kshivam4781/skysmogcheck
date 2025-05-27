<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';
require_once '../config/db_connection.php';

// Check if quotation ID is provided
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$quotation_id = $_GET['id'];

// Fetch quotation details
$stmt = $conn->prepare("
    SELECT q.*, c.company_name, c.contact_name, c.email, c.phone
    FROM quotations q
    JOIN customers c ON q.customer_id = c.id
    WHERE q.id = ?
");

if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $quotation_id);
$stmt->execute();
$quotation = $stmt->get_result()->fetch_assoc();

if (!$quotation) {
    header("Location: index.php");
    exit();
}

// Fetch vehicle details
$stmt = $conn->prepare("
    SELECT v.*, s.name as service_name, s.price as service_price
    FROM vehicles v
    JOIN services s ON v.service_id = s.id
    WHERE v.quotation_id = ?
");

$stmt->bind_param("i", $quotation_id);
$stmt->execute();
$vehicles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate total
$total = 0;
foreach ($vehicles as $vehicle) {
    $total += $vehicle['service_price'];
}

// Create PDF
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
$pdf->Cell(0, 5, $quotation_id, 0, 1);
$pdf->Cell(30, 5, 'Date:', 0);
$pdf->Cell(0, 5, date('F d, Y', strtotime($quotation['created_at'])), 0, 1);
$pdf->Ln(5);

// Customer Information
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 5, 'Customer Information', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(30, 5, 'Company:', 0);
$pdf->Cell(0, 5, $quotation['company_name'], 0, 1);
$pdf->Cell(30, 5, 'Contact:', 0);
$pdf->Cell(0, 5, $quotation['contact_name'], 0, 1);
$pdf->Cell(30, 5, 'Email:', 0);
$pdf->Cell(0, 5, $quotation['email'], 0, 1);
$pdf->Cell(30, 5, 'Phone:', 0);
$pdf->Cell(0, 5, $quotation['phone'], 0, 1);
$pdf->Ln(5);

// Vehicles Table
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 5, 'Vehicle Details', 0, 1);
$pdf->Ln(2);

// Table Header
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(20, 7, 'Year', 1);
$pdf->Cell(40, 7, 'Make', 1);
$pdf->Cell(40, 7, 'VIN', 1);
$pdf->Cell(30, 7, 'License', 1);
$pdf->Cell(30, 7, 'Service', 1);
$pdf->Cell(30, 7, 'Price', 1);
$pdf->Ln();

// Table Data
$pdf->SetFont('Arial', '', 10);
foreach ($vehicles as $vehicle) {
    $pdf->Cell(20, 7, $vehicle['year'], 1);
    $pdf->Cell(40, 7, $vehicle['make'], 1);
    $pdf->Cell(40, 7, $vehicle['vin'], 1);
    $pdf->Cell(30, 7, $vehicle['license_plate'], 1);
    $pdf->Cell(30, 7, $vehicle['service_name'], 1);
    $pdf->Cell(30, 7, '$' . number_format($vehicle['service_price'], 2), 1);
    $pdf->Ln();
}

// Total
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(160, 7, 'Total', 1, 0, 'R');
$pdf->Cell(30, 7, '$' . number_format($total, 2), 1, 1);

// Special Instructions
if (!empty($quotation['special_instructions'])) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 5, 'Special Instructions', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(0, 5, $quotation['special_instructions']);
}

// Output PDF
$pdf->Output('I', 'quotation_' . $quotation_id . '.pdf');
?> 
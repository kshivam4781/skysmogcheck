<?php
// Configure error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';
require_once __DIR__ . '/../config/db_connection.php';

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
        $this->Cell(0, 20, 'Smoke Test Certificate', 0, 1, 'C');
        
        // Line break
        $this->Ln(20);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

function generateCertificate($data) {
    try {
        error_log("Starting PDF generation with data: " . print_r($data, true));
        
        // Validate required data
        if (empty($data['vehicle_id'])) {
            throw new Exception('Vehicle ID is required');
        }

        $pdf = new PDF();
        $pdf->AliasNbPages();
        $pdf->AddPage();
        
        error_log("PDF page added");
        
        // Certificate Number
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Certificate #: ' . str_pad($data['vehicle_id'], 8, '0', STR_PAD_LEFT), 0, 1, 'R');
        $pdf->Ln(10);

        // Test Result
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'TEST RESULT: ' . strtoupper($data['status']), 0, 1, 'C');
        $pdf->Ln(10);

        // Vehicle Information
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Vehicle Information', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(60, 8, 'Year:', 0, 0);
        $pdf->Cell(0, 8, $data['vehYear'] ?? 'N/A', 0, 1);
        $pdf->Cell(60, 8, 'Make:', 0, 0);
        $pdf->Cell(0, 8, $data['vehMake'] ?? 'N/A', 0, 1);
        $pdf->Cell(60, 8, 'VIN:', 0, 0);
        $pdf->Cell(0, 8, $data['vin'] ?? 'N/A', 0, 1);
        $pdf->Cell(60, 8, 'Plate Number:', 0, 0);
        $pdf->Cell(0, 8, $data['plateNo'] ?? 'N/A', 0, 1);
        $pdf->Ln(10);

        // Company Information
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Company Information', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(60, 8, 'Company Name:', 0, 0);
        $pdf->Cell(0, 8, $data['companyName'] ?? 'N/A', 0, 1);
        $pdf->Cell(60, 8, 'Contact Person:', 0, 0);
        $pdf->Cell(0, 8, $data['Name'] ?? 'N/A', 0, 1);
        $pdf->Ln(10);

        // Test Details
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Test Details', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(60, 8, 'Test Date:', 0, 0);
        $pdf->Cell(0, 8, date('F j, Y', strtotime($data['test_date'])), 0, 1);
        $pdf->Cell(60, 8, 'Location:', 0, 0);
        $pdf->Cell(0, 8, $data['description'] ?? 'N/A', 0, 1);
        if (!empty($data['notes'])) {
            $pdf->Cell(60, 8, 'Notes:', 0, 0);
            $pdf->MultiCell(0, 8, $data['notes'], 0, 'L');
        }
        $pdf->Ln(10);

        // Next Due Date
        if (!empty($data['next_due_date'])) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Next Test Due:', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(0, 8, date('F j, Y', strtotime($data['next_due_date'])), 0, 1);
        }

        // Signature
        $pdf->Ln(20);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 10, 'Authorized Signature', 0, 1, 'C');
        $pdf->Ln(10);
        $pdf->Cell(0, 10, '________________________', 0, 1, 'C');
        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(0, 5, 'Sky Smoke Check LLC', 0, 1, 'C');
        
        error_log("PDF content generated");
        
        // Generate unique filename
        $filename = 'smoke_test_certificate_' . $data['vehicle_id'] . '_' . time() . '.pdf';
        $filepath = __DIR__ . '/../temp/' . $filename;
        
        error_log("Attempting to save PDF to: " . $filepath);
        
        // Create directory if it doesn't exist
        $uploadDir = dirname($filepath);
        if (!file_exists($uploadDir)) {
            error_log("Creating directory: " . $uploadDir);
            if (!mkdir($uploadDir, 0777, true)) {
                throw new Exception('Failed to create upload directory: ' . $uploadDir);
            }
        }
        
        // Save PDF
        $pdf->Output('F', $filepath);
        
        if (!file_exists($filepath)) {
            throw new Exception('Failed to generate PDF file at: ' . $filepath);
        }
        
        error_log("PDF saved successfully at: " . $filepath);
        
        return [
            'filename' => $filename,
            'filepath' => $filepath
        ];
    } catch (Exception $e) {
        error_log("Error in generateCertificate: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }
}
?> 
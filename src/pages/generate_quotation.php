<?php
session_start();
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../vendor/setasign/fpdf/fpdf.php';
require_once __DIR__ . '/../config/db_connection.php';

class PDF extends FPDF {
    // Page header
    function Header() {
        // Logo
        $this->Image('../assets/images/logo.png', 10, 6, 30);
        // Company info
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Sky Smoke Check LLC', 0, 1, 'R');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, '121 E 11th St, Tracy, CA 95376', 0, 1, 'R');
        $this->Cell(0, 5, 'Phone: (555) 123-4567 | Email: info@skysmokecheck.com', 0, 1, 'R');
        // Line break
        $this->Ln(10);
    }

    // Page footer
    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

// Get appointment ID from URL
$appointment_id = $_GET['id'] ?? null;

if ($appointment_id) {
    try {
        // Fetch appointment details
        $stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ?");
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointment = $result->fetch_assoc();

        if ($appointment) {
            // Create PDF
            $pdf = new PDF();
            $pdf->AliasNbPages();
            $pdf->AddPage();
            
            // Set font
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 10, 'QUOTATION', 0, 1, 'C');
            $pdf->Ln(5);

            // Customer Information
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Customer Information', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            
            $pdf->Cell(40, 7, 'Company Name:', 0);
            $pdf->Cell(0, 7, $appointment['companyName'], 0, 1);
            
            if (!empty($appointment['Name'])) {
                $pdf->Cell(40, 7, 'Contact Person:', 0);
                $pdf->Cell(0, 7, $appointment['Name'], 0, 1);
            }
            
            $pdf->Cell(40, 7, 'Email:', 0);
            $pdf->Cell(0, 7, $appointment['email'], 0, 1);
            
            $pdf->Cell(40, 7, 'Phone:', 0);
            $pdf->Cell(0, 7, $appointment['phone'], 0, 1);
            
            if ($appointment['test_location'] === 'your_location') {
                $pdf->Cell(40, 7, 'Test Address:', 0);
                $pdf->MultiCell(0, 7, $appointment['test_address'], 0, 1);
            }
            
            $pdf->Ln(5);
            
            // Quotation Details
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Quotation Details', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            
            $pdf->Cell(40, 7, 'Date:', 0);
            $pdf->Cell(0, 7, date('F d, Y', strtotime($appointment['bookingDate'])), 0, 1);
            
            $pdf->Cell(40, 7, 'Time:', 0);
            $pdf->Cell(0, 7, $appointment['bookingTime'], 0, 1);
            
            $pdf->Cell(40, 7, 'Number of Vehicles:', 0);
            $pdf->Cell(0, 7, $appointment['number_of_vehicles'], 0, 1);
            
            $pdf->Cell(40, 7, 'Quotation #:', 0);
            $pdf->Cell(0, 7, 'Q' . str_pad($appointment_id, 6, '0', STR_PAD_LEFT), 0, 1);
            
            $pdf->Ln(5);
            
            // Vehicle Information Table
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Vehicle Information', 0, 1);
            
            // Table Header
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(15, 7, 'No.', 1);
            $pdf->Cell(40, 7, 'Item', 1);
            $pdf->Cell(80, 7, 'Description', 1);
            $pdf->Cell(30, 7, 'Rate', 1);
            $pdf->Cell(30, 7, 'Amount', 1);
            $pdf->Ln();
            
            // Table Content
            $pdf->SetFont('Arial', '', 10);
            $rate = 75.00;
            $total = 0;
            $vehicle_number = 1;
            
            // Get all vehicles for this appointment
            $vehicle_stmt = $conn->prepare("SELECT * FROM vehicles WHERE appointment_id = ?");
            $vehicle_stmt->bind_param("i", $appointment_id);
            $vehicle_stmt->execute();
            $vehicles = $vehicle_stmt->get_result();
            
            while ($vehicle = $vehicles->fetch_assoc()) {
                $total += $rate;
                $description = $vehicle['vehYear'] . ' ' . $vehicle['vehMake'] . ' - VIN: ' . $vehicle['vin'] . ' - Plate: ' . $vehicle['plateNo'];
                
                // Calculate the required height for this row
                $pdf->SetFont('Arial', '', 10);
                $item_text = 'Clean Truck Registration';
                
                // Calculate number of lines needed for each cell
                $desc_lines = ceil($pdf->GetStringWidth($description) / 80);
                $item_lines = ceil($pdf->GetStringWidth($item_text) / 40);
                
                // Get the maximum number of lines needed
                $max_lines = max($desc_lines, $item_lines, 1);
                $cell_height = 7 * $max_lines;
                
                // Get starting position
                $start_x = $pdf->GetX();
                $start_y = $pdf->GetY();
                
                // Draw No. cell
                $pdf->Cell(15, $cell_height, $vehicle_number, 1);
                
                // Draw Item cell
                $pdf->Cell(40, $cell_height, $item_text, 1);
                
                // Draw Description cell
                $pdf->MultiCell(80, 7, $description, 1, 'L');
                
                // Move back to the right of Description cell
                $pdf->SetXY($start_x + 15 + 40 + 80, $start_y);
                
                // Draw Rate cell
                $pdf->Cell(30, $cell_height, '$' . number_format($rate, 2), 1);
                
                // Draw Amount cell
                $pdf->Cell(30, $cell_height, '$' . number_format($rate, 2), 1);
                
                // Move to next line
                $pdf->Ln();
                $vehicle_number++;
            }
            
            // Table Footer
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(135, 7, 'Total', 1);
            $pdf->Cell(30, 7, '', 1);
            $pdf->Cell(30, 7, '$' . number_format($total, 2), 1);
            
            $pdf->Ln(10);
            
            // Terms and Conditions
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 10, 'Terms and Conditions', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            
            $terms = "1. This quotation is valid for 30 days from the date of issue.\n";
            $terms .= "2. Payment is due upon completion of the smoke test.\n";
            $terms .= "3. We accept cash, check, and all major credit cards.\n";
            $terms .= "4. Cancellation must be made 24 hours prior to the appointment.\n";
            $terms .= "5. All tests are performed in accordance with state regulations.\n";
            $terms .= "6. Results are typically available within 24 hours.\n";
            $terms .= "7. Additional charges may apply for mobile testing services.\n";
            
            $pdf->MultiCell(0, 7, $terms);
            
            // Save PDF to a temporary file
            $pdf_filename = 'quotation_' . $appointment_id . '.pdf';
            $pdf->Output('F', __DIR__ . '/../../temp/' . $pdf_filename);
            
            // Display HTML page with PDF viewer and checkboxes
            ?>
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="description" content="Sky Smoke Check LLC - Professional truck and commercial vehicle smoke testing services. Certified emissions testing for fleets and individual vehicles. Schedule your appointment today.">
                <meta name="keywords" content="truck smoke testing, commercial vehicle testing, emissions testing, fleet testing, DOT compliance, environmental testing">
                <meta name="author" content="Sky Smoke Check LLC">
                <meta name="robots" content="index, follow">
                <title>Review Quotation - Sky Smoke Check LLC</title>
                <!-- Bootstrap CSS -->
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <!-- Font Awesome -->
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
                <!-- Custom CSS -->
                <link rel="stylesheet" href="../styles/styles.css">
                <link rel="stylesheet" href="../styles/main.css">
                <link rel="stylesheet" href="../styles/footer.css">
                <style>
                    .pdf-container {
                        width: 100%;
                        height: 600px;
                        margin-bottom: 20px;
                    }
                    .terms-container {
                        max-width: 800px;
                        margin: 0 auto;
                        padding: 20px;
                    }
                    .checkbox-group {
                        margin: 20px 0;
                    }
                    .payment-button {
                        margin-top: 20px;
                    }
                </style>
            </head>
            <body>
                <header>
                    <nav>
                        <div class="logo">
                            <a href="index.php" style="text-decoration: none;">
                                <h1>Sky Smoke Check LLC</h1>
                            </a>
                        </div>
                        <ul class="nav-links">
                            <li class="nav-item">
                                <a class="nav-link" href="index.php">Home</a>
                            </li>
                            <li><a href="services.php">Services</a></li>
                            <li><a href="about.php">About</a></li>
                            <li><a href="news.php">News</a></li>
                            <li><a href="contact.php">Contact</a></li>
                            <?php if(isset($_SESSION['user_id'])): ?>
                                <li><a href="welcome.php" class="login-button">My Account</a></li>
                                <li><a href="logout.php" class="login-button">Logout</a></li>
                            <?php else: ?>
                                <li><a href="login.php" class="login-button">Login</a></li>
                            <?php endif; ?>
                            <li><a href="schedule.php" class="cta-button">Schedule Test</a></li>
                        </ul>
                        <div class="burger">
                            <div class="line1"></div>
                            <div class="line2"></div>
                            <div class="line3"></div>
                        </div>
                    </nav>
                </header>

                <main class="container mt-4">
                    <h2 class="text-center mb-4">Review Your Quotation</h2>
                    
                    <div class="pdf-container">
                        <iframe src="../../temp/<?php echo $pdf_filename; ?>" width="100%" height="100%" frameborder="0"></iframe>
                    </div>

                    <div class="terms-container">
                        <form id="paymentForm" action="payment_confirmation.php" method="POST">
                            <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                            
                            <div class="checkbox-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="termsCheck" name="termsCheck" required>
                                    <label class="form-check-label" for="termsCheck">
                                        I agree to all the terms and conditions stated in the quotation.
                                    </label>
                                </div>
                            </div>

                            <div class="checkbox-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="paymentCheck" name="paymentCheck" required>
                                    <label class="form-check-label" for="paymentCheck">
                                        I agree to the payment terms and authorize Sky Smoke Check LLC to store my payment information securely in accordance with data protection regulations.
                                    </label>
                                </div>
                            </div>
                            <div class="checkbox-group">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="lightCheck" name="lightCheck" required>
                                    <label class="form-check-label" for="lightCheck">
                                        I agree that there are no engine check lights on my truck.
                                    </label>
                                </div>
                            </div>

                            <div class="text-center payment-button">
                                <button type="submit" class="btn btn-primary btn-lg" id="proceedPayment" disabled>
                                    Proceed to Payment
                                </button>
                            </div>
                        </form>
                    </div>
                </main>

                <footer class="site-footer">
                    <div class="footer-content">
                        <div class="footer-section">
                            <h3>Sky Smoke Check LLC</h3>
                            <p>Professional truck and vehicle smoke testing services.</p>
                        </div>
                        
                        <div class="footer-section">
                            <h3>Quick Links</h3>
                            <ul>
                                <li><a href="services.php">Services</a></li>
                                <li><a href="about.php">About Us</a></li>
                                <li><a href="contact.php">Contact</a></li>
                                <li><a href="schedule.php">Book Appointment</a></li>
                            </ul>
                        </div>
                        
                        <div class="footer-section">
                            <h3>Legal</h3>
                            <ul>
                                <li><a href="privacy.php">Privacy & Cookies Policy</a></li>
                                <li><a href="terms.php">Web Terms of Use</a></li>
                                <li><a href="#">Fraud Warning</a></li>
                            </ul>
                        </div>
                        
                        <div class="footer-section">
                            <h3>Contact Us</h3>
                            <p>
                                <i class="fas fa-phone"></i> (555) 123-4567<br>
                                <i class="fas fa-envelope"></i> info@skysmoke.com<br>
                                <i class="fas fa-location-dot"></i> 121 E 11th St, Tracy, CA 95376
                            </p>
                        </div>
                        
                        <div class="footer-section">
                            <h3>Follow Us</h3>
                            <div class="social-links">
                                <a href="#" class="social-link"><i class="fab fa-facebook"></i></a>
                                <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                                <a href="#" class="social-link"><i class="fab fa-linkedin"></i></a>
                                <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="footer-bottom">
                        <div class="footer-bottom-content">
                            <div class="copyright">
                                <p>&copy; 2024 Sky Smoke Check LLC. All rights reserved.</p>
                            </div>
                        </div>
                    </div>
                </footer>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const termsCheck = document.getElementById('termsCheck');
                        const paymentCheck = document.getElementById('paymentCheck');
                        const lightCheck = document.getElementById('lightCheck');
                        const proceedButton = document.getElementById('proceedPayment');

                        function updateButtonState() {
                            proceedButton.disabled = !(termsCheck.checked && paymentCheck.checked && lightCheck.checked);
                        }

                        termsCheck.addEventListener('change', updateButtonState);
                        paymentCheck.addEventListener('change', updateButtonState);
                        lightCheck.addEventListener('change', updateButtonState);
                    });
                </script>
            </body>
            </html>
            <?php
        }
    } catch (Exception $e) {
        error_log("Error generating PDF: " . $e->getMessage());
        header("Location: schedule.php?error=1");
        exit();
    }
} else {
    header("Location: schedule.php");
    exit();
}
?> 
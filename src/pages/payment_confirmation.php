<?php
session_start();
require_once '../config/db_connection.php';
require_once '../includes/email_helper.php';

// Get appointment ID from POST data
$appointment_id = $_POST['appointment_id'] ?? null;

if (!$appointment_id) {
    header("Location: schedule.php");
    exit();
}

// Get current domain
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$domain = $protocol . $_SERVER['HTTP_HOST'];
$base_url = $domain . dirname($_SERVER['PHP_SELF']);
$base_url = rtrim($base_url, '/');

// Fetch appointment details
$stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

// Send email to all consultants
$stmt = $conn->prepare("SELECT email, firstName, lastName FROM accounts WHERE accountType = 2");
$stmt->execute();
$result = $stmt->get_result();
$consultants = $result->fetch_all(MYSQLI_ASSOC);

foreach ($consultants as $consultant) {
    // Generate unique token for this consultant
    $token = bin2hex(random_bytes(32));
    
    // Store the token in the database
    $stmt = $conn->prepare("INSERT INTO appointment_tokens (appointment_id, consultant_email, token) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $appointment_id, $consultant['email'], $token);
    $stmt->execute();
    
    $to = $consultant['email'];
    $subject = "New Smoke Check Appointment Request";
    
    $message = '<!DOCTYPE html>
    <html>
    <head>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                color: #333;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px;
            }
            .header {
                color: #2c3e50;
                border-bottom: 2px solid #3498db;
                padding-bottom: 10px;
                margin-bottom: 20px;
            }
            .details {
                background-color: #f8f9fa;
                padding: 15px;
                border-radius: 5px;
                margin: 20px 0;
            }
            .button {
                display: inline-block;
                padding: 10px 20px;
                margin: 10px 5px;
                background-color: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 5px;
            }
            .button.accept {
                background-color: #2ecc71;
            }
            .button.deny {
                background-color: #e74c3c;
            }
            .footer {
                margin-top: 30px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                color: #7f8c8d;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h2>New Smoke Check Appointment Request</h2>
        </div>
        
        <p>Dear ' . htmlspecialchars($consultant['firstName'] . ' ' . $consultant['lastName']) . ',</p>
        
        <p>A new smoke check appointment has been requested.</p>
        
        <div class="details">
            <h3>Appointment Details:</h3>
            <p><strong>Company:</strong> ' . htmlspecialchars($appointment['companyName']) . '</p>
            <p><strong>Date:</strong> ' . htmlspecialchars($appointment['bookingDate']) . '</p>
            <p><strong>Time:</strong> ' . htmlspecialchars($appointment['bookingTime']) . '</p>
            <p><strong>Number of Vehicles:</strong> ' . htmlspecialchars($appointment['number_of_vehicles']) . '</p>
        </div>
        
        <p>Please review the attached quotation and respond to this request by clicking one of the buttons below:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="' . $base_url . '/process_consultant_response.php?action=accept&token=' . $token . '" class="button accept">Accept Appointment</a>
            <a href="' . $base_url . '/reschedule_appointment.php?id=' . $appointment_id . '&token=' . $token . '" class="button" style="background-color: #f39c12;">Reschedule Appointment</a>
            <a href="' . $base_url . '/process_consultant_response.php?action=deny&token=' . $token . '" class="button deny">Deny Appointment</a>
        </div>
        
        <div class="footer">
            <p>Best regards,<br>Sky Smoke Check LLC Team</p>
        </div>
    </body>
    </html>';
    
    // Add PDF attachment
    $pdf_path = __DIR__ . '/../../temp/quotation_' . $appointment_id . '.pdf';
    $attachments = [
        [
            'path' => $pdf_path,
            'name' => 'Quotation_' . $appointment_id . '.pdf'
        ]
    ];
    
    sendEmail($to, $subject, $message, true, $attachments);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Submitted - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 4rem auto;
            padding: 2rem;
            text-align: center;
        }

        .confirmation-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 1.5rem;
        }

        .appointment-details {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: left;
        }

        .detail-row {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .detail-label {
            font-weight: 600;
            color: #495057;
        }

        .action-buttons {
            margin-top: 2rem;
        }

        .btn {
            margin: 0.5rem;
            padding: 0.75rem 2rem;
        }

        .registration-benefits {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: left;
        }

        .benefit-item {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .benefit-item i {
            color: #28a745;
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .company-advantages {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 2rem;
            margin: 2rem 0;
            text-align: left;
        }

        .advantage-item {
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .advantage-item i {
            color: #28a745;
            margin-right: 1rem;
            font-size: 1.2rem;
        }

        .next-steps {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #e9ecef;
            border-radius: 8px;
        }

        .next-steps p {
            margin-bottom: 0.5rem;
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

    <main class="confirmation-container">
        <div class="confirmation-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1>Appointment Request Submitted</h1>
        <p class="lead">Thank you for choosing Sky Smoke Check LLC. Your appointment request has been received and will be processed shortly.</p>

        <div class="appointment-details">
            <h2>Your Appointment Details</h2>
            <div class="detail-row">
                <span class="detail-label">Company Name:</span>
                <span><?php echo htmlspecialchars($appointment['companyName']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date:</span>
                <span><?php echo htmlspecialchars($appointment['bookingDate']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Time:</span>
                <span><?php echo htmlspecialchars($appointment['bookingTime']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Number of Vehicles:</span>
                <span><?php echo htmlspecialchars($appointment['number_of_vehicles']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Location:</span>
                <span><?php echo $appointment['test_location'] === 'our_location' ? 'Our Facility' : 'Your Location'; ?></span>
            </div>
        </div>

        <div class="company-advantages">
            <h2>Why Choose Sky Smoke Check LLC?</h2>
            <div class="advantage-item">
                <i class="fas fa-check-circle"></i>
                <strong>Expert Technicians:</strong> Our certified professionals ensure accurate and reliable testing.
            </div>
            <div class="advantage-item">
                <i class="fas fa-check-circle"></i>
                <strong>Quick Service:</strong> We complete tests efficiently without compromising quality.
            </div>
            <div class="advantage-item">
                <i class="fas fa-check-circle"></i>
                <strong>Mobile Testing:</strong> We come to your location for your convenience.
            </div>
            <div class="advantage-item">
                <i class="fas fa-check-circle"></i>
                <strong>Competitive Pricing:</strong> We offer the best value for professional smoke testing services.
            </div>
        </div>

        <div class="next-steps">
            <p>You will receive a confirmation email once your appointment is approved by our team.</p>
            <p>For any questions, please contact us at (555) 123-4567</p>
        </div>

        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary">Return to Home</a>
            <a href="schedule.php" class="btn btn-outline-primary">Schedule Another Test</a>
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
        </div>
        
        <div class="footer-bottom">
            <div class="footer-bottom-content">
                <div class="copyright">
                    <p>&copy; 2024 Sky Smoke Check LLC. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/script.js"></script>
</body>
</html> 
<?php
session_start();
require_once '../config/db_connection.php';

// Get appointment ID from URL
$appointment_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch appointment details
$appointment = null;
$vehicles = [];
if ($appointment_id > 0) {
    // Get appointment details
    $stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment = $result->fetch_assoc();

    // Get vehicle details
    $vehicle_stmt = $conn->prepare("SELECT * FROM vehicles WHERE appointment_id = ?");
    $vehicle_stmt->bind_param("i", $appointment_id);
    $vehicle_stmt->execute();
    $vehicle_result = $vehicle_stmt->get_result();
    while ($vehicle = $vehicle_result->fetch_assoc()) {
        $vehicles[] = $vehicle;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Request Received - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/main.css">
    <style>
        .success-container {
            max-width: 800px;
            margin: 4rem auto;
            padding: 2rem;
            text-align: center;
        }

        .success-icon {
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

        .vehicle-list {
            margin-top: 1rem;
        }

        .vehicle-item {
            background: white;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
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
                <li><a href="about.html">About</a></li>
                <li><a href="news.html">News</a></li>
                <li><a href="contact.html">Contact</a></li>
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

    <main class="success-container">
        <div class="success-icon">
            <i class="fas fa-check-circle"></i>
        </div>
        <h1>Quotation Request Received!</h1>
        <p class="lead">Thank you for requesting a quotation for your smoke test with Sky Smoke Check LLC.</p>
        
        <?php if ($appointment): ?>
        <div class="appointment-details">
            <div class="detail-row">
                <span class="detail-label">Request ID:</span>
                <span><?php echo htmlspecialchars($appointment['id']); ?></span>
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
                <span class="detail-label">Test Location:</span>
                <span><?php echo $appointment['test_location'] === 'our_location' ? '121 E 11th St, Tracy, CA 95376' : htmlspecialchars($appointment['test_address']); ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Number of Vehicles:</span>
                <span><?php echo htmlspecialchars($appointment['number_of_vehicles']); ?></span>
            </div>
            <?php if (!empty($vehicles)): ?>
            <div class="detail-row">
                <span class="detail-label">Vehicles:</span>
                <div class="vehicle-list">
                    <?php foreach ($vehicles as $vehicle): ?>
                    <div class="vehicle-item">
                        <?php echo htmlspecialchars($vehicle['vehYear'] . ' ' . $vehicle['vehMake'] . ' - VIN: ' . $vehicle['vin'] . ' - Plate: ' . $vehicle['plateNo']); ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="index.php" class="btn btn-primary">Return to Home</a>
            <a href="schedule.php" class="btn btn-outline-primary">Request Another Quotation</a>
        </div>

        <div class="mt-4">
            <p>A confirmation email has been sent to your email address with these details.</p>
            <p>Our team will review your request and send you a quotation within 24 hours.</p>
            <p>If you have any questions, please contact us at (555) 123-4567.</p>
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
                    <li><a href="about.html">About Us</a></li>
                    <li><a href="contact.html">Contact</a></li>
                    <li><a href="#schedule">Book Appointment</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Legal</h3>
                <ul>
                    <li><a href="privacy.html">Privacy & Cookies Policy</a></li>
                    <li><a href="terms.html">Web Terms of Use</a></li>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/script.js"></script>
</body>
</html> 
</html> 
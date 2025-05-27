<?php
session_start();
require_once '../config/db_connection.php';

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header("Location: login.php");
    exit();
}

try {
    // Verify token and update account status
    $stmt = $conn->prepare("
        SELECT consultant_email 
        FROM appointment_tokens 
        WHERE token = ? AND used = 0
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Invalid or expired token");
    }
    
    $row = $result->fetch_assoc();
    $email = $row['consultant_email'];
    
    // Start transaction
    $conn->begin_transaction();
    
    // Update account status
    $stmt = $conn->prepare("
        UPDATE accounts 
        SET status = 'active' 
        WHERE email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    // Update client status
    $stmt = $conn->prepare("
        UPDATE clients 
        SET status = 'active' 
        WHERE email = ?
    ");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    
    // Mark token as used
    $stmt = $conn->prepare("
        UPDATE appointment_tokens 
        SET used = 1, used_at = NOW() 
        WHERE token = ?
    ");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    // Set success message
    $_SESSION['success_message'] = "Your account has been successfully activated! You can now log in.";
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error_message'] = "Error activating account: " . $e->getMessage();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Confirmed - Sky Smoke Check LLC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/footer.css">
    <style>
        .confirmation-container {
            min-height: calc(100vh - 100px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            margin-top: 100px;
        }
        .confirmation-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
            padding: 40px;
            text-align: center;
            max-width: 600px;
            width: 100%;
        }
        .cloud-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }
        .confirmation-title {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        .confirmation-message {
            color: #6c757d;
            font-size: 1.2rem;
            margin-bottom: 30px;
        }
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        .btn-custom {
            padding: 12px 30px;
            font-size: 1.1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .btn-login {
            background-color: #28a745;
            color: white;
            border: none;
        }
        .btn-login:hover {
            background-color: #218838;
            transform: translateY(-2px);
            color: white;
        }
        .btn-home {
            background-color: #17a2b8;
            color: white;
            border: none;
        }
        .btn-home:hover {
            background-color: #138496;
            transform: translateY(-2px);
            color: white;
        }
        @keyframes float {
            0% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-20px);
            }
            100% {
                transform: translateY(0px);
            }
        }
    </style>
</head>
<body>
    <header>
        <nav>
            <div class="logo">
                <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 10px;">
                    <img src="../assets/images/logo.png" alt="Sky Smog Check Logo" class="nav-logo">
                    <h1>Sky Smog Check</h1>
                </a>
            </div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="services.php">Services</a></li>
                <li><a href="about.php">About</a></li>
                <li><a href="news.php">News</a></li>
                <li><a href="contact.php">Contact</a></li>
                <li><a href="login.php" class="login-button">Login</a></li>
                <li><a href="schedule.php" class="cta-button">Schedule Test</a></li>
            </ul>
            <div class="burger">
                <div class="line1"></div>
                <div class="line2"></div>
                <div class="line3"></div>
            </div>
        </nav>
    </header>

    <main>
        <div class="confirmation-container">
            <div class="confirmation-card">
                <i class="fas fa-cloud cloud-icon"></i>
                <h1 class="confirmation-title">Account Confirmed!</h1>
                <p class="confirmation-message">
                    Your account has been successfully activated! 🎉<br>
                    You can now log in to access your account and manage your vehicles.
                </p>
                <div class="button-group">
                    <a href="login.php" class="btn btn-custom btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                    </a>
                    <a href="index.php" class="btn btn-custom btn-home">
                        <i class="fas fa-home me-2"></i>Back to Home
                    </a>
                </div>
            </div>
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
                    <li><a href="index.php#schedule">Book Appointment</a></li>
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
                    <p>&copy; <?php echo date('Y'); ?> Sky Smog Check. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
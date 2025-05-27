<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Created - Sky Smoke Check LLC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/footer.css">
    <style>
        .success-container {
            min-height: calc(100vh - 100px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            margin-top: 100px;
        }
        .success-card {
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
            color: #17a2b8;
            margin-bottom: 20px;
            animation: float 3s ease-in-out infinite;
        }
        .success-title {
            color: #2c3e50;
            font-size: 2.5rem;
            margin-bottom: 20px;
        }
        .success-message {
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
            background-color: #17a2b8;
            color: white;
            border: none;
        }
        .btn-login:hover {
            background-color: #138496;
            transform: translateY(-2px);
            color: white;
        }
        .btn-home {
            background-color: #28a745;
            color: white;
            border: none;
        }
        .btn-home:hover {
            background-color: #218838;
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
        <div class="success-container">
            <div class="success-card">
                <i class="fas fa-cloud cloud-icon"></i>
                <h1 class="success-title">Welcome to the Sky Family!</h1>
                <p class="success-message">
                    Your account has been created successfully! 🎉<br>
                    Please check your email inbox to activate your account.<br>
                    We're excited to have you on board and look forward to helping you with all your trucking needs!
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
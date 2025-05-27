<?php
session_start();
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
    <link rel="stylesheet" href="../styles/footer.css">
    <style>
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            animation: bounce 1s ease infinite;
        }
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        .benefits-card {
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            background-color: #f8f9fa;
            transition: transform 0.3s ease;
        }
        .benefits-card:hover {
            transform: translateY(-5px);
        }
        .benefit-icon {
            font-size: 2rem;
            color: #17a2b8;
            margin-bottom: 15px;
        }
        .register-btn {
            background-color: #17a2b8;
            color: white;
            padding: 15px 30px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            transition: all 0.3s ease;
            border: none;
        }
        .register-btn:hover {
            background-color: #138496;
            color: white;
            transform: scale(1.05);
        }
        .easy-registration {
            font-style: italic;
            color: #6c757d;
            margin: 20px 0;
        }
        main {
            margin-top: 100px; /* Add margin to account for fixed header */
            min-height: calc(100vh - 100px); /* Ensure minimum height for content */
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
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="user-actions">
                        <?php 
                        $email = $_SESSION['email'] ?? '';
                        $domain = substr(strrchr($email, "@"), 1);
                        $redirect_page = ($domain === 'skytransportsolutions.com') ? 'admin_welcome.php' : 'welcome.php';
                        ?>
                        <a href="<?php echo $redirect_page; ?>" class="user-icon" title="My Account">
                            <i class="fas fa-user-circle"></i>
                        </a>
                        <a href="logout.php" class="login-button">Logout</a>
                    </li>
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

    <main>
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-8 text-center">
                    <i class="fas fa-check-circle success-icon"></i>
                    <h1 class="mt-4">Request Submitted Successfully!</h1>
                    <p class="lead">Your service request has been sent. You will receive a confirmation email once it is accepted.</p>
                    
                    <div class="easy-registration">
                        <p class="h4">Creating an account with us is as easy as backing into a dock on a quiet Sunday morning.</p>
                    </div>

                    <div class="row mt-5">
                        <div class="col-md-4">
                            <div class="benefits-card">
                                <i class="fas fa-clock benefit-icon"></i>
                                <h4>Save Time</h4>
                                <p>Quick booking and easy scheduling for all your future services</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="benefits-card">
                                <i class="fas fa-history benefit-icon"></i>
                                <h4>Track History</h4>
                                <p>Access your complete service history and documents anytime</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="benefits-card">
                                <i class="fas fa-bell benefit-icon"></i>
                                <h4>Stay Updated</h4>
                                <p>Get instant notifications about your service status</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <a href="register.php?appointment_id=<?php echo urlencode($_SESSION['last_appointment_id'] ?? ''); ?>" class="register-btn">
                            <i class="fas fa-user-plus me-2"></i>Create Your Account
                        </a>
                    </div>

                    <div class="mt-4">
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>Return to Home
                        </a>
                    </div>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/script.js"></script>
</body>
</html> 
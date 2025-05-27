<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Terms of Service - Sky Smoke Check LLC">
    <title>Terms of Service | Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/legal.css">
    <link rel="stylesheet" href="../styles/footer.css">
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
                <li><a href="index.php">Home</a></li>
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

    <main>
        <div class="legal-container">
            <div class="legal-content">
                <h1>Terms of Service</h1>
                <p class="last-updated">Last Updated: March 2024</p>

                <section>
                    <h2>1. Acceptance of Terms</h2>
                    <p>By accessing and using the services provided by Sky Smoke Check LLC ("we," "our," or "us"), you agree to comply with and be bound by these Terms of Service.</p>
                </section>

                <section>
                    <h2>2. Services Description</h2>
                    <p>Sky Smoke Check LLC provides smoke testing services for trucks and vehicles, including but not limited to:</p>
                    <ul>
                        <li>Vehicle emissions testing</li>
                        <li>Smoke opacity measurements</li>
                        <li>Compliance certification</li>
                        <li>Testing documentation</li>
                    </ul>
                </section>

                <section>
                    <h2>3. User Responsibilities</h2>
                    <p>Users of our services agree to:</p>
                    <ul>
                        <li>Provide accurate and complete information</li>
                        <li>Maintain the confidentiality of their account</li>
                        <li>Comply with all applicable laws and regulations</li>
                        <li>Use the services only for lawful purposes</li>
                    </ul>
                </section>

                <section>
                    <h2>4. Payment Terms</h2>
                    <p>Users agree to pay all fees associated with our services. Payment terms are as follows:</p>
                    <ul>
                        <li>Payment is required at the time of service</li>
                        <li>We accept major credit cards and approved payment methods</li>
                        <li>All fees are non-refundable unless otherwise specified</li>
                    </ul>
                </section>

                <section>
                    <h2>5. Limitation of Liability</h2>
                    <p>Sky Smoke Check LLC shall not be liable for any indirect, incidental, special, consequential, or punitive damages resulting from your use or inability to use our services.</p>
                </section>

                <section>
                    <h2>6. Changes to Terms</h2>
                    <p>We reserve the right to modify these terms at any time. Users will be notified of any changes through our website or email.</p>
                </section>

                <section>
                    <h2>7. Contact Information</h2>
                    <p>If you have any questions about these Terms of Service, please contact us at:</p>
                    <address>
                        Sky Smoke Check LLC<br>
                        Email: info@skysmoke.com<br>
                        Phone: (555) 123-4567
                    </address>
                </section>
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
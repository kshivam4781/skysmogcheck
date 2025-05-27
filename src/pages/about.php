<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Learn about Sky Smoke Check LLC - Our mission, team, and commitment to excellence in emissions testing services.">
    <meta name="keywords" content="about smoke testing, emissions testing company, truck testing experts, certified technicians">
    <title>About Us - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/about.css">
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
                <li><a href="public_quotation.php" class="cta-button">Schedule Test</a></li>
            </ul>
            <div class="burger">
                <div class="line1"></div>
                <div class="line2"></div>
                <div class="line3"></div>
            </div>
        </nav>
    </header>

    <main>
        <section class="page-header">
            <h1>About Sky Smoke Check</h1>
            <p>Your trusted partner in emissions testing and compliance</p>
        </section>

        <section class="about-main">
            <div class="about-content">
                <div class="about-grid">
                    <div class="about-item">
                        <i class="fas fa-certificate"></i>
                        <h3>Certified Technicians</h3>
                        <p>Our team consists of certified professionals with years of experience in emissions testing. Each technician undergoes regular training to stay current with the latest regulations and testing methods.</p>
                    </div>
                    <div class="about-item">
                        <i class="fas fa-clock"></i>
                        <h3>Quick Turnaround</h3>
                        <p>Get your test results and documentation within 24 hours of testing. We understand that time is valuable in the transportation industry, and we're committed to providing fast, efficient service.</p>
                    </div>
                    <div class="about-item">
                        <i class="fas fa-shield-alt"></i>
                        <h3>Compliance Guarantee</h3>
                        <p>We ensure your vehicles meet all state and federal regulations. Our comprehensive testing procedures and detailed reporting help you maintain compliance with confidence.</p>
                    </div>
                </div>
            </div>

            <section class="mission-vision">
                <div class="mission">
                    <h2>Our Mission</h2>
                    <p>To provide reliable, accurate, and efficient emissions testing services that help businesses maintain compliance while minimizing operational disruptions.</p>
                </div>
                <div class="vision">
                    <h2>Our Vision</h2>
                    <p>To be the leading provider of emissions testing services, setting the standard for quality, reliability, and customer service in the industry.</p>
                </div>
            </section>

            <section class="team">
                <h2>Our Team</h2>
                <div class="team-grid">
                    <div class="team-member">
                        <img src="https://via.placeholder.com/150" alt="Team Member 1">
                        <h3>John Smith</h3>
                        <p>Chief Executive Officer</p>
                        <p>20+ years of experience in emissions testing and compliance</p>
                    </div>
                    <div class="team-member">
                        <img src="https://via.placeholder.com/150" alt="Team Member 2">
                        <h3>Sarah Johnson</h3>
                        <p>Technical Director</p>
                        <p>Certified emissions testing specialist with 15 years of experience</p>
                    </div>
                    <div class="team-member">
                        <img src="https://via.placeholder.com/150" alt="Team Member 3">
                        <h3>Michael Brown</h3>
                        <p>Operations Manager</p>
                        <p>Expert in fleet testing and compliance management</p>
                    </div>
                </div>
            </section>

            <section class="certifications">
                <h2>Our Certifications</h2>
                <div class="cert-grid">
                    <div class="cert-item">
                        <i class="fas fa-award"></i>
                        <h3>DOT Certified</h3>
                        <p>Authorized testing facility for Department of Transportation requirements</p>
                    </div>
                    <div class="cert-item">
                        <i class="fas fa-certificate"></i>
                        <h3>EPA Approved</h3>
                        <p>Recognized by the Environmental Protection Agency for emissions testing</p>
                    </div>
                    <div class="cert-item">
                        <i class="fas fa-shield-alt"></i>
                        <h3>ISO 9001</h3>
                        <p>Quality management system certified for consistent service delivery</p>
                    </div>
                </div>
            </section>
        </section>
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
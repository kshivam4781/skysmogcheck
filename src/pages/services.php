<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Comprehensive smoke testing services for commercial vehicles. Our services include truck testing, fleet testing, compliance services, and consulting.">
    <meta name="keywords" content="truck smoke testing services, fleet testing, compliance services, emissions consulting, DOT testing">
    <title>Services - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
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
            <h1>Our Services</h1>
            <p>Professional smog testing and clean truck check services at competitive prices</p>
        </section>

        <section id="smog-test" class="service-detail">
            <div class="service-content">
                <h2>Smog Test</h2>
                <div class="service-description">
                    <img src="../assets/images/smog-test.jpg" alt="Smog Test Service">
                    <div class="text-content">
                        <h3>Professional Smog Testing</h3>
                        <p>We provide fast and accurate smog testing services for your vehicles. Our experienced technicians ensure thorough testing and compliance with all regulations.</p>
                        <ul>
                            <li>Professional and certified testing</li>
                            <li>Fast and accurate results</li>
                            <li>Mobile service available at your location</li>
                            <li>Comprehensive testing and documentation</li>
                        </ul>
                        <p class="price">Price: $75 per vehicle</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="clean-truck-check" class="service-detail">
            <div class="service-content">
                <h2>Clean Truck Check</h2>
                <div class="service-description">
                    <img src="../assets/images/clean-truck.jpg" alt="Clean Truck Check Service">
                    <div class="text-content">
                        <h3>Comprehensive Clean Truck Check</h3>
                        <p>Our clean truck check service ensures your vehicle meets all safety and environmental standards. We provide detailed inspections and necessary documentation.</p>
                        <ul>
                            <li>Thorough vehicle inspection</li>
                            <li>Safety compliance check</li>
                            <li>Mobile service available</li>
                            <li>Detailed inspection report</li>
                        </ul>
                        <p class="price">Price: $50 per vehicle</p>
                    </div>
                </div>
            </div>
        </section>

        <section id="mobile-service" class="service-detail">
            <div class="service-content">
                <h2>Mobile Service</h2>
                <div class="service-description">
                    <img src="../assets/images/mobile-service.jpg" alt="Mobile Service">
                    <div class="text-content">
                        <h3>We Come to You</h3>
                        <p>For your convenience, we offer mobile testing services at your location. Our team brings all necessary equipment to perform both smog tests and clean truck checks on-site.</p>
                        <ul>
                            <li>On-site testing available</li>
                            <li>Same professional service</li>
                            <li>Save time and transportation costs</li>
                            <li>Flexible scheduling</li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <section class="pricing">
            <h2>Service Pricing</h2>
            <div class="pricing-grid">
                <div class="pricing-card">
                    <h3>Smog Test</h3>
                    <p class="price">$75</p>
                    <ul>
                        <li>Professional testing</li>
                        <li>Fast results</li>
                        <li>Compliance certificate</li>
                        <li>Mobile service available</li>
                    </ul>
                    <a href="public_quotation.php" class="cta-button">Schedule Now</a>
                </div>
                <div class="pricing-card featured">
                    <h3>Clean Truck Check</h3>
                    <p class="price">$50</p>
                    <ul>
                        <li>Comprehensive inspection</li>
                        <li>Safety compliance</li>
                        <li>Detailed report</li>
                        <li>Mobile service available</li>
                    </ul>
                    <a href="public_quotation.php" class="cta-button">Schedule Now</a>
                </div>
                <div class="pricing-card">
                    <h3>Other Services</h3>
                    <p class="price">Custom</p>
                    <ul>
                        <li>Additional services available</li>
                        <li>Special discounts</li>
                        <li>Custom solutions</li>
                        <li>Call for details</li>
                    </ul>
                    <a href="contact.php" class="cta-button">Contact Us</a>
                </div>
            </div>
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
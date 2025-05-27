<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sky Smog Check - Professional truck and commercial vehicle smoke testing services. Certified emissions testing for fleets and individual vehicles. Schedule your appointment today.">
    <meta name="keywords" content="truck smoke testing, commercial vehicle testing, emissions testing, fleet testing, DOT compliance, environmental testing">
    <meta name="author" content="Sky Smog Check">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="Sky Smog Check - Professional Truck Smoke Testing">
    <meta property="og:description" content="Professional smoke testing services for commercial vehicles. Ensuring compliance with environmental standards.">
    <meta property="og:image" content="https://images.unsplash.com/photo-1517841905240-472988babdf9">
    <meta property="og:url" content="https://skysmogcheck.com">
    <link rel="canonical" href="https://skysmogcheck.com">
    <title>Sky Smog Check - Professional Truck Smoke Testing Services</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/main.css">
    <link rel="stylesheet" href="../styles/contact.css">
    <link rel="stylesheet" href="../styles/footer.css">
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
        <section id="home" class="hero">
            <div class="hero-content">
                <h1>Professional Smoke Testing for Commercial Vehicles</h1>
                <p>Expert emissions testing services ensuring your fleet meets environmental standards with precision and care</p>
                <a href="public_quotation.php" class="cta-button">Schedule Your Test Today</a>
            </div>
        </section>

        <section id="services" class="services">
            <h2>Our Services</h2>
            <div class="services-grid">
                <div class="service-card">
                    <i class="fas fa-truck"></i>
                    <h3>Truck Smoke Testing</h3>
                    <p>Comprehensive smoke testing for all types of commercial trucks, ensuring compliance with environmental regulations.</p>
                    <a href="services.html#truck-testing" class="learn-more">Learn More</a>
                </div>
                <div class="service-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3>Flexible Scheduling</h3>
                    <p>Convenient appointment times to fit your business needs, with mobile testing options available.</p>
                    <a href="services.html#scheduling" class="learn-more">Learn More</a>
                </div>
                <div class="service-card">
                    <i class="fas fa-file-alt"></i>
                    <h3>Detailed Reports</h3>
                    <p>Complete documentation of test results and compliance certificates for your records.</p>
                    <a href="services.html#reports" class="learn-more">Learn More</a>
                </div>
            </div>
        </section>

        <section class="about-preview">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="about-text">
                            <h6>Professional Service</h6>
                            <h2>Why Choose Sky Smog Check?</h2>
                            <p>Sky Smog Check has extensive expertise in emissions testing and compliance for commercial vehicles. Our skilled staff is equipped to handle all your smog testing needs, ensuring your fleet meets all environmental standards and regulations. We make sure our customers stay current with their records and compliance requirements.</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="feature-box mt-4">
                                    <div class="feature-box-content text-center">
                                        <div class="fbc-btn">
                                            <i class="fas fa-certificate"></i>
                                        </div>
                                        <h3>Certified Technicians</h3>
                                        <p>Our team consists of certified professionals with years of experience in emissions testing.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="feature-box">
                                    <div class="feature-box-content text-center">
                                        <div class="fbc-btn">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <h3>Quick Turnaround</h3>
                                        <p>Get your test results and documentation within 24 hours of testing.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="feature-box">
                                    <div class="feature-box-content text-center">
                                        <div class="fbc-btn">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                        <h3>Compliance Guarantee</h3>
                                        <p>We ensure your vehicles meet all state and federal regulations.</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="feature-box active-feature">
                                    <div class="feature-box-content text-center">
                                        <div class="fbc-btn">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                        <h3>Mobile Service</h3>
                                        <p>We come to you with our mobile testing units for your convenience.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="schedule" class="schedule">
            <h2>Send Us a Message</h2>
            <div class="schedule-form">
                <form id="contactForm" action="contact_handler.php" method="POST">
                    <div class="row">
                        <div class="col-md-6">
                    <div class="form-group">
                        <input type="text" id="name" name="name" placeholder="Your Name" required>
                    </div>
                        </div>
                        <div class="col-md-6">
                    <div class="form-group">
                        <input type="email" id="email" name="email" placeholder="Email Address" required>
                    </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <input type="text" id="subject" name="subject" placeholder="Subject" required>
                    </div>
                    <div class="form-group">
                        <textarea id="message" name="message" placeholder="Your Message" rows="5" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; resize: vertical; min-height: 120px; transition: border-color 0.3s ease;"></textarea>
                    </div>
                    <button type="submit" class="cta-button">Send Message</button>
                </form>
            </div>
        </section>

        <section class="testimonials">
            <div class="Testimonials_top">
                <div class="Testimonials_title">
                    <h2>What Our Clients Say</h2>
                    <p>Hear from our satisfied customers about their experience with Sky Smoke Check LLC</p>
                </div>
            </div>
            <div class="Testimonials_bottom">
                <div class="testimonials_container">
                    <div class="testimonials_container_left">
                        <div class="listing-carousel-button listing-carousel-button-prev">
                            <i class="fas fa-chevron-left" style="color: #fff"></i>
                        </div>
                    </div>

                    <div class="testimonials_container_center">
                        <div class="testimonials_content">
                            <div class="testimonials_avatar">
                                <i class="fas fa-user-tie"></i>
                            </div>

                            <div class="testimonials_text_before">
                                <i class="fas fa-quote-right"></i>
                            </div>

                            <div class="testimonials_text">
                                <p>Sky Smoke Check provided excellent service and quick turnaround. Their team was professional and thorough. The testing process was efficient and their reports were detailed and accurate.</p>
                                <div class="testimonials_information">
                                    <h3>John Smith</h3>
                                    <h4>Fleet Manager, ABC Trucking</h4>
                                </div>
                            </div>
                            <div class="testimonials_text_after">
                                <i class="fas fa-quote-left"></i>
                            </div>
                        </div>

                        <div class="testimonials_content active">
                            <div class="testimonials_avatar">
                                <i class="fas fa-user-graduate"></i>
                            </div>

                            <div class="testimonials_text_before">
                                <i class="fas fa-quote-right"></i>
                            </div>

                            <div class="testimonials_text">
                                <p>We've been using their services for years. Always reliable and their reports are detailed and accurate. Their mobile testing service has been a game-changer for our fleet operations.</p>
                                <div class="testimonials_information">
                                    <h3>Sarah Johnson</h3>
                                    <h4>Operations Director, XYZ Logistics</h4>
                                </div>
                            </div>
                            <div class="testimonials_text_after">
                                <i class="fas fa-quote-left"></i>
                            </div>
                        </div>

                        <div class="testimonials_content">
                            <div class="testimonials_avatar">
                                <i class="fas fa-user-cog"></i>
                            </div>

                            <div class="testimonials_text_before">
                                <i class="fas fa-quote-right"></i>
                            </div>

                            <div class="testimonials_text">
                                <p>The team at Sky Smoke Check is knowledgeable and professional. They helped us navigate complex compliance requirements and provided excellent support throughout the entire process.</p>
                                <div class="testimonials_information">
                                    <h3>Michael Brown</h3>
                                    <h4>Transportation Manager, DEF Services</h4>
                                </div>
                            </div>
                            <div class="testimonials_text_after">
                                <i class="fas fa-quote-left"></i>
                            </div>
                        </div>
                    </div>

                    <div class="testimonials_container_right">
                        <div class="listing-carousel-button listing-carousel-button-next">
                            <i class="fas fa-chevron-right" style="color: #fff"></i>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="contact" class="contact">
            <h2>Contact Us</h2>
            <div class="contact-container" style="display: flex; justify-content: space-between; gap: 20px;">
                <!-- Phone Contact -->
                <div class="contact-section" style="flex: 1;">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h3>Phone</h3>
                        <a href="tel:+18004989820" class="contact-link">(800) 498-9820</a>
                        <p>Available Monday - Friday, 9am - 5:30pm</p>
                    </div>
                </div>

                <!-- Email Contact -->
                <div class="contact-section" style="flex: 1;">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3>Email</h3>
                        <a href="mailto:info@skysmogcheck.com" class="contact-link">info@skysmogcheck.com</a>
                        <p>We typically respond within 24 hours</p>
                    </div>
                </div>

                <!-- Location Contact -->
                <div class="contact-section" style="flex: 1;">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h3>Location</h3>
                        <a href="https://www.google.com/maps/place/121+E+11th+St,+Tracy,+CA+95376" target="_blank" rel="noopener noreferrer" class="contact-link">
                            121 E 11th St<br>
                            Tracy, CA 95376
                        </a>
                        <p>Business Hours:<br>
                        Mon-Fri: 9:00 AM - 5:30 PM<br>
                        Sat-Sun: Closed</p>
                    </div>
                </div>
            </div>

            <!-- Google Maps Section -->
            <!-- <div class="map-container" style="margin-top: 40px; margin-bottom: 20px; width: 100%;">
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3151.8354345096034!2d-121.4257677!3d37.7399331!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x80903d5086832d2b%3A0x4fc4235e86cc7bd8!2s121%20E%2011th%20St%2C%20Tracy%2C%20CA%2095376!5e0!3m2!1sen!2sus!4v1713734400000!5m2!1sen!2sus" 
                    width="100%" 
                    height="450" 
                    style="border:0; border-radius: 8px;" 
                    allowfullscreen="" 
                    loading="lazy" 
                    referrerpolicy="no-referrer-when-downgrade">
                </iframe>
            </div> -->
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-content">
                    <div class="footer-section">
                        <h3>Sky Smog Check</h3>
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
                    <i class="fas fa-phone"></i> (800) 498-9820<br>
                    <i class="fas fa-envelope"></i> info@skysmogcheck.com<br>
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
                        <p>&copy; 2024 Sky Smog Check. All rights reserved.</p>
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
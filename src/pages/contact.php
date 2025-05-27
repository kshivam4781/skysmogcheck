<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contact Sky Smoke Check LLC - Professional truck and commercial vehicle smoke testing services. Get in touch with our team for appointments and inquiries.">
    <meta name="keywords" content="contact smoke testing, truck testing contact, emissions testing contact, fleet testing contact">
    <meta name="author" content="Sky Smoke Check LLC">
    <meta name="robots" content="index, follow">
    <title>Contact Us - Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/contact.css">
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
                <h1>Contact Us</h1>
                <p>Get in touch with our team for professional smoke testing services</p>
        </section>

        

        <section class="contact-info">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="contact-details">
                            <h2>Get in Touch</h2>
                            <div class="contact-method">
                                <i class="fas fa-map-marker-alt"></i>
                                <div>
                                    <h3>Visit Us</h3>
                                    <a href="https://www.google.com/maps/place/121+E+11th+St,+Tracy,+CA+95376" target="_blank" rel="noopener noreferrer">
                                        121 E 11th St<br>
                                        Tracy, CA 95376
                                    </a>
                                </div>
                            </div>
                            <div class="contact-method">
                                <i class="fas fa-phone"></i>
                                <div>
                                    <h3>Call Us</h3>
                                    <a href="tel:+15551234567">(555) 123-4567</a>
                                    <p>Monday - Friday, 9am - 5pm</p>
                                </div>
                            </div>
                            <div class="contact-method">
                                <i class="fas fa-envelope"></i>
                                <div>
                                    <h3>Email Us</h3>
                                    <a href="mailto:info@skysmoke.com">info@skysmoke.com</a>
                                    <p>We typically respond within 24 hours</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="map-container">
                            <iframe 
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3151.8354345096034!2d-121.4257677!3d37.7399331!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x80903d5086832d2b%3A0x4fc4235e86cc7bd8!2s121%20E%2011th%20St%2C%20Tracy%2C%20CA%2095376!5e0!3m2!1sen!2sus!4v1713734400000!5m2!1sen!2sus" 
                                width="100%" 
                                height="450" 
                                style="border:0;" 
                                allowfullscreen="" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="contact-form-container">
            <h2>Send Us a Message</h2>
            
            <?php if (isset($_SESSION['contact_success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION['contact_success'];
                    unset($_SESSION['contact_success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['contact_error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION['contact_error'];
                    unset($_SESSION['contact_error']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['contact_errors'])): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($_SESSION['contact_errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php unset($_SESSION['contact_errors']); ?>
                </div>
            <?php endif; ?>

            <form class="contact-form" action="contact_handler.php" method="POST">
                <div class="form-group">
                    <label for="name">Name *</label>
                    <input type="text" id="name" name="name" required 
                           value="<?php echo htmlspecialchars($_SESSION['contact_form_data']['name'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required
                           value="<?php echo htmlspecialchars($_SESSION['contact_form_data']['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="phone">Phone</label>
                    <input type="tel" id="phone" name="phone"
                           value="<?php echo htmlspecialchars($_SESSION['contact_form_data']['phone'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="subject">Subject *</label>
                    <input type="text" id="subject" name="subject" required
                           value="<?php echo htmlspecialchars($_SESSION['contact_form_data']['subject'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="message">Message *</label>
                    <textarea id="message" name="message" rows="5" required><?php echo htmlspecialchars($_SESSION['contact_form_data']['message'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="cta-button">Send Message</button>
            </form>
            <?php unset($_SESSION['contact_form_data']); ?>
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="../scripts/script.js"></script>
</body>
</html> 
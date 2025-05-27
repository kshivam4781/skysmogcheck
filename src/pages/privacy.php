<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - Sky Smoke Check LLC</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/footer.css">
    <style>
        .privacy-container {
            max-width: 1000px;
            margin: 120px auto 40px;
            padding: 20px;
        }
        .privacy-section {
            margin-bottom: 30px;
        }
        .privacy-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }
        .privacy-section h3 {
            color: #17a2b8;
            margin: 25px 0 15px;
            font-size: 1.4rem;
        }
        .privacy-section p {
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .privacy-section ul {
            color: #555;
            line-height: 1.6;
            margin-bottom: 15px;
            padding-left: 20px;
        }
        .privacy-section li {
            margin-bottom: 10px;
        }
        .last-updated {
            color: #666;
            font-style: italic;
            margin-bottom: 30px;
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
        <div class="privacy-container">
            <h1 class="mb-4">Privacy Policy</h1>
            <p class="last-updated">Last Updated: <?php echo date('F d, Y'); ?></p>

            <div class="privacy-section">
                <h2>Introduction</h2>
                <p>At Sky Smoke Check LLC, we take your privacy seriously. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our smog check and clean truck check services. Please read this privacy policy carefully. If you do not agree with the terms of this privacy policy, please do not access our services.</p>
            </div>

            <div class="privacy-section">
                <h2>Information We Collect</h2>
                <h3>Personal Information</h3>
                <p>We collect personal information that you voluntarily provide to us when you:</p>
                <ul>
                    <li>Register for an account</li>
                    <li>Schedule a smog check or clean truck check</li>
                    <li>Make a payment</li>
                    <li>Contact our customer service</li>
                </ul>
                <p>This information may include:</p>
                <ul>
                    <li>Name and contact information (email, phone number, address)</li>
                    <li>Company information (for business accounts)</li>
                    <li>Vehicle information (VIN, license plate, make, model, year)</li>
                    <li>Payment information (credit card details, billing address)</li>
                    <li>Driver's license information</li>
                    <li>Insurance information</li>
                </ul>

                <h3>Service-Related Information</h3>
                <p>We collect information related to our services, including:</p>
                <ul>
                    <li>Test results and certifications</li>
                    <li>Service history</li>
                    <li>Vehicle maintenance records</li>
                    <li>Compliance documentation</li>
                </ul>
            </div>

            <div class="privacy-section">
                <h2>How We Use Your Information</h2>
                <p>We use the information we collect to:</p>
                <ul>
                    <li>Provide and maintain our services</li>
                    <li>Process your transactions</li>
                    <li>Send you service-related notifications</li>
                    <li>Comply with legal requirements</li>
                    <li>Improve our services</li>
                    <li>Communicate with you about your account or services</li>
                    <li>Send you marketing communications (with your consent)</li>
                </ul>
            </div>

            <div class="privacy-section">
                <h2>Information Sharing and Disclosure</h2>
                <p>We may share your information with:</p>
                <ul>
                    <li>Government agencies (as required by law)</li>
                    <li>Service providers who assist in our operations</li>
                    <li>Payment processors for transaction processing</li>
                    <li>Third-party portals for service completion</li>
                    <li>Legal authorities when required by law</li>
                </ul>
                <p>We do not sell your personal information to third parties.</p>
            </div>

            <div class="privacy-section">
                <h2>Data Security</h2>
                <p>We implement appropriate security measures to protect your personal information, including:</p>
                <ul>
                    <li>Encryption of sensitive data</li>
                    <li>Secure payment processing</li>
                    <li>Regular security assessments</li>
                    <li>Access controls and authentication</li>
                    <li>Secure data storage and transmission</li>
                </ul>
            </div>

            <div class="privacy-section">
                <h2>Your Rights</h2>
                <p>You have the right to:</p>
                <ul>
                    <li>Access your personal information</li>
                    <li>Correct inaccurate information</li>
                    <li>Request deletion of your information</li>
                    <li>Opt-out of marketing communications</li>
                    <li>File a complaint about our data practices</li>
                </ul>
            </div>

            <div class="privacy-section">
                <h2>Third-Party Services</h2>
                <p>We use various third-party services to complete your service requests, including:</p>
                <ul>
                    <li>Payment processing services</li>
                    <li>Vehicle information databases</li>
                    <li>Government compliance portals</li>
                    <li>Customer relationship management systems</li>
                </ul>
                <p>These services have their own privacy policies and may collect information as specified in their respective privacy policies.</p>
            </div>

            <div class="privacy-section">
                <h2>Contact Us</h2>
                <p>If you have any questions about this Privacy Policy, please contact us at:</p>
                <ul>
                    <li>Email: privacy@skysmoke.com</li>
                    <li>Phone: (555) 123-4567</li>
                    <li>Address: 121 E 11th St, Tracy, CA 95376</li>
                </ul>
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
<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Client Testimonials - Sky Smoke Check LLC">
    <title>Testimonials | Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/testimonials.css">
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
                <li><a href="index.php#schedule" class="cta-button">Schedule Test</a></li>
            </ul>
            <div class="burger">
                <div class="line1"></div>
                <div class="line2"></div>
                <div class="line3"></div>
            </div>
        </nav>
    </header>

    <main>
        <section class="testimonials">
            <div class="testimonials_title">
                <h2>What Our Clients Say</h2>
                <p>Hear from our satisfied customers about their experiences with our services</p>
            </div>
            
            <div class="testimonials_carousel">
                <div class="testimonials_content active">
                    <p class="testimonial_quote">"The service was exceptional! The team went above and beyond to meet our needs. Highly recommended!"</p>
                    <div class="testimonial_author">
                        <img src="https://via.placeholder.com/60" alt="Client 1" class="testimonial_avatar">
                        <div class="testimonial_info">
                            <h4>John Doe</h4>
                            <p>CEO, Tech Company</p>
                        </div>
                    </div>
                </div>
                
                <div class="testimonials_content">
                    <p class="testimonial_quote">"Working with this team has been a game-changer for our business. Their expertise and dedication are unmatched."</p>
                    <div class="testimonial_author">
                        <img src="https://via.placeholder.com/60" alt="Client 2" class="testimonial_avatar">
                        <div class="testimonial_info">
                            <h4>Jane Smith</h4>
                            <p>Marketing Director</p>
                        </div>
                    </div>
                </div>
                
                <div class="testimonials_content">
                    <p class="testimonial_quote">"The results exceeded our expectations. Professional, efficient, and truly customer-focused."</p>
                    <div class="testimonial_author">
                        <img src="https://via.placeholder.com/60" alt="Client 3" class="testimonial_avatar">
                        <div class="testimonial_info">
                            <h4>Mike Johnson</h4>
                            <p>Business Owner</p>
                        </div>
                    </div>
                </div>
                
                <button class="listing-carousel-button-prev" aria-label="Previous testimonial">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                
                <button class="listing-carousel-button-next" aria-label="Next testimonial">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 6L15 12L9 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
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
    <script src="../scripts/testimonials.js"></script>
</body>
</html> 
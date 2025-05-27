<?php
session_start();
?>
<!DOCTYPE html>
<html lang="pa">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="ਸਕਾਈ ਸਮੋਕ ਚੈਕ LLC - ਪ੍ਰੋਫੈਸ਼ਨਲ ਟਰੱਕ ਅਤੇ ਕਮਰਸ਼ੀਅਲ ਵਾਹਨ ਸਮੋਕ ਟੈਸਟਿੰਗ ਸੇਵਾਵਾਂ। ਫਲੀਟਾਂ ਅਤੇ ਵਿਅਕਤੀਗਤ ਵਾਹਨਾਂ ਲਈ ਸਰਟੀਫਾਈਡ ਐਮਿਸ਼ਨ ਟੈਸਟਿੰਗ। ਅੱਜ ਹੀ ਆਪਣੀ ਅਪਾਇੰਟਮੈਂਟ ਸ਼ੈਡਿਊਲ ਕਰੋ।">
    <meta name="keywords" content="ਟਰੱਕ ਸਮੋਕ ਟੈਸਟਿੰਗ, ਕਮਰਸ਼ੀਅਲ ਵਾਹਨ ਟੈਸਟਿੰਗ, ਐਮਿਸ਼ਨ ਟੈਸਟਿੰਗ, ਫਲੀਟ ਟੈਸਟਿੰਗ, DOT ਕੰਪਲਾਇੰਸ, ਵਾਤਾਵਰਣ ਟੈਸਟਿੰਗ">
    <meta name="author" content="ਸਕਾਈ ਸਮੋਕ ਚੈਕ LLC">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="ਸਕਾਈ ਸਮੋਕ ਚੈਕ LLC - ਪ੍ਰੋਫੈਸ਼ਨਲ ਟਰੱਕ ਸਮੋਕ ਟੈਸਟਿੰਗ">
    <meta property="og:description" content="ਕਮਰਸ਼ੀਅਲ ਵਾਹਨਾਂ ਲਈ ਪ੍ਰੋਫੈਸ਼ਨਲ ਸਮੋਕ ਟੈਸਟਿੰਗ ਸੇਵਾਵਾਂ। ਵਾਤਾਵਰਣ ਮਿਆਰਾਂ ਦੀ ਪਾਲਣਾ ਨੂੰ ਯਕੀਨੀ ਬਣਾਉਣਾ।">
    <meta property="og:image" content="https://images.unsplash.com/photo-1517841905240-472988babdf9">
    <meta property="og:url" content="https://skysmokecheck.com">
    <link rel="canonical" href="https://skysmokecheck.com">
    <title>ਸਕਾਈ ਸਮੋਕ ਚੈਕ LLC - ਪ੍ਰੋਫੈਸ਼ਨਲ ਟਰੱਕ ਸਮੋਕ ਟੈਸਟਿੰਗ ਸੇਵਾਵਾਂ</title>
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
                <a href="punjabi_index.php" style="text-decoration: none;">
                <h1>ਸਕਾਈ ਸਮੋਕ ਚੈਕ LLC</h1>
                </a>
            </div>
            <ul class="nav-links">
                <li><a href="punjabi_index.php">ਘਰ</a></li>
                <li><a href="punjabi_services.php">ਸੇਵਾਵਾਂ</a></li>
                <li><a href="punjabi_about.php">ਸਾਡੇ ਬਾਰੇ</a></li>
                <li><a href="punjabi_news.php">ਖਬਰਾਂ</a></li>
                <li><a href="punjabi_contact.php">ਸੰਪਰਕ</a></li>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="user-actions">
                        <?php 
                        $email = $_SESSION['email'] ?? '';
                        $domain = substr(strrchr($email, "@"), 1);
                        $redirect_page = ($domain === 'skytransportsolutions.com') ? 'admin_welcome.php' : 'welcome.php';
                        ?>
                        <a href="<?php echo $redirect_page; ?>" class="user-icon" title="ਮੇਰਾ ਖਾਤਾ">
                            <i class="fas fa-user-circle"></i>
                        </a>
                        <a href="logout.php" class="login-button">ਲੌਗਆਉਟ</a>
                    </li>
                <?php else: ?>
                    <li><a href="login.php" class="login-button">ਲੌਗਇਨ</a></li>
                <?php endif; ?>
                <li><a href="punjabi_schedule.php" class="cta-button">ਟੈਸਟ ਸ਼ੈਡਿਊਲ ਕਰੋ</a></li>
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
                <h1>ਕਮਰਸ਼ੀਅਲ ਵਾਹਨਾਂ ਲਈ ਪ੍ਰੋਫੈਸ਼ਨਲ ਸਮੋਕ ਟੈਸਟਿੰਗ</h1>
                <p>ਸਹੀ ਅਤੇ ਦੇਖਭਾਲ ਨਾਲ ਆਪਣੀ ਫਲੀਟ ਨੂੰ ਵਾਤਾਵਰਣ ਮਿਆਰਾਂ ਨੂੰ ਪੂਰਾ ਕਰਨਾ</p>
                <a href="punjabi_schedule.php" class="cta-button">ਅੱਜ ਹੀ ਆਪਣਾ ਟੈਸਟ ਸ਼ੈਡਿਊਲ ਕਰੋ</a>
            </div>
        </section>

        <section id="services" class="services">
            <h2>ਸਾਡੀਆਂ ਸੇਵਾਵਾਂ</h2>
            <div class="services-grid">
                <div class="service-card">
                    <i class="fas fa-truck"></i>
                    <h3>ਟਰੱਕ ਸਮੋਕ ਟੈਸਟਿੰਗ</h3>
                    <p>ਸਾਰੇ ਕਿਸਮਾਂ ਦੇ ਕਮਰਸ਼ੀਅਲ ਟਰੱਕਾਂ ਲਈ ਵਿਆਪਕ ਸਮੋਕ ਟੈਸਟਿੰਗ, ਵਾਤਾਵਰਣ ਨਿਯਮਾਂ ਦੀ ਪਾਲਣਾ ਨੂੰ ਯਕੀਨੀ ਬਣਾਉਂਦੀ ਹੈ।</p>
                    <a href="punjabi_services.php#truck-testing" class="learn-more">ਹੋਰ ਜਾਣੋ</a>
                </div>
                <div class="service-card">
                    <i class="fas fa-calendar-check"></i>
                    <h3>ਲਚਕਦਾਰ ਸ਼ੈਡਿਊਲਿੰਗ</h3>
                    <p>ਤੁਹਾਡੀਆਂ ਵਪਾਰਕ ਲੋੜਾਂ ਨੂੰ ਪੂਰਾ ਕਰਨ ਲਈ ਸੁਵਿਧਾਜਨਕ ਅਪਾਇੰਟਮੈਂਟ ਸਮੇਂ, ਮੋਬਾਇਲ ਟੈਸਟਿੰਗ ਵਿਕਲਪਾਂ ਦੇ ਨਾਲ।</p>
                    <a href="punjabi_services.php#scheduling" class="learn-more">ਹੋਰ ਜਾਣੋ</a>
                </div>
                <div class="service-card">
                    <i class="fas fa-file-alt"></i>
                    <h3>ਵਿਸਤ੍ਰਿਤ ਰਿਪੋਰਟਾਂ</h3>
                    <p>ਤੁਹਾਡੇ ਰਿਕਾਰਡਾਂ ਲਈ ਟੈਸਟ ਨਤੀਜਿਆਂ ਅਤੇ ਕੰਪਲਾਇੰਸ ਸਰਟੀਫਿਕੇਟਾਂ ਦੀ ਪੂਰੀ ਦਸਤਾਵੇਜ਼ੀ।</p>
                    <a href="punjabi_services.php#reports" class="learn-more">ਹੋਰ ਜਾਣੋ</a>
                </div>
            </div>
        </section>

        <section class="about-preview">
            <div class="container">
                <div class="row">
                    <div class="col-lg-6">
                        <div class="about-text">
                            <h6>ਪ੍ਰੋਫੈਸ਼ਨਲ ਸੇਵਾ</h6>
                            <h2>ਸਕਾਈ ਸਮੋਕ ਚੈਕ ਨੂੰ ਕਿ ਚੁਣੋ?</h2>
                            <p>ਅਸੀਂ ਸਰਟੀਫਾਈਡ ਪ੍ਰੋਫੈਸ਼ਨਲਾਂ ਨਾਲ ਸਿਖਰ ਦੀ ਸਮੋਕ ਟੈਸਟਿੰਗ ਸੇਵਾਵਾਂ ਪ੍ਰਦਾਨ ਕਰਦੇ ਹਾਂ, ਇਹ ਯਕੀਨੀ ਬਣਾਉਂਦੇ ਹਾਂ ਕਿ ਤੁਹਾਡੇ ਵਾਹਨ ਸਾਰੇ ਵਾਤਾਵਰਣ ਮਿਆਰਾਂ ਅਤੇ ਨਿਯਮਾਂ ਨੂੰ ਪੂਰਾ ਕਰਦੇ ਹਨ।</p>
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
                                        <h3>ਸਰਟੀਫਾਈਡ ਟੈਕਨੀਸ਼ੀਅਨ</h3>
                                        <p>ਸਾਡੀ ਟੀਮ ਵਿੱਚ ਐਮਿਸ਼ਨ ਟੈਸਟਿੰਗ ਵਿੱਚ ਸਾਲਾਂ ਦੇ ਤਜਰਬੇ ਵਾਲੇ ਸਰਟੀਫਾਈਡ ਪ੍ਰੋਫੈਸ਼ਨਲ ਹਨ।</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="feature-box">
                                    <div class="feature-box-content text-center">
                                        <div class="fbc-btn">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <h3>ਤੇਜ਼ ਟਰਨਅਰਾਊਂਡ</h3>
                                        <p>ਟੈਸਟਿੰਗ ਦੇ 24 ਘੰਟਿਆਂ ਦੇ ਅੰਦਰ ਆਪਣੇ ਟੈਸਟ ਨਤੀਜੇ ਅਤੇ ਦਸਤਾਵੇਜ਼ੀਕਰਨ ਪ੍ਰਾਪਤ ਕਰੋ।</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="feature-box">
                                    <div class="feature-box-content text-center">
                                        <div class="fbc-btn">
                                            <i class="fas fa-shield-alt"></i>
                                        </div>
                                        <h3>ਕੰਪਲਾਇੰਸ ਗਾਰੰਟੀ</h3>
                                        <p>ਅਸੀਂ ਯਕੀਨੀ ਬਣਾਉਂਦੇ ਹਾਂ ਕਿ ਤੁਹਾਡੇ ਵਾਹਨ ਸਾਰੇ ਰਾਜ ਅਤੇ ਫੈਡਰਲ ਨਿਯਮਾਂ ਨੂੰ ਪੂਰਾ ਕਰਦੇ ਹਨ।</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="feature-box active-feature">
                                    <div class="feature-box-content text-center">
                                        <div class="fbc-btn">
                                            <i class="fas fa-truck"></i>
                                        </div>
                                        <h3>ਮੋਬਾਇਲ ਸੇਵਾ</h3>
                                        <p>ਤੁਹਾਡੀ ਸੁਵਿਧਾ ਲਈ ਅਸੀਂ ਆਪਣੇ ਮੋਬਾਇਲ ਟੈਸਟਿੰਗ ਯੂਨਿਟਾਂ ਨਾਲ ਤੁਹਾਡੇ ਕੋਲ ਆਉਂਦੇ ਹਾਂ।</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="schedule" class="schedule">
            <h2>ਸਾਨੂੰ ਸੁਨੇਹਾ ਭੇਜੋ</h2>
            <div class="schedule-form">
                <form id="contactForm" action="contact_handler.php" method="POST">
                    <div class="row">
                        <div class="col-md-6">
                    <div class="form-group">
                        <input type="text" id="name" name="name" placeholder="ਤੁਹਾਡਾ ਨਾਮ" required>
                    </div>
                        </div>
                        <div class="col-md-6">
                    <div class="form-group">
                        <input type="email" id="email" name="email" placeholder="ਈਮੇਲ ਪਤਾ" required>
                    </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <input type="text" id="subject" name="subject" placeholder="ਵਿਸ਼ਾ" required>
                    </div>
                    <div class="form-group">
                        <textarea id="message" name="message" placeholder="ਤੁਹਾਡਾ ਸੁਨੇਹਾ" rows="5" required style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; resize: vertical; min-height: 120px; transition: border-color 0.3s ease;"></textarea>
                    </div>
                    <button type="submit" class="cta-button">ਸੁਨੇਹਾ ਭੇਜੋ</button>
                </form>
            </div>
        </section>

        <section class="testimonials">
            <div class="Testimonials_top">
                <div class="Testimonials_title">
                    <h2>ਸਾਡੇ ਕਲਾਇੰਟ ਕੀ ਕਹਿੰਦੇ ਹਨ</h2>
                    <p>ਸਕਾਈ ਸਮੋਕ ਚੈਕ LLC ਨਾਲ ਆਪਣੇ ਤਜਰਬੇ ਬਾਰੇ ਸਾਡੇ ਸੰਤੁਸ਼ਟ ਗਾਹਕਾਂ ਤੋਂ ਸੁਣੋ</p>
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
                                <img src="https://images.unsplash.com/photo-1517841905240-472988babdf9" alt="ਕਲਾਇੰਟ 1">
                            </div>

                            <div class="testimonials_text_before">
                                <i class="fas fa-quote-right"></i>
                            </div>

                            <div class="testimonials_text">
                                <p>ਸਕਾਈ ਸਮੋਕ ਚੈਕ ਨੇ ਉੱਤਮ ਸੇਵਾ ਅਤੇ ਤੇਜ਼ ਟਰਨਅਰਾਊਂਡ ਪ੍ਰਦਾਨ ਕੀਤਾ। ਉਨ੍ਹਾਂ ਦੀ ਟੀਮ ਪ੍ਰੋਫੈਸ਼ਨਲ ਅਤੇ ਥੋਰੋ ਸੀ। ਟੈਸਟਿੰਗ ਪ੍ਰਕਿਰਿਆ ਕੁਸ਼ਲ ਸੀ ਅਤੇ ਉਨ੍ਹਾਂ ਦੀਆਂ ਰਿਪੋਰਟਾਂ ਵਿਸਤ੍ਰਿਤ ਅਤੇ ਸਹੀ ਸਨ।</p>
                                <div class="testimonials_information">
                                    <h3>ਜੌਨ ਸਮਿਥ</h3>
                                    <h4>ਫਲੀਟ ਮੈਨੇਜਰ, ABC ਟਰੱਕਿੰਗ</h4>
                                </div>
                            </div>
                            <div class="testimonials_text_after">
                                <i class="fas fa-quote-left"></i>
                            </div>
                        </div>

                        <div class="testimonials_content active">
                            <div class="testimonials_avatar">
                                <img src="https://images.unsplash.com/photo-1517841905240-472988babdf9" alt="ਕਲਾਇੰਟ 2">
                            </div>

                            <div class="testimonials_text_before">
                                <i class="fas fa-quote-right"></i>
                            </div>

                            <div class="testimonials_text">
                                <p>ਅਸੀਂ ਸਾਲਾਂ ਤੋਂ ਉਨ੍ਹਾਂ ਦੀਆਂ ਸੇਵਾਵਾਂ ਦੀ ਵਰਤੋਂ ਕਰ ਰਹੇ ਹਾਂ। ਹਮੇਸ਼ਾ ਭਰੋਸੇਯੋਗ ਅਤੇ ਉਨ੍ਹਾਂ ਦੀਆਂ ਰਿਪੋਰਟਾਂ ਵਿਸਤ੍ਰਿਤ ਅਤੇ ਸਹੀ ਹਨ। ਉਨ੍ਹਾਂ ਦੀ ਮੋਬਾਇਲ ਟੈਸਟਿੰਗ ਸੇਵਾ ਸਾਡੀਆਂ ਫਲੀਟ ਕਾਰਵਾਈਆਂ ਲਈ ਗੇਮ-ਚੇਂਜਰ ਰਹੀ ਹੈ।</p>
                                <div class="testimonials_information">
                                    <h3>ਸਾਰਾ ਜੌਨਸਨ</h3>
                                    <h4>ਆਪਰੇਸ਼ਨਜ਼ ਡਾਇਰੈਕਟਰ, XYZ ਲੌਜਿਸਟਿਕਸ</h4>
                                </div>
                            </div>
                            <div class="testimonials_text_after">
                                <i class="fas fa-quote-left"></i>
                            </div>
                        </div>

                        <div class="testimonials_content">
                            <div class="testimonials_avatar">
                                <img src="https://images.unsplash.com/photo-1517841905240-472988babdf9" alt="ਕਲਾਇੰਟ 3">
                            </div>

                            <div class="testimonials_text_before">
                                <i class="fas fa-quote-right"></i>
                            </div>

                            <div class="testimonials_text">
                                <p>ਸਕਾਈ ਸਮੋਕ ਚੈਕ ਦੀ ਟੀਮ ਜਾਣਕਾਰ ਅਤੇ ਪ੍ਰੋਫੈਸ਼ਨਲ ਹੈ। ਉਨ੍ਹਾਂ ਨੇ ਸਾਨੂੰ ਗੁੰਝਲਦਾਰ ਕੰਪਲਾਇੰਸ ਲੋੜਾਂ ਨੂੰ ਨੈਵੀਗੇਟ ਕਰਨ ਵਿੱਚ ਮਦਦ ਕੀਤੀ ਅਤੇ ਪੂਰੀ ਪ੍ਰਕਿਰਿਆ ਵਿੱਚ ਉੱਤਮ ਸਹਾਇਤਾ ਪ੍ਰਦਾਨ ਕੀਤੀ।</p>
                                <div class="testimonials_information">
                                    <h3>ਮਾਈਕਲ ਬ੍ਰਾਊਨ</h3>
                                    <h4>ਟ੍ਰਾਂਸਪੋਰਟੇਸ਼ਨ ਮੈਨੇਜਰ, DEF ਸਰਵਿਸਿਜ਼</h4>
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
            <h2>ਸਾਡੇ ਨਾਲ ਸੰਪਰਕ ਕਰੋ</h2>
            <div class="contact-container" style="display: flex; justify-content: space-between; gap: 20px;">
                <!-- Phone Contact -->
                <div class="contact-section" style="flex: 1;">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h3>ਫੋਨ</h3>
                        <a href="tel:+15551234567" class="contact-link">(555) 123-4567</a>
                        <p>ਸੋਮਵਾਰ - ਸ਼ੁੱਕਰਵਾਰ, ਸਵੇਰੇ 9 ਵਜੇ - ਸ਼ਾਮ 5 ਵਜੇ ਉਪਲਬਧ</p>
                    </div>
                </div>

                <!-- Email Contact -->
                <div class="contact-section" style="flex: 1;">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h3>ਈਮੇਲ</h3>
                        <a href="mailto:info@skysmokecheck.com" class="contact-link">info@skysmokecheck.com</a>
                        <p>ਅਸੀਂ ਆਮ ਤੌਰ 'ਤੇ 24 ਘੰਟਿਆਂ ਦੇ ਅੰਦਰ ਜਵਾਬ ਦਿੰਦੇ ਹਾਂ</p>
                    </div>
                </div>

                <!-- Location Contact -->
                <div class="contact-section" style="flex: 1;">
                    <div class="contact-card">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h3>ਟਿਕਾਣਾ</h3>
                        <a href="https://www.google.com/maps/place/121+E+11th+St,+Tracy,+CA+95376" target="_blank" rel="noopener noreferrer" class="contact-link">
                            121 E 11th St<br>
                            Tracy, CA 95376
                        </a>
                        <p>ਕਾਰੋਬਾਰੀ ਘੰਟਿਆਂ ਦੌਰਾਨ ਸਾਡੇ ਦਫਤਰ ਵਿੱਚ ਆਓ</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="site-footer">
        <div class="footer-content">
                    <div class="footer-section">
                        <h3>ਸਕਾਈ ਸਮੋਕ ਚੈਕ LLC</h3>
                <p>ਪ੍ਰੋਫੈਸ਼ਨਲ ਟਰੱਕ ਅਤੇ ਵਾਹਨ ਸਮੋਕ ਟੈਸਟਿੰਗ ਸੇਵਾਵਾਂ।</p>
                    </div>
            
                    <div class="footer-section">
                        <h3>ਤੇਜ਼ ਲਿੰਕ</h3>
                        <ul>
                            <li><a href="punjabi_services.php">ਸੇਵਾਵਾਂ</a></li>
                    <li><a href="punjabi_about.php">ਸਾਡੇ ਬਾਰੇ</a></li>
                    <li><a href="punjabi_contact.php">ਸੰਪਰਕ</a></li>
                            <li><a href="#schedule">ਅਪਾਇੰਟਮੈਂਟ ਬੁੱਕ ਕਰੋ</a></li>
                        </ul>
                    </div>
            
            <div class="footer-section">
                <h3>ਕਾਨੂੰਨੀ</h3>
                <ul>
                    <li><a href="punjabi_privacy.php">ਪਰਾਈਵੇਸੀ ਅਤੇ ਕੂਕੀਜ਼ ਨੀਤੀ</a></li>
                    <li><a href="punjabi_terms.php">ਵੈੱਬ ਵਰਤੋਂ ਦੀਆਂ ਸ਼ਰਤਾਂ</a></li>
                    <li><a href="#">ਧੋਖਾਧੜੀ ਦੀ ਚੇਤਾਵਨੀ</a></li>
                </ul>
                </div>
            
                    <div class="footer-section">
                        <h3>ਸਾਡੇ ਨਾਲ ਸੰਪਰਕ ਕਰੋ</h3>
                <p>
                    <i class="fas fa-phone"></i> (555) 123-4567<br>
                    <i class="fas fa-envelope"></i> info@skysmoke.com<br>
                    <i class="fas fa-location-dot"></i> 121 E 11th St, Tracy, CA 95376
                </p>
                    </div>
            
            <div class="footer-section">
                <h3>ਸਾਨੂੰ ਫਾਲੋ ਕਰੋ</h3>
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
                        <p>&copy; 2024 ਸਕਾਈ ਸਮੋਕ ਚੈਕ LLC. ਸਾਰੇ ਹੱਕ ਰਾਖਵੇਂ ਹਨ।</p>
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
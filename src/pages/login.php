<?php
session_start();
// Determine which tab should be active
$activeTab = isset($_SESSION['active_tab']) ? $_SESSION['active_tab'] : (isset($_SESSION['register_error']) ? 'register' : 'login');
// Clear the active_tab session variable after using it
unset($_SESSION['active_tab']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Login or Register to access Sky Smoke Check LLC services and manage your account.">
    <meta name="keywords" content="login, register, account, smoke testing, truck testing">
    <meta name="author" content="Sky Smoke Check LLC">
    <meta name="robots" content="noindex, nofollow">
    <title>Login | Sky Smoke Check LLC</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../styles/styles.css">
    <link rel="stylesheet" href="../styles/login.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .login-logo {
            width: 60px;
            height: auto;
            margin-bottom: 15px;
        }

        /* Background logo styles */
        .background-logo {
            position: fixed;
            z-index: -1;
            opacity: 0.1;
            transition: transform 0.3s ease;
            pointer-events: none;
        }

        .background-logo.left {
            left: 10%;
            top: 50%;
            transform: translateY(-50%);
            width: 300px;
            height: auto;
        }

        .background-logo.right {
            right: 5%;
            top: 50%;
            transform: translateY(-50%);
            width: 400px;
            height: auto;
        }

        /* Add perspective to the body for 3D effect */
        body {
            perspective: 1000px;
            overflow-x: hidden;
        }

        /* Make login box more prominent */
        .login-box {
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <!-- Add background logos -->
    <img src="../assets/images/logo.png" alt="Background Logo" class="background-logo left">
    <img src="../assets/images/logo.png" alt="Background Logo" class="background-logo right">
    
    <div class="login-container">
        <div class="login-box">
            <div class="login-header">
                <img src="../assets/images/logo.png" alt="Sky Smoke Check Logo" class="login-logo">
                <p>Welcome to Sky Smoke Check. Please login or register to continue.</p>
            </div>

            <div class="login-tabs">
                <button class="tab-btn <?php echo $activeTab === 'login' ? 'active' : ''; ?>" data-tab="login">Login</button>
                <button class="tab-btn <?php echo $activeTab === 'register' ? 'active' : ''; ?>" data-tab="register">Register</button>
            </div>

            <div class="tab-content">
                <!-- Login Form -->
                <div class="tab-pane <?php echo $activeTab === 'login' ? 'active' : ''; ?>" id="login">
                    <form id="loginForm" action="login_handler.php" method="POST">
                        <?php
                        if (isset($_SESSION['login_error'])) {
                            echo '<div class="alert alert-danger">' . $_SESSION['login_error'] . '</div>';
                            unset($_SESSION['login_error']);
                        }
                        ?>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="password-input">
                                <input type="password" id="password" name="password" required>
                                <button type="button" class="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn-primary">Login</button>
                        </div>
                    </form>
                </div>

                <!-- Register Form -->
                <div class="tab-pane <?php echo $activeTab === 'register' ? 'active' : ''; ?>" id="register">
                    <form id="registerForm" action="register_handler.php" method="POST">
                        <?php
                        if (isset($_SESSION['register_error'])) {
                            echo '<div class="alert alert-danger">' . $_SESSION['register_error'] . '</div>';
                            unset($_SESSION['register_error']);
                        }
                        ?>
                        <div class="form-group">
                            <label for="company_name">Company Name</label>
                            <input type="text" id="company_name" name="company_name" value="Golden State Transport LLC" required>
                        </div>

                        <div class="form-group">
                            <label for="contact_person">Contact Person</label>
                            <input type="text" id="contact_person" name="contact_person" value="Michael Rodriguez" required>
                        </div>

                        <div class="form-group">
                            <label for="reg_email">Email Address</label>
                            <input type="email" id="reg_email" name="reg_email" value="operations@goldenstatetransport.com" required>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="(209) 555-7890" required>
                        </div>

                        <div class="form-group">
                            <label for="dot_number">DOT Number</label>
                            <input type="text" id="dot_number" name="dot_number" value="MC-987654" required>
                        </div>

                        <div class="form-group">
                            <label for="company_type">Company Type</label>
                            <select id="company_type" name="company_type" required>
                                <option value="">Select Company Type</option>
                                <option value="IRP" selected>IRP</option>
                                <option value="Local">Local</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reg_password">Password</label>
                            <div class="password-input">
                                <input type="password" id="reg_password" name="reg_password" value="GST@2024" required>
                                <button type="button" class="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reg_confirm_password">Confirm Password</label>
                            <div class="password-input">
                                <input type="password" id="reg_confirm_password" name="reg_confirm_password" value="GST@2024" required>
                                <button type="button" class="toggle-password">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <button type="submit" class="btn-primary">Register</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Custom JS -->
    <script src="../scripts/login.js"></script>
    <script>
        // Handle registration success
        <?php if (isset($_SESSION['register_success']) && $_SESSION['register_success'] === true): ?>
            Swal.fire({
                title: 'Success!',
                text: '<?php echo $_SESSION['success_message']; ?>',
                icon: 'success',
                confirmButtonText: 'OK'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Switch to login tab
                    const loginTab = document.querySelector('[data-tab="login"]');
                    if (loginTab) {
                        loginTab.click();
                    }
                }
            });
            <?php 
            unset($_SESSION['register_success']);
            unset($_SESSION['success_message']);
        endif; ?>

        // Handle pending verification
        <?php if (isset($_SESSION['pending_verification'])): ?>
        Swal.fire({
            title: 'Account Not Activated',
            html: `
                <p>Your account is not activated yet. Please check your email for our verification link. Once verified, you can login to our page.</p>
                <p>This is to maintain transparency and verify the authenticity of our users.</p>
            `,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Resend Verification Email',
            cancelButtonText: 'Close'
        }).then((result) => {
            if (result.isConfirmed) {
                resendVerificationEmail();
            }
        });
        <?php unset($_SESSION['pending_verification']); endif; ?>

        // Function to resend verification email
        function resendVerificationEmail() {
            const email = '<?php echo isset($_SESSION['pending_email']) ? $_SESSION['pending_email'] : ''; ?>';
            
            fetch('resend_verification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Verification email has been resent. Please check your inbox.',
                        icon: 'success'
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Failed to resend verification email. Please try again later.',
                        icon: 'error'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'An error occurred. Please try again later.',
                    icon: 'error'
                });
            });
        }

        // Add mouse movement effect script
        document.addEventListener('mousemove', (e) => {
            const leftLogo = document.querySelector('.background-logo.left');
            const rightLogo = document.querySelector('.background-logo.right');
            
            // Calculate mouse position relative to window
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;
            
            // Apply 3D rotation based on mouse position
            const rotateX = (mouseY - 0.5) * 20; // Vertical rotation
            const rotateY = (mouseX - 0.5) * 20; // Horizontal rotation
            
            // Apply transform to left logo
            leftLogo.style.transform = `translateY(-50%) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            
            // Apply inverse transform to right logo for opposite effect
            rightLogo.style.transform = `translateY(-50%) rotateX(${-rotateX}deg) rotateY(${-rotateY}deg)`;
        });
    </script>
</body>
</html> 
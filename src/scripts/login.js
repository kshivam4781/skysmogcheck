document.addEventListener('DOMContentLoaded', function() {
    // Tab switching functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');

    function switchTab(tabId) {
        // Hide all tab panes
        tabPanes.forEach(pane => {
            pane.classList.remove('active');
        });

        // Show selected tab pane
        const selectedPane = document.getElementById(tabId);
        if (selectedPane) {
            selectedPane.classList.add('active');
        }

        // Update tab buttons
        tabButtons.forEach(btn => {
            btn.classList.remove('active');
            if (btn.getAttribute('data-tab') === tabId) {
                btn.classList.add('active');
            }
        });
    }

    // Add click event listeners to tab buttons
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            switchTab(tabId);
        });
    });

    // Initialize with the active tab from PHP
    const activeTab = document.querySelector('.tab-pane.active').id;
    switchTab(activeTab);

    // Password visibility toggle
    const toggleButtons = document.querySelectorAll('.toggle-password');
    
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            // Toggle eye icon
            const icon = this.querySelector('i');
            icon.classList.toggle('fa-eye');
            icon.classList.toggle('fa-eye-slash');
        });
    });

    // Form validation
    const loginForm = document.getElementById('loginForm');
    const registerForm = document.getElementById('registerForm');

    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value.trim();

            if (!email || !password) {
                e.preventDefault();
                showError('Please fill in all fields');
            }
        });
    }

    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            const firstName = document.getElementById('firstName').value.trim();
            const lastName = document.getElementById('lastName').value.trim();
            const email = document.getElementById('reg_email').value.trim();
            const password = document.getElementById('reg_password').value.trim();
            const confirmPassword = document.getElementById('reg_confirm_password').value.trim();

            if (!firstName || !lastName || !email || !password || !confirmPassword) {
                e.preventDefault();
                showError('Please fill in all fields');
                return;
            }

            if (password !== confirmPassword) {
                e.preventDefault();
                showError('Passwords do not match');
                return;
            }

            if (password.length < 8) {
                e.preventDefault();
                showError('Password must be at least 8 characters long');
                return;
            }
        });
    }

    function showError(message) {
        // You can implement your error display logic here
        alert(message);
    }

    // Social login buttons
    const socialButtons = document.querySelectorAll('.social-btn');
    socialButtons.forEach(button => {
        button.addEventListener('click', function() {
            const provider = this.classList.contains('google') ? 'Google' : 'Facebook';
            console.log(`${provider} login clicked`);
            // Here you would typically implement OAuth login
        });
    });
}); 
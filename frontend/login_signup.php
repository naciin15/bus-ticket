<?php
// Start session at the very top
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF token generation function
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token function
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Define base URL (adjust if your project is under a folder like http://localhost/busbooking/)
$baseUrl = ''; // Change this if your URL is different (e.g., '/busbooking')

// Display error messages from session
$errorMessage = '';
$successMessage = '';

if (isset($_SESSION['signup_errors']) && is_array($_SESSION['signup_errors'])) {
    $errorMessage = implode('<br>', $_SESSION['signup_errors']);
    unset($_SESSION['signup_errors']);
}

if (isset($_SESSION['signup_success']) && $_SESSION['signup_success']) {
    $successMessage = 'Registration successful! Please log in.';
    unset($_SESSION['signup_success']);
}

// Preserve form data if there was an error
$formData = [
    'email' => $_SESSION['signup_form_data']['email'] ?? '',
    'username' => $_SESSION['signup_form_data']['username'] ?? '',
    'first_name' => $_SESSION['signup_form_data']['first_name'] ?? '',
    'last_name' => $_SESSION['signup_form_data']['last_name'] ?? ''
];
unset($_SESSION['signup_form_data']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Bus Ticket Booking</title>
    <style>
        :root {
            --primary: #7cc242;
            --secondary: #e6007a;
            --light: #f8fbff;
            --dark: #222;
            --gray: #eaeaea;
            --error: #ff3333;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: var(--light);
            color: var(--dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        header {
            background: white;
            padding: 1rem 5%;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nav-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary);
        }
        
        .auth-container {
            display: flex;
            flex-grow: 1;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        
        .auth-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 450px;
            padding: 2.5rem;
        }
        
        .auth-tabs {
            display: flex;
            margin-bottom: 2rem;
        }
        
        .auth-tab {
            flex: 1;
            text-align: center;
            padding: 1rem;
            background: #f4f4f4;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .auth-tab.active {
            background: var(--primary);
            color: white;
        }
        
        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .form-group label {
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-control {
            padding: 0.8rem 1rem;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .btn-primary {
            background: var(--secondary);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 1rem;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-primary:hover {
            background: #c4005f;
        }
        
        .form-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
            font-size: 0.9rem;
        }
        
        .form-footer a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .error-message {
            color: var(--error);
            background-color: #ffeeee;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .success-message {
            color: var(--primary);
            background-color: #eeffee;
            padding: 0.8rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            text-align: center;
        }
        
        .newsletter {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .newsletter h3 {
            margin-bottom: 1rem;
        }
        
        .newsletter p {
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .checkbox-group {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        footer {
            background: white;
            padding: 2rem 5%;
            margin-top: auto;
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 2rem;
        }
        
        .footer-section {
            flex: 1;
            min-width: 200px;
        }
        
        .footer-section h3 {
            margin-bottom: 1rem;
            color: var(--primary);
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section li {
            margin-bottom: 0.5rem;
        }
        
        .footer-section a {
            color: var(--dark);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-section a:hover {
            color: var(--secondary);
        }
        
        .copyright {
            text-align: center;
            padding-top: 2rem;
            margin-top: 2rem;
            border-top: 1px solid var(--gray);
            color: #666;
        }
        
        @media (max-width: 768px) {
            .auth-card {
                padding: 1.5rem;
            }
            
            .footer-content {
                flex-direction: column;
                gap: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="nav-container">
            <div class="logo">busbook.com</div>
        </div>
    </header>
    
    <main class="auth-container">
        <div class="auth-card">
            <div class="auth-tabs">
                <button class="auth-tab active" id="login-tab">Login</button>
                <button class="auth-tab" id="signup-tab">Sign Up</button>
            </div>
            
            <?php if (!empty($errorMessage)): ?>
                <div class="error-message"><?php echo $errorMessage; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($successMessage)): ?>
                <div class="success-message"><?php echo $successMessage; ?></div>
            <?php endif; ?>
            
            <form class="auth-form" id="login-form" action="<?php echo $baseUrl; ?>login.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="form-group">
                    <label for="login-email">Email</label>
                    <input type="email" id="login-email" name="email" class="form-control" required 
                           value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" class="form-control" required>
                </div>
                
                <button type="submit" class="btn-primary">Login</button>
                
                <div class="form-footer">
                    <a href="forgot_password.php">Forgot password?</a>
                    <a href="#" id="switch-to-signup">Create an account</a>
                </div>
            </form>
            
            <form class="auth-form" id="signup-form" action="<?php echo $baseUrl; ?>signup.php" method="POST" style="display: none;">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                <div class="form-group">
                    <label for="signup-username">Username</label>
                    <input type="text" id="signup-username" name="username" class="form-control" required
                           value="<?php echo htmlspecialchars($formData['username'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="signup-email">Email</label>
                    <input type="email" id="signup-email" name="email" class="form-control" required
                           value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="signup-firstname">First Name</label>
                    <input type="text" id="signup-firstname" name="first_name" class="form-control" required
                           value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="signup-lastname">Last Name</label>
                    <input type="text" id="signup-lastname" name="last_name" class="form-control" required
                           value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="signup-password">Password (min 8 characters)</label>
                    <input type="password" id="signup-password" name="password" class="form-control" required minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="signup-confirm">Confirm Password</label>
                    <input type="password" id="signup-confirm" name="confirm_password" class="form-control" required minlength="8">
                </div>
                
                <button type="submit" class="btn-primary">Create Account</button>
                
                <div class="form-footer">
                    <a href="#" id="switch-to-login">Already have an account?</a>
                </div>
            </form>
            
            <div class="newsletter">
                <h3>The best offers, directly to your inbox!</h3>
                <p>By providing your email address, you will receive exclusive information and fantastic deals to travel smart, every month.</p>
                <form>
                    <div class="form-group">
                        <input type="email" placeholder="Your email address" class="form-control">
                    </div>
                    <div class="checkbox-group">
                        <input type="checkbox" id="newsletter-consent">
                        <label for="newsletter-consent">I agree to receive promotional emails</label>
                    </div>
                    <button type="submit" class="btn-primary">Subscribe</button>
                </form>
            </div>
        </div>
    </main>
    
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>Busbook.com</h3>
                <p>Economical, comfortable, and practical, the airport shuttle is the best transfer solution to main European airports.</p>
            </div>
            
            <div class="footer-section">
                <h3>Customer Service</h3>
                <ul>
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Contact Us</a></li>
                    <li><a href="#">Manage Booking</a></li>
                    <li><a href="#">FAQ</a></li>
                </ul>
            </div>
            
            <div class="footer-section">
                <h3>Information</h3>
                <ul>
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms & Conditions</a></li>
                    <li><a href="#">Cookie Policy</a></li>
                </ul>
            </div>
        </div>
        
        <div class="copyright">
            <p>&copy; 2025 Busbook.com. All rights reserved.</p>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginTab = document.getElementById('login-tab');
            const signupTab = document.getElementById('signup-tab');
            const loginForm = document.getElementById('login-form');
            const signupForm = document.getElementById('signup-form');
            const switchToSignup = document.getElementById('switch-to-signup');
            const switchToLogin = document.getElementById('switch-to-login');
            
            function showLogin() {
                loginTab.classList.add('active');
                signupTab.classList.remove('active');
                loginForm.style.display = 'flex';
                signupForm.style.display = 'none';
            }
            
            function showSignup() {
                signupTab.classList.add('active');
                loginTab.classList.remove('active');
                signupForm.style.display = 'flex';
                loginForm.style.display = 'none';
            }
            
            loginTab.addEventListener('click', showLogin);
            signupTab.addEventListener('click', showSignup);
            switchToSignup.addEventListener('click', function(e) {
                e.preventDefault();
                showSignup();
            });
            switchToLogin.addEventListener('click', function(e) {
                e.preventDefault();
                showLogin();
            });

            // Show appropriate form based on messages
            <?php if (!empty($errorMessage) || !empty($successMessage)): ?>
                showSignup();
            <?php endif; ?>
            
            // Add form validation
            const signupFormEl = document.getElementById('signup-form');
            if (signupFormEl) {
                signupFormEl.addEventListener('submit', function(e) {
                    const password = document.getElementById('signup-password').value;
                    const confirmPassword = document.getElementById('signup-confirm').value;
                    
                    if (password !== confirmPassword) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Passwords do not match!'
                        });
                        return false;
                    }
                    
                    if (password.length < 8) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Password must be at least 8 characters long!'
                        });
                        return false;
                    }
                    
                    return true;
                });
            }

            // Add login form validation
            const loginFormEl = document.getElementById('login-form');
            if (loginFormEl) {
                loginFormEl.addEventListener('submit', function(e) {
                    const email = document.getElementById('login-email').value;
                    const password = document.getElementById('login-password').value;
                    
                    if (!email || !password) {
                        e.preventDefault();
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Please fill in all fields!'
                        });
                        return false;
                    }
                    
                    return true;
                });
            }
        });
    </script>
</body>
</html>
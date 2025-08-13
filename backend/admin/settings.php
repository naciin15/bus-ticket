<?php
require_once __DIR__ . '/../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: /busbooking/backend/admin/login.php');
    exit();
}

// Set default admin values if not set
if (!isset($_SESSION['admin_name'])) {
    $_SESSION['admin_name'] = 'Admin';
}
if (!isset($_SESSION['admin_email'])) {
    $_SESSION['admin_email'] = 'admin@example.com';
}

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'] ?? 'admin';
$admin_name = $_SESSION['admin_name'];
$admin_email = $_SESSION['admin_email'];

// Initialize variables
$success_message = '';
$error_message = '';
$current_tab = $_GET['tab'] ?? 'general';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($current_tab === 'general') {
            // Handle general settings update
            $site_name = $_POST['site_name'] ?? '';
            $site_email = $_POST['site_email'] ?? '';
            $timezone = $_POST['timezone'] ?? '';
            
            // Validate inputs
            if (empty($site_name) || empty($site_email) || empty($timezone)) {
                throw new Exception('All fields are required');
            }
            
            if (!filter_var($site_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address');
            }
            
            // In a real application, you would save these to database
            // For this example, we'll just show a success message
            $success_message = 'General settings updated successfully';
            
        } elseif ($current_tab === 'email') {
            // Handle email settings update
            $smtp_host = $_POST['smtp_host'] ?? '';
            $smtp_port = $_POST['smtp_port'] ?? '';
            $smtp_username = $_POST['smtp_username'] ?? '';
            $smtp_password = $_POST['smtp_password'] ?? '';
            $smtp_encryption = $_POST['smtp_encryption'] ?? '';
            
            // Validate inputs
            if (empty($smtp_host) || empty($smtp_port)) {
                throw new Exception('SMTP host and port are required');
            }
            
            // In a real application, you would save these to database
            $success_message = 'Email settings updated successfully';
            
        } elseif ($current_tab === 'security') {
            // Handle security settings update
            $login_attempts = $_POST['login_attempts'] ?? '';
            $password_expiry = $_POST['password_expiry'] ?? '';
            $session_timeout = $_POST['session_timeout'] ?? '';
            
            // Validate inputs
            if (!is_numeric($login_attempts) || $login_attempts < 1) {
                throw new Exception('Login attempts must be a positive number');
            }
            
            if (!is_numeric($password_expiry) || $password_expiry < 0) {
                throw new Exception('Password expiry must be a positive number or zero');
            }
            
            if (!is_numeric($session_timeout) || $session_timeout < 1) {
                throw new Exception('Session timeout must be a positive number');
            }
            
            // In a real application, you would save these to database
            $success_message = 'Security settings updated successfully';
            
        } elseif ($current_tab === 'profile') {
            // Handle profile update
            $new_name = $_POST['name'] ?? '';
            $new_email = $_POST['email'] ?? '';
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            // Validate inputs
            if (empty($new_name) || empty($new_email)) {
                throw new Exception('Name and email are required');
            }
            
            if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address');
            }
            
            if (!empty($new_password)) {
                if (empty($current_password)) {
                    throw new Exception('Current password is required to change password');
                }
                
                if ($new_password !== $confirm_password) {
                    throw new Exception('New passwords do not match');
                }
                
                if (strlen($new_password) < 8) {
                    throw new Exception('Password must be at least 8 characters');
                }
                
                // In a real application, you would verify current password and update
                $success_message = 'Profile and password updated successfully';
            } else {
                $success_message = 'Profile updated successfully';
            }
            
            // Update session with new name/email
            $_SESSION['admin_name'] = $new_name;
            $_SESSION['admin_email'] = $new_email;
            $admin_name = $new_name;
            $admin_email = $new_email;
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Get timezones for dropdown
$timezones = DateTimeZone::listIdentifiers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - LineBus Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #4CAF50;
            --primary-dark: #388E3C;
            --primary-light: #C8E6C9;
            --secondary: #FF5722;
            --secondary-dark: #E64A19;
            --accent: #2196F3;
            --dark: #212121;
            --medium: #757575;
            --light: #FFFFFF;
            --light-bg: #F5F7FA;
            --border: #E0E0E0;
            --success: #4CAF50;
            --warning: #FFC107;
            --danger: #F44336;
            --info: #2196F3;
            --super-admin: #9C27B0;
            --admin: #2196F3;
            --manager: #FF9800;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark);
            line-height: 1.6;
        }

        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar - Same as other pages */
        .sidebar {
            background: var(--dark);
            color: var(--light);
            padding: 2rem 1rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .profile {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .profile-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
            border: 3px solid var(--primary);
        }

        .profile h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .profile p {
            color: var(--medium);
            font-size: 0.9rem;
        }

        .role-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .role-super-admin {
            background-color: rgba(156, 39, 176, 0.1);
            color: var(--super-admin);
        }

        .role-admin {
            background-color: rgba(33, 150, 243, 0.1);
            color: var(--admin);
        }

        .role-manager {
            background-color: rgba(255, 152, 0, 0.1);
            color: var(--manager);
        }

        .nav-menu {
            list-style: none;
        }

        .nav-menu li {
            margin-bottom: 0.5rem;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem 1rem;
            color: rgba(255,255,255,0.7);
            border-radius: 6px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255,255,255,0.1);
            color: var(--light);
        }

        .nav-menu a i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            padding: 2rem;
            overflow-x: hidden;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.8rem;
            color: var(--dark);
        }

        /* Settings Tabs */
        .settings-tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .tab {
            padding: 0.8rem 1.5rem;
            cursor: pointer;
            font-weight: 500;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab:hover {
            color: var(--primary);
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        /* Form Container */
        .form-container {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            max-width: 800px;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* Buttons */
        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--light-bg);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background-color: #e0e0e0;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success);
            border: 1px solid rgba(76, 175, 80, 0.2);
        }

        .alert-danger {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger);
            border: 1px solid rgba(244, 67, 54, 0.2);
        }

        /* Grid Layout for Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                height: auto;
                position: static;
                display: none;
            }
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .settings-tabs {
                overflow-x: auto;
                white-space: nowrap;
                padding-bottom: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1rem;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="profile">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_name ?? 'Admin') ?>&background=4CAF50&color=fff" 
                     alt="Profile" class="profile-img">
                <h3><?= htmlspecialchars($admin_name ?? 'Admin') ?></h3>
                <p><?= htmlspecialchars($admin_email ?? 'admin@example.com') ?></p>
                <span class="role-badge role-<?= str_replace('_', '-', $admin_role) ?>">
                    <?= ucfirst(str_replace('_', ' ', $admin_role)) ?>
                </span>
            </div>
            <ul class="nav-menu">
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_bookings.php"><i class="fas fa-ticket-alt"></i> Bookings</a></li>
                <li><a href="manage_buses.php"><i class="fas fa-bus"></i> Buses</a></li>
                <li><a href="manage_routes.php"><i class="fas fa-route"></i> Routes</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users"></i> Users</a></li>
                <?php if ($admin_role === 'super_admin'): ?>
                    <li><a href="admins.php"><i class="fas fa-user-shield"></i> Admins</a></li>
                <?php endif; ?>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="/busbooking/backend/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="header">
                <h1>Settings</h1>
                <div>
                    <span><?= date('l, F j, Y') ?></span>
                </div>
            </div>

            <div class="settings-tabs">
                <a href="?tab=general" class="tab <?= $current_tab === 'general' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i> General
                </a>
                <a href="?tab=email" class="tab <?= $current_tab === 'email' ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i> Email
                </a>
                <a href="?tab=security" class="tab <?= $current_tab === 'security' ? 'active' : '' ?>">
                    <i class="fas fa-shield-alt"></i> Security
                </a>
                <a href="?tab=profile" class="tab <?= $current_tab === 'profile' ? 'active' : '' ?>">
                    <i class="fas fa-user"></i> Profile
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <?php if ($current_tab === 'general'): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label for="site_name">Site Name</label>
                            <input type="text" id="site_name" name="site_name" class="form-control" 
                                   value="LineBus" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_email">Site Email</label>
                            <input type="email" id="site_email" name="site_email" class="form-control" 
                                   value="info@linebus.com" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="timezone">Timezone</label>
                            <select id="timezone" name="timezone" class="form-control" required>
                                <?php foreach ($timezones as $tz): ?>
                                    <option value="<?= $tz ?>" <?= $tz === 'Europe/Paris' ? 'selected' : '' ?>>
                                        <?= $tz ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                
                <?php elseif ($current_tab === 'email'): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label for="smtp_host">SMTP Host</label>
                            <input type="text" id="smtp_host" name="smtp_host" class="form-control" 
                                   value="smtp.example.com">
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="smtp_port">SMTP Port</label>
                                <input type="number" id="smtp_port" name="smtp_port" class="form-control" 
                                       value="587">
                            </div>
                            
                            <div class="form-group">
                                <label for="smtp_encryption">Encryption</label>
                                <select id="smtp_encryption" name="smtp_encryption" class="form-control">
                                    <option value="">None</option>
                                    <option value="tls" selected>TLS</option>
                                    <option value="ssl">SSL</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_username">SMTP Username</label>
                            <input type="text" id="smtp_username" name="smtp_username" class="form-control" 
                                   value="user@example.com">
                        </div>
                        
                        <div class="form-group">
                            <label for="smtp_password">SMTP Password</label>
                            <input type="password" id="smtp_password" name="smtp_password" class="form-control">
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                
                <?php elseif ($current_tab === 'security'): ?>
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="login_attempts">Max Login Attempts</label>
                                <input type="number" id="login_attempts" name="login_attempts" class="form-control" 
                                       value="5" min="1" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="password_expiry">Password Expiry (days)</label>
                                <input type="number" id="password_expiry" name="password_expiry" class="form-control" 
                                       value="90" min="0" required>
                                <small class="text-muted">0 = never expire</small>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="session_timeout">Session Timeout (minutes)</label>
                            <input type="number" id="session_timeout" name="session_timeout" class="form-control" 
                                   value="30" min="1" required>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Save Settings</button>
                        </div>
                    </form>
                
                <?php elseif ($current_tab === 'profile'): ?>
                    <form method="POST">
                        <div class="form-group">
                            <label for="name">Name</label>
                            <input type="text" id="name" name="name" class="form-control" 
                                   value="<?= htmlspecialchars($admin_name) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   value="<?= htmlspecialchars($admin_email) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control">
                            <small class="text-muted">Required only if changing password</small>
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" class="form-control">
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>
</body>
</html>
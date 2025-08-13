<?php
require_once __DIR__ . '/../config.php';



// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    

    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $errors[] = "Email and password are required.";
    }

    if (empty($errors)) {
        // 1. Try users table first
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            session_regenerate_id(true);

            // Update last login timestamp
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);

            // Redirect based on role
            if ($user['role'] === 'admin') {
                header("Location: ../backend/admin/admin_dashboard.php");
                exit;
            } else {
                header("Location: ../backend/users/dashboard.php");
                exit;
            }
        } else {
            // 2. Try admin table (for legacy/extra admins)
            $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ?");
            $stmt->execute([$email]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);

            // NOTE: If you store hashed passwords in admin table, use password_verify()
            if ($admin && ($password === $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                session_regenerate_id(true);
                header("Location: ../backend/admin/admin_dashboard.php");
                exit;
            } else {
                $errors[] = "Invalid credentials.";
            }
        }
    }
    
    // Store errors in session for display
    $_SESSION['login_errors'] = $errors;
    $_SESSION['login_form_data'] = ['email' => $_POST['email'] ?? ''];
    header("Location: login.php");
    exit;
}
?>

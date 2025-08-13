<?php
require __DIR__ . '/../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout action if admin was logged in
if (isset($_SESSION['admin_id'])) {
    try {
        $pdo->prepare("
            INSERT INTO admin_logs (user_id, action, ip_address, user_agent)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $_SESSION['admin_id'],
            'logout',
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);
    } catch (PDOException $e) {
        // Log error but don't prevent logout
        error_log("Failed to log admin logout: " . $e->getMessage());
    }
}

// Clear all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: /busbooking/backend/admin/login.php');
exit();
?>
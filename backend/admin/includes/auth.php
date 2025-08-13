<?php
// Point to your config.php file with the correct path
require_once __DIR__ . 'config.php'; // Adjust path as needed

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Get admin details using PDO
function getAdminDetails($pdo, $adminId) {
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
    $stmt->execute([$adminId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Log admin action using PDO
function logAdminAction($pdo, $action) {
    $adminId = $_SESSION['admin_id'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $pdo->prepare("INSERT INTO admin_logs (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
    $stmt->execute([$adminId, $action, $ip, $userAgent]);
}
?>
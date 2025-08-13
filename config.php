<?php
// Prevent direct access
defined('ROOT_PATH') or define('ROOT_PATH', dirname(__DIR__));

// Database configuration
defined('DB_HOST') or define('DB_HOST', 'localhost');
defined('DB_NAME') or define('DB_NAME', 'bus_booking');
defined('DB_USER') or define('DB_USER', 'root');
defined('DB_PASSWORD') or define('DB_PASSWORD', '');

// Security settings
defined('PASSWORD_COST') or define('PASSWORD_COST', 12);
defined('MAX_LOGIN_ATTEMPTS') or define('MAX_LOGIN_ATTEMPTS', 5);
defined('LOGIN_TIMEOUT') or define('LOGIN_TIMEOUT', 300); // 5 minutes

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'name' => 'LineBusSession',
        'cookie_lifetime' => 86400, // 1 day
        'cookie_httponly' => true,
        'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'use_strict_mode' => true,
        'cookie_samesite' => 'Strict'
    ]);
}

// PDO connection
if (!isset($pdo)) {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASSWORD);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("System error. Please try again later.");
    }
}

// Utility functions with existence checks
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('validate_email')) {
    function validate_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

if (!function_exists('password_strength')) {
    function password_strength($password) {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validate_csrf')) {
    function validate_csrf($token) {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }
}

if (!function_exists('verifyCSRFToken')) {
    function verifyCSRFToken($token) {
        return validate_csrf($token);
    }
}

if (!function_exists('record_login_attempt')) {
    function record_login_attempt($email, $user_id = null, $success = false) {
        global $pdo;
        $stmt = $pdo->prepare("INSERT INTO login_attempts (user_id, email, success, ip_address, user_agent) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $email,
            $success ? 1 : 0,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }
}

if (!function_exists('is_brute_force')) {
    function is_brute_force($email) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts 
                              WHERE email = ? AND success = 0 
                              AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)");
        $stmt->execute([$email, LOGIN_TIMEOUT]);
        return $stmt->fetchColumn() >= MAX_LOGIN_ATTEMPTS;
    }
}

// Error reporting (development only)
if (!defined('PRODUCTION')) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
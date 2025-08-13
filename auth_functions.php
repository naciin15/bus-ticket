<?php
// Prevent direct access
if (!defined('ROOT_PATH')) {
    die("Direct access not permitted");
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Secure password hashing
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against stored hash
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Log in a user using email and password
 */
function loginUser($email, $password, $mysqli) {
    $email = trim($email);
    $query = "SELECT id, username, email, password_hash, role FROM users WHERE email = ? AND is_active = 1 LIMIT 1";
    
    if ($stmt = $mysqli->prepare($query)) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (verifyPassword($password, $user['password_hash'])) {
                // Regenerate session ID for security
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['logged_in'] = true;

                // Update last login timestamp if column exists
                if ($mysqli->query("SHOW COLUMNS FROM users LIKE 'last_login'")->num_rows > 0) {
                    $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = ?";
                    if ($updateStmt = $mysqli->prepare($updateQuery)) {
                        $updateStmt->bind_param("i", $user['id']);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                }

                return true;
            }
        }

        $stmt->close();
    }

    return false;
}

/**
 * Log out the current user
 */
function logoutUser() {
    $_SESSION = [];

    // Destroy cookie if used
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Check if a user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Check if the logged-in user is an admin
 */
function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
?>

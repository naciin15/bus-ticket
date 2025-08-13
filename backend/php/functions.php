<?php
// Prevent direct access
if (!defined('ROOT_PATH')) {
    die("Direct access not permitted");
}

/**
 * Sanitize input data recursively.
 *
 * @param mixed $data
 * @return mixed
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to specified URL (relative to BASE_URL).
 *
 * @param string $url
 */
function redirect($url) {
    header("Location: " . BASE_URL . ltrim($url, '/'));
    exit();
}

/**
 * Display an error message (HTML).
 *
 * @param string $message
 * @return string
 */
function displayError($message) {
    return '<div class="error-message">' . sanitizeInput($message) . '</div>';
}

/**
 * Display a success message (HTML).
 *
 * @param string $message
 * @return string
 */
function displaySuccess($message) {
    return '<div class="success-message">' . sanitizeInput($message) . '</div>';
}

/**
 * Validate email format.
 *
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Log system errors to the database and to a file.
 *
 * @param string $errorType
 * @param string $details
 * @param int|null $userId
 */
function logError($errorType, $details, $userId = null) {
    global $mysqli;

    // Insert into database
    $query = "INSERT INTO system_errors (user_id, error_type, details) VALUES (?, ?, ?)";
    if ($stmt = $mysqli->prepare($query)) {
        $stmt->bind_param("iss", $userId, $errorType, $details);
        $stmt->execute();
        $stmt->close();
    }

    // Also log to file
    $logMessage = date('[Y-m-d H:i:s]') . " [$errorType] " . ($userId ? "User:$userId " : "") . $details . PHP_EOL;
    $logDir = ROOT_PATH . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    file_put_contents($logDir . '/errors.log', $logMessage, FILE_APPEND);
}
?>

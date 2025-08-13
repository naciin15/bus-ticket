<?php
require __DIR__ . '/../config.php';

// No need to call session_start() again — it's already called in config.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Sanitize and validate input
    $username = sanitize($_POST['username'] ?? '');
    $email = validate_email($_POST['email'] ?? '') ? $_POST['email'] : false;
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation checks
    if (!$username || !$email || !$first_name || !$last_name || !$password || !$confirm_password) {
        $errors[] = "All fields are required.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    if (!password_strength($password)) {
        $errors[] = "Password must be at least 8 characters and include a lowercase letter, uppercase letter, and number.";
    }

    // Check for existing username or email
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Username or email already exists.";
        }
    }

    // Insert user if all is good
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password_hash, first_name, last_name, role)
            VALUES (?, ?, ?, ?, ?, 'user')
        ");
        $stmt->execute([$username, $email, $password_hash, $first_name, $last_name]);

        $_SESSION['signup_success'] = true;
        header("Location: busbooking\frontend\login_signup.php");
        exit;
    } else {
        // Preserve form data and errors in session
        $_SESSION['signup_errors'] = $errors;
        $_SESSION['signup_form_data'] = [
            'username' => $username,
            'email' => $_POST['email'] ?? '',
            'first_name' => $first_name,
            'last_name' => $last_name
        ];
        header("Location: busbooking\frontend\login_signup.php");
        exit;
    }
}
?>

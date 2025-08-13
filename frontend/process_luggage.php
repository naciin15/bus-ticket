<?php
require 'C:/xampp/htdocs/busbooking/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /busbooking/frontend/login_signup.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /busbooking/frontend/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$booking_id = $_POST['booking_id'] ?? null;
$luggage_count = (int)($_POST['luggage_count'] ?? 0);
$notes = $_POST['notes'] ?? null;

// Calculate luggage fee
$luggage_fee = 0;
if ($luggage_count === 1) {
    $luggage_fee = 5.00;
} elseif ($luggage_count === 2) {
    $luggage_fee = 9.00;
} elseif ($luggage_count >= 3) {
    $luggage_fee = 12.00;
}

try {
    // Verify the booking belongs to the user
    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $_SESSION['error'] = "Booking not found or doesn't belong to you.";
        header('Location: /busbooking/frontend/dashboard.php');
        exit();
    }

    // Check if booking extras already exists
    $stmt = $pdo->prepare("SELECT id FROM booking_extras WHERE booking_id = ?");
    $stmt->execute([$booking_id]);
    $existing = $stmt->fetch();

    if ($existing) {
        // Update existing record
        $stmt = $pdo->prepare("
            UPDATE booking_extras 
            SET luggage_count = ?, luggage_fee = ?, notes = ?
            WHERE booking_id = ?
        ");
        $stmt->execute([$luggage_count, $luggage_fee, $notes, $booking_id]);
    } else {
        // Insert new record
        $stmt = $pdo->prepare("
            INSERT INTO booking_extras (booking_id, luggage_count, luggage_fee, notes)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$booking_id, $luggage_count, $luggage_fee, $notes]);
    }

    $_SESSION['success'] = "Luggage information updated successfully!";
    header('Location: /busbooking/frontend/dashboard.php');
    exit();

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while processing your request. Please try again.";
    header('Location: /busbooking/frontend/dashboard.php');
    exit();
}
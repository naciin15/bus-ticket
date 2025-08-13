<?php
require 'C:/xampp/htdocs/busbooking/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id'])) {
    header('Location: /busbooking/frontend/login_signup.php');
    exit();
}

$booking_id = $_GET['booking_id'] ?? null;

if (!$booking_id) {
    header('Location: /busbooking/frontend/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Verify the booking belongs to the user and is confirmed
    $stmt = $pdo->prepare("SELECT id FROM bookings WHERE id = ? AND user_id = ? AND status = 'confirmed'");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $_SESSION['error'] = "Booking not found, doesn't belong to you, or cannot be cancelled.";
        header('Location: /busbooking/frontend/dashboard.php');
        exit();
    }

    // Update booking status to cancelled
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$booking_id]);

    // Update payment status to refunded if paid
    $stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'refunded' WHERE id = ? AND payment_status = 'paid'");
    $stmt->execute([$booking_id]);

    $_SESSION['success'] = "Booking cancelled successfully!";
    header('Location: /busbooking/frontend/dashboard.php');
    exit();

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while cancelling your booking. Please try again.";
    header('Location: /busbooking/frontend/dashboard.php');
    exit();
}
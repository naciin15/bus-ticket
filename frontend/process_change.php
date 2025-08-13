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
$change_type = $_POST['change_type'] ?? null;
$notes = $_POST['notes'] ?? null;

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

    // Prepare update data based on change type
    $update_data = [
        'change_request' => $change_type,
        'change_request_status' => 'pending',
        'notes' => $notes
    ];

    if ($change_type === 'seat_change' || $change_type === 'both') {
        $update_data['new_seat_numbers'] = $_POST['new_seats'] ?? null;
    }

    if ($change_type === 'date_change' || $change_type === 'both') {
        $update_data['new_departure_date'] = $_POST['new_date'] ?? null;
        $update_data['new_departure_time'] = $_POST['new_time'] ?? null;
    }

    // Update the booking
    $update_fields = [];
    $update_values = [];
    foreach ($update_data as $field => $value) {
        $update_fields[] = "$field = ?";
        $update_values[] = $value;
    }
    $update_values[] = $booking_id;

    $sql = "UPDATE bookings SET " . implode(', ', $update_fields) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($update_values);

    $_SESSION['success'] = "Your change request has been submitted successfully!";
    header('Location: /busbooking/frontend/dashboard.php');
    exit();

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while processing your request. Please try again.";
    header('Location: /busbooking/frontend/dashboard.php');
    exit();
}
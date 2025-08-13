<?php
require __DIR__ . '/../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/frontend/login_signup.php");
    exit();
}

if (!isset($_GET['booking_id'])) {
    header("Location: " . BASE_URL . "/frontend/my_bookings.php");
    exit();
}

$booking_id = intval($_GET['booking_id']);
$user_id = $_SESSION['user_id'];

try {
    // Verify booking belongs to user
    $stmt = $pdo->prepare("SELECT bus_id, passengers FROM bookings WHERE id = ? AND user_id = ?");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        $_SESSION['error'] = "Booking not found or access denied";
        header("Location: " . BASE_URL . "/frontend/my_bookings.php");
        exit();
    }
    
    $pdo->beginTransaction();
    
    // Update booking status
    $stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$booking_id]);
    
    // Return seats to availability
    $stmt = $pdo->prepare("UPDATE buses SET available_seats = available_seats + ? WHERE id = ?");
    $stmt->execute([$booking['passengers'], $booking['bus_id']]);
    
    $pdo->commit();
    
    $_SESSION['success'] = "Booking cancelled successfully";
    header("Location: " . BASE_URL . "/frontend/my_bookings.php");
    exit();
    
} catch (PDOException $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Failed to cancel booking: " . $e->getMessage();
    header("Location: " . BASE_URL . "/frontend/my_bookings.php");
    exit();
}
?>
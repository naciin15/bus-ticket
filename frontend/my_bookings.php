<?php
require __DIR__ . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/frontend/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user's bookings with bus details
try {
    $stmt = $pdo->prepare("
        SELECT b.id AS booking_id, buses.bus_number, buses.operator, buses.departure_city, buses.arrival_city, 
               buses.departure_time, buses.arrival_time, buses.date, b.passengers, b.seat_numbers, b.total_price, 
               b.status, b.payment_status, b.booking_date
        FROM bookings b
        JOIN buses ON b.bus_id = buses.id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC
    ");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Bus Booking System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --light: #f8f9fa;
            --dark: #212529;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        h1 {
            color: var(--primary);
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-outline {
            border: 1px solid var(--primary);
            color: var(--primary);
            background: white;
        }
        
        .bookings-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .booking-card {
            padding: 20px;
            border-bottom: 1px solid #eee;
            transition: background 0.3s;
        }
        
        .booking-card:hover {
            background: #f9f9f9;
        }
        
        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .booking-id {
            font-weight: 500;
            color: #666;
        }
        
        .booking-status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-confirmed {
            background: #e3f2fd;
            color: var(--primary);
        }
        
        .status-cancelled {
            background: #ffebee;
            color: var(--danger);
        }
        
        .status-completed {
            background: #e8f5e9;
            color: var(--success);
        }
        
        .payment-status {
            font-size: 0.9rem;
        }
        
        .payment-paid {
            color: var(--success);
        }
        
        .payment-pending {
            color: var(--warning);
        }
        
        .payment-failed {
            color: var(--danger);
        }
        
        .booking-details {
            display: flex;
            gap: 30px;
            margin-bottom: 15px;
        }
        
        .route {
            flex: 1;
        }
        
        .departure, .arrival {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        
        .time {
            font-size: 1.1rem;
            font-weight: 500;
        }
        
        .city {
            font-weight: 500;
        }
        
        .duration {
            text-align: center;
            color: #666;
            margin: 10px 0;
            position: relative;
        }
        
        .duration::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #ddd;
            z-index: 1;
        }
        
        .duration span {
            background: white;
            position: relative;
            z-index: 2;
            padding: 0 10px;
        }
        
        .booking-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .meta-item {
            margin-right: 20px;
        }
        
        .meta-label {
            font-size: 0.9rem;
            color: #666;
        }
        
        .meta-value {
            font-weight: 500;
        }
        
        .booking-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .btn-view {
            background: var(--primary);
            color: white;
        }
        
        .btn-cancel {
            background: var(--danger);
            color: white;
        }
        
        .no-bookings {
            text-align: center;
            padding: 40px;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>My Bookings</h1>
            <div class="user-actions">
                <a href="<?= BASE_URL ?>/frontend/bus_listing.php" class="btn btn-outline">Book New Trip</a>
                <a href="<?= BASE_URL ?>/backend/logout.php" class="btn btn-primary">Logout</a>
            </div>
        </header>
        
        <div class="bookings-list">
            <?php if (empty($bookings)): ?>
                <div class="no-bookings">
                    <h3>You have no bookings yet</h3>
                    <p>Start by booking your first trip</p>
                    <a href="<?= BASE_URL ?>/frontend/bus_listing.php" class="btn btn-primary">Browse Buses</a>
                </div>
            <?php else: ?>
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card">
                        <div class="booking-header">
                            <div class="booking-id">Booking #<?= $booking['booking_id'] ?></div>
                            <div class="booking-status status-<?= $booking['status'] ?>">
                                <?= ucfirst($booking['status']) ?>
                                <span class="payment-status payment-<?= $booking['payment_status'] ?>">
                                    (<?= ucfirst($booking['payment_status']) ?>)
                                </span>
                            </div>
                        </div>
                        
                        <div class="booking-details">
                            <div class="route">
                                <div class="departure">
                                    <div class="time"><?= date('h:i A', strtotime($booking['departure_time'])) ?></div>
                                    <div class="city"><?= htmlspecialchars($booking['departure_city']) ?></div>
                                </div>
                                <div class="duration">
                                    <span><?= calculateDuration($booking['departure_time'], $booking['arrival_time']) ?></span>
                                </div>
                                <div class="arrival">
                                    <div class="time"><?= date('h:i A', strtotime($booking['arrival_time'])) ?></div>
                                    <div class="city"><?= htmlspecialchars($booking['arrival_city']) ?></div>
                                </div>
                            </div>
                            
                            <div class="booking-info">
                                <div class="meta-item">
                                    <div class="meta-label">Bus Number</div>
                                    <div class="meta-value"><?= htmlspecialchars($booking['bus_number']) ?></div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">Operator</div>
                                    <div class="meta-value"><?= htmlspecialchars($booking['operator']) ?></div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">Date</div>
                                    <div class="meta-value"><?= date('M j, Y', strtotime($booking['date'])) ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="booking-meta">
                            <div>
                                <div class="meta-item">
                                    <div class="meta-label">Passengers</div>
                                    <div class="meta-value"><?= $booking['passengers'] ?> (Seats: <?= $booking['seat_numbers'] ?>)</div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">Total Price</div>
                                    <div class="meta-value">"$"<?= number_format($booking['total_price'], 2) ?></div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">Booked On</div>
                                    <div class="meta-value"><?= date('M j, Y H:i', strtotime($booking['booking_date'])) ?></div>
                                </div>
                            </div>
                            
                            <div class="booking-actions">
                                <a href="<?= BASE_URL ?>/frontend/ticket.php?booking_id=<?= $booking['booking_id'] ?>" class="action-btn btn-view">View Ticket</a>
                                <?php if ($booking['status'] == 'confirmed'): ?>
                                    <a href="<?= BASE_URL ?>/backend/admin/bookings/cancel_booking.php?booking_id=<?= $booking['booking_id'] ?>" class="action-btn btn-cancel">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<?php
function calculateDuration($departure, $arrival) {
    $departure = new DateTime($departure);
    $arrival = new DateTime($arrival);
    $interval = $departure->diff($arrival);
    return $interval->format('%hh %Im');
}
?>
<?php
require __DIR__ . '/../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: /busbooking/frontend/login_signup.php');
    exit();
}

// Check if booking_id is provided
if (!isset($_GET['booking_id'])) {
    header('Location: /busbooking/frontend/bus_listing.php');
    exit();
}

$booking_id = $_GET['booking_id'];
$user_id = $_SESSION['user_id'];

try {
    // Fetch booking details with bus information
    $booking_stmt = $pdo->prepare("
        SELECT b.*, buses.bus_number, buses.departure_city, buses.arrival_city, 
               buses.departure_time, buses.arrival_time, buses.date
        FROM bookings b
        JOIN buses ON b.bus_id = buses.id
        WHERE b.id = ? AND b.user_id = ?
    ");
    $booking_stmt->execute([$booking_id, $user_id]);
    $booking = $booking_stmt->fetch();

    if (!$booking) {
        header('Location: /busbooking/frontend/bus_listing.php');
        exit();
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - LineBus</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Reuse the same CSS variables and base styles from previous pages */
        :root {
            --primary: #4CAF50;
            --primary-dark: #388E3C;
            --primary-light: #C8E6C9;
            --secondary: #FF5722;
            --secondary-dark: #E64A19;
            --accent: #2196F3;
            --dark: #212121;
            --medium: #757575;
            --light: #FFFFFF;
            --light-bg: #F5F7FA;
            --border: #E0E0E0;
            --success: #4CAF50;
            --warning: #FFC107;
            --danger: #F44336;
            --info: #2196F3;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--light-bg);
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .confirmation-card {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            text-align: center;
        }

        .confirmation-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: var(--primary);
            font-size: 2.5rem;
        }

        .confirmation-title {
            font-size: 1.8rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .confirmation-subtitle {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: var(--medium);
        }

        .booking-details {
            text-align: left;
            margin: 2rem 0;
            padding: 1.5rem;
            border-radius: 8px;
            background: var(--light-bg);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .detail-label {
            font-weight: 500;
            color: var(--medium);
        }

        .detail-value {
            font-weight: 600;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border: none;
            border-radius: 6px;
            background: var(--primary);
            color: var(--light);
            font-family: inherit;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-top: 1rem;
        }

        .btn:hover {
            background: var(--primary-dark);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
            margin-left: 1rem;
        }

        .btn-outline:hover {
            background: var(--primary-light);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="confirmation-card">
            <div class="confirmation-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1 class="confirmation-title">Booking Confirmed!</h1>
            <p class="confirmation-subtitle">Your booking #<?= $booking_id ?> has been successfully confirmed</p>
            
            <div class="booking-details">
                <div class="detail-row">
                    <span class="detail-label">Route:</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($booking['departure_city']) ?> → <?= htmlspecialchars($booking['arrival_city']) ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Travel Date:</span>
                    <span class="detail-value">
                        <?= date('D, M j, Y', strtotime($booking['date'])) ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Departure Time:</span>
                    <span class="detail-value">
                        <?= date('H:i', strtotime($booking['departure_time'])) ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Bus Number:</span>
                    <span class="detail-value">
                        <?= htmlspecialchars($booking['bus_number']) ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Passengers:</span>
                    <span class="detail-value">
                        <?= $booking['passengers'] ?>
                        <?php if (!empty($booking['seat_numbers'])): ?>
                            (Seats: <?= $booking['seat_numbers'] ?>)
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Total Price:</span>
                    <span class="detail-value">
                        €<?= number_format($booking['total_price'], 2) ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Booking Date:</span>
                    <span class="detail-value">
                        <?= date('M j, Y H:i', strtotime($booking['booking_date'])) ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value" style="color: <?= 
                        $booking['status'] == 'confirmed' ? 'var(--success)' : 
                        ($booking['status'] == 'cancelled' ? 'var(--danger)' : 'var(--info)') 
                    ?>">
                        <?= ucfirst($booking['status']) ?>
                    </span>
                </div>
            </div>
            
            <div>
                <a href="/busbooking/frontend/dashboard.php" class="btn">Go to Dashboard</a>
                <a href="/busbooking/backend/admin/bookings/ticket.php?booking_id=<?= $booking_id ?>" class="btn btn-outline">
                    <i class="fas fa-ticket-alt"></i> View Ticket
                </a>
            </div>
        </div>
    </div>
</body>
</html>
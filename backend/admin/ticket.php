<?php
require __DIR__ . '/../../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/frontend/login_signup.php");
    exit();
}

if (!isset($_GET['booking_id'])) {
    die("No booking specified");
}

$booking_id = intval($_GET['booking_id']);
$user_id = $_SESSION['user_id'];

// Fetch booking details
try {
    $stmt = $pdo->prepare("
        SELECT b.*, buses.bus_number, buses.operator, buses.departure_city, buses.arrival_city, 
               buses.departure_time, buses.arrival_time, buses.date, users.full_name, users.phone
        FROM bookings b
        JOIN buses ON b.bus_id = buses.id
        JOIN users ON b.user_id = users.id
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        die("Booking not found or access denied");
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
    <title>Ticket - Booking #<?= $booking['id'] ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --success: #4cc9f0;
            --danger: #f72585;
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
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        
        .ticket {
            max-width: 600px;
            width: 100%;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .ticket-header {
            background: var(--primary);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .ticket-title {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .ticket-subtitle {
            font-size: 1rem;
            opacity: 0.8;
        }
        
        .ticket-body {
            padding: 25px;
        }
        
        .ticket-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .info-group {
            flex: 1;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 500;
            font-size: 1.1rem;
        }
        
        .ticket-route {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            position: relative;
        }
        
        .route-stop {
            text-align: center;
            flex: 1;
        }
        
        .stop-time {
            font-size: 1.3rem;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .stop-city {
            font-weight: 500;
        }
        
        .route-line {
            flex: 2;
            height: 2px;
            background: var(--primary);
            position: relative;
        }
        
        .route-line::before {
            content: "";
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: var(--primary);
        }
        
        .route-line::after {
            content: "";
            position: absolute;
            top: 50%;
            right: 0;
            transform: translateY(-50%);
            width: 15px;
            height: 15px;
            border-radius: 50%;
            background: var(--primary);
        }
        
        .ticket-details {
            margin-bottom: 30px;
        }
        
        .details-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px dashed #ddd;
        }
        
        .details-label {
            font-weight: 500;
        }
        
        .ticket-qr {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            border: 1px dashed #ddd;
            border-radius: 5px;
        }
        
        .ticket-footer {
            text-align: center;
            padding: 15px;
            background: #f9f9f9;
            font-size: 0.9rem;
            color: #666;
        }
        
        .print-btn {
            display: block;
            margin: 20px auto 0;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 5px;
            font-family: inherit;
            font-weight: 500;
            cursor: pointer;
        }
        
        @media print {
            body {
                background: white;
            }
            .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="ticket-header">
            <div class="ticket-title">Bus Ticket</div>
            <div class="ticket-subtitle">Booking #<?= $booking['id'] ?></div>
        </div>
        
        <div class="ticket-body">
            <div class="ticket-info">
                <div class="info-group">
                    <div class="info-label">Passenger Name</div>
                    <div class="info-value"><?= htmlspecialchars($booking['full_name']) ?></div>
                </div>
                <div class="info-group">
                    <div class="info-label">Booking Status</div>
                    <div class="info-value" style="color: <?= $booking['status'] == 'confirmed' ? 'var(--primary)' : ($booking['status'] == 'cancelled' ? 'var(--danger)' : 'var(--success)') ?>">
                        <?= ucfirst($booking['status']) ?>
                    </div>
                </div>
            </div>
            
            <div class="ticket-route">
                <div class="route-stop">
                    <div class="stop-time"><?= date('h:i A', strtotime($booking['departure_time'])) ?></div>
                    <div class="stop-city"><?= htmlspecialchars($booking['departure_city']) ?></div>
                </div>
                
                <div class="route-line"></div>
                
                <div class="route-stop">
                    <div class="stop-time"><?= date('h:i A', strtotime($booking['arrival_time'])) ?></div>
                    <div class="stop-city"><?= htmlspecialchars($booking['arrival_city']) ?></div>
                </div>
            </div>
            
            <div class="ticket-details">
                <div class="details-row">
                    <span class="details-label">Bus Number</span>
                    <span><?= htmlspecialchars($booking['bus_number']) ?></span>
                </div>
                <div class="details-row">
                    <span class="details-label">Operator</span>
                    <span><?= htmlspecialchars($booking['operator']) ?></span>
                </div>
                <div class="details-row">
                    <span class="details-label">Date of Journey</span>
                    <span><?= date('F j, Y', strtotime($booking['date'])) ?></span>
                </div>
                <div class="details-row">
                    <span class="details-label">Passengers</span>
                    <span><?= $booking['passengers'] ?></span>
                </div>
                <div class="details-row">
                    <span class="details-label">Seat Numbers</span>
                    <span><?= $booking['seat_numbers'] ?></span>
                </div>
                <div class="details-row">
                    <span class="details-label">Total Fare</span>
                    <span>₹<?= number_format($booking['total_price'], 2) ?></span>
                </div>
                <div class="details-row">
                    <span class="details-label">Payment Status</span>
                    <span style="color: <?= $booking['payment_status'] == 'paid' ? 'var(--success)' : ($booking['payment_status'] == 'failed' ? 'var(--danger)' : 'var(--warning)') ?>">
                        <?= ucfirst($booking['payment_status']) ?>
                    </span>
                </div>
            </div>
            
            <div class="ticket-qr">
                <!-- Replace with actual QR code generation in production -->
                <div style="width: 150px; height: 150px; margin: 0 auto; background: #eee; display: flex; align-items: center; justify-content: center;">
                    QR Code
                </div>
                <p>Scan this code at boarding</p>
            </div>
        </div>
        
        <div class="ticket-footer">
            <p>Thank you for choosing our service. Have a safe journey!</p>
            <p>For any queries, contact: support@busbookingsystem.com</p>
        </div>
    </div>
    
    <button class="print-btn" onclick="window.print()">Print Ticket</button>
</body>
</html>
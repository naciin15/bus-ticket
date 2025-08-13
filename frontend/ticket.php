
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
    // Fetch booking details with bus and user info
    $stmt = $pdo->prepare("
        SELECT b.*, 
               buses.bus_number, buses.operator, buses.departure_city, buses.arrival_city, 
               buses.departure_time, buses.arrival_time, buses.date,
               u.first_name, u.last_name, u.email, u.phone,
               be.luggage_count, be.luggage_fee
        FROM bookings b
        JOIN buses ON b.bus_id = buses.id
        JOIN users u ON b.user_id = u.id
        LEFT JOIN booking_extras be ON b.id = be.booking_id
        WHERE b.id = ? AND b.user_id = ?
    ");
    $stmt->execute([$booking_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        $_SESSION['error'] = "Ticket not found or doesn't belong to you.";
        header('Location: /busbooking/frontend/dashboard.php');
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
    <title>Your Ticket - LineBus</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 2rem;
        }
        
        .ticket-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .ticket-header {
            background: #4CAF50;
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .ticket-header h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .ticket-body {
            padding: 2rem;
        }
        
        .ticket-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .info-section h2 {
            color: #4CAF50;
            margin-top: 0;
            font-size: 1.2rem;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 0.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.8rem;
        }
        
        .info-label {
            font-weight: 500;
            color: #555;
        }
        
        .info-value {
            font-weight: 600;
        }
        
        .route-display {
            background: #f9f9f9;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .cities {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .city {
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .arrow {
            font-size: 2rem;
            color: #4CAF50;
        }
        
        .details {
            display: flex;
            justify-content: space-between;
            margin-top: 1rem;
        }
        
        .detail-item {
            text-align: center;
        }
        
        .detail-label {
            font-size: 0.9rem;
            color: #777;
        }
        
        .detail-value {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .barcode {
            text-align: center;
            margin-top: 2rem;
            padding: 1rem;
            background: white;
            border: 1px dashed #ddd;
        }
        
        .print-btn {
            display: block;
            width: 100%;
            max-width: 200px;
            margin: 2rem auto 0;
            padding: 0.8rem;
            background: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .print-btn {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .ticket-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="ticket-container">
        <div class="ticket-header">
            <h1>LineBus E-Ticket</h1>
            <p>Booking Reference: #<?= $booking['id'] ?></p>
        </div>
        
        <div class="ticket-body">
            <div class="ticket-info">
                <div class="info-section">
                    <h2>Journey Details</h2>
                    <div class="info-row">
                        <span class="info-label">Bus Number:</span>
                        <span class="info-value"><?= htmlspecialchars($booking['bus_number']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Operator:</span>
                        <span class="info-value"><?= htmlspecialchars($booking['operator']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Date:</span>
                        <span class="info-value"><?= date('M j, Y', strtotime($booking['date'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Departure:</span>
                        <span class="info-value"><?= htmlspecialchars($booking['departure_time']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Arrival:</span>
                        <span class="info-value"><?= htmlspecialchars($booking['arrival_time']) ?></span>
                    </div>
                    <?php if ($booking['luggage_count'] > 0): ?>
                    <div class="info-row">
                        <span class="info-label">Luggage:</span>
                        <span class="info-value"><?= $booking['luggage_count'] ?> (€<?= number_format($booking['luggage_fee'], 2) ?>)</span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="info-section">
                    <h2>Passenger Details</h2>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?= htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?= htmlspecialchars($booking['email']) ?></span>
                    </div>
                    <?php if ($booking['phone']): ?>
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><?= htmlspecialchars($booking['phone']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Passengers:</span>
                        <span class="info-value"><?= $booking['passengers'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Seats:</span>
                        <span class="info-value"><?= $booking['seat_numbers'] ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total Paid:</span>
                        <span class="info-value">€<?= number_format($booking['total_price'], 2) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="route-display">
                <div class="cities">
                    <div class="city"><?= htmlspecialchars($booking['departure_city']) ?></div>
                    <div class="arrow">→</div>
                    <div class="city"><?= htmlspecialchars($booking['arrival_city']) ?></div>
                </div>
                
                <div class="details">
                    <div class="detail-item">
                        <div class="detail-label">Departure</div>
                        <div class="detail-value"><?= htmlspecialchars($booking['departure_time']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Arrival</div>
                        <div class="detail-value"><?= htmlspecialchars($booking['arrival_time']) ?></div>
                    </div>
                    <div class="detail-item">
                        <div class="detail-label">Date</div>
                        <div class="detail-value"><?= date('M j, Y', strtotime($booking['date'])) ?></div>
                    </div>
                </div>
            </div>
            
            <div class="barcode">
                <p>Scan this code at boarding</p>
                <div id="barcode"></div>
                <p><?= strtoupper(uniqid('LB')) ?></p>
            </div>
            
            <div class="info-section">
                <h2>Important Information</h2>
                <ul>
                    <li>Please arrive at least 30 minutes before departure</li>
                    <li>Have your ID and this ticket ready for inspection</li>
                    <li>Changes to bookings may incur additional fees</li>
                    <li>For assistance, contact support@linebus.com</li>
                </ul>
            </div>
            
            <button class="print-btn" onclick="window.print()">Print Ticket</button>
        </div>
    </div>
    
    <script>
        // Simple barcode generation (for demo purposes)
        const barcode = document.getElementById('barcode');
        for (let i = 0; i < 20; i++) {
            const bar = document.createElement('div');
            bar.style.display = 'inline-block';
            bar.style.height = '40px';
            bar.style.width = Math.floor(Math.random() * 4) + 1 + 'px';
            bar.style.backgroundColor = 'black';
            bar.style.margin = '0 1px';
            barcode.appendChild(bar);
        }
    </script>
</body>
</html>
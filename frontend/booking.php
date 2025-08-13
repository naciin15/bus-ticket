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

$user_id = $_SESSION['user_id'];

// Check if bus_id is provided
if (!isset($_GET['bus_id'])) {
    header('Location: /busbooking/frontend/bus_listing.php');
    exit();
}

$bus_id = $_GET['bus_id'];

try {
    // Fetch bus details
    $bus_stmt = $pdo->prepare("SELECT * FROM buses WHERE id = ?");
    $bus_stmt->execute([$bus_id]);
    $bus = $bus_stmt->fetch();

    if (!$bus) {
        header('Location: /busbooking/frontend/bus_listing.php');
        exit();
    }

    // Fetch user details
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $passengers = $_POST['passengers'] ?? 1;
        $seat_numbers = $_POST['seat_numbers'] ?? '';
        $payment_method = $_POST['payment_method'] ?? 'cash';
        
        // Validate inputs
        $errors = [];
        
        if ($passengers < 1 || $passengers > 10) {
            $errors[] = "Number of passengers must be between 1 and 10";
        }
        
        if ($bus['available_seats'] < $passengers) {
            $errors[] = "Not enough seats available";
        }
        
        if (empty($errors)) {
            // Calculate total price
            $total_price = $bus['price'] * $passengers;
            
            // Start transaction
            $pdo->beginTransaction();
            
            try {
                // Create booking
                $booking_stmt = $pdo->prepare("
                    INSERT INTO bookings (
                        bus_id, 
                        user_id, 
                        passengers, 
                        total_price, 
                        booking_date, 
                        status, 
                        payment_status, 
                        seat_numbers,
                        payment_method
                    ) VALUES (?, ?, ?, ?, NOW(), 'confirmed', 'pending', ?, ?)
                ");
                
                $booking_stmt->execute([
                    $bus_id,
                    $user_id,
                    $passengers,
                    $total_price,
                    $seat_numbers,
                    $payment_method
                ]);
                
                // Update available seats
                $update_stmt = $pdo->prepare("
                    UPDATE buses 
                    SET available_seats = available_seats - ? 
                    WHERE id = ?
                ");
                $update_stmt->execute([$passengers, $bus_id]);
                
                // Commit transaction
                $pdo->commit();
                
                // Redirect to booking confirmation
                $booking_id = $pdo->lastInsertId();
                header("Location: booking_confirmation.php?booking_id=$booking_id");
                exit();
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $errors[] = "Booking failed: " . $e->getMessage();
            }
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}


function calculateDuration($departure, $arrival) {
    $depTime = new DateTime($departure);
    $arrTime = new DateTime($arrival);

    // If arrival is on the next day
    if ($arrTime < $depTime) {
        $arrTime->modify('+1 day');
    }

    $interval = $depTime->diff($arrTime);
    return $interval->h . 'h ' . $interval->i . 'm';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Bus - LineBus</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Reuse the same CSS variables and base styles from bus_listing.php */
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .booking-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .booking-summary, .booking-form {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .section-title {
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .bus-info {
            margin-bottom: 2rem;
        }

        .route {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .city {
            font-weight: 600;
            font-size: 1.2rem;
        }

        .time {
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0.5rem 0;
        }

        .date {
            color: var(--medium);
            font-size: 0.9rem;
        }

        .duration {
            text-align: center;
            color: var(--medium);
            margin: 1rem 0;
        }

        .bus-number {
            font-weight: 600;
            color: var(--primary);
        }

        .price-summary {
            margin-top: 2rem;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .total-price {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: inherit;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
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
        }

        .btn:hover {
            background: var(--primary-dark);
        }

        .error {
            color: var(--danger);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .booking-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="booking-container">
            <div class="booking-summary">
                <h2 class="section-title">Trip Summary</h2>
                
                <div class="bus-info">
                    <div class="route">
                        <div>
                            <div class="city"><?= htmlspecialchars($bus['departure_city']) ?></div>
                            <div class="time"><?= date('H:i', strtotime($bus['departure_time'])) ?></div>
                            <div class="date"><?= date('D, M j, Y', strtotime($bus['date'])) ?></div>
                        </div>
                        
                        <div class="duration">
                            <div><?= calculateDuration($bus['departure_time'], $bus['arrival_time']) ?></div>
                            <div><i class="fas fa-long-arrow-alt-right"></i></div>
                        </div>
                        
                        <div>
                            <div class="city"><?= htmlspecialchars($bus['arrival_city']) ?></div>
                            <div class="time"><?= date('H:i', strtotime($bus['arrival_time'])) ?></div>
                            <div class="date"><?= date('D, M j, Y', strtotime($bus['date'])) ?></div>
                        </div>
                    </div>
                    
                    <div>
                        <span class="bus-number"><?= htmlspecialchars($bus['bus_number']) ?></span>
                        <span><?= htmlspecialchars($bus['operator']) ?></span>
                    </div>
                </div>
                
                <div class="price-summary">
                    <h3 class="section-title">Price Details</h3>
                    
                    <div class="price-row">
                        <span>Base Fare (x<span id="passenger-count">1</span>)</span>
                        <span>€<span id="base-price"><?= number_format($bus['price'], 2) ?></span></span>
                    </div>
                    
                    <div class="price-row">
                        <span>Taxes & Fees</span>
                        <span>€0.00</span>
                    </div>
                    
                    <div class="price-row total-price">
                        <span>Total</span>
                        <span>€<span id="total-price"><?= number_format($bus['price'], 2) ?></span></span>
                    </div>
                </div>
            </div>
            
            <div class="booking-form">
                <h2 class="section-title">Passenger Details</h2>
                
                <?php if (!empty($errors)): ?>
                    <div class="error">
                        <?php foreach ($errors as $error): ?>
                            <p><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="booking.php?bus_id=<?= $bus_id ?>">
                    <div class="form-group">
                        <label for="passengers">Number of Passengers</label>
                        <select id="passengers" name="passengers" class="form-control" required>
                            <?php for ($i = 1; $i <= min(10, $bus['available_seats']); $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?> passenger<?= $i > 1 ? 's' : '' ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="seat_numbers">Preferred Seat Numbers (optional)</label>
                        <input type="text" id="seat_numbers" name="seat_numbers" class="form-control" 
                               placeholder="e.g., 12, 13, 14">
                        <small>Separate multiple seats with commas</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select id="payment_method" name="payment_method" class="form-control" required>
                            <option value="cash">Cash</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="paypal">PayPal</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn">Confirm Booking</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Update price when passengers change
        document.getElementById('passengers').addEventListener('change', function() {
            const passengers = this.value;
            const basePrice = <?= $bus['price'] ?>;
            
            document.getElementById('passenger-count').textContent = passengers;
            document.getElementById('base-price').textContent = (basePrice * passengers).toFixed(2);
            document.getElementById('total-price').textContent = (basePrice * passengers).toFixed(2);
        });
    </script>
</body>
</html>
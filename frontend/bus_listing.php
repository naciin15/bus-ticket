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

// Get search parameters if any
$departure_city = $_GET['departure_city'] ?? '';
$arrival_city = $_GET['arrival_city'] ?? '';
$travel_date = $_GET['date'] ?? date('Y-m-d');

try {
    // Fetch available buses based on search criteria
    $sql = "SELECT * FROM buses 
            WHERE departure_city LIKE :departure 
            AND arrival_city LIKE :arrival 
            AND date >= :date 
            AND status = 'active'
            AND available_seats > 0
            ORDER BY departure_time ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':departure' => "%$departure_city%",
        ':arrival' => "%$arrival_city%",
        ':date' => $travel_date
    ]);
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Bus - LineBus</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
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

        .search-box {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .search-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
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
            align-self: flex-end;
        }

        .btn:hover {
            background: var(--primary-dark);
        }

        .bus-list {
            display: grid;
            gap: 1.5rem;
        }

        .bus-card {
            background: var(--light);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .bus-card:hover {
            transform: translateY(-5px);
        }

        .bus-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .bus-number {
            font-weight: 600;
            color: var(--primary);
        }

        .operator {
            color: var(--medium);
            font-size: 0.9rem;
        }

        .bus-details {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .route {
            text-align: center;
        }

        .city {
            font-weight: 600;
            font-size: 1.2rem;
        }

        .time {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0.5rem 0;
        }

        .date {
            color: var(--medium);
            font-size: 0.9rem;
        }

        .duration {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            color: var(--medium);
        }

        .duration-line {
            width: 100%;
            height: 1px;
            background: var(--border);
            position: relative;
            margin: 0.5rem 0;
        }

        .duration-line::after {
            content: '';
            position: absolute;
            top: -4px;
            left: 50%;
            transform: translateX(-50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
        }

        .bus-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .price {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }

        .seats {
            color: var(--medium);
        }

        .book-btn {
            padding: 0.8rem 1.5rem;
            background: var(--primary);
            color: var(--light);
            border: none;
            border-radius: 6px;
            font-family: inherit;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .book-btn:hover {
            background: var(--primary-dark);
        }

        .no-results {
            text-align: center;
            padding: 3rem;
            color: var(--medium);
        }

        .no-results i {
            font-size: 3rem;
            color: var(--primary-light);
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .bus-details {
                grid-template-columns: 1fr;
            }
            
            .duration {
                flex-direction: row;
                justify-content: space-between;
                margin: 1rem 0;
            }
            
            .duration-line {
                width: 1px;
                height: 40px;
                margin: 0 1rem;
            }
            
            .duration-line::after {
                top: 50%;
                left: -4px;
                transform: translateY(-50%);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="search-box">
            <form class="search-form" method="GET" action="bus_listing.php">
                <div class="form-group">
                    <label for="departure_city">From</label>
                    <input type="text" id="departure_city" name="departure_city" class="form-control" 
                           value="<?= htmlspecialchars($departure_city) ?>" placeholder="Departure city">
                </div>
                <div class="form-group">
                    <label for="arrival_city">To</label>
                    <input type="text" id="arrival_city" name="arrival_city" class="form-control" 
                           value="<?= htmlspecialchars($arrival_city) ?>" placeholder="Arrival city">
                </div>
                <div class="form-group">
                    <label for="date">Travel Date</label>
                    <input type="date" id="date" name="date" class="form-control" 
                           value="<?= htmlspecialchars($travel_date) ?>" min="<?= date('Y-m-d') ?>">
                </div>
                <button type="submit" class="btn">Search Buses</button>
            </form>
        </div>

        <div class="bus-list">
            <?php if (empty($buses)): ?>
                <div class="no-results">
                    <i class="fas fa-bus"></i>
                    <h3>No buses found</h3>
                    <p>Try changing your search criteria</p>
                </div>
            <?php else: ?>
                <?php foreach ($buses as $bus): ?>
                    <div class="bus-card">
                        <div class="bus-header">
                            <div>
                                <span class="bus-number"><?= htmlspecialchars($bus['bus_number']) ?></span>
                                <span class="operator"><?= htmlspecialchars($bus['operator']) ?></span>
                            </div>
                            <div class="seats">
                                <?= $bus['available_seats'] ?> of <?= $bus['total_seats'] ?> seats available
                            </div>
                        </div>
                        
                        <div class="bus-details">
                            <div class="route">
                                <div class="city"><?= htmlspecialchars($bus['departure_city']) ?></div>
                                <div class="time"><?= date('H:i', strtotime($bus['departure_time'])) ?></div>
                                <div class="date"><?= date('D, M j', strtotime($bus['date'])) ?></div>
                            </div>
                            
                            <div class="duration">
                                <div><?= calculateDuration($bus['departure_time'], $bus['arrival_time']) ?></div>
                                <div class="duration-line"></div>
                                <div>Direct</div>
                            </div>
                            
                            <div class="route">
                                <div class="city"><?= htmlspecialchars($bus['arrival_city']) ?></div>
                                <div class="time"><?= date('H:i', strtotime($bus['arrival_time'])) ?></div>
                                <div class="date"><?= date('D, M j', strtotime($bus['date'])) ?></div>
                            </div>
                        </div>
                        
                        <div class="bus-footer">
                            <div class="price">€<?= number_format($bus['price'], 2) ?></div>
                            <a href="booking.php?bus_id=<?= $bus['id'] ?>" class="book-btn">Book Now</a>
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
    $departure_time = new DateTime($departure);
    $arrival_time = new DateTime($arrival);
    
    // Handle overnight trips
    if ($arrival_time < $departure_time) {
        $arrival_time->modify('+1 day');
    }
    
    $interval = $departure_time->diff($arrival_time);
    
    if ($interval->h > 0 && $interval->i > 0) {
        return $interval->h . 'h ' . $interval->i . 'm';
    } elseif ($interval->h > 0) {
        return $interval->h . 'h';
    } else {
        return $interval->i . 'm';
    }
}
?>
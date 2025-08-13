<?php
require 'C:/xampp/htdocs/busbooking/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if required columns exist in bookings table
try {
    $columns = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'change_request'")->fetch();
    
    if (!$columns) {
        // Add missing columns
        $pdo->exec("ALTER TABLE `bookings` 
          ADD COLUMN `change_request` enum('none','seat_change','date_change','both') DEFAULT 'none',
          ADD COLUMN `new_departure_date` date DEFAULT NULL,
          ADD COLUMN `new_departure_time` time DEFAULT NULL,
          ADD COLUMN `new_seat_numbers` varchar(255) DEFAULT NULL,
          ADD COLUMN `change_request_status` enum('pending','approved','rejected') DEFAULT 'pending'");
    }
} catch (PDOException $e) {
    die("Database error during column check: " . $e->getMessage());
}

if (!isset($_SESSION['user_id'])) {
    header('Location: /busbooking/frontend/login_signup.php');
    exit();
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch user details
    $user_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->execute([$user_id]);
    $user = $user_stmt->fetch();

    // Fetch bookings with all fields
    $bookings_stmt = $pdo->prepare("
        SELECT b.id AS booking_id, buses.bus_number, buses.departure_city, 
               buses.arrival_city, buses.departure_time, buses.arrival_time, buses.date, 
               b.passengers, b.seat_numbers, b.total_price, b.status, b.payment_status, 
               b.booking_date, b.payment_method,
               COALESCE(b.change_request, 'none') AS change_request,
               b.new_departure_date, b.new_departure_time, b.new_seat_numbers,
               COALESCE(b.change_request_status, 'pending') AS change_request_status,
               COALESCE(be.luggage_count, 0) AS luggage_count,
               COALESCE(be.luggage_fee, 0) AS luggage_fee
        FROM bookings b
        JOIN buses ON b.bus_id = buses.id
        LEFT JOIN booking_extras be ON b.id = be.booking_id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC
    ");
    $bookings_stmt->execute([$user_id]);
    $bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Count bookings by status
    $status_counts = [
        'confirmed' => 0,
        'cancelled' => 0,
        'completed' => 0
    ];
    
    foreach ($bookings as $booking) {
        $status_counts[$booking['status']]++;
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
    <title>My Dashboard - LineBus</title>
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

        .dashboard-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            background: var(--dark);
            color: var(--light);
            padding: 2rem 1rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .profile {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .profile-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 1rem;
            border: 3px solid var(--primary);
        }

        .profile h3 {
            font-size: 1.2rem;
            margin-bottom: 0.5rem;
        }

        .profile p {
            color: var(--medium);
            font-size: 0.9rem;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-menu li {
            margin-bottom: 0.5rem;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem 1rem;
            color: rgba(255,255,255,0.7);
            border-radius: 6px;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255,255,255,0.1);
            color: var(--light);
        }

        .nav-menu a i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            padding: 2rem;
            overflow-x: hidden;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header h1 {
            font-size: 1.8rem;
            color: var(--dark);
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--light);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 0.9rem;
            color: var(--medium);
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .stat-card .value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-card .change {
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .stat-card .change.up {
            color: var(--success);
        }

        .stat-card .change.down {
            color: var(--danger);
        }

        .bookings-container {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-header h2 {
            font-size: 1.5rem;
            color: var(--dark);
        }

        .booking-filters {
            display: flex;
            gap: 0.8rem;
        }

        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            border: none;
            background: var(--light-bg);
            color: var(--medium);
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .filter-btn.active {
            background: var(--primary);
            color: var(--light);
        }

        .bookings-table {
            width: 100%;
            border-collapse: collapse;
        }

        .bookings-table th {
            text-align: left;
            padding: 1rem;
            background: var(--light-bg);
            color: var(--medium);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .bookings-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .booking-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .booking-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--primary-light);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }

        .route {
            font-weight: 500;
        }

        .route-details {
            font-size: 0.9rem;
            color: var(--medium);
            margin-top: 0.3rem;
        }

        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-confirmed {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
        }

        .status-cancelled {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
        }

        .status-completed {
            background: rgba(33, 150, 243, 0.1);
            color: var(--info);
        }

        .payment-status {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .payment-paid {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
        }

        .payment-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .payment-failed {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
        }

        .request-status {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .request-pending {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
        }

        .request-approved {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
        }

        .request-rejected {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
        }

        .action-btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .action-btn.view {
            background: var(--primary-light);
            color: var(--primary);
        }

        .action-btn.cancel {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
        }

        .action-btn.change {
            background: rgba(33, 150, 243, 0.1);
            color: var(--accent);
        }

        .action-btn.luggage {
            background: rgba(156, 39, 176, 0.1);
            color: #9C27B0;
        }

        .action-btn:hover {
            opacity: 0.8;
        }

        .no-bookings {
            text-align: center;
            padding: 3rem;
            color: var(--medium);
        }

        .no-bookings i {
            font-size: 3rem;
            color: var(--primary-light);
            margin-bottom: 1rem;
        }

        .no-bookings a {
            display: inline-block;
            margin-top: 1.5rem;
            padding: 0.8rem 1.5rem;
            background: var(--primary);
            color: var(--light);
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .no-bookings a:hover {
            background: var(--primary-dark);
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--dark);
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--medium);
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-group input, .form-group select {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-family: inherit;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-secondary {
            background-color: var(--light-bg);
            color: var(--dark);
        }

        /* Luggage info */
        .luggage-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.9rem;
        }

        .luggage-info i {
            color: var(--medium);
        }

        @media (max-width: 992px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                height: auto;
                position: static;
                display: none;
            }

            .nav-menu {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .nav-menu li {
                margin-bottom: 0;
            }

            .nav-menu a {
                padding: 0.5rem 0.8rem;
            }
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr 1fr;
            }

            .booking-filters {
                flex-wrap: wrap;
            }

            .bookings-table td {
                padding: 0.8rem;
            }

            .action-btn {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .bookings-table {
                display: block;
                overflow-x: auto;
            }

            .modal-content {
                width: 95%;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="profile">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($user['first_name'] . '+' . $user['last_name'] ?? $user['username']) ?>&background=4CAF50&color=fff" 
                     alt="Profile" class="profile-img">
                <h3><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] ?? $user['username']) ?></h3>
                <p><?= htmlspecialchars($user['email']) ?></p>
            </div>
            <ul class="nav-menu">
                <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="/busbooking/frontend/bus_listing.php"><i class="fas fa-bus"></i> Book a Bus</a></li>
                <li><a href="/busbooking/frontend/profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                <li><a href="/busbooking/frontend/payment_methods.php"><i class="fas fa-wallet"></i> Payment Methods</a></li>
                <li><a href="/busbooking/frontend/manage_bookings.php"><i class="fas fa-ticket-alt"></i> Manage Bookings</a></li>
                <li><a href="/busbooking/frontend/luggage.php"><i class="fas fa-suitcase"></i> Add Luggage</a></li>
                <li><a href="/busbooking/frontend/help.php"><i class="fas fa-question-circle"></i> Help Center</a></li>
                <li><a href="/busbooking/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Welcome back, <?= htmlspecialchars($user['first_name'] ?? $user['username']) ?>!</h1>
                <div>
                    <span><?= date('l, F j, Y') ?></span>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <h3>Total Bookings</h3>
                    <div class="value"><?= count($bookings) ?></div>
                    <div class="change up">
                        <i class="fas fa-arrow-up"></i>
                        <span>12% from last month</span>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Confirmed</h3>
                    <div class="value"><?= $status_counts['confirmed'] ?></div>
                    <div class="change up">
                        <i class="fas fa-arrow-up"></i>
                        <span>5% from last month</span>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Cancelled</h3>
                    <div class="value"><?= $status_counts['cancelled'] ?></div>
                    <div class="change down">
                        <i class="fas fa-arrow-down"></i>
                        <span>3% from last month</span>
                    </div>
                </div>
                <div class="stat-card">
                    <h3>Completed</h3>
                    <div class="value"><?= $status_counts['completed'] ?></div>
                    <div class="change up">
                        <i class="fas fa-arrow-up"></i>
                        <span>8% from last month</span>
                    </div>
                </div>
            </div>

            <!-- Bookings Section -->
            <div class="bookings-container">
                <div class="section-header">
                    <h2>Your Bookings</h2>
                    <div class="booking-filters">
                        <button class="filter-btn active">All</button>
                        <button class="filter-btn">Confirmed</button>
                        <button class="filter-btn">Cancelled</button>
                        <button class="filter-btn">Completed</button>
                    </div>
                </div>

                <?php if (empty($bookings)): ?>
                    <div class="no-bookings">
                        <i class="fas fa-bus"></i>
                        <h3>You have no bookings yet</h3>
                        <p>Start by booking your first trip with us</p>
                        <a href="/busbooking/frontend/bus_listing.php">Book a Bus Now</a>
                    </div>
                <?php else: ?>
                    <table class="bookings-table">
                        <thead>
                            <tr>
                                <th>Booking</th>
                                <th>Date & Time</th>
                                <th>Passengers</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bookings as $booking): ?>
                                <tr>
                                    <td>
                                        <div class="booking-info">
                                            <div class="booking-icon">
                                                <i class="fas fa-bus"></i>
                                            </div>
                                            <div>
                                                <div class="route">
                                                    <?= htmlspecialchars($booking['departure_city']) ?> → <?= htmlspecialchars($booking['arrival_city']) ?>
                                                </div>
                                                <div class="route-details">
                                                    #<?= $booking['booking_id'] ?> • <?= htmlspecialchars($booking['bus_number']) ?>
                                                    <?php if ($booking['luggage_count'] > 0): ?>
                                                        <div class="luggage-info">
                                                            <i class="fas fa-suitcase"></i>
                                                            <?= $booking['luggage_count'] ?> luggage (€<?= number_format($booking['luggage_fee'], 2) ?>)
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($booking['change_request'] !== 'none'): ?>
                                                        <div class="route-details">
                                                            <strong>Change Request:</strong> 
                                                            <span class="request-status request-<?= $booking['change_request_status'] ?>">
                                                                <?= ucfirst($booking['change_request_status']) ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?= date('M j, Y', strtotime($booking['date'])) ?></div>
                                        <div class="route-details">
                                            <?= htmlspecialchars($booking['departure_time']) ?> - <?= htmlspecialchars($booking['arrival_time']) ?>
                                            <?php if ($booking['change_request'] === 'date_change' || $booking['change_request'] === 'both'): ?>
                                                <div class="route-details">
                                                    <strong>New:</strong> <?= date('M j, Y', strtotime($booking['new_departure_date'])) ?> at <?= htmlspecialchars($booking['new_departure_time']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?= $booking['passengers'] ?> <span class="route-details">(Seats: <?= $booking['seat_numbers'] ?>)</span>
                                        <?php if ($booking['change_request'] === 'seat_change' || $booking['change_request'] === 'both'): ?>
                                            <div class="route-details">
                                                <strong>New:</strong> <?= $booking['new_seat_numbers'] ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        €<?= number_format($booking['total_price'], 2) ?>
                                        <div class="route-details">
                                            <?= !empty($booking['payment_method']) ? ucfirst($booking['payment_method']) : 'Not specified' ?>
                                            <span class="payment-status payment-<?= $booking['payment_status'] ?>">
                                                <?= ucfirst($booking['payment_status']) ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?= $booking['status'] ?>">
                                            <?= ucfirst($booking['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                            <a href="/busbooking/frontend/ticket.php?booking_id=<?= $booking['booking_id'] ?>" 
                                               class="action-btn view">
                                                <i class="fas fa-ticket-alt"></i> Ticket
                                            </a>
                                            <?php if ($booking['status'] == 'confirmed'): ?>
                                                <button class="action-btn change" 
                                                        onclick="openChangeModal(<?= $booking['booking_id'] ?>)">
                                                    <i class="fas fa-exchange-alt"></i> Change
                                                </button>
                                                <button class="action-btn luggage" 
                                                        onclick="openLuggageModal(<?= $booking['booking_id'] ?>)">
                                                    <i class="fas fa-suitcase"></i> Luggage
                                                </button>
                                                <a href="/busbooking/frontend/cancel_booking.php?booking_id=<?= $booking['booking_id'] ?>" 
                                                   class="action-btn cancel"
                                                   onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                    <i class="fas fa-times"></i> Cancel
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Change Booking Modal -->
    <div id="changeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Change Booking Details</h3>
                <button class="close-modal" onclick="closeModal('changeModal')">&times;</button>
            </div>
            <form id="changeForm" action="/busbooking/frontend/process_change.php" method="POST">
                <input type="hidden" name="booking_id" id="changeBookingId">
                
                <div class="form-group">
                    <label for="changeType">What would you like to change?</label>
                    <select name="change_type" id="changeType" required>
                        <option value="">Select option</option>
                        <option value="seat_change">Change Seats</option>
                        <option value="date_change">Change Date/Time</option>
                        <option value="both">Change Both</option>
                    </select>
                </div>
                
                <div id="seatChangeSection" style="display: none;">
                    <div class="form-group">
                        <label for="newSeats">New Seat Numbers (comma separated)</label>
                        <input type="text" name="new_seats" id="newSeats" placeholder="e.g. 1A, 2B">
                    </div>
                </div>
                
                <div id="dateChangeSection" style="display: none;">
                    <div class="form-group">
                        <label for="newDate">New Departure Date</label>
                        <input type="date" name="new_date" id="newDate">
                    </div>
                    <div class="form-group">
                        <label for="newTime">New Departure Time</label>
                        <input type="time" name="new_time" id="newTime">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="changeNotes">Additional Notes</label>
                    <textarea name="notes" id="changeNotes" rows="3" placeholder="Any special requests..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('changeModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Add Luggage Modal -->
    <div id="luggageModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Luggage to Booking</h3>
                <button class="close-modal" onclick="closeModal('luggageModal')">&times;</button>
            </div>
            <form id="luggageForm" action="/busbooking/frontend/process_luggage.php" method="POST">
                <input type="hidden" name="booking_id" id="luggageBookingId">
                
                <div class="form-group">
                    <label for="luggageCount">Number of Luggage Items</label>
                    <select name="luggage_count" id="luggageCount" required>
                        <option value="0">None</option>
                        <option value="1">1 Item (€5.00)</option>
                        <option value="2">2 Items (€9.00)</option>
                        <option value="3">3 Items (€12.00)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="luggageNotes">Special Instructions</label>
                    <textarea name="notes" id="luggageNotes" rows="3" placeholder="Any special instructions for your luggage..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('luggageModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Luggage</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filter bookings
        const filterBtns = document.querySelectorAll('.filter-btn');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                filterBtns.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                
                const filter = btn.textContent.toLowerCase();
                const rows = document.querySelectorAll('.bookings-table tbody tr');
                
                rows.forEach(row => {
                    const status = row.querySelector('.status-badge').textContent.toLowerCase();
                    if (filter === 'all' || status === filter) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        });

        // Modal functions
        function openChangeModal(bookingId) {
            document.getElementById('changeBookingId').value = bookingId;
            document.getElementById('changeModal').style.display = 'flex';
        }

        function openLuggageModal(bookingId) {
            document.getElementById('luggageBookingId').value = bookingId;
            document.getElementById('luggageModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Show/hide sections based on change type
        document.getElementById('changeType').addEventListener('change', function() {
            const seatSection = document.getElementById('seatChangeSection');
            const dateSection = document.getElementById('dateChangeSection');
            
            seatSection.style.display = 'none';
            dateSection.style.display = 'none';
            
            if (this.value === 'seat_change' || this.value === 'both') {
                seatSection.style.display = 'block';
            }
            
            if (this.value === 'date_change' || this.value === 'both') {
                dateSection.style.display = 'block';
            }
        });

        // Responsive sidebar toggle for mobile
        const sidebarToggle = document.createElement('button');
        sidebarToggle.innerHTML = '<i class="fas fa-bars"></i>';
        sidebarToggle.style.position = 'fixed';
        sidebarToggle.style.bottom = '20px';
        sidebarToggle.style.right = '20px';
        sidebarToggle.style.zIndex = '1000';
        sidebarToggle.style.width = '50px';
        sidebarToggle.style.height = '50px';
        sidebarToggle.style.borderRadius = '50%';
        sidebarToggle.style.background = 'var(--primary)';
        sidebarToggle.style.color = 'white';
        sidebarToggle.style.border = 'none';
        sidebarToggle.style.boxShadow = '0 4px 10px rgba(0,0,0,0.2)';
        sidebarToggle.style.cursor = 'pointer';
        sidebarToggle.style.display = 'none';
        
        document.body.appendChild(sidebarToggle);
        
        const sidebar = document.querySelector('.sidebar');
        
        function checkScreenSize() {
            if (window.innerWidth <= 992) {
                sidebarToggle.style.display = 'flex';
                sidebarToggle.style.alignItems = 'center';
                sidebarToggle.style.justifyContent = 'center';
                sidebar.style.display = 'none';
            } else {
                sidebarToggle.style.display = 'none';
                sidebar.style.display = 'block';
            }
        }
        
        sidebarToggle.addEventListener('click', () => {
            if (sidebar.style.display === 'none') {
                sidebar.style.display = 'block';
            } else {
                sidebar.style.display = 'none';
            }
        });
        
        window.addEventListener('resize', checkScreenSize);
        checkScreenSize();

        // Close modal when clicking outside
        window.addEventListener('click', (event) => {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        });
    </script>
</body>
</html>
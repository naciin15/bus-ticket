<?php
require_once __DIR__ . '/../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: /busbooking/backend/admin/login.php');
    exit();
}

// Set default admin values if not set
if (!isset($_SESSION['admin_name'])) {
    $_SESSION['admin_name'] = 'Admin';
}
if (!isset($_SESSION['admin_email'])) {
    $_SESSION['admin_email'] = 'admin@example.com';
}

$admin_id = $_SESSION['admin_id'];
$admin_role = $_SESSION['admin_role'] ?? 'admin';
$admin_name = $_SESSION['admin_name'];
$admin_email = $_SESSION['admin_email'];

// Get booking ID from URL
$booking_id = $_GET['id'] ?? 0;

try {
    // Fetch booking details
    $stmt = $pdo->prepare("
        SELECT b.*, 
               u.username, u.email, u.first_name, u.last_name, u.phone,
               bs.bus_number, bs.departure_city, bs.arrival_city, 
               bs.departure_time, bs.arrival_time, bs.date, bs.price,
               be.luggage_count, be.luggage_fee, be.seat_change_fee, be.date_change_fee, be.notes
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN buses bs ON b.bus_id = bs.id
        LEFT JOIN booking_extras be ON b.id = be.booking_id
        WHERE b.id = ?
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception('Booking not found');
    }

    // Fetch booked seats (if you have a seat selection system)
    $seats_stmt = $pdo->prepare("SELECT seat_number FROM booked_seats WHERE booking_id = ?");
    $seats_stmt->execute([$booking_id]);
    $seats = $seats_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - LineBus Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Reuse the same CSS from other admin pages */
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

        /* Sidebar - Same as other pages */
        .sidebar {
            background: var(--dark);
            color: var(--light);
            padding: 2rem 1rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
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

        /* Booking Details Card */
        .booking-card {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .booking-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .booking-id {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .booking-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
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

        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }

        .detail-group {
            margin-bottom: 1.5rem;
        }

        .detail-label {
            font-size: 0.9rem;
            color: var(--medium);
            margin-bottom: 0.5rem;
            display: block;
        }

        .detail-value {
            font-size: 1.1rem;
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            padding: 0.8rem 1.5rem;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-family: inherit;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--light-bg);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background-color: #e0e0e0;
        }

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: var(--secondary-dark);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                height: auto;
                position: static;
                display: none;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar - Same as other pages -->
        <aside class="sidebar">
            <div class="profile">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin_name ?? 'Admin') ?>&background=4CAF50&color=fff" 
                     alt="Profile" class="profile-img">
                <h3><?= htmlspecialchars($admin_name ?? 'Admin') ?></h3>
                <p><?= htmlspecialchars($admin_email ?? 'admin@example.com') ?></p>
                <span class="role-badge role-<?= str_replace('_', '-', $admin_role) ?>">
                    <?= ucfirst(str_replace('_', ' ', $admin_role)) ?>
                </span>
            </div>
            <ul class="nav-menu">
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_bookings.php"><i class="fas fa-ticket-alt"></i> Bookings</a></li>
                <li><a href="manage_buses.php"><i class="fas fa-bus"></i> Buses</a></li>
                <li><a href="manage_routes.php"><i class="fas fa-route"></i> Routes</a></li>
                <li><a href="manage_users.php"><i class="fas fa-users"></i> Users</a></li>
                <?php if ($admin_role === 'super_admin'): ?>
                    <li><a href="admins.php"><i class="fas fa-user-shield"></i> Admins</a></li>
                <?php endif; ?>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="/busbooking/backend/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="header">
                <h1>Booking Details</h1>
                <div>
                    <a href="manage_bookings.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Bookings
                    </a>
                </div>
            </div>

            <div class="booking-card">
                <div class="booking-header">
                    <div class="booking-id">Booking #<?= $booking['id'] ?></div>
                    <span class="booking-status status-<?= $booking['status'] ?>">
                        <?= ucfirst($booking['status']) ?>
                    </span>
                </div>

                <div class="details-grid">
                    <div>
                        <div class="detail-group">
                            <span class="detail-label">Customer</span>
                            <div class="detail-value">
                                <?= htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Email</span>
                            <div class="detail-value">
                                <?= htmlspecialchars($booking['email']) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Phone</span>
                            <div class="detail-value">
                                <?= $booking['phone'] ? htmlspecialchars($booking['phone']) : 'N/A' ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Booking Date</span>
                            <div class="detail-value">
                                <?= date('M j, Y g:i a', strtotime($booking['booking_date'])) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="detail-group">
                            <span class="detail-label">Bus Number</span>
                            <div class="detail-value">
                                <?= htmlspecialchars($booking['bus_number']) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Route</span>
                            <div class="detail-value">
                                <?= htmlspecialchars($booking['departure_city']) ?> → <?= htmlspecialchars($booking['arrival_city']) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Departure</span>
                            <div class="detail-value">
                                <?= date('M j, Y', strtotime($booking['date'])) ?> at <?= date('g:i a', strtotime($booking['departure_time'])) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Arrival</span>
                            <div class="detail-value">
                                <?= date('M j, Y', strtotime($booking['date'])) ?> at <?= date('g:i a', strtotime($booking['arrival_time'])) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="detail-group">
                            <span class="detail-label">Passengers</span>
                            <div class="detail-value">
                                <?= $booking['passengers'] ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($seats)): ?>
                        <div class="detail-group">
                            <span class="detail-label">Seat Numbers</span>
                            <div class="detail-value">
                                <?= implode(', ', $seats) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <div class="detail-group">
                            <span class="detail-label">Base Price</span>
                            <div class="detail-value">
                                €<?= number_format($booking['price'], 2) ?>
                            </div>
                        </div>
                        
                        <?php if ($booking['luggage_count'] > 0): ?>
                        <div class="detail-group">
                            <span class="detail-label">Luggage (<?= $booking['luggage_count'] ?>)</span>
                            <div class="detail-value">
                                €<?= number_format($booking['luggage_fee'], 2) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="detail-group">
                            <span class="detail-label">Total Price</span>
                            <div class="detail-value" style="font-weight: 600;">
                                €<?= number_format($booking['total_price'], 2) ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($booking['notes'])): ?>
                <div class="detail-group">
                    <span class="detail-label">Special Notes</span>
                    <div class="detail-value" style="background: var(--light-bg); padding: 1rem; border-radius: 4px;">
                        <?= nl2br(htmlspecialchars($booking['notes'])) ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="action-buttons">
                    <a href="edit_booking.php?id=<?= $booking['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Booking
                    </a>
                    
                    <?php if ($booking['status'] === 'confirmed'): ?>
                    <a href="cancel_booking.php?id=<?= $booking['id'] ?>" class="btn btn-danger" 
                       onclick="return confirm('Are you sure you want to cancel this booking?')">
                        <i class="fas fa-times"></i> Cancel Booking
                    </a>
                    <?php endif; ?>
                    
                    <a href="#" class="btn btn-secondary">
                        <i class="fas fa-print"></i> Print Ticket
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
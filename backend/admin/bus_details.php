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

// Get bus ID from URL
$bus_id = $_GET['id'] ?? 0;

try {
    // Fetch bus details
    $stmt = $pdo->prepare("
        SELECT b.*, 
               COUNT(bk.id) AS bookings_count,
               (b.total_seats - b.available_seats) AS occupied_seats,
               ROUND(((b.total_seats - b.available_seats) / b.total_seats * 100), 2) AS utilization_percent
        FROM buses b
        LEFT JOIN bookings bk ON b.id = bk.bus_id
        WHERE b.id = ?
        GROUP BY b.id
    ");
    $stmt->execute([$bus_id]);
    $bus = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bus) {
        throw new Exception('Bus not found');
    }

    // Fetch upcoming bookings for this bus
    $bookings_stmt = $pdo->prepare("
        SELECT bk.id, bk.booking_date, bk.status, bk.passengers, bk.total_price,
               u.username, u.email
        FROM bookings bk
        JOIN users u ON bk.user_id = u.id
        WHERE bk.bus_id = ?
        ORDER BY bk.booking_date DESC
        LIMIT 5
    ");
    $bookings_stmt->execute([$bus_id]);
    $bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bus Details - LineBus Admin</title>
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

        /* Bus Details Card */
        .bus-card {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .bus-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .bus-number {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .bus-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-active {
            background: rgba(76, 175, 80, 0.1);
            color: var(--success);
        }

        .status-inactive {
            background: rgba(117, 117, 117, 0.1);
            color: var(--medium);
        }

        .status-maintenance {
            background: rgba(255, 152, 0, 0.1);
            color: var(--warning);
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

        /* Utilization Bar */
        .utilization-bar {
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
            margin-top: 0.5rem;
        }

        .utilization-fill {
            height: 100%;
            background-color: var(--info);
            border-radius: 10px;
        }

        .utilization-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 0.7rem;
            font-weight: 600;
            color: var(--light);
            mix-blend-mode: difference;
        }

        /* Bookings Table */
        .bookings-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--light);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
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

        .bookings-table tr:last-child td {
            border-bottom: none;
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
                <h1>Bus Details</h1>
                <div>
                    <a href="manage_buses.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Buses
                    </a>
                </div>
            </div>

            <div class="bus-card">
                <div class="bus-header">
                    <div class="bus-number"><?= htmlspecialchars($bus['bus_number']) ?></div>
                    <span class="bus-status status-<?= $bus['status'] ?>">
                        <?= ucfirst($bus['status']) ?>
                    </span>
                </div>

                <div class="details-grid">
                    <div>
                        <div class="detail-group">
                            <span class="detail-label">Route</span>
                            <div class="detail-value">
                                <?= htmlspecialchars($bus['departure_city']) ?> → <?= htmlspecialchars($bus['arrival_city']) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Departure</span>
                            <div class="detail-value">
                                <?= date('M j, Y', strtotime($bus['date'])) ?> at <?= date('g:i a', strtotime($bus['departure_time'])) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Arrival</span>
                            <div class="detail-value">
                                <?= date('M j, Y', strtotime($bus['date'])) ?> at <?= date('g:i a', strtotime($bus['arrival_time'])) ?>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="detail-group">
                            <span class="detail-label">Price</span>
                            <div class="detail-value">
                                €<?= number_format($bus['price'], 2) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Seats</span>
                            <div class="detail-value">
                                <?= $bus['occupied_seats'] ?>/<?= $bus['total_seats'] ?> (<?= $bus['utilization_percent'] ?>% utilized)
                            </div>
                            <div class="utilization-bar">
                                <div class="utilization-fill" style="width: <?= $bus['utilization_percent'] ?>%"></div>
                                <span class="utilization-text"><?= $bus['utilization_percent'] ?>% utilized</span>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Bookings</span>
                            <div class="detail-value">
                                <?= $bus['bookings_count'] ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="edit_bus.php?id=<?= $bus['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Bus
                    </a>
                    
                    <?php if ($bus['status'] === 'active'): ?>
                    <a href="deactivate_bus.php?id=<?= $bus['id'] ?>" class="btn btn-danger" 
                       onclick="return confirm('Are you sure you want to deactivate this bus?')">
                        <i class="fas fa-ban"></i> Deactivate
                    </a>
                    <?php else: ?>
                    <a href="activate_bus.php?id=<?= $bus['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-check"></i> Activate
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <h2 style="margin-bottom: 1rem;">Recent Bookings</h2>
            
            <?php if (empty($bookings)): ?>
                <div style="background: var(--light); padding: 2rem; border-radius: 10px; text-align: center;">
                    No bookings found for this bus
                </div>
            <?php else: ?>
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Passengers</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>#<?= $booking['id'] ?></td>
                                <td><?= date('M j, Y', strtotime($booking['booking_date'])) ?></td>
                                <td>
                                    <div><?= htmlspecialchars($booking['username']) ?></div>
                                    <small><?= htmlspecialchars($booking['email']) ?></small>
                                </td>
                                <td><?= $booking['passengers'] ?></td>
                                <td>€<?= number_format($booking['total_price'], 2) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $booking['status'] ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="manage_bookings.php?bus_id=<?= $bus['id'] ?>" class="btn btn-secondary" style="margin-top: 1rem;">
                        View All Bookings
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
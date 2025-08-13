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

// Get route parameters from URL
$departure_city = $_GET['departure'] ?? '';
$arrival_city = $_GET['arrival'] ?? '';

try {
    // Fetch route details
    $stmt = $pdo->prepare("
        SELECT 
            departure_city, 
            arrival_city,
            MIN(price) AS min_price,
            MAX(price) AS max_price,
            AVG(price) AS avg_price,
            COUNT(*) AS bus_count,
            SUM(total_seats) AS total_seats,
            SUM(total_seats - available_seats) AS booked_seats,
            ROUND(SUM(total_seats - available_seats) / SUM(total_seats) * 100, 2) AS utilization_percent
        FROM buses
        WHERE departure_city = ? AND arrival_city = ?
        GROUP BY departure_city, arrival_city
    ");
    $stmt->execute([$departure_city, $arrival_city]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$route) {
        throw new Exception('Route not found');
    }

    // Fetch upcoming buses for this route
    $buses_stmt = $pdo->prepare("
        SELECT id, bus_number, date, departure_time, arrival_time, 
               price, total_seats, available_seats, status
        FROM buses
        WHERE departure_city = ? AND arrival_city = ? AND date >= CURDATE()
        ORDER BY date, departure_time
        LIMIT 10
    ");
    $buses_stmt->execute([$departure_city, $arrival_city]);
    $buses = $buses_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch booking statistics
    $stats_stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_bookings,
            SUM(total_price) AS total_revenue,
            AVG(total_price) AS avg_revenue,
            SUM(passengers) AS total_passengers
        FROM bookings b
        JOIN buses bs ON b.bus_id = bs.id
        WHERE bs.departure_city = ? AND bs.arrival_city = ?
    ");
    $stats_stmt->execute([$departure_city, $arrival_city]);
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Route Details - LineBus Admin</title>
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

        /* Route Card */
        .route-card {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .route-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .route-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--light-bg);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-value.price {
            color: var(--success);
        }

        .stat-value.revenue {
            color: var(--accent);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--medium);
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

        /* Buses Table */
        .buses-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--light);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .buses-table th {
            text-align: left;
            padding: 1rem;
            background: var(--light-bg);
            color: var(--medium);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .buses-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .buses-table tr:last-child td {
            border-bottom: none;
        }

        /* Status Badges */
        .status-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
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

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
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
                <li><a href="manage_routes.php" class="active"><i class="fas fa-route"></i> Routes</a></li>
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
                <h1>Route Details</h1>
                <div>
                    <a href="manage_routes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Routes
                    </a>
                </div>
            </div>

            <div class="route-card">
                <div class="route-header">
                    <div class="route-title">
                        <?= htmlspecialchars($route['departure_city']) ?> → <?= htmlspecialchars($route['arrival_city']) ?>
                    </div>
                    <div>
                        <span class="status-badge status-active">
                            Active Route
                        </span>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value price">€<?= number_format($route['min_price'], 2) ?>-€<?= number_format($route['max_price'], 2) ?></div>
                        <div class="stat-label">Price Range</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= $route['bus_count'] ?></div>
                        <div class="stat-label">Scheduled Buses</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= $route['total_seats'] ?></div>
                        <div class="stat-label">Total Seats</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= $route['utilization_percent'] ?>%</div>
                        <div class="stat-label">Utilization Rate</div>
                        <div class="utilization-bar">
                            <div class="utilization-fill" style="width: <?= $route['utilization_percent'] ?>%"></div>
                            <span class="utilization-text"><?= $route['utilization_percent'] ?>% utilized</span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value revenue">€<?= number_format($stats['total_revenue'] ?? 0, 2) ?></div>
                        <div class="stat-label">Total Revenue</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value"><?= $stats['total_bookings'] ?? 0 ?></div>
                        <div class="stat-label">Total Bookings</div>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="edit_route.php?departure=<?= urlencode($route['departure_city']) ?>&arrival=<?= urlencode($route['arrival_city']) ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit Route
                    </a>
                    
                    <a href="delete_route.php?departure=<?= urlencode($route['departure_city']) ?>&arrival=<?= urlencode($route['arrival_city']) ?>" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Delete Route
                    </a>
                    
                    <a href="add_bus.php?route=<?= urlencode($route['departure_city'] . '-' . $route['arrival_city']) ?>" class="btn btn-secondary">
                        <i class="fas fa-plus"></i> Add Bus Schedule
                    </a>
                </div>
            </div>

            <h2 style="margin-bottom: 1rem;">Upcoming Buses</h2>
            
            <?php if (empty($buses)): ?>
                <div style="background: var(--light); padding: 2rem; border-radius: 10px; text-align: center;">
                    No upcoming buses found for this route
                </div>
            <?php else: ?>
                <table class="buses-table">
                    <thead>
                        <tr>
                            <th>Bus #</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Price</th>
                            <th>Seats</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buses as $bus): ?>
                            <tr>
                                <td><?= htmlspecialchars($bus['bus_number']) ?></td>
                                <td><?= date('M j, Y', strtotime($bus['date'])) ?></td>
                                <td>
                                    <?= date('g:i a', strtotime($bus['departure_time'])) ?> - 
                                    <?= date('g:i a', strtotime($bus['arrival_time'])) ?>
                                </td>
                                <td>€<?= number_format($bus['price'], 2) ?></td>
                                <td><?= ($bus['total_seats'] - $bus['available_seats']) ?>/<?= $bus['total_seats'] ?></td>
                                <td>
                                    <span class="status-badge status-<?= $bus['status'] ?>">
                                        <?= ucfirst($bus['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="bus_details.php?id=<?= $bus['id'] ?>" class="btn btn-secondary" style="padding: 0.5rem;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="manage_buses.php?departure=<?= urlencode($route['departure_city']) ?>&arrival=<?= urlencode($route['arrival_city']) ?>" class="btn btn-secondary" style="margin-top: 1rem;">
                        View All Buses
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
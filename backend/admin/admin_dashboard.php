<?php
require __DIR__ . '/../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: /busbooking/backend/admin/login.php');
    exit();
}

$admin_id = $_SESSION['admin_id'];

try {
    // Fetch admin details
    $admin_stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
    $admin_stmt->execute([$admin_id]);
    $admin = $admin_stmt->fetch();

    // Count total bookings
    $bookings_count = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    
    // Count total users
    $users_count = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    
    // Count total buses
    $buses_count = $pdo->query("SELECT COUNT(*) FROM buses")->fetchColumn();
    
    // Count revenue (sum of all paid bookings)
    $revenue = $pdo->query("SELECT SUM(total_price) FROM bookings WHERE payment_status = 'paid'")->fetchColumn();
    $revenue = $revenue ?: 0;

    // Recent bookings (last 5)
    $recent_bookings = $pdo->query("
        SELECT b.*, u.username, u.email, buses.bus_number, buses.departure_city, buses.arrival_city
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN buses ON b.bus_id = buses.id
        ORDER BY b.booking_date DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Recent system errors (last 5)
    $recent_errors = $pdo->query("
        SELECT e.*, u.username 
        FROM system_errors e
        LEFT JOIN users u ON e.user_id = u.id
        ORDER BY e.created_at DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LineBus</title>
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
            --super-admin: #9C27B0;
            --admin: #2196F3;
            --manager: #FF9800;
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

        .role-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .role-super_admin {
            background: rgba(156, 39, 176, 0.1);
            color: var(--super-admin);
        }

        .role-admin {
            background: rgba(33, 150, 243, 0.1);
            color: var(--admin);
        }

        .role-manager {
            background: rgba(255, 152, 0, 0.1);
            color: var(--manager);
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

        .dashboard-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .dashboard-section {
            background: var(--light);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .section-header h2 {
            font-size: 1.2rem;
            color: var(--dark);
        }

        .section-header a {
            font-size: 0.9rem;
            color: var(--primary);
            text-decoration: none;
        }

        .recent-list {
            list-style: none;
        }

        .recent-item {
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
        }

        .recent-item:last-child {
            border-bottom: none;
        }

        .recent-item-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .recent-item-title {
            font-weight: 500;
        }

        .recent-item-meta {
            font-size: 0.8rem;
            color: var(--medium);
        }

        .recent-item-details {
            font-size: 0.9rem;
            color: var(--medium);
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

        .error-type {
            font-weight: 500;
            color: var(--danger);
        }

        @media (max-width: 992px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }

            .sidebar {
                height: auto;
                position: static;
            }

            .dashboard-sections {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-cards {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="profile">
                <img src="https://ui-avatars.com/api/?name=<?= urlencode($admin['username']) ?>&background=4CAF50&color=fff" 
                     alt="Profile" class="profile-img">
                <h3><?= htmlspecialchars($admin['username']) ?></h3>
                <p><?= htmlspecialchars($admin['email']) ?></p>
                <span class="role-badge role-<?= str_replace('_', '-', $admin['role']) ?>">
                    <?= ucfirst(str_replace('_', ' ', $admin['role'])) ?>
                </span>
            </div>
            <ul class="nav-menu">
                <li><a href="busbooking/backend/admin/admin_dashboard.php" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="../admin/bookings.php"><i class="fas fa-ticket-alt"></i> Bookings</a></li>
                <li><a href="buses.php"><i class="fas fa-bus"></i> Buses</a></li>
                <li><a href="routes.php"><i class="fas fa-route"></i> Routes</a></li>
                <li><a href="users.php"><i class="fas fa-users"></i> Users</a></li>
                <li><a href="admins.php"><i class="fas fa-user-shield"></i> Admins</a></li>
                <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="busbooking/backend/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="header">
                <h1>Admin Dashboard</h1>
                <div>
                    <span><?= date('l, F j, Y') ?></span>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <div class="stat-card">
                    <h3>Total Bookings</h3>
                    <div class="value"><?= $bookings_count ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Users</h3>
                    <div class="value"><?= $users_count ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Buses</h3>
                    <div class="value"><?= $buses_count ?></div>
                </div>
                <div class="stat-card">
                    <h3>Total Revenue</h3>
                    <div class="value">€<?= number_format($revenue, 2) ?></div>
                </div>
            </div>

            <!-- Recent Activity Sections -->
            <div class="dashboard-sections">
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Recent Bookings</h2>
                        <a href="bookings.php">View All</a>
                    </div>
                    <ul class="recent-list">
                        <?php if (empty($recent_bookings)): ?>
                            <li class="recent-item">
                                <div class="recent-item-details">No recent bookings</div>
                            </li>
                        <?php else: ?>
                            <?php foreach ($recent_bookings as $booking): ?>
                                <li class="recent-item">
                                    <div class="recent-item-header">
                                        <div class="recent-item-title">
                                            #<?= $booking['id'] ?> - <?= htmlspecialchars($booking['username']) ?>
                                        </div>
                                        <span class="status-badge status-<?= $booking['status'] ?>">
                                            <?= ucfirst($booking['status']) ?>
                                        </span>
                                    </div>
                                    <div class="recent-item-details">
                                        <?= htmlspecialchars($booking['departure_city']) ?> → <?= htmlspecialchars($booking['arrival_city']) ?>
                                        • <?= date('M j, Y', strtotime($booking['booking_date'])) ?>

                                        
                                    </div>
                                    <div class="recent-item-meta">
                                        €<?= number_format($booking['total_price'], 2) ?> • <?= $booking['passengers'] ?> passengers
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="dashboard-section">
                    <div class="section-header">
                        <h2>Recent System Errors</h2>
                        <a href="errors.php">View All</a>
                    </div>
                    <ul class="recent-list">
                        <?php if (empty($recent_errors)): ?>
                            <li class="recent-item">
                                <div class="recent-item-details">No recent errors</div>
                            </li>
                        <?php else: ?>
                            <?php foreach ($recent_errors as $error): ?>
                                <li class="recent-item">
                                    <div class="recent-item-header">
                                        <div class="error-type"><?= htmlspecialchars($error['error_type']) ?></div>
                                        <div class="recent-item-meta">
                                            <?= date('M j, H:i', strtotime($error['created_at'])) ?>
                                        </div>
                                    </div>
                                    <div class="recent-item-details">
                                        <?= htmlspecialchars(substr($error['details'], 0, 100)) ?>...
                                    </div>
                                    <?php if ($error['username']): ?>
                                        <div class="recent-item-meta">
                                            User: <?= htmlspecialchars($error['username']) ?>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
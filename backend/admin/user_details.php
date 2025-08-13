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

// Get user ID from URL
$user_id = $_GET['id'] ?? 0;

try {
    // Fetch user details
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COUNT(b.id) AS booking_count,
               SUM(b.total_price) AS total_spent,
               MAX(b.booking_date) AS last_booking_date
        FROM users u
        LEFT JOIN bookings b ON u.id = b.user_id
        WHERE u.id = ?
        GROUP BY u.id
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('User not found');
    }

    // Fetch recent bookings
    $bookings_stmt = $pdo->prepare("
        SELECT b.id, b.booking_date, b.status, b.total_price,
               bs.bus_number, bs.departure_city, bs.arrival_city, bs.date
        FROM bookings b
        JOIN buses bs ON b.bus_id = bs.id
        WHERE b.user_id = ?
        ORDER BY b.booking_date DESC
        LIMIT 5
    ");
    $bookings_stmt->execute([$user_id]);
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
    <title>User Details - LineBus Admin</title>
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

        /* User Card */
        .user-card {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .user-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
        }

        .user-name {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .user-status {
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
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .stat-value.revenue {
            color: var(--success);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--medium);
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

        /* Status Badges */
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
                <li><a href="manage_users.php" class="active"><i class="fas fa-users"></i> Users</a></li>
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
                <h1>User Details</h1>
                <div>
                    <a href="manage_users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>
            </div>

            <div class="user-card">
                <div class="user-header">
                    <div class="user-name">
                        <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                    </div>
                    <span class="user-status status-<?= $user['is_active'] ? 'active' : 'inactive' ?>">
                        <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?= $user['booking_count'] ?? 0 ?></div>
                        <div class="stat-label">Total Bookings</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value revenue">€<?= number_format($user['total_spent'] ?? 0, 2) ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-value">
                            <?= $user['last_booking_date'] ? date('M j, Y', strtotime($user['last_booking_date'])) : 'Never' ?>
                        </div>
                        <div class="stat-label">Last Booking</div>
                    </div>
                </div>

                <div class="details-grid">
                    <div>
                        <div class="detail-group">
                            <span class="detail-label">Username</span>
                            <div class="detail-value">
                                <?= htmlspecialchars($user['username']) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Email</span>
                            <div class="detail-value">
                                <?= htmlspecialchars($user['email']) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Phone</span>
                            <div class="detail-value">
                                <?= $user['phone'] ? htmlspecialchars($user['phone']) : 'Not provided' ?>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <div class="detail-group">
                            <span class="detail-label">Account Created</span>
                            <div class="detail-value">
                                <?= date('M j, Y', strtotime($user['created_at'])) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Last Login</span>
                            <div class="detail-value">
                                <?= date('M j, Y g:i a', strtotime($user['last_login'])) ?>
                            </div>
                        </div>
                        
                        <div class="detail-group">
                            <span class="detail-label">Role</span>
                            <div class="detail-value">
                                <?= ucfirst($user['role']) ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="action-buttons">
                    <a href="edit_user.php?id=<?= $user['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i> Edit User
                    </a>
                    
                    <?php if ($user['is_active']): ?>
                    <a href="deactivate_user.php?id=<?= $user['id'] ?>" class="btn btn-danger" 
                       onclick="return confirm('Are you sure you want to deactivate this user?')">
                        <i class="fas fa-ban"></i> Deactivate
                    </a>
                    <?php else: ?>
                    <a href="activate_user.php?id=<?= $user['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-check"></i> Activate
                    </a>
                    <?php endif; ?>
                    
                    <a href="#" class="btn btn-secondary">
                        <i class="fas fa-envelope"></i> Send Message
                    </a>
                </div>
            </div>

            <h2 style="margin-bottom: 1rem;">Recent Bookings</h2>
            
            <?php if (empty($bookings)): ?>
                <div style="background: var(--light); padding: 2rem; border-radius: 10px; text-align: center;">
                    No bookings found for this user
                </div>
            <?php else: ?>
                <table class="bookings-table">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Date</th>
                            <th>Route</th>
                            <th>Bus</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>#<?= $booking['id'] ?></td>
                                <td><?= date('M j, Y', strtotime($booking['booking_date'])) ?></td>
                                <td>
                                    <?= htmlspecialchars($booking['departure_city']) ?> → <?= htmlspecialchars($booking['arrival_city']) ?>
                                </td>
                                <td><?= htmlspecialchars($booking['bus_number']) ?></td>
                                <td>€<?= number_format($booking['total_price'], 2) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $booking['status'] ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="booking_details.php?id=<?= $booking['id'] ?>" class="btn btn-secondary" style="padding: 0.5rem;">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <a href="manage_bookings.php?user_id=<?= $user['id'] ?>" class="btn btn-secondary" style="margin-top: 1rem;">
                        View All Bookings
                    </a>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
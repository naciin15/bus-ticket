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

// Set default values for admin session variables if not set
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

// Get filter parameters
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

try {
    // Build query with filters
    $sql = "
        SELECT b.*, u.username, u.email, buses.bus_number, buses.departure_city, buses.arrival_city
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN buses ON b.bus_id = buses.id
        WHERE 1=1
    ";
    
    $params = [];
    
    if ($status) {
        $sql .= " AND b.status = ?";
        $params[] = $status;
    }
    
    if ($date_from) {
        $sql .= " AND DATE(b.booking_date) >= ?";
        $params[] = $date_from;
    }
    
    if ($date_to) {
        $sql .= " AND DATE(b.booking_date) <= ?";
        $params[] = $date_to;
    }
    
    if ($search) {
        $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR buses.bus_number LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $sql .= " ORDER BY b.booking_date DESC";
    
    // Count total bookings for pagination
    $count_sql = "SELECT COUNT(*) FROM ($sql) AS total";
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_bookings = $count_stmt->fetchColumn();
    
    // Pagination
    $per_page = 10;
    $total_pages = ceil($total_bookings / $per_page);
    $page = $_GET['page'] ?? 1;
    $offset = ($page - 1) * $per_page;
    
    $sql .= " LIMIT $per_page OFFSET $offset";
    
    // Fetch bookings
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - LineBus Admin</title>
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

        .role-badge {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .role-super-admin {
            background-color: rgba(156, 39, 176, 0.1);
            color: var(--super-admin);
        }

        .role-admin {
            background-color: rgba(33, 150, 243, 0.1);
            color: var(--admin);
        }

        .role-manager {
            background-color: rgba(255, 152, 0, 0.1);
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

        /* Filters */
        .filters {
            background: var(--light);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .filter-group {
            margin-bottom: 0;
        }

        .filter-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Form Controls */
        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-family: inherit;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
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
            display: inline-block;
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

        /* Data Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--light);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .data-table th {
            text-align: left;
            padding: 1rem;
            background: var(--light-bg);
            color: var(--medium);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        .data-table tr:last-child td {
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

        /* Action Buttons */
        .action-btn {
            padding: 0.5rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            text-decoration: none;
        }

        .action-btn.view {
            background: var(--primary-light);
            color: var(--primary);
        }

        .action-btn.edit {
            background: rgba(33, 150, 243, 0.1);
            color: var(--info);
        }

        .action-btn.cancel {
            background: rgba(244, 67, 54, 0.1);
            color: var(--danger);
        }

        .action-btn:hover {
            opacity: 0.8;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }

        .page-link {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            background: var(--light);
            color: var(--dark);
            text-decoration: none;
            border: 1px solid var(--border);
        }

        .page-link:hover {
            background: var(--light-bg);
        }

        .page-link.active {
            background: var(--primary);
            color: var(--light);
            border-color: var(--primary);
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
            .filter-form {
                grid-template-columns: 1fr 1fr;
            }

            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .data-table td {
                padding: 0.8rem;
            }

            .action-btn {
                padding: 0.5rem;
                font-size: 0.8rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 1rem;
            }

            .filter-form {
                grid-template-columns: 1fr;
            }

            .data-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
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
                <li><a href="manage_bookings.php" class="active"><i class="fas fa-ticket-alt"></i> Bookings</a></li>
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
                <h1>Manage Bookings</h1>
                <div>
                    <span><?= date('l, F j, Y') ?></span>
                </div>
            </div>

            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control">
                            <option value="">All Statuses</option>
                            <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_from">From Date</label>
                        <input type="date" id="date_from" name="date_from" class="form-control" 
                               value="<?= htmlspecialchars($date_from) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="date_to">To Date</label>
                        <input type="date" id="date_to" name="date_to" class="form-control" 
                               value="<?= htmlspecialchars($date_to) ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?= htmlspecialchars($search) ?>" placeholder="Username, Email, Bus #">
                    </div>
                    
                    <div class="filter-group" style="align-self: flex-end;">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                    </div>
                </form>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>User</th>
                        <th>Route</th>
                        <th>Bus</th>
                        <th>Date</th>
                        <th>Passengers</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bookings)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2rem;">No bookings found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($bookings as $booking): ?>
                            <tr>
                                <td>#<?= $booking['id'] ?></td>
                                <td>
                                    <div><?= htmlspecialchars($booking['username']) ?></div>
                                    <small><?= htmlspecialchars($booking['email']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($booking['departure_city']) ?> → <?= htmlspecialchars($booking['arrival_city']) ?>
                                </td>
                                <td><?= htmlspecialchars($booking['bus_number']) ?></td>
                                <td>
                                    <?php if (!empty($booking['booking_date'])): ?>
                                        <?= date('M j, Y', strtotime($booking['booking_date'])) ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td><?= $booking['passengers'] ?></td>
                                <td>€<?= number_format($booking['total_price'], 2) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $booking['status'] ?>">
                                        <?= ucfirst($booking['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="booking_details.php?id=<?= $booking['id'] ?>" class="action-btn view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($admin_role === 'super_admin' || $admin_role === 'admin'): ?>
                                            <a href="edit_booking.php?id=<?= $booking['id'] ?>" class="action-btn edit">
                                                <i class="fas fa-edit"></i> Edit
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($booking['status'] === 'confirmed' && ($admin_role === 'super_admin' || $admin_role === 'admin')): ?>
                                            <a href="cancel_booking.php?id=<?= $booking['id'] ?>" class="action-btn cancel" onclick="return confirm('Are you sure you want to cancel this booking?')">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">
                            &laquo; Previous
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" 
                           class="page-link <?= $page == $i ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">
                            Next &raquo;
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
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

// Get route ID from URL
$route_id = $_GET['id'] ?? 0;

// Initialize variables
$error = '';
$success = '';
$route = null;
$bus_count = 0;

try {
    // Fetch route details (in this system, routes are represented by buses)
    $stmt = $pdo->prepare("
        SELECT DISTINCT departure_city, arrival_city 
        FROM buses 
        WHERE id = ?
    ");
    $stmt->execute([$route_id]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$route) {
        throw new Exception('Route not found');
    }

    // Count how many buses use this route
    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM buses 
        WHERE departure_city = ? AND arrival_city = ?
    ");
    $count_stmt->execute([$route['departure_city'], $route['arrival_city']]);
    $bus_count = $count_stmt->fetchColumn();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $confirm = $_POST['confirm'] ?? '';
        
        if ($confirm !== 'DELETE') {
            throw new Exception('Please type "DELETE" to confirm');
        }

        if ($bus_count > 1) {
            // Delete only this specific bus (route instance)
            $delete_stmt = $pdo->prepare("DELETE FROM buses WHERE id = ?");
            $delete_stmt->execute([$route_id]);
            $success = 'Bus schedule for this route has been deleted';
        } else {
            // Delete all references to this route
            // First get all bus IDs for this route
            $bus_ids_stmt = $pdo->prepare("
                SELECT id FROM buses 
                WHERE departure_city = ? AND arrival_city = ?
            ");
            $bus_ids_stmt->execute([$route['departure_city'], $route['arrival_city']]);
            $bus_ids = $bus_ids_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($bus_ids)) {
                // Delete bookings for these buses
                $delete_bookings = $pdo->prepare("
                    DELETE FROM bookings 
                    WHERE bus_id IN (" . implode(',', array_fill(0, count($bus_ids), '?')) . ")
                ");
                $delete_bookings->execute($bus_ids);
                
                // Delete the buses
                $delete_buses = $pdo->prepare("
                    DELETE FROM buses 
                    WHERE id IN (" . implode(',', array_fill(0, count($bus_ids), '?')) . ")
                ");
                $delete_buses->execute($bus_ids);
            }
            
            $success = 'Route and all associated buses and bookings have been deleted';
        }
        
        // Redirect to manage routes page after deletion
        if ($success) {
            header("Location: manage_routes.php?success=" . urlencode($success));
            exit();
        }
    }

} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Route - LineBus Admin</title>
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

        /* Confirmation Card */
        .confirmation-card {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            max-width: 600px;
            margin: 0 auto;
        }

        .confirmation-message {
            margin-bottom: 2rem;
            line-height: 1.8;
        }

        .warning-message {
            background-color: rgba(255, 152, 0, 0.1);
            border-left: 4px solid var(--warning);
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .danger-message {
            background-color: rgba(244, 67, 54, 0.1);
            border-left: 4px solid var(--danger);
            padding: 1rem;
            margin-bottom: 2rem;
        }

        /* Form Controls */
        .form-control {
            width: 100%;
            padding: 0.8rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            font-family: inherit;
            transition: border-color 0.3s;
            margin-bottom: 1.5rem;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--danger);
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

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: var(--secondary-dark);
        }

        .btn-secondary {
            background-color: var(--light-bg);
            color: var(--dark);
        }

        .btn-secondary:hover {
            background-color: #e0e0e0;
        }

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger);
            border: 1px solid rgba(244, 67, 54, 0.2);
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
            
            .form-actions {
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
                <h1>Delete Route</h1>
                <div>
                    <a href="manage_routes.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Routes
                    </a>
                </div>
            </div>

            <div class="confirmation-card">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="confirmation-message">
                    <h3>Confirm Route Deletion</h3>
                    <p>You are about to delete the following route:</p>
                    <p style="font-weight: 500; margin: 1rem 0;">
                        <?= htmlspecialchars($route['departure_city']) ?> → <?= htmlspecialchars($route['arrival_city']) ?>
                    </p>
                </div>

                <?php if ($bus_count > 1): ?>
                    <div class="warning-message">
                        <p><strong>Note:</strong> There are <?= $bus_count ?> buses scheduled for this route.</p>
                        <p>By proceeding, you will only delete this specific bus schedule (ID: <?= $route_id ?>).</p>
                    </div>
                <?php else: ?>
                    <div class="danger-message">
                        <p><strong>Warning:</strong> This is the only bus scheduled for this route.</p>
                        <p>By proceeding, you will permanently delete this route and all associated data including:</p>
                        <ul style="margin: 0.5rem 0 0 1.5rem;">
                            <li>All scheduled buses for this route</li>
                            <li>All bookings for these buses</li>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div>
                        <label for="confirm" style="display: block; margin-bottom: 0.5rem;">
                            To confirm, please type <strong>DELETE</strong> in the box below:
                        </label>
                        <input type="text" id="confirm" name="confirm" class="form-control" 
                               placeholder="Type DELETE to confirm" required>
                    </div>

                    <div class="form-actions">
                        <a href="manage_routes.php" class="btn btn-secondary">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash-alt"></i> Delete Route
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
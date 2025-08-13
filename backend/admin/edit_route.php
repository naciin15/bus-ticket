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

// Initialize variables
$error = '';
$success = '';
$route = null;

try {
    // Fetch route details
    $stmt = $pdo->prepare("
        SELECT 
            departure_city, 
            arrival_city,
            MIN(price) AS min_price,
            MAX(price) AS max_price,
            AVG(price) AS avg_price
        FROM buses
        WHERE departure_city = ? AND arrival_city = ?
        GROUP BY departure_city, arrival_city
    ");
    $stmt->execute([$departure_city, $arrival_city]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$route) {
        throw new Exception('Route not found');
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $new_departure = $_POST['departure_city'] ?? '';
        $new_arrival = $_POST['arrival_city'] ?? '';
        $base_price = $_POST['base_price'] ?? '';
        $distance = $_POST['distance'] ?? '';
        $duration = $_POST['duration'] ?? '';
        
        // Validate inputs
        if (empty($new_departure) || empty($new_arrival)) {
            throw new Exception('Departure and arrival cities are required');
        }
        
        if ($new_departure === $new_arrival) {
            throw new Exception('Departure and arrival cities must be different');
        }
        
        if (!is_numeric($base_price) || $base_price <= 0) {
            throw new Exception('Base price must be a positive number');
        }
        
        // Update all buses for this route
        $update_stmt = $pdo->prepare("
            UPDATE buses 
            SET departure_city = ?, arrival_city = ?, price = ?
            WHERE departure_city = ? AND arrival_city = ?
        ");
        $update_stmt->execute([
            $new_departure, 
            $new_arrival,
            $base_price,
            $departure_city,
            $arrival_city
        ]);
        
        $success = 'Route updated successfully';
        
        // Update route data with new values
        $route['departure_city'] = $new_departure;
        $route['arrival_city'] = $new_arrival;
        $route['min_price'] = $base_price;
        $route['max_price'] = $base_price;
        $route['avg_price'] = $base_price;
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
    <title>Edit Route - LineBus Admin</title>
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

        /* Form Container */
        .form-container {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            max-width: 800px;
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

        /* Form Actions */
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background-color: rgba(76, 175, 80, 0.1);
            color: var(--success);
            border: 1px solid rgba(76, 175, 80, 0.2);
        }

        .alert-danger {
            background-color: rgba(244, 67, 54, 0.1);
            color: var(--danger);
            border: 1px solid rgba(244, 67, 54, 0.2);
        }

        /* Grid Layout for Form */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
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
            .form-grid {
                grid-template-columns: 1fr;
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
                <h1>Edit Route</h1>
                <div>
                    <a href="route_details.php?departure=<?= urlencode($departure_city) ?>&arrival=<?= urlencode($arrival_city) ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Details
                    </a>
                </div>
            </div>

            <div class="form-container">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="departure_city">Departure City</label>
                            <input type="text" id="departure_city" name="departure_city" class="form-control" 
                                   value="<?= htmlspecialchars($route['departure_city']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="arrival_city">Arrival City</label>
                            <input type="text" id="arrival_city" name="arrival_city" class="form-control" 
                                   value="<?= htmlspecialchars($route['arrival_city']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="base_price">Base Price (€)</label>
                            <input type="number" id="base_price" name="base_price" class="form-control" 
                                   step="0.01" min="0" value="<?= number_format($route['avg_price'], 2) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="distance">Distance (km)</label>
                            <input type="number" id="distance" name="distance" class="form-control" 
                                   step="1" min="1" value="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Duration (hours)</label>
                            <input type="number" id="duration" name="duration" class="form-control" 
                                   step="0.1" min="0.5" value="2.5">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn btn-secondary">Reset</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
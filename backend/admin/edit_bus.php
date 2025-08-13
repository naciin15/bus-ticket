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

// Initialize variables
$error = '';
$success = '';

try {
    // Fetch bus details
    $stmt = $pdo->prepare("SELECT * FROM buses WHERE id = ?");
    $stmt->execute([$bus_id]);
    $bus = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bus) {
        throw new Exception('Bus not found');
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $bus_number = $_POST['bus_number'] ?? '';
        $departure_city = $_POST['departure_city'] ?? '';
        $arrival_city = $_POST['arrival_city'] ?? '';
        $departure_time = $_POST['departure_time'] ?? '';
        $arrival_time = $_POST['arrival_time'] ?? '';
        $date = $_POST['date'] ?? '';
        $price = $_POST['price'] ?? '';
        $total_seats = $_POST['total_seats'] ?? '';
        $status = $_POST['status'] ?? 'active';
        
        // Validate inputs
        if (empty($bus_number) || empty($departure_city) || empty($arrival_city)) {
            throw new Exception('All fields are required');
        }
        
        if ($departure_city === $arrival_city) {
            throw new Exception('Departure and arrival cities must be different');
        }
        
        if (!is_numeric($price) || $price <= 0) {
            throw new Exception('Price must be a positive number');
        }
        
        if (!is_numeric($total_seats) || $total_seats <= 0) {
            throw new Exception('Total seats must be a positive number');
        }
        
        // Calculate available seats
        $booked_seats = $bus['total_seats'] - $bus['available_seats'];
        $new_available_seats = max(0, $total_seats - $booked_seats);
        
        // Update bus
        $update_stmt = $pdo->prepare("
            UPDATE buses 
            SET bus_number = ?, departure_city = ?, arrival_city = ?, 
                departure_time = ?, arrival_time = ?, date = ?, 
                price = ?, total_seats = ?, available_seats = ?, status = ?
            WHERE id = ?
        ");
        $update_stmt->execute([
            $bus_number, $departure_city, $arrival_city,
            $departure_time, $arrival_time, $date,
            $price, $total_seats, $new_available_seats, $status,
            $bus_id
        ]);
        
        $success = 'Bus updated successfully';
        
        // Refresh bus data
        $stmt->execute([$bus_id]);
        $bus = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Edit Bus - LineBus Admin</title>
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
                <h1>Edit Bus</h1>
                <div>
                    <a href="bus_details.php?id=<?= $bus_id ?>" class="btn btn-secondary">
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
                            <label for="bus_number">Bus Number</label>
                            <input type="text" id="bus_number" name="bus_number" class="form-control" 
                                   value="<?= htmlspecialchars($bus['bus_number']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="departure_city">Departure City</label>
                            <input type="text" id="departure_city" name="departure_city" class="form-control" 
                                   value="<?= htmlspecialchars($bus['departure_city']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="arrival_city">Arrival City</label>
                            <input type="text" id="arrival_city" name="arrival_city" class="form-control" 
                                   value="<?= htmlspecialchars($bus['arrival_city']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" class="form-control" 
                                   value="<?= htmlspecialchars($bus['date']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="departure_time">Departure Time</label>
                            <input type="time" id="departure_time" name="departure_time" class="form-control" 
                                   value="<?= htmlspecialchars($bus['departure_time']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="arrival_time">Arrival Time</label>
                            <input type="time" id="arrival_time" name="arrival_time" class="form-control" 
                                   value="<?= htmlspecialchars($bus['arrival_time']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price (€)</label>
                            <input type="number" id="price" name="price" class="form-control" 
                                   step="0.01" min="0" value="<?= htmlspecialchars($bus['price']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="total_seats">Total Seats</label>
                            <input type="number" id="total_seats" name="total_seats" class="form-control" 
                                   min="1" value="<?= htmlspecialchars($bus['total_seats']) ?>" required>
                            <small class="text-muted">Currently available: <?= $bus['available_seats'] ?></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="active" <?= $bus['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $bus['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="maintenance" <?= $bus['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            </select>
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
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
    
    try {
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
        
        // Check if bus with same number already exists on the same date
        $stmt = $pdo->prepare("SELECT id FROM buses WHERE bus_number = ? AND date = ? LIMIT 1");
        $stmt->execute([$bus_number, $date]);
        if ($stmt->fetch()) {
            throw new Exception('Bus with this number already exists on the selected date');
        }
        
        // Insert new bus
        $stmt = $pdo->prepare("INSERT INTO buses (bus_number, departure_city, arrival_city, departure_time, arrival_time, date, price, available_seats, total_seats, status, created_at) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())");
        $stmt->execute([
            $bus_number,
            $departure_city,
            $arrival_city,
            $departure_time,
            $arrival_time,
            $date,
            $price,
            $total_seats,
            $total_seats
        ]);
        
        // Redirect to manage buses page
        header('Location: manage_buses.php?success=Bus added successfully');
        exit();
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get available routes for dropdown
try {
    $routes = $pdo->query("SELECT DISTINCT departure_city, arrival_city FROM buses")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $routes = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Bus - LineBus Admin</title>
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

        /* Form Container */
        .form-container {
            background: var(--light);
            border-radius: 10px;
            padding: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: 0 auto;
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
                <li><a href="manage_buses.php" class="active"><i class="fas fa-bus"></i> Buses</a></li>
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
                <h1>Add New Bus</h1>
                <div>
                    <a href="manage_buses.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Buses
                    </a>
                </div>
            </div>

            <div class="form-container">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="bus_number">Bus Number</label>
                            <input type="text" id="bus_number" name="bus_number" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="date">Date</label>
                            <input type="date" id="date" name="date" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="departure_city">Departure City</label>
                            <select id="departure_city" name="departure_city" class="form-control" required>
                                <option value="">Select Departure City</option>
                                <?php foreach ($routes as $route): ?>
                                    <option value="<?= htmlspecialchars($route['departure_city']) ?>">
                                        <?= htmlspecialchars($route['departure_city']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="arrival_city">Arrival City</label>
                            <select id="arrival_city" name="arrival_city" class="form-control" required>
                                <option value="">Select Arrival City</option>
                                <?php foreach ($routes as $route): ?>
                                    <option value="<?= htmlspecialchars($route['arrival_city']) ?>">
                                        <?= htmlspecialchars($route['arrival_city']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="departure_time">Departure Time</label>
                            <input type="time" id="departure_time" name="departure_time" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="arrival_time">Arrival Time</label>
                            <input type="time" id="arrival_time" name="arrival_time" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price (€)</label>
                            <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="total_seats">Total Seats</label>
                            <input type="number" id="total_seats" name="total_seats" class="form-control" min="1" required>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="reset" class="btn btn-secondary">Reset</button>
                        <button type="submit" class="btn btn-primary">Add Bus</button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Simple JavaScript to enhance the form
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to today
            const dateInput = document.getElementById('date');
            const today = new Date().toISOString().split('T')[0];
            dateInput.min = today;
            
            // You could add more interactive features here, like:
            // - Auto-calculating arrival time based on route duration
            // - Validating that arrival time is after departure time
            // - Fetching suggested price based on route
        });
    </script>
</body>
</html>
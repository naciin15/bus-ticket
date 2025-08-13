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

// Initialize variables
$error = '';
$success = '';

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

    // Fetch available buses for the dropdown
    $buses = $pdo->query("SELECT id, bus_number, departure_city, arrival_city FROM buses WHERE date >= CURDATE() ORDER BY date, departure_time")->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $passengers = $_POST['passengers'] ?? 1;
        $bus_id = $_POST['bus_id'] ?? $booking['bus_id'];
        $status = $_POST['status'] ?? $booking['status'];
        $payment_status = $_POST['payment_status'] ?? $booking['payment_status'];
        $luggage_count = $_POST['luggage_count'] ?? $booking['luggage_count'];
        $notes = $_POST['notes'] ?? $booking['notes'];
        
        // Validate inputs
        if ($passengers < 1) {
            throw new Exception('Number of passengers must be at least 1');
        }
        
        // Calculate new total price
        $bus_stmt = $pdo->prepare("SELECT price FROM buses WHERE id = ?");
        $bus_stmt->execute([$bus_id]);
        $bus_price = $bus_stmt->fetchColumn();
        
        $luggage_fee = $luggage_count * 5; // Example: €5 per luggage
        $total_price = ($bus_price * $passengers) + $luggage_fee;
        
        // Update booking
        $update_stmt = $pdo->prepare("
            UPDATE bookings 
            SET passengers = ?, bus_id = ?, total_price = ?, status = ?, payment_status = ?
            WHERE id = ?
        ");
        $update_stmt->execute([$passengers, $bus_id, $total_price, $status, $payment_status, $booking_id]);
        
        // Update booking extras
        $extras_stmt = $pdo->prepare("
            INSERT INTO booking_extras (booking_id, luggage_count, luggage_fee, notes)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                luggage_count = VALUES(luggage_count),
                luggage_fee = VALUES(luggage_fee),
                notes = VALUES(notes)
        ");
        $extras_stmt->execute([$booking_id, $luggage_count, $luggage_fee, $notes]);
        
        $success = 'Booking updated successfully';
        
        // Refresh booking data
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <title>Edit Booking - LineBus Admin</title>
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

        /* Price Preview */
        .price-preview {
            background: var(--light-bg);
            padding: 1rem;
            border-radius: 4px;
            margin-top: 1rem;
        }

        .price-breakdown {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: var(--medium);
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
                <h1>Edit Booking</h1>
                <div>
                    <a href="booking_details.php?id=<?= $booking_id ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Details
                    </a>
                </div>
            </div>

            <div class="form-container">
                <?php if (isset($success) && $success): ?>
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
                            <label for="bus_id">Bus</label>
                            <select id="bus_id" name="bus_id" class="form-control" required>
                                <?php foreach ($buses as $bus): ?>
                                    <option value="<?= $bus['id'] ?>" 
                                        <?= $bus['id'] == $booking['bus_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($bus['bus_number']) ?> - 
                                        <?= htmlspecialchars($bus['departure_city']) ?> to 
                                        <?= htmlspecialchars($bus['arrival_city']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="passengers">Passengers</label>
                            <input type="number" id="passengers" name="passengers" class="form-control" 
                                   min="1" value="<?= $booking['passengers'] ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="confirmed" <?= $booking['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="cancelled" <?= $booking['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                <option value="completed" <?= $booking['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_status">Payment Status</label>
                            <select id="payment_status" name="payment_status" class="form-control" required>
                                <option value="pending" <?= $booking['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="paid" <?= $booking['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                                <option value="refunded" <?= $booking['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="luggage_count">Luggage Count</label>
                            <input type="number" id="luggage_count" name="luggage_count" class="form-control" 
                                   min="0" value="<?= $booking['luggage_count'] ?? 0 ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Special Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="4"><?= htmlspecialchars($booking['notes'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="price-preview">
                        <div><strong>Total Price: €<?= number_format($booking['total_price'], 2) ?></strong></div>
                        <div class="price-breakdown">
                            (Base price: €<?= number_format($booking['price'], 2) ?> × <?= $booking['passengers'] ?> passengers + 
                            €<?= number_format($booking['luggage_fee'] ?? 0, 2) ?> luggage fees)
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
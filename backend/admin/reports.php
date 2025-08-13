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

// Get filter parameters
$report_type = $_GET['report_type'] ?? 'bookings';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$bus_id = $_GET['bus_id'] ?? '';

try {
    // Initialize report data
    $report_data = [];
    $chart_data = [];
    $total_value = 0;
    $report_title = '';

    // Generate report based on type
    switch ($report_type) {
        case 'bookings':
            $report_title = 'Booking Report';
            
            // Query for bookings report
            $sql = "SELECT 
                        b.id, 
                        b.booking_date, 
                        u.username, 
                        u.email, 
                        bs.bus_number,
                        CONCAT(bs.departure_city, ' → ', bs.arrival_city) AS route,
                        b.passengers, 
                        b.total_price,
                        b.status
                    FROM bookings b
                    JOIN users u ON b.user_id = u.id
                    JOIN buses bs ON b.bus_id = bs.id
                    WHERE DATE(b.booking_date) BETWEEN ? AND ?";
            
            $params = [$date_from, $date_to];
            
            if ($bus_id) {
                $sql .= " AND b.bus_id = ?";
                $params[] = $bus_id;
            }
            
            $sql .= " ORDER BY b.booking_date DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare chart data (bookings by day)
            $chart_sql = "SELECT 
                            DATE(booking_date) AS day, 
                            COUNT(*) AS bookings_count,
                            SUM(total_price) AS daily_revenue
                          FROM bookings
                          WHERE DATE(booking_date) BETWEEN ? AND ?";
            
            $chart_params = [$date_from, $date_to];
            
            if ($bus_id) {
                $chart_sql .= " AND bus_id = ?";
                $chart_params[] = $bus_id;
            }
            
            $chart_sql .= " GROUP BY DATE(booking_date) ORDER BY day";
            
            $chart_stmt = $pdo->prepare($chart_sql);
            $chart_stmt->execute($chart_params);
            $chart_raw_data = $chart_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format chart data for Chart.js
            $chart_labels = [];
            $chart_bookings = [];
            $chart_revenue = [];
            
            foreach ($chart_raw_data as $row) {
                $chart_labels[] = date('M j', strtotime($row['day']));
                $chart_bookings[] = $row['bookings_count'];
                $chart_revenue[] = $row['daily_revenue'];
            }
            
            $chart_data = [
                'labels' => $chart_labels,
                'datasets' => [
                    [
                        'label' => 'Bookings',
                        'data' => $chart_bookings,
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'borderWidth' => 1
                    ],
                    [
                        'label' => 'Revenue (€)',
                        'data' => $chart_revenue,
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                        'borderColor' => 'rgba(75, 192, 192, 1)',
                        'borderWidth' => 1,
                        'type' => 'line',
                        'yAxisID' => 'y1'
                    ]
                ]
            ];
            
            // Calculate total value
            $total_value = array_sum($chart_revenue);
            break;
            
        case 'buses':
            $report_title = 'Bus Utilization Report';
            
            // Query for buses report
            $sql = "SELECT 
                        b.id,
                        b.bus_number,
                        CONCAT(b.departure_city, ' → ', b.arrival_city) AS route,
                        b.date,
                        b.departure_time,
                        b.arrival_time,
                        b.total_seats,
                        b.available_seats,
                        b.price,
                        COUNT(bk.id) AS bookings_count,
                        (b.total_seats - b.available_seats) AS seats_sold,
                        ROUND(((b.total_seats - b.available_seats) / b.total_seats) * 100, 2) AS utilization_percent
                    FROM buses b
                    LEFT JOIN bookings bk ON b.id = bk.bus_id
                    WHERE b.date BETWEEN ? AND ?";
            
            $params = [$date_from, $date_to];
            
            if ($bus_id) {
                $sql .= " AND b.id = ?";
                $params[] = $bus_id;
            }
            
            $sql .= " GROUP BY b.id ORDER BY b.date, b.departure_time";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare chart data (utilization by bus)
            $chart_labels = [];
            $chart_utilization = [];
            
            foreach ($report_data as $bus) {
                $chart_labels[] = $bus['bus_number'] . ' (' . date('M j', strtotime($bus['date'])) . ')';
                $chart_utilization[] = $bus['utilization_percent'];
            }
            
            $chart_data = [
                'labels' => $chart_labels,
                'datasets' => [
                    [
                        'label' => 'Utilization %',
                        'data' => $chart_utilization,
                        'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                        'borderColor' => 'rgba(255, 99, 132, 1)',
                        'borderWidth' => 1
                    ]
                ]
            ];
            
            // Calculate average utilization
            if (count($report_data) > 0) {
                $total_value = array_sum($chart_utilization) / count($chart_utilization);
            }
            break;
            
        case 'revenue':
            $report_title = 'Revenue Report';
            
            // Query for revenue by route
            $sql = "SELECT 
                        CONCAT(b.departure_city, ' → ', b.arrival_city) AS route,
                        COUNT(bk.id) AS bookings_count,
                        SUM(bk.total_price) AS total_revenue,
                        AVG(bk.total_price) AS avg_revenue
                    FROM bookings bk
                    JOIN buses b ON bk.bus_id = b.id
                    WHERE DATE(bk.booking_date) BETWEEN ? AND ?";
            
            $params = [$date_from, $date_to];
            
            if ($bus_id) {
                $sql .= " AND bk.bus_id = ?";
                $params[] = $bus_id;
            }
            
            $sql .= " GROUP BY b.departure_city, b.arrival_city ORDER BY total_revenue DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Prepare chart data (revenue by route)
            $chart_labels = [];
            $chart_revenue = [];
            
            foreach ($report_data as $route) {
                $chart_labels[] = $route['route'];
                $chart_revenue[] = $route['total_revenue'];
            }
            
            $chart_data = [
                'labels' => $chart_labels,
                'datasets' => [
                    [
                        'label' => 'Revenue (€)',
                        'data' => $chart_revenue,
                        'backgroundColor' => [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)'
                        ],
                        'borderColor' => [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 206, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        'borderWidth' => 1
                    ]
                ]
            ];
            
            // Calculate total revenue
            $total_value = array_sum($chart_revenue);
            break;
    }
    
    // Get list of buses for filter dropdown
    $buses = $pdo->query("SELECT id, bus_number FROM buses ORDER BY bus_number")->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - LineBus Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        /* Report Summary */
        .report-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-card {
            background: var(--light);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .summary-card h3 {
            font-size: 1rem;
            color: var(--medium);
            margin-bottom: 0.5rem;
        }

        .summary-card .value {
            font-size: 2rem;
            font-weight: 600;
            color: var(--dark);
        }

        .summary-card .value.revenue {
            color: var(--success);
        }

        .summary-card .value.utilization {
            color: var(--info);
        }

        /* Chart Container */
        .chart-container {
            background: var(--light);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            height: 400px;
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

        /* Utilization Bar */
        .utilization-bar {
            height: 20px;
            background-color: #f0f0f0;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
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

        /* Export Buttons */
        .export-buttons {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            justify-content: flex-end;
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

            .export-buttons {
                flex-direction: column;
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
                <li><a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><a href="/busbooking/backend/admin/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="header">
                <h1>Reports</h1>
                <div>
                    <span><?= date('l, F j, Y') ?></span>
                </div>
            </div>

            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="filter-group">
                        <label for="report_type">Report Type</label>
                        <select id="report_type" name="report_type" class="form-control">
                            <option value="bookings" <?= $report_type === 'bookings' ? 'selected' : '' ?>>Bookings</option>
                            <option value="buses" <?= $report_type === 'buses' ? 'selected' : '' ?>>Bus Utilization</option>
                            <option value="revenue" <?= $report_type === 'revenue' ? 'selected' : '' ?>>Revenue</option>
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
                        <label for="bus_id">Bus Filter</label>
                        <select id="bus_id" name="bus_id" class="form-control">
                            <option value="">All Buses</option>
                            <?php foreach ($buses as $bus): ?>
                                <option value="<?= $bus['id'] ?>" <?= $bus_id == $bus['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($bus['bus_number']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="align-self: flex-end;">
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                </form>
            </div>

            <div class="report-summary">
                <div class="summary-card">
                    <h3>Report Type</h3>
                    <div class="value"><?= htmlspecialchars(ucfirst($report_type)) ?> Report</div>
                </div>
                
                <div class="summary-card">
                    <h3>Date Range</h3>
                    <div class="value"><?= date('M j, Y', strtotime($date_from)) ?> - <?= date('M j, Y', strtotime($date_to)) ?></div>
                </div>
                
                <div class="summary-card">
                    <h3>
                        <?= $report_type === 'bookings' ? 'Total Revenue' : 
                           ($report_type === 'buses' ? 'Avg Utilization' : 'Total Revenue') ?>
                    </h3>
                    <div class="value <?= $report_type === 'bookings' || $report_type === 'revenue' ? 'revenue' : 'utilization' ?>">
                        <?php if ($report_type === 'bookings' || $report_type === 'revenue'): ?>
                            €<?= number_format($total_value, 2) ?>
                        <?php elseif ($report_type === 'buses'): ?>
                            <?= number_format($total_value, 2) ?>%
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="export-buttons">
                <a href="#" class="btn btn-secondary">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
                <a href="#" class="btn btn-secondary">
                    <i class="fas fa-file-excel"></i> Export Excel
                </a>
                <a href="#" class="btn btn-secondary">
                    <i class="fas fa-file-csv"></i> Export CSV
                </a>
            </div>

            <div class="chart-container">
                <canvas id="reportChart"></canvas>
            </div>

            <h2 style="margin-bottom: 1rem;"><?= $report_title ?></h2>
            
            <?php if (empty($report_data)): ?>
                <div style="background: var(--light); padding: 2rem; border-radius: 10px; text-align: center;">
                    No data found for the selected criteria
                </div>
            <?php else: ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <?php if ($report_type === 'bookings'): ?>
                                <th>Booking ID</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Route</th>
                                <th>Bus</th>
                                <th>Passengers</th>
                                <th>Amount</th>
                                <th>Status</th>
                            <?php elseif ($report_type === 'buses'): ?>
                                <th>Bus</th>
                                <th>Route</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Seats</th>
                                <th>Utilization</th>
                                <th>Bookings</th>
                            <?php elseif ($report_type === 'revenue'): ?>
                                <th>Route</th>
                                <th>Bookings</th>
                                <th>Total Revenue</th>
                                <th>Avg. Revenue</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data as $row): ?>
                            <tr>
                                <?php if ($report_type === 'bookings'): ?>
                                    <td>#<?= $row['id'] ?></td>
                                    <td><?= date('M j, Y', strtotime($row['booking_date'])) ?></td>
                                    <td>
                                        <div><?= htmlspecialchars($row['username']) ?></div>
                                        <small><?= htmlspecialchars($row['email']) ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($row['route']) ?></td>
                                    <td><?= htmlspecialchars($row['bus_number']) ?></td>
                                    <td><?= $row['passengers'] ?></td>
                                    <td>€<?= number_format($row['total_price'], 2) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= $row['status'] ?>">
                                            <?= ucfirst($row['status']) ?>
                                        </span>
                                    </td>
                                <?php elseif ($report_type === 'buses'): ?>
                                    <td><?= htmlspecialchars($row['bus_number']) ?></td>
                                    <td><?= htmlspecialchars($row['route']) ?></td>
                                    <td><?= date('M j, Y', strtotime($row['date'])) ?></td>
                                    <td>
                                        <?= date('g:i a', strtotime($row['departure_time'])) ?> - 
                                        <?= date('g:i a', strtotime($row['arrival_time'])) ?>
                                    </td>
                                    <td><?= $row['seats_sold'] ?>/<?= $row['total_seats'] ?></td>
                                    <td>
                                        <div class="utilization-bar">
                                            <div class="utilization-fill" style="width: <?= $row['utilization_percent'] ?>%"></div>
                                            <span class="utilization-text"><?= $row['utilization_percent'] ?>%</span>
                                        </div>
                                    </td>
                                    <td><?= $row['bookings_count'] ?></td>
                                <?php elseif ($report_type === 'revenue'): ?>
                                    <td><?= htmlspecialchars($row['route']) ?></td>
                                    <td><?= $row['bookings_count'] ?></td>
                                    <td>€<?= number_format($row['total_revenue'], 2) ?></td>
                                    <td>€<?= number_format($row['avg_revenue'], 2) ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get chart data from PHP
            const chartData = <?= json_encode($chart_data) ?>;
            
            // Create chart
            const ctx = document.getElementById('reportChart').getContext('2d');
            const chart = new Chart(ctx, {
                type: 'bar',
                data: chartData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Count'
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: '<?= $report_type === 'bookings' ? 'true' : 'false' ?>',
                            position: 'right',
                            beginAtZero: true,
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Revenue (€)'
                            }
                        }
                    },
                    plugins: {
                        title: {
                            display: true,
                            text: '<?= $report_title ?> Trends',
                            font: {
                                size: 16
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.dataset.yAxisID === 'y1') {
                                        label += '€' + context.parsed.y.toFixed(2);
                                    } else {
                                        label += context.parsed.y;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
            
            // Update chart when report type changes
            document.getElementById('report_type').addEventListener('change', function() {
                document.querySelector('form').submit();
            });
        });
    </script>
</body>
</html>
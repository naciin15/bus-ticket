<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

// Check admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: /login.php");
    exit();
}

// Get unread notifications count
$notification_count = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
    $stmt->execute();
    $notification_count = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Notification count error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Bus Booking System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-dashboard">
    <div class="wrapper">
        <!-- Sidebar will be included here -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <div class="main">
            <nav class="navbar navbar-expand navbar-light bg-white shadow-sm">
                <div class="container-fluid px-4">
                    <button class="btn btn-link d-md-none" type="button" id="sidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-bell-fill"></i>
                                <?php if ($notification_count > 0): ?>
                                    <span class="badge bg-danger rounded-pill"><?= $notification_count ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php
                                try {
                                    $stmt = $conn->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 5");
                                    $stmt->execute();
                                    $notifications = $stmt->fetchAll();
                                    
                                    if (empty($notifications)) {
                                        echo '<li><span class="dropdown-item text-muted">No new notifications</span></li>';
                                    } else {
                                        foreach ($notifications as $notification) {
                                            echo '<li><a class="dropdown-item" href="#">' . htmlspecialchars($notification['message']) . '</a></li>';
                                        }
                                    }
                                } catch (PDOException $e) {
                                    error_log("Notification fetch error: " . $e->getMessage());
                                }
                                ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-center" href="/admin/notifications.php">View All</a></li>
                            </ul>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_name']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/admin/profile.php"><i class="bi bi-person"></i> Profile</a></li>
                                <li><a class="dropdown-item" href="/admin/settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <main class="content px-4 py-4">
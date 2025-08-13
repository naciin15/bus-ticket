<?php
// Ensure config is loaded
require __DIR__ . '/../../config.php';

// Check session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: /busbooking/admin/login.php');
    exit();
}

try {
    // Use $pdo instead of $conn
    $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();
} catch (PDOException $e) {
    die("Database error in sidebar: " . $e->getMessage());
}
?>
<link rel="stylesheet" href="backend/admin/assets/css/style.css">
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
        <li><a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="bookings.php" class="<?= basename($_SERVER['PHP_SELF']) === 'bookings.php' ? 'active' : '' ?>"><i class="fas fa-ticket-alt"></i> Bookings</a></li>
        <li><a href="buses.php" class="<?= basename($_SERVER['PHP_SELF']) === 'buses.php' ? 'active' : '' ?>"><i class="fas fa-bus"></i> Buses</a></li>
        <li><a href="users.php" class="<?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>"><i class="fas fa-users"></i> Users</a></li>
        <li><a href="reports.php" class="<?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <li><a href="settings.php" class="<?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>"><i class="fas fa-cog"></i> Settings</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</aside>
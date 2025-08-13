<div class="sidebar bg-primary text-white">
    <div class="sidebar-brand d-flex align-items-center justify-content-center">
        <div class="sidebar-brand-icon">
            <i class="bi bi-bus-front"></i>
        </div>
        <div class="sidebar-brand-text mx-3">Bus Booking</div>
    </div>
    <hr class="sidebar-divider my-0">
    <li class="nav-item <?php echo $activePage == 'dashboard' ? 'active' : ''; ?>">
        <a class="nav-link" href="index.php">
            <i class="bi bi-speedometer2"></i>
            <span>Dashboard</span>
        </a>
    </li>
    <hr class="sidebar-divider">
    <div class="sidebar-heading">Management</div>
    <li class="nav-item <?php echo $activePage == 'buses' ? 'active' : ''; ?>">
        <a class="nav-link" href="buses.php">
            <i class="bi bi-bus-front"></i>
            <span>Buses</span>
        </a>
    </li>
    <li class="nav-item <?php echo $activePage == 'bookings' ? 'active' : ''; ?>">
        <a class="nav-link" href="bookings.php">
            <i class="bi bi-journal-text"></i>
            <span>Bookings</span>
        </a>
    </li>
    <li class="nav-item <?php echo $activePage == 'users' ? 'active' : ''; ?>">
        <a class="nav-link" href="users.php">
            <i class="bi bi-people-fill"></i>
            <span>Users</span>
        </a>
    </li>
    <hr class="sidebar-divider">
    <div class="sidebar-heading">System</div>
    <li class="nav-item <?php echo $activePage == 'reports' ? 'active' : ''; ?>">
        <a class="nav-link" href="reports.php">
            <i class="bi bi-graph-up"></i>
            <span>Reports</span>
        </a>
    </li>
    <li class="nav-item <?php echo $activePage == 'settings' ? 'active' : ''; ?>">
        <a class="nav-link" href="settings.php">
            <i class="bi bi-gear-fill"></i>
            <span>Settings</span>
        </a>
    </li>
</div>
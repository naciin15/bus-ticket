<?php
require_once __DIR__ . '../config.php'; // Adjust path as needed

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function getTotalCount($pdo, $table) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM $table");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['count'];
}

function getTotalRevenue($pdo) {
    $stmt = $pdo->prepare("SELECT SUM(total_price) as revenue FROM bookings WHERE payment_status = 'paid'");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result['revenue'] ?? 0;
}

// Update other functions to use $pdo instead of $conn
// ... rest of your functions ...


function getRecentBookings($conn, $limit = 5) {
    $stmt = $conn->prepare("
        SELECT b.*, u.username, bus.bus_number 
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        JOIN buses bus ON b.bus_id = bus.id
        ORDER BY b.booking_date DESC
        LIMIT ?
    ");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = $row;
    }
    return $bookings;
}

function getSystemAlerts($conn) {
    $stmt = $conn->prepare("SELECT * FROM system_errors ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $alerts = [];
    while ($row = $result->fetch_assoc()) {
        $alerts[] = $row;
    }
    return $alerts;
}

function getAllBuses($conn) {
    $stmt = $conn->prepare("SELECT * FROM buses ORDER BY date, departure_time");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $buses = [];
    while ($row = $result->fetch_assoc()) {
        $buses[] = $row;
    }
    return $buses;
}

function getBusById($conn, $id) {
    $stmt = $conn->prepare("SELECT * FROM buses WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

function getStatusBadge($status) {
    switch ($status) {
        case 'active':
        case 'confirmed':
        case 'paid':
            return 'success';
        case 'inactive':
        case 'cancelled':
            return 'danger';
        case 'maintenance':
        case 'pending':
            return 'warning';
        case 'completed':
            return 'info';
        default:
            return 'secondary';
    }
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . " seconds ago";
    } elseif ($diff < 3600) {
        return round($diff / 60) . " minutes ago";
    } elseif ($diff < 86400) {
        return round($diff / 3600) . " hours ago";
    } elseif ($diff < 604800) {
        return round($diff / 86400) . " days ago";
    } else {
        return date("M j, Y", $time);
    }
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    require_once 'auth.php';
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => 'Invalid action'];
    
    switch ($_POST['action']) {
        case 'get_bus':
            if (isset($_POST['bus_id'])) {
                $bus = getBusById($conn, intval($_POST['bus_id']));
                if ($bus) {
                    $response = ['success' => true, 'data' => $bus];
                } else {
                    $response = ['success' => false, 'message' => 'Bus not found'];
                }
            }
            break;
    }
    
    echo json_encode($response);
    exit;
}
?>
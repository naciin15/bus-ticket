<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/db_connect.php';

// Validate input
$required = ['from', 'to', 'date', 'passengers'];
foreach ($required as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['error' => "Missing required field: $field"]);
        exit;
    }
}

// Sanitize inputs
$from = filter_input(INPUT_POST, 'from', FILTER_SANITIZE_STRING);
$to = filter_input(INPUT_POST, 'to', FILTER_SANITIZE_STRING);
$date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
$passengers = filter_input(INPUT_POST, 'passengers', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 10]
]);

if ($passengers === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid number of passengers']);
    exit;
}

// Prepare SQL with prepared statements to prevent SQL injection
try {
    $stmt = $pdo->prepare("
        SELECT b.* 
        FROM buses b
        WHERE b.departure_city = :from 
        AND b.arrival_city = :to 
        AND b.date = :date 
        AND b.available_seats >= :passengers
        AND b.status = 'active'
    ");
    
    $stmt->execute([
        ':from' => $from,
        ':to' => $to,
        ':date' => $date,
        ':passengers' => $passengers
    ]);
    
    $buses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($buses)) {
        echo json_encode(['message' => 'No buses available for your selected criteria']);
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $buses,
        'count' => count($buses)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
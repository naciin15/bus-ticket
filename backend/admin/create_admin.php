
<?php
require __DIR__ . '/../backend/php/config.php';

// Only allow this to be run from command line or by authenticated admin
if (php_sapi_name() !== 'cli' && !(isset($_SESSION['user_id']) && $_SESSION['user_role'] !== 'admin')) {
    header("HTTP/1.1 403 Forbidden");
    exit("Access denied");
}

// Function to create admin user
function createAdminUser($username, $email, $password, $firstName, $lastName) {
    global $conn;
    
    try {
        // Check if user already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
        $stmt->execute([$email, $username]);
        
        if ($stmt->rowCount() > 0) {
            return "User with this email or username already exists";
        }
        
        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // Insert admin user
        $stmt = $conn->prepare("INSERT INTO users (username, email, password_hash, first_name, last_name, role, is_active, created_at) 
                               VALUES (?, ?, ?, ?, ?, 'admin', TRUE, NOW())");
        $stmt->execute([$username, $email, $hashedPassword, $firstName, $lastName]);
        
        return "Admin user created successfully!";
        
    } catch (PDOException $e) {
        return "Error: " . $e->getMessage();
    }
}

// Handle command line execution
if (php_sapi_name() === 'cli') {
    if ($argc < 6) {
        echo "Usage: php create_admin.php <username> <email> <password> <first_name> <last_name>\n";
        exit(1);
    }
    
    $result = createAdminUser($argv[1], $argv[2], $argv[3], $argv[4], $argv[5]);
    echo $result . "\n";
    exit(0);
}

// Handle web form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    
    $result = createAdminUser($username, $email, $password, $firstName, $lastName);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Admin User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h3 class="text-center">Create Admin User</h3>
                    </div>
                    <div class="card-body">
                        <?php if (isset($result)): ?>
                            <div class="alert alert-info"><?= htmlspecialchars($result) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <div class="mb-3">
                                <label for="first_name" class="form-label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="mb-3">
                                <label for="last_name" class="form-label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Create Admin</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
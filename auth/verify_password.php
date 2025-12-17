<?php
require_once '../config/database.php';
require_once '../config/session_config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Not authenticated'
    ]);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
    exit();
}

// Get and validate input
$password = $_POST['password'] ?? '';

if (empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Password is required'
    ]);
    exit();
}

try {
    // Get user's hashed password from database
    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'User not found'
        ]);
        exit();
    }
    
    // Verify password
    if (password_verify($password, $user['password'])) {
        // Password is correct, set a verification token that's valid for 5 minutes
        $verificationToken = bin2hex(random_bytes(32));
        $_SESSION['password_verified'] = [
            'token' => $verificationToken,
            'expires' => time() + 300 // 5 minutes from now
        ];
        
        echo json_encode([
            'success' => true,
            'token' => $verificationToken
        ]);
    } else {
        // Password is incorrect
        echo json_encode([
            'success' => false,
            'message' => 'Incorrect password'
        ]);
    }
} catch (PDOException $e) {
    error_log('Password verification error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again.'
    ]);
}

<?php
require_once '../config/session_config.php';

// This endpoint is called via AJAX to keep the session alive
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reset the session warning flag
    if (isset($_SESSION['session_warning_shown'])) {
        unset($_SESSION['session_warning_shown']);
    }
    
    // Update last activity time
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
        echo json_encode(['status' => 'success', 'message' => 'Session extended']);
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    }
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
}

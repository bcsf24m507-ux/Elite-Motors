<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Store any messages before destroying session
$message = '';
$message_type = 'info';

// Check for timeout
if (isset($_GET['timeout']) && $_GET['timeout'] == 1) {
    $message = 'Your session has timed out due to inactivity. Please log in again.';
    $message_type = 'warning';
}

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// Unset all session variables
$_SESSION = [];

// Delete the session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destroy the session
session_destroy();

// Redirect to login page with message if any
$redirect = '../index.php';
if (!empty($message)) {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $message_type;
    $redirect = 'login.php';
}

header('Location: ' . $redirect);
exit();

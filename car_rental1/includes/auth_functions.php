<?php
/**
 * Authentication Helper Functions
 */

/**
 * Check if the user's password has been verified for a specific action
 * @param string $action Optional action name for additional verification
 * @return bool True if verified, false otherwise
 */
function is_password_verified($action = null) {
    // Check if verification exists and hasn't expired
    if (isset($_SESSION['password_verified']) && 
        isset($_SESSION['password_verified']['expires']) && 
        $_SESSION['password_verified']['expires'] > time()) {
        
        // If an action is specified, check if it matches
        if ($action !== null) {
            return isset($_SESSION['password_verified']['action']) && 
                   $_SESSION['password_verified']['action'] === $action;
        }
        
        return true;
    }
    
    return false;
}

/**
 * Require password verification for sensitive actions
 * @param string $action Optional action name for additional verification
 * @param callable $onSuccess Callback function to execute if verified
 * @param string $redirectUrl URL to redirect to if not verified
 */
function require_password_verification($action = null, $onSuccess = null, $redirectUrl = null) {
    // If already verified, execute the success callback if provided
    if (is_password_verified($action)) {
        if (is_callable($onSuccess)) {
            return $onSuccess();
        }
        return true;
    }
    
    // Store the current URL for redirection after verification
    if ($redirectUrl === null) {
        $redirectUrl = $_SERVER['REQUEST_URI'] ?? '/';
    }
    
    // Store the action in session if provided
    if ($action !== null) {
        $_SESSION['pending_action'] = [
            'action' => $action,
            'redirect' => $redirectUrl
        ];
    } else {
        $_SESSION['pending_redirect'] = $redirectUrl;
    }
    
    // Redirect to password verification page
    header('Location: /auth/verify_password.php?redirect=' . urlencode($redirectUrl));
    exit();
}

/**
 * Mark a password as verified for a specific action
 * @param string $action Optional action name
 * @param int $lifetime Optional lifetime in seconds (default: 300 seconds / 5 minutes)
 * @return string Verification token
 */
function mark_password_verified($action = null, $lifetime = 300) {
    $token = bin2hex(random_bytes(32));
    
    $_SESSION['password_verified'] = [
        'token' => $token,
        'expires' => time() + $lifetime,
        'action' => $action,
        'verified_at' => time()
    ];
    
    return $token;
}

/**
 * Clear password verification
 */
function clear_password_verification() {
    unset($_SESSION['password_verified']);
    unset($_SESSION['pending_action']);
    unset($_SESSION['pending_redirect']);
}

/**
 * Get the remaining time for the current verification in seconds
 * @return int Remaining seconds, or 0 if not verified
 */
function get_verification_remaining_time() {
    if (isset($_SESSION['password_verified']['expires'])) {
        $remaining = $_SESSION['password_verified']['expires'] - time();
        return max(0, $remaining);
    }
    return 0;
}

/**
 * Get a human-readable string of remaining verification time
 * @return string Human-readable time remaining
 */
function get_verification_time_remaining_string() {
    $seconds = get_verification_remaining_time();
    
    if ($seconds <= 0) {
        return 'expired';
    }
    
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    
    if ($minutes > 0) {
        return $minutes . 'm ' . $seconds . 's';
    }
    
    return $seconds . 's';
}

<?php
// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', 1800); // 30 minutes

// Set session name to prevent session fixation
$session_name = 'elite_motors_sid';
session_name($session_name);

// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user session is valid
 */
function validate_session() {
    $inactive_timeout = 1800; // 30 minutes in seconds
    $warning_time = 300; // 5 minutes before timeout
    
    // Check if user is logged in
    if (isset($_SESSION['user_id'])) {
        $current_time = time();
        $last_activity = $_SESSION['last_activity'] ?? 0;
        $time_since_last_activity = $current_time - $last_activity;
        
        // Update last activity time
        $_SESSION['last_activity'] = $current_time;
        
        // Check for session timeout
        if ($time_since_last_activity > $inactive_timeout) {
            // Session expired
            session_unset();
            session_destroy();
            
            // Set session expired message
            $_SESSION['session_expired'] = true;
            
            // Redirect to login with a message
            $redirect = $_SERVER['PHP_SELF'] ?? 'index.php';
            header('Location: /auth/login.php?expired=1&redirect=' . urlencode($redirect));
            exit();
        }
        
        // Add JavaScript to handle session timeout warning
        if (!isset($_SESSION['session_warning_shown'])) {
            $remaining_time = $inactive_timeout - $time_since_last_activity;
            if ($remaining_time <= $warning_time) {
                $_SESSION['session_warning_shown'] = true;
                add_session_timeout_script($remaining_time);
            }
        }
    }
}

/**
 * Add JavaScript for session timeout warning
 */
function add_session_timeout_script($remaining_time) {
    $warning_time = 300; // 5 minutes in seconds
    $logout_url = '/auth/logout.php?timeout=1';
    
    $script = <<<EOT
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const warningTime = {$warning_time} * 1000; // 5 minutes in milliseconds
        const remainingTime = {$remaining_time} * 1000; // Remaining time in milliseconds
        const logoutUrl = '{$logout_url}';
        let warningShown = false;
        
        // Show warning when time is running out
        const warningTimeout = setTimeout(showWarning, remainingTime - warningTime);
        
        // Logout when time is up
        const logoutTimeout = setTimeout(logout, remainingTime);
        
        // Reset timers on user activity
        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(event => {
            window.addEventListener(event, resetTimers);
        });
        
        function resetTimers() {
            // Clear existing timeouts
            clearTimeout(warningTimeout);
            clearTimeout(logoutTimeout);
            
            // Reset warning flag
            warningShown = false;
            
            // Hide warning if shown
            const warningModal = document.getElementById('sessionWarningModal');
            if (warningModal) {
                warningModal.style.display = 'none';
            }
            
            // Reset session warning flag via AJAX
            fetch('/auth/keepalive.php', {
                method: 'POST',
                credentials: 'same-origin'
            });
        }
        
        function showWarning() {
            if (!warningShown) {
                warningShown = true;
                
                // Create or show warning modal
                let modal = document.getElementById('sessionWarningModal');
                if (!modal) {
                    modal = document.createElement('div');
                    modal.id = 'sessionWarningModal';
                    modal.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: #fff3cd;
                        border: 1px solid #ffeeba;
                        border-radius: 5px;
                        padding: 15px;
                        max-width: 300px;
                        z-index: 9999;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    `;
                    
                    const content = `
                        <h5 style="margin-top: 0; color: #856404;">Session Expiring Soon</h5>
                        <p>Your session will expire in 5 minutes due to inactivity.</p>
                        <button id="extendSession" class="btn btn-sm btn-primary">Stay Logged In</button>
                    `;
                    
                    modal.innerHTML = content;
                    document.body.appendChild(modal);
                    
                    // Add event listener to extend button
                    document.getElementById('extendSession').addEventListener('click', function() {
                        resetTimers();
                        modal.style.display = 'none';
                    });
                } else {
                    modal.style.display = 'block';
                }
                
                // Auto-hide after 10 seconds
                setTimeout(() => {
                    if (modal) modal.style.display = 'none';
                }, 10000);
            }
        }
        
        function logout() {
            window.location.href = logoutUrl;
        }
    });
    </script>
    EOT;
    
    echo $script;
}

// Add session validation to all pages
validate_session();

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to check if user is admin
function is_admin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// Function to require login
function require_login() {
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: /auth/login.php');
        exit();
    }
}

// Function to require admin access
function require_admin() {
    require_login();
    if (!is_admin()) {
        header('HTTP/1.1 403 Forbidden');
        die('Access Denied: You do not have permission to access this page.');
    }
}

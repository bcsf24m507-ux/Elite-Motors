<?php
/**
 * Security functions for the application
 */

/**
 * Track login attempts and check if rate limited
 * @param PDO $pdo Database connection
 * @param string $ip User IP address
 * @param string $username Username being attempted
 * @return array [is_limited, message, remaining_attempts]
 */
function check_login_attempts($pdo, $ip, $username) {
    $max_attempts = 5;
    $lockout_time = 15 * 60; // 15 minutes in seconds
    
    try {
        // Create login_attempts table if it doesn't exist
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                username VARCHAR(100) NOT NULL,
                attempt_time DATETIME NOT NULL,
                is_successful BOOLEAN DEFAULT FALSE,
                INDEX idx_ip_username (ip_address, username),
                INDEX idx_attempt_time (attempt_time)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        
            CREATE TABLE IF NOT EXISTS locked_accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                locked_until DATETIME NOT NULL,
                UNIQUE KEY unique_username (username)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        
        // Check if account is locked
        $stmt = $pdo->prepare("SELECT locked_until FROM locked_accounts WHERE username = ? AND locked_until > NOW()");
        $stmt->execute([$username]);
        $locked_until = $stmt->fetchColumn();
        
        if ($locked_until) {
            $remaining = strtotime($locked_until) - time();
            $minutes = ceil($remaining / 60);
            return [
                'is_limited' => true,
                'message' => "Account locked. Please try again in {$minutes} minutes.",
                'remaining_attempts' => 0
            ];
        }
        
        // Count recent failed attempts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM login_attempts 
            WHERE ip_address = ? 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND is_successful = 0
        ");
        $stmt->execute([$ip]);
        $attempts = $stmt->fetchColumn();
        
        $remaining_attempts = max(0, $max_attempts - $attempts);
        
        if ($attempts >= $max_attempts) {
            // Lock the account for 15 minutes
            $lock_until = date('Y-m-d H:i:s', time() + $lockout_time);
            $stmt = $pdo->prepare("
                INSERT INTO locked_accounts (username, locked_until) 
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE locked_until = ?
            ");
            $stmt->execute([$username, $lock_until, $lock_until]);
            
            return [
                'is_limited' => true,
                'message' => "Too many failed attempts. Account locked for 15 minutes.",
                'remaining_attempts' => 0
            ];
        }
        
        return [
            'is_limited' => false,
            'message' => "",
            'remaining_attempts' => $remaining_attempts
        ];
        
    } catch (PDOException $e) {
        error_log("Login attempt check failed: " . $e->getMessage());
        // Fail open in case of database issues
        return [
            'is_limited' => false,
            'message' => "",
            'remaining_attempts' => $max_attempts
        ];
    }
}

/**
 * Record a login attempt
 * @param PDO $pdo Database connection
 * @param string $ip User IP address
 * @param string $username Username being attempted
 * @param bool $is_successful Whether the login was successful
 */
function record_login_attempt($pdo, $ip, $username, $is_successful) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_attempts (ip_address, username, attempt_time, is_successful)
            VALUES (?, ?, NOW(), ?)
        ");
        $stmt->execute([$ip, $username, $is_successful ? 1 : 0]);
        
        // Clear old attempts to keep table size manageable
        $pdo->exec("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        
        // Clear expired account locks
        $pdo->exec("DELETE FROM locked_accounts WHERE locked_until < NOW()");
        
        // If login was successful, clear any existing locks for this user
        if ($is_successful) {
            $stmt = $pdo->prepare("DELETE FROM locked_accounts WHERE username = ?");
            $stmt->execute([$username]);
        }
        
    } catch (PDOException $e) {
        error_log("Failed to record login attempt: " . $e->getMessage());
    }
}

/**
 * Get the client's IP address
 * @return string Client IP address
 */
function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

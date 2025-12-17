<?php
// Database configuration for Car Rental Management System
// Supports both local development and Render + Aiven deployment

// Detect environment
$isProduction = getenv('RENDER') || 
                (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== 'localhost' && $_SERVER['SERVER_NAME'] !== '127.0.0.1');

if ($isProduction) {
    // Aiven MySQL on Render (Production)
    $host = 'mysql-3e1ce2-pucit-337f.f.aivencloud.com';
    $dbname = 'defaultdb';  // Aiven uses 'defaultdb' by default
    $username = 'avnadmin';
    $password = 'AVNS_VfCo0APBMLZpWRssU4Y';
    $port = 17642;
    
    // Aiven requires SSL connection
    $sslOptions = [
        PDO::MYSQL_ATTR_SSL_CA => '/etc/ssl/certs/ca-certificates.crt',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false  // Set to true for production
    ];
    
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    try {
        $pdo = new PDO($dsn, $username, $password, $sslOptions);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        
        // Test the connection
        $pdo->query("SELECT 1");
        
    } catch(PDOException $e) {
        // If SSL fails, try without SSL
        try {
            $pdo = new PDO($dsn, $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->query("SELECT 1");
        } catch(PDOException $e2) {
            die("Aiven MySQL Connection Failed: " . $e2->getMessage() . 
                "<br>Original SSL error: " . $e->getMessage());
        }
    }
    
} else {
    // Local development
    $host = 'localhost';
    $dbname = 'car_rental_system';
    $username = 'root';
    $password = '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        die("Local Database connection failed: " . $e->getMessage());
    }
}

// Helper function to get database connection
function getDB() {
    global $pdo;
    return $pdo;
}

// Optional: Log connection type for debugging
if (isset($_GET['debug'])) {
    echo "Connected to: " . ($isProduction ? "Aiven MySQL (Production)" : "Local MySQL");
    echo "<br>Database: $dbname";
}
?>

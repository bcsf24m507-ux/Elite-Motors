<?php
require_once 'config/database.php';

echo "<h2>Testing Admin Login</h2>";

// Test database connection
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM admins");
    $result = $stmt->fetch();
    echo "<p>✅ Database connected. Admin records: " . $result['count'] . "</p>";
    
    // Get admin details
    $stmt = $pdo->query("SELECT username, email, full_name, role FROM admins");
    $admins = $stmt->fetchAll();
    
    echo "<h3>Admin Records:</h3>";
    foreach ($admins as $admin) {
        echo "<p>Username: " . htmlspecialchars($admin['username']) . "</p>";
        echo "<p>Email: " . htmlspecialchars($admin['email']) . "</p>";
        echo "<p>Full Name: " . htmlspecialchars($admin['full_name']) . "</p>";
        echo "<p>Role: " . htmlspecialchars($admin['role']) . "</p>";
        echo "<hr>";
    }
    
    // Test password verification
    $stmt = $pdo->prepare("SELECT password FROM admins WHERE username = ?");
    $stmt->execute(['admin']);
    $admin = $stmt->fetch();
    
    if ($admin) {
        $stored_hash = $admin['password'];
        $test_password = 'password123';
        
        if (password_verify($test_password, $stored_hash)) {
            echo "<p>✅ Password verification successful for 'password123'</p>";
        } else {
            echo "<p>❌ Password verification failed for 'password123'</p>";
            echo "<p>Stored hash: " . $stored_hash . "</p>";
        }
    } else {
        echo "<p>❌ No admin found with username 'admin'</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}
?>

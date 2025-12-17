<?php
require_once 'config/database.php';

echo "<h2>Reset Admin Password</h2>";

try {
    // Reset admin password to 'password123'
    $new_password = 'password123';
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
    $result = $stmt->execute([$hashed_password]);
    
    if ($result) {
        echo "<p>✅ Admin password reset successfully!</p>";
        echo "<p>Username: admin</p>";
        echo "<p>Password: password123</p>";
        echo "<p>New hash: " . $hashed_password . "</p>";
    } else {
        echo "<p>❌ Failed to reset password</p>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
}
?>

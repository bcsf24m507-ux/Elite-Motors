<?php
session_start();
require_once '../config/database.php';
require_once '../config/security_functions.php';

// Set security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Get client IP
$ip = get_client_ip();

// Check for session expiration
if (isset($_GET['expired']) && $_GET['expired'] == 1) {
    $error = 'Your session has expired due to inactivity. Please log in again.';
}

$error = $error ?? '';
$remaining_attempts = 5; // Default value

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || 
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid form submission. Please try again.';
        http_response_code(400);
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        // Check rate limiting
        $rate_limit = check_login_attempts($pdo, $ip, $username);
        if ($rate_limit['is_limited']) {
            $error = $rate_limit['message'];
            $remaining_attempts = 0;
        } elseif (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            try {
                // Check if user exists
                $stmt = $pdo->prepare("SELECT id, username, email, password, full_name FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();

                if ($user) {
                    // Verify password
                    if (password_verify($password, $user['password'])) {
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['user_type'] = 'customer';
                        $_SESSION['last_activity'] = time();
                        
                        // Record successful login
                        record_login_attempt($pdo, $ip, $username, true);
                        
                        // Clear any existing login attempts for this IP
                        $pdo->prepare("DELETE FROM login_attempts WHERE ip_address = ?")->execute([$ip]);
                        
                        // Redirect to intended page or home
                        $redirect = $_SESSION['redirect_after_login'] ?? '../index.php';
                        unset($_SESSION['redirect_after_login']);
                        
                        header('Location: ' . $redirect);
                        exit();
                    }
                }
                
                // If we get here, login failed
                $remaining_attempts = $rate_limit['remaining_attempts'] - 1;
                $error = 'Invalid username/email or password.';
                if ($remaining_attempts > 0) {
                    $error .= " You have {$remaining_attempts} attempt" . ($remaining_attempts > 1 ? 's' : '') . " remaining.";
                } else {
                    $error = 'Too many failed attempts. Please try again later.';
                }
                
                // Record failed attempt
                record_login_attempt($pdo, $ip, $username, false);
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ELITE MOTORS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet"> <!-- Versioned for cache busting -->
</head>

<body class="modern-auth-body">
    <div class="auth-container">
        <!-- Left Side - Visual -->
        <div class="auth-visual">
            <div class="auth-visual-content">
                <div class="auth-logo">
                    <i class="fas fa-car logo-car"></i>
                    <h2>ELITE MOTORS</h2>
                </div>
                <div class="auth-animation">
                    <div class="floating-cars">
                        <i class="fas fa-car car-1"></i>
                        <i class="fas fa-car car-2"></i>
                        <i class="fas fa-car car-3"></i>
                    </div>
                </div>
                <div class="auth-message">
                    <h3>Welcome Back! 🚗</h3>
                    <p>Continue your journey with us and discover amazing vehicles for your next adventure. 🚙</p>
                </div>
            </div>
        </div>

        <!-- Right Side - Form -->
        <div class="auth-form-container">
            <div class="auth-form">
                <div class="form-header">
                    <h1>Sign In</h1>
                    <p>Enter your credentials to access your account</p>
                    <?php if (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-clock me-2"></i>
                            Your session has timed out due to inactivity.
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger modern-alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <?php 
// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<form method="POST" action="" class="modern-form" id="loginForm">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label for="username" class="form-label">
                            <i class="fas fa-user me-2"></i>Username or Email
                        </label>
                        <input type="text" class="form-control modern-input" id="username" name="username" required
                            value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                            placeholder="Enter your username or email">
                    </div>

                    <div class="form-group">
                        <label for="password" class="form-label">
                            <i class="fas fa-lock me-2"></i>Password
                        </label>
                        <div class="password-input">
                            <div class="input-group">
                                <input type="password" class="form-control modern-input" id="password" name="password" required
                                    placeholder="Enter your password" autocomplete="current-password">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn modern-btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>
                        Sign In
                    </button>
                </form>

                <div class="form-footer">
                    <div class="divider">
                        <span>or</span>
                    </div>

                    <div class="auth-links">
                        <a href="register.php" class="auth-link">
                            <i class="fas fa-user-plus me-2"></i>
                            Create Account
                        </a>
                        <a href="admin_login.php" class="auth-link">
                            <i class="fas fa-user-shield me-2"></i>
                            Admin Access
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const passwordInput = this.previousElementSibling;
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.querySelector('i').classList.toggle('fa-eye');
            this.querySelector('i').classList.toggle('fa-eye-slash');
        });
    });

    // Disable form after submission to prevent double submission
    document.getElementById('loginForm').addEventListener('submit', function() {
        const submitButton = this.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Signing in...';
        }
    });
    </script>
</body>

</html>
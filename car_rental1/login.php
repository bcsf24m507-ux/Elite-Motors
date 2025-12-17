<?php
session_start();
require_once '../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, username, email, password, full_name FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = 'customer';

                header('Location: ../index.php');
                exit();
            } else {
                $error = 'Invalid username/email or password.';
            }
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
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
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger modern-alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="" class="modern-form">
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
                            <input type="password" class="form-control modern-input" id="password" name="password"
                                required placeholder="Enter your password">
                            <button type="button" class="password-toggle" onclick="togglePassword()">
                                <i class="fas fa-eye"></i>
                            </button>
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
    function togglePassword() {
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.querySelector('.password-toggle i');

        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleBtn.classList.remove('fa-eye');
            toggleBtn.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            toggleBtn.classList.remove('fa-eye-slash');
            toggleBtn.classList.add('fa-eye');
        }
    }

    // Add focus effects to inputs
    document.querySelectorAll('.modern-input').forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });

        input.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });
    });
    </script>
</body>

</html>
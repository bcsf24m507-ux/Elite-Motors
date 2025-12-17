<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$message = '';
$error = '';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : ($user['full_name'] ?? '');
    $email = isset($_POST['email']) ? trim($_POST['email']) : ($user['email'] ?? '');
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : ($user['phone'] ?? '');
    $address = isset($_POST['address']) ? trim($_POST['address']) : ($user['address'] ?? '');
    
    // Handle profile picture
    $current_profile_picture = $user['profile_picture'] ?? '';
    $profile_picture = $current_profile_picture;
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $upload_dir = '../uploads/profiles/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $file_type = $_FILES['profile_picture']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $file_name = uniqid() . '_' . basename($_FILES['profile_picture']['name']);
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $profile_picture = 'uploads/profiles/' . $file_name;
                // Update session with new profile picture
                $_SESSION['profile_picture'] = $profile_picture;
                // Delete old profile picture if exists
                if (!empty($current_profile_picture) && file_exists('../' . $current_profile_picture)) {
                    @unlink('../' . $current_profile_picture);
                }
            }
        }
    }
    
    // Handle delete profile picture
    if (isset($_POST['delete_picture']) && $_POST['delete_picture'] == '1') {
        if (!empty($current_profile_picture) && file_exists('../' . $current_profile_picture)) {
            @unlink('../' . $current_profile_picture);
        }
        $profile_picture = '';
        // Remove profile picture from session
        unset($_SESSION['profile_picture']);
    }
    
    // Validate input
    if (empty($full_name) || empty($email)) {
        $error = 'Full name and email are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $_SESSION['user_id']]);
        if ($stmt->fetch()) {
            $error = 'This email is already registered to another account.';
        } else {
            // Ensure profile_picture column exists
            try {
                $check_stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
                if ($check_stmt->rowCount() == 0) {
                    $pdo->exec("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) DEFAULT NULL AFTER address");
                }
            } catch (PDOException $e) {
                // Column might already exist or other error
            }
            
            // Update user profile
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, profile_picture = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $email, $phone, $address, $profile_picture, $_SESSION['user_id']])) {
                // Update session variables
                $_SESSION['full_name'] = $full_name;
                $_SESSION['email'] = $email;
                // Update profile picture in session if it was changed
                if (!empty($profile_picture)) {
                    $_SESSION['profile_picture'] = $profile_picture;
                } elseif (isset($_POST['delete_picture']) && $_POST['delete_picture'] == '1') {
                    unset($_SESSION['profile_picture']);
                }
                $message = 'Profile updated successfully!';
                // Refresh user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
            }
        }
    }
}

// Get user's booking statistics
$stats = [
    'total_bookings' => 0,
    'active_bookings' => 0,
    'total_spent' => 0,
    'total_cars' => 0,
    'available_cars' => 0
];

// User-specific stats
$stmt = $pdo->prepare("SELECT COUNT(*) as total_bookings FROM bookings WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['total_bookings'] = $stmt->fetch()['total_bookings'];

$stmt = $pdo->prepare("SELECT COUNT(*) as active_bookings FROM bookings WHERE user_id = ? AND status IN ('pending', 'confirmed', 'active')");
$stmt->execute([$_SESSION['user_id']]);
$stats['active_bookings'] = $stmt->fetch()['active_bookings'];

$stmt = $pdo->prepare("SELECT SUM(total_amount) as total_spent FROM bookings WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$_SESSION['user_id']]);
$stats['total_spent'] = $stmt->fetch()['total_spent'] ?? 0;

// Get car statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_cars FROM cars WHERE status != 'unavailable'");
$stats['total_cars'] = $stmt->fetch()['total_cars'];

// Get current date for availability check
$today = date('Y-m-d');

// Get total cars (excluding those marked as unavailable)
$stmt = $pdo->query("SELECT COUNT(*) as total_cars FROM cars WHERE status != 'unavailable'");
$stats['total_cars'] = $stmt->fetch()['total_cars'];

// Get available cars (excluding those with active bookings on current date)
$stmt = $pdo->prepare("SELECT COUNT(DISTINCT c.id) as available_cars 
                       FROM cars c 
                       WHERE c.status = 'available'
                       AND c.id NOT IN (
                           SELECT DISTINCT b.car_id 
                           FROM bookings b 
                           WHERE b.status IN ('pending', 'confirmed', 'active')
                           AND b.start_date <= ? 
                           AND b.end_date >= ?
                       )");
$stmt->execute([$today, $today]);
$stats['available_cars'] = $stmt->fetch()['available_cars'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage your ELITE MOTORS profile, view booking history, and update your personal information.">
    <meta name="author" content="ELITE MOTORS">
    <title>My Profile - ELITE MOTORS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #FFD700;  /* Gold */
            --secondary-color: #121212;  /* Darker background */
            --text-color: #FFFFFF;  /* White text */
            --text-muted: #B0B0B0;  /* Light gray for secondary text */
            
        body {
            background-color: #000000;
            color: var(--text-color);
        }
        
        .main-content {
            background-color: #000000;
            min-height: calc(100vh - 200px);
        }
        
        .content-container {
            background-color: #000000;
            padding: 2rem 0;
        }
        
        .card {
            background-color: #121212;
            border: 1px solid #333;
            color: var(--text-color);
        }
        
        .card-header {
            background-color: #1a1a1a;
            border-bottom: 1px solid #333;
            color: #FFD700;
            font-weight: 600;
        }
            --accent-color: #1E1E1E;  /* Slightly lighter than secondary for cards */
            --border-color: #333333;  /* Dark gray for borders */
            --input-bg: #2D2D2D;  /* Dark gray for input backgrounds */
            --input-text: #FFFFFF;  /* White text for inputs */
        }
        
        /* Reset default margin and padding */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* Make html and body take full viewport height */
        html {
            height: 100%;
        }
        
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
            padding: 0;
            background-color: var(--secondary-color);
            color: var(--text-color);
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
        }
        
        /* Main content wrapper */
        .main-content {
            flex: 1 0 auto;
            width: 100%;
            padding: 20px 0;
            overflow-y: auto;
        }
        
        /* Container for main content */
        .content-container {
            min-height: calc(100vh - 250px);
            display: flex;
            flex-direction: column;
        }
        .sidebar {
            background: var(--accent-color);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            height: 100%;
            overflow-y: auto;
            border: 1px solid var(--border-color);
        }
        .nav-link {
            color: #fff !important;
            padding: 12px 20px;
            margin: 8px 0;
            border-radius: 6px;
            transition: all 0.3s ease;
            font-size: 1.05rem;
            display: flex;
            background: #1a1a1a;
            border: 1px solid #333;
        }
        .nav-link:hover {
            background: var(--primary-color) !important;
            color: #000 !important;
        }
        
        .nav-link:hover, .nav-link:focus {
            color: var(--primary-color) !important;
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
        }
        .nav-link:hover,
        .nav-pills .nav-link.active {
            background-color: var(--primary-color) !important;
            color: #000 !important;
            font-weight: 700;
            border: 1px solid var(--primary-color);
        }
        
        /* Form controls */
        .form-control,
        .form-select {
            background-color: var(--input-bg);
            border: 1px solid var(--border-color);
            color: var(--input-text);
        }
        
        .form-control:focus,
        .form-select:focus {
            background-color: var(--input-bg);
            color: var(--input-text);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.25);
        }
        
        .form-label {
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }
        
        /* Card styles */
        .stats-card {
            background-color: var(--primary-color);
            color: #000 !important;
            border: 1px solid var(--primary-color);
            border-radius: 0.5rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .stats-card .display-4 {
            color: #000 !important;
            font-weight: 700;
        }
        
        .card {
            background-color: var(--accent-color);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: rgba(0, 0, 0, 0.2);
            border-bottom: 1px solid var(--border-color);
            color: var(--primary-color);
            font-weight: 600;
        }
        
        /* Buttons */
        .btn-primary {
            background-color: #FFD700;
            border-color: #FFD700;
            color: #000000;
            font-weight: 500;
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: #E6C200;
            border-color: #E6C200;
            color: #000000;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 215, 0, 0.3);
        }
        
        /* Profile picture section */
        .profile-pic {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 3px solid var(--primary-color);
            border-radius: 50%;
            background: #000;
        }
        
        /* Text colors */
        .text-muted {
            color: var(--text-muted) !important;
        }
        
        /* Links */
        a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        a:hover {
            color: #E6C200;
            text-decoration: underline;
        }
        
        /* Custom file upload button */
        .custom-file-upload {
            border: 1px solid var(--border-color);
            background-color: var(--input-bg);
            color: var(--text-color);
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            display: inline-block;
        }
        
        .custom-file-upload:hover {
            background-color: #2D2D2D;
            border-color: var(--primary-color);
        }
        
        /* Checkbox label */
        .form-check-label {
            color: var(--text-color);
            margin-left: 0.5rem;
        }
        }
        .card {
            background: var(--accent-color);
            border: 1px solid #333;
            border-radius: 10px;
            margin-bottom: 20px;
            transition: transform 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .card-body {
            overflow-y: auto;
            max-height: 400px;
            padding: 1.25rem;
            flex: 1;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #2a2a2a;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #FFD700;
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #e6c200;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.2);
        }
        .card-header {
            background: #1a1a1a;
            color: #FFD700;
            font-weight: 600;
            border-bottom: 1px solid #333;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-control, .form-select {
            background-color: #2a2a2a;
            border: 1px solid #444;
            color: var(--text-color);
        }
        .form-control:focus, .form-select:focus {
            background-color: #333;
            color: var(--text-color);
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.25);
        }
        .btn-primary {
            background-color: #FFD700;
            color: #000000;
            border: none;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn-primary:hover, .btn-primary:focus {
            background-color: #E6C200;
            color: #000000;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(255, 215, 0, 0.3);
        }
        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            background: var(--primary-color);
            margin-bottom: 20px;
            color: #000;
            border: 1px solid var(--primary-color);
        }
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #000;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #000;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .alert {
            border: none;
            border-left: 4px solid;
        }
        .alert-success {
            background-color: #1a2e1a;
            border-left-color: #28a745;
            color: #a3d9a5;
        }
        .alert-danger {
            background-color: #2e1a1a;
            border-left-color: #dc3545;
            color: #e6a3a3;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include '../includes/navbar.php'; ?>
    
    <!-- Page Header -->
    <header class="bg-dark text-white py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-0">
                        <i class="fas fa-user-circle me-2"></i>My Profile
                    </h1>
                </div>
                <div class="col-md-4 text-md-end">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb justify-content-md-end mb-0">
                            <li class="breadcrumb-item"><a href="../index.php" class="text-gold">Home</a></li>
                            <li class="breadcrumb-item active text-white" aria-current="page">Profile</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container content-container">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="sidebar">
                    <div class="text-center mb-4">
                        <?php 
                        $profilePic = !empty($user['profile_picture']) && file_exists('../' . $user['profile_picture']) 
                            ? '../' . $user['profile_picture'] 
                            : 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name'] ?? 'User') . '&background=FFD700&color=000&size=150';
                        ?>
                        <img src="<?php echo $profilePic; ?>" 
                             class="profile-pic mb-3" 
                             alt="Profile Picture"
                             id="profileImagePreview"
                             style="width: 150px; height: 150px; object-fit: cover; border: 3px solid var(--primary-color); border-radius: 50%; background: #000;">
                        <h5 class="mb-0"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                        <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="profile.php">
                                <i class="fas fa-user me-2"></i> My Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
                                <i class="fas fa-calendar-alt me-2"></i> My Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="change_password.php">
                                <i class="fas fa-key me-2"></i> Change Password
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Stats -->
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['active_bookings']; ?></div>
                    <div class="stat-label">Active Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">$<?php echo number_format($stats['total_spent'], 2); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['available_cars']; ?>/<?php echo $stats['total_cars']; ?></div>
                    <div class="stat-label">Available Cars</div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-lg-9">
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-user-edit me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="address" class="form-label">Address</label>
                                    <input type="text" class="form-control" id="address" name="address" 
                                           value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="profile_picture" class="form-label">Profile Picture</label>
                                <input class="form-control" type="file" id="profile_picture" name="profile_picture" 
                                       accept="image/jpeg,image/jpg,image/png">
                                <?php if (!empty($user['profile_picture'])): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="delete_picture" id="delete_picture" value="1">
                                        <label class="form-check-label" for="delete_picture">
                                            Remove current picture
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Account Security Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Account Security</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <a href="change_password.php" class="btn btn-outline-primary">
                                <i class="fas fa-key me-2"></i>Change Password
                            </a>
                            <button class="btn btn-outline-secondary" disabled>
                                <i class="fas fa-shield-alt me-2"></i>Two-Factor Authentication (Coming Soon)
                            </button>
                            <button class="btn btn-outline-secondary" disabled>
                                <i class="fas fa-history me-2"></i>Login History (Coming Soon)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-4 mt-auto">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5>ELITE MOTORS</h5>
                    <p class="mb-0">Your premium car rental service</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> ELITE MOTORS. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Add active class to current nav item
    document.addEventListener('DOMContentLoaded', function() {
        // Update profile image preview when a new file is selected
        const profilePicInput = document.getElementById('profile_picture');
        const profilePicPreview = document.getElementById('profileImagePreview');
        
        if (profilePicInput && profilePicPreview) {
            profilePicInput.addEventListener('change', function(e) {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        profilePicPreview.src = e.target.result;
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
        
        // Handle delete picture checkbox
        const deleteCheckbox = document.getElementById('delete_picture');
        if (deleteCheckbox) {
            deleteCheckbox.addEventListener('change', function() {
                if (this.checked) {
                    profilePicPreview.src = '../assets/images/default-avatar.png';
                } else {
                    profilePicPreview.src = '<?php echo !empty($user['profile_picture']) ? '../' . $user['profile_picture'] : '../assets/images/default-avatar.png'; ?>';
                }
            });
        }
    });
    </script>
</body>
</html>

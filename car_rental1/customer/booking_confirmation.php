<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if booking_id is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Invalid booking reference.';
    header('Location: bookings.php');
    exit();
}

$booking_id = (int)$_GET['id'];
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Get booking details with payment and car information
$stmt = $pdo->prepare("SELECT b.*, p.transaction_id, p.payment_status, p.payment_date,
                      c.brand, c.model, c.year, c.image_path, c.registration_number,
                      u.full_name, u.email, u.phone, u.address
                      FROM bookings b 
                      JOIN payments p ON b.id = p.booking_id 
                      JOIN cars c ON b.car_id = c.id 
                      JOIN users u ON b.user_id = u.id 
                      WHERE b.id = ? AND b.user_id = ?");
$stmt->execute([$booking_id, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    $_SESSION['error'] = 'Booking not found or access denied.';
    header('Location: bookings.php');
    exit();
}

// Format dates
$start_date = new DateTime($booking['start_date']);
$end_date = new DateTime($booking['end_date']);
$total_days = $start_date->diff($end_date)->days + 1;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - ELITE MOTORS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        .confirmation-card {
            border: 1px solid #333;
            border-radius: 10px;
            background: #0b0b0b;
            overflow: hidden;
        }
        .confirmation-header {
            background: linear-gradient(135deg, #1a0f0a 0%, #2c1a10 100%);
            color: #FFD700;
            padding: 30px;
            text-align: center;
            border-bottom: 1px solid #333;
        }
        .confirmation-body {
            padding: 30px;
        }
        .booking-details {
            background: #111;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .car-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .print-btn {
            background: linear-gradient(135deg, #FFD700 0%, #B8860B 100%);
            border: none;
            color: #000;
            font-weight: 700;
            padding: 12px 30px;
            border-radius: 30px;
            transition: all 0.3s ease;
        }
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: 600;
        }
        .status-confirmed {
            background-color: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        .status-pending {
            background-color: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
    </style>
</head>
<body style="background:#000000;">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-car me-2"></i>ELITE MOTORS
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="bookings.php"><i class="fas fa-calendar-alt me-2"></i>My Bookings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit me-2"></i>Edit Profile</a></li>
                        <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="confirmation-card">
                    <div class="confirmation-header">
                        <div class="d-flex justify-content-center mb-3">
                            <div class="status-badge <?php echo $booking['status'] === 'confirmed' ? 'status-confirmed' : 'status-pending'; ?>">
                                <?php echo strtoupper($booking['status']); ?>
                            </div>
                        </div>
                        <h2><i class="fas fa-check-circle me-2"></i>Booking Confirmed!</h2>
                        <p class="mb-0">Your booking has been successfully processed.</p>
                    </div>
                    
                    <div class="confirmation-body">
                        <div class="text-center mb-4">
                            <h4 class="text-gold">Booking #<?php echo $booking['id']; ?></h4>
                            <p class="text-muted">We've sent the booking details to <?php echo htmlspecialchars($booking['email']); ?></p>
                            
                            <div class="d-flex justify-content-center gap-3 mt-4">
                                <a href="bookings.php" class="btn btn-outline-light">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Bookings
                                </a>
                                <button onclick="window.print()" class="btn print-btn">
                                    <i class="fas fa-print me-2"></i>Print Confirmation
                                </button>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-5">
                                <?php if (!empty($booking['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($booking['image_path']); ?>" alt="Car Image" class="car-image">
                                <?php endif; ?>
                                
                                <div class="booking-details">
                                    <h5 class="text-gold mb-3">Booking Details</h5>
                                    <div class="mb-2">
                                        <small class="text-muted">Booking ID</small>
                                        <p class="mb-0">#<?php echo $booking['id']; ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Transaction ID</small>
                                        <p class="mb-0"><?php echo $booking['transaction_id'] ?? 'N/A'; ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Booking Date</small>
                                        <p class="mb-0"><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></p>
                                    </div>
                                    <div class="mb-2">
                                        <small class="text-muted">Status</small>
                                        <p class="mb-0">
                                            <span class="status-badge <?php echo $booking['status'] === 'confirmed' ? 'status-confirmed' : 'status-pending'; ?>">
                                                <?php echo strtoupper($booking['status']); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="booking-details mt-3">
                                    <h5 class="text-gold mb-3">Rental Period</h5>
                                    <div class="d-flex justify-content-between mb-2">
                                        <div>
                                            <small class="text-muted">Pickup</small>
                                            <p class="mb-0"><?php echo $start_date->format('D, M j, Y'); ?></p>
                                            <small class="text-muted">10:00 AM</small>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">Return</small>
                                            <p class="mb-0"><?php echo $end_date->format('D, M j, Y'); ?></p>
                                            <small class="text-muted">09:00 AM</small>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <small class="text-muted">Duration</small>
                                        <p class="mb-0"><?php echo $total_days; ?> day<?php echo $total_days > 1 ? 's' : ''; ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-7">
                                <div class="booking-details">
                                    <h5 class="text-gold mb-3">Car Details</h5>
                                    <h4 class="mb-3"><?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model'] . ' (' . $booking['year'] . ')'); ?></h4>
                                    
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <div class="mb-2">
                                                <small class="text-muted">Registration Number</small>
                                                <p class="mb-0"><?php echo htmlspecialchars($booking['registration_number'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="mb-2">
                                                <small class="text-muted">Color</small>
                                                <p class="mb-0">Black</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <h5 class="text-gold mt-4 mb-3">Pricing</h5>
                                    <div class="mb-2 d-flex justify-content-between">
                                        <span class="text-muted">Daily Rate</span>
                                        <span>₹<?php echo number_format($booking['total_amount'] / $total_days, 2); ?> x <?php echo $total_days; ?> day<?php echo $total_days > 1 ? 's' : ''; ?></span>
                                    </div>
                                    <div class="mb-2 d-flex justify-content-between">
                                        <span class="text-muted">Subtotal</span>
                                        <span>₹<?php echo number_format($booking['total_amount'] / 1.18, 2); ?></span>
                                    </div>
                                    <div class="mb-2 d-flex justify-content-between">
                                        <span class="text-muted">GST (18%)</span>
                                        <span>₹<?php echo number_format($booking['total_amount'] * 0.18, 2); ?></span>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between fw-bold">
                                        <span>Total Amount</span>
                                        <span>₹<?php echo number_format($booking['total_amount'], 2); ?></span>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-success">
                                            <i class="fas fa-check-circle"></i> 
                                            <?php echo $booking['payment_status'] === 'paid' ? 'Paid on ' . date('M j, Y', strtotime($booking['payment_date'])) : 'Payment Pending'; ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="booking-details mt-3">
                                    <h5 class="text-gold mb-3">Customer Information</h5>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <small class="text-muted">Full Name</small>
                                                <p class="mb-0"><?php echo htmlspecialchars($booking['full_name']); ?></p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <small class="text-muted">Email</small>
                                                <p class="mb-0"><?php echo htmlspecialchars($booking['email']); ?></p>
                                            </div>
                                        </div>
                                        <?php if (!empty($booking['phone'])): ?>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <small class="text-muted">Phone</small>
                                                <p class="mb-0"><?php echo htmlspecialchars($booking['phone']); ?></p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($booking['pickup_location'])): ?>
                                        <div class="col-12">
                                            <div class="mb-3">
                                                <small class="text-muted">Pickup Location</small>
                                                <p class="mb-0"><?php echo htmlspecialchars($booking['pickup_location']); ?></p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if (!empty($booking['notes'])): ?>
                                        <div class="col-12">
                                            <div class="mb-3">
                                                <small class="text-muted">Special Requests</small>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($booking['notes'])); ?></p>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="alert alert-info mt-4">
                                    <div class="d-flex">
                                        <i class="fas fa-info-circle me-3 mt-1"></i>
                                        <div>
                                            <h6 class="alert-heading">Need to make changes?</h6>
                                            <p class="mb-0 small">Please contact our customer support at <a href="mailto:support@elitemotors.com" class="alert-link">support@elitemotors.com</a> or call us at <strong>+91 1234567890</strong> for any modifications to your booking.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Print only the confirmation card when printing
    function printDiv() {
        window.print();
    }
    </script>
</body>
</html>

<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if booking_id is provided
if (!isset($_GET['booking_id'])) {
    $_SESSION['error'] = 'Invalid booking reference.';
    header('Location: bookings.php');
    exit();
}

$booking_id = (int)$_GET['booking_id'];
$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

// Get booking details
$stmt = $pdo->prepare("SELECT b.*, p.id as payment_id, p.amount as payment_amount, p.payment_status, 
                      c.brand, c.model, c.year, c.image_path, 
                      u.full_name, u.email, u.phone
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

// Check if already paid
if ($booking['payment_status'] === 'paid') {
    $_SESSION['success'] = 'This booking has already been paid for.';
    header('Location: booking_confirmation.php?id=' . $booking_id);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Payment - ELITE MOTORS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        .payment-card {
            border: 1px solid #444;
            border-radius: 10px;
            background: #111;
            transition: all 0.3s ease;
        }
        .payment-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.2);
        }
        .payment-card.selected {
            border-color: #FFD700;
            background: rgba(255, 215, 0, 0.1);
        }
        .btn-pay-now {
            background: linear-gradient(135deg, #FFD700 0%, #B8860B 100%);
            border: none;
            color: #000;
            font-weight: 700;
            padding: 12px 30px;
            border-radius: 30px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        .btn-pay-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
        }
        .booking-summary {
            background: #0b0b0b;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 20px;
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
            <div class="col-lg-8">
                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="text-center mb-5">
                    <h1 class="text-gold mb-3">Complete Your Booking</h1>
                    <p class="text-muted">Please review your booking details and complete the payment</p>
                </div>

                <div class="row">
                    <div class="col-lg-7">
                        <div class="card payment-card mb-4">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Method</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="payment_method" id="creditCard" checked>
                                        <label class="form-check-label fw-bold" for="creditCard">
                                            Credit/Debit Card
                                        </label>
                                    </div>
                                    
                                    <div class="card-details">
                                        <div class="mb-3">
                                            <label for="cardNumber" class="form-label">Card Number</label>
                                            <div class="input-group">
                                                <span class="input-group-text"><i class="far fa-credit-card"></i></span>
                                                <input type="text" class="form-control" id="cardNumber" placeholder="1234 5678 9012 3456">
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="expiryDate" class="form-label">Expiry Date</label>
                                                <input type="text" class="form-control" id="expiryDate" placeholder="MM/YY">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="cvv" class="form-label">CVV</label>
                                                <div class="input-group">
                                                    <input type="text" class="form-control" id="cvv" placeholder="123">
                                                    <span class="input-group-text" data-bs-toggle="tooltip" title="3-digit code on the back of your card">
                                                        <i class="fas fa-question-circle"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="cardName" class="form-label">Name on Card</label>
                                            <input type="text" class="form-control" id="cardName" placeholder="John Doe">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <form id="paymentForm" action="../payment/process.php" method="POST">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
                                        <button type="submit" class="btn btn-warning btn-pay-now w-100">
                                            <i class="fas fa-lock me-2"></i>Pay Now <?php echo '₹' . number_format($booking['total_amount'], 2); ?>
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="text-center mt-3">
                                    <p class="small text-muted">
                                        <i class="fas fa-lock me-1"></i> Your payment is secure and encrypted
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <div class="d-flex">
                                <i class="fas fa-info-circle me-3 mt-1"></i>
                                <div>
                                    <h6 class="alert-heading">Need help?</h6>
                                    <p class="mb-0 small">Contact our support team at <a href="mailto:support@elitemotors.com" class="alert-link">support@elitemotors.com</a> or call us at <strong>+91 1234567890</strong>.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-5">
                        <div class="booking-summary mb-4">
                            <h5 class="text-gold mb-4">Booking Summary</h5>
                            
                            <div class="d-flex justify-content-between mb-3">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model'] . ' (' . $booking['year'] . ')'); ?></h6>
                                    <p class="small text-muted mb-0">
                                        <?php 
                                        $start = new DateTime($booking['start_date']);
                                        $end = new DateTime($booking['end_date']);
                                        echo $start->format('M j, Y') . ' - ' . $end->format('M j, Y');
                                        ?>
                                    </p>
                                </div>
                                <?php if (!empty($booking['image_path'])): ?>
                                <img src="<?php echo htmlspecialchars($booking['image_path']); ?>" alt="Car Image" class="img-fluid rounded" style="width: 80px; height: 60px; object-fit: cover;">
                                <?php endif; ?>
                            </div>
                            
                            <hr class="my-3">
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">Subtotal</span>
                                    <span>₹<?php echo number_format($booking['total_amount'] / 1.18, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">GST (18%)</span>
                                    <span>₹<?php echo number_format($booking['total_amount'] * 0.18, 2); ?></span>
                                </div>
                                <div class="d-flex justify-content-between fw-bold">
                                    <span>Total Amount</span>
                                    <span>₹<?php echo number_format($booking['total_amount'], 2); ?></span>
                                </div>
                            </div>
                            
                            <hr class="my-3">
                            
                            <div class="small">
                                <p class="mb-2"><i class="fas fa-user me-2"></i> <?php echo htmlspecialchars($booking['full_name']); ?></p>
                                <p class="mb-2"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($booking['email']); ?></p>
                                <?php if (!empty($booking['phone'])): ?>
                                <p class="mb-0"><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($booking['phone']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card bg-dark text-white">
                            <div class="card-body">
                                <h6><i class="fas fa-shield-alt me-2 text-warning"></i> Secure Payment</h6>
                                <p class="small mb-0">Your payment information is processed securely. We do not store your credit card details.</p>
                                <div class="mt-3 d-flex justify-content-between">
                                    <img src="../assets/images/visa.png" alt="Visa" style="height: 30px;">
                                    <img src="../assets/images/mastercard.png" alt="Mastercard" style="height: 30px;">
                                    <img src="../assets/images/amex.png" alt="American Express" style="height: 30px;">
                                    <img src="../assets/images/rupay.png" alt="RuPay" style="height: 30px;">
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
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Format card number
    document.getElementById('cardNumber').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\s+/g, '');
        if (value.length > 0) {
            value = value.match(new RegExp('.{1,4}', 'g')).join(' ');
        }
        e.target.value = value;
    });
    
    // Format expiry date
    document.getElementById('expiryDate').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        e.target.value = value;
    });
    
    // Prevent form submission if validation fails
    document.getElementById('paymentForm').addEventListener('submit', function(e) {
        const cardNumber = document.getElementById('cardNumber').value.replace(/\s+/g, '');
        const expiryDate = document.getElementById('expiryDate').value;
        const cvv = document.getElementById('cvv').value;
        const cardName = document.getElementById('cardName').value;
        
        if (!cardNumber || cardNumber.length !== 16 || !/^\d+$/.test(cardNumber)) {
            e.preventDefault();
            alert('Please enter a valid 16-digit card number.');
            return false;
        }
        
        if (!expiryDate || !/^\d{2}\/\d{2}$/.test(expiryDate)) {
            e.preventDefault();
            alert('Please enter a valid expiry date (MM/YY).');
            return false;
        }
        
        if (!cvv || !/^\d{3,4}$/.test(cvv)) {
            e.preventDefault();
            alert('Please enter a valid CVV (3 or 4 digits).');
            return false;
        }
        
        if (!cardName.trim()) {
            e.preventDefault();
            alert('Please enter the name on card.');
            return false;
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
    });
    </script>
</body>
</html>

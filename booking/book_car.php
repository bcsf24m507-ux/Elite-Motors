<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in (not admin)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$error = '';
$success = '';

// Get car ID from URL
$car_id = isset($_GET['car_id']) ? (int)$_GET['car_id'] : 0;

if (!$car_id) {
    header('Location: ../index.php');
    exit();
}

// Get car details
$stmt = $pdo->prepare("SELECT c.*, cc.name as category_name FROM cars c 
                      LEFT JOIN car_categories cc ON c.category_id = cc.id 
                      WHERE c.id = ?");
$stmt->execute([$car_id]);
$car = $stmt->fetch();

if (!$car) {
    header('Location: ../index.php');
    exit();
}

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'] ?? '09:00';
    $end_date = $_POST['end_date'];
    $end_time = $_POST['end_time'] ?? '18:00';
    $pickup_location = trim($_POST['pickup_location'] ?? '');
    $return_location = trim($_POST['return_location'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    // Combine date and time
    $start_datetime = $start_date . ' ' . $start_time . ':00';
    $end_datetime = $end_date . ' ' . $end_time . ':00';
    
    // Validation
    if (empty($start_date) || empty($end_date)) {
        $error = 'Please select both pickup and return dates.';
    } elseif (strtotime($start_date) < strtotime('today')) {
        $error = 'Pickup date cannot be in the past.';
    } elseif (strtotime($end_date) < strtotime($start_date)) {
        $error = 'Return date must be on or after pickup date.';
    } elseif ($start_date == $end_date && strtotime($end_time) <= strtotime($start_time)) {
        $error = 'For same-day bookings, return time must be after pickup time.';
    } else {
        // Check for overlapping bookings including pending and unpaid ones to prevent race conditions
        $check_stmt = $pdo->prepare("SELECT b.id, b.status, p.payment_status 
                                  FROM bookings b
                                  LEFT JOIN payments p ON b.id = p.booking_id
                                  WHERE b.car_id = ? 
                                  AND (b.status IN ('pending', 'confirmed', 'active') OR 
                                      (p.payment_status IS NULL OR p.payment_status != 'completed'))
                                  AND NOT (CONCAT(b.end_date, ' ', b.end_time) <= ? OR CONCAT(b.start_date, ' ', b.start_time) >= ?)");
        $check_stmt->execute([$car_id, $start_datetime, $end_datetime]);
        
        if ($check_stmt->rowCount() > 0) {
            $conflicting_booking = $check_stmt->fetch();
            $status = $conflicting_booking['status'];
            
            if ($status === 'pending' || $conflicting_booking['payment_status'] !== 'completed') {
                $error = 'This car has a pending booking for the selected dates. Please try different dates.';
            } else {
                $error = 'This car is already booked for the selected dates. Please try different dates.';
            }
        } else {
            // Calculate total hours and amount
            $start = new DateTime($start_datetime);
            $end = new DateTime($end_datetime);
            $interval = $start->diff($end);
            
            // Calculate total hours
            $total_hours = $interval->days * 24 + $interval->h + ($interval->i / 60);
            
            // Minimum 1 hour booking
            if ($total_hours < 1) $total_hours = 1;
            
            // Calculate price (minimum charge is for 1 day)
            $total_days = $interval->days;
            if ($total_days == 0) $total_days = 1; // Minimum 1 day charge
            
            $total_amount = $total_days * $car['price_per_day'];
            
            // For same-day bookings, calculate hourly rate if less than 24 hours
            if ($start_date == $end_date) {
                $hourly_rate = $car['price_per_day'] / 12; // Assuming 12-hour day rate
                $total_amount = ceil($total_hours) * $hourly_rate;
                $total_days = $total_hours / 24; // Store as fraction of a day
            }
            
            // Create booking
            $stmt = $pdo->prepare("INSERT INTO bookings (user_id, car_id, start_date, start_time, end_date, end_time, total_days, total_amount, pickup_location, return_location, notes, status) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $result = $stmt->execute([
                $_SESSION['user_id'], 
                $car_id, 
                $start_date,
                $start_time,
                $end_date,
                $end_time, 
                $total_days, 
                $total_amount, 
                $pickup_location, 
                $return_location, 
                $notes
            ]);
            
            if ($result) {
                $booking_id = $pdo->lastInsertId();
                
                // Create payment record
                $payment_stmt = $pdo->prepare("INSERT INTO payments (booking_id, amount, payment_status) VALUES (?, ?, 'pending')");
                $payment_result = $payment_stmt->execute([$booking_id, $total_amount]);
                
                if ($payment_result) {
                    // Set success message and redirect
                    $_SESSION['booking_success'] = 'Booking created successfully! Your booking is pending confirmation.';
                    header('Location: ../customer/bookings.php');
                    exit();
                } else {
                    $error = 'Error creating payment record. Please try again.';
                }
            } else {
                $error = 'Error creating booking. Please try again.';
                
                // Check for duplicate entry error
                $error_info = $stmt->errorInfo();
                if (isset($error_info[1]) && $error_info[1] == 1062) { // MySQL duplicate entry error code
                    $error = 'This time slot was just booked by another user. Please try different dates.';
                }
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
    <title>Book Car - Car Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-car me-2"></i>ELITE MOTORS
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="../index.php">Home</a>
                <a class="nav-link" href="../customer/dashboard.php">Dashboard</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Book Car</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <!-- Car Details -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <?php if ($car['image_path']): ?>
                                <img src="../<?php echo htmlspecialchars($car['image_path']); ?>" class="img-fluid rounded" alt="Car Image">
                                <?php else: ?>
                                <div class="rounded d-flex align-items-center justify-content-center" style="height: 200px; background-color: #f8f9fa; border: 1px solid #dee2e6;">
                                    <i class="fas fa-car fa-5x text-secondary" style="opacity: 0.3;"></i>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-8">
                                <h4><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h4>
                                <p class="text-muted"><?php echo htmlspecialchars($car['category_name']); ?> • <?php echo $car['year']; ?></p>
                                <p><strong>Price per day:</strong> $<?php echo number_format($car['price_per_day'], 2); ?></p>
                                <p><strong>Transmission:</strong> <?php echo htmlspecialchars($car['transmission']); ?></p>
                                <p><strong>Fuel Type:</strong> <?php echo htmlspecialchars($car['fuel_type']); ?></p>
                                <p><strong>Seats:</strong> <?php echo $car['seats']; ?></p>
                            </div>
                        </div>
                        
                        <!-- Booking Form -->
                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="start_date" class="form-label">Pickup Date *</label>
                                        <input type="date" class="form-control mb-2" id="start_date" name="start_date" 
                                               min="<?php echo date('Y-m-d'); ?>" required 
                                               value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : date('Y-m-d'); ?>">
                                        <select class="form-select" id="start_time" name="start_time" required>
                                            <?php for($h=8; $h<=20; $h++): 
                                                $time = sprintf('%02d:00', $h);
                                                $selected = (isset($_POST['start_time']) && $_POST['start_time'] == $time) ? 'selected' : '';
                                                if(!isset($_POST['start_time']) && $h == 9) $selected = 'selected';
                                            ?>
                                            <option value="<?php echo $time; ?>" <?php echo $selected; ?>>
                                                <?php echo date('h:i A', strtotime($time)); ?>
                                            </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="end_date" class="form-label">Return Date *</label>
                                        <input type="date" class="form-control mb-2" id="end_date" name="end_date" 
                                               min="<?php echo date('Y-m-d'); ?>" required 
                                               value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : date('Y-m-d'); ?>">
                                        <select class="form-select" id="end_time" name="end_time" required>
                                            <?php for($h=9; $h<=21; $h++): 
                                                $time = sprintf('%02d:00', $h);
                                                $selected = (isset($_POST['end_time']) && $_POST['end_time'] == $time) ? 'selected' : '';
                                                if(!isset($_POST['end_time']) && $h == 18) $selected = 'selected';
                                            ?>
                                            <option value="<?php echo $time; ?>" <?php echo $selected; ?>>
                                                <?php echo date('h:i A', strtotime($time)); ?>
                                            </option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="pickup_location" class="form-label">Pickup Location</label>
                                        <input type="text" class="form-control" id="pickup_location" name="pickup_location" 
                                               value="<?php echo isset($_POST['pickup_location']) ? htmlspecialchars($_POST['pickup_location']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="return_location" class="form-label">Return Location</label>
                                        <input type="text" class="form-control" id="return_location" name="return_location" 
                                               value="<?php echo isset($_POST['return_location']) ? htmlspecialchars($_POST['return_location']) : ''; ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Additional Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                            </div>
                            
                            <div class="alert alert-info" style="background: #FFF9E6; border: 2px solid #FFD700; color: #000000;">
                                <strong style="color: #000000; font-size: 1.2rem;">Estimated Total:</strong> 
                                <span id="estimated_total" style="color: #198754; font-size: 1.3rem; font-weight: bold;">$0.00</span>
                                <span id="days_info" style="color: #666666; font-size: 1.1rem;"></span>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check me-2"></i>Confirm Booking
                                </button>
                                <a href="../index.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    const pricePerDay = <?php echo $car['price_per_day']; ?>;
    const hourlyRate = Math.ceil(pricePerDay / 12); // Calculate hourly rate (12-hour day)
    
    function updateBookingSummary() {
        const startDate = document.getElementById('start_date').value;
        const startTime = document.getElementById('start_time').value;
        const endDate = document.getElementById('end_date').value;
        const endTime = document.getElementById('end_time').value;
        
        if (!startDate || !endDate) return;
        
        const start = new Date(startDate + 'T' + startTime);
        const end = new Date(endDate + 'T' + endTime);
        
        // Calculate total hours
        const diffMs = end - start;
        const diffHours = Math.ceil(diffMs / (1000 * 60 * 60));
        
        // Calculate total days (minimum 1 day)
        const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));
        
        // Calculate total amount
        let totalAmount = 0;
        let summaryText = '';
        
        if (startDate === endDate) {
            // Same day booking (hourly rate)
            const hours = Math.max(1, diffHours); // Minimum 1 hour
            totalAmount = hours * hourlyRate;
            summaryText = `${hours} hour${hours > 1 ? 's' : ''} (Hourly Rate: $${hourlyRate.toFixed(2)}/hr)`;
        } else {
            // Multi-day booking (daily rate)
            const days = Math.max(1, diffDays); // Minimum 1 day
            totalAmount = days * pricePerDay;
            summaryText = `${days} day${days > 1 ? 's' : ''} (Daily Rate: $${pricePerDay.toFixed(2)}/day)`;
        }
        
        // Update the summary
        const summaryElement = document.getElementById('booking-summary');
        const totalElement = document.getElementById('total-amount');
        
        if (summaryElement) {
            summaryElement.textContent = `Booking for ${summaryText}`;
        }
        if (totalElement) {
            totalElement.textContent = `$${totalAmount.toFixed(2)}`;
        }
    }
    
    // Add event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Update summary when any date/time input changes
        ['start_date', 'start_time', 'end_date', 'end_time'].forEach(id => {
            document.getElementById(id).addEventListener('change', updateBookingSummary);
        });
        
        // Set minimum end date to start date
        document.getElementById('start_date').addEventListener('change', function() {
            document.getElementById('end_date').min = this.value;
            if (new Date(document.getElementById('end_date').value) < new Date(this.value)) {
                document.getElementById('end_date').value = this.value;
            }
            updateBookingSummary();
        });
        
        // Initial update
        updateBookingSummary();
    });
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const estimatedTotal = document.getElementById('estimated_total');
    const daysInfo = document.getElementById('days_info');
    
    function calculateTotal() {
        const startDate = document.getElementById('start_date').value;
        const startTime = document.getElementById('start_time').value;
        const endDate = document.getElementById('end_date').value;
        const endTime = document.getElementById('end_time').value;
        
        if (startDate && endDate) {
            const start = new Date(`${startDate}T${startTime}:00`);
            const end = new Date(`${endDate}T${endTime}:00`);
            
            // Calculate difference in hours
            const diffMs = Math.max(0, end - start);
            const diffHours = Math.ceil(diffMs / (1000 * 60 * 60));
            
            // Always charge at least 1 day
            const diffDays = Math.max(1, Math.ceil(diffHours / 24));
            const total = diffDays * pricePerDay;
            
            // Format the display
            estimatedTotal.textContent = '$' + total.toFixed(2);
            
            if (diffHours <= 24) {
                // For same day or less than 24 hours
                const hours = Math.max(1, diffHours);
                daysInfo.textContent = `(1 day minimum charge)`;
            } else {
                // For multiple days
                daysInfo.textContent = `(${diffDays} day${diffDays > 1 ? 's' : ''} @ $${pricePerDay.toFixed(2)}/day)`;
            }
        } else {
            estimatedTotal.textContent = '$0.00';
            daysInfo.textContent = '';
        }
    }
    
    startDateInput.addEventListener('change', function() {
        if (endDateInput.value && new Date(endDateInput.value) <= new Date(this.value)) {
            endDateInput.value = '';
        }
        endDateInput.min = this.value;
        calculateTotal();
    });
    
    endDateInput.addEventListener('change', calculateTotal);
    </script>
</body>
</html>

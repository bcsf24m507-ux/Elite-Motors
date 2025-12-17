<?php
// File: customer/booking_history.php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$userId = $_SESSION['user_id'];

// Get user's bookings
$stmt = $pdo->prepare("
    SELECT 
        b.*, 
        c.brand, 
        c.model, 
        c.license_plate,
        c.image_path,
        p.status as payment_status
    FROM bookings b
    JOIN cars c ON b.car_id = c.id
    LEFT JOIN payments p ON b.id = p.booking_id
    WHERE b.user_id = ?
    ORDER BY b.created_at DESC
");
$stmt->execute([$userId]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - Car Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .status-badge { padding: 0.35em 0.65em; font-size: 0.75rem; }
        .card { transition: transform 0.2s; margin-bottom: 1.5rem; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .car-image { 
            height: 150px; 
            object-fit: cover; 
            width: 100%;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">Car Rental</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="booking_history.php">My Bookings</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="../auth/logout.php" class="btn btn-outline-light">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>My Bookings</h2>
            <a href="../index.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> New Booking
            </a>
        </div>

        <?php if (empty($bookings)): ?>
            <div class="alert alert-info">
                You don't have any bookings yet. <a href="../index.php">Book a car now!</a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($bookings as $booking): 
                    $statusClass = [
                        'pending' => 'bg-warning',
                        'confirmed' => 'bg-info',
                        'active' => 'bg-primary',
                        'completed' => 'bg-success',
                        'cancelled' => 'bg-danger'
                    ][$booking['status']] ?? 'bg-secondary';
                    
                    $paymentClass = [
                        'pending' => 'bg-warning',
                        'paid' => 'bg-success',
                        'failed' => 'bg-danger',
                        'refunded' => 'bg-info'
                    ][$booking['payment_status'] ?? 'pending'] ?? 'bg-secondary';
                    
                    $canCancel = $booking['status'] === 'pending' || $booking['status'] === 'confirmed';
                ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <?php if ($booking['image_path']): ?>
                            <img src="../<?= htmlspecialchars($booking['image_path']) ?>" 
                                 class="card-img-top car-image" 
                                 alt="<?= htmlspecialchars($booking['brand'] . ' ' . $booking['model']) ?>">
                        <?php else: ?>
                            <div class="bg-light text-center py-5">
                                <i class="bi bi-car-front fs-1 text-muted"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title">
                                <?= htmlspecialchars($booking['brand'] . ' ' . $booking['model']) ?>
                                <span class="badge <?= $statusClass ?> float-end">
                                    <?= ucfirst($booking['status']) ?>
                                </span>
                            </h5>
                            
                            <p class="card-text">
                                <i class="bi bi-calendar-check"></i> 
                                <?= date('M j, Y', strtotime($booking['start_date'])) ?> - 
                                <?= date('M j, Y', strtotime($booking['end_date'])) ?>
                                <br>
                                <small class="text-muted">
                                    <?= (new DateTime($booking['start_date']))->diff(new DateTime($booking['end_date']))->days + 1 ?> days
                                </small>
                            </p>
                            
                            <p class="card-text">
                                <i class="bi bi-credit-card"></i> 
                                Total: $<?= number_format($booking['total_amount'], 2) ?>
                                <span class="badge <?= $paymentClass ?> ms-2">
                                    <?= ucfirst($booking['payment_status'] ?? 'pending') ?>
                                </span>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="booking_details.php?id=<?= $booking['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> View Details
                                </a>
                                
                                <?php if ($canCancel): ?>
                                    <button class="btn btn-sm btn-outline-danger cancel-booking" 
                                            data-booking-id="<?= $booking['id'] ?>">
                                        <i class="bi bi-x-circle"></i> Cancel
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cancel Booking Modal -->
    <div class="modal fade" id="cancelBookingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="cancelBookingForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Cancel Booking</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="booking_id" id="cancelBookingId">
                        <p>Are you sure you want to cancel this booking? This action cannot be undone.</p>
                        <div class="mb-3">
                            <label for="cancelReason" class="form-label">Reason for cancellation (optional)</label>
                            <textarea class="form-control" id="cancelReason" name="reason" rows="3"></textarea>
                        </div>
                        <div id="cancelAlert" class="alert d-none" role="alert"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger" id="confirmCancelBtn">
                            <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true"></span>
                            Confirm Cancellation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle cancel booking button clicks
        document.querySelectorAll('.cancel-booking').forEach(button => {
            button.addEventListener('click', function() {
                const bookingId = this.getAttribute('data-booking-id');
                document.getElementById('cancelBookingId').value = bookingId;
                document.getElementById('cancelAlert').classList.add('d-none');
                
                const modal = new bootstrap.Modal(document.getElementById('cancelBookingModal'));
                modal.show();
            });
        });

        // Handle cancel booking form submission
        document.getElementById('cancelBookingForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const form = this;
            const formData = new FormData(form);
            const submitBtn = form.querySelector('button[type="submit"]');
            const spinner = submitBtn.querySelector('.spinner-border');
            const alertDiv = document.getElementById('cancelAlert');
            
            // Show loading state
            submitBtn.disabled = true;
            spinner.classList.remove('d-none');
            alertDiv.classList.add('d-none');
            
            // Send AJAX request to cancel booking
            fetch('cancel_booking.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal and show success message
                    const modal = bootstrap.Modal.getInstance(document.getElementById('cancelBookingModal'));
                    modal.hide();
                    showAlert('success', 'Your booking has been cancelled successfully.');
                    
                    // Reload the page after a short delay
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    throw new Error(data.message || 'Failed to cancel booking');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alertDiv.textContent = error.message || 'An error occurred while cancelling the booking.';
                alertDiv.classList.remove('d-none', 'alert-success', 'alert-danger');
                alertDiv.classList.add('alert-danger');
                submitBtn.disabled = false;
                spinner.classList.add('d-none');
            });
        });

        // Show alert message
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            alertDiv.role = 'alert';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                const alert = bootstrap.Alert.getOrCreateInstance(alertDiv);
                alert.close();
            }, 5000);
        }
    </script>
</body>
</html>
<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/admin_login.php');
    exit();
}

$message = '';
$error = '';

// Handle payment operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_payment_status':
                $id = (int)$_POST['id'];
                $status = $_POST['status'];

                try {
                    $stmt = $pdo->prepare("UPDATE payments SET payment_status = ? WHERE id = ?");
                    $stmt->execute([$status, $id]);
                    
                    // If payment is marked as 'paid', automatically confirm the booking
                    if ($status == 'paid') {
                        // Get booking_id from payment
                        $payment_stmt = $pdo->prepare("SELECT booking_id FROM payments WHERE id = ?");
                        $payment_stmt->execute([$id]);
                        $payment = $payment_stmt->fetch();
                        
                        if ($payment) {
                            // Update booking status to 'confirmed' if it's currently 'pending'
                            $booking_stmt = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ? AND status = 'pending'");
                            $booking_stmt->execute([$payment['booking_id']]);
                        }
                    }
                    
                    $message = 'Payment status updated successfully!';
                    if ($status == 'paid') {
                        $message .= ' Booking has been automatically confirmed.';
                    }
                } catch (PDOException $e) {
                    $error = 'Error updating payment: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Get total payments count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM payments");
$total_payments = $stmt->fetch()['total'];
$total_pages = ceil($total_payments / $limit);

// Get payments with pagination
$stmt = $pdo->prepare("SELECT p.id, p.booking_id, p.amount, p.payment_status, p.created_at, b.start_date, b.end_date, b.total_amount as booking_amount,
                    u.full_name as customer_name, c.brand, c.model
                    FROM payments p 
                    JOIN bookings b ON p.booking_id = b.id 
                    JOIN users u ON b.user_id = u.id 
                    JOIN cars c ON b.car_id = c.id 
                    ORDER BY p.created_at DESC
                    LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$payments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payments - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
</head>

<body class="admin-page">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-user-shield me-2"></i>Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['admin_full_name']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="dashboard.php"><i
                                    class="fas fa-tachometer-alt me-2"></i>Dashboard</a></li>
                        <li><a class="dropdown-item" href="cars.php"><i class="fas fa-car me-2"></i>Manage Cars</a></li>
                        <li><a class="dropdown-item" href="bookings.php"><i class="fas fa-calendar me-2"></i>Manage
                                Bookings</a></li>
                        <li><a class="dropdown-item" href="customers.php"><i class="fas fa-users me-2"></i>Manage
                                Customers</a></li>
                        <li><a class="dropdown-item" href="payments.php"><i class="fas fa-credit-card me-2"></i>Manage
                                Payments</a></li>
                        <li><a class="dropdown-item" href="reports.php"><i class="fas fa-chart-bar me-2"></i>Reports</a>
                        </li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i
                                    class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <div class="row">
            <div class="col-lg-3">
                <div class="sidebar">
                    <h5 class="mb-3">Admin Menu</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="cars.php">
                                <i class="fas fa-car me-2"></i>Manage Cars
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings.php">
                                <i class="fas fa-calendar me-2"></i>Manage Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="customers.php">
                                <i class="fas fa-users me-2"></i>Manage Customers
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="payments.php">
                                <i class="fas fa-credit-card me-2"></i>Manage Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="dashboard-container" style="min-height: 80vh; overflow-y: auto;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title" style="color: #000000; font-size: 2.5rem;">Manage Payments</h1>
                            <p style="color: #666666; font-size: 1.2rem;">View and manage all payment transactions</p>
                        </div>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Payment ID</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Customer</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Car</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Amount</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Status</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Date</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td style="font-size: 1.2rem; padding: 1.2rem;">
                                        <strong style="color: #000000; font-size: 1.3rem; font-weight: bold;">#<?php echo $payment['id']; ?></strong>
                                        <br>
                                        <small style="color: #666666; font-size: 1.1rem;">Booking
                                            #<?php echo $payment['booking_id'] ?? 'N/A'; ?></small>
                                    </td>
                                    <td style="font-size: 1.2rem; padding: 1.2rem;">
                                        <strong style="color: #000000; font-size: 1.3rem; font-weight: bold;"><?php echo htmlspecialchars($payment['customer_name']); ?></strong>
                                    </td>
                                    <td style="font-size: 1.2rem; padding: 1.2rem;">
                                        <div>
                                            <strong style="color: #000000; font-size: 1.3rem; font-weight: bold;"><?php echo htmlspecialchars($payment['brand'] . ' ' . $payment['model']); ?></strong>
                                            <br>
                                            <small style="color: #666666; font-size: 1.1rem;"><?php echo date('M j', strtotime($payment['start_date'])); ?>
                                                - <?php echo date('M j', strtotime($payment['end_date'])); ?></small>
                                        </div>
                                    </td>
                                    <td style="font-size: 1.2rem; padding: 1.2rem;">
                                        <strong style="color: #198754; font-size: 1.4rem; font-weight: bold;">$<?php echo number_format($payment['amount'], 2); ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                            $status_class = '';
                                            switch ($payment['payment_status']) {
                                                case 'pending':
                                                    $status_class = 'bg-warning';
                                                    break;
                                                case 'paid':
                                                    $status_class = 'bg-success';
                                                    break;
                                                case 'failed':
                                                    $status_class = 'bg-danger';
                                                    break;
                                                case 'refunded':
                                                    $status_class = 'bg-secondary';
                                                    break;
                                            }
                                            ?>
                                        <span
                                            class="badge <?php echo $status_class; ?>"><?php echo ucfirst($payment['payment_status']); ?></span>
                                    </td>
                                    <td style="font-size: 1.2rem; padding: 1.2rem;">
                                        <div>
                                            <strong style="color: #000000; font-size: 1.3rem; font-weight: bold;"><?php echo date('M j, Y', strtotime($payment['created_at'])); ?></strong>
                                            <br>
                                            <small style="color: #666666; font-size: 1.1rem;"><?php echo date('g:i A', strtotime($payment['created_at'])); ?></small>
                                        </div>
                                    </td>
                                    <td style="font-size: 1.2rem; padding: 1.2rem;">
                                        <button class="btn btn-sm btn-outline-primary"
                                            onclick="updatePaymentStatus(<?php echo $payment['id']; ?>, '<?php echo $payment['payment_status']; ?>')"
                                            style="font-size: 1.1rem; padding: 0.6rem 1.2rem; color: #000000; border-color: #FFD700; background: #FFD700; font-weight: bold;">
                                            <i class="fas fa-edit"></i> Update
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Payment pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                            </li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Payment Status Modal -->
    <div class="modal fade" id="updatePaymentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Payment Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="updatePaymentForm">
                    <input type="hidden" name="action" value="update_payment_status">
                    <input type="hidden" name="id" id="update_payment_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="payment_status" class="form-label">Payment Status</label>
                            <select class="form-select" id="payment_status" name="status" required>
                                <option value="pending">Pending</option>
                                <option value="paid">Paid</option>
                                <option value="failed">Failed</option>
                                <option value="refunded">Refunded</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function updatePaymentStatus(paymentId, currentStatus) {
        document.getElementById('update_payment_id').value = paymentId;
        document.getElementById('payment_status').value = currentStatus;
        new bootstrap.Modal(document.getElementById('updatePaymentModal')).show();
    }
    </script>
</body>

</html>
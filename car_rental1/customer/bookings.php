<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

$message = '';
$error = '';

// Handle booking operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_status':
                $id = (int)$_POST['id'];
                $status = $_POST['status'];

                try {
                    $stmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
                    $stmt->execute([$status, $id]);
                    $message = 'Booking status updated successfully!';
                } catch (PDOException $e) {
                    $error = 'Error updating booking: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Get total bookings count for this user
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_bookings = $stmt->fetch()['total'];
$total_pages = ceil($total_bookings / $limit);

// Get user's bookings with pagination (only their own bookings)
$stmt = $pdo->prepare("SELECT b.*, u.full_name as customer_name, u.email as customer_email, 
                      c.brand, c.model, c.year, c.image_path, cc.name as category_name 
                      FROM bookings b 
                      JOIN users u ON b.user_id = u.id 
                      JOIN cars c ON b.car_id = c.id 
                      LEFT JOIN car_categories cc ON c.category_id = cc.id 
                      WHERE b.user_id = ?
                      ORDER BY b.created_at DESC 
                      LIMIT ? OFFSET ?");
$stmt->bindValue(1, $_SESSION['user_id'], PDO::PARAM_INT);
$stmt->bindValue(2, $limit, PDO::PARAM_INT);
$stmt->bindValue(3, $offset, PDO::PARAM_INT);
$stmt->execute();
$bookings = $stmt->fetchAll();

// Get booking statistics for this user
$stats = [];
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM bookings WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$stats['total_bookings'] = $stmt->fetch()['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as active FROM bookings WHERE user_id = ? AND status IN ('confirmed', 'active')");
$stmt->execute([$_SESSION['user_id']]);
$stats['active_bookings'] = $stmt->fetch()['active'];

$stmt = $pdo->prepare("SELECT SUM(total_amount) as total FROM bookings WHERE user_id = ? AND status = 'completed'");
$stmt->execute([$_SESSION['user_id']]);
$stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - ELITE MOTORS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <style>
        body {
            background: #000000;
            color: #ffffff;
        }
        .sidebar {
            background: #0f0f0f;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #333;
        }
        .sidebar .nav-link {
            color: #d1c58a;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #1a1a1a;
            color: #FFD700;
        }
        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 10px;
        }
        .table-modern {
            background: #0f0f0f;
            color: #ffffff;
            border-radius: 10px;
            overflow: hidden;
        }
        .table-modern thead th {
            background: #1a1a1a;
            color: #FFD700;
            font-weight: 600;
            border-bottom: 2px solid #333;
        }
        .table-modern tbody tr {
            border-bottom: 1px solid #333;
            transition: all 0.3s;
        }
        .table-modern tbody tr:hover {
            background: #1a1a1a;
        }
        .table-modern tbody td {
            vertical-align: middle;
            color: #d1c58a;
        }
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: capitalize;
        }
        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #FFC107;
        }
        .status-confirmed {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        .status-cancelled {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        .status-completed {
            background: rgba(108, 117, 125, 0.2);
            color: #6c757d;
        }
        .btn-gold {
            background: #FFD700;
            color: #000;
            font-weight: 600;
            border: none;
            transition: all 0.3s;
        }
        .btn-gold:hover {
            background: #e6c200;
            color: #000;
            transform: translateY(-2px);
        }
        .stat-card {
            background: #0f0f0f;
            border: 1px solid #333;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
        }
        .stat-card i {
            font-size: 2rem;
            margin-bottom: 15px;
            color: #FFD700;
        }
        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #FFD700;
            margin-bottom: 5px;
        }
        .stat-card p {
            color: #d1c58a;
            margin-bottom: 0;
        }
        .modal-content {
            background: #0f0f0f;
            color: #ffffff;
            border: 1px solid #333;
        }
        .modal-header {
            border-bottom: 1px solid #333;
        }
        .modal-footer {
            border-top: 1px solid #333;
        }
        .form-control, .form-select {
            background: #1a1a1a;
            border: 1px solid #333;
            color: #ffffff;
        }
        .form-control:focus, .form-select:focus {
            background: #1a1a1a;
            color: #ffffff;
            border-color: #FFD700;
            box-shadow: 0 0 0 0.25rem rgba(255, 215, 0, 0.25);
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
                        <li><a class="dropdown-item" href="bookings.php"><i class="fas fa-calendar me-2"></i>My Bookings</a></li>
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user-edit me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="../index.php"><i class="fas fa-car me-2"></i>Browse Cars</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-5" style="background-color: #000000; color: #FFFFFF;">
        <div class="row">
            <div class="col-lg-3">
                <div class="sidebar">
                    <h5 class="mb-3">Navigation</h5>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="bookings.php">
                                <i class="fas fa-calendar me-2"></i>My Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user-edit me-2"></i>Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-car me-2"></i>Browse Cars
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="col-lg-9">
                <div class="dashboard-container" style="min-height: 80vh; overflow-y: auto; background-color: #000000; color: #FFFFFF;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title" style="color: #FFD700; font-size: 2.5rem;">My Bookings</h1>
                            <p style="color: #d1c58a; font-size: 1.2rem;">View and manage your car rental bookings.</p>
                            <?php if (isset($_SESSION['booking_success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['booking_success']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['booking_success']); endif; ?>
                        </div>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- Statistics Cards -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon text-primary">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                            <div class="stat-label">Total Bookings</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon text-success">
                                <i class="fas fa-car"></i>
                            </div>
                            <div class="stat-number"><?php echo $stats['active_bookings']; ?></div>
                            <div class="stat-label">Active Bookings</div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon text-warning">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>
                    </div>

                    <!-- Bookings Table -->
                    <div class="mt-5">
                        <?php if (empty($bookings)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times text-muted mb-3" style="font-size: 3rem;"></i>
                            <h5>No bookings found</h5>
                            <p class="text-muted">No bookings have been made yet</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive" style="max-height: 60vh; overflow-y: auto; background-color: #000000;">
                            <table class="table table-modern" style="background-color: #000000; color: #FFFFFF;">
                                <thead>
                                    <tr style="background-color: #121212; color: #FFD700;">
                                    <th style="font-size: 1.2rem; padding: 1.2rem; color: #FFD700;">Customer</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Car</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Pickup Date</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Return Date</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Duration</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Amount</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Status</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                    <tr style="background:#0b0b0b; color:#FFD700;">
                                        <td>
                                            <div>
                                                <div class="fw-bold" style="color: #FFD700; font-size: 1.2rem;"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                                                <small style="color: #d1c58a; font-size: 1rem;"><?php echo htmlspecialchars($booking['customer_email']); ?></small>
                                            </div>
                                        </td>
                                    <td style="padding: 1.2rem;">
                                        <div class="d-flex align-items-center">
                                            <?php if ($booking['image_path']): ?>
                                            <img src="../<?php echo htmlspecialchars($booking['image_path']); ?>"
                                                class="rounded me-3" width="80" height="60"
                                                style="object-fit: cover; border: 2px solid #FFD700;">
                                            <?php else: ?>
                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center"
                                                style="width: 80px; height: 60px; background: #F5F5F5 !important; border: 1px solid #E0E0E0;">
                                                <i class="fas fa-car" style="color: #666666; font-size: 1.5rem;"></i>
                                            </div>
                                            <?php endif; ?>
                                            <div>
                                                <div class="fw-bold" style="color: #FFD700; font-size: 1.3rem; font-weight: bold;">
                                                    <?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model']); ?>
                                                </div>
                                                <small style="color: #d1c58a; font-size: 1.1rem;"><?php echo htmlspecialchars($booking['category_name']); ?>
                                                    • <?php echo $booking['year']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                        <td>
                                            <div class="fw-bold" style="color: #FFD700; font-size: 1.2rem;">
                                                <?php echo date('M j, Y', strtotime($booking['start_date'])); ?></div>
                                            <small style="color:#d1c58a; font-size: 1rem;"><?php echo date('g:i A', strtotime($booking['start_date'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold" style="color: #FFD700; font-size: 1.2rem;">
                                                <?php echo date('M j, Y', strtotime($booking['end_date'])); ?></div>
                                            <small style="color:#d1c58a; font-size: 1rem;"><?php echo date('g:i A', strtotime($booking['end_date'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold" style="color: #FFD700; font-size: 1.2rem;"><?php echo $booking['total_days']; ?> days</div>
                                        </td>
                                        <td>
                                            <div class="fw-bold" style="color: #FFD700; font-size: 1.3rem; font-weight: bold;">
                                                $<?php echo number_format($booking['total_amount'], 2); ?></div>
                                        </td>
                                        <td>
                                            <?php
                                                    $status_class = '';
                                                    switch ($booking['status']) {
                                                        case 'pending':
                                                            $status_class = 'bg-warning';
                                                            break;
                                                        case 'confirmed':
                                                            $status_class = 'bg-info';
                                                            break;
                                                        case 'active':
                                                            $status_class = 'bg-success';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'bg-secondary';
                                                            break;
                                                        case 'cancelled':
                                                            $status_class = 'bg-danger';
                                                            break;
                                                    }
                                                    ?>
                                            <span
                                                class="badge <?php echo $status_class; ?>"><?php echo ucfirst($booking['status']); ?></span>
                                        </td>
                                        <td style="font-size: 1.1rem; padding: 1.2rem;">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#statusModal<?php echo $booking['id']; ?>"
                                                    style="font-size: 1.1rem; padding: 0.6rem 1.2rem; color: #000000; border-color: #FFD700; background: #FFD700; font-weight: bold;">
                                                    <i class="fas fa-edit"></i> Update
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Status Update Modal -->
                                    <div class="modal fade" id="statusModal<?php echo $booking['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Update Booking Status</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="id" value="<?php echo $booking['id']; ?>">
                                                    <div class="modal-body">
                                                        <div class="mb-3">
                                                            <label for="status<?php echo $booking['id']; ?>" class="form-label">Status</label>
                                                            <select class="form-select" id="status<?php echo $booking['id']; ?>" name="status" required>
                                                                <option value="pending" <?php echo $booking['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                                <option value="confirmed" <?php echo $booking['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                                <option value="active" <?php echo $booking['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                                <option value="completed" <?php echo $booking['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                                <option value="cancelled" <?php echo $booking['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
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
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <nav aria-label="Bookings pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

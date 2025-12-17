<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/admin_login.php');
    exit();
}

// Get dashboard statistics
$stats = [];

// Total cars
$stmt = $pdo->query("SELECT COUNT(*) as total FROM cars");
$stats['total_cars'] = $stmt->fetch()['total'];

// Available cars (exclude cars with CONFIRMED and ACTIVE bookings only - PENDING does NOT block)
// Get list of cars that are actually available
$available_cars_query = $pdo->query("SELECT c.id, c.brand, c.model 
                                     FROM cars c 
                                     WHERE c.status = 'available' 
                                     AND c.id NOT IN (
                                         SELECT DISTINCT car_id FROM bookings 
                                         WHERE status IN ('confirmed', 'active')
                                         AND end_date >= CURDATE()
                                     )
                                     ORDER BY c.brand, c.model");
$available_cars_list = $available_cars_query->fetchAll();

$stats['available_cars'] = count($available_cars_list);
$stats['available_cars_details'] = $available_cars_list; // Store for display

// Total bookings
$stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings");
$stats['total_bookings'] = $stmt->fetch()['total'];

// Active bookings
$stmt = $pdo->query("SELECT COUNT(*) as total FROM bookings WHERE status IN ('confirmed', 'active')");
$stats['active_bookings'] = $stmt->fetch()['total'];

// Total revenue
$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM bookings WHERE status = 'completed'");
$stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;

// Total customers
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$stats['total_customers'] = $stmt->fetch()['total'];

// Recent cars
$stmt = $pdo->query("SELECT * FROM cars ORDER BY created_at DESC LIMIT 5");
$recent_cars = $stmt->fetchAll();

// Recent bookings
$stmt = $pdo->query("SELECT b.*, u.full_name FROM bookings b JOIN users u ON b.user_id = u.id ORDER BY b.created_at DESC LIMIT 5");
$recent_bookings = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Car Rental System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
</head>

<body class="admin-page">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php" style="font-size: 1.8rem; font-weight: bold;">
                <i class="fas fa-user-shield me-2"></i>Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" style="font-size: 1.3rem; padding: 0.75rem 1.5rem;">
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
                            <a class="nav-link active" href="dashboard.php">
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
                            <a class="nav-link" href="payments.php">
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
                    <!-- Larger and scrollable -->
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title" style="color: #000000; font-size: 2.5rem;">Dashboard Overview</h1>
                            <p style="color: #666666; font-size: 1.2rem;">Welcome back,
                                <?php echo htmlspecialchars($_SESSION['admin_full_name']); ?>!</p>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="stats-grid">
                        <a href="cars.php" style="text-decoration: none; color: inherit;">
                            <div class="stat-card" style="cursor: pointer;">
                                <div class="stat-icon text-primary">
                                    <i class="fas fa-car"></i>
                                </div>
                                <div class="stat-number"><?php echo $stats['total_cars']; ?></div>
                                <div class="stat-label">Total Cars</div>
                            </div>
                        </a>

                        <div class="stat-card" style="cursor: pointer;" onclick="showAvailableCarsModal()">
                            <div class="stat-icon text-success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-number"><?php echo $stats['available_cars']; ?></div>
                            <div class="stat-label">Available Cars</div>
                        </div>

                        <a href="bookings.php" style="text-decoration: none; color: inherit;">
                            <div class="stat-card" style="cursor: pointer;">
                                <div class="stat-icon text-info">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                                <div class="stat-label">Total Bookings</div>
                            </div>
                        </a>

                        <a href="bookings.php?filter=active" style="text-decoration: none; color: inherit;">
                            <div class="stat-card" style="cursor: pointer;">
                                <div class="stat-icon text-warning">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="stat-number"><?php echo $stats['active_bookings']; ?></div>
                                <div class="stat-label">Active Bookings</div>
                            </div>
                        </a>

                        <div class="stat-card">
                            <div class="stat-icon text-success">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                            <div class="stat-number">$<?php echo number_format($stats['total_revenue'], 2); ?></div>
                            <div class="stat-label">Total Revenue</div>
                        </div>

                        <a href="customers.php" style="text-decoration: none; color: inherit;">
                            <div class="stat-card" style="cursor: pointer;">
                                <div class="stat-icon text-secondary">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="stat-number"><?php echo $stats['total_customers']; ?></div>
                                <div class="stat-label">Total Customers</div>
                            </div>
                        </a>
                    </div>

                    <div class="row mt-5">
                        <!-- Recent Bookings -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-calendar me-2"></i>Recent Bookings</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_bookings)): ?>
                                    <p class="text-muted">No recent bookings</p>
                                    <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($recent_bookings as $booking): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?php echo htmlspecialchars($booking['full_name']); ?></strong>
                                                <br>
                                                <small
                                                    class="text-muted"><?php echo date('M j, Y', strtotime($booking['created_at'])); ?></small>
                                            </div>
                                            <span
                                                class="badge bg-<?php echo $booking['status'] == 'confirmed' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Cars -->
                        <div class="col-lg-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-car me-2"></i>Recent Cars</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recent_cars)): ?>
                                    <p class="text-muted">No recent cars</p>
                                    <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($recent_cars as $car): ?>
                                        <li class="list-group-item d-flex align-items-center">
                                            <div class="bg-dark rounded me-3 d-flex align-items-center justify-content-center"
                                                style="width: 40px; height: 30px; background: #000000; color: white;">
                                                <i class="fas fa-car text-warning"></i> <!-- Car icon in dark area -->
                                            </div>
                                            <div>
                                                <div class="fw-bold">
                                                    <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                                                </div>
                                                <small class="text-muted"><?php echo $car['year']; ?> -
                                                    $<?php echo number_format($car['price_per_day'], 2); ?>/day</small>
                                            </div>
                                        </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Available Cars Modal -->
    <div class="modal fade" id="availableCarsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: #FFD700; color: #000000;">
                    <h5 class="modal-title" style="color: #000000; font-size: 1.5rem; font-weight: bold;">
                        <i class="fas fa-check-circle me-2"></i>Available Cars (<?php echo $stats['available_cars']; ?>)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="background: #FFFFFF; color: #000000;">
                    <p style="color: #666666; font-size: 1.1rem; margin-bottom: 1.5rem;">
                        These are the cars that are currently available for booking (not booked, not in maintenance, and not unavailable).
                    </p>
                    <?php if (empty($stats['available_cars_details'])): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>No cars are currently available.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead style="background: #FFD700; color: #000000;">
                                    <tr>
                                        <th style="font-size: 1.1rem; padding: 1rem;">#</th>
                                        <th style="font-size: 1.1rem; padding: 1rem;">Car</th>
                                        <th style="font-size: 1.1rem; padding: 1rem;">Brand</th>
                                        <th style="font-size: 1.1rem; padding: 1rem;">Model</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['available_cars_details'] as $index => $car): ?>
                                    <tr style="background: #FFFFFF;">
                                        <td style="color: #000000; font-size: 1.1rem; padding: 1rem; font-weight: bold;"><?php echo $index + 1; ?></td>
                                        <td style="color: #000000; font-size: 1.1rem; padding: 1rem;"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></td>
                                        <td style="color: #000000; font-size: 1.1rem; padding: 1rem;"><?php echo htmlspecialchars($car['brand']); ?></td>
                                        <td style="color: #000000; font-size: 1.1rem; padding: 1rem;"><?php echo htmlspecialchars($car['model']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer" style="background: #FFFFFF;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="closeModal()">Close</button>
                    <a href="cars.php?filter=available" class="btn btn-primary" onclick="closeModal()">
                        <i class="fas fa-car me-2"></i>View in Manage Cars
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let availableCarsModal = null;
    
    function showAvailableCarsModal() {
        if (!availableCarsModal) {
            availableCarsModal = new bootstrap.Modal(document.getElementById('availableCarsModal'), {
                backdrop: true,
                keyboard: true
            });
        }
        availableCarsModal.show();
    }
    
    function closeModal() {
        if (availableCarsModal) {
            availableCarsModal.hide();
            // Remove backdrop if it exists
            setTimeout(() => {
                const backdrops = document.querySelectorAll('.modal-backdrop');
                backdrops.forEach(backdrop => backdrop.remove());
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }, 100);
        }
    }
    
    // Clean up on modal close
    document.getElementById('availableCarsModal').addEventListener('hidden.bs.modal', function () {
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    });
    </script>
</body>

</html>
<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/admin_login.php');
    exit();
}

// Get various statistics for reports
$stats = [];

// Total revenue
$stmt = $pdo->query("SELECT SUM(total_amount) as total FROM bookings WHERE status = 'completed'");
$stats['total_revenue'] = $stmt->fetch()['total'] ?? 0;

// Monthly revenue (last 6 months)
$stmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, SUM(total_amount) as revenue 
                    FROM bookings 
                    WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                    ORDER BY month DESC");
$monthly_revenue = $stmt->fetchAll();

// Popular cars (all bookings, not just completed)
$stmt = $pdo->query("SELECT c.brand, c.model, COUNT(b.id) as booking_count, SUM(b.total_amount) as revenue
                    FROM cars c 
                    LEFT JOIN bookings b ON c.id = b.car_id
                    GROUP BY c.id, c.brand, c.model
                    HAVING booking_count > 0
                    ORDER BY booking_count DESC
                    LIMIT 10");
$popular_cars = $stmt->fetchAll();

// Recent bookings
$stmt = $pdo->query("SELECT b.*, u.full_name as customer_name, c.brand, c.model
                    FROM bookings b 
                    JOIN users u ON b.user_id = u.id 
                    JOIN cars c ON b.car_id = c.id 
                    ORDER BY b.created_at DESC 
                    LIMIT 10");
$recent_bookings = $stmt->fetchAll();

// Revenue by month (current year)
$stmt = $pdo->query("SELECT MONTH(created_at) as month, SUM(total_amount) as revenue
                    FROM bookings 
                    WHERE status = 'completed' AND YEAR(created_at) = YEAR(NOW())
                    GROUP BY MONTH(created_at)
                    ORDER BY month");
$revenue_by_month = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?= time() ?>" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <a class="nav-link" href="payments.php">
                                <i class="fas fa-credit-card me-2"></i>Manage Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="reports.php">
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
                            <h1 class="dashboard-title" style="color: #000000; font-size: 2.5rem;">Reports & Analytics</h1>
                            <p style="color: #666666; font-size: 1.2rem;">Comprehensive business insights and statistics</p>
                        </div>
                    </div>

                    <!-- Revenue Overview -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0" style="color: #FFD700; font-size: 1.3rem; font-weight: bold;">Total Revenue</h5>
                                </div>
                                <div class="card-body">
                                    <h2 style="color: #FFD700; font-size: 2.5rem; font-weight: bold;">$<?php echo number_format($stats['total_revenue'], 2); ?>
                                    </h2>
                                    <p style="color: #666666; font-size: 1.1rem;">All time revenue from completed bookings</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0" style="color: #FFD700; font-size: 1.3rem; font-weight: bold;">Monthly Revenue Trend</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="revenueChart" width="400" height="200"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Popular Cars -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0" style="color: #FFD700; font-size: 1.3rem; font-weight: bold;">Most Popular Cars</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-modern">
                                            <thead>
                                                <tr>
                                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Car</th>
                                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Bookings</th>
                                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Revenue</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($popular_cars as $car): ?>
                                                <tr>
                                                    <td style="color: #000000; font-size: 1.2rem; padding: 1.2rem;">
                                                        <strong style="color: #000000;"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></strong>
                                                    </td>
                                                    <td style="color: #000000; font-size: 1.2rem; padding: 1.2rem;">
                                                        <span
                                                            class="badge bg-primary"><?php echo $car['booking_count']; ?></span>
                                                    </td>
                                                    <td style="color: #000000; font-size: 1.2rem; padding: 1.2rem;">
                                                        <strong style="color: #198754; font-weight: bold;">$<?php echo number_format($car['revenue'] ?? 0, 2); ?></strong>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Bookings -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0" style="color: #FFD700; font-size: 1.3rem; font-weight: bold;">Recent Bookings</h5>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-modern">
                                            <thead>
                                                <tr>
                                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Customer</th>
                                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Car</th>
                                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Dates</th>
                                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Amount</th>
                                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_bookings as $booking): ?>
                                                <tr>
                                                    <td style="color: #000000; font-size: 1.2rem; padding: 1.2rem;"><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                                    <td style="color: #000000; font-size: 1.2rem; padding: 1.2rem;"><?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model']); ?>
                                                    </td>
                                                    <td style="color: #000000; font-size: 1.2rem; padding: 1.2rem;">
                                                        <small style="color: #666666; font-size: 1.1rem;"><?php echo date('M j', strtotime($booking['start_date'])); ?>
                                                            -
                                                            <?php echo date('M j', strtotime($booking['end_date'])); ?></small>
                                                    </td>
                                                    <td style="color: #198754; font-size: 1.2rem; font-weight: bold; padding: 1.2rem;">$<?php echo number_format($booking['total_amount'], 2); ?></td>
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
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
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
    // Revenue Chart
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const revenueData = <?php echo json_encode($revenue_by_month); ?>;

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const revenue = new Array(12).fill(0);

    revenueData.forEach(item => {
        revenue[item.month - 1] = parseFloat(item.revenue);
    });

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [{
                label: 'Revenue ($)',
                data: revenue,
                borderColor: 'rgb(75, 192, 192)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    </script>
</body>

</html>
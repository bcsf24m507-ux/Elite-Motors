<?php
session_start();
require_once '../config/database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: ../auth/admin_login.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// Get total customers count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM users");
$total_customers = $stmt->fetch()['total'];
$total_pages = ceil($total_customers / $limit);

// Get customers with pagination
$stmt = $pdo->prepare("SELECT u.*, 
                    COUNT(b.id) as total_bookings,
                    SUM(CASE WHEN b.status = 'completed' THEN b.total_amount ELSE 0 END) as total_spent
                    FROM users u 
                    LEFT JOIN bookings b ON u.id = b.user_id 
                    GROUP BY u.id 
                    ORDER BY u.created_at DESC
                    LIMIT ? OFFSET ?");
$stmt->bindValue(1, $limit, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$customers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Customers - Admin Panel</title>
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
                            <a class="nav-link active" href="customers.php">
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
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title" style="color: #000000; font-size: 2.5rem;">Manage Customers</h1>
                            <p style="color: #666666; font-size: 1.2rem;">View and manage all registered customers</p>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Customer</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Contact</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">License</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Bookings</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Total Spent</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Member Since</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($customers as $customer): ?>
                                <tr style="background: #FFFFFF;">
                                    <td style="padding: 1.2rem;">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary rounded-circle me-3 d-flex align-items-center justify-content-center text-white"
                                                style="width: 50px; height: 50px; font-size: 1.2rem;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <strong style="color: #000000; font-size: 1.3rem; font-weight: bold;"><?php echo htmlspecialchars($customer['full_name']); ?></strong>
                                                <br>
                                                <small style="color: #666666; font-size: 1.1rem;">@<?php echo htmlspecialchars($customer['username']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="padding: 1.2rem;">
                                        <div>
                                            <strong style="color: #000000; font-size: 1.2rem; font-weight: bold;"><?php echo htmlspecialchars($customer['email']); ?></strong>
                                            <?php if ($customer['phone']): ?>
                                            <br>
                                            <small style="color: #666666; font-size: 1.1rem;"><?php echo htmlspecialchars($customer['phone']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="padding: 1.2rem;">
                                        <?php if ($customer['license_number']): ?>
                                        <span
                                            class="badge bg-success" style="font-size: 1rem; padding: 0.5rem 1rem;"><?php echo htmlspecialchars($customer['license_number']); ?></span>
                                        <?php else: ?>
                                        <span class="badge bg-warning" style="font-size: 1rem; padding: 0.5rem 1rem;">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1.2rem;">
                                        <strong style="color: #000000; font-size: 1.3rem; font-weight: bold;"><?php echo $customer['total_bookings']; ?></strong>
                                        <br>
                                        <small style="color: #666666; font-size: 1.1rem;">bookings</small>
                                    </td>
                                    <td style="padding: 1.2rem;">
                                        <strong style="color: #198754; font-size: 1.4rem; font-weight: bold;">$<?php echo number_format($customer['total_spent'] ?? 0, 2); ?></strong>
                                    </td>
                                    <td style="padding: 1.2rem;">
                                        <div>
                                            <strong style="color: #000000; font-size: 1.2rem; font-weight: bold;"><?php echo date('M j, Y', strtotime($customer['created_at'])); ?></strong>
                                            <br>
                                            <small style="color: #666666; font-size: 1.1rem;"><?php echo date('g:i A', strtotime($customer['created_at'])); ?></small>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                    <nav aria-label="Customer pagination">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
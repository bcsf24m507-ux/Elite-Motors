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

// Handle car operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $brand = trim($_POST['brand']);
                $model = trim($_POST['model']);
                $year = (int)$_POST['year'];
                $color = trim($_POST['color']);
                $category_id = (int)$_POST['category_id'];
                $price_per_day = (float)$_POST['price_per_day'];
                $fuel_type = $_POST['fuel_type'];
                $transmission = $_POST['transmission'];
                $seats = (int)$_POST['seats'];
                $features = trim($_POST['features']);
                $image_path = '';
                $image_url = trim($_POST['image_url'] ?? '');

                // Handle image upload or URL
                if (!empty($image_url)) {
                    $image_path = $image_url;
                } elseif (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $upload_dir = '../uploads/cars/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    $file_name = uniqid() . '_' . basename($_FILES['image']['name']);
                    $target_file = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        $image_path = 'uploads/cars/' . $file_name;
                    }
                }

                // Validate image required
                if (empty($image_path)) {
                    $error = 'Please provide a car image (upload or URL) before adding.';
                    break;
                }

                try {
                    $stmt = $pdo->prepare("INSERT INTO cars (brand, model, year, color, category_id, price_per_day, fuel_type, transmission, seats, features, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$brand, $model, $year, $color, $category_id, $price_per_day, $fuel_type, $transmission, $seats, $features, $image_path]);
                    $message = 'Car added successfully!';
                } catch (PDOException $e) {
                    $error = 'Error adding car: ' . $e->getMessage();
                }
                break;
                
            case 'update':
                $id = (int)$_POST['id'];
                $brand = trim($_POST['brand']);
                $model = trim($_POST['model']);
                $year = (int)$_POST['year'];
                $color = trim($_POST['color']);
                $category_id = (int)$_POST['category_id'];
                $price_per_day = (float)$_POST['price_per_day'];
                $fuel_type = $_POST['fuel_type'];
                $transmission = $_POST['transmission'];
                $seats = (int)$_POST['seats'];
                $features = trim($_POST['features']);
                $status = $_POST['status'];
                $image_path = $_POST['existing_image'];
                $image_url = trim($_POST['image_url'] ?? '');

                // Handle image upload, URL, or delete
                if (isset($_POST['delete_image']) && $_POST['delete_image'] == '1') {
                    $image_path = '';
                } elseif (!empty($image_url)) {
                    $image_path = $image_url;
                } elseif (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
                    $upload_dir = '../uploads/cars/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
                    $file_name = uniqid() . '_' . basename($_FILES['image']['name']);
                    $target_file = $upload_dir . $file_name;
                    if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                        $image_path = 'uploads/cars/' . $file_name;
                    }
                }

                try {
                    $stmt = $pdo->prepare("UPDATE cars SET brand=?, model=?, year=?, color=?, category_id=?, price_per_day=?, fuel_type=?, transmission=?, seats=?, features=?, status=?, image_path=? WHERE id=?");
                    $stmt->execute([$brand, $model, $year, $color, $category_id, $price_per_day, $fuel_type, $transmission, $seats, $features, $status, $image_path, $id]);
                    $message = 'Car updated successfully!';
                } catch (PDOException $e) {
                    $error = 'Error updating car: ' . $e->getMessage();
                }
                break;
                
            case 'delete':
                $id = (int)$_POST['id'];
                try {
                    $stmt = $pdo->prepare("DELETE FROM cars WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Car deleted successfully!';
                } catch (PDOException $e) {
                    $error = 'Error deleting car: ' . $e->getMessage();
                }
                break;
                
            case 'delete_image':
                $id = (int)$_POST['id'];
                try {
                    $stmt = $pdo->prepare("UPDATE cars SET image_path = '' WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = 'Car image deleted successfully!';
                } catch (PDOException $e) {
                    $error = 'Error deleting image: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50; // Default page size
$offset = ($page - 1) * $per_page;

// Check if filter is applied
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';

// Build query based on filter
if ($filter == 'available') {
    // Show only available cars (not booked, not maintenance, not unavailable)
    $cars_query = "SELECT c.*, cc.name as category_name,
                   CASE 
                       WHEN EXISTS (
                           SELECT 1 FROM bookings b 
                           WHERE b.car_id = c.id 
                           AND b.status IN ('confirmed', 'active')
                           AND b.end_date >= CURDATE()
                       ) THEN 'booked'
                       ELSE c.status
                   END as actual_status
                   FROM cars c 
                   LEFT JOIN car_categories cc ON c.category_id = cc.id 
                   WHERE c.status = 'available'
                   AND c.id NOT IN (
                       SELECT DISTINCT car_id FROM bookings 
                       WHERE status IN ('confirmed', 'active')
                       AND end_date >= CURDATE()
                   )
                   ORDER BY c.created_at DESC 
                   LIMIT ? OFFSET ?";

    // Update total count for available filter
    $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM cars c 
                               WHERE c.status = 'available' 
                               AND c.id NOT IN (
                                   SELECT DISTINCT car_id FROM bookings 
                                   WHERE status IN ('confirmed', 'active')
                                   AND end_date >= CURDATE()
                               )");
    $total_cars = $total_stmt->fetch()['total'];
    $total_pages = ceil($total_cars / $per_page);
} else {
    // Show all cars
    $cars_query = "SELECT c.*, cc.name as category_name,
                   CASE 
                       WHEN EXISTS (
                           SELECT 1 FROM bookings b 
                           WHERE b.car_id = c.id 
                           AND b.status IN ('confirmed', 'active')
                           AND b.end_date >= CURDATE()
                       ) THEN 'booked'
                       ELSE c.status
                   END as actual_status
                   FROM cars c 
                   LEFT JOIN car_categories cc ON c.category_id = cc.id 
                   ORDER BY c.created_at DESC 
                   LIMIT ? OFFSET ?";

    $total_stmt = $pdo->query("SELECT COUNT(*) as total FROM cars");
    $total_cars = $total_stmt->fetch()['total'];
    $total_pages = ceil($total_cars / $per_page);
}

$stmt = $pdo->prepare($cars_query);
$stmt->bindValue(1, $per_page, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$cars = $stmt->fetchAll();

// Get categories for dropdown
$categories = $pdo->query("SELECT * FROM car_categories ORDER BY name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Cars - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
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
                            <a class="nav-link active" href="cars.php">
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
                    <!-- Scrollable -->
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title" style="color: #000000; font-size: 2.5rem;">Manage Cars</h1>
                            <p style="color: #666666; font-size: 1.2rem;">
                                <?php if ($filter == 'available'): ?>
                                    Viewing Available Cars Only
                                <?php else: ?>
                                    Add, edit, and manage your car inventory
                                <?php endif; ?>
                            </p>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCarModal">
                            <i class="fas fa-plus me-2"></i>Add New Car
                        </button>
                    </div>

                    <?php if ($message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                        <!-- Scrollable table -->
                        <table class="table table-modern">
                            <thead>
                                <tr>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Car</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Category</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Price/Day</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Status</th>
                                    <th style="font-size: 1.2rem; padding: 1.2rem;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cars as $car): ?>
                                <tr style="background: #0b0b0b; color:#FFD700;">
                                    <td style="padding: 1.2rem;">
                                        <div class="d-flex align-items-center">
                                            <div class="bg-light rounded me-3 d-flex align-items-center justify-content-center"
                                                style="width: 80px; height: 60px; background: #111111 !important; border: 1px solid #333333;">
                                                <?php if ($car['image_path']): ?>
                                                    <?php if (filter_var($car['image_path'], FILTER_VALIDATE_URL)): ?>
                                                        <!-- If it's a URL, use it directly -->
                                                        <img src="<?php echo htmlspecialchars($car['image_path']); ?>"
                                                            style="width: 100%; height: 100%; object-fit: cover; border-radius: 5px;"
                                                            alt="Car Image" onerror="this.onerror=null; this.src='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';">
                                                    <?php else: ?>
                                                        <!-- If it's a local path, prepend ../ -->
                                                        <img src="../<?php echo htmlspecialchars($car['image_path']); ?>"
                                                            style="width: 100%; height: 100%; object-fit: cover; border-radius: 5px;"
                                                            alt="Car Image" onerror="this.onerror=null; this.src='data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';">
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <i class="fas fa-car" style="font-size: 1.5rem; color: #666666;"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold" style="color: #FFD700; font-size: 1.3rem; font-weight: bold;">
                                                    <?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                                                </div>
                                                <small style="color: #d1c58a; font-size: 1.1rem;"><?php echo $car['year']; ?> •
                                                    <?php echo $car['color']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="color: #FFD700; font-size: 1.2rem; padding: 1.2rem;"><?php echo htmlspecialchars($car['category_name']); ?></td>
                                    <td style="color: #FFD700; font-size: 1.3rem; font-weight: bold; padding: 1.2rem;">$<?php echo number_format($car['price_per_day'], 2); ?></td>
                                    <td style="padding: 1.2rem;">
                                        <?php
                                            // Use actual_status if available, otherwise use status
                                            $display_status = isset($car['actual_status']) ? $car['actual_status'] : $car['status'];
                                            $status_class = '';
                                            $status_text = '';
                                            
                                            switch ($display_status) {
                                                case 'available': 
                                                    $status_class = 'bg-success'; 
                                                    $status_text = 'Available';
                                                    break;
                                                case 'booked':
                                                    $status_class = 'bg-warning'; 
                                                    $status_text = 'Booked';
                                                    break;
                                                case 'rented': 
                                                    $status_class = 'bg-warning'; 
                                                    $status_text = 'Rented';
                                                    break;
                                                case 'maintenance': 
                                                    $status_class = 'bg-info'; 
                                                    $status_text = 'Maintenance';
                                                    break;
                                                case 'unavailable': 
                                                    $status_class = 'bg-danger'; 
                                                    $status_text = 'Unavailable';
                                                    break;
                                                default:
                                                    $status_class = 'bg-secondary';
                                                    $status_text = ucfirst($display_status);
                                            }
                                            ?>
                                        <span
                                            class="badge <?php echo $status_class; ?>" style="font-size: 1rem; padding: 0.5rem 1rem;"><?php echo $status_text; ?></span>
                                    </td>
                                    <td style="padding: 1.2rem;">
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary"
                                                onclick="editCar(<?php echo htmlspecialchars(json_encode($car)); ?>)"
                                                style="font-size: 1.1rem; padding: 0.6rem 1.2rem; color: #000000; border-color: #FFD700; background: #FFD700; font-weight: bold;">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger"
                                                onclick="deleteCar(<?php echo $car['id']; ?>)"
                                                style="font-size: 1.1rem; padding: 0.6rem 1.2rem;">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <nav aria-label="Page navigation">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Car Modal -->
    <div class="modal fade" id="addCarModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Car</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="brand" class="form-label">Brand *</label>
                                    <input type="text" class="form-control" id="brand" name="brand" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="model" class="form-label">Model *</label>
                                    <input type="text" class="form-control" id="model" name="model" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="year" class="form-label">Year *</label>
                                    <input type="number" class="form-control" id="year" name="year" required min="2000"
                                        max="2024">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="color" class="form-label">Color</label>
                                    <input type="text" class="form-control" id="color" name="color">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="seats" class="form-label">Seats</label>
                                    <input type="number" class="form-control" id="seats" name="seats" min="2" max="8"
                                        value="5">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Category *</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="price_per_day" class="form-label">Price per Day *</label>
                                    <input type="number" class="form-control" id="price_per_day" name="price_per_day"
                                        required min="0" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fuel_type" class="form-label">Fuel Type</label>
                                    <select class="form-select" id="fuel_type" name="fuel_type">
                                        <option value="Petrol">Petrol</option>
                                        <option value="Diesel">Diesel</option>
                                        <option value="Electric">Electric</option>
                                        <option value="Hybrid">Hybrid</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="transmission" class="form-label">Transmission</label>
                                    <select class="form-select" id="transmission" name="transmission">
                                        <option value="Manual">Manual</option>
                                        <option value="Automatic">Automatic</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="image" class="form-label" style="color: #000000; font-size: 1.1rem; font-weight: 600;">Car Image</label>
                            <div class="mb-2">
                                <label class="form-label small" style="color: #000000; font-size: 1rem;">Option 1: Upload from Device (JPG/PNG)</label>
                                <input type="file" class="form-control" id="image" name="image" accept="image/jpeg,image/jpg,image/png" onchange="previewAddImage(this)">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" style="color: #000000; font-size: 1rem;">Option 2: Enter Image URL</label>
                                <input type="url" class="form-control" id="image_url" name="image_url" placeholder="https://example.com/image.jpg" style="font-size: 1.1rem;" oninput="previewAddFromUrl(this.value)">
                            </div>
                            <div id="add_image_preview" class="mt-2"></div>
                            <small class="text-muted" style="color: #666666; font-size: 1rem;">Upload a JPG/PNG image from your device or paste an image URL from the web.</small>
                        </div>

                        <div class="mb-3">
                            <label for="features" class="form-label">Features</label>
                            <textarea class="form-control" id="features" name="features" rows="3"
                                placeholder="Air Conditioning, Bluetooth, GPS, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Car</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Car Modal -->
    <div class="modal fade" id="editCarModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Car</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="editCarForm">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="existing_image" id="edit_existing_image">
                    <div class="modal-body">
                        <!-- Same form fields as add modal -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_brand" class="form-label">Brand *</label>
                                    <input type="text" class="form-control" id="edit_brand" name="brand" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_model" class="form-label">Model *</label>
                                    <input type="text" class="form-control" id="edit_model" name="model" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_year" class="form-label">Year *</label>
                                    <input type="number" class="form-control" id="edit_year" name="year" required
                                        min="2000" max="2024">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_color" class="form-label">Color</label>
                                    <input type="text" class="form-control" id="edit_color" name="color">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_seats" class="form-label">Seats</label>
                                    <input type="number" class="form-control" id="edit_seats" name="seats" min="2"
                                        max="8">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_category_id" class="form-label">Category *</label>
                                    <select class="form-select" id="edit_category_id" name="category_id" required>
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_price_per_day" class="form-label">Price per Day *</label>
                                    <input type="number" class="form-control" id="edit_price_per_day"
                                        name="price_per_day" required min="0" step="0.01">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_fuel_type" class="form-label">Fuel Type</label>
                                    <select class="form-select" id="edit_fuel_type" name="fuel_type">
                                        <option value="Petrol">Petrol</option>
                                        <option value="Diesel">Diesel</option>
                                        <option value="Electric">Electric</option>
                                        <option value="Hybrid">Hybrid</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_transmission" class="form-label">Transmission</label>
                                    <select class="form-select" id="edit_transmission" name="transmission">
                                        <option value="Manual">Manual</option>
                                        <option value="Automatic">Automatic</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_status" class="form-label">Status</label>
                                    <select class="form-select" id="edit_status" name="status">
                                        <option value="available">Available</option>
                                        <option value="rented">Rented</option>
                                        <option value="maintenance">Maintenance</option>
                                        <option value="unavailable">Unavailable</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_image" class="form-label" style="color: #000000; font-size: 1.1rem; font-weight: 600;">Car Image</label>
                            <div id="current_image_preview" class="mb-3"></div>
                            <div class="mb-2">
                                <label class="form-label small" style="color: #000000; font-size: 1rem;">Option 1: Upload from Device (JPG/PNG)</label>
                                <input type="file" class="form-control" id="edit_image" name="image" accept="image/jpeg,image/jpg,image/png">
                            </div>
                            <div class="mb-2">
                                <label class="form-label small" style="color: #000000; font-size: 1rem;">Option 2: Enter Image URL</label>
                                <input type="url" class="form-control" id="edit_image_url" name="image_url" placeholder="https://example.com/image.jpg" style="font-size: 1.1rem;">
                            </div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="delete_image_check" name="delete_image" value="1" style="width: 1.2rem; height: 1.2rem; border: 2px solid #666666;">
                                <label class="form-check-label" for="delete_image_check" style="color: #000000; font-size: 1rem; margin-left: 0.5rem;">
                                    Delete current image
                                </label>
                            </div>
                            <small class="text-muted" style="color: #666666; font-size: 1rem;">Upload a JPG/PNG image, paste an image URL, or delete the current image.</small>
                        </div>

                        <div class="mb-3">
                            <label for="edit_features" class="form-label">Features</label>
                            <textarea class="form-control" id="edit_features" name="features" rows="3"
                                placeholder="Air Conditioning, Bluetooth, GPS, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Car</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Car Form -->
    <form method="POST" id="deleteCarForm" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="delete_id">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editCar(car) {
        document.getElementById('edit_id').value = car.id;
        document.getElementById('edit_brand').value = car.brand;
        document.getElementById('edit_model').value = car.model;
        document.getElementById('edit_year').value = car.year;
        document.getElementById('edit_color').value = car.color;
        document.getElementById('edit_category_id').value = car.category_id;
        document.getElementById('edit_price_per_day').value = car.price_per_day;
        document.getElementById('edit_fuel_type').value = car.fuel_type;
        document.getElementById('edit_transmission').value = car.transmission;
        document.getElementById('edit_seats').value = car.seats;
        document.getElementById('edit_status').value = car.status;
        document.getElementById('edit_features').value = car.features;
        document.getElementById('edit_existing_image').value = car.image_path;
        document.getElementById('edit_image_url').value = '';
        document.getElementById('delete_image_check').checked = false;
        
        // Show current image preview
        const preview = document.getElementById('current_image_preview');
        if (car.image_path) {
            preview.innerHTML = '<label class="form-label small">Current Image:</label><br><img src="../' + car.image_path + '" style="max-width: 200px; max-height: 150px; border-radius: 8px; border: 2px solid #FFD700;" alt="Current Image">';
        } else {
            preview.innerHTML = '<label class="form-label small text-muted">No image currently set</label>';
        }
        
        new bootstrap.Modal(document.getElementById('editCarModal')).show();
    }

    function deleteCar(id) {
        if (confirm('Are you sure you want to delete this car?')) {
            document.getElementById('delete_id').value = id;
            document.getElementById('deleteCarForm').submit();
        }
    }

    // Live previews
    function renderPreview(elementId, src) {
        const el = document.getElementById(elementId);
        if (!el) return;
        if (!src) {
            el.innerHTML = '<label class="form-label small text-muted">No image selected yet</label>';
            return;
        }
        el.innerHTML = '<img src="' + src + '" style="max-width: 220px; max-height: 160px; border-radius: 10px; border: 2px solid #FFD700; box-shadow: 0 8px 24px rgba(0,0,0,0.35);" alt="Preview">';
    }

    function previewAddImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => renderPreview('add_image_preview', e.target.result);
            reader.readAsDataURL(input.files[0]);
        } else {
            renderPreview('add_image_preview', '');
        }
    }

    function previewAddFromUrl(val) {
        renderPreview('add_image_preview', val);
    }

    function previewEditFromUrl(val) {
        renderPreview('current_image_preview', val);
    }

    document.getElementById('edit_image')?.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => renderPreview('current_image_preview', e.target.result);
            reader.readAsDataURL(this.files[0]);
        }
    });

    document.getElementById('edit_image_url')?.addEventListener('input', function() {
        previewEditFromUrl(this.value);
    });
    </script>
</body>

</html>
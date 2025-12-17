<?php
session_start();
require_once 'config/database.php';

// Ensure feedback table exists (for ratings/reviews)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        booking_id INT NOT NULL UNIQUE,
        rating INT NOT NULL,
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // fail silently in UI
}

// Get car categories for filter
$categories = $pdo->query("SELECT * FROM car_categories ORDER BY name")->fetchAll();

// Get available cars (include cars with pending bookings)
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$price_min = isset($_GET['price_min']) ? $_GET['price_min'] : '';
$price_max = isset($_GET['price_max']) ? $_GET['price_max'] : '';

$where_conditions = ["c.status = 'available'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(c.brand LIKE ? OR c.model LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $where_conditions[] = "c.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($price_min)) {
    $where_conditions[] = "c.price_per_day >= ?";
    $params[] = $price_min;
}

if (!empty($price_max)) {
    $where_conditions[] = "c.price_per_day <= ?";
    $params[] = $price_max;
}

$where_clause = implode(' AND ', $where_conditions);
// Get all available cars, including those with pending bookings
$sql = "SELECT 
            c.*, 
            cc.name as category_name,
            (SELECT COUNT(*) FROM bookings b 
             WHERE b.car_id = c.id 
             AND b.status = 'pending' 
             AND b.end_date >= CURDATE()) as pending_count
        FROM cars c 
        LEFT JOIN car_categories cc ON c.category_id = cc.id 
        WHERE $where_clause 
        AND c.status = 'available'
        AND c.id NOT IN (
            SELECT DISTINCT car_id FROM bookings 
            WHERE status IN ('confirmed', 'active')
            AND end_date >= CURDATE()
        )
        ORDER BY c.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cars = $stmt->fetchAll();

// Overall rating for About section
$rating_data = ['avg' => 0, 'total' => 0];
$avg_stmt = $pdo->query("SELECT ROUND(AVG(rating),1) as avg_rating, COUNT(*) as total FROM feedback");
$avg_row = $avg_stmt ? $avg_stmt->fetch() : null;
if ($avg_row) {
    $rating_data['avg'] = $avg_row['avg_rating'] ?? 0;
    $rating_data['total'] = $avg_row['total'] ?? 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ELITE MOTORS - Premium Car Rental</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?php echo time(); ?>" rel="stylesheet">
    <script src="assets/js/loader.js"></script>
</head>

<body>
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <!-- Enhanced hero -->
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <div class="hero-content" style="text-align: center; max-width: 600px; margin: 0 auto;">
                        <h1 class="hero-title" style="color: #FFD700; font-size: 3.5rem; font-weight: bold; text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Find Your Perfect Car</h1>
                        <p class="hero-subtitle" style="color:#FFFFFF; font-size:1.3rem; margin:1.5rem 0;">Discover premium vehicles with a seamless booking experience. From luxury
                            sedans to eco-friendly hybrids, we have the perfect ride for every journey.</p>
                        <div class="hero-cta">
                            <a href="#cars" class="btn btn-lg" style="font-size: 1.3rem; padding: 1rem 2.5rem; margin: 0.5rem; border-radius: 10px; background: linear-gradient(135deg, #FFD700 0%, #D4AF37 100%); border: 2px solid #FFD700; color: #000000; font-weight: bold; text-decoration: none; display: inline-block;">
                                <i class="fas fa-car me-2"></i>Browse Cars
                            </a>
                            <a href="auth/login.php" class="btn btn-lg" style="font-size: 1.3rem; padding: 1rem 2.5rem; margin: 0.5rem; border-radius: 10px; background: #000000; border: 2px solid #FFD700; color: #FFD700; font-weight: bold; text-decoration: none; display: inline-block; transition: all 0.3s ease;">
                                <i class="fas fa-user me-2"></i>Get Started
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="hero-image hero-3d-card" style="text-align: center; perspective: 1200px;">
                        <div class="hero-3d-inner hero-drive" style="display: inline-block; transform-style: preserve-3d; transition: transform 0.6s ease, box-shadow 0.6s ease; border-radius: 15px; box-shadow: 0 20px 40px rgba(0,0,0,0.6); overflow: hidden; background: radial-gradient(circle at top left, rgba(255,215,0,0.35), transparent 55%), #000000;">
                            <img src="https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?w=800&h=600&fit=crop" alt="Premium Cars" style="max-width: 100%; height: auto; display:block; opacity:0.95;">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Premium Video Experience Section -->
    <section class="py-5" style="background: #000000;">
        <div class="container">
            <div class="text-center mb-4">
                <h2 style="color:#FFD700; font-size:2.5rem; font-weight:bold;">Experience ELITE MOTORS in Motion</h2>
                <p style="color:#FFFFFF; font-size:1.1rem; max-width:700px; margin:0 auto;">Showcase your fleet with a cinematic feel. Replace this sample clip with your own branded car walk-throughs when you are ready to go live.</p>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="video-frame hover-glow shine" style="position:relative; padding-top:56.25%; box-shadow:0 24px 60px rgba(0,0,0,0.8);">
                        <video style="position:absolute; top:0; left:0; width:100%; height:100%; object-fit:cover;" autoplay muted loop playsinline poster="https://images.unsplash.com/photo-1503736334956-4c8f8e92946d?w=900&auto=format&fit=crop&q=60">
                            <source src="https://cdn.coverr.co/videos/coverr-driving-a-convertible-1570/1080p.mp4" type="video/mp4">
                            <source src="https://cdn.coverr.co/videos/coverr-driving-a-convertible-1570/720p.mp4" type="video/mp4">
                        </video>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Search and Filter Section -->
    <section class="py-5" id="cars" style="background: linear-gradient(135deg, #1a1a1a 0%, #000000 100%);">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <h2 class="text-center mb-4" style="color: #FFD700; font-size: 2.5rem; font-weight: bold;">Find Your Perfect Car</h2>
                </div>
            </div>

            <form method="GET" class="row g-3 mb-4">
                <div class="col-md-3">
                    <label for="search" class="form-label" style="color: #FFD700; font-size: 1.2rem; font-weight: 600;">🔍 Search</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="Enter brand or model name..." 
                        value="<?php echo htmlspecialchars($search); ?>" 
                        style="font-size: 1.1rem; padding: 0.875rem; background: #111111; border: 2px solid #FFD700; color: #FFFFFF; border-radius: 8px;"
                        onfocus="this.style.background='#1a1a1a'; this.style.color='#FFFFFF';"
                        onblur="this.style.background='#111111';">
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label" style="color: #FFD700; font-size: 1.2rem; font-weight: 600;">📂 Category</label>
                    <select class="form-select" id="category" name="category" 
                        style="font-size: 1.1rem; padding: 0.875rem; background: #111111; border: 2px solid #FFD700; color: #FFFFFF; border-radius: 8px;">
                        <option value="" style="background: #111111; color: #FFFFFF;">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"
                            <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>
                            style="background: #111111; color: #FFFFFF;">
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="price_min" class="form-label" style="color: #FFD700; font-size: 1.2rem; font-weight: 600;">💰 Min Price</label>
                    <input type="number" class="form-control" id="price_min" name="price_min" placeholder="From $0"
                        value="<?php echo htmlspecialchars($price_min); ?>"
                        style="font-size: 1.1rem; padding: 0.875rem; background: #111111; border: 2px solid #FFD700; color: #FFFFFF; border-radius: 8px;"
                        onfocus="this.style.background='#1a1a1a'; this.style.color='#FFFFFF';"
                        onblur="this.style.background='#111111';">
                </div>
                <div class="col-md-2">
                    <label for="price_max" class="form-label" style="color: #FFD700; font-size: 1.2rem; font-weight: 600;">💰 Max Price</label>
                    <input type="number" class="form-control" id="price_max" name="price_max" placeholder="Up to $1000"
                        value="<?php echo htmlspecialchars($price_max); ?>"
                        style="font-size: 1.1rem; padding: 0.875rem; background: #111111; border: 2px solid #FFD700; color: #FFFFFF; border-radius: 8px;"
                        onfocus="this.style.background='#1a1a1a'; this.style.color='#FFFFFF';"
                        onblur="this.style.background='#111111';">
                </div>
                <div class="col-md-2">
                    <label class="form-label" style="color: #FFD700; font-size: 1.2rem; font-weight: 600;">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100" style="font-size: 1.1rem; padding: 0.875rem; background: linear-gradient(135deg, #FFD700 0%, #D4AF37 100%); border: none; color: #000000; font-weight: bold; border-radius: 8px;">
                        <i class="fas fa-filter me-2"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Cars Grid - Available -->
    <section class="py-5">
        <div class="container">
            <div class="row">
                <?php if (empty($cars)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-search text-muted mb-3" style="font-size: 3rem;"></i>
                        <h3>No cars found</h3>
                        <p class="text-muted">Try adjusting your search criteria</p>
                    </div>
                </div>
                <?php else: ?>
                <?php foreach ($cars as $car): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card car-card h-100 hover-glow">
                        <div class="car-image">
                            <?php if ($car['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($car['image_path']); ?>" class="card-img-top"
                                alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                            <?php else: ?>
                            <img src="https://via.placeholder.com/400x240/cccccc/000000?text=No+Image"
                                class="card-img-top" alt="No Image Available"> <!-- Default placeholder -->
                            <?php endif; ?>
                            <div class="car-badge">
                                <?php if ($car['pending_count'] > 0): ?>
                                    <span class="badge bg-warning">Pending (<?php echo $car['pending_count']; ?>)</span>
                                <?php else: ?>
                                    <span class="badge bg-success">Available</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title" style="font-size: 1.5rem; font-weight: bold;"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>
                            </h5>
                            <p class="card-text"><?php echo htmlspecialchars($car['category_name']); ?></p>
                            <div class="car-details">
                                <div class="row">
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i><?php echo $car['year']; ?>
                                        </small>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-users me-1"></i><?php echo $car['seats']; ?> seats
                                        </small>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-cog me-1"></i><?php echo $car['transmission']; ?>
                                        </small>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted">
                                            <i class="fas fa-gas-pump me-1"></i><?php echo $car['fuel_type']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="price">
                                    <span
                                        class="h5" style="color:#FFD700;">$<?php echo number_format($car['price_per_day'], 2); ?></span>
                                    <small style="color:#d1c58a;">/day</small>
                                </div>
                                <?php if (isset($_SESSION['user_id'])): ?>
                                <a href="booking/book_car.php?car_id=<?php echo $car['id']; ?>"
                                    class="btn btn-primary shine" style="background: #000000; border: 2px solid #FFD700; color: #FFD700; font-weight:800;">Book Now</a>
                                <?php else: ?>
                                <a href="auth/login.php" class="btn btn-outline-primary" style="background: #000000; border: 2px solid #FFD700; color: #FFD700; font-weight: 600;">Login to Book</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Unavailable Cars -->
    <?php
    // Build list of cars that are currently not available for booking
    $unavailable_sql = "SELECT 
                            c.*, 
                            cc.name as category_name,
                            (
                                SELECT b.status 
                                FROM bookings b 
                                WHERE b.car_id = c.id 
                                AND b.status IN ('confirmed', 'active')
                                AND b.end_date >= CURDATE()
                                ORDER BY 
                                    CASE b.status
                                        WHEN 'active' THEN 0
                                        WHEN 'confirmed' THEN 1
                                        ELSE 2
                                    END
                                LIMIT 1
                            ) as booking_status,
                            c.status as car_status
                        FROM cars c
                        LEFT JOIN car_categories cc ON c.category_id = cc.id 
                        WHERE c.status != 'available' 
                           OR c.id IN (
                                SELECT DISTINCT car_id FROM bookings 
                                WHERE status IN ('confirmed', 'active')
                                AND end_date >= CURDATE()
                           )
                        ORDER BY 
                            CASE 
                                WHEN booking_status IS NOT NULL THEN 0
                                ELSE 1
                            END,
                            c.created_at DESC";
    $unavailable_cars = $pdo->query($unavailable_sql)->fetchAll();
    ?>

    <?php if (!empty($unavailable_cars)): ?>
    <section class="py-5" style="background: #000000;">
        <div class="container">
            <h2 class="text-center mb-4" style="color:#FFD700; font-size:2.4rem; font-weight:bold;">Currently Unavailable Cars</h2>
            <p class="text-center mb-5" style="color:#FFFFFF; font-size:1.05rem;">These vehicles are temporarily unavailable for booking. Check their live status (BOOKED, MAINTENANCE, UNAVAILABLE) before planning.</p>
            <div class="row">
                <?php foreach ($unavailable_cars as $car): ?>
                <?php
                    $status = strtolower($car['booking_status'] ?? $car['car_status']);
                    $status_label = strtoupper($status);
                    $badge_class = 'bg-secondary';
                    
                    switch ($status) {
                        case 'active':
                            $status_label = 'RENTED';
                            $badge_class = 'bg-danger';
                            break;
                        case 'confirmed':
                            $status_label = 'BOOKED';
                            $badge_class = 'bg-warning';
                            break;
                        case 'maintenance':
                            $status_label = 'MAINTENANCE';
                            $badge_class = 'bg-info';
                            break;
                        case 'unavailable':
                            $status_label = 'UNAVAILABLE';
                            $badge_class = 'bg-secondary';
                            break;
                        default:
                            $status_label = 'UNAVAILABLE';
                            $badge_class = 'bg-secondary';
                    }
                ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card car-card h-100" style="background:#1a1a1a; color:#FFFFFF; border-color:#333333; opacity:0.9;">
                        <div class="car-image" style="position:relative; filter:grayscale(40%);">
                            <?php if ($car['image_path']): ?>
                            <img src="<?php echo htmlspecialchars($car['image_path']); ?>" class="card-img-top"
                                alt="<?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?>">
                            <?php else: ?>
                            <img src="https://via.placeholder.com/400x240/444444/000000?text=No+Image"
                                class="card-img-top" alt="No Image Available">
                            <?php endif; ?>
                            <div class="car-badge" style="position:absolute; top:12px; left:12px;">
                                <span class="badge <?php echo $badge_class; ?>" style="font-size:0.95rem;"><?php echo $status_label; ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <h5 class="card-title" style="color:#FFD700; font-size:1.4rem; font-weight:bold;"><?php echo htmlspecialchars($car['brand'] . ' ' . $car['model']); ?></h5>
                            <p class="card-text" style="color:#d1c58a;"><?php echo htmlspecialchars($car['category_name']); ?></p>
                            <div class="car-details">
                                <div class="row text-white-50">
                                    <div class="col-6">
                                        <small>
                                            <i class="fas fa-calendar me-1"></i><?php echo $car['year']; ?>
                                        </small>
                                    </div>
                                    <div class="col-6">
                                        <small>
                                            <i class="fas fa-users me-1"></i><?php echo $car['seats']; ?> seats
                                        </small>
                                    </div>
                                    <div class="col-6">
                                        <small>
                                            <i class="fas fa-cog me-1"></i><?php echo $car['transmission']; ?>
                                        </small>
                                    </div>
                                    <div class="col-6">
                                        <small>
                                            <i class="fas fa-gas-pump me-1"></i><?php echo $car['fuel_type']; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="price">
                                    <span class="h5" style="color:#FFD700;">$<?php echo number_format($car['price_per_day'], 2); ?></span>
                                    <small style="color:#d1c58a;">/day</small>
                                </div>
                                <button class="btn" disabled style="background-color: #dc3545; border: 1px solid #dc3545; color: white; font-weight: 600; padding: 0.5rem 1rem;">
                                    Not Available
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- About Section -->
    <section id="about" class="py-5" style="background: var(--background-color);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 mb-4 mb-lg-0">
                    <h2 style="font-size:2.5rem; font-weight:800; color:#000000; margin-bottom:1rem;">About ELITE MOTORS</h2>
                    <div style="margin-bottom:0.75rem;" class="star-rating">
                        <?php 
                            $filled = (int)floor($rating_data['avg']);
                            $half = ($rating_data['avg'] - $filled) >= 0.5;
                            for ($i=0; $i<5; $i++):
                                if ($i < $filled) {
                                    echo '<i class="fas fa-star"></i>';
                                } elseif ($half && $i == $filled) {
                                    echo '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    echo '<i class="far fa-star" style="color:#555;"></i>';
                                }
                            endfor;
                        ?>
                        <span style="color:#5C4B1B; font-weight:700; margin-left:8px;"><?php echo number_format($rating_data['avg'],1); ?> / 5</span>
                        <span style="color:#5C4B1B;">(<?php echo $rating_data['total']; ?> reviews)</span>
                    </div>
                    <p style="font-size:1.1rem; color:#5C4B1B; margin-bottom:1rem;">
                        ELITE MOTORS is designed as a premium-ready car rental platform, showcasing your fleet with a
                        showroom-grade interface and precise availability logic.
                    </p>
                    <p style="font-size:1.05rem; color:#5C4B1B;">
                        Built for real-world use, every screen in this system is crafted to look like a product that is
                        ready to sell to clients, investors, or end customers.
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="row g-3">
                        <div class="col-6">
                            <div style="background:#000000; border-radius:14px; padding:1.5rem; height:100%; box-shadow:var(--shadow);">
                                <h5 style="color:#FFD700; font-weight:700;">Premium Fleet</h5>
                                <p style="color:#FFFFFF; font-size:0.95rem; margin-bottom:0;">Highlight luxury, SUV, and business-class vehicles with rich visuals.</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div style="background:#000000; border-radius:14px; padding:1.5rem; height:100%; box-shadow:var(--shadow);">
                                <h5 style="color:#FFD700; font-weight:700;">Smart Status</h5>
                                <p style="color:#FFFFFF; font-size:0.95rem; margin-bottom:0;">Instantly see which cars are BOOKED, in MAINTENANCE, or UNAVAILABLE.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <h5 class="mb-3"><i class="fas fa-car me-2"></i>ELITE MOTORS</h5>
                    <p class="mb-0" style="color:#d1c58a !important;">&copy; 2023 ELITE MOTORS. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
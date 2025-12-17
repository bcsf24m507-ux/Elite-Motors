<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #000000; border-bottom: 3px solid #FFD700; padding: 0.5rem 0;">
    <div class="container">
        <a class="navbar-brand" href="../index.php" style="color: #FFD700 !important; font-size: 1.8rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase;">
            ELITE MOTORS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" 
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link px-3" href="../index.php" style="color: #FFFFFF; font-weight: 500; text-transform: uppercase; font-size: 0.9rem;">
                        Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" href="../index.php#vehicles" style="color: #FFFFFF; font-weight: 500; text-transform: uppercase; font-size: 0.9rem;">
                        Vehicles
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" href="../index.php#about" style="color: #FFFFFF; font-weight: 500; text-transform: uppercase; font-size: 0.9rem;">
                        About
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-3" href="../index.php#contact" style="color: #FFFFFF; font-weight: 500; text-transform: uppercase; font-size: 0.9rem;">
                        Contact
                    </a>
                </li>
            </ul>
            
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center px-3" href="#" id="userDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false" style="color: #FFFFFF !important; font-weight: 500; text-transform: uppercase; font-size: 0.9rem;">
                            <?php
                            $profilePic = !empty($_SESSION['profile_picture']) ? 
                                (file_exists($_SERVER['DOCUMENT_ROOT'] . '/car_rental1/' . ltrim($_SESSION['profile_picture'], '/')) 
                                    ? '/' . 'car_rental1/' . ltrim($_SESSION['profile_picture'], '/') 
                                    : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['full_name'] ?? 'User') . '&background=FFD700&color=000&size=32') 
                                : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['full_name'] ?? 'User') . '&background=FFD700&color=000&size=32';
                            ?>
                            <img src="<?php echo $profilePic; ?>" 
                                 class="rounded-circle me-2" 
                                 alt="Profile"
                                 style="width: 36px; height: 36px; object-fit: cover; background: #FFD700; color: #000; font-weight: bold; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; border: 2px solid #FFD700;">
                            <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'My Account'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end p-2" aria-labelledby="userDropdown" style="min-width: 220px;">
                            <li class="dropdown-header px-3 py-2">
                                <div class="d-flex align-items-center">
                                    <?php
                                    $dropdownProfilePic = !empty($_SESSION['profile_picture']) ? 
                                        (file_exists($_SERVER['DOCUMENT_ROOT'] . '/car_rental1/' . ltrim($_SESSION['profile_picture'], '/')) 
                                            ? '/' . 'car_rental1/' . ltrim($_SESSION['profile_picture'], '/') 
                                            : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['full_name'] ?? 'User') . '&background=FFD700&color=000&size=64') 
                                        : 'https://ui-avatars.com/api/?name=' . urlencode($_SESSION['full_name'] ?? 'User') . '&background=FFD700&color=000&size=64';
                                    ?>
                                    <img src="<?php echo $dropdownProfilePic; ?>" 
                                         class="rounded-circle me-2" 
                                         alt="Profile"
                                         style="width: 40px; height: 40px; object-fit: cover; background: #FFD700; color: #000; font-weight: bold; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; border: 2px solid #FFD700;">
                                    <div>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'My Account'); ?></div>
                                        <small class="text-muted"><?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?></small>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider my-2"></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="/car_rental1/customer/profile.php">
                                    <i class="fas fa-user me-2" style="width: 20px; text-align: center;"></i>
                                    <span>My Profile</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="/car_rental1/customer/bookings.php">
                                    <i class="fas fa-calendar-alt me-2" style="width: 20px; text-align: center;"></i>
                                    <span>My Bookings</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-2"></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center" href="/car_rental1/auth/logout.php">
                                    <i class="fas fa-sign-out-alt me-2" style="width: 20px; text-align: center;"></i>
                                    <span>Logout</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link px-3" href="/car_rental1/auth/login.php" style="color: #FFFFFF; font-weight: 500; text-transform: uppercase; font-size: 0.9rem;">
                            Login
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn ms-2" href="/car_rental1/auth/register.php" style="background-color: #FFD700; color: #000000; font-weight: 600; text-transform: uppercase; font-size: 0.9rem; padding: 0.4rem 1.2rem; border-radius: 4px;">
                            Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

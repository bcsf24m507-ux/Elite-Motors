-- Car Rental Management System Database Schema
CREATE DATABASE IF NOT EXISTS car_rental_system;
USE car_rental_system;

-- Users table for customers
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    license_number VARCHAR(50),
    date_of_birth DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admins table for system administrators
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('super_admin', 'admin', 'employee') DEFAULT 'employee',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Car categories table
CREATE TABLE IF NOT EXISTS car_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Cars table
CREATE TABLE IF NOT EXISTS cars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand VARCHAR(50) NOT NULL,
    model VARCHAR(50) NOT NULL,
    year INT NOT NULL,
    color VARCHAR(30),
    category_id INT,
    price_per_day DECIMAL(10,2) NOT NULL,
    mileage INT DEFAULT 0,
    fuel_type ENUM('Petrol', 'Diesel', 'Electric', 'Hybrid') DEFAULT 'Petrol',
    transmission ENUM('Manual', 'Automatic') DEFAULT 'Manual',
    seats INT DEFAULT 5,
    image_path VARCHAR(255),
    status ENUM('available', 'rented', 'maintenance', 'unavailable') DEFAULT 'available',
    features TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES car_categories(id) ON DELETE SET NULL
);

-- Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    car_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pickup_location VARCHAR(100),
    return_location VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE CASCADE
);

-- Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('cash', 'credit_card', 'debit_card', 'bank_transfer', 'online') DEFAULT 'cash',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
    transaction_id VARCHAR(100),
    payment_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
);

-- Insert default car categories
INSERT INTO car_categories (name, description) VALUES 
('Economy', 'Budget-friendly cars for everyday use'),
('Compact', 'Small and efficient cars for city driving'),
('Sedan', 'Comfortable four-door cars'),
('SUV', 'Sport Utility Vehicles for family trips'),
('Luxury', 'Premium cars with advanced features'),
('Sports', 'High-performance sports cars');

-- Insert sample admin
INSERT INTO admins (username, email, password, full_name, role) VALUES 
('admin', 'admin@carrental.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'super_admin');

-- Insert sample cars
INSERT INTO cars (brand, model, year, color, category_id, price_per_day, fuel_type, transmission, seats, status, features) VALUES 
('Toyota', 'Camry', 2023, 'White', 3, 45.00, 'Petrol', 'Automatic', 5, 'available', 'Air Conditioning, Bluetooth, GPS'),
('Honda', 'Civic', 2023, 'Silver', 2, 35.00, 'Petrol', 'Manual', 5, 'available', 'Air Conditioning, Bluetooth'),
('BMW', 'X5', 2023, 'Black', 4, 85.00, 'Petrol', 'Automatic', 7, 'available', 'Leather Seats, Sunroof, GPS, Premium Sound'),
('Mercedes', 'C-Class', 2023, 'Blue', 5, 95.00, 'Petrol', 'Automatic', 5, 'available', 'Leather Seats, Premium Sound, Navigation'),
('Ford', 'Mustang', 2023, 'Red', 6, 120.00, 'Petrol', 'Manual', 4, 'available', 'Sports Package, Premium Sound'),
('Nissan', 'Altima', 2023, 'Gray', 3, 40.00, 'Petrol', 'Automatic', 5, 'available', 'Air Conditioning, Bluetooth'),
('Hyundai', 'Elantra', 2023, 'White', 1, 30.00, 'Petrol', 'Manual', 5, 'available', 'Air Conditioning, Bluetooth'),
('Audi', 'A4', 2023, 'Black', 5, 90.00, 'Petrol', 'Automatic', 5, 'available', 'Leather Seats, Premium Sound, Navigation');

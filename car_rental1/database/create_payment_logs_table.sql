-- Create payment_logs table to track all payment attempts
CREATE TABLE IF NOT EXISTS payment_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'success', 'failed', 'refunded', 'partially_refunded') NOT NULL,
    transaction_id VARCHAR(100) DEFAULT NULL,
    payment_method VARCHAR(50) DEFAULT NULL,
    gateway_response TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for better query performance
CREATE INDEX idx_payment_logs_booking_id ON payment_logs(booking_id);
CREATE INDEX idx_payment_logs_status ON payment_logs(status);
CREATE INDEX idx_payment_logs_created_at ON payment_logs(created_at);

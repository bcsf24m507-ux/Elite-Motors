<?php
// File: customer/cancel_booking.php
session_start();
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get input data
$bookingId = $_POST['booking_id'] ?? null;
$reason = $_POST['reason'] ?? '';
$userId = $_SESSION['user_id'];

// Validate input
if (!$bookingId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing booking ID']);
    exit();
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get the booking to verify ownership and status
    $stmt = $pdo->prepare("
        SELECT b.*, c.status as car_status 
        FROM bookings b
        JOIN cars c ON b.car_id = c.id
        WHERE b.id = ? AND b.user_id = ?
        FOR UPDATE
    ");
    $stmt->execute([$bookingId, $userId]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        throw new Exception('Booking not found or you do not have permission to cancel it');
    }
    
    // Check if booking can be cancelled (only pending or confirmed)
    if (!in_array($booking['status'], ['pending', 'confirmed'])) {
        throw new Exception('This booking cannot be cancelled at this stage');
    }
    
    // Update booking status to cancelled
    $stmt = $pdo->prepare("
        UPDATE bookings 
        SET status = 'cancelled', 
            updated_at = NOW() 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$bookingId, $userId]);
    
    // Log the cancellation
    $stmt = $pdo->prepare("
        INSERT INTO status_change_logs 
        (booking_id, user_id, entity_type, old_status, new_status, notes, ip_address)
        VALUES (?, ?, 'booking', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $bookingId, 
        $userId, 
        $booking['status'], 
        'cancelled', 
        "User cancelled booking. Reason: " . ($reason ?: 'No reason provided'),
        $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
    ]);
    
    // Update car status if no other active bookings
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM bookings 
        WHERE car_id = ? 
        AND id != ? 
        AND status IN ('confirmed', 'active')
        AND end_date >= CURDATE()
    ");
    $stmt->execute([$booking['car_id'], $bookingId]);
    $hasActiveBookings = $stmt->fetchColumn() > 0;
    
    if (!$hasActiveBookings) {
        $stmt = $pdo->prepare("
            UPDATE cars 
            SET status = 'available', 
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$booking['car_id']]);
    }
    
    // If payment was made, initiate refund process
    if ($booking['payment_status'] === 'paid') {
        // In a real application, you would integrate with a payment gateway here
        // For now, we'll just log it
        error_log("Refund initiated for booking #{$bookingId}");
    }
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    error_log("Error cancelling booking: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
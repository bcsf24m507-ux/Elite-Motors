<?php
// File: admin/update_booking_status.php
require_once '../config/database.php';
require_once '../includes/BookingManager.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if admin is logged in
session_start();
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get input data
$bookingId = $_POST['booking_id'] ?? null;
$newStatus = $_POST['status'] ?? '';
$notes = $_POST['notes'] ?? '';
$adminId = $_SESSION['admin_id'];

// Validate input
if (!$bookingId || !$newStatus) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

try {
    $bookingManager = new BookingManager($pdo);
    $result = $bookingManager->updateBookingStatus($bookingId, $newStatus, $adminId, $notes);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception('Failed to update booking status');
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}
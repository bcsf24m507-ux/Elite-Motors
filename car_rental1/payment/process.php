<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit();
}

// Check if booking_id is provided
if (!isset($_POST['booking_id'])) {
    $_SESSION['error'] = 'Invalid booking reference.';
    header('Location: ../customer/bookings.php');
    exit();
}

$booking_id = (int)$_POST['booking_id'];
$error = '';
$success = '';

// Start transaction
$pdo->beginTransaction();

try {
    // 1. Lock the booking and payment records
    $stmt = $pdo->prepare("SELECT b.*, p.id as payment_id, p.amount as payment_amount, p.payment_status 
                          FROM bookings b 
                          LEFT JOIN payments p ON b.id = p.booking_id 
                          WHERE b.id = ? AND b.user_id = ? FOR UPDATE");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();

    if (!$booking) {
        throw new Exception('Booking not found or access denied.');
    }

    // 2. Check if booking is already paid for
    if ($booking['payment_status'] === 'paid') {
        throw new Exception('This booking has already been paid for.');
    }

    // 3. Process payment (simulated)
    $payment_successful = simulatePaymentProcessing($booking['total_amount']);

    if (!$payment_successful) {
        // Log failed payment attempt
        logPaymentAttempt($booking_id, $booking['total_amount'], 'failed');
        throw new Exception('Payment processing failed. Please try again or use a different payment method.');
    }

    // 4. Update payment status
    $update_payment = $pdo->prepare("UPDATE payments SET payment_status = 'paid', 
                                   payment_date = NOW(), transaction_id = ? 
                                   WHERE booking_id = ?");
    $transaction_id = 'TXN' . time() . rand(1000, 9999);
    $update_payment->execute([$transaction_id, $booking_id]);

    // 5. Update booking status
    $update_booking = $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?");
    $update_booking->execute([$booking_id]);

    // 6. Log successful payment
    logPaymentAttempt($booking_id, $booking['total_amount'], 'success', $transaction_id);

    // Commit transaction
    $pdo->commit();

    // Send confirmation email
    sendConfirmationEmail($_SESSION['email'], $booking_id, $transaction_id);

    // Set success message
    $_SESSION['success'] = 'Payment processed successfully! Your booking is now confirmed.';
    header('Location: ../customer/booking_confirmation.php?id=' . $booking_id);
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = $e->getMessage();
    header('Location: ../customer/booking_payment.php?booking_id=' . $booking_id);
    exit();
}

/**
 * Simulates payment processing with a 90% success rate
 */
function simulatePaymentProcessing($amount) {
    // Simulate network delay
    usleep(rand(500000, 2000000)); // 0.5-2 seconds delay
    
    // Simulate 90% success rate
    return (rand(1, 10) <= 9);
}

/**
 * Logs payment attempts
 */
function logPaymentAttempt($booking_id, $amount, $status, $transaction_id = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("INSERT INTO payment_logs 
                          (booking_id, amount, status, transaction_id, created_at) 
                          VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$booking_id, $amount, $status, $transaction_id]);
    
    return $pdo->lastInsertId();
}

/**
 * Sends booking confirmation email
 */
function sendConfirmationEmail($to_email, $booking_id, $transaction_id) {
    // In a real application, you would implement email sending here
    // This is a placeholder that would be replaced with actual email code
    $subject = "Booking Confirmation #$booking_id";
    $message = "Thank you for your payment. Your booking #$booking_id has been confirmed.\n";
    $message .= "Transaction ID: $transaction_id\n";
    $message .= "You can view your booking details in your account.\n\n";
    $message .= "Best regards,\nElite Motors Team";
    
    // Uncomment in production:
    // mail($to_email, $subject, $message);
    
    // For now, just log it
    error_log("Email to $to_email: $subject");
}
?>

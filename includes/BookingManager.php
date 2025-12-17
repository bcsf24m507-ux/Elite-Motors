<?php
class BookingManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function updateBookingStatus($bookingId, $newStatus, $adminId = null, $reason = '') {
        try {
            $this->db->beginTransaction();
            
            // Get current booking details
            $stmt = $this->db->prepare("
                SELECT b.*, u.id as user_id, c.id as car_id, c.status as car_status
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN cars c ON b.car_id = c.id
                WHERE b.id = ? FOR UPDATE
            
            ");
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                throw new Exception("Booking not found");
            }
            
            $currentStatus = $booking['status'];
            
            // Validate status transition
            if (!$this->isValidStatusTransition($currentStatus, $newStatus)) {
                throw new Exception("Invalid status transition from {$currentStatus} to {$newStatus}");
            }
            
            // Update booking status
            $stmt = $this->db->prepare("
                UPDATE bookings 
                SET status = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            
            ");
            $stmt->execute([$newStatus, $bookingId]);
            
            // Log the status change
            $this->logStatusChange(
                $bookingId, 
                $booking['user_id'],
                $adminId,
                'booking',
                $currentStatus,
                $newStatus,
                $reason,
                $this->getClientIP()
            );
            
            // Handle car status updates based on booking status
            $this->updateCarStatusBasedOnBooking($booking['car_id'], $newStatus, $bookingId);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Booking status update failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    private function updateCarStatusBasedOnBooking($carId, $bookingStatus, $bookingId) {
        $carStatus = null;
        
        switch ($bookingStatus) {
            case 'confirmed':
                // Only mark as unavailable when booking is confirmed
                $carStatus = 'unavailable';
                break;
            case 'active':
                $carStatus = 'rented';
                break;
            case 'completed':
            case 'cancelled':
                // Only set to available if no other confirmed or active bookings for this car
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as active_bookings
                    FROM bookings 
                    WHERE car_id = ? 
                    AND id != ?
                    AND status IN ('confirmed', 'active')
                ");
                $stmt->execute([$carId, $bookingId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['active_bookings'] == 0) {
                    $carStatus = 'available';
                }
                break;
            case 'pending':
                // Do not change car status for pending bookings
                // The car should remain available for other bookings
                break;
        }
        
        if ($carStatus) {
            $this->updateCarStatus($carId, $carStatus);
        }
    }
    
    private function updateCarStatus($carId, $newStatus) {
        $stmt = $this->db->prepare("
            UPDATE cars 
            SET status = ?, 
                updated_at = NOW() 
            WHERE id = ?
        
        
        ");
        return $stmt->execute([$newStatus, $carId]);
    }
    
    private function logStatusChange($bookingId, $userId, $adminId, $entityType, $oldStatus, $newStatus, $reason, $ipAddress) {
        $stmt = $this->db->prepare("
            INSERT INTO status_change_logs 
            (booking_id, user_id, admin_id, entity_type, old_status, new_status, reason, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        
        
        ");
        return $stmt->execute([
            $bookingId,
            $userId,
            $adminId,
            $entityType,
            $oldStatus,
            $newStatus,
            $reason,
            $ipAddress
        ]);
    }
    
    private function isValidStatusTransition($currentStatus, $newStatus) {
        $validTransitions = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['active', 'cancelled'],
            'active' => ['completed'],
            'completed' => [],
            'cancelled' => []
        ];
        
        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }
    
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
    
    public function getDashboardStats() {
        $stats = [];
        
        // Active bookings count
        $stmt = $this->db->query("SELECT COUNT(*) FROM bookings WHERE status = 'active'");
        $stats['active_bookings'] = $stmt->fetchColumn();
        
        // Available cars count
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM cars 
            WHERE status = 'available' 
            AND id NOT IN (
                SELECT car_id FROM bookings 
                WHERE status IN ('confirmed', 'active')
                AND end_date >= CURDATE()
            )
        
        
        ");
        $stats['available_cars'] = $stmt->fetchColumn();
        
        // Total revenue (only completed bookings)
        $stmt = $this->db->query("
            SELECT COALESCE(SUM(total_amount), 0) 
            FROM bookings 
            WHERE status = 'completed'
        
        
        ");
        $stats['total_revenue'] = $stmt->fetchColumn();
        
        // Total bookings count
        $stmt = $this->db->query("SELECT COUNT(*) FROM bookings");
        $stats['total_bookings'] = $stmt->fetchColumn();
        
        return $stats;
    }
}

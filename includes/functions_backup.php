<?php
// Common functions for the application

/**
 * Sanitize input data with optional database escaping
 */
function sanitize_input($data, $conn = null) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    
    // Use provided connection or global connection for SQL escaping
    if ($conn !== null && is_object($conn)) {
        $data = mysqli_real_escape_string($conn, $data);
    }
    
    return $data;
}

/**
 * Get hospital inventory summary
 */
function get_hospital_inventory_detailed($hospital_id, $conn) {
    $sql = "SELECT bi.blood_type,
                   SUM(bi.quantity_ml) as total_quantity,
                   COUNT(*) as unit_count
            FROM blood_inventory bi
            WHERE bi.blood_bank_id IN (
                SELECT bb.id FROM blood_banks bb
                WHERE bb.user_id = $hospital_id
            )
            AND bi.expiry_date > CURDATE()
            GROUP BY bi.blood_type
            ORDER BY bi.blood_type";
    $result = $conn->query($sql);
    $inventory = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $inventory[] = $row;
        }
    }
    return $inventory;
}

/**
 * Get inventory count by blood type
 */
function get_inventory_count($blood_bank_id, $blood_type, $conn) {
    $sql = "SELECT SUM(quantity_ml) as total 
            FROM blood_inventory 
            WHERE blood_bank_id = ? AND blood_type = ? AND expiry_date > CURDATE()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $blood_bank_id, $blood_type);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['total'] : 0;
}

/**
 * Get total donations count
 */
function get_donations_count($blood_bank_id, $conn) {
    $sql = "SELECT COUNT(*) as count 
            FROM blood_donations 
            WHERE blood_bank_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $blood_bank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;
}

/**
 * Get recent donations
 */
function get_recent_donations($blood_bank_id, $conn, $limit = 5) {
    $sql = "SELECT bd.*, d.first_name, d.last_name, d.blood_type as donor_blood_type
            FROM blood_donations bd
            JOIN donors d ON bd.donor_id = d.id
            WHERE bd.blood_bank_id = ?
            ORDER BY bd.donation_date DESC, bd.created_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $blood_bank_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $donations = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $donations[] = $row;
        }
    }
    return $donations;
}

/**
 * Format date for display
 */
function format_date($date, $format = 'M j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format time for display
 */
function format_datetime($datetime, $format = 'M j, Y g:i A') {
    return date($format, strtotime($datetime));
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Check user role
 */
function has_role($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Generate random password
 */
function generate_password($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

/**
 * Validate blood type
 */
function is_valid_blood_type($blood_type) {
    $valid_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
    return in_array($blood_type, $valid_types);
}

/**
 * Get blood compatibility
 */
function get_compatible_blood_types($blood_type) {
    $compatibility = [
        'A+' => ['A+', 'A-', 'O+', 'O-'],
        'A-' => ['A-', 'O-'],
        'B+' => ['B+', 'B-', 'O+', 'O-'],
        'B-' => ['B-', 'O-'],
        'AB+' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'AB-' => ['A-', 'B-', 'AB-', 'O-'],
        'O+' => ['O+', 'O-'],
        'O-' => ['O-']
    ];
    
    return $compatibility[$blood_type] ?? [];
}

/**
 * Get blood bank/hospital statistics
 */
function get_blood_bank_stats($blood_bank_id, $conn) {
    $stats = [
        'total_inventory' => 0,
        'total_donations' => 0,
        'recent_donations' => 0,
        'blood_types' => []
    ];
    
    // Get total inventory
    $sql = "SELECT SUM(units_available) as total FROM blood_inventory WHERE blood_bank_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $blood_bank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_inventory'] = $row['total'] ?: 0;
    }
    
    // Get total donations
    $sql = "SELECT COUNT(*) as total FROM blood_donations WHERE blood_bank_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $blood_bank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_donations'] = $row['total'] ?: 0;
    }
    
    // Get recent donations (last 30 days)
    $sql = "SELECT COUNT(*) as total FROM blood_donations WHERE blood_bank_id = ? AND donation_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $blood_bank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stats['recent_donations'] = $row['total'] ?: 0;
    }
    
    // Get blood type breakdown
    $sql = "SELECT blood_type, SUM(units_available) as units FROM blood_inventory WHERE blood_bank_id = ? GROUP BY blood_type ORDER BY blood_type";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $blood_bank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['blood_types'][$row['blood_type']] = $row['units'];
        }
    }
    
    return $stats;
}

/**
 * Get hospital statistics for blood requests
 */
function get_hospital_stats($hospital_id, $conn) {
    $stats = [
        'total_requests' => 0,
        'pending_requests' => 0,
        'approved_requests' => 0,
        'completed_requests' => 0,
        'recent_requests' => 0
    ];
    
    // Total requests
    $sql = "SELECT COUNT(*) as count FROM blood_requests WHERE hospital_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stats['total_requests'] = $row['count'] ?: 0;
    }
    
    // Pending requests
    $sql = "SELECT COUNT(*) as count FROM blood_requests WHERE hospital_id = ? AND status = 'pending'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stats['pending_requests'] = $row['count'] ?: 0;
    }
    
    // Approved requests
    $sql = "SELECT COUNT(*) as count FROM blood_requests WHERE hospital_id = ? AND status = 'approved'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stats['approved_requests'] = $row['count'] ?: 0;
    }
    
    // Completed requests
    $sql = "SELECT COUNT(*) as count FROM blood_requests WHERE hospital_id = ? AND status = 'completed'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stats['completed_requests'] = $row['count'] ?: 0;
    }
    
    // Recent requests (last 30 days)
    $sql = "SELECT COUNT(*) as count FROM blood_requests WHERE hospital_id = ? AND request_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $hospital_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $stats['recent_requests'] = $row['count'] ?: 0;
    }
    
    return $stats;
}
?>

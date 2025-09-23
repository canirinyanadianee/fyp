<?php
/**
 * Common utility functions for the Blood Management System
 */

/**
 * Sanitize user input to prevent XSS and SQL injection
 */
function sanitize_input($data, $conn = null) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    
    // If database connection is provided, also escape for SQL
    if ($conn !== null) {
        $data = $conn->real_escape_string($data);
    }
    
    return $data;
}

/**
 * Get detailed hospital inventory (for compatibility)
 */
function get_hospital_inventory_detailed($hospital_id, $conn) {
    $inventory = [];
    
    // Get inventory from hospital_blood_inventory table if it exists
    $sql = "SELECT blood_type, quantity_ml, expiry_date 
            FROM hospital_blood_inventory 
            WHERE hospital_id = ? 
            ORDER BY blood_type";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $hospital_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $inventory[] = $row;
        }
    }
    
    return $inventory;
}

/**
 * Get inventory count for a specific blood type at a blood bank
 */
function get_inventory_count($blood_bank_id, $blood_type, $conn) {
    $sql = "SELECT SUM(quantity_ml) as total FROM blood_inventory WHERE blood_bank_id = ? AND blood_type = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $blood_bank_id, $blood_type);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        return $row['total'] ?: 0;
    }
    
    return 0;
}

/**
 * Get total donations count for a blood bank
 */
function get_donations_count($blood_bank_id, $conn) {
    $sql = "SELECT COUNT(*) as total FROM blood_donations WHERE blood_bank_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $blood_bank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        return $row['total'] ?: 0;
    }
    
    return 0;
}

/**
 * Get recent donations for a blood bank
 */
function get_recent_donations($blood_bank_id, $conn, $limit = 5) {
    $donations = [];
    
    $sql = "SELECT d.*, don.first_name, don.last_name 
            FROM blood_donations d 
            LEFT JOIN donors don ON d.donor_id = don.id 
            WHERE d.blood_bank_id = ? 
            ORDER BY d.donation_date DESC 
            LIMIT ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $blood_bank_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $donations[] = $row;
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
 * Format datetime for display
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
 * Check if user has specific role
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
 * Get compatible blood types for transfusion
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
 * Get blood bank statistics
 */
function get_blood_bank_stats($blood_bank_id, $conn) {
    $stats = [
        'total_inventory' => 0,
        'total_donations' => 0,
        'recent_donations' => 0,
        'blood_types' => []
    ];
    
    // Get total inventory
    $sql = "SELECT SUM(quantity_ml) as total FROM blood_inventory WHERE blood_bank_id = ?";
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
    $sql = "SELECT blood_type, SUM(quantity_ml) as total FROM blood_inventory WHERE blood_bank_id = ? GROUP BY blood_type";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $blood_bank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $stats['blood_types'][$row['blood_type']] = $row['total'];
    }
    
    return $stats;
}

/**
 * Calculate next eligible donation date for a donor.
 * Rules: default male 90 days, female 120 days; falls back to config constant if present.
 * Returns date string (Y-m-d) or null if no last_donation_date available.
 */
function get_next_eligible_date_for_donor($donor) {
    if (empty($donor) || empty($donor['last_donation_date'])) return null;

    $last = $donor['last_donation_date'];
    $gender = strtolower($donor['gender'] ?? 'male');

    // Default intervals
    $male_days = 90; // ~3 months
    $female_days = 120; // ~4 months

    // Allow global override
    if (defined('DONATION_INTERVAL_DAYS') && DONATION_INTERVAL_DAYS) {
        $default_days = (int)DONATION_INTERVAL_DAYS;
    } else {
        $default_days = ($gender === 'female') ? $female_days : $male_days;
    }

    $interval_days = ($gender === 'female') ? $female_days : $male_days;
    // If DONATION_INTERVAL_DAYS is defined use it as minimum baseline
    if (defined('DONATION_INTERVAL_DAYS') && DONATION_INTERVAL_DAYS) {
        $interval_days = max($interval_days, (int)DONATION_INTERVAL_DAYS);
    }

    $ts = strtotime($last);
    if ($ts === false) return null;

    $next_ts = strtotime("+{$interval_days} days", $ts);
    return date('Y-m-d', $next_ts);
}

/**
 * Days until next eligible donation (negative if already eligible)
 */
function days_until_next_eligible($donor) {
    $next = get_next_eligible_date_for_donor($donor);
    if (!$next) return null;
    $diff = strtotime($next) - strtotime(date('Y-m-d'));
    return (int)floor($diff / 86400);
}

/**
 * Schedule an in-app reminder by inserting into ai_notifications
 */
function schedule_donor_reminder($donor_id, $message, $blood_type, $conn, $priority = 'medium') {
    $donor_id = (int)$donor_id;
    $message_safe = $conn->real_escape_string($message);
    $blood_type_safe = $conn->real_escape_string($blood_type ?? '');
    $sql = "INSERT INTO ai_notifications (entity_type, entity_id, message, blood_type, priority) VALUES ('donor', $donor_id, '$message_safe', '$blood_type_safe', '$priority')";
    return $conn->query($sql);
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

/**
 * Convert a timestamp to a human-readable time ago format
 * 
 * @param string $datetime The datetime string (format: Y-m-d H:i:s)
 * @return string Formatted time ago string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $time_difference = time() - $time;

    if($time_difference < 1) { return 'just now'; }
    $condition = [
        12 * 30 * 24 * 60 * 60  =>  'year',
        30 * 24 * 60 * 60       =>  'month',
        24 * 60 * 60            =>  'day',
        60 * 60                 =>  'hour',
        60                      =>  'minute',
        1                       =>  'second'
    ];

    foreach($condition as $secs => $str) {
        $d = $time_difference / $secs;
        if($d >= 1) {
            $t = round($d);
            return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
        }
    }
}

?>

<?php
// Hospital Dashboard
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and has hospital role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../login.php');
    exit;
}

// Get hospital details from hospitals table
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM hospitals WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $hospital = $result->fetch_assoc();
    $hospital_id = $hospital['id'];
} else {
    // Create a basic hospital record if not found
    $sql = "SELECT * FROM users WHERE id = ? AND role = 'hospital'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $insert_sql = "INSERT INTO hospitals (user_id, name, email, license_number) VALUES (?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $hospital_name = "Diane Diane Hospital"; // Set a proper hospital name
        $license_number = 'H' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
        $insert_stmt->bind_param("isss", $user_id, $hospital_name, $user_data['email'], $license_number);
        if ($insert_stmt->execute()) {
            $hospital_id = $conn->insert_id;
            $hospital = [
                'id' => $hospital_id,
                'user_id' => $user_id,
                'name' => $hospital_name,
                'email' => $user_data['email'],
                'license_number' => $license_number
            ];
        } else {
            header('Location: ../login.php');
            exit;
        }
    } else {
        header('Location: ../login.php');
        exit;
    }
}

// Get blood inventory data for display (using sample data structure from screenshot)
$blood_types = [
    'A+' => ['available' => 0, 'status' => 'Critical'],
    'A-' => ['available' => 0, 'status' => 'Critical'], 
    'B+' => ['available' => 0, 'status' => 'Critical'],
    'B-' => ['available' => 0, 'status' => 'Critical'],
    'AB+' => ['available' => 0, 'status' => 'Critical'],
    'AB-' => ['available' => 0, 'status' => 'Critical'],
    'O+' => ['available' => 0, 'status' => 'Critical'],
    'O-' => ['available' => 0, 'status' => 'Critical']
];

// Try to get real inventory data if available
$inventory_sql = "SELECT blood_type, SUM(quantity_ml) as total_ml 
                  FROM blood_inventory 
                  WHERE expiry_date > CURDATE() AND status = 'available'
                  GROUP BY blood_type";
$inventory_result = $conn->query($inventory_sql);
if ($inventory_result && $inventory_result->num_rows > 0) {
    while ($row = $inventory_result->fetch_assoc()) {
        if (isset($blood_types[$row['blood_type']])) {
            $blood_types[$row['blood_type']]['available'] = $row['total_ml'];
            if ($row['total_ml'] > 1000) {
                $blood_types[$row['blood_type']]['status'] = 'Available';
            } else if ($row['total_ml'] > 500) {
                $blood_types[$row['blood_type']]['status'] = 'Low';
            } else {
                $blood_types[$row['blood_type']]['status'] = 'Critical';
            }
        }
    }
}

$page_title = "Hospital Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - BloodConnect</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-green: #28a745;
            --light-green: #e8f5e8;
            --dark-green: #1e7e34;
            --sidebar-bg: #2c5aa0;
            --card-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(180deg, var(--sidebar-bg) 0%, #1a4480 100%);
            min-height: 100vh;
            color: white;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 4px 8px;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255,255,255,0.2);
            color: white;
        }
        
        .top-header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--dark-green) 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .blood-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: var(--card-shadow);
            border: 1px solid #e9ecef;
            transition: transform 0.2s ease;
        }
        
        .blood-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .blood-type {
            font-size: 2.5rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }
        
        .blood-volume {
            font-size: 1.8rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
        }
        
        .blood-status {
            text-align: center;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .status-critical { color: #dc3545; }
        .status-low { color: #ffc107; }
        .status-available { color: #28a745; }
        
        .request-btn {
            width: 100%;
            background: #dc3545;
            border: none;
            color: white;
            padding: 8px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .request-btn:hover {
            background: #c82333;
        }
        
        .profile-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
        }
        
        .notifications-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            margin-top: 20px;
        }
        
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .slider {
            background-color: var(--primary-green);
        }
        
        input:checked + .slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 sidebar">
                <div class="p-3">
                    <h5 class="text-center mb-4">
                        <i class="fas fa-hospital-alt"></i> BloodConnect | Hospital
                    </h5>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="toggleView('inventory')">
                                <i class="fas fa-tint me-2"></i> Blood Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="toggleView('requests')">
                                <i class="fas fa-plus-circle me-2"></i> Request Blood
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="toggleView('requests')">
                                <i class="fas fa-list-alt me-2"></i> Blood Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="toggleView('usage')">
                                <i class="fas fa-chart-line me-2"></i> Record Usage
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="toggleView('reports')">
                                <i class="fas fa-file-alt me-2"></i> Usage Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="toggleView('predictions')">
                                <i class="fas fa-brain me-2"></i> AI Predictions
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="toggleView('banks')">
                                <i class="fas fa-building me-2"></i> Nearby Blood Banks
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#" onclick="toggleView('staff')">
                                <i class="fas fa-users me-2"></i> Staff Management
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <div class="text-center">
                                <small class="text-light">Critical Blood Types</small>
                            </div>
                        </li>
                        <?php 
                        $critical_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-'];
                        foreach($critical_types as $type): ?>
                        <li class="nav-item">
                            <span class="badge bg-danger me-2"><?php echo $type; ?></span>
                            <button class="btn btn-sm btn-outline-light">Request</button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-md-9 col-lg-10 px-4">
                <!-- Header -->
                <div class="top-header">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h3 class="mb-0">Welcome, Diane Diane!</h3>
                            <p class="mb-0">Blood Management Dashboard | Today is <?php echo date('l, F j, Y'); ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <label class="toggle-switch">
                                <input type="checkbox" id="darkModeToggle">
                                <span class="slider"></span>
                            </label>
                            <span class="ms-2">Dark Mode</span>
                            <a href="../logout.php" class="btn btn-outline-light ms-3">
                                <i class="fas fa-sign-out-alt"></i> ntyazo
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Blood Inventory Section -->
                    <div class="col-lg-8">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4><i class="fas fa-tint text-danger"></i> Blood Inventory</h4>
                            <div>
                                <button class="btn btn-outline-primary btn-sm me-2">Detailed View</button>
                                <button class="btn btn-danger btn-sm">Request Blood</button>
                            </div>
                        </div>
                        
                        <!-- Blood Type Cards Grid -->
                        <div class="row">
                            <?php foreach($blood_types as $type => $data): ?>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <div class="blood-card">
                                    <div class="blood-type text-danger"><?php echo $type; ?></div>
                                    <div class="blood-volume"><?php echo $data['available']; ?> ml</div>
                                    <div class="blood-status status-critical"><?php echo $data['status']; ?></div>
                                    <button class="request-btn">Request Urgently</button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Right Sidebar -->
                    <div class="col-lg-4">
                        <!-- Profile Card -->
                        <div class="profile-card">
                            <div class="d-flex align-items-center mb-3">
                                <div class="me-3">
                                    <div class="bg-secondary rounded-circle d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                        <i class="fas fa-user fa-2x text-white"></i>
                                    </div>
                                </div>
                                <div>
                                    <h5 class="mb-0">Diane Diane</h5>
                                    <small class="text-muted"><i class="fas fa-map-marker-alt"></i> MIDINA</small>
                                </div>
                            </div>
                            
                            <div class="row text-center">
                                <div class="col-6">
                                    <div><i class="fas fa-phone text-primary"></i></div>
                                    <small>Phone</small>
                                    <div>0789736352</div>
                                </div>
                                <div class="col-6">
                                    <div><i class="fas fa-envelope text-primary"></i></div>
                                    <small>Email</small>
                                    <div>ntyazo@gmail.com</div>
                                </div>
                            </div>
                            
                            <div class="row text-center mt-3">
                                <div class="col-6">
                                    <div><i class="fas fa-user-md text-primary"></i></div>
                                    <small>Type</small>
                                </div>
                                <div class="col-6">
                                    <div><i class="fas fa-bed text-primary"></i></div>
                                    <small>Beds</small>
                                    <div>0</div>
                                </div>
                            </div>
                            
                            <button class="btn btn-outline-primary w-100 mt-3">Edit Profile</button>
                        </div>

                        <!-- Critical Notifications -->
                        <div class="notifications-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6><i class="fas fa-exclamation-triangle text-warning"></i> Critical Notifications</h6>
                                <button class="btn btn-sm btn-outline-secondary">View All</button>
                            </div>
                            
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="fas fa-check-circle me-2"></i>
                                <span>No critical notifications at this time.</span>
                            </div>
                        </div>

                        <!-- Pending Requests -->
                        <div class="notifications-card">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6><i class="fas fa-hourglass-half text-warning"></i> Pending Requests</h6>
                                <span class="badge bg-warning">0</span>
                            </div>
                            
                            <div class="alert alert-success d-flex align-items-center">
                                <i class="fas fa-check-circle me-2"></i>
                                <span>No pending requests at this time.</span>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleView(view) {
            // Add functionality for different views
            console.log('Switching to view:', view);
        }
        
        // Dark mode toggle
        document.getElementById('darkModeToggle').addEventListener('change', function() {
            document.body.classList.toggle('dark-mode');
        });
        
        // Auto-refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

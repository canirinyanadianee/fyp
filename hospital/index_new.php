<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check if user is logged in and is a hospital
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../login.php');
    exit();
}

// Get user information
$user_id = $_SESSION['user_id'];
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get blood request statistics
$stats_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_requests
    FROM blood_requests 
    WHERE hospital_id = ?";
$stats_stmt = $pdo->prepare($stats_query);
$stats_stmt->execute([$user_id]);
$stats = $stats_stmt->fetch();

// Get blood inventory with detailed information
$inventory_query = "SELECT 
    blood_type,
    SUM(quantity_ml) as total_quantity,
    FLOOR(SUM(quantity_ml) / 450) as unit_count
    FROM blood_inventory 
    WHERE bloodbank_id IN (
        SELECT id FROM users WHERE role = 'bloodbank'
    )
    GROUP BY blood_type
    ORDER BY blood_type";
$inventory_stmt = $pdo->prepare($inventory_query);
$inventory_stmt->execute();
$inventory_data = $inventory_stmt->fetchAll();

// Create complete blood type array with all types
$blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
$inventory = [];
foreach ($blood_types as $type) {
    $found = false;
    foreach ($inventory_data as $item) {
        if ($item['blood_type'] === $type) {
            $inventory[$type] = $item;
            $found = true;
            break;
        }
    }
    if (!$found) {
        $inventory[$type] = [
            'blood_type' => $type,
            'total_quantity' => 0,
            'unit_count' => 0
        ];
    }
}

// Get recent blood requests
$recent_requests_query = "SELECT br.*, u.name as bloodbank_name 
    FROM blood_requests br 
    LEFT JOIN users u ON br.bloodbank_id = u.id 
    WHERE br.hospital_id = ? 
    ORDER BY br.created_at DESC 
    LIMIT 5";
$recent_stmt = $pdo->prepare($recent_requests_query);
$recent_stmt->execute([$user_id]);
$recent_requests = $recent_stmt->fetchAll();

// Mock AI predictions data (in real app, this would come from AI service)
$predictions = [
    'A+' => ['current' => $inventory['A+']['total_quantity'], 'predicted' => 3854, 'confidence' => 72],
    'A-' => ['current' => $inventory['A-']['total_quantity'], 'predicted' => 4275, 'confidence' => 72],
    'B+' => ['current' => $inventory['B+']['total_quantity'], 'predicted' => 3655, 'confidence' => 72],
    'B-' => ['current' => $inventory['B-']['total_quantity'], 'predicted' => 3347, 'confidence' => 72],
    'AB+' => ['current' => $inventory['AB+']['total_quantity'], 'predicted' => 3545, 'confidence' => 72],
    'AB-' => ['current' => $inventory['AB-']['total_quantity'], 'predicted' => 3149, 'confidence' => 72],
    'O+' => ['current' => $inventory['O+']['total_quantity'], 'predicted' => 3325, 'confidence' => 72],
    'O-' => ['current' => $inventory['O-']['total_quantity'], 'predicted' => 2491, 'confidence' => 72]
];

// Calculate usage statistics
$this_month_query = "SELECT SUM(quantity_ml) as total FROM blood_requests WHERE hospital_id = ? AND MONTH(created_at) = MONTH(NOW()) AND status = 'completed'";
$this_month_stmt = $pdo->prepare($this_month_query);
$this_month_stmt->execute([$user_id]);
$this_month_usage = $this_month_stmt->fetch()['total'] ?? 0;

$last_month_query = "SELECT SUM(quantity_ml) as total FROM blood_requests WHERE hospital_id = ? AND MONTH(created_at) = MONTH(NOW()) - 1 AND status = 'completed'";
$last_month_stmt = $pdo->prepare($last_month_query);
$last_month_stmt->execute([$user_id]);
$last_month_usage = $last_month_stmt->fetch()['total'] ?? 0;

// Mock chart data for blood usage by type
$chart_data = [
    'A+' => 700, 'A-' => 750, 'B+' => 800, 'B-' => 850,
    'AB+' => 400, 'AB-' => 1250, 'O+' => 600, 'O-' => 500
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Blood Management System | Hospital Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-green: #2E8B57;
            --secondary-green: #20B2AA;
            --dark-green: #006400;
            --light-green: #90EE90;
            --red-critical: #DC3545;
            --orange-warning: #FD7E14;
            --blue-info: #0DCAF0;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--secondary-green) 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header .navbar-brand {
            font-weight: bold;
            font-size: 1.25rem;
        }

        .header .nav-link {
            color: white !important;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .header .nav-link:hover {
            background: rgba(255,255,255,0.1);
            border-radius: 5px;
        }

        .dashboard-content {
            padding: 2rem 0;
        }

        .main-wrapper {
            display: flex;
            gap: 2rem;
        }

        .sidebar {
            width: 280px;
            flex-shrink: 0;
        }

        .content-area {
            flex: 1;
        }

        .user-profile {
            width: 320px;
            flex-shrink: 0;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .blood-card {
            text-align: center;
            padding: 1.5rem;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
        }

        .blood-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-green), var(--secondary-green));
        }

        .blood-type {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .blood-type.positive { color: var(--red-critical); }
        .blood-type.negative { color: var(--red-critical); }

        .blood-quantity {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .blood-status {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
        }

        .status-badge {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.8rem;
        }

        .status-critical {
            background: var(--red-critical);
            color: white;
        }

        .status-low {
            background: var(--orange-warning);
            color: white;
        }

        .status-good {
            background: var(--primary-green);
            color: white;
        }

        .request-btn {
            background: var(--red-critical);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 20px;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .request-btn:hover {
            background: #c82333;
            transform: scale(1.05);
        }

        .sidebar-nav {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
        }

        .sidebar-nav .nav-link {
            color: #333;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link.active {
            background: var(--primary-green);
            color: white;
        }

        .sidebar-nav .nav-link i {
            width: 20px;
            text-align: center;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        .profile-name {
            font-size: 1.25rem;
            font-weight: bold;
            color: #333;
            margin-bottom: 0.5rem;
        }

        .profile-location {
            color: #666;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .profile-contact {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .contact-item:last-child {
            margin-bottom: 0;
        }

        .predictions-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
        }

        .table th {
            background: var(--primary-green);
            color: white;
            border: none;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
        }

        .confidence-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
        }

        .confidence-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--blue-info), var(--primary-green));
            border-radius: 3px;
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .action-btn {
            padding: 1rem;
            border-radius: 10px;
            border: none;
            color: white;
            font-weight: bold;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .action-btn.primary {
            background: linear-gradient(135deg, var(--red-critical), #e74c3c);
        }

        .action-btn.secondary {
            background: linear-gradient(135deg, var(--blue-info), #3498db);
        }

        .action-btn.tertiary {
            background: linear-gradient(135deg, var(--primary-green), var(--secondary-green));
        }

        .action-btn.quaternary {
            background: linear-gradient(135deg, var(--blue-info), #5bc0de);
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .notifications-panel {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .notification-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1rem;
        }

        .notification-item:last-child {
            margin-bottom: 0;
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--primary-green);
            color: white;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .view-all-btn {
            color: var(--blue-info);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .view-all-btn:hover {
            color: var(--primary-green);
        }

        .usage-stats {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-green);
        }

        .stat-label {
            font-size: 0.9rem;
            color: #666;
        }

        @media (max-width: 1200px) {
            .main-wrapper {
                flex-direction: column;
            }
            
            .sidebar,
            .user-profile {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container-fluid">
            <nav class="navbar navbar-expand-lg">
                <a class="navbar-brand" href="#">
                    <i class="fas fa-tint me-2"></i>
                    AI Blood Management System | Hospital
                </a>
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="#">
                        <i class="fas fa-tachometer-alt me-1"></i>
                        Dashboard
                    </a>
                    <a class="nav-link" href="#">
                        <i class="fas fa-tint me-1"></i>
                        Blood
                    </a>
                    <a class="nav-link" href="requests.php">
                        <i class="fas fa-clipboard-list me-1"></i>
                        Requests
                    </a>
                    <a class="nav-link" href="#">
                        <i class="fas fa-chart-line me-1"></i>
                        Predictions
                    </a>
                    <a class="nav-link" href="#">
                        <i class="fas fa-hospital me-1"></i>
                        Blood Banks
                    </a>
                    <div class="nav-link">
                        <label class="form-check-label me-2">Dark Mode</label>
                        <input class="form-check-input" type="checkbox" id="darkModeToggle">
                    </div>
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($user['name'] ?? 'nityazo'); ?>
                    </a>
                </div>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container-fluid dashboard-content">
        <div class="main-wrapper">
            <!-- Sidebar -->
            <div class="sidebar">
                <div class="sidebar-nav">
                    <h6 class="mb-3 text-muted">WELCOME, <?php echo strtoupper($user['name'] ?? 'Diane Diane'); ?></h6>
                    <p class="small text-muted mb-3">Blood Management Dashboard | Today is <?php echo date('l, F j, Y'); ?></p>
                    
                    <div class="nav flex-column">
                        <a class="nav-link active" href="#">
                            <i class="fas fa-chart-bar"></i>
                            Blood Inventory
                        </a>
                        <a class="nav-link" href="requests.php">
                            <i class="fas fa-plus-circle"></i>
                            Request Blood
                        </a>
                        <a class="nav-link" href="requests.php">
                            <i class="fas fa-list"></i>
                            Blood Requests
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-chart-line"></i>
                            Record Usage
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-file-alt"></i>
                            Usage Reports
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-robot"></i>
                            AI Predictions
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-map-marker-alt"></i>
                            Nearby Blood Banks
                        </a>
                        <a class="nav-link" href="#">
                            <i class="fas fa-users"></i>
                            Staff Management
                        </a>
                    </div>

                    <div class="mt-4">
                        <h6 class="text-muted mb-2">
                            <i class="fas fa-exclamation-triangle text-warning"></i>
                            Critical Blood Types
                        </h6>
                        <?php foreach ($blood_types as $type): ?>
                            <?php if ($inventory[$type]['unit_count'] < 5): ?>
                                <div class="d-flex align-items-center justify-content-between mb-2 p-2 bg-light rounded">
                                    <span class="badge bg-danger"><?php echo $type; ?></span>
                                    <button class="btn btn-sm btn-outline-danger">Request</button>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="content-area">
                <!-- Blood Inventory Section -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-tint text-danger"></i>
                        Blood Inventory
                    </h2>
                    <div>
                        <a href="#" class="view-all-btn me-3">Detailed View</a>
                        <button class="btn btn-danger">Request Blood</button>
                    </div>
                </div>

                <div class="row mb-4">
                    <?php foreach ($blood_types as $type): ?>
                        <div class="col-md-3 col-sm-6 mb-3">
                            <div class="card blood-card">
                                <div class="blood-type <?php echo strpos($type, '+') !== false ? 'positive' : 'negative'; ?>">
                                    <?php echo $type; ?>
                                </div>
                                <div class="blood-quantity">
                                    <?php echo $inventory[$type]['total_quantity']; ?> ml
                                </div>
                                <div class="blood-status">
                                    Available
                                </div>
                                <div class="mb-3">
                                    <?php
                                    $units = $inventory[$type]['unit_count'];
                                    if ($units >= 10) {
                                        echo '<span class="status-badge status-good">Good Stock</span>';
                                    } elseif ($units >= 5) {
                                        echo '<span class="status-badge status-low">Low Stock</span>';
                                    } else {
                                        echo '<span class="status-badge status-critical">Critical</span>';
                                    }
                                    ?>
                                </div>
                                <button class="request-btn">
                                    Request Urgently
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- AI Predictions Section -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-robot text-primary"></i>
                        AI Blood Usage Predictions (Next 7 Days)
                    </h2>
                    <a href="#" class="view-all-btn">View Detailed Predictions</a>
                </div>

                <div class="card predictions-table mb-4">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Blood Type</th>
                                    <th>Current Stock</th>
                                    <th>Predicted Usage</th>
                                    <th>Status</th>
                                    <th>Confidence</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($predictions as $type => $data): ?>
                                    <tr>
                                        <td><span class="badge bg-primary"><?php echo $type; ?></span></td>
                                        <td><?php echo number_format($data['current']); ?> ml</td>
                                        <td><?php echo number_format($data['predicted']); ?> ml</td>
                                        <td>
                                            <span class="badge bg-danger">Shortage Predicted</span>
                                        </td>
                                        <td>
                                            <div><?php echo $data['confidence']; ?>% confidence</div>
                                            <div class="confidence-bar mt-1">
                                                <div class="confidence-fill" style="width: <?php echo $data['confidence']; ?>%"></div>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-danger">Request Blood</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Usage Chart -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <h5 class="mb-3">Blood Usage Statistics</h5>
                            <canvas id="usageChart" height="300"></canvas>
                            <div class="usage-stats">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($this_month_usage); ?> ml</div>
                                    <div class="stat-label">This Month</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($last_month_usage); ?> ml</div>
                                    <div class="stat-label">Last Month</div>
                                </div>
                            </div>
                            <div class="text-center mt-2">
                                <a href="#" class="btn btn-outline-primary">View Detailed Reports</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="quick-actions">
                            <button class="action-btn primary">
                                <i class="fas fa-tint"></i>
                                Request Blood
                            </button>
                            <button class="action-btn secondary">
                                <i class="fas fa-lock"></i>
                                Record Blood Usage
                            </button>
                            <button class="action-btn tertiary">
                                <i class="fas fa-map-marker-alt"></i>
                                Find Nearby Blood Banks
                            </button>
                            <button class="action-btn quaternary">
                                <i class="fas fa-robot"></i>
                                Run AI Prediction
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Requests -->
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clipboard-list text-info"></i>
                        Recent Blood Requests
                    </h2>
                    <a href="requests.php" class="view-all-btn">View All</a>
                </div>

                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Request ID</th>
                                    <th>Blood Type</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Requested On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_requests)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4">
                                            <i class="fas fa-info-circle text-muted"></i>
                                            No recent requests found.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_requests as $request): ?>
                                        <tr>
                                            <td>#<?php echo $request['id']; ?></td>
                                            <td><span class="badge bg-primary"><?php echo $request['blood_type']; ?></span></td>
                                            <td><?php echo $request['quantity_ml']; ?> ml</td>
                                            <td>
                                                <?php
                                                $status_class = [
                                                    'pending' => 'bg-warning',
                                                    'approved' => 'bg-success',
                                                    'rejected' => 'bg-danger',
                                                    'completed' => 'bg-success'
                                                ];
                                                ?>
                                                <span class="badge <?php echo $status_class[$request['status']] ?? 'bg-secondary'; ?>">
                                                    <?php echo ucfirst($request['status']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary">View</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- User Profile Sidebar -->
            <div class="user-profile">
                <!-- Critical Notifications -->
                <div class="notifications-panel">
                    <h6 class="d-flex align-items-center justify-content-between mb-3">
                        <span>
                            <i class="fas fa-exclamation-triangle text-danger"></i>
                            Critical Notifications
                        </span>
                        <a href="#" class="view-all-btn">View All</a>
                    </h6>
                    <div class="notification-item">
                        <div class="notification-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <div class="fw-bold">No critical notifications at this time.</div>
                        </div>
                    </div>
                </div>

                <!-- Pending Requests -->
                <div class="notifications-panel">
                    <h6 class="d-flex align-items-center justify-content-between mb-3">
                        <span>
                            <i class="fas fa-clock text-warning"></i>
                            Pending Requests
                        </span>
                        <span class="badge bg-warning">0</span>
                    </h6>
                    <div class="notification-item">
                        <div class="notification-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <div class="fw-bold">No pending requests at this time.</div>
                        </div>
                    </div>
                </div>

                <!-- User Profile -->
                <div class="profile-card">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['name'] ?? 'DD', 0, 2)); ?>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user['name'] ?? 'Diane Diane'); ?></div>
                    <div class="profile-location">
                        <i class="fas fa-map-marker-alt"></i>
                        MIDUHA
                    </div>
                    
                    <div class="profile-contact">
                        <div class="contact-item">
                            <i class="fas fa-phone text-primary"></i>
                            <strong>Phone:</strong>
                            <span>078736352</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope text-primary"></i>
                            <strong>Email:</strong>
                            <span><?php echo htmlspecialchars($user['email'] ?? 'nityazo@gmail.com'); ?></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-bed text-primary"></i>
                            <strong>Type:</strong>
                            <span>Hospital</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-bed text-primary"></i>
                            <strong>Beds:</strong>
                            <span>0</span>
                        </div>
                    </div>
                    
                    <button class="btn btn-outline-primary w-100">Edit Profile</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart.js configuration
        const ctx = document.getElementById('usageChart').getContext('2d');
        const chartData = <?php echo json_encode(array_values($chart_data)); ?>;
        const chartLabels = <?php echo json_encode(array_keys($chart_data)); ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartLabels,
                datasets: [{
                    label: 'Usage (ml)',
                    data: chartData,
                    backgroundColor: [
                        '#28a745', '#28a745', '#17a2b8', '#17a2b8',
                        '#fd7e14', '#dc3545', '#6f42c1', '#6f42c1'
                    ],
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#e9ecef'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

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

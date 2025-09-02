<?php
// hospital/reports.php
// Show reports for the hospital
require_once '../includes/config.php';
$session_already_started = session_status() === PHP_SESSION_ACTIVE;
if (!$session_already_started) {
    session_start();
}
$hospital_id = isset($_SESSION['hospital_id']) ? intval($_SESSION['hospital_id']) : 0;
if ($hospital_id) {
    require_once '../includes/db.php';
    require_once '../includes/functions.php';
    $totalTransfers = $conn->query("SELECT COUNT(*) as total FROM blood_transfers WHERE hospital_id = $hospital_id")->fetch_assoc()['total'];
    $totalQuantity = $conn->query("SELECT SUM(quantity_ml) as total FROM blood_transfers WHERE hospital_id = $hospital_id")->fetch_assoc()['total'];
    $completedTransfers = $conn->query("SELECT COUNT(*) as total FROM blood_transfers WHERE hospital_id = $hospital_id AND status='completed'")->fetch_assoc()['total'];
    $stats = get_hospital_stats($hospital_id, $conn);
} else {
    $totalTransfers = $totalQuantity = $completedTransfers = 0;
    $stats = [
        'total_requests' => 0,
        'pending_requests' => 0,
        'approved_requests' => 0,
        'completed_requests' => 0,
        'recent_requests' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #e0e7ff 0%, #f8fafc 100%);
            min-height: 100vh;
        }
        .dashboard-title {
            background: linear-gradient(90deg, #6366f1 0%, #06b6d4 100%);
            color: #fff;
            border-radius: 1rem;
            padding: 1.5rem 0;
            box-shadow: 0 4px 24px rgba(99,102,241,0.12);
        }
        .card {
            border: none;
            border-radius: 1rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .card:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 8px 32px rgba(99,102,241,0.18);
        }
        .card-title, .card-text {
            color: #334155;
        }
        .card .fa-2x, .card .fa-lg {
            margin-bottom: 0.5rem;
        }
        .summary-section {
            background: linear-gradient(90deg, #f472b6 0%, #facc15 100%);
            color: #fff;
            border-radius: 1rem;
            padding: 1.25rem 0.5rem;
            margin-top: 2rem;
            box-shadow: 0 2px 16px rgba(244,114,182,0.10);
        }
        .summary-section strong {
            color: #fff;
        }
        .dashboard-icon {
            background: linear-gradient(135deg, #6366f1 0%, #06b6d4 100%);
            color: #fff;
            border-radius: 50%;
            padding: 0.75rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 8px rgba(99,102,241,0.10);
        }
    </style>
</head>
<body>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <div class="container py-5">
        <div class="text-center mb-5">
            <h1 class="display-5 fw-bold text-primary mb-2"><i class="fas fa-chart-bar me-2"></i>Hospital Reports</h1>
            <p class="lead text-secondary">Overview of your hospital's blood requests, transfers, and usage statistics</p>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-exchange-alt fa-2x text-info mb-2"></i>
                        <h5 class="card-title">Total Transfers</h5>
                        <p class="display-6 fw-bold mb-0"><?php echo $totalTransfers; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-tint fa-2x text-danger mb-2"></i>
                        <h5 class="card-title">Total Quantity (ml)</h5>
                        <p class="display-6 fw-bold mb-0"><?php echo $totalQuantity ? $totalQuantity : 0; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h5 class="card-title">Completed Transfers</h5>
                        <p class="display-6 fw-bold mb-0"><?php echo $completedTransfers; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row g-4 mb-4">
            <div class="col-md-3">
                <div class="card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-list fa-lg text-primary mb-2"></i>
                        <h6 class="card-title">Total Requests</h6>
                        <p class="display-6 fw-bold mb-0"><?php echo $stats['total_requests']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-hourglass-half fa-lg text-warning mb-2"></i>
                        <h6 class="card-title">Pending Requests</h6>
                        <p class="display-6 fw-bold mb-0"><?php echo $stats['pending_requests']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-thumbs-up fa-lg text-info mb-2"></i>
                        <h6 class="card-title">Approved Requests</h6>
                        <p class="display-6 fw-bold mb-0"><?php echo $stats['approved_requests']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow h-100 border-0">
                    <div class="card-body text-center">
                        <i class="fas fa-check-double fa-lg text-success mb-2"></i>
                        <h6 class="card-title">Completed Requests</h6>
                        <p class="display-6 fw-bold mb-0"><?php echo $stats['completed_requests']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-5">
            <div class="col-12">
                <div class="alert alert-info text-center shadow-sm">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Tip:</strong> Use this dashboard to monitor your hospital's blood activity and optimize your requests and usage.
                </div>
            </div>
        </div>
    </div>
                                    <p class="card-text display-6"><?php echo $stats['completed_requests']; ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Add more detailed reports/analytics here as needed -->
</html>

<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check if user is logged in and is a hospital
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user information including location
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get search parameters
$search_city = $_GET['city'] ?? $user['city'] ?? '';
$blood_type_filter = $_GET['blood_type'] ?? '';
$radius = $_GET['radius'] ?? '50'; // Default 50km radius

// Get all blood banks with their inventory
$blood_banks_query = "
    SELECT 
        bb.*,
        COUNT(bi.id) as inventory_count,
        SUM(CASE WHEN bi.expiry_date > CURDATE() THEN bi.quantity_ml ELSE 0 END) as total_stock,
        GROUP_CONCAT(
            CASE WHEN bi.expiry_date > CURDATE() 
            THEN CONCAT(bi.blood_type, ':', bi.quantity_ml) 
            END SEPARATOR '|'
        ) as available_stock
    FROM blood_banks bb
    LEFT JOIN blood_inventory bi ON bb.id = bi.blood_bank_id
    WHERE bb.city LIKE ? OR bb.name LIKE ?
    GROUP BY bb.id
    ORDER BY bb.city, bb.name
";

$search_term = '%' . $search_city . '%';
$banks_stmt = $conn->prepare($blood_banks_query);
$blood_banks = [];
if ($banks_stmt) {
    $banks_stmt->bind_param("ss", $search_term, $search_term);
    $banks_stmt->execute();
    $banks_result = $banks_stmt->get_result();
    while ($row = $banks_result->fetch_assoc()) {
        // Parse available stock
        $stock_data = [];
        if ($row['available_stock']) {
            $stock_items = explode('|', $row['available_stock']);
            foreach ($stock_items as $item) {
                if (strpos($item, ':') !== false) {
                    list($type, $quantity) = explode(':', $item);
                    if (!isset($stock_data[$type])) {
                        $stock_data[$type] = 0;
                    }
                    $stock_data[$type] += $quantity;
                }
            }
        }
        $row['stock_by_type'] = $stock_data;
        
        // Filter by blood type if specified
        if (empty($blood_type_filter) || isset($stock_data[$blood_type_filter])) {
            $blood_banks[] = $row;
        }
    }
}

// Get blood bank statistics
$stats_query = "
    SELECT 
        COUNT(DISTINCT bb.id) as total_banks,
        COUNT(DISTINCT bb.city) as cities_covered,
        SUM(CASE WHEN bi.expiry_date > CURDATE() THEN bi.quantity_ml ELSE 0 END) as total_inventory,
        AVG(bb.rating) as avg_rating
    FROM blood_banks bb
    LEFT JOIN blood_inventory bi ON bb.id = bi.blood_bank_id
    WHERE bb.city LIKE ?
";

$stats_stmt = $conn->prepare($stats_query);
$stats = ['total_banks' => 0, 'cities_covered' => 0, 'total_inventory' => 0, 'avg_rating' => 0];
if ($stats_stmt) {
    $stats_stmt->bind_param("s", $search_term);
    $stats_stmt->execute();
    $stats_result = $stats_stmt->get_result();
    $stats = $stats_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nearby Blood Banks - Hospital Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .blood-bank-card {
            transition: transform 0.2s;
        }
        .blood-bank-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .stock-badge {
            font-size: 0.8em;
            margin: 2px;
        }
        .rating-stars {
            color: #ffc107;
        }
    </style>
</head>
<body>
    <!-- Include header -->
    <?php include '../includes/hospital_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-light sidebar py-3">
                <h6 class="text-muted mb-3">HOSPITAL MENU</h6>
                <div class="nav flex-column">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-chart-bar"></i> Blood Inventory
                    </a>
                    <a class="nav-link" href="requests.php">
                        <i class="fas fa-plus-circle"></i> Request Blood
                    </a>
                    <a class="nav-link" href="blood_requests.php">
                        <i class="fas fa-list"></i> Blood Requests
                    </a>
                    <a class="nav-link" href="record_usage.php">
                        <i class="fas fa-chart-line"></i> Record Usage
                    </a>
                    <a class="nav-link" href="usage_reports.php">
                        <i class="fas fa-file-alt"></i> Usage Reports
                    </a>
                    <a class="nav-link" href="ai_predictions.php">
                        <i class="fas fa-robot"></i> AI Predictions
                    </a>
                    <a class="nav-link active" href="nearby_bloodbanks.php">
                        <i class="fas fa-map-marker-alt"></i> Nearby Blood Banks
                    </a>
                    <a class="nav-link" href="staff_management.php">
                        <i class="fas fa-users"></i> Staff Management
                    </a>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Nearby Blood Banks</h1>
                    <div>
                        <button class="btn btn-outline-primary" onclick="refreshLocation()">
                            <i class="fas fa-location-arrow"></i> Refresh Location
                        </button>
                        <button class="btn btn-outline-success" onclick="exportBanks()">
                            <i class="fas fa-download"></i> Export List
                        </button>
                    </div>
                </div>

                <!-- Search Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="city" class="form-label">City/Location</label>
                                <input type="text" class="form-control" id="city" name="city" 
                                       value="<?php echo htmlspecialchars($search_city); ?>" 
                                       placeholder="Enter city name">
                            </div>
                            <div class="col-md-3">
                                <label for="blood_type" class="form-label">Blood Type Available</label>
                                <select class="form-select" id="blood_type" name="blood_type">
                                    <option value="">All Types</option>
                                    <option value="A+" <?php echo ($blood_type_filter === 'A+') ? 'selected' : ''; ?>>A+</option>
                                    <option value="A-" <?php echo ($blood_type_filter === 'A-') ? 'selected' : ''; ?>>A-</option>
                                    <option value="B+" <?php echo ($blood_type_filter === 'B+') ? 'selected' : ''; ?>>B+</option>
                                    <option value="B-" <?php echo ($blood_type_filter === 'B-') ? 'selected' : ''; ?>>B-</option>
                                    <option value="AB+" <?php echo ($blood_type_filter === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                    <option value="AB-" <?php echo ($blood_type_filter === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                    <option value="O+" <?php echo ($blood_type_filter === 'O+') ? 'selected' : ''; ?>>O+</option>
                                    <option value="O-" <?php echo ($blood_type_filter === 'O-') ? 'selected' : ''; ?>>O-</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="radius" class="form-label">Search Radius</label>
                                <select class="form-select" id="radius" name="radius">
                                    <option value="10" <?php echo ($radius === '10') ? 'selected' : ''; ?>>10 km</option>
                                    <option value="25" <?php echo ($radius === '25') ? 'selected' : ''; ?>>25 km</option>
                                    <option value="50" <?php echo ($radius === '50') ? 'selected' : ''; ?>>50 km</option>
                                    <option value="100" <?php echo ($radius === '100') ? 'selected' : ''; ?>>100 km</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Statistics Row -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?php echo $stats['total_banks']; ?></h3>
                                <p class="card-text">Blood Banks Found</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?php echo $stats['cities_covered']; ?></h3>
                                <p class="card-text">Cities Covered</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo number_format($stats['total_inventory']); ?> ml</h3>
                                <p class="card-text">Total Inventory</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning">
                                    <?php echo number_format($stats['avg_rating'], 1); ?> 
                                    <i class="fas fa-star rating-stars"></i>
                                </h3>
                                <p class="card-text">Average Rating</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Blood Banks List -->
                <?php if (empty($blood_banks)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-map-marker-alt fa-3x text-muted mb-3"></i>
                            <h5>No Blood Banks Found</h5>
                            <p class="text-muted">No blood banks found in the specified location. Try expanding your search radius or checking nearby cities.</p>
                            <button class="btn btn-primary" onclick="document.getElementById('radius').value='100'; document.querySelector('form').submit();">
                                Expand Search to 100km
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($blood_banks as $bank): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card blood-bank-card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><?php echo htmlspecialchars($bank['name']); ?></h6>
                                        <div class="rating-stars">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo ($i <= $bank['rating']) ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt"></i> 
                                                <?php echo htmlspecialchars($bank['city']); ?>
                                                <?php if (!empty($bank['address'])): ?>
                                                    <br><?php echo htmlspecialchars($bank['address']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>

                                        <?php if (!empty($bank['contact_phone'])): ?>
                                            <div class="mb-2">
                                                <small class="text-muted">
                                                    <i class="fas fa-phone"></i> 
                                                    <a href="tel:<?php echo htmlspecialchars($bank['contact_phone']); ?>">
                                                        <?php echo htmlspecialchars($bank['contact_phone']); ?>
                                                    </a>
                                                </small>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($bank['email'])): ?>
                                            <div class="mb-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-envelope"></i> 
                                                    <a href="mailto:<?php echo htmlspecialchars($bank['email']); ?>">
                                                        <?php echo htmlspecialchars($bank['email']); ?>
                                                    </a>
                                                </small>
                                            </div>
                                        <?php endif; ?>

                                        <div class="mb-3">
                                            <small class="text-muted d-block mb-1">Available Blood Types:</small>
                                            <?php if (empty($bank['stock_by_type'])): ?>
                                                <span class="badge bg-secondary">No stock available</span>
                                            <?php else: ?>
                                                <?php foreach ($bank['stock_by_type'] as $type => $quantity): ?>
                                                    <span class="badge bg-primary stock-badge">
                                                        <?php echo $type; ?>: <?php echo number_format($quantity); ?>ml
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>

                                        <div class="mb-3">
                                            <small class="text-muted">
                                                Total Stock: <strong><?php echo number_format($bank['total_stock']); ?> ml</strong>
                                            </small>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="d-grid gap-2">
                                            <a href="requests.php?blood_bank_id=<?php echo $bank['id']; ?>" 
                                               class="btn btn-primary btn-sm">
                                                <i class="fas fa-plus"></i> Request Blood
                                            </a>
                                            <button class="btn btn-outline-info btn-sm" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#bankModal<?php echo $bank['id']; ?>">
                                                <i class="fas fa-info-circle"></i> View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Blood Bank Detail Modals -->
    <?php foreach ($blood_banks as $bank): ?>
        <div class="modal fade" id="bankModal<?php echo $bank['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><?php echo htmlspecialchars($bank['name']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Contact Information</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Location:</strong></td>
                                        <td><?php echo htmlspecialchars($bank['city']); ?></td>
                                    </tr>
                                    <?php if (!empty($bank['address'])): ?>
                                    <tr>
                                        <td><strong>Address:</strong></td>
                                        <td><?php echo htmlspecialchars($bank['address']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($bank['contact_phone'])): ?>
                                    <tr>
                                        <td><strong>Phone:</strong></td>
                                        <td>
                                            <a href="tel:<?php echo htmlspecialchars($bank['contact_phone']); ?>">
                                                <?php echo htmlspecialchars($bank['contact_phone']); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <?php if (!empty($bank['email'])): ?>
                                    <tr>
                                        <td><strong>Email:</strong></td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($bank['email']); ?>">
                                                <?php echo htmlspecialchars($bank['email']); ?>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr>
                                        <td><strong>Rating:</strong></td>
                                        <td>
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo ($i <= $bank['rating']) ? '' : '-o'; ?> rating-stars"></i>
                                            <?php endfor; ?>
                                            (<?php echo $bank['rating']; ?>/5)
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Inventory Details</h6>
                                <?php if (empty($bank['stock_by_type'])): ?>
                                    <p class="text-muted">No blood inventory available</p>
                                <?php else: ?>
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Blood Type</th>
                                                <th>Quantity</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($bank['stock_by_type'] as $type => $quantity): ?>
                                                <tr>
                                                    <td><span class="badge bg-primary"><?php echo $type; ?></span></td>
                                                    <td><?php echo number_format($quantity); ?> ml</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="mt-2">
                                        <strong>Total Available: <?php echo number_format($bank['total_stock']); ?> ml</strong>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <a href="requests.php?blood_bank_id=<?php echo $bank['id']; ?>" 
                           class="btn btn-primary">
                            <i class="fas fa-plus"></i> Request Blood
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    // In a real implementation, you would reverse geocode the coordinates
                    alert('Location refreshed. You may need to update your profile with your current city.');
                });
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }

        function exportBanks() {
            let csv = 'Name,City,Phone,Email,Rating,Total Stock (ml)\n';
            <?php foreach ($blood_banks as $bank): ?>
                csv += '"<?php echo str_replace('"', '""', $bank['name']); ?>","<?php echo str_replace('"', '""', $bank['city']); ?>","<?php echo str_replace('"', '""', $bank['contact_phone'] ?? ''); ?>","<?php echo str_replace('"', '""', $bank['email'] ?? ''); ?>",<?php echo $bank['rating']; ?>,<?php echo $bank['total_stock']; ?>\n';
            <?php endforeach; ?>
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'blood_banks_<?php echo date("Y-m-d"); ?>.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>

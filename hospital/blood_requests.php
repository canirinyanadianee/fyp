<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check if user is logged in and is a hospital
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user information
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Resolve hospital_id for this hospital user (most tables use hospitals.id)
$hospital_id = null;
$hstmt = $conn->prepare("SELECT id FROM hospitals WHERE user_id = ? LIMIT 1");
if ($hstmt) {
    $hstmt->bind_param("i", $user_id);
    $hstmt->execute();
    $hres = $hstmt->get_result();
    if ($hrow = $hres->fetch_assoc()) {
        $hospital_id = (int)$hrow['id'];
    }
}
// Fallback: if no hospitals row, use the user id (keeps backward compatibility)
if (empty($hospital_id)) {
    $hospital_id = (int)$user_id;
}

// Handle status update (approve/reject/cancel) with optional reason
if ($_POST && isset($_POST['update_request'])) {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $new_status = $_POST['status'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if ($request_id > 0 && in_array($new_status, ['approved','rejected','completed','pending'], true)) {
        // If rejecting and reason provided, append to notes
        if ($new_status === 'rejected' && $reason !== '') {
             $upd = $conn->prepare("UPDATE blood_requests SET status = 'rejected', notes = CONCAT(COALESCE(notes,''), CASE WHEN notes IS NULL OR notes = '' THEN '' ELSE '\n' END, 'Rejection reason: ', ?), updated_at = NOW() WHERE id = ? AND hospital_id = ?");
            if ($upd) {
                $upd->bind_param('sii', $reason, $request_id, $hospital_id);
                $upd->execute();
            }
        } else {
            $update_sql = "UPDATE blood_requests SET status = ?, updated_at = NOW() WHERE id = ? AND hospital_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            if ($update_stmt) {
                $update_stmt->bind_param("sii", $new_status, $request_id, $hospital_id);
                $update_stmt->execute();
            }
        }
    }
}

// Get all blood requests for this hospital
$requests_query = "
    SELECT br.*
    FROM blood_requests br 
    WHERE br.hospital_id = ? 
    ORDER BY br.created_at DESC
";
$requests_stmt = $conn->prepare($requests_query);
$requests = [];
if ($requests_stmt) {
    $requests_stmt->bind_param("i", $hospital_id);
    $requests_stmt->execute();
    $result = $requests_stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        // fetch recommendations for this request if present
        $rec_stmt = $conn->prepare("SELECT recommended FROM ai_recommendations WHERE request_id = ? LIMIT 1");
        if ($rec_stmt) {
            $rec_stmt->bind_param('i', $row['id']);
            $rec_stmt->execute();
            $rres = $rec_stmt->get_result();
            if ($rrow = $rres->fetch_assoc()) {
                $row['ai_recommendations'] = json_decode($rrow['recommended'], true);
            } else {
                $row['ai_recommendations'] = null;
            }
        }
        $requests[] = $row;
    }
}

// If redirected after creating a new request, capture new_id to open modal
$new_request_id = isset($_GET['new_id']) ? (int) $_GET['new_id'] : 0;

// Get request statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_requests
    FROM blood_requests 
    WHERE hospital_id = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats = ['total_requests' => 0, 'pending_requests' => 0, 'approved_requests' => 0, 'rejected_requests' => 0, 'completed_requests' => 0];
if ($stats_stmt) {
    $stats_stmt->bind_param("i", $hospital_id);
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
    <title>Blood Requests - Hospital Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
                    <a class="nav-link active" href="blood_requests.php">
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
                    <a class="nav-link" href="nearby_bloodbanks.php">
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
                    <h1 class="h2">Blood Requests</h1>
                    <a href="requests.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Request
                    </a>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-primary"><?php echo $stats['total_requests']; ?></h3>
                                <p class="card-text">Total Requests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-warning"><?php echo $stats['pending_requests']; ?></h3>
                                <p class="card-text">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-success"><?php echo $stats['approved_requests']; ?></h3>
                                <p class="card-text">Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h3 class="text-info"><?php echo $stats['completed_requests']; ?></h3>
                                <p class="card-text">Completed</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requests Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">All Blood Requests</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($requests)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-list-alt fa-3x text-muted mb-3"></i>
                                <h5>No Blood Requests</h5>
                                <p class="text-muted">You haven't made any blood requests yet.</p>
                                <a href="requests.php" class="btn btn-primary">Make Your First Request</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Blood Type</th>
                                            <th>Quantity</th>
                                            <th>Patient</th>
                                            <th>Urgency</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $request): ?>
                                            <tr>
                                                <td>#<?php echo $request['id']; ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $request['blood_type']; ?></span>
                                                </td>
                                                <td><?php echo number_format($request['quantity_ml']); ?> ml</td>
                                                <td><?php echo htmlspecialchars($request['patient_name'] ?? 'Not specified'); ?></td>
                                                <td>
                                                    <?php
                                                    $urgency_class = '';
                                                    switch ($request['urgency']) {
                                                        case 'routine':
                                                            $urgency_class = 'bg-secondary';
                                                            break;
                                                        case 'urgent':
                                                            $urgency_class = 'bg-warning';
                                                            break;
                                                        case 'emergency':
                                                            $urgency_class = 'bg-danger';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $urgency_class; ?>">
                                                        <?php echo ucfirst($request['urgency']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    switch ($request['status']) {
                                                        case 'pending':
                                                            $status_class = 'bg-warning text-dark';
                                                            break;
                                                        case 'approved':
                                                            $status_class = 'bg-success';
                                                            break;
                                                        case 'rejected':
                                                            $status_class = 'bg-danger';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'bg-info';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($request['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewModal<?php echo $request['id']; ?>">
                                                        <i class="fas fa-eye"></i> View
                                                    </button>

                                                    <?php if ($request['status'] === 'pending'): ?>
                                                        <form method="post" class="d-inline ms-1" onsubmit="return confirm('Approve this request?');">
                                                            <input type="hidden" name="update_request" value="1">
                                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                            <input type="hidden" name="status" value="approved">
                                                            <button type="submit" class="btn btn-sm btn-success">
                                                                <i class="fas fa-check me-1"></i>Approve
                                                            </button>
                                                        </form>
                                                        <button class="btn btn-sm btn-danger ms-1" data-bs-toggle="modal" data-bs-target="#rejectModal" data-id="<?php echo $request['id']; ?>">
                                                            <i class="fas fa-times me-1"></i>Reject
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Request Modals -->
    <?php foreach ($requests as $request): ?>
        <div class="modal fade" id="viewModal<?php echo $request['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Blood Request #<?php echo $request['id']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-12">
                                <h6>Request Details</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Blood Type:</strong></td>
                                        <td><span class="badge bg-primary"><?php echo $request['blood_type']; ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Quantity:</strong></td>
                                        <td><?php echo number_format($request['quantity_ml']); ?> ml</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Patient Name:</strong></td>
                                        <td><?php echo htmlspecialchars($request['patient_name'] ?? 'Not specified'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Urgency:</strong></td>
                                        <td><?php echo ucfirst($request['urgency']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td><?php echo ucfirst($request['status']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if (!empty($request['notes'])): ?>
                            <div class="mt-3">
                                <h6>Notes</h6>
                                <p class="text-muted"><?php echo nl2br(htmlspecialchars($request['notes'])); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                Created: <?php echo date('F j, Y g:i A', strtotime($request['created_at'])); ?><br>
                                Last Updated: <?php echo date('F j, Y g:i A', strtotime($request['updated_at'])); ?>
                            </small>
                        </div>

                        <?php if (!empty($request['ai_recommendations'])): ?>
                            <div class="mt-3">
                                <h6>Recommended Donors (automated)</h6>
                                <table class="table table-sm">
                                    <thead>
                                        <tr><th>Name</th><th>Phone</th><th>Distance (km)</th><th>Score</th><th>Action</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($request['ai_recommendations'] as $rec): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($rec['name'] ?? 'Donor'); ?></td>
                                                <td><?php echo htmlspecialchars($rec['phone'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($rec['distance_km'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($rec['score'] ?? '0'); ?></td>
                                                <td>
                                                    <?php if (!empty($rec['phone'])): ?>
                                                        <a class="btn btn-sm btn-outline-primary" href="tel:<?php echo htmlspecialchars($rec['phone']); ?>">Call</a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No contact</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Reject Modal (reused for any request) -->
    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">Reject Blood Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="update_request" value="1">
                        <input type="hidden" name="status" value="rejected">
                        <input type="hidden" name="request_id" id="rejectRequestId" value="0">
                        <div class="mb-3">
                            <label for="rejectReason" class="form-label">Reason for rejection</label>
                            <textarea class="form-control" id="rejectReason" name="reason" rows="3" placeholder="Provide a reason" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Reject Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // If the page was loaded with ?new_id=, open the corresponding modal and highlight
        (function(){
            var newId = <?php echo json_encode($new_request_id); ?>;
            if (newId && document.getElementById('viewModal' + newId)) {
                var myModal = new bootstrap.Modal(document.getElementById('viewModal' + newId));
                myModal.show();
                // highlight row briefly
                var row = document.querySelector('button[data-bs-target="#viewModal' + newId + '"]').closest('tr');
                if (row) {
                    row.style.transition = 'background-color 0.4s';
                    row.style.backgroundColor = '#fff9c4';
                    setTimeout(function(){ row.style.backgroundColor = ''; }, 3000);
                }
            }
        })();
        // Populate reject modal with the clicked request id
        (function(){
            var rejectModal = document.getElementById('rejectModal');
            if (!rejectModal) return;
            rejectModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Button that triggered the modal
                if (!button) return;
                var reqId = button.getAttribute('data-id');
                var input = document.getElementById('rejectRequestId');
                if (input && reqId) input.value = reqId;
            });
        })();
    </script>
</body>
</html>

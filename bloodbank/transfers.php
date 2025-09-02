<?php
// Process Transfers page for Blood Bank
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bloodbank') {
    header('Location: ../login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
$blood_bank_id = $bank['id'];

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$blood_type_filter = $_GET['blood_type'] ?? '';

// Build base query with inventory check
$query = "SELECT t.*, h.name as hospital_name, 
    (SELECT SUM(quantity_ml) FROM blood_inventory 
     WHERE blood_bank_id = t.blood_bank_id AND blood_type = t.blood_type) as available_quantity
    FROM blood_transfers t 
    JOIN hospitals h ON t.hospital_id = h.id 
    WHERE t.blood_bank_id = $blood_bank_id";

// Add filters
if ($status_filter) {
    $status = $conn->real_escape_string($status_filter);
    $query .= " AND t.status = '$status'";
}
if ($blood_type_filter) {
    $type = $conn->real_escape_string($blood_type_filter);
    $query .= " AND t.blood_type = '$type'";
}

$query .= " ORDER BY 
    CASE 
        WHEN t.status = 'requested' THEN 1
        WHEN t.status = 'approved' THEN 2
        WHEN t.status = 'completed' THEN 3
        ELSE 4
    END,
    t.transfer_date DESC";

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transfer_id'])) {
    $transfer_id = intval($_POST['transfer_id']);
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        // Check inventory before approving
        $transfer = $conn->query("
            SELECT t.*, 
                (SELECT SUM(quantity_ml) FROM blood_inventory 
                 WHERE blood_bank_id = $blood_bank_id AND blood_type = t.blood_type) as available_quantity
            FROM blood_transfers t 
            WHERE t.id = $transfer_id AND t.blood_bank_id = $blood_bank_id
        ")->fetch_assoc();
        
        if ($transfer && $transfer['available_quantity'] >= $transfer['quantity_ml']) {
            $conn->query("UPDATE blood_transfers SET status = 'approved', approved_at = NOW() 
                         WHERE id = $transfer_id AND blood_bank_id = $blood_bank_id");
            $_SESSION['success'] = "Transfer request approved";
        } else {
            $_SESSION['error'] = "Not enough inventory to approve this transfer";
        }
    } 
    elseif ($action === 'reject') {
        $reason = $conn->real_escape_string($_POST['reject_reason'] ?? 'No reason provided');
        $conn->query("UPDATE blood_transfers SET status = 'rejected', rejected_at = NOW(), 
                     reject_reason = '$reason' WHERE id = $transfer_id");
        $_SESSION['success'] = "Transfer request rejected";
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

$transfers = $conn->query($query);
$blood_types = $conn->query("SELECT DISTINCT blood_type FROM blood_inventory WHERE blood_bank_id = $blood_bank_id")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Process Transfers - <?php echo htmlspecialchars($bank['name']); ?> Blood Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-requested { background-color: #e2e3e5; }
        .status-approved { background-color: #c3e6cb; }
        .status-completed { background-color: #b8daff; }
        .status-rejected { background-color: #f5c6cb; }
    </style>
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4">
        <i class="fas fa-exchange-alt text-warning me-2"></i>Process Transfer Requests
    </h2>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <div class="col-md-5">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="requested" <?= $status_filter === 'requested' ? 'selected' : '' ?>>Requested</option>
                        <option value="approved" <?= $status_filter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Blood Type</label>
                    <select name="blood_type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($blood_types as $type): ?>
                            <option value="<?= htmlspecialchars($type['blood_type']) ?>" 
                                <?= $blood_type_filter === $type['blood_type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['blood_type']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="transfers.php" class="btn btn-outline-secondary">
                        <i class="fas fa-undo"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success'] ?>
            <?php unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error'] ?>
            <?php unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Hospital</th>
                        <th>Blood Type</th>
                        <th>Quantity</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($transfers && $transfers->num_rows > 0): ?>
                    <?php while ($tr = $transfers->fetch_assoc()): ?>
                    <tr class="status-<?= strtolower($tr['status']) ?>">
                        <td><?= htmlspecialchars($tr['hospital_name']) ?></td>
                        <td><?= htmlspecialchars($tr['blood_type']) ?></td>
                        <td>
                            <?= number_format($tr['quantity_ml']) ?> ml
                            <?php if ($tr['status'] === 'requested' && isset($tr['available_quantity'])): ?>
                                <div class="small text-<?= $tr['available_quantity'] >= $tr['quantity_ml'] ? 'success' : 'danger' ?>">
                                    <?= number_format($tr['available_quantity']) ?> ml available
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= 
                                $tr['status'] === 'approved' ? 'success' : 
                                ($tr['status'] === 'rejected' ? 'danger' : 'warning') 
                            ?>">
                                <?= ucfirst($tr['status']) ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y', strtotime($tr['transfer_date'])) ?></td>
                        <td>
                            <?php if ($tr['status'] === 'requested'): ?>
                                <form method="post" class="d-inline" onsubmit="return confirm('Approve this transfer?');">
                                    <input type="hidden" name="transfer_id" value="<?= $tr['id'] ?>">
                                    <button type="submit" name="action" value="approve" 
                                        class="btn btn-sm btn-success" 
                                        <?= ($tr['available_quantity'] ?? 0) < $tr['quantity_ml'] ? 'disabled title="Not enough inventory"' : '' ?>>
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm btn-danger" 
                                    onclick="showRejectModal(<?= $tr['id'] ?>)">
                                    <i class="fas fa-times"></i>
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            <div class="text-muted">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <p>No transfer requests found</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <a href="index.php" class="btn btn-link mt-4"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Transfer Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="transfer_id" id="rejectTransferId">
                    <input type="hidden" name="action" value="reject">
                    <div class="mb-3">
                        <label for="rejectReason" class="form-label">Reason for Rejection</label>
                        <textarea class="form-control" id="rejectReason" name="reject_reason" rows="3" required></textarea>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showRejectModal(transferId) {
    document.getElementById('rejectTransferId').value = transferId;
    var modal = new bootstrap.Modal(document.getElementById('rejectModal'));
    modal.show();
}
</script>
</body>
</html>

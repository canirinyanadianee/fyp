<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "Blood Request Workflow";
include '../includes/header.php';
require_once '../includes/db.php';

// Fetch blood requests and their workflow status
$requests = $conn->query("SELECT r.id, r.hospital_id, h.name AS hospital_name, r.blood_type, r.quantity_ml, r.status, r.requested_at, r.supplied_at, r.used_at FROM blood_requests r JOIN hospitals h ON r.hospital_id = h.id ORDER BY r.requested_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);

?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Blood Request Workflow</h1>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Blood Requests</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Hospital</th>
                                <th>Blood Type</th>
                                <th>Quantity (ml)</th>
                                <th>Status</th>
                                <th>Requested At</th>
                                <th>Supplied At</th>
                                <th>Used At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $req): ?>
                            <tr>
                                <td><?php echo $req['id']; ?></td>
                                <td><?php echo htmlspecialchars($req['hospital_name']); ?></td>
                                <td><?php echo $req['blood_type']; ?></td>
                                <td><?php echo $req['quantity_ml']; ?></td>
                                <td><span class="badge bg-<?php echo $req['status'] === 'pending' ? 'warning' : ($req['status'] === 'supplied' ? 'info' : ($req['status'] === 'used' ? 'success' : 'secondary')); ?>"><?php echo ucfirst($req['status']); ?></span></td>
                                <td><?php echo $req['requested_at']; ?></td>
                                <td><?php echo $req['supplied_at'] ?? '--'; ?></td>
                                <td><?php echo $req['used_at'] ?? '--'; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>

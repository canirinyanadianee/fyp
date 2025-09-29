<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "System Monitoring";
include '../includes/header.php';
require_once '../includes/db.php';

// Fetch activity logs (example: last 50 activities)
$logs = $conn->query("SELECT * FROM activity_logs ORDER BY timestamp DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);

?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">System Monitoring</h1>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Activities</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo $log['timestamp']; ?></td>
                                <td><?php echo htmlspecialchars($log['username']); ?></td>
                                <td><?php echo ucfirst($log['role']); ?></td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['status']); ?></td>
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

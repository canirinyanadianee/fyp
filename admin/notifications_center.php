<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "Notifications & Alerts Center";
include '../includes/header.php';
require_once '../includes/db.php';

// Fetch recent notifications (last 50)
$notifications = $conn->query("SELECT * FROM ai_notifications ORDER BY created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);

?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Notifications & Alerts</h1>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent System Alerts</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Message</th>
                                <th>Urgency</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $note): ?>
                            <tr>
                                <td><?php echo $note['created_at']; ?></td>
                                <td><?php echo ucfirst($note['entity_type']); ?></td>
                                <td><?php echo htmlspecialchars($note['message']); ?></td>
                                <td><span class="badge bg-<?php echo $note['urgency_level'] === 'high' ? 'danger' : ($note['urgency_level'] === 'medium' ? 'warning' : 'info'); ?>"><?php echo ucfirst($note['urgency_level']); ?></span></td>
                                <td><span class="badge bg-<?php echo $note['status'] === 'pending' ? 'warning' : ($note['status'] === 'reviewed' ? 'info' : 'success'); ?>"><?php echo ucfirst($note['status']); ?></span></td>
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

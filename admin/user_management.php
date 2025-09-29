<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
$page_title = "User Management Panel";
include '../includes/header.php';
require_once '../includes/db.php';

// Fetch all users (donors, hospitals, blood banks)
$users = $conn->query("SELECT id, username, email, role, is_active FROM users ORDER BY role, username")->fetch_all(MYSQLI_ASSOC);

?>
<div class="container-fluid">
    <div class="row">
        <?php include 'includes/sidebar.php'; ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">User Management</h1>
            </div>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">All Users</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><?php echo ucfirst($user['role']); ?></td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Blocked</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="user_verify.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">Verify</a>
                                    <a href="user_block.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-danger">Block</a>
                                    <a href="user_update.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-secondary">Update</a>
                                </td>
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

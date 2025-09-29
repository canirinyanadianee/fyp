<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db.php';

$roles = ['admin','bloodbank','hospital','donor'];

// Detect schema differences
$hasIsActive = false;
if ($result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'")) {
    $hasIsActive = ($result->num_rows > 0);
    $result->free();
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if ($id <= 0 || $username === '' || $email === '' || !in_array($role, $roles, true)) {
        redirect('user_management.php', 'Invalid input.', 'danger');
    }

    // Uniqueness checks to avoid duplicate key errors
    $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
    $chk->bind_param('si', $email, $id);
    $chk->execute();
    $dup = $chk->get_result()->fetch_assoc();
    if ($dup) {
        redirect('user_update.php?id=' . $id, 'Email is already in use by another account.', 'warning');
    }
    $chk->close();

    $chk2 = $conn->prepare("SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1");
    $chk2->bind_param('si', $username, $id);
    $chk2->execute();
    $dup2 = $chk2->get_result()->fetch_assoc();
    if ($dup2) {
        redirect('user_update.php?id=' . $id, 'Username is already taken.', 'warning');
    }
    $chk2->close();

    if ($hasIsActive) {
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param('sssii', $username, $email, $role, $is_active, $id);
    } else {
        $status = $is_active ? 'active' : 'suspended';
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE id = ?");
        $stmt->bind_param('ssssi', $username, $email, $role, $status, $id);
    }

    if ($stmt && $stmt->execute()) {
        log_activity($_SESSION['user_id'] ?? 0, 'update_user', 'Updated user ID ' . $id);
        redirect('user_management.php', 'User updated successfully.', 'success');
    } else {
        $err = $stmt ? $stmt->error : $conn->error;
        redirect('user_update.php?id=' . $id, 'Failed to update user: ' . $err, 'danger');
    }
}

// GET: load and show form
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    redirect('user_management.php', 'Invalid user ID.', 'danger');
}

$select = $hasIsActive
    ? "SELECT id, username, email, role, is_active AS active_flag FROM users WHERE id = ?"
    : "SELECT id, username, email, role, (CASE WHEN status='active' THEN 1 ELSE 0 END) AS active_flag FROM users WHERE id = ?";

$stmt = $conn->prepare($select);
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
if (!$user) {
    redirect('user_management.php', 'User not found.', 'warning');
}

$page_title = "Update User | Admin";
include '../includes/header.php';
?>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Update User #<?php echo (int)$user['id']; ?></h5>
                </div>
                <div class="card-body">
                    <form method="post" action="user_update.php">
                        <input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>" />
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?php echo $r; ?>" <?php echo ($user['role'] === $r ? 'selected' : ''); ?>><?php echo ucfirst($r); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo ((int)$user['active_flag'] ? 'checked' : ''); ?> />
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                            <a href="user_management.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/admin_footer.php'; ?>

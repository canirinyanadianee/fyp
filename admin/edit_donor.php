<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();

function flash($key, $msg) { $_SESSION[$key] = $msg; }
function back_to_list() { header('Location: donors.php'); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { flash('error', 'Invalid donor ID.'); back_to_list(); }

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    $phone      = trim($_POST['phone'] ?? '');
    $blood_type = trim($_POST['blood_type'] ?? '');

    if ($first_name === '' || $last_name === '') {
        flash('error', 'First name and last name are required.');
        header('Location: edit_donor.php?id=' . $id); exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE donors
                                 SET first_name = :first_name,
                                     last_name = :last_name,
                                     phone = :phone,
                                     blood_type = :blood_type
                                 WHERE id = :id");
        $ok = $stmt->execute([
            ':first_name' => $first_name,
            ':last_name'  => $last_name,
            ':phone'      => $phone,
            ':blood_type' => $blood_type,
            ':id'         => $id,
        ]);
        flash($ok ? 'success' : 'error', $ok ? 'Donor updated successfully.' : 'Failed to update donor.');
    } catch (Exception $e) {
        flash('error', 'Error: ' . $e->getMessage());
    }
    back_to_list();
}

// GET: load current donor
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid donor ID.'); back_to_list(); }

$donor = fetchOne("SELECT d.*, u.email FROM donors d JOIN users u ON d.user_id = u.id WHERE d.id = :id", [':id' => $id]);
if (!$donor) { flash('error', 'Donor not found.'); back_to_list(); }

$page_title = 'Edit Donor | ' . APP_NAME;
include '../includes/header.php';
?>
<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Edit Donor #<?php echo (int)$donor['id']; ?></h5>
        </div>
        <div class="card-body">
          <form method="post" action="edit_donor.php">
            <input type="hidden" name="id" value="<?php echo (int)$donor['id']; ?>" />
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">First Name</label>
                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($donor['first_name'] ?? ''); ?>" required />
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Last Name</label>
                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($donor['last_name'] ?? ''); ?>" required />
              </div>
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Phone</label>
                <input type="text" class="form-control" name="phone" value="<?php echo htmlspecialchars($donor['phone'] ?? ''); ?>" />
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Blood Type</label>
                <select class="form-select" name="blood_type">
                  <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bt): ?>
                    <option value="<?php echo $bt; ?>" <?php echo (($donor['blood_type'] ?? '') === $bt ? 'selected' : ''); ?>><?php echo $bt; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">Save Changes</button>
              <a href="donors.php" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

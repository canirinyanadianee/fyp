<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();

// Helpers for flash messages via session
function flash($key, $msg) { $_SESSION[$key] = $msg; }
function back_to_list() { header('Location: bloodbank.php'); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { flash('error', 'Invalid blood bank ID.'); back_to_list(); }

    $name = trim($_POST['name'] ?? '');
    $license_number = trim($_POST['license_number'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');

    if ($name === '' || $license_number === '') {
        flash('error', 'Name and License Number are required.');
        header('Location: edit_bloodbank.php?id=' . $id); exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE blood_banks
                                 SET name = :name,
                                     license_number = :license_number,
                                     contact_phone = :contact_phone,
                                     contact_email = :contact_email,
                                     address = :address,
                                     city = :city,
                                     state = :state,
                                     postal_code = :postal_code
                                 WHERE id = :id");
        $ok = $stmt->execute([
            ':name' => $name,
            ':license_number' => $license_number,
            ':contact_phone' => $contact_phone,
            ':contact_email' => $contact_email,
            ':address' => $address,
            ':city' => $city,
            ':state' => $state,
            ':postal_code' => $postal_code,
            ':id' => $id,
        ]);
        if ($ok) {
            flash('success', 'Blood bank updated successfully.');
        } else {
            flash('error', 'Failed to update blood bank.');
        }
    } catch (Exception $e) {
        flash('error', 'Error: ' . $e->getMessage());
    }
    back_to_list();
}

// GET: load the record
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid blood bank ID.'); back_to_list(); }

$bank = fetchOne("SELECT * FROM blood_banks WHERE id = :id", [':id' => $id]);
if (!$bank) { flash('error', 'Blood bank not found.'); back_to_list(); }

$page_title = 'Edit Blood Bank | ' . APP_NAME;
include '../includes/header.php';
?>
<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Edit Blood Bank #<?php echo (int)$bank['id']; ?></h5>
        </div>
        <div class="card-body">
          <form method="post" action="edit_bloodbank.php">
            <input type="hidden" name="id" value="<?php echo (int)$bank['id']; ?>" />
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($bank['name'] ?? ''); ?>" required />
            </div>
            <div class="mb-3">
              <label class="form-label">License Number</label>
              <input type="text" class="form-control" name="license_number" value="<?php echo htmlspecialchars($bank['license_number'] ?? ''); ?>" required />
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Contact Phone</label>
                <input type="text" class="form-control" name="contact_phone" value="<?php echo htmlspecialchars($bank['contact_phone'] ?? ''); ?>" />
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Contact Email</label>
                <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($bank['contact_email'] ?? ''); ?>" />
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Address</label>
              <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($bank['address'] ?? ''); ?>" />
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">City</label>
                <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($bank['city'] ?? ''); ?>" />
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">State</label>
                <input type="text" class="form-control" name="state" value="<?php echo htmlspecialchars($bank['state'] ?? ''); ?>" />
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Postal Code</label>
                <input type="text" class="form-control" name="postal_code" value="<?php echo htmlspecialchars($bank['postal_code'] ?? ''); ?>" />
              </div>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">Save Changes</button>
              <a href="bloodbank.php" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

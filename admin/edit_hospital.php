<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
require_once '../includes/db_connect.php';
requireAdminAccess();

function flash($key, $msg) { $_SESSION[$key] = $msg; }
function back_to_list() { header('Location: hospitals.php'); exit; }

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { flash('error', 'Invalid hospital ID.'); back_to_list(); }

    $name = trim($_POST['name'] ?? '');
    $registration_number = trim($_POST['registration_number'] ?? '');
    $contact_phone = trim($_POST['contact_phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postal_code = trim($_POST['postal_code'] ?? '');

    if ($name === '' || $registration_number === '') {
        flash('error', 'Name and Registration Number are required.');
        header('Location: edit_hospital.php?id=' . $id); exit;
    }

    try {
        $stmt = $conn->prepare("UPDATE hospitals
                                 SET name = :name,
                                     registration_number = :registration_number,
                                     contact_phone = :contact_phone,
                                     contact_email = :contact_email,
                                     address = :address,
                                     city = :city,
                                     state = :state,
                                     postal_code = :postal_code
                                 WHERE id = :id");
        $ok = $stmt->execute([
            ':name' => $name,
            ':registration_number' => $registration_number,
            ':contact_phone' => $contact_phone,
            ':contact_email' => $contact_email,
            ':address' => $address,
            ':city' => $city,
            ':state' => $state,
            ':postal_code' => $postal_code,
            ':id' => $id,
        ]);
        flash($ok ? 'success' : 'error', $ok ? 'Hospital updated successfully.' : 'Failed to update hospital.');
    } catch (Exception $e) {
        flash('error', 'Error: ' . $e->getMessage());
    }
    back_to_list();
}

// GET: load hospital
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { flash('error', 'Invalid hospital ID.'); back_to_list(); }

$hospital = fetchOne("SELECT * FROM hospitals WHERE id = :id", [':id' => $id]);
if (!$hospital) { flash('error', 'Hospital not found.'); back_to_list(); }

$page_title = 'Edit Hospital | ' . APP_NAME;
include '../includes/header.php';
?>
<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Edit Hospital #<?php echo (int)$hospital['id']; ?></h5>
        </div>
        <div class="card-body">
          <form method="post" action="edit_hospital.php">
            <input type="hidden" name="id" value="<?php echo (int)$hospital['id']; ?>" />
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($hospital['name'] ?? ''); ?>" required />
            </div>
            <div class="mb-3">
              <label class="form-label">Registration Number</label>
              <input type="text" class="form-control" name="registration_number" value="<?php echo htmlspecialchars($hospital['registration_number'] ?? ''); ?>" required />
            </div>
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Contact Phone</label>
                <input type="text" class="form-control" name="contact_phone" value="<?php echo htmlspecialchars($hospital['contact_phone'] ?? ''); ?>" />
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Contact Email</label>
                <input type="email" class="form-control" name="contact_email" value="<?php echo htmlspecialchars($hospital['contact_email'] ?? ''); ?>" />
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Address</label>
              <input type="text" class="form-control" name="address" value="<?php echo htmlspecialchars($hospital['address'] ?? ''); ?>" />
            </div>
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">City</label>
                <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($hospital['city'] ?? ''); ?>" />
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">State</label>
                <input type="text" class="form-control" name="state" value="<?php echo htmlspecialchars($hospital['state'] ?? ''); ?>" />
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Postal Code</label>
                <input type="text" class="form-control" name="postal_code" value="<?php echo htmlspecialchars($hospital['postal_code'] ?? ''); ?>" />
              </div>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn btn-primary">Save Changes</button>
              <a href="hospitals.php" class="btn btn-secondary">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

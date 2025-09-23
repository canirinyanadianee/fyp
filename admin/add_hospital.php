<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php'; // $conn is PDO

$page_title = 'Add Hospital | ' . APP_NAME;

$errors = [];
$success = '';
$generatedPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');

    if ($name === '') $errors[] = 'Hospital name is required';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';

    try {
        // Check if email already exists in users
        $stmt = $conn->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'Email is already in use';
        }
    } catch (Exception $e) {
        $errors[] = 'Failed to validate email uniqueness';
    }

    if (empty($errors)) {
        try {
            $conn->beginTransaction();

            // Detect optional columns in users
            $hasIsActive = false; $hasStatus = false;
            try { $hasIsActive = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'")->rowCount() > 0; } catch (Exception $e) {}
            try { $hasStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'status'")->rowCount() > 0; } catch (Exception $e) {}

            // Create user account for hospital
            // Generate a random password and hash
            $generatedPassword = bin2hex(random_bytes(4)); // 8 hex chars
            $hash = password_hash($generatedPassword, PASSWORD_BCRYPT);

            $columns = ['email','password','role'];
            $placeholders = ['?','?','?'];
            $values = [$email, $hash, 'hospital'];
            if ($hasIsActive) { $columns[]='is_active'; $placeholders[]='?'; $values[] = 1; }
            if ($hasStatus) { $columns[]='status'; $placeholders[]='?'; $values[] = 'active'; }

            $sqlUser = 'INSERT INTO users (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
            $stmtUser = $conn->prepare($sqlUser);
            $stmtUser->execute($values);
            $userId = (int)$conn->lastInsertId();

            // Insert hospital
            $sqlHosp = 'INSERT INTO hospitals (user_id, name, phone, address, city, state) VALUES (?, ?, ?, ?, ?, ?)';
            $stmtHosp = $conn->prepare($sqlHosp);
            $stmtHosp->execute([$userId, $name, $phone, $address, $city, $state]);

            $conn->commit();
            $_SESSION['success'] = 'Hospital created successfully. A user account has been created for ' . htmlspecialchars($email) . '.';
            // Also show the generated password one time on the success page
            $_SESSION['info'] = 'Temporary password for the hospital user: ' . htmlspecialchars($generatedPassword) . ' (please share securely and have them change it after first login).';
            header('Location: hospitals.php');
            exit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $errors[] = 'Failed to create hospital: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>
<div class="container-fluid">
  <div class="row">
    <?php include 'includes/sidebar.php'; ?>
    <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0"><i class="fas fa-hospital me-2 text-primary"></i>Add New Hospital</h2>
        <a href="hospitals.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
          <ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <div class="card shadow-sm">
        <div class="card-body">
          <form method="post" class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Hospital Name</label>
              <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Address</label>
              <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">City</label>
              <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">State</label>
              <input type="text" name="state" class="form-control" value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>">
            </div>
            <div class="col-12 d-flex justify-content-end mt-3">
              <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Create Hospital</button>
            </div>
          </form>
        </div>
      </div>

      <div class="alert alert-info mt-3">
        <i class="fas fa-info-circle me-1"></i> A hospital user account will be created automatically. The temporary password will be shown after creation on the hospitals list page.
      </div>
    </main>
  </div>
</div>
<?php include '../includes/footer.php'; ?>

<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db_connect.php'; // $conn is PDO

$page_title = 'Add Blood Bank | ' . APP_NAME;
$errors = [];
$success = '';
$generatedPassword = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');

    if ($name === '') $errors[] = 'Blood bank name is required';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required';

    try {
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
            $username = $email ? $email : strtolower(str_replace(' ', '', $name)) . rand(100,999);
            $generatedPassword = bin2hex(random_bytes(4));
            $hash = password_hash($generatedPassword, PASSWORD_BCRYPT);
            $columns = ['username','email','password','role'];
            $placeholders = ['?','?','?','?'];
            $values = [$username, $email, $hash, 'bloodbank'];
            $hasIsActive = false; $hasStatus = false;
            try { $hasIsActive = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'")->rowCount() > 0; } catch (Exception $e) {}
            try { $hasStatus = $conn->query("SHOW COLUMNS FROM users LIKE 'status'")->rowCount() > 0; } catch (Exception $e) {}
            if ($hasIsActive) { $columns[]='is_active'; $placeholders[]='?'; $values[] = 1; }
            if ($hasStatus) { $columns[]='status'; $placeholders[]='?'; $values[] = 'active'; }
            $sqlUser = 'INSERT INTO users (' . implode(',', $columns) . ') VALUES (' . implode(',', $placeholders) . ')';
            $stmtUser = $conn->prepare($sqlUser);
            $stmtUser->execute($values);
            $userId = (int)$conn->lastInsertId();
            $sqlBB = 'INSERT INTO blood_banks (user_id, name, phone, address, city, state) VALUES (?, ?, ?, ?, ?, ?)';
            $stmtBB = $conn->prepare($sqlBB);
            $stmtBB->execute([$userId, $name, $phone, $address, $city, $state]);
            $conn->commit();
            $_SESSION['success'] = 'Blood bank created successfully. A user account has been created for ' . htmlspecialchars($email) . '.';
            $_SESSION['info'] = 'Temporary password for the blood bank user: ' . htmlspecialchars($generatedPassword) . ' (please share securely and have them change it after first login).';
            header('Location: bloodbank.php');
            exit();
        } catch (Exception $e) {
            if ($conn->inTransaction()) $conn->rollBack();
            $errors[] = 'Failed to create blood bank: ' . $e->getMessage();
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
        <h2 class="mb-0"><i class="fas fa-tint me-2 text-danger"></i>Add New Blood Bank</h2>
        <a href="bloodbank.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Back</a>
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
              <label for="name" class="form-label">Blood Bank Name</label>
              <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label for="email" class="form-label">Email</label>
              <input type="email" class="form-control" id="email" name="email" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label for="phone" class="form-label">Phone</label>
              <input type="text" class="form-control" id="phone" name="phone" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label for="address" class="form-label">Address</label>
              <input type="text" class="form-control" id="address" name="address" required value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label for="city" class="form-label">City</label>
              <input type="text" class="form-control" id="city" name="city" required value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>">
            </div>
            <div class="col-md-6">
              <label for="state" class="form-label">State</label>
              <input type="text" class="form-control" id="state" name="state" required value="<?php echo htmlspecialchars($_POST['state'] ?? ''); ?>">
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-primary">Create Blood Bank</button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>
</div>
<?php include '../includes/admin_footer.php'; ?>

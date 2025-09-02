<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Must be hospital
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Resolve hospital record
$hosp_q = "SELECT * FROM hospitals WHERE user_id = ? LIMIT 1";
$hosp_stmt = $conn->prepare($hosp_q);
$hosp_stmt->bind_param('i', $user_id);
$hosp_stmt->execute();
$hosp_res = $hosp_stmt->get_result();
$hospital = $hosp_res->fetch_assoc();

// Also load user record
$u_q = "SELECT * FROM users WHERE id = ? LIMIT 1";
$u_stmt = $conn->prepare($u_q);
$u_stmt->bind_param('i', $user_id);
$u_stmt->execute();
$u_res = $u_stmt->get_result();
$user = $u_res->fetch_assoc();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $beds = intval($_POST['beds'] ?? 0);

    if ($name === '') $errors[] = 'Name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email required.';

    if (empty($errors)) {
        // Check username uniqueness
        $check_u = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ? LIMIT 1");
        $check_u->bind_param('si', $name, $user_id);
        $check_u->execute();
        $check_u_res = $check_u->get_result();
        if ($check_u_res && $check_u_res->fetch_assoc()) {
            $errors[] = 'This username is already taken. Please choose another.';
        }

        // Check email uniqueness
        $check_e = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $check_e->bind_param('si', $email, $user_id);
        $check_e->execute();
        $check_e_res = $check_e->get_result();
        if ($check_e_res && $check_e_res->fetch_assoc()) {
            $errors[] = 'This email is already in use. Please use a different email.';
        }

        // If still no errors, perform updates
        if (empty($errors)) {
            // Update users table
            $up_u = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE id = ?");
            $up_u->bind_param('ssi', $name, $email, $user_id);
            $up_u->execute();

            if ($hospital) {
                $up_h = $conn->prepare("UPDATE hospitals SET name = ?, phone = ?, location = ?, beds = ? WHERE user_id = ?");
                $up_h->bind_param('sssii', $name, $phone, $location, $beds, $user_id);
                $up_h->execute();
            } else {
                // create hospital record if missing
                $ins = $conn->prepare("INSERT INTO hospitals (user_id, name, phone, location, beds) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param('isssi', $user_id, $name, $phone, $location, $beds);
                $ins->execute();
            }

            header('Location: index.php');
            exit();
        }
    }
}

?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Hospital Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>.container{max-width:680px;padding-top:24px}</style>
</head>
<body>
<div class="container">
    <h3>Edit Hospital Profile</h3>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="post">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input name="name" class="form-control" value="<?php echo htmlspecialchars($hospital['name'] ?? $user['username'] ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Phone</label>
            <input name="phone" class="form-control" value="<?php echo htmlspecialchars($hospital['phone'] ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Location</label>
            <input name="location" class="form-control" value="<?php echo htmlspecialchars($hospital['location'] ?? ''); ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Beds</label>
            <input name="beds" type="number" class="form-control" value="<?php echo htmlspecialchars($hospital['beds'] ?? 0); ?>">
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary">Save</button>
            <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
</body>
</html>

<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bloodbank') {
    header('Location: ../login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Example: allow changing notification email
    $notification_email = trim($_POST['notification_email'] ?? '');
    if (!filter_var($notification_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid notification email.';
    } else {
        $stmt = $conn->prepare("UPDATE blood_banks SET notification_email=? WHERE user_id=?");
        $stmt->bind_param('si', $notification_email, $user_id);
        if ($stmt->execute()) {
            $success = 'Settings updated successfully.';
            $bank['notification_email'] = $notification_email;
        } else {
            $errors[] = 'Failed to update settings.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Blood Bank Settings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-cog text-secondary me-2"></i>Blood Bank Settings</h2>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    <?php if ($errors): ?>
        <div class="alert alert-danger"><ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>
    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label for="notification_email" class="form-label">Notification Email</label>
            <input type="email" class="form-control" id="notification_email" name="notification_email" value="<?php echo htmlspecialchars($bank['notification_email'] ?? ''); ?>">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Save Settings</button>
            <a href="index.php" class="btn btn-link">Cancel</a>
        </div>
    </form>
</div>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</body>
</html>

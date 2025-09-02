<?php
// Blood Bank Profile Page
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
$user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - <?php echo htmlspecialchars($bank['name'] ?? ''); ?> Blood Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-user text-secondary me-2"></i>Blood Bank Profile</h2>
    <table class="table table-bordered">
        <tr><th>Name</th><td><?php echo htmlspecialchars($bank['name'] ?? ''); ?></td></tr>
        <tr><th>License #</th><td><?php echo htmlspecialchars($bank['license_number'] ?? ''); ?></td></tr>
        <tr><th>Address</th><td><?php echo htmlspecialchars($bank['address'] ?? ''); ?></td></tr>
        <tr><th>City</th><td><?php echo htmlspecialchars($bank['city'] ?? ''); ?></td></tr>
        <tr><th>State</th><td><?php echo htmlspecialchars($bank['state'] ?? ''); ?></td></tr>
        <tr><th>Postal Code</th><td><?php echo htmlspecialchars($bank['postal_code'] ?? ''); ?></td></tr>
        <tr><th>Phone</th><td><?php echo htmlspecialchars($bank['phone'] ?? ''); ?></td></tr>
        <tr><th>Contact Email</th><td><?php echo htmlspecialchars($bank['contact_email'] ?? ''); ?></td></tr>
        <tr><th>Login Email</th><td><?php echo htmlspecialchars($user['email'] ?? ''); ?></td></tr>
    </table>
    <a href="index.php" class="btn btn-link mt-4"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</body>
</html>

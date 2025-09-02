<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bloodbank') {
    header('Location: ../login.php');
    exit();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $message = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $message = 'New passwords do not match.';
    } else {
        $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stmt->bind_result($hashed_password);
    $stmt->fetch();
    $stmt->close();

        if (!password_verify($current_password, $hashed_password)) {
            $message = 'Current password is incorrect.';
        } else {
            $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $new_hashed, $user_id);
            if ($stmt->execute()) {
                $message = 'Password changed successfully.';
            } else {
                $message = 'Error updating password.';
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Password</title>
    <style>
        body { font-family: Arial, sans-serif; }
        .container { max-width: 400px; margin: 50px auto; padding: 20px; border: 1px solid #ccc; }
        .message { color: red; }
    </style>
</head>
<body>
<div class="container">
    <h2>Change Password</h2>
    <?php if ($message): ?>
        <p class="message"><?php echo htmlspecialchars($message); ?></p>
    <?php endif; ?>
    <form method="post" action="">
        <label>Current Password:</label><br>
        <input type="password" name="current_password" required><br><br>
        <label>New Password:</label><br>
        <input type="password" name="new_password" required><br><br>
        <label>Confirm New Password:</label><br>
        <input type="password" name="confirm_password" required><br><br>
        <button type="submit">Change Password</button>
    </form>
</div>
</body>
</html>
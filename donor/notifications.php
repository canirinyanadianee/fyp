<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
	header('Location: ../login.php');
	exit();
}
$user_id = $_SESSION['user_id'];
$donor = $conn->query("SELECT * FROM donors WHERE user_id = $user_id")->fetch_assoc();
$donor_id = $donor ? $donor['id'] : 0;
$notifications = $conn->query("SELECT * FROM ai_notifications WHERE entity_type = 'donor' AND entity_id = $donor_id ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang='en'>
<head>
	<meta charset='UTF-8'>
	<meta name='viewport' content='width=device-width, initial-scale=1.0'>
	<title>Notifications</title>
	<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
	<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="#">AI Blood Management System | Donor</a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
                <li class="nav-item"><a class="nav-link" href="appointments.php"><i class="fas fa-calendar-alt me-1"></i>Appointments</a></li>
                <li class="nav-item"><a class="nav-link" href="donation_history.php"><i class="fas fa-history me-1"></i>Donation History</a></li>
                <li class="nav-item"><a class="nav-link" href="rewards.php"><i class="fas fa-award me-1"></i>Rewards</a></li>
                <li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($donor['first_name'] ?? $_SESSION['username']); ?></a></li>
                <li class="nav-item"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-1 text-danger"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</nav>
<div class='container py-5'>
	<h2 class='mb-4 text-primary'>My Notifications</h2>
	<ul class='list-group'>
		<?php if ($notifications && $notifications->num_rows > 0):
			while($n = $notifications->fetch_assoc()): ?>
			<li class='list-group-item d-flex justify-content-between align-items-center'>
				<?php echo htmlspecialchars($n['message']); ?>
				<span class='badge bg-secondary'><?php echo date('M d, Y', strtotime($n['created_at'])); ?></span>
			</li>
		<?php endwhile; else: ?>
			<li class='list-group-item text-center'>No notifications yet.</li>
		<?php endif; ?>
	</ul>
	<a href='index.php' class='btn btn-secondary mt-3'>Back to Dashboard</a>
</div>
</body>
</html>

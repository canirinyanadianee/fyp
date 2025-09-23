<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
	echo '<div style="margin:2em; padding:2em; border:1px solid #ccc; text-align:center;">'
		. 'You are not logged in. <a href="../login.php">Click here to login</a>.'
		. '</div>';
	exit();
}
$user_id = $_SESSION['user_id'];
$donor = $conn->query("SELECT * FROM donors WHERE user_id = $user_id")->fetch_assoc();
$donor_id = $donor ? $donor['id'] : 0;
$appointments = $conn->query("SELECT a.*, b.name as blood_bank FROM appointments a JOIN blood_banks b ON a.blood_bank_id = b.id WHERE a.donor_id = $donor_id ORDER BY a.appointment_date DESC");
?>
<!DOCTYPE html>
<html lang='en'>
<head>
	<meta charset='UTF-8'>
	<meta name='viewport' content='width=device-width, initial-scale=1.0'>
	<title>My Appointments</title>
	<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css' rel='stylesheet'>

	<style>
		body {
			background: #f7f9fc;
			font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
		}
		.container {
			background: #fff;
			border-radius: 12px;
			box-shadow: 0px 4px 15px rgba(0,0,0,0.1);
			padding: 2rem;
		}
		h2 {
			font-weight: 600;
			text-align: center;
			margin-bottom: 2rem;
			color: #2c3e50;
		}
		.table {
			border-radius: 10px;
			overflow: hidden;
		}
		.table th {
			background: #34495e !important;
			color: #fff !important;
			text-align: center;
		}
		.table td {
			vertical-align: middle;
			text-align: center;
		}
		.badge {
			font-size: 0.9rem;
			padding: 0.6em 1em;
			border-radius: 20px;
		}
		.btn-secondary {
			border-radius: 25px;
			padding: 0.6em 1.5em;
			transition: 0.3s;
		}
		.btn-secondary:hover {
			background: #2c3e50;
			color: #fff;
			transform: translateY(-2px);
			box-shadow: 0px 3px 10px rgba(0,0,0,0.15);
		}
		.table tbody tr:hover {
			background: #f2f6fa;
			transition: background 0.3s;
		}
	</style>
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
	
<div class='container my-5'>
	  
	<h2>🩸 My Appointments</h2>
	<table class='table table-bordered'>
		<thead>
			<tr>
				<th>Date & Time</th>
				<th>Blood Bank</th>
				<th>Status</th>
				<th>Reason</th>
				<th>Actions</th>
			</tr>
		</thead>
		<tbody>
		<?php if ($appointments && $appointments->num_rows > 0):
			while($row = $appointments->fetch_assoc()): ?>
			<tr>
				<td><?php echo date('M d, Y H:i', strtotime($row['appointment_date'])); ?></td>
				<td><?php echo htmlspecialchars($row['blood_bank']); ?></td>
				<td><span class='badge <?php echo ($row['status']==='confirmed'||$row['status']==='approved'||$row['status']==='completed') ? 'bg-success' : (($row['status']==='rejected') ? 'bg-danger' : (($row['status']==='cancelled') ? 'bg-secondary' : 'bg-warning text-dark')); ?>'>
					<?php echo ucfirst($row['status']); ?>
				</span></td>
				<td class="text-muted small">
					<?php echo htmlspecialchars($row['decision_reason'] ?? $row['notes'] ?? ''); ?>
				</td>
				<td>
					<a href="view_appointment.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">View</a>
				</td>
			</tr>
		<?php endwhile; else: ?>
			<tr><td colspan='5' class='text-center text-muted'>No appointments found.</td></tr>
		<?php endif; ?>
		</tbody>
	</table>
	<div class="text-center">
		<a href='index.php' class='btn btn-secondary mt-3'>⬅ Back to Dashboard</a>
	</div>
</div>
</body>
</html>

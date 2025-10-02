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
if ($donor) {
	$donor_id = $donor['id'];
	$donations = $conn->query("SELECT d.*, b.name as blood_bank 
		FROM blood_donations d 
		JOIN blood_banks b ON d.blood_bank_id = b.id 
		WHERE d.donor_id = $donor_id 
		ORDER BY d.donation_date DESC");
} else {
	$donor_id = 0;
	$donations = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Donation History</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
	<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
	<style>
		body {
			background: #f4f6f9;
		}
		.card {
			border-radius: 1rem;
			box-shadow: 0 4px 12px rgba(0,0,0,0.08);
		}
		.table {
			border-radius: 0.75rem;
			overflow: hidden;
		}
		.table thead {
			background: #667eea;
			color: white;
		}
		.badge {
			font-size: 0.85rem;
		}
		.hero-header {
			background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
			color: white;
			padding: 2px;
			border-radius: 1rem;
			margin-bottom: 1rem;
			text-align: center;
		}
	</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="#"> Donation history| Donor</a>
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

<div class="container py-4">
	<!-- Hero Header -->
	<div class="hero-header">
		<h1 class="fw-bold"><i class="fas fa-hand-holding-medical me-2"></i> My Donation History</h1>
		<p class="lead">Track your past donations and their status</p>
	</div>

	<!-- Donation History Card -->
	<div class="card p-4">
		<div class="table-responsive">
			<table class="table table-striped align-middle">
				<thead>
					<tr>
						<th><i class="fas fa-calendar-day me-2"></i>Date</th>
						<th><i class="fas fa-hospital me-2"></i>Blood Bank</th>
						<th><i class="fas fa-tint me-2"></i>Blood Type</th>
					
						
					</tr>
				</thead>
				<tbody>
				<?php if ($donations && $donations->num_rows > 0): 
					while($row = $donations->fetch_assoc()): ?>
					<tr>
						<td><?php echo date('M d, Y', strtotime($row['donation_date'])); ?></td>
						<td><?php echo htmlspecialchars($row['blood_bank']); ?></td>
						<td><span class="fw-bold text-danger"><?php echo htmlspecialchars($row['blood_type']); ?></span></td>
						<td>
							<?php if ($row['status'] === 'pending'): ?>
								<form method="post" action="update_donation.php" class="d-flex align-items-center gap-2">
									<input type="hidden" name="donation_id" value="<?php echo $row['id']; ?>">
									
									<button type="submit" class="btn btn-sm btn-success">
										<i class="fas fa-check"></i> Complete
									</button>
								</form>
							<?php else: ?>
								
							<?php endif; ?>
						</td>
					
					</tr>
				<?php endwhile; else: ?>
					<tr>
						<td colspan="5" class="text-center text-muted">
							<i class="fas fa-info-circle me-2"></i>No donations yet.
						</td>
					</tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</div>

	<!-- Back Button -->
	<div class="text-center mt-4">
		<a href="index.php" class="btn btn-outline-primary px-4">
			<i class="fas fa-arrow-left me-2"></i> Back to Dashboard
		</a>
	</div>
</div>

</body>
</html>
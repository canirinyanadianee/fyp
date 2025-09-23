<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/donor_header.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
		header('Location: ../login.php');
		exit();
}
$user_id = $_SESSION['user_id'];
$donor = $conn->query("SELECT * FROM donors WHERE user_id = $user_id")->fetch_assoc();
$donor_id = $donor ? $donor['id'] : 0;
$total_donations = $conn->query("SELECT COUNT(*) as total FROM blood_donations WHERE donor_id = $donor_id")->fetch_assoc()['total'] ?? 0;
$total_ml = $conn->query("SELECT SUM(quantity_ml) as total_ml FROM blood_donations WHERE donor_id = $donor_id")->fetch_assoc()['total_ml'] ?? 0;
$lives_saved = ceil($total_ml / 450); // Assume 1 donation (450ml) saves 1 life
$donation_streak = $conn->query("SELECT COUNT(*) as streak FROM blood_donations WHERE donor_id = $donor_id AND donation_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)")->fetch_assoc()['streak'] ?? 0;
$last_donation = $conn->query("SELECT MAX(donation_date) as last FROM blood_donations WHERE donor_id = $donor_id")->fetch_assoc()['last'] ?? null;
$notifications = $conn->query("SELECT * FROM ai_notifications WHERE entity_type = 'donor' AND entity_id = $donor_id ORDER BY created_at DESC LIMIT 3");
$appointments = $conn->query("SELECT a.*, b.name as blood_bank, b.address FROM appointments a JOIN blood_banks b ON a.blood_bank_id = b.id WHERE a.donor_id = $donor_id ORDER BY a.appointment_date DESC LIMIT 2");
$recent_donations = $conn->query("SELECT d.*, b.name as blood_bank FROM blood_donations d JOIN blood_banks b ON d.blood_bank_id = b.id WHERE d.donor_id = $donor_id ORDER BY d.donation_date DESC LIMIT 2");
?>
<!DOCTYPE html>
<html lang="en">
<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>AI Blood Management System | Donor Dashboard</title>
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
		<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
		<style>
				body { background:rgb(129, 124, 136);
					background-image: url('4.jpg');}
				.sidebar { min-height: 100vh; background: #c6c6c6ff; border-right: 1px solid #e5e7eb; }
				.sidebar .nav-link { color: #333; font-weight: 500; }
				.sidebar .nav-link.active, .sidebar .nav-link:hover { background: #8e979fff; color: #0d6efd; }
				.dashboard-card { border-radius: 1rem;background: #023907ad; color: #f2f0f0ff;}
				.quick-action-btn { border-radius: 0.75rem; font-weight: 500;background: #023907ad; color: #f2f0f0ff; }
				.profile-card { border-radius: 1rem; background: #023907ad; color: #f2f0f0ff;}
				.notification-icon { font-size: 1.5rem;background: #023907ad; color: #f2f0f0ff; }
				.donor-avatar { width: 60px; height: 60px; border-radius: 50%; background: #18232dff; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #6c757d; }
		
		/* Sidebar Styling */
.sidebar {
  background: #ffffff;
  border-right: 1px solid #e5e5e5;
  min-height: 100vh;
  padding-top: 1rem;
}

.sidebar .nav-link {
  color: #555;
  font-weight: 500;
  border-radius: 8px;
  padding: 10px 15px;
  margin: 4px 8px;
  display: flex;
  align-items: center;
  transition: all 0.2s ease;
}

.sidebar .nav-link i {
  color: #dc3545; /* red accent for icons */
  width: 20px;
  text-align: center;
}

.sidebar .nav-link:hover {
  background: rgba(220, 53, 69, 0.1);
  color: #dc3545;
  transform: translateX(4px);
}

.sidebar .nav-link.active {
  background: linear-gradient(135deg, #dc3545, #e4606d);
  color: #fff !important;
  font-weight: 600;
  box-shadow: 0 3px 6px rgba(220, 53, 69, 0.2);
}

.sidebar .nav-link.active i {
  color: #fff !important;
}

.eligibility-card {
  background: #f8fdf9;
  border: 1px solid #d1e7dd;
  color: #198754;
}
		.navbar {
  transition: all 0.3s ease-in-out;
  background: #000000ff;
}

.nav-link {
  transition: color 0.3s ease, transform 0.2s ease;
}

.nav-link:hover {
  color: #dc3545 !important; /* Red highlight on hover */
  transform: translateY(-2px);
}

.navbar-brand {
  font-size: 1.2rem;
}

.nav-item .active {
  border-bottom: 2px solid #0d6efd;
}

		</style>
</head>
<body>
<!-- Top Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
	<div class="container-fluid">
		<a class="navbar-brand fw-bold text-primary" href="#">AI Blood Management System | Donor</a>
		<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
			<span class="navbar-toggler-icon"></span>
		</button>
		<div class="collapse navbar-collapse" id="navbarNav">
			<ul class="navbar-nav ms-auto align-items-center">
				<li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
				<li class="nav-item"><a class="nav-link" href="appointments.php"><i class="fas fa-calendar-alt me-1"></i>Appointments</a></li>
				<li class="nav-item"><a class="nav-link" href="view_appointment.php"><i  class="fas fa-history me-1"></i>View Appointments</a></li>
				<li class="nav-item"><a class="nav-link" href="donation_history.php"><i class="fas fa-history me-1"></i>Donation History</a></li>
				<li class="nav-item"><a class="nav-link" href="rewards.php"><i class="fas fa-award me-1"></i>Rewards</a></li>
				<li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($donor['first_name'] ?? $_SESSION['username']); ?></a></li>
				<li class="nav-item"><a class="nav-link" href="../logout.php"><i class="fas fa-sign-out-alt me-1 text-danger"></i>Logout</a></li>
			</ul>
		</div>
	</div>
</nav>
<div class="container-fluid">
	<div class="row">
		<!-- Sidebar -->
		<nav class="col-md-2 d-none d-md-block sidebar py-4">
			<div class="position-sticky">
				<ul class="nav flex-column mb-4">
					<li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
					<li class="nav-item"><a class="nav-link" href="appointments.php"><i class="fas fa-calendar-alt me-2"></i>Appointments</a></li>
					<li class="nav-item"><a class="nav-link" href="book_appointment.php"><i class="fas fa-plus-circle me-2"></i>Book Appointment</a></li>
					<li class="nav-item"><a class="nav-link" href="donation_history.php"><i class="fas fa-history me-2"></i>Donation History</a></li>
					<li class="nav-item"><a class="nav-link" href="bloodbanks.php"><i class="fas fa-university me-2"></i>Blood Banks</a></li>
					<li class="nav-item"><a class="nav-link" href="eligibility_check.php"><i class="fas fa-check-circle me-2"></i>Eligibility Check</a></li>
					<li class="nav-item"><a class="nav-link" href="rewards.php"><i class="fas fa-award me-2"></i>Rewards & Badges</a></li>
					<li class="nav-item"><a class="nav-link" href="health_info.php"><i class="fas fa-notes-medical me-2"></i>Health Information</a></li>
					<li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
				</ul>
				<div class="card mb-3 p-3 text-center bg-light">
					<div class="mb-2 text-success"><i class="fas fa-check-circle fa-2x"></i></div>
					<div class="fw-bold">Next Eligible Date</div>
					<div class="text-success">You are eligible now!</div>
				</div>
			</div>
		</nav>
		<!-- Main Content -->
		<main class="col-md-10 ms-sm-auto px-md-4 py-4">
			<div class="row g-4 mb-4">
				<div class="col-md-6">
					<div class="card dashboard-card p-4 text-center">
						<div class="mb-3 text-success" style="font-size:2.5rem;"><i class="fas fa-check-circle"></i></div>
						<h5 class="fw-bold">Donation Eligibility</h5>
						<div class="fs-5 mb-2">You are eligible to donate!</div>
						<div class="text-muted">You are currently eligible to donate blood.</div>
						<a href="eligibility_guidelines.php" class="btn btn-outline-primary btn-sm mt-3">Eligibility Guidelines</a>
					</div>
				</div>
				<div class="col-md-6">
					<div class="card dashboard-card p-4 text-center">
						<h5 class="fw-bold mb-3">Your Donation Stats</h5>
						<div class="row">
							<div class="col-6">
								<div class="fs-4 fw-bold text-primary"><?php echo $total_donations; ?></div>
								<div class="small">Total Donations</div>
							</div>
							<div class="col-6">
								<div class="fs-4 fw-bold text-danger"><?php echo number_format($total_ml); ?> ml</div>
								<div class="small">Blood Donated</div>
							</div>
						</div>
						<div class="row mt-3">
							<div class="col-6">
								<div class="fs-5 text-success"><?php echo $lives_saved; ?></div>
								<div class="small">Lives Saved</div>
							</div>
							<div class="col-6">
								<div class="fs-5 text-warning"><?php echo $donation_streak; ?></div>
								<div class="small">Donation Streak</div>
							</div>
						</div>
						<a href="donation_history.php" class="btn btn-outline-success btn-sm mt-3">View Full History</a>
					</div>
				</div>
			</div>
			<div class="row g-4 mb-4">
				<div class="col-md-8">
					<div class="card p-4">
						<h5 class="fw-bold mb-3">Upcoming Appointments</h5>
						<table class="table table-bordered mb-0">
							<thead class="table-light">
								<tr><th>Date & Time</th><th>Blood Bank</th><th>Address</th><th>Status</th><th>Actions</th></tr>
							</thead>
							<tbody>
								<?php if ($appointments && $appointments->num_rows > 0):
									while($row = $appointments->fetch_assoc()): ?>
									<tr>
										<td><?php echo date('M d, Y H:i', strtotime($row['appointment_date'])); ?></td>
										<td><?php echo htmlspecialchars($row['blood_bank']); ?></td>
										<td><?php echo htmlspecialchars($row['address']); ?></td>
										<td><span class="badge bg-<?php echo $row['status'] === 'confirmed' ? 'success' : 'warning'; ?>"><?php echo ucfirst($row['status']); ?></span></td>
										<td>
											<a href="#" class="btn btn-outline-info btn-sm" title="View"><i class="fas fa-eye"></i></a>
											<a href="#" class="btn btn-outline-danger btn-sm" title="Cancel"><i class="fas fa-times"></i></a>
										</td>
									</tr>
								<?php endwhile; else: ?>
									<tr><td colspan="5" class="text-center">No upcoming appointments.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
						<a href="book_appointment.php" class="btn btn-primary mt-3">+ Book Appointment</a>
					</div>
				</div>
				<div class="col-md-4">
					<div class="card p-4 mb-4">
						<div class="d-flex align-items-center mb-3">
							<div class="donor-avatar me-3"><i class="fas fa-user"></i></div>
							<div>
								<div class="fw-bold"><?php echo htmlspecialchars($donor['first_name'] ?? $_SESSION['username']); ?></div>
								<div class="text-muted small"><?php echo htmlspecialchars($donor['blood_type'] ?? ''); ?> Blood Type</div>
							</div>
						</div>
						<a href="profile.php" class="btn btn-outline-primary btn-sm w-100 mb-2">Edit Profile</a>
						<div class="card mt-3">
							<div class="card-header bg-warning text-dark">Notifications</div>
							<div class="card-body p-2">
								<?php if ($notifications && $notifications->num_rows > 0): ?>
									<ul class="list-group list-group-flush">
									<?php while($n = $notifications->fetch_assoc()): ?>
										<li class="list-group-item d-flex justify-content-between align-items-center">
											<?php echo htmlspecialchars($n['message']); ?>
											<span class="badge bg-secondary"><?php echo date('M d, Y', strtotime($n['created_at'])); ?></span>
										</li>
									<?php endwhile; ?>
									</ul>
								<?php else: ?>
									<div class="text-center text-muted"><i class="fas fa-bell-slash fa-lg"></i><br>No notifications</div>
								<?php endif; ?>
								<a href="notifications.php" class="btn btn-link btn-sm w-100 mt-2">View All</a>
							</div>
						</div>
					</div>
					<div class="card p-4">
						<h6 class="fw-bold mb-2">Blood Demand in Your Area</h6>
						<div class="mb-2">Blood Type Demand Levels</div>
						<div class="mb-2"><span class="fw-bold">A+</span> <span class="badge bg-danger">High</span></div>
						<div class="mb-2"><span class="fw-bold">A-</span> <span class="badge bg-warning text-dark">Medium</span></div>
						<div class="mb-2"><span class="fw-bold">B+</span> <span class="badge bg-warning text-dark">Medium</span></div>
						<div class="mb-2"><span class="fw-bold">B-</span> <span class="badge bg-success">Low</span></div>
						<div class="mb-2"><span class="fw-bold">AB+</span> <span class="badge bg-success">Low</span></div>
						<div class="mb-2"><span class="fw-bold">AB-</span> <span class="badge bg-success">Low</span></div>
						<div class="mb-2"><span class="fw-bold">O+</span> <span class="badge bg-danger">High</span></div>
						<div class="mb-2"><span class="fw-bold">O-</span> <span class="badge bg-warning text-dark">Medium</span></div>
						<div class="alert alert-info mt-3 p-2 small"><i class="fas fa-tint me-1"></i> Your <b><?php echo htmlspecialchars($donor['blood_type'] ?? ''); ?></b> blood is currently well-stocked in your area.</div>
						<a href="nearby_bloodbanks.php" class="btn btn-outline-primary btn-sm w-100 mt-2"><i class="fas fa-search-location me-1"></i>Find Nearby Blood Banks</a>
					</div>
				</div>
			</div>
			<div class="row g-4 mb-4">
				<div class="col-md-6">
					<div class="card p-4">
						<h5 class="fw-bold mb-3">AI Blood Donation Insights</h5>
						<div class="row">
							<div class="col-6">
								<div class="mb-2">Your Blood Type Impact</div>
								<div class="alert alert-warning p-2">Your <?php echo htmlspecialchars($donor['blood_type'] ?? ''); ?> blood is currently in Moderate demand. Donations can help save lives!<br><span class="fw-bold">Current Demand</span>: <span class="badge bg-warning text-dark">Moderate Demand</span></div>
							</div>
							<div class="col-6">
								<div class="mb-2">Suggested Donation Times</div>
								<div class="alert alert-info p-2">Our AI suggests the following optimal donation times based on your profile and local needs:<br>
									<ul class="mb-0 ps-3">
										<li>Wednesday Morning</li>
										<li>Saturday Afternoon</li>
										<li>Monday Evening</li>
									</ul>
								</div>
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-6">
					<div class="card p-4">
						<h5 class="fw-bold mb-3">Recent Donations</h5>
						<table class="table table-bordered mb-0">
							<thead class="table-light">
								<tr><th>Date</th><th>Blood Bank</th><th>Quantity</th><th>Certificate</th></tr>
							</thead>
							<tbody>
								<?php if ($recent_donations && $recent_donations->num_rows > 0):
									while($row = $recent_donations->fetch_assoc()): ?>
									<tr>
										<td><?php echo date('M d, Y', strtotime($row['donation_date'])); ?></td>
										<td><?php echo htmlspecialchars($row['blood_bank']); ?></td>
										<td><?php echo $row['quantity_ml']; ?> ml</td>
										<td>N/A</td>
									</tr>
								<?php endwhile; else: ?>
									<tr><td colspan="4" class="text-center">No recent donations.</td></tr>
								<?php endif; ?>
							</tbody>
						</table>
						<a href="donation_history.php" class="btn btn-link btn-sm w-100 mt-2">View All</a>
					</div>
				</div>
			</div>
			<div class="row g-4 mb-4">
				<div class="col-md-12">
					<div class="card p-4">
						<h5 class="fw-bold mb-3">Quick Actions</h5>
						<div class="row g-2">
							<div class="col-md-3 col-6"><a href="book_appointment.php" class="btn btn-primary quick-action-btn w-100"><i class="fas fa-calendar-plus me-2"></i>Book Donation Appointment</a></div>
							<div class="col-md-3 col-6"><a href="eligibility_check.php" class="btn btn-info quick-action-btn w-100"><i class="fas fa-check-circle me-2"></i>Check Eligibility</a></div>
							<div class="col-md-3 col-6"><a href="donation_history.php" class="btn btn-secondary quick-action-btn w-100"><i class="fas fa-history me-2"></i>View Donation History</a></div>
							<div class="col-md-3 col-6"><a href="health_info.php" class="btn btn-warning quick-action-btn w-100"><i class="fas fa-notes-medical me-2"></i>Update Health Info</a></div>
						</div>
					</div>
				</div>
			</div>
		</main>
	</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once '../includes/donor_footer.php'; ?>

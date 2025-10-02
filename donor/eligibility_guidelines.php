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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Eligibility Guidelines</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

  <style>
    body {
      background: #f8f9fa;
    }
    .guidelines-card {
      border-radius: 15px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08);
      padding: 2rem;
      background: #fff;
      margin-top: 2rem;
    }
    h4, h5 {
      color: #0d6efd;
      font-weight: 600;
    }
    ul li {
      margin-bottom: 0.5rem;
    }
    .section-icon {
      color: #0d6efd;
      margin-right: 8px;
    }
    .btn-primary {
      border-radius: 30px;
      padding: 10px 20px;
    }
    .btn-link {
      text-decoration: none;
    }
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
  <div class="container-fluid">
   
    <div class="collapse navbar-collapse" id="navbarNav">
    <a class="navbar-brand fw-bold text-primary" href="#">  Donation</a>
      <ul class="navbar-nav ms-auto align-items-center">
     
        <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home me-1"></i>Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="appointments.php"><i class="fas fa-calendar-alt me-1"></i>Appointments</a></li>
        <li class="nav-item"><a class="nav-link" href="donation_history.php"><i class="fas fa-history me-1"></i>Donation History</a></li>
        <li class="nav-item"><a class="nav-link" href="rewards.php"><i class="fas fa-award me-1"></i>Rewards</a></li>
        <li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user me-1"></i><?php echo htmlspecialchars($donor['first_name'] ?? $_SESSION['username']); ?></a></li>
        <li class="nav-item"><a class="nav-link text-danger" href="../logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container">
  <div class="guidelines-card">
    <h4 class="mb-3"><i class="fas fa-heartbeat section-icon"></i>Eligibility Guidelines</h4>
    <p class="text-muted">These are general guidelines. Final eligibility is determined by the blood bank staff at donation time.</p>

    <h5><i class="fas fa-user-check section-icon"></i>Basic Requirements</h5>
    <ul>
      <li>Age: Typically between 18 and 65 years (varies by country and local rules).</li>
      <li>Weight: Usually at least 50 kg (110 lbs) — check local requirements.</li>
      <li>Identification: Bring a valid ID to the donation site.</li>
    </ul>

    <h5><i class="fas fa-stethoscope section-icon"></i>Health & Medical Considerations</h5>
    <ul>
      <li>No recent fever or active infection.</li>
      <li>No certain chronic medical conditions (check with staff if unsure).</li>
      <li>Avoid donating if you are on disqualifying medications — consult your physician or the blood bank.</li>
      <li>If you have traveled to malaria-risk areas recently, you may be temporarily deferred.</li>
    </ul>

    <h5><i class="fas fa-clock section-icon"></i>Donation Interval</h5>
    <p>Typical recommended interval between whole blood donations is 
      <strong><?php echo defined('DONATION_INTERVAL_DAYS') ? (int)DONATION_INTERVAL_DAYS : 90; ?> days</strong>. 
      Plasma and platelet donation rules differ.</p>

    <h5><i class="fas fa-utensils section-icon"></i>Before Donation</h5>
    <ul>
      <li>Eat a healthy meal and stay hydrated.</li>
      <li>Avoid heavy exercise or alcohol before donation.</li>
      <li>Bring a list of medications and recent medical history if requested.</li>
    </ul>

    <h5><i class="fas fa-hand-holding-medical section-icon"></i>After Donation</h5>
    <ul>
      <li>Rest briefly at the donation center and follow staff instructions.</li>
      <li>Drink fluids and avoid heavy lifting for the rest of the day.</li>
      <li>Report any side effects to the blood bank.</li>
    </ul>

    <div class="mt-4 d-flex gap-3">
      <a href="eligibility_check.php" class="btn btn-primary"><i class="fas fa-check-circle me-2"></i>Run Eligibility Check</a>

    </div>
  </div>
</div>

<?php require_once '../includes/donor_footer.php'; ?>
</body>
</html>

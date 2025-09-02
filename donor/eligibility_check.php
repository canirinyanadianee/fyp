<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
// include header which prints navbar + sidebar and opens main content
require_once '../includes/donor_header.php';

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$donor = $conn->query("SELECT * FROM donors WHERE user_id = $user_id")->fetch_assoc();

$result = '';
$errors = [];

// Basic eligibility rules
function check_eligibility($donor) {
    $now = new DateTime();
    if (empty($donor)) return ['eligible' => false, 'reason' => 'No donor profile found. Please complete your profile.'];
    
    // age check
    if (!empty($donor['dob'])) {
        $dob = new DateTime($donor['dob']);
        $age = $dob->diff($now)->y;
        if ($age < 17 || $age > 65) 
            return ['eligible' => false, 'reason' => "Age restriction: $age years old."];
    }
    
    // last donation check
    $last = $donor['last_donation_date'] ?? null;
    $min_days = defined('DONATION_INTERVAL_DAYS') ? (int)DONATION_INTERVAL_DAYS : 90;
    if ($last) {
        $ld = new DateTime($last);
        $diff = $now->diff($ld)->days;
        if ($diff < $min_days) {
            return ['eligible' => false, 'reason' => "Last donation was $diff days ago. Wait $min_days days between donations."];
        }
    }
    
    // health flags
    if (!empty($donor['health_conditions'])) {
        return ['eligible' => false, 'reason' => 'Reported health conditions. Please consult the guidance.'];
    }
    
    return ['eligible' => true, 'reason' => '✅ You are likely eligible to donate. Please follow the guidelines and visit a blood bank.'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $check = check_eligibility($donor);
    $result = $check;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Eligibility Check</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

  <style>
    body {
      background: #f8f9fa;
    }
    .eligibility-card {
      border-radius: 15px;
      padding: 2rem;
      background: #fff;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      margin-top: 2rem;
    }
    h4 {
      color: #0d6efd;
      font-weight: 600;
    }
    .info-box {
      background: #f1f5ff;
      padding: 1rem;
      border-radius: 10px;
      margin-bottom: 1.5rem;
    }
    .info-box strong {
      color: #0d6efd;
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
<div class="container">
  <div class="eligibility-card">
    <h4 class="mb-3"><i class="fas fa-heartbeat me-2 text-danger"></i>Eligibility Check</h4>
    <p class="text-muted">Quickly check if you are eligible to donate based on your profile information.</p>

    <?php if (!empty($result)): ?>
        <?php if ($result['eligible']): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($result['reason']); ?></div>
        <?php else: ?>
            <div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($result['reason']); ?></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="info-box">
        <p><strong><i class="fas fa-user me-2"></i>Name:</strong> <?php echo htmlspecialchars($donor['first_name'] ?? $_SESSION['username'] ?? ''); ?></p>
        <p><strong><i class="fas fa-tint me-2"></i>Blood Type:</strong> <?php echo htmlspecialchars($donor['blood_type'] ?? 'Unknown'); ?></p>
        <p><strong><i class="fas fa-clock me-2"></i>Last Donation:</strong> <?php echo htmlspecialchars($donor['last_donation_date'] ?? 'Never'); ?></p>
    </div>

    <form method="post" action="">
        <button type="submit" class="btn btn-primary"><i class="fas fa-vial me-2"></i>Run Eligibility Check</button>
        <a href="eligibility_guidelines.php" class="btn btn-outline-primary"><i class="fas fa-book-medical me-2"></i>View Guidelines</a>
    </form>
  </div>
</div>

<?php require_once '../includes/donor_footer.php'; ?>
</body>
</html>

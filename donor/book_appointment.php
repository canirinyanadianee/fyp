<?php
// book_appointment.php

session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Initialize
$errors = [];
$success = '';
// optional HTML links returned on success
$success_link = '';

// Get current user and donor profile (present on GET and POST)
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $donor = $conn->query("SELECT * FROM donors WHERE user_id = $user_id")->fetch_assoc();
    $donor_missing = $donor ? false : true;
    $profile_link = $donor_missing ? "<a href='profile.php'>Go to Profile</a>" : '';
} else {
    // Shouldn't happen because we redirect earlier, but guard anyway
    $donor = null;
    $donor_missing = true;
    $profile_link = "<a href='../login.php'>Login</a>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If donor profile missing, prevent booking and show friendly message
    if ($donor_missing) {
        $errors[] = "You must complete your donor profile before booking an appointment.";
    } else {
        $donor_id = $donor['id'];
        $blood_type = $donor['blood_type'] ?? '';
        $date = $_POST['date'] ?? '';
        $time = $_POST['time'] ?? '';
        $blood_bank_id = $_POST['blood_bank_id'] ?? '';

        // Basic validation
        if (empty($date) || empty($time) || empty($blood_bank_id)) {
            $errors[] = "All fields are required.";
        }

        // Insert appointment if no errors
        if (empty($errors)) {
            $appointment_date = $date . ' ' . $time;
            $stmt = $conn->prepare("INSERT INTO appointments (donor_id, blood_bank_id, appointment_date) VALUES (?, ?, ?)");
            $stmt->bind_param("iis", $donor_id, $blood_bank_id, $appointment_date);

            if ($stmt->execute()) {
                // capture the new appointment id for a direct view link
                $appointment_id = $conn->insert_id;
                // Also insert a pending donation record for this appointment
                $quantity_ml = 0;
                $status = 'pending';
                // use appointment datetime as donation_date so it appears correctly in history
                $donation_date = $appointment_date; // datetime
                $stmt2 = $conn->prepare("INSERT INTO blood_donations (donor_id, blood_bank_id, blood_type, quantity_ml, donation_date, status) VALUES (?, ?, ?, ?, ?, ?)");
                // types: donor_id (i), blood_bank_id (i), blood_type (s), quantity_ml (i), donation_date (s), status (s)
                $stmt2->bind_param("iisiss", $donor_id, $blood_bank_id, $blood_type, $quantity_ml, $donation_date, $status);
                $stmt2->execute();
                $stmt2->close();
                $success = "Appointment booked successfully!";
                $success_link = "<a href='view_appointment.php?id={$appointment_id}'>View Appointment</a> | <a href='donation_history.php'>View Donation History</a> | <a href='index.php'>Go to Dashboard</a>";
            } else {
                $errors[] = "Failed to book appointment. Please try again.";
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; }
        .sidebar { min-height: 100vh; background: #fff; border-right: 1px solid #e5e7eb; }
        .sidebar .nav-link { color: #333; font-weight: 500; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { background: #e9ecef; color: #0d6efd; }
        .profile-card { border-radius: 1rem; }
        .donor-avatar { width: 60px; height: 60px; border-radius: 50%; background: #e9ecef; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #6c757d; }
    </style>
</head>
<body>
<!-- Top Navbar -->
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
<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <nav class="col-md-2 d-none d-md-block sidebar py-4">
            <div class="position-sticky">
                <ul class="nav flex-column mb-4">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="appointments.php"><i class="fas fa-calendar-alt me-2"></i>Appointments</a></li>
                    <li class="nav-item"><a class="nav-link active" href="book_appointment.php"><i class="fas fa-plus-circle me-2"></i>Book Appointment</a></li>
                    <li class="nav-item"><a class="nav-link" href="donation_history.php"><i class="fas fa-history me-2"></i>Donation History</a></li>
                    <li class="nav-item"><a class="nav-link" href="bloodbanks.php"><i class="fas fa-university me-2"></i>Blood Banks</a></li>
                    <li class="nav-item"><a class="nav-link" href="eligibility_check.php"><i class="fas fa-check-circle me-2"></i>Eligibility Check</a></li>
                    <li class="nav-item"><a class="nav-link" href="rewards.php"><i class="fas fa-award me-2"></i>Rewards & Badges</a></li>
                    <li class="nav-item"><a class="nav-link" href="health_info.php"><i class="fas fa-notes-medical me-2"></i>Health Information</a></li>
                    <li class="nav-item"><a class="nav-link" href="profile.php"><i class="fas fa-user me-2"></i>My Profile</a></li>
                </ul>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="col-md-10 ms-sm-auto px-md-4 py-4">
            <div class="row">
                <div class="col-12">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h4 class="mb-3">Book Appointment</h4>
                            <?php if ($donor_missing): ?>
                                <div class="alert alert-info">You have not completed your donor profile yet. Please <a href="profile.php">complete your profile</a>. Booking may be blocked until your profile exists.</div>
                            <?php endif; ?>

                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <?php echo htmlspecialchars($success); ?>
                                    <?php if (!empty($success_link)): ?>
                                        <div class="mt-2"><?php echo $success_link; ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($errors): ?>
                                <div class="alert alert-danger">
                                    <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php if (!empty($profile_link) && $donor_missing): ?>
                                    <div class="alert alert-warning">
                                        <?php echo $profile_link; ?>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <form method="post" action="" novalidate>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Date <span class="text-muted small">(required)</span></label>
                                        <input class="form-control form-control-lg" type="date" name="date" value="<?php echo htmlspecialchars($_POST['date'] ?? $_GET['date'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Time <span class="text-muted small">(required)</span></label>
                                        <input class="form-control form-control-lg" type="time" name="time" value="<?php echo htmlspecialchars($_POST['time'] ?? $_GET['time'] ?? ''); ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Blood Bank <span class="text-muted small">(required)</span></label>
                                        <select name="blood_bank_id" class="form-select form-select-lg" required>
                                            <option value="">Select Blood Bank</option>
                                            <?php
                                            $selected_bank = (string)($_POST['blood_bank_id'] ?? $_GET['blood_bank_id'] ?? '');
                                            $banks = $conn->query("SELECT id, name FROM blood_banks ORDER BY name");
                                            while ($row = $banks->fetch_assoc()): ?>
                                                <option value="<?php echo $row['id']; ?>" <?php echo ($selected_bank === (string)$row['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($row['name']); ?></option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-end mt-4">
                                    <button class="btn btn-primary btn-lg" type="submit">Book Appointment</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
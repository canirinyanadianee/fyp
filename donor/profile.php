<?php
// donor/profile.php - create or update donor profile
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'donor') {
    header('Location: ../login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$errors = [];
$success = '';

// Fetch last automated eligibility check for this donor (if any)
$last_check = null;
if ($donor) {
    $d_id = (int)$donor['id'];
    $chk_stmt = $conn->prepare("SELECT * FROM ai_eligibility_checks WHERE donor_id = ? ORDER BY created_at DESC LIMIT 1");
    if ($chk_stmt) {
        $chk_stmt->bind_param('i', $d_id);
        $chk_stmt->execute();
        $chk_res = $chk_stmt->get_result();
        if ($chk_row = $chk_res->fetch_assoc()) {
            $last_check = $chk_row;
        }
    }
}

// Fetch existing donor profile if any
$donor = $conn->query("SELECT * FROM donors WHERE user_id = $user_id")->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // If donor requested a live eligibility check
    if (isset($_POST['run_eligibility_check']) && $donor) {
        $donor_id = (int)$donor['id'];
        // call AI service endpoint
        $ai_url = 'http://127.0.0.1:5000/api/eligibility-check?donor_id=' . $donor_id;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $ai_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $resp = curl_exec($ch);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($resp) {
            $json = json_decode($resp, true);
            if (!empty($json) && isset($json['eligible'])) {
                // persist to ai_eligibility_checks
                $features = json_encode(['trigger'=>'manual_profile_check']);
                $result_json = json_encode($json);
                $ins = $conn->prepare("INSERT INTO ai_eligibility_checks (donor_id, checked_by, features, result) VALUES (?,?,?,?)");
                if ($ins) {
                    $checked_by = 'donor_profile_ui';
                    $ins->bind_param('isss', $donor_id, $checked_by, $features, $result_json);
                    $ins->execute();
                    $ins->close();
                }
                // reload last_check variable to show result
                $chk_stmt = $conn->prepare("SELECT * FROM ai_eligibility_checks WHERE donor_id = ? ORDER BY created_at DESC LIMIT 1");
                if ($chk_stmt) {
                    $chk_stmt->bind_param('i', $donor_id);
                    $chk_stmt->execute();
                    $chk_res = $chk_stmt->get_result();
                    if ($chk_row = $chk_res->fetch_assoc()) {
                        $last_check = $chk_row;
                    }
                }
                $success = 'Eligibility check completed.';
            } else {
                $errors[] = 'Eligibility check failed or returned unexpected data.';
            }
        } else {
            $errors[] = 'Eligibility service unavailable: ' . $curl_err;
        }
    }

    // Collect and sanitize
    $first_name = sanitize_input($_POST['first_name'] ?? '', $conn);
    $last_name = sanitize_input($_POST['last_name'] ?? '', $conn);
    $dob = sanitize_input($_POST['dob'] ?? '', $conn);
    $gender = sanitize_input($_POST['gender'] ?? '', $conn);
    $blood_type = sanitize_input($_POST['blood_type'] ?? '', $conn);
    $phone = sanitize_input($_POST['phone'] ?? '', $conn);
    $address = sanitize_input($_POST['address'] ?? '', $conn);
    $city = sanitize_input($_POST['city'] ?? '', $conn);
    $state = sanitize_input($_POST['state'] ?? '', $conn);
    $postal_code = sanitize_input($_POST['postal_code'] ?? '', $conn);
    $health_conditions = sanitize_input($_POST['health_conditions'] ?? '', $conn);
    $last_donation_date = sanitize_input($_POST['last_donation_date'] ?? '', $conn);

    // Basic validation
    if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender) || empty($blood_type)) {
        $errors[] = 'Please fill the required fields: first name, last name, DOB, gender and blood type.';
    }
    if (!is_valid_blood_type($blood_type)) {
        $errors[] = 'Invalid blood type selected.';
    }

    if (empty($errors)) {
        if ($donor) {
            // Update
            $sql = "UPDATE donors SET first_name=?, last_name=?, dob=?, gender=?, blood_type=?, phone=?, address=?, city=?, state=?, postal_code=?, health_conditions=?, last_donation_date=? WHERE user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('ssssssssssssi', $first_name, $last_name, $dob, $gender, $blood_type, $phone, $address, $city, $state, $postal_code, $health_conditions, $last_donation_date, $user_id);
            if ($stmt->execute()) {
                $success = 'Profile updated successfully.';
                // refresh donor
                $donor = $conn->query("SELECT * FROM donors WHERE user_id = $user_id")->fetch_assoc();
                // refresh last check for current donor id
                if ($donor) {
                    $d_id = (int)$donor['id'];
                    $chk_stmt = $conn->prepare("SELECT * FROM ai_eligibility_checks WHERE donor_id = ? ORDER BY created_at DESC LIMIT 1");
                    if ($chk_stmt) {
                        $chk_stmt->bind_param('i', $d_id);
                        $chk_stmt->execute();
                        $chk_res = $chk_stmt->get_result();
                        if ($chk_row = $chk_res->fetch_assoc()) {
                            $last_check = $chk_row;
                        }
                    }
                }
            } else {
                $errors[] = 'Failed to update profile: ' . $conn->error;
            }
            $stmt->close();
        } else {
            // Insert
            $sql = "INSERT INTO donors (user_id, first_name, last_name, dob, gender, blood_type, phone, address, city, state, postal_code, health_conditions, last_donation_date) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('issssssssssss', $user_id, $first_name, $last_name, $dob, $gender, $blood_type, $phone, $address, $city, $state, $postal_code, $health_conditions, $last_donation_date);
            if ($stmt->execute()) {
                $success = 'Profile created successfully.';
                $donor = $conn->query("SELECT * FROM donors WHERE user_id = $user_id")->fetch_assoc();
                // refresh eligibility check
                if ($donor) {
                    $d_id = (int)$donor['id'];
                    $chk_stmt = $conn->prepare("SELECT * FROM ai_eligibility_checks WHERE donor_id = ? ORDER BY created_at DESC LIMIT 1");
                    if ($chk_stmt) {
                        $chk_stmt->bind_param('i', $d_id);
                        $chk_stmt->execute();
                        $chk_res = $chk_stmt->get_result();
                        if ($chk_row = $chk_res->fetch_assoc()) {
                            $last_check = $chk_row;
                        }
                    }
                }
            } else {
                $errors[] = 'Failed to create profile: ' . $conn->error;
            }
            $stmt->close();
        }
    }
}

// Pre-fill values
$values = [
    'first_name' => $donor['first_name'] ?? '',
    'last_name' => $donor['last_name'] ?? '',
    'dob' => $donor['dob'] ?? '',
    'gender' => $donor['gender'] ?? '',
    'blood_type' => $donor['blood_type'] ?? '',
    'phone' => $donor['phone'] ?? '',
    'address' => $donor['address'] ?? '',
    'city' => $donor['city'] ?? '',
    'state' => $donor['state'] ?? '',
    'postal_code' => $donor['postal_code'] ?? '',
    'health_conditions' => $donor['health_conditions'] ?? '',
    'last_donation_date' => $donor['last_donation_date'] ?? ''
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Donor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
<div class="container py-5">
    <h2 class="mb-4">My Profile</h2>

    <!-- Eligibility status -->
    <div class="mb-4">
        <h5>Eligibility Status</h5>
        <?php if ($last_check):
            $res = json_decode($last_check['result'], true);
        ?>
            <div class="card p-3 mb-2">
                <strong>Last checked:</strong> <?php echo date('F j, Y g:i A', strtotime($last_check['created_at'])); ?><br>
                <strong>Checked by:</strong> <?php echo htmlspecialchars($last_check['checked_by'] ?? 'system'); ?><br>
                <strong>Eligible:</strong> <?php echo (!empty($res['eligible']) ? '<span class="text-success">Yes</span>' : '<span class="text-danger">No</span>'); ?><br>
                <?php if (!empty($res['reasons'])): ?>
                    <strong>Reasons:</strong>
                    <ul class="mb-0">
                        <?php foreach ($res['reasons'] as $r): ?>
                            <li><?php echo htmlspecialchars($r); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">No automated eligibility checks found. You can run one now.</div>
        <?php endif; ?>
        <form method="post" class="d-inline">
            <input type="hidden" name="run_eligibility_check" value="1">
            <button class="btn btn-outline-primary btn-sm">Run Eligibility Check</button>
        </form>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <form method="post" action="">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">First name</label>
                <input class="form-control" name="first_name" required value="<?php echo htmlspecialchars($values['first_name']); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Last name</label>
                <input class="form-control" name="last_name" required value="<?php echo htmlspecialchars($values['last_name']); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Date of birth</label>
                <input type="date" class="form-control" name="dob" required value="<?php echo htmlspecialchars($values['dob']); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select" required>
                    <option value="">Select</option>
                    <option value="male" <?php echo $values['gender']==='male'?'selected':''; ?>>Male</option>
                    <option value="female" <?php echo $values['gender']==='female'?'selected':''; ?>>Female</option>
                    <option value="other" <?php echo $values['gender']==='other'?'selected':''; ?>>Other</option>
                </select>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Blood type</label>
                <select name="blood_type" class="form-select" required>
                    <option value="">Select</option>
                    <?php foreach (BLOOD_TYPES as $bt): ?>
                        <option value="<?php echo $bt; ?>" <?php echo $values['blood_type']===$bt?'selected':''; ?>><?php echo $bt; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Phone</label>
                <input class="form-control" name="phone" value="<?php echo htmlspecialchars($values['phone']); ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">Last donation date (optional)</label>
                <input type="date" class="form-control" name="last_donation_date" value="<?php echo htmlspecialchars($values['last_donation_date']); ?>">
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Address</label>
                <textarea class="form-control" name="address"><?php echo htmlspecialchars($values['address']); ?></textarea>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">City</label>
                <input class="form-control" name="city" value="<?php echo htmlspecialchars($values['city']); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">State</label>
                <input class="form-control" name="state" value="<?php echo htmlspecialchars($values['state']); ?>">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Postal Code</label>
                <input class="form-control" name="postal_code" value="<?php echo htmlspecialchars($values['postal_code']); ?>">
            </div>
            <div class="col-12 mb-3">
                <label class="form-label">Health conditions / notes (for screening)</label>
                <textarea class="form-control" name="health_conditions"><?php echo htmlspecialchars($values['health_conditions']); ?></textarea>
            </div>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" type="submit">Save Profile</button>
            <a class="btn btn-secondary" href="index.php">Back to Dashboard</a>
            <a class="btn btn-outline-secondary" href="book_appointment.php">Book Appointment</a>
        </div>
    </form>
</div>
</body>
</html>

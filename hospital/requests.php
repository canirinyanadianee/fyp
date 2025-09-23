<?php
// Hospital Blood Request page
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/db.php';

// Check if user is logged in and has hospital role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../login.php');
    exit;
}

// Get hospital details
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM hospitals WHERE user_id = $user_id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $hospital = $result->fetch_assoc();
    $hospital_id = $hospital['id'];
} else {
    $sql = "SELECT * FROM users WHERE id = $user_id AND role = 'hospital'";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        $hospital = $result->fetch_assoc();
        $hospital_id = $hospital['id'];
    } else {
        header('Location: ../login.php');
        exit;
    }
}

$success = '';
$error = '';

// Define blood types
$blood_types = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

// Prefill values
$prefill_blood_type = '';
$prefill_urgency = '';
$prefill_quantity = 450;
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!empty($_GET['blood_type']) && in_array($_GET['blood_type'], $blood_types)) {
        $prefill_blood_type = $_GET['blood_type'];
    }
    if (!empty($_GET['urgency'])) {
        $prefill_urgency = in_array($_GET['urgency'], ['routine','urgent','emergency']) ? $_GET['urgency'] : '';
    }
    if (!empty($_GET['quantity_ml']) && is_numeric($_GET['quantity_ml'])) {
        $prefill_quantity = (int) $_GET['quantity_ml'];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $blood_type = sanitize_input($_POST['blood_type'] ?? '');
    $quantity_ml = (int)($_POST['quantity_ml'] ?? 0);
    $urgency = sanitize_input($_POST['urgency'] ?? 'routine');
    $patient_name = sanitize_input($_POST['patient_name'] ?? '');
    $patient_age = (int)($_POST['patient_age'] ?? 0);
    $reason = sanitize_input($_POST['reason'] ?? '');
    $required_by = sanitize_input($_POST['required_by'] ?? '');
    $selected_bank_id = isset($_POST['blood_bank_id']) ? (int)$_POST['blood_bank_id'] : 0;

    if (empty($blood_type) || !in_array($blood_type, $blood_types)) {
        $error = 'Please select a valid blood type';
    } elseif ($quantity_ml <= 0) {
        $error = 'Please enter a valid quantity (greater than 0)';
    } elseif (empty($patient_name)) {
        $error = 'Please enter patient name';
    } elseif ($selected_bank_id <= 0) {
        $error = 'Please choose a blood bank to send this request to';
    } else {
        // Validate selected bank exists
        $bank_ok = false;
        if ($chk = $conn->prepare("SELECT id FROM blood_banks WHERE id = ? LIMIT 1")) {
            $chk->bind_param('i', $selected_bank_id);
            $chk->execute();
            $bank_ok = (bool)$chk->get_result()->fetch_assoc();
            $chk->close();
        }
        if (!$bank_ok) {
            $error = 'Selected blood bank is invalid.';
        }
    }

    if (empty($error)) {
        $sql = "INSERT INTO blood_requests (hospital_id, blood_type, quantity_ml, urgency, patient_name, notes)
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $notes = $reason;
        $stmt->bind_param("isisss", $hospital_id, $blood_type, $quantity_ml, $urgency, $patient_name, $notes);
        
        if ($stmt->execute()) {
            $new_id = (int) $conn->insert_id;
            // Create a targeted transfer to the selected bank with status 'requested'
            if ($tr = $conn->prepare("INSERT INTO blood_transfers (blood_bank_id, hospital_id, blood_type, quantity_ml, request_type, status, transfer_date, notes, proposal_origin, proposed_by) VALUES (?, ?, ?, ?, 'hospital_request', 'requested', NOW(), ?, 'hospital_portal', ?)")) {
                $t_notes = 'Requested via hospital portal for request #' . $new_id;
                $proposed_by = 'hospital_user_' . $user_id;
                $tr->bind_param('iisiss', $selected_bank_id, $hospital_id, $blood_type, $quantity_ml, $t_notes, $proposed_by);
                $tr->execute();
            }
            // Call AI recommender to get recommended donors for this request
            try {
                $ai_url = 'http://127.0.0.1:5000/api/recommend-donors?blood_type=' . urlencode($blood_type) . '&max_results=20';
                // if hospital has location in hospitals table, pass lat/lon
                // Older schemas may not have latitude/longitude columns on hospitals table.
                // Check information_schema for the presence of these columns before querying them.
                $has_geo = false;
                $schema_q = $conn->prepare("SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'hospitals' AND COLUMN_NAME IN ('latitude','longitude')");
                if ($schema_q) {
                    $dbn = DB_NAME;
                    $schema_q->bind_param('s', $dbn);
                    $schema_q->execute();
                    $sr = $schema_q->get_result()->fetch_assoc();
                    if ($sr && intval($sr['c']) === 2) $has_geo = true;
                }
                if ($has_geo) {
                    $hstmt = $conn->prepare('SELECT latitude, longitude FROM hospitals WHERE id = ? LIMIT 1');
                    if ($hstmt) {
                        $hstmt->bind_param('i', $hospital_id);
                        $hstmt->execute();
                        $hres = $hstmt->get_result();
                        if ($hrow = $hres->fetch_assoc()) {
                            if (!empty($hrow['latitude']) && !empty($hrow['longitude'])) {
                                $ai_url .= '&lat=' . urlencode($hrow['latitude']) . '&lon=' . urlencode($hrow['longitude']);
                            }
                        }
                    }
                }
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $ai_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 5);
                $resp = curl_exec($ch);
                $curl_err = curl_error($ch);
                curl_close($ch);
                if ($resp) {
                    $json = json_decode($resp, true);
                    if (!empty($json['recommendations'])) {
                        $rec_json = json_encode($json['recommendations']);
                        $ins = $conn->prepare("INSERT INTO ai_recommendations (request_id, hospital_id, blood_type, recommended) VALUES (?,?,?,?)");
                        if ($ins) {
                            $ins->bind_param('iiss', $new_id, $hospital_id, $blood_type, $rec_json);
                            $ins->execute();
                            $ins->close();
                        }
                    }
                } else {
                    // log but do not block request creation
                    error_log('AI recommender error: ' . $curl_err);
                }
            } catch (Exception $e) {
                error_log('AI recommender exception: ' . $e->getMessage());
            }
            header('Location: blood_requests.php?new_id=' . $new_id);
            exit;
        } else {
            $error = 'Error submitting request: ' . $stmt->error;
        }
    }
}

// Blood banks
$blood_banks_sql = "SELECT id, name, contact_phone, city FROM blood_banks ORDER BY name";
$blood_banks_result = $conn->query($blood_banks_sql);
$blood_banks = [];
if ($blood_banks_result && $blood_banks_result->num_rows > 0) {
    while ($row = $blood_banks_result->fetch_assoc()) {
        $blood_banks[] = $row;
    }
}

$page_title = "Request Blood";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title; ?> - <?= APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom Embedded CSS -->
    <style>
        body { background-color: #f8f9fc; font-family: 'Segoe UI', sans-serif; }
        .card { border-radius: 12px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .card:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(0,0,0,0.08); }
        .navbar-brand { font-weight: bold; font-size: 1.2rem; letter-spacing: 0.5px; }
        .form-label { font-weight: 600; color: #333; }
        input, select, textarea { border-radius: 10px !important; }
        .btn { border-radius: 10px; padding: 8px 20px; font-weight: 600; transition: all 0.2s ease; }
        .btn-primary { background: linear-gradient(90deg, #007bff, #0056b3); border: none; }
        .btn-primary:hover { background: linear-gradient(90deg, #0056b3, #003d80); }
        footer { font-size: 0.9rem; }
        .stepper { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .step { flex: 1; text-align: center; font-size: 0.85rem; font-weight: 600; color: #6c757d; }
        .step.active { color: #007bff; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-hospital me-2"></i><?= APP_NAME; ?> - Hospital
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link active" href="requests.php">Blood Requests</a></li>
                    <li class="nav-item"><a class="nav-link" href="inventory.php">Available Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="transfers.php">Transfer History</a></li>
                    <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?= htmlspecialchars($_SESSION['username'] ?? 'Hospital User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <li><a class="dropdown-item" href="change_password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main -->
    <div class="container py-4">
        <div class="row mb-4">
            <div class="col">
                <h2 class="mb-3"><i class="fas fa-hand-holding-medical me-2 text-danger"></i> Request Blood</h2>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                        <li class="breadcrumb-item active">Request Blood</li>
                    </ol>
                </nav>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (!empty($success)): ?><div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= $success; ?></div><?php endif; ?>
        <?php if (!empty($error)): ?><div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i><?= $error; ?></div><?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2"></i>New Blood Request</h5>
                    </div>
                    <div class="card-body">
                        <!-- Stepper -->
                        <div class="stepper">
                            <div class="step active">Step 1<br>Fill Form</div>
                            <div class="step">Step 2<br>Review</div>
                            <div class="step">Step 3<br>Submit</div>
                        </div>
                        <form method="post" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-tint text-danger me-2"></i>Blood Type *</label>
                                    <select class="form-select" name="blood_type" required>
                                        <option value="">Select</option>
                                        <?php foreach ($blood_types as $type): ?>
                                            <option value="<?= $type; ?>" <?= ($prefill_blood_type === $type) ? 'selected' : ''; ?>><?= $type; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-vial me-2 text-info"></i>Quantity (ml) *</label>
                                    <input type="number" class="form-control" name="quantity_ml" min="100" max="2000" value="<?= htmlspecialchars($prefill_quantity); ?>" required>
                                    <div class="form-text">Standard unit = 450ml</div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-bolt me-2 text-warning"></i>Urgency *</label>
                                    <select class="form-select" name="urgency" required>
                                        <option value="routine" <?= ($prefill_urgency === 'routine') ? 'selected' : ''; ?>>Routine</option>
                                        <option value="urgent" <?= ($prefill_urgency === 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                                        <option value="emergency" <?= ($prefill_urgency === 'emergency') ? 'selected' : ''; ?>>Emergency</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-calendar-alt me-2 text-primary"></i>Required By</label>
                                    <input type="date" class="form-control" name="required_by" min="<?= date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-warehouse me-2 text-success"></i>Choose Blood Bank *</label>
                                <select class="form-select" name="blood_bank_id" required>
                                    <option value="">Select a blood bank</option>
                                    <?php foreach ($blood_banks as $bank): ?>
                                        <option value="<?= (int)$bank['id']; ?>"><?= htmlspecialchars($bank['name']); ?><?= !empty($bank['city']) ? ' - ' . htmlspecialchars($bank['city']) : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">Your request will be routed to the selected blood bank.</div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-user me-2"></i>Patient Name *</label>
                                    <input type="text" class="form-control" name="patient_name" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><i class="fas fa-birthday-cake me-2"></i>Patient Age</label>
                                    <input type="number" class="form-control" name="patient_age" min="0" max="120">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label"><i class="fas fa-notes-medical me-2"></i>Reason</label>
                                <textarea class="form-control" name="reason" rows="3" placeholder="Provide medical reason or details"></textarea>
                            </div>

                            <div class="text-end">
                                <a href="index.php" class="btn btn-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Submit</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Side Panel -->
            <div class="col-md-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-info text-white"><i class="fas fa-building me-2"></i>Available Blood Banks</div>
                    <div class="card-body">
                        <?php if (empty($blood_banks)): ?>
                            <p class="text-muted mb-0">No blood banks available.</p>
                        <?php else: ?>
                            <?php foreach ($blood_banks as $bank): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <strong><?= htmlspecialchars($bank['name']); ?></strong><br>
                                    <small class="text-muted">
                                        <i class="fas fa-phone me-1"></i><?= htmlspecialchars($bank['contact_phone'] ?? 'N/A'); ?><br>
                                        <i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($bank['city'] ?? 'Not specified'); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-warning text-dark"><i class="fas fa-info-circle me-2"></i>Request Guidelines</div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li><i class="fas fa-check text-success me-2"></i>Standard unit: 450ml</li>
                            <li><i class="fas fa-check text-success me-2"></i>Emergency processed within 1 hour</li>
                            <li><i class="fas fa-check text-success me-2"></i>Urgent within 4 hours</li>
                            <li><i class="fas fa-check text-success me-2"></i>Routine within 24 hours</li>
                            <li><i class="fas fa-check text-success me-2"></i>Cross-matching required</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-5">
        <div class="container text-center">
            <p class="mb-0">&copy; <?= date('Y'); ?> <?= APP_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

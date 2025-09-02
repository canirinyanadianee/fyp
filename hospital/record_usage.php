<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check if user is logged in and is a hospital
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'hospital') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user information
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Resolve hospital_id from hospitals table (hospital users have a hospitals record linked by user_id)
$hospital_id = null;
$hquery = "SELECT id FROM hospitals WHERE user_id = ? LIMIT 1";
$hstmt = $conn->prepare($hquery);
if ($hstmt) {
    $hstmt->bind_param("i", $user_id);
    $hstmt->execute();
    $hres = $hstmt->get_result();
    if ($hrow = $hres->fetch_assoc()) {
        $hospital_id = (int) $hrow['id'];
    } else {
        // No hospital record found for this user
        $error = 'Hospital profile not found. Please complete your hospital profile before recording usage.';
    }
} else {
    $error = 'Database error while verifying hospital profile: ' . $conn->error;
}

$success = '';
$error = '';

// Initialize form variables
$blood_type = '';
$quantity_used = '';
$patient_name = '';
$patient_id = '';
$purpose = '';
$usage_date = '';
$notes = '';

// Handle form submission
if ($_POST) {
    $blood_type = $_POST['blood_type'] ?? '';
    $quantity_used = $_POST['quantity_used'] ?? '';
    $patient_name = $_POST['patient_name'] ?? '';
    $patient_id = $_POST['patient_id'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    $usage_date = $_POST['usage_date'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if (empty($blood_type)) {
        $error = 'Please select blood type';
    } else if (empty($quantity_used) || $quantity_used <= 0) {
        $error = 'Please enter valid quantity used';
    } else if (empty($patient_name)) {
        $error = 'Please enter patient name';
    } else if (empty($patient_id)) {
        $error = 'Please enter patient ID';
    } else if (empty($purpose)) {
        $error = 'Please select purpose';
    } else if (empty($usage_date)) {
        $error = 'Please select usage date';
    } else {
        // Ensure hospital_id is available (prevent FK violation)
        if (empty($hospital_id)) {
            $error = 'Unable to determine hospital identity. Cannot record usage.';
        } else {
            // Record blood usage - ensure we have the right number of parameters
            $sql = "INSERT INTO blood_usage (hospital_id, blood_type, quantity_ml, patient_name, patient_id, purpose, usage_date, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Type string: i=integer, s=string
            // hospital_id(i), blood_type(s), quantity_ml(i), patient_name(s), patient_id(s), purpose(s), usage_date(s), notes(s)
        // Ensure numeric types are correctly cast
        $quantity_used = (int) $quantity_used;
        $stmt->bind_param("isisssss", $hospital_id, $blood_type, $quantity_used, $patient_name, $patient_id, $purpose, $usage_date, $notes);
            if ($stmt->execute()) {
                $success = 'Blood usage recorded successfully';
                // Clear form
                $blood_type = $quantity_used = $patient_name = $patient_id = $purpose = $usage_date = $notes = '';
            } else {
                $error = 'Error recording usage: ' . $stmt->error;
            }
        } else {
            $error = 'Database error: ' . $conn->error;
        }
    }
    }
}

// Get recent usage records (only when hospital_id is known)
$recent_usage = [];
if (!empty($hospital_id)) {
    $recent_usage_query = "SELECT * FROM blood_usage WHERE hospital_id = ? ORDER BY usage_date DESC LIMIT 10";
    $recent_stmt = $conn->prepare($recent_usage_query);
    if ($recent_stmt) {
        $recent_stmt->bind_param("i", $hospital_id);
        $recent_stmt->execute();
        $recent_result = $recent_stmt->get_result();
        while ($row = $recent_result->fetch_assoc()) {
            $recent_usage[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Blood Usage - Hospital Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <!-- Include header -->
    <?php include '../includes/hospital_header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 bg-light sidebar py-3">
                <h6 class="text-muted mb-3">HOSPITAL MENU</h6>
                <div class="nav flex-column">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-chart-bar"></i> Blood Inventory
                    </a>
                    <a class="nav-link" href="requests.php">
                        <i class="fas fa-plus-circle"></i> Request Blood
                    </a>
                    <a class="nav-link" href="blood_requests.php">
                        <i class="fas fa-list"></i> Blood Requests
                    </a>
                    <a class="nav-link active" href="record_usage.php">
                        <i class="fas fa-chart-line"></i> Record Usage
                    </a>
                    <a class="nav-link" href="usage_reports.php">
                        <i class="fas fa-file-alt"></i> Usage Reports
                    </a>
                    <a class="nav-link" href="ai_predictions.php">
                        <i class="fas fa-robot"></i> AI Predictions
                    </a>
                    <a class="nav-link" href="nearby_bloodbanks.php">
                        <i class="fas fa-map-marker-alt"></i> Nearby Blood Banks
                    </a>
                    <a class="nav-link" href="staff_management.php">
                        <i class="fas fa-users"></i> Staff Management
                    </a>
                </div>
            </div>

            <!-- Main content -->
            <div class="col-md-10">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Record Blood Usage</h1>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="row">
                    <!-- Usage Form -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Record New Usage</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="blood_type" class="form-label">Blood Type</label>
                                        <select class="form-select" id="blood_type" name="blood_type" required>
                                            <option value="">Select Blood Type</option>
                                            <option value="A+" <?php echo ($blood_type === 'A+') ? 'selected' : ''; ?>>A+</option>
                                            <option value="A-" <?php echo ($blood_type === 'A-') ? 'selected' : ''; ?>>A-</option>
                                            <option value="B+" <?php echo ($blood_type === 'B+') ? 'selected' : ''; ?>>B+</option>
                                            <option value="B-" <?php echo ($blood_type === 'B-') ? 'selected' : ''; ?>>B-</option>
                                            <option value="AB+" <?php echo ($blood_type === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                            <option value="AB-" <?php echo ($blood_type === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                            <option value="O+" <?php echo ($blood_type === 'O+') ? 'selected' : ''; ?>>O+</option>
                                            <option value="O-" <?php echo ($blood_type === 'O-') ? 'selected' : ''; ?>>O-</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="quantity_used" class="form-label">Quantity Used (ml)</label>
                                        <input type="number" class="form-control" id="quantity_used" name="quantity_used" 
                                               value="<?php echo htmlspecialchars($quantity_used); ?>" min="1" step="50" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="patient_name" class="form-label">Patient Name</label>
                                        <input type="text" class="form-control" id="patient_name" name="patient_name" 
                                               value="<?php echo htmlspecialchars($patient_name); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="patient_id" class="form-label">Patient ID</label>
                                        <input type="text" class="form-control" id="patient_id" name="patient_id" 
                                               value="<?php echo htmlspecialchars($patient_id); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="purpose" class="form-label">Purpose</label>
                                        <select class="form-select" id="purpose" name="purpose" required>
                                            <option value="">Select Purpose</option>
                                            <option value="surgery" <?php echo ($purpose === 'surgery') ? 'selected' : ''; ?>>Surgery</option>
                                            <option value="trauma" <?php echo ($purpose === 'trauma') ? 'selected' : ''; ?>>Trauma</option>
                                            <option value="transfusion" <?php echo ($purpose === 'transfusion') ? 'selected' : ''; ?>>Blood Transfusion</option>
                                            <option value="emergency" <?php echo ($purpose === 'emergency') ? 'selected' : ''; ?>>Emergency</option>
                                            <option value="routine" <?php echo ($purpose === 'routine') ? 'selected' : ''; ?>>Routine Treatment</option>
                                            <option value="other" <?php echo ($purpose === 'other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="usage_date" class="form-label">Usage Date</label>
                                        <input type="date" class="form-control" id="usage_date" name="usage_date" 
                                               value="<?php echo htmlspecialchars($usage_date); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes (Optional)</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($notes); ?></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">Record Usage</button>
                                    <a href="index.php" class="btn btn-secondary">Back to Dashboard</a>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Usage Records -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Recent Usage Records</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_usage)): ?>
                                    <p class="text-muted">No usage records found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Blood Type</th>
                                                    <th>Quantity</th>
                                                    <th>Patient</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_usage as $usage): ?>
                                                    <tr>
                                                        <td><span class="badge bg-primary"><?php echo $usage['blood_type']; ?></span></td>
                                                        <td><?php echo number_format($usage['quantity_ml']); ?> ml</td>
                                                        <td><?php echo htmlspecialchars($usage['patient_name']); ?></td>
                                                        <td><?php echo date('M j', strtotime($usage['usage_date'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <a href="usage_reports.php?start_date=1970-01-01&end_date=2099-12-31" class="btn btn-outline-primary btn-sm">View All Records</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set today's date as default
        document.getElementById('usage_date').value = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>

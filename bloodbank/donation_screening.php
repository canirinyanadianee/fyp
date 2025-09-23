<?php
// Error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Set content type
header('Content-Type: text/html; charset=utf-8');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a blood bank
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bloodbank') {
    $_SESSION['error'] = 'Please log in to access this page.';
    header('Location: ../login.php');
    exit();
}

// Include required files
$base_dir = dirname(__DIR__);
require_once $base_dir . '/includes/config.php';
require_once $base_dir . '/includes/db.php';
require_once $base_dir . '/includes/functions.php';

// Initialize error array
$errors = [];
$success = '';

// Set page title and breadcrumbs
$page_title = 'Record Blood Donation';
$breadcrumbs = [
    'Donations' => 'donations.php',
    'Record Donation' => ''
];

try {
    // Get blood bank info
    $user_id = $_SESSION['user_id'];
    $blood_bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id");
    
    if (!$blood_bank) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $blood_bank = $blood_bank->fetch_assoc();
    
    if (!$blood_bank) {
        throw new Exception('Blood bank not found for this user.');
    }
    
    $blood_bank_id = $blood_bank['id'];
    
    // Get donor ID from request
    $donor_id = isset($_GET['donor_id']) ? (int)$_GET['donor_id'] : 0;
    $donor = null;
    $appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
    
    // Validate donor ID
    if ($donor_id <= 0) {
        throw new Exception('Invalid donor ID');
    }
    
    // Get donor details
    $stmt = $conn->prepare("SELECT * FROM donors WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Database error: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $donor_id);
    if (!$stmt->execute()) {
        throw new Exception('Failed to fetch donor details: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Donor not found');
    }
    
    $donor = $result->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    $errors[] = $e->getMessage();
    error_log('Donation Screening Error: ' . $e->getMessage());
}

// The donor details are already fetched in the try-catch block above
// This duplicate code is not needed and can cause issues

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $donor_id = (int)$_POST['donor_id'];
    $appointment_id = !empty($_POST['appointment_id']) ? (int)$_POST['appointment_id'] : null;
    $donation_date = $_POST['donation_date'];
    $blood_type = $_POST['blood_type'];
    $quantity_ml = (int)$_POST['quantity_ml'];
    $hemoglobin_level = (float)$_POST['hemoglobin_level'];
    $blood_pressure = $_POST['blood_pressure'];
    $pulse = (int)$_POST['pulse'];
    $temperature = (float)$_POST['temperature'];
    $weight = (float)$_POST['weight'];
    $notes = $conn->real_escape_string($_POST['notes']);
    
    // Test results
    $hiv = isset($_POST['hiv']) ? 1 : 0;
    $hepatitis_b = isset($_POST['hepatitis_b']) ? 1 : 0;
    $hepatitis_c = isset($_POST['hepatitis_c']) ? 1 : 0;
    $syphilis = isset($_POST['syphilis']) ? 1 : 0;
    $malaria = isset($_POST['malaria']) ? 1 : 0;
    $htlv = isset($_POST['htlv']) ? 1 : 0;
    $west_nile = isset($_POST['west_nile']) ? 1 : 0;
    $chagas = isset($_POST['chagas']) ? 1 : 0;
    $cmv = isset($_POST['cmv']) ? 1 : 0;
    $irregular_antibodies = isset($_POST['irregular_antibodies']) ? 1 : 0;
    
    $test_results = json_encode([
        'hiv' => $hiv,
        'hepatitis_b' => $hepatitis_b,
        'hepatitis_c' => $hepatitis_c,
        'syphilis' => $syphilis,
        'malaria' => $malaria,
        'htlv' => $htlv,
        'west_nile' => $west_nile,
        'chagas' => $chagas,
        'cmv' => $cmv,
        'irregular_antibodies' => $irregular_antibodies
    ]);
    
    // Calculate if blood is safe (all tests negative)
    $is_safe = !($hiv || $hepatitis_b || $hepatitis_c || $syphilis || $malaria || $htlv || $west_nile || $chagas);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // 1. Record the donation
        $stmt = $conn->prepare("INSERT INTO blood_donations (donor_id, blood_bank_id, blood_type, quantity_ml, donation_date, test_results, status, notes) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $status = $is_safe ? 'tested' : 'discarded';
        $stmt->bind_param('iissssss', $donor_id, $blood_bank_id, $blood_type, $quantity_ml, $donation_date, $test_results, $status, $notes);
        $stmt->execute();
        $donation_id = $conn->insert_id;
        
        // 2. Update donor's last donation date and count
        $conn->query("UPDATE donors SET last_donation_date = '$donation_date', donation_count = donation_count + 1 WHERE id = $donor_id");
        
        // 3. If blood is safe, add to inventory
        if ($is_safe) {
            $expiry_date = date('Y-m-d', strtotime('+42 days', strtotime($donation_date)));
            
            // Check if inventory for this blood type already exists
            $check = $conn->query("SELECT id, quantity_ml FROM blood_inventory 
                                   WHERE blood_bank_id = $blood_bank_id 
                                   AND blood_type = '$blood_type' 
                                   AND expiry_date = '$expiry_date' 
                                   AND status = 'available'");
            
            if ($check->num_rows > 0) {
                // Update existing inventory
                $inventory = $check->fetch_assoc();
                $new_quantity = $inventory['quantity_ml'] + $quantity_ml;
                $conn->query("UPDATE blood_inventory SET quantity_ml = $new_quantity, last_updated = NOW() 
                              WHERE id = {$inventory['id']}");
            } else {
                // Create new inventory record
                $conn->query("INSERT INTO blood_inventory (blood_bank_id, blood_type, quantity_ml, expiry_date, status) 
                              VALUES ($blood_bank_id, '$blood_type', $quantity_ml, '$expiry_date', 'available')");
            }
        }
        
        // 4. Update appointment status if this was from an appointment
        if ($appointment_id) {
            $conn->query("UPDATE appointments SET status = 'completed' WHERE id = $appointment_id");
        }
        
        // 5. Log the activity
        $action = $is_safe ? 'donation_recorded' : 'donation_rejected';
        $description = $is_safe 
            ? "Recorded new donation #$donation_id (${quantity_ml}ml of $blood_type)"
            : "Rejected donation #$donation_id due to positive test results";
        log_activity($user_id, $action, $description);
        
        // Commit transaction
        $conn->commit();
        
        // Redirect to success page
        $_SESSION['success'] = "Donation successfully recorded. Blood is " . ($is_safe ? 'safe and added to inventory.' : 'not safe and has been discarded.');
        header("Location: donation_details.php?id=$donation_id");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = "Error recording donation: " . $e->getMessage();
    }
}

// Set default values
$donation_date = date('Y-m-d\TH:i');
$hemoglobin_level = '';
$blood_pressure = '';
$pulse = '';
$temperature = '';
$weight = '';
$notes = '';

// Set default test results
$test_results = [
    'hiv' => 0,
    'hepatitis_b' => 0,
    'hepatitis_c' => 0,
    'syphilis' => 0,
    'malaria' => 0,
    'htlv' => 0,
    'west_nile' => 0,
    'chagas' => 0,
    'cmv' => 0,
    'irregular_antibodies' => 0
];

// Get today's date for the form
try {
    $donation_date = (new DateTime())->format('Y-m-d\TH:i');
} catch (Exception $e) {
    $donation_date = date('Y-m-d\TH:i');
    error_log('Date initialization error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title . ' - ' . ($blood_bank['name'] ?? 'Blood Bank')); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .card {
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #fff;
            border-radius: 0.5rem;
            border-left: 4px solid #dc3545;
        }
        .test-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        .test-checkbox input[type="checkbox"] {
            width: 1.2rem;
            height: 1.2rem;
            margin-right: 0.5rem;
        }
        .test-result {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }
        .test-group {
            flex: 1 1 200px;
            padding: 1rem;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
        }
        .test-group h6 {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
            color: #495057;
        }
        .required:after {
            content: ' *';
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php 
    // Include header
    $header_path = __DIR__ . '/includes/header.php';
    if (!file_exists($header_path)) {
        die('Header file not found. Please check the file path.');
    }
    include $header_path; 
    
    // Display errors if any
    if (!empty($errors)) {
        echo '<div class="container mt-4">';
        echo '<div class="alert alert-danger">';
        echo '<h5>Error(s) occurred:</h5><ul class="mb-0">';
        foreach ($errors as $error) {
            echo '<li>' . htmlspecialchars($error) . '</li>';
        }
        echo '</ul></div>';
        echo '<a href="donors.php" class="btn btn-primary">Return to Donors</a>';
        echo '</div>';
        include __DIR__ . '/includes/footer.php';
        exit();
    }
    ?>
    
    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-tint text-danger me-2"></i>Record Blood Donation</h2>
            <a href="donations.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Donations
            </a>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Donor Information</h5>
            </div>
            <div class="card-body">
                <?php if ($donor): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> <?php echo htmlspecialchars($donor['first_name'] . ' ' . $donor['last_name']); ?></p>
                            <p><strong>Blood Type:</strong> <?php echo htmlspecialchars($donor['blood_type']); ?></p>
                            <p><strong>Date of Birth:</strong> <?php echo date('M j, Y', strtotime($donor['dob'])); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Last Donation:</strong> 
                                <?php 
                                    echo $donor['last_donation_date'] 
                                        ? date('M j, Y', strtotime($donor['last_donation_date'])) 
                                        : 'First time donor';
                                ?>
                            </p>
                            <p><strong>Total Donations:</strong> <?php echo (int)$donor['donation_count']; ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">No donor selected. Please select a donor first.</div>
                    <a href="donors.php" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Find Donor
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($donor): ?>
        <form method="POST" id="donationForm">
            <input type="hidden" name="donor_id" value="<?php echo $donor_id; ?>">
            <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
            
            <div class="form-section">
                <h5 class="mb-4"><i class="fas fa-clipboard-check text-primary me-2"></i>Donation Details</h5>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="donation_date" class="form-label required">Donation Date & Time</label>
                        <input type="datetime-local" class="form-control" id="donation_date" name="donation_date" 
                               value="<?php echo $donation_date; ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="blood_type" class="form-label required">Blood Type</label>
                        <select class="form-select" id="blood_type" name="blood_type" required>
                            <option value="">Select Blood Type</option>
                            <option value="A+" <?php echo ($donor && $donor['blood_type'] === 'A+') ? 'selected' : ''; ?>>A+</option>
                            <option value="A-" <?php echo ($donor && $donor['blood_type'] === 'A-') ? 'selected' : ''; ?>>A-</option>
                            <option value="B+" <?php echo ($donor && $donor['blood_type'] === 'B+') ? 'selected' : ''; ?>>B+</option>
                            <option value="B-" <?php echo ($donor && $donor['blood_type'] === 'B-') ? 'selected' : ''; ?>>B-</option>
                            <option value="AB+" <?php echo ($donor && $donor['blood_type'] === 'AB+') ? 'selected' : ''; ?>>AB+</option>
                            <option value="AB-" <?php echo ($donor && $donor['blood_type'] === 'AB-') ? 'selected' : ''; ?>>AB-</option>
                            <option value="O+" <?php echo ($donor && $donor['blood_type'] === 'O+') ? 'selected' : ''; ?>>O+</option>
                            <option value="O-" <?php echo ($donor && $donor['blood_type'] === 'O-') ? 'selected' : ''; ?>>O-</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="quantity_ml" class="form-label required">Quantity (ml)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="quantity_ml" name="quantity_ml" 
                                   min="450" max="500" value="450" required>
                            <span class="input-group-text">ml</span>
                        </div>
                        <small class="text-muted">Standard donation is 450-500ml</small>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h5 class="mb-4"><i class="fas fa-heartbeat text-danger me-2"></i>Vital Signs</h5>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="hemoglobin_level" class="form-label required">Hemoglobin Level (g/dL)</label>
                        <div class="input-group">
                            <input type="number" step="0.1" class="form-control" id="hemoglobin_level" name="hemoglobin_level" 
                                   min="0" max="25" required>
                            <span class="input-group-text">g/dL</span>
                        </div>
                        <small class="text-muted">Minimum: 12.5 g/dL for women, 13.0 g/dL for men</small>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="blood_pressure" class="form-label required">Blood Pressure</label>
                        <input type="text" class="form-control" id="blood_pressure" name="blood_pressure" 
                               placeholder="e.g., 120/80" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="pulse" class="form-label required">Pulse (bpm)</label>
                        <div class="input-group">
                            <input type="number" class="form-control" id="pulse" name="pulse" 
                                   min="40" max="120" required>
                            <span class="input-group-text">bpm</span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="temperature" class="form-label required">Temperature (°C)</label>
                        <div class="input-group">
                            <input type="number" step="0.1" class="form-control" id="temperature" name="temperature" 
                                   min="35" max="40" required>
                            <span class="input-group-text">°C</span>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="weight" class="form-label required">Weight (kg)</label>
                        <div class="input-group">
                            <input type="number" step="0.1" class="form-control" id="weight" name="weight" 
                                   min="45" max="200" required>
                            <span class="input-group-text">kg</span>
                        </div>
                        <small class="text-muted">Minimum: 50kg</small>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h5 class="mb-4"><i class="fas fa-flask text-success me-2"></i>Test Results</h5>
                <p class="text-muted mb-4">Check all tests that are positive. Leave unchecked if negative.</p>
                
                <div class="test-result">
                    <div class="test-group">
                        <h6>Infectious Diseases</h6>
                        <div class="test-checkbox">
                            <input type="checkbox" class="form-check-input" id="hiv" name="hiv">
                            <label class="form-check-label" for="hiv">HIV</label>
                        </div>
                        <div class="test-checkbox">
                            <input type="checkbox" class="form-check-input" id="hepatitis_b" name="hepatitis_b">
                            <label class="form-check-label" for="hepatitis_b">Hepatitis B</label>
                        </div>
                        <div class="test-checkbox">
                            <input type="checkbox" class="form-check-input" id="hepatitis_c" name="hepatitis_c">
                            <label class="form-check-label" for="hepatitis_c">Hepatitis C</label>
                        </div>
                        <div class="test-checkbox">
                            <input type="checkbox" class="form-check-input" id="syphilis" name="syphilis">
                            <label class="form-check-label" for="syphilis">Syphilis</label>
                        </div>
                        <div class="test-checkbox">
                            <input type="checkbox" class="form-check-input" id="malaria" name="malaria">
                            <label class="form-check-label" for="malaria">Malaria</label>
                        </div>
                    </div>
                    
                    <div class="test-group">
                        <h6>Additional Tests</h6>
                        <div class="test-checkbox">
                            <input type="checkbox" class="form-check-input" id="htlv" name="htlv">
                            <label class="form-check-label" for="htlv">HTLV-I/II</label>
                        </div>
                        <div class="test-checkbox">
                            <input type="checkbox" class="form-check-input" id="west_nile" name="west_nile">
                            <label class="form-check-label" for="west_nile">West Nile Virus</label>
                        </div>
                        <div class="test-checkbox">
                            <input type="checkbox" class="form-check-input" id="chagas" name="chagas">
                            <label class="form-check-label" for="chagas">Chagas Disease</label>
                        </div>
                        <div class="test-checkbox">
                            <input type="checkbox" class="form-check-input" id="cmv" name="cmv">
                            <label class="form-check-label" for="cmv">CMV</label>
                        </div>
                        <div class="test-checkbox">
                            <input type="checkbox" class="form-check-input" id="irregular_antibodies" name="irregular_antibodies">
                            <label class="form-check-label" for="irregular_antibodies">Irregular Antibodies</label>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h5 class="mb-4"><i class="fas fa-notes-medical text-info me-2"></i>Additional Notes</h5>
                <div class="mb-3">
                    <label for="notes" class="form-label">Notes</label>
                    <textarea class="form-control" id="notes" name="notes" rows="3" 
                              placeholder="Any additional information about the donation..."></textarea>
                </div>
            </div>
            
            <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                <button type="reset" class="btn btn-outline-secondary me-md-2">
                    <i class="fas fa-undo me-2"></i>Reset Form
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-2"></i>Save Donation
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Client-side validation
        document.getElementById('donationForm').addEventListener('submit', function(e) {
            let isValid = true;
            const requiredFields = this.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Validate blood pressure format
            const bpInput = document.getElementById('blood_pressure');
            const bpRegex = /^\d{2,3}\/\d{2,3}$/;
            if (bpInput && !bpRegex.test(bpInput.value)) {
                bpInput.classList.add('is-invalid');
                isValid = false;
            } else if (bpInput) {
                bpInput.classList.remove('is-invalid');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
                return false;
            }
            
            // Confirm before submitting if any test is positive
            const checkboxes = this.querySelectorAll('input[type="checkbox"]:checked');
            if (checkboxes.length > 0) {
                if (!confirm('WARNING: One or more tests are marked as positive. This will mark the blood as unsafe. Continue?')) {
                    e.preventDefault();
                    return false;
                }
            }
            
            return true;
        });
    </script>
</body>
</html>

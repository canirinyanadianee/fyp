<?php
require_once '../includes/config.php';
require_once '../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bloodbank') {
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch bank profile (if exists)
$result = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id");
$bank = $result && $result->num_rows > 0 ? $result->fetch_assoc() : [];

// For messages
$errors = [];
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $license       = trim($_POST['license_number'] ?? '');
    $address       = trim($_POST['address'] ?? '');
    $city          = trim($_POST['city'] ?? '');
    $state         = trim($_POST['state'] ?? '');
    $postal        = trim($_POST['postal_code'] ?? '');
    $phone         = trim($_POST['phone'] ?? '');
    $contact_email = trim($_POST['contact_email'] ?? '');

    if ($name === '') $errors[] = 'Blood bank name is required';
    if ($license === '') $errors[] = 'License number is required';

    if (empty($errors)) {
        if ($bank) {
            // Update existing profile
            $stmt = $conn->prepare("UPDATE blood_banks 
                SET name=?, license_number=?, address=?, city=?, state=?, postal_code=?, phone=?, contact_email=? 
                WHERE user_id=?");
            $stmt->bind_param('ssssssssi', $name, $license, $address, $city, $state, $postal, $phone, $contact_email, $user_id);
            $stmt->execute();
            $stmt->close();
            $success = 'Profile updated successfully.';
        } else {
            // Insert new profile if it doesn’t exist
            $stmt = $conn->prepare("INSERT INTO blood_banks 
                (user_id, name, license_number, address, city, state, postal_code, phone, contact_email) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssssss', $user_id, $name, $license, $address, $city, $state, $postal, $phone, $contact_email);
            $stmt->execute();
            $stmt->close();
            $success = 'Profile created successfully.';
        }

        // Refresh bank info
        $bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Blood Bank Profile</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container py-4">
    <h2 class="mb-4"><i class="fas fa-user-edit text-secondary me-2"></i>Edit Blood Bank Profile</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul><?php foreach ($errors as $e): ?><li><?php echo htmlspecialchars($e); ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" class="row g-3">
        <div class="col-md-6">
            <label for="name" class="form-label">Blood Bank Name</label>
            <input type="text" class="form-control" id="name" name="name" required
                   value="<?= isset($bank['name']) ? htmlspecialchars($bank['name']) : '' ?>">
        </div>
        <div class="col-md-6">
            <label for="license_number" class="form-label">License #</label>
            <input type="text" class="form-control" id="license_number" name="license_number" required
                   value="<?= isset($bank['license_number']) ? htmlspecialchars($bank['license_number']) : '' ?>">
        </div>
        <div class="col-md-6">
            <label for="address" class="form-label">Address</label>
            <input type="text" class="form-control" id="address" name="address"
                   value="<?= isset($bank['address']) ? htmlspecialchars($bank['address']) : '' ?>">
        </div>
        <div class="col-md-6">
            <label for="city" class="form-label">City</label>
            <input type="text" class="form-control" id="city" name="city"
                   value="<?= isset($bank['city']) ? htmlspecialchars($bank['city']) : '' ?>">
        </div>
        <div class="col-md-6">
            <label for="state" class="form-label">State</label>
            <input type="text" class="form-control" id="state" name="state"
                   value="<?= isset($bank['state']) ? htmlspecialchars($bank['state']) : '' ?>">
        </div>
        <div class="col-md-6">
            <label for="postal_code" class="form-label">Postal Code</label>
            <input type="text" class="form-control" id="postal_code" name="postal_code"
                   value="<?= isset($bank['postal_code']) ? htmlspecialchars($bank['postal_code']) : '' ?>">
        </div>
        <div class="col-md-6">
            <label for="phone" class="form-label">Phone</label>
            <input type="text" class="form-control" id="phone" name="phone"
                   value="<?= isset($bank['phone']) ? htmlspecialchars($bank['phone']) : '' ?>">
        </div>
        <div class="col-md-6">
            <label for="contact_email" class="form-label">Contact Email</label>
            <input type="email" class="form-control" id="contact_email" name="contact_email"
                   value="<?= isset($bank['contact_email']) ? htmlspecialchars($bank['contact_email']) : '' ?>">
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <a href="profile.php" class="btn btn-link">Cancel</a>
        </div>
    </form>
</div>
</body>
</html>

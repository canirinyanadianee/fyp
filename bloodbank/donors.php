<?php
// View Donors for this Blood Bank
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'bloodbank') {
    header('Location: ../login.php');
    exit();
}
$user_id = $_SESSION['user_id'];
$bank = $conn->query("SELECT * FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
$blood_bank_id = $bank['id'];

// Get unique donors who have donated to this blood bank
$donors = $conn->query("SELECT d.* FROM donors d JOIN blood_donations bd ON d.id = bd.donor_id WHERE bd.blood_bank_id = $blood_bank_id GROUP BY d.id ORDER BY d.first_name, d.last_name");
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>View Donors - <?php echo htmlspecialchars($bank['name']); ?> Blood Bank</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css' rel='stylesheet'>
</head>
<body>
<div class='container py-4'>
    <h2 class='mb-4'><i class='fas fa-users text-success me-2'></i>Donors for <?php echo htmlspecialchars($bank['name']); ?></h2>
    <table class='table table-bordered'>
        <thead class='table-light'>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Blood Type</th>
                <th>Phone</th>
                <th>Last Donation</th>
            </tr>
        </thead>
        <tbody>
        <?php while ($donor = $donors->fetch_assoc()): ?>
            <?php
                $last = $conn->query("SELECT MAX(donation_date) as last_date FROM blood_donations WHERE donor_id = {$donor['id']} AND blood_bank_id = $blood_bank_id")->fetch_assoc();
            ?>
            <tr>
                <td><?php echo htmlspecialchars($donor['first_name']); ?></td>
                <td><?php echo htmlspecialchars($donor['last_name']); ?></td>
                <td><?php echo htmlspecialchars($donor['email'] ?? ''); ?></td>
                <td><?php echo htmlspecialchars($donor['blood_type']); ?></td>
                <td><?php echo htmlspecialchars($donor['phone']); ?></td>
                <td><?php echo $last['last_date'] ? $last['last_date'] : '-'; ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <a href='index.php' class='btn btn-link mt-4'><i class='fas fa-arrow-left'></i> Back to Dashboard</a>
</div>
</body>
</html>

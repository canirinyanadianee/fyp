<?php
// Include configuration and database connection
require_once '../includes/config.php';
require_once '../includes/db.php';

// Check if user is logged in and is a blood bank
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'bloodbank') {
    header('Location: ../login.php');
    exit();
}

// Determine current blood bank
$user_id = (int)$_SESSION['user_id'];
$bank = $conn->query("SELECT id, name FROM blood_banks WHERE user_id = $user_id")->fetch_assoc();
if (!$bank) {
    $_SESSION['error'] = 'Blood bank profile not found for this account.';
    header('Location: index.php');
    exit();
}
$blood_bank_id = (int)$bank['id'];

// Fetch donors and their donations for this bank using blood_donations table
$sql = "
    SELECT d.id AS donor_id,
           CONCAT(d.first_name, ' ', d.last_name) AS donor_name,
           d.blood_type,
           bd.id AS donation_id,
           bd.donation_date AS date,
           bd.quantity_ml AS amount
    FROM blood_donations bd
    INNER JOIN donors d ON d.id = bd.donor_id
    WHERE bd.blood_bank_id = ?
    ORDER BY d.first_name, d.last_name, bd.donation_date DESC
";

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param('i', $blood_bank_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
} else {
    $result = false;
}

include __DIR__ . '/includes/header.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Donor Donations</title>
    <style>
        background-color:rgb(78, 84, 126);
        table { border-collapse: collapse; width: 80%; margin: 20px auto; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
    </style>
</head>
<body>
    <h2 style="text-align:center;">Donor Donations</h2>
    <table>
        <tr>
            <th>Donor Name</th>
            <th>Blood Type</th>
            <th>Donation ID</th>
            <th>Date</th>
            <th>Amount (ml)</th>
        </tr>
        <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['donor_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['blood_type']); ?></td>
                    <td><?php echo htmlspecialchars($row['donation_id']); ?></td>
                    <td><?php echo htmlspecialchars($row['date']); ?></td>
                    <td><?php echo htmlspecialchars($row['amount']); ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr><td colspan="5">No donations found.</td></tr>
        <?php endif; ?>
    </table>
</body>
</html>

<?php
$conn->close();
?>
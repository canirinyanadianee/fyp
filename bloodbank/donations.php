<?php
// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'bloodbank';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch donors and their donations
$sql = "
    SELECT donors.id AS donor_id, donors.name AS donor_name, donors.blood_type, donations.id AS donation_id, donations.date, donations.amount
    FROM donors
    INNER JOIN donations ON donors.id = donations.donor_id
    ORDER BY donors.name, donations.date DESC
";
$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Donor Donations</title>
    <style>
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
?></tr></body></style>
<?php
require_once '../includes/auth_check.php';
requireAdminAccess();
require_once '../includes/db.php';

$page_title = "Test Donor Matching";
include '../includes/header.php';
?>

<div class="container">
    <h1>Test Donor Matching</h1>
    
    <div class="card mt-4">
        <div class="card-header">
            <h3>Test Matching by Blood Type</h3>
        </div>
        <div class="card-body">
            <form method="GET" action="">
                <div class="mb-3">
                    <label for="blood_type" class="form-label">Blood Type:</label>
                    <select name="blood_type" id="blood_type" class="form-select">
                        <option value="">Select Blood Type</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Test Match</button>
            </form>
            
            <?php
            if (isset($_GET['blood_type']) && !empty($_GET['blood_type'])) {
                $blood_type = $conn->real_escape_string($_GET['blood_type']);
                
                echo "<hr><h4>Results for Blood Type: $blood_type</h4>";
                
                $query = "SELECT d.*, 
                         (SELECT COUNT(*) FROM blood_donations WHERE donor_id = d.id) as donation_count,
                         (SELECT MAX(donation_date) FROM blood_donations WHERE donor_id = d.id) as last_donation
                         FROM donors d 
                         WHERE d.blood_type = '$blood_type' 
                         AND d.is_active = 1
                         ORDER BY last_donation ASC, donation_count ASC";
                
                $result = $conn->query($query);
                
                if ($result && $result->num_rows > 0) {
                    echo "<div class='table-responsive mt-3'>";
                    echo "<table class='table table-striped'>";
                    echo "<tr><th>ID</th><th>Name</th><th>Blood Type</th><th>Last Donation</th><th>Total Donations</th></tr>";
                    
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['blood_type']) . "</td>";
                        echo "<td>" . ($row['last_donation'] ?: 'Never') . "</td>";
                        echo "<td>" . ($row['donation_count'] ?: '0') . "</td>";
                        echo "</tr>";
                    }
                    
                    echo "</table>";
                    echo "</div>";
                } else {
                    echo "<div class='alert alert-warning mt-3'>No matching donors found.</div>";
                    echo "<p>Query: " . htmlspecialchars($query) . "</p>";
                }
            }
            ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

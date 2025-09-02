<?php
require_once 'includes/db.php';

echo "<h2>Blood Banks Table Structure:</h2>";
$result = $conn->query('DESCRIBE blood_banks');
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error or table doesn't exist: " . $conn->error;
}

echo "<h2>Sample Data:</h2>";
$sample = $conn->query('SELECT * FROM blood_banks LIMIT 3');
if ($sample && $sample->num_rows > 0) {
    echo "<table border='1'>";
    $first = true;
    while ($row = $sample->fetch_assoc()) {
        if ($first) {
            echo "<tr>";
            foreach (array_keys($row) as $key) {
                echo "<th>$key</th>";
            }
            echo "</tr>";
            $first = false;
        }
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>$value</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "No data found or error: " . $conn->error;
}
?>

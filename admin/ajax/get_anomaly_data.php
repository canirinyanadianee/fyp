<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';
requireAdminAccess();

header('Content-Type: application/json');

// Function to mock anomaly detection - in a real app, this would use a proper ML model
function detectAnomalies($data) {
    $anomalies = [];
    
    // Simple anomaly detection: flag values that are 2 standard deviations from the mean
    $values = array_column($data, 'donation_count');
    $mean = array_sum($values) / count($values);
    $stdDev = stats_standard_deviation($values);
    
    foreach ($data as $item) {
        $zScore = $stdDev != 0 ? ($item['donation_count'] - $mean) / $stdDev : 0;
        if (abs($zScore) > 2) {
            $severity = abs($zScore) > 3 ? 'High' : (abs($zScore) > 2 ? 'Medium' : 'Low');
            $anomalies[] = [
                'date' => $item['donation_date'],
                'blood_type' => $item['blood_type'],
                'expected' => round($mean, 1),
                'actual' => $item['donation_count'],
                'deviation' => round(($item['donation_count'] - $mean) / $mean * 100, 1),
                'severity' => $severity,
                'z_score' => round($zScore, 2)
            ];
        }
    }
    
    return $anomalies;
}

// Function to calculate standard deviation
function stats_standard_deviation(array $a, $sample = false) {
    $n = count($a);
    if ($n === 0) {
        return 0.0;
    }
    if ($sample && $n === 1) {
        return 0.0;
    }
    $mean = array_sum($a) / $n;
    $sum = 0;
    foreach ($a as $val) {
        $d = ((double) $val) - $mean;
        $sum += $d * $d;
    };
    if ($sample) {
        $n--;
    }
    return sqrt($sum / $n);
}

try {
    // Get recent donation data (last 30 days by default)
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime("-$days days", strtotime($endDate)));
    
    $query = "SELECT 
                DATE(donation_date) as donation_date,
                blood_type,
                COUNT(*) as donation_count
              FROM donations 
              WHERE donation_date BETWEEN ? AND ?
              GROUP BY DATE(donation_date), blood_type
              ORDER BY donation_date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $donationData = $result->fetch_all(MYSQLI_ASSOC);
    
    // Process data for chart
    $chartData = [
        'labels' => [],
        'datasets' => []
    ];
    
    $groupedData = [];
    $dates = [];
    
    // Group data by blood type and date
    foreach ($donationData as $row) {
        $date = $row['donation_date'];
        $bloodType = $row['blood_type'];
        $count = (int)$row['donation_count'];
        
        if (!in_array($date, $dates)) {
            $dates[] = $date;
        }
        
        if (!isset($groupedData[$bloodType])) {
            $groupedData[$bloodType] = [];
        }
        
        $groupedData[$bloodType][$date] = $count;
    }
    
    // Sort dates
    sort($dates);
    $chartData['labels'] = $dates;
    
    // Prepare chart data for each blood type
    $bloodTypeColors = [
        'O+' => 'rgba(255, 99, 132, 1)',
        'O-' => 'rgba(54, 162, 235, 1)',
        'A+' => 'rgba(255, 206, 86, 1)',
        'A-' => 'rgba(75, 192, 192, 1)',
        'B+' => 'rgba(153, 102, 255, 1)',
        'B-' => 'rgba(255, 159, 64, 1)',
        'AB+' => 'rgba(199, 199, 199, 1)',
        'AB-' => 'rgba(83, 102, 255, 1)'
    ];
    
    $anomalyPoints = [];
    
    foreach ($groupedData as $bloodType => $data) {
        $color = $bloodTypeColors[$bloodType] ?? 'rgba(201, 203, 207, 1)';
        $dataset = [
            'label' => $bloodType,
            'data' => [],
            'borderColor' => $color,
            'backgroundColor' => str_replace('1)', '0.2)', $color),
            'borderWidth' => 2,
            'pointRadius' => 3,
            'pointHoverRadius' => 5,
            'tension' => 0.3
        ];
        
        foreach ($dates as $date) {
            $value = $data[$date] ?? 0;
            $dataset['data'][] = [
                'x' => $date,
                'y' => $value,
                'isAnomaly' => false
            ];
        }
        
        $chartData['datasets'][] = $dataset;
    }
    
    // Detect anomalies
    $anomalies = detectAnomalies($donationData);
    
    // Mark anomaly points in the chart data
    foreach ($anomalies as $anomaly) {
        $dateIndex = array_search($anomaly['date'], $dates);
        if ($dateIndex !== false) {
            foreach ($chartData['datasets'] as &$dataset) {
                if ($dataset['label'] === $anomaly['blood_type']) {
                    $dataset['data'][$dateIndex]['isAnomaly'] = true;
                    $dataset['data'][$dateIndex]['anomalyDetails'] = [
                        'severity' => $anomaly['severity'],
                        'deviation' => $anomaly['deviation'],
                        'expected' => $anomaly['expected']
                    ];
                }
            }
        }
    }
    
    // Prepare response
    $response = [
        'status' => 'success',
        'data' => [
            'chartData' => $chartData,
            'anomalies' => $anomalies,
            'summary' => [
                'totalAnomalies' => count($anomalies),
                'timeRange' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ]
        ]
    ];
    
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => 'An error occurred: ' . $e->getMessage(),
        'data' => null
    ];
}

echo json_encode($response);
$conn->close();
?>

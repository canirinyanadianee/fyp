<?php
require_once '../../includes/db_connect.php';
require_once '../../includes/auth_check.php';
requireAdminAccess();

header('Content-Type: application/json');

// Function to generate forecast data (mock implementation)
function generateForecast($historicalData, $daysToForecast = 30) {
    $forecast = [];
    $bloodTypes = array_unique(array_column($historicalData, 'blood_type'));
    $lastDate = max(array_column($historicalData, 'request_date'));
    
    foreach ($bloodTypes as $bloodType) {
        $typeData = array_filter($historicalData, function($item) use ($bloodType) {
            return $item['blood_type'] === $bloodType;
        });
        
        $values = array_column($typeData, 'request_count');
        $mean = count($values) > 0 ? array_sum($values) / count($values) : 0;
        $stdDev = stats_standard_deviation($values);
        
        // Generate forecast for next 30 days
        for ($i = 1; $i <= $daysToForecast; $i++) {
            $forecastDate = date('Y-m-d', strtotime("+$i days", strtotime($lastDate)));
            $dayOfWeek = date('N', strtotime($forecastDate));
            
            // Apply some seasonality (weekends typically have lower donation rates)
            $dayFactor = ($dayOfWeek >= 6) ? 0.8 : 1.0;
            
            // Add some randomness
            $randomFactor = 1 + (mt_rand(-20, 20) / 100);
            
            $forecastValue = round($mean * $dayFactor * $randomFactor);
            $confidenceRange = round($stdDev * 1.96); // 95% confidence interval
            
            // Get current inventory (mock data)
            $currentInventory = getCurrentInventory($bloodType);
            
            // Determine status
            $status = 'Adequate';
            $daysCoverage = $currentInventory > 0 ? round($currentInventory / ($forecastValue / 7)) : 0;
            
            if ($daysCoverage < 1) {
                $status = 'Critical';
            } elseif ($daysCoverage < 3) {
                $status = 'Low';
            } elseif ($daysCoverage > 14) {
                $status = 'Excess';
            }
            
            $forecast[] = [
                'date' => $forecastDate,
                'blood_type' => $bloodType,
                'forecasted_demand' => $forecastValue,
                'confidence_interval' => "±{$confidenceRange}",
                'current_inventory' => $currentInventory,
                'days_coverage' => $daysCoverage,
                'status' => $status
            ];
        }
    }
    
    return $forecast;
}

// Function to get current inventory (mock implementation)
function getCurrentInventory($bloodType) {
    // In a real app, this would query the database
    $baseInventory = [
        'O+' => 45,
        'O-' => 15,
        'A+' => 40,
        'A-' => 10,
        'B+' => 20,
        'B-' => 8,
        'AB+' => 12,
        'AB-' => 5
    ];
    
    // Add some randomness
    $randomFactor = mt_rand(80, 120) / 100;
    return round(($baseInventory[$bloodType] ?? 10) * $randomFactor);
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
    return $n > 0 ? sqrt($sum / $n) : 0;
}

try {
    // Get historical data (last 90 days by default)
    $days = isset($_GET['days']) ? (int)$_GET['days'] : 90;
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime("-$days days", strtotime($endDate)));
    
    $query = "SELECT 
                DATE(request_date) as request_date,
                blood_type,
                COUNT(*) as request_count
              FROM blood_requests 
              WHERE request_date BETWEEN ? AND ?
                AND status = 'completed'
              GROUP BY DATE(request_date), blood_type
              ORDER BY request_date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $startDate, $endDate);
    $stmt->execute();
    $result = $stmt->get_result();
    $historicalData = $result->fetch_all(MYSQLI_ASSOC);
    
    // Generate forecast
    $forecastData = generateForecast($historicalData, 30); // Forecast next 30 days
    
    // Prepare chart data
    $chartData = [
        'labels' => [],
        'datasets' => []
    ];
    
    // Group data by blood type
    $groupedData = [];
    $dates = [];
    
    // Add historical data
    foreach ($historicalData as $row) {
        $date = $row['request_date'];
        $bloodType = $row['blood_type'];
        $count = (int)$row['request_count'];
        
        if (!in_array($date, $dates)) {
            $dates[] = $date;
        }
        
        if (!isset($groupedData[$bloodType])) {
            $groupedData[$bloodType] = [
                'actual' => [],
                'forecast' => [],
                'confidenceInterval' => []
            ];
        }
        
        $groupedData[$bloodType]['actual'][] = [
            'x' => $date,
            'y' => $count
        ];
    }
    
    // Add forecast data
    foreach ($forecastData as $item) {
        $date = $item['date'];
        $bloodType = $item['blood_type'];
        $forecast = $item['forecasted_demand'];
        $confidence = (int)str_replace('±', '', $item['confidence_interval']);
        
        if (!in_array($date, $dates)) {
            $dates[] = $date;
        }
        
        if (!isset($groupedData[$bloodType])) {
            $groupedData[$bloodType] = [
                'actual' => [],
                'forecast' => [],
                'confidenceInterval' => []
            ];
        }
        
        $groupedData[$bloodType]['forecast'][] = [
            'x' => $date,
            'y' => $forecast
        ];
        
        $groupedData[$bloodType]['confidenceInterval'][] = [
            'x' => $date,
            'y' => $forecast - $confidence
        ];
        
        // Add upper bound point (in reverse order for polygon)
        if (count($groupedData[$bloodType]['confidenceInterval']) === 1) {
            $groupedData[$bloodType]['confidenceInterval'][] = [
                'x' => $date,
                'y' => $forecast + $confidence
            ];
        } else {
            array_splice($groupedData[$bloodType]['confidenceInterval'], 1, 0, [[
                'x' => $date,
                'y' => $forecast + $confidence
            ]]);
        }
    }
    
    // Sort dates
    usort($dates, function($a, $b) {
        return strtotime($a) - strtotime($b);
    });
    
    $chartData['labels'] = $dates;
    $chartData['datasets'] = $groupedData;
    
    // Prepare response
    $response = [
        'status' => 'success',
        'data' => [
            'chartData' => $chartData,
            'forecast' => $forecastData,
            'summary' => [
                'timeRange' => [
                    'start' => $startDate,
                    'end' => date('Y-m-d', strtotime('+30 days', strtotime($endDate)))
                ],
                'totalBloodTypes' => count(array_unique(array_column($forecastData, 'blood_type'))),
                'forecastDays' => 30
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

<?php
/**
 * AI Service Connector
 * Connects to the Python AI service for predictions and insights
 */

class AIConnector {
    private $ai_service_url = 'http://localhost:5000';
    private $timeout = 30;

    /**
     * Get blood usage predictions from AI service
     */
    public function getBloodPredictions() {
        $url = $this->ai_service_url . '/api/blood-predictions';
        
        $response = $this->makeRequest($url);
        
        if ($response && $response['success']) {
            return $response['predictions'];
        }
        
        // Fallback predictions if AI service is unavailable
        return $this->getFallbackPredictions();
    }

    /**
     * Get demand analysis from AI service
     */
    public function getDemandAnalysis() {
        $url = $this->ai_service_url . '/api/demand-analysis';
        
        $response = $this->makeRequest($url);
        
        if ($response && $response['success']) {
            return $response['demand_levels'];
        }
        
        return $this->getFallbackDemandAnalysis();
    }

    /**
     * Get donation insights for a specific donor
     */
    public function getDonationInsights($donor_id) {
        $url = $this->ai_service_url . '/api/donation-insights?donor_id=' . $donor_id;
        
        $response = $this->makeRequest($url);
        
        if ($response && $response['success']) {
            return $response['insights'];
        }
        
        return $this->getFallbackDonationInsights();
    }

    /**
     * Check if AI service is running
     */
    public function isServiceRunning() {
        $url = $this->ai_service_url . '/health';
        
        $response = $this->makeRequest($url, 5); // 5 second timeout for health check
        
        return $response !== false && isset($response['status']);
    }

    /**
     * Make HTTP request to AI service
     */
    private function makeRequest($url, $timeout = null) {
        $timeout = $timeout ?? $this->timeout;
        
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'GET',
                'header' => 'Content-Type: application/json'
            ]
        ]);

        try {
            $response = @file_get_contents($url, false, $context);
            
            if ($response === false) {
                error_log("AI Service request failed: $url");
                return false;
            }
            
            $decoded = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("AI Service JSON decode error: " . json_last_error_msg());
                return false;
            }
            
            return $decoded;
            
        } catch (Exception $e) {
            error_log("AI Service exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fallback predictions when AI service is unavailable
     */
    private function getFallbackPredictions() {
        return [
            [
                'blood_type' => 'A+',
                'current_stock' => '0 ml',
                'predicted_usage' => '3,854 ml',
                'status' => 'Shortage Predicted',
                'confidence' => '72%',
                'confidence_level' => 72
            ],
            [
                'blood_type' => 'A-',
                'current_stock' => '0 ml',
                'predicted_usage' => '4,275 ml',
                'status' => 'Shortage Predicted',
                'confidence' => '72%',
                'confidence_level' => 72
            ],
            [
                'blood_type' => 'B+',
                'current_stock' => '0 ml',
                'predicted_usage' => '3,655 ml',
                'status' => 'Shortage Predicted',
                'confidence' => '72%',
                'confidence_level' => 72
            ],
            [
                'blood_type' => 'B-',
                'current_stock' => '0 ml',
                'predicted_usage' => '3,347 ml',
                'status' => 'Shortage Predicted',
                'confidence' => '72%',
                'confidence_level' => 72
            ],
            [
                'blood_type' => 'AB+',
                'current_stock' => '0 ml',
                'predicted_usage' => '3,545 ml',
                'status' => 'Shortage Predicted',
                'confidence' => '72%',
                'confidence_level' => 72
            ],
            [
                'blood_type' => 'AB-',
                'current_stock' => '0 ml',
                'predicted_usage' => '3,149 ml',
                'status' => 'Shortage Predicted',
                'confidence' => '72%',
                'confidence_level' => 72
            ],
            [
                'blood_type' => 'O+',
                'current_stock' => '0 ml',
                'predicted_usage' => '3,325 ml',
                'status' => 'Shortage Predicted',
                'confidence' => '72%',
                'confidence_level' => 72
            ],
            [
                'blood_type' => 'O-',
                'current_stock' => '0 ml',
                'predicted_usage' => '2,491 ml',
                'status' => 'Shortage Predicted',
                'confidence' => '72%',
                'confidence_level' => 72
            ]
        ];
    }

    /**
     * Fallback demand analysis when AI service is unavailable
     */
    private function getFallbackDemandAnalysis() {
        return [
            ['blood_type' => 'A+', 'demand_level' => 'High', 'request_count' => 15],
            ['blood_type' => 'A-', 'demand_level' => 'Medium', 'request_count' => 8],
            ['blood_type' => 'B+', 'demand_level' => 'Medium', 'request_count' => 7],
            ['blood_type' => 'B-', 'demand_level' => 'Low', 'request_count' => 3],
            ['blood_type' => 'AB+', 'demand_level' => 'Low', 'request_count' => 2],
            ['blood_type' => 'AB-', 'demand_level' => 'Low', 'request_count' => 1],
            ['blood_type' => 'O+', 'demand_level' => 'High', 'request_count' => 12],
            ['blood_type' => 'O-', 'demand_level' => 'Medium', 'request_count' => 6]
        ];
    }

    /**
     * Fallback donation insights when AI service is unavailable
     */
    private function getFallbackDonationInsights() {
        return [
            'blood_type_impact' => 'Your blood type is currently in moderate demand. Donations can help save lives!',
            'current_demand' => 'Moderate Demand',
            'optimal_times' => [
                'Wednesday Morning',
                'Saturday Afternoon',
                'Monday Evening'
            ],
            'impact_message' => 'Your blood donations make a significant difference in your community.'
        ];
    }
}

// Global AI connector instance
$ai_connector = new AIConnector();
?>

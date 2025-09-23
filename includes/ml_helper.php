<?php
/**
 * ML Helper Functions
 * 
 * Provides PHP interfaces to the Python ML models
 */

class MLHelper {
    private $pythonPath = 'python';
    private $scriptPath = __DIR__ . '/../ml';
    
    public function __construct() {
        // You can override the Python path in your config if needed
        if (defined('PYTHON_PATH')) {
            $this->pythonPath = PYTHON_PATH;
        }
    }
    
    /**
     * Get demand forecast for a blood type
     * 
     * @param int $bloodBankId
     * @param string $bloodType
     * @param int $days
     * @return array
     */
    public function getDemandForecast($bloodBankId, $bloodType = null, $days = 30) {
        $command = sprintf(
            '%s %s/demand_forecast.py --blood_bank_id %d --days %d',
            $this->pythonPath,
            $this->scriptPath,
            $bloodBankId,
            $days
        );
        
        if ($bloodType) {
            $command .= ' --blood_type ' . escapeshellarg($bloodType);
        }
        
        return $this->executePythonScript($command);
    }
    
    /**
     * Get shortage predictions
     * 
     * @param int $bloodBankId
     * @param int $forecastDays
     * @return array
     */
    public function getShortagePredictions($bloodBankId, $forecastDays = 7) {
        $command = sprintf(
            '%s %s/shortage_prediction.py --blood_bank_id %d --days %d',
            $this->pythonPath,
            $this->scriptPath,
            $bloodBankId,
            $forecastDays
        );
        
        return $this->executePythonScript($command);
    }
    
    /**
     * Get storage optimization recommendations
     * 
     * @param int $bloodBankId
     * @return array
     */
    public function getStorageRecommendations($bloodBankId) {
        $command = sprintf(
            '%s %s/storage_optimization.py --blood_bank_id %d',
            $this->pythonPath,
            $this->scriptPath,
            $bloodBankId
        );
        
        return $this->executePythonScript($command);
    }
    
    /**
     * Execute a Python script and return the JSON output
     * 
     * @param string $command
     * @return array
     */
    private function executePythonScript($command) {
        // Add environment variables if needed
        $env = [
            'DB_HOST' => DB_HOST,
            'DB_NAME' => DB_NAME,
            'DB_USER' => DB_USER,
            'DB_PASS' => DB_PASS,
        ];
        
        $envVars = '';
        foreach ($env as $key => $value) {
            $envVars .= $key . '=' . escapeshellarg($value) . ' ';
        }
        
        $command = $envVars . $command . ' 2>&1';
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            error_log("Python script error: " . implode("\n", $output));
            return [
                'success' => false,
                'error' => 'Failed to execute ML model',
                'details' => $output
            ];
        }
        
        $json = json_decode(implode("\n", $output), true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response from ML model',
                'details' => $output
            ];
        }
        
        return $json;
    }
}

// Helper function to get ML Helper instance
function getMLHelper() {
    static $mlHelper = null;
    
    if ($mlHelper === null) {
        $mlHelper = new MLHelper();
    }
    
    return $mlHelper;
}

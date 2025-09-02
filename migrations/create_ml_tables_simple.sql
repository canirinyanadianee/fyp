-- Drop tables if they exist to avoid conflicts
DROP TABLE IF EXISTS ml_demand_predictions;
DROP TABLE IF EXISTS ml_inventory_data;
DROP TABLE IF EXISTS ml_blood_demand_data;

-- Table for storing demand predictions
CREATE TABLE IF NOT EXISTS ml_demand_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prediction_date DATE NOT NULL,
    blood_type VARCHAR(5) NOT NULL,
    predicted_demand_7d INT NOT NULL DEFAULT 0,
    predicted_demand_30d INT NOT NULL DEFAULT 0,
    confidence FLOAT DEFAULT 0.0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_prediction (prediction_date, blood_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for storing inventory data
CREATE TABLE IF NOT EXISTS ml_inventory_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    blood_type VARCHAR(5) NOT NULL,
    quantity_ml INT NOT NULL,
    expiry_date DATE NOT NULL,
    blood_bank_id INT NOT NULL,
    wastage_ml INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blood_type (blood_type),
    INDEX idx_expiry (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for storing historical demand data
CREATE TABLE IF NOT EXISTS ml_blood_demand_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    blood_type VARCHAR(5) NOT NULL,
    requested_units INT NOT NULL,
    fulfilled_units INT NOT NULL,
    hospital_id INT NULL,
    is_emergency BOOLEAN DEFAULT FALSE,
    season VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blood_type (blood_type),
    INDEX idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert some sample data for testing
INSERT INTO ml_blood_demand_data 
(date, blood_type, requested_units, fulfilled_units, hospital_id, is_emergency, season)
VALUES 
    (CURDATE() - INTERVAL 7 DAY, 'A+', 10, 8, 1, 0, 'Summer'),
    (CURDATE() - INTERVAL 6 DAY, 'A+', 8, 8, 2, 1, 'Summer'),
    (CURDATE() - INTERVAL 5 DAY, 'B+', 15, 12, 1, 0, 'Summer'),
    (CURDATE() - INTERVAL 4 DAY, 'O+', 20, 18, 3, 1, 'Summer');

-- Generate some sample predictions
INSERT INTO ml_demand_predictions 
(prediction_date, blood_type, predicted_demand_7d, predicted_demand_30d, confidence)
VALUES 
    (CURDATE(), 'A+', 12, 45, 0.85),
    (CURDATE(), 'B+', 8, 32, 0.82),
    (CURDATE(), 'O+', 25, 90, 0.88),
    (CURDATE(), 'AB+', 5, 18, 0.75);

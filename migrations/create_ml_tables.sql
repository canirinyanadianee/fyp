-- Migration: create_ml_tables.sql
-- Adds ML-related tables: ai_learning_data, ai_models, ai_model_versions,
-- ai_predictions, ai_forecasts, ai_recommendations, ai_eligibility_checks,
-- ai_anomalies, ml_training_jobs, donor_locations
-- Run this in phpMyAdmin or mysql client against your blood_management database.

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS ai_learning_data (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  source_table VARCHAR(64) NOT NULL,
  source_id BIGINT NULL,
  hospital_id INT NULL,
  blood_type VARCHAR(5) NULL,
  event_date DATE NULL,
  features JSON NOT NULL,
  label JSON NULL,
  partition_tag VARCHAR(32) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_learning_hosp (hospital_id),
  INDEX idx_ai_learning_blood (blood_type),
  INDEX idx_ai_learning_date (event_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_models (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(128) NOT NULL,
  task_type VARCHAR(64) NOT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_model_versions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  model_id INT NOT NULL,
  version VARCHAR(64) NOT NULL,
  artifact_path VARCHAR(255) NULL,
  params JSON NULL,
  metrics JSON NULL,
  trained_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (model_id) REFERENCES ai_models(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_predictions (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  model_version_id BIGINT NULL,
  hospital_id INT NULL,
  target_date DATE NULL,
  blood_type VARCHAR(5) NULL,
  prediction JSON NOT NULL,
  confidence FLOAT NULL,
  horizon_days INT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_pred_hosp_date (hospital_id, target_date),
  FOREIGN KEY (model_version_id) REFERENCES ai_model_versions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_forecasts (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  hospital_id INT NULL,
  blood_type VARCHAR(5) NOT NULL,
  forecast_date DATE NOT NULL,
  horizon_days INT NOT NULL,
  predicted_quantity_ml INT NOT NULL,
  lower_ml INT NULL,
  upper_ml INT NULL,
  model_version_id BIGINT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_forecast_hosp (hospital_id, blood_type, forecast_date),
  FOREIGN KEY (model_version_id) REFERENCES ai_model_versions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_recommendations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT NULL,
  hospital_id INT NULL,
  blood_type VARCHAR(5) NULL,
  recommended JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_recom_request (request_id),
  INDEX idx_ai_recom_hosp (hospital_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_eligibility_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  donor_id INT NOT NULL,
  checked_by VARCHAR(64) NULL,
  features JSON NOT NULL,
  result JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ai_elig_donor (donor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_anomalies (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  anomaly_type VARCHAR(64) NOT NULL,
  details JSON NOT NULL,
  score FLOAT NULL,
  resolved BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ml_training_jobs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  model_id INT NULL,
  job_name VARCHAR(128) NULL,
  status ENUM('pending','running','success','failed') DEFAULT 'pending',
  params JSON NULL,
  metrics JSON NULL,
  log TEXT NULL,
  started_at TIMESTAMP NULL,
  finished_at TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (model_id) REFERENCES ai_models(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional donor_locations: use PostGIS for production, fallback to lat/lon numeric for MySQL
CREATE TABLE IF NOT EXISTS donor_locations (
  donor_id BIGINT PRIMARY KEY,
  latitude DECIMAL(9,6) NULL,
  longitude DECIMAL(9,6) NULL,
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_donor_latlon (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ML Data Collection Tables
CREATE TABLE IF NOT EXISTS ml_blood_demand_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    blood_type VARCHAR(5) NOT NULL,
    requested_units INT NOT NULL,
    fulfilled_units INT NOT NULL,
    hospital_id INT,
    is_emergency BOOLEAN DEFAULT FALSE,
    season VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_blood_type (blood_type),
    INDEX idx_date (date)
);

CREATE TABLE IF NOT EXISTS ml_inventory_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL,
    blood_type VARCHAR(5) NOT NULL,
    quantity_ml INT NOT NULL,
    expiry_date DATE NOT NULL,
    blood_bank_id INT NOT NULL,
    wastage_ml INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blood_bank_id) REFERENCES blood_banks(id),
    INDEX idx_blood_type (blood_type),
    INDEX idx_expiry (expiry_date)
);

-- ML Prediction Tables
CREATE TABLE IF NOT EXISTS ml_demand_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prediction_date DATE NOT NULL,
    blood_type VARCHAR(5) NOT NULL,
    predicted_demand_7d INT NOT NULL,
    predicted_demand_30d INT NOT NULL,
    confidence FLOAT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_prediction (prediction_date, blood_type)
);

CREATE TABLE IF NOT EXISTS ml_expiry_predictions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prediction_date DATE NOT NULL,
    blood_type VARCHAR(5) NOT NULL,
    units_at_risk_7d INT NOT NULL,
    units_at_risk_30d INT NOT NULL,
    suggested_actions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_expiry_pred (prediction_date, blood_type)
);

-- ML Model Metadata
CREATE TABLE IF NOT EXISTS ml_models (
    id INT AUTO_INCREMENT PRIMARY KEY,
    model_name VARCHAR(100) NOT NULL,
    model_version VARCHAR(50) NOT NULL,
    model_path VARCHAR(255) NOT NULL,
    metrics JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_model (model_name, model_version)
);

-- Add sample data collection triggers
DELIMITER //
CREATE TRIGGER after_blood_request
AFTER INSERT ON blood_requests
FOR EACH ROW
BEGIN
    INSERT INTO ml_blood_demand_data 
    (date, blood_type, requested_units, fulfilled_units, hospital_id, is_emergency, season)
    VALUES (
        CURDATE(),
        NEW.blood_type,
        NEW.quantity_ml / 450,  -- Convert ml to units (1 unit ≈ 450ml)
        NEW.fulfilled_quantity / 450,
        NEW.hospital_id,
        NEW.is_emergency,
        CASE 
            WHEN MONTH(CURDATE()) IN (12,1,2) THEN 'Winter'
            WHEN MONTH(CURDATE()) BETWEEN 3 AND 5 THEN 'Spring'
            WHEN MONTH(CURDATE()) BETWEEN 6 AND 8 THEN 'Summer'
            ELSE 'Fall'
        END
    );
END //

CREATE TRIGGER after_inventory_update
AFTER UPDATE ON blood_inventory
FOR EACH ROW
BEGIN
    IF OLD.quantity_ml > NEW.quantity_ml THEN
        -- Record usage/wastage
        INSERT INTO ml_inventory_data 
        (date, blood_type, quantity_ml, expiry_date, blood_bank_id, wastage_ml)
        VALUES (
            CURDATE(),
            OLD.blood_type,
            OLD.quantity_ml - NEW.quantity_ml,
            OLD.expiry_date,
            OLD.blood_bank_id,
            IF(DATEDIFF(OLD.expiry_date, CURDATE()) <= 0, OLD.quantity_ml - NEW.quantity_ml, 0)
        );
    END IF;
END //

DELIMITER ;

SET FOREIGN_KEY_CHECKS = 1;

-- Notes:
-- 1) Run this file in the same DB as your application (blood_management or blood_management_db)
-- 2) If you use Postgres/PostGIS, convert donor_locations to a geometry(Point,4326) with GIST index.
-- 3) After creating tables, you can populate ai_learning_data by aggregating from blood_usage and blood_donations.
-- 4) If your schema already has ai_notifications, don't drop it; the cron wrapper writes to it.

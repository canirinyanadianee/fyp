-- Migration: Create AI/ML support tables (Donor/Hospital/BloodBank partitions)
-- Run this in phpMyAdmin or via mysql CLI against your database (blood_management)

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
  INDEX (hospital_id),
  INDEX (blood_type),
  INDEX (event_date)
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
  INDEX (hospital_id),
  INDEX (target_date),
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
  INDEX (hospital_id, blood_type, forecast_date),
  FOREIGN KEY (model_version_id) REFERENCES ai_model_versions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_recommendations (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  request_id BIGINT NULL,
  hospital_id INT NULL,
  blood_type VARCHAR(5) NULL,
  recommended JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (request_id),
  INDEX (hospital_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_eligibility_checks (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  donor_id INT NOT NULL,
  checked_by VARCHAR(64) NULL,
  features JSON NOT NULL,
  result JSON NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (donor_id)
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

-- Add proposal metadata to existing blood_transfers for safe automation
ALTER TABLE blood_transfers 
  ADD COLUMN proposal_origin VARCHAR(64) NULL AFTER request_type,
  ADD COLUMN proposed_by VARCHAR(64) NULL AFTER proposal_origin;

-- Optional: donor_locations table for geospatial queries (lat/lon)
CREATE TABLE IF NOT EXISTS donor_locations (
  donor_id BIGINT PRIMARY KEY,
  latitude DECIMAL(9,6) NULL,
  longitude DECIMAL(9,6) NULL,
  last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (latitude),
  INDEX (longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Done
SELECT 'migration_complete' as status;

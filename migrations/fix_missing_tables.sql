-- Add missing tables for blood bank system

-- Create blood_screening table
CREATE TABLE IF NOT EXISTS blood_screening (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    blood_bank_id INT NOT NULL,
    hemoglobin_level DECIMAL(5,2) NOT NULL,
    blood_pressure VARCHAR(20) NOT NULL,
    pulse_rate INT NOT NULL,
    temperature DECIMAL(4,2) NOT NULL,
    weight_kg DECIMAL(5,2) NOT NULL,
    is_eligible BOOLEAN DEFAULT TRUE,
    notes TEXT,
    screening_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (donation_id) REFERENCES donations(id) ON DELETE CASCADE,
    FOREIGN KEY (blood_bank_id) REFERENCES blood_banks(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add created_at column to blood_transfers if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'blood_transfers';
SET @columnname = 'created_at';
SET @preparedStatement = (SELECT IF(
    (
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
        WHERE (TABLE_SCHEMA = @dbname)
        AND (TABLE_NAME = @tablename)
        AND (COLUMN_NAME = @columnname)
    ) = 0,
    "ALTER TABLE blood_transfers ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER status",
    'SELECT 1;'
));

PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Create donations table if it doesn't exist
CREATE TABLE IF NOT EXISTS donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    blood_bank_id INT NOT NULL,
    donation_date DATE NOT NULL,
    blood_type ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-') NOT NULL,
    quantity_ml INT NOT NULL,
    status ENUM('pending', 'screened', 'processed', 'rejected') DEFAULT 'pending',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(id) ON DELETE CASCADE,
    FOREIGN KEY (blood_bank_id) REFERENCES blood_banks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

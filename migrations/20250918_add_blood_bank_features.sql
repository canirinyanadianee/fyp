-- Add tables for Blood Bank Management System features

-- Blood screening results table
CREATE TABLE IF NOT EXISTS blood_screening (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    hiv_test BOOLEAN DEFAULT FALSE,
    hepatitis_b_test BOOLEAN DEFAULT FALSE,
    hepatitis_c_test BOOLEAN DEFAULT FALSE,
    syphilis_test BOOLEAN DEFAULT FALSE,
    test_notes TEXT,
    tested_by INT NOT NULL,
    test_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'passed', 'failed') DEFAULT 'pending',
    FOREIGN KEY (donation_id) REFERENCES blood_donations(id) ON DELETE CASCADE,
    FOREIGN KEY (tested_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Blood distribution logs
CREATE TABLE IF NOT EXISTS distribution_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blood_inventory_id INT NOT NULL,
    hospital_id INT NOT NULL,
    quantity_ml INT NOT NULL,
    distributed_by INT NOT NULL,
    distribution_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (blood_inventory_id) REFERENCES blood_inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (hospital_id) REFERENCES hospitals(id) ON DELETE CASCADE,
    FOREIGN KEY (distributed_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Blood wastage records
CREATE TABLE IF NOT EXISTS wastage_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blood_inventory_id INT NOT NULL,
    quantity_ml INT NOT NULL,
    reason ENUM('expired', 'contaminated', 'damaged', 'other') NOT NULL,
    notes TEXT,
    recorded_by INT NOT NULL,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blood_inventory_id) REFERENCES blood_inventory(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Add blood bank staff roles
ALTER TABLE users 
MODIFY COLUMN role ENUM('admin', 'donor', 'bloodbank_staff', 'bloodbank_manager', 'hospital') NOT NULL;

-- Add indexes for better performance
CREATE INDEX idx_blood_donations_status ON blood_donations(status);
CREATE INDEX idx_blood_inventory_status ON blood_inventory(status);
CREATE INDEX idx_blood_transfers_status ON blood_transfers(status);

-- Add view for blood bank dashboard
CREATE OR REPLACE VIEW blood_bank_dashboard AS
SELECT 
    bb.id AS blood_bank_id,
    bb.name AS blood_bank_name,
    bt.blood_type,
    COALESCE(SUM(CASE WHEN bi.status = 'available' THEN bi.quantity_ml ELSE 0 END), 0) AS available_ml,
    COALESCE(SUM(CASE WHEN bi.status = 'reserved' THEN bi.quantity_ml ELSE 0 END), 0) AS reserved_ml,
    COUNT(CASE WHEN bi.expiry_date < CURDATE() + INTERVAL 7 DAY THEN 1 END) AS expiring_soon_count,
    COUNT(CASE WHEN bd.status = 'collected' THEN 1 END) AS pending_screening_count,
    COUNT(CASE WHEN bt.status = 'requested' THEN 1 END) AS pending_transfer_requests
FROM blood_banks bb
LEFT JOIN blood_inventory bi ON bb.id = bi.blood_bank_id
LEFT JOIN blood_donations bd ON bb.id = bd.blood_bank_id
LEFT JOIN blood_transfers bt ON bb.id = bt.blood_bank_id
GROUP BY bb.id, bb.name, bt.blood_type;

-- Add view for blood bank analytics
CREATE OR REPLACE VIEW blood_bank_analytics AS
SELECT 
    bb.id AS blood_bank_id,
    bb.name AS blood_bank_name,
    DATE(bd.donation_date) AS donation_date,
    bd.blood_type,
    COUNT(bd.id) AS donation_count,
    SUM(bd.quantity_ml) AS total_ml_donated,
    COUNT(DISTINCT bd.donor_id) AS unique_donors,
    AVG(bd.quantity_ml) AS avg_donation_ml,
    COUNT(CASE WHEN bs.status = 'passed' THEN 1 END) AS passed_screening,
    COUNT(CASE WHEN bs.status = 'failed' THEN 1 END) AS failed_screening
FROM blood_banks bb
LEFT JOIN blood_donations bd ON bb.id = bd.blood_bank_id
LEFT JOIN blood_screening bs ON bd.id = bs.donation_id
WHERE bd.donation_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY bb.id, bb.name, DATE(bd.donation_date), bd.blood_type
ORDER BY donation_date DESC;

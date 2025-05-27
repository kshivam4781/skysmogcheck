ALTER TABLE vehicles
ADD COLUMN smoke_test_status ENUM('pending', 'passed', 'failed', 'warmup') DEFAULT 'pending',
ADD COLUMN smoke_test_notes TEXT,
ADD COLUMN result ENUM('pass', 'fail', 'warmup') DEFAULT NULL,
ADD COLUMN error_code VARCHAR(50),
ADD COLUMN warm_up INT,
ADD COLUMN next_due_date DATE; 
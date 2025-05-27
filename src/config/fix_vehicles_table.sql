-- First, modify the result enum to match the code
ALTER TABLE vehicles 
MODIFY COLUMN result ENUM('pass', 'fail', 'warmup') DEFAULT NULL;

-- Add the missing smoke_test_status column
ALTER TABLE vehicles
ADD COLUMN smoke_test_status ENUM('pending', 'passed', 'failed', 'warmup') DEFAULT 'pending';

-- Modify smoke_test_notes to be TEXT type
ALTER TABLE vehicles
MODIFY COLUMN smoke_test_notes TEXT; 
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    companyName VARCHAR(100) NOT NULL,
    Name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    bookingDate DATE NOT NULL,
    bookingTime TIME NOT NULL,
    test_location ENUM('our_location', 'your_location') NOT NULL,
    test_address TEXT,
    special_instructions TEXT,
    number_of_vehicles INT NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
); 
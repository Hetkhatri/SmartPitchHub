-- SmartPitchHub Database Setup
-- Create database
CREATE DATABASE IF NOT EXISTS smartpitchhub;
USE smartpitchhub;

-- Create otp_verifications table
CREATE TABLE IF NOT EXISTS otp_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    contact VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('entrepreneur', 'investor') NOT NULL,
    startup_name VARCHAR(255),
    otp VARCHAR(6) NOT NULL,
    status ENUM('pending', 'verified') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create entrepreneurs table
CREATE TABLE IF NOT EXISTS entrepreneurs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    contact VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    startup_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create investors table
CREATE TABLE IF NOT EXISTS investors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    contact VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create users table (for unified access)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    contact VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('entrepreneur', 'investor') NOT NULL,
    startup_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create pitches table
CREATE TABLE IF NOT EXISTS pitches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entrepreneur_id INT NOT NULL,
    startup_name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL,
    funding_goal DECIMAL(15,2) NOT NULL,
    status ENUM('draft', 'active', 'inactive', 'deleted') DEFAULT 'draft',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (entrepreneur_id) REFERENCES entrepreneurs(id) ON DELETE CASCADE
);

-- Create investments table
CREATE TABLE IF NOT EXISTS investments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    investor_id INT NOT NULL,
    pitch_id INT NOT NULL,
    status ENUM('new', 'contacted', 'scheduled', 'completed') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (investor_id) REFERENCES investors(id) ON DELETE CASCADE,
    FOREIGN KEY (pitch_id) REFERENCES pitches(id) ON DELETE CASCADE
);

-- Create admins table
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT IGNORE INTO admins (name, email, password) VALUES
('Admin User', 'admin@smartpitchhub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
('Het', 'het@smartpitchhub.com', '$2y$10$Ay/7T9dMOlI5KReYZcktJuCLPt9dSqXMVTFpvpElvYeeKI6hbK4Tm');

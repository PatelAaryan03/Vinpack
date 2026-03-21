-- Vinpack Database Schema
-- Create database and tables for inquiry management

-- Create Database
CREATE DATABASE IF NOT EXISTS vinpack;
USE vinpack;

-- Create Inquiries Table
CREATE TABLE IF NOT EXISTS inquiries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20) NOT NULL,
    product_interest VARCHAR(255) NOT NULL,
    message LONGTEXT NOT NULL,
    status ENUM('new', 'contacted', 'completed', 'spam') DEFAULT 'new',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    admin_notes LONGTEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_status (status),
    INDEX idx_submitted_at (submitted_at),
    INDEX idx_email (email)
);

-- Create Deleted Inquiries Table (Archive)
CREATE TABLE IF NOT EXISTS deleted_inquiries (
    id INT PRIMARY KEY AUTO_INCREMENT,
    original_inquiry_id INT NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    contact_name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(20) NOT NULL,
    product_interest VARCHAR(255) NOT NULL,
    message LONGTEXT NOT NULL,
    status VARCHAR(50),
    submitted_at TIMESTAMP,
    admin_notes LONGTEXT,
    deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_by VARCHAR(255),
    
    INDEX idx_original_id (original_inquiry_id),
    INDEX idx_deleted_at (deleted_at),
    INDEX idx_email (email)
);

-- Create Products Table (Optional - for reference)
CREATE TABLE IF NOT EXISTS products (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample products
INSERT INTO products (name, description) VALUES
('HDPE/PP Woven Fabrics', 'High-density polyethylene and polypropylene woven fabrics'),
('FIBC / Jumbo Bags', 'Flexible Intermediate Bulk Containers'),
('BOPP Bags', 'Biaxially Oriented Polypropylene bags'),
('Woven Sacks', 'Reliable and cost-effective woven sacks'),
('Multifilament Yarns', 'High-tenacity multifilament yarns'),
('Polyester Yarn', 'Premium quality polyester yarn'),
('Custom Solution', 'Custom packaging solutions');

-- Display confirmation
SELECT 'Database setup completed successfully!' AS message;
SELECT COUNT(*) AS total_inquiries FROM inquiries;
SELECT COUNT(*) AS total_products FROM products;

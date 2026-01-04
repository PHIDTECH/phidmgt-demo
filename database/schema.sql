-- PhidTech Management System Database Schema

CREATE DATABASE IF NOT EXISTS phidtech_db;
USE phidtech_db;

-- Users Table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    role ENUM('ceo', 'manager', 'accountant', 'hr', 'supervisor', 'it_staff', 'junior_staff', 'customer') NOT NULL DEFAULT 'customer',
    department VARCHAR(50),
    salary DECIMAL(15, 2) DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Services Table
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    base_price DECIMAL(15, 2) NOT NULL,
    duration_days INT DEFAULT 7,
    features TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Projects/Orders Table
CREATE TABLE projects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    reference VARCHAR(50) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    service_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    requirements TEXT,
    total_amount DECIMAL(15, 2) NOT NULL,
    paid_amount DECIMAL(15, 2) DEFAULT 0,
    status ENUM('pending', 'payment_received', 'in_progress', 'review', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_to INT,
    start_date DATE,
    due_date DATE,
    completed_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES users(id),
    FOREIGN KEY (service_id) REFERENCES services(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Tasks Table
CREATE TABLE tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    assigned_to INT,
    assigned_by INT,
    status ENUM('pending', 'in_progress', 'submitted', 'approved', 'rejected') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    due_date DATE,
    completed_date DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (assigned_by) REFERENCES users(id)
);

-- Payments Table
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT NOT NULL,
    reference VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    payment_date DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id)
);

-- Accounting - Revenue Table
CREATE TABLE revenue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    project_id INT,
    payment_id INT,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    category VARCHAR(50),
    date DATE NOT NULL,
    recorded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (payment_id) REFERENCES payments(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- Accounting - Expenses Table
CREATE TABLE expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    category VARCHAR(50),
    date DATE NOT NULL,
    receipt_path VARCHAR(255),
    approved_by INT,
    recorded_by INT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id)
);

-- Accounting - Assets Table
CREATE TABLE assets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    purchase_value DECIMAL(15, 2) NOT NULL,
    current_value DECIMAL(15, 2) NOT NULL,
    purchase_date DATE,
    depreciation_rate DECIMAL(5, 2) DEFAULT 0,
    category VARCHAR(50),
    status ENUM('active', 'disposed', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Accounting - Liabilities Table
CREATE TABLE liabilities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    description VARCHAR(255) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    creditor VARCHAR(100),
    due_date DATE,
    status ENUM('active', 'paid', 'overdue') DEFAULT 'active',
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Petty Cash Table
CREATE TABLE petty_cash (
    id INT PRIMARY KEY AUTO_INCREMENT,
    description VARCHAR(255) NOT NULL,
    type ENUM('deposit', 'withdrawal') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    balance DECIMAL(15, 2) NOT NULL,
    date DATE NOT NULL,
    recorded_by INT,
    approved_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Salary Records Table
CREATE TABLE salary_records (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    month VARCHAR(7) NOT NULL,
    basic_salary DECIMAL(15, 2) NOT NULL,
    allowances DECIMAL(15, 2) DEFAULT 0,
    deductions DECIMAL(15, 2) DEFAULT 0,
    net_salary DECIMAL(15, 2) NOT NULL,
    status ENUM('pending', 'paid') DEFAULT 'pending',
    payment_date DATE,
    processed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (processed_by) REFERENCES users(id)
);

-- Leave Applications Table
CREATE TABLE leave_applications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    leave_type ENUM('annual', 'sick', 'maternity', 'paternity', 'emergency', 'unpaid') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days INT NOT NULL,
    reason TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT,
    approval_date DATETIME,
    comments TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Messages/Notifications Table
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT,
    recipient_id INT NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('notification', 'task', 'payment', 'project', 'leave', 'general') DEFAULT 'general',
    reference_type VARCHAR(50),
    reference_id INT,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (recipient_id) REFERENCES users(id)
);

-- Activity Log Table
CREATE TABLE activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert Default Users (passwords: admin123, manager123, acc123, hr123, super123, it123, junior123)
-- All passwords below are bcrypt hashes
INSERT INTO users (name, email, password, phone, role, department, salary) VALUES
('John Mwamba', 'admin@phidtech.com', '$2y$10$YourHashHere1234567890abcdefghijklmnopqrstuvwxyzABCD', '+255700000001', 'ceo', 'Executive', 5000000),
('Sarah Kimaro', 'manager@phidtech.com', '$2y$10$YourHashHere1234567890abcdefghijklmnopqrstuvwxyzABCD', '+255700000002', 'manager', 'Operations', 3000000),
('Peter Msemwa', 'accountant@phidtech.com', '$2y$10$YourHashHere1234567890abcdefghijklmnopqrstuvwxyzABCD', '+255700000003', 'accountant', 'Finance', 2500000),
('Grace Mushi', 'hr@phidtech.com', '$2y$10$YourHashHere1234567890abcdefghijklmnopqrstuvwxyzABCD', '+255700000004', 'hr', 'Human Resources', 2500000),
('James Njau', 'supervisor@phidtech.com', '$2y$10$YourHashHere1234567890abcdefghijklmnopqrstuvwxyzABCD', '+255700000005', 'supervisor', 'IT', 2000000),
('Alice Kapinga', 'it@phidtech.com', '$2y$10$YourHashHere1234567890abcdefghijklmnopqrstuvwxyzABCD', '+255700000006', 'it_staff', 'IT', 1500000),
('Michael Swai', 'junior@phidtech.com', '$2y$10$YourHashHere1234567890abcdefghijklmnopqrstuvwxyzABCD', '+255700000007', 'junior_staff', 'IT', 800000);

-- Insert Default Services
INSERT INTO services (name, description, category, base_price, duration_days, features) VALUES
('Basic Website', 'Professional 5-page responsive website with modern design', 'Web Development', 500000, 7, 'Responsive Design, Contact Form, SEO Basic, 1 Month Support'),
('E-Commerce Website', 'Full-featured online store with payment integration', 'Web Development', 2000000, 21, 'Product Management, Payment Gateway, Order Tracking, Admin Dashboard, 3 Months Support'),
('Custom Web Application', 'Tailored web application for your business needs', 'Web Development', 5000000, 45, 'Custom Features, Database Design, API Integration, User Management, 6 Months Support'),
('Mobile App Development', 'Native or cross-platform mobile application', 'Mobile Development', 8000000, 60, 'iOS & Android, Push Notifications, API Integration, App Store Submission'),
('Logo Design', 'Professional logo design with multiple concepts', 'Graphics Design', 150000, 3, '3 Concepts, Unlimited Revisions, Source Files, Brand Guidelines'),
('Social Media Kit', 'Complete social media branding package', 'Graphics Design', 300000, 5, 'Profile Images, Cover Photos, Post Templates, Story Templates'),
('SEO Package', 'Search engine optimization for better visibility', 'Digital Marketing', 400000, 30, 'Keyword Research, On-page SEO, Technical SEO, Monthly Reports'),
('IT Consultation', 'Expert IT consultation and system analysis', 'Consultation', 200000, 1, 'System Analysis, Recommendations, Implementation Plan, Follow-up Support'),
('Domain & Hosting', 'Annual domain registration and web hosting', 'Hosting', 150000, 365, '.co.tz/.com Domain, 10GB Storage, SSL Certificate, Email Accounts'),
('System Maintenance', 'Monthly website/system maintenance and updates', 'Maintenance', 100000, 30, 'Security Updates, Backups, Performance Monitoring, Bug Fixes');

-- Create indexes for better performance
CREATE INDEX idx_projects_customer ON projects(customer_id);
CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_tasks_project ON tasks(project_id);
CREATE INDEX idx_tasks_assigned ON tasks(assigned_to);
CREATE INDEX idx_messages_recipient ON messages(recipient_id);
CREATE INDEX idx_messages_read ON messages(is_read);
CREATE INDEX idx_payments_project ON payments(project_id);

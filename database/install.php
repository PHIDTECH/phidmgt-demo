<?php
/**
 * PhidTech Management System - Database Installation Script
 * Run this file once to set up the database
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'phidtech_db';

echo "<!DOCTYPE html><html><head><title>PhidTech Installation</title>";
echo "<style>body{font-family:Arial,sans-serif;max-width:800px;margin:50px auto;padding:20px;}
table{border-collapse:collapse;width:100%;}th,td{border:1px solid #ddd;padding:10px;text-align:left;}
th{background:#2563eb;color:white;}.success{color:green;}.error{color:red;}.warning{color:orange;}
.btn{display:inline-block;padding:10px 20px;background:#2563eb;color:white;text-decoration:none;border-radius:5px;margin:5px;}</style></head><body>";

echo "<h1>🚀 PhidTech Database Installation</h1>";

try {
    // Connect without database
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE $dbname");
    echo "<p class='success'>✓ Database '$dbname' created successfully!</p>";
    
    // Create tables
    $pdo->exec("DROP TABLE IF EXISTS activity_log, messages, leave_applications, salary_records, petty_cash, liabilities, assets, expenses, revenue, payments, tasks, projects, services, role_permissions, permissions, roles, users");
    
    // Users table
    $pdo->exec("CREATE TABLE users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        role ENUM('superadmin', 'ceo', 'manager', 'accountant', 'hr', 'supervisor', 'it_staff', 'junior_staff', 'customer') NOT NULL DEFAULT 'customer',
        department VARCHAR(50),
        salary DECIMAL(15, 2) DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    // Roles table
    $pdo->exec("CREATE TABLE roles (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(50) UNIQUE NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        description TEXT,
        level INT DEFAULT 1,
        is_system TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Permissions table
    $pdo->exec("CREATE TABLE permissions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) UNIQUE NOT NULL,
        display_name VARCHAR(100) NOT NULL,
        description TEXT,
        module VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Role-Permission pivot table
    $pdo->exec("CREATE TABLE role_permissions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        role_id INT NOT NULL,
        permission_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
        UNIQUE KEY unique_role_permission (role_id, permission_id)
    )");
    
    // Services table
    $pdo->exec("CREATE TABLE services (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        category VARCHAR(50),
        base_price DECIMAL(15, 2) NOT NULL,
        duration_days INT DEFAULT 7,
        features TEXT,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Projects table
    $pdo->exec("CREATE TABLE projects (
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
    )");
    
    // Tasks table
    $pdo->exec("CREATE TABLE tasks (
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
    )");
    
    // Payments table
    $pdo->exec("CREATE TABLE payments (
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
    )");
    
    // Revenue table
    $pdo->exec("CREATE TABLE revenue (
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
    )");
    
    // Expenses table
    $pdo->exec("CREATE TABLE expenses (
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
    )");
    
    // Assets table
    $pdo->exec("CREATE TABLE assets (
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
    )");
    
    // Liabilities table
    $pdo->exec("CREATE TABLE liabilities (
        id INT PRIMARY KEY AUTO_INCREMENT,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(15, 2) NOT NULL,
        creditor VARCHAR(100),
        due_date DATE,
        status ENUM('active', 'paid', 'overdue') DEFAULT 'active',
        category VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Petty cash table
    $pdo->exec("CREATE TABLE petty_cash (
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
    )");
    
    // Salary records table
    $pdo->exec("CREATE TABLE salary_records (
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
    )");
    
    // Leave applications table
    $pdo->exec("CREATE TABLE leave_applications (
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
    )");
    
    // Messages table
    $pdo->exec("CREATE TABLE messages (
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
    )");
    
    // Activity log table
    $pdo->exec("CREATE TABLE activity_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT,
        action VARCHAR(100) NOT NULL,
        description TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    
    echo "<p class='success'>✓ All tables created successfully!</p>";
    
    // Insert default users with proper password hashes
    $users = [
        ['Super Admin', 'admin@yourdomain.com', 'admin123', '+255700000000', 'superadmin', 'Administration', 0],
        ['Sample CEO', 'ceo@example.com', 'demo123', '+255700000001', 'ceo', 'Executive', 5000000],
        ['Sample Manager', 'manager@example.com', 'demo123', '+255700000002', 'manager', 'Operations', 3000000],
        ['Sample Accountant', 'accountant@example.com', 'demo123', '+255700000003', 'accountant', 'Finance', 2500000],
        ['Sample HR', 'hr@example.com', 'demo123', '+255700000004', 'hr', 'Human Resources', 2500000],
        ['Sample Supervisor', 'supervisor@example.com', 'demo123', '+255700000005', 'supervisor', 'IT', 2000000],
        ['Sample IT Staff', 'itstaff@example.com', 'demo123', '+255700000006', 'it_staff', 'IT', 1500000],
        ['Sample Junior', 'junior@example.com', 'demo123', '+255700000007', 'junior_staff', 'IT', 800000],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, phone, role, department, salary) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($users as $u) {
        $stmt->execute([$u[0], $u[1], password_hash($u[2], PASSWORD_DEFAULT), $u[3], $u[4], $u[5], $u[6]]);
    }
    echo "<p class='success'>✓ Default users created!</p>";
    
    // Insert default services
    $services = [
        ['Basic Website', 'Professional 5-page responsive website with modern design', 'Web Development', 500000, 7, 'Responsive Design, Contact Form, SEO Basic, 1 Month Support'],
        ['E-Commerce Website', 'Full-featured online store with payment integration', 'Web Development', 2000000, 21, 'Product Management, Payment Gateway, Order Tracking, Admin Dashboard, 3 Months Support'],
        ['Custom Web Application', 'Tailored web application for your business needs', 'Web Development', 5000000, 45, 'Custom Features, Database Design, API Integration, User Management, 6 Months Support'],
        ['Mobile App Development', 'Native or cross-platform mobile application', 'Mobile Development', 8000000, 60, 'iOS & Android, Push Notifications, API Integration, App Store Submission'],
        ['Logo Design', 'Professional logo design with multiple concepts', 'Graphics Design', 150000, 3, '3 Concepts, Unlimited Revisions, Source Files, Brand Guidelines'],
        ['Social Media Kit', 'Complete social media branding package', 'Graphics Design', 300000, 5, 'Profile Images, Cover Photos, Post Templates, Story Templates'],
        ['SEO Package', 'Search engine optimization for better visibility', 'Digital Marketing', 400000, 30, 'Keyword Research, On-page SEO, Technical SEO, Monthly Reports'],
        ['IT Consultation', 'Expert IT consultation and system analysis', 'Consultation', 200000, 1, 'System Analysis, Recommendations, Implementation Plan, Follow-up Support'],
        ['Domain & Hosting', 'Annual domain registration and web hosting', 'Hosting', 150000, 365, '.co.tz/.com Domain, 10GB Storage, SSL Certificate, Email Accounts'],
        ['System Maintenance', 'Monthly website/system maintenance and updates', 'Maintenance', 100000, 30, 'Security Updates, Backups, Performance Monitoring, Bug Fixes'],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO services (name, description, category, base_price, duration_days, features) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($services as $s) {
        $stmt->execute($s);
    }
    echo "<p class='success'>✓ Default services created!</p>";
    
    // Create indexes
    $pdo->exec("CREATE INDEX idx_projects_customer ON projects(customer_id)");
    $pdo->exec("CREATE INDEX idx_projects_status ON projects(status)");
    $pdo->exec("CREATE INDEX idx_tasks_project ON tasks(project_id)");
    $pdo->exec("CREATE INDEX idx_tasks_assigned ON tasks(assigned_to)");
    $pdo->exec("CREATE INDEX idx_messages_recipient ON messages(recipient_id)");
    $pdo->exec("CREATE INDEX idx_messages_read ON messages(is_read)");
    $pdo->exec("CREATE INDEX idx_payments_project ON payments(project_id)");
    echo "<p class='success'>✓ Database indexes created!</p>";
    
    echo "<h2>✅ Installation Complete!</h2>";
    
    // Insert default roles
    $roles = [
        ['superadmin', 'Super Admin', 'Full system access - manages users, roles, permissions', 10, 1],
        ['ceo', 'CEO', 'Chief Executive Officer - company oversight', 8, 1],
        ['manager', 'Manager', 'Operations management', 7, 1],
        ['accountant', 'Accountant', 'Financial management', 6, 1],
        ['hr', 'HR', 'Human Resources management', 5, 1],
        ['supervisor', 'Supervisor', 'Team supervision', 4, 1],
        ['it_staff', 'IT Staff', 'Technical implementation', 3, 1],
        ['junior_staff', 'Junior Staff', 'Task execution', 2, 1],
        ['customer', 'Customer', 'External customer', 1, 1],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO roles (name, display_name, description, level, is_system) VALUES (?, ?, ?, ?, ?)");
    foreach ($roles as $r) {
        $stmt->execute($r);
    }
    echo "<p class='success'>✓ Default roles created!</p>";
    
    // Insert default permissions
    $permissions = [
        ['manage_users', 'Manage Users', 'Create, edit, delete users', 'users'],
        ['manage_roles', 'Manage Roles', 'Create, edit, delete roles', 'users'],
        ['manage_permissions', 'Manage Permissions', 'Assign permissions to roles', 'users'],
        ['view_dashboard', 'View Dashboard', 'Access to dashboard', 'general'],
        ['manage_projects', 'Manage Projects', 'Create, edit, assign projects', 'projects'],
        ['view_projects', 'View Projects', 'View project details', 'projects'],
        ['manage_tasks', 'Manage Tasks', 'Create, assign, update tasks', 'tasks'],
        ['view_tasks', 'View Tasks', 'View task details', 'tasks'],
        ['manage_accounting', 'Manage Accounting', 'Full accounting access', 'accounting'],
        ['view_accounting', 'View Accounting', 'View financial reports', 'accounting'],
        ['manage_hr', 'Manage HR', 'Staff and leave management', 'hr'],
        ['view_hr', 'View HR', 'View HR information', 'hr'],
        ['manage_services', 'Manage Services', 'Create, edit services', 'services'],
        ['manage_customers', 'Manage Customers', 'View and manage customers', 'customers'],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO permissions (name, display_name, description, module) VALUES (?, ?, ?, ?)");
    foreach ($permissions as $p) {
        $stmt->execute($p);
    }
    echo "<p class='success'>✓ Default permissions created!</p>";

    echo "<h3>Default Login Credentials</h3>";
    echo "<table>";
    echo "<tr><th>Role</th><th>Email</th><th>Password</th></tr>";
    echo "<tr style='background:#fef3c7;'><td><strong>Super Admin</strong></td><td><strong>admin@yourdomain.com</strong></td><td><strong>admin123</strong></td></tr>";
    echo "<tr><td>CEO</td><td>ceo@example.com</td><td>demo123</td></tr>";
    echo "<tr><td>Manager</td><td>manager@example.com</td><td>demo123</td></tr>";
    echo "<tr><td>Accountant</td><td>accountant@example.com</td><td>demo123</td></tr>";
    echo "<tr><td>HR</td><td>hr@example.com</td><td>demo123</td></tr>";
    echo "<tr><td>Supervisor</td><td>supervisor@example.com</td><td>demo123</td></tr>";
    echo "<tr><td>IT Staff</td><td>itstaff@example.com</td><td>demo123</td></tr>";
    echo "<tr><td>Junior Staff</td><td>junior@example.com</td><td>demo123</td></tr>";
    echo "</table>";
    
    echo "<br><a href='../' class='btn'>Go to Homepage</a>";
    echo "<a href='../auth/login.php' class='btn'>Login Now</a>";
    
    echo "<p class='warning'><strong>⚠️ Security Warning:</strong> Delete this file after installation!</p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "</body></html>";

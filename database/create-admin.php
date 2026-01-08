<?php
require_once __DIR__ . '/../config/database.php';

$adminEmail = 'admin@yourdomain.com';  // Change to your admin email
$adminPassword = 'ChangeThisPassword123!';  // Change to a secure password
$hashedPassword = password_hash($adminPassword, PASSWORD_DEFAULT);

$db = new Database();
$conn = $db->getConnection();

// Check if admin already exists
$stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$adminEmail]);

if ($stmt->fetch()) {
    // Update existing user
    $stmt = $conn->prepare("UPDATE users SET password = ?, role = 'ceo', status = 'active' WHERE email = ?");
    $stmt->execute([$hashedPassword, $adminEmail]);
    echo "Admin user updated successfully!<br>";
} else {
    // Create new admin user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role, department, status) VALUES (?, ?, ?, ?, 'ceo', 'Executive', 'active')");
    $stmt->execute(['System Admin', $adminEmail, $hashedPassword, '+255700000000']);
    echo "Admin user created successfully!<br>";
}

echo "<br><strong>Login credentials:</strong><br>";
echo "Email: " . $adminEmail . "<br>";
echo "Password: " . $adminPassword . "<br>";
echo "<br><strong>DELETE THIS FILE after use for security!</strong>";
echo "<br><br><a href='/auth/login.php'>Go to Login</a>";
?>

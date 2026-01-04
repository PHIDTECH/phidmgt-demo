<?php
session_start();

define('SITE_NAME', 'PhidTech Solutions');
define('SITE_URL', 'http://localhost:8000');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// User Roles
define('ROLE_SUPERADMIN', 'superadmin');
define('ROLE_CEO', 'ceo');
define('ROLE_MANAGER', 'manager');
define('ROLE_ACCOUNTANT', 'accountant');
define('ROLE_HR', 'hr');
define('ROLE_SUPERVISOR', 'supervisor');
define('ROLE_IT_STAFF', 'it_staff');
define('ROLE_JUNIOR_STAFF', 'junior_staff');
define('ROLE_CUSTOMER', 'customer');

// Role Hierarchy (for permissions)
$ROLE_HIERARCHY = [
    ROLE_SUPERADMIN => 10,
    ROLE_CEO => 8,
    ROLE_MANAGER => 7,
    ROLE_ACCOUNTANT => 6,
    ROLE_HR => 5,
    ROLE_SUPERVISOR => 4,
    ROLE_IT_STAFF => 3,
    ROLE_JUNIOR_STAFF => 2,
    ROLE_CUSTOMER => 1
];

// Permissions
$PERMISSIONS = [
    'manage_users' => [ROLE_SUPERADMIN],
    'manage_roles' => [ROLE_SUPERADMIN],
    'manage_permissions' => [ROLE_SUPERADMIN],
    'view_all_data' => [ROLE_SUPERADMIN, ROLE_CEO],
    'manage_projects' => [ROLE_SUPERADMIN, ROLE_CEO, ROLE_MANAGER],
    'manage_accounting' => [ROLE_SUPERADMIN, ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT],
    'manage_hr' => [ROLE_SUPERADMIN, ROLE_CEO, ROLE_MANAGER, ROLE_HR],
    'manage_tasks' => [ROLE_SUPERADMIN, ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR],
];

// Project Status
define('STATUS_PENDING', 'pending');
define('STATUS_PAYMENT_RECEIVED', 'payment_received');
define('STATUS_IN_PROGRESS', 'in_progress');
define('STATUS_REVIEW', 'review');
define('STATUS_COMPLETED', 'completed');
define('STATUS_CANCELLED', 'cancelled');

// Task Status
define('TASK_PENDING', 'pending');
define('TASK_IN_PROGRESS', 'in_progress');
define('TASK_SUBMITTED', 'submitted');
define('TASK_APPROVED', 'approved');
define('TASK_REJECTED', 'rejected');

// Payment Status
define('PAYMENT_PENDING', 'pending');
define('PAYMENT_COMPLETED', 'completed');
define('PAYMENT_FAILED', 'failed');

// Leave Status
define('LEAVE_PENDING', 'pending');
define('LEAVE_APPROVED', 'approved');
define('LEAVE_REJECTED', 'rejected');

// Helper Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role']
        ];
    }
    return null;
}

function hasRole($required_roles) {
    if (!isLoggedIn()) return false;
    
    if (is_string($required_roles)) {
        $required_roles = [$required_roles];
    }
    
    return in_array($_SESSION['user_role'], $required_roles);
}

function hasMinRole($min_role) {
    global $ROLE_HIERARCHY;
    if (!isLoggedIn()) return false;
    
    $user_level = $ROLE_HIERARCHY[$_SESSION['user_role']] ?? 0;
    $required_level = $ROLE_HIERARCHY[$min_role] ?? 0;
    
    return $user_level >= $required_level;
}

function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit;
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatCurrency($amount) {
    return 'TZS ' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M d, Y H:i', strtotime($datetime));
}

function generateReference($prefix = 'PHD') {
    return $prefix . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

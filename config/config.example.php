<?php
/**
 * Configuration Example File
 * ==========================
 * Copy this file to config.php and update with your settings
 */

session_start();

// Site Settings - Update these with your company info
define('SITE_NAME', 'Your Company Name');

// Auto-detect site URL (usually no need to change)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL', $protocol . $host);

// Upload path
define('UPLOAD_PATH', __DIR__ . '/../uploads/');

// User Roles - Do not modify unless you understand the system
define('ROLE_SUPERADMIN', 'superadmin');
define('ROLE_CEO', 'ceo');
define('ROLE_MANAGER', 'manager');
define('ROLE_ACCOUNTANT', 'accountant');
define('ROLE_HR', 'hr');
define('ROLE_SUPERVISOR', 'supervisor');
define('ROLE_IT_STAFF', 'it_staff');
define('ROLE_JUNIOR_STAFF', 'junior_staff');
define('ROLE_CUSTOMER', 'customer');

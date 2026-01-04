<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get unread message count for logged in users
$unreadCount = 0;
if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unreadCount = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? $pageTitle . ' - ' : '' ?><?= SITE_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        secondary: '#1e40af',
                        accent: '#3b82f6',
                        dark: '#1e293b',
                        light: '#f8fafc'
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif']
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .sidebar-link.active { background-color: #2563eb; color: white; }
        .dropdown:hover .dropdown-menu { display: block; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
<?php if (isLoggedIn() && $_SESSION['user_role'] !== ROLE_CUSTOMER): ?>
<!-- Staff Navigation -->
<div class="flex h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-dark text-white fixed h-full overflow-y-auto">
        <div class="p-4 border-b border-gray-700">
            <h1 class="text-xl font-bold text-primary">
                <i class="fas fa-code mr-2"></i>PhidTech
            </h1>
            <p class="text-xs text-gray-400 mt-1">Management System</p>
        </div>
        
        <nav class="p-4">
            <div class="mb-4">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Main</p>
                <a href="<?= SITE_URL ?>/dashboard/" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition <?= strpos($_SERVER['REQUEST_URI'], '/dashboard/') !== false && strpos($_SERVER['REQUEST_URI'], '/dashboard/index') !== false ? 'active' : '' ?>">
                    <i class="fas fa-home w-5 mr-3"></i>Dashboard
                </a>
            </div>
            
            <?php if (hasMinRole(ROLE_JUNIOR_STAFF)): ?>
            <div class="mb-4">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Projects</p>
                <a href="<?= SITE_URL ?>/dashboard/projects.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-project-diagram w-5 mr-3"></i>Projects
                </a>
                <a href="<?= SITE_URL ?>/dashboard/tasks.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-tasks w-5 mr-3"></i>My Tasks
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT])): ?>
            <div class="mb-4">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Accounting</p>
                <a href="<?= SITE_URL ?>/dashboard/accounting/revenue.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-coins w-5 mr-3"></i>Revenue
                </a>
                <a href="<?= SITE_URL ?>/dashboard/accounting/expenses.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-receipt w-5 mr-3"></i>Expenses
                </a>
                <a href="<?= SITE_URL ?>/dashboard/accounting/assets.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-building w-5 mr-3"></i>Assets
                </a>
                <a href="<?= SITE_URL ?>/dashboard/accounting/liabilities.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-file-invoice-dollar w-5 mr-3"></i>Liabilities
                </a>
                <a href="<?= SITE_URL ?>/dashboard/accounting/petty-cash.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-wallet w-5 mr-3"></i>Petty Cash
                </a>
                <a href="<?= SITE_URL ?>/dashboard/accounting/reports.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-chart-bar w-5 mr-3"></i>Reports
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_HR])): ?>
            <div class="mb-4">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">HR</p>
                <a href="<?= SITE_URL ?>/dashboard/hr/staff.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-users w-5 mr-3"></i>Staff
                </a>
                <a href="<?= SITE_URL ?>/dashboard/hr/leave.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-calendar-alt w-5 mr-3"></i>Leave Management
                </a>
                <a href="<?= SITE_URL ?>/dashboard/hr/salaries.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-money-bill-wave w-5 mr-3"></i>Salaries
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (hasMinRole(ROLE_JUNIOR_STAFF)): ?>
            <div class="mb-4">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Personal</p>
                <a href="<?= SITE_URL ?>/dashboard/my-leave.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-plane w-5 mr-3"></i>My Leave
                </a>
                <a href="<?= SITE_URL ?>/dashboard/my-salary.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-file-invoice w-5 mr-3"></i>My Salary
                </a>
            </div>
            <?php endif; ?>
            
            <div class="mb-4">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Communication</p>
                <a href="<?= SITE_URL ?>/dashboard/messages.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-envelope w-5 mr-3"></i>Messages
                    <?php if ($unreadCount > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
            
            <?php if (hasRole([ROLE_CEO, ROLE_MANAGER])): ?>
            <div class="mb-4">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Admin</p>
                <a href="<?= SITE_URL ?>/dashboard/services.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-cogs w-5 mr-3"></i>Services
                </a>
                <a href="<?= SITE_URL ?>/dashboard/customers.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-user-tie w-5 mr-3"></i>Customers
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (hasRole([ROLE_SUPERADMIN])): ?>
            <div class="mb-4">
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Super Admin</p>
                <a href="<?= SITE_URL ?>/admin/" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-shield-alt w-5 mr-3"></i>Admin Panel
                </a>
                <a href="<?= SITE_URL ?>/admin/users.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-users-cog w-5 mr-3"></i>User Management
                </a>
                <a href="<?= SITE_URL ?>/admin/roles.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-user-tag w-5 mr-3"></i>Roles
                </a>
                <a href="<?= SITE_URL ?>/admin/permissions.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-key w-5 mr-3"></i>Permissions
                </a>
            </div>
            <?php endif; ?>
        </nav>
    </aside>
    
    <!-- Main Content -->
    <div class="flex-1 ml-64">
        <!-- Top Bar -->
        <header class="bg-white shadow-sm sticky top-0 z-10">
            <div class="flex items-center justify-between px-6 py-4">
                <h2 class="text-xl font-semibold text-gray-800"><?= $pageTitle ?? 'Dashboard' ?></h2>
                <div class="flex items-center space-x-4">
                    <a href="<?= SITE_URL ?>/dashboard/messages.php" class="relative text-gray-600 hover:text-primary">
                        <i class="fas fa-bell text-xl"></i>
                        <?php if ($unreadCount > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 flex items-center justify-center rounded-full"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="relative dropdown">
                        <button class="flex items-center space-x-2 text-gray-700 hover:text-primary">
                            <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center">
                                <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                            </div>
                            <span class="font-medium"><?= $_SESSION['user_name'] ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        <div class="dropdown-menu hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg py-2 z-20">
                            <div class="px-4 py-2 border-b">
                                <p class="text-sm text-gray-500"><?= ucfirst(str_replace('_', ' ', $_SESSION['user_role'])) ?></p>
                            </div>
                            <a href="<?= SITE_URL ?>/dashboard/profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i>Profile
                            </a>
                            <a href="<?= SITE_URL ?>/auth/logout.php" class="block px-4 py-2 text-red-600 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Page Content -->
        <main class="p-6">
<?php else: ?>
<!-- Public/Customer Navigation -->
<nav class="bg-white shadow-sm sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <a href="<?= SITE_URL ?>" class="text-2xl font-bold text-primary">
                <i class="fas fa-code mr-2"></i>PhidTech
            </a>
            
            <div class="hidden md:flex items-center space-x-8">
                <a href="<?= SITE_URL ?>" class="text-gray-700 hover:text-primary">Home</a>
                <a href="<?= SITE_URL ?>/services.php" class="text-gray-700 hover:text-primary">Services</a>
                <a href="<?= SITE_URL ?>/about.php" class="text-gray-700 hover:text-primary">About</a>
                <a href="<?= SITE_URL ?>/contact.php" class="text-gray-700 hover:text-primary">Contact</a>
            </div>
            
            <div class="flex items-center space-x-4">
                <?php if (isLoggedIn()): ?>
                <a href="<?= SITE_URL ?>/customer/dashboard.php" class="text-gray-700 hover:text-primary">
                    <i class="fas fa-user mr-1"></i>My Account
                </a>
                <a href="<?= SITE_URL ?>/auth/logout.php" class="text-red-600 hover:text-red-700">Logout</a>
                <?php else: ?>
                <a href="<?= SITE_URL ?>/auth/login.php" class="text-gray-700 hover:text-primary">Login</a>
                <a href="<?= SITE_URL ?>/auth/register.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<?php endif; ?>

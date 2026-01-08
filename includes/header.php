<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Track visitor - disabled temporarily
// require_once __DIR__ . '/visitor-tracker.php';

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
        .dropdown-menu { display: none; }
        .dropdown-menu.show { display: block; }
        
        /* YouTube Video Background */
        #youtube-bg { pointer-events: none; }
        #youtube-bg iframe { width: 100%; height: 100%; position: absolute; top: 0; left: 0; }
        
        /* Categories Navigation Styles - Original White */
        .categories-nav { background: #ffffff; border-bottom: 1px solid #e4e5e7; }
        .categories-container { display: flex; flex-wrap: wrap; justify-content: center; max-width: 1400px; margin: 0 auto; }
        .category-item { position: relative; flex: 0 0 calc(100% / 7); max-width: calc(100% / 7); }
        .category-link { color: #62646a; padding: 16px 14px; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: 500; white-space: nowrap; transition: all 0.2s; border-bottom: 3px solid transparent; }
        .category-link:hover { color: #1dbf73; border-bottom-color: #1dbf73; }
        .category-link i { font-size: 15px; margin-right: 8px; }
        .category-dropdown { position: absolute; top: 100%; left: 50%; transform: translateX(-50%) translateY(10px); min-width: 260px; background: white; box-shadow: 0 10px 40px rgba(0,0,0,0.15); border-radius: 0 0 8px 8px; opacity: 0; visibility: hidden; transition: all 0.2s ease; z-index: 100; }
        .category-item:hover .category-dropdown { opacity: 1; visibility: visible; transform: translateX(-50%) translateY(0); }
        .category-dropdown a { display: flex; align-items: center; padding: 14px 20px; color: #404145; font-size: 15px; font-weight: 400; transition: all 0.2s; border-left: 3px solid transparent; }
        .category-dropdown a:hover { background: #f5f5f5; color: #1dbf73; border-left-color: #1dbf73; }
        .category-dropdown a i { width: 22px; margin-right: 12px; color: #74767e; font-size: 14px; }
        .category-dropdown a:hover i { color: #1dbf73; }
        @media (max-width: 1024px) { .category-item { flex: 0 0 calc(100% / 4); max-width: calc(100% / 4); } }
        @media (max-width: 640px) { .category-item { flex: 0 0 calc(100% / 2); max-width: calc(100% / 2); } .category-link { padding: 10px 6px; font-size: 12px; } }
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
                <a href="<?= SITE_URL ?>/dashboard/salary-advances.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-hand-holding-usd w-5 mr-3"></i>Salary Advances
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
                <p class="text-xs text-gray-500 uppercase tracking-wider mb-2">Weekly Reports</p>
                <a href="<?= SITE_URL ?>/dashboard/weekly-report.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-file-alt w-5 mr-3"></i>Submit Report
                </a>
                <a href="<?= SITE_URL ?>/dashboard/my-reports.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-folder-open w-5 mr-3"></i>My Reports
                </a>
            </div>
            
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
                <a href="<?= SITE_URL ?>/dashboard/analytics.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-chart-line w-5 mr-3"></i>Analytics
                </a>
                <a href="<?= SITE_URL ?>/dashboard/review-reports.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-clipboard-check w-5 mr-3"></i>Review Reports
                </a>
                <a href="<?= SITE_URL ?>/dashboard/services.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-cogs w-5 mr-3"></i>Services
                </a>
                <a href="<?= SITE_URL ?>/dashboard/digital-products.php" class="sidebar-link flex items-center px-3 py-2 rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-download w-5 mr-3"></i>Digital Products
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
                    <div class="flex items-center space-x-3">
                        <a href="<?= SITE_URL ?>/dashboard/profile.php" class="flex items-center space-x-2 text-gray-700 hover:text-primary">
                            <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center">
                                <?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <span class="font-medium"><?= $_SESSION['user_name'] ?></span>
                                <p class="text-xs text-gray-500"><?= ucfirst(str_replace('_', ' ', $_SESSION['user_role'])) ?></p>
                            </div>
                        </a>
                        <a href="<?= SITE_URL ?>/auth/logout.php" class="bg-red-500 text-white px-3 py-2 rounded-lg hover:bg-red-600 transition text-sm">
                            <i class="fas fa-sign-out-alt mr-1"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Page Content -->
        <main class="p-6">
<?php else: ?>
<!-- Public/Customer Navigation -->
<nav class="bg-gradient-to-r from-primary to-secondary shadow-md sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <a href="<?= SITE_URL ?>" class="text-2xl font-bold text-white">
                <i class="fas fa-code mr-2"></i>PhidTech
            </a>
            
            <div class="hidden md:flex items-center space-x-8">
                <a href="<?= SITE_URL ?>" class="text-white hover:text-blue-200">Home</a>
                <a href="<?= SITE_URL ?>/services.php" class="text-white hover:text-blue-200">Services</a>
                <a href="<?= SITE_URL ?>/about.php" class="text-white hover:text-blue-200">About</a>
                <a href="<?= SITE_URL ?>/contact.php" class="text-white hover:text-blue-200">Contact</a>
            </div>
            
            <div class="flex items-center space-x-4">
                <?php if (isLoggedIn()): ?>
                <a href="<?= SITE_URL ?>/customer/dashboard.php" class="text-white hover:text-blue-200">
                    <i class="fas fa-user mr-1"></i>My Account
                </a>
                <a href="<?= SITE_URL ?>/auth/logout.php" class="text-red-300 hover:text-red-100">Logout</a>
                <?php else: ?>
                <a href="<?= SITE_URL ?>/auth/login.php" class="text-white hover:text-blue-200">Login</a>
                <a href="<?= SITE_URL ?>/auth/register.php" class="bg-white text-primary px-4 py-2 rounded-lg hover:bg-blue-100 transition font-semibold">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Categories Navigation Bar -->
<div class="categories-nav shadow-md py-2">
    <div class="categories-container px-4">
                <!-- Graphics & Design -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=graphics-design" class="category-link">
                        <i class="fas fa-palette mr-2"></i>Graphics & Design
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=logo-design"><i class="fas fa-signature"></i>Logo Design</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=brand-identity"><i class="fas fa-id-badge"></i>Brand Identity</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=illustration"><i class="fas fa-pen-nib"></i>Illustration</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=web-ui-design"><i class="fas fa-desktop"></i>Web & App Design</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=packaging"><i class="fas fa-box"></i>Packaging Design</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=social-media"><i class="fas fa-share-alt"></i>Social Media Design</a>
                    </div>
                </div>

                <!-- Digital Marketing -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=digital-marketing" class="category-link">
                        <i class="fas fa-bullhorn mr-2"></i>Digital Marketing
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=seo"><i class="fas fa-search"></i>SEO</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=social-media-marketing"><i class="fas fa-thumbs-up"></i>Social Media Marketing</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=ppc"><i class="fas fa-ad"></i>PPC Advertising</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=email-marketing"><i class="fas fa-envelope-open-text"></i>Email Marketing</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=content-marketing"><i class="fas fa-pencil-alt"></i>Content Marketing</a>
                    </div>
                </div>

                <!-- Writing & Translation -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=writing-translation" class="category-link">
                        <i class="fas fa-pen mr-2"></i>Writing & Translation
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=content-writing"><i class="fas fa-file-alt"></i>Content Writing</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=copywriting"><i class="fas fa-copy"></i>Copywriting</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=translation"><i class="fas fa-language"></i>Translation</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=proofreading"><i class="fas fa-spell-check"></i>Proofreading & Editing</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=resume-writing"><i class="fas fa-file-signature"></i>Resume Writing</a>
                    </div>
                </div>

                <!-- Video & Animation -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=video-animation" class="category-link">
                        <i class="fas fa-video mr-2"></i>Video & Animation
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=video-editing"><i class="fas fa-film"></i>Video Editing</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=animation"><i class="fas fa-magic"></i>Animation</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=motion-graphics"><i class="fas fa-spinner"></i>Motion Graphics</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=explainer-videos"><i class="fas fa-play-circle"></i>Explainer Videos</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=intro-outro"><i class="fas fa-star"></i>Intro & Outro</a>
                    </div>
                </div>

                <!-- Music & Audio -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=music-audio" class="category-link">
                        <i class="fas fa-music mr-2"></i>Music & Audio
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=voice-over"><i class="fas fa-microphone"></i>Voice Over</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=mixing-mastering"><i class="fas fa-sliders-h"></i>Mixing & Mastering</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=music-production"><i class="fas fa-headphones"></i>Music Production</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=podcast-editing"><i class="fas fa-podcast"></i>Podcast Editing</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=sound-effects"><i class="fas fa-volume-up"></i>Sound Effects</a>
                    </div>
                </div>

                <!-- Programming & Tech -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=programming-tech" class="category-link">
                        <i class="fas fa-code mr-2"></i>Programming & Tech
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=web-development"><i class="fas fa-globe"></i>Web Development</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=mobile-apps"><i class="fas fa-mobile-alt"></i>Mobile Apps</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=software-development"><i class="fas fa-laptop-code"></i>Software Development</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=wordpress"><i class="fab fa-wordpress"></i>WordPress</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=ecommerce"><i class="fas fa-shopping-cart"></i>E-Commerce</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=game-development"><i class="fas fa-gamepad"></i>Game Development</a>
                    </div>
                </div>

                <!-- AI Services -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=ai-services" class="category-link">
                        <i class="fas fa-robot mr-2"></i>AI Services
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=ai-chatbots"><i class="fas fa-comments"></i>AI Chatbots</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=machine-learning"><i class="fas fa-brain"></i>Machine Learning</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=ai-content"><i class="fas fa-robot"></i>AI Content Generation</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=data-science"><i class="fas fa-chart-line"></i>Data Science</a>
                    </div>
                </div>

                <!-- Consultation -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=consultation" class="category-link">
                        <i class="fas fa-handshake mr-2"></i>Consultation
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=business-consulting"><i class="fas fa-briefcase"></i>Business Consultation</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=it-consulting"><i class="fas fa-server"></i>IT Consultation</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=marketing-strategy"><i class="fas fa-chart-pie"></i>Marketing Strategy</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=career-counseling"><i class="fas fa-user-graduate"></i>Career Counseling</a>
                    </div>
                </div>

                <!-- Software (Digital Products) -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/software.php" class="category-link">
                        <i class="fas fa-download mr-2"></i>Software
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/software.php?type=applications"><i class="fas fa-window-maximize"></i>Applications</a>
                        <a href="<?= SITE_URL ?>/software.php?type=templates"><i class="fas fa-file-code"></i>Templates</a>
                        <a href="<?= SITE_URL ?>/software.php?type=ebooks"><i class="fas fa-book"></i>E-Books & PDFs</a>
                        <a href="<?= SITE_URL ?>/software.php?type=graphics"><i class="fas fa-images"></i>Graphics & Assets</a>
                        <a href="<?= SITE_URL ?>/software.php?type=video-courses"><i class="fas fa-video"></i>Video Courses</a>
                        <a href="<?= SITE_URL ?>/software.php?type=audio"><i class="fas fa-headphones"></i>Audio Files</a>
                    </div>
                </div>

                <!-- Data -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=data" class="category-link">
                        <i class="fas fa-database mr-2"></i>Data
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=data-entry"><i class="fas fa-keyboard"></i>Data Entry</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=data-analytics"><i class="fas fa-chart-bar"></i>Data Analytics</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=data-visualization"><i class="fas fa-chart-area"></i>Data Visualization</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=database-management"><i class="fas fa-database"></i>Database Management</a>
                    </div>
                </div>

                <!-- Business -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=business" class="category-link">
                        <i class="fas fa-building mr-2"></i>Business
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=virtual-assistant"><i class="fas fa-user-tie"></i>Virtual Assistant</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=market-research"><i class="fas fa-search-dollar"></i>Market Research</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=business-plans"><i class="fas fa-file-contract"></i>Business Plans</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=project-management"><i class="fas fa-tasks"></i>Project Management</a>
                    </div>
                </div>

                <!-- Personal Growth -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=personal-growth" class="category-link">
                        <i class="fas fa-seedling mr-2"></i>Personal Growth
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=online-tutoring"><i class="fas fa-chalkboard-teacher"></i>Online Tutoring</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=coaching"><i class="fas fa-user-friends"></i>Life Coaching</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=fitness"><i class="fas fa-dumbbell"></i>Fitness & Wellness</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=arts-crafts"><i class="fas fa-paint-brush"></i>Arts & Crafts</a>
                    </div>
                </div>

                <!-- Photography -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=photography" class="category-link">
                        <i class="fas fa-camera mr-2"></i>Photography
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=photo-editing"><i class="fas fa-image"></i>Photo Editing</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=product-photography"><i class="fas fa-box-open"></i>Product Photography</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=portrait-retouching"><i class="fas fa-portrait"></i>Portrait Retouching</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=photo-restoration"><i class="fas fa-history"></i>Photo Restoration</a>
                    </div>
                </div>

                <!-- Finance -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=finance" class="category-link">
                        <i class="fas fa-coins mr-2"></i>Finance
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=accounting"><i class="fas fa-calculator"></i>Accounting</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=financial-consulting"><i class="fas fa-hand-holding-usd"></i>Financial Consulting</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=tax-services"><i class="fas fa-file-invoice-dollar"></i>Tax Services</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=bookkeeping"><i class="fas fa-book"></i>Bookkeeping</a>
                    </div>
                </div>

                <!-- End-to-End Projects -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php?category=end-to-end" class="category-link">
                        <i class="fas fa-project-diagram mr-2"></i>End-to-End Projects
                    </a>
                    <div class="category-dropdown">
                        <a href="<?= SITE_URL ?>/services.php?sub=full-website"><i class="fas fa-sitemap"></i>Full Website Development</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=full-app"><i class="fas fa-mobile"></i>Complete App Development</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=brand-package"><i class="fas fa-gift"></i>Complete Branding Package</a>
                        <a href="<?= SITE_URL ?>/services.php?sub=marketing-campaign"><i class="fas fa-bullseye"></i>Full Marketing Campaign</a>
                    </div>
                </div>

                <!-- Service Catalog -->
                <div class="category-item">
                    <a href="<?= SITE_URL ?>/services.php" class="category-link">
                        <i class="fas fa-th-list mr-2"></i>Service Catalog
                    </a>
                </div>
    </div>
</div>
<?php endif; ?>

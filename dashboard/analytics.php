<?php
/**
 * Website Analytics Dashboard
 * Shows visitor statistics, geographic data, and metrics
 */
$pageTitle = 'Website Analytics';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check access - Admin only
if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERADMIN])) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Create table if not exists
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS visitor_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45),
            country VARCHAR(100),
            country_code VARCHAR(10),
            city VARCHAR(100),
            region VARCHAR(100),
            latitude DECIMAL(10,8),
            longitude DECIMAL(11,8),
            timezone VARCHAR(50),
            isp VARCHAR(255),
            page_url VARCHAR(500),
            page_title VARCHAR(255),
            referrer VARCHAR(500),
            user_agent TEXT,
            device_type VARCHAR(50),
            browser VARCHAR(100),
            os VARCHAR(100),
            session_id VARCHAR(100),
            user_id INT DEFAULT NULL,
            visit_date DATE,
            visit_time TIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip (ip_address),
            INDEX idx_date (visit_date),
            INDEX idx_country (country_code),
            INDEX idx_page (page_url(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Table exists
}

// Get date filter
$dateFilter = $_GET['date'] ?? 'today';
$startDate = date('Y-m-d');
$endDate = date('Y-m-d');

switch ($dateFilter) {
    case 'yesterday':
        $startDate = date('Y-m-d', strtotime('-1 day'));
        $endDate = $startDate;
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('-7 days'));
        break;
    case 'month':
        $startDate = date('Y-m-d', strtotime('-30 days'));
        break;
    case 'year':
        $startDate = date('Y-m-d', strtotime('-365 days'));
        break;
}

// Get total visitors today
$stmt = $conn->prepare("SELECT COUNT(DISTINCT ip_address) as total FROM visitor_logs WHERE visit_date = CURDATE()");
$stmt->execute();
$todayVisitors = $stmt->fetch()['total'] ?? 0;

// Get total page views today
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM visitor_logs WHERE visit_date = CURDATE()");
$stmt->execute();
$todayPageViews = $stmt->fetch()['total'] ?? 0;

// Get unique visitors in date range
$stmt = $conn->prepare("SELECT COUNT(DISTINCT ip_address) as total FROM visitor_logs WHERE visit_date BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$uniqueVisitors = $stmt->fetch()['total'] ?? 0;

// Get total page views in date range
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM visitor_logs WHERE visit_date BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$totalPageViews = $stmt->fetch()['total'] ?? 0;

// Get visitors by country
$stmt = $conn->prepare("
    SELECT country, country_code, COUNT(DISTINCT ip_address) as visitors, COUNT(*) as page_views
    FROM visitor_logs 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY country, country_code 
    ORDER BY visitors DESC 
    LIMIT 10
");
$stmt->execute([$startDate, $endDate]);
$visitorsByCountry = $stmt->fetchAll();

// Get visitors by city
$stmt = $conn->prepare("
    SELECT city, country, COUNT(DISTINCT ip_address) as visitors
    FROM visitor_logs 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY city, country 
    ORDER BY visitors DESC 
    LIMIT 10
");
$stmt->execute([$startDate, $endDate]);
$visitorsByCity = $stmt->fetchAll();

// Get top pages
$stmt = $conn->prepare("
    SELECT page_url, COUNT(*) as views, COUNT(DISTINCT ip_address) as unique_visitors
    FROM visitor_logs 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY page_url 
    ORDER BY views DESC 
    LIMIT 10
");
$stmt->execute([$startDate, $endDate]);
$topPages = $stmt->fetchAll();

// Get device breakdown
$stmt = $conn->prepare("
    SELECT device_type, COUNT(DISTINCT ip_address) as visitors
    FROM visitor_logs 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY device_type 
    ORDER BY visitors DESC
");
$stmt->execute([$startDate, $endDate]);
$deviceStats = $stmt->fetchAll();

// Get browser breakdown
$stmt = $conn->prepare("
    SELECT browser, COUNT(DISTINCT ip_address) as visitors
    FROM visitor_logs 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY browser 
    ORDER BY visitors DESC
    LIMIT 5
");
$stmt->execute([$startDate, $endDate]);
$browserStats = $stmt->fetchAll();

// Get OS breakdown
$stmt = $conn->prepare("
    SELECT os, COUNT(DISTINCT ip_address) as visitors
    FROM visitor_logs 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY os 
    ORDER BY visitors DESC
    LIMIT 5
");
$stmt->execute([$startDate, $endDate]);
$osStats = $stmt->fetchAll();

// Get daily visitors for chart (last 7 days)
$stmt = $conn->prepare("
    SELECT visit_date, COUNT(DISTINCT ip_address) as visitors, COUNT(*) as page_views
    FROM visitor_logs 
    WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY visit_date 
    ORDER BY visit_date ASC
");
$stmt->execute();
$dailyStats = $stmt->fetchAll();

// Get recent visitors
$stmt = $conn->prepare("
    SELECT * FROM visitor_logs 
    WHERE visit_date BETWEEN ? AND ?
    ORDER BY created_at DESC 
    LIMIT 20
");
$stmt->execute([$startDate, $endDate]);
$recentVisitors = $stmt->fetchAll();

// Get referrer stats
$stmt = $conn->prepare("
    SELECT 
        CASE 
            WHEN referrer = '' OR referrer IS NULL THEN 'Direct'
            WHEN referrer LIKE '%google%' THEN 'Google'
            WHEN referrer LIKE '%facebook%' THEN 'Facebook'
            WHEN referrer LIKE '%twitter%' OR referrer LIKE '%x.com%' THEN 'Twitter/X'
            WHEN referrer LIKE '%instagram%' THEN 'Instagram'
            WHEN referrer LIKE '%linkedin%' THEN 'LinkedIn'
            ELSE 'Other'
        END as source,
        COUNT(DISTINCT ip_address) as visitors
    FROM visitor_logs 
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY source 
    ORDER BY visitors DESC
");
$stmt->execute([$startDate, $endDate]);
$referrerStats = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Website Analytics</h1>
            <p class="text-gray-600">Track visitor statistics and geographic data</p>
        </div>
        
        <!-- Date Filter -->
        <div class="flex gap-2">
            <a href="?date=today" class="px-4 py-2 rounded-lg <?= $dateFilter === 'today' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Today</a>
            <a href="?date=yesterday" class="px-4 py-2 rounded-lg <?= $dateFilter === 'yesterday' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Yesterday</a>
            <a href="?date=week" class="px-4 py-2 rounded-lg <?= $dateFilter === 'week' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">7 Days</a>
            <a href="?date=month" class="px-4 py-2 rounded-lg <?= $dateFilter === 'month' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">30 Days</a>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-users text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Today's Visitors</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($todayVisitors) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 text-green-600 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-eye text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Today's Page Views</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($todayPageViews) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-user-check text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Unique Visitors (<?= ucfirst($dateFilter) ?>)</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($uniqueVisitors) ?></p>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow p-6">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-orange-100 text-orange-600 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-file-alt text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Page Views (<?= ucfirst($dateFilter) ?>)</p>
                    <p class="text-2xl font-bold text-gray-800"><?= number_format($totalPageViews) ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Visitors by Country -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-globe-africa text-primary mr-2"></i>Visitors by Country
            </h3>
            <?php if (empty($visitorsByCountry)): ?>
            <p class="text-gray-500 text-center py-8">No data yet</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($visitorsByCountry as $country): ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <img src="https://flagcdn.com/24x18/<?= strtolower($country['country_code']) ?>.png" 
                             alt="<?= $country['country'] ?>" class="w-6 h-4 mr-3 rounded">
                        <span class="text-gray-700"><?= htmlspecialchars($country['country']) ?></span>
                    </div>
                    <div class="text-right">
                        <span class="font-semibold text-gray-800"><?= number_format($country['visitors']) ?></span>
                        <span class="text-gray-500 text-sm ml-1">visitors</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Visitors by City -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-city text-primary mr-2"></i>Top Cities
            </h3>
            <?php if (empty($visitorsByCity)): ?>
            <p class="text-gray-500 text-center py-8">No data yet</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($visitorsByCity as $city): ?>
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-gray-700"><?= htmlspecialchars($city['city']) ?></span>
                        <span class="text-gray-400 text-sm">, <?= htmlspecialchars($city['country']) ?></span>
                    </div>
                    <span class="font-semibold text-gray-800"><?= number_format($city['visitors']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <!-- Device Stats -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-mobile-alt text-primary mr-2"></i>Devices
            </h3>
            <?php if (empty($deviceStats)): ?>
            <p class="text-gray-500 text-center py-4">No data yet</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php 
                $deviceIcons = ['Desktop' => 'desktop', 'Mobile' => 'mobile-alt', 'Tablet' => 'tablet-alt'];
                foreach ($deviceStats as $device): 
                ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <i class="fas fa-<?= $deviceIcons[$device['device_type']] ?? 'question' ?> text-gray-400 w-6"></i>
                        <span class="text-gray-700 ml-2"><?= $device['device_type'] ?></span>
                    </div>
                    <span class="font-semibold"><?= number_format($device['visitors']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Browser Stats -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-globe text-primary mr-2"></i>Browsers
            </h3>
            <?php if (empty($browserStats)): ?>
            <p class="text-gray-500 text-center py-4">No data yet</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($browserStats as $browser): ?>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700"><?= $browser['browser'] ?></span>
                    <span class="font-semibold"><?= number_format($browser['visitors']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Traffic Sources -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-share-alt text-primary mr-2"></i>Traffic Sources
            </h3>
            <?php if (empty($referrerStats)): ?>
            <p class="text-gray-500 text-center py-4">No data yet</p>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($referrerStats as $ref): ?>
                <div class="flex items-center justify-between">
                    <span class="text-gray-700"><?= $ref['source'] ?></span>
                    <span class="font-semibold"><?= number_format($ref['visitors']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Top Pages -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-file text-primary mr-2"></i>Top Pages
        </h3>
        <?php if (empty($topPages)): ?>
        <p class="text-gray-500 text-center py-8">No data yet</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 text-gray-600 font-medium">Page URL</th>
                        <th class="text-right py-3 text-gray-600 font-medium">Views</th>
                        <th class="text-right py-3 text-gray-600 font-medium">Unique Visitors</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topPages as $page): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 text-gray-700"><?= htmlspecialchars($page['page_url']) ?></td>
                        <td class="py-3 text-right font-medium"><?= number_format($page['views']) ?></td>
                        <td class="py-3 text-right"><?= number_format($page['unique_visitors']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Visitors -->
    <div class="bg-white rounded-xl shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-clock text-primary mr-2"></i>Recent Visitors
        </h3>
        <?php if (empty($recentVisitors)): ?>
        <p class="text-gray-500 text-center py-8">No visitors yet. Data will appear as visitors browse your site.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-3 text-gray-600 font-medium">Time</th>
                        <th class="text-left py-3 text-gray-600 font-medium">Location</th>
                        <th class="text-left py-3 text-gray-600 font-medium">Page</th>
                        <th class="text-left py-3 text-gray-600 font-medium">Device</th>
                        <th class="text-left py-3 text-gray-600 font-medium">Browser</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentVisitors as $visitor): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-3 text-gray-600">
                            <?= date('M j, g:i A', strtotime($visitor['created_at'])) ?>
                        </td>
                        <td class="py-3">
                            <div class="flex items-center">
                                <?php if ($visitor['country_code'] && $visitor['country_code'] !== 'XX'): ?>
                                <img src="https://flagcdn.com/16x12/<?= strtolower($visitor['country_code']) ?>.png" 
                                     alt="" class="w-4 h-3 mr-2">
                                <?php endif; ?>
                                <span class="text-gray-700"><?= htmlspecialchars($visitor['city']) ?>, <?= htmlspecialchars($visitor['country']) ?></span>
                            </div>
                        </td>
                        <td class="py-3 text-gray-700 max-w-xs truncate"><?= htmlspecialchars($visitor['page_url']) ?></td>
                        <td class="py-3 text-gray-600"><?= $visitor['device_type'] ?></td>
                        <td class="py-3 text-gray-600"><?= $visitor['browser'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

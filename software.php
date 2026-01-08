<?php
$pageTitle = 'Software & Digital Products';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get filter type
$type = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$sql = "SELECT * FROM digital_products WHERE status = 'active'";
$params = [];

if ($type && in_array($type, ['applications', 'templates', 'ebooks', 'graphics', 'video-courses', 'audio'])) {
    $sql .= " AND category = ?";
    $params[] = $type;
}

if ($search) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// Category info
$categories = [
    'applications' => ['name' => 'Applications', 'icon' => 'window-maximize', 'desc' => 'Ready-to-use software applications'],
    'templates' => ['name' => 'Templates', 'icon' => 'file-code', 'desc' => 'Website and app templates'],
    'ebooks' => ['name' => 'E-Books & PDFs', 'icon' => 'book', 'desc' => 'Digital books and documents'],
    'graphics' => ['name' => 'Graphics & Assets', 'icon' => 'images', 'desc' => 'Design assets and graphics'],
    'video-courses' => ['name' => 'Video Courses', 'icon' => 'video', 'desc' => 'Educational video content'],
    'audio' => ['name' => 'Audio Files', 'icon' => 'headphones', 'desc' => 'Music and audio content']
];

require_once __DIR__ . '/includes/header.php';
?>

<section class="bg-gradient-to-br from-primary to-secondary text-white py-16">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <h1 class="text-4xl font-bold mb-4">
            <i class="fas fa-download mr-3"></i>Software & Digital Products
        </h1>
        <p class="text-xl text-blue-100 max-w-2xl mx-auto mb-8">
            Instant download after purchase. Get templates, applications, e-books, and more.
        </p>
        
        <!-- Search -->
        <form method="GET" class="max-w-xl mx-auto">
            <div class="flex">
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search products..." 
                       class="flex-1 px-4 py-3 rounded-l-lg text-gray-800 focus:outline-none">
                <button type="submit" class="bg-white text-primary px-6 py-3 rounded-r-lg font-semibold hover:bg-gray-100 transition">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</section>

<!-- Category Filter -->
<section class="bg-white border-b">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex flex-wrap items-center justify-center gap-2 py-4">
            <a href="<?= SITE_URL ?>/software.php" 
               class="px-4 py-2 rounded-full text-sm font-medium transition <?= !$type ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                All Products
            </a>
            <?php foreach ($categories as $key => $cat): ?>
            <a href="<?= SITE_URL ?>/software.php?type=<?= $key ?>" 
               class="px-4 py-2 rounded-full text-sm font-medium transition <?= $type === $key ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                <i class="fas fa-<?= $cat['icon'] ?> mr-1"></i><?= $cat['name'] ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-7xl mx-auto px-4">
        <?php if ($type && isset($categories[$type])): ?>
        <div class="mb-8">
            <h2 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-<?= $categories[$type]['icon'] ?> text-primary mr-2"></i>
                <?= $categories[$type]['name'] ?>
            </h2>
            <p class="text-gray-600"><?= $categories[$type]['desc'] ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (empty($products)): ?>
        <div class="text-center py-16">
            <div class="w-24 h-24 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-box-open text-4xl text-gray-400"></i>
            </div>
            <h3 class="text-xl font-semibold text-gray-700 mb-2">No Products Found</h3>
            <p class="text-gray-500 mb-4">We're adding new products soon. Check back later!</p>
            <a href="<?= SITE_URL ?>/software.php" class="text-primary hover:underline">View all products</a>
        </div>
        <?php else: ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($products as $product): ?>
            <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition overflow-hidden group">
                <!-- Thumbnail -->
                <div class="h-48 bg-gradient-to-br from-blue-500 to-purple-600 relative overflow-hidden">
                    <?php if ($product['thumbnail']): ?>
                    <img src="<?= SITE_URL ?>/<?= $product['thumbnail'] ?>" alt="<?= htmlspecialchars($product['name']) ?>" 
                         class="w-full h-full object-cover">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center">
                        <i class="fas fa-<?= $categories[$product['category']]['icon'] ?? 'file' ?> text-6xl text-white/50"></i>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Category Badge -->
                    <span class="absolute top-3 left-3 bg-white/90 text-gray-800 px-3 py-1 rounded-full text-xs font-medium">
                        <i class="fas fa-<?= $categories[$product['category']]['icon'] ?? 'file' ?> mr-1"></i>
                        <?= $categories[$product['category']]['name'] ?? $product['category'] ?>
                    </span>
                    
                    <!-- File Info -->
                    <?php if ($product['file_type'] || $product['file_size']): ?>
                    <span class="absolute top-3 right-3 bg-black/50 text-white px-2 py-1 rounded text-xs">
                        <?= strtoupper($product['file_type']) ?><?= $product['file_size'] ? ' • ' . $product['file_size'] : '' ?>
                    </span>
                    <?php endif; ?>
                </div>
                
                <!-- Content -->
                <div class="p-5">
                    <h3 class="text-lg font-bold text-gray-800 mb-2 group-hover:text-primary transition">
                        <?= htmlspecialchars($product['name']) ?>
                    </h3>
                    <p class="text-gray-600 text-sm mb-4 line-clamp-2">
                        <?= htmlspecialchars($product['short_description'] ?: substr($product['description'], 0, 100)) ?>
                    </p>
                    
                    <!-- Features -->
                    <?php if ($product['features']): ?>
                    <div class="flex flex-wrap gap-1 mb-4">
                        <?php foreach (array_slice(explode(',', $product['features']), 0, 3) as $feature): ?>
                        <span class="bg-blue-50 text-blue-700 text-xs px-2 py-1 rounded"><?= trim($feature) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Price & Action -->
                    <div class="flex items-center justify-between pt-4 border-t">
                        <div>
                            <span class="text-2xl font-bold text-primary"><?= formatCurrency($product['price']) ?></span>
                            <?php if ($product['download_count'] > 0): ?>
                            <p class="text-xs text-gray-500"><i class="fas fa-download mr-1"></i><?= $product['download_count'] ?> downloads</p>
                            <?php endif; ?>
                        </div>
                        <a href="<?= SITE_URL ?>/software-order.php?product=<?= $product['id'] ?>" 
                           class="bg-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-secondary transition">
                            <i class="fas fa-shopping-cart mr-1"></i>Buy Now
                        </a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- Features Section -->
<section class="py-12 bg-white">
    <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Why Buy Digital Products From Us?</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-bolt text-2xl"></i>
                </div>
                <h3 class="font-semibold mb-2">Instant Download</h3>
                <p class="text-gray-600 text-sm">Get your files immediately after payment</p>
            </div>
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-shield-alt text-2xl"></i>
                </div>
                <h3 class="font-semibold mb-2">Secure Payment</h3>
                <p class="text-gray-600 text-sm">Pay safely with Mobile Money or Cards</p>
            </div>
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-sync text-2xl"></i>
                </div>
                <h3 class="font-semibold mb-2">Free Updates</h3>
                <p class="text-gray-600 text-sm">Get future updates at no extra cost</p>
            </div>
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-headset text-2xl"></i>
                </div>
                <h3 class="font-semibold mb-2">Support Included</h3>
                <p class="text-gray-600 text-sm">Get help when you need it</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

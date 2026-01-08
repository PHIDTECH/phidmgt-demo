<?php
/**
 * Digital Product Download Success Page
 * Shows after successful payment with download link
 */
$pageTitle = 'Download Ready';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->getConnection();

$orderRef = $_GET['order'] ?? '';
$order = null;
$product = null;

if ($orderRef) {
    // Find the digital order
    $stmt = $conn->prepare("
        SELECT do.*, dp.name as product_name, dp.file_type, dp.file_size, dp.category
        FROM digital_orders do
        JOIN digital_products dp ON do.product_id = dp.id
        WHERE do.order_reference = ?
    ");
    $stmt->execute([$orderRef]);
    $order = $stmt->fetch();
    
    if ($order) {
        $stmt = $conn->prepare("SELECT * FROM digital_products WHERE id = ?");
        $stmt->execute([$order['product_id']]);
        $product = $stmt->fetch();
    }
}

$categories = [
    'applications' => ['name' => 'Applications', 'icon' => 'window-maximize'],
    'templates' => ['name' => 'Templates', 'icon' => 'file-code'],
    'ebooks' => ['name' => 'E-Books & PDFs', 'icon' => 'book'],
    'graphics' => ['name' => 'Graphics & Assets', 'icon' => 'images'],
    'video-courses' => ['name' => 'Video Courses', 'icon' => 'video'],
    'audio' => ['name' => 'Audio Files', 'icon' => 'headphones']
];

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-16 bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto px-4">
        <?php if ($order && $order['payment_status'] === 'completed'): ?>
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <!-- Success Header -->
            <div class="bg-gradient-to-r from-green-500 to-emerald-600 text-white p-8 text-center">
                <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-check text-4xl"></i>
                </div>
                <h1 class="text-2xl font-bold mb-2">Payment Successful!</h1>
                <p class="text-green-100">Your download is ready</p>
            </div>
            
            <div class="p-8">
                <!-- Product Info -->
                <div class="bg-gray-50 rounded-lg p-4 mb-6">
                    <div class="flex items-center">
                        <div class="w-12 h-12 bg-primary/10 rounded-lg flex items-center justify-center mr-4">
                            <i class="fas fa-<?= $categories[$order['category']]['icon'] ?? 'file' ?> text-primary text-xl"></i>
                        </div>
                        <div>
                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($order['product_name']) ?></h3>
                            <p class="text-sm text-gray-500">
                                <?= strtoupper($order['file_type']) ?> • <?= $order['file_size'] ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Download Button -->
                <div class="text-center mb-6">
                    <a href="<?= SITE_URL ?>/download.php?token=<?= $order['download_token'] ?>" 
                       class="inline-flex items-center bg-primary text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-secondary transition shadow-lg hover:shadow-xl">
                        <i class="fas fa-download mr-3 text-xl"></i>Download Now
                    </a>
                    <p class="text-sm text-gray-500 mt-3">
                        <i class="fas fa-info-circle mr-1"></i>
                        <?= $order['max_downloads'] - $order['download_count'] ?> downloads remaining • 
                        Expires <?= date('M j, Y', strtotime($order['download_expiry'])) ?>
                    </p>
                </div>
                
                <!-- Order Details -->
                <div class="border-t pt-6">
                    <h3 class="font-semibold text-gray-800 mb-3">Order Details</h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Order Reference:</span>
                            <span class="font-mono font-medium"><?= $order['order_reference'] ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Amount Paid:</span>
                            <span class="font-medium text-green-600"><?= formatCurrency($order['amount']) ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Email:</span>
                            <span class="font-medium"><?= htmlspecialchars($order['customer_email']) ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Email Notice -->
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
                    <p class="text-blue-800 text-sm">
                        <i class="fas fa-envelope mr-2"></i>
                        A download link has also been sent to <strong><?= htmlspecialchars($order['customer_email']) ?></strong>
                    </p>
                </div>
                
                <!-- Actions -->
                <div class="flex gap-3 mt-6">
                    <a href="<?= SITE_URL ?>/software.php" class="flex-1 text-center border border-gray-300 text-gray-700 py-3 rounded-lg font-semibold hover:bg-gray-50 transition">
                        <i class="fas fa-store mr-2"></i>More Products
                    </a>
                    <a href="<?= SITE_URL ?>" class="flex-1 text-center border border-gray-300 text-gray-700 py-3 rounded-lg font-semibold hover:bg-gray-50 transition">
                        <i class="fas fa-home mr-2"></i>Home
                    </a>
                </div>
            </div>
        </div>
        
        <?php elseif ($order && $order['payment_status'] === 'pending'): ?>
        <!-- Payment Pending -->
        <div class="bg-white rounded-xl shadow-lg p-8 text-center">
            <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-clock text-4xl text-yellow-500"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Payment Processing</h1>
            <p class="text-gray-600 mb-6">
                Your payment is being processed. Once confirmed, you'll receive a download link via email.
            </p>
            <p class="text-sm text-gray-500">Order: <?= $orderRef ?></p>
            
            <div class="mt-6">
                <a href="<?= SITE_URL ?>" class="inline-block bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-secondary transition">
                    <i class="fas fa-home mr-2"></i>Back to Home
                </a>
            </div>
        </div>
        
        <?php else: ?>
        <!-- Order Not Found -->
        <div class="bg-white rounded-xl shadow-lg p-8 text-center">
            <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-search text-4xl text-gray-400"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Order Not Found</h1>
            <p class="text-gray-600 mb-6">
                We couldn't find this order. Please check your email for the download link or contact support.
            </p>
            
            <div class="space-y-3">
                <a href="<?= SITE_URL ?>/software.php" class="block w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
                    <i class="fas fa-store mr-2"></i>Browse Products
                </a>
                <a href="<?= SITE_URL ?>/contact.php" class="block w-full border border-gray-300 text-gray-700 py-3 rounded-lg font-semibold hover:bg-gray-50 transition">
                    <i class="fas fa-headset mr-2"></i>Contact Support
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

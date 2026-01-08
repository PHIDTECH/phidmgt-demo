<?php
/**
 * Software/Digital Product Order Page
 * Different from regular services - instant download after payment
 */
ini_set('display_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Purchase Digital Product';

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/selcom.php';

$db = new Database();
$conn = $db->getConnection();

$error = '';
$product = null;

// Get product
if (isset($_GET['product'])) {
    $stmt = $conn->prepare("SELECT * FROM digital_products WHERE id = ? AND status = 'active'");
    $stmt->execute([$_GET['product']]);
    $product = $stmt->fetch();
}

if (!$product) {
    header("Location: " . SITE_URL . "/software.php");
    exit;
}

// Category info
$categories = [
    'applications' => ['name' => 'Applications', 'icon' => 'window-maximize'],
    'templates' => ['name' => 'Templates', 'icon' => 'file-code'],
    'ebooks' => ['name' => 'E-Books & PDFs', 'icon' => 'book'],
    'graphics' => ['name' => 'Graphics & Assets', 'icon' => 'images'],
    'video-courses' => ['name' => 'Video Courses', 'icon' => 'video'],
    'audio' => ['name' => 'Audio Files', 'icon' => 'headphones']
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customerName = sanitize($_POST['customer_name'] ?? '');
    $customerEmail = sanitize($_POST['customer_email'] ?? '');
    $customerPhone = sanitize($_POST['customer_phone'] ?? '');
    $customerPassword = $_POST['customer_password'] ?? '';
    
    // Validation
    if (empty($customerName)) {
        $error = 'Please enter your full name';
    } elseif (empty($customerEmail) || !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } elseif (empty($customerPhone)) {
        $error = 'Please enter your phone number for payment';
    } elseif (!isLoggedIn() && (empty($customerPassword) || strlen($customerPassword) < 6)) {
        $error = 'Please create a password (minimum 6 characters)';
    } else {
        // Check if email exists for non-logged in users
        if (!isLoggedIn()) {
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$customerEmail]);
            if ($stmt->fetch()) {
                $error = 'An account with this email exists. Please <a href="' . SITE_URL . '/auth/login.php" class="underline">login</a> first.';
            }
        }
        
        if (empty($error)) {
            // Generate unique order reference
            $orderReference = 'DIG' . date('ymd') . strtoupper(substr(uniqid(), -6));
            
            // Generate download token
            $downloadToken = bin2hex(random_bytes(32));
            
            // Set download expiry (7 days from now)
            $downloadExpiry = date('Y-m-d H:i:s', strtotime('+7 days'));
            
            // Get customer ID if logged in
            $customerId = isLoggedIn() ? $_SESSION['user_id'] : null;
            
            // Hash password for new customers
            $passwordHash = !isLoggedIn() ? password_hash($customerPassword, PASSWORD_DEFAULT) : null;
            
            try {
                // Create digital order record
                $stmt = $conn->prepare("
                    INSERT INTO digital_orders (
                        order_reference, product_id, customer_id, customer_name, 
                        customer_email, customer_phone, amount, payment_status,
                        download_token, download_expiry, password_hash, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $orderReference,
                    $product['id'],
                    $customerId,
                    $customerName,
                    $customerEmail,
                    $customerPhone,
                    $product['price'],
                    $downloadToken,
                    $downloadExpiry,
                    $passwordHash
                ]);
                
                $orderId = $conn->lastInsertId();
                
                // Format phone for Selcom
                $buyerPhone = formatPhoneForSelcom($customerPhone);
                
                // Create Selcom checkout
                $selcomData = [
                    'order_id' => $orderReference,
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $buyerPhone,
                    'amount' => $product['price'],
                    'description' => 'Digital Product: ' . $product['name']
                ];
                
                $selcomResult = createSelcomCheckoutOrderFull($selcomData);
                
                // Check if Selcom returned success
                $response = $selcomResult['response'] ?? [];
                if (isset($response['result']) && $response['result'] === 'SUCCESS' && !empty($response['data'][0]['payment_gateway_url'])) {
                    // Store order reference in session for callback
                    $_SESSION['digital_order'] = [
                        'order_id' => $orderId,
                        'reference' => $orderReference,
                        'product_id' => $product['id'],
                        'download_token' => $downloadToken
                    ];
                    
                    // Redirect to Selcom payment page
                    $gatewayUrl = base64_decode($response['data'][0]['payment_gateway_url']);
                    header("Location: " . $gatewayUrl);
                    exit;
                } else {
                    // Selcom failed
                    $errorMsg = $response['message'] ?? 'Payment gateway error';
                    $error = 'Payment error: ' . $errorMsg . '. Please try again.';
                    
                    // Delete the failed order
                    $stmt = $conn->prepare("DELETE FROM digital_orders WHERE id = ?");
                    $stmt->execute([$orderId]);
                }
                
            } catch (PDOException $e) {
                $error = 'Database error. Please try again. ' . $e->getMessage();
            }
        }
    }
}

// Pre-fill logged in user data
$prefillName = '';
$prefillEmail = '';
$prefillPhone = '';

if (isLoggedIn()) {
    $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        $prefillName = $user['name'];
        $prefillEmail = $user['email'];
        $prefillPhone = $user['phone'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-12 bg-gray-50 min-h-screen">
    <div class="max-w-5xl mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Product Info -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Product Image -->
                <div class="h-56 bg-gradient-to-br from-blue-500 to-purple-600 relative">
                    <?php if ($product['thumbnail']): ?>
                    <img src="<?= SITE_URL ?>/<?= $product['thumbnail'] ?>" alt="<?= htmlspecialchars($product['name']) ?>" 
                         class="w-full h-full object-cover">
                    <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center">
                        <i class="fas fa-<?= $categories[$product['category']]['icon'] ?? 'file' ?> text-7xl text-white/50"></i>
                    </div>
                    <?php endif; ?>
                    <span class="absolute top-4 left-4 bg-white/90 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">
                        <i class="fas fa-<?= $categories[$product['category']]['icon'] ?? 'file' ?> mr-1"></i>
                        <?= $categories[$product['category']]['name'] ?? $product['category'] ?>
                    </span>
                </div>
                
                <div class="p-6">
                    <h1 class="text-2xl font-bold text-gray-800 mb-3"><?= htmlspecialchars($product['name']) ?></h1>
                    
                    <p class="text-gray-600 mb-4"><?= nl2br(htmlspecialchars($product['description'])) ?></p>
                    
                    <!-- File Info -->
                    <div class="flex flex-wrap gap-4 mb-4 text-sm">
                        <?php if ($product['file_type']): ?>
                        <span class="flex items-center text-gray-600">
                            <i class="fas fa-file mr-2 text-primary"></i>
                            <?= strtoupper($product['file_type']) ?> File
                        </span>
                        <?php endif; ?>
                        <?php if ($product['file_size']): ?>
                        <span class="flex items-center text-gray-600">
                            <i class="fas fa-hdd mr-2 text-primary"></i>
                            <?= $product['file_size'] ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($product['version']): ?>
                        <span class="flex items-center text-gray-600">
                            <i class="fas fa-code-branch mr-2 text-primary"></i>
                            Version <?= $product['version'] ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Features -->
                    <?php if ($product['features']): ?>
                    <div class="border-t pt-4 mt-4">
                        <h3 class="font-semibold text-gray-800 mb-2">What's Included:</h3>
                        <ul class="space-y-1">
                            <?php foreach (explode(',', $product['features']) as $feature): ?>
                            <li class="flex items-center text-gray-600 text-sm">
                                <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                <?= trim($feature) ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Requirements -->
                    <?php if ($product['requirements']): ?>
                    <div class="border-t pt-4 mt-4">
                        <h3 class="font-semibold text-gray-800 mb-2">Requirements:</h3>
                        <p class="text-gray-600 text-sm"><?= nl2br(htmlspecialchars($product['requirements'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Order Form -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-primary to-secondary text-white p-6">
                    <h2 class="text-xl font-bold">Complete Your Purchase</h2>
                    <p class="text-blue-100 text-sm">Instant download after payment</p>
                </div>
                
                <div class="p-6">
                    <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Order Summary -->
                    <div class="bg-blue-50 rounded-lg p-4 mb-6">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-700"><?= htmlspecialchars($product['name']) ?></span>
                            <span class="font-semibold"><?= formatCurrency($product['price']) ?></span>
                        </div>
                        <div class="border-t border-blue-200 pt-2 mt-2">
                            <div class="flex justify-between items-center">
                                <span class="font-bold text-gray-800">Total</span>
                                <span class="text-2xl font-bold text-primary"><?= formatCurrency($product['price']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <form method="POST" class="space-y-4">
                        <?php if (isLoggedIn()): ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">
                            <p class="text-green-800 text-sm">
                                <i class="fas fa-check-circle mr-2"></i>
                                Purchasing as: <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong>
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                            <input type="text" name="customer_name" required 
                                   value="<?= htmlspecialchars($_POST['customer_name'] ?? $prefillName) ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Enter your full name">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input type="email" name="customer_email" required 
                                   value="<?= htmlspecialchars($_POST['customer_email'] ?? $prefillEmail) ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="your@email.com">
                            <p class="text-xs text-gray-500 mt-1">Download link will be sent here</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number (for payment) *</label>
                            <input type="tel" name="customer_phone" required 
                                   value="<?= htmlspecialchars($_POST['customer_phone'] ?? $prefillPhone) ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="0712 345 678">
                        </div>
                        
                        <?php if (!isLoggedIn()): ?>
                        <div class="border-t pt-4">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Create Password *</label>
                            <input type="password" name="customer_password" required minlength="6"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="Min 6 characters">
                            <p class="text-xs text-gray-500 mt-1">For accessing your downloads later</p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="pt-4">
                            <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
                                <i class="fas fa-lock mr-2"></i>Pay <?= formatCurrency($product['price']) ?>
                            </button>
                        </div>
                        
                        <div class="text-center text-sm text-gray-500">
                            <i class="fas fa-shield-alt mr-1"></i>
                            Secure payment via Selcom (M-Pesa, Tigo Pesa, Airtel Money, Cards)
                        </div>
                    </form>
                    
                    <!-- What happens next -->
                    <div class="mt-6 pt-6 border-t">
                        <h3 class="font-semibold text-gray-800 mb-3">What happens after payment?</h3>
                        <ol class="space-y-2 text-sm text-gray-600">
                            <li class="flex items-start">
                                <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-xs mr-2 flex-shrink-0">1</span>
                                Complete payment via mobile money or card
                            </li>
                            <li class="flex items-start">
                                <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-xs mr-2 flex-shrink-0">2</span>
                                Receive instant download link on screen & email
                            </li>
                            <li class="flex items-start">
                                <span class="w-6 h-6 bg-primary text-white rounded-full flex items-center justify-center text-xs mr-2 flex-shrink-0">3</span>
                                Download your files (up to 5 times within 7 days)
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

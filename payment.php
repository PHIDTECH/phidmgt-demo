<?php
// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

$pageTitle = 'Payment';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// Allow both logged in users AND pending customers
try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    die('Database connection error. Please try again later.');
}

$error = '';
$success = '';
$checkoutUrl = '';
$project = null;
$pendingCustomer = $_SESSION['pending_customer'] ?? null;

// Get order by reference
if (isset($_GET['order'])) {
    $stmt = $conn->prepare("
        SELECT p.*, COALESCE(s.name, p.service_name) as service_name, 
               u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM projects p
        LEFT JOIN services s ON p.service_id = s.id
        LEFT JOIN users u ON p.customer_id = u.id
        WHERE p.reference = ?
    ");
    $stmt->execute([$_GET['order']]);
    $project = $stmt->fetch();
}

if (!$project) {
    redirect('/');
}

// Get customer info from session if not logged in
$customerName = $pendingCustomer['name'] ?? ($project['customer_name'] ?? '');
$customerEmail = $pendingCustomer['email'] ?? ($project['customer_email'] ?? '');
$customerPhone = $pendingCustomer['phone'] ?? ($project['customer_phone'] ?? '');

// Calculate remaining balance
$remainingBalance = $project['total_amount'] - ($project['paid_amount'] ?? 0);

// Ensure pending_payments table exists with all needed columns
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS pending_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            selcom_order_id VARCHAR(100) UNIQUE,
            project_id INT,
            customer_name VARCHAR(255),
            customer_email VARCHAR(255),
            customer_phone VARCHAR(50),
            customer_password VARCHAR(255),
            amount DECIMAL(15,2),
            status VARCHAR(20) DEFAULT 'pending',
            selcom_transid VARCHAR(100),
            checkout_url TEXT,
            created_at DATETIME,
            updated_at DATETIME
        )
    ");
} catch (PDOException $e) {
    // Table might already exist - that's OK
}

// Try to add password column if table exists but column doesn't
try {
    $conn->exec("ALTER TABLE pending_payments ADD COLUMN customer_password VARCHAR(255)");
} catch (PDOException $e) {
    // Column already exists - that's OK
}

// Process payment - redirect to Selcom checkout
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $phone = sanitize($_POST['phone'] ?? '');
    
    // Get customer info
    $paymentCustomerName = $customerName ?: sanitize($_POST['customer_name'] ?? '');
    $paymentCustomerEmail = $customerEmail ?: sanitize($_POST['customer_email'] ?? '');
    $paymentCustomerPhone = $phone;
    
    if ($amount <= 0) {
        $error = 'Please enter a valid amount';
    } elseif ($amount > $remainingBalance) {
        $error = 'Amount exceeds remaining balance';
    } elseif (empty($paymentCustomerPhone)) {
        $error = 'Please enter your phone number';
    } elseif (empty($paymentCustomerName)) {
        $error = 'Please enter your name';
    } elseif (empty($paymentCustomerEmail)) {
        $error = 'Please enter your email address';
    } else {
        // Store customer info in session for webhook
        $_SESSION['pending_payment'] = [
            'name' => $paymentCustomerName,
            'email' => $paymentCustomerEmail,
            'phone' => $paymentCustomerPhone,
            'project_id' => $project['id'],
            'project_ref' => $project['reference'],
            'amount' => $amount
        ];
        
        // Generate unique order ID for Selcom
        $selcomOrderId = 'PHD' . $project['id'] . 'T' . time();
        
        // Get password from session if available (email is used as username)
        $customerPasswordHash = $pendingCustomer['password'] ?? null;
        
        // Store pending payment with account credentials
        $stmt = $conn->prepare("
            INSERT INTO pending_payments (selcom_order_id, project_id, customer_name, customer_email, customer_phone, customer_password, amount, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ON DUPLICATE KEY UPDATE customer_name=VALUES(customer_name), customer_email=VALUES(customer_email), 
            customer_phone=VALUES(customer_phone), customer_password=VALUES(customer_password), amount=VALUES(amount), status='pending', updated_at=NOW()
        ");
        $stmt->execute([$selcomOrderId, $project['id'], $paymentCustomerName, $paymentCustomerEmail, $paymentCustomerPhone, $customerPasswordHash, $amount]);
        
        // Load Selcom config and create checkout
        try {
            require_once __DIR__ . '/config/selcom.php';
            
            // Check if Selcom is configured
            if (SELCOM_VENDOR_ID === 'YOUR_VENDOR_ID' || SELCOM_API_KEY === 'YOUR_API_KEY') {
                $error = 'Payment gateway is not configured. Please contact support.';
            } else {
                // Format phone for Selcom
                $formattedPhone = formatPhoneForSelcom($paymentCustomerPhone);
                
                // Create Selcom checkout order with redirect URLs
                $selcomResponse = createSelcomCheckoutOrderFull([
                    'order_id' => $selcomOrderId,
                    'amount' => $amount,
                    'name' => $paymentCustomerName,
                    'email' => $paymentCustomerEmail,
                    'phone' => $formattedPhone,
                    'description' => 'Payment for ' . $project['title']
                ]);
                
                // Check response
                if (isset($selcomResponse['response']['result']) && $selcomResponse['response']['result'] == 'SUCCESS') {
                    // Get checkout URL from response
                    $gatewayUrl = $selcomResponse['response']['data'][0]['payment_gateway_url'] ?? '';
                    
                    if (!$gatewayUrl) {
                        // Try alternative response format
                        $gatewayUrl = $selcomResponse['response']['payment_gateway_url'] ?? '';
                    }
                    
                    if ($gatewayUrl) {
                        // Update pending payment with checkout URL
                        $stmt = $conn->prepare("UPDATE pending_payments SET checkout_url = ? WHERE selcom_order_id = ?");
                        $stmt->execute([$gatewayUrl, $selcomOrderId]);
                        
                        // Redirect to Selcom checkout
                        header("Location: " . $gatewayUrl);
                        exit;
                    } else {
                        $error = 'Could not get payment gateway URL. Response: ' . json_encode($selcomResponse['response']);
                    }
                } else {
                    $errorMsg = $selcomResponse['response']['message'] ?? $selcomResponse['error'] ?? 'Payment gateway error';
                    $httpCode = $selcomResponse['http_code'] ?? 'N/A';
                    $error = 'Payment failed (HTTP ' . $httpCode . '): ' . $errorMsg;
                    
                    // Log error
                    error_log("Selcom Error: " . json_encode($selcomResponse));
                }
            }
        } catch (Exception $e) {
            $error = 'Payment system error: ' . $e->getMessage();
            error_log("Payment Exception: " . $e->getMessage());
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-16 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Order Summary -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-800 mb-4">Order Summary</h2>
                
                <div class="space-y-4">
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-gray-600">Reference:</span>
                        <span class="font-semibold"><?= $project['reference'] ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-gray-600">Service:</span>
                        <span class="font-semibold"><?= htmlspecialchars($project['service_name']) ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-gray-600">Project:</span>
                        <span class="font-semibold"><?= htmlspecialchars($project['title']) ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-gray-600">Total Amount:</span>
                        <span class="font-semibold"><?= formatCurrency($project['total_amount']) ?></span>
                    </div>
                    <div class="flex justify-between py-2 border-b">
                        <span class="text-gray-600">Paid Amount:</span>
                        <span class="font-semibold text-green-600"><?= formatCurrency($project['paid_amount']) ?></span>
                    </div>
                    <div class="flex justify-between py-2 text-lg">
                        <span class="font-semibold text-gray-800">Balance Due:</span>
                        <span class="font-bold text-primary"><?= formatCurrency($remainingBalance) ?></span>
                    </div>
                </div>
                
                <?php if ($remainingBalance <= 0): ?>
                <div class="mt-6 bg-green-100 text-green-800 p-4 rounded-lg text-center">
                    <i class="fas fa-check-circle text-2xl mb-2"></i>
                    <p class="font-semibold">Payment Complete!</p>
                    <a href="<?= SITE_URL ?>/customer/dashboard.php" class="text-green-600 underline">View your projects</a>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Payment Form -->
            <?php if ($remainingBalance > 0): ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6">
                    <h2 class="text-xl font-bold flex items-center">
                        <i class="fas fa-credit-card mr-2"></i>Selcom Payment
                    </h2>
                    <p class="text-green-100 text-sm">Secure mobile money payment</p>
                </div>
                
                <div class="p-6">
                    <?php if ($error): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
                        <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
                        <i class="fas fa-check-circle mr-2"></i><?= $success ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="space-y-4">
                        <?php if (empty($customerName)): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Your Name *</label>
                            <input type="text" name="customer_name" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="customer_name" value="<?= htmlspecialchars($customerName) ?>">
                        <?php endif; ?>
                        
                        <?php if (empty($customerEmail)): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                            <input type="email" name="customer_email" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="your@email.com" value="<?= htmlspecialchars($_POST['customer_email'] ?? '') ?>">
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="customer_email" value="<?= htmlspecialchars($customerEmail) ?>">
                        <?php endif; ?>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-mobile-alt mr-1 text-green-600"></i>Mobile Money Number *
                            </label>
                            <input type="tel" name="phone" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="0712 345 678" value="<?= htmlspecialchars($customerPhone) ?>">
                            <p class="text-xs text-gray-500 mt-1">M-Pesa, Tigo Pesa, Airtel Money, or Halo Pesa</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Amount (TZS)</label>
                            <input type="number" name="amount" required min="1000" max="<?= $remainingBalance ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="Enter amount" value="<?= $remainingBalance ?>">
                            <p class="text-sm text-gray-500 mt-1">Balance due: <?= formatCurrency($remainingBalance) ?></p>
                        </div>
                        
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 text-sm">
                            <p class="text-blue-800"><i class="fas fa-info-circle mr-1"></i>After clicking "Pay Now", you will be redirected to Selcom's secure checkout page to complete your payment via mobile money.</p>
                        </div>
                        
                        <button type="submit" class="w-full bg-green-500 text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition">
                            <i class="fas fa-lock mr-2"></i>Proceed to Selcom Checkout
                        </button>
                        
                        <p class="text-xs text-gray-500 text-center">
                            <i class="fas fa-shield-alt mr-1"></i>
                            Secured by Selcom Payment Gateway
                        </p>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

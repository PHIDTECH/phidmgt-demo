<?php
$pageTitle = 'Payment Successful';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->getConnection();

$order = $_GET['order'] ?? '';
$project = null;
$pendingPayment = null;
$digitalOrder = null;

// Check if this is a digital product order (DIG prefix)
if ($order && strpos($order, 'DIG') === 0) {
    // Redirect to digital download success page
    header("Location: " . SITE_URL . "/download-success.php?order=" . $order);
    exit;
}

if ($order) {
    // Check pending_payments table
    $stmt = $conn->prepare("SELECT * FROM pending_payments WHERE selcom_order_id = ?");
    $stmt->execute([$order]);
    $pendingPayment = $stmt->fetch();
    
    if ($pendingPayment) {
        // Get project details
        $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$pendingPayment['project_id']]);
        $project = $stmt->fetch();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-16 bg-gray-50 min-h-screen">
    <div class="max-w-2xl mx-auto px-4">
        <div class="bg-white rounded-xl shadow-lg p-8 text-center">
            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-check text-4xl text-green-500"></i>
            </div>
            
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Payment Successful!</h1>
            
            <p class="text-gray-600 mb-6">
                Thank you for your payment. Your order has been confirmed and we will start working on your project soon.
            </p>
            
            <?php if ($project): ?>
            <div class="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                <h3 class="font-semibold text-gray-800 mb-2">Order Details</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Reference:</span>
                        <span class="font-medium"><?= htmlspecialchars($project['reference']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Project:</span>
                        <span class="font-medium"><?= htmlspecialchars($project['title']) ?></span>
                    </div>
                    <?php if ($pendingPayment): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Amount Paid:</span>
                        <span class="font-medium text-green-600"><?= formatCurrency($pendingPayment['amount']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="space-y-3">
                <a href="<?= SITE_URL ?>" class="block w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
                    <i class="fas fa-home mr-2"></i>Back to Home
                </a>
                <?php if (isLoggedIn()): ?>
                <a href="<?= SITE_URL ?>/customer/dashboard.php" class="block w-full border border-primary text-primary py-3 rounded-lg font-semibold hover:bg-blue-50 transition">
                    <i class="fas fa-tachometer-alt mr-2"></i>View Dashboard
                </a>
                <?php endif; ?>
            </div>
            
            <p class="text-sm text-gray-500 mt-6">
                <i class="fas fa-envelope mr-1"></i>
                A confirmation email has been sent to your email address.
            </p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$pageTitle = 'Payment Cancelled';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->getConnection();

$order = $_GET['order'] ?? '';
$project = null;

if ($order) {
    // Check pending_payments table and update status
    $stmt = $conn->prepare("UPDATE pending_payments SET status = 'failed', updated_at = NOW() WHERE selcom_order_id = ?");
    $stmt->execute([$order]);
    
    // Get pending payment to find project
    $stmt = $conn->prepare("SELECT * FROM pending_payments WHERE selcom_order_id = ?");
    $stmt->execute([$order]);
    $pendingPayment = $stmt->fetch();
    
    if ($pendingPayment) {
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
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-times text-4xl text-red-500"></i>
            </div>
            
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Payment Cancelled</h1>
            
            <p class="text-gray-600 mb-6">
                Your payment was cancelled or could not be completed. Don't worry, no charges have been made to your account.
            </p>
            
            <?php if ($project): ?>
            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <p class="text-sm text-gray-600">
                    Order Reference: <strong><?= htmlspecialchars($project['reference']) ?></strong>
                </p>
            </div>
            
            <div class="space-y-3">
                <a href="<?= SITE_URL ?>/payment.php?order=<?= htmlspecialchars($project['reference']) ?>" 
                   class="block w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
                    <i class="fas fa-redo mr-2"></i>Try Payment Again
                </a>
                <a href="<?= SITE_URL ?>" class="block w-full border border-gray-300 text-gray-700 py-3 rounded-lg font-semibold hover:bg-gray-50 transition">
                    <i class="fas fa-home mr-2"></i>Back to Home
                </a>
            </div>
            <?php else: ?>
            <a href="<?= SITE_URL ?>" class="block w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
                <i class="fas fa-home mr-2"></i>Back to Home
            </a>
            <?php endif; ?>
            
            <p class="text-sm text-gray-500 mt-6">
                <i class="fas fa-question-circle mr-1"></i>
                Need help? <a href="<?= SITE_URL ?>/contact.php" class="text-primary underline">Contact us</a>
            </p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

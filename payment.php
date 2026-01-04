<?php
$pageTitle = 'Payment';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/notifications.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();
$notifier = new NotificationService($conn);

$error = '';
$success = '';
$project = null;

// Get order
if (isset($_GET['order'])) {
    $stmt = $conn->prepare("
        SELECT p.*, s.name as service_name, u.name as customer_name, u.email as customer_email
        FROM projects p
        JOIN services s ON p.service_id = s.id
        JOIN users u ON p.customer_id = u.id
        WHERE p.reference = ? AND p.customer_id = ?
    ");
    $stmt->execute([$_GET['order'], $_SESSION['user_id']]);
    $project = $stmt->fetch();
}

if (!$project) {
    redirect('/customer/dashboard.php');
}

// Calculate remaining balance
$remainingBalance = $project['total_amount'] - $project['paid_amount'];

// Process payment (Selcom simulation)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = floatval($_POST['amount'] ?? 0);
    $paymentMethod = sanitize($_POST['payment_method'] ?? 'mobile_money');
    $phone = sanitize($_POST['phone'] ?? '');
    
    if ($amount <= 0) {
        $error = 'Please enter a valid amount';
    } elseif ($amount > $remainingBalance) {
        $error = 'Amount exceeds remaining balance';
    } else {
        // Simulate Selcom payment processing
        $transactionId = 'SELCOM' . date('YmdHis') . rand(1000, 9999);
        $paymentRef = generateReference('PAY');
        
        // Create payment record
        $stmt = $conn->prepare("
            INSERT INTO payments (project_id, reference, amount, payment_method, transaction_id, status, payment_date)
            VALUES (?, ?, ?, ?, ?, 'completed', NOW())
        ");
        $stmt->execute([$project['id'], $paymentRef, $amount, $paymentMethod, $transactionId]);
        $paymentId = $conn->lastInsertId();
        
        // Update project paid amount
        $newPaidAmount = $project['paid_amount'] + $amount;
        $newStatus = $newPaidAmount >= $project['total_amount'] ? STATUS_PAYMENT_RECEIVED : $project['status'];
        
        $stmt = $conn->prepare("UPDATE projects SET paid_amount = ?, status = ? WHERE id = ?");
        $stmt->execute([$newPaidAmount, $newStatus, $project['id']]);
        
        // Record revenue
        $stmt = $conn->prepare("
            INSERT INTO revenue (project_id, payment_id, description, amount, category, date, recorded_by)
            VALUES (?, ?, ?, ?, 'Service Payment', CURDATE(), ?)
        ");
        $stmt->execute([
            $project['id'],
            $paymentId,
            "Payment for {$project['title']}",
            $amount,
            $_SESSION['user_id']
        ]);
        
        // Get payment and customer for notification
        $stmt = $conn->prepare("SELECT * FROM payments WHERE id = ?");
        $stmt->execute([$paymentId]);
        $payment = $stmt->fetch();
        
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $customer = $stmt->fetch();
        
        // Refresh project
        $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$project['id']]);
        $updatedProject = $stmt->fetch();
        
        // Send notifications
        $notifier->notifyPaymentReceived($updatedProject, $payment, $customer);
        
        $success = 'Payment successful! Transaction ID: ' . $transactionId;
        
        // Refresh page data
        $project['paid_amount'] = $newPaidAmount;
        $project['status'] = $newStatus;
        $remainingBalance = $project['total_amount'] - $newPaidAmount;
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
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                            <select name="payment_method" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500">
                                <option value="mpesa">M-Pesa</option>
                                <option value="tigopesa">Tigo Pesa</option>
                                <option value="airtelmoney">Airtel Money</option>
                                <option value="halopesa">Halo Pesa</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                            <input type="tel" name="phone" required 
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="0700 000 000">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Amount (TZS)</label>
                            <input type="number" name="amount" required min="1000" max="<?= $remainingBalance ?>"
                                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500"
                                   placeholder="Enter amount" value="<?= $remainingBalance ?>">
                            <p class="text-sm text-gray-500 mt-1">Maximum: <?= formatCurrency($remainingBalance) ?></p>
                        </div>
                        
                        <button type="submit" class="w-full bg-green-500 text-white py-3 rounded-lg font-semibold hover:bg-green-600 transition">
                            <i class="fas fa-lock mr-2"></i>Pay Now
                        </button>
                        
                        <p class="text-xs text-gray-500 text-center">
                            <i class="fas fa-shield-alt mr-1"></i>
                            Secured by Selcom Payment Gateway (Simulated)
                        </p>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

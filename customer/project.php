<?php
$pageTitle = 'Project Details';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== ROLE_CUSTOMER) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();

$projectId = intval($_GET['id'] ?? 0);

// Get project details
$stmt = $conn->prepare("
    SELECT p.*, s.name as service_name, s.features as service_features
    FROM projects p 
    JOIN services s ON p.service_id = s.id 
    WHERE p.id = ? AND p.customer_id = ?
");
$stmt->execute([$projectId, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    redirect('/customer/dashboard.php');
}

// Get payments
$stmt = $conn->prepare("SELECT * FROM payments WHERE project_id = ? ORDER BY created_at DESC");
$stmt->execute([$projectId]);
$payments = $stmt->fetchAll();

// Get tasks progress
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status IN ('approved', 'completed') THEN 1 ELSE 0 END) as completed
    FROM tasks WHERE project_id = ?
");
$stmt->execute([$projectId]);
$taskProgress = $stmt->fetch();

$progressPercent = $taskProgress['total'] > 0 
    ? round(($taskProgress['completed'] / $taskProgress['total']) * 100) 
    : 0;

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-6">
        <a href="<?= SITE_URL ?>/customer/dashboard.php" class="text-primary hover:underline">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Main Content -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Project Info -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($project['title']) ?></h1>
                        <p class="text-gray-500 font-mono"><?= $project['reference'] ?></p>
                    </div>
                    <?php
                    $statusColors = [
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'payment_received' => 'bg-blue-100 text-blue-800',
                        'in_progress' => 'bg-purple-100 text-purple-800',
                        'review' => 'bg-orange-100 text-orange-800',
                        'completed' => 'bg-green-100 text-green-800',
                        'cancelled' => 'bg-red-100 text-red-800'
                    ];
                    $color = $statusColors[$project['status']] ?? 'bg-gray-100 text-gray-800';
                    ?>
                    <span class="px-4 py-2 rounded-full text-sm font-medium <?= $color ?>">
                        <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                    </span>
                </div>
                
                <div class="border-t pt-4 mt-4">
                    <h3 class="font-semibold text-gray-800 mb-2">Service</h3>
                    <p class="text-gray-600"><?= htmlspecialchars($project['service_name']) ?></p>
                </div>
                
                <?php if ($project['description']): ?>
                <div class="border-t pt-4 mt-4">
                    <h3 class="font-semibold text-gray-800 mb-2">Description</h3>
                    <p class="text-gray-600"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                </div>
                <?php endif; ?>
                
                <?php if ($project['requirements']): ?>
                <div class="border-t pt-4 mt-4">
                    <h3 class="font-semibold text-gray-800 mb-2">Requirements</h3>
                    <p class="text-gray-600"><?= nl2br(htmlspecialchars($project['requirements'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Progress -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Project Progress</h2>
                <div class="mb-2 flex justify-between text-sm">
                    <span class="text-gray-600">Progress</span>
                    <span class="font-semibold"><?= $progressPercent ?>%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-4">
                    <div class="bg-primary h-4 rounded-full transition-all duration-500" style="width: <?= $progressPercent ?>%"></div>
                </div>
                <p class="text-sm text-gray-500 mt-2">
                    <?= $taskProgress['completed'] ?> of <?= $taskProgress['total'] ?> tasks completed
                </p>
            </div>
            
            <!-- Payments History -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Payment History</h2>
                
                <?php if (empty($payments)): ?>
                <p class="text-gray-500 text-center py-4">No payments recorded yet</p>
                <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($payments as $payment): ?>
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div>
                            <p class="font-medium"><?= formatCurrency($payment['amount']) ?></p>
                            <p class="text-sm text-gray-500"><?= $payment['payment_method'] ?> • <?= $payment['transaction_id'] ?></p>
                        </div>
                        <div class="text-right">
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">
                                <?= ucfirst($payment['status']) ?>
                            </span>
                            <p class="text-sm text-gray-500 mt-1"><?= formatDateTime($payment['payment_date']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="space-y-6">
            <!-- Payment Summary -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Payment Summary</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Total Amount</span>
                        <span class="font-semibold"><?= formatCurrency($project['total_amount']) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Paid Amount</span>
                        <span class="font-semibold text-green-600"><?= formatCurrency($project['paid_amount']) ?></span>
                    </div>
                    <div class="border-t pt-3">
                        <div class="flex justify-between">
                            <span class="font-semibold text-gray-800">Balance Due</span>
                            <span class="font-bold text-primary"><?= formatCurrency($project['total_amount'] - $project['paid_amount']) ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if ($project['paid_amount'] < $project['total_amount']): ?>
                <a href="<?= SITE_URL ?>/payment.php?order=<?= $project['reference'] ?>" 
                   class="block w-full bg-green-500 text-white text-center py-3 rounded-lg font-semibold hover:bg-green-600 transition mt-4">
                    <i class="fas fa-credit-card mr-2"></i>Pay Now
                </a>
                <?php endif; ?>
            </div>
            
            <!-- Timeline -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Timeline</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Created</span>
                        <span><?= formatDate($project['created_at']) ?></span>
                    </div>
                    <?php if ($project['start_date']): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Started</span>
                        <span><?= formatDate($project['start_date']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Due Date</span>
                        <span class="<?= strtotime($project['due_date']) < time() && $project['status'] !== 'completed' ? 'text-red-600' : '' ?>">
                            <?= formatDate($project['due_date']) ?>
                        </span>
                    </div>
                    <?php if ($project['completed_date']): ?>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Completed</span>
                        <span class="text-green-600"><?= formatDate($project['completed_date']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Features -->
            <?php if ($project['service_features']): ?>
            <div class="bg-white rounded-xl shadow-md p-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Included Features</h2>
                <ul class="space-y-2">
                    <?php foreach (explode(',', $project['service_features']) as $feature): ?>
                    <li class="flex items-center text-sm text-gray-600">
                        <i class="fas fa-check text-green-500 mr-2"></i>
                        <?= trim($feature) ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

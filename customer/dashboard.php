<?php
$pageTitle = 'My Dashboard';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== ROLE_CUSTOMER) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();

// Get customer's projects
$stmt = $conn->prepare("
    SELECT p.*, s.name as service_name 
    FROM projects p 
    JOIN services s ON p.service_id = s.id 
    WHERE p.customer_id = ? 
    ORDER BY p.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();

// Get unread messages
$stmt = $conn->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0");
$stmt->execute([$_SESSION['user_id']]);
$unreadMessages = $stmt->fetchColumn();

// Calculate totals
$totalProjects = count($projects);
$totalSpent = array_sum(array_column($projects, 'paid_amount'));
$activeProjects = count(array_filter($projects, fn($p) => !in_array($p['status'], ['completed', 'cancelled'])));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-800">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?>!</h1>
        <p class="text-gray-600">Manage your projects and track progress</p>
    </div>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Projects</p>
                    <p class="text-3xl font-bold text-gray-800"><?= $totalProjects ?></p>
                </div>
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-folder text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Active Projects</p>
                    <p class="text-3xl font-bold text-gray-800"><?= $activeProjects ?></p>
                </div>
                <div class="w-12 h-12 bg-green-100 text-green-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-spinner text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Total Spent</p>
                    <p class="text-2xl font-bold text-gray-800"><?= formatCurrency($totalSpent) ?></p>
                </div>
                <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-credit-card text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-500 text-sm">Messages</p>
                    <p class="text-3xl font-bold text-gray-800"><?= $unreadMessages ?></p>
                </div>
                <div class="w-12 h-12 bg-red-100 text-red-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-envelope text-xl"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="flex flex-wrap gap-4 mb-8">
        <a href="<?= SITE_URL ?>/services.php" class="bg-primary text-white px-6 py-3 rounded-lg font-semibold hover:bg-secondary transition">
            <i class="fas fa-plus mr-2"></i>New Project
        </a>
        <a href="<?= SITE_URL ?>/customer/messages.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-semibold hover:bg-gray-300 transition">
            <i class="fas fa-envelope mr-2"></i>Messages
            <?php if ($unreadMessages > 0): ?>
            <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full ml-1"><?= $unreadMessages ?></span>
            <?php endif; ?>
        </a>
    </div>
    
    <!-- Projects List -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-6 border-b">
            <h2 class="text-xl font-bold text-gray-800">My Projects</h2>
        </div>
        
        <?php if (empty($projects)): ?>
        <div class="p-12 text-center">
            <div class="w-16 h-16 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-folder-open text-2xl"></i>
            </div>
            <p class="text-gray-500 mb-4">You don't have any projects yet</p>
            <a href="<?= SITE_URL ?>/services.php" class="text-primary font-semibold hover:underline">
                Browse our services to get started
            </a>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($projects as $project): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <span class="font-mono text-sm"><?= $project['reference'] ?></span>
                        </td>
                        <td class="px-6 py-4">
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($project['title']) ?></p>
                            <p class="text-sm text-gray-500"><?= formatDate($project['created_at']) ?></p>
                        </td>
                        <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($project['service_name']) ?></td>
                        <td class="px-6 py-4">
                            <p class="font-medium"><?= formatCurrency($project['total_amount']) ?></p>
                            <?php if ($project['paid_amount'] < $project['total_amount']): ?>
                            <p class="text-sm text-red-500">Paid: <?= formatCurrency($project['paid_amount']) ?></p>
                            <?php else: ?>
                            <p class="text-sm text-green-500">Fully Paid</p>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
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
                            <span class="px-3 py-1 rounded-full text-xs font-medium <?= $color ?>">
                                <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <a href="<?= SITE_URL ?>/customer/project.php?id=<?= $project['id'] ?>" 
                               class="text-primary hover:underline">View Details</a>
                            <?php if ($project['paid_amount'] < $project['total_amount']): ?>
                            <a href="<?= SITE_URL ?>/payment.php?order=<?= $project['reference'] ?>" 
                               class="text-green-600 hover:underline ml-3">Pay Now</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

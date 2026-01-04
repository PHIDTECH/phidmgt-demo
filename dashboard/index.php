<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] === ROLE_CUSTOMER) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();

// Get statistics based on role
$stats = [];

// Projects stats
$stmt = $conn->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
FROM projects");
$projectStats = $stmt->fetch();

// Revenue stats (this month)
$stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM revenue WHERE MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE())");
$monthlyRevenue = $stmt->fetchColumn();

// Pending tasks for current user
$stmt = $conn->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status NOT IN ('approved', 'rejected')");
$stmt->execute([$_SESSION['user_id']]);
$myTasks = $stmt->fetchColumn();

// Pending leave applications
$stmt = $conn->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'pending'");
$pendingLeave = $stmt->fetchColumn();

// Recent projects
$stmt = $conn->query("
    SELECT p.*, s.name as service_name, u.name as customer_name 
    FROM projects p 
    JOIN services s ON p.service_id = s.id 
    JOIN users u ON p.customer_id = u.id 
    ORDER BY p.created_at DESC LIMIT 5
");
$recentProjects = $stmt->fetchAll();

// Recent payments
$stmt = $conn->query("
    SELECT pay.*, p.title as project_title, p.reference as project_ref
    FROM payments pay
    JOIN projects p ON pay.project_id = p.id
    WHERE pay.status = 'completed'
    ORDER BY pay.created_at DESC LIMIT 5
");
$recentPayments = $stmt->fetchAll();

// My pending tasks
$stmt = $conn->prepare("
    SELECT t.*, p.title as project_title 
    FROM tasks t 
    JOIN projects p ON t.project_id = p.id 
    WHERE t.assigned_to = ? AND t.status NOT IN ('approved')
    ORDER BY t.due_date ASC LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$pendingTasks = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Projects</p>
                <p class="text-3xl font-bold text-gray-800"><?= $projectStats['total'] ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-project-diagram text-xl"></i>
            </div>
        </div>
        <div class="mt-3 text-sm">
            <span class="text-yellow-600"><?= $projectStats['pending'] ?> pending</span> • 
            <span class="text-purple-600"><?= $projectStats['in_progress'] ?> active</span>
        </div>
    </div>
    
    <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT])): ?>
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Monthly Revenue</p>
                <p class="text-2xl font-bold text-gray-800"><?= formatCurrency($monthlyRevenue) ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 text-green-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-coins text-xl"></i>
            </div>
        </div>
        <p class="mt-3 text-sm text-gray-500"><?= date('F Y') ?></p>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">My Tasks</p>
                <p class="text-3xl font-bold text-gray-800"><?= $myTasks ?></p>
            </div>
            <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-tasks text-xl"></i>
            </div>
        </div>
        <a href="<?= SITE_URL ?>/dashboard/tasks.php" class="mt-3 text-sm text-primary hover:underline block">View Tasks →</a>
    </div>
    
    <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_HR])): ?>
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Pending Leave</p>
                <p class="text-3xl font-bold text-gray-800"><?= $pendingLeave ?></p>
            </div>
            <div class="w-12 h-12 bg-orange-100 text-orange-600 rounded-lg flex items-center justify-center">
                <i class="fas fa-calendar-alt text-xl"></i>
            </div>
        </div>
        <a href="<?= SITE_URL ?>/dashboard/hr/leave.php" class="mt-3 text-sm text-primary hover:underline block">Review →</a>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Projects -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="font-bold text-gray-800">Recent Projects</h2>
            <a href="<?= SITE_URL ?>/dashboard/projects.php" class="text-sm text-primary hover:underline">View All</a>
        </div>
        <div class="divide-y">
            <?php foreach ($recentProjects as $project): ?>
            <div class="p-4 hover:bg-gray-50">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($project['title']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($project['customer_name']) ?></p>
                    </div>
                    <?php
                    $statusColors = [
                        'pending' => 'bg-yellow-100 text-yellow-800',
                        'payment_received' => 'bg-blue-100 text-blue-800',
                        'in_progress' => 'bg-purple-100 text-purple-800',
                        'completed' => 'bg-green-100 text-green-800'
                    ];
                    $color = $statusColors[$project['status']] ?? 'bg-gray-100 text-gray-800';
                    ?>
                    <span class="px-2 py-1 rounded-full text-xs font-medium <?= $color ?>">
                        <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- My Tasks -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="font-bold text-gray-800">My Pending Tasks</h2>
            <a href="<?= SITE_URL ?>/dashboard/tasks.php" class="text-sm text-primary hover:underline">View All</a>
        </div>
        <?php if (empty($pendingTasks)): ?>
        <div class="p-8 text-center text-gray-500">
            <i class="fas fa-check-circle text-4xl text-green-300 mb-2"></i>
            <p>No pending tasks!</p>
        </div>
        <?php else: ?>
        <div class="divide-y">
            <?php foreach ($pendingTasks as $task): ?>
            <div class="p-4 hover:bg-gray-50">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($task['title']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($task['project_title']) ?></p>
                    </div>
                    <div class="text-right">
                        <?php
                        $taskColors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'in_progress' => 'bg-blue-100 text-blue-800',
                            'submitted' => 'bg-purple-100 text-purple-800'
                        ];
                        $taskColor = $taskColors[$task['status']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $taskColor ?>">
                            <?= ucfirst(str_replace('_', ' ', $task['status'])) ?>
                        </span>
                        <?php if ($task['due_date']): ?>
                        <p class="text-xs text-gray-500 mt-1">Due: <?= formatDate($task['due_date']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT])): ?>
    <!-- Recent Payments -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="font-bold text-gray-800">Recent Payments</h2>
            <a href="<?= SITE_URL ?>/dashboard/accounting/revenue.php" class="text-sm text-primary hover:underline">View All</a>
        </div>
        <div class="divide-y">
            <?php foreach ($recentPayments as $payment): ?>
            <div class="p-4 hover:bg-gray-50">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($payment['project_title']) ?></p>
                        <p class="text-sm text-gray-500"><?= $payment['project_ref'] ?></p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold text-green-600"><?= formatCurrency($payment['amount']) ?></p>
                        <p class="text-xs text-gray-500"><?= formatDate($payment['payment_date']) ?></p>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

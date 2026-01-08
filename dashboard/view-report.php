<?php
/**
 * View Weekly Report Details
 * Shows full report with manager/CEO reviews
 */
$pageTitle = 'View Report';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$reportId = $_GET['id'] ?? null;
if (!$reportId) {
    header("Location: " . SITE_URL . "/dashboard/my-reports.php");
    exit;
}

// Get report
$stmt = $conn->prepare("SELECT * FROM weekly_reports WHERE id = ?");
$stmt->execute([$reportId]);
$report = $stmt->fetch();

if (!$report) {
    header("Location: " . SITE_URL . "/dashboard/my-reports.php");
    exit;
}

// Check access - staff can only view their own, managers/CEO can view all
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'];
if ($report['staff_id'] != $userId && !hasRole([ROLE_MANAGER, ROLE_CEO, ROLE_SUPERADMIN])) {
    header("Location: " . SITE_URL . "/dashboard/my-reports.php");
    exit;
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-file-alt text-primary mr-2"></i>Weekly Report
            </h1>
            <p class="text-gray-600">
                <?= date('M j', strtotime($report['week_start'])) ?> - <?= date('M j, Y', strtotime($report['week_end'])) ?>
                by <?= htmlspecialchars($report['staff_name']) ?>
            </p>
        </div>
        <div class="flex gap-2">
            <?php if (hasRole([ROLE_MANAGER, ROLE_CEO, ROLE_SUPERADMIN])): ?>
            <a href="<?= SITE_URL ?>/dashboard/review-reports.php" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition">
                <i class="fas fa-arrow-left mr-2"></i>Back to Reviews
            </a>
            <?php else: ?>
            <a href="<?= SITE_URL ?>/dashboard/my-reports.php" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition">
                <i class="fas fa-arrow-left mr-2"></i>My Reports
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Status Badge -->
    <div class="mb-6">
        <?php
        $statusColors = [
            'draft' => 'bg-gray-100 text-gray-700',
            'submitted' => 'bg-blue-100 text-blue-700',
            'manager_reviewed' => 'bg-yellow-100 text-yellow-700',
            'ceo_reviewed' => 'bg-purple-100 text-purple-700',
            'approved' => 'bg-green-100 text-green-700',
            'rejected' => 'bg-red-100 text-red-700'
        ];
        $statusLabels = [
            'draft' => 'Draft',
            'submitted' => 'Pending Manager Review',
            'manager_reviewed' => 'Pending CEO Review',
            'ceo_reviewed' => 'CEO Reviewed',
            'approved' => 'Approved',
            'rejected' => 'Rejected'
        ];
        ?>
        <span class="px-4 py-2 text-sm rounded-full <?= $statusColors[$report['status']] ?>">
            <i class="fas fa-circle text-xs mr-2"></i><?= $statusLabels[$report['status']] ?>
        </span>
    </div>
    
    <!-- Basic Info -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
            <i class="fas fa-info-circle text-primary mr-2"></i>Basic Information
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <p class="text-sm text-gray-500">Staff Name</p>
                <p class="font-medium"><?= htmlspecialchars($report['staff_name']) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Branch</p>
                <p class="font-medium"><?= htmlspecialchars($report['branch']) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Week Period</p>
                <p class="font-medium"><?= date('M j', strtotime($report['week_start'])) ?> - <?= date('M j, Y', strtotime($report['week_end'])) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Submitted</p>
                <p class="font-medium"><?= $report['submitted_at'] ? date('M j, Y g:i A', strtotime($report['submitted_at'])) : 'Not submitted' ?></p>
            </div>
        </div>
    </div>
    
    <!-- Task Management -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
            <i class="fas fa-tasks text-primary mr-2"></i>Task Management
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-gray-500 mb-1">Tasks Planned</p>
                <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($report['tasks_planned']) ?: '-' ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">Tasks Completed ✅</p>
                <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($report['tasks_completed']) ?: '-' ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">Tasks In Progress 🔄</p>
                <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($report['tasks_in_progress']) ?: '-' ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">Tasks Overdue ⚠️</p>
                <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($report['tasks_overdue']) ?: '-' ?></p>
            </div>
            <div class="md:col-span-2">
                <p class="text-sm text-gray-500 mb-1">Reasons for Overdue</p>
                <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($report['overdue_reasons']) ?: '-' ?></p>
            </div>
        </div>
    </div>
    
    <!-- Performance Metrics -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
            <i class="fas fa-chart-bar text-primary mr-2"></i>Performance Metrics (KPIs)
        </h3>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="bg-blue-50 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-blue-600"><?= $report['projects_handled'] ?></p>
                <p class="text-sm text-gray-600">Projects Handled</p>
            </div>
            <div class="bg-green-50 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-green-600"><?= $report['projects_completed'] ?></p>
                <p class="text-sm text-gray-600">Completed</p>
            </div>
            <div class="bg-purple-50 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-purple-600"><?= $report['customer_interactions'] ?></p>
                <p class="text-sm text-gray-600">Customer Interactions</p>
            </div>
            <div class="bg-orange-50 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-orange-600"><?= $report['new_customers'] ?></p>
                <p class="text-sm text-gray-600">New Customers</p>
            </div>
            <div class="bg-teal-50 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-teal-600"><?= $report['support_tickets_resolved'] ?></p>
                <p class="text-sm text-gray-600">Tickets Resolved</p>
            </div>
        </div>
    </div>
    
    <!-- Marketing & Sales -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
            <i class="fas fa-bullhorn text-primary mr-2"></i>Marketing & Sales
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
            <div>
                <p class="text-sm text-gray-500 mb-1">Marketing Activities This Week</p>
                <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($report['marketing_activities']) ?: '-' ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">Marketing Plan for Next Week</p>
                <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($report['marketing_plan_next_week']) ?: '-' ?></p>
            </div>
        </div>
        <div class="grid grid-cols-3 gap-4">
            <div class="bg-indigo-50 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-indigo-600"><?= $report['leads_generated'] ?></p>
                <p class="text-sm text-gray-600">Leads Generated</p>
            </div>
            <div class="bg-pink-50 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-pink-600"><?= $report['conversions'] ?></p>
                <p class="text-sm text-gray-600">Conversions</p>
            </div>
            <div class="bg-green-50 rounded-lg p-4 text-center">
                <p class="text-2xl font-bold text-green-600">TZS <?= number_format($report['revenue_generated']) ?></p>
                <p class="text-sm text-gray-600">Revenue Generated</p>
            </div>
        </div>
    </div>
    
    <!-- Learning & Challenges -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-graduation-cap text-primary mr-2"></i>Learning & Development
            </h3>
            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-500 mb-1">Skills Learned</p>
                    <p class="text-gray-800"><?= htmlspecialchars($report['skills_learned']) ?: '-' ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 mb-1">Training Attended</p>
                    <p class="text-gray-800"><?= htmlspecialchars($report['training_attended']) ?: '-' ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-exclamation-triangle text-primary mr-2"></i>Challenges & Solutions
            </h3>
            <div class="space-y-4">
                <div>
                    <p class="text-sm text-gray-500 mb-1">Challenges Faced</p>
                    <p class="text-gray-800"><?= htmlspecialchars($report['challenges_faced']) ?: '-' ?></p>
                </div>
                <div>
                    <p class="text-sm text-gray-500 mb-1">Solutions Implemented</p>
                    <p class="text-gray-800"><?= htmlspecialchars($report['solutions_implemented']) ?: '-' ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Staff Recommendations -->
    <div class="bg-white rounded-xl shadow p-6 mb-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
            <i class="fas fa-lightbulb text-primary mr-2"></i>Staff Recommendations
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <p class="text-sm text-gray-500 mb-1">Recommendations</p>
                <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($report['staff_recommendations']) ?: '-' ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">Resources Needed</p>
                <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($report['resources_needed']) ?: '-' ?></p>
            </div>
        </div>
    </div>
    
    <!-- Manager Review -->
    <?php if ($report['manager_recommendations'] || $report['manager_rating']): ?>
    <div class="bg-yellow-50 rounded-xl shadow p-6 mb-6 border-l-4 border-yellow-500">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-user-tie text-yellow-600 mr-2"></i>Manager Review
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-500 mb-1">Rating</p>
                <p class="font-medium capitalize"><?= str_replace('_', ' ', $report['manager_rating']) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">Reviewed At</p>
                <p class="font-medium"><?= $report['manager_reviewed_at'] ? date('M j, Y g:i A', strtotime($report['manager_reviewed_at'])) : '-' ?></p>
            </div>
            <div class="md:col-span-2">
                <p class="text-sm text-gray-500 mb-1">Manager Recommendations</p>
                <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($report['manager_recommendations']) ?: '-' ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- CEO Review -->
    <?php if ($report['ceo_recommendations'] || $report['ceo_rating']): ?>
    <div class="bg-purple-50 rounded-xl shadow p-6 mb-6 border-l-4 border-purple-500">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-crown text-purple-600 mr-2"></i>CEO Review
        </h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-500 mb-1">Rating</p>
                <p class="font-medium capitalize"><?= str_replace('_', ' ', $report['ceo_rating']) ?></p>
            </div>
            <div>
                <p class="text-sm text-gray-500 mb-1">Reviewed At</p>
                <p class="font-medium"><?= $report['ceo_reviewed_at'] ? date('M j, Y g:i A', strtotime($report['ceo_reviewed_at'])) : '-' ?></p>
            </div>
            <div class="md:col-span-2">
                <p class="text-sm text-gray-500 mb-1">CEO Recommendations</p>
                <p class="text-gray-800 whitespace-pre-line"><?= htmlspecialchars($report['ceo_recommendations']) ?: '-' ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

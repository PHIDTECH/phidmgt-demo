<?php
/**
 * My Weekly Reports
 * Staff can view their submitted reports and status
 */
$pageTitle = 'My Weekly Reports';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Create table if not exists
try {
    $conn->exec("
        CREATE TABLE IF NOT EXISTS weekly_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            staff_id INT NOT NULL,
            staff_name VARCHAR(255),
            branch VARCHAR(100),
            report_date DATE NOT NULL,
            week_start DATE,
            week_end DATE,
            tasks_planned TEXT,
            tasks_completed TEXT,
            tasks_in_progress TEXT,
            tasks_overdue TEXT,
            overdue_reasons TEXT,
            projects_handled INT DEFAULT 0,
            projects_completed INT DEFAULT 0,
            customer_interactions INT DEFAULT 0,
            new_customers INT DEFAULT 0,
            support_tickets_resolved INT DEFAULT 0,
            marketing_activities TEXT,
            marketing_plan_next_week TEXT,
            leads_generated INT DEFAULT 0,
            conversions INT DEFAULT 0,
            revenue_generated DECIMAL(15,2) DEFAULT 0,
            skills_learned TEXT,
            training_attended TEXT,
            challenges_faced TEXT,
            solutions_implemented TEXT,
            staff_recommendations TEXT,
            resources_needed TEXT,
            manager_id INT,
            manager_recommendations TEXT,
            manager_rating ENUM('excellent','good','satisfactory','needs_improvement','poor'),
            manager_reviewed_at DATETIME,
            ceo_id INT,
            ceo_recommendations TEXT,
            ceo_rating ENUM('excellent','good','satisfactory','needs_improvement','poor'),
            ceo_reviewed_at DATETIME,
            status ENUM('draft','submitted','manager_reviewed','ceo_reviewed','approved','rejected') DEFAULT 'draft',
            submitted_at DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Table exists
}

$userId = $_SESSION['user_id'];

// Get all reports for this staff
$stmt = $conn->prepare("
    SELECT * FROM weekly_reports 
    WHERE staff_id = ? 
    ORDER BY week_start DESC
");
$stmt->execute([$userId]);
$reports = $stmt->fetchAll();

// Get stats
$totalReports = count($reports);
$submittedReports = count(array_filter($reports, fn($r) => $r['status'] !== 'draft'));
$approvedReports = count(array_filter($reports, fn($r) => $r['status'] === 'approved'));
$pendingReports = count(array_filter($reports, fn($r) => in_array($r['status'], ['submitted', 'manager_reviewed'])));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-file-alt text-primary mr-2"></i>My Weekly Reports
            </h1>
            <p class="text-gray-600">View and manage your weekly performance reports</p>
        </div>
        <a href="<?= SITE_URL ?>/dashboard/weekly-report.php" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition">
            <i class="fas fa-plus mr-2"></i>New Report
        </a>
    </div>
    
    <?php if (isset($_GET['success'])): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
        <i class="fas fa-check-circle mr-2"></i>
        <?php if ($_GET['success'] === 'submitted'): ?>
            Report submitted successfully! It will be reviewed by your manager and CEO.
        <?php else: ?>
            Action completed successfully.
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Total Reports</p>
                    <p class="text-xl font-bold"><?= $totalReports ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-yellow-100 text-yellow-600 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Pending Review</p>
                    <p class="text-xl font-bold"><?= $pendingReports ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-green-100 text-green-600 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Approved</p>
                    <p class="text-xl font-bold"><?= $approvedReports ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Submitted</p>
                    <p class="text-xl font-bold"><?= $submittedReports ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reports Table -->
    <div class="bg-white rounded-xl shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Week</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Branch</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Submitted</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Manager Rating</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">CEO Rating</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                            <i class="fas fa-file-alt text-4xl mb-2 text-gray-300"></i>
                            <p>No reports yet. Submit your first weekly report!</p>
                            <a href="<?= SITE_URL ?>/dashboard/weekly-report.php" class="text-primary hover:underline mt-2 inline-block">
                                <i class="fas fa-plus mr-1"></i>Create Report
                            </a>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-800">
                                <?= date('M j', strtotime($report['week_start'])) ?> - <?= date('M j, Y', strtotime($report['week_end'])) ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 text-gray-600"><?= htmlspecialchars($report['branch']) ?></td>
                        <td class="px-6 py-4 text-gray-600">
                            <?= $report['submitted_at'] ? date('M j, Y', strtotime($report['submitted_at'])) : '-' ?>
                        </td>
                        <td class="px-6 py-4">
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
                                'submitted' => 'Pending Manager',
                                'manager_reviewed' => 'Pending CEO',
                                'ceo_reviewed' => 'CEO Reviewed',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected'
                            ];
                            ?>
                            <span class="px-2 py-1 text-xs rounded-full <?= $statusColors[$report['status']] ?>">
                                <?= $statusLabels[$report['status']] ?>
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($report['manager_rating']): ?>
                            <span class="text-sm capitalize"><?= str_replace('_', ' ', $report['manager_rating']) ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <?php if ($report['ceo_rating']): ?>
                            <span class="text-sm capitalize"><?= str_replace('_', ' ', $report['ceo_rating']) ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex gap-2">
                                <a href="<?= SITE_URL ?>/dashboard/view-report.php?id=<?= $report['id'] ?>" 
                                   class="text-primary hover:text-secondary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($report['status'] === 'draft'): ?>
                                <a href="<?= SITE_URL ?>/dashboard/weekly-report.php?id=<?= $report['id'] ?>" 
                                   class="text-yellow-600 hover:text-yellow-700" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

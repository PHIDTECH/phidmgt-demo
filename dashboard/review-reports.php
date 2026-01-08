<?php
/**
 * Review Weekly Reports
 * Manager/CEO review and approve staff reports
 */
$pageTitle = 'Review Reports';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check access - Manager/CEO only
if (!isLoggedIn() || !hasRole([ROLE_MANAGER, ROLE_CEO, ROLE_SUPERADMIN])) {
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
$userRole = $_SESSION['user_role'];
$error = '';
$success = '';

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = $_POST['report_id'] ?? null;
    $action = $_POST['action'] ?? '';
    
    if ($reportId) {
        // Get current report
        $stmt = $conn->prepare("SELECT * FROM weekly_reports WHERE id = ?");
        $stmt->execute([$reportId]);
        $report = $stmt->fetch();
        
        if ($report) {
            if ($userRole === ROLE_MANAGER || $userRole === ROLE_SUPERADMIN) {
                // Manager review
                if ($action === 'manager_review' && $report['status'] === 'submitted') {
                    $stmt = $conn->prepare("
                        UPDATE weekly_reports SET 
                            manager_id = ?,
                            manager_recommendations = ?,
                            manager_rating = ?,
                            manager_reviewed_at = NOW(),
                            status = 'manager_reviewed'
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $userId,
                        $_POST['manager_recommendations'] ?? '',
                        $_POST['manager_rating'] ?? 'satisfactory',
                        $reportId
                    ]);
                    $success = 'Report reviewed successfully. Forwarded to CEO.';
                }
            }
            
            if ($userRole === ROLE_CEO || $userRole === ROLE_SUPERADMIN) {
                // CEO review
                if ($action === 'ceo_review' && in_array($report['status'], ['submitted', 'manager_reviewed'])) {
                    $finalStatus = $_POST['final_status'] ?? 'approved';
                    $stmt = $conn->prepare("
                        UPDATE weekly_reports SET 
                            ceo_id = ?,
                            ceo_recommendations = ?,
                            ceo_rating = ?,
                            ceo_reviewed_at = NOW(),
                            status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $userId,
                        $_POST['ceo_recommendations'] ?? '',
                        $_POST['ceo_rating'] ?? 'satisfactory',
                        $finalStatus,
                        $reportId
                    ]);
                    $success = 'Report ' . ($finalStatus === 'approved' ? 'approved' : 'rejected') . ' successfully.';
                }
            }
        }
    }
}

// Get filter
$statusFilter = $_GET['status'] ?? 'pending';
$weekFilter = $_GET['week'] ?? '';

// Build query based on role and filter
$sql = "SELECT * FROM weekly_reports WHERE 1=1";
$params = [];

if ($statusFilter === 'pending') {
    if ($userRole === ROLE_MANAGER) {
        $sql .= " AND status = 'submitted'";
    } elseif ($userRole === ROLE_CEO || $userRole === ROLE_SUPERADMIN) {
        $sql .= " AND status IN ('submitted', 'manager_reviewed')";
    }
} elseif ($statusFilter === 'reviewed') {
    $sql .= " AND status IN ('manager_reviewed', 'ceo_reviewed', 'approved', 'rejected')";
} elseif ($statusFilter === 'approved') {
    $sql .= " AND status = 'approved'";
}

$sql .= " ORDER BY submitted_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$reports = $stmt->fetchAll();

// Get stats
$stmtPending = $conn->query("SELECT COUNT(*) FROM weekly_reports WHERE status IN ('submitted', 'manager_reviewed')");
$pendingCount = $stmtPending->fetchColumn();

$stmtApproved = $conn->query("SELECT COUNT(*) FROM weekly_reports WHERE status = 'approved'");
$approvedCount = $stmtApproved->fetchColumn();

$stmtThisWeek = $conn->query("SELECT COUNT(*) FROM weekly_reports WHERE week_start = '" . date('Y-m-d', strtotime('monday this week')) . "'");
$thisWeekCount = $stmtThisWeek->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-clipboard-check text-primary mr-2"></i>Review Weekly Reports
            </h1>
            <p class="text-gray-600">Review and approve staff weekly performance reports</p>
        </div>
    </div>
    
    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
        <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6">
        <i class="fas fa-check-circle mr-2"></i><?= $success ?>
    </div>
    <?php endif; ?>
    
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-yellow-100 text-yellow-600 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-clock"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">Pending Review</p>
                    <p class="text-xl font-bold"><?= $pendingCount ?></p>
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
                    <p class="text-xl font-bold"><?= $approvedCount ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow p-4">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-calendar-week"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-xs">This Week</p>
                    <p class="text-xl font-bold"><?= $thisWeekCount ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Tabs -->
    <div class="bg-white rounded-xl shadow mb-6">
        <div class="flex border-b">
            <a href="?status=pending" class="px-6 py-3 font-medium <?= $statusFilter === 'pending' ? 'text-primary border-b-2 border-primary' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-clock mr-2"></i>Pending Review
            </a>
            <a href="?status=reviewed" class="px-6 py-3 font-medium <?= $statusFilter === 'reviewed' ? 'text-primary border-b-2 border-primary' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-check mr-2"></i>Reviewed
            </a>
            <a href="?status=approved" class="px-6 py-3 font-medium <?= $statusFilter === 'approved' ? 'text-primary border-b-2 border-primary' : 'text-gray-500 hover:text-gray-700' ?>">
                <i class="fas fa-check-double mr-2"></i>Approved
            </a>
        </div>
    </div>
    
    <!-- Reports List -->
    <?php if (empty($reports)): ?>
    <div class="bg-white rounded-xl shadow p-8 text-center">
        <i class="fas fa-inbox text-5xl text-gray-300 mb-4"></i>
        <p class="text-gray-500">No reports to review at this time.</p>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($reports as $report): ?>
        <div class="bg-white rounded-xl shadow overflow-hidden">
            <div class="p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800">
                            <?= htmlspecialchars($report['staff_name']) ?>
                        </h3>
                        <p class="text-gray-500 text-sm">
                            <i class="fas fa-map-marker-alt mr-1"></i><?= htmlspecialchars($report['branch']) ?>
                            &nbsp;|&nbsp;
                            <i class="fas fa-calendar mr-1"></i><?= date('M j', strtotime($report['week_start'])) ?> - <?= date('M j, Y', strtotime($report['week_end'])) ?>
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php
                        $statusColors = [
                            'submitted' => 'bg-blue-100 text-blue-700',
                            'manager_reviewed' => 'bg-yellow-100 text-yellow-700',
                            'approved' => 'bg-green-100 text-green-700',
                            'rejected' => 'bg-red-100 text-red-700'
                        ];
                        $statusLabels = [
                            'submitted' => 'Pending Manager',
                            'manager_reviewed' => 'Pending CEO',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected'
                        ];
                        ?>
                        <span class="px-3 py-1 text-xs rounded-full <?= $statusColors[$report['status']] ?? 'bg-gray-100' ?>">
                            <?= $statusLabels[$report['status']] ?? $report['status'] ?>
                        </span>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-4 bg-gray-50 rounded-lg p-3">
                    <div class="text-center">
                        <p class="text-lg font-bold text-blue-600"><?= $report['projects_completed'] ?>/<?= $report['projects_handled'] ?></p>
                        <p class="text-xs text-gray-500">Projects</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-green-600"><?= $report['new_customers'] ?></p>
                        <p class="text-xs text-gray-500">New Customers</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-purple-600"><?= $report['leads_generated'] ?></p>
                        <p class="text-xs text-gray-500">Leads</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-orange-600"><?= $report['conversions'] ?></p>
                        <p class="text-xs text-gray-500">Conversions</p>
                    </div>
                    <div class="text-center">
                        <p class="text-lg font-bold text-teal-600">TZS <?= number_format($report['revenue_generated']) ?></p>
                        <p class="text-xs text-gray-500">Revenue</p>
                    </div>
                </div>
                
                <div class="flex justify-between items-center">
                    <a href="<?= SITE_URL ?>/dashboard/view-report.php?id=<?= $report['id'] ?>" class="text-primary hover:underline">
                        <i class="fas fa-eye mr-1"></i>View Full Report
                    </a>
                    
                    <?php if ($report['status'] === 'submitted' && ($userRole === ROLE_MANAGER || $userRole === ROLE_SUPERADMIN)): ?>
                    <button onclick="openReviewModal(<?= $report['id'] ?>, 'manager')" class="bg-yellow-500 text-white px-4 py-2 rounded-lg hover:bg-yellow-600 transition">
                        <i class="fas fa-check mr-2"></i>Manager Review
                    </button>
                    <?php endif; ?>
                    
                    <?php if (in_array($report['status'], ['submitted', 'manager_reviewed']) && ($userRole === ROLE_CEO || $userRole === ROLE_SUPERADMIN)): ?>
                    <button onclick="openReviewModal(<?= $report['id'] ?>, 'ceo')" class="bg-purple-500 text-white px-4 py-2 rounded-lg hover:bg-purple-600 transition">
                        <i class="fas fa-crown mr-2"></i>CEO Review
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Review Modal -->
<div id="reviewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4">
        <div class="p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-4" id="modalTitle">Review Report</h3>
            <form method="POST" id="reviewForm">
                <input type="hidden" name="report_id" id="reviewReportId">
                <input type="hidden" name="action" id="reviewAction">
                
                <!-- Manager Fields -->
                <div id="managerFields" class="hidden space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                        <select name="manager_rating" class="w-full px-3 py-2 border rounded-lg">
                            <option value="excellent">Excellent</option>
                            <option value="good">Good</option>
                            <option value="satisfactory" selected>Satisfactory</option>
                            <option value="needs_improvement">Needs Improvement</option>
                            <option value="poor">Poor</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Manager Recommendations</label>
                        <textarea name="manager_recommendations" rows="4" class="w-full px-3 py-2 border rounded-lg" placeholder="Your feedback and recommendations..."></textarea>
                    </div>
                </div>
                
                <!-- CEO Fields -->
                <div id="ceoFields" class="hidden space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Rating</label>
                        <select name="ceo_rating" class="w-full px-3 py-2 border rounded-lg">
                            <option value="excellent">Excellent</option>
                            <option value="good">Good</option>
                            <option value="satisfactory" selected>Satisfactory</option>
                            <option value="needs_improvement">Needs Improvement</option>
                            <option value="poor">Poor</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">CEO Recommendations</label>
                        <textarea name="ceo_recommendations" rows="4" class="w-full px-3 py-2 border rounded-lg" placeholder="Your feedback and recommendations..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Final Decision</label>
                        <select name="final_status" class="w-full px-3 py-2 border rounded-lg">
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                        </select>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 mt-6">
                    <button type="button" onclick="closeReviewModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2 bg-primary text-white rounded-lg hover:bg-secondary">
                        Submit Review
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReviewModal(reportId, type) {
    document.getElementById('reviewReportId').value = reportId;
    document.getElementById('reviewAction').value = type + '_review';
    
    if (type === 'manager') {
        document.getElementById('modalTitle').textContent = 'Manager Review';
        document.getElementById('managerFields').classList.remove('hidden');
        document.getElementById('ceoFields').classList.add('hidden');
    } else {
        document.getElementById('modalTitle').textContent = 'CEO Review';
        document.getElementById('managerFields').classList.add('hidden');
        document.getElementById('ceoFields').classList.remove('hidden');
    }
    
    document.getElementById('reviewModal').classList.remove('hidden');
    document.getElementById('reviewModal').classList.add('flex');
}

function closeReviewModal() {
    document.getElementById('reviewModal').classList.add('hidden');
    document.getElementById('reviewModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
/**
 * Weekly Report Submission Form
 * Staff submit weekly performance reports to Manager/CEO
 */
$pageTitle = 'Weekly Report';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Create tables if not exist
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
            
            -- Task Management
            tasks_planned TEXT,
            tasks_completed TEXT,
            tasks_in_progress TEXT,
            tasks_overdue TEXT,
            overdue_reasons TEXT,
            
            -- Performance Metrics
            projects_handled INT DEFAULT 0,
            projects_completed INT DEFAULT 0,
            customer_interactions INT DEFAULT 0,
            new_customers INT DEFAULT 0,
            support_tickets_resolved INT DEFAULT 0,
            
            -- Marketing & Sales
            marketing_activities TEXT,
            marketing_plan_next_week TEXT,
            leads_generated INT DEFAULT 0,
            conversions INT DEFAULT 0,
            revenue_generated DECIMAL(15,2) DEFAULT 0,
            
            -- Quality & Development
            skills_learned TEXT,
            training_attended TEXT,
            challenges_faced TEXT,
            solutions_implemented TEXT,
            
            -- Recommendations
            staff_recommendations TEXT,
            resources_needed TEXT,
            
            -- Manager Review
            manager_id INT,
            manager_recommendations TEXT,
            manager_rating ENUM('excellent','good','satisfactory','needs_improvement','poor'),
            manager_reviewed_at DATETIME,
            
            -- CEO Review
            ceo_id INT,
            ceo_recommendations TEXT,
            ceo_rating ENUM('excellent','good','satisfactory','needs_improvement','poor'),
            ceo_reviewed_at DATETIME,
            
            -- Status
            status ENUM('draft','submitted','manager_reviewed','ceo_reviewed','approved','rejected') DEFAULT 'draft',
            submitted_at DATETIME,
            
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            INDEX idx_staff (staff_id),
            INDEX idx_date (report_date),
            INDEX idx_status (status),
            INDEX idx_week (week_start, week_end)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
} catch (PDOException $e) {
    // Table exists
}

$error = '';
$success = '';
$editReport = null;

// Get staff info
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? '';
$userRole = $_SESSION['user_role'];

// Check if editing existing report
$reportId = $_GET['id'] ?? null;
if ($reportId) {
    $stmt = $conn->prepare("SELECT * FROM weekly_reports WHERE id = ? AND staff_id = ?");
    $stmt->execute([$reportId, $userId]);
    $editReport = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    
    $data = [
        'staff_id' => $userId,
        'staff_name' => $userName,
        'branch' => $_POST['branch'] ?? '',
        'report_date' => $_POST['report_date'] ?? date('Y-m-d'),
        'week_start' => $_POST['week_start'] ?? '',
        'week_end' => $_POST['week_end'] ?? '',
        
        'tasks_planned' => $_POST['tasks_planned'] ?? '',
        'tasks_completed' => $_POST['tasks_completed'] ?? '',
        'tasks_in_progress' => $_POST['tasks_in_progress'] ?? '',
        'tasks_overdue' => $_POST['tasks_overdue'] ?? '',
        'overdue_reasons' => $_POST['overdue_reasons'] ?? '',
        
        'projects_handled' => (int)($_POST['projects_handled'] ?? 0),
        'projects_completed' => (int)($_POST['projects_completed'] ?? 0),
        'customer_interactions' => (int)($_POST['customer_interactions'] ?? 0),
        'new_customers' => (int)($_POST['new_customers'] ?? 0),
        'support_tickets_resolved' => (int)($_POST['support_tickets_resolved'] ?? 0),
        
        'marketing_activities' => $_POST['marketing_activities'] ?? '',
        'marketing_plan_next_week' => $_POST['marketing_plan_next_week'] ?? '',
        'leads_generated' => (int)($_POST['leads_generated'] ?? 0),
        'conversions' => (int)($_POST['conversions'] ?? 0),
        'revenue_generated' => (float)($_POST['revenue_generated'] ?? 0),
        
        'skills_learned' => $_POST['skills_learned'] ?? '',
        'training_attended' => $_POST['training_attended'] ?? '',
        'challenges_faced' => $_POST['challenges_faced'] ?? '',
        'solutions_implemented' => $_POST['solutions_implemented'] ?? '',
        
        'staff_recommendations' => $_POST['staff_recommendations'] ?? '',
        'resources_needed' => $_POST['resources_needed'] ?? '',
        
        'status' => $action === 'submit' ? 'submitted' : 'draft',
        'submitted_at' => $action === 'submit' ? date('Y-m-d H:i:s') : null
    ];
    
    try {
        if ($reportId && $editReport) {
            // Update existing report
            $sql = "UPDATE weekly_reports SET 
                branch = ?, report_date = ?, week_start = ?, week_end = ?,
                tasks_planned = ?, tasks_completed = ?, tasks_in_progress = ?, tasks_overdue = ?, overdue_reasons = ?,
                projects_handled = ?, projects_completed = ?, customer_interactions = ?, new_customers = ?, support_tickets_resolved = ?,
                marketing_activities = ?, marketing_plan_next_week = ?, leads_generated = ?, conversions = ?, revenue_generated = ?,
                skills_learned = ?, training_attended = ?, challenges_faced = ?, solutions_implemented = ?,
                staff_recommendations = ?, resources_needed = ?, status = ?, submitted_at = ?
                WHERE id = ? AND staff_id = ?";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $data['branch'], $data['report_date'], $data['week_start'], $data['week_end'],
                $data['tasks_planned'], $data['tasks_completed'], $data['tasks_in_progress'], $data['tasks_overdue'], $data['overdue_reasons'],
                $data['projects_handled'], $data['projects_completed'], $data['customer_interactions'], $data['new_customers'], $data['support_tickets_resolved'],
                $data['marketing_activities'], $data['marketing_plan_next_week'], $data['leads_generated'], $data['conversions'], $data['revenue_generated'],
                $data['skills_learned'], $data['training_attended'], $data['challenges_faced'], $data['solutions_implemented'],
                $data['staff_recommendations'], $data['resources_needed'], $data['status'], $data['submitted_at'],
                $reportId, $userId
            ]);
            
            $success = $action === 'submit' ? 'Report submitted successfully!' : 'Report saved as draft.';
        } else {
            // Check if report already exists for this week
            $stmt = $conn->prepare("SELECT id FROM weekly_reports WHERE staff_id = ? AND week_start = ? AND week_end = ?");
            $stmt->execute([$userId, $data['week_start'], $data['week_end']]);
            if ($stmt->fetch()) {
                $error = 'A report for this week already exists.';
            } else {
                // Insert new report
                $sql = "INSERT INTO weekly_reports (
                    staff_id, staff_name, branch, report_date, week_start, week_end,
                    tasks_planned, tasks_completed, tasks_in_progress, tasks_overdue, overdue_reasons,
                    projects_handled, projects_completed, customer_interactions, new_customers, support_tickets_resolved,
                    marketing_activities, marketing_plan_next_week, leads_generated, conversions, revenue_generated,
                    skills_learned, training_attended, challenges_faced, solutions_implemented,
                    staff_recommendations, resources_needed, status, submitted_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($sql);
                $stmt->execute([
                    $data['staff_id'], $data['staff_name'], $data['branch'], $data['report_date'], $data['week_start'], $data['week_end'],
                    $data['tasks_planned'], $data['tasks_completed'], $data['tasks_in_progress'], $data['tasks_overdue'], $data['overdue_reasons'],
                    $data['projects_handled'], $data['projects_completed'], $data['customer_interactions'], $data['new_customers'], $data['support_tickets_resolved'],
                    $data['marketing_activities'], $data['marketing_plan_next_week'], $data['leads_generated'], $data['conversions'], $data['revenue_generated'],
                    $data['skills_learned'], $data['training_attended'], $data['challenges_faced'], $data['solutions_implemented'],
                    $data['staff_recommendations'], $data['resources_needed'], $data['status'], $data['submitted_at']
                ]);
                
                $success = $action === 'submit' ? 'Report submitted successfully!' : 'Report saved as draft.';
                
                if ($action === 'submit') {
                    header("Location: " . SITE_URL . "/dashboard/my-reports.php?success=submitted");
                    exit;
                }
            }
        }
    } catch (PDOException $e) {
        $error = 'Error saving report: ' . $e->getMessage();
    }
}

// Get current week dates
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">
                <i class="fas fa-file-alt text-primary mr-2"></i>
                <?= $editReport ? 'Edit Weekly Report' : 'Submit Weekly Report' ?>
            </h1>
            <p class="text-gray-600">Fill in your weekly performance report</p>
        </div>
        <a href="<?= SITE_URL ?>/dashboard/my-reports.php" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition">
            <i class="fas fa-arrow-left mr-2"></i>My Reports
        </a>
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
    
    <form method="POST" class="space-y-6">
        <!-- Basic Info -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-info-circle text-primary mr-2"></i>Basic Information
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff Name</label>
                    <input type="text" value="<?= htmlspecialchars($userName) ?>" class="w-full px-3 py-2 border rounded-lg bg-gray-100" readonly>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Branch *</label>
                    <select name="branch" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                        <option value="">Select Branch</option>
                        <option value="Dar es Salaam" <?= ($editReport['branch'] ?? '') === 'Dar es Salaam' ? 'selected' : '' ?>>Dar es Salaam</option>
                        <option value="Karagwe" <?= ($editReport['branch'] ?? '') === 'Karagwe' ? 'selected' : '' ?>>Karagwe</option>
                        <option value="Remote" <?= ($editReport['branch'] ?? '') === 'Remote' ? 'selected' : '' ?>>Remote</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Week Start *</label>
                    <input type="date" name="week_start" value="<?= $editReport['week_start'] ?? $weekStart ?>" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Week End *</label>
                    <input type="date" name="week_end" value="<?= $editReport['week_end'] ?? $weekEnd ?>" required class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <input type="hidden" name="report_date" value="<?= date('Y-m-d') ?>">
            </div>
        </div>
        
        <!-- Task Management -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-tasks text-primary mr-2"></i>Task Management
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tasks Planned This Week</label>
                    <textarea name="tasks_planned" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="List tasks you planned to accomplish..."><?= htmlspecialchars($editReport['tasks_planned'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tasks Completed ✅</label>
                    <textarea name="tasks_completed" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="List tasks you completed..."><?= htmlspecialchars($editReport['tasks_completed'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tasks In Progress 🔄</label>
                    <textarea name="tasks_in_progress" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="List tasks still in progress..."><?= htmlspecialchars($editReport['tasks_in_progress'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tasks Overdue ⚠️</label>
                    <textarea name="tasks_overdue" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="List overdue tasks..."><?= htmlspecialchars($editReport['tasks_overdue'] ?? '') ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reasons for Overdue Tasks</label>
                    <textarea name="overdue_reasons" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="Explain why tasks are overdue..."><?= htmlspecialchars($editReport['overdue_reasons'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Performance Metrics -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-chart-bar text-primary mr-2"></i>Performance Metrics (KPIs)
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Projects Handled</label>
                    <input type="number" name="projects_handled" value="<?= $editReport['projects_handled'] ?? 0 ?>" min="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Projects Completed</label>
                    <input type="number" name="projects_completed" value="<?= $editReport['projects_completed'] ?? 0 ?>" min="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Customer Interactions</label>
                    <input type="number" name="customer_interactions" value="<?= $editReport['customer_interactions'] ?? 0 ?>" min="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">New Customers</label>
                    <input type="number" name="new_customers" value="<?= $editReport['new_customers'] ?? 0 ?>" min="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Support Tickets Resolved</label>
                    <input type="number" name="support_tickets_resolved" value="<?= $editReport['support_tickets_resolved'] ?? 0 ?>" min="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                </div>
            </div>
        </div>
        
        <!-- Marketing & Sales -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-bullhorn text-primary mr-2"></i>Marketing & Sales
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Marketing Activities This Week</label>
                    <textarea name="marketing_activities" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="Describe marketing activities performed..."><?= htmlspecialchars($editReport['marketing_activities'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Marketing Plan for Next Week</label>
                    <textarea name="marketing_plan_next_week" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="Outline marketing plans for next week..."><?= htmlspecialchars($editReport['marketing_plan_next_week'] ?? '') ?></textarea>
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Leads Generated</label>
                    <input type="number" name="leads_generated" value="<?= $editReport['leads_generated'] ?? 0 ?>" min="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Conversions</label>
                    <input type="number" name="conversions" value="<?= $editReport['conversions'] ?? 0 ?>" min="0" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Revenue Generated (TZS)</label>
                    <input type="number" name="revenue_generated" value="<?= $editReport['revenue_generated'] ?? 0 ?>" min="0" step="0.01" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                </div>
            </div>
        </div>
        
        <!-- Learning & Development -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-graduation-cap text-primary mr-2"></i>Learning & Development
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Skills Learned This Week</label>
                    <textarea name="skills_learned" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="New skills or knowledge acquired..."><?= htmlspecialchars($editReport['skills_learned'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Training/Workshops Attended</label>
                    <textarea name="training_attended" rows="2" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="Any training or workshops attended..."><?= htmlspecialchars($editReport['training_attended'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Challenges & Solutions -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-exclamation-triangle text-primary mr-2"></i>Challenges & Solutions
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Challenges Faced</label>
                    <textarea name="challenges_faced" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="Describe challenges encountered..."><?= htmlspecialchars($editReport['challenges_faced'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Solutions Implemented</label>
                    <textarea name="solutions_implemented" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="How did you solve or address them..."><?= htmlspecialchars($editReport['solutions_implemented'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Recommendations -->
        <div class="bg-white rounded-xl shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4 border-b pb-2">
                <i class="fas fa-lightbulb text-primary mr-2"></i>Staff Recommendations
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Your Recommendations</label>
                    <textarea name="staff_recommendations" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="Your suggestions for improvement..."><?= htmlspecialchars($editReport['staff_recommendations'] ?? '') ?></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Resources Needed</label>
                    <textarea name="resources_needed" rows="3" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="Tools, training, support needed..."><?= htmlspecialchars($editReport['resources_needed'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Submit Buttons -->
        <div class="flex justify-end gap-4">
            <button type="submit" name="action" value="save" class="bg-gray-500 text-white px-6 py-3 rounded-lg hover:bg-gray-600 transition">
                <i class="fas fa-save mr-2"></i>Save as Draft
            </button>
            <button type="submit" name="action" value="submit" class="bg-primary text-white px-8 py-3 rounded-lg hover:bg-secondary transition">
                <i class="fas fa-paper-plane mr-2"></i>Submit Report
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

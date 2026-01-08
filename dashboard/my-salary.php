<?php
$pageTitle = 'My Salary';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

if (!isLoggedIn() || $_SESSION['user_role'] === ROLE_CUSTOMER) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();
$notifier = new NotificationService($conn);

// Create salary_advances table if not exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS salary_advances (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        amount DECIMAL(15,2) NOT NULL,
        reason TEXT,
        status ENUM('pending', 'approved', 'rejected', 'disbursed') DEFAULT 'pending',
        approved_by INT DEFAULT NULL,
        approved_at DATETIME DEFAULT NULL,
        disbursed_by INT DEFAULT NULL,
        disbursed_at DATETIME DEFAULT NULL,
        rejection_reason TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (approved_by) REFERENCES users(id),
        FOREIGN KEY (disbursed_by) REFERENCES users(id)
    )");
} catch (PDOException $e) {
    // Table exists
}

// Get current salary info first (needed for validation)
$stmt = $conn->prepare("SELECT salary FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentSalary = $stmt->fetchColumn() ?: 0;

$success = '';
$error = '';

// Handle salary advance application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_advance'])) {
    $amount = floatval($_POST['amount']);
    $reason = sanitize($_POST['reason']);
    
    if ($amount <= 0) {
        $error = 'Please enter a valid amount';
    } else {
        // Check for pending advances
        $stmt = $conn->prepare("SELECT COUNT(*) FROM salary_advances WHERE user_id = ? AND status = 'pending'");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetchColumn() > 0) {
            $error = 'You already have a pending advance application';
        } else {
            $stmt = $conn->prepare("INSERT INTO salary_advances (user_id, amount, reason) VALUES (?, ?, ?)");
            if ($stmt->execute([$_SESSION['user_id'], $amount, $reason])) {
                $advanceId = $conn->lastInsertId();
                
                // Notify Manager, Accountant, CEO
                $subject = "Salary Advance Application - {$_SESSION['user_name']}";
                $message = "{$_SESSION['user_name']} has applied for a salary advance of TZS " . number_format($amount, 2) . ". Reason: " . ($reason ?: 'Not specified');
                $notifier->sendToRoles([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT], $subject, $message, 'notification', 'salary_advance', $advanceId);
                
                $success = 'Salary advance application submitted successfully!';
            } else {
                $error = 'Failed to submit application';
            }
        }
    }
}

// Get my salary records
$stmt = $conn->prepare("
    SELECT * FROM salary_records 
    WHERE user_id = ? 
    ORDER BY month DESC
");
$stmt->execute([$_SESSION['user_id']]);
$records = $stmt->fetchAll();

// Get my salary advances
$stmt = $conn->prepare("
    SELECT sa.*, ab.name as approved_by_name, db.name as disbursed_by_name
    FROM salary_advances sa
    LEFT JOIN users ab ON sa.approved_by = ab.id
    LEFT JOIN users db ON sa.disbursed_by = db.id
    WHERE sa.user_id = ?
    ORDER BY sa.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$advances = $stmt->fetchAll();

// Get year-to-date
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(net_salary), 0) FROM salary_records 
    WHERE user_id = ? AND YEAR(STR_TO_DATE(CONCAT(month, '-01'), '%Y-%m-%d')) = YEAR(CURDATE()) AND status = 'paid'
");
$stmt->execute([$_SESSION['user_id']]);
$ytdEarnings = $stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Current Monthly Salary</p>
        <p class="text-2xl font-bold text-primary"><?= formatCurrency($currentSalary) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Year-to-Date Earnings</p>
        <p class="text-2xl font-bold text-green-600"><?= formatCurrency($ytdEarnings) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Records</p>
        <p class="text-2xl font-bold text-gray-800"><?= count($records) ?></p>
    </div>
</div>

<!-- Salary Records -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b">
        <h2 class="font-bold text-gray-800">Salary History</h2>
    </div>
    
    <?php if (empty($records)): ?>
    <div class="p-8 text-center text-gray-500">No salary records yet</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Basic Salary</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allowances</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deductions</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net Salary</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($records as $record): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium"><?= date('F Y', strtotime($record['month'] . '-01')) ?></td>
                    <td class="px-6 py-4 text-right"><?= formatCurrency($record['basic_salary']) ?></td>
                    <td class="px-6 py-4 text-right text-green-600">+<?= formatCurrency($record['allowances']) ?></td>
                    <td class="px-6 py-4 text-right text-red-600">-<?= formatCurrency($record['deductions']) ?></td>
                    <td class="px-6 py-4 text-right font-semibold"><?= formatCurrency($record['net_salary']) ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $record['status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                            <?= ucfirst($record['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= $record['payment_date'] ? formatDate($record['payment_date']) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Salary Advances Section -->
<div class="bg-white rounded-xl shadow-md overflow-hidden mt-6">
    <div class="p-4 border-b flex justify-between items-center">
        <h2 class="font-bold text-gray-800"><i class="fas fa-hand-holding-usd text-primary mr-2"></i>Salary Advances</h2>
        <button onclick="openAdvanceModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary text-sm">
            <i class="fas fa-plus mr-1"></i>Apply for Advance
        </button>
    </div>
    
    <?php if (empty($advances)): ?>
    <div class="p-8 text-center text-gray-500">No salary advance applications yet</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Applied</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($advances as $adv): ?>
                <?php
                $statusColors = [
                    'pending' => 'bg-yellow-100 text-yellow-800',
                    'approved' => 'bg-blue-100 text-blue-800',
                    'rejected' => 'bg-red-100 text-red-800',
                    'disbursed' => 'bg-green-100 text-green-800'
                ];
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4"><?= formatDateTime($adv['created_at']) ?></td>
                    <td class="px-6 py-4 text-right font-semibold"><?= formatCurrency($adv['amount']) ?></td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($adv['reason'] ?: '-') ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusColors[$adv['status']] ?>">
                            <?= ucfirst($adv['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php if ($adv['status'] === 'approved'): ?>
                            Approved by <?= htmlspecialchars($adv['approved_by_name']) ?> on <?= formatDate($adv['approved_at']) ?>
                        <?php elseif ($adv['status'] === 'disbursed'): ?>
                            Disbursed by <?= htmlspecialchars($adv['disbursed_by_name']) ?> on <?= formatDate($adv['disbursed_at']) ?>
                        <?php elseif ($adv['status'] === 'rejected'): ?>
                            <?= htmlspecialchars($adv['rejection_reason'] ?: 'Rejected') ?>
                        <?php else: ?>
                            Awaiting review
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Apply for Advance Modal -->
<div id="advanceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold mb-4"><i class="fas fa-hand-holding-usd text-primary mr-2"></i>Apply for Salary Advance</h3>
        <form method="POST">
            <input type="hidden" name="apply_advance" value="1">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Amount (TZS) *</label>
                <input type="number" name="amount" required step="any" 
                       class="w-full px-4 py-2 border rounded-lg" placeholder="Enter amount">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Reason</label>
                <textarea name="reason" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Why do you need this advance?"></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeAdvanceModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-secondary">
                    <i class="fas fa-paper-plane mr-1"></i>Submit Application
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAdvanceModal() {
    document.getElementById('advanceModal').classList.remove('hidden');
    document.getElementById('advanceModal').classList.add('flex');
}

function closeAdvanceModal() {
    document.getElementById('advanceModal').classList.add('hidden');
    document.getElementById('advanceModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

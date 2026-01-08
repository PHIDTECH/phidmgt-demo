<?php
$pageTitle = 'Salary Advances';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT])) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();
$notifier = new NotificationService($conn);

$success = '';
$error = '';

// Handle approve
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve'])) {
    $advanceId = intval($_POST['advance_id']);
    
    $stmt = $conn->prepare("UPDATE salary_advances SET status = 'approved', approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'");
    if ($stmt->execute([$_SESSION['user_id'], $advanceId])) {
        // Get advance details for notification
        $stmt = $conn->prepare("SELECT sa.*, u.name as staff_name FROM salary_advances sa JOIN users u ON sa.user_id = u.id WHERE sa.id = ?");
        $stmt->execute([$advanceId]);
        $advance = $stmt->fetch();
        
        if ($advance) {
            // Notify the staff member
            $subject = "Salary Advance Approved";
            $message = "Your salary advance application of TZS " . number_format($advance['amount'], 2) . " has been APPROVED by {$_SESSION['user_name']}. It will be disbursed soon.";
            $notifier->send($advance['user_id'], $subject, $message, 'notification', 'salary_advance', $advanceId);
            
            // Notify other managers/accountant/CEO
            $notifySubject = "Salary Advance Approved - {$advance['staff_name']}";
            $notifyMessage = "{$_SESSION['user_name']} has approved the salary advance of TZS " . number_format($advance['amount'], 2) . " for {$advance['staff_name']}. Pending disbursement.";
            $notifier->sendToRoles([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT], $notifySubject, $notifyMessage, 'notification', 'salary_advance', $advanceId);
        }
        
        $success = 'Salary advance approved successfully!';
    } else {
        $error = 'Failed to approve advance';
    }
}

// Handle reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject'])) {
    $advanceId = intval($_POST['advance_id']);
    $reason = sanitize($_POST['rejection_reason'] ?? '');
    
    $stmt = $conn->prepare("UPDATE salary_advances SET status = 'rejected', rejection_reason = ?, approved_by = ?, approved_at = NOW() WHERE id = ? AND status = 'pending'");
    if ($stmt->execute([$reason, $_SESSION['user_id'], $advanceId])) {
        // Get advance details for notification
        $stmt = $conn->prepare("SELECT sa.*, u.name as staff_name FROM salary_advances sa JOIN users u ON sa.user_id = u.id WHERE sa.id = ?");
        $stmt->execute([$advanceId]);
        $advance = $stmt->fetch();
        
        if ($advance) {
            // Notify the staff member
            $subject = "Salary Advance Rejected";
            $message = "Your salary advance application of TZS " . number_format($advance['amount'], 2) . " has been REJECTED. Reason: " . ($reason ?: 'Not specified');
            $notifier->send($advance['user_id'], $subject, $message, 'notification', 'salary_advance', $advanceId);
        }
        
        $success = 'Salary advance rejected';
    } else {
        $error = 'Failed to reject advance';
    }
}

// Handle disburse
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['disburse'])) {
    $advanceId = intval($_POST['advance_id']);
    
    $stmt = $conn->prepare("UPDATE salary_advances SET status = 'disbursed', disbursed_by = ?, disbursed_at = NOW() WHERE id = ? AND status = 'approved'");
    if ($stmt->execute([$_SESSION['user_id'], $advanceId])) {
        // Get advance details for notification
        $stmt = $conn->prepare("SELECT sa.*, u.name as staff_name FROM salary_advances sa JOIN users u ON sa.user_id = u.id WHERE sa.id = ?");
        $stmt->execute([$advanceId]);
        $advance = $stmt->fetch();
        
        if ($advance) {
            // Notify the staff member
            $subject = "Salary Advance Disbursed";
            $message = "Your salary advance of TZS " . number_format($advance['amount'], 2) . " has been DISBURSED by {$_SESSION['user_name']}. Please collect or check your account.";
            $notifier->send($advance['user_id'], $subject, $message, 'notification', 'salary_advance', $advanceId);
            
            // Notify other managers/accountant/CEO
            $notifySubject = "Salary Advance Disbursed - {$advance['staff_name']}";
            $notifyMessage = "{$_SESSION['user_name']} has disbursed the salary advance of TZS " . number_format($advance['amount'], 2) . " to {$advance['staff_name']}.";
            $notifier->sendToRoles([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT], $notifySubject, $notifyMessage, 'notification', 'salary_advance', $advanceId);
        }
        
        $success = 'Salary advance disbursed successfully!';
    } else {
        $error = 'Failed to disburse advance';
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'pending';
$where = "1=1";
if ($filter === 'pending') $where = "sa.status = 'pending'";
elseif ($filter === 'approved') $where = "sa.status = 'approved'";
elseif ($filter === 'disbursed') $where = "sa.status = 'disbursed'";
elseif ($filter === 'rejected') $where = "sa.status = 'rejected'";

// Get all advances
$stmt = $conn->query("
    SELECT sa.*, u.name as staff_name, u.role as staff_role, u.salary as staff_salary,
           ab.name as approved_by_name, db.name as disbursed_by_name
    FROM salary_advances sa
    JOIN users u ON sa.user_id = u.id
    LEFT JOIN users ab ON sa.approved_by = ab.id
    LEFT JOIN users db ON sa.disbursed_by = db.id
    WHERE $where
    ORDER BY sa.created_at DESC
");
$advances = $stmt->fetchAll();

// Count by status
$stmt = $conn->query("SELECT status, COUNT(*) as cnt FROM salary_advances GROUP BY status");
$counts = [];
while ($row = $stmt->fetch()) {
    $counts[$row['status']] = $row['cnt'];
}

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
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-yellow-600"><?= $counts['pending'] ?? 0 ?></p>
        <p class="text-sm text-gray-600">Pending</p>
    </div>
    <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-blue-600"><?= $counts['approved'] ?? 0 ?></p>
        <p class="text-sm text-gray-600">Approved</p>
    </div>
    <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-green-600"><?= $counts['disbursed'] ?? 0 ?></p>
        <p class="text-sm text-gray-600">Disbursed</p>
    </div>
    <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-red-600"><?= $counts['rejected'] ?? 0 ?></p>
        <p class="text-sm text-gray-600">Rejected</p>
    </div>
</div>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-md p-4 mb-6">
    <div class="flex flex-wrap gap-2">
        <a href="?filter=pending" class="px-4 py-2 rounded-lg <?= $filter === 'pending' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Pending
        </a>
        <a href="?filter=approved" class="px-4 py-2 rounded-lg <?= $filter === 'approved' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Approved
        </a>
        <a href="?filter=disbursed" class="px-4 py-2 rounded-lg <?= $filter === 'disbursed' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Disbursed
        </a>
        <a href="?filter=rejected" class="px-4 py-2 rounded-lg <?= $filter === 'rejected' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Rejected
        </a>
        <a href="?filter=all" class="px-4 py-2 rounded-lg <?= $filter === 'all' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            All
        </a>
    </div>
</div>

<!-- Advances List -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <?php if (empty($advances)): ?>
    <div class="p-12 text-center">
        <div class="w-16 h-16 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-hand-holding-usd text-2xl"></i>
        </div>
        <p class="text-gray-500">No salary advance applications found</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Staff</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Salary</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Applied</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
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
                    <td class="px-6 py-4">
                        <p class="font-medium"><?= htmlspecialchars($adv['staff_name']) ?></p>
                        <p class="text-xs text-gray-500"><?= ucfirst(str_replace('_', ' ', $adv['staff_role'])) ?></p>
                    </td>
                    <td class="px-6 py-4 text-right font-semibold text-primary"><?= formatCurrency($adv['amount']) ?></td>
                    <td class="px-6 py-4 text-right text-sm text-gray-500"><?= formatCurrency($adv['staff_salary']) ?></td>
                    <td class="px-6 py-4 text-sm max-w-xs truncate"><?= htmlspecialchars($adv['reason'] ?: '-') ?></td>
                    <td class="px-6 py-4 text-sm"><?= formatDateTime($adv['created_at']) ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusColors[$adv['status']] ?>">
                            <?= ucfirst($adv['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($adv['status'] === 'pending'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="advance_id" value="<?= $adv['id'] ?>">
                            <button type="submit" name="approve" class="text-green-600 hover:text-green-800 mr-2" title="Approve">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                        <button onclick="openRejectModal(<?= $adv['id'] ?>)" class="text-red-600 hover:text-red-800" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                        <?php elseif ($adv['status'] === 'approved'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="advance_id" value="<?= $adv['id'] ?>">
                            <button type="submit" name="disburse" class="bg-green-600 text-white px-3 py-1 rounded text-xs hover:bg-green-700">
                                <i class="fas fa-money-bill-wave mr-1"></i>Disburse
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="text-gray-400 text-sm">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold mb-4 text-red-600"><i class="fas fa-times-circle mr-2"></i>Reject Advance</h3>
        <form method="POST">
            <input type="hidden" name="reject" value="1">
            <input type="hidden" name="advance_id" id="rejectAdvanceId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Rejection</label>
                <textarea name="rejection_reason" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Why is this advance being rejected?"></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeRejectModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                    <i class="fas fa-times mr-1"></i>Reject
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(advanceId) {
    document.getElementById('rejectAdvanceId').value = advanceId;
    document.getElementById('rejectModal').classList.remove('hidden');
    document.getElementById('rejectModal').classList.add('flex');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('rejectModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

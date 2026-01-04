<?php
$pageTitle = 'My Leave';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

if (!isLoggedIn() || $_SESSION['user_role'] === ROLE_CUSTOMER) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();
$notifier = new NotificationService($conn);

$success = '';
$error = '';

// Handle new leave application
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_leave'])) {
    $leaveType = sanitize($_POST['leave_type']);
    $startDate = $_POST['start_date'];
    $endDate = $_POST['end_date'];
    $reason = sanitize($_POST['reason']);
    
    // Calculate days
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $days = $start->diff($end)->days + 1;
    
    if ($start > $end) {
        $error = 'End date must be after start date';
    } else {
        $stmt = $conn->prepare("INSERT INTO leave_applications (user_id, leave_type, start_date, end_date, days, reason) VALUES (?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$_SESSION['user_id'], $leaveType, $startDate, $endDate, $days, $reason])) {
            $leaveId = $conn->lastInsertId();
            
            // Get leave data for notification
            $stmt = $conn->prepare("SELECT * FROM leave_applications WHERE id = ?");
            $stmt->execute([$leaveId]);
            $leave = $stmt->fetch();
            
            $applicant = ['id' => $_SESSION['user_id'], 'name' => $_SESSION['user_name']];
            $notifier->notifyLeaveApplied($leave, $applicant);
            
            $success = 'Leave application submitted successfully';
        }
    }
}

// Get my leave applications
$stmt = $conn->prepare("
    SELECT l.*, a.name as approved_by_name
    FROM leave_applications l
    LEFT JOIN users a ON l.approved_by = a.id
    WHERE l.user_id = ?
    ORDER BY l.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$myLeave = $stmt->fetchAll();

// Get leave balance (simplified - just count used days this year)
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(days), 0) FROM leave_applications 
    WHERE user_id = ? AND status = 'approved' AND YEAR(start_date) = YEAR(CURDATE())
");
$stmt->execute([$_SESSION['user_id']]);
$usedDays = $stmt->fetchColumn();
$totalAllowance = 21; // Standard annual leave
$remainingDays = $totalAllowance - $usedDays;

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

<!-- Leave Balance -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Annual Allowance</p>
        <p class="text-3xl font-bold text-gray-800"><?= $totalAllowance ?> days</p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Used This Year</p>
        <p class="text-3xl font-bold text-orange-600"><?= $usedDays ?> days</p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Remaining</p>
        <p class="text-3xl font-bold text-green-600"><?= $remainingDays ?> days</p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <button onclick="document.getElementById('leaveModal').classList.remove('hidden'); document.getElementById('leaveModal').classList.add('flex');" 
                class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
            <i class="fas fa-plus mr-2"></i>Apply for Leave
        </button>
    </div>
</div>

<!-- My Applications -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b">
        <h2 class="font-bold text-gray-800">My Leave Applications</h2>
    </div>
    
    <?php if (empty($myLeave)): ?>
    <div class="p-8 text-center text-gray-500">No leave applications yet</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Days</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Processed By</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($myLeave as $leave): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                            <?= ucfirst($leave['leave_type']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <?= formatDate($leave['start_date']) ?> - <?= formatDate($leave['end_date']) ?>
                    </td>
                    <td class="px-6 py-4 text-center font-medium"><?= $leave['days'] ?></td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars(substr($leave['reason'] ?? '', 0, 50)) ?></td>
                    <td class="px-6 py-4">
                        <?php
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800'
                        ];
                        $color = $statusColors[$leave['status']] ?? 'bg-gray-100';
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $color ?>">
                            <?= ucfirst($leave['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <?= $leave['approved_by_name'] ? htmlspecialchars($leave['approved_by_name']) : '-' ?>
                        <?php if ($leave['comments']): ?>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($leave['comments']) ?></p>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Apply Leave Modal -->
<div id="leaveModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4">Apply for Leave</h3>
        <form method="POST">
            <input type="hidden" name="apply_leave" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Leave Type *</label>
                    <select name="leave_type" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="annual">Annual Leave</option>
                        <option value="sick">Sick Leave</option>
                        <option value="maternity">Maternity Leave</option>
                        <option value="paternity">Paternity Leave</option>
                        <option value="emergency">Emergency Leave</option>
                        <option value="unpaid">Unpaid Leave</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date *</label>
                        <input type="date" name="start_date" required min="<?= date('Y-m-d') ?>" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date *</label>
                        <input type="date" name="end_date" required min="<?= date('Y-m-d') ?>" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason</label>
                    <textarea name="reason" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Reason for leave..."></textarea>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="document.getElementById('leaveModal').classList.add('hidden'); document.getElementById('leaveModal').classList.remove('flex');" 
                        class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Submit</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
$pageTitle = 'Leave Management';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/notifications.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_HR])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();
$notifier = new NotificationService($conn);

$success = '';

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_leave'])) {
    $leaveId = intval($_POST['leave_id']);
    $action = sanitize($_POST['action']);
    $comments = sanitize($_POST['comments'] ?? '');
    
    $stmt = $conn->prepare("UPDATE leave_applications SET status = ?, approved_by = ?, approval_date = NOW(), comments = ? WHERE id = ?");
    if ($stmt->execute([$action, $_SESSION['user_id'], $comments, $leaveId])) {
        // Get leave and user for notification
        $stmt = $conn->prepare("SELECT l.*, u.id as user_id, u.name as user_name FROM leave_applications l JOIN users u ON l.user_id = u.id WHERE l.id = ?");
        $stmt->execute([$leaveId]);
        $leave = $stmt->fetch();
        
        $approver = ['id' => $_SESSION['user_id'], 'name' => $_SESSION['user_name']];
        $applicant = ['id' => $leave['user_id'], 'name' => $leave['user_name']];
        
        $notifier->notifyLeaveDecision($leave, $applicant, $action, $approver);
        
        $success = 'Leave request ' . $action;
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'pending';
$where = "1=1";
if ($filter === 'pending') $where = "l.status = 'pending'";
elseif ($filter === 'approved') $where = "l.status = 'approved'";
elseif ($filter === 'rejected') $where = "l.status = 'rejected'";

// Get leave applications
$stmt = $conn->query("
    SELECT l.*, u.name as user_name, u.department, a.name as approved_by_name
    FROM leave_applications l
    JOIN users u ON l.user_id = u.id
    LEFT JOIN users a ON l.approved_by = a.id
    WHERE $where
    ORDER BY l.created_at DESC
");
$applications = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-md p-4 mb-6">
    <div class="flex flex-wrap gap-2">
        <a href="?filter=pending" class="px-4 py-2 rounded-lg <?= $filter === 'pending' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Pending
        </a>
        <a href="?filter=approved" class="px-4 py-2 rounded-lg <?= $filter === 'approved' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Approved
        </a>
        <a href="?filter=rejected" class="px-4 py-2 rounded-lg <?= $filter === 'rejected' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Rejected
        </a>
        <a href="?filter=all" class="px-4 py-2 rounded-lg <?= $filter === 'all' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            All
        </a>
    </div>
</div>

<!-- Applications Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Period</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Days</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reason</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($applications as $app): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($app['user_name']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($app['department'] ?: '-') ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded-full text-xs font-medium">
                            <?= ucfirst($app['leave_type']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <?= formatDate($app['start_date']) ?> - <?= formatDate($app['end_date']) ?>
                    </td>
                    <td class="px-6 py-4 text-center font-medium"><?= $app['days'] ?></td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars(substr($app['reason'] ?? '', 0, 50)) ?>...</td>
                    <td class="px-6 py-4">
                        <?php
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800'
                        ];
                        $color = $statusColors[$app['status']] ?? 'bg-gray-100';
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $color ?>">
                            <?= ucfirst($app['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($app['status'] === 'pending'): ?>
                        <button onclick="openLeaveModal(<?= htmlspecialchars(json_encode($app)) ?>)" 
                                class="text-primary hover:underline text-sm">Review</button>
                        <?php else: ?>
                        <span class="text-sm text-gray-500">
                            <?= $app['approved_by_name'] ? 'By: ' . htmlspecialchars($app['approved_by_name']) : '-' ?>
                        </span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Review Modal -->
<div id="leaveModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-2">Review Leave Request</h3>
        <div id="leaveDetails" class="text-sm text-gray-600 mb-4"></div>
        <form method="POST">
            <input type="hidden" name="process_leave" value="1">
            <input type="hidden" name="leave_id" id="leaveId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Comments</label>
                <textarea name="comments" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Optional comments..."></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeLeaveModal()" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" name="action" value="rejected" class="px-4 py-2 bg-red-500 text-white rounded-lg">Reject</button>
                <button type="submit" name="action" value="approved" class="px-4 py-2 bg-green-500 text-white rounded-lg">Approve</button>
            </div>
        </form>
    </div>
</div>

<script>
function openLeaveModal(app) {
    document.getElementById('leaveId').value = app.id;
    document.getElementById('leaveDetails').innerHTML = `
        <strong>${app.user_name}</strong> is requesting <strong>${app.days}</strong> day(s) of <strong>${app.leave_type}</strong> leave<br>
        From: ${app.start_date} To: ${app.end_date}<br>
        <em>Reason: ${app.reason || 'Not specified'}</em>
    `;
    document.getElementById('leaveModal').classList.remove('hidden');
    document.getElementById('leaveModal').classList.add('flex');
}

function closeLeaveModal() {
    document.getElementById('leaveModal').classList.add('hidden');
    document.getElementById('leaveModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

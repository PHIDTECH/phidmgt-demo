<?php
$pageTitle = 'My Tasks';
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

// Handle task status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task'])) {
    $taskId = intval($_POST['task_id']);
    $newStatus = sanitize($_POST['status']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    // Get task
    $stmt = $conn->prepare("SELECT t.*, p.title as project_title FROM tasks t JOIN projects p ON t.project_id = p.id WHERE t.id = ?");
    $stmt->execute([$taskId]);
    $task = $stmt->fetch();
    
    if ($task) {
        // Junior staff can only submit, supervisors+ can approve/reject
        $canUpdate = false;
        
        if ($task['assigned_to'] == $_SESSION['user_id']) {
            // Assigned user can update to in_progress or submitted
            if (in_array($newStatus, ['in_progress', 'submitted'])) {
                $canUpdate = true;
            }
        }
        
        if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])) {
            // Supervisors+ can approve or reject
            $canUpdate = true;
        }
        
        if ($canUpdate) {
            $completedDate = $newStatus === 'approved' ? date('Y-m-d H:i:s') : null;
            
            $stmt = $conn->prepare("UPDATE tasks SET status = ?, notes = CONCAT(IFNULL(notes, ''), '\n', ?), completed_date = ? WHERE id = ?");
            
            if ($stmt->execute([$newStatus, $notes, $completedDate, $taskId])) {
                // Notify on submission
                if ($newStatus === 'submitted') {
                    $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
                    $stmt->execute([$task['project_id']]);
                    $project = $stmt->fetch();
                    
                    $completedBy = ['id' => $_SESSION['user_id'], 'name' => $_SESSION['user_name']];
                    $notifier->notifyTaskCompleted($task, $project, $completedBy);
                }
                
                $success = 'Task updated successfully';
            }
        } else {
            $error = 'You do not have permission to perform this action';
        }
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'my';
$where = "t.assigned_to = " . $_SESSION['user_id'];

if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])) {
    if ($filter === 'all') $where = "1=1";
    elseif ($filter === 'pending_review') $where = "t.status = 'submitted'";
    elseif ($filter === 'my') $where = "t.assigned_to = " . $_SESSION['user_id'];
}

// Get tasks
$stmt = $conn->query("
    SELECT t.*, p.title as project_title, p.reference as project_ref,
           u.name as assigned_name, ab.name as assigned_by_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN users ab ON t.assigned_by = ab.id
    WHERE $where
    ORDER BY 
        CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END,
        t.due_date ASC
");
$tasks = $stmt->fetchAll();

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

<!-- Filters -->
<div class="bg-white rounded-xl shadow-md p-4 mb-6">
    <div class="flex flex-wrap gap-2">
        <a href="?filter=my" class="px-4 py-2 rounded-lg <?= $filter === 'my' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            My Tasks
        </a>
        <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])): ?>
        <a href="?filter=pending_review" class="px-4 py-2 rounded-lg <?= $filter === 'pending_review' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Pending Review
        </a>
        <a href="?filter=all" class="px-4 py-2 rounded-lg <?= $filter === 'all' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            All Tasks
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Tasks Grid -->
<?php if (empty($tasks)): ?>
<div class="bg-white rounded-xl shadow-md p-12 text-center">
    <div class="w-16 h-16 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-tasks text-2xl"></i>
    </div>
    <p class="text-gray-500">No tasks found</p>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($tasks as $task): ?>
    <?php
    $taskColors = [
        'pending' => 'border-yellow-400',
        'in_progress' => 'border-blue-400',
        'submitted' => 'border-purple-400',
        'approved' => 'border-green-400',
        'rejected' => 'border-red-400'
    ];
    $borderColor = $taskColors[$task['status']] ?? 'border-gray-400';
    
    $priorityColors = [
        'low' => 'bg-gray-100 text-gray-600',
        'medium' => 'bg-blue-100 text-blue-600',
        'high' => 'bg-orange-100 text-orange-600',
        'urgent' => 'bg-red-100 text-red-600'
    ];
    ?>
    <div class="bg-white rounded-xl shadow-md overflow-hidden border-l-4 <?= $borderColor ?>">
        <div class="p-4">
            <div class="flex justify-between items-start mb-2">
                <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($task['title']) ?></h3>
                <span class="px-2 py-1 rounded text-xs font-medium <?= $priorityColors[$task['priority']] ?>">
                    <?= ucfirst($task['priority']) ?>
                </span>
            </div>
            
            <p class="text-sm text-gray-500 mb-3">
                <a href="<?= SITE_URL ?>/dashboard/project-details.php?id=<?= $task['project_id'] ?>" class="hover:text-primary">
                    <?= htmlspecialchars($task['project_title']) ?>
                </a>
            </p>
            
            <?php if ($task['description']): ?>
            <p class="text-sm text-gray-600 mb-3"><?= htmlspecialchars(substr($task['description'], 0, 100)) ?>...</p>
            <?php endif; ?>
            
            <div class="flex items-center justify-between text-sm text-gray-500 mb-3">
                <span><i class="fas fa-user mr-1"></i><?= htmlspecialchars($task['assigned_name'] ?? 'Unassigned') ?></span>
                <?php if ($task['due_date']): ?>
                <span class="<?= strtotime($task['due_date']) < time() && !in_array($task['status'], ['approved']) ? 'text-red-500' : '' ?>">
                    <i class="fas fa-calendar mr-1"></i><?= formatDate($task['due_date']) ?>
                </span>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center justify-between pt-3 border-t">
                <?php
                $statusLabels = [
                    'pending' => 'Pending',
                    'in_progress' => 'In Progress',
                    'submitted' => 'Submitted',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected'
                ];
                $statusBadgeColors = [
                    'pending' => 'bg-yellow-100 text-yellow-800',
                    'in_progress' => 'bg-blue-100 text-blue-800',
                    'submitted' => 'bg-purple-100 text-purple-800',
                    'approved' => 'bg-green-100 text-green-800',
                    'rejected' => 'bg-red-100 text-red-800'
                ];
                ?>
                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $statusBadgeColors[$task['status']] ?>">
                    <?= $statusLabels[$task['status']] ?>
                </span>
                
                <?php if ($task['status'] !== 'approved'): ?>
                <button onclick="openTaskModal(<?= htmlspecialchars(json_encode($task)) ?>)" 
                        class="text-primary hover:underline text-sm">
                    Update
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Update Task Modal -->
<div id="taskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-2">Update Task</h3>
        <p id="taskTitle" class="text-gray-600 mb-4"></p>
        <form method="POST">
            <input type="hidden" name="update_task" value="1">
            <input type="hidden" name="task_id" id="taskId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" id="taskStatus" required class="w-full px-4 py-2 border rounded-lg">
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="submitted">Submit for Review</option>
                    <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])): ?>
                    <option value="approved">Approve</option>
                    <option value="rejected">Reject</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Notes</label>
                <textarea name="notes" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Add any notes..."></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeTaskModal()" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openTaskModal(task) {
    document.getElementById('taskId').value = task.id;
    document.getElementById('taskTitle').textContent = task.title;
    document.getElementById('taskStatus').value = task.status;
    document.getElementById('taskModal').classList.remove('hidden');
    document.getElementById('taskModal').classList.add('flex');
}

function closeTaskModal() {
    document.getElementById('taskModal').classList.add('hidden');
    document.getElementById('taskModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

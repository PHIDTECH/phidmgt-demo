<?php
$pageTitle = 'Project Details';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

if (!isLoggedIn() || $_SESSION['user_role'] === ROLE_CUSTOMER) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();
$notifier = new NotificationService($conn);

$projectId = intval($_GET['id'] ?? 0);
$success = '';
$error = '';

// Get project
$stmt = $conn->prepare("
    SELECT p.*, COALESCE(p.service_name, s.name) as service_display, s.features,
           c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
           a.name as assigned_name
    FROM projects p 
    LEFT JOIN services s ON p.service_id = s.id 
    JOIN users c ON p.customer_id = c.id 
    LEFT JOIN users a ON p.assigned_to = a.id
    WHERE p.id = ?
");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    redirect('/dashboard/projects.php');
}

// Handle project update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project'])) {
    if (hasRole([ROLE_CEO, ROLE_MANAGER])) {
        $title = sanitize($_POST['title']);
        $serviceName = sanitize($_POST['service_name']);
        $description = sanitize($_POST['description']);
        $requirements = sanitize($_POST['requirements']);
        $dueDate = sanitize($_POST['due_date']);
        $status = sanitize($_POST['status']);
        $assignedTo = !empty($_POST['assigned_to']) ? intval($_POST['assigned_to']) : null;
        
        $stmt = $conn->prepare("
            UPDATE projects SET 
                title = ?, 
                service_name = ?, 
                description = ?, 
                requirements = ?, 
                due_date = ?, 
                status = ?,
                assigned_to = ?,
                start_date = CASE WHEN start_date IS NULL AND ? = 'in_progress' THEN CURDATE() ELSE start_date END,
                completed_date = CASE WHEN ? = 'completed' THEN CURDATE() ELSE NULL END
            WHERE id = ?
        ");
        
        if ($stmt->execute([$title, $serviceName, $description, $requirements, $dueDate, $status, $assignedTo, $status, $status, $projectId])) {
            $success = 'Project updated successfully';
            // Refresh project data
            $stmt = $conn->prepare("
                SELECT p.*, COALESCE(p.service_name, s.name) as service_display, s.features,
                       c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
                       a.name as assigned_name
                FROM projects p 
                LEFT JOIN services s ON p.service_id = s.id 
                JOIN users c ON p.customer_id = c.id 
                LEFT JOIN users a ON p.assigned_to = a.id
                WHERE p.id = ?
            ");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch();
        } else {
            $error = 'Failed to update project';
        }
    }
}

// Handle task creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])) {
        $title = sanitize($_POST['task_title']);
        $description = sanitize($_POST['task_description']);
        $assignTo = intval($_POST['assign_to']);
        $dueDate = $_POST['due_date'];
        $priority = sanitize($_POST['priority']);
        
        $stmt = $conn->prepare("
            INSERT INTO tasks (project_id, title, description, assigned_to, assigned_by, due_date, priority)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$projectId, $title, $description, $assignTo, $_SESSION['user_id'], $dueDate, $priority])) {
            $taskId = $conn->lastInsertId();
            
            // Get task for notification
            $stmt = $conn->prepare("SELECT * FROM tasks WHERE id = ?");
            $stmt->execute([$taskId]);
            $task = $stmt->fetch();
            
            $assigner = ['id' => $_SESSION['user_id'], 'name' => $_SESSION['user_name']];
            $notifier->notifyTaskAssigned($task, $project, $assigner);
            
            $success = 'Task created and assigned successfully';
        }
    }
}

// Get tasks
$stmt = $conn->prepare("
    SELECT t.*, u.name as assigned_name, ab.name as assigned_by_name
    FROM tasks t
    LEFT JOIN users u ON t.assigned_to = u.id
    LEFT JOIN users ab ON t.assigned_by = ab.id
    WHERE t.project_id = ?
    ORDER BY t.created_at DESC
");
$stmt->execute([$projectId]);
$tasks = $stmt->fetchAll();

// Get payments (kept for legacy but not displayed)
$stmt = $conn->prepare("SELECT * FROM payments WHERE project_id = ? ORDER BY created_at DESC");
$stmt->execute([$projectId]);
$payments = $stmt->fetchAll();

// Get staff
$stmt = $conn->query("SELECT id, name, role FROM users WHERE role NOT IN ('customer') AND status = 'active' ORDER BY name");
$staff = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <a href="<?= SITE_URL ?>/dashboard/projects.php" class="text-primary hover:underline">
        <i class="fas fa-arrow-left mr-2"></i>Back to Projects
    </a>
</div>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Main Content -->
    <div class="lg:col-span-2 space-y-6">
        <!-- Project Info -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($project['title']) ?></h1>
                    <p class="text-gray-500 font-mono"><?= $project['reference'] ?></p>
                </div>
                <div class="flex items-center space-x-2">
                    <?php if (hasRole([ROLE_CEO, ROLE_MANAGER])): ?>
                    <button onclick="openEditModal()" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">
                        <i class="fas fa-edit mr-1"></i>Edit Project
                    </button>
                    <?php endif; ?>
                <?php
                $statusColors = [
                    'pending' => 'bg-yellow-100 text-yellow-800',
                    'payment_received' => 'bg-blue-100 text-blue-800',
                    'in_progress' => 'bg-purple-100 text-purple-800',
                    'review' => 'bg-orange-100 text-orange-800',
                    'completed' => 'bg-green-100 text-green-800',
                    'cancelled' => 'bg-red-100 text-red-800'
                ];
                $color = $statusColors[$project['status']] ?? 'bg-gray-100 text-gray-800';
                ?>
                <span class="px-4 py-2 rounded-full text-sm font-medium <?= $color ?>">
                        <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                    </span>
                </div>
            </div>
            
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-500">Service</p>
                    <p class="font-medium"><?= htmlspecialchars($project['service_display'] ?? '-') ?></p>
                </div>
                <div>
                    <p class="text-gray-500">Assigned To</p>
                    <p class="font-medium"><?= $project['assigned_name'] ?: 'Unassigned' ?></p>
                </div>
                <div>
                    <p class="text-gray-500">Created</p>
                    <p class="font-medium"><?= formatDate($project['created_at']) ?></p>
                </div>
                <div>
                    <p class="text-gray-500">Due Date</p>
                    <p class="font-medium"><?= formatDate($project['due_date']) ?></p>
                </div>
            </div>
            
            <?php if ($project['description']): ?>
            <div class="mt-4 pt-4 border-t">
                <h3 class="font-semibold text-gray-800 mb-2">Description</h3>
                <p class="text-gray-600"><?= nl2br(htmlspecialchars($project['description'])) ?></p>
            </div>
            <?php endif; ?>
            
            <?php if ($project['requirements']): ?>
            <div class="mt-4 pt-4 border-t">
                <h3 class="font-semibold text-gray-800 mb-2">Requirements</h3>
                <p class="text-gray-600"><?= nl2br(htmlspecialchars($project['requirements'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Tasks -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 border-b flex justify-between items-center">
                <h2 class="font-bold text-gray-800">Tasks</h2>
                <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])): ?>
                <button onclick="document.getElementById('taskModal').classList.remove('hidden'); document.getElementById('taskModal').classList.add('flex');" 
                        class="bg-primary text-white px-4 py-2 rounded-lg text-sm">
                    <i class="fas fa-plus mr-1"></i>Add Task
                </button>
                <?php endif; ?>
            </div>
            
            <?php if (empty($tasks)): ?>
            <div class="p-8 text-center text-gray-500">No tasks created yet</div>
            <?php else: ?>
            <div class="divide-y">
                <?php foreach ($tasks as $task): ?>
                <div class="p-4 hover:bg-gray-50">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($task['title']) ?></p>
                            <p class="text-sm text-gray-500">
                                Assigned to: <?= htmlspecialchars($task['assigned_name'] ?? 'Unassigned') ?>
                                <?php if ($task['due_date']): ?> • Due: <?= formatDate($task['due_date']) ?><?php endif; ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <?php
                            $taskColors = [
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'in_progress' => 'bg-blue-100 text-blue-800',
                                'submitted' => 'bg-purple-100 text-purple-800',
                                'approved' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-red-100 text-red-800'
                            ];
                            $taskColor = $taskColors[$task['status']] ?? 'bg-gray-100 text-gray-800';
                            
                            $priorityColors = [
                                'low' => 'text-gray-500',
                                'medium' => 'text-blue-500',
                                'high' => 'text-orange-500',
                                'urgent' => 'text-red-500'
                            ];
                            ?>
                            <span class="px-2 py-1 rounded-full text-xs font-medium <?= $taskColor ?>">
                                <?= ucfirst($task['status']) ?>
                            </span>
                            <p class="text-xs <?= $priorityColors[$task['priority']] ?? '' ?> mt-1">
                                <i class="fas fa-flag mr-1"></i><?= ucfirst($task['priority']) ?>
                            </p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Sidebar -->
    <div class="space-y-6">
        <!-- Customer Info -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h2 class="font-bold text-gray-800 mb-4">Customer</h2>
            <div class="space-y-2">
                <p class="font-medium text-gray-800"><?= htmlspecialchars($project['customer_name']) ?></p>
                <p class="text-sm text-gray-600"><i class="fas fa-envelope mr-2"></i><?= htmlspecialchars($project['customer_email']) ?></p>
                <?php if ($project['customer_phone']): ?>
                <p class="text-sm text-gray-600"><i class="fas fa-phone mr-2"></i><?= htmlspecialchars($project['customer_phone']) ?></p>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
</div>

<!-- Add Task Modal -->
<div id="taskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg">
        <h3 class="text-lg font-bold mb-4">Create Task</h3>
        <form method="POST">
            <input type="hidden" name="create_task" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Task Title *</label>
                    <input type="text" name="task_title" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="task_description" rows="3" class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Assign To *</label>
                        <select name="assign_to" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="">Select...</option>
                            <?php foreach ($staff as $member): ?>
                            <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Priority</label>
                        <select name="priority" class="w-full px-4 py-2 border rounded-lg">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                    <input type="date" name="due_date" class="w-full px-4 py-2 border rounded-lg">
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="document.getElementById('taskModal').classList.add('hidden'); document.getElementById('taskModal').classList.remove('flex');" 
                        class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Create Task</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Project Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl p-6 w-full max-w-2xl my-8 mx-4">
        <h3 class="text-lg font-bold mb-4"><i class="fas fa-edit text-blue-600 mr-2"></i>Edit Project</h3>
        <form method="POST">
            <input type="hidden" name="update_project" value="1">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Project Title *</label>
                    <input type="text" name="title" required class="w-full px-4 py-2 border rounded-lg" value="<?= htmlspecialchars($project['title']) ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Service/Product</label>
                    <input type="text" name="service_name" class="w-full px-4 py-2 border rounded-lg" value="<?= htmlspecialchars($project['service_name'] ?? $project['service_display'] ?? '') ?>">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                    <input type="date" name="due_date" required class="w-full px-4 py-2 border rounded-lg" value="<?= $project['due_date'] ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border rounded-lg">
                        <option value="pending" <?= $project['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="payment_received" <?= $project['status'] === 'payment_received' ? 'selected' : '' ?>>Payment Received</option>
                        <option value="in_progress" <?= $project['status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="review" <?= $project['status'] === 'review' ? 'selected' : '' ?>>Under Review</option>
                        <option value="completed" <?= $project['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $project['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Assigned To</label>
                <select name="assigned_to" class="w-full px-4 py-2 border rounded-lg">
                    <option value="">Unassigned</option>
                    <?php foreach ($staff as $member): ?>
                    <option value="<?= $member['id'] ?>" <?= $project['assigned_to'] == $member['id'] ? 'selected' : '' ?>><?= htmlspecialchars($member['name']) ?> (<?= ucfirst(str_replace('_', ' ', $member['role'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Requirements</label>
                <textarea name="requirements" rows="3" class="w-full px-4 py-2 border rounded-lg"><?= htmlspecialchars($project['requirements'] ?? '') ?></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal() {
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

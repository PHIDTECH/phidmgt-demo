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

// Ensure required columns exist
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query("SELECT attachment FROM tasks LIMIT 1");
} catch (PDOException $e) {
    $conn->query("ALTER TABLE tasks ADD COLUMN attachment VARCHAR(255) DEFAULT NULL");
}

// Add recurring task columns if not exist
try {
    $conn->query("SELECT is_recurring FROM tasks LIMIT 1");
} catch (PDOException $e) {
    $conn->exec("ALTER TABLE tasks 
        ADD COLUMN is_recurring TINYINT(1) DEFAULT 0,
        ADD COLUMN recurrence_type ENUM('none', 'daily', 'weekly', 'monthly', 'yearly') DEFAULT 'none',
        ADD COLUMN recurrence_day INT DEFAULT NULL,
        ADD COLUMN recurrence_month INT DEFAULT NULL,
        ADD COLUMN recurrence_start_date DATE DEFAULT NULL,
        ADD COLUMN recurrence_end_date DATE DEFAULT NULL,
        ADD COLUMN last_recurrence_date DATE DEFAULT NULL,
        ADD COLUMN next_recurrence_date DATE DEFAULT NULL,
        ADD COLUMN notify_customer TINYINT(1) DEFAULT 0,
        ADD COLUMN notify_days_before INT DEFAULT 1");
}

$success = '';
$error = '';

// Handle create task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])) {
        $projectId = intval($_POST['project_id']);
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $assignedTo = intval($_POST['assigned_to']);
        $priority = sanitize($_POST['priority']);
        $dueDate = sanitize($_POST['due_date']);
        
        // Handle file attachment
        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/tasks/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['attachment']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                $attachment = 'uploads/tasks/' . $fileName;
            }
        }
        
        // Handle recurring task settings
        $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;
        $recurrenceType = sanitize($_POST['recurrence_type'] ?? 'none');
        $recurrenceDay = !empty($_POST['recurrence_day']) ? intval($_POST['recurrence_day']) : null;
        $recurrenceMonth = !empty($_POST['recurrence_month']) ? intval($_POST['recurrence_month']) : null;
        $recurrenceStartDate = !empty($_POST['recurrence_start_date']) ? sanitize($_POST['recurrence_start_date']) : null;
        $recurrenceEndDate = !empty($_POST['recurrence_end_date']) ? sanitize($_POST['recurrence_end_date']) : null;
        $notifyCustomer = isset($_POST['notify_customer']) ? 1 : 0;
        $notifyDaysBefore = intval($_POST['notify_days_before'] ?? 1);
        
        // Calculate next recurrence date
        $nextRecurrenceDate = null;
        if ($isRecurring && $recurrenceType !== 'none') {
            $nextRecurrenceDate = $dueDate; // First occurrence is the due date
        }
        
        $stmt = $conn->prepare("INSERT INTO tasks (project_id, title, description, assigned_to, assigned_by, priority, due_date, status, attachment, is_recurring, recurrence_type, recurrence_day, recurrence_month, recurrence_start_date, recurrence_end_date, next_recurrence_date, notify_customer, notify_days_before) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$projectId, $title, $description, $assignedTo, $_SESSION['user_id'], $priority, $dueDate, $attachment, $isRecurring, $recurrenceType, $recurrenceDay, $recurrenceMonth, $recurrenceStartDate, $recurrenceEndDate, $nextRecurrenceDate, $notifyCustomer, $notifyDaysBefore])) {
            // Notify assigned user
            $stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
            $stmt->execute([$assignedTo]);
            $assignedUser = $stmt->fetch();
            
            $stmt = $conn->prepare("SELECT title FROM projects WHERE id = ?");
            $stmt->execute([$projectId]);
            $project = $stmt->fetch();
            
            // Send notification
            $stmt = $conn->prepare("INSERT INTO messages (sender_id, recipient_id, subject, message, type) VALUES (?, ?, ?, ?, 'task')");
            $stmt->execute([
                $_SESSION['user_id'],
                $assignedTo,
                'New Task Assigned: ' . $title,
                'You have been assigned a new task "' . $title . '" for project "' . $project['title'] . '". Priority: ' . ucfirst($priority) . '. Due: ' . $dueDate
            ]);
            
            $success = 'Task created and assigned successfully!';
        } else {
            $error = 'Failed to create task';
        }
    }
}

// Handle edit task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_task'])) {
    if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])) {
        $taskId = intval($_POST['task_id']);
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $assignedTo = intval($_POST['assigned_to']);
        $priority = sanitize($_POST['priority']);
        $dueDate = sanitize($_POST['due_date']);
        
        // Handle file attachment
        $newAttachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/tasks/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = time() . '_' . basename($_FILES['attachment']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                $newAttachment = 'uploads/tasks/' . $fileName;
            }
        }
        
        if ($newAttachment) {
            $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, due_date = ?, attachment = ? WHERE id = ?");
            $stmt->execute([$title, $description, $assignedTo, $priority, $dueDate, $newAttachment, $taskId]);
        } else {
            $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, due_date = ? WHERE id = ?");
            $stmt->execute([$title, $description, $assignedTo, $priority, $dueDate, $taskId]);
        }
        
        $success = 'Task updated successfully!';
    }
}

// Handle add note only
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_note'])) {
    $taskId = intval($_POST['task_id']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (!empty($notes)) {
        $noteEntry = '[' . date('Y-m-d H:i') . ' - ' . $_SESSION['user_name'] . '] ' . $notes;
        $stmt = $conn->prepare("UPDATE tasks SET notes = CONCAT(IFNULL(notes, ''), '\n', ?) WHERE id = ?");
        if ($stmt->execute([$noteEntry, $taskId])) {
            $success = 'Note added successfully';
        }
    }
}

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

// Get filter - All staff can view all tasks
$filter = $_GET['filter'] ?? 'my';
$where = "t.assigned_to = " . $_SESSION['user_id'];

if ($filter === 'all') $where = "1=1";
elseif ($filter === 'my') $where = "t.assigned_to = " . $_SESSION['user_id'];

if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])) {
    if ($filter === 'pending_review') $where = "t.status = 'submitted'";
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

// Get projects for create task (only active projects)
$stmt = $conn->query("SELECT id, title, reference FROM projects WHERE status NOT IN ('completed', 'cancelled') ORDER BY created_at DESC");
$projects = $stmt->fetchAll();

// Get staff for assignment
$stmt = $conn->query("SELECT id, name, role FROM users WHERE role NOT IN ('customer') AND status = 'active' ORDER BY name");
$staff = $stmt->fetchAll();

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
    <div class="flex flex-col lg:flex-row gap-4 items-center justify-between">
    <div class="flex flex-wrap gap-2">
        <a href="?filter=my" class="px-4 py-2 rounded-lg <?= $filter === 'my' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            My Tasks
        </a>
        <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])): ?>
        <a href="?filter=pending_review" class="px-4 py-2 rounded-lg <?= $filter === 'pending_review' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Pending Review
        </a>
        <?php endif; ?>
        <a href="?filter=all" class="px-4 py-2 rounded-lg <?= $filter === 'all' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            All Tasks
        </a>
    </div>
    <div class="flex items-center gap-3">
        <div class="relative">
            <input type="text" id="taskSearch" placeholder="Search tasks..." 
                   class="w-64 pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                   onkeyup="searchTasks()">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
        </div>
    <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])): ?>
    <button onclick="openCreateTaskModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center">
        <i class="fas fa-plus mr-2"></i>Create Task
    </button>
    <?php endif; ?>
    </div>
    </div>
</div>

<!-- Tasks Grid -->
<div id="tasksGrid">
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
            
            <?php if (!empty($task['attachment'])): ?>
            <div class="mb-3">
                <a href="<?= SITE_URL ?>/<?= $task['attachment'] ?>" target="_blank" class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                    <i class="fas fa-paperclip mr-1"></i>Attachment
                </a>
            </div>
            <?php endif; ?>
            
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
                
                <div class="flex space-x-2">
                    <button onclick='openViewTaskModal(<?= json_encode($task) ?>)' class="text-gray-600 hover:text-gray-800 text-sm" title="View"><i class="fas fa-eye"></i></button>
                    <button onclick='openAddNoteModal(<?= json_encode($task) ?>)' class="text-green-600 hover:text-green-800 text-sm" title="Add Note"><i class="fas fa-comment"></i></button>
                    <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])): ?>
                    <button onclick='openEditTaskModal(<?= json_encode($task) ?>)' class="text-blue-600 hover:text-blue-800 text-sm" title="Edit"><i class="fas fa-edit"></i></button>
                    <?php endif; ?>
                    <?php if ($task['status'] !== 'approved' && ($task['assigned_to'] == $_SESSION['user_id'] || hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR]))): ?>
                    <button onclick="openTaskModal(<?= htmlspecialchars(json_encode($task)) ?>)" class="text-primary hover:underline text-sm">Update</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
</div>

<!-- Create Task Modal -->
<div id="createTaskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg my-8 mx-4">
        <h3 class="text-lg font-bold mb-4"><i class="fas fa-plus-circle text-green-600 mr-2"></i>Create New Task</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="create_task" value="1">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Project *</label>
                <select name="project_id" required class="w-full px-4 py-2 border rounded-lg">
                    <option value="">Select Project...</option>
                    <?php foreach ($projects as $project): ?>
                    <option value="<?= $project['id'] ?>"><?= htmlspecialchars($project['reference'] . ' - ' . $project['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Task Title *</label>
                <input type="text" name="title" required class="w-full px-4 py-2 border rounded-lg" placeholder="Enter task title">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Task description"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assign To *</label>
                    <select name="assigned_to" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Select Staff...</option>
                        <?php foreach ($staff as $member): ?>
                        <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?> (<?= ucfirst(str_replace('_', ' ', $member['role'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Priority *</label>
                    <select name="priority" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                <input type="date" name="due_date" required class="w-full px-4 py-2 border rounded-lg">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Attachment</label>
                <input type="file" name="attachment" class="w-full px-4 py-2 border rounded-lg bg-white" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip,.rar">
                <p class="text-xs text-gray-500 mt-1">Allowed: PDF, DOC, XLS, Images, ZIP (Max 10MB)</p>
            </div>
            
            <!-- Recurring Task Settings -->
            <div class="border-t pt-4 mt-4">
                <div class="flex items-center mb-4">
                    <input type="checkbox" name="is_recurring" id="is_recurring" class="w-4 h-4 mr-2" onchange="toggleRecurringOptions()">
                    <label for="is_recurring" class="text-sm font-medium text-gray-700"><i class="fas fa-redo text-primary mr-1"></i>Recurring Task (Monthly/Yearly)</label>
                </div>
                
                <div id="recurringOptions" class="hidden bg-blue-50 border border-blue-200 rounded-lg p-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-clock mr-1"></i>Repeat Frequency *</label>
                        <select name="recurrence_type" id="recurrence_type" class="w-full px-4 py-2 border rounded-lg bg-white" onchange="toggleRecurrenceFields()">
                            <option value="monthly">Every Month</option>
                            <option value="yearly">Every Year</option>
                        </select>
                    </div>
                    
                    <div id="monthlyOptions">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-calendar-day mr-1"></i>Day of Month *</label>
                        <select name="recurrence_day" id="recurrence_day_monthly" class="w-full px-4 py-2 border rounded-lg bg-white">
                            <?php for ($i = 1; $i <= 31; $i++): ?>
                            <option value="<?= $i ?>"><?= $i ?><?= $i == 1 ? 'st' : ($i == 2 ? 'nd' : ($i == 3 ? 'rd' : 'th')) ?> of every month</option>
                            <?php endfor; ?>
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Task will be due on this day every month</p>
                    </div>
                    
                    <div id="yearlyOptions" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-calendar mr-1"></i>Date Every Year *</label>
                        <div class="grid grid-cols-2 gap-3">
                            <select name="recurrence_month" id="recurrence_month" class="px-4 py-2 border rounded-lg bg-white">
                                <option value="1">January</option>
                                <option value="2">February</option>
                                <option value="3">March</option>
                                <option value="4">April</option>
                                <option value="5">May</option>
                                <option value="6">June</option>
                                <option value="7">July</option>
                                <option value="8">August</option>
                                <option value="9">September</option>
                                <option value="10">October</option>
                                <option value="11">November</option>
                                <option value="12">December</option>
                            </select>
                            <select name="recurrence_day" id="recurrence_day_yearly" class="px-4 py-2 border rounded-lg bg-white">
                                <?php for ($i = 1; $i <= 31; $i++): ?>
                                <option value="<?= $i ?>"><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Task will be due on this date every year</p>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Remind Staff</label>
                            <select name="notify_days_before" class="w-full px-4 py-2 border rounded-lg bg-white">
                                <option value="1">1 day before</option>
                                <option value="2">2 days before</option>
                                <option value="3">3 days before</option>
                                <option value="7">1 week before</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">End Recurrence</label>
                            <input type="date" name="recurrence_end_date" class="w-full px-4 py-2 border rounded-lg bg-white" placeholder="Optional">
                        </div>
                    </div>
                    
                    <div class="flex items-center pt-2">
                        <input type="checkbox" name="notify_customer" id="notify_customer" class="w-4 h-4 mr-2">
                        <label for="notify_customer" class="text-sm text-gray-700">Also notify customer when task is due</label>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-2 mt-4">
                <button type="button" onclick="closeCreateTaskModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"><i class="fas fa-save mr-2"></i>Create Task</button>
            </div>
        </form>
    </div>
</div>

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

<!-- Add Note Modal -->
<div id="addNoteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold mb-2"><i class="fas fa-comment text-green-600 mr-2"></i>Add Note</h3>
        <p id="noteTaskTitle" class="text-gray-600 mb-4"></p>
        <form method="POST">
            <input type="hidden" name="add_note" value="1">
            <input type="hidden" name="task_id" id="noteTaskId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Your Note</label>
                <textarea name="notes" rows="4" required class="w-full px-4 py-2 border rounded-lg" placeholder="Add your note or update about this task..."></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeAddNoteModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"><i class="fas fa-plus mr-2"></i>Add Note</button>
            </div>
        </form>
    </div>
</div>

<!-- View Task Modal -->
<div id="viewTaskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg my-8 mx-4">
        <div class="flex justify-between items-start mb-4">
            <h3 class="text-lg font-bold"><i class="fas fa-eye text-gray-600 mr-2"></i>Task Details</h3>
            <button onclick="closeViewTaskModal()" class="text-gray-500 hover:text-gray-700"><i class="fas fa-times"></i></button>
        </div>
        <div class="space-y-4">
            <div>
                <label class="text-sm text-gray-500">Title</label>
                <p id="viewTitle" class="font-semibold text-gray-800"></p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Project</label>
                <p id="viewProject" class="text-gray-700"></p>
            </div>
            <div>
                <label class="text-sm text-gray-500">Description</label>
                <p id="viewDescription" class="text-gray-700"></p>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-500">Assigned To</label>
                    <p id="viewAssigned" class="text-gray-700"></p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Priority</label>
                    <p id="viewPriority" class="font-medium"></p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="text-sm text-gray-500">Due Date</label>
                    <p id="viewDueDate" class="text-gray-700"></p>
                </div>
                <div>
                    <label class="text-sm text-gray-500">Status</label>
                    <p id="viewStatus" class="font-medium"></p>
                </div>
            </div>
            <div>
                <label class="text-sm text-gray-500">Attachment</label>
                <p id="viewAttachmentText" class="text-gray-500">No attachment</p>
                <p id="viewAttachmentLink" class="hidden"><a id="viewAttachment" href="#" target="_blank" class="inline-flex items-center text-blue-600 hover:text-blue-800 bg-blue-50 px-3 py-2 rounded-lg"><i class="fas fa-paperclip mr-2"></i><span id="viewAttachmentName">Download File</span></a></p>
            </div>
            <div id="viewNotesDiv" class="hidden">
                <label class="text-sm text-gray-500">Notes</label>
                <p id="viewNotes" class="text-gray-700 whitespace-pre-line"></p>
            </div>
        </div>
        <div class="flex justify-end mt-6">
            <button onclick="closeViewTaskModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Close</button>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div id="editTaskModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg my-8 mx-4">
        <h3 class="text-lg font-bold mb-4"><i class="fas fa-edit text-blue-600 mr-2"></i>Edit Task</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="edit_task" value="1">
            <input type="hidden" name="task_id" id="editTaskId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Task Title *</label>
                <input type="text" name="title" id="editTaskTitle" required class="w-full px-4 py-2 border rounded-lg">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" id="editTaskDescription" rows="3" class="w-full px-4 py-2 border rounded-lg"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Assign To *</label>
                    <select name="assigned_to" id="editTaskAssigned" required class="w-full px-4 py-2 border rounded-lg">
                        <?php foreach ($staff as $member): ?>
                        <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Priority *</label>
                    <select name="priority" id="editTaskPriority" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                <input type="date" name="due_date" id="editTaskDueDate" required class="w-full px-4 py-2 border rounded-lg">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Attachment</label>
                <input type="file" name="attachment" class="w-full px-4 py-2 border rounded-lg bg-white" accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.zip,.rar">
                <p id="editCurrentAttachment" class="text-xs text-gray-500 mt-1"></p>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeEditTaskModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Save Changes</button>
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

function openCreateTaskModal() {
    document.getElementById('createTaskModal').classList.remove('hidden');
    document.getElementById('createTaskModal').classList.add('flex');
}

function closeCreateTaskModal() {
    document.getElementById('createTaskModal').classList.add('hidden');
    document.getElementById('createTaskModal').classList.remove('flex');
}

function openViewTaskModal(task) {
    document.getElementById('viewTitle').textContent = task.title;
    document.getElementById('viewProject').textContent = task.project_title || '-';
    document.getElementById('viewDescription').textContent = task.description || 'No description';
    document.getElementById('viewAssigned').textContent = task.assigned_name || 'Unassigned';
    document.getElementById('viewPriority').textContent = task.priority ? task.priority.charAt(0).toUpperCase() + task.priority.slice(1) : '-';
    document.getElementById('viewDueDate').textContent = task.due_date || '-';
    document.getElementById('viewStatus').textContent = task.status ? task.status.replace('_', ' ').charAt(0).toUpperCase() + task.status.replace('_', ' ').slice(1) : '-';
    
    if (task.attachment) {
        document.getElementById('viewAttachmentText').classList.add('hidden');
        document.getElementById('viewAttachmentLink').classList.remove('hidden');
        document.getElementById('viewAttachment').href = '<?= SITE_URL ?>/' + task.attachment;
        document.getElementById('viewAttachmentName').textContent = task.attachment.split('/').pop();
    } else {
        document.getElementById('viewAttachmentText').classList.remove('hidden');
        document.getElementById('viewAttachmentLink').classList.add('hidden');
    }
    
    if (task.notes) {
        document.getElementById('viewNotesDiv').classList.remove('hidden');
        document.getElementById('viewNotes').textContent = task.notes;
    } else {
        document.getElementById('viewNotesDiv').classList.add('hidden');
    }
    
    document.getElementById('viewTaskModal').classList.remove('hidden');
    document.getElementById('viewTaskModal').classList.add('flex');
}

function closeViewTaskModal() {
    document.getElementById('viewTaskModal').classList.add('hidden');
    document.getElementById('viewTaskModal').classList.remove('flex');
}

function openEditTaskModal(task) {
    document.getElementById('editTaskId').value = task.id;
    document.getElementById('editTaskTitle').value = task.title;
    document.getElementById('editTaskDescription').value = task.description || '';
    document.getElementById('editTaskAssigned').value = task.assigned_to;
    document.getElementById('editTaskPriority').value = task.priority;
    document.getElementById('editTaskDueDate').value = task.due_date;
    
    if (task.attachment) {
        document.getElementById('editCurrentAttachment').textContent = 'Current: ' + task.attachment.split('/').pop();
    } else {
        document.getElementById('editCurrentAttachment').textContent = 'No current attachment';
    }
    
    document.getElementById('editTaskModal').classList.remove('hidden');
    document.getElementById('editTaskModal').classList.add('flex');
}

function closeEditTaskModal() {
    document.getElementById('editTaskModal').classList.add('hidden');
    document.getElementById('editTaskModal').classList.remove('flex');
}

function openAddNoteModal(task) {
    document.getElementById('noteTaskId').value = task.id;
    document.getElementById('noteTaskTitle').textContent = task.title;
    document.getElementById('addNoteModal').classList.remove('hidden');
    document.getElementById('addNoteModal').classList.add('flex');
}

function closeAddNoteModal() {
    document.getElementById('addNoteModal').classList.add('hidden');
    document.getElementById('addNoteModal').classList.remove('flex');
}

// Recurring task functions
function toggleRecurringOptions() {
    const checkbox = document.getElementById('is_recurring');
    const options = document.getElementById('recurringOptions');
    if (checkbox.checked) {
        options.classList.remove('hidden');
    } else {
        options.classList.add('hidden');
    }
}

function toggleRecurrenceFields() {
    const type = document.getElementById('recurrence_type').value;
    const monthlyOptions = document.getElementById('monthlyOptions');
    const yearlyOptions = document.getElementById('yearlyOptions');
    
    if (type === 'monthly') {
        monthlyOptions.classList.remove('hidden');
        yearlyOptions.classList.add('hidden');
    } else if (type === 'yearly') {
        monthlyOptions.classList.add('hidden');
        yearlyOptions.classList.remove('hidden');
    }
}

function searchTasks() {
    const searchTerm = document.getElementById('taskSearch').value.toLowerCase();
    const tasksGrid = document.getElementById('tasksGrid');
    const taskCards = tasksGrid.querySelectorAll('.bg-white.rounded-xl.shadow-md');
    
    taskCards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

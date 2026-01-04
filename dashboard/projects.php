<?php
$pageTitle = 'Projects';
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

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])) {
        $projectId = intval($_POST['project_id']);
        $newStatus = sanitize($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE projects SET status = ?, start_date = CASE WHEN start_date IS NULL AND ? = 'in_progress' THEN CURDATE() ELSE start_date END, completed_date = CASE WHEN ? = 'completed' THEN CURDATE() ELSE NULL END WHERE id = ?");
        
        if ($stmt->execute([$newStatus, $newStatus, $newStatus, $projectId])) {
            // If completed, notify customer
            if ($newStatus === 'completed') {
                $stmt = $conn->prepare("SELECT p.*, u.id as customer_id, u.name as customer_name FROM projects p JOIN users u ON p.customer_id = u.id WHERE p.id = ?");
                $stmt->execute([$projectId]);
                $project = $stmt->fetch();
                $customer = ['id' => $project['customer_id'], 'name' => $project['customer_name']];
                $notifier->notifyProjectCompleted($project, $customer);
            }
            $success = 'Project status updated successfully';
        }
    }
}

// Handle assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_project'])) {
    if (hasRole([ROLE_CEO, ROLE_MANAGER])) {
        $projectId = intval($_POST['project_id']);
        $assignTo = intval($_POST['assign_to']);
        
        $stmt = $conn->prepare("UPDATE projects SET assigned_to = ? WHERE id = ?");
        if ($stmt->execute([$assignTo, $projectId])) {
            $success = 'Project assigned successfully';
        }
    }
}

// Get filter
$filter = $_GET['filter'] ?? 'all';
$where = "1=1";
if ($filter === 'pending') $where = "p.status = 'pending'";
elseif ($filter === 'in_progress') $where = "p.status IN ('payment_received', 'in_progress')";
elseif ($filter === 'completed') $where = "p.status = 'completed'";
elseif ($filter === 'my') $where = "p.assigned_to = " . $_SESSION['user_id'];

// Get projects
$stmt = $conn->query("
    SELECT p.*, s.name as service_name, 
           c.name as customer_name, c.email as customer_email,
           a.name as assigned_name
    FROM projects p 
    JOIN services s ON p.service_id = s.id 
    JOIN users c ON p.customer_id = c.id 
    LEFT JOIN users a ON p.assigned_to = a.id
    WHERE $where
    ORDER BY p.created_at DESC
");
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

<!-- Filters -->
<div class="bg-white rounded-xl shadow-md p-4 mb-6">
    <div class="flex flex-wrap gap-2">
        <a href="?filter=all" class="px-4 py-2 rounded-lg <?= $filter === 'all' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            All Projects
        </a>
        <a href="?filter=pending" class="px-4 py-2 rounded-lg <?= $filter === 'pending' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Pending
        </a>
        <a href="?filter=in_progress" class="px-4 py-2 rounded-lg <?= $filter === 'in_progress' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            In Progress
        </a>
        <a href="?filter=completed" class="px-4 py-2 rounded-lg <?= $filter === 'completed' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Completed
        </a>
        <a href="?filter=my" class="px-4 py-2 rounded-lg <?= $filter === 'my' ? 'bg-primary text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
            Assigned to Me
        </a>
    </div>
</div>

<!-- Projects Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Assigned</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($projects as $project): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <span class="font-mono text-sm"><?= $project['reference'] ?></span>
                    </td>
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($project['title']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($project['service_name']) ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-gray-800"><?= htmlspecialchars($project['customer_name']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($project['customer_email']) ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <p class="font-medium"><?= formatCurrency($project['total_amount']) ?></p>
                        <p class="text-sm <?= $project['paid_amount'] >= $project['total_amount'] ? 'text-green-600' : 'text-red-500' ?>">
                            Paid: <?= formatCurrency($project['paid_amount']) ?>
                        </p>
                    </td>
                    <td class="px-6 py-4">
                        <?php if ($project['assigned_name']): ?>
                        <span class="text-gray-800"><?= htmlspecialchars($project['assigned_name']) ?></span>
                        <?php else: ?>
                        <span class="text-gray-400">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4">
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
                        <span class="px-3 py-1 rounded-full text-xs font-medium <?= $color ?>">
                            <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center space-x-2">
                            <a href="<?= SITE_URL ?>/dashboard/project-details.php?id=<?= $project['id'] ?>" 
                               class="text-primary hover:underline text-sm">View</a>
                            
                            <?php if (hasRole([ROLE_CEO, ROLE_MANAGER])): ?>
                            <button onclick="openAssignModal(<?= $project['id'] ?>, '<?= htmlspecialchars($project['title']) ?>')" 
                                    class="text-blue-600 hover:underline text-sm">Assign</button>
                            <?php endif; ?>
                            
                            <?php if (hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERVISOR])): ?>
                            <button onclick="openStatusModal(<?= $project['id'] ?>, '<?= $project['status'] ?>')" 
                                    class="text-green-600 hover:underline text-sm">Status</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assign Modal -->
<div id="assignModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4">Assign Project</h3>
        <p id="assignProjectTitle" class="text-gray-600 mb-4"></p>
        <form method="POST">
            <input type="hidden" name="assign_project" value="1">
            <input type="hidden" name="project_id" id="assignProjectId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Assign To</label>
                <select name="assign_to" required class="w-full px-4 py-2 border rounded-lg">
                    <option value="">Select Staff...</option>
                    <?php foreach ($staff as $member): ?>
                    <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?> (<?= ucfirst(str_replace('_', ' ', $member['role'])) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeAssignModal()" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Assign</button>
            </div>
        </form>
    </div>
</div>

<!-- Status Modal -->
<div id="statusModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4">Update Status</h3>
        <form method="POST">
            <input type="hidden" name="update_status" value="1">
            <input type="hidden" name="project_id" id="statusProjectId">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select name="status" id="statusSelect" required class="w-full px-4 py-2 border rounded-lg">
                    <option value="pending">Pending</option>
                    <option value="payment_received">Payment Received</option>
                    <option value="in_progress">In Progress</option>
                    <option value="review">Under Review</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeStatusModal()" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAssignModal(id, title) {
    document.getElementById('assignProjectId').value = id;
    document.getElementById('assignProjectTitle').textContent = title;
    document.getElementById('assignModal').classList.remove('hidden');
    document.getElementById('assignModal').classList.add('flex');
}

function closeAssignModal() {
    document.getElementById('assignModal').classList.add('hidden');
    document.getElementById('assignModal').classList.remove('flex');
}

function openStatusModal(id, currentStatus) {
    document.getElementById('statusProjectId').value = id;
    document.getElementById('statusSelect').value = currentStatus;
    document.getElementById('statusModal').classList.remove('hidden');
    document.getElementById('statusModal').classList.add('flex');
}

function closeStatusModal() {
    document.getElementById('statusModal').classList.add('hidden');
    document.getElementById('statusModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

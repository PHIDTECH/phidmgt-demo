<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

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

// Ensure service_name column exists
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query("SELECT service_name FROM projects LIMIT 1");
} catch (PDOException $e) {
    $conn->query("ALTER TABLE projects ADD COLUMN service_name VARCHAR(255) DEFAULT NULL");
}

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

// Handle create project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_project'])) {
    if (hasRole([ROLE_CEO, ROLE_MANAGER])) {
        $customerId = intval($_POST['customer_id']);
        $serviceName = sanitize($_POST['service_name']);
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $requirements = sanitize($_POST['requirements']);
        $dueDate = sanitize($_POST['due_date']);
        
        // Generate reference
        $reference = 'PRJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        
        $stmt = $conn->prepare("INSERT INTO projects (reference, customer_id, service_name, title, description, requirements, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        if ($stmt->execute([$reference, $customerId, $serviceName, $title, $description, $requirements, $dueDate])) {
            $success = 'Project created successfully! Reference: ' . $reference;
        } else {
            $error = 'Failed to create project';
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
$projects = [];
try {
    $stmt = $conn->query("
        SELECT p.*, 
               COALESCE(p.service_name, s.name) as service_display,
               c.name as customer_name, c.email as customer_email,
               a.name as assigned_name
        FROM projects p 
        LEFT JOIN services s ON p.service_id = s.id 
        JOIN users c ON p.customer_id = c.id 
        LEFT JOIN users a ON p.assigned_to = a.id
        WHERE $where
        ORDER BY p.created_at DESC
    ");
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    // Fallback query without services table
    $stmt = $conn->query("
        SELECT p.*, 
               p.service_name as service_display,
               c.name as customer_name, c.email as customer_email,
               a.name as assigned_name
        FROM projects p 
        JOIN users c ON p.customer_id = c.id 
        LEFT JOIN users a ON p.assigned_to = a.id
        WHERE $where
        ORDER BY p.created_at DESC
    ");
    $projects = $stmt->fetchAll();
}

// Get staff for assignment
$stmt = $conn->query("SELECT id, name, role FROM users WHERE role NOT IN ('customer') AND status = 'active' ORDER BY name");
$staff = $stmt->fetchAll();

// Get customers for create project
$stmt = $conn->query("SELECT id, name, email FROM users WHERE role = 'customer' AND status = 'active' ORDER BY name");
$customers = $stmt->fetchAll();

// Get services for create project (if table exists)
$services = [];
try {
    $stmt = $conn->query("SELECT id, name, base_price, duration_days FROM services WHERE status = 'active' ORDER BY name");
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    // Services table might not exist
}

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="bg-white rounded-xl shadow-md p-4 mb-6">
    <div class="flex flex-col lg:flex-row gap-4 items-center justify-between">
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
    <div class="flex items-center gap-3">
        <div class="relative">
            <input type="text" id="projectSearch" placeholder="Search projects..." 
                   class="w-64 pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                   onkeyup="searchProjects()">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
        </div>
    <?php if (hasRole([ROLE_CEO, ROLE_MANAGER])): ?>
    <button onclick="openCreateModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center">
        <i class="fas fa-plus mr-2"></i>Create Project
    </button>
    <?php endif; ?>
    </div>
    </div>
</div>

<!-- Projects Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full" id="projectsTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
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
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($project['service_display'] ?? '-') ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-gray-800"><?= htmlspecialchars($project['customer_name']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($project['customer_email']) ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <p class="font-medium"><?= $project['due_date'] ? formatDate($project['due_date']) : '-' ?></p>
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

<!-- Create Project Modal -->
<div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl p-6 w-full max-w-2xl my-8 mx-4">
        <h3 class="text-lg font-bold mb-4"><i class="fas fa-plus-circle text-green-600 mr-2"></i>Create New Project</h3>
        <form method="POST">
            <input type="hidden" name="create_project" value="1">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Customer *</label>
                    <select name="customer_id" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Select Customer...</option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?> (<?= $customer['email'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Service/Product *</label>
                    <input type="text" name="service_name" required class="w-full px-4 py-2 border rounded-lg" placeholder="Enter service or product name">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Project Title *</label>
                    <input type="text" name="title" required class="w-full px-4 py-2 border rounded-lg" placeholder="Enter project title">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Due Date *</label>
                    <input type="date" name="due_date" required class="w-full px-4 py-2 border rounded-lg">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Description</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Project description"></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Requirements</label>
                <textarea name="requirements" rows="3" class="w-full px-4 py-2 border rounded-lg" placeholder="Project requirements"></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"><i class="fas fa-save mr-2"></i>Create Project</button>
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

function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
    document.getElementById('createModal').classList.add('flex');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
    document.getElementById('createModal').classList.remove('flex');
}

function searchProjects() {
    const searchTerm = document.getElementById('projectSearch').value.toLowerCase();
    const table = document.getElementById('projectsTable');
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

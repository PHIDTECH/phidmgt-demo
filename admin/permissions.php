<?php
$pageTitle = 'Permission Management';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== ROLE_SUPERADMIN) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle permission creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_permission'])) {
    $name = strtolower(str_replace(' ', '_', sanitize($_POST['name'])));
    $displayName = sanitize($_POST['display_name']);
    $description = sanitize($_POST['description']);
    $module = sanitize($_POST['module']);
    
    $stmt = $conn->prepare("SELECT id FROM permissions WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        $error = 'Permission already exists';
    } else {
        $stmt = $conn->prepare("INSERT INTO permissions (name, display_name, description, module) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $displayName, $description, $module])) {
            $success = 'Permission created successfully';
        }
    }
}

// Handle permission assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_permissions'])) {
    $roleId = intval($_POST['role_id']);
    $permissions = $_POST['permissions'] ?? [];
    
    // Clear existing permissions
    $stmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt->execute([$roleId]);
    
    // Add new permissions
    if (!empty($permissions)) {
        $stmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($permissions as $permId) {
            $stmt->execute([$roleId, intval($permId)]);
        }
    }
    
    $success = 'Permissions assigned successfully';
}

// Get permissions grouped by module
$stmt = $conn->query("SELECT * FROM permissions ORDER BY module, name");
$permissions = $stmt->fetchAll();
$permissionsByModule = [];
foreach ($permissions as $p) {
    $permissionsByModule[$p['module'] ?? 'general'][] = $p;
}

// Get roles
$stmt = $conn->query("SELECT * FROM roles ORDER BY level DESC");
$roles = $stmt->fetchAll();

// Get role permissions
$stmt = $conn->query("SELECT role_id, permission_id FROM role_permissions");
$rolePermissions = [];
while ($row = $stmt->fetch()) {
    $rolePermissions[$row['role_id']][] = $row['permission_id'];
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

<!-- Tabs -->
<div class="flex border-b mb-6">
    <button onclick="showTab('permissions')" id="tab-permissions" class="px-6 py-3 border-b-2 border-primary text-primary font-medium">
        All Permissions
    </button>
    <button onclick="showTab('assign')" id="tab-assign" class="px-6 py-3 border-b-2 border-transparent text-gray-500 hover:text-gray-700">
        Assign to Roles
    </button>
</div>

<!-- Permissions Tab -->
<div id="content-permissions">
    <div class="flex justify-between items-center mb-6">
        <p class="text-gray-600"><?= count($permissions) ?> permissions</p>
        <button onclick="openAddModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition">
            <i class="fas fa-plus mr-2"></i>Add Permission
        </button>
    </div>
    
    <div class="space-y-6">
        <?php foreach ($permissionsByModule as $module => $perms): ?>
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="p-4 bg-gray-50 border-b">
                <h3 class="font-bold text-gray-800 capitalize"><?= htmlspecialchars($module) ?> Module</h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($perms as $perm): ?>
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($perm['display_name']) ?></span>
                            <span class="text-xs bg-gray-100 px-2 py-1 rounded"><?= htmlspecialchars($perm['name']) ?></span>
                        </div>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($perm['description'] ?: 'No description') ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Assign Tab -->
<div id="content-assign" class="hidden">
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <div class="p-4 border-b">
            <h3 class="font-bold text-gray-800">Assign Permissions to Roles</h3>
        </div>
        <div class="p-6">
            <form method="POST">
                <input type="hidden" name="assign_permissions" value="1">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Select Role</label>
                    <select name="role_id" id="roleSelect" onchange="loadRolePermissions()" class="w-full md:w-1/3 px-4 py-2 border rounded-lg">
                        <option value="">Select a role...</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>" data-permissions='<?= json_encode($rolePermissions[$role['id']] ?? []) ?>'>
                            <?= htmlspecialchars($role['display_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="space-y-6">
                    <?php foreach ($permissionsByModule as $module => $perms): ?>
                    <div>
                        <h4 class="font-medium text-gray-800 mb-3 capitalize"><?= htmlspecialchars($module) ?></h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <?php foreach ($perms as $perm): ?>
                            <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="checkbox" name="permissions[]" value="<?= $perm['id'] ?>" class="perm-checkbox mr-3 w-4 h-4 text-primary">
                                <div>
                                    <span class="text-sm font-medium"><?= htmlspecialchars($perm['display_name']) ?></span>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="mt-6 pt-6 border-t">
                    <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg hover:bg-secondary transition">
                        Save Permissions
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Permission Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4">Add New Permission</h3>
        <form method="POST">
            <input type="hidden" name="add_permission" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Permission Name *</label>
                    <input type="text" name="name" required pattern="[a-zA-Z_]+" class="w-full px-4 py-2 border rounded-lg" placeholder="e.g., view_reports">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name *</label>
                    <input type="text" name="display_name" required class="w-full px-4 py-2 border rounded-lg" placeholder="e.g., View Reports">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Module *</label>
                    <select name="module" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="general">General</option>
                        <option value="users">Users</option>
                        <option value="projects">Projects</option>
                        <option value="tasks">Tasks</option>
                        <option value="accounting">Accounting</option>
                        <option value="hr">HR</option>
                        <option value="services">Services</option>
                        <option value="customers">Customers</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Create Permission</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tab) {
    document.getElementById('content-permissions').classList.add('hidden');
    document.getElementById('content-assign').classList.add('hidden');
    document.getElementById('tab-permissions').classList.remove('border-primary', 'text-primary');
    document.getElementById('tab-permissions').classList.add('border-transparent', 'text-gray-500');
    document.getElementById('tab-assign').classList.remove('border-primary', 'text-primary');
    document.getElementById('tab-assign').classList.add('border-transparent', 'text-gray-500');
    
    document.getElementById('content-' + tab).classList.remove('hidden');
    document.getElementById('tab-' + tab).classList.remove('border-transparent', 'text-gray-500');
    document.getElementById('tab-' + tab).classList.add('border-primary', 'text-primary');
}

function loadRolePermissions() {
    const select = document.getElementById('roleSelect');
    const option = select.options[select.selectedIndex];
    const permissions = JSON.parse(option.dataset.permissions || '[]');
    
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        cb.checked = permissions.includes(parseInt(cb.value));
    });
}

function openAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
    document.getElementById('addModal').classList.add('flex');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.getElementById(modalId).classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

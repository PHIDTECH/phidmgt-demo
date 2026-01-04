<?php
$pageTitle = 'Role Management';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== ROLE_SUPERADMIN) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle role creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_role'])) {
    $name = strtolower(str_replace(' ', '_', sanitize($_POST['name'])));
    $displayName = sanitize($_POST['display_name']);
    $description = sanitize($_POST['description']);
    $level = intval($_POST['level']);
    
    $stmt = $conn->prepare("SELECT id FROM roles WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        $error = 'Role already exists';
    } else {
        $stmt = $conn->prepare("INSERT INTO roles (name, display_name, description, level) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$name, $displayName, $description, $level])) {
            $success = 'Role created successfully';
        }
    }
}

// Handle role update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_role'])) {
    $roleId = intval($_POST['role_id']);
    $displayName = sanitize($_POST['display_name']);
    $description = sanitize($_POST['description']);
    $level = intval($_POST['level']);
    
    $stmt = $conn->prepare("UPDATE roles SET display_name = ?, description = ?, level = ? WHERE id = ?");
    if ($stmt->execute([$displayName, $description, $level, $roleId])) {
        $success = 'Role updated successfully';
    }
}

// Handle role deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_role'])) {
    $roleId = intval($_POST['role_id']);
    
    // Check if system role
    $stmt = $conn->prepare("SELECT is_system FROM roles WHERE id = ?");
    $stmt->execute([$roleId]);
    $role = $stmt->fetch();
    
    if ($role && $role['is_system']) {
        $error = 'Cannot delete system role';
    } else {
        $stmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
        if ($stmt->execute([$roleId])) {
            $success = 'Role deleted successfully';
        }
    }
}

// Get roles
$stmt = $conn->query("SELECT r.*, (SELECT COUNT(*) FROM users u WHERE u.role = r.name) as user_count FROM roles r ORDER BY r.level DESC");
$roles = $stmt->fetchAll();

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

<!-- Header -->
<div class="flex justify-between items-center mb-6">
    <p class="text-gray-600"><?= count($roles) ?> roles configured</p>
    <button onclick="openAddModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition">
        <i class="fas fa-plus mr-2"></i>Add Role
    </button>
</div>

<!-- Roles Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($roles as $role): ?>
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="font-bold text-gray-800"><?= htmlspecialchars($role['display_name']) ?></h3>
                <p class="text-sm text-gray-500"><?= htmlspecialchars($role['name']) ?></p>
            </div>
            <span class="px-2 py-1 bg-primary/10 text-primary rounded-full text-xs font-medium">
                Level <?= $role['level'] ?>
            </span>
        </div>
        
        <p class="text-sm text-gray-600 mb-4"><?= htmlspecialchars($role['description'] ?: 'No description') ?></p>
        
        <div class="flex items-center justify-between pt-4 border-t">
            <span class="text-sm text-gray-500">
                <i class="fas fa-users mr-1"></i> <?= $role['user_count'] ?> users
            </span>
            <div class="flex space-x-2">
                <?php if ($role['is_system']): ?>
                <span class="text-xs text-gray-400">System Role</span>
                <?php else: ?>
                <button onclick='openEditModal(<?= json_encode($role) ?>)' class="text-blue-600 hover:underline text-sm">Edit</button>
                <button onclick='confirmDelete(<?= $role["id"] ?>)' class="text-red-600 hover:underline text-sm">Delete</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Add Role Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4">Add New Role</h3>
        <form method="POST">
            <input type="hidden" name="add_role" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role Name *</label>
                    <input type="text" name="name" required pattern="[a-zA-Z_]+" class="w-full px-4 py-2 border rounded-lg" placeholder="e.g., sales_manager">
                    <p class="text-xs text-gray-500 mt-1">Letters and underscores only</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name *</label>
                    <input type="text" name="display_name" required class="w-full px-4 py-2 border rounded-lg" placeholder="e.g., Sales Manager">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Level (1-10) *</label>
                    <input type="number" name="level" required min="1" max="10" value="5" class="w-full px-4 py-2 border rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">Higher level = more permissions</p>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Create Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Role Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4">Edit Role</h3>
        <form method="POST">
            <input type="hidden" name="update_role" value="1">
            <input type="hidden" name="role_id" id="edit_role_id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role Name</label>
                    <input type="text" id="edit_name" disabled class="w-full px-4 py-2 border rounded-lg bg-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Display Name *</label>
                    <input type="text" name="display_name" id="edit_display_name" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" id="edit_description" rows="2" class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Level (1-10) *</label>
                    <input type="number" name="level" id="edit_level" required min="1" max="10" class="w-full px-4 py-2 border rounded-lg">
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Update Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="delete_role" value="1">
    <input type="hidden" name="role_id" id="delete_role_id">
</form>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
    document.getElementById('addModal').classList.add('flex');
}

function openEditModal(role) {
    document.getElementById('edit_role_id').value = role.id;
    document.getElementById('edit_name').value = role.name;
    document.getElementById('edit_display_name').value = role.display_name;
    document.getElementById('edit_description').value = role.description || '';
    document.getElementById('edit_level').value = role.level;
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.getElementById(modalId).classList.remove('flex');
}

function confirmDelete(roleId) {
    if (confirm('Are you sure you want to delete this role?')) {
        document.getElementById('delete_role_id').value = roleId;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

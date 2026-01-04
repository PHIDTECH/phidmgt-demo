<?php
$pageTitle = 'User Management';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== ROLE_SUPERADMIN) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $role = sanitize($_POST['role']);
    $department = sanitize($_POST['department']);
    $salary = floatval($_POST['salary']);
    $password = $_POST['password'];
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error = 'Email already exists';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role, department, salary) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$name, $email, $hashedPassword, $phone, $role, $department, $salary])) {
            $success = 'User created successfully';
        }
    }
}

// Handle user update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $userId = intval($_POST['user_id']);
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $role = sanitize($_POST['role']);
    $department = sanitize($_POST['department']);
    $salary = floatval($_POST['salary']);
    $status = sanitize($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, role = ?, department = ?, salary = ?, status = ? WHERE id = ?");
    if ($stmt->execute([$name, $phone, $role, $department, $salary, $status, $userId])) {
        $success = 'User updated successfully';
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $userId = intval($_POST['user_id']);
    $newPassword = $_POST['new_password'];
    
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    if ($stmt->execute([$hashedPassword, $userId])) {
        $success = 'Password reset successfully';
    }
}

// Handle user deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $userId = intval($_POST['user_id']);
    
    // Don't allow deleting superadmin
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['role'] === 'superadmin') {
        $error = 'Cannot delete superadmin user';
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        if ($stmt->execute([$userId])) {
            $success = 'User deleted successfully';
        }
    }
}

// Get filter
$filter = $_GET['role'] ?? 'all';
$where = "1=1";
if ($filter !== 'all') {
    $where = "role = '$filter'";
}

// Get users
$stmt = $conn->query("SELECT * FROM users WHERE $where ORDER BY created_at DESC");
$users = $stmt->fetchAll();

// Get roles for dropdown
$roles = ['superadmin', 'ceo', 'manager', 'accountant', 'hr', 'supervisor', 'it_staff', 'junior_staff', 'customer'];

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
<div class="flex flex-wrap justify-between items-center mb-6 gap-4">
    <div class="flex flex-wrap gap-2">
        <a href="?role=all" class="px-4 py-2 rounded-lg <?= $filter === 'all' ? 'bg-primary text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">All</a>
        <a href="?role=superadmin" class="px-4 py-2 rounded-lg <?= $filter === 'superadmin' ? 'bg-primary text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">Superadmin</a>
        <a href="?role=ceo" class="px-4 py-2 rounded-lg <?= $filter === 'ceo' ? 'bg-primary text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">CEO</a>
        <a href="?role=manager" class="px-4 py-2 rounded-lg <?= $filter === 'manager' ? 'bg-primary text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">Manager</a>
        <a href="?role=customer" class="px-4 py-2 rounded-lg <?= $filter === 'customer' ? 'bg-primary text-white' : 'bg-gray-100 hover:bg-gray-200' ?>">Customer</a>
    </div>
    <button onclick="openAddModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition">
        <i class="fas fa-plus mr-2"></i>Add User
    </button>
</div>

<!-- Users Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($users as $user): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center font-medium mr-3">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($user['name']) ?></p>
                                <p class="text-xs text-gray-500">ID: <?= $user['id'] ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm"><?= htmlspecialchars($user['email']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($user['phone'] ?: '-') ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $user['role'] === 'superadmin' ? 'bg-red-100 text-red-800' : 'bg-primary/10 text-primary' ?>">
                            <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($user['department'] ?: '-') ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= ucfirst($user['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <button onclick='openEditModal(<?= json_encode($user) ?>)' class="text-blue-600 hover:underline text-sm mr-2">Edit</button>
                        <button onclick='openPasswordModal(<?= $user["id"] ?>)' class="text-orange-600 hover:underline text-sm mr-2">Reset Pass</button>
                        <?php if ($user['role'] !== 'superadmin'): ?>
                        <button onclick='confirmDelete(<?= $user["id"] ?>)' class="text-red-600 hover:underline text-sm">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Modal -->
<div id="addModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold mb-4">Add New User</h3>
        <form method="POST">
            <input type="hidden" name="add_user" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="name" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                    <input type="email" name="email" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                    <input type="password" name="password" required minlength="6" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" name="phone" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                        <select name="role" required class="w-full px-4 py-2 border rounded-lg">
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r ?>"><?= ucfirst(str_replace('_', ' ', $r)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <input type="text" name="department" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salary (TZS)</label>
                        <input type="number" name="salary" min="0" step="0.01" value="0" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-bold mb-4">Edit User</h3>
        <form method="POST">
            <input type="hidden" name="update_user" value="1">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="name" id="edit_name" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" id="edit_email" disabled class="w-full px-4 py-2 border rounded-lg bg-gray-100">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" name="phone" id="edit_phone" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                        <select name="role" id="edit_role" required class="w-full px-4 py-2 border rounded-lg">
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r ?>"><?= ucfirst(str_replace('_', ' ', $r)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <input type="text" name="department" id="edit_department" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Salary (TZS)</label>
                        <input type="number" name="salary" id="edit_salary" min="0" step="0.01" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="edit_status" class="w-full px-4 py-2 border rounded-lg">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Update User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="passwordModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4">Reset Password</h3>
        <form method="POST">
            <input type="hidden" name="reset_password" value="1">
            <input type="hidden" name="user_id" id="password_user_id">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">New Password *</label>
                <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-2 border rounded-lg">
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeModal('passwordModal')" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-orange-500 text-white rounded-lg">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" class="hidden">
    <input type="hidden" name="delete_user" value="1">
    <input type="hidden" name="user_id" id="delete_user_id">
</form>

<script>
function openAddModal() {
    document.getElementById('addModal').classList.remove('hidden');
    document.getElementById('addModal').classList.add('flex');
}

function openEditModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_name').value = user.name;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_phone').value = user.phone || '';
    document.getElementById('edit_role').value = user.role;
    document.getElementById('edit_department').value = user.department || '';
    document.getElementById('edit_salary').value = user.salary;
    document.getElementById('edit_status').value = user.status;
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}

function openPasswordModal(userId) {
    document.getElementById('password_user_id').value = userId;
    document.getElementById('passwordModal').classList.remove('hidden');
    document.getElementById('passwordModal').classList.add('flex');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.add('hidden');
    document.getElementById(modalId).classList.remove('flex');
}

function confirmDelete(userId) {
    if (confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        document.getElementById('delete_user_id').value = userId;
        document.getElementById('deleteForm').submit();
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

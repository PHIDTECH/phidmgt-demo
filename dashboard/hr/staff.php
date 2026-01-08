<?php
$pageTitle = 'Staff Management';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_HR])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';

// Handle new staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $role = sanitize($_POST['role']);
    $department = sanitize($_POST['department']);
    $salary = floatval($_POST['salary']);
    $password = password_hash('phidtech123', PASSWORD_DEFAULT);
    
    // Collect permissions
    $permissions = isset($_POST['permissions']) ? implode(',', $_POST['permissions']) : '';
    
    // Check if permissions column exists, if not add it
    try {
        $conn->query("SELECT permissions FROM users LIMIT 1");
    } catch (Exception $e) {
        $conn->query("ALTER TABLE users ADD COLUMN permissions TEXT DEFAULT NULL");
    }
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, department, salary, permissions) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$name, $email, $phone, $password, $role, $department, $salary, $permissions])) {
        $success = 'Staff member added successfully. Default password: phidtech123';
    }
}

// Handle edit staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_staff'])) {
    $userId = intval($_POST['user_id']);
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $role = sanitize($_POST['role']);
    $department = sanitize($_POST['department']);
    $salary = floatval($_POST['salary']);
    $permissions = isset($_POST['permissions']) ? implode(',', $_POST['permissions']) : '';
    $newPassword = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
    
    // Check if permissions column exists, if not add it
    try {
        $conn->query("SELECT permissions FROM users LIMIT 1");
    } catch (Exception $e) {
        $conn->query("ALTER TABLE users ADD COLUMN permissions TEXT DEFAULT NULL");
    }
    
    // Update with or without password
    if (!empty($newPassword)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, department = ?, salary = ?, permissions = ?, password = ? WHERE id = ?");
        if ($stmt->execute([$name, $email, $phone, $role, $department, $salary, $permissions, $hashedPassword, $userId])) {
            $success = 'Staff member updated successfully with new password';
        }
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, role = ?, department = ?, salary = ?, permissions = ? WHERE id = ?");
        if ($stmt->execute([$name, $email, $phone, $role, $department, $salary, $permissions, $userId])) {
            $success = 'Staff member updated successfully';
        }
    }
}

// Handle delete staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff'])) {
    $userId = intval($_POST['user_id']);
    
    // Don't allow deleting yourself
    if ($userId != $_SESSION['user_id']) {
        // CEO can delete anyone, others cannot delete CEO
        if ($_SESSION['user_role'] === ROLE_CEO) {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'ceo'");
        }
        if ($stmt->execute([$userId])) {
            $success = 'Staff member deleted successfully';
        }
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $userId = intval($_POST['user_id']);
    $status = sanitize($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
    $stmt->execute([$status, $userId]);
    $success = 'Staff status updated';
}

// Get staff
$stmt = $conn->query("SELECT * FROM users WHERE role != 'customer' ORDER BY name");
$staff = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<!-- Summary -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Staff</p>
        <p class="text-3xl font-bold text-gray-800"><?= count($staff) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Active</p>
        <p class="text-3xl font-bold text-green-600"><?= count(array_filter($staff, fn($s) => $s['status'] === 'active')) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Salaries</p>
        <p class="text-2xl font-bold text-primary"><?= formatCurrency(array_sum(array_column($staff, 'salary'))) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <button onclick="document.getElementById('staffModal').classList.remove('hidden'); document.getElementById('staffModal').classList.add('flex');" 
                class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
            <i class="fas fa-plus mr-2"></i>Add Staff
        </button>
    </div>
</div>

<!-- Staff Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Department</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Salary</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($staff as $member): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($member['name']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($member['phone']) ?></p>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($member['email']) ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-primary/10 text-primary rounded-full text-xs font-medium">
                            <?= ucfirst(str_replace('_', ' ', $member['role'])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($member['department'] ?: '-') ?></td>
                    <td class="px-6 py-4 text-right font-medium"><?= formatCurrency($member['salary']) ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $member['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= ucfirst($member['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center space-x-2">
                        <button onclick='openEditStaffModal(<?= json_encode($member) ?>)' class="text-blue-600 hover:underline text-sm">Edit</button>
                        <form method="POST" class="inline">
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                            <?php if ($member['status'] === 'active'): ?>
                            <button type="submit" name="status" value="inactive" class="text-orange-600 hover:underline text-sm">Deactivate</button>
                            <?php else: ?>
                            <button type="submit" name="status" value="active" class="text-green-600 hover:underline text-sm">Activate</button>
                            <?php endif; ?>
                        </form>
                        <?php if ($member['id'] != $_SESSION['user_id'] && ($_SESSION['user_role'] === ROLE_CEO || $member['role'] !== 'ceo')): ?>
                        <button onclick="confirmDelete(<?= $member['id'] ?>, '<?= htmlspecialchars($member['name']) ?>')" class="text-red-600 hover:underline text-sm">Delete</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Staff Modal -->
<div id="staffModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl w-full max-w-lg mx-4 max-h-[90vh] flex flex-col">
        <div class="p-6 border-b">
            <h3 class="text-lg font-bold">Add Staff Member</h3>
        </div>
        <form method="POST" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="add_staff" value="1">
            <div class="p-6 space-y-4 overflow-y-auto flex-1">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="name" required placeholder="Enter staff full name" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" required placeholder="email@example.com" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" name="phone" value="255" pattern="255[0-9]{9}" placeholder="255XXXXXXXXX" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                        <select name="role" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="junior_staff">Junior Staff</option>
                            <option value="it_staff">IT Staff</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="hr">HR</option>
                            <option value="accountant">Accountant</option>
                            <option value="manager">Manager</option>
                            <?php if ($_SESSION['user_role'] === ROLE_CEO): ?>
                            <option value="ceo">CEO</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <input type="text" name="department" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Salary (TZS)</label>
                    <input type="number" name="salary" min="0" step="0.01" class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <!-- Permissions Section -->
                <div class="border-t pt-4 mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Permissions</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_customers" class="rounded text-primary">
                            <span>View Customers</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="create_customer" class="rounded text-primary">
                            <span>Create Customer</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_projects" class="rounded text-primary">
                            <span>View Projects</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="create_project" class="rounded text-primary">
                            <span>Create Project</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_tasks" class="rounded text-primary">
                            <span>View Tasks</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="create_task" class="rounded text-primary">
                            <span>Create Task</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_messages" class="rounded text-primary">
                            <span>View Messages</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_revenue" class="rounded text-primary">
                            <span>View Revenue</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_expenses" class="rounded text-primary">
                            <span>View Expenses</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="create_expense" class="rounded text-primary">
                            <span>Create Expense</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_staff" class="rounded text-primary">
                            <span>View Staff</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="create_staff" class="rounded text-primary">
                            <span>Create Staff</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_reports" class="rounded text-primary">
                            <span>View Reports</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_services" class="rounded text-primary">
                            <span>Manage Services</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_assets" class="rounded text-primary">
                            <span>View Assets</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_salaries" class="rounded text-primary">
                            <span>Manage Salaries</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_expenses" class="rounded text-primary">
                            <span>Manage Expenses</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_petty_cash" class="rounded text-primary">
                            <span>Manage Petty Cash</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_assets" class="rounded text-primary">
                            <span>Manage Assets</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_liabilities" class="rounded text-primary">
                            <span>Manage Liabilities</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_revenue" class="rounded text-primary">
                            <span>Manage Revenue</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_leave" class="rounded text-primary">
                            <span>Manage Leave</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm bg-yellow-50 p-2 rounded col-span-2">
                            <input type="checkbox" name="permissions[]" value="admin" class="rounded text-primary">
                            <span class="font-medium text-yellow-800">Full Admin Access</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t bg-gray-50">
                <p class="text-sm text-gray-500 mb-4">Default password will be: phidtech123</p>
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="document.getElementById('staffModal').classList.add('hidden'); document.getElementById('staffModal').classList.remove('flex');" 
                            class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Add Staff</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Edit Staff Modal -->
<div id="editStaffModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl w-full max-w-lg mx-4 max-h-[90vh] flex flex-col">
        <div class="p-6 border-b">
            <h3 class="text-lg font-bold"><i class="fas fa-edit text-blue-600 mr-2"></i>Edit Staff Member</h3>
        </div>
        <form method="POST" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="edit_staff" value="1">
            <input type="hidden" name="user_id" id="editUserId">
            <div class="p-6 space-y-4 overflow-y-auto flex-1">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="name" id="editName" required placeholder="Enter staff full name" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" id="editEmail" required placeholder="email@example.com" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" name="phone" id="editPhone" pattern="255[0-9]{9}" placeholder="255XXXXXXXXX" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Role *</label>
                        <select name="role" id="editRole" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="junior_staff">Junior Staff</option>
                            <option value="it_staff">IT Staff</option>
                            <option value="supervisor">Supervisor</option>
                            <option value="hr">HR</option>
                            <option value="accountant">Accountant</option>
                            <option value="manager">Manager</option>
                            <?php if ($_SESSION['user_role'] === ROLE_CEO): ?>
                            <option value="ceo">CEO</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Department</label>
                        <input type="text" name="department" id="editDepartment" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Monthly Salary (TZS)</label>
                    <input type="number" name="salary" id="editSalary" min="0" step="0.01" class="w-full px-4 py-2 border rounded-lg">
                </div>
                
                <!-- Password Reset Section -->
                <div class="border-t pt-4 mt-4 bg-orange-50 -mx-6 px-6 py-4">
                    <label class="block text-sm font-medium text-orange-800 mb-1"><i class="fas fa-key mr-1"></i>Reset Password</label>
                    <input type="password" name="new_password" id="editNewPassword" placeholder="Leave empty to keep current password" class="w-full px-4 py-2 border border-orange-200 rounded-lg focus:ring-orange-500 focus:border-orange-500">
                    <p class="text-xs text-orange-600 mt-1">Only fill this if you want to reset the staff member's password</p>
                </div>
                
                <!-- Permissions Section -->
                <div class="border-t pt-4 mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-3">Permissions</label>
                    <div class="grid grid-cols-2 gap-3" id="editPermissionsContainer">
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_customers" class="rounded text-primary edit-perm">
                            <span>View Customers</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="create_customer" class="rounded text-primary edit-perm">
                            <span>Create Customer</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_projects" class="rounded text-primary edit-perm">
                            <span>View Projects</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="create_project" class="rounded text-primary edit-perm">
                            <span>Create Project</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_tasks" class="rounded text-primary edit-perm">
                            <span>View Tasks</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="create_task" class="rounded text-primary edit-perm">
                            <span>Create Task</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_messages" class="rounded text-primary edit-perm">
                            <span>View Messages</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_revenue" class="rounded text-primary edit-perm">
                            <span>View Revenue</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_expenses" class="rounded text-primary edit-perm">
                            <span>View Expenses</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="create_expense" class="rounded text-primary edit-perm">
                            <span>Create Expense</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_staff" class="rounded text-primary edit-perm">
                            <span>View Staff</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="create_staff" class="rounded text-primary edit-perm">
                            <span>Create Staff</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_reports" class="rounded text-primary edit-perm">
                            <span>View Reports</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_services" class="rounded text-primary edit-perm">
                            <span>Manage Services</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="view_assets" class="rounded text-primary edit-perm">
                            <span>View Assets</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_salaries" class="rounded text-primary edit-perm">
                            <span>Manage Salaries</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_expenses" class="rounded text-primary edit-perm">
                            <span>Manage Expenses</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_petty_cash" class="rounded text-primary edit-perm">
                            <span>Manage Petty Cash</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_assets" class="rounded text-primary edit-perm">
                            <span>Manage Assets</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_liabilities" class="rounded text-primary edit-perm">
                            <span>Manage Liabilities</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_revenue" class="rounded text-primary edit-perm">
                            <span>Manage Revenue</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm">
                            <input type="checkbox" name="permissions[]" value="manage_leave" class="rounded text-primary edit-perm">
                            <span>Manage Leave</span>
                        </label>
                        <label class="flex items-center space-x-2 text-sm bg-yellow-50 p-2 rounded col-span-2">
                            <input type="checkbox" name="permissions[]" value="admin" class="rounded text-primary edit-perm">
                            <span class="font-medium text-yellow-800">Full Admin Access</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="p-6 border-t bg-gray-50">
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="closeEditStaffModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Update Staff</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-bold mb-4 text-red-600"><i class="fas fa-exclamation-triangle mr-2"></i>Delete Staff Member</h3>
        <p class="text-gray-600 mb-4">Are you sure you want to delete <strong id="deleteStaffName"></strong>? This action cannot be undone.</p>
        <form method="POST">
            <input type="hidden" name="delete_staff" value="1">
            <input type="hidden" name="user_id" id="deleteUserId">
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"><i class="fas fa-trash mr-2"></i>Delete</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditStaffModal(staff) {
    document.getElementById('editUserId').value = staff.id;
    document.getElementById('editName').value = staff.name;
    document.getElementById('editEmail').value = staff.email;
    document.getElementById('editPhone').value = staff.phone || '';
    document.getElementById('editRole').value = staff.role;
    document.getElementById('editDepartment').value = staff.department || '';
    document.getElementById('editSalary').value = staff.salary || 0;
    
    // Reset all checkboxes
    document.querySelectorAll('.edit-perm').forEach(cb => cb.checked = false);
    
    // Set permissions
    if (staff.permissions) {
        const perms = staff.permissions.split(',');
        perms.forEach(perm => {
            const cb = document.querySelector(`.edit-perm[value="${perm.trim()}"]`);
            if (cb) cb.checked = true;
        });
    }
    
    document.getElementById('editStaffModal').classList.remove('hidden');
    document.getElementById('editStaffModal').classList.add('flex');
}

function closeEditStaffModal() {
    document.getElementById('editStaffModal').classList.add('hidden');
    document.getElementById('editStaffModal').classList.remove('flex');
}

function confirmDelete(userId, userName) {
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteStaffName').textContent = userName;
    document.getElementById('deleteModal').classList.remove('hidden');
    document.getElementById('deleteModal').classList.add('flex');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
    document.getElementById('deleteModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

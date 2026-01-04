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
    
    $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password, role, department, salary) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$name, $email, $phone, $password, $role, $department, $salary])) {
        $success = 'Staff member added successfully. Default password: phidtech123';
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
                    <td class="px-6 py-4 text-center">
                        <form method="POST" class="inline">
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" name="user_id" value="<?= $member['id'] ?>">
                            <?php if ($member['status'] === 'active'): ?>
                            <button type="submit" name="status" value="inactive" class="text-red-600 hover:underline text-sm">Deactivate</button>
                            <?php else: ?>
                            <button type="submit" name="status" value="active" class="text-green-600 hover:underline text-sm">Activate</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Staff Modal -->
<div id="staffModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg">
        <h3 class="text-lg font-bold mb-4">Add Staff Member</h3>
        <form method="POST">
            <input type="hidden" name="add_staff" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" name="name" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" name="email" required class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                        <input type="tel" name="phone" class="w-full px-4 py-2 border rounded-lg">
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
            </div>
            <p class="text-sm text-gray-500 mt-4">Default password will be: phidtech123</p>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="document.getElementById('staffModal').classList.add('hidden'); document.getElementById('staffModal').classList.remove('flex');" 
                        class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Add Staff</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

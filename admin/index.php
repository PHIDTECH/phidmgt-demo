<?php
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== ROLE_SUPERADMIN) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();

// Get stats
$stmt = $conn->query("SELECT COUNT(*) FROM users");
$totalUsers = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role != 'customer'");
$totalStaff = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM roles");
$totalRoles = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COUNT(*) FROM permissions");
$totalPermissions = $stmt->fetchColumn();

// Recent users
$stmt = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recentUsers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Stats -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Total Users</p>
                <p class="text-3xl font-bold text-gray-800"><?= $totalUsers ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                <i class="fas fa-users text-xl"></i>
            </div>
        </div>
        <a href="users.php" class="text-primary text-sm hover:underline mt-2 inline-block">Manage Users →</a>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Staff Members</p>
                <p class="text-3xl font-bold text-green-600"><?= $totalStaff ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 text-green-600 rounded-full flex items-center justify-center">
                <i class="fas fa-user-tie text-xl"></i>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Roles</p>
                <p class="text-3xl font-bold text-purple-600"><?= $totalRoles ?></p>
            </div>
            <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-full flex items-center justify-center">
                <i class="fas fa-user-tag text-xl"></i>
            </div>
        </div>
        <a href="roles.php" class="text-primary text-sm hover:underline mt-2 inline-block">Manage Roles →</a>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-500 text-sm">Permissions</p>
                <p class="text-3xl font-bold text-orange-600"><?= $totalPermissions ?></p>
            </div>
            <div class="w-12 h-12 bg-orange-100 text-orange-600 rounded-full flex items-center justify-center">
                <i class="fas fa-key text-xl"></i>
            </div>
        </div>
        <a href="permissions.php" class="text-primary text-sm hover:underline mt-2 inline-block">Manage Permissions →</a>
    </div>
</div>

<!-- Quick Actions -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 gap-4">
            <a href="users.php?action=add" class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                <i class="fas fa-user-plus text-blue-600 text-xl mr-3"></i>
                <span class="font-medium">Add User</span>
            </a>
            <a href="roles.php?action=add" class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition">
                <i class="fas fa-plus-circle text-purple-600 text-xl mr-3"></i>
                <span class="font-medium">Add Role</span>
            </a>
            <a href="permissions.php" class="flex items-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition">
                <i class="fas fa-shield-alt text-orange-600 text-xl mr-3"></i>
                <span class="font-medium">Permissions</span>
            </a>
            <a href="../dashboard/" class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition">
                <i class="fas fa-tachometer-alt text-green-600 text-xl mr-3"></i>
                <span class="font-medium">Main Dashboard</span>
            </a>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md p-6">
        <h2 class="text-lg font-bold text-gray-800 mb-4">System Info</h2>
        <div class="space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">PHP Version</span>
                <span class="font-medium"><?= phpversion() ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">MySQL Version</span>
                <span class="font-medium"><?= $conn->getAttribute(PDO::ATTR_SERVER_VERSION) ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Server Time</span>
                <span class="font-medium"><?= date('Y-m-d H:i:s') ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">System Status</span>
                <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-medium">Online</span>
            </div>
        </div>
    </div>
</div>

<!-- Recent Users -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b flex justify-between items-center">
        <h2 class="font-bold text-gray-800">Recent Users</h2>
        <a href="users.php" class="text-primary text-sm hover:underline">View All</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($recentUsers as $user): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-8 h-8 bg-primary text-white rounded-full flex items-center justify-center text-sm font-medium mr-3">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </div>
                            <span class="font-medium"><?= htmlspecialchars($user['name']) ?></span>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($user['email']) ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-primary/10 text-primary rounded-full text-xs font-medium">
                            <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $user['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= ucfirst($user['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= formatDate($user['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

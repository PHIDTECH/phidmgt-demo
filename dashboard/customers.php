<?php
$pageTitle = 'Customers';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

// Get customers with their stats
$stmt = $conn->query("
    SELECT u.*, 
           COUNT(DISTINCT p.id) as total_projects,
           COALESCE(SUM(p.paid_amount), 0) as total_spent
    FROM users u
    LEFT JOIN projects p ON u.id = p.customer_id
    WHERE u.role = 'customer'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Summary -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Customers</p>
        <p class="text-3xl font-bold text-gray-800"><?= count($customers) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Active Customers</p>
        <p class="text-3xl font-bold text-green-600"><?= count(array_filter($customers, fn($c) => $c['status'] === 'active')) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Revenue</p>
        <p class="text-2xl font-bold text-primary"><?= formatCurrency(array_sum(array_column($customers, 'total_spent'))) ?></p>
    </div>
</div>

<!-- Customers Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Projects</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Total Spent</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Joined</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($customers as $customer): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-primary text-white rounded-full flex items-center justify-center font-semibold mr-3">
                                <?= strtoupper(substr($customer['name'], 0, 1)) ?>
                            </div>
                            <p class="font-medium text-gray-800"><?= htmlspecialchars($customer['name']) ?></p>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($customer['email']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($customer['phone'] ?: '-') ?></p>
                    </td>
                    <td class="px-6 py-4 text-center font-medium"><?= $customer['total_projects'] ?></td>
                    <td class="px-6 py-4 text-right font-semibold text-green-600"><?= formatCurrency($customer['total_spent']) ?></td>
                    <td class="px-6 py-4 text-sm"><?= formatDate($customer['created_at']) ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $customer['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= ucfirst($customer['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

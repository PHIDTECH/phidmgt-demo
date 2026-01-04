<?php
$pageTitle = 'My Salary';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || $_SESSION['user_role'] === ROLE_CUSTOMER) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();

// Get my salary records
$stmt = $conn->prepare("
    SELECT * FROM salary_records 
    WHERE user_id = ? 
    ORDER BY month DESC
");
$stmt->execute([$_SESSION['user_id']]);
$records = $stmt->fetchAll();

// Get current salary info
$stmt = $conn->prepare("SELECT salary FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$currentSalary = $stmt->fetchColumn();

// Get year-to-date
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(net_salary), 0) FROM salary_records 
    WHERE user_id = ? AND YEAR(STR_TO_DATE(CONCAT(month, '-01'), '%Y-%m-%d')) = YEAR(CURDATE()) AND status = 'paid'
");
$stmt->execute([$_SESSION['user_id']]);
$ytdEarnings = $stmt->fetchColumn();

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Current Monthly Salary</p>
        <p class="text-2xl font-bold text-primary"><?= formatCurrency($currentSalary) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Year-to-Date Earnings</p>
        <p class="text-2xl font-bold text-green-600"><?= formatCurrency($ytdEarnings) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Records</p>
        <p class="text-2xl font-bold text-gray-800"><?= count($records) ?></p>
    </div>
</div>

<!-- Salary Records -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b">
        <h2 class="font-bold text-gray-800">Salary History</h2>
    </div>
    
    <?php if (empty($records)): ?>
    <div class="p-8 text-center text-gray-500">No salary records yet</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Month</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Basic Salary</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allowances</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deductions</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net Salary</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($records as $record): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium"><?= date('F Y', strtotime($record['month'] . '-01')) ?></td>
                    <td class="px-6 py-4 text-right"><?= formatCurrency($record['basic_salary']) ?></td>
                    <td class="px-6 py-4 text-right text-green-600">+<?= formatCurrency($record['allowances']) ?></td>
                    <td class="px-6 py-4 text-right text-red-600">-<?= formatCurrency($record['deductions']) ?></td>
                    <td class="px-6 py-4 text-right font-semibold"><?= formatCurrency($record['net_salary']) ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $record['status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                            <?= ucfirst($record['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= $record['payment_date'] ? formatDate($record['payment_date']) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
$pageTitle = 'Financial Reports';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

// Date filter
$startDate = $_GET['start'] ?? date('Y-01-01');
$endDate = $_GET['end'] ?? date('Y-12-31');

// Revenue by category
$stmt = $conn->prepare("
    SELECT COALESCE(category, 'Other') as category, SUM(amount) as total 
    FROM revenue 
    WHERE date BETWEEN ? AND ?
    GROUP BY category
");
$stmt->execute([$startDate, $endDate]);
$revenueByCategory = $stmt->fetchAll();

// Total Revenue
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE date BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$totalRevenue = $stmt->fetchColumn();

// Expenses by category (approved only)
$stmt = $conn->prepare("
    SELECT COALESCE(category, 'Other') as category, SUM(amount) as total 
    FROM expenses 
    WHERE date BETWEEN ? AND ? AND status = 'approved'
    GROUP BY category
");
$stmt->execute([$startDate, $endDate]);
$expensesByCategory = $stmt->fetchAll();

// Total Expenses
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE date BETWEEN ? AND ? AND status = 'approved'");
$stmt->execute([$startDate, $endDate]);
$totalExpenses = $stmt->fetchColumn();

// Salary expenses
$stmt = $conn->prepare("SELECT COALESCE(SUM(net_salary), 0) FROM salary_records WHERE month BETWEEN ? AND ? AND status = 'paid'");
$stmt->execute([substr($startDate, 0, 7), substr($endDate, 0, 7)]);
$totalSalaries = $stmt->fetchColumn();

// Net Profit
$netProfit = $totalRevenue - $totalExpenses - $totalSalaries;

// Assets & Liabilities for Trial Balance
$stmt = $conn->query("SELECT COALESCE(SUM(current_value), 0) FROM assets WHERE status = 'active'");
$totalAssets = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) FROM liabilities WHERE status IN ('active', 'overdue')");
$totalLiabilities = $stmt->fetchColumn();

// Monthly Revenue Trend
$stmt = $conn->prepare("
    SELECT DATE_FORMAT(date, '%Y-%m') as month, SUM(amount) as total 
    FROM revenue 
    WHERE date BETWEEN ? AND ?
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month
");
$stmt->execute([$startDate, $endDate]);
$monthlyRevenue = $stmt->fetchAll();

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Date Filter -->
<div class="bg-white rounded-xl shadow-md p-4 mb-6">
    <form method="GET" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
            <input type="date" name="start" value="<?= $startDate ?>" class="px-4 py-2 border rounded-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
            <input type="date" name="end" value="<?= $endDate ?>" class="px-4 py-2 border rounded-lg">
        </div>
        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg">Generate Report</button>
    </form>
</div>

<!-- Profit & Loss Statement -->
<div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
    <div class="p-4 border-b bg-gray-50">
        <h2 class="font-bold text-gray-800 text-lg">Profit & Loss Statement</h2>
        <p class="text-sm text-gray-500"><?= formatDate($startDate) ?> - <?= formatDate($endDate) ?></p>
    </div>
    <div class="p-6">
        <table class="w-full">
            <tbody>
                <tr class="border-b">
                    <td colspan="2" class="py-3 font-bold text-gray-800 text-lg">Revenue</td>
                </tr>
                <?php foreach ($revenueByCategory as $item): ?>
                <tr>
                    <td class="py-2 pl-4 text-gray-600"><?= htmlspecialchars($item['category']) ?></td>
                    <td class="py-2 text-right"><?= formatCurrency($item['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="border-b bg-green-50">
                    <td class="py-3 pl-4 font-semibold text-green-800">Total Revenue</td>
                    <td class="py-3 text-right font-bold text-green-800"><?= formatCurrency($totalRevenue) ?></td>
                </tr>
                
                <tr class="border-b">
                    <td colspan="2" class="py-3 font-bold text-gray-800 text-lg pt-6">Expenses</td>
                </tr>
                <?php foreach ($expensesByCategory as $item): ?>
                <tr>
                    <td class="py-2 pl-4 text-gray-600"><?= htmlspecialchars($item['category']) ?></td>
                    <td class="py-2 text-right"><?= formatCurrency($item['total']) ?></td>
                </tr>
                <?php endforeach; ?>
                <tr>
                    <td class="py-2 pl-4 text-gray-600">Salaries & Wages</td>
                    <td class="py-2 text-right"><?= formatCurrency($totalSalaries) ?></td>
                </tr>
                <tr class="border-b bg-red-50">
                    <td class="py-3 pl-4 font-semibold text-red-800">Total Expenses</td>
                    <td class="py-3 text-right font-bold text-red-800"><?= formatCurrency($totalExpenses + $totalSalaries) ?></td>
                </tr>
                
                <tr class="<?= $netProfit >= 0 ? 'bg-green-100' : 'bg-red-100' ?>">
                    <td class="py-4 font-bold text-lg <?= $netProfit >= 0 ? 'text-green-800' : 'text-red-800' ?>">Net Profit/Loss</td>
                    <td class="py-4 text-right font-bold text-xl <?= $netProfit >= 0 ? 'text-green-800' : 'text-red-800' ?>">
                        <?= formatCurrency($netProfit) ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Trial Balance -->
<div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
    <div class="p-4 border-b bg-gray-50">
        <h2 class="font-bold text-gray-800 text-lg">Trial Balance Summary</h2>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="font-semibold text-gray-800 mb-4 pb-2 border-b">Assets (Debit)</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Fixed Assets</span>
                        <span class="font-medium"><?= formatCurrency($totalAssets) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Cash & Bank (Revenue)</span>
                        <span class="font-medium"><?= formatCurrency($totalRevenue) ?></span>
                    </div>
                    <div class="flex justify-between pt-2 border-t font-semibold">
                        <span>Total Debit</span>
                        <span><?= formatCurrency($totalAssets + $totalRevenue) ?></span>
                    </div>
                </div>
            </div>
            <div>
                <h3 class="font-semibold text-gray-800 mb-4 pb-2 border-b">Liabilities & Equity (Credit)</h3>
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Current Liabilities</span>
                        <span class="font-medium"><?= formatCurrency($totalLiabilities) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Expenses</span>
                        <span class="font-medium"><?= formatCurrency($totalExpenses + $totalSalaries) ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Retained Earnings</span>
                        <span class="font-medium"><?= formatCurrency($netProfit) ?></span>
                    </div>
                    <div class="flex justify-between pt-2 border-t font-semibold">
                        <span>Total Credit</span>
                        <span><?= formatCurrency($totalLiabilities + $totalExpenses + $totalSalaries + $netProfit) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Monthly Revenue Chart -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b bg-gray-50">
        <h2 class="font-bold text-gray-800 text-lg">Monthly Revenue Trend</h2>
    </div>
    <div class="p-6">
        <div class="space-y-3">
            <?php 
            $maxRevenue = max(array_column($monthlyRevenue, 'total')) ?: 1;
            foreach ($monthlyRevenue as $item): 
                $percentage = ($item['total'] / $maxRevenue) * 100;
            ?>
            <div>
                <div class="flex justify-between text-sm mb-1">
                    <span class="text-gray-600"><?= date('F Y', strtotime($item['month'] . '-01')) ?></span>
                    <span class="font-medium"><?= formatCurrency($item['total']) ?></span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-4">
                    <div class="bg-primary h-4 rounded-full" style="width: <?= $percentage ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

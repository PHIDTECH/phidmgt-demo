<?php
$pageTitle = 'Revenue';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

// Date filter
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-t');

// Get revenue
$stmt = $conn->prepare("
    SELECT r.*, p.title as project_title, p.reference as project_ref, u.name as recorded_by_name
    FROM revenue r
    LEFT JOIN projects p ON r.project_id = p.id
    LEFT JOIN users u ON r.recorded_by = u.id
    WHERE r.date BETWEEN ? AND ?
    ORDER BY r.date DESC
");
$stmt->execute([$startDate, $endDate]);
$revenues = $stmt->fetchAll();

// Get totals
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE date BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$totalRevenue = $stmt->fetchColumn();

// Get by category
$stmt = $conn->prepare("
    SELECT category, SUM(amount) as total 
    FROM revenue 
    WHERE date BETWEEN ? AND ?
    GROUP BY category
");
$stmt->execute([$startDate, $endDate]);
$byCategory = $stmt->fetchAll();

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
        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg">Filter</button>
    </form>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Revenue</p>
        <p class="text-3xl font-bold text-green-600"><?= formatCurrency($totalRevenue) ?></p>
        <p class="text-sm text-gray-500"><?= formatDate($startDate) ?> - <?= formatDate($endDate) ?></p>
    </div>
    
    <?php foreach ($byCategory as $cat): ?>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm"><?= htmlspecialchars($cat['category'] ?: 'Other') ?></p>
        <p class="text-2xl font-bold text-gray-800"><?= formatCurrency($cat['total']) ?></p>
    </div>
    <?php endforeach; ?>
</div>

<!-- Revenue Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b">
        <h2 class="font-bold text-gray-800">Revenue Records</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Project</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($revenues as $rev): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm"><?= formatDate($rev['date']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($rev['description']) ?></td>
                    <td class="px-6 py-4 text-sm">
                        <?php if ($rev['project_ref']): ?>
                        <a href="<?= SITE_URL ?>/dashboard/project-details.php?id=<?= $rev['project_id'] ?>" class="text-primary hover:underline">
                            <?= $rev['project_ref'] ?>
                        </a>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($rev['category'] ?: '-') ?></td>
                    <td class="px-6 py-4 text-right font-semibold text-green-600"><?= formatCurrency($rev['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="4" class="px-6 py-4 font-bold">Total</td>
                    <td class="px-6 py-4 text-right font-bold text-green-600"><?= formatCurrency($totalRevenue) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

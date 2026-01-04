<?php
$pageTitle = 'Salary Management';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_HR, ROLE_ACCOUNTANT])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';

// Handle salary processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_salaries'])) {
    $month = sanitize($_POST['month']);
    
    // Get all active staff
    $stmt = $conn->query("SELECT id, salary FROM users WHERE role != 'customer' AND status = 'active' AND salary > 0");
    $staff = $stmt->fetchAll();
    
    foreach ($staff as $member) {
        // Check if already processed
        $check = $conn->prepare("SELECT id FROM salary_records WHERE user_id = ? AND month = ?");
        $check->execute([$member['id'], $month]);
        
        if (!$check->fetch()) {
            $basicSalary = $member['salary'];
            $allowances = 0;
            $deductions = 0;
            $netSalary = $basicSalary + $allowances - $deductions;
            
            $stmt = $conn->prepare("INSERT INTO salary_records (user_id, month, basic_salary, allowances, deductions, net_salary, processed_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$member['id'], $month, $basicSalary, $allowances, $deductions, $netSalary, $_SESSION['user_id']]);
        }
    }
    
    $success = 'Salaries processed for ' . $month;
}

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    $recordId = intval($_POST['record_id']);
    
    $stmt = $conn->prepare("UPDATE salary_records SET status = 'paid', payment_date = CURDATE() WHERE id = ?");
    $stmt->execute([$recordId]);
    $success = 'Salary marked as paid';
}

// Get current month
$currentMonth = $_GET['month'] ?? date('Y-m');

// Get salary records
$stmt = $conn->prepare("
    SELECT sr.*, u.name, u.email, u.department, u.role
    FROM salary_records sr
    JOIN users u ON sr.user_id = u.id
    WHERE sr.month = ?
    ORDER BY u.name
");
$stmt->execute([$currentMonth]);
$records = $stmt->fetchAll();

// Get totals
$stmt = $conn->prepare("SELECT COALESCE(SUM(net_salary), 0) FROM salary_records WHERE month = ?");
$stmt->execute([$currentMonth]);
$totalSalaries = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COALESCE(SUM(net_salary), 0) FROM salary_records WHERE month = ? AND status = 'paid'");
$stmt->execute([$currentMonth]);
$paidSalaries = $stmt->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Salaries</p>
        <p class="text-2xl font-bold text-gray-800"><?= formatCurrency($totalSalaries) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Paid</p>
        <p class="text-2xl font-bold text-green-600"><?= formatCurrency($paidSalaries) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Pending</p>
        <p class="text-2xl font-bold text-orange-600"><?= formatCurrency($totalSalaries - $paidSalaries) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <form method="POST" class="flex gap-2">
            <input type="hidden" name="process_salaries" value="1">
            <input type="month" name="month" value="<?= date('Y-m') ?>" class="flex-1 px-3 py-2 border rounded-lg text-sm">
            <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm">Process</button>
        </form>
    </div>
</div>

<!-- Month Filter -->
<div class="bg-white rounded-xl shadow-md p-4 mb-6">
    <form method="GET" class="flex items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Select Month</label>
            <input type="month" name="month" value="<?= $currentMonth ?>" class="px-4 py-2 border rounded-lg">
        </div>
        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg">View</button>
    </form>
</div>

<!-- Salary Records -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="p-4 border-b">
        <h2 class="font-bold text-gray-800">Salary Records - <?= date('F Y', strtotime($currentMonth . '-01')) ?></h2>
    </div>
    
    <?php if (empty($records)): ?>
    <div class="p-8 text-center text-gray-500">
        No salary records for this month. Click "Process" to generate.
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Employee</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Role</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Basic</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Allowances</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Deductions</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Net Salary</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($records as $record): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($record['name']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($record['department'] ?: '-') ?></p>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= ucfirst(str_replace('_', ' ', $record['role'])) ?></td>
                    <td class="px-6 py-4 text-right"><?= formatCurrency($record['basic_salary']) ?></td>
                    <td class="px-6 py-4 text-right text-green-600">+<?= formatCurrency($record['allowances']) ?></td>
                    <td class="px-6 py-4 text-right text-red-600">-<?= formatCurrency($record['deductions']) ?></td>
                    <td class="px-6 py-4 text-right font-semibold"><?= formatCurrency($record['net_salary']) ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $record['status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                            <?= ucfirst($record['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($record['status'] === 'pending'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="mark_paid" value="1">
                            <input type="hidden" name="record_id" value="<?= $record['id'] ?>">
                            <button type="submit" class="text-green-600 hover:underline text-sm">Mark Paid</button>
                        </form>
                        <?php else: ?>
                        <span class="text-sm text-gray-500"><?= $record['payment_date'] ? formatDate($record['payment_date']) : '-' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="5" class="px-6 py-4 font-bold">Total</td>
                    <td class="px-6 py-4 text-right font-bold"><?= formatCurrency($totalSalaries) ?></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

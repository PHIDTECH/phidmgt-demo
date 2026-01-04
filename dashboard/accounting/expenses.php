<?php
$pageTitle = 'Expenses';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle new expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $description = sanitize($_POST['description']);
    $amount = floatval($_POST['amount']);
    $category = sanitize($_POST['category']);
    $date = $_POST['date'];
    
    $stmt = $conn->prepare("INSERT INTO expenses (description, amount, category, date, recorded_by, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    if ($stmt->execute([$description, $amount, $category, $date, $_SESSION['user_id']])) {
        $success = 'Expense recorded successfully';
    }
}

// Handle approval
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_expense'])) {
    if (hasRole([ROLE_CEO, ROLE_MANAGER])) {
        $expenseId = intval($_POST['expense_id']);
        $action = $_POST['action'];
        
        $stmt = $conn->prepare("UPDATE expenses SET status = ?, approved_by = ? WHERE id = ?");
        $stmt->execute([$action, $_SESSION['user_id'], $expenseId]);
        $success = 'Expense ' . $action;
    }
}

// Date filter
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-t');

// Get expenses
$stmt = $conn->prepare("
    SELECT e.*, u.name as recorded_by_name, a.name as approved_by_name
    FROM expenses e
    LEFT JOIN users u ON e.recorded_by = u.id
    LEFT JOIN users a ON e.approved_by = a.id
    WHERE e.date BETWEEN ? AND ?
    ORDER BY e.date DESC
");
$stmt->execute([$startDate, $endDate]);
$expenses = $stmt->fetchAll();

// Get totals
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE date BETWEEN ? AND ? AND status = 'approved'");
$stmt->execute([$startDate, $endDate]);
$totalExpenses = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE date BETWEEN ? AND ? AND status = 'pending'");
$stmt->execute([$startDate, $endDate]);
$pendingExpenses = $stmt->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Approved Expenses</p>
        <p class="text-3xl font-bold text-red-600"><?= formatCurrency($totalExpenses) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Pending Approval</p>
        <p class="text-3xl font-bold text-yellow-600"><?= formatCurrency($pendingExpenses) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <button onclick="document.getElementById('expenseModal').classList.remove('hidden'); document.getElementById('expenseModal').classList.add('flex');" 
                class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
            <i class="fas fa-plus mr-2"></i>Add Expense
        </button>
    </div>
</div>

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

<!-- Expenses Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recorded By</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($expenses as $exp): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm"><?= formatDate($exp['date']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($exp['description']) ?></td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($exp['category'] ?: '-') ?></td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($exp['recorded_by_name']) ?></td>
                    <td class="px-6 py-4">
                        <?php
                        $statusColors = [
                            'pending' => 'bg-yellow-100 text-yellow-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'rejected' => 'bg-red-100 text-red-800'
                        ];
                        $color = $statusColors[$exp['status']] ?? 'bg-gray-100';
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $color ?>">
                            <?= ucfirst($exp['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right font-semibold text-red-600"><?= formatCurrency($exp['amount']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($exp['status'] === 'pending' && hasRole([ROLE_CEO, ROLE_MANAGER])): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="approve_expense" value="1">
                            <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                            <button type="submit" name="action" value="approved" class="text-green-600 hover:underline text-sm mr-2">Approve</button>
                            <button type="submit" name="action" value="rejected" class="text-red-600 hover:underline text-sm">Reject</button>
                        </form>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Expense Modal -->
<div id="expenseModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4">Record Expense</h3>
        <form method="POST">
            <input type="hidden" name="add_expense" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                    <input type="text" name="description" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (TZS) *</label>
                    <input type="number" name="amount" required min="0" step="0.01" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Select...</option>
                        <option value="Office Supplies">Office Supplies</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Transport">Transport</option>
                        <option value="Equipment">Equipment</option>
                        <option value="Software">Software</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                    <input type="date" name="date" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2 border rounded-lg">
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="document.getElementById('expenseModal').classList.add('hidden'); document.getElementById('expenseModal').classList.remove('flex');" 
                        class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Save</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

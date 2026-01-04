<?php
$pageTitle = 'Petty Cash';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';

// Get current balance
$stmt = $conn->query("SELECT COALESCE(balance, 0) FROM petty_cash ORDER BY id DESC LIMIT 1");
$currentBalance = $stmt->fetchColumn() ?: 0;

// Handle transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_transaction'])) {
    $description = sanitize($_POST['description']);
    $type = sanitize($_POST['type']);
    $amount = floatval($_POST['amount']);
    $date = $_POST['date'];
    
    $newBalance = $type === 'deposit' ? $currentBalance + $amount : $currentBalance - $amount;
    
    if ($type === 'withdrawal' && $amount > $currentBalance) {
        $error = 'Insufficient balance for withdrawal';
    } else {
        $stmt = $conn->prepare("INSERT INTO petty_cash (description, type, amount, balance, date, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$description, $type, $amount, $newBalance, $date, $_SESSION['user_id']])) {
            $currentBalance = $newBalance;
            $success = 'Transaction recorded successfully';
        }
    }
}

// Date filter
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-t');

// Get transactions
$stmt = $conn->prepare("
    SELECT pc.*, u.name as recorded_by_name
    FROM petty_cash pc
    LEFT JOIN users u ON pc.recorded_by = u.id
    WHERE pc.date BETWEEN ? AND ?
    ORDER BY pc.date DESC, pc.id DESC
");
$stmt->execute([$startDate, $endDate]);
$transactions = $stmt->fetchAll();

// Get period totals
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM petty_cash WHERE type = 'deposit' AND date BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$totalDeposits = $stmt->fetchColumn();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM petty_cash WHERE type = 'withdrawal' AND date BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$totalWithdrawals = $stmt->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<?php if (isset($error)): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Current Balance</p>
        <p class="text-3xl font-bold text-primary"><?= formatCurrency($currentBalance) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Period Deposits</p>
        <p class="text-2xl font-bold text-green-600"><?= formatCurrency($totalDeposits) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Period Withdrawals</p>
        <p class="text-2xl font-bold text-red-600"><?= formatCurrency($totalWithdrawals) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <button onclick="document.getElementById('transactionModal').classList.remove('hidden'); document.getElementById('transactionModal').classList.add('flex');" 
                class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
            <i class="fas fa-plus mr-2"></i>New Transaction
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

<!-- Transactions Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Recorded By</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($transactions as $trans): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm"><?= formatDate($trans['date']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($trans['description']) ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $trans['type'] === 'deposit' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= ucfirst($trans['type']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($trans['recorded_by_name']) ?></td>
                    <td class="px-6 py-4 text-right font-semibold <?= $trans['type'] === 'deposit' ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $trans['type'] === 'deposit' ? '+' : '-' ?><?= formatCurrency($trans['amount']) ?>
                    </td>
                    <td class="px-6 py-4 text-right font-semibold"><?= formatCurrency($trans['balance']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Transaction Modal -->
<div id="transactionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4">New Transaction</h3>
        <form method="POST">
            <input type="hidden" name="add_transaction" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type *</label>
                    <select name="type" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="deposit">Deposit</option>
                        <option value="withdrawal">Withdrawal</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                    <input type="text" name="description" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Amount (TZS) *</label>
                    <input type="number" name="amount" required min="0" step="0.01" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                    <input type="date" name="date" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2 border rounded-lg">
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="document.getElementById('transactionModal').classList.add('hidden'); document.getElementById('transactionModal').classList.remove('flex');" 
                        class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Save</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

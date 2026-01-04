<?php
$pageTitle = 'Liabilities';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';

// Handle new liability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_liability'])) {
    $description = sanitize($_POST['description']);
    $amount = floatval($_POST['amount']);
    $creditor = sanitize($_POST['creditor']);
    $dueDate = $_POST['due_date'] ?: null;
    $category = sanitize($_POST['category']);
    
    $stmt = $conn->prepare("INSERT INTO liabilities (description, amount, creditor, due_date, category) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$description, $amount, $creditor, $dueDate, $category])) {
        $success = 'Liability added successfully';
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $id = intval($_POST['liability_id']);
    $status = sanitize($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE liabilities SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    $success = 'Status updated';
}

// Get liabilities
$stmt = $conn->query("SELECT * FROM liabilities ORDER BY due_date ASC, created_at DESC");
$liabilities = $stmt->fetchAll();

// Get totals
$stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) FROM liabilities WHERE status = 'active'");
$totalActive = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COALESCE(SUM(amount), 0) FROM liabilities WHERE status = 'overdue'");
$totalOverdue = $stmt->fetchColumn();

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
        <p class="text-gray-500 text-sm">Active Liabilities</p>
        <p class="text-2xl font-bold text-red-600"><?= formatCurrency($totalActive) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Overdue</p>
        <p class="text-2xl font-bold text-orange-600"><?= formatCurrency($totalOverdue) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <button onclick="document.getElementById('liabilityModal').classList.remove('hidden'); document.getElementById('liabilityModal').classList.add('flex');" 
                class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
            <i class="fas fa-plus mr-2"></i>Add Liability
        </button>
    </div>
</div>

<!-- Liabilities Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Creditor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($liabilities as $liability): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4"><?= htmlspecialchars($liability['description']) ?></td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($liability['creditor'] ?: '-') ?></td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($liability['category'] ?: '-') ?></td>
                    <td class="px-6 py-4 text-sm <?= $liability['due_date'] && strtotime($liability['due_date']) < time() && $liability['status'] !== 'paid' ? 'text-red-600 font-semibold' : '' ?>">
                        <?= $liability['due_date'] ? formatDate($liability['due_date']) : '-' ?>
                    </td>
                    <td class="px-6 py-4 text-right font-semibold text-red-600"><?= formatCurrency($liability['amount']) ?></td>
                    <td class="px-6 py-4">
                        <?php
                        $statusColors = [
                            'active' => 'bg-blue-100 text-blue-800',
                            'paid' => 'bg-green-100 text-green-800',
                            'overdue' => 'bg-red-100 text-red-800'
                        ];
                        $color = $statusColors[$liability['status']] ?? 'bg-gray-100';
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $color ?>">
                            <?= ucfirst($liability['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php if ($liability['status'] !== 'paid'): ?>
                        <form method="POST" class="inline">
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" name="liability_id" value="<?= $liability['id'] ?>">
                            <button type="submit" name="status" value="paid" class="text-green-600 hover:underline text-sm">Mark Paid</button>
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

<!-- Add Liability Modal -->
<div id="liabilityModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-bold mb-4">Add Liability</h3>
        <form method="POST">
            <input type="hidden" name="add_liability" value="1">
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">Creditor</label>
                    <input type="text" name="creditor" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Due Date</label>
                    <input type="date" name="due_date" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Select...</option>
                        <option value="Loan">Loan</option>
                        <option value="Accounts Payable">Accounts Payable</option>
                        <option value="Rent">Rent</option>
                        <option value="Utilities">Utilities</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="document.getElementById('liabilityModal').classList.add('hidden'); document.getElementById('liabilityModal').classList.remove('flex');" 
                        class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Save</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

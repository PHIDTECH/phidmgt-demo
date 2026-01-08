<?php
$pageTitle = 'Revenue';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Handle delete revenue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_revenue'])) {
    $revenueId = intval($_POST['revenue_id']);
    try {
        $stmt = $conn->prepare("DELETE FROM revenue WHERE id = ?");
        $stmt->execute([$revenueId]);
        $success = 'Revenue record deleted successfully';
    } catch (PDOException $e) {
        $error = 'Failed to delete revenue record';
    }
}

// Handle add revenue
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_revenue'])) {
    $customerId = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
    $description = sanitize($_POST['description']);
    $amount = floatval($_POST['amount']);
    $category = sanitize($_POST['category']);
    $paymentMethod = sanitize($_POST['payment_method']);
    $transactionRef = sanitize($_POST['transaction_reference']);
    $date = sanitize($_POST['date']);
    
    try {
        $stmt = $conn->prepare("
            INSERT INTO revenue (customer_id, description, amount, category, payment_method, transaction_reference, date, recorded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$customerId, $description, $amount, $category, $paymentMethod, $transactionRef, $date, $_SESSION['user_id']]);
        $success = 'Revenue recorded successfully';
    } catch (PDOException $e) {
        $error = 'Failed to record revenue: ' . $e->getMessage();
    }
}

// Date filter
$startDate = $_GET['start'] ?? date('Y-m-01');
$endDate = $_GET['end'] ?? date('Y-m-t');

// Get revenue from revenue table
$stmt = $conn->prepare("
    SELECT r.id, r.date, r.description, r.amount, r.category, r.payment_method, r.transaction_reference,
           c.name as customer_name, c.email as customer_email,
           'revenue' as source
    FROM revenue r
    LEFT JOIN users c ON r.customer_id = c.id
    WHERE r.date BETWEEN ? AND ?
    ORDER BY r.date DESC
");
$stmt->execute([$startDate, $endDate]);
$revenueTableRecords = $stmt->fetchAll();

// Get customer payments (paid_amount from users table)
$stmt = $conn->query("
    SELECT u.id as customer_id, u.name as customer_name, u.email as customer_email,
           u.paid_amount as amount, u.created_at as date,
           'Customer Payment' as description, 'Customer Payment' as category,
           NULL as payment_method, NULL as transaction_reference,
           'customer' as source
    FROM users u
    WHERE u.role = 'customer' AND u.paid_amount > 0
    ORDER BY u.created_at DESC
");
$customerPayments = $stmt->fetchAll();

// Combine and sort by date
$revenues = array_merge($revenueTableRecords, $customerPayments);
usort($revenues, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});

// Get customers for dropdown
$stmt = $conn->query("SELECT id, name, email FROM users WHERE role = 'customer' AND status = 'active' ORDER BY name");
$customers = $stmt->fetchAll();

// Get totals - combine revenue table + customer payments
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) FROM revenue WHERE date BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$revenueTableTotal = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COALESCE(SUM(paid_amount), 0) FROM users WHERE role = 'customer'");
$customerPaymentsTotal = $stmt->fetchColumn();

$totalRevenue = $revenueTableTotal + $customerPaymentsTotal;

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

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
    <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
</div>
<?php endif; ?>

<!-- Date Filter -->
<div class="bg-white rounded-xl shadow-md p-4 mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
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
    <button onclick="openAddRevenueModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
        <i class="fas fa-plus mr-2"></i>Add Revenue
    </button>
    </div>
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment Method</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($revenues as $rev): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm"><?= formatDate($rev['date']) ?></td>
                    <td class="px-6 py-4"><?= htmlspecialchars($rev['description']) ?></td>
                    <td class="px-6 py-4 text-sm">
                        <?php if ($rev['customer_name']): ?>
                        <p class="font-medium"><?= htmlspecialchars($rev['customer_name']) ?></p>
                        <p class="text-xs text-gray-500"><?= htmlspecialchars($rev['customer_email']) ?></p>
                        <?php else: ?>
                        <span class="text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($rev['category'] ?: '-') ?></td>
                    <td class="px-6 py-4 text-sm">
                        <?php if (!empty($rev['payment_method'])): ?>
                        <span class="px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-800"><?= htmlspecialchars($rev['payment_method']) ?></span>
                        <?php if (!empty($rev['transaction_reference'])): ?>
                        <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($rev['transaction_reference']) ?></p>
                        <?php endif; ?>
                        <?php else: ?>
                        -
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right font-semibold text-green-600"><?= formatCurrency($rev['amount']) ?></td>
                    <td class="px-6 py-4 text-center">
                        <?php 
                        // Check if this is a Selcom payment (protected from deletion)
                        $isSelcomPayment = (
                            (isset($rev['payment_method']) && stripos($rev['payment_method'], 'Selcom') !== false) ||
                            (isset($rev['description']) && stripos($rev['description'], 'Selcom') !== false)
                        );
                        ?>
                        <?php if ($isSelcomPayment): ?>
                        <span class="text-xs text-blue-600 font-medium"><i class="fas fa-shield-alt mr-1"></i>From Selcom</span>
                        <?php elseif (isset($rev['source']) && $rev['source'] === 'revenue'): ?>
                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this revenue record?');">
                            <input type="hidden" name="delete_revenue" value="1">
                            <input type="hidden" name="revenue_id" value="<?= $rev['id'] ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm"><i class="fas fa-trash"></i></button>
                        </form>
                        <?php elseif (isset($rev['source']) && $rev['source'] === 'customer'): ?>
                        <span class="text-xs text-gray-400">From Customers</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="6" class="px-6 py-4 font-bold">Total</td>
                    <td class="px-6 py-4 text-right font-bold text-green-600"><?= formatCurrency($totalRevenue) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Add Revenue Modal -->
<div id="addRevenueModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg my-8 mx-4">
        <h3 class="text-lg font-bold mb-4"><i class="fas fa-plus-circle text-green-600 mr-2"></i>Add Revenue</h3>
        <form method="POST">
            <input type="hidden" name="add_revenue" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Customer</label>
                    <select name="customer_id" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">-- Select Customer (Optional) --</option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?= $customer['id'] ?>"><?= htmlspecialchars($customer['name']) ?> (<?= $customer['email'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description *</label>
                    <input type="text" name="description" required class="w-full px-4 py-2 border rounded-lg" placeholder="e.g., Website development payment">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount (TZS) *</label>
                        <input type="number" name="amount" required step="0.01" min="0" class="w-full px-4 py-2 border rounded-lg" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                        <input type="date" name="date" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">Select...</option>
                            <option value="Web Development">Web Development</option>
                            <option value="Mobile App">Mobile App</option>
                            <option value="Graphics Design">Graphics Design</option>
                            <option value="Digital Marketing">Digital Marketing</option>
                            <option value="Hosting & Domain">Hosting & Domain</option>
                            <option value="Consultation">Consultation</option>
                            <option value="Maintenance">Maintenance</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <select name="payment_method" class="w-full px-4 py-2 border rounded-lg">
                            <option value="">Select...</option>
                            <option value="Selcom">Selcom</option>
                            <option value="M-Pesa">M-Pesa</option>
                            <option value="Tigo Pesa">Tigo Pesa</option>
                            <option value="Airtel Money">Airtel Money</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                            <option value="Cash">Cash</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Transaction Reference</label>
                    <input type="text" name="transaction_reference" class="w-full px-4 py-2 border rounded-lg" placeholder="e.g., Selcom transaction ID">
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeAddRevenueModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"><i class="fas fa-save mr-2"></i>Save Revenue</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddRevenueModal() {
    document.getElementById('addRevenueModal').classList.remove('hidden');
    document.getElementById('addRevenueModal').classList.add('flex');
}

function closeAddRevenueModal() {
    document.getElementById('addRevenueModal').classList.add('hidden');
    document.getElementById('addRevenueModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

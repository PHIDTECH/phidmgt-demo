<?php
$pageTitle = 'Customers';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

// Ensure payment columns exist
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query("SELECT total_amount FROM users LIMIT 1");
} catch (PDOException $e) {
    $conn->query("ALTER TABLE users ADD COLUMN total_amount DECIMAL(15,2) DEFAULT 0");
    $conn->query("ALTER TABLE users ADD COLUMN paid_amount DECIMAL(15,2) DEFAULT 0");
    $conn->query("ALTER TABLE users ADD COLUMN payment_due_date DATE DEFAULT NULL");
}

// Ensure payment_notes column exists
try {
    $conn->query("SELECT payment_notes FROM users LIMIT 1");
} catch (PDOException $e) {
    $conn->query("ALTER TABLE users ADD COLUMN payment_notes TEXT DEFAULT NULL");
}

$success = '';
$error = '';

// Handle create customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_customer'])) {
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $totalAmount = floatval($_POST['total_amount'] ?? 0);
    $paidAmount = floatval($_POST['paid_amount'] ?? 0);
    $paymentDueDate = sanitize($_POST['payment_due_date'] ?? '');
    $paymentNotes = sanitize($_POST['payment_notes'] ?? '');
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    
    if ($stmt->fetch()) {
        $error = 'Email already exists';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, role, status, total_amount, paid_amount, payment_due_date, payment_notes) VALUES (?, ?, ?, ?, 'customer', 'active', ?, ?, ?, ?)");
        
        if ($stmt->execute([$name, $email, $hashedPassword, $phone, $totalAmount, $paidAmount, $paymentDueDate ?: null, $paymentNotes ?: null])) {
            $success = 'Customer created successfully!';
        } else {
            $error = 'Failed to create customer';
        }
    }
}

// Handle edit customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_customer'])) {
    $customerId = intval($_POST['customer_id']);
    $name = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $totalAmount = floatval($_POST['total_amount'] ?? 0);
    $paidAmount = floatval($_POST['paid_amount'] ?? 0);
    $paymentDueDate = sanitize($_POST['payment_due_date'] ?? '');
    $paymentNotes = sanitize($_POST['payment_notes'] ?? '');
    
    // Check if email exists for another user
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$email, $customerId]);
    
    if ($stmt->fetch()) {
        $error = 'Email already exists for another user';
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, total_amount = ?, paid_amount = ?, payment_due_date = ?, payment_notes = ? WHERE id = ? AND role = 'customer'");
        if ($stmt->execute([$name, $email, $phone, $totalAmount, $paidAmount, $paymentDueDate ?: null, $paymentNotes ?: null, $customerId])) {
            $success = 'Customer updated successfully!';
        } else {
            $error = 'Failed to update customer';
        }
    }
}

// Handle status toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $customerId = intval($_POST['customer_id']);
    $newStatus = $_POST['new_status'];
    
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'customer'");
    if ($stmt->execute([$newStatus, $customerId])) {
        $success = 'Customer status updated!';
    }
}

// Get customers with their stats
$stmt = $conn->query("
    SELECT u.*, 
           COUNT(DISTINCT p.id) as total_projects,
           COALESCE(u.paid_amount, 0) as total_spent,
           (COALESCE(u.total_amount, 0) - COALESCE(u.paid_amount, 0)) as outstanding_amount
    FROM users u
    LEFT JOIN projects p ON u.id = p.customer_id
    WHERE u.role = 'customer'
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$customers = $stmt->fetchAll();

// Calculate total revenue from all customer payments
$totalRevenue = array_sum(array_column($customers, 'total_spent'));

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
</div>
<?php endif; ?>

<!-- Header with Search and Create Button -->
<div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
    <div class="w-full md:w-96">
        <div class="relative">
            <input type="text" id="customerSearch" placeholder="Search customers by name, email, phone..." 
                   class="w-full pl-10 pr-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary"
                   onkeyup="searchCustomers()">
            <i class="fas fa-search absolute left-3 top-3 text-gray-400"></i>
        </div>
    </div>
    <button onclick="openCreateModal()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center">
        <i class="fas fa-plus mr-2"></i>Create Customer
    </button>
</div>

<!-- Summary -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Customers</p>
        <p class="text-3xl font-bold text-gray-800"><?= count($customers) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Active Customers</p>
        <p class="text-3xl font-bold text-green-600"><?= count(array_filter($customers, fn($c) => $c['status'] === 'active')) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Revenue (Paid)</p>
        <p class="text-2xl font-bold text-primary"><?= formatCurrency($totalRevenue) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Outstanding Amount</p>
        <p class="text-2xl font-bold text-red-600"><?= formatCurrency(array_sum(array_column($customers, 'outstanding_amount'))) ?></p>
    </div>
</div>

<!-- Customers Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full" id="customersTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Contact</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Paid</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Outstanding</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Due Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
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
                    <td class="px-6 py-4 text-right font-medium"><?= formatCurrency($customer['total_amount'] ?? 0) ?></td>
                    <td class="px-6 py-4 text-right font-semibold text-green-600"><?= formatCurrency($customer['paid_amount'] ?? 0) ?></td>
                    <td class="px-6 py-4 text-right font-semibold <?= ($customer['outstanding_amount'] ?? 0) > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= formatCurrency($customer['outstanding_amount'] ?? 0) ?></td>
                    <td class="px-6 py-4 text-sm"><?= $customer['payment_due_date'] ? formatDate($customer['payment_due_date']) : '-' ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $customer['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= ucfirst($customer['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 space-x-2">
                        <button onclick='openEditModal(<?= json_encode($customer) ?>)' class="text-blue-600 hover:text-blue-800 text-sm">Edit</button>
                        <form method="POST" class="inline">
                            <input type="hidden" name="toggle_status" value="1">
                            <input type="hidden" name="customer_id" value="<?= $customer['id'] ?>">
                            <input type="hidden" name="new_status" value="<?= $customer['status'] === 'active' ? 'inactive' : 'active' ?>">
                            <button type="submit" class="text-sm <?= $customer['status'] === 'active' ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' ?>">
                                <?= $customer['status'] === 'active' ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Create Customer Modal -->
<div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg my-8 mx-4">
        <h3 class="text-lg font-bold mb-4"><i class="fas fa-user-plus text-green-600 mr-2"></i>Create New Customer</h3>
        <form method="POST">
            <input type="hidden" name="create_customer" value="1">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                    <input type="text" name="name" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="Enter full name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" name="phone" value="255" pattern="255[0-9]{9}" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="255XXXXXXXXX">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                <input type="email" name="email" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="customer@example.com">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                <input type="password" name="password" required minlength="6" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="Min 6 characters">
            </div>
            <hr class="my-4">
            <p class="text-sm font-semibold text-gray-600 mb-3"><i class="fas fa-money-bill-wave mr-2"></i>Payment Information</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Total Amount (TZS)</label>
                    <input type="number" name="total_amount" step="0.01" min="0" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Amount Paid (TZS)</label>
                    <input type="number" name="paid_amount" step="0.01" min="0" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="0.00">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Due Date</label>
                <input type="date" name="payment_due_date" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-tasks mr-1"></i>Payment Tasks/Notes</label>
                <textarea name="payment_notes" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="e.g. Customer paid 500,000 TZS on 05/01/2026 via M-Pesa..."></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeCreateModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700"><i class="fas fa-save mr-2"></i>Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Customer Modal -->
<div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg my-8 mx-4">
        <h3 class="text-lg font-bold mb-4"><i class="fas fa-edit text-blue-600 mr-2"></i>Edit Customer</h3>
        <form method="POST">
            <input type="hidden" name="edit_customer" value="1">
            <input type="hidden" name="customer_id" id="editCustomerId">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Full Name *</label>
                    <input type="text" name="name" id="editName" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="Enter full name">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                    <input type="tel" name="phone" id="editPhone" pattern="255[0-9]{9}" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="255XXXXXXXXX">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                <input type="email" name="email" id="editEmail" required class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="customer@example.com">
            </div>
            <hr class="my-4">
            <p class="text-sm font-semibold text-gray-600 mb-3"><i class="fas fa-money-bill-wave mr-2"></i>Payment Information</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Total Amount (TZS)</label>
                    <input type="number" name="total_amount" id="editTotalAmount" step="0.01" min="0" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="0.00">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Amount Paid (TZS)</label>
                    <input type="number" name="paid_amount" id="editPaidAmount" step="0.01" min="0" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="0.00">
                </div>
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Due Date</label>
                <input type="date" name="payment_due_date" id="editPaymentDueDate" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
            </div>
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2"><i class="fas fa-tasks mr-1"></i>Payment Tasks/Notes</label>
                <textarea name="payment_notes" id="editPaymentNotes" rows="3" class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary" placeholder="e.g. Customer paid 500,000 TZS on 05/01/2026 via M-Pesa..."></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"><i class="fas fa-save mr-2"></i>Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
    document.getElementById('createModal').classList.add('flex');
}

function closeCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
    document.getElementById('createModal').classList.remove('flex');
}

function openEditModal(customer) {
    document.getElementById('editCustomerId').value = customer.id;
    document.getElementById('editName').value = customer.name;
    document.getElementById('editEmail').value = customer.email;
    document.getElementById('editPhone').value = customer.phone || '';
    document.getElementById('editTotalAmount').value = customer.total_amount || 0;
    document.getElementById('editPaidAmount').value = customer.paid_amount || 0;
    document.getElementById('editPaymentDueDate').value = customer.payment_due_date || '';
    document.getElementById('editPaymentNotes').value = customer.payment_notes || '';
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModal').classList.add('flex');
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModal').classList.remove('flex');
}

function searchCustomers() {
    const searchTerm = document.getElementById('customerSearch').value.toLowerCase();
    const table = document.getElementById('customersTable');
    const rows = table.querySelectorAll('tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

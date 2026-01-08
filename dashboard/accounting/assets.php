<?php
$pageTitle = 'Assets';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_ACCOUNTANT])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

// Ensure asset_type column exists
try {
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->query("SELECT asset_type FROM assets LIMIT 1");
} catch (PDOException $e) {
    $conn->query("ALTER TABLE assets ADD COLUMN asset_type VARCHAR(50) DEFAULT 'fixed'");
}

$success = '';

// Handle new asset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_asset'])) {
    $assetType = sanitize($_POST['asset_type']);
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $purchaseValue = floatval($_POST['purchase_value']);
    $currentValue = floatval($_POST['current_value']);
    $purchaseDate = $_POST['purchase_date'] ?: null;
    $category = sanitize($_POST['category']);
    $depreciationRate = floatval($_POST['depreciation_rate'] ?? 0);
    
    $stmt = $conn->prepare("INSERT INTO assets (asset_type, name, description, purchase_value, current_value, purchase_date, category, depreciation_rate) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$assetType, $name, $description, $purchaseValue, $currentValue, $purchaseDate, $category, $depreciationRate])) {
        $success = 'Asset added successfully';
    }
}

// Get assets
$stmt = $conn->query("SELECT * FROM assets ORDER BY purchase_date DESC");
$assets = $stmt->fetchAll();

// Get totals
$stmt = $conn->query("SELECT COALESCE(SUM(purchase_value), 0) as purchase_total, COALESCE(SUM(current_value), 0) as current_total FROM assets WHERE status = 'active'");
$totals = $stmt->fetch();

// Get totals by type
$stmt = $conn->query("SELECT COALESCE(SUM(current_value), 0) FROM assets WHERE status = 'active' AND asset_type = 'fixed'");
$fixedAssetsTotal = $stmt->fetchColumn();

$stmt = $conn->query("SELECT COALESCE(SUM(current_value), 0) FROM assets WHERE status = 'active' AND asset_type = 'current'");
$currentAssetsTotal = $stmt->fetchColumn();

require_once __DIR__ . '/../../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Fixed Assets</p>
        <p class="text-2xl font-bold text-gray-800"><?= formatCurrency($fixedAssetsTotal) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Current Assets (Cash)</p>
        <p class="text-2xl font-bold text-green-600"><?= formatCurrency($currentAssetsTotal) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Purchase Value</p>
        <p class="text-2xl font-bold text-gray-800"><?= formatCurrency($totals['purchase_total']) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <p class="text-gray-500 text-sm">Total Current Value</p>
        <p class="text-2xl font-bold text-primary"><?= formatCurrency($totals['current_total']) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-6">
        <button onclick="document.getElementById('assetModal').classList.remove('hidden'); document.getElementById('assetModal').classList.add('flex');" 
                class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
            <i class="fas fa-plus mr-2"></i>Add Asset
        </button>
    </div>
</div>

<!-- Assets Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Asset</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Current Value</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($assets as $asset): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($asset['name']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars($asset['description']) ?></p>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= ($asset['asset_type'] ?? 'fixed') === 'current' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' ?>">
                            <?= ($asset['asset_type'] ?? 'fixed') === 'current' ? 'Current' : 'Fixed' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($asset['category'] ?: '-') ?></td>
                    <td class="px-6 py-4 text-right font-semibold"><?= formatCurrency($asset['current_value']) ?></td>
                    <td class="px-6 py-4">
                        <?php
                        $statusColors = [
                            'active' => 'bg-green-100 text-green-800',
                            'disposed' => 'bg-red-100 text-red-800',
                            'maintenance' => 'bg-yellow-100 text-yellow-800'
                        ];
                        $color = $statusColors[$asset['status']] ?? 'bg-gray-100';
                        ?>
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $color ?>">
                            <?= ucfirst($asset['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Asset Modal -->
<div id="assetModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 overflow-y-auto">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg my-8 mx-4">
        <h3 class="text-lg font-bold mb-4"><i class="fas fa-boxes text-primary mr-2"></i>Add Asset</h3>
        <form method="POST">
            <input type="hidden" name="add_asset" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Asset Type *</label>
                    <select name="asset_type" id="assetType" required onchange="toggleAssetFields()" class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Select Type...</option>
                        <option value="fixed">Fixed Asset (Equipment, Furniture, etc.)</option>
                        <option value="current">Current Asset (Cash, Bank, Mobile Money)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Asset Name *</label>
                    <input type="text" name="name" required class="w-full px-4 py-2 border rounded-lg" placeholder="e.g. Dell Laptop, Cash at Bank">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2 border rounded-lg" placeholder="Optional details"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                    <select name="category" id="categorySelect" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Select Category...</option>
                        <optgroup label="Fixed Assets" id="fixedCategories">
                            <option value="Computer Equipment">Computer Equipment</option>
                            <option value="Office Furniture">Office Furniture</option>
                            <option value="Vehicles">Vehicles</option>
                            <option value="Software">Software</option>
                            <option value="Machinery">Machinery</option>
                            <option value="Other Fixed">Other Fixed Asset</option>
                        </optgroup>
                        <optgroup label="Current Assets (Cash)" id="currentCategories">
                            <option value="Cash at Bank">Cash at Bank</option>
                            <option value="Cash on Hand">Cash on Hand</option>
                            <option value="Cash on Mobile">Cash on Mobile (M-Pesa, etc.)</option>
                            <option value="Petty Cash">Petty Cash</option>
                            <option value="Other Cash">Other Cash/Liquid Asset</option>
                        </optgroup>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" id="purchaseLabel">Purchase Value *</label>
                        <input type="number" name="purchase_value" required min="0" step="0.01" class="w-full px-4 py-2 border rounded-lg" placeholder="0.00">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" id="currentLabel">Current Value *</label>
                        <input type="number" name="current_value" required min="0" step="0.01" class="w-full px-4 py-2 border rounded-lg" placeholder="0.00">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4" id="fixedAssetFields">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Purchase Date</label>
                        <input type="date" name="purchase_date" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Depreciation Rate (%)</label>
                        <input type="number" name="depreciation_rate" min="0" max="100" step="0.01" class="w-full px-4 py-2 border rounded-lg" placeholder="0">
                    </div>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeAssetModal()" class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg"><i class="fas fa-save mr-2"></i>Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function closeAssetModal() {
    document.getElementById('assetModal').classList.add('hidden');
    document.getElementById('assetModal').classList.remove('flex');
}

function toggleAssetFields() {
    const assetType = document.getElementById('assetType').value;
    const fixedFields = document.getElementById('fixedAssetFields');
    const purchaseLabel = document.getElementById('purchaseLabel');
    const currentLabel = document.getElementById('currentLabel');
    
    if (assetType === 'current') {
        fixedFields.style.display = 'none';
        purchaseLabel.textContent = 'Initial Amount *';
        currentLabel.textContent = 'Current Balance *';
    } else {
        fixedFields.style.display = 'grid';
        purchaseLabel.textContent = 'Purchase Value *';
        currentLabel.textContent = 'Current Value *';
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

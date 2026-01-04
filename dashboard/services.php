<?php
$pageTitle = 'Services Management';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER])) {
    redirect('/dashboard/');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';

// Handle new service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $category = sanitize($_POST['category']);
    $basePrice = floatval($_POST['base_price']);
    $duration = intval($_POST['duration_days']);
    $features = sanitize($_POST['features']);
    
    $stmt = $conn->prepare("INSERT INTO services (name, description, category, base_price, duration_days, features) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$name, $description, $category, $basePrice, $duration, $features])) {
        $success = 'Service added successfully';
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $serviceId = intval($_POST['service_id']);
    $status = sanitize($_POST['status']);
    
    $stmt = $conn->prepare("UPDATE services SET status = ? WHERE id = ?");
    $stmt->execute([$status, $serviceId]);
    $success = 'Service status updated';
}

// Get services
$stmt = $conn->query("SELECT * FROM services ORDER BY category, name");
$services = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($success): ?>
<div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
    <i class="fas fa-check-circle mr-2"></i><?= $success ?>
</div>
<?php endif; ?>

<!-- Header -->
<div class="flex justify-between items-center mb-6">
    <p class="text-gray-600"><?= count($services) ?> services</p>
    <button onclick="document.getElementById('serviceModal').classList.remove('hidden'); document.getElementById('serviceModal').classList.add('flex');" 
            class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition">
        <i class="fas fa-plus mr-1"></i>Add Service
    </button>
</div>

<!-- Services Table -->
<div class="bg-white rounded-xl shadow-md overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Duration</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($services as $service): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <p class="font-medium text-gray-800"><?= htmlspecialchars($service['name']) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars(substr($service['description'], 0, 60)) ?>...</p>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars($service['category']) ?></td>
                    <td class="px-6 py-4 text-right font-semibold"><?= formatCurrency($service['base_price']) ?></td>
                    <td class="px-6 py-4 text-sm"><?= $service['duration_days'] ?> days</td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded-full text-xs font-medium <?= $service['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= ucfirst($service['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <form method="POST" class="inline">
                            <input type="hidden" name="update_status" value="1">
                            <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                            <?php if ($service['status'] === 'active'): ?>
                            <button type="submit" name="status" value="inactive" class="text-red-600 hover:underline text-sm">Deactivate</button>
                            <?php else: ?>
                            <button type="submit" name="status" value="active" class="text-green-600 hover:underline text-sm">Activate</button>
                            <?php endif; ?>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Service Modal -->
<div id="serviceModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg">
        <h3 class="text-lg font-bold mb-4">Add Service</h3>
        <form method="POST">
            <input type="hidden" name="add_service" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Service Name *</label>
                    <input type="text" name="name" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full px-4 py-2 border rounded-lg"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category *</label>
                        <select name="category" required class="w-full px-4 py-2 border rounded-lg">
                            <option value="Web Development">Web Development</option>
                            <option value="Mobile Development">Mobile Development</option>
                            <option value="Graphics Design">Graphics Design</option>
                            <option value="Digital Marketing">Digital Marketing</option>
                            <option value="Consultation">Consultation</option>
                            <option value="Hosting">Hosting</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration (days) *</label>
                        <input type="number" name="duration_days" required min="1" value="7" class="w-full px-4 py-2 border rounded-lg">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Base Price (TZS) *</label>
                    <input type="number" name="base_price" required min="0" step="0.01" class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Features (comma-separated)</label>
                    <input type="text" name="features" class="w-full px-4 py-2 border rounded-lg" placeholder="Feature 1, Feature 2, Feature 3">
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="document.getElementById('serviceModal').classList.add('hidden'); document.getElementById('serviceModal').classList.remove('flex');" 
                        class="px-4 py-2 bg-gray-200 rounded-lg">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg">Add Service</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

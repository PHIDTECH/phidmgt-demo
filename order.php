<?php
$pageTitle = 'Place Order';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/notifications.php';

$db = new Database();
$conn = $db->getConnection();
$notifier = new NotificationService($conn);

$error = '';
$success = '';
$service = null;

// Get service if specified
if (isset($_GET['service'])) {
    $stmt = $conn->prepare("SELECT * FROM services WHERE id = ? AND status = 'active'");
    $stmt->execute([$_GET['service']]);
    $service = $stmt->fetch();
}

// Get all services for dropdown
$stmt = $conn->query("SELECT * FROM services WHERE status = 'active' ORDER BY name");
$allServices = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isLoggedIn()) {
        $error = 'Please login or register to place an order';
    } else {
        $serviceId = intval($_POST['service_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $requirements = sanitize($_POST['requirements'] ?? '');
        
        // Get service details
        $stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
        $stmt->execute([$serviceId]);
        $selectedService = $stmt->fetch();
        
        if (!$selectedService) {
            $error = 'Invalid service selected';
        } elseif (empty($title)) {
            $error = 'Please enter a project title';
        } else {
            $reference = generateReference('ORD');
            $totalAmount = $selectedService['base_price'];
            $dueDate = date('Y-m-d', strtotime('+' . $selectedService['duration_days'] . ' days'));
            
            $stmt = $conn->prepare("
                INSERT INTO projects (reference, customer_id, service_id, title, description, requirements, total_amount, due_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([
                $reference,
                $_SESSION['user_id'],
                $serviceId,
                $title,
                $description,
                $requirements,
                $totalAmount,
                $dueDate
            ])) {
                $projectId = $conn->lastInsertId();
                
                // Get full project data
                $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
                $stmt->execute([$projectId]);
                $project = $stmt->fetch();
                
                // Get customer data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $customer = $stmt->fetch();
                
                // Send notification
                $notifier->notifyNewOrder($project, $customer);
                
                // Redirect to payment
                header("Location: " . SITE_URL . "/payment.php?order=" . $reference);
                exit;
            } else {
                $error = 'Failed to create order. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<section class="py-16 bg-gray-50 min-h-screen">
    <div class="max-w-4xl mx-auto px-4">
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="bg-gradient-to-r from-primary to-secondary text-white p-6">
                <h1 class="text-2xl font-bold">Place Your Order</h1>
                <p class="text-blue-100">Complete the form below to get started</p>
            </div>
            
            <div class="p-6">
                <?php if ($error): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
                    <?php if (!isLoggedIn()): ?>
                    <div class="mt-2">
                        <a href="<?= SITE_URL ?>/auth/login.php" class="text-red-800 underline">Login</a> or 
                        <a href="<?= SITE_URL ?>/auth/register.php" class="text-red-800 underline">Register</a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Service *</label>
                        <select name="service_id" id="serviceSelect" required 
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent">
                            <option value="">Choose a service...</option>
                            <?php foreach ($allServices as $s): ?>
                            <option value="<?= $s['id'] ?>" 
                                    data-price="<?= $s['base_price'] ?>" 
                                    data-duration="<?= $s['duration_days'] ?>"
                                    <?= ($service && $service['id'] == $s['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($s['name']) ?> - <?= formatCurrency($s['base_price']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div id="serviceDetails" class="bg-blue-50 p-4 rounded-lg <?= $service ? '' : 'hidden' ?>">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-semibold text-gray-800">Price: <span id="servicePrice"><?= $service ? formatCurrency($service['base_price']) : '' ?></span></p>
                                <p class="text-sm text-gray-600">Estimated delivery: <span id="serviceDuration"><?= $service ? $service['duration_days'] : '' ?></span> days</p>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project Title *</label>
                        <input type="text" name="title" required 
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                               placeholder="e.g., Company Website Redesign" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Project Description</label>
                        <textarea name="description" rows="4" 
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                  placeholder="Describe your project in detail..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Special Requirements</label>
                        <textarea name="requirements" rows="3" 
                                  class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                  placeholder="Any specific requirements or features you need..."><?= htmlspecialchars($_POST['requirements'] ?? '') ?></textarea>
                    </div>
                    
                    <div class="bg-gray-50 -mx-6 -mb-6 px-6 py-4 mt-6">
                        <button type="submit" class="w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
                            <i class="fas fa-shopping-cart mr-2"></i>Proceed to Payment
                        </button>
                        <p class="text-sm text-gray-500 text-center mt-2">
                            You will be redirected to our secure payment page
                        </p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
document.getElementById('serviceSelect').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const details = document.getElementById('serviceDetails');
    
    if (this.value) {
        document.getElementById('servicePrice').textContent = 'TZS ' + parseFloat(selected.dataset.price).toLocaleString();
        document.getElementById('serviceDuration').textContent = selected.dataset.duration;
        details.classList.remove('hidden');
    } else {
        details.classList.add('hidden');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

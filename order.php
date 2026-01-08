<?php
// Error handling - enable for debugging
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

$pageTitle = 'Place Order';

try {
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/selcom.php';
} catch (Exception $e) {
    die('Config error: ' . $e->getMessage());
}

try {
    $db = new Database();
    $conn = $db->getConnection();
} catch (Exception $e) {
    die('Database connection error');
}

// Load notifications if available
$notifier = null;
if (file_exists(__DIR__ . '/includes/notifications.php')) {
    require_once __DIR__ . '/includes/notifications.php';
    $notifier = new NotificationService($conn);
}

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
    $selectedServiceIds = $_POST['service_ids'] ?? [];
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $requirements = sanitize($_POST['requirements'] ?? '');
    
    // If not logged in, store customer info for Selcom payment (customer created AFTER successful payment)
    $customerName = '';
    $customerEmail = '';
    $customerPhone = '';
    
    if (!isLoggedIn()) {
        $customerName = sanitize($_POST['customer_name'] ?? '');
        $customerEmail = sanitize($_POST['customer_email'] ?? '');
        $customerPhone = sanitize($_POST['customer_phone'] ?? '');
        $customerPassword = $_POST['customer_password'] ?? '';
        
        if (empty($customerName) || empty($customerEmail)) {
            $error = 'Please enter your name and email address';
        } elseif (empty($customerPhone)) {
            $error = 'Please enter your phone number';
        } elseif (empty($customerPassword) || strlen($customerPassword) < 6) {
            $error = 'Please enter a password (minimum 6 characters)';
        } else {
            // Check if email already exists - if so, they should login
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$customerEmail]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                $error = 'An account with this email already exists. Please <a href="' . SITE_URL . '/auth/login.php" class="underline">login</a> first.';
            } else {
                // Store customer info in session - email is used as username
                $_SESSION['pending_customer'] = [
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $customerPhone,
                    'password' => password_hash($customerPassword, PASSWORD_DEFAULT)
                ];
            }
        }
    }
    
    // Proceed with order if logged in OR has pending customer info
    if ((isLoggedIn() || isset($_SESSION['pending_customer'])) && empty($error)) {
        
        // Validate services selected
        if (empty($selectedServiceIds)) {
            $error = 'Please select at least one service';
        } elseif (empty($title)) {
            $error = 'Please enter a project title';
        } else {
            // Get all selected services
            $placeholders = implode(',', array_fill(0, count($selectedServiceIds), '?'));
            $stmt = $conn->prepare("SELECT * FROM services WHERE id IN ($placeholders) AND status = 'active'");
            $stmt->execute($selectedServiceIds);
            $selectedServices = $stmt->fetchAll();
            
            if (count($selectedServices) === 0) {
                $error = 'Invalid services selected';
            } else {
                // Calculate totals
                $totalAmount = 0;
                $maxDuration = 0;
                $serviceNames = [];
                
                foreach ($selectedServices as $svc) {
                    $totalAmount += $svc['base_price'];
                    $maxDuration = max($maxDuration, $svc['duration_days']);
                    $serviceNames[] = $svc['name'];
                }
                
                $serviceName = implode(' + ', $serviceNames);
                $reference = generateReference('ORD');
                $dueDate = date('Y-m-d', strtotime('+' . $maxDuration . ' days'));
            
                // Get customer ID (if logged in) or use NULL for pending orders
                $customerId = isLoggedIn() ? $_SESSION['user_id'] : null;
                
                // Store order info in session for payment page
                $_SESSION['pending_order'] = [
                    'reference' => $reference,
                    'service_ids' => $selectedServiceIds,
                    'service_name' => $serviceName,
                    'title' => $title,
                    'description' => $description,
                    'requirements' => $requirements,
                    'total_amount' => $totalAmount,
                    'due_date' => $dueDate
                ];
                
                // Try inserting project (customer_id can be NULL for pending orders)
                try {
                    $stmt = $conn->prepare("
                        INSERT INTO projects (reference, customer_id, service_id, service_name, title, description, requirements, total_amount, due_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $reference,
                        $customerId,
                        $selectedServices[0]['id'], // Primary service ID
                        $serviceName,
                        $title,
                        $description,
                        $requirements,
                        $totalAmount,
                        $dueDate
                    ]);
                } catch (PDOException $e) {
                    // Fallback: try without service_id if foreign key issue
                    $stmt = $conn->prepare("
                        INSERT INTO projects (reference, customer_id, service_name, title, description, requirements, total_amount, due_date, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $stmt->execute([
                        $reference,
                        $customerId,
                        $serviceName,
                        $title,
                        $description,
                        $requirements,
                        $totalAmount,
                        $dueDate
                    ]);
                }
                
                if ($conn->lastInsertId()) {
                    $projectId = $conn->lastInsertId();
                    $_SESSION['pending_order']['project_id'] = $projectId;
                    
                    // Get customer info
                    $buyerName = $customerName ?: ($_SESSION['user_name'] ?? 'Customer');
                    $buyerEmail = $customerEmail ?: ($_SESSION['user_email'] ?? '');
                    $buyerPhone = $customerPhone ?: ($_SESSION['user_phone'] ?? '');
                    
                    // Format phone for Selcom (must start with 255)
                    $buyerPhone = formatPhoneForSelcom($buyerPhone);
                    
                    try {
                        // Store pending payment info for webhook
                        $stmt = $conn->prepare("
                            INSERT INTO pending_payments (order_reference, project_id, customer_email, customer_name, customer_phone, amount, password_hash, status, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                            ON DUPLICATE KEY UPDATE project_id = VALUES(project_id), amount = VALUES(amount)
                        ");
                        $passwordHash = isset($_SESSION['pending_customer']) ? $_SESSION['pending_customer']['password'] : null;
                        $stmt->execute([$reference, $projectId, $buyerEmail, $buyerName, $buyerPhone, $totalAmount, $passwordHash]);
                    } catch (PDOException $e) {
                        // Table might not exist - continue anyway
                        error_log("Pending payments error: " . $e->getMessage());
                    }
                    
                    // Create Selcom checkout order
                    $selcomData = [
                        'order_id' => $reference,
                        'name' => $buyerName,
                        'email' => $buyerEmail,
                        'phone' => $buyerPhone,
                        'amount' => $totalAmount,
                        'description' => $serviceName . ' - ' . $title
                    ];
                    
                    $selcomResult = createSelcomCheckoutOrderFull($selcomData);
                    
                    // Check if Selcom returned success
                    $response = $selcomResult['response'] ?? [];
                    if (isset($response['result']) && $response['result'] === 'SUCCESS' && !empty($response['data'][0]['payment_gateway_url'])) {
                        // Decode the base64 URL and redirect
                        $gatewayUrl = base64_decode($response['data'][0]['payment_gateway_url']);
                        header("Location: " . $gatewayUrl);
                        exit;
                    } else {
                        // Selcom failed - show error but order is created
                        $errorMsg = $response['message'] ?? 'Payment gateway error';
                        $error = 'Order created but payment gateway error: ' . $errorMsg . '. Please try again from your dashboard.';
                    }
                } else {
                    $error = 'Failed to create order. Please try again.';
                }
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
                    <?php if (!isLoggedIn()): ?>
                    <!-- Customer Information Section -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
                        <h3 class="font-semibold text-gray-800 mb-3"><i class="fas fa-user mr-2"></i>Your Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                <input type="text" name="customer_name" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="Enter your full name" value="<?= htmlspecialchars($_POST['customer_name'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                                <input type="tel" name="customer_phone" required
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="0712 345 678" value="<?= htmlspecialchars($_POST['customer_phone'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                            <input type="email" name="customer_email" required 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                   placeholder="your@email.com" value="<?= htmlspecialchars($_POST['customer_email'] ?? '') ?>">
                        </div>
                        
                        <!-- Account Password -->
                        <div class="mt-4 pt-4 border-t border-blue-200">
                            <h4 class="font-medium text-gray-800 mb-2"><i class="fas fa-lock mr-2"></i>Create Your Account Password</h4>
                            <p class="text-xs text-gray-500 mb-3">Your email will be your username for login</p>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
                                <input type="password" name="customer_password" required minlength="6"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                                       placeholder="Create a password (min 6 characters)">
                            </div>
                        </div>
                        
                        <p class="text-xs text-gray-500 mt-3"><i class="fas fa-info-circle mr-1"></i>Your account will be created after successful payment. Already have an account? <a href="<?= SITE_URL ?>/auth/login.php" class="text-primary underline">Login here</a></p>
                    </div>
                    <?php else: ?>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                        <p class="text-green-800"><i class="fas fa-check-circle mr-2"></i>Ordering as: <strong><?= htmlspecialchars($_SESSION['user_name']) ?></strong> (<?= htmlspecialchars($_SESSION['user_email']) ?>)</p>
                    </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Select Services * <span class="text-xs text-gray-500">(Select one or more)</span></label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-80 overflow-y-auto border border-gray-200 rounded-lg p-4 bg-gray-50">
                            <?php foreach ($allServices as $s): ?>
                            <label class="service-item flex items-start p-3 bg-white rounded-lg border border-gray-200 hover:border-primary hover:shadow-sm cursor-pointer transition">
                                <input type="checkbox" name="service_ids[]" value="<?= $s['id'] ?>" 
                                       data-price="<?= $s['base_price'] ?>" 
                                       data-duration="<?= $s['duration_days'] ?>"
                                       data-name="<?= htmlspecialchars($s['name']) ?>"
                                       class="service-checkbox mt-1 h-4 w-4 text-primary border-gray-300 rounded focus:ring-primary"
                                       <?= ($service && $service['id'] == $s['id']) ? 'checked' : '' ?>
                                       <?= (isset($_POST['service_ids']) && in_array($s['id'], $_POST['service_ids'])) ? 'checked' : '' ?>>
                                <div class="ml-3 flex-1">
                                    <p class="font-medium text-gray-800"><?= htmlspecialchars($s['name']) ?></p>
                                    <p class="text-sm text-primary font-semibold"><?= formatCurrency($s['base_price']) ?></p>
                                    <p class="text-xs text-gray-500"><?= $s['duration_days'] ?> days delivery</p>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <p class="text-xs text-gray-500 mt-2"><i class="fas fa-info-circle mr-1"></i>Select multiple services to bundle them together</p>
                    </div>
                    
                    <div id="serviceDetails" class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-lg border border-blue-200 hidden">
                        <h4 class="font-semibold text-gray-800 mb-2"><i class="fas fa-shopping-cart mr-2 text-primary"></i>Order Summary</h4>
                        <div id="selectedServicesList" class="space-y-1 mb-3 text-sm"></div>
                        <div class="border-t border-blue-200 pt-3 flex justify-between items-center">
                            <div>
                                <p class="text-lg font-bold text-gray-800">Total: <span id="servicePrice" class="text-primary">TZS 0</span></p>
                                <p class="text-sm text-gray-600">Estimated delivery: <span id="serviceDuration">0</span> days</p>
                            </div>
                            <div class="text-right">
                                <span id="serviceCount" class="inline-block bg-primary text-white text-sm px-3 py-1 rounded-full">0 services</span>
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
// Handle multiple service selection
function updateOrderSummary() {
    const checkboxes = document.querySelectorAll('.service-checkbox:checked');
    const details = document.getElementById('serviceDetails');
    const servicesList = document.getElementById('selectedServicesList');
    const servicePrice = document.getElementById('servicePrice');
    const serviceDuration = document.getElementById('serviceDuration');
    const serviceCount = document.getElementById('serviceCount');
    
    let totalPrice = 0;
    let maxDuration = 0;
    let servicesHtml = '';
    
    checkboxes.forEach(function(cb) {
        const price = parseFloat(cb.dataset.price);
        const duration = parseInt(cb.dataset.duration);
        const name = cb.dataset.name;
        
        totalPrice += price;
        maxDuration = Math.max(maxDuration, duration);
        servicesHtml += '<div class="flex justify-between text-gray-700"><span><i class="fas fa-check text-green-500 mr-2"></i>' + name + '</span><span>TZS ' + price.toLocaleString() + '</span></div>';
    });
    
    if (checkboxes.length > 0) {
        servicesList.innerHTML = servicesHtml;
        servicePrice.textContent = 'TZS ' + totalPrice.toLocaleString();
        serviceDuration.textContent = maxDuration;
        serviceCount.textContent = checkboxes.length + ' service' + (checkboxes.length > 1 ? 's' : '');
        details.classList.remove('hidden');
    } else {
        details.classList.add('hidden');
    }
}

// Add event listeners to all checkboxes
document.querySelectorAll('.service-checkbox').forEach(function(checkbox) {
    checkbox.addEventListener('change', updateOrderSummary);
});

// Highlight selected items
document.querySelectorAll('.service-item').forEach(function(item) {
    const checkbox = item.querySelector('.service-checkbox');
    
    // Initial state
    if (checkbox.checked) {
        item.classList.add('border-primary', 'bg-blue-50');
    }
    
    checkbox.addEventListener('change', function() {
        if (this.checked) {
            item.classList.add('border-primary', 'bg-blue-50');
        } else {
            item.classList.remove('border-primary', 'bg-blue-50');
        }
    });
});

// Initialize on page load
updateOrderSummary();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

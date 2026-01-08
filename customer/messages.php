<?php
$pageTitle = 'My Messages';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/notifications.php';

if (!isLoggedIn() || $_SESSION['user_role'] !== ROLE_CUSTOMER) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();

// Ensure messages table exists
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT PRIMARY KEY AUTO_INCREMENT,
        sender_id INT,
        recipient_id INT NOT NULL,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('notification', 'task', 'payment', 'project', 'leave', 'general', 'chat') DEFAULT 'general',
        reference_type VARCHAR(50),
        reference_id INT,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_recipient (recipient_id),
        INDEX idx_is_read (is_read)
    )");
} catch (PDOException $e) {
    // Table exists
}

$notifier = new NotificationService($conn);
$success = '';

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipientId = intval($_POST['recipient_id']);
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    $returnUrl = $_POST['return_url'] ?? '';
    
    $notifier->send($recipientId, $subject, $message, 'chat', null, null, $_SESSION['user_id']);
    $success = 'Message sent successfully!';
    
    // If return URL provided, redirect back
    if (!empty($returnUrl)) {
        $_SESSION['flash_success'] = $success;
        header("Location: " . $returnUrl);
        exit;
    }
}

// Mark as read
if (isset($_GET['read'])) {
    $notifier->markAsRead(intval($_GET['read']), $_SESSION['user_id']);
    redirect('/customer/messages.php');
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $notifier->markAllAsRead($_SESSION['user_id']);
    redirect('/customer/messages.php');
}

// Get staff list for compose
$stmt = $conn->query("SELECT id, name, role FROM users WHERE role != 'customer' AND status = 'active' ORDER BY name");
$staffList = $stmt->fetchAll();

$messages = $notifier->getMessages($_SESSION['user_id'], 100, false, $_SESSION['user_role']);
$unreadCount = $notifier->getUnreadCount($_SESSION['user_id']);

// Check for flash message
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <?php if ($success): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6 alert-auto-hide">
        <i class="fas fa-check-circle mr-2"></i><?= $success ?>
    </div>
    <?php endif; ?>
    
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Messages</h1>
            <p class="text-gray-600"><?= $unreadCount ?> unread message(s)</p>
        </div>
        <div class="flex gap-2">
            <?php if ($unreadCount > 0): ?>
            <a href="?mark_all_read=1" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-300">
                <i class="fas fa-check-double mr-1"></i>Mark All Read
            </a>
            <?php endif; ?>
            <button onclick="openComposeModal()" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary">
                <i class="fas fa-pen mr-1"></i>Compose
            </button>
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-md overflow-hidden">
        <?php if (empty($messages)): ?>
        <div class="p-12 text-center">
            <div class="w-16 h-16 bg-gray-100 text-gray-400 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-envelope-open text-2xl"></i>
            </div>
            <p class="text-gray-500">No messages yet</p>
        </div>
        <?php else: ?>
        <div class="divide-y">
            <?php foreach ($messages as $msg): ?>
            <div class="p-4 hover:bg-gray-50 <?= !$msg['is_read'] ? 'bg-blue-50' : '' ?>">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center mb-1">
                            <?php if (!$msg['is_read']): ?>
                            <span class="w-2 h-2 bg-primary rounded-full mr-2"></span>
                            <?php endif; ?>
                            <h3 class="font-semibold text-gray-800"><?= htmlspecialchars($msg['subject']) ?></h3>
                            <span class="ml-2 px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded">
                                <?= ucfirst($msg['type']) ?>
                            </span>
                        </div>
                        <p class="text-gray-600 text-sm"><?= htmlspecialchars($msg['message']) ?></p>
                        <p class="text-gray-400 text-xs mt-2">
                            <?= $msg['sender_name'] ? 'From: ' . htmlspecialchars($msg['sender_name']) . ' • ' : '' ?>
                            <?= formatDateTime($msg['created_at']) ?>
                        </p>
                    </div>
                    <?php if (!$msg['is_read']): ?>
                    <a href="?read=<?= $msg['id'] ?>" class="text-gray-400 hover:text-primary ml-4" title="Mark as read">
                        <i class="fas fa-check"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <div class="mt-6">
        <a href="<?= SITE_URL ?>/customer/dashboard.php" class="text-primary hover:underline">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>
</div>

<!-- Compose Modal -->
<div id="composeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl p-6 w-full max-w-lg mx-4">
        <h3 class="text-lg font-bold mb-4"><i class="fas fa-pen text-primary mr-2"></i>New Message</h3>
        <form method="POST">
            <input type="hidden" name="send_message" value="1">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To *</label>
                    <select name="recipient_id" required class="w-full px-4 py-2 border rounded-lg">
                        <option value="">Select recipient...</option>
                        <?php foreach ($staffList as $staff): ?>
                        <option value="<?= $staff['id'] ?>"><?= htmlspecialchars($staff['name']) ?> (<?= ucfirst(str_replace('_', ' ', $staff['role'])) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Subject *</label>
                    <input type="text" name="subject" required class="w-full px-4 py-2 border rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Message *</label>
                    <textarea name="message" required rows="4" class="w-full px-4 py-2 border rounded-lg" placeholder="Type your message..."></textarea>
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button type="button" onclick="closeComposeModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-secondary">
                    <i class="fas fa-paper-plane mr-1"></i>Send
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openComposeModal() {
    document.getElementById('composeModal').classList.remove('hidden');
    document.getElementById('composeModal').classList.add('flex');
}

function closeComposeModal() {
    document.getElementById('composeModal').classList.add('hidden');
    document.getElementById('composeModal').classList.remove('flex');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

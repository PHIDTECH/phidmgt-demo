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
$notifier = new NotificationService($conn);

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

$messages = $notifier->getMessages($_SESSION['user_id'], 100);
$unreadCount = $notifier->getUnreadCount($_SESSION['user_id']);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Messages</h1>
            <p class="text-gray-600"><?= $unreadCount ?> unread message(s)</p>
        </div>
        <?php if ($unreadCount > 0): ?>
        <a href="?mark_all_read=1" class="text-primary hover:underline">
            <i class="fas fa-check-double mr-1"></i>Mark all as read
        </a>
        <?php endif; ?>
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

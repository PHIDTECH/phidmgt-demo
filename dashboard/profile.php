<?php
$pageTitle = 'My Profile';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isLoggedIn()) {
    redirect('/auth/login.php');
}

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    
    $stmt = $conn->prepare("UPDATE users SET name = ?, phone = ? WHERE id = ?");
    if ($stmt->execute([$name, $phone, $_SESSION['user_id']])) {
        $_SESSION['user_name'] = $name;
        $user['name'] = $name;
        $user['phone'] = $phone;
        $success = 'Profile updated successfully';
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (!password_verify($currentPassword, $user['password'])) {
        $error = 'Current password is incorrect';
    } elseif (strlen($newPassword) < 6) {
        $error = 'New password must be at least 6 characters';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'New passwords do not match';
    } else {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        if ($stmt->execute([$hashedPassword, $_SESSION['user_id']])) {
            $success = 'Password changed successfully';
        }
    }
}

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

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Profile Card -->
    <div class="bg-white rounded-xl shadow-md p-6">
        <div class="text-center">
            <div class="w-24 h-24 bg-primary text-white rounded-full flex items-center justify-center text-3xl font-bold mx-auto mb-4">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
            <h2 class="text-xl font-bold text-gray-800"><?= htmlspecialchars($user['name']) ?></h2>
            <p class="text-gray-500"><?= htmlspecialchars($user['email']) ?></p>
            <span class="inline-block mt-2 px-3 py-1 bg-primary/10 text-primary rounded-full text-sm font-medium">
                <?= ucfirst(str_replace('_', ' ', $user['role'])) ?>
            </span>
        </div>
        
        <div class="mt-6 pt-6 border-t space-y-3">
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Department</span>
                <span class="font-medium"><?= htmlspecialchars($user['department'] ?: '-') ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Phone</span>
                <span class="font-medium"><?= htmlspecialchars($user['phone'] ?: '-') ?></span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-500">Joined</span>
                <span class="font-medium"><?= formatDate($user['created_at']) ?></span>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Edit Profile</h3>
            <form method="POST">
                <input type="hidden" name="update_profile" value="1">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                        <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled 
                               class="w-full px-4 py-2 border rounded-lg bg-gray-100">
                        <p class="text-xs text-gray-500 mt-1">Email cannot be changed</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                <button type="submit" class="mt-4 bg-primary text-white px-6 py-2 rounded-lg hover:bg-secondary transition">
                    Update Profile
                </button>
            </form>
        </div>
        
        <!-- Change Password -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Change Password</h3>
            <form method="POST">
                <input type="hidden" name="change_password" value="1">
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <input type="password" name="current_password" required 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                        <input type="password" name="new_password" required minlength="6"
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input type="password" name="confirm_password" required 
                               class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary">
                    </div>
                </div>
                <button type="submit" class="mt-4 bg-orange-500 text-white px-6 py-2 rounded-lg hover:bg-orange-600 transition">
                    Change Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

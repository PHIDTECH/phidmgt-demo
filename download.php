<?php
/**
 * Secure Digital Product Download Handler
 * Validates download token and serves file securely
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->getConnection();

$token = $_GET['token'] ?? '';
$error = '';

if (empty($token)) {
    $error = 'Invalid download link';
} else {
    // Find the order by download token
    $stmt = $conn->prepare("
        SELECT do.*, dp.name as product_name, dp.file_path, dp.file_type, dp.file_size
        FROM digital_orders do
        JOIN digital_products dp ON do.product_id = dp.id
        WHERE do.download_token = ? AND do.payment_status = 'completed'
    ");
    $stmt->execute([$token]);
    $order = $stmt->fetch();
    
    if (!$order) {
        $error = 'Invalid or expired download link. Please check your email for the correct link.';
    } elseif (strtotime($order['download_expiry']) < time()) {
        $error = 'This download link has expired. Please contact support for assistance.';
    } elseif ($order['download_count'] >= $order['max_downloads']) {
        $error = 'Maximum download limit reached. Please contact support if you need additional downloads.';
    } else {
        // Check if file exists
        $filePath = __DIR__ . '/' . $order['file_path'];
        
        if (!file_exists($filePath)) {
            $error = 'File not found. Please contact support.';
        } else {
            // Increment download count
            $stmt = $conn->prepare("UPDATE digital_orders SET download_count = download_count + 1 WHERE id = ?");
            $stmt->execute([$order['id']]);
            
            // Also increment product download count
            $stmt = $conn->prepare("UPDATE digital_products SET download_count = download_count + 1 WHERE id = ?");
            $stmt->execute([$order['product_id']]);
            
            // Get file info
            $fileName = basename($order['file_path']);
            $fileSize = filesize($filePath);
            
            // Determine content type
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $contentTypes = [
                'pdf' => 'application/pdf',
                'zip' => 'application/zip',
                'rar' => 'application/x-rar-compressed',
                'mp4' => 'video/mp4',
                'mp3' => 'audio/mpeg',
                'png' => 'image/png',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif' => 'image/gif',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'psd' => 'application/octet-stream',
                'ai' => 'application/postscript'
            ];
            
            $contentType = $contentTypes[$extension] ?? 'application/octet-stream';
            
            // Clear any output buffers
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            // Set headers for download
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            // Output file
            readfile($filePath);
            exit;
        }
    }
}

// If we reach here, show error page
$pageTitle = 'Download';
require_once __DIR__ . '/includes/header.php';
?>

<section class="py-16 bg-gray-50 min-h-screen">
    <div class="max-w-xl mx-auto px-4">
        <div class="bg-white rounded-xl shadow-lg p-8 text-center">
            <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-exclamation-triangle text-4xl text-red-500"></i>
            </div>
            
            <h1 class="text-2xl font-bold text-gray-800 mb-4">Download Error</h1>
            
            <p class="text-gray-600 mb-6"><?= htmlspecialchars($error) ?></p>
            
            <div class="space-y-3">
                <a href="<?= SITE_URL ?>/software.php" class="block w-full bg-primary text-white py-3 rounded-lg font-semibold hover:bg-secondary transition">
                    <i class="fas fa-store mr-2"></i>Browse Products
                </a>
                <a href="<?= SITE_URL ?>/contact.php" class="block w-full border border-gray-300 text-gray-700 py-3 rounded-lg font-semibold hover:bg-gray-50 transition">
                    <i class="fas fa-headset mr-2"></i>Contact Support
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

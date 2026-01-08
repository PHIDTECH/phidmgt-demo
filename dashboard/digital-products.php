<?php
/**
 * Digital Products Management (Admin/Manager only)
 */
$pageTitle = 'Digital Products';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Check access
if (!isLoggedIn() || !hasRole([ROLE_CEO, ROLE_MANAGER, ROLE_SUPERADMIN])) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}

$db = new Database();
$conn = $db->getConnection();

$success = '';
$error = '';
$tableExists = true;

// Check if digital_products table exists, create if not
try {
    $stmt = $conn->query("SHOW TABLES LIKE 'digital_products'");
    if ($stmt->rowCount() == 0) {
        // Create tables
        $conn->exec("
            CREATE TABLE IF NOT EXISTS digital_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) UNIQUE,
                description TEXT,
                short_description VARCHAR(500),
                price DECIMAL(15,2) NOT NULL DEFAULT 0,
                category ENUM('applications', 'templates', 'ebooks', 'graphics', 'video-courses', 'audio') DEFAULT 'applications',
                file_path VARCHAR(500) NOT NULL,
                file_type VARCHAR(50),
                file_size VARCHAR(50),
                thumbnail VARCHAR(500),
                preview_images TEXT,
                demo_url VARCHAR(500),
                version VARCHAR(50),
                requirements TEXT,
                features TEXT,
                download_count INT DEFAULT 0,
                status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_category (category),
                INDEX idx_status (status),
                INDEX idx_slug (slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $conn->exec("
            CREATE TABLE IF NOT EXISTS digital_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_reference VARCHAR(50) UNIQUE NOT NULL,
                product_id INT NOT NULL,
                customer_id INT,
                customer_name VARCHAR(255),
                customer_email VARCHAR(255) NOT NULL,
                customer_phone VARCHAR(50),
                amount DECIMAL(15,2) NOT NULL,
                payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
                payment_method VARCHAR(50) DEFAULT 'Selcom',
                transaction_id VARCHAR(255),
                download_token VARCHAR(255) UNIQUE,
                download_count INT DEFAULT 0,
                max_downloads INT DEFAULT 5,
                download_expiry DATETIME,
                password_hash VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_order_ref (order_reference),
                INDEX idx_product (product_id),
                INDEX idx_customer (customer_id),
                INDEX idx_token (download_token),
                INDEX idx_payment_status (payment_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        $success = 'Digital products tables created successfully!';
    }
} catch (PDOException $e) {
    $error = 'Database setup error: ' . $e->getMessage();
    $tableExists = false;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add' || $action === 'edit') {
        $id = $_POST['id'] ?? null;
        $name = sanitize($_POST['name'] ?? '');
        $slug = sanitize($_POST['slug'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $shortDescription = sanitize($_POST['short_description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $category = $_POST['category'] ?? 'applications';
        $fileType = sanitize($_POST['file_type'] ?? '');
        $fileSize = sanitize($_POST['file_size'] ?? '');
        $version = sanitize($_POST['version'] ?? '');
        $requirements = sanitize($_POST['requirements'] ?? '');
        $features = sanitize($_POST['features'] ?? '');
        $status = $_POST['status'] ?? 'active';
        
        // Generate slug if empty
        if (empty($slug)) {
            $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        }
        
        // Handle file upload
        $filePath = $_POST['existing_file'] ?? '';
        if (isset($_FILES['product_file']) && $_FILES['product_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/digital/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['product_file']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['product_file']['tmp_name'], $targetPath)) {
                $filePath = 'uploads/digital/' . $fileName;
            } else {
                $error = 'Failed to upload file';
            }
        }
        
        // Handle thumbnail upload
        $thumbnail = $_POST['existing_thumbnail'] ?? '';
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/thumbnails/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $_FILES['thumbnail']['name']);
            $targetPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $targetPath)) {
                $thumbnail = 'uploads/thumbnails/' . $fileName;
            }
        }
        
        if (empty($error)) {
            if (empty($name) || empty($price)) {
                $error = 'Name and price are required';
            } elseif ($action === 'add' && empty($filePath)) {
                $error = 'Please upload a product file';
            } else {
                try {
                    if ($action === 'add') {
                        $stmt = $conn->prepare("
                            INSERT INTO digital_products (name, slug, description, short_description, price, category, file_path, file_type, file_size, thumbnail, version, requirements, features, status, created_by)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$name, $slug, $description, $shortDescription, $price, $category, $filePath, $fileType, $fileSize, $thumbnail, $version, $requirements, $features, $status, $_SESSION['user_id']]);
                        $success = 'Product added successfully';
                    } else {
                        $stmt = $conn->prepare("
                            UPDATE digital_products SET name = ?, slug = ?, description = ?, short_description = ?, price = ?, category = ?, file_path = ?, file_type = ?, file_size = ?, thumbnail = ?, version = ?, requirements = ?, features = ?, status = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([$name, $slug, $description, $shortDescription, $price, $category, $filePath, $fileType, $fileSize, $thumbnail, $version, $requirements, $features, $status, $id]);
                        $success = 'Product updated successfully';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? 0;
        $stmt = $conn->prepare("DELETE FROM digital_products WHERE id = ?");
        $stmt->execute([$id]);
        $success = 'Product deleted successfully';
    }
}

// Get all products
$products = [];
$stats = ['total' => 0, 'revenue' => 0];

if ($tableExists) {
    try {
        $stmt = $conn->query("SELECT * FROM digital_products ORDER BY created_at DESC");
        $products = $stmt->fetchAll();
        
        // Get digital orders stats
        $stmt = $conn->query("SELECT COUNT(*) as total, SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as revenue FROM digital_orders");
        $stats = $stmt->fetch();
    } catch (PDOException $e) {
        $error = 'Error loading products: ' . $e->getMessage();
    }
}

// Categories
$categories = [
    'applications' => 'Applications',
    'templates' => 'Templates',
    'ebooks' => 'E-Books & PDFs',
    'graphics' => 'Graphics & Assets',
    'video-courses' => 'Video Courses',
    'audio' => 'Audio Files'
];

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-6">
    <div class="flex justify-between items-center mb-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Digital Products</h1>
            <p class="text-gray-600">Manage downloadable software, templates, and digital content</p>
        </div>
        <button onclick="openModal('add')" class="bg-primary text-white px-4 py-2 rounded-lg hover:bg-secondary transition">
            <i class="fas fa-plus mr-2"></i>Add Product
        </button>
    </div>
    
    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-box text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Products</p>
                    <p class="text-2xl font-bold"><?= count($products) ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-green-100 text-green-600 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-shopping-cart text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Total Orders</p>
                    <p class="text-2xl font-bold"><?= $stats['total'] ?? 0 ?></p>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-coins text-xl"></i>
                </div>
                <div>
                    <p class="text-gray-500 text-sm">Revenue</p>
                    <p class="text-2xl font-bold"><?= formatCurrency($stats['revenue'] ?? 0) ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($success): ?>
    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4">
        <i class="fas fa-check-circle mr-2"></i><?= $success ?>
    </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4">
        <i class="fas fa-exclamation-circle mr-2"></i><?= $error ?>
    </div>
    <?php endif; ?>
    
    <!-- Products Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Product</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Price</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Downloads</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($products)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                        <i class="fas fa-box-open text-4xl mb-2"></i>
                        <p>No products yet. Click "Add Product" to create one.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gray-200 rounded flex items-center justify-center mr-3">
                                <?php if ($product['thumbnail']): ?>
                                <img src="<?= SITE_URL ?>/<?= $product['thumbnail'] ?>" class="w-10 h-10 rounded object-cover">
                                <?php else: ?>
                                <i class="fas fa-file text-gray-400"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800"><?= htmlspecialchars($product['name']) ?></p>
                                <p class="text-xs text-gray-500"><?= strtoupper($product['file_type']) ?> • <?= $product['file_size'] ?></p>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-xs">
                            <?= $categories[$product['category']] ?? $product['category'] ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 font-medium"><?= formatCurrency($product['price']) ?></td>
                    <td class="px-6 py-4"><?= $product['download_count'] ?></td>
                    <td class="px-6 py-4">
                        <span class="px-2 py-1 rounded text-xs <?= $product['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                            <?= ucfirst($product['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <button onclick="editProduct(<?= htmlspecialchars(json_encode($product)) ?>)" class="text-blue-600 hover:text-blue-800 mr-2">
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST" class="inline" onsubmit="return confirm('Delete this product?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $product['id'] ?>">
                            <button type="submit" class="text-red-600 hover:text-red-800">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Modal -->
<div id="productModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 overflow-y-auto">
    <div class="min-h-screen px-4 flex items-center justify-center">
        <div class="bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="p-6 border-b">
                <h3 id="modalTitle" class="text-xl font-bold">Add Product</h3>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-6 space-y-4">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="productId">
                <input type="hidden" name="existing_file" id="existingFile">
                <input type="hidden" name="existing_thumbnail" id="existingThumbnail">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product Name *</label>
                        <input type="text" name="name" id="productName" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Price (TZS) *</label>
                        <input type="number" name="price" id="productPrice" required class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="category" id="productCategory" class="w-full px-3 py-2 border rounded-lg">
                            <?php foreach ($categories as $key => $label): ?>
                            <option value="<?= $key ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" id="productStatus" class="w-full px-3 py-2 border rounded-lg">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="draft">Draft</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Short Description</label>
                    <input type="text" name="short_description" id="productShortDesc" class="w-full px-3 py-2 border rounded-lg" maxlength="500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Description</label>
                    <textarea name="description" id="productDescription" rows="3" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">File Type</label>
                        <input type="text" name="file_type" id="productFileType" class="w-full px-3 py-2 border rounded-lg" placeholder="zip, pdf, mp4">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">File Size</label>
                        <input type="text" name="file_size" id="productFileSize" class="w-full px-3 py-2 border rounded-lg" placeholder="15 MB">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Version</label>
                        <input type="text" name="version" id="productVersion" class="w-full px-3 py-2 border rounded-lg" placeholder="1.0">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Features (comma separated)</label>
                    <input type="text" name="features" id="productFeatures" class="w-full px-3 py-2 border rounded-lg" placeholder="Feature 1, Feature 2, Feature 3">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Requirements</label>
                    <textarea name="requirements" id="productRequirements" rows="2" class="w-full px-3 py-2 border rounded-lg"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Product File *</label>
                        <input type="file" name="product_file" id="productFile" class="w-full px-3 py-2 border rounded-lg">
                        <p id="currentFile" class="text-xs text-gray-500 mt-1"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Thumbnail Image</label>
                        <input type="file" name="thumbnail" accept="image/*" class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div class="flex justify-end space-x-3 pt-4 border-t">
                    <button type="button" onclick="closeModal()" class="px-4 py-2 border rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg hover:bg-secondary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openModal(action) {
    document.getElementById('productModal').classList.remove('hidden');
    document.getElementById('formAction').value = action;
    document.getElementById('modalTitle').textContent = action === 'add' ? 'Add Product' : 'Edit Product';
    
    if (action === 'add') {
        document.getElementById('productId').value = '';
        document.getElementById('productName').value = '';
        document.getElementById('productPrice').value = '';
        document.getElementById('productCategory').value = 'applications';
        document.getElementById('productStatus').value = 'active';
        document.getElementById('productShortDesc').value = '';
        document.getElementById('productDescription').value = '';
        document.getElementById('productFileType').value = '';
        document.getElementById('productFileSize').value = '';
        document.getElementById('productVersion').value = '';
        document.getElementById('productFeatures').value = '';
        document.getElementById('productRequirements').value = '';
        document.getElementById('existingFile').value = '';
        document.getElementById('existingThumbnail').value = '';
        document.getElementById('currentFile').textContent = '';
        document.getElementById('productFile').required = true;
    }
}

function closeModal() {
    document.getElementById('productModal').classList.add('hidden');
}

function editProduct(product) {
    openModal('edit');
    document.getElementById('productId').value = product.id;
    document.getElementById('productName').value = product.name;
    document.getElementById('productPrice').value = product.price;
    document.getElementById('productCategory').value = product.category;
    document.getElementById('productStatus').value = product.status;
    document.getElementById('productShortDesc').value = product.short_description || '';
    document.getElementById('productDescription').value = product.description || '';
    document.getElementById('productFileType').value = product.file_type || '';
    document.getElementById('productFileSize').value = product.file_size || '';
    document.getElementById('productVersion').value = product.version || '';
    document.getElementById('productFeatures').value = product.features || '';
    document.getElementById('productRequirements').value = product.requirements || '';
    document.getElementById('existingFile').value = product.file_path || '';
    document.getElementById('existingThumbnail').value = product.thumbnail || '';
    document.getElementById('currentFile').textContent = product.file_path ? 'Current: ' + product.file_path : '';
    document.getElementById('productFile').required = false;
}

// Close modal on outside click
document.getElementById('productModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

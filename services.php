<?php
$pageTitle = 'Services';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get all active services grouped by category
$stmt = $conn->query("SELECT * FROM services WHERE status = 'active' ORDER BY category, base_price ASC");
$services = $stmt->fetchAll();

$grouped = [];
foreach ($services as $service) {
    $grouped[$service['category']][] = $service;
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<section class="bg-gradient-to-br from-primary to-secondary text-white py-16">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <h1 class="text-4xl font-bold mb-4">Our Services</h1>
        <p class="text-xl text-blue-100 max-w-2xl mx-auto">
            Comprehensive digital solutions tailored to your business needs
        </p>
    </div>
</section>

<!-- Services List -->
<section class="py-16">
    <div class="max-w-7xl mx-auto px-4">
        <?php foreach ($grouped as $category => $categoryServices): ?>
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center">
                <i class="fas fa-<?= $category === 'Web Development' ? 'globe' : ($category === 'Mobile Development' ? 'mobile-alt' : ($category === 'Graphics Design' ? 'palette' : ($category === 'Digital Marketing' ? 'chart-line' : 'cog'))) ?> mr-3 text-primary"></i>
                <?= htmlspecialchars($category) ?>
            </h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($categoryServices as $service): ?>
                <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition overflow-hidden">
                    <div class="p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-2"><?= htmlspecialchars($service['name']) ?></h3>
                        <p class="text-gray-600 mb-4"><?= htmlspecialchars($service['description']) ?></p>
                        
                        <?php if ($service['features']): ?>
                        <div class="mb-4">
                            <p class="text-sm font-medium text-gray-700 mb-2">Features:</p>
                            <ul class="text-sm text-gray-600 space-y-1">
                                <?php foreach (explode(',', $service['features']) as $feature): ?>
                                <li><i class="fas fa-check text-green-500 mr-2"></i><?= trim($feature) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center justify-between pt-4 border-t">
                            <div>
                                <span class="text-2xl font-bold text-primary"><?= formatCurrency($service['base_price']) ?></span>
                                <span class="text-sm text-gray-500 block"><?= $service['duration_days'] ?> days delivery</span>
                            </div>
                            <a href="<?= SITE_URL ?>/order.php?service=<?= $service['id'] ?>" 
                               class="bg-primary text-white px-4 py-2 rounded-lg font-semibold hover:bg-secondary transition">
                                Order Now
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Custom Project CTA -->
<section class="py-16 bg-gray-50">
    <div class="max-w-4xl mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold text-gray-800 mb-4">Need a Custom Solution?</h2>
        <p class="text-gray-600 mb-8">
            Don't see exactly what you need? We specialize in custom projects tailored to your specific requirements.
        </p>
        <a href="<?= SITE_URL ?>/contact.php" class="inline-block bg-primary text-white px-8 py-3 rounded-lg font-semibold hover:bg-secondary transition">
            <i class="fas fa-comments mr-2"></i>Request Custom Quote
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

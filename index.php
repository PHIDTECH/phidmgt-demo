<?php
$pageTitle = 'Home';
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

$db = new Database();
$conn = $db->getConnection();

// Get featured services
$stmt = $conn->query("SELECT * FROM services WHERE status = 'active' ORDER BY base_price ASC LIMIT 6");
$services = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="bg-gradient-to-br from-primary to-secondary text-white py-20">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
            <div>
                <h1 class="text-4xl md:text-5xl font-bold mb-6 leading-tight">
                    Transform Your Ideas Into Digital Reality
                </h1>
                <p class="text-xl text-blue-100 mb-8">
                    PhidTech Solutions delivers cutting-edge web development, mobile apps, and IT services 
                    to help your business thrive in the digital age.
                </p>
                <div class="flex flex-wrap gap-4">
                    <a href="<?= SITE_URL ?>/services.php" class="bg-white text-primary px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                        <i class="fas fa-rocket mr-2"></i>Explore Services
                    </a>
                    <a href="<?= SITE_URL ?>/auth/register.php" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-primary transition">
                        <i class="fas fa-user-plus mr-2"></i>Get Started
                    </a>
                </div>
            </div>
            <div class="hidden md:block">
                <div class="bg-white/10 backdrop-blur-sm rounded-2xl p-8">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="bg-white/20 rounded-xl p-6 text-center">
                            <i class="fas fa-code text-4xl mb-3"></i>
                            <h3 class="font-semibold">Web Development</h3>
                        </div>
                        <div class="bg-white/20 rounded-xl p-6 text-center">
                            <i class="fas fa-mobile-alt text-4xl mb-3"></i>
                            <h3 class="font-semibold">Mobile Apps</h3>
                        </div>
                        <div class="bg-white/20 rounded-xl p-6 text-center">
                            <i class="fas fa-paint-brush text-4xl mb-3"></i>
                            <h3 class="font-semibold">Graphics Design</h3>
                        </div>
                        <div class="bg-white/20 rounded-xl p-6 text-center">
                            <i class="fas fa-chart-line text-4xl mb-3"></i>
                            <h3 class="font-semibold">Digital Marketing</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Stats Section -->
<section class="py-12 bg-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
            <div>
                <div class="text-4xl font-bold text-primary">100+</div>
                <div class="text-gray-600">Projects Completed</div>
            </div>
            <div>
                <div class="text-4xl font-bold text-primary">50+</div>
                <div class="text-gray-600">Happy Clients</div>
            </div>
            <div>
                <div class="text-4xl font-bold text-primary">5+</div>
                <div class="text-gray-600">Years Experience</div>
            </div>
            <div>
                <div class="text-4xl font-bold text-primary">24/7</div>
                <div class="text-gray-600">Support</div>
            </div>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-gray-800 mb-4">Our Services</h2>
            <p class="text-gray-600 max-w-2xl mx-auto">
                We offer a comprehensive range of digital services to help your business succeed online.
            </p>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($services as $service): ?>
            <div class="bg-white rounded-xl shadow-md hover:shadow-xl transition p-6">
                <div class="w-14 h-14 bg-primary/10 text-primary rounded-lg flex items-center justify-center mb-4">
                    <i class="fas fa-<?= $service['category'] === 'Web Development' ? 'globe' : ($service['category'] === 'Mobile Development' ? 'mobile-alt' : ($service['category'] === 'Graphics Design' ? 'palette' : 'cog')) ?> text-2xl"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-800 mb-2"><?= htmlspecialchars($service['name']) ?></h3>
                <p class="text-gray-600 mb-4"><?= htmlspecialchars($service['description']) ?></p>
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-bold text-primary"><?= formatCurrency($service['base_price']) ?></span>
                    <a href="<?= SITE_URL ?>/order.php?service=<?= $service['id'] ?>" class="text-primary font-semibold hover:underline">
                        Order Now <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="text-center mt-10">
            <a href="<?= SITE_URL ?>/services.php" class="inline-block bg-primary text-white px-8 py-3 rounded-lg font-semibold hover:bg-secondary transition">
                View All Services
            </a>
        </div>
    </div>
</section>

<!-- Why Choose Us -->
<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 mb-6">Why Choose PhidTech?</h2>
                <div class="space-y-6">
                    <div class="flex items-start">
                        <div class="w-12 h-12 bg-green-100 text-green-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-check-circle text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-semibold text-gray-800">Quality Guaranteed</h3>
                            <p class="text-gray-600">We deliver high-quality solutions that exceed expectations.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-12 h-12 bg-blue-100 text-blue-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-clock text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-semibold text-gray-800">On-Time Delivery</h3>
                            <p class="text-gray-600">We respect deadlines and deliver projects on schedule.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-12 h-12 bg-purple-100 text-purple-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-headset text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-semibold text-gray-800">24/7 Support</h3>
                            <p class="text-gray-600">Our team is always available to assist you.</p>
                        </div>
                    </div>
                    <div class="flex items-start">
                        <div class="w-12 h-12 bg-orange-100 text-orange-600 rounded-lg flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-dollar-sign text-xl"></i>
                        </div>
                        <div class="ml-4">
                            <h3 class="font-semibold text-gray-800">Affordable Pricing</h3>
                            <p class="text-gray-600">Competitive prices without compromising quality.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-br from-primary to-secondary rounded-2xl p-8 text-white">
                <h3 class="text-2xl font-bold mb-6">Ready to Start Your Project?</h3>
                <p class="text-blue-100 mb-6">
                    Get in touch with us today and let's discuss how we can help bring your ideas to life.
                </p>
                <ul class="space-y-3 mb-8">
                    <li><i class="fas fa-check mr-2"></i>Free consultation</li>
                    <li><i class="fas fa-check mr-2"></i>Custom solutions</li>
                    <li><i class="fas fa-check mr-2"></i>Flexible payment options</li>
                    <li><i class="fas fa-check mr-2"></i>Post-delivery support</li>
                </ul>
                <a href="<?= SITE_URL ?>/contact.php" class="inline-block bg-white text-primary px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                    <i class="fas fa-envelope mr-2"></i>Contact Us
                </a>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-16 bg-dark text-white">
    <div class="max-w-4xl mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-4">Start Your Digital Journey Today</h2>
        <p class="text-gray-400 mb-8">
            Join hundreds of satisfied clients who have transformed their businesses with PhidTech Solutions.
        </p>
        <div class="flex flex-wrap justify-center gap-4">
            <a href="<?= SITE_URL ?>/auth/register.php" class="bg-primary text-white px-8 py-3 rounded-lg font-semibold hover:bg-secondary transition">
                <i class="fas fa-user-plus mr-2"></i>Create Account
            </a>
            <a href="<?= SITE_URL ?>/services.php" class="border border-gray-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-gray-800 transition">
                <i class="fas fa-list mr-2"></i>View Services
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

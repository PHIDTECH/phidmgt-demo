<?php
$pageTitle = 'About Us';
require_once __DIR__ . '/includes/header.php';
?>

<section class="bg-gradient-to-br from-primary to-secondary text-white py-16">
    <div class="max-w-7xl mx-auto px-4 text-center">
        <h1 class="text-4xl font-bold mb-4">About <?= SITE_NAME ?></h1>
        <p class="text-xl text-blue-100 max-w-2xl mx-auto">
            Your trusted partner for professional services and solutions
        </p>
    </div>
</section>

<section class="py-16">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 mb-6">Our Story</h2>
                <p class="text-gray-600 mb-4">
                    Our company was founded with a vision to deliver exceptional services to our clients. We believe every business, regardless of size, deserves access to world-class solutions.
                </p>
                <p class="text-gray-600 mb-4">
                    Our team of experienced developers, designers, and digital strategists work tirelessly to deliver innovative solutions that drive business growth and success.
                </p>
                <p class="text-gray-600">
                    From startups to established enterprises, we've helped hundreds of businesses transform their digital presence and achieve their goals.
                </p>
            </div>
            <div class="bg-gradient-to-br from-primary/10 to-secondary/10 rounded-2xl p-8">
                <div class="grid grid-cols-2 gap-6">
                    <div class="text-center">
                        <div class="text-4xl font-bold text-primary">5+</div>
                        <div class="text-gray-600">Years Experience</div>
                    </div>
                    <div class="text-center">
                        <div class="text-4xl font-bold text-primary">100+</div>
                        <div class="text-gray-600">Projects Delivered</div>
                    </div>
                    <div class="text-center">
                        <div class="text-4xl font-bold text-primary">50+</div>
                        <div class="text-gray-600">Happy Clients</div>
                    </div>
                    <div class="text-center">
                        <div class="text-4xl font-bold text-primary">10+</div>
                        <div class="text-gray-600">Team Members</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <h2 class="text-3xl font-bold text-gray-800 text-center mb-12">Our Values</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-white rounded-xl p-6 shadow-md text-center">
                <div class="w-16 h-16 bg-primary/10 text-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-star text-2xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Excellence</h3>
                <p class="text-gray-600">We strive for excellence in everything we do, delivering quality that exceeds expectations.</p>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-md text-center">
                <div class="w-16 h-16 bg-primary/10 text-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-handshake text-2xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Integrity</h3>
                <p class="text-gray-600">We operate with transparency and honesty, building trust with our clients.</p>
            </div>
            <div class="bg-white rounded-xl p-6 shadow-md text-center">
                <div class="w-16 h-16 bg-primary/10 text-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <i class="fas fa-lightbulb text-2xl"></i>
                </div>
                <h3 class="text-xl font-semibold mb-2">Innovation</h3>
                <p class="text-gray-600">We embrace new technologies and creative solutions to solve complex problems.</p>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php if (isLoggedIn() && $_SESSION['user_role'] !== ROLE_CUSTOMER): ?>
        </main>
    </div>
</div>
<?php else: ?>
<!-- Public Footer -->
<footer class="bg-dark text-white py-12 mt-12">
    <div class="max-w-7xl mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <h3 class="text-xl font-bold text-primary mb-4">
                    <i class="fas fa-code mr-2"></i><?= SITE_NAME ?>
                </h3>
                <p class="text-gray-400">Your trusted partner for professional services. Quality work, delivered on time.</p>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Services</h4>
                <ul class="space-y-2 text-gray-400">
                    <li><a href="<?= SITE_URL ?>/services.php" class="hover:text-primary">Web Development</a></li>
                    <li><a href="<?= SITE_URL ?>/services.php" class="hover:text-primary">Mobile Apps</a></li>
                    <li><a href="<?= SITE_URL ?>/services.php" class="hover:text-primary">Graphics Design</a></li>
                    <li><a href="<?= SITE_URL ?>/services.php" class="hover:text-primary">IT Consultation</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Quick Links</h4>
                <ul class="space-y-2 text-gray-400">
                    <li><a href="<?= SITE_URL ?>/about.php" class="hover:text-primary">About Us</a></li>
                    <li><a href="<?= SITE_URL ?>/contact.php" class="hover:text-primary">Contact</a></li>
                    <li><a href="<?= SITE_URL ?>/auth/login.php" class="hover:text-primary">Client Login</a></li>
                    <li><a href="<?= SITE_URL ?>/auth/register.php" class="hover:text-primary">Get Started</a></li>
                </ul>
            </div>
            <div>
                <h4 class="font-semibold mb-4">Contact Us</h4>
                <ul class="space-y-2 text-gray-400">
                    <li><i class="fas fa-map-marker-alt mr-2"></i>Your Address Here</li>
                    <li><i class="fas fa-phone mr-2"></i>+255 700 000 000</li>
                    <li><i class="fas fa-envelope mr-2"></i>info@yourdomain.com</li>
                </ul>
                <div class="flex space-x-4 mt-4">
                    <a href="#" class="text-gray-400 hover:text-primary"><i class="fab fa-facebook text-xl"></i></a>
                    <a href="#" class="text-gray-400 hover:text-primary"><i class="fab fa-twitter text-xl"></i></a>
                    <a href="#" class="text-gray-400 hover:text-primary"><i class="fab fa-instagram text-xl"></i></a>
                    <a href="#" class="text-gray-400 hover:text-primary"><i class="fab fa-linkedin text-xl"></i></a>
                </div>
            </div>
        </div>
        <div class="border-t border-gray-700 mt-8 pt-8 text-center text-gray-400">
            <p>&copy; <?= date('Y') ?> <?= SITE_NAME ?>. All rights reserved.</p>
        </div>
    </div>
</footer>
<?php endif; ?>

<script>
// Profile dropdown toggle
function toggleDropdown() {
    var dropdown = document.getElementById('profileDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
    var dropdown = document.getElementById('profileDropdown');
    var dropdownBtn = document.querySelector('.dropdown button');
    if (dropdown && dropdownBtn && !dropdownBtn.contains(e.target) && !dropdown.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

// Auto-hide alerts after 5 seconds
document.querySelectorAll('.alert-auto-hide').forEach(function(alert) {
    setTimeout(function() {
        alert.style.opacity = '0';
        setTimeout(function() { alert.remove(); }, 300);
    }, 5000);
});

// Confirm delete actions
document.querySelectorAll('.confirm-delete').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        if (!confirm('Are you sure you want to delete this item?')) {
            e.preventDefault();
        }
    });
});
</script>
</body>
</html>

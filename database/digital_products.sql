-- Digital Products Table for Software Downloads
-- Run this SQL to create the necessary tables

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Digital Product Orders/Purchases
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
    INDEX idx_payment_status (payment_status),
    FOREIGN KEY (product_id) REFERENCES digital_products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample Digital Products (optional - for testing)
INSERT INTO digital_products (name, slug, description, short_description, price, category, file_path, file_type, file_size, features, status) VALUES
('Business Website Template', 'business-website-template', 'A professional and modern business website template built with HTML5, CSS3, and JavaScript. Fully responsive and SEO optimized.', 'Professional business website template', 150000, 'templates', 'uploads/digital/business-template.zip', 'zip', '15 MB', 'Responsive Design, SEO Optimized, Easy Customization, Cross-browser Compatible', 'active'),
('E-Commerce Starter Kit', 'ecommerce-starter-kit', 'Complete e-commerce solution with shopping cart, payment integration, and admin panel. Built with PHP and MySQL.', 'Complete e-commerce solution', 500000, 'applications', 'uploads/digital/ecommerce-kit.zip', 'zip', '45 MB', 'Shopping Cart, Payment Gateway, Admin Panel, Inventory Management', 'active'),
('Digital Marketing Guide', 'digital-marketing-guide', 'Comprehensive guide to digital marketing strategies including SEO, Social Media, and Email Marketing.', 'Learn digital marketing strategies', 50000, 'ebooks', 'uploads/digital/marketing-guide.pdf', 'pdf', '5 MB', '200+ Pages, Practical Tips, Case Studies, Templates Included', 'active'),
('Social Media Graphics Pack', 'social-media-graphics', 'Over 100 professionally designed social media templates for Instagram, Facebook, and Twitter.', '100+ social media templates', 75000, 'graphics', 'uploads/digital/social-graphics.zip', 'zip', '120 MB', '100+ Templates, Editable PSD Files, Multiple Sizes, Brand Guidelines', 'active'),
('Web Development Course', 'web-development-course', 'Complete web development course covering HTML, CSS, JavaScript, PHP, and MySQL. Over 50 hours of video content.', 'Learn web development from scratch', 200000, 'video-courses', 'uploads/digital/webdev-course.zip', 'zip', '2 GB', '50+ Hours Video, Source Code, Certificate, Lifetime Access', 'active');

# Business Management System - Complete Documentation

A comprehensive business management system with project tracking, accounting, HR management, and integrated payment processing.

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Requirements](#requirements)
3. [Installation Guide](#installation-guide)
4. [Configuration](#configuration)
5. [User Roles & Permissions](#user-roles--permissions)
6. [Module Guide](#module-guide)
7. [Payment Integration (Selcom)](#payment-integration-selcom)
8. [Customization Guide](#customization-guide)
9. [Troubleshooting](#troubleshooting)
10. [Support](#support)

---

## System Overview

This is a complete business management solution designed for service-based companies. It includes:

- **Customer Portal** - Allow customers to browse services, place orders, and track projects
- **Project Management** - Track projects from order to completion
- **Task Management** - Assign and monitor tasks with approval workflows
- **Accounting Module** - Revenue, expenses, assets, liabilities, and financial reports
- **HR Module** - Staff management, leave applications, and salary records
- **Payment Processing** - Integrated Selcom payment gateway for mobile money and card payments
- **Messaging System** - Internal notifications and communication

---

## Requirements

### Server Requirements
- **PHP** 7.4 or higher (PHP 8.0+ recommended)
- **MySQL** 5.7 or higher / MariaDB 10.3+
- **Apache** web server with mod_rewrite enabled
- **cURL** PHP extension (for payment processing)
- **PDO** PHP extension with MySQL driver

### Recommended Hosting
- XAMPP, WAMP, or LAMP for local development
- Any PHP hosting provider (e.g., Hostinger, Namecheap, DigitalOcean)
- Minimum 512MB RAM, 1GB storage

---

## Installation Guide

### Step 1: Upload Files
Upload all files to your web server's document root (e.g., `htdocs`, `public_html`, or `www`).

```
/your-domain/
├── admin/
├── api/
├── auth/
├── config/
├── customer/
├── dashboard/
├── database/
├── includes/
├── uploads/
├── index.php
└── ... other files
```

### Step 2: Configure Database Connection
Edit `config/database.php` with your database credentials:

```php
define('DB_HOST', 'localhost');      // Your database host
define('DB_USER', 'your_db_user');   // Your database username
define('DB_PASS', 'your_db_pass');   // Your database password
define('DB_NAME', 'your_db_name');   // Your database name
```

### Step 3: Run Installation Script
Option A - **Automated Installation (Recommended)**
1. Open your browser and navigate to: `http://yourdomain.com/database/install.php`
2. The script will create all tables and default data
3. **Important:** Delete `install.php` after successful installation

Option B - **Manual Installation**
1. Create a new database in phpMyAdmin or MySQL
2. Import `database/schema.sql`
3. Run `database/create-admin.php` to create your admin account
4. Delete both files after use

### Step 4: Configure Site Settings
Edit `config/config.php` to customize your site:

```php
define('SITE_NAME', 'Your Company Name');
```

### Step 5: Set Up Payment Gateway (Optional)
See [Payment Integration](#payment-integration-selcom) section below.

### Step 6: Security Steps
After installation:
1. Delete `database/install.php`
2. Delete `database/create-admin.php`
3. Change all default passwords
4. Set proper file permissions (755 for directories, 644 for files)

---

## Configuration

### Main Configuration (`config/config.php`)
| Setting | Description |
|---------|-------------|
| `SITE_NAME` | Your company/business name |
| `SITE_URL` | Auto-detected, but can be manually set |
| `UPLOAD_PATH` | Path for file uploads |

### Database Configuration (`config/database.php`)
| Setting | Description |
|---------|-------------|
| `DB_HOST` | Database server (usually `localhost`) |
| `DB_USER` | Database username |
| `DB_PASS` | Database password |
| `DB_NAME` | Database name |

### Payment Configuration (`config/selcom.php`)
See [Payment Integration](#payment-integration-selcom) section.

---

## User Roles & Permissions

The system includes 9 built-in roles with hierarchical permissions:

| Role | Level | Access |
|------|-------|--------|
| **Super Admin** | 10 | Full system access, user management, role management |
| **CEO** | 8 | Company oversight, all reports, project approval |
| **Manager** | 7 | Project management, task assignment, staff supervision |
| **Accountant** | 6 | Full accounting access, financial reports |
| **HR** | 5 | Staff management, leave approval, salary processing |
| **Supervisor** | 4 | Task oversight, report review |
| **IT Staff** | 3 | Project execution, task completion |
| **Junior Staff** | 2 | Task execution only |
| **Customer** | 1 | Customer portal access only |

### Default Login Credentials
After installation, use these demo accounts:

| Role | Email | Password |
|------|-------|----------|
| Super Admin | admin@yourdomain.com | admin123 |
| CEO | ceo@example.com | demo123 |
| Manager | manager@example.com | demo123 |
| Accountant | accountant@example.com | demo123 |
| HR | hr@example.com | demo123 |
| Supervisor | supervisor@example.com | demo123 |
| IT Staff | itstaff@example.com | demo123 |
| Junior Staff | junior@example.com | demo123 |

**⚠️ IMPORTANT: Change all default passwords immediately after installation!**

---

## Module Guide

### 1. Customer Portal
**URL:** `http://yourdomain.com/`

Features:
- Browse available services with pricing
- Register/login as customer
- Place service orders
- Make payments via Selcom
- Track project progress
- View payment history
- Send messages to support

### 2. Staff Dashboard
**URL:** `http://yourdomain.com/dashboard/`

Features:
- Overview of assigned projects and tasks
- Project management (CEO, Manager)
- Task assignment and tracking
- Profile management
- Internal messaging

### 3. Project Management
**Access:** Manager, CEO, Super Admin

Workflow:
1. Customer places order → Project created (status: Pending)
2. Payment received → Status: Payment Received
3. Manager assigns to staff → Status: In Progress
4. Staff complete tasks → Tasks reviewed by Supervisor
5. Project completed → Customer notified

### 4. Task Management
**URL:** `http://yourdomain.com/dashboard/tasks.php`

Task Workflow:
1. Manager creates task for project
2. Task assigned to staff member
3. Staff marks task as "Submitted"
4. Supervisor reviews and approves/rejects
5. Approved tasks count toward project completion

### 5. Accounting Module
**URL:** `http://yourdomain.com/dashboard/accounting/`

Sub-modules:
- **Revenue** - Track all income from projects and other sources
- **Expenses** - Record and approve business expenses
- **Assets** - Manage company assets with depreciation
- **Liabilities** - Track debts and obligations
- **Petty Cash** - Manage petty cash fund
- **Reports** - P&L statements, trial balance, financial summaries

### 6. HR Module
**URL:** `http://yourdomain.com/dashboard/hr/`

Features:
- Staff directory with contact info
- Leave application and approval system
- Salary records and payroll processing
- Department management

### 7. Admin Panel
**URL:** `http://yourdomain.com/admin/`

**Access:** Super Admin only

Features:
- User management (create, edit, delete)
- Role management
- Permission assignment
- System configuration

---

## Payment Integration (Selcom)

This system integrates with **Selcom Payment Gateway** for processing payments in Tanzania.

### Supported Payment Methods
- M-Pesa (Vodacom)
- Tigo Pesa
- Airtel Money
- Halotel
- Bank Cards (Visa, Mastercard)

### Getting Selcom Credentials
1. Register at [Selcom Developer Portal](https://developers.selcommobile.com)
2. Create a merchant account
3. Get your API credentials:
   - API Key
   - API Secret
   - Vendor ID / Merchant ID
   - Till Number

### Configuration
Edit `config/selcom.php`:

```php
define('SELCOM_API_KEY', 'YOUR_SELCOM_API_KEY_HERE');
define('SELCOM_API_SECRET', 'YOUR_SELCOM_API_SECRET_HERE');
define('SELCOM_MERCHANT_ID', 'YOUR_MERCHANT_ID');
define('SELCOM_VENDOR_ID', 'YOUR_VENDOR_ID');
define('SELCOM_TILL_NUMBER', 'YOUR_TILL_NUMBER');
```

### Webhook Configuration
Set up webhook URL in your Selcom dashboard:
```
https://yourdomain.com/api/selcom-webhook.php
```

### Testing Payments
1. Use Selcom sandbox credentials first
2. Set `SELCOM_ENVIRONMENT` to `'sandbox'` for testing
3. Switch to `'production'` for live payments

---

## Customization Guide

### Branding
1. **Logo:** Replace logo in `includes/header.php`
2. **Colors:** Edit TailwindCSS classes or add custom CSS
3. **Company Name:** Change `SITE_NAME` in `config/config.php`

### Services
1. Login as CEO or Super Admin
2. Go to Dashboard → Services
3. Add/edit/delete services with pricing

### Email Templates
Notification emails can be customized in the respective PHP files in the `dashboard/` directory.

### Adding New User Roles
1. Go to Admin → Roles
2. Create new role with display name and level
3. Assign permissions to the role

### Currency
The system uses TZS (Tanzanian Shilling) by default. To change:
1. Edit `formatCurrency()` function in `config/config.php`
2. Update `SELCOM_CURRENCY` in `config/selcom.php` if using Selcom

---

## Troubleshooting

### Common Issues

**1. Database Connection Error**
- Verify credentials in `config/database.php`
- Ensure MySQL service is running
- Check database exists and user has permissions

**2. 404 Errors / Page Not Found**
- Enable mod_rewrite in Apache
- Check `.htaccess` file exists
- Verify Apache AllowOverride is set to All

**3. Payment Not Working**
- Verify Selcom credentials are correct
- Check webhook URL is accessible
- Ensure cURL extension is enabled
- Check API logs in `/logs/` directory

**4. Login Not Working**
- Clear browser cookies
- Verify user exists in database
- Check password was hashed correctly

**5. File Upload Errors**
- Check `uploads/` directory permissions (755)
- Verify PHP upload_max_filesize setting
- Ensure directory exists

### Error Logs
Check these locations for errors:
- PHP error log (check php.ini for location)
- Apache error log
- Application logs in `/logs/` directory

---

## File Structure

```
├── admin/                  # Admin panel
│   ├── index.php          # Admin dashboard
│   ├── users.php          # User management
│   ├── roles.php          # Role management
│   └── permissions.php    # Permission management
├── api/                   # API endpoints
│   └── selcom-webhook.php # Payment webhook
├── auth/                  # Authentication
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── config/                # Configuration
│   ├── config.php         # Main config
│   ├── database.php       # Database config
│   ├── selcom.php         # Payment config
│   └── SelcomClient.php   # Payment client
├── customer/              # Customer portal
│   ├── dashboard.php
│   ├── project.php
│   └── messages.php
├── dashboard/             # Staff dashboard
│   ├── index.php          # Main dashboard
│   ├── projects.php       # Project list
│   ├── tasks.php          # Task management
│   ├── accounting/        # Accounting module
│   └── hr/                # HR module
├── database/              # Database files
│   ├── schema.sql         # Database schema
│   └── install.php        # Installation script
├── includes/              # Shared components
│   ├── header.php
│   └── footer.php
├── uploads/               # File uploads
├── index.php              # Homepage
├── services.php           # Services page
├── order.php              # Order form
├── payment.php            # Payment page
├── about.php              # About page
└── contact.php            # Contact page
```

---

## Security Best Practices

1. **Change Default Passwords** - Immediately after installation
2. **Delete Installation Files** - Remove `install.php` and `create-admin.php`
3. **Use HTTPS** - Install SSL certificate for secure connections
4. **Regular Backups** - Backup database and files regularly
5. **Update PHP** - Keep PHP and MySQL updated
6. **File Permissions** - Set appropriate permissions (755/644)
7. **Firewall** - Use server firewall to restrict access

---

## Support

For technical support, customization requests, or questions:

- **Email:** support@yourdomain.com
- **Documentation:** This file
- **Updates:** Contact for latest version

---

## License

This software is licensed for single-site use. Redistribution or resale is prohibited without written permission.

© 2024 All Rights Reserved.

# Quick Start Guide
## Business Management System

---

## 5-Minute Installation

### Step 1: Upload Files
Upload all files to your web server's `public_html` or `htdocs` folder.

### Step 2: Configure Database
Edit `config/database.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'your_database');
```

### Step 3: Run Installer
Open in browser:
```
http://yourdomain.com/database/install.php
```

### Step 4: Delete Install Files
Remove these files after installation:
- `database/install.php`
- `database/create-admin.php`

### Step 5: Login & Configure
1. Login with admin credentials
2. Go to Settings → Update company info
3. Configure payment gateway (optional)

---

## First Login

**URL:** `http://yourdomain.com/auth/login.php`

**Default Admin:**
- Email: `admin@yourdomain.com`
- Password: `admin123`

**⚠️ Change password immediately!**

---

## Key Configuration Files

| File | What to Configure |
|------|-------------------|
| `config/config.php` | Company name |
| `config/database.php` | Database credentials |
| `config/selcom.php` | Payment gateway (optional) |
| `includes/footer.php` | Contact info |
| `contact.php` | Address & phone |

---

## Quick Tasks

### Add New Staff Member
1. Login as Admin
2. Go to Admin → Users
3. Click "Add New User"
4. Fill details & assign role

### Add New Service
1. Login as Manager/Admin
2. Go to Dashboard → Services
3. Click "Add Service"
4. Set name, price, description

### View Financial Reports
1. Login as Accountant/Admin
2. Go to Accounting → Reports
3. Select report type

---

## Module Access by Role

| Module | Admin | Manager | Accountant | HR | Staff |
|--------|-------|---------|------------|-----|-------|
| Dashboard | ✅ | ✅ | ✅ | ✅ | ✅ |
| Projects | ✅ | ✅ | ❌ | ❌ | ✅ |
| Tasks | ✅ | ✅ | ❌ | ❌ | ✅ |
| Accounting | ✅ | ❌ | ✅ | ❌ | ❌ |
| HR | ✅ | ✅ | ❌ | ✅ | ❌ |
| Admin | ✅ | ❌ | ❌ | ❌ | ❌ |

---

## Need Help?

📧 Email: support@yourdomain.com
📖 Full docs: See `DOCUMENTATION.md`

---

© 2024 Business Management System

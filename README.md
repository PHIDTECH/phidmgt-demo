# PhidTech Management System

A comprehensive business management system for PhidTech Solutions, built with PHP and MySQL.

## Features

### 1. Customer Portal
- Browse services with pricing
- Register and create account
- Order services with Selcom payment integration (simulated)
- Track project status and progress
- View payment history

### 2. User Roles & Access Control
- **CEO** - Full system access
- **Manager** - Project and staff management
- **Accountant** - Financial management
- **HR** - Staff and leave management
- **Supervisor** - Task oversight
- **IT Staff** - Project execution
- **Junior Staff** - Task execution
- **Customer** - Service ordering and tracking

### 3. Project Management
- Service selection and ordering
- Payment tracking
- Task assignment workflow
- Progress tracking: Junior Staff → Supervisor → Manager → CEO

### 4. Accounting System
- Revenue tracking
- Expense management with approval workflow
- Asset tracking
- Liability management
- Profit & Loss statements
- Trial balance
- Petty cash management
- Salary tracking

### 5. HR Management
- Staff directory and management
- Leave applications with approval workflow
- Salary records and payroll processing

### 6. Internal Messaging
- Real-time notifications
- Task assignment messages
- Payment confirmation alerts
- Project completion notifications
- Role-based message routing

### 7. Notification System
Messages are automatically sent when:
- Payment is received (CEO, Accountant, Customer)
- Tasks are assigned (Assigned staff)
- Tasks are completed (Manager, CEO, Assigned staff)
- Projects are completed (Customer, Manager, CEO)
- Leave is applied (HR, Manager)

## Installation

### Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite enabled
- XAMPP, WAMP, or similar local server

### Setup Steps

1. **Copy files to web server**
   ```
   Copy the phidtech.com folder to your htdocs directory
   ```

2. **Create the database**
   - Open phpMyAdmin or MySQL command line
   - Run the SQL file: `database/schema.sql`
   
   OR
   
   - Visit: `http://localhost/phidtech.com/database/install.php`

3. **Configure database connection** (if needed)
   - Edit `config/database.php`
   - Update DB_HOST, DB_USER, DB_PASS, DB_NAME

4. **Access the system**
   - Homepage: `http://localhost/phidtech.com/`
   - Login: `http://localhost/phidtech.com/auth/login.php`

## Demo Login Credentials

| Role | Email | Password |
|------|-------|----------|
| CEO | admin@phidtech.com | admin123 |
| Manager | manager@phidtech.com | manager123 |
| Accountant | accountant@phidtech.com | acc123 |
| HR | hr@phidtech.com | hr123 |
| Supervisor | supervisor@phidtech.com | super123 |
| IT Staff | it@phidtech.com | it123 |
| Junior Staff | junior@phidtech.com | junior123 |

**Note:** For demo purposes, all staff passwords are set to 'password' (hashed).

## Directory Structure

```
phidtech.com/
├── auth/                   # Authentication pages
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── config/                 # Configuration files
│   ├── config.php
│   └── database.php
├── customer/               # Customer portal
│   ├── dashboard.php
│   ├── project.php
│   └── messages.php
├── dashboard/              # Staff dashboard
│   ├── index.php
│   ├── projects.php
│   ├── project-details.php
│   ├── tasks.php
│   ├── messages.php
│   ├── services.php
│   ├── customers.php
│   ├── profile.php
│   ├── my-leave.php
│   ├── my-salary.php
│   ├── accounting/         # Accounting module
│   │   ├── revenue.php
│   │   ├── expenses.php
│   │   ├── assets.php
│   │   ├── liabilities.php
│   │   ├── petty-cash.php
│   │   └── reports.php
│   └── hr/                 # HR module
│       ├── staff.php
│       ├── leave.php
│       └── salaries.php
├── database/               # Database files
│   ├── schema.sql
│   └── install.php
├── includes/               # Shared components
│   ├── header.php
│   ├── footer.php
│   └── notifications.php
├── index.php               # Homepage
├── services.php            # Services listing
├── order.php               # Order form
├── payment.php             # Payment processing
├── about.php               # About page
├── contact.php             # Contact page
├── .htaccess               # Apache configuration
└── README.md               # This file
```

## Technology Stack

- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+
- **Frontend:** TailwindCSS (CDN)
- **Icons:** Font Awesome 6
- **Fonts:** Google Fonts (Inter)

## Security Features

- Password hashing with bcrypt
- Session-based authentication
- Role-based access control
- SQL injection prevention with PDO prepared statements
- XSS prevention with htmlspecialchars
- CSRF protection (forms use POST)

## Support

For support, contact: info@phidtech.com

## License

© 2024 PhidTech Solutions. All rights reserved.

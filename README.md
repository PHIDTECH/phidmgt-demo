# Business Management System

A comprehensive business management system built with PHP and MySQL. Includes customer portal, project management, accounting, HR, and integrated payment processing.

## Quick Start

### Requirements
- PHP 7.4+ (PHP 8.0+ recommended)
- MySQL 5.7+ / MariaDB 10.3+
- Apache with mod_rewrite enabled

### Installation

1. **Upload files** to your web server
2. **Configure database** - Edit `config/database.php` with your credentials
3. **Run installer** - Visit `http://yourdomain.com/database/install.php`
4. **Delete install files** - Remove `database/install.php` after setup
5. **Login** - Use default admin credentials (change immediately!)

📚 **See `DOCUMENTATION.md` for complete setup and usage guide.**

## Features

- **Customer Portal** - Service ordering, project tracking, payments
- **Project Management** - Full project lifecycle with task workflows
- **Accounting** - Revenue, expenses, assets, liabilities, reports
- **HR Module** - Staff management, leave, payroll
- **Payment Gateway** - Selcom integration (M-Pesa, Tigo Pesa, cards)
- **Role-Based Access** - 9 user roles with granular permissions
- **Notifications** - Automated messaging system

## Technology Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 7.4+ |
| Database | MySQL 5.7+ |
| Frontend | TailwindCSS |
| Icons | Font Awesome 6 |
| Payments | Selcom Gateway |

## Configuration Files

| File | Purpose |
|------|---------|
| `config/database.php` | Database connection |
| `config/config.php` | Site settings |
| `config/selcom.php` | Payment gateway |

## Security

- Bcrypt password hashing
- PDO prepared statements
- Session-based auth
- Role-based access control
- XSS protection

## Documentation

Complete documentation available in `DOCUMENTATION.md` including:
- Detailed installation guide
- User role explanations
- Module documentation
- Payment setup
- Customization guide
- Troubleshooting

## License

Licensed for single-site use. See license terms for details.

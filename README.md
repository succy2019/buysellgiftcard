# FEX Trading Platform

A comprehensive cryptocurrency and gift card trading platform built with PHP, MySQL, and modern web technologies.

## 🚀 Features

### User Features
- **User Registration & Authentication** - Secure account creation and login
- **Cryptocurrency Trading** - Buy and sell Bitcoin, Ethereum, USDT, and more
- **Gift Card Exchange** - Exchange gift cards for cash with competitive rates
- **Real-time Balance Management** - Track earnings and spending
- **Transaction History** - Complete record of all trading activities
- **Responsive Dashboard** - Mobile-friendly trading interface

### Admin Features
- **Admin Authentication** - Role-based access control (Super Admin, Admin, Moderator)
- **User Management** - View, search, and manage user accounts
- **Transaction Oversight** - Monitor all platform transactions
- **Gift Card Reviews** - Approve/reject gift card submissions with automated payouts
- **Rate Management** - Update cryptocurrency and gift card exchange rates
- **Platform Analytics** - Revenue, user, and transaction reports
- **System Monitoring** - Real-time platform status and health checks

## 📁 Project Structure

```
FEX Trading Platform/
├── config/
│   └── database.php          # Database configuration and connection
├── includes/
│   ├── utils.php             # Utility functions and helpers
│   ├── user_auth.php         # User authentication system
│   ├── user_dashboard.php    # User dashboard functions
│   ├── admin_auth.php        # Admin authentication system
│   └── admin_dashboard.php   # Admin dashboard functions
├── database/
│   └── schema.sql            # Complete database schema
├── static/
│   └── assets/               # CSS, JS, images, and other assets
├── uploads/                  # Gift card image uploads (auto-created)
├── logs/                     # Application logs (auto-created)
├── index.html                # Landing page
├── login.php                 # User login page
├── register.php              # User registration page
├── user-dashboard.php        # User trading dashboard
├── admin-login.php           # Admin login page
├── admin-dashboard.php       # Admin control panel
├── api.php                   # REST API endpoints
└── README.md                 # This file
```

## ⚙️ Setup Instructions

### Prerequisites
- PHP 7.4+ with PDO extension
- MySQL 5.7+ or MariaDB 10.2+
- Apache/Nginx web server
- Composer (optional, for dependencies)

### Installation Steps

1. **Clone/Download the project**
   ```bash
   git clone <repository-url>
   cd fex-trading-platform
   ```

2. **Database Setup**
   ```sql
   -- Create database
   CREATE DATABASE fex_trading;
   
   -- Import schema
   mysql -u username -p fex_trading < database/schema.sql
   ```

3. **Configure Database Connection**
   ```php
   // Edit config/database.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'fex_trading');
   define('DB_USER', 'your_username');
   define('DB_PASS', 'your_password');
   ```

4. **Set Permissions**
   ```bash
   chmod 755 uploads/
   chmod 755 logs/
   ```

5. **Create Admin Account**
   ```sql
   INSERT INTO admin_users (first_name, last_name, email, password, role, status) 
   VALUES ('Admin', 'User', 'admin@fex.com', '$2y$10$hash_here', 'super_admin', 'active');
   ```

6. **Web Server Configuration**
   - Point document root to project directory
   - Enable mod_rewrite (Apache) or equivalent
   - Ensure PHP has write permissions to uploads/ and logs/

## 🗄️ Database Schema

### Core Tables
- **users** - User accounts and profile information
- **admin_users** - Administrative accounts with role hierarchy
- **transactions** - All platform transactions (crypto + gift cards)
- **cryptocurrency_rates** - Real-time crypto exchange rates
- **gift_card_brands** - Supported gift card brands and rates
- **gift_card_submissions** - User gift card exchange requests
- **user_crypto_holdings** - User cryptocurrency balances
- **activity_logs** - Admin activity tracking
- **rate_history** - Historical exchange rate data
- **system_settings** - Platform configuration

### Key Relationships
- Users → Transactions (1:many)
- Users → Crypto Holdings (1:many)
- Users → Gift Card Submissions (1:many)
- Admin Users → Activity Logs (1:many)
- Gift Card Brands → Submissions (1:many)

## 🔐 Security Features

### Authentication & Authorization
- **Password Hashing** - bcrypt with salt
- **Session Management** - Secure session handling
- **Role-based Access** - Admin hierarchy (Super Admin > Admin > Moderator)
- **Rate Limiting** - Prevent brute force attacks
- **CSRF Protection** - Form security tokens

### Data Protection
- **Input Sanitization** - All user inputs filtered
- **SQL Injection Prevention** - PDO prepared statements
- **File Upload Security** - Image validation and virus scanning
- **Sensitive Data Encryption** - User data protection
- **Activity Logging** - Comprehensive audit trail

## 🔌 API Endpoints

### User Authentication
```
POST /api.php?endpoint=user/login
POST /api.php?endpoint=user/register
POST /api.php?endpoint=user/logout
```

### User Dashboard
```
GET  /api.php?endpoint=user/dashboard
GET  /api.php?endpoint=user/balance
GET  /api.php?endpoint=user/crypto-holdings
GET  /api.php?endpoint=user/transactions
POST /api.php?endpoint=user/buy-crypto
POST /api.php?endpoint=user/sell-crypto
POST /api.php?endpoint=user/submit-giftcard
```

### Admin Management
```
POST /api.php?endpoint=admin/login
GET  /api.php?endpoint=admin/dashboard
GET  /api.php?endpoint=admin/users
GET  /api.php?endpoint=admin/transactions
POST /api.php?endpoint=admin/update-user-status
POST /api.php?endpoint=admin/review-giftcard
POST /api.php?endpoint=admin/update-crypto-rates
```

### Public Data
```
GET /api.php?endpoint=crypto-rates
GET /api.php?endpoint=giftcard-rates
GET /api.php?endpoint=system/status
```

## 💰 Trading Features

### Cryptocurrency Trading
- **Supported Coins**: BTC, ETH, USDT, LTC, XRP, ADA, DOT
- **Real-time Rates** - Dynamic buy/sell pricing
- **Instant Execution** - Automated transaction processing
- **Portfolio Tracking** - Holdings and performance metrics
- **Transaction Fees** - Configurable platform fees

### Gift Card Exchange
- **Supported Brands**: Amazon, iTunes, Google Play, Steam, Walmart, Target
- **Rate Management** - Admin-configurable exchange rates
- **Image Verification** - Upload and review system
- **Automated Payouts** - Instant balance credit upon approval
- **Fraud Prevention** - Manual review process

## 👥 User Management

### User Accounts
- **Registration** - Email verification and KYC
- **Profile Management** - Personal information updates
- **Balance Tracking** - Real-time balance updates
- **Transaction History** - Complete activity log
- **Status Management** - Active/Suspended/Pending states

### Admin Controls
- **User Search** - Filter by name, email, status
- **Account Actions** - Activate, suspend, verify accounts
- **Balance Adjustments** - Manual balance corrections
- **Activity Monitoring** - User behavior tracking

## 📊 Admin Dashboard

### Platform Statistics
- **Revenue Tracking** - Total and monthly earnings
- **User Metrics** - Registration and activity stats
- **Transaction Volume** - Trading activity overview
- **Pending Reviews** - Gift card approval queue

### Management Tools
- **User Management** - Search, filter, and manage accounts
- **Transaction Oversight** - Monitor all platform activity
- **Rate Updates** - Real-time exchange rate management
- **Report Generation** - Revenue, user, and transaction reports

## 🛡️ System Requirements

### Server Requirements
- **PHP**: 7.4+ with extensions (PDO, GD, OpenSSL, JSON)
- **Database**: MySQL 5.7+ or MariaDB 10.2+
- **Web Server**: Apache 2.4+ or Nginx 1.16+
- **Storage**: 10GB+ for uploads and logs
- **Memory**: 512MB+ PHP memory limit

### Browser Support
- Chrome 70+
- Firefox 65+
- Safari 12+
- Edge 79+

## 🔧 Configuration Options

### Environment Settings
```php
// config/database.php
define('ENVIRONMENT', 'production'); // development, staging, production
define('DEBUG_MODE', false);
define('MAINTENANCE_MODE', false);
```

### Platform Settings
```php
// Trading fees
define('CRYPTO_TRADING_FEE', 0.25); // 0.25%
define('GIFT_CARD_PROCESSING_FEE', 2.00); // $2.00

// File upload limits
define('MAX_UPLOAD_SIZE', 5242880); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png']);

// Rate limiting
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
```

## 📈 Performance Optimization

### Database Optimization
- **Indexes** - Optimized queries with proper indexing
- **Caching** - Query result caching for static data
- **Connection Pooling** - Efficient database connections

### Frontend Optimization
- **Minified Assets** - Compressed CSS and JavaScript
- **Image Optimization** - Optimized images and lazy loading
- **CDN Ready** - Static asset delivery optimization

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Check database credentials in `config/database.php`
   - Verify MySQL service is running
   - Test database connectivity

2. **File Upload Errors**
   - Check `uploads/` directory permissions (755)
   - Verify PHP `upload_max_filesize` setting
   - Ensure adequate disk space

3. **Session Issues**
   - Verify PHP session configuration
   - Check session directory permissions
   - Clear browser cookies

4. **Permission Denied**
   - Set correct file permissions (644 for files, 755 for directories)
   - Verify web server user ownership
   - Check SELinux settings if applicable

## 📞 Support & Maintenance

### Monitoring
- **Error Logs** - Check `logs/` directory for errors
- **Database Health** - Monitor connection and performance
- **Disk Space** - Monitor uploads and log file sizes
- **Security** - Regular security updates and patches

### Backup Strategy
- **Database Backup** - Daily automated MySQL dumps
- **File Backup** - Regular backup of uploads and configuration
- **Code Backup** - Version control with Git

## 🔄 Updates & Maintenance

### Regular Tasks
- **Database Cleanup** - Archive old transactions and logs
- **Security Updates** - Keep PHP and dependencies updated
- **Rate Updates** - Update cryptocurrency exchange rates
- **Log Rotation** - Manage log file sizes

### Feature Additions
- **New Cryptocurrencies** - Add support for additional coins
- **Payment Methods** - Integrate additional payment gateways
- **Enhanced Security** - Two-factor authentication
- **Mobile App** - Native mobile applications

## 📝 License

This project is proprietary software. All rights reserved.

## 👨‍💻 Development Team

- **Backend Development** - PHP/MySQL architecture
- **Frontend Development** - Responsive web interface
- **Security Implementation** - Authentication and data protection
- **Database Design** - Normalized schema and optimization

---

**FEX Trading Platform** - Professional cryptocurrency and gift card trading solution built for scalability, security, and performance.
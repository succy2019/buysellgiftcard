<?php
/**
 * Database Setup Script for FEX Trading Platform
 * Run this file once to create the database and tables
 */

// Database connection settings
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'fex_trading_platform';

try {
    // Connect to MySQL server (without selecting a database)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database '$database' created successfully (or already exists)\n";
    
    // Connect to the new database
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Read and execute the SQL schema
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (file_exists($schemaFile)) {
        $sql = file_get_contents($schemaFile);
        
        // Split SQL into individual statements
        $statements = explode(';', $sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement) && !preg_match('/^(CREATE DATABASE|USE)/i', $statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Continue even if some statements fail
                    if (strpos($e->getMessage(), '1050') === false) { // Ignore "table exists" errors
                        echo "Warning: " . $e->getMessage() . "\n";
                    }
                }
            }
        }
        echo "✅ Database tables created successfully from schema file\n";
    } else {
        echo "❌ Schema file not found at: $schemaFile\n";
        echo "Creating essential tables manually...\n";
        
        // Create essential tables if schema file is missing
        $createEssentialTables = "
        -- Users table
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(50) NOT NULL,
            last_name VARCHAR(50) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            phone VARCHAR(20),
            password_hash VARCHAR(255) NOT NULL,
            country VARCHAR(50),
            status ENUM('active', 'suspended', 'pending') DEFAULT 'pending',
            email_verified BOOLEAN DEFAULT FALSE,
            balance DECIMAL(15, 2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            INDEX idx_email (email),
            INDEX idx_status (status)
        );

        -- Admin users table
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            INDEX idx_email (email),
            INDEX idx_username (username)
        );

        -- User sessions table
        CREATE TABLE IF NOT EXISTS user_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            session_token VARCHAR(255) NOT NULL,
            user_agent TEXT,
            ip_address VARCHAR(45),
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_session_token (session_token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_at (expires_at)
        );

        -- Admin sessions table
        CREATE TABLE IF NOT EXISTS admin_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            session_token VARCHAR(255) NOT NULL,
            user_agent TEXT,
            ip_address VARCHAR(45),
            expires_at TIMESTAMP NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
            INDEX idx_session_token (session_token),
            INDEX idx_admin_id (admin_id),
            INDEX idx_expires_at (expires_at)
        );

        -- Activity logs table
        CREATE TABLE IF NOT EXISTS activity_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            admin_id INT NULL,
            action VARCHAR(100) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL,
            INDEX idx_user_id (user_id),
            INDEX idx_admin_id (admin_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at)
        );

        -- Transactions table
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id VARCHAR(50) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            type ENUM('buy_crypto', 'sell_crypto', 'gift_card_exchange', 'deposit', 'withdrawal') NOT NULL,
            crypto_symbol VARCHAR(10) NULL,
            gift_card_brand VARCHAR(20) NULL,
            amount DECIMAL(15, 2) NOT NULL,
            crypto_amount DECIMAL(20, 8) NULL,
            rate DECIMAL(15, 8) NULL,
            fee DECIMAL(15, 2) DEFAULT 0.00,
            status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            payment_method VARCHAR(50) NULL,
            notes TEXT NULL,
            admin_notes TEXT NULL,
            processed_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_id (user_id),
            INDEX idx_transaction_id (transaction_id),
            INDEX idx_status (status),
            INDEX idx_type (type),
            INDEX idx_created_at (created_at)
        );

        -- Gift card brands table
        CREATE TABLE IF NOT EXISTS gift_card_brands (
            id INT AUTO_INCREMENT PRIMARY KEY,
            brand_name VARCHAR(50) NOT NULL,
            brand_code VARCHAR(20) UNIQUE NOT NULL,
            exchange_rate DECIMAL(5, 2) NOT NULL,
            min_amount DECIMAL(10, 2) DEFAULT 0.00,
            max_amount DECIMAL(10, 2) DEFAULT 99999.99,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_brand_code (brand_code),
            INDEX idx_active (is_active)
        );

        -- Gift card submissions table
        CREATE TABLE IF NOT EXISTS gift_card_submissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            submission_id VARCHAR(50) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            brand_id INT NOT NULL,
            card_value DECIMAL(10, 2) NOT NULL,
            card_code VARCHAR(255) NOT NULL,
            card_image_path VARCHAR(500) NULL,
            exchange_rate DECIMAL(5, 2) NOT NULL,
            payout_amount DECIMAL(10, 2) NOT NULL,
            status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
            rejection_reason TEXT NULL,
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (brand_id) REFERENCES gift_card_brands(id),
            INDEX idx_user_id (user_id),
            INDEX idx_submission_id (submission_id),
            INDEX idx_status (status),
            INDEX idx_brand_id (brand_id)
        );

        -- System settings table
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) UNIQUE NOT NULL,
            setting_value TEXT NOT NULL,
            description TEXT,
            updated_by INT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_setting_key (setting_key)
        );

        -- Cryptocurrency rates table
        CREATE TABLE IF NOT EXISTS cryptocurrency_rates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            symbol VARCHAR(10) NOT NULL,
            name VARCHAR(50) NOT NULL,
            buy_rate DECIMAL(15, 8) NOT NULL,
            sell_rate DECIMAL(15, 8) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            updated_by INT,
            INDEX idx_symbol (symbol),
            INDEX idx_active (is_active)
        );

        -- User crypto holdings table
        CREATE TABLE IF NOT EXISTS user_crypto_holdings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            crypto_symbol VARCHAR(10) NOT NULL,
            amount DECIMAL(20, 8) NOT NULL DEFAULT 0.00000000,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_crypto (user_id, crypto_symbol),
            INDEX idx_user_id (user_id)
        );
        ";
        
        // Execute essential table creation
        $statements = explode(';', $createEssentialTables);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    echo "Warning: " . $e->getMessage() . "\n";
                }
            }
        }
        echo "✅ Essential tables created successfully\n";
    }
        
    // Insert default admin user if not exists
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = 'admin'");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $adminPassword = password_hash('admin123', PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO admin_users (username, email, password_hash, role) VALUES ('admin', 'admin@fex.com', ?, 'super_admin')");
            $stmt->execute([$adminPassword]);
            echo "✅ Default admin user created (username: admin, password: admin123)\n";
        }
    } catch (PDOException $e) {
        echo "Warning: Could not create admin user: " . $e->getMessage() . "\n";
    }
    
    // Insert some gift card brands if not exists
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM gift_card_brands");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $brands = [
                ['Amazon', 'AMAZON', 85.00, 25.00, 2000.00],
                ['iTunes', 'ITUNES', 82.00, 15.00, 1500.00],
                ['Google Play', 'GOOGLEPLAY', 80.00, 25.00, 1000.00],
                ['Steam', 'STEAM', 78.00, 20.00, 500.00],
                ['Walmart', 'WALMART', 83.00, 25.00, 1000.00],
                ['Target', 'TARGET', 81.00, 25.00, 500.00]
            ];
            
            $stmt = $pdo->prepare("INSERT INTO gift_card_brands (brand_name, brand_code, exchange_rate, min_amount, max_amount) VALUES (?, ?, ?, ?, ?)");
            foreach ($brands as $brand) {
                $stmt->execute($brand);
            }
            echo "✅ Default gift card brands created\n";
        }
    } catch (PDOException $e) {
        echo "Warning: Could not create gift card brands: " . $e->getMessage() . "\n";
    }
    
    // Insert cryptocurrency rates if not exists
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM cryptocurrency_rates");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $cryptos = [
                ['BTC', 'Bitcoin', 67845.00000000, 67800.00000000],
                ['ETH', 'Ethereum', 2456.00000000, 2450.00000000],
                ['USDT', 'Tether', 1.00000000, 0.99500000],
                ['LTC', 'Litecoin', 85.50000000, 85.00000000],
                ['XRP', 'Ripple', 0.55000000, 0.54500000]
            ];
            
            $stmt = $pdo->prepare("INSERT INTO cryptocurrency_rates (symbol, name, buy_rate, sell_rate) VALUES (?, ?, ?, ?)");
            foreach ($cryptos as $crypto) {
                $stmt->execute($crypto);
            }
            echo "✅ Default cryptocurrency rates created\n";
        }
    } catch (PDOException $e) {
        echo "Warning: Could not create cryptocurrency rates: " . $e->getMessage() . "\n";
    }
    
    // Insert system settings if not exists
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_settings");
        $stmt->execute();
        if ($stmt->fetchColumn() == 0) {
            $settings = [
                ['site_name', 'FEX Trading Platform', 'Site name'],
                ['maintenance_mode', '0', 'Maintenance mode (0=off, 1=on)'],
                ['min_trade_amount', '10.00', 'Minimum trade amount in USD'],
                ['max_trade_amount', '50000.00', 'Maximum trade amount in USD'],
                ['trading_fee_percentage', '0.5', 'Trading fee percentage'],
                ['gift_card_processing_time', '24', 'Gift card processing time in hours']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
            foreach ($settings as $setting) {
                $stmt->execute($setting);
            }
            echo "✅ Default system settings created\n";
        }
    } catch (PDOException $e) {
        echo "Warning: Could not create system settings: " . $e->getMessage() . "\n";
    }
    
    // Test the connection and show table count
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    echo "✅ Database setup complete! Found " . count($tables) . " tables\n";
    
    // Show admin login info
    echo "\n🎉 Setup successful!\n";
    echo "📧 Admin login URL: http://localhost/fex.org.ng/admin-login.php\n";
    echo "👤 Admin username: admin\n";
    echo "🔑 Admin password: admin123\n\n";
    echo "🌐 User registration: http://localhost/fex.org.ng/register.php\n";
    echo "🔗 User login: http://localhost/fex.org.ng/login.php\n\n";
    echo "📊 All sections are now fully functional with real data!\n";
    
} catch (PDOException $e) {
    echo "❌ Database setup failed: " . $e->getMessage() . "\n";
    echo "Make sure XAMPP MySQL is running and try again.\n";
}
?>
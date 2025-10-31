-- FEX Cryptocurrency & Gift Card Trading Platform Database Schema
-- Created: October 31, 2025

CREATE DATABASE IF NOT EXISTS fex_trading_platform;
USE fex_trading_platform;

-- Users table for customer accounts
CREATE TABLE users (
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
CREATE TABLE admin_users (
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

-- Cryptocurrency rates table
CREATE TABLE cryptocurrency_rates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    symbol VARCHAR(10) NOT NULL,
    name VARCHAR(50) NOT NULL,
    buy_rate DECIMAL(15, 8) NOT NULL,
    sell_rate DECIMAL(15, 8) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT,
    FOREIGN KEY (updated_by) REFERENCES admin_users(id),
    INDEX idx_symbol (symbol),
    INDEX idx_active (is_active)
);

-- Gift card brands and rates table
CREATE TABLE gift_card_brands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand_name VARCHAR(50) NOT NULL,
    brand_code VARCHAR(20) UNIQUE NOT NULL,
    exchange_rate DECIMAL(5, 2) NOT NULL, -- Percentage rate (e.g., 85.00 for 85%)
    min_amount DECIMAL(10, 2) DEFAULT 0.00,
    max_amount DECIMAL(10, 2) DEFAULT 99999.99,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_brand_code (brand_code),
    INDEX idx_active (is_active)
);

-- User cryptocurrency holdings
CREATE TABLE user_crypto_holdings (
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

-- Transactions table for all trading activities
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    type ENUM('buy_crypto', 'sell_crypto', 'gift_card_exchange', 'deposit', 'withdrawal') NOT NULL,
    crypto_symbol VARCHAR(10) NULL, -- For crypto transactions
    gift_card_brand VARCHAR(20) NULL, -- For gift card transactions
    amount DECIMAL(15, 2) NOT NULL, -- USD amount
    crypto_amount DECIMAL(20, 8) NULL, -- Crypto amount
    rate DECIMAL(15, 8) NULL, -- Exchange rate at time of transaction
    fee DECIMAL(15, 2) DEFAULT 0.00,
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    payment_method VARCHAR(50) NULL,
    notes TEXT NULL,
    admin_notes TEXT NULL,
    processed_by INT NULL, -- Admin who processed
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES admin_users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
);

-- Gift card submissions table
CREATE TABLE gift_card_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    brand_id INT NOT NULL,
    card_value DECIMAL(10, 2) NOT NULL,
    card_code VARCHAR(255) NOT NULL, -- Encrypted gift card code
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
    FOREIGN KEY (reviewed_by) REFERENCES admin_users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_submission_id (submission_id),
    INDEX idx_status (status),
    INDEX idx_brand_id (brand_id)
);

-- User sessions table for security
CREATE TABLE user_sessions (
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
CREATE TABLE admin_sessions (
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

-- Support tickets table
CREATE TABLE support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    category ENUM('general', 'trading', 'gift_cards', 'technical', 'account') DEFAULT 'general',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES admin_users(id),
    INDEX idx_user_id (user_id),
    INDEX idx_ticket_id (ticket_id),
    INDEX idx_status (status)
);

-- System settings table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES admin_users(id),
    INDEX idx_setting_key (setting_key)
);

-- Activity logs table for audit trail
CREATE TABLE activity_logs (
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

-- Insert default cryptocurrency rates
INSERT INTO cryptocurrency_rates (symbol, name, buy_rate, sell_rate) VALUES
('BTC', 'Bitcoin', 67845.00000000, 67800.00000000),
('ETH', 'Ethereum', 2456.00000000, 2450.00000000),
('USDT', 'Tether', 1.00000000, 0.99500000),
('LTC', 'Litecoin', 85.50000000, 85.00000000),
('XRP', 'Ripple', 0.55000000, 0.54500000);

-- Insert default gift card brands
INSERT INTO gift_card_brands (brand_name, brand_code, exchange_rate, min_amount, max_amount) VALUES
('Amazon', 'AMAZON', 85.00, 25.00, 2000.00),
('iTunes', 'ITUNES', 82.00, 15.00, 1500.00),
('Google Play', 'GOOGLEPLAY', 80.00, 25.00, 1000.00),
('Steam', 'STEAM', 78.00, 20.00, 500.00),
('Walmart', 'WALMART', 83.00, 25.00, 1000.00),
('Target', 'TARGET', 81.00, 25.00, 500.00);

-- Insert default admin user
INSERT INTO admin_users (username, email, password_hash, role) VALUES
('admin', 'admin@fex.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin'); -- password: admin123

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'FEX Trading Platform', 'Site name'),
('maintenance_mode', '0', 'Maintenance mode (0=off, 1=on)'),
('min_trade_amount', '10.00', 'Minimum trade amount in USD'),
('max_trade_amount', '50000.00', 'Maximum trade amount in USD'),
('trading_fee_percentage', '0.5', 'Trading fee percentage'),
('gift_card_processing_time', '24', 'Gift card processing time in hours');
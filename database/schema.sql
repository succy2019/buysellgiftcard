-- DFQ Exchange Cryptocurrency & Gift Card Trading Platform Database Schema
-- Created: October 31, 2025

CREATE DATABASE IF NOT EXISTS dfqexchange;
USE dfqexchange;

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
    balance_ngn DECIMAL(18, 2) DEFAULT 0.00,
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

-- Single-use password reset tokens (users and admins).
-- Only the SHA-256 hash of each token is stored.
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_type ENUM('user', 'admin') NOT NULL,
    account_id INT NOT NULL,
    email VARCHAR(100) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    ip_address VARCHAR(45),
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_token_hash (token_hash),
    INDEX idx_account (account_type, account_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- Support ticket conversation
CREATE TABLE support_ticket_replies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    author_type ENUM('user', 'admin') NOT NULL,
    author_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket_id (ticket_id),
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
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

-- Company receiving accounts: the bank/wallet details users pay into
CREATE TABLE company_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(100) NOT NULL,
    method ENUM('bank', 'crypto') NOT NULL,
    currency VARCHAR(10) NOT NULL,
    network VARCHAR(30) NULL,
    bank_name VARCHAR(100) NULL,
    account_name VARCHAR(100) NULL,
    account_number VARCHAR(50) NULL,
    wallet_address VARCHAR(255) NULL,
    qr_code_path VARCHAR(500) NULL,
    instructions TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_company_active (is_active),
    INDEX idx_company_currency (currency)
);

-- User payout accounts: where the user wants to receive NGN or USDT
CREATE TABLE user_payout_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(100) NULL,
    method ENUM('bank', 'crypto') NOT NULL,
    currency VARCHAR(10) NOT NULL,
    network VARCHAR(30) NULL,
    bank_name VARCHAR(100) NULL,
    account_name VARCHAR(100) NULL,
    account_number VARCHAR(50) NULL,
    wallet_address VARCHAR(255) NULL,
    is_default BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    rejection_reason VARCHAR(255) NULL,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_payout_user (user_id),
    INDEX idx_payout_status (status)
);

-- Manual crypto transfer requests (deposit into the platform or withdrawal)
CREATE TABLE transfer_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(50) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    direction ENUM('deposit', 'withdrawal') NOT NULL DEFAULT 'deposit',
    crypto_symbol VARCHAR(10) NOT NULL,
    network VARCHAR(30) NULL,
    crypto_amount DECIMAL(20, 8) NOT NULL DEFAULT 0.00000000,
    amount_usd DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    payout_currency ENUM('NGN', 'USDT') NOT NULL DEFAULT 'NGN',
    payout_amount DECIMAL(20, 8) NULL,
    rate_used DECIMAL(20, 8) NULL,
    fee DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    company_account_id INT NULL,
    user_payout_account_id INT NULL,
    user_tx_hash VARCHAR(255) NULL,
    user_proof_path VARCHAR(500) NULL,
    admin_tx_hash VARCHAR(255) NULL,
    admin_proof_path VARCHAR(500) NULL,
    user_notes TEXT NULL,
    admin_notes TEXT NULL,
    status ENUM('awaiting_payment', 'proof_submitted', 'under_review', 'verified', 'processing', 'paid', 'rejected', 'cancelled') DEFAULT 'proof_submitted',
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    processed_by INT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_account_id) REFERENCES company_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (user_payout_account_id) REFERENCES user_payout_accounts(id) ON DELETE SET NULL,
    FOREIGN KEY (verified_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    FOREIGN KEY (processed_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_transfer_user (user_id),
    INDEX idx_transfer_reference (reference),
    INDEX idx_transfer_status (status),
    INDEX idx_transfer_direction (direction),
    INDEX idx_transfer_created (created_at)
);

-- Immutable ledger of every wallet movement
CREATE TABLE ledger_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    currency VARCHAR(10) NOT NULL,
    direction ENUM('credit', 'debit') NOT NULL,
    amount DECIMAL(20, 8) NOT NULL,
    balance_after DECIMAL(20, 8) NOT NULL,
    reference_type VARCHAR(50) NOT NULL,
    reference_id INT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES admin_users(id) ON DELETE SET NULL,
    INDEX idx_ledger_user (user_id),
    INDEX idx_ledger_currency (currency),
    INDEX idx_ledger_reference (reference_type, reference_id),
    INDEX idx_ledger_created (created_at)
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
-- Password: Admin@12345 (change immediately after first login)
INSERT INTO admin_users (username, email, password_hash, role) VALUES
('admin', 'admin@dfqexchange.com.ng', '$2y$12$qIzfmv0ku74.443GmiwWD.JFovgzkWh6tV2HWu04VYBFs9p8Bu11i', 'super_admin');

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'DFQ Exchange', 'Site name'),
('maintenance_mode', '0', 'Maintenance mode (0=off, 1=on)'),
('min_trade_amount', '10.00', 'Minimum trade amount in USD'),
('max_trade_amount', '50000.00', 'Maximum trade amount in USD'),
('trading_fee_percentage', '0.5', 'Trading fee percentage'),
('gift_card_processing_time', '24', 'Gift card processing time in hours'),
('usd_to_ngn_rate', '1500.00', 'Naira value of 1 USD used for payouts'),
('transfer_fee_percentage', '0.5', 'Fee percentage applied to crypto transfer payouts'),
('min_transfer_usd', '10.00', 'Minimum crypto transfer value in USD'),
('max_transfer_usd', '50000.00', 'Maximum crypto transfer value in USD');
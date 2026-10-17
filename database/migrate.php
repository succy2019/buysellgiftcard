<?php
/**
 * Safe, idempotent migration runner for existing installations.
 *
 * Usage (command line only):
 *     php database/migrate.php
 *
 * It is safe to run multiple times: existing tables/columns/settings are left
 * untouched and only missing pieces are added.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration script can only be run from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';

$db = getDB();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function columnExists(PDO $db, $table, $column) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column"
    );
    $stmt->execute([':table' => $table, ':column' => $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function tableExists(PDO $db, $table) {
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table"
    );
    $stmt->execute([':table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

echo "Running DFQ Exchange migrations against " . DB_NAME . "...\n";

// 1. Naira wallet column
if (!columnExists($db, 'users', 'balance_ngn')) {
    $db->exec("ALTER TABLE users ADD COLUMN balance_ngn DECIMAL(18,2) DEFAULT 0.00 AFTER balance");
    echo "  + users.balance_ngn added\n";
} else {
    echo "  = users.balance_ngn already present\n";
}

// 2. Company receiving accounts
if (!tableExists($db, 'company_accounts')) {
    $db->exec("
        CREATE TABLE company_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            label VARCHAR(100) NOT NULL,
            method ENUM('bank','crypto') NOT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  + company_accounts created\n";
} else {
    echo "  = company_accounts already present\n";
}

// 3. User payout accounts
if (!tableExists($db, 'user_payout_accounts')) {
    $db->exec("
        CREATE TABLE user_payout_accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            label VARCHAR(100) NULL,
            method ENUM('bank','crypto') NOT NULL,
            currency VARCHAR(10) NOT NULL,
            network VARCHAR(30) NULL,
            bank_name VARCHAR(100) NULL,
            account_name VARCHAR(100) NULL,
            account_number VARCHAR(50) NULL,
            wallet_address VARCHAR(255) NULL,
            is_default BOOLEAN DEFAULT FALSE,
            status ENUM('pending','verified','rejected') DEFAULT 'pending',
            rejection_reason VARCHAR(255) NULL,
            verified_by INT NULL,
            verified_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (verified_by) REFERENCES admin_users(id) ON DELETE SET NULL,
            INDEX idx_payout_user (user_id),
            INDEX idx_payout_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  + user_payout_accounts created\n";
} else {
    echo "  = user_payout_accounts already present\n";
}

// 4. Transfer requests
if (!tableExists($db, 'transfer_requests')) {
    $db->exec("
        CREATE TABLE transfer_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(50) UNIQUE NOT NULL,
            user_id INT NOT NULL,
            direction ENUM('deposit','withdrawal') NOT NULL DEFAULT 'deposit',
            crypto_symbol VARCHAR(10) NOT NULL,
            network VARCHAR(30) NULL,
            crypto_amount DECIMAL(20,8) NOT NULL DEFAULT 0.00000000,
            amount_usd DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            payout_currency ENUM('NGN','USDT') NOT NULL DEFAULT 'NGN',
            payout_amount DECIMAL(20,8) NULL,
            rate_used DECIMAL(20,8) NULL,
            fee DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            company_account_id INT NULL,
            user_payout_account_id INT NULL,
            user_tx_hash VARCHAR(255) NULL,
            user_proof_path VARCHAR(500) NULL,
            admin_tx_hash VARCHAR(255) NULL,
            admin_proof_path VARCHAR(500) NULL,
            user_notes TEXT NULL,
            admin_notes TEXT NULL,
            status ENUM('awaiting_payment','proof_submitted','under_review','verified','processing','paid','rejected','cancelled') DEFAULT 'proof_submitted',
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  + transfer_requests created\n";
} else {
    echo "  = transfer_requests already present\n";
}

// 5. Ledger
if (!tableExists($db, 'ledger_entries')) {
    $db->exec("
        CREATE TABLE ledger_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            currency VARCHAR(10) NOT NULL,
            direction ENUM('credit','debit') NOT NULL,
            amount DECIMAL(20,8) NOT NULL,
            balance_after DECIMAL(20,8) NOT NULL,
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "  + ledger_entries created\n";
} else {
    echo "  = ledger_entries already present\n";
}

// 6. Default settings for the transfer flow
$settings = [
    ['usd_to_ngn_rate', '1500.00', 'Naira value of 1 USD used for payouts'],
    ['transfer_fee_percentage', '0.5', 'Fee percentage applied to crypto transfer payouts'],
    ['min_transfer_usd', '10.00', 'Minimum crypto transfer value in USD'],
    ['max_transfer_usd', '50000.00', 'Maximum crypto transfer value in USD'],
];

$stmt = $db->prepare(
    "INSERT INTO system_settings (setting_key, setting_value, description)
     VALUES (:key, :value, :description)
     ON DUPLICATE KEY UPDATE setting_key = setting_key"
);
foreach ($settings as $setting) {
    $stmt->execute([
        ':key' => $setting[0],
        ':value' => $setting[1],
        ':description' => $setting[2],
    ]);
}
echo "  + default transfer settings present\n";

echo "Migration complete.\n";

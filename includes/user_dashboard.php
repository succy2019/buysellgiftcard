<?php
/**
 * User Dashboard Functions for DFQ Exchange
 * Created: October 31, 2025
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email_templates.php';

class UserDashboard {
    private $db;
    private $userId;
    
    public function __construct($userId = null) {
        $this->db = getDB();
        $this->userId = $userId ?? $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get user dashboard overview data
     */
    public function getDashboardOverview() {
        try {
            if (!$this->userId) {
                throw new Exception('User ID not provided');
            }
            
            // Get user basic info and balance
            $userInfo = $this->getUserInfo();
            
            // Get trading statistics
            $stats = $this->getTradingStats();
            
            // Get recent transactions
            $recentTransactions = $this->getRecentTransactions(5);
            
            // Get crypto holdings
            $cryptoHoldings = $this->getCryptoHoldings();
            
            return [
                'success' => true,
                'data' => [
                    'user' => $userInfo,
                    'stats' => $stats,
                    'recent_transactions' => $recentTransactions,
                    'crypto_holdings' => $cryptoHoldings
                ]
            ];
            
        } catch (Exception $e) {
            logError('Dashboard overview error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to load dashboard data'
            ];
        }
    }
    
    /**
     * Get user information
     */
    public function getUserInfo() {
        try {
            $sql = "SELECT first_name, last_name, email, balance, balance_ngn, status, created_at, last_login
                    FROM users WHERE id = :user_id LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $this->userId]);
            $user = $stmt->fetch();

            if ($user) {
                $user['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $user['balance_formatted'] = formatCurrency($user['balance']);
                $user['balance_ngn_formatted'] = formatNaira($user['balance_ngn']);
                $user['member_since'] = date('F Y', strtotime($user['created_at']));
            }
            
            return $user;
            
        } catch (PDOException $e) {
            logError('Get user info error: ' . $e->getMessage());
            throw new Exception('Failed to get user information');
        }
    }
    
    /**
     * Get trading statistics
     */
    public function getTradingStats() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_trades,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_trades,
                        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as monthly_trades,
                        COALESCE(SUM(CASE WHEN status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN amount ELSE 0 END), 0) as monthly_volume
                    FROM transactions 
                    WHERE user_id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $this->userId]);
            $stats = $stmt->fetch();
            
            if ($stats) {
                $stats['monthly_volume_formatted'] = formatCurrency($stats['monthly_volume']);
            }
            
            return $stats;
            
        } catch (PDOException $e) {
            logError('Get trading stats error: ' . $e->getMessage());
            return [
                'total_trades' => 0,
                'completed_trades' => 0,
                'monthly_trades' => 0,
                'monthly_volume' => 0,
                'monthly_volume_formatted' => '$0.00'
            ];
        }
    }
    
    /**
     * Get recent transactions
     */
    public function getRecentTransactions($limit = 10) {
        try {
            $sql = "SELECT transaction_id, type, crypto_symbol, gift_card_brand, amount, 
                           crypto_amount, status, created_at
                    FROM transactions 
                    WHERE user_id = :user_id
                    ORDER BY created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $this->userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $transactions = $stmt->fetchAll();
            
            // Format transactions
            foreach ($transactions as &$transaction) {
                $transaction['amount_formatted'] = formatCurrency($transaction['amount']);
                $transaction['type_formatted'] = $this->formatTransactionType($transaction);
                $transaction['date_formatted'] = date('M d, Y', strtotime($transaction['created_at']));
                $transaction['time_ago'] = timeAgo($transaction['created_at']);
            }
            
            return $transactions;
            
        } catch (PDOException $e) {
            logError('Get recent transactions error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get crypto holdings
     */
    public function getCryptoHoldings($userId = null) {
        try {
            if ($userId) {
                $this->userId = $userId;
            }
            
            // Get detailed holdings for dashboard display
            $sql = "SELECT h.crypto_symbol, h.amount, r.name, r.buy_rate, r.sell_rate
                    FROM user_crypto_holdings h
                    LEFT JOIN cryptocurrency_rates r ON h.crypto_symbol = r.symbol
                    WHERE h.user_id = :user_id AND h.amount > 0
                    ORDER BY (h.amount * r.sell_rate) DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $this->userId]);
            
            $detailedHoldings = $stmt->fetchAll();
            
            // Format detailed holdings
            foreach ($detailedHoldings as &$holding) {
                $holding['amount_formatted'] = formatCrypto($holding['amount'], $holding['crypto_symbol']);
                $holding['value_usd'] = $holding['amount'] * $holding['sell_rate'];
                $holding['value_formatted'] = formatCurrency($holding['value_usd']);
            }
            
            // Also return simple array for backward compatibility
            $holdings = [];
            $sql = "SELECT crypto_symbol, amount FROM user_crypto_holdings WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $this->userId]);
            $results = $stmt->fetchAll();
            
            foreach ($results as $result) {
                $holdings[$result['crypto_symbol']] = $result['amount'];
            }
            
            // Ensure all major cryptos are represented
            $majors = ['BTC', 'ETH', 'USDT', 'LTC'];
            foreach ($majors as $symbol) {
                if (!isset($holdings[$symbol])) {
                    $holdings[$symbol] = 0;
                }
            }
            
            return $holdings;
            
        } catch (PDOException $e) {
            logError('Get crypto holdings error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get current cryptocurrency rates
     */
    public function getCryptoRates() {
        try {
            $sql = "SELECT symbol, name, buy_rate, sell_rate 
                    FROM cryptocurrency_rates 
                    WHERE is_active = 1 
                    ORDER BY name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $rates = $stmt->fetchAll();
            
            // Format rates
            foreach ($rates as &$rate) {
                $rate['buy_rate_formatted'] = formatCurrency($rate['buy_rate']);
                $rate['sell_rate_formatted'] = formatCurrency($rate['sell_rate']);
            }
            
            return $rates;
            
        } catch (PDOException $e) {
            logError('Get crypto rates error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get gift card brands and rates
     */
    public function getGiftCardBrands() {
        try {
            $sql = "SELECT brand_name, brand_code, exchange_rate, min_amount, max_amount
                    FROM gift_card_brands 
                    WHERE is_active = 1 
                    ORDER BY brand_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $brands = $stmt->fetchAll();
            
            // Format brands
            foreach ($brands as &$brand) {
                $brand['rate_formatted'] = $brand['exchange_rate'] . '%';
                $brand['min_amount_formatted'] = formatCurrency($brand['min_amount']);
                $brand['max_amount_formatted'] = formatCurrency($brand['max_amount']);
            }
            
            return $brands;
            
        } catch (PDOException $e) {
            logError('Get gift card brands error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Buy cryptocurrency
     */
    public function buyCryptocurrency($cryptoSymbolOrUserId, $amountUSDOrSymbol, $amountUSD = null) {
        // Handle both old and new method signatures
        if ($amountUSD !== null) {
            // New signature: buyCryptocurrency($userId, $symbol, $amount)
            $this->userId = $cryptoSymbolOrUserId;
            $cryptoSymbol = $amountUSDOrSymbol;
            $amountUSD = $amountUSD;
        } else {
            // Old signature: buyCryptocurrency($symbol, $amount)
            $cryptoSymbol = $cryptoSymbolOrUserId;
            $amountUSD = $amountUSDOrSymbol;
        }
        try {
            $this->db->beginTransaction();
            
            // Validate input
            if (empty($cryptoSymbol) || $amountUSD <= 0) {
                throw new Exception('Invalid input parameters');
            }
            
            // Check minimum/maximum trade amounts
            $limits = getTradeLimits();
            if ($amountUSD < $limits['min']) {
                throw new Exception('Minimum trade amount is ' . formatCurrency($limits['min']));
            }
            
            if ($amountUSD > $limits['max']) {
                throw new Exception('Maximum trade amount is ' . formatCurrency($limits['max']));
            }
            
            // Get current crypto rate
            $sql = "SELECT buy_rate FROM cryptocurrency_rates WHERE symbol = :symbol AND is_active = 1 LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':symbol' => $cryptoSymbol]);
            $rate = $stmt->fetch();
            
            if (!$rate) {
                throw new Exception('Cryptocurrency not available for trading');
            }
            
            // Calculate crypto amount and fee
            $fee = calculateTradingFee($amountUSD);
            $totalAmount = $amountUSD + $fee;
            $cryptoAmount = $amountUSD / $rate['buy_rate'];
            
            // Check user balance (lock the row for the duration of the trade)
            $userBalance = $this->getUserBalance(true);
            if ($userBalance < $totalAmount) {
                throw new Exception('Insufficient balance. You need ' . formatCurrency($totalAmount - $userBalance) . ' more.');
            }
            
            // Create transaction record
            $transactionId = generateTransactionId('BUY');
            
            $sql = "INSERT INTO transactions (transaction_id, user_id, type, crypto_symbol, amount, 
                                           crypto_amount, rate, fee, status) 
                    VALUES (:tx_id, :user_id, 'buy_crypto', :symbol, :amount, :crypto_amount, :rate, :fee, 'processing')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tx_id' => $transactionId,
                ':user_id' => $this->userId,
                ':symbol' => $cryptoSymbol,
                ':amount' => $amountUSD,
                ':crypto_amount' => $cryptoAmount,
                ':rate' => $rate['buy_rate'],
                ':fee' => $fee
            ]);
            
            $transactionRowId = (int)$this->db->lastInsertId();

            // Update user balance and holding, recording ledger entries
            $this->updateUserBalance(-$totalAmount, 'transaction', $transactionRowId);
            $this->updateCryptoHolding($cryptoSymbol, $cryptoAmount, 'transaction', $transactionRowId);
            
            // Complete transaction
            $this->completeTransaction($transactionId);
            
            $this->db->commit();
            
            // Log activity
            $this->logUserActivity('crypto_purchase', "Purchased {$cryptoAmount} {$cryptoSymbol} for " . formatCurrency($amountUSD));
            
            return [
                'success' => true,
                'message' => 'Cryptocurrency purchased successfully!',
                'transaction_id' => $transactionId,
                'crypto_amount' => $cryptoAmount,
                'total_cost' => $totalAmount
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            logError('Buy crypto error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Sell cryptocurrency
     */
    public function sellCryptocurrency($cryptoSymbolOrUserId, $cryptoAmountOrSymbol, $cryptoAmount = null) {
        // Handle both old and new method signatures
        if ($cryptoAmount !== null) {
            // New signature: sellCryptocurrency($userId, $symbol, $amount)
            $this->userId = $cryptoSymbolOrUserId;
            $cryptoSymbol = $cryptoAmountOrSymbol;
            $cryptoAmount = $cryptoAmount;
        } else {
            // Old signature: sellCryptocurrency($symbol, $amount)
            $cryptoSymbol = $cryptoSymbolOrUserId;
            $cryptoAmount = $cryptoAmountOrSymbol;
        }
        try {
            $this->db->beginTransaction();
            
            // Validate input
            if (empty($cryptoSymbol) || $cryptoAmount <= 0) {
                throw new Exception('Invalid input parameters');
            }
            
            // Check user's crypto holdings (lock the row for the duration)
            $holding = $this->getCryptoHolding($cryptoSymbol, true);
            if (!$holding || $holding['amount'] < $cryptoAmount) {
                throw new Exception('Insufficient ' . $cryptoSymbol . ' balance');
            }
            
            // Get current crypto rate
            $sql = "SELECT sell_rate FROM cryptocurrency_rates WHERE symbol = :symbol AND is_active = 1 LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':symbol' => $cryptoSymbol]);
            $rate = $stmt->fetch();
            
            if (!$rate) {
                throw new Exception('Cryptocurrency not available for trading');
            }
            
            // Calculate USD amount and fee
            $usdAmount = $cryptoAmount * $rate['sell_rate'];
            $fee = calculateTradingFee($usdAmount);
            $netAmount = $usdAmount - $fee;
            
            // Check minimum trade amount
            $sellLimits = getTradeLimits();
            if ($usdAmount < $sellLimits['min']) {
                throw new Exception('Minimum trade amount is ' . formatCurrency($sellLimits['min']));
            }
            if ($usdAmount > $sellLimits['max']) {
                throw new Exception('Maximum trade amount is ' . formatCurrency($sellLimits['max']));
            }
            
            // Create transaction record
            $transactionId = generateTransactionId('SELL');
            
            $sql = "INSERT INTO transactions (transaction_id, user_id, type, crypto_symbol, amount, 
                                           crypto_amount, rate, fee, status) 
                    VALUES (:tx_id, :user_id, 'sell_crypto', :symbol, :amount, :crypto_amount, :rate, :fee, 'processing')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':tx_id' => $transactionId,
                ':user_id' => $this->userId,
                ':symbol' => $cryptoSymbol,
                ':amount' => $usdAmount,
                ':crypto_amount' => $cryptoAmount,
                ':rate' => $rate['sell_rate'],
                ':fee' => $fee
            ]);
            
            $transactionRowId = (int)$this->db->lastInsertId();

            // Update user balance and holding, recording ledger entries
            $this->updateUserBalance($netAmount, 'transaction', $transactionRowId);
            $this->updateCryptoHolding($cryptoSymbol, -$cryptoAmount, 'transaction', $transactionRowId);
            
            // Complete transaction
            $this->completeTransaction($transactionId);
            
            $this->db->commit();
            
            // Log activity
            $this->logUserActivity('crypto_sale', "Sold {$cryptoAmount} {$cryptoSymbol} for " . formatCurrency($netAmount));
            
            return [
                'success' => true,
                'message' => 'Cryptocurrency sold successfully!',
                'transaction_id' => $transactionId,
                'usd_amount' => $netAmount,
                'fee' => $fee
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            logError('Sell crypto error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Submit gift card for exchange
     */
    public function submitGiftCard($userId, $brandCode, $cardValue, $cardCode, $cardImage = null) {
        $this->userId = $userId;
        $uploadedPath = null;

        try {
            $this->db->beginTransaction();

            // Validate input
            if (empty($brandCode) || $cardValue <= 0 || empty($cardCode)) {
                throw new Exception('All fields are required');
            }

            // Get gift card brand info
            $sql = "SELECT id, brand_name, exchange_rate, min_amount, max_amount
                    FROM gift_card_brands
                    WHERE brand_code = :brand_code AND is_active = 1 LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':brand_code' => $brandCode]);
            $brand = $stmt->fetch();

            if (!$brand) {
                throw new Exception('Gift card brand not supported');
            }

            // Check amount limits
            if ($cardValue < $brand['min_amount']) {
                throw new Exception('Minimum amount for ' . $brand['brand_name'] . ' is ' . formatCurrency($brand['min_amount']));
            }

            if ($cardValue > $brand['max_amount']) {
                throw new Exception('Maximum amount for ' . $brand['brand_name'] . ' is ' . formatCurrency($brand['max_amount']));
            }

            // Calculate payout amount
            $payoutAmount = $cardValue * ($brand['exchange_rate'] / 100);

            // A proof photo is mandatory for gift card review.
            $imagePath = null;
            if (is_string($cardImage) && $cardImage !== '') {
                $imagePath = ltrim(str_replace('\\', '/', $cardImage), '/');
            } elseif (is_array($cardImage) && ($cardImage['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadResult = saveUploadedFile($cardImage, 'gift_cards');
                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['message']);
                }
                $imagePath = $uploadResult['path'];
                $uploadedPath = $imagePath;
            }

            if (!$imagePath) {
                throw new Exception('A clear photo of the gift card is required');
            }
            
            // Generate submission ID
            $submissionId = generateSubmissionId();
            
            // Create gift card submission
            $sql = "INSERT INTO gift_card_submissions (submission_id, user_id, brand_id, card_value, 
                                                     card_code, card_image_path, exchange_rate, payout_amount, status) 
                    VALUES (:submission_id, :user_id, :brand_id, :card_value, :card_code, :image_path, :rate, :payout, 'pending')";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':submission_id' => $submissionId,
                ':user_id' => $this->userId,
                ':brand_id' => $brand['id'],
                ':card_value' => $cardValue,
                ':card_code' => encryptData($cardCode), // Encrypt sensitive card code
                ':image_path' => $imagePath,
                ':rate' => $brand['exchange_rate'],
                ':payout' => $payoutAmount
            ]);
            
            $this->db->commit();
            
            // Log activity
            $this->logUserActivity('gift_card_submission', "Submitted {$brand['brand_name']} gift card worth " . formatCurrency($cardValue));

            // Receipt for the user + review alert for the admin team.
            $this->sendGiftCardNotifications($userId, $brand['brand_name'], $cardValue, $payoutAmount, $submissionId);

            return [
                'success' => true,
                'message' => 'Gift card submitted successfully! It will be reviewed within 24 hours.',
                'submission_id' => $submissionId,
                'payout_amount' => $payoutAmount,
                'expected_payout' => formatCurrency($payoutAmount)
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($uploadedPath !== null) {
                $full = UPLOAD_DIR . $uploadedPath;
                if (is_file($full)) {
                    @unlink($full);
                }
            }
            logError('Submit gift card error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get gift card submissions
     */
    public function getGiftCardSubmissions($limit = 20) {
        try {
            $sql = "SELECT s.submission_id, s.card_value, s.payout_amount, s.status, s.created_at,
                           s.reviewed_at, s.rejection_reason, b.brand_name
                    FROM gift_card_submissions s
                    LEFT JOIN gift_card_brands b ON s.brand_id = b.id
                    WHERE s.user_id = :user_id
                    ORDER BY s.created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $this->userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $submissions = $stmt->fetchAll();
            
            // Format submissions
            foreach ($submissions as &$submission) {
                $submission['card_value_formatted'] = formatCurrency($submission['card_value']);
                $submission['payout_amount_formatted'] = formatCurrency($submission['payout_amount']);
                $submission['date_formatted'] = date('M d, Y', strtotime($submission['created_at']));
                $submission['time_ago'] = timeAgo($submission['created_at']);
            }
            
            return $submissions;
            
        } catch (PDOException $e) {
            logError('Get gift card submissions error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get transaction history
     */
    public function getTransactionHistory($page = 1, $limit = 20, $type = null, $userId = null) {
        try {
            if ($userId !== null) {
                $this->userId = $userId;
            }

            $page = max(1, (int)$page);
            $limit = min(100, max(1, (int)$limit));
            $offset = ($page - 1) * $limit;
            
            $whereClause = "WHERE user_id = :user_id";
            $params = [':user_id' => $this->userId];
            
            if ($type) {
                $whereClause .= " AND type = :type";
                $params[':type'] = $type;
            }
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM transactions $whereClause";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];
            
            // Get transactions
            $sql = "SELECT transaction_id, type, crypto_symbol, gift_card_brand, amount, 
                           crypto_amount, rate, fee, status, created_at, completed_at
                    FROM transactions 
                    $whereClause
                    ORDER BY created_at DESC
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $transactions = $stmt->fetchAll();
            
            // Format transactions
            foreach ($transactions as &$transaction) {
                $transaction['amount_formatted'] = formatCurrency($transaction['amount']);
                $transaction['type_formatted'] = $this->formatTransactionType($transaction);
                $transaction['date_formatted'] = date('M d, Y H:i', strtotime($transaction['created_at']));
                $transaction['fee_formatted'] = formatCurrency($transaction['fee']);
            }
            
            return [
                'transactions' => $transactions,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (PDOException $e) {
            logError('Get transaction history error: ' . $e->getMessage());
            return [
                'transactions' => [],
                'pagination' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'pages' => 0]
            ];
        }
    }
    
    /**
     * Get dashboard data (wrapper method for compatibility)
     */
    public function getDashboardData($userId) {
        $this->userId = $userId;
        $stats = $this->getTradingStats();
        return [
            'transaction_count' => $stats['completed_trades'] ?? 0,
            'monthly_volume' => $stats['monthly_volume'] ?? 0
        ];
    }
    
    /**
     * Get cryptocurrency rates (alias method)
     */
    public function getCryptocurrencyRates() {
        return $this->getCryptoRates();
    }
    
    /**
     * Get gift card rates (alias method)
     */
    public function getGiftCardRates() {
        return $this->getGiftCardBrands();
    }
    
    /**
     * Upload gift card image
     */
    public function uploadGiftCardImage($file) {
        return saveUploadedFile($file, 'gift_cards');
    }

    /**
     * Wallet balances for the current user.
     */
    public function getWalletBalances($userId = null) {
        if ($userId !== null) {
            $this->userId = $userId;
        }

        $stmt = $this->db->prepare("SELECT balance, balance_ngn FROM users WHERE id = :user_id LIMIT 1");
        $stmt->execute([':user_id' => $this->userId]);
        $row = $stmt->fetch() ?: ['balance' => 0, 'balance_ngn' => 0];

        $stmt = $this->db->prepare("SELECT amount FROM user_crypto_holdings WHERE user_id = :user_id AND crypto_symbol = 'USDT' LIMIT 1");
        $stmt->execute([':user_id' => $this->userId]);
        $usdt = $stmt->fetch();

        return [
            'USD' => (float)$row['balance'],
            'NGN' => (float)$row['balance_ngn'],
            'USDT' => (float)($usdt['amount'] ?? 0),
        ];
    }

    /**
     * A user's saved payout accounts.
     */
    public function getPayoutAccounts($userId = null, $onlyVerified = false) {
        $userId = $userId ?? $this->userId;

        $sql = "SELECT * FROM user_payout_accounts WHERE user_id = :user_id";
        if ($onlyVerified) {
            $sql .= " AND status = 'verified'";
        }
        $sql .= " ORDER BY is_default DESC, created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $accounts = $stmt->fetchAll();

        foreach ($accounts as &$account) {
            if ($account['method'] === 'bank') {
                $account['display'] = $account['bank_name'] . ' • ' . $account['account_number'] . ' (' . $account['account_name'] . ')';
            } else {
                $wallet = (string)$account['wallet_address'];
                $short = strlen($wallet) > 14
                    ? substr($wallet, 0, 8) . '...' . substr($wallet, -6)
                    : $wallet;
                $account['display'] = $account['currency'] . ' ' . ($account['network'] ?: '') . ' • ' . $short;
            }
        }

        return $accounts;
    }

    /**
     * Add a bank or wallet payout account. Accounts start as "pending" until an
     * admin verifies them.
     */
    public function addPayoutAccount($userId, $data) {
        try {
            $method = ($data['method'] ?? '') === 'crypto' ? 'crypto' : 'bank';
            $label = sanitizeInput(trim($data['label'] ?? ''));
            $makeDefault = !empty($data['is_default']);

            if ($method === 'bank') {
                $currency = 'NGN';
                $network = null;
                $bankName = sanitizeInput(trim($data['bank_name'] ?? ''));
                $accountName = sanitizeInput(trim($data['account_name'] ?? ''));
                $accountNumber = preg_replace('/\s+/', '', (string)($data['account_number'] ?? ''));
                $walletAddress = null;

                if ($bankName === '' || $accountName === '' || $accountNumber === '') {
                    return ['success' => false, 'message' => 'Bank name, account name and account number are required'];
                }
                if (!preg_match('/^[0-9]{10,20}$/', $accountNumber)) {
                    return ['success' => false, 'message' => 'Enter a valid account number (10-20 digits)'];
                }
            } else {
                $currency = 'USDT';
                $network = strtoupper(sanitizeInput(trim($data['network'] ?? '')));
                $walletAddress = sanitizeInput(trim($data['wallet_address'] ?? ''));
                $bankName = $accountName = $accountNumber = null;

                if (!in_array($network, ['TRC20', 'ERC20', 'BEP20'], true)) {
                    return ['success' => false, 'message' => 'Select a valid USDT network'];
                }
                if (!validateCryptoAddress($walletAddress, 'USDT', $network)) {
                    return ['success' => false, 'message' => 'Enter a valid ' . $network . ' wallet address'];
                }
            }

            // Reject exact duplicates.
            if ($method === 'bank') {
                $stmt = $this->db->prepare(
                    "SELECT id FROM user_payout_accounts
                     WHERE user_id = :user_id AND method = 'bank' AND account_number = :account_number LIMIT 1"
                );
                $stmt->execute([':user_id' => $userId, ':account_number' => $accountNumber]);
            } else {
                $stmt = $this->db->prepare(
                    "SELECT id FROM user_payout_accounts
                     WHERE user_id = :user_id AND method = 'crypto' AND wallet_address = :wallet AND network = :network LIMIT 1"
                );
                $stmt->execute([':user_id' => $userId, ':wallet' => $walletAddress, ':network' => $network]);
            }
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'That payout account has already been added'];
            }

            $this->db->beginTransaction();

            // The first account automatically becomes the default.
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_payout_accounts WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            $isFirst = (int)$stmt->fetchColumn() === 0;

            if ($makeDefault || $isFirst) {
                $stmt = $this->db->prepare("UPDATE user_payout_accounts SET is_default = 0 WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);
                $makeDefault = true;
            }

            $sql = "INSERT INTO user_payout_accounts
                        (user_id, label, method, currency, network, bank_name, account_name,
                         account_number, wallet_address, is_default, status)
                    VALUES
                        (:user_id, :label, :method, :currency, :network, :bank_name, :account_name,
                         :account_number, :wallet_address, :is_default, 'pending')";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':label' => $label !== '' ? $label : null,
                ':method' => $method,
                ':currency' => $currency,
                ':network' => $network,
                ':bank_name' => $bankName,
                ':account_name' => $accountName,
                ':account_number' => $accountNumber,
                ':wallet_address' => $walletAddress,
                ':is_default' => $makeDefault ? 1 : 0,
            ]);

            $newId = (int)$this->db->lastInsertId();
            $this->db->commit();

            $this->logUserActivity('payout_account_added', 'Added a ' . $method . ' payout account');

            return [
                'success' => true,
                'message' => 'Payout account added. It will be active once reviewed by an admin.',
                'account_id' => $newId,
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            logError('Add payout account error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to save the payout account'];
        }
    }

    public function deletePayoutAccount($userId, $accountId) {
        try {
            $stmt = $this->db->prepare("SELECT id, is_default FROM user_payout_accounts WHERE id = :id AND user_id = :user_id LIMIT 1");
            $stmt->execute([':id' => $accountId, ':user_id' => $userId]);
            $account = $stmt->fetch();

            if (!$account) {
                return ['success' => false, 'message' => 'Payout account not found'];
            }

            $stmt = $this->db->prepare("DELETE FROM user_payout_accounts WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':id' => $accountId, ':user_id' => $userId]);

            // Promote another account to default if we removed the default one.
            if ((int)$account['is_default'] === 1) {
                $stmt = $this->db->prepare(
                    "UPDATE user_payout_accounts SET is_default = 1
                     WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 1"
                );
                $stmt->execute([':user_id' => $userId]);
            }

            return ['success' => true, 'message' => 'Payout account removed'];
        } catch (Exception $e) {
            logError('Delete payout account error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to remove the payout account'];
        }
    }

    public function setDefaultPayoutAccount($userId, $accountId) {
        try {
            $stmt = $this->db->prepare("SELECT id FROM user_payout_accounts WHERE id = :id AND user_id = :user_id LIMIT 1");
            $stmt->execute([':id' => $accountId, ':user_id' => $userId]);
            if (!$stmt->fetch()) {
                return ['success' => false, 'message' => 'Payout account not found'];
            }

            $this->db->beginTransaction();
            $stmt = $this->db->prepare("UPDATE user_payout_accounts SET is_default = 0 WHERE user_id = :user_id");
            $stmt->execute([':user_id' => $userId]);
            $stmt = $this->db->prepare("UPDATE user_payout_accounts SET is_default = 1 WHERE id = :id AND user_id = :user_id");
            $stmt->execute([':id' => $accountId, ':user_id' => $userId]);
            $this->db->commit();

            return ['success' => true, 'message' => 'Default payout account updated'];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            logError('Set default payout account error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update the default account'];
        }
    }

    /**
     * Company receiving accounts that users can transfer to.
     */
    public function getCompanyAccounts($activeOnly = true) {
        $sql = "SELECT * FROM company_accounts";
        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }
        $sql .= " ORDER BY method ASC, currency ASC, network ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $accounts = $stmt->fetchAll();

        foreach ($accounts as &$account) {
            if ($account['method'] === 'bank') {
                $account['display'] = $account['bank_name'] . ' • ' . $account['account_number'] . ' (' . $account['account_name'] . ')';
                $account['address'] = $account['account_number'];
            } else {
                $account['display'] = $account['currency'] . ' ' . ($account['network'] ?: '') . ' • ' . $account['wallet_address'];
                $account['address'] = $account['wallet_address'];
            }
            $account['qr_url'] = $account['qr_code_path'] ? uploadUrl($account['qr_code_path']) : null;
        }

        return $accounts;
    }

    /**
     * Create a deposit (crypto sent to the company, paid out in NGN/USDT) or a
     * withdrawal (NGN/USDT balance paid to the user's saved account).
     */
    public function createTransferRequest($userId, $data, $proofFile = null) {
        $this->userId = $userId;
        $uploadedPath = null;

        try {
            $direction = ($data['direction'] ?? 'deposit') === 'withdrawal' ? 'withdrawal' : 'deposit';
            $payoutCurrency = strtoupper(trim($data['payout_currency'] ?? ''));

            if (!in_array($payoutCurrency, ['NGN', 'USDT'], true)) {
                throw new Exception('Select NGN or USDT as the payout currency');
            }

            $payoutAccountId = (int)($data['payout_account_id'] ?? 0);
            $stmt = $this->db->prepare(
                "SELECT * FROM user_payout_accounts
                 WHERE id = :id AND user_id = :user_id AND status = 'verified' LIMIT 1"
            );
            $stmt->execute([':id' => $payoutAccountId, ':user_id' => $userId]);
            $payoutAccount = $stmt->fetch();

            if (!$payoutAccount) {
                throw new Exception('Select a verified payout account. Add one under Profile and wait for admin approval.');
            }

            if ($payoutCurrency === 'NGN' && $payoutAccount['method'] !== 'bank') {
                throw new Exception('Choose a verified bank account for NGN payouts');
            }
            if ($payoutCurrency === 'USDT' && $payoutAccount['method'] !== 'crypto') {
                throw new Exception('Choose a verified USDT wallet for USDT payouts');
            }

            $usdToNgn = (float)getSystemSetting('usd_to_ngn_rate', 1500);
            if ($usdToNgn <= 0) {
                throw new Exception('The NGN exchange rate is not configured. Please contact support.');
            }

            $feePct = max(0, (float)getSystemSetting('transfer_fee_percentage', 0.5)) / 100;
            $minUsd = (float)getSystemSetting('min_transfer_usd', MIN_TRADE_AMOUNT);
            $maxUsd = (float)getSystemSetting('max_transfer_usd', MAX_TRADE_AMOUNT);

            $this->db->beginTransaction();

            if ($direction === 'deposit') {
                $symbol = strtoupper(trim($data['crypto_symbol'] ?? ''));
                $companyAccountId = (int)($data['company_account_id'] ?? 0);

                $stmt = $this->db->prepare(
                    "SELECT * FROM company_accounts
                     WHERE id = :id AND is_active = 1 AND method = 'crypto' AND currency = :symbol LIMIT 1"
                );
                $stmt->execute([':id' => $companyAccountId, ':symbol' => $symbol]);
                $companyAccount = $stmt->fetch();

                if (!$companyAccount) {
                    throw new Exception('Select a valid receiving wallet');
                }

                $cryptoAmount = (float)($data['crypto_amount'] ?? 0);
                if ($cryptoAmount <= 0) {
                    throw new Exception('Enter the amount of crypto you sent');
                }

                $txHash = sanitizeInput(trim($data['tx_hash'] ?? ''));
                if (strlen($txHash) < 6) {
                    throw new Exception('Enter the blockchain transaction hash');
                }

                if (!$proofFile || ($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                    throw new Exception('Upload proof of your transfer');
                }

                $stmt = $this->db->prepare(
                    "SELECT sell_rate FROM cryptocurrency_rates
                     WHERE symbol = :symbol AND is_active = 1 LIMIT 1"
                );
                $stmt->execute([':symbol' => $symbol]);
                $rate = $stmt->fetch();
                if (!$rate) {
                    throw new Exception('This cryptocurrency is not currently supported');
                }

                $amountUsd = $cryptoAmount * (float)$rate['sell_rate'];
                if ($amountUsd < $minUsd) {
                    throw new Exception('Minimum transfer value is ' . formatCurrency($minUsd));
                }
                if ($amountUsd > $maxUsd) {
                    throw new Exception('Maximum transfer value is ' . formatCurrency($maxUsd));
                }

                $fee = $amountUsd * $feePct;
                $netUsd = $amountUsd - $fee;
                $payoutAmount = $payoutCurrency === 'NGN' ? $netUsd * $usdToNgn : $netUsd;
                $rateUsed = $payoutCurrency === 'NGN' ? $usdToNgn : 1.0;

                $upload = saveUploadedFile($proofFile, 'transfer_proofs');
                if (!$upload['success']) {
                    throw new Exception($upload['message']);
                }
                $uploadedPath = $upload['path'];

                $reference = 'TRF' . date('Ymd') . strtoupper(bin2hex(random_bytes(4)));

                $sql = "INSERT INTO transfer_requests
                            (reference, user_id, direction, crypto_symbol, network, crypto_amount,
                             amount_usd, payout_currency, payout_amount, rate_used, fee,
                             company_account_id, user_payout_account_id, user_tx_hash,
                             user_proof_path, user_notes, status)
                        VALUES
                            (:reference, :user_id, 'deposit', :symbol, :network, :crypto_amount,
                             :amount_usd, :payout_currency, :payout_amount, :rate_used, :fee,
                             :company_account_id, :payout_account_id, :tx_hash,
                             :proof_path, :notes, 'proof_submitted')";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':reference' => $reference,
                    ':user_id' => $userId,
                    ':symbol' => $symbol,
                    ':network' => $companyAccount['network'],
                    ':crypto_amount' => $cryptoAmount,
                    ':amount_usd' => $amountUsd,
                    ':payout_currency' => $payoutCurrency,
                    ':payout_amount' => $payoutAmount,
                    ':rate_used' => $rateUsed,
                    ':fee' => $fee,
                    ':company_account_id' => $companyAccountId,
                    ':payout_account_id' => $payoutAccountId,
                    ':tx_hash' => $txHash,
                    ':proof_path' => $uploadedPath,
                    ':notes' => sanitizeInput(trim($data['user_notes'] ?? '')),
                ]);

                $this->db->commit();
                $this->logUserActivity('transfer_request', "Submitted crypto transfer $reference");

                $this->sendTransferNotifications($userId, $reference, 'deposit', $payoutAmount, $payoutCurrency);

                return [
                    'success' => true,
                    'message' => 'Transfer submitted for review. Reference: ' . $reference,
                    'reference' => $reference,
                    'payout_amount' => $payoutAmount,
                    'payout_currency' => $payoutCurrency,
                ];
            }

            // Withdrawal from the user's platform balance.
            $amount = (float)($data['amount'] ?? 0);
            if ($amount <= 0) {
                throw new Exception('Enter an amount to withdraw');
            }

            $amountUsd = $payoutCurrency === 'NGN' ? $amount / $usdToNgn : $amount;
            if ($amountUsd < $minUsd) {
                throw new Exception('Minimum withdrawal value is ' . formatCurrency($minUsd));
            }
            if ($amountUsd > $maxUsd) {
                throw new Exception('Maximum withdrawal value is ' . formatCurrency($maxUsd));
            }

            $reference = 'TRF' . date('Ymd') . strtoupper(bin2hex(random_bytes(4)));

            $sql = "INSERT INTO transfer_requests
                        (reference, user_id, direction, crypto_symbol, crypto_amount, amount_usd,
                         payout_currency, payout_amount, rate_used, fee, user_payout_account_id,
                         user_notes, status)
                    VALUES
                        (:reference, :user_id, 'withdrawal', :symbol, :crypto_amount, :amount_usd,
                         :payout_currency, :payout_amount, :rate_used, 0, :payout_account_id,
                         :notes, 'under_review')";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':reference' => $reference,
                ':user_id' => $userId,
                ':symbol' => $payoutCurrency,
                ':crypto_amount' => $amount,
                ':amount_usd' => $amountUsd,
                ':payout_currency' => $payoutCurrency,
                ':payout_amount' => $amount,
                ':rate_used' => $payoutCurrency === 'NGN' ? $usdToNgn : 1.0,
                ':payout_account_id' => $payoutAccountId,
                ':notes' => sanitizeInput(trim($data['user_notes'] ?? '')),
            ]);

            $requestId = (int)$this->db->lastInsertId();

            // Debit the balance immediately so it cannot be spent twice.
            if ($payoutCurrency === 'NGN') {
                $stmt = $this->db->prepare("SELECT balance_ngn FROM users WHERE id = :user_id LIMIT 1 FOR UPDATE");
                $stmt->execute([':user_id' => $userId]);
                $balance = (float)$stmt->fetchColumn();

                if ($balance < $amount) {
                    throw new Exception('Insufficient NGN balance');
                }

                $stmt = $this->db->prepare("UPDATE users SET balance_ngn = balance_ngn - :amount, updated_at = NOW() WHERE id = :user_id");
                $stmt->execute([':amount' => $amount, ':user_id' => $userId]);

                recordLedgerEntry($this->db, $userId, 'NGN', 'debit', $amount, $balance - $amount, 'transfer', $requestId);
            } else {
                $stmt = $this->db->prepare(
                    "SELECT amount FROM user_crypto_holdings
                     WHERE user_id = :user_id AND crypto_symbol = 'USDT' LIMIT 1 FOR UPDATE"
                );
                $stmt->execute([':user_id' => $userId]);
                $balance = (float)$stmt->fetchColumn();

                if ($balance < $amount) {
                    throw new Exception('Insufficient USDT balance');
                }

                $stmt = $this->db->prepare(
                    "UPDATE user_crypto_holdings SET amount = amount - :amount, updated_at = NOW()
                     WHERE user_id = :user_id AND crypto_symbol = 'USDT'"
                );
                $stmt->execute([':amount' => $amount, ':user_id' => $userId]);

                recordLedgerEntry($this->db, $userId, 'USDT', 'debit', $amount, $balance - $amount, 'transfer', $requestId);
            }

            $this->db->commit();
            $this->logUserActivity('transfer_request', "Submitted withdrawal request $reference");

            $this->sendTransferNotifications($userId, $reference, 'withdrawal', $amount, $payoutCurrency);

            return [
                'success' => true,
                'message' => 'Withdrawal request submitted. Reference: ' . $reference,
                'reference' => $reference,
            ];

        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($uploadedPath !== null) {
                $full = UPLOAD_DIR . $uploadedPath;
                if (is_file($full)) {
                    @unlink($full);
                }
            }
            logError('Create transfer request error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * The current user's transfer requests, newest first.
     */
    public function getTransferRequests($userId = null, $limit = 50) {
        $userId = $userId ?? $this->userId;
        $limit = min(200, max(1, (int)$limit));

        $sql = "SELECT t.*,
                       pa.method AS payout_method, pa.bank_name, pa.account_name,
                       pa.account_number, pa.wallet_address AS payout_wallet, pa.network AS payout_network,
                       ca.label AS company_label, ca.wallet_address AS company_wallet
                FROM transfer_requests t
                LEFT JOIN user_payout_accounts pa ON pa.id = t.user_payout_account_id
                LEFT JOIN company_accounts ca ON ca.id = t.company_account_id
                WHERE t.user_id = :user_id
                ORDER BY t.created_at DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $requests = $stmt->fetchAll();

        foreach ($requests as &$request) {
            $request['amount_usd_formatted'] = formatCurrency($request['amount_usd']);
            if ($request['payout_currency'] === 'NGN') {
                $request['payout_amount_formatted'] = formatNaira($request['payout_amount']);
            } else {
                $request['payout_amount_formatted'] = formatCrypto($request['payout_amount'], 'USDT', 2);
            }
            $request['crypto_amount_formatted'] = $request['crypto_symbol'] === 'NGN'
                ? formatNaira($request['crypto_amount'])
                : formatCrypto($request['crypto_amount'], $request['crypto_symbol'], 8);
            $request['date_formatted'] = date('M d, Y H:i', strtotime($request['created_at']));
            $request['status_label'] = ucwords(str_replace('_', ' ', $request['status']));
            $request['user_proof_url'] = $request['user_proof_path'] ? uploadUrl($request['user_proof_path']) : null;
            $request['admin_proof_url'] = $request['admin_proof_path'] ? uploadUrl($request['admin_proof_path']) : null;
        }

        return $requests;
    }

    /**
     * Helper Methods
     */

    /**
     * Email a receipt to the user and a review alert to the admin team.
     * Mail problems are logged and never affect the completed transaction.
     */
    private function sendGiftCardNotifications($userId, $brand, $cardValue, $payout, $submissionId) {
        try {
            $contact = $this->getUserContact($userId);
            if (!$contact) {
                return;
            }

            $userMail = emailTemplateGiftCardReceived(
                $contact['first_name'], $brand, $cardValue, $payout, $submissionId
            );
            sendAppEmail($contact['email'], $userMail['subject'], $userMail['html'], $userMail['text'], $contact['name']);

            $adminMail = emailTemplateAdminNewGiftCard(
                $submissionId, $brand, $cardValue, $payout, $contact['name'], $contact['email']
            );
            notifyAdmin($adminMail['subject'], $adminMail['html'], $adminMail['text']);
        } catch (Throwable $e) {
            logError('Gift card email error: ' . $e->getMessage());
        }
    }

    /**
     * Email a transfer receipt to the user and an alert to the admin team.
     */
    private function sendTransferNotifications($userId, $reference, $direction, $payoutAmount, $payoutCurrency) {
        try {
            $contact = $this->getUserContact($userId);
            if (!$contact) {
                return;
            }

            $userMail = emailTemplateTransferReceived(
                $contact['first_name'], $reference, $direction, $payoutAmount, $payoutCurrency
            );
            sendAppEmail($contact['email'], $userMail['subject'], $userMail['html'], $userMail['text'], $contact['name']);

            $adminMail = emailTemplateAdminNewTransfer(
                $reference, $direction, $contact['name'], $contact['email'], $payoutAmount, $payoutCurrency
            );
            notifyAdmin($adminMail['subject'], $adminMail['html'], $adminMail['text']);
        } catch (Throwable $e) {
            logError('Transfer email error: ' . $e->getMessage());
        }
    }

    private function getUserContact($userId) {
        $stmt = $this->db->prepare(
            "SELECT first_name, last_name, email FROM users WHERE id = :user_id LIMIT 1"
        );
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return [
            'first_name' => $row['first_name'],
            'name' => trim($row['first_name'] . ' ' . $row['last_name']),
            'email' => $row['email'],
        ];
    }

    private function getUserBalance($forUpdate = false) {
        $sql = "SELECT balance FROM users WHERE id = :user_id LIMIT 1" . ($forUpdate ? " FOR UPDATE" : "");
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $this->userId]);
        $result = $stmt->fetch();

        return $result ? (float)$result['balance'] : 0;
    }

    private function updateUserBalance($amount, $referenceType = null, $referenceId = null) {
        $sql = "UPDATE users SET balance = balance + :amount, updated_at = NOW() WHERE id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':amount' => $amount,
            ':user_id' => $this->userId
        ]);

        if ($referenceType !== null) {
            $newBalance = $this->getUserBalance();
            recordLedgerEntry(
                $this->db,
                $this->userId,
                'USD',
                $amount >= 0 ? 'credit' : 'debit',
                abs($amount),
                $newBalance,
                $referenceType,
                $referenceId
            );
        }
    }

    private function getCryptoHolding($symbol, $forUpdate = false) {
        $sql = "SELECT amount FROM user_crypto_holdings
                WHERE user_id = :user_id AND crypto_symbol = :symbol LIMIT 1" . ($forUpdate ? " FOR UPDATE" : "");
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $this->userId,
            ':symbol' => $symbol
        ]);

        return $stmt->fetch();
    }

    private function updateCryptoHolding($symbol, $amount, $referenceType = null, $referenceId = null) {
        $sql = "INSERT INTO user_crypto_holdings (user_id, crypto_symbol, amount)
                VALUES (:user_id_insert, :symbol_insert, :amount_insert)
                ON DUPLICATE KEY UPDATE amount = amount + :amount_update, updated_at = NOW()";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id_insert' => $this->userId,
            ':symbol_insert' => $symbol,
            ':amount_insert' => $amount,
            ':amount_update' => $amount
        ]);

        if ($referenceType !== null) {
            $holding = $this->getCryptoHolding($symbol);
            $newBalance = $holding ? (float)$holding['amount'] : 0;
            recordLedgerEntry(
                $this->db,
                $this->userId,
                strtoupper($symbol),
                $amount >= 0 ? 'credit' : 'debit',
                abs($amount),
                $newBalance,
                $referenceType,
                $referenceId
            );
        }
    }
    
    private function completeTransaction($transactionId) {
        $sql = "UPDATE transactions SET status = 'completed', completed_at = NOW() WHERE transaction_id = :tx_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tx_id' => $transactionId]);
    }
    
    private function formatTransactionType($transaction) {
        switch ($transaction['type']) {
            case 'buy_crypto':
                return 'Buy ' . strtoupper($transaction['crypto_symbol']);
            case 'sell_crypto':
                return 'Sell ' . strtoupper($transaction['crypto_symbol']);
            case 'gift_card_exchange':
                return $transaction['gift_card_brand'] . ' Gift Card';
            default:
                return ucwords(str_replace('_', ' ', $transaction['type']));
        }
    }
    
    private function logUserActivity($action, $description) {
        try {
            $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                    VALUES (:user_id, :action, :description, :ip_address, :user_agent)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $this->userId,
                ':action' => $action,
                ':description' => $description,
                ':ip_address' => getUserIP(),
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            logError('Log user activity error: ' . $e->getMessage());
        }
    }
}
?>
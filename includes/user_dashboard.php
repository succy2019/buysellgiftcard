<?php
/**
 * User Dashboard Functions for FEX Trading Platform
 * Created: October 31, 2025
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';

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
            $sql = "SELECT first_name, last_name, email, balance, status, created_at, last_login 
                    FROM users WHERE id = :user_id LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $this->userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                $user['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $user['balance_formatted'] = formatCurrency($user['balance']);
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
    public function getCryptoHoldings() {
        try {
            $sql = "SELECT h.crypto_symbol, h.amount, r.name, r.buy_rate, r.sell_rate
                    FROM user_crypto_holdings h
                    LEFT JOIN cryptocurrency_rates r ON h.crypto_symbol = r.symbol
                    WHERE h.user_id = :user_id AND h.amount > 0
                    ORDER BY (h.amount * r.sell_rate) DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $this->userId]);
            
            $holdings = $stmt->fetchAll();
            
            // Format holdings
            foreach ($holdings as &$holding) {
                $holding['amount_formatted'] = formatCrypto($holding['amount'], $holding['crypto_symbol']);
                $holding['value_usd'] = $holding['amount'] * $holding['sell_rate'];
                $holding['value_formatted'] = formatCurrency($holding['value_usd']);
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
    public function buyCryptocurrency($cryptoSymbol, $amountUSD) {
        try {
            $this->db->beginTransaction();
            
            // Validate input
            if (empty($cryptoSymbol) || $amountUSD <= 0) {
                throw new Exception('Invalid input parameters');
            }
            
            // Check minimum/maximum trade amounts
            if ($amountUSD < MIN_TRADE_AMOUNT) {
                throw new Exception('Minimum trade amount is ' . formatCurrency(MIN_TRADE_AMOUNT));
            }
            
            if ($amountUSD > MAX_TRADE_AMOUNT) {
                throw new Exception('Maximum trade amount is ' . formatCurrency(MAX_TRADE_AMOUNT));
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
            
            // Check user balance
            $userBalance = $this->getUserBalance();
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
            
            // Update user balance
            $this->updateUserBalance(-$totalAmount);
            
            // Update or create crypto holding
            $this->updateCryptoHolding($cryptoSymbol, $cryptoAmount);
            
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
            $this->db->rollBack();
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
    public function sellCryptocurrency($cryptoSymbol, $cryptoAmount) {
        try {
            $this->db->beginTransaction();
            
            // Validate input
            if (empty($cryptoSymbol) || $cryptoAmount <= 0) {
                throw new Exception('Invalid input parameters');
            }
            
            // Check user's crypto holdings
            $holding = $this->getCryptoHolding($cryptoSymbol);
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
            if ($usdAmount < MIN_TRADE_AMOUNT) {
                throw new Exception('Minimum trade amount is ' . formatCurrency(MIN_TRADE_AMOUNT));
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
            
            // Update user balance
            $this->updateUserBalance($netAmount);
            
            // Update crypto holding
            $this->updateCryptoHolding($cryptoSymbol, -$cryptoAmount);
            
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
            $this->db->rollBack();
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
    public function submitGiftCard($brandCode, $cardValue, $cardCode, $cardImage = null) {
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
            
            // Handle file upload if provided
            $imagePath = null;
            if ($cardImage && $cardImage['size'] > 0) {
                $uploadResult = saveUploadedFile($cardImage, 'gift_cards');
                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['message']);
                }
                $imagePath = $uploadResult['path'];
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
            
            return [
                'success' => true,
                'message' => 'Gift card submitted successfully! It will be reviewed within 24 hours.',
                'submission_id' => $submissionId,
                'payout_amount' => $payoutAmount,
                'expected_payout' => formatCurrency($payoutAmount)
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
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
    public function getTransactionHistory($page = 1, $limit = 20, $type = null) {
        try {
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
     * Helper Methods
     */
    
    private function getUserBalance() {
        $sql = "SELECT balance FROM users WHERE id = :user_id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $this->userId]);
        $result = $stmt->fetch();
        
        return $result ? $result['balance'] : 0;
    }
    
    private function updateUserBalance($amount) {
        $sql = "UPDATE users SET balance = balance + :amount, updated_at = NOW() WHERE id = :user_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':amount' => $amount,
            ':user_id' => $this->userId
        ]);
    }
    
    private function getCryptoHolding($symbol) {
        $sql = "SELECT amount FROM user_crypto_holdings WHERE user_id = :user_id AND crypto_symbol = :symbol LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $this->userId,
            ':symbol' => $symbol
        ]);
        
        return $stmt->fetch();
    }
    
    private function updateCryptoHolding($symbol, $amount) {
        $sql = "INSERT INTO user_crypto_holdings (user_id, crypto_symbol, amount) 
                VALUES (:user_id, :symbol, :amount)
                ON DUPLICATE KEY UPDATE amount = amount + :amount, updated_at = NOW()";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':user_id' => $this->userId,
            ':symbol' => $symbol,
            ':amount' => $amount
        ]);
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
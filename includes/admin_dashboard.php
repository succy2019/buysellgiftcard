<?php
/**
 * Admin Dashboard Functions for DFQ Exchange
 * Created: October 31, 2025
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';

class AdminDashboard {
    private $db;
    private $adminId;
    
    public function __construct($adminId = null) {
        $this->db = getDB();
        $this->adminId = $adminId ?? $_SESSION['admin_id'] ?? null;
    }
    
    /**
     * Get admin dashboard overview data
     */
    public function getDashboardOverview() {
        try {
            if (!$this->adminId) {
                throw new Exception('Admin ID not provided');
            }
            
            // Get platform statistics
            $stats = $this->getPlatformStats();
            
            // Get recent transactions
            $recentTransactions = $this->getRecentTransactions(10);
            
            // Get pending reviews
            $pendingReviews = $this->getPendingReviews();
            
            // Get system status
            $systemStatus = $this->getSystemStatus();
            
            // Get user activity
            $userActivity = $this->getUserActivity();
            
            return [
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_transactions' => $recentTransactions,
                    'pending_reviews' => $pendingReviews,
                    'system_status' => $systemStatus,
                    'user_activity' => $userActivity
                ]
            ];
            
        } catch (Exception $e) {
            logError('Admin dashboard overview error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to load dashboard data'
            ];
        }
    }
    
    /**
     * Get platform statistics
     */
    public function getPlatformStats() {
        try {
            // Total revenue calculation
            $sql = "SELECT COALESCE(SUM(fee), 0) as total_revenue FROM transactions WHERE status = 'completed'";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $totalRevenue = $stmt->fetch()['total_revenue'];
            
            // Monthly revenue
            $sql = "SELECT COALESCE(SUM(fee), 0) as monthly_revenue 
                    FROM transactions 
                    WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $monthlyRevenue = $stmt->fetch()['monthly_revenue'];
            
            // User statistics
            $sql = "SELECT 
                        COUNT(*) as total_users,
                        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users,
                        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_30d,
                        COUNT(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_users_7d
                    FROM users";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $userStats = $stmt->fetch();
            
            // Transaction statistics
            $sql = "SELECT 
                        COUNT(*) as total_transactions,
                        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_transactions,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_transactions,
                        COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as weekly_transactions,
                        COALESCE(SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END), 0) as total_volume
                    FROM transactions";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $transactionStats = $stmt->fetch();
            
            // Gift card statistics
            $sql = "SELECT 
                        COUNT(*) as total_submissions,
                        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_reviews,
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_submissions,
                        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_submissions
                    FROM gift_card_submissions";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $giftCardStats = $stmt->fetch();
            
            return [
                'revenue' => [
                    'total' => $totalRevenue,
                    'monthly' => $monthlyRevenue,
                    'total_formatted' => formatCurrency($totalRevenue),
                    'monthly_formatted' => formatCurrency($monthlyRevenue)
                ],
                'users' => $userStats,
                'transactions' => $transactionStats,
                'gift_cards' => $giftCardStats
            ];
            
        } catch (PDOException $e) {
            logError('Get platform stats error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get recent transactions for admin overview
     */
    public function getRecentTransactions($limit = 20) {
        try {
            $sql = "SELECT t.transaction_id, t.type, t.crypto_symbol, t.gift_card_brand, 
                           t.amount, t.status, t.created_at, t.user_id,
                           u.email as user_email, u.first_name, u.last_name
                    FROM transactions t
                    LEFT JOIN users u ON t.user_id = u.id
                    ORDER BY t.created_at DESC
                    LIMIT :limit";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $transactions = $stmt->fetchAll();
            
            // Format transactions
            foreach ($transactions as &$transaction) {
                $transaction['amount_formatted'] = formatCurrency($transaction['amount']);
                $transaction['type_formatted'] = $this->formatTransactionType($transaction);
                $transaction['date_formatted'] = date('M d, Y H:i', strtotime($transaction['created_at']));
                $transaction['user_name'] = $transaction['first_name'] . ' ' . $transaction['last_name'];
                $transaction['time_ago'] = timeAgo($transaction['created_at']);
            }
            
            return $transactions;
            
        } catch (PDOException $e) {
            logError('Get recent transactions error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get pending reviews (gift cards)
     */
    public function getPendingReviews() {
        try {
            $sql = "SELECT s.id, s.submission_id, s.card_value, s.payout_amount, s.created_at,
                           u.email as user_email, u.first_name, u.last_name,
                           b.brand_name
                    FROM gift_card_submissions s
                    LEFT JOIN users u ON s.user_id = u.id
                    LEFT JOIN gift_card_brands b ON s.brand_id = b.id
                    WHERE s.status = 'pending'
                    ORDER BY s.created_at ASC
                    LIMIT 10";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $reviews = $stmt->fetchAll();
            
            // Format reviews
            foreach ($reviews as &$review) {
                $review['card_value_formatted'] = formatCurrency($review['card_value']);
                $review['payout_amount_formatted'] = formatCurrency($review['payout_amount']);
                $review['date_formatted'] = date('M d, Y H:i', strtotime($review['created_at']));
                $review['user_name'] = $review['first_name'] . ' ' . $review['last_name'];
                $review['time_ago'] = timeAgo($review['created_at']);
            }
            
            return $reviews;
            
        } catch (PDOException $e) {
            logError('Get pending reviews error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get system status information
     */
    public function getSystemStatus() {
        try {
            $status = [
                'server' => 'online',
                'api' => 'active',
                'payment_gateway' => 'connected',
                'database' => 'optimal'
            ];
            
            // Check database connection
            try {
                $this->db->query("SELECT 1");
            } catch (Exception $e) {
                $status['database'] = 'error';
            }
            
            // Check maintenance mode
            $maintenanceMode = getSystemSetting('maintenance_mode', '0');
            $status['maintenance_mode'] = $maintenanceMode == '1';
            
            return $status;
            
        } catch (Exception $e) {
            logError('Get system status error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get user activity data
     */
    public function getUserActivity() {
        try {
            $sql = "SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as registrations
                    FROM users 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC
                    LIMIT 30";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            logError('Get user activity error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all users with pagination
     */
    public function getUsers($page = 1, $limit = 20, $search = '', $status = '') {
        try {
            $offset = ($page - 1) * $limit;
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if (!empty($search)) {
                $whereClause .= " AND (first_name LIKE :search_first OR last_name LIKE :search_last OR email LIKE :search_email)";
                $term = '%' . $search . '%';
                $params[':search_first'] = $term;
                $params[':search_last'] = $term;
                $params[':search_email'] = $term;
            }
            
            if (!empty($status)) {
                $whereClause .= " AND status = :status";
                $params[':status'] = $status;
            }
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM users $whereClause";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];
            
            // Get users
            $sql = "SELECT id, first_name, last_name, email, phone, country, balance, 
                           status, email_verified, created_at, last_login
                    FROM users 
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
            
            $users = $stmt->fetchAll();
            
            // Format users
            foreach ($users as &$user) {
                $user['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $user['balance_formatted'] = formatCurrency($user['balance']);
                $user['join_date'] = date('M d, Y', strtotime($user['created_at']));
                $user['last_login_formatted'] = $user['last_login'] ? timeAgo($user['last_login']) : 'Never';
            }
            
            return [
                'users' => $users,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (PDOException $e) {
            logError('Get users error: ' . $e->getMessage());
            return [
                'users' => [],
                'pagination' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'pages' => 0]
            ];
        }
    }
    
    /**
     * Update user status
     */
    public function updateUserStatus($userId, $status) {
        try {
            if (!in_array($status, ['active', 'suspended', 'pending'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid status'
                ];
            }
            
            $sql = "UPDATE users SET status = :status, updated_at = NOW() WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':status' => $status,
                ':user_id' => $userId
            ]);
            
            if ($result) {
                // Log admin activity
                $this->logAdminActivity('user_status_update', "Updated user #$userId status to: $status");
                
                return [
                    'success' => true,
                    'message' => 'User status updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update user status'
                ];
            }
            
        } catch (PDOException $e) {
            logError('Update user status error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error occurred'
            ];
        }
    }
    
    /**
     * Get all transactions with pagination
     */
    public function getTransactions($page = 1, $limit = 20, $type = '', $status = '') {
        try {
            $offset = ($page - 1) * $limit;
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if (!empty($type)) {
                $whereClause .= " AND t.type = :type";
                $params[':type'] = $type;
            }
            
            if (!empty($status)) {
                $whereClause .= " AND t.status = :status";
                $params[':status'] = $status;
            }
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM transactions t $whereClause";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];
            
            // Get transactions
            $sql = "SELECT t.id, t.transaction_id, t.type, t.crypto_symbol, t.gift_card_brand,
                           t.amount, t.fee, t.status, t.created_at, t.completed_at,
                           u.email as user_email, u.first_name, u.last_name
                    FROM transactions t
                    LEFT JOIN users u ON t.user_id = u.id
                    $whereClause
                    ORDER BY t.created_at DESC
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
                $transaction['fee_formatted'] = formatCurrency($transaction['fee']);
                $transaction['type_formatted'] = $this->formatTransactionType($transaction);
                $transaction['date_formatted'] = date('M d, Y H:i', strtotime($transaction['created_at']));
                $transaction['user_name'] = $transaction['first_name'] . ' ' . $transaction['last_name'];
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
            logError('Get transactions error: ' . $e->getMessage());
            return [
                'transactions' => [],
                'pagination' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'pages' => 0]
            ];
        }
    }
    
    /**
     * Get gift card submissions with pagination
     */
    public function getGiftCardSubmissions($page = 1, $limit = 20, $status = '') {
        try {
            $offset = ($page - 1) * $limit;
            
            $whereClause = "WHERE 1=1";
            $params = [];
            
            if (!empty($status)) {
                $whereClause .= " AND s.status = :status";
                $params[':status'] = $status;
            }
            
            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM gift_card_submissions s $whereClause";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];
            
            // Get submissions
            $sql = "SELECT s.id, s.submission_id, s.card_value, s.exchange_rate, s.payout_amount, 
                           s.status, s.created_at, s.reviewed_at, s.rejection_reason,
                           u.email as user_email, u.first_name, u.last_name,
                           b.brand_name
                    FROM gift_card_submissions s
                    LEFT JOIN users u ON s.user_id = u.id
                    LEFT JOIN gift_card_brands b ON s.brand_id = b.id
                    $whereClause
                    ORDER BY s.created_at DESC
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $submissions = $stmt->fetchAll();
            
            // Format submissions
            foreach ($submissions as &$submission) {
                $submission['card_value_formatted'] = formatCurrency($submission['card_value']);
                $submission['payout_amount_formatted'] = formatCurrency($submission['payout_amount']);
                $submission['rate_formatted'] = $submission['exchange_rate'] . '%';
                $submission['date_formatted'] = date('M d, Y H:i', strtotime($submission['created_at']));
                $submission['user_name'] = $submission['first_name'] . ' ' . $submission['last_name'];
                $submission['time_ago'] = timeAgo($submission['created_at']);
            }
            
            return [
                'submissions' => $submissions,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($total / $limit)
                ]
            ];
            
        } catch (PDOException $e) {
            logError('Get gift card submissions error: ' . $e->getMessage());
            return [
                'submissions' => [],
                'pagination' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'pages' => 0]
            ];
        }
    }
    
    /**
     * Review gift card submission
     */
    public function reviewGiftCardSubmission($submissionId, $action, $rejectionReason = '') {
        try {
            $this->db->beginTransaction();
            
            if (!in_array($action, ['approve', 'reject'])) {
                throw new Exception('Invalid action');
            }
            
            // Get submission details
            $sql = "SELECT s.*, b.brand_name, u.id as user_id
                    FROM gift_card_submissions s
                    LEFT JOIN users u ON s.user_id = u.id
                    LEFT JOIN gift_card_brands b ON s.brand_id = b.id
                    WHERE s.id = :submission_id AND s.status = 'pending' LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':submission_id' => $submissionId]);
            $submission = $stmt->fetch();

            if (!$submission) {
                throw new Exception('Submission not found or already processed');
            }

            if ($action === 'approve') {
                // Lock the user row so concurrent credits cannot interleave.
                $sql = "SELECT balance FROM users WHERE id = :user_id LIMIT 1 FOR UPDATE";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':user_id' => $submission['user_id']]);
                $currentBalance = (float)$stmt->fetchColumn();

                // Update user balance
                $sql = "UPDATE users SET balance = balance + :amount, updated_at = NOW() WHERE id = :user_id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':amount' => $submission['payout_amount'],
                    ':user_id' => $submission['user_id']
                ]);
                
                // Update submission status
                $sql = "UPDATE gift_card_submissions 
                        SET status = 'approved', reviewed_by = :admin_id, reviewed_at = NOW() 
                        WHERE id = :submission_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':admin_id' => $this->adminId,
                    ':submission_id' => $submissionId
                ]);
                
                // Create transaction record
                $transactionId = generateTransactionId('GC');
                $sql = "INSERT INTO transactions (transaction_id, user_id, type, gift_card_brand, 
                                               amount, fee, status, processed_by) 
                        VALUES (:tx_id, :user_id, 'gift_card_exchange', :brand, :amount, 0, 'completed', :admin_id)";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':tx_id' => $transactionId,
                    ':user_id' => $submission['user_id'],
                    ':brand' => $submission['brand_name'] ?? 'Gift Card',
                    ':amount' => $submission['payout_amount'],
                    ':admin_id' => $this->adminId
                ]);

                $transactionRowId = (int)$this->db->lastInsertId();
                recordLedgerEntry(
                    $this->db,
                    $submission['user_id'],
                    'USD',
                    'credit',
                    (float)$submission['payout_amount'],
                    $currentBalance + (float)$submission['payout_amount'],
                    'transaction',
                    $transactionRowId,
                    $this->adminId
                );

                $message = 'Gift card approved and payment processed';
                
            } else {
                // Reject submission
                $sql = "UPDATE gift_card_submissions 
                        SET status = 'rejected', rejection_reason = :reason, reviewed_by = :admin_id, reviewed_at = NOW() 
                        WHERE id = :submission_id";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':reason' => $rejectionReason,
                    ':admin_id' => $this->adminId,
                    ':submission_id' => $submissionId
                ]);
                
                $message = 'Gift card submission rejected';
            }
            
            $this->db->commit();
            
            // Log admin activity
            $this->logAdminActivity('gift_card_review', "$message: {$submission['submission_id']}");
            
            return [
                'success' => true,
                'message' => $message
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            logError('Review gift card error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update cryptocurrency rates
     */
    public function updateCryptoRates($rates) {
        try {
            $this->db->beginTransaction();
            
            foreach ($rates as $symbol => $rateData) {
                if (!isset($rateData['buy_rate']) || !isset($rateData['sell_rate'])) {
                    continue;
                }
                
                $sql = "UPDATE cryptocurrency_rates 
                        SET buy_rate = :buy_rate, sell_rate = :sell_rate, updated_by = :admin_id, updated_at = NOW() 
                        WHERE symbol = :symbol";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':buy_rate' => $rateData['buy_rate'],
                    ':sell_rate' => $rateData['sell_rate'],
                    ':admin_id' => $this->adminId,
                    ':symbol' => $symbol
                ]);
            }
            
            $this->db->commit();
            
            // Log admin activity
            $this->logAdminActivity('crypto_rates_update', 'Updated cryptocurrency exchange rates');
            
            return [
                'success' => true,
                'message' => 'Cryptocurrency rates updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            logError('Update crypto rates error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update cryptocurrency rates'
            ];
        }
    }
    
    /**
     * Update gift card rates
     */
    public function updateGiftCardRates($rates) {
        try {
            $this->db->beginTransaction();
            
            foreach ($rates as $brandCode => $rate) {
                if (!is_numeric($rate) || $rate < 0 || $rate > 100) {
                    continue;
                }
                
                $sql = "UPDATE gift_card_brands 
                        SET exchange_rate = :rate, updated_at = NOW() 
                        WHERE brand_code = :brand_code";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':rate' => $rate,
                    ':brand_code' => $brandCode
                ]);
            }
            
            $this->db->commit();
            
            // Log admin activity
            $this->logAdminActivity('gift_card_rates_update', 'Updated gift card exchange rates');
            
            return [
                'success' => true,
                'message' => 'Gift card rates updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            logError('Update gift card rates error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update gift card rates'
            ];
        }
    }
    
    /**
     * Update system settings
     */
    public function updateSystemSettings($settings) {
        try {
            $this->db->beginTransaction();
            
            foreach ($settings as $key => $value) {
                if (empty($key) || $value === '') {
                    continue;
                }
                
                $sql = "INSERT INTO system_settings (setting_key, setting_value, updated_by)
                        VALUES (:key_insert, :value_insert, :admin_insert)
                        ON DUPLICATE KEY UPDATE
                        setting_value = :value_update, updated_by = :admin_update, updated_at = NOW()";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':key_insert' => $key,
                    ':value_insert' => $value,
                    ':admin_insert' => $this->adminId,
                    ':value_update' => $value,
                    ':admin_update' => $this->adminId
                ]);
            }
            
            $this->db->commit();
            
            // Log admin activity
            $this->logAdminActivity('system_settings_update', 'Updated system settings');
            
            return [
                'success' => true,
                'message' => 'System settings updated successfully'
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            logError('Update system settings error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update system settings'
            ];
        }
    }
    
    /**
     * Generate platform reports
     */
    public function generateReport($type, $startDate, $endDate) {
        try {
            $reports = [];
            
            switch ($type) {
                case 'revenue':
                    $reports = $this->generateRevenueReport($startDate, $endDate);
                    break;
                    
                case 'users':
                    $reports = $this->generateUsersReport($startDate, $endDate);
                    break;
                    
                case 'transactions':
                    $reports = $this->generateTransactionsReport($startDate, $endDate);
                    break;
                    
                default:
                    throw new Exception('Invalid report type');
            }
            
            return [
                'success' => true,
                'data' => $reports
            ];
            
        } catch (Exception $e) {
            logError('Generate report error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to generate report'
            ];
        }
    }
    
    /**
     * Get cryptocurrency rates
     */
    public function getCryptocurrencyRates() {
        try {
            $sql = "SELECT symbol, name, buy_rate, sell_rate, is_active, updated_at 
                    FROM cryptocurrency_rates 
                    ORDER BY name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            logError('Get cryptocurrency rates error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get gift card rates
     */
    public function getGiftCardRates() {
        try {
            $sql = "SELECT brand_name, brand_code, exchange_rate, min_amount, max_amount, is_active, updated_at 
                    FROM gift_card_brands 
                    ORDER BY brand_name";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            logError('Get gift card rates error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Database server version, for the system status panel.
     */
    public function getDatabaseVersion() {
        try {
            return $this->db->query("SELECT VERSION()")->fetchColumn() ?: 'Unknown';
        } catch (Exception $e) {
            logError('Get database version error: ' . $e->getMessage());
            return 'Unknown';
        }
    }

    /**
     * Approve (complete) or cancel a transaction. Cancelling a buy/sell
     * reverses the balance and holding movements made when it was created.
     */
    public function updateTransactionStatus($transactionId, $action) {
        try {
            if (!in_array($action, ['approve', 'cancel'], true)) {
                throw new Exception('Invalid action');
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT * FROM transactions WHERE id = :id LIMIT 1 FOR UPDATE");
            $stmt->execute([':id' => $transactionId]);
            $transaction = $stmt->fetch();

            if (!$transaction) {
                throw new Exception('Transaction not found');
            }
            if (!in_array($transaction['status'], ['pending', 'processing'], true)) {
                throw new Exception('This transaction has already been finalized');
            }

            if ($action === 'approve') {
                $stmt = $this->db->prepare(
                    "UPDATE transactions SET status = 'completed', processed_by = :admin_id,
                     completed_at = NOW(), updated_at = NOW() WHERE id = :id"
                );
                $stmt->execute([':admin_id' => $this->adminId, ':id' => $transactionId]);
                $message = 'Transaction completed';
            } else {
                $this->reverseTransaction($transaction);

                $stmt = $this->db->prepare(
                    "UPDATE transactions SET status = 'cancelled', processed_by = :admin_id,
                     updated_at = NOW() WHERE id = :id"
                );
                $stmt->execute([':admin_id' => $this->adminId, ':id' => $transactionId]);
                $message = 'Transaction cancelled and balances reversed';
            }

            $this->db->commit();
            $this->logAdminActivity('transaction_status', "Transaction {$transaction['transaction_id']} $action");

            return ['success' => true, 'message' => $message];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            logError('Update transaction status error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /* ------------------------------------------------------------------
     * Company receiving accounts
     * ------------------------------------------------------------------ */

    public function getCompanyAccounts($includeInactive = true) {
        try {
            $sql = "SELECT * FROM company_accounts";
            if (!$includeInactive) {
                $sql .= " WHERE is_active = 1";
            }
            $sql .= " ORDER BY is_active DESC, method ASC, currency ASC, id ASC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $accounts = $stmt->fetchAll();

            foreach ($accounts as &$account) {
                if ($account['method'] === 'bank') {
                    $account['display'] = $account['bank_name'] . ' • ' . $account['account_number'] . ' (' . $account['account_name'] . ')';
                } else {
                    $account['display'] = $account['currency'] . ' ' . ($account['network'] ?: '') . ' • ' . $account['wallet_address'];
                }
                $account['qr_url'] = $account['qr_code_path'] ? uploadUrl($account['qr_code_path']) : null;
                $account['created_formatted'] = date('M d, Y', strtotime($account['created_at']));
            }

            return $accounts;
        } catch (PDOException $e) {
            logError('Get company accounts error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Create or update a company receiving account.
     */
    public function saveCompanyAccount($data, $id = null) {
        try {
            $label = sanitizeInput(trim($data['label'] ?? ''));
            $method = ($data['method'] ?? '') === 'bank' ? 'bank' : 'crypto';
            $network = strtoupper(sanitizeInput(trim($data['network'] ?? '')));
            $instructions = sanitizeInput(trim($data['instructions'] ?? ''));
            $isActive = !empty($data['is_active']) && $data['is_active'] !== '0' ? 1 : 0;

            if ($label === '') {
                return ['success' => false, 'message' => 'A label is required'];
            }

            if ($method === 'bank') {
                $network = null;
                $bankName = sanitizeInput(trim($data['bank_name'] ?? ''));
                $accountName = sanitizeInput(trim($data['account_name'] ?? ''));
                $accountNumber = preg_replace('/\s+/', '', (string)($data['account_number'] ?? ''));
                $walletAddress = null;

                if ($bankName === '' || $accountName === '' || $accountNumber === '') {
                    return ['success' => false, 'message' => 'Bank name, account name and account number are required'];
                }
                if (!preg_match('/^[0-9]{6,30}$/', $accountNumber)) {
                    return ['success' => false, 'message' => 'Enter a valid account number'];
                }
                $currencyValue = 'NGN';
            } else {
                $currency = strtoupper(sanitizeInput(trim($data['currency'] ?? '')));
                $walletAddress = sanitizeInput(trim($data['wallet_address'] ?? ''));
                $bankName = $accountName = $accountNumber = null;

                if (!in_array($currency, ['BTC', 'ETH', 'USDT', 'LTC', 'XRP'], true)) {
                    return ['success' => false, 'message' => 'Select a supported cryptocurrency'];
                }
                if ($walletAddress === '' || !validateCryptoAddress($walletAddress, $currency, $network ?: null)) {
                    return ['success' => false, 'message' => 'Enter a valid wallet address for the selected network'];
                }
                $currencyValue = $currency;
            }

            // Optional QR code image.
            $qrPath = null;
            if (!empty($data['qr_code']) && is_array($data['qr_code']) && ($data['qr_code']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $upload = saveUploadedFile($data['qr_code'], 'company_qr');
                if (!$upload['success']) {
                    return ['success' => false, 'message' => $upload['message']];
                }
                $qrPath = $upload['path'];
            }

            if ($id) {
                $sql = "UPDATE company_accounts SET
                            label = :label, method = :method, currency = :currency, network = :network,
                            bank_name = :bank_name, account_name = :account_name, account_number = :account_number,
                            wallet_address = :wallet_address, instructions = :instructions, is_active = :is_active,
                            updated_at = NOW()";
                $params = [
                    ':label' => $label,
                    ':method' => $method,
                    ':currency' => $currencyValue,
                    ':network' => $network,
                    ':bank_name' => $bankName,
                    ':account_name' => $accountName,
                    ':account_number' => $accountNumber,
                    ':wallet_address' => $walletAddress,
                    ':instructions' => $instructions !== '' ? $instructions : null,
                    ':is_active' => $isActive,
                    ':id' => $id,
                ];
                if ($qrPath !== null) {
                    $sql .= ", qr_code_path = :qr_code_path";
                    $params[':qr_code_path'] = $qrPath;
                }
                $sql .= " WHERE id = :id";

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);
                $message = 'Receiving account updated';
            } else {
                $sql = "INSERT INTO company_accounts
                            (label, method, currency, network, bank_name, account_name, account_number,
                             wallet_address, qr_code_path, instructions, is_active, created_by)
                        VALUES
                            (:label, :method, :currency, :network, :bank_name, :account_name, :account_number,
                             :wallet_address, :qr_code_path, :instructions, :is_active, :created_by)";

                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':label' => $label,
                    ':method' => $method,
                    ':currency' => $currencyValue,
                    ':network' => $network,
                    ':bank_name' => $bankName,
                    ':account_name' => $accountName,
                    ':account_number' => $accountNumber,
                    ':wallet_address' => $walletAddress,
                    ':qr_code_path' => $qrPath,
                    ':instructions' => $instructions !== '' ? $instructions : null,
                    ':is_active' => $isActive,
                    ':created_by' => $this->adminId,
                ]);
                $message = 'Receiving account created';
            }

            $this->logAdminActivity('company_account_save', $message . ': ' . $label);

            return ['success' => true, 'message' => $message];
        } catch (Exception $e) {
            logError('Save company account error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to save the receiving account'];
        }
    }

    public function toggleCompanyAccount($id) {
        try {
            $stmt = $this->db->prepare("UPDATE company_accounts SET is_active = NOT is_active, updated_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Receiving account not found'];
            }

            $this->logAdminActivity('company_account_toggle', "Toggled receiving account #$id");
            return ['success' => true, 'message' => 'Receiving account status updated'];
        } catch (Exception $e) {
            logError('Toggle company account error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to update the receiving account'];
        }
    }

    /* ------------------------------------------------------------------
     * User payout accounts
     * ------------------------------------------------------------------ */

    public function getPayoutAccounts($page = 1, $limit = 20, $status = '') {
        try {
            $page = max(1, (int)$page);
            $limit = min(100, max(1, (int)$limit));
            $offset = ($page - 1) * $limit;

            $where = "WHERE 1=1";
            $params = [];
            if ($status !== '' && in_array($status, ['pending', 'verified', 'rejected'], true)) {
                $where .= " AND pa.status = :status";
                $params[':status'] = $status;
            }

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM user_payout_accounts pa $where");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();

            $sql = "SELECT pa.*, u.email AS user_email, u.first_name, u.last_name
                    FROM user_payout_accounts pa
                    LEFT JOIN users u ON u.id = pa.user_id
                    $where
                    ORDER BY FIELD(pa.status, 'pending', 'verified', 'rejected'), pa.created_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $accounts = $stmt->fetchAll();
            foreach ($accounts as &$account) {
                $account['user_name'] = trim(($account['first_name'] ?? '') . ' ' . ($account['last_name'] ?? ''));
                $account['created_formatted'] = date('M d, Y H:i', strtotime($account['created_at']));
                if ($account['method'] === 'bank') {
                    $account['display'] = $account['bank_name'] . ' • ' . $account['account_number'] . ' (' . $account['account_name'] . ')';
                } else {
                    $account['display'] = $account['currency'] . ' ' . ($account['network'] ?: '') . ' • ' . $account['wallet_address'];
                }
            }

            return [
                'accounts' => $accounts,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => (int)ceil($total / $limit),
                ],
            ];
        } catch (PDOException $e) {
            logError('Get payout accounts error: ' . $e->getMessage());
            return [
                'accounts' => [],
                'pagination' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'pages' => 0],
            ];
        }
    }

    public function reviewPayoutAccount($accountId, $action, $reason = '') {
        try {
            if (!in_array($action, ['approve', 'reject'], true)) {
                throw new Exception('Invalid action');
            }

            $status = $action === 'approve' ? 'verified' : 'rejected';
            $sql = "UPDATE user_payout_accounts
                    SET status = :status, rejection_reason = :reason,
                        verified_by = :admin_id, verified_at = NOW(), updated_at = NOW()
                    WHERE id = :id AND status = 'pending'";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':status' => $status,
                ':reason' => $action === 'reject' ? sanitizeInput($reason) : null,
                ':admin_id' => $this->adminId,
                ':id' => $accountId,
            ]);

            if ($stmt->rowCount() === 0) {
                return ['success' => false, 'message' => 'Payout account not found or already reviewed'];
            }

            $this->logAdminActivity('payout_account_review', "Payout account #$accountId marked $status");

            return ['success' => true, 'message' => 'Payout account ' . $status];
        } catch (Exception $e) {
            logError('Review payout account error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to review the payout account'];
        }
    }

    /* ------------------------------------------------------------------
     * Transfer requests (deposits and withdrawals)
     * ------------------------------------------------------------------ */

    public function getTransferStatusCounts() {
        try {
            $stmt = $this->db->query(
                "SELECT status, COUNT(*) AS count FROM transfer_requests GROUP BY status"
            );
            $counts = [];
            foreach ($stmt->fetchAll() as $row) {
                $counts[$row['status']] = (int)$row['count'];
            }
            return $counts;
        } catch (PDOException $e) {
            logError('Transfer status counts error: ' . $e->getMessage());
            return [];
        }
    }

    public function getTransferRequests($page = 1, $limit = 20, $status = '', $direction = '') {
        try {
            $page = max(1, (int)$page);
            $limit = min(100, max(1, (int)$limit));
            $offset = ($page - 1) * $limit;

            $where = "WHERE 1=1";
            $params = [];

            if ($status !== '') {
                $where .= " AND t.status = :status";
                $params[':status'] = $status;
            }
            if ($direction !== '' && in_array($direction, ['deposit', 'withdrawal'], true)) {
                $where .= " AND t.direction = :direction";
                $params[':direction'] = $direction;
            }

            $stmt = $this->db->prepare("SELECT COUNT(*) FROM transfer_requests t $where");
            $stmt->execute($params);
            $total = (int)$stmt->fetchColumn();

            $sql = "SELECT t.*, u.email AS user_email, u.first_name, u.last_name,
                           pa.method AS payout_method, pa.bank_name, pa.account_number,
                           pa.wallet_address AS payout_wallet,
                           ca.label AS company_label
                    FROM transfer_requests t
                    LEFT JOIN users u ON u.id = t.user_id
                    LEFT JOIN user_payout_accounts pa ON pa.id = t.user_payout_account_id
                    LEFT JOIN company_accounts ca ON ca.id = t.company_account_id
                    $where
                    ORDER BY FIELD(t.status, 'proof_submitted', 'under_review', 'verified', 'processing', 'paid', 'rejected', 'cancelled', 'awaiting_payment'),
                             t.created_at DESC
                    LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $requests = $stmt->fetchAll();
            foreach ($requests as &$request) {
                $this->formatTransferRow($request);
                $request['user_name'] = trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
            }

            return [
                'requests' => $requests,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => (int)ceil($total / $limit),
                ],
            ];
        } catch (PDOException $e) {
            logError('Get transfer requests error: ' . $e->getMessage());
            return [
                'requests' => [],
                'pagination' => ['total' => 0, 'page' => 1, 'limit' => $limit, 'pages' => 0],
            ];
        }
    }

    public function getTransferRequest($id) {
        try {
            $sql = "SELECT t.*, u.email AS user_email, u.first_name, u.last_name,
                           pa.method AS payout_method, pa.bank_name, pa.account_name, pa.account_number,
                           pa.wallet_address AS payout_wallet, pa.network AS payout_network,
                           ca.label AS company_label, ca.bank_name AS company_bank_name,
                           ca.account_name AS company_account_name, ca.account_number AS company_account_number,
                           ca.wallet_address AS company_wallet, ca.network AS company_network
                    FROM transfer_requests t
                    LEFT JOIN users u ON u.id = t.user_id
                    LEFT JOIN user_payout_accounts pa ON pa.id = t.user_payout_account_id
                    LEFT JOIN company_accounts ca ON ca.id = t.company_account_id
                    WHERE t.id = :id LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $request = $stmt->fetch();

            if (!$request) {
                return null;
            }

            $this->formatTransferRow($request);
            $request['user_name'] = trim(($request['first_name'] ?? '') . ' ' . ($request['last_name'] ?? ''));
            $request['user_email'] = $request['user_email'] ?? '';

            return $request;
        } catch (PDOException $e) {
            logError('Get transfer request error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify or reject a transfer after the user's proof has been checked.
     */
    public function verifyTransferRequest($id, $action, $notes = '') {
        try {
            if (!in_array($action, ['verify', 'reject'], true)) {
                throw new Exception('Invalid action');
            }

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT * FROM transfer_requests WHERE id = :id LIMIT 1 FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $request = $stmt->fetch();

            if (!$request) {
                throw new Exception('Transfer request not found');
            }
            if (!in_array($request['status'], ['proof_submitted', 'under_review'], true)) {
                throw new Exception('This request has already been reviewed');
            }

            $notes = sanitizeInput(trim($notes));
            $adminNote = $notes !== '' ? $notes : null;

            if ($action === 'verify') {
                $stmt = $this->db->prepare(
                    "UPDATE transfer_requests
                     SET status = 'verified', verified_by = :admin_id, verified_at = NOW(),
                         admin_notes = CONCAT_WS('\n', admin_notes, :notes), updated_at = NOW()
                     WHERE id = :id"
                );
                $stmt->execute([':admin_id' => $this->adminId, ':notes' => $adminNote, ':id' => $id]);
                $message = 'Transfer verified. It can now be paid out.';
            } else {
                if ($adminNote === null) {
                    throw new Exception('Provide a reason for rejecting the transfer');
                }

                $stmt = $this->db->prepare(
                    "UPDATE transfer_requests
                     SET status = 'rejected', verified_by = :admin_id, verified_at = NOW(),
                         admin_notes = CONCAT_WS('\n', admin_notes, :notes), updated_at = NOW()
                     WHERE id = :id"
                );
                $stmt->execute([':admin_id' => $this->adminId, ':notes' => $adminNote, ':id' => $id]);

                // Refund withdrawals: the balance was debited when the request was made.
                if ($request['direction'] === 'withdrawal') {
                    $this->refundWithdrawal($request);
                }

                $message = 'Transfer rejected' . ($request['direction'] === 'withdrawal' ? ' and balance refunded' : '');
            }

            $this->db->commit();
            $this->logAdminActivity('transfer_review', "Transfer {$request['reference']} $action");

            return ['success' => true, 'message' => $message];
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            logError('Verify transfer error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Mark a verified transfer as paid: upload the payout proof and, for
     * deposits, credit the user's NGN or USDT wallet.
     */
    public function processTransferRequest($id, $data, $proofFile = null) {
        $uploadedPath = null;

        try {
            $txHash = sanitizeInput(trim($data['admin_tx_hash'] ?? ''));
            $notes = sanitizeInput(trim($data['admin_notes'] ?? ''));

            if (strlen($txHash) < 6) {
                throw new Exception('Enter the payout transaction hash or bank reference');
            }
            if (!$proofFile || ($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                throw new Exception('Upload proof of the payout');
            }

            $upload = saveUploadedFile($proofFile, 'transfer_proofs');
            if (!$upload['success']) {
                throw new Exception($upload['message']);
            }
            $uploadedPath = $upload['path'];

            $this->db->beginTransaction();

            $stmt = $this->db->prepare("SELECT * FROM transfer_requests WHERE id = :id LIMIT 1 FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $request = $stmt->fetch();

            if (!$request) {
                throw new Exception('Transfer request not found');
            }
            if (!in_array($request['status'], ['verified', 'processing'], true)) {
                throw new Exception('Only verified transfers can be processed');
            }

            if ($request['direction'] === 'deposit') {
                $this->creditTransferPayout($request);
            }

            $stmt = $this->db->prepare(
                "UPDATE transfer_requests
                 SET status = 'paid', processed_by = :admin_id, processed_at = NOW(),
                     admin_tx_hash = :tx_hash, admin_proof_path = :proof_path,
                     admin_notes = CONCAT_WS('\n', admin_notes, :notes), updated_at = NOW()
                 WHERE id = :id"
            );
            $stmt->execute([
                ':admin_id' => $this->adminId,
                ':tx_hash' => $txHash,
                ':proof_path' => $uploadedPath,
                ':notes' => $notes !== '' ? $notes : null,
                ':id' => $id,
            ]);

            // Mirror the completed transfer into the general transaction log.
            $transactionId = generateTransactionId($request['direction'] === 'deposit' ? 'DEP' : 'WDR');
            $stmt = $this->db->prepare(
                "INSERT INTO transactions
                     (transaction_id, user_id, type, crypto_symbol, amount, crypto_amount, rate, fee,
                      status, payment_method, notes, processed_by, completed_at)
                 VALUES
                     (:transaction_id, :user_id, :type, :symbol, :amount, :crypto_amount, :rate, :fee,
                      'completed', :payment_method, :notes, :admin_id, NOW())"
            );
            $stmt->execute([
                ':transaction_id' => $transactionId,
                ':user_id' => $request['user_id'],
                ':type' => $request['direction'],
                ':symbol' => $request['crypto_symbol'],
                ':amount' => $request['amount_usd'],
                ':crypto_amount' => $request['payout_amount'],
                ':rate' => $request['rate_used'],
                ':fee' => $request['fee'],
                ':payment_method' => $request['payout_currency'],
                ':notes' => 'Transfer ' . $request['reference'],
                ':admin_id' => $this->adminId,
            ]);

            $this->db->commit();
            $this->logAdminActivity('transfer_process', "Paid transfer {$request['reference']}");

            return ['success' => true, 'message' => 'Transfer marked as paid'];
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
            logError('Process transfer error: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Gift card submission with the decrypted card code for admin review.
     */
    public function getGiftCardSubmissionDetail($id) {
        try {
            $sql = "SELECT s.*, b.brand_name, b.brand_code,
                           u.email AS user_email, u.first_name, u.last_name
                    FROM gift_card_submissions s
                    LEFT JOIN gift_card_brands b ON b.id = s.brand_id
                    LEFT JOIN users u ON u.id = s.user_id
                    WHERE s.id = :id LIMIT 1";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':id' => $id]);
            $submission = $stmt->fetch();

            if (!$submission) {
                return null;
            }

            $submission['card_code_decrypted'] = decryptData($submission['card_code']);
            $submission['card_image_url'] = $submission['card_image_path']
                ? uploadUrl($submission['card_image_path'])
                : null;
            $submission['card_value_formatted'] = formatCurrency($submission['card_value']);
            $submission['payout_amount_formatted'] = formatCurrency($submission['payout_amount']);
            $submission['user_name'] = trim(($submission['first_name'] ?? '') . ' ' . ($submission['last_name'] ?? ''));
            $submission['date_formatted'] = date('M d, Y H:i', strtotime($submission['created_at']));

            return $submission;
        } catch (Exception $e) {
            logError('Get gift card detail error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Private helper methods
     */
    
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
    
    private function formatTransferRow(array &$request) {
        $request['amount_usd_formatted'] = formatCurrency($request['amount_usd']);
        $request['fee_formatted'] = formatCurrency($request['fee']);
        $request['payout_amount_formatted'] = $request['payout_currency'] === 'NGN'
            ? formatNaira($request['payout_amount'])
            : formatCrypto($request['payout_amount'], 'USDT', 2);
        $request['crypto_amount_formatted'] = in_array($request['crypto_symbol'], ['NGN', 'USDT'], true)
            ? ($request['crypto_symbol'] === 'NGN'
                ? formatNaira($request['crypto_amount'])
                : formatCrypto($request['crypto_amount'], 'USDT', 2))
            : formatCrypto($request['crypto_amount'], $request['crypto_symbol'], 8);
        $request['date_formatted'] = date('M d, Y H:i', strtotime($request['created_at']));
        $request['status_label'] = ucwords(str_replace('_', ' ', $request['status']));
        $request['direction_label'] = ucfirst($request['direction']);
        $request['user_proof_url'] = $request['user_proof_path'] ? uploadUrl($request['user_proof_path']) : null;
        $request['admin_proof_url'] = $request['admin_proof_path'] ? uploadUrl($request['admin_proof_path']) : null;
    }

    /**
     * Reverse the wallet/holding movements of an in-flight buy or sell.
     * Must be called inside an open transaction.
     */
    private function reverseTransaction(array $transaction) {
        $userId = (int)$transaction['user_id'];

        if ($transaction['type'] === 'buy_crypto') {
            $cryptoAmount = (float)$transaction['crypto_amount'];
            $usdAmount = (float)$transaction['amount'];

            // Remove the crypto that was credited, then refund the USD paid.
            $stmt = $this->db->prepare(
                "SELECT amount FROM user_crypto_holdings
                 WHERE user_id = :user_id AND crypto_symbol = :symbol LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([':user_id' => $userId, ':symbol' => $transaction['crypto_symbol']]);
            $holding = (float)$stmt->fetchColumn();

            if ($holding + 0.00000001 < $cryptoAmount) {
                throw new Exception('Cannot cancel: the user no longer holds enough ' . $transaction['crypto_symbol']);
            }

            $stmt = $this->db->prepare(
                "UPDATE user_crypto_holdings SET amount = amount - :amount, updated_at = NOW()
                 WHERE user_id = :user_id AND crypto_symbol = :symbol"
            );
            $stmt->execute([':amount' => $cryptoAmount, ':user_id' => $userId, ':symbol' => $transaction['crypto_symbol']]);

            $stmt = $this->db->prepare("SELECT balance FROM users WHERE id = :user_id LIMIT 1 FOR UPDATE");
            $stmt->execute([':user_id' => $userId]);
            $balance = (float)$stmt->fetchColumn();

            $stmt = $this->db->prepare("UPDATE users SET balance = balance + :amount, updated_at = NOW() WHERE id = :user_id");
            $stmt->execute([':amount' => $usdAmount, ':user_id' => $userId]);

            recordLedgerEntry($this->db, $userId, 'USD', 'credit', $usdAmount, $balance + $usdAmount, 'transaction', (int)$transaction['id'], $this->adminId);
            recordLedgerEntry($this->db, $userId, strtoupper($transaction['crypto_symbol']), 'debit', $cryptoAmount, $holding - $cryptoAmount, 'transaction', (int)$transaction['id'], $this->adminId);
        } elseif ($transaction['type'] === 'sell_crypto') {
            $cryptoAmount = (float)$transaction['crypto_amount'];
            $usdAmount = (float)$transaction['amount'];

            $stmt = $this->db->prepare("SELECT balance FROM users WHERE id = :user_id LIMIT 1 FOR UPDATE");
            $stmt->execute([':user_id' => $userId]);
            $balance = (float)$stmt->fetchColumn();

            if ($balance + 0.00000001 < $usdAmount) {
                throw new Exception('Cannot cancel: the user no longer has enough USD balance');
            }

            $stmt = $this->db->prepare("UPDATE users SET balance = balance - :amount, updated_at = NOW() WHERE id = :user_id");
            $stmt->execute([':amount' => $usdAmount, ':user_id' => $userId]);

            $stmt = $this->db->prepare(
                "SELECT amount FROM user_crypto_holdings
                 WHERE user_id = :user_id AND crypto_symbol = :symbol LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([':user_id' => $userId, ':symbol' => $transaction['crypto_symbol']]);
            $holding = (float)$stmt->fetchColumn();

            $stmt = $this->db->prepare(
                "UPDATE user_crypto_holdings SET amount = amount + :amount, updated_at = NOW()
                 WHERE user_id = :user_id AND crypto_symbol = :symbol"
            );
            $stmt->execute([':amount' => $cryptoAmount, ':user_id' => $userId, ':symbol' => $transaction['crypto_symbol']]);

            recordLedgerEntry($this->db, $userId, 'USD', 'debit', $usdAmount, $balance - $usdAmount, 'transaction', (int)$transaction['id'], $this->adminId);
            recordLedgerEntry($this->db, $userId, strtoupper($transaction['crypto_symbol']), 'credit', $cryptoAmount, $holding + $cryptoAmount, 'transaction', (int)$transaction['id'], $this->adminId);
        }
        // Other transaction types (gift cards, deposits, withdrawals) are
        // cancelled through their own review/transfer workflows.
    }

    /**
     * Refund the debited NGN/USDT balance for a rejected withdrawal.
     * Must be called inside an open transaction.
     */
    private function refundWithdrawal(array $request) {
        $userId = (int)$request['user_id'];
        $amount = (float)$request['crypto_amount'];

        if ($amount <= 0) {
            return;
        }

        if ($request['payout_currency'] === 'NGN') {
            $stmt = $this->db->prepare("SELECT balance_ngn FROM users WHERE id = :user_id LIMIT 1 FOR UPDATE");
            $stmt->execute([':user_id' => $userId]);
            $balance = (float)$stmt->fetchColumn();

            $stmt = $this->db->prepare("UPDATE users SET balance_ngn = balance_ngn + :amount, updated_at = NOW() WHERE id = :user_id");
            $stmt->execute([':amount' => $amount, ':user_id' => $userId]);

            recordLedgerEntry($this->db, $userId, 'NGN', 'credit', $amount, $balance + $amount, 'transfer', (int)$request['id'], $this->adminId);
        } else {
            $stmt = $this->db->prepare(
                "SELECT amount FROM user_crypto_holdings
                 WHERE user_id = :user_id AND crypto_symbol = 'USDT' LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([':user_id' => $userId]);
            $balance = (float)$stmt->fetchColumn();

            $stmt = $this->db->prepare(
                "INSERT INTO user_crypto_holdings (user_id, crypto_symbol, amount)
                 VALUES (:user_id, 'USDT', :amount)
                 ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount), updated_at = NOW()"
            );
            $stmt->execute([':user_id' => $userId, ':amount' => $amount]);

            recordLedgerEntry($this->db, $userId, 'USDT', 'credit', $amount, $balance + $amount, 'transfer', (int)$request['id'], $this->adminId);
        }
    }

    /**
     * Credit the user's platform wallet for an approved deposit payout.
     * Must be called inside an open transaction.
     */
    private function creditTransferPayout(array $request) {
        $userId = (int)$request['user_id'];
        $amount = (float)$request['payout_amount'];

        if ($amount <= 0) {
            throw new Exception('The payout amount is invalid');
        }

        if ($request['payout_currency'] === 'NGN') {
            $stmt = $this->db->prepare("SELECT balance_ngn FROM users WHERE id = :user_id LIMIT 1 FOR UPDATE");
            $stmt->execute([':user_id' => $userId]);
            $balance = (float)$stmt->fetchColumn();

            $stmt = $this->db->prepare("UPDATE users SET balance_ngn = balance_ngn + :amount, updated_at = NOW() WHERE id = :user_id");
            $stmt->execute([':amount' => $amount, ':user_id' => $userId]);

            recordLedgerEntry($this->db, $userId, 'NGN', 'credit', $amount, $balance + $amount, 'transfer', (int)$request['id'], $this->adminId);
        } else {
            $stmt = $this->db->prepare(
                "SELECT amount FROM user_crypto_holdings
                 WHERE user_id = :user_id AND crypto_symbol = 'USDT' LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([':user_id' => $userId]);
            $balance = (float)$stmt->fetchColumn();

            $stmt = $this->db->prepare(
                "INSERT INTO user_crypto_holdings (user_id, crypto_symbol, amount)
                 VALUES (:user_id, 'USDT', :amount)
                 ON DUPLICATE KEY UPDATE amount = amount + VALUES(amount), updated_at = NOW()"
            );
            $stmt->execute([':user_id' => $userId, ':amount' => $amount]);

            recordLedgerEntry($this->db, $userId, 'USDT', 'credit', $amount, $balance + $amount, 'transfer', (int)$request['id'], $this->adminId);
        }
    }

    private function generateRevenueReport($startDate, $endDate) {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as transactions,
                    SUM(fee) as revenue
                FROM transactions 
                WHERE status = 'completed' 
                AND created_at BETWEEN :start_date AND :end_date
                GROUP BY DATE(created_at)
                ORDER BY date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        return $stmt->fetchAll();
    }
    
    private function generateUsersReport($startDate, $endDate) {
        $sql = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as new_users,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_users
                FROM users 
                WHERE created_at BETWEEN :start_date AND :end_date
                GROUP BY DATE(created_at)
                ORDER BY date ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        return $stmt->fetchAll();
    }
    
    private function generateTransactionsReport($startDate, $endDate) {
        $sql = "SELECT 
                    type,
                    COUNT(*) as count,
                    SUM(amount) as volume,
                    AVG(amount) as avg_amount
                FROM transactions 
                WHERE created_at BETWEEN :start_date AND :end_date
                GROUP BY type
                ORDER BY volume DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        
        return $stmt->fetchAll();
    }
    
    private function logAdminActivity($action, $description) {
        try {
            $sql = "INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent) 
                    VALUES (:admin_id, :action, :description, :ip_address, :user_agent)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':admin_id' => $this->adminId,
                ':action' => $action,
                ':description' => $description,
                ':ip_address' => getUserIP(),
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            logError('Log admin activity error: ' . $e->getMessage());
        }
    }
}
?>
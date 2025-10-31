<?php
/**
 * Admin Dashboard Functions for FEX Trading Platform
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
                $whereClause .= " AND (first_name LIKE :search OR last_name LIKE :search OR email LIKE :search)";
                $params[':search'] = "%$search%";
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
            $sql = "SELECT t.transaction_id, t.type, t.crypto_symbol, t.gift_card_brand, 
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
            $sql = "SELECT s.*, u.balance, u.id as user_id 
                    FROM gift_card_submissions s
                    LEFT JOIN users u ON s.user_id = u.id
                    WHERE s.id = :submission_id AND s.status = 'pending' LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':submission_id' => $submissionId]);
            $submission = $stmt->fetch();
            
            if (!$submission) {
                throw new Exception('Submission not found or already processed');
            }
            
            if ($action === 'approve') {
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
            $this->db->rollBack();
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
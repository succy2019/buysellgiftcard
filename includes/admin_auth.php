<?php
/**
 * Admin Authentication Functions for FEX Trading Platform
 * Created: October 31, 2025
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';

class AdminAuth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Admin login
     */
    public function login($email, $password) {
        try {
            // Rate limiting check
            $rateLimit = checkRateLimit('admin_login_' . getUserIP(), 5, 900); // 5 attempts per 15 minutes
            if (!$rateLimit['allowed']) {
                return [
                    'success' => false,
                    'message' => 'Too many login attempts. Please try again later.',
                    'locked_until' => date('Y-m-d H:i:s', $rateLimit['reset_time'])
                ];
            }
            
            // Validate input
            if (empty($email) || empty($password)) {
                return [
                    'success' => false,
                    'message' => 'Email and password are required'
                ];
            }
            
            // Get admin by email
            $sql = "SELECT id, username, email, password_hash, role, status 
                    FROM admin_users WHERE email = :email LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':email' => strtolower($email)]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }
            
            // Verify password
            if (!password_verify($password, $admin['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }
            
            // Check account status
            if ($admin['status'] !== 'active') {
                return [
                    'success' => false,
                    'message' => 'Admin account is inactive'
                ];
            }
            
            // Create admin session
            $sessionToken = $this->createAdminSession($admin['id']);
            
            // Update last login
            $this->updateLastLogin($admin['id']);
            
            // Set session variables
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_email'] = $admin['email'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_role'] = $admin['role'];
            $_SESSION['admin_session_token'] = $sessionToken;
            $_SESSION['user_type'] = 'admin';
            
            // Log admin login activity
            $this->logAdminActivity($admin['id'], 'admin_login', 'Admin logged in successfully');
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'admin' => [
                    'id' => $admin['id'],
                    'username' => $admin['username'],
                    'email' => $admin['email'],
                    'role' => $admin['role']
                ]
            ];
            
        } catch (PDOException $e) {
            logError('Admin login error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error. Please try again later.'
            ];
        }
    }
    
    /**
     * Admin logout
     */
    public function logout() {
        try {
            if (isset($_SESSION['admin_id'], $_SESSION['admin_session_token'])) {
                // Remove session from database
                $sql = "DELETE FROM admin_sessions WHERE admin_id = :admin_id AND session_token = :token";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':admin_id' => $_SESSION['admin_id'],
                    ':token' => $_SESSION['admin_session_token']
                ]);
                
                // Log logout activity
                $this->logAdminActivity($_SESSION['admin_id'], 'admin_logout', 'Admin logged out');
            }
            
            // Clear session
            session_destroy();
            session_start();
            
            return [
                'success' => true,
                'message' => 'Logged out successfully'
            ];
            
        } catch (PDOException $e) {
            logError('Admin logout error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Logout error occurred'
            ];
        }
    }
    
    /**
     * Check if admin is logged in
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['admin_id'], $_SESSION['admin_session_token'])) {
            return false;
        }
        
        try {
            // Verify session in database
            $sql = "SELECT id FROM admin_sessions 
                    WHERE admin_id = :admin_id AND session_token = :token 
                    AND expires_at > NOW() LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':admin_id' => $_SESSION['admin_id'],
                ':token' => $_SESSION['admin_session_token']
            ]);
            
            $result = $stmt->fetch();
            
            // If session is valid, extend it
            if ($result) {
                $this->extendSession($_SESSION['admin_session_token']);
                return true;
            }
            
            return false;
            
        } catch (PDOException $e) {
            logError('Admin session check error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current admin data
     */
    public function getCurrentAdmin() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $sql = "SELECT id, username, email, role, status, created_at, last_login 
                    FROM admin_users WHERE id = :admin_id LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':admin_id' => $_SESSION['admin_id']]);
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            logError('Get current admin error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check admin permission
     */
    public function hasPermission($requiredRole = 'admin') {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $roleHierarchy = [
            'moderator' => 1,
            'admin' => 2,
            'super_admin' => 3
        ];
        
        $currentRole = $_SESSION['admin_role'] ?? 'moderator';
        $currentLevel = $roleHierarchy[$currentRole] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 2;
        
        return $currentLevel >= $requiredLevel;
    }
    
    /**
     * Change admin password
     */
    public function changePassword($adminId, $currentPassword, $newPassword) {
        try {
            // Get current password hash
            $sql = "SELECT password_hash FROM admin_users WHERE id = :admin_id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':admin_id' => $adminId]);
            $admin = $stmt->fetch();
            
            if (!$admin) {
                return [
                    'success' => false,
                    'message' => 'Admin not found'
                ];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $admin['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }
            
            // Validate new password
            $passwordValidation = validatePassword($newPassword);
            if (!$passwordValidation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Password requirements not met: ' . implode(', ', $passwordValidation['errors'])
                ];
            }
            
            // Hash new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            
            // Update password
            $sql = "UPDATE admin_users SET password_hash = :password_hash, updated_at = NOW() 
                    WHERE id = :admin_id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':password_hash' => $newPasswordHash,
                ':admin_id' => $adminId
            ]);
            
            if ($result) {
                // Log password change
                $this->logAdminActivity($adminId, 'admin_password_change', 'Admin password changed');
                
                return [
                    'success' => true,
                    'message' => 'Password changed successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to change password'
                ];
            }
            
        } catch (PDOException $e) {
            logError('Admin change password error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error. Please try again later.'
            ];
        }
    }
    
    /**
     * Create new admin user (Super admin only)
     */
    public function createAdmin($adminData) {
        try {
            // Check if current admin has super_admin role
            if (!$this->hasPermission('super_admin')) {
                return [
                    'success' => false,
                    'message' => 'Permission denied. Super admin access required.'
                ];
            }
            
            // Validate input data
            $validation = $this->validateAdminData($adminData);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Check if username/email already exists
            if ($this->adminExists($adminData['username'], $adminData['email'])) {
                return [
                    'success' => false,
                    'message' => 'Username or email already exists'
                ];
            }
            
            // Hash password
            $passwordHash = password_hash($adminData['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            
            // Create admin
            $sql = "INSERT INTO admin_users (username, email, password_hash, role, status) 
                    VALUES (:username, :email, :password_hash, :role, 'active')";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':username' => sanitizeInput($adminData['username']),
                ':email' => strtolower(sanitizeInput($adminData['email'])),
                ':password_hash' => $passwordHash,
                ':role' => $adminData['role'] ?? 'admin'
            ]);
            
            if ($result) {
                $newAdminId = $this->db->lastInsertId();
                
                // Log admin creation
                $this->logAdminActivity($_SESSION['admin_id'], 'admin_created', "Created new admin: {$adminData['username']}");
                
                return [
                    'success' => true,
                    'message' => 'Admin created successfully',
                    'admin_id' => $newAdminId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to create admin'
                ];
            }
            
        } catch (PDOException $e) {
            logError('Create admin error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error. Please try again later.'
            ];
        }
    }
    
    /**
     * Update admin status (Super admin only)
     */
    public function updateAdminStatus($adminId, $status) {
        try {
            if (!$this->hasPermission('super_admin')) {
                return [
                    'success' => false,
                    'message' => 'Permission denied. Super admin access required.'
                ];
            }
            
            if (!in_array($status, ['active', 'inactive'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid status'
                ];
            }
            
            // Don't allow disabling self
            if ($adminId == $_SESSION['admin_id'] && $status === 'inactive') {
                return [
                    'success' => false,
                    'message' => 'Cannot disable your own account'
                ];
            }
            
            $sql = "UPDATE admin_users SET status = :status, updated_at = NOW() WHERE id = :admin_id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':status' => $status,
                ':admin_id' => $adminId
            ]);
            
            if ($result) {
                $this->logAdminActivity($_SESSION['admin_id'], 'admin_status_update', "Updated admin status to: $status");
                
                return [
                    'success' => true,
                    'message' => 'Admin status updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update admin status'
                ];
            }
            
        } catch (PDOException $e) {
            logError('Update admin status error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error. Please try again later.'
            ];
        }
    }
    
    /**
     * Get all admins (Admin+ only)
     */
    public function getAllAdmins() {
        try {
            if (!$this->hasPermission('admin')) {
                return [];
            }
            
            $sql = "SELECT id, username, email, role, status, created_at, last_login 
                    FROM admin_users 
                    ORDER BY created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            $admins = $stmt->fetchAll();
            
            // Format admins
            foreach ($admins as &$admin) {
                $admin['created_date'] = date('M d, Y', strtotime($admin['created_at']));
                $admin['last_login_formatted'] = $admin['last_login'] ? timeAgo($admin['last_login']) : 'Never';
            }
            
            return $admins;
            
        } catch (PDOException $e) {
            logError('Get all admins error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Private helper methods
     */
    
    private function createAdminSession($adminId) {
        try {
            // Generate session token
            $sessionToken = bin2hex(random_bytes(32));
            
            // Set expiration (shorter for admin sessions)
            $expiresAt = date('Y-m-d H:i:s', time() + ADMIN_SESSION_LIFETIME);
            
            // Clean old sessions
            $this->cleanAdminSessions($adminId);
            
            // Insert new session
            $sql = "INSERT INTO admin_sessions (admin_id, session_token, user_agent, ip_address, expires_at) 
                    VALUES (:admin_id, :token, :user_agent, :ip_address, :expires_at)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':admin_id' => $adminId,
                ':token' => $sessionToken,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ':ip_address' => getUserIP(),
                ':expires_at' => $expiresAt
            ]);
            
            return $sessionToken;
            
        } catch (PDOException $e) {
            logError('Create admin session error: ' . $e->getMessage());
            return null;
        }
    }
    
    private function cleanAdminSessions($adminId) {
        try {
            // Remove expired sessions
            $sql = "DELETE FROM admin_sessions WHERE expires_at < NOW()";
            $this->db->prepare($sql)->execute();
            
            // Keep only latest 3 sessions for admin
            $sql = "DELETE FROM admin_sessions 
                    WHERE admin_id = :admin_id 
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM admin_sessions 
                            WHERE admin_id = :admin_id 
                            ORDER BY created_at DESC 
                            LIMIT 3
                        ) as keep_sessions
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':admin_id' => $adminId]);
            
        } catch (PDOException $e) {
            logError('Clean admin sessions error: ' . $e->getMessage());
        }
    }
    
    private function extendSession($sessionToken) {
        try {
            $newExpiration = date('Y-m-d H:i:s', time() + ADMIN_SESSION_LIFETIME);
            
            $sql = "UPDATE admin_sessions SET expires_at = :expires_at WHERE session_token = :token";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':expires_at' => $newExpiration,
                ':token' => $sessionToken
            ]);
            
        } catch (PDOException $e) {
            logError('Extend admin session error: ' . $e->getMessage());
        }
    }
    
    private function updateLastLogin($adminId) {
        try {
            $sql = "UPDATE admin_users SET last_login = NOW() WHERE id = :admin_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':admin_id' => $adminId]);
            
        } catch (PDOException $e) {
            logError('Update admin last login error: ' . $e->getMessage());
        }
    }
    
    private function validateAdminData($data) {
        $errors = [];
        
        // Required fields
        $requiredFields = ['username', 'email', 'password'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst($field) . ' is required';
            }
        }
        
        // Username validation
        if (!empty($data['username'])) {
            if (strlen($data['username']) < 3) {
                $errors[] = 'Username must be at least 3 characters long';
            }
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
                $errors[] = 'Username can only contain letters, numbers, and underscores';
            }
        }
        
        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }
        
        // Password validation
        if (!empty($data['password'])) {
            $passwordValidation = validatePassword($data['password']);
            if (!$passwordValidation['valid']) {
                $errors = array_merge($errors, $passwordValidation['errors']);
            }
        }
        
        // Role validation
        if (!empty($data['role']) && !in_array($data['role'], ['admin', 'moderator'])) {
            $errors[] = 'Invalid role';
        }
        
        return [
            'success' => empty($errors),
            'message' => empty($errors) ? 'Validation passed' : implode(', ', $errors),
            'errors' => $errors
        ];
    }
    
    private function adminExists($username, $email) {
        try {
            $sql = "SELECT id FROM admin_users WHERE username = :username OR email = :email LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':username' => $username,
                ':email' => strtolower($email)
            ]);
            
            return $stmt->fetch() !== false;
            
        } catch (PDOException $e) {
            logError('Admin exists check error: ' . $e->getMessage());
            return false;
        }
    }
    
    private function logAdminActivity($adminId, $action, $description) {
        try {
            $sql = "INSERT INTO activity_logs (admin_id, action, description, ip_address, user_agent) 
                    VALUES (:admin_id, :action, :description, :ip_address, :user_agent)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':admin_id' => $adminId,
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

// Helper function to require admin login
function requireAdminLogin($requiredRole = 'admin') {
    $auth = new AdminAuth();
    if (!$auth->isLoggedIn() || !$auth->hasPermission($requiredRole)) {
        header('Location: admin-login.html');
        exit;
    }
    return $auth->getCurrentAdmin();
}

// Helper function to redirect if admin logged in
function redirectIfAdminLoggedIn($redirectTo = 'admin-dashboard.html') {
    $auth = new AdminAuth();
    if ($auth->isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}
?>
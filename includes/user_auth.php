<?php
/**
 * User Authentication Functions for FEX Trading Platform
 * Created: October 31, 2025
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';

class UserAuth {
    private $db;
    
    public function __construct() {
        $this->db = getDB();
    }
    
    /**
     * Register a new user
     */
    public function register($userData) {
        try {
            // Validate input data
            $validation = $this->validateRegistrationData($userData);
            if (!$validation['success']) {
                return $validation;
            }
            
            // Check if email already exists
            if ($this->emailExists($userData['email'])) {
                return [
                    'success' => false,
                    'message' => 'Email address already registered'
                ];
            }
            
            // Hash password
            $passwordHash = password_hash($userData['password'], PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            
            // Prepare insert statement
            $sql = "INSERT INTO users (first_name, last_name, email, phone, password_hash, country, status) 
                    VALUES (:first_name, :last_name, :email, :phone, :password_hash, :country, 'pending')";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':first_name' => sanitizeInput($userData['first_name']),
                ':last_name' => sanitizeInput($userData['last_name']),
                ':email' => strtolower(sanitizeInput($userData['email'])),
                ':phone' => sanitizeInput($userData['phone']),
                ':password_hash' => $passwordHash,
                ':country' => sanitizeInput($userData['country'])
            ]);
            
            if ($result) {
                $userId = $this->db->lastInsertId();
                
                // Log registration activity
                $this->logActivity($userId, 'user_registration', 'User registered successfully');
                
                return [
                    'success' => true,
                    'message' => 'Registration successful! Please login to continue.',
                    'user_id' => $userId
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Registration failed. Please try again.'
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Registration error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error. Please try again later.'
            ];
        }
    }
    
    /**
     * Login user
     */
    public function login($email, $password, $rememberMe = false) {
        try {
            // Validate input
            if (empty($email) || empty($password)) {
                return [
                    'success' => false,
                    'message' => 'Email and password are required'
                ];
            }
            
            // Get user by email
            $sql = "SELECT id, first_name, last_name, email, password_hash, status, email_verified 
                    FROM users WHERE email = :email LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':email' => strtolower($email)]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }
            
            // Verify password
            if (!password_verify($password, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid email or password'
                ];
            }
            
            // Check account status
            if ($user['status'] !== 'active') {
                $statusMessage = $user['status'] === 'suspended' ? 'Account suspended' : 'Account pending verification';
                return [
                    'success' => false,
                    'message' => $statusMessage
                ];
            }
            
            // Create session
            $sessionToken = $this->createUserSession($user['id'], $rememberMe);
            
            // Update last login
            $this->updateLastLogin($user['id']);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['session_token'] = $sessionToken;
            $_SESSION['user_type'] = 'user';
            
            // Log login activity
            $this->logActivity($user['id'], 'user_login', 'User logged in successfully');
            
            return [
                'success' => true,
                'message' => 'Login successful',
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['first_name'] . ' ' . $user['last_name'],
                    'email' => $user['email']
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error. Please try again later.'
            ];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        try {
            if (isset($_SESSION['user_id'], $_SESSION['session_token'])) {
                // Remove session from database
                $sql = "DELETE FROM user_sessions WHERE user_id = :user_id AND session_token = :token";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':user_id' => $_SESSION['user_id'],
                    ':token' => $_SESSION['session_token']
                ]);
                
                // Log logout activity
                $this->logActivity($_SESSION['user_id'], 'user_logout', 'User logged out');
            }
            
            // Clear session
            session_destroy();
            session_start();
            
            return [
                'success' => true,
                'message' => 'Logged out successfully'
            ];
            
        } catch (PDOException $e) {
            error_log("Logout error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Logout error occurred'
            ];
        }
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id'], $_SESSION['session_token'])) {
            return false;
        }
        
        try {
            // Verify session in database
            $sql = "SELECT id FROM user_sessions 
                    WHERE user_id = :user_id AND session_token = :token 
                    AND expires_at > NOW() LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':token' => $_SESSION['session_token']
            ]);
            
            return $stmt->fetch() !== false;
            
        } catch (PDOException $e) {
            error_log("Session check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        try {
            $sql = "SELECT id, first_name, last_name, email, phone, country, balance, 
                           status, email_verified, created_at, last_login 
                    FROM users WHERE id = :user_id LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $_SESSION['user_id']]);
            
            return $stmt->fetch();
            
        } catch (PDOException $e) {
            error_log("Get current user error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get user data by ID (wrapper method for dashboard compatibility)
     */
    public function getUserData($userId) {
        try {
            $sql = "SELECT id, first_name, last_name, email, phone, country, balance, 
                           status, email_verified, created_at, last_login 
                    FROM users WHERE id = :user_id LIMIT 1";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $user = $stmt->fetch();
            
            if ($user) {
                return [
                    'success' => true,
                    'data' => $user
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Get user data error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Database error occurred'
            ];
        }
    }
    
    /**
     * Change password
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // Get current password hash
            $sql = "SELECT password_hash FROM users WHERE id = :user_id LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }
            
            // Verify current password
            if (!password_verify($currentPassword, $user['password_hash'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }
            
            // Validate new password
            if (strlen($newPassword) < 8) {
                return [
                    'success' => false,
                    'message' => 'New password must be at least 8 characters long'
                ];
            }
            
            // Hash new password
            $newPasswordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            
            // Update password
            $sql = "UPDATE users SET password_hash = :password_hash, updated_at = NOW() 
                    WHERE id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':password_hash' => $newPasswordHash,
                ':user_id' => $userId
            ]);
            
            if ($result) {
                // Log password change
                $this->logActivity($userId, 'password_change', 'Password changed successfully');
                
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
            error_log("Change password error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error. Please try again later.'
            ];
        }
    }
    
    /**
     * Update user profile
     */
    public function updateProfile($userId, $profileData) {
        try {
            $allowedFields = ['first_name', 'last_name', 'phone', 'country'];
            $updateFields = [];
            $params = [':user_id' => $userId];
            
            foreach ($allowedFields as $field) {
                if (isset($profileData[$field]) && !empty($profileData[$field])) {
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = sanitizeInput($profileData[$field]);
                }
            }
            
            if (empty($updateFields)) {
                return [
                    'success' => false,
                    'message' => 'No valid fields to update'
                ];
            }
            
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() 
                    WHERE id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if ($result) {
                $this->logActivity($userId, 'profile_update', 'Profile updated successfully');
                
                return [
                    'success' => true,
                    'message' => 'Profile updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update profile'
                ];
            }
            
        } catch (PDOException $e) {
            error_log("Update profile error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'System error. Please try again later.'
            ];
        }
    }
    
    /**
     * Validate registration data
     */
    private function validateRegistrationData($data) {
        $errors = [];
        
        // Required fields
        $requiredFields = ['first_name', 'last_name', 'email', 'phone', 'password', 'country'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }
        
        // Email validation
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email address';
        }
        
        // Password validation
        if (!empty($data['password']) && strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }
        
        // Password confirmation
        if (!empty($data['password']) && !empty($data['confirm_password'])) {
            if ($data['password'] !== $data['confirm_password']) {
                $errors[] = 'Passwords do not match';
            }
        }
        
        // Phone validation
        if (!empty($data['phone']) && !preg_match('/^[\+]?[0-9\-\(\)\s]+$/', $data['phone'])) {
            $errors[] = 'Invalid phone number format';
        }
        
        return [
            'success' => empty($errors),
            'message' => empty($errors) ? 'Validation passed' : implode(', ', $errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check if email exists
     */
    private function emailExists($email) {
        try {
            $sql = "SELECT id FROM users WHERE email = :email LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':email' => strtolower($email)]);
            
            return $stmt->fetch() !== false;
            
        } catch (PDOException $e) {
            error_log("Email check error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create user session
     */
    private function createUserSession($userId, $rememberMe = false) {
        try {
            // Generate session token
            $sessionToken = bin2hex(random_bytes(32));
            
            // Set expiration
            $expiresAt = $rememberMe ? 
                date('Y-m-d H:i:s', time() + (30 * 24 * 3600)) : // 30 days
                date('Y-m-d H:i:s', time() + SESSION_LIFETIME);   // Default session lifetime
            
            // Clean old sessions for this user (keep only latest 5)
            $this->cleanUserSessions($userId);
            
            // Insert new session
            $sql = "INSERT INTO user_sessions (user_id, session_token, user_agent, ip_address, expires_at) 
                    VALUES (:user_id, :token, :user_agent, :ip_address, :expires_at)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':token' => $sessionToken,
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                ':ip_address' => getUserIP(),
                ':expires_at' => $expiresAt
            ]);
            
            return $sessionToken;
            
        } catch (PDOException $e) {
            error_log("Create session error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Clean old user sessions
     */
    private function cleanUserSessions($userId) {
        try {
            // Remove expired sessions
            $sql = "DELETE FROM user_sessions WHERE expires_at < NOW()";
            $this->db->prepare($sql)->execute();
            
            // Keep only latest 5 sessions for user
            $sql = "DELETE FROM user_sessions 
                    WHERE user_id = :user_id 
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM user_sessions 
                            WHERE user_id = :user_id 
                            ORDER BY created_at DESC 
                            LIMIT 5
                        ) as keep_sessions
                    )";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            
        } catch (PDOException $e) {
            error_log("Clean sessions error: " . $e->getMessage());
        }
    }
    
    /**
     * Update last login time
     */
    private function updateLastLogin($userId) {
        try {
            $sql = "UPDATE users SET last_login = NOW() WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':user_id' => $userId]);
            
        } catch (PDOException $e) {
            error_log("Update last login error: " . $e->getMessage());
        }
    }
    
    /**
     * Log user activity
     */
    private function logActivity($userId, $action, $description) {
        try {
            $sql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                    VALUES (:user_id, :action, :description, :ip_address, :user_agent)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':user_id' => $userId,
                ':action' => $action,
                ':description' => $description,
                ':ip_address' => getUserIP(),
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
            
        } catch (PDOException $e) {
            error_log("Log activity error: " . $e->getMessage());
        }
    }
}

// Helper function to require user login
function requireUserLogin() {
    $auth = new UserAuth();
    if (!$auth->isLoggedIn()) {
        header('Location: login.html');
        exit;
    }
    return $auth->getCurrentUser();
}

// Helper function to redirect if logged in
function redirectIfLoggedIn($redirectTo = 'user-dashboard.html') {
    $auth = new UserAuth();
    if ($auth->isLoggedIn()) {
        header("Location: $redirectTo");
        exit;
    }
}
?>
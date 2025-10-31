<?php
/**
 * Utility Functions for FEX Trading Platform
 * Created: October 31, 2025
 */

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    return $data;
}

/**
 * Get user's real IP address
 */
function getUserIP() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Generate unique transaction ID
 */
function generateTransactionId($prefix = 'TX') {
    return $prefix . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

/**
 * Generate unique submission ID
 */
function generateSubmissionId($prefix = 'GC') {
    return $prefix . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

/**
 * Format currency amount
 */
function formatCurrency($amount, $currency = 'USD', $decimals = 2) {
    if ($currency === 'USD') {
        return '$' . number_format($amount, $decimals);
    }
    
    return number_format($amount, $decimals) . ' ' . $currency;
}

/**
 * Format cryptocurrency amount
 */
function formatCrypto($amount, $symbol, $decimals = 8) {
    return number_format($amount, $decimals) . ' ' . strtoupper($symbol);
}

/**
 * Validate email address
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone number
 */
function isValidPhone($phone) {
    return preg_match('/^[\+]?[0-9\-\(\)\s]+$/', $phone);
}

/**
 * Generate secure random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Validate password strength
 */
function validatePassword($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'strength' => calculatePasswordStrength($password)
    ];
}

/**
 * Calculate password strength
 */
function calculatePasswordStrength($password) {
    $score = 0;
    
    // Length bonus
    $score += min(25, strlen($password) * 2);
    
    // Character variety bonus
    if (preg_match('/[a-z]/', $password)) $score += 10;
    if (preg_match('/[A-Z]/', $password)) $score += 10;
    if (preg_match('/[0-9]/', $password)) $score += 10;
    if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 15;
    
    // Length penalties
    if (strlen($password) < 8) $score -= 20;
    if (strlen($password) < 6) $score -= 30;
    
    $score = max(0, min(100, $score));
    
    if ($score >= 80) return 'Strong';
    if ($score >= 60) return 'Good';
    if ($score >= 40) return 'Fair';
    return 'Weak';
}

/**
 * Time ago function
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}

/**
 * Send JSON response
 */
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Log error message
 */
function logError($message, $context = []) {
    $logMessage = date('Y-m-d H:i:s') . ' - ' . $message;
    
    if (!empty($context)) {
        $logMessage .= ' - Context: ' . json_encode($context);
    }
    
    error_log($logMessage);
}

/**
 * Validate file upload
 */
function validateFileUpload($file) {
    $errors = [];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        switch ($file['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errors[] = 'File is too large';
                break;
            case UPLOAD_ERR_PARTIAL:
                $errors[] = 'File upload was interrupted';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errors[] = 'No file was uploaded';
                break;
            default:
                $errors[] = 'File upload failed';
        }
    }
    
    // Check file size
    if ($file['size'] > UPLOAD_MAX_SIZE) {
        $errors[] = 'File size exceeds maximum allowed size of ' . formatBytes(UPLOAD_MAX_SIZE);
    }
    
    // Check file type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, UPLOAD_ALLOWED_TYPES)) {
        $errors[] = 'File type not allowed. Only images are permitted.';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'mime_type' => $mimeType
    ];
}

/**
 * Format bytes to human readable size
 */
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Save uploaded file
 */
function saveUploadedFile($file, $directory = 'gift_cards') {
    try {
        // Validate file
        $validation = validateFileUpload($file);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'message' => implode(', ', $validation['errors'])
            ];
        }
        
        // Create directory if it doesn't exist
        $uploadDir = UPLOAD_DIR . $directory . '/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = generateRandomString(16) . '.' . $extension;
        $filePath = $uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $directory . '/' . $filename,
                'full_path' => $filePath
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Failed to save uploaded file'
            ];
        }
        
    } catch (Exception $e) {
        logError('File upload error: ' . $e->getMessage());
        return [
            'success' => false,
            'message' => 'File upload failed'
        ];
    }
}

/**
 * Encrypt sensitive data
 */
function encryptData($data, $key = null) {
    if ($key === null) {
        $key = hash('sha256', 'FEX_ENCRYPTION_KEY_2025', true);
    }
    
    $cipher = 'AES-256-CBC';
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivLength);
    
    $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt sensitive data
 */
function decryptData($encryptedData, $key = null) {
    if ($key === null) {
        $key = hash('sha256', 'FEX_ENCRYPTION_KEY_2025', true);
    }
    
    $cipher = 'AES-256-CBC';
    $data = base64_decode($encryptedData);
    $ivLength = openssl_cipher_iv_length($cipher);
    
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    
    return openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Rate limiting check
 */
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) { // 15 minutes
    $file = sys_get_temp_dir() . '/fex_rate_limit_' . md5($identifier);
    
    $attempts = [];
    if (file_exists($file)) {
        $attempts = json_decode(file_get_contents($file), true) ?: [];
    }
    
    // Clean old attempts
    $currentTime = time();
    $attempts = array_filter($attempts, function($timestamp) use ($currentTime, $timeWindow) {
        return ($currentTime - $timestamp) < $timeWindow;
    });
    
    // Check if limit exceeded
    if (count($attempts) >= $maxAttempts) {
        return [
            'allowed' => false,
            'attempts' => count($attempts),
            'reset_time' => min($attempts) + $timeWindow
        ];
    }
    
    // Add current attempt
    $attempts[] = $currentTime;
    file_put_contents($file, json_encode($attempts));
    
    return [
        'allowed' => true,
        'attempts' => count($attempts),
        'remaining' => $maxAttempts - count($attempts)
    ];
}

/**
 * Generate QR code data URL
 */
function generateQRCode($data, $size = 200) {
    // This would typically use a QR code library
    // For now, return a placeholder URL
    return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($data);
}

/**
 * Validate cryptocurrency address
 */
function validateCryptoAddress($address, $currency) {
    switch (strtoupper($currency)) {
        case 'BTC':
            return preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address) || 
                   preg_match('/^bc1[a-z0-9]{39,59}$/', $address);
        
        case 'ETH':
        case 'USDT':
            return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
        
        case 'LTC':
            return preg_match('/^[LM3][a-km-zA-HJ-NP-Z1-9]{26,33}$/', $address) ||
                   preg_match('/^ltc1[a-z0-9]{39,59}$/', $address);
        
        case 'XRP':
            return preg_match('/^r[0-9a-zA-Z]{24,34}$/', $address);
        
        default:
            return false;
    }
}

/**
 * Calculate trading fee
 */
function calculateTradingFee($amount, $feePercentage = null) {
    if ($feePercentage === null) {
        $feePercentage = DEFAULT_TRADING_FEE;
    }
    
    return $amount * $feePercentage;
}

/**
 * Check if maintenance mode is enabled
 */
function isMaintenanceMode() {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'maintenance_mode' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result && $result['setting_value'] == '1';
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get system setting
 */
function getSystemSetting($key, $default = null) {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Set system setting
 */
function setSystemSetting($key, $value, $adminId = null) {
    try {
        $db = getDB();
        
        $sql = "INSERT INTO system_settings (setting_key, setting_value, updated_by) 
                VALUES (:key, :value, :admin_id) 
                ON DUPLICATE KEY UPDATE 
                setting_value = :value, updated_by = :admin_id, updated_at = NOW()";
        
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            ':key' => $key,
            ':value' => $value,
            ':admin_id' => $adminId
        ]);
    } catch (Exception $e) {
        logError('Set system setting error: ' . $e->getMessage());
        return false;
    }
}
?>
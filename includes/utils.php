<?php
/**
 * Utility Functions for DFQ Exchange
 * Created: October 31, 2025
 */

/**
 * Sanitize input data (for storage/display). Use h() when echoing untrusted
 * values directly into HTML.
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }

    $data = trim((string)$data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');

    return $data;
}

/**
 * Escape a value for HTML output.
 */
function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Get the client IP address. Only REMOTE_ADDR is trusted; proxy headers can be
 * spoofed by clients and must not be used for security decisions.
 */
function getUserIP() {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Generate unique transaction ID
 */
function generateTransactionId($prefix = 'TX') {
    return $prefix . date('Ymd') . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Generate unique submission ID
 */
function generateSubmissionId($prefix = 'GC') {
    return $prefix . date('Ymd') . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Format currency amount
 */
function formatCurrency($amount, $currency = 'USD', $decimals = 2) {
    if ($currency === 'USD') {
        return '$' . number_format((float)$amount, $decimals);
    }

    return number_format((float)$amount, $decimals) . ' ' . $currency;
}

/**
 * Format a Naira amount.
 */
function formatNaira($amount, $decimals = 2) {
    return '₦' . number_format((float)$amount, $decimals);
}

/**
 * Format cryptocurrency amount
 */
function formatCrypto($amount, $symbol, $decimals = 8) {
    return number_format((float)$amount, $decimals) . ' ' . strtoupper($symbol);
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
    return bin2hex(random_bytes((int)ceil($length / 2)));
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
 * CSRF token helpers
 */
function csrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . h(csrfToken()) . '">';
}

function verifyCsrfToken($token = null) {
    if (!is_string($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Abort the request unless the current POST contains a valid CSRF token.
 */
function requireCsrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid or expired security token. Please refresh the page and try again.');
    }
}

/**
 * Validate file upload. Returns the detected MIME type and a safe extension.
 */
function validateFileUpload($file) {
    $errors = [];

    if (!is_array($file) || !isset($file['error'])) {
        return [
            'valid' => false,
            'errors' => ['No file was uploaded'],
            'mime_type' => null,
            'extension' => null,
        ];
    }

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

    // Reject anything that did not arrive through PHP's upload handler.
    if (!empty($file['tmp_name']) && !is_uploaded_file($file['tmp_name'])) {
        $errors[] = 'Invalid upload';
    }

    // Check file size
    if (isset($file['size']) && $file['size'] > UPLOAD_MAX_SIZE) {
        $errors[] = 'File size exceeds maximum allowed size of ' . formatBytes(UPLOAD_MAX_SIZE);
    }

    $mimeType = null;
    $extension = null;

    if (empty($errors)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!isset(UPLOAD_ALLOWED_TYPES[$mimeType])) {
            $errors[] = 'File type not allowed. Only JPEG, PNG, GIF and WebP images are permitted.';
        } else {
            $extension = UPLOAD_ALLOWED_TYPES[$mimeType];

            // Validate that the payload really is an image.
            if (@getimagesize($file['tmp_name']) === false) {
                $errors[] = 'The uploaded file is not a valid image';
                $extension = null;
            }
        }
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'mime_type' => $mimeType,
        'extension' => $extension,
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
 * Save uploaded file. The extension is derived from the detected MIME type,
 * never from the client-supplied filename.
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

        // Only allow a safe, single-level directory name.
        $directory = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$directory);
        if ($directory === '') {
            $directory = 'uploads';
        }

        // Create directory if it doesn't exist
        $uploadDir = UPLOAD_DIR . $directory . '/';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            return [
                'success' => false,
                'message' => 'Unable to create the upload directory'
            ];
        }

        // Generate unique filename with a safe extension
        $extension = $validation['extension'];
        $filename = generateRandomString(32) . '.' . $extension;
        $filePath = $uploadDir . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            @chmod($filePath, 0644);
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
 * Public URL for a stored upload path.
 */
function uploadUrl($path) {
    if (empty($path)) {
        return null;
    }
    return 'uploads/' . ltrim(str_replace('\\', '/', $path), '/');
}

/**
 * Encrypt sensitive data
 */
function encryptData($data, $key = null) {
    $key = $key ?? hash('sha256', ENCRYPTION_KEY, true);

    $cipher = 'AES-256-CBC';
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = random_bytes($ivLength);

    $encrypted = openssl_encrypt($data, $cipher, $key, OPENSSL_RAW_DATA, $iv);

    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt sensitive data
 */
function decryptData($encryptedData, $key = null) {
    $key = $key ?? hash('sha256', ENCRYPTION_KEY, true);

    $cipher = 'AES-256-CBC';
    $data = base64_decode($encryptedData, true);
    $ivLength = openssl_cipher_iv_length($cipher);

    if ($data === false || strlen($data) <= $ivLength) {
        return false;
    }

    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);

    return openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);
}

/**
 * Rate limiting check with atomic file locking.
 */
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) {
    $file = sys_get_temp_dir() . '/fex_rate_limit_' . md5($identifier);
    $handle = @fopen($file, 'c+');

    if ($handle === false) {
        // Fail open rather than locking users out if the temp dir is unusable.
        return ['allowed' => true, 'attempts' => 0, 'remaining' => $maxAttempts];
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            return ['allowed' => true, 'attempts' => 0, 'remaining' => $maxAttempts];
        }

        $contents = stream_get_contents($handle);
        $attempts = json_decode($contents ?: '[]', true);
        if (!is_array($attempts)) {
            $attempts = [];
        }

        $currentTime = time();
        $attempts = array_values(array_filter($attempts, function ($timestamp) use ($currentTime, $timeWindow) {
            return is_numeric($timestamp) && ($currentTime - (int)$timestamp) < $timeWindow;
        }));

        if (count($attempts) >= $maxAttempts) {
            flock($handle, LOCK_UN);
            return [
                'allowed' => false,
                'attempts' => count($attempts),
                'reset_time' => (int)min($attempts) + $timeWindow
            ];
        }

        $attempts[] = $currentTime;

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($attempts));
        fflush($handle);
        flock($handle, LOCK_UN);

        return [
            'allowed' => true,
            'attempts' => count($attempts),
            'remaining' => $maxAttempts - count($attempts)
        ];
    } finally {
        fclose($handle);
    }
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
 * Validate cryptocurrency address.
 *
 * @param string      $address
 * @param string      $currency BTC|ETH|USDT|LTC|XRP|BNB
 * @param string|null $network  TRC20|ERC20|BEP20 etc. (used for USDT)
 */
function validateCryptoAddress($address, $currency, $network = null) {
    $address = trim((string)$address);

    switch (strtoupper($currency)) {
        case 'BTC':
            return preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address) ||
                   preg_match('/^bc1[a-z0-9]{39,59}$/', $address);

        case 'ETH':
        case 'BNB':
            return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);

        case 'USDT':
            $network = strtoupper((string)$network);
            if ($network === 'TRC20') {
                return preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address);
            }
            if (in_array($network, ['ERC20', 'BEP20'], true)) {
                return preg_match('/^0x[a-fA-F0-9]{40}$/', $address);
            }
            // Unknown network: accept either supported format.
            return preg_match('/^T[1-9A-HJ-NP-Za-km-z]{33}$/', $address) ||
                   preg_match('/^0x[a-fA-F0-9]{40}$/', $address);

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
        // Admin-configured percentage (e.g. 0.5 = 0.5%); fall back to the default constant.
        $configured = getSystemSetting('trading_fee_percentage', null);
        $feePercentage = ($configured !== null && is_numeric($configured))
            ? max(0, (float)$configured) / 100
            : DEFAULT_TRADING_FEE;
    }

    return $amount * $feePercentage;
}

/**
 * Naira value of 1 USD, set by the admin under Exchange Rates.
 */
function getUsdToNgnRate() {
    $rate = (float)getSystemSetting('usd_to_ngn_rate', 1500);
    return $rate > 0 ? $rate : 1500.0;
}

/**
 * Admin-configurable trade limits in USD, falling back to the defaults.
 */
function getTradeLimits() {
    $min = getSystemSetting('min_trade_amount', null);
    $max = getSystemSetting('max_trade_amount', null);
    return [
        'min' => is_numeric($min) ? (float)$min : MIN_TRADE_AMOUNT,
        'max' => is_numeric($max) ? (float)$max : MAX_TRADE_AMOUNT,
    ];
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

        // Native prepared statements cannot reuse the same named parameter.
        $sql = "INSERT INTO system_settings (setting_key, setting_value, updated_by)
                VALUES (:key_insert, :value_insert, :admin_insert)
                ON DUPLICATE KEY UPDATE
                setting_value = :value_update, updated_by = :admin_update, updated_at = NOW()";

        $stmt = $db->prepare($sql);
        return $stmt->execute([
            ':key_insert' => $key,
            ':value_insert' => $value,
            ':admin_insert' => $adminId,
            ':value_update' => $value,
            ':admin_update' => $adminId
        ]);
    } catch (Exception $e) {
        logError('Set system setting error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Append an immutable ledger entry for a wallet movement.
 */
function recordLedgerEntry($db, $userId, $currency, $direction, $amount, $balanceAfter, $referenceType, $referenceId, $createdBy = null) {
    try {
        $stmt = $db->prepare(
            "INSERT INTO ledger_entries
                (user_id, currency, direction, amount, balance_after, reference_type, reference_id, created_by)
             VALUES
                (:user_id, :currency, :direction, :amount, :balance_after, :reference_type, :reference_id, :created_by)"
        );

        return $stmt->execute([
            ':user_id' => $userId,
            ':currency' => strtoupper($currency),
            ':direction' => $direction === 'credit' ? 'credit' : 'debit',
            ':amount' => abs((float)$amount),
            ':balance_after' => (float)$balanceAfter,
            ':reference_type' => $referenceType,
            ':reference_id' => $referenceId,
            ':created_by' => $createdBy,
        ]);
    } catch (Exception $e) {
        logError('Ledger entry error: ' . $e->getMessage());
        return false;
    }
}

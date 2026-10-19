<?php
/**
 * Password reset service for DFQ Exchange.
 *
 * Tokens are random 256-bit values. Only their SHA-256 hash is stored, so a
 * leaked database row cannot be turned back into a working reset link.
 * Links are single-use and expire after 60 minutes.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/email_templates.php';

class PasswordReset {
    const TOKEN_TTL_MINUTES = 60;
    const MIN_SECONDS_BETWEEN_REQUESTS = 60;

    private $db;

    private static $accountTables = [
        'user' => 'users',
        'admin' => 'admin_users',
    ];

    public function __construct() {
        $this->db = getDB();
    }

    public static function isValidType($type) {
        return is_string($type) && isset(self::$accountTables[$type]);
    }

    public static function genericMessage() {
        return "If an account exists for that email, we've sent a password reset link. "
            . "Please check your inbox and spam folder. The link expires in "
            . self::TOKEN_TTL_MINUTES . " minutes.";
    }

    /**
     * Request a reset link. Always returns the same generic message so the
     * endpoint cannot be used to discover registered email addresses.
     */
    public function requestReset($email, $type = null) {
        $response = ['success' => true, 'message' => self::genericMessage()];

        try {
            $email = strtolower(trim((string)$email));
            if (!isValidEmail($email)) {
                return $response;
            }

            $rateLimit = checkRateLimit(
                'password_reset_' . md5($email) . '_' . getUserIP(),
                5,
                900
            );
            if (!$rateLimit['allowed']) {
                return $response;
            }

            $account = $this->findAccount($email, $type);
            if (!$account) {
                return $response;
            }

            // Do not send another link within the cooldown window.
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM password_resets
                 WHERE account_type = :type AND account_id = :account_id
                   AND created_at > (NOW() - INTERVAL " . self::MIN_SECONDS_BETWEEN_REQUESTS . " SECOND)"
            );
            $stmt->execute([
                ':type' => $account['account_type'],
                ':account_id' => $account['id'],
            ]);
            if ((int)$stmt->fetchColumn() > 0) {
                return $response;
            }

            // Housekeeping: expired tokens are useless.
            $this->db->exec("DELETE FROM password_resets WHERE expires_at < (NOW() - INTERVAL 2 DAY)");

            // Invalidate any earlier unused links for this account.
            $stmt = $this->db->prepare(
                "DELETE FROM password_resets
                 WHERE account_type = :type AND account_id = :account_id AND used_at IS NULL"
            );
            $stmt->execute([
                ':type' => $account['account_type'],
                ':account_id' => $account['id'],
            ]);

            $token = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);

            $stmt = $this->db->prepare(
                "INSERT INTO password_resets
                    (account_type, account_id, email, token_hash, ip_address, expires_at)
                 VALUES
                    (:type, :account_id, :email, :token_hash, :ip,
                     DATE_ADD(NOW(), INTERVAL " . self::TOKEN_TTL_MINUTES . " MINUTE))"
            );
            $stmt->execute([
                ':type' => $account['account_type'],
                ':account_id' => $account['id'],
                ':email' => $account['email'],
                ':token_hash' => $tokenHash,
                ':ip' => substr(getUserIP(), 0, 45),
            ]);

            $baseUrl = rtrim((string)envValue('DFQ_APP_URL', 'http://localhost/dfqexchange'), '/');
            $resetLink = $baseUrl . '/reset-password.php?token=' . urlencode($token)
                . '&type=' . urlencode($account['account_type']);

            $template = emailTemplatePasswordReset(
                $account['display_name'],
                $resetLink,
                self::TOKEN_TTL_MINUTES
            );

            sendAppEmail(
                $account['email'],
                $template['subject'],
                $template['html'],
                $template['text'],
                $account['display_name']
            );

            $this->logResetRequest($account);
        } catch (Throwable $e) {
            // Never reveal whether the account exists or whether mail failed.
            logError('Password reset request error: ' . $e->getMessage());
        }

        return $response;
    }

    /**
     * Validate a reset token and return the matching account, or null.
     */
    public function getValidReset($token, $type) {
        try {
            $token = (string)$token;
            if (!preg_match('/^[a-f0-9]{64}$/', $token) || !self::isValidType($type)) {
                return null;
            }

            $stmt = $this->db->prepare(
                "SELECT * FROM password_resets
                 WHERE token_hash = :token_hash AND account_type = :type
                   AND used_at IS NULL AND expires_at > NOW()
                 LIMIT 1"
            );
            $stmt->execute([
                ':token_hash' => hash('sha256', $token),
                ':type' => $type,
            ]);
            $row = $stmt->fetch();
            if (!$row) {
                return null;
            }

            $account = $this->getAccount($type, (int)$row['account_id']);
            if (!$account) {
                return null;
            }

            return [
                'display_name' => $account['display_name'],
                'email' => $account['email'],
                'account_type' => $type,
                'expires_at' => $row['expires_at'],
            ];
        } catch (Throwable $e) {
            logError('Password reset validation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Set a new password and invalidate every active session for the account.
     */
    public function resetPassword($token, $type, $newPassword) {
        $token = (string)$token;

        if (!preg_match('/^[a-f0-9]{64}$/', $token) || !self::isValidType($type)) {
            return ['success' => false, 'message' => 'This reset link is invalid or has expired.'];
        }

        $passwordValidation = validatePassword($newPassword);
        if (!$passwordValidation['valid']) {
            return ['success' => false, 'message' => implode('. ', $passwordValidation['errors'])];
        }

        $table = self::$accountTables[$type];

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "SELECT * FROM password_resets
                 WHERE token_hash = :token_hash AND account_type = :type
                   AND used_at IS NULL AND expires_at > NOW()
                 LIMIT 1 FOR UPDATE"
            );
            $stmt->execute([
                ':token_hash' => hash('sha256', $token),
                ':type' => $type,
            ]);
            $reset = $stmt->fetch();

            if (!$reset) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'This reset link is invalid or has expired. Please request a new one.'];
            }

            $account = $this->getAccount($type, (int)$reset['account_id']);
            if (!$account) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'This reset link is invalid or has expired. Please request a new one.'];
            }

            $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

            $stmt = $this->db->prepare(
                "UPDATE {$table} SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id"
            );
            $stmt->execute([
                ':password_hash' => $passwordHash,
                ':id' => (int)$reset['account_id'],
            ]);

            $stmt = $this->db->prepare(
                "UPDATE password_resets SET used_at = NOW() WHERE id = :id"
            );
            $stmt->execute([':id' => (int)$reset['id']]);

            $stmt = $this->db->prepare(
                "DELETE FROM password_resets
                 WHERE account_type = :type AND account_id = :account_id AND used_at IS NULL"
            );
            $stmt->execute([
                ':type' => $type,
                ':account_id' => (int)$reset['account_id'],
            ]);

            // Sign the account out everywhere; the old password may be known.
            if ($type === 'user') {
                $stmt = $this->db->prepare("DELETE FROM user_sessions WHERE user_id = :id");
                $stmt->execute([':id' => (int)$reset['account_id']]);
            } else {
                $stmt = $this->db->prepare("DELETE FROM admin_sessions WHERE admin_id = :id");
                $stmt->execute([':id' => (int)$reset['account_id']]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            logError('Password reset error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Unable to reset your password right now. Please try again.'];
        }

        // Best-effort security notification; never block the reset on mail.
        try {
            $template = emailTemplatePasswordChanged($account['display_name']);
            sendAppEmail(
                $account['email'],
                $template['subject'],
                $template['html'],
                $template['text'],
                $account['display_name']
            );
        } catch (Throwable $e) {
            logError('Password changed email error: ' . $e->getMessage());
        }

        return [
            'success' => true,
            'message' => 'Your password has been reset. You can now sign in with your new password.',
        ];
    }

    /* ------------------------------------------------------------------
     * Helpers
     * ------------------------------------------------------------------ */

    private function findAccount($email, $type = null) {
        $types = self::isValidType($type) ? [$type] : ['user', 'admin'];

        foreach ($types as $candidate) {
            $account = $this->findAccountByEmail($candidate, $email);
            if ($account) {
                return $account;
            }
        }

        return null;
    }

    private function findAccountByEmail($type, $email) {
        $table = self::$accountTables[$type];
        $nameColumn = $type === 'admin' ? 'username' : 'first_name';

        $stmt = $this->db->prepare(
            "SELECT id, {$nameColumn} AS display_name, email FROM {$table} WHERE email = :email LIMIT 1"
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        if (!$row) {
            return null;
        }

        return [
            'id' => (int)$row['id'],
            'display_name' => (string)$row['display_name'],
            'email' => (string)$row['email'],
            'account_type' => $type,
        ];
    }

    private function getAccount($type, $id) {
        if (!self::isValidType($type)) {
            return null;
        }

        $table = self::$accountTables[$type];
        $nameColumn = $type === 'admin' ? 'username' : 'first_name';

        $stmt = $this->db->prepare(
            "SELECT id, {$nameColumn} AS display_name, email FROM {$table} WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function logResetRequest($account) {
        try {
            $isUser = $account['account_type'] === 'user';
            $stmt = $this->db->prepare(
                "INSERT INTO activity_logs (user_id, admin_id, action, description, ip_address)
                 VALUES (:user_id, :admin_id, 'password_reset_request', :description, :ip)"
            );
            $stmt->execute([
                ':user_id' => $isUser ? $account['id'] : null,
                ':admin_id' => $isUser ? null : $account['id'],
                ':description' => 'Password reset link requested for ' . $account['email'],
                ':ip' => getUserIP(),
            ]);
        } catch (Throwable $e) {
            logError('Password reset activity log error: ' . $e->getMessage());
        }
    }
}

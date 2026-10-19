<?php
/**
 * Outbound email for DFQ Exchange.
 *
 * Supports three transports, configured through the environment / .env:
 *
 *   DFQ_MAIL_TRANSPORT = smtp | mail | log
 *     smtp - authenticated SMTP (recommended for production)
 *     mail - PHP's mail() function (shared hosting with sendmail)
 *     log  - writes the full message to logs/mail.log (local development)
 *
 * SMTP settings:
 *   DFQ_SMTP_HOST, DFQ_SMTP_PORT, DFQ_SMTP_USER, DFQ_SMTP_PASS,
 *   DFQ_SMTP_SECURE = tls | ssl | none
 *
 * Sender settings:
 *   DFQ_MAIL_FROM, DFQ_MAIL_FROM_NAME, DFQ_MAIL_REPLY_TO, DFQ_SUPPORT_EMAIL
 *
 * This mailer deliberately never throws: callers (registration, payments,
 * password resets) must not fail because an email could not be delivered.
 * Failures are written to the PHP error log and exposed via lastError().
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/utils.php';

class Mailer {
    private $transport;
    private $fromEmail;
    private $fromName;
    private $replyTo;
    private $smtpHost;
    private $smtpPort;
    private $smtpUser;
    private $smtpPass;
    private $smtpSecure;
    private $lastError = '';

    public function __construct() {
        $this->transport = strtolower(trim((string)envValue('DFQ_MAIL_TRANSPORT', 'log')));

        $defaultDomain = $this->defaultDomain();
        $this->fromEmail = trim((string)envValue('DFQ_MAIL_FROM', 'no-reply@' . $defaultDomain));
        $this->fromName = trim((string)envValue('DFQ_MAIL_FROM_NAME', APP_NAME));
        $this->replyTo = trim((string)envValue(
            'DFQ_MAIL_REPLY_TO',
            envValue('DFQ_SUPPORT_EMAIL', 'support@dfqexchange.com.ng')
        ));

        if (!isValidEmail($this->fromEmail)) {
            $this->fromEmail = 'no-reply@' . $defaultDomain;
        }
        if (!isValidEmail($this->replyTo)) {
            $this->replyTo = $this->fromEmail;
        }

        $this->smtpHost = trim((string)envValue('DFQ_SMTP_HOST', ''));
        $this->smtpSecure = strtolower(trim((string)envValue('DFQ_SMTP_SECURE', 'tls')));
        $defaultPort = $this->smtpSecure === 'ssl' ? 465 : 587;
        $this->smtpPort = (int)envValue('DFQ_SMTP_PORT', (string)$defaultPort);
        $this->smtpUser = trim((string)envValue('DFQ_SMTP_USER', ''));
        $this->smtpPass = (string)envValue('DFQ_SMTP_PASS', '');
    }

    public function lastError() {
        return $this->lastError;
    }

    /**
     * Send an HTML email with a plain-text alternative.
     */
    public function send($toEmail, $subject, $htmlBody, $textBody = '', $toName = '') {
        $toEmail = trim((string)$toEmail);

        if (!isValidEmail($toEmail)) {
            $this->lastError = 'Invalid recipient email address';
            logError('Mail not sent: invalid recipient', ['to' => $toEmail]);
            return false;
        }

        try {
            $boundary = 'dfq_' . bin2hex(random_bytes(16));
            $headers = $this->buildHeaders($boundary);
            $body = $this->buildBody($boundary, (string)$htmlBody, (string)$textBody);
            $toHeader = $this->formatAddress($toEmail, $toName);
            $encodedSubject = $this->encodeHeader((string)$subject);

            if ($this->transport === 'smtp') {
                return $this->sendSmtp($toEmail, $toHeader, $encodedSubject, $headers, $body);
            }

            if ($this->transport === 'mail') {
                return $this->sendPhpMail($toHeader, $encodedSubject, $headers, $body);
            }

            return $this->sendToLog($toHeader, $encodedSubject, $headers, $body);
        } catch (Throwable $e) {
            $this->lastError = $e->getMessage();
            logError('Mail error: ' . $e->getMessage(), ['to' => $toEmail, 'subject' => $subject]);
            return false;
        }
    }

    /* ------------------------------------------------------------------
     * Message construction
     * ------------------------------------------------------------------ */

    private function defaultDomain() {
        $host = parse_url((string)envValue('DFQ_APP_URL', 'http://localhost'), PHP_URL_HOST);
        if (!$host || $host === 'localhost') {
            return 'dfqexchange.com.ng';
        }
        return $host;
    }

    private function formatAddress($email, $name = '') {
        $email = str_replace(["\r", "\n", '<', '>'], '', $email);
        $name = trim(str_replace(["\r", "\n", '"'], '', (string)$name));
        if ($name === '') {
            return $email;
        }
        return $this->encodeHeader($name) . ' <' . $email . '>';
    }

    private function encodeHeader($value) {
        $value = trim(str_replace(["\r", "\n"], '', (string)$value));
        if ($value === '') {
            return '';
        }
        if (function_exists('mb_encode_mimeheader')) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }
        if (preg_match('/[\x80-\xFF]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function buildHeaders($boundary) {
        $domain = substr(strrchr($this->fromEmail, '@') ?: '@dfqexchange.com.ng', 1);

        return [
            'From: ' . $this->formatAddress($this->fromEmail, $this->fromName),
            'Reply-To: ' . $this->replyTo,
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'Date: ' . date('r'),
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: DFQ Exchange',
        ];
    }

    private function buildBody($boundary, $htmlBody, $textBody) {
        if (trim($textBody) === '') {
            $textBody = str_replace(
                ["<br>", "<br/>", "<br />", '</p>', '</div>', '</h1>', '</h2>', '</li>'],
                "\n",
                $htmlBody
            );
            $textBody = trim(html_entity_decode(strip_tags($textBody), ENT_QUOTES, 'UTF-8'));
        }

        $textPart = chunk_split(base64_encode($textBody), 76, "\r\n");
        $htmlPart = chunk_split(base64_encode($htmlBody), 76, "\r\n");

        return "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . $textPart
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . $htmlPart
            . "--{$boundary}--\r\n";
    }

    /* ------------------------------------------------------------------
     * Transports
     * ------------------------------------------------------------------ */

    private function sendPhpMail($toHeader, $encodedSubject, array $headers, $body) {
        $ok = @mail($toHeader, $encodedSubject, $body, implode("\r\n", $headers));
        if (!$ok) {
            $this->lastError = 'PHP mail() returned false';
            logError('Mail not sent via mail()', ['to' => $toHeader]);
        }
        return (bool)$ok;
    }

    private function sendToLog($toHeader, $encodedSubject, array $headers, $body) {
        $dir = dirname(__DIR__) . '/logs';
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            $this->lastError = 'Unable to create the logs directory';
            logError('Mail not logged: ' . $this->lastError);
            return false;
        }

        $entry = str_repeat('=', 78) . "\n"
            . 'Date: ' . date('c') . "\n"
            . 'To: ' . $toHeader . "\n"
            . 'Subject: ' . $encodedSubject . "\n"
            . implode("\n", $headers) . "\n\n"
            . $body . "\n";

        $written = @file_put_contents($dir . '/mail.log', $entry, FILE_APPEND | LOCK_EX);
        if ($written === false) {
            $this->lastError = 'Unable to write logs/mail.log';
            logError('Mail not logged: ' . $this->lastError);
            return false;
        }

        return true;
    }

    private function sendSmtp($toEmail, $toHeader, $encodedSubject, array $headers, $body) {
        if ($this->smtpHost === '') {
            $this->lastError = 'DFQ_SMTP_HOST is not configured';
            logError('Mail not sent: ' . $this->lastError);
            return false;
        }

        $scheme = $this->smtpSecure === 'ssl' ? 'ssl://' : 'tcp://';
        $port = $this->smtpPort > 0 ? $this->smtpPort : ($this->smtpSecure === 'ssl' ? 465 : 587);
        $endpoint = $scheme . $this->smtpHost . ':' . $port;

        $errno = 0;
        $errstr = '';
        $fp = @stream_socket_client($endpoint, $errno, $errstr, 20);
        if (!$fp) {
            $this->lastError = "SMTP connection failed: {$errstr} ({$errno})";
            logError($this->lastError);
            return false;
        }

        stream_set_timeout($fp, 20);

        try {
            if (!$this->smtpExpect($fp, null, [220])) {
                return false;
            }

            $ehloName = $_SERVER['SERVER_NAME'] ?? 'localhost';
            if (!$this->smtpExpect($fp, 'EHLO ' . $ehloName, [250])) {
                return false;
            }

            if ($this->smtpSecure === 'tls') {
                if (!$this->smtpExpect($fp, 'STARTTLS', [220])) {
                    return false;
                }
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    $this->lastError = 'Unable to start TLS with the SMTP server';
                    logError($this->lastError);
                    return false;
                }
                if (!$this->smtpExpect($fp, 'EHLO ' . $ehloName, [250])) {
                    return false;
                }
            }

            if ($this->smtpUser !== '') {
                if (!$this->smtpExpect($fp, 'AUTH LOGIN', [334])) {
                    return false;
                }
                if (!$this->smtpExpect($fp, base64_encode($this->smtpUser), [334])) {
                    return false;
                }
                if (!$this->smtpExpect($fp, base64_encode($this->smtpPass), [235])) {
                    return false;
                }
            }

            if (!$this->smtpExpect($fp, 'MAIL FROM:<' . $this->fromEmail . '>', [250])) {
                return false;
            }
            if (!$this->smtpExpect($fp, 'RCPT TO:<' . $toEmail . '>', [250, 251])) {
                return false;
            }
            if (!$this->smtpExpect($fp, 'DATA', [354])) {
                return false;
            }

            $data = 'To: ' . $toHeader . "\r\n"
                . 'Subject: ' . $encodedSubject . "\r\n"
                . implode("\r\n", $headers) . "\r\n\r\n"
                . str_replace("\n", "\r\n", str_replace("\r\n", "\n", $body));

            if (substr($data, -2) !== "\r\n") {
                $data .= "\r\n";
            }
            // Dot-stuffing for lines that start with a period.
            $data = preg_replace('/^\./m', '..', $data);

            fwrite($fp, $data . ".\r\n");
            if (!$this->smtpExpect($fp, null, [250])) {
                return false;
            }

            @fwrite($fp, "QUIT\r\n");
            return true;
        } finally {
            @fclose($fp);
        }
    }

    /**
     * Write a command (if any) and check the SMTP response code.
     */
    private function smtpExpect($fp, $command, array $expectedCodes) {
        if ($command !== null) {
            $written = @fwrite($fp, $command . "\r\n");
            if ($written === false) {
                $this->lastError = 'SMTP write failed';
                logError($this->lastError);
                return false;
            }
        }

        $response = '';
        while (($line = fgets($fp, 515)) !== false) {
            $response .= $line;
            // A space in the fourth position marks the final line of a reply.
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        if ($response === '') {
            $this->lastError = 'SMTP server closed the connection unexpectedly';
            logError($this->lastError);
            return false;
        }

        $code = (int)substr($response, 0, 3);
        if (!in_array($code, $expectedCodes, true)) {
            $this->lastError = 'SMTP error: ' . trim($response);
            logError($this->lastError);
            return false;
        }

        return true;
    }
}

/**
 * Shared Mailer instance for the current request.
 */
function appMailer() {
    static $mailer = null;
    if ($mailer === null) {
        $mailer = new Mailer();
    }
    return $mailer;
}

/**
 * Send an email using the shared Mailer instance.
 */
function sendAppEmail($toEmail, $subject, $htmlBody, $textBody = '', $toName = '') {
    return appMailer()->send($toEmail, $subject, $htmlBody, $textBody, $toName);
}

/**
 * Send an operational notification to the admin/support inbox.
 */
function notifyAdmin($subject, $htmlBody, $textBody = '') {
    $to = trim((string)envValue(
        'DFQ_ADMIN_NOTIFY_EMAIL',
        envValue('DFQ_SUPPORT_EMAIL', 'support@dfqexchange.com.ng')
    ));
    return sendAppEmail($to, $subject, $htmlBody, $textBody, 'DFQ Exchange');
}

function mailerLastError() {
    return appMailer()->lastError();
}

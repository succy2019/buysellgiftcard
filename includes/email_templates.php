<?php
/**
 * Branded transactional email templates for DFQ Exchange.
 *
 * Every template returns:
 *   ['subject' => string, 'html' => string, 'text' => string]
 *
 * Callers pass the result to sendAppEmail() or notifyAdmin().
 */

require_once __DIR__ . '/utils.php';

/**
 * Shared HTML shell. Inline styles are used because email clients strip
 * <style> blocks inconsistently.
 */
function emailLayout($heading, $contentHtml, $preheader = '') {
    $year = date('Y');
    $supportEmail = h((string)envValue('DFQ_SUPPORT_EMAIL', 'support@dfqexchange.com.ng'));
    $appUrl = rtrim((string)envValue('DFQ_APP_URL', 'http://localhost/dfqexchange'), '/');
    $logoUrl = h($appUrl . '/static/assets/img/app-icon-192.png');

    return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>' . h($heading) . '</title>
</head>
<body style="margin:0;padding:0;background:#eef2fb;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;color:#1c2a3a;">
' . ($preheader !== '' ? '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . h($preheader) . '</div>' : '') . '
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#eef2fb;padding:24px 12px;">
  <tr>
    <td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 6px 24px rgba(6,36,137,0.08);">
        <tr>
          <td style="background:#062489;padding:26px 32px;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td style="padding-right:12px;">
                  <img src="' . $logoUrl . '" width="40" height="40" alt="DFQ Exchange" style="display:block;border-radius:10px;">
                </td>
                <td style="color:#ffffff;font-size:20px;font-weight:800;letter-spacing:.3px;">DFQ Exchange</td>
              </tr>
            </table>
          </td>
        </tr>
        <tr>
          <td style="padding:34px 32px 10px 32px;">
            <h1 style="margin:0 0 18px 0;font-size:23px;line-height:1.3;color:#0b1f3a;">' . h($heading) . '</h1>
            <div style="font-size:15px;line-height:1.65;color:#33445c;">' . $contentHtml . '</div>
          </td>
        </tr>
        <tr>
          <td style="padding:18px 32px 32px 32px;">
            <hr style="border:0;border-top:1px solid #e3e9f4;margin:0 0 18px 0;">
            <p style="margin:0;font-size:12.5px;line-height:1.6;color:#7a8aa3;">
              Need help? Email <a href="mailto:' . $supportEmail . '" style="color:#062489;text-decoration:none;font-weight:600;">' . $supportEmail . '</a>.
            </p>
            <p style="margin:8px 0 0 0;font-size:11.5px;color:#9aa8bd;">&copy; ' . $year . ' DFQ Exchange. All rights reserved.</p>
          </td>
        </tr>
      </table>
      <p style="max-width:600px;margin:14px auto 0 auto;font-size:11px;line-height:1.5;color:#9aa8bd;text-align:center;">
        This is an automated message from DFQ Exchange. Please do not reply directly to this email.
      </p>
    </td>
  </tr>
</table>
</body>
</html>';
}

function emailButton($label, $url) {
    return '<p style="margin:26px 0;">'
        . '<a href="' . h($url) . '" style="display:inline-block;background:#062489;color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;padding:13px 26px;border-radius:8px;">'
        . h($label) . '</a></p>';
}

function emailSignoff() {
    return '<p style="margin:24px 0 0 0;">Kind regards,<br><strong>The DFQ Exchange Team</strong></p>';
}

function emailMoney($amount, $currency = 'USD') {
    $currency = strtoupper((string)$currency);
    if ($currency === 'NGN') {
        return formatNaira($amount);
    }
    if ($currency === 'USDT') {
        return formatCrypto($amount, 'USDT', 2);
    }
    return formatCurrency($amount, 'USD');
}

function emailTextShell($heading, $bodyLines) {
    $support = (string)envValue('DFQ_SUPPORT_EMAIL', 'support@dfqexchange.com.ng');
    return $heading . "\n\n"
        . implode("\n\n", $bodyLines)
        . "\n\n--\nDFQ Exchange\nSupport: " . $support
        . "\nThis is an automated message. Please do not reply directly.";
}

/* --------------------------------------------------------------------------
 * Account emails
 * -------------------------------------------------------------------------- */

function emailTemplateWelcome($firstName, $email, $pending = true) {
    $name = trim($firstName) !== '' ? trim($firstName) : 'there';
    $statusLine = $pending
        ? 'Your account has been created and is currently being reviewed. We will email you as soon as it is activated.'
        : 'Your account is ready. You can sign in and start trading.';

    $html = emailLayout(
        'Welcome to DFQ Exchange',
        '<p style="margin:0 0 14px 0;">Hi ' . h($name) . ',</p>'
        . '<p style="margin:0 0 14px 0;">Thanks for creating a DFQ Exchange account for <strong>' . h($email) . '</strong>.</p>'
        . '<p style="margin:0 0 14px 0;">' . h($statusLine) . '</p>'
        . emailButton('Visit DFQ Exchange', rtrim((string)envValue('DFQ_APP_URL', 'http://localhost/dfqexchange'), '/') . '/login.php')
        . '<p style="margin:0;">You can buy and sell Bitcoin, Ethereum, USDT and popular gift cards, with payouts in NGN or USDT.</p>'
        . emailSignoff(),
        'Welcome to DFQ Exchange'
    );

    return [
        'subject' => 'Welcome to DFQ Exchange',
        'html' => $html,
        'text' => emailTextShell('Welcome to DFQ Exchange', [
            'Hi ' . $name . ',',
            'Thanks for creating a DFQ Exchange account for ' . $email . '.',
            $statusLine,
            'Sign in: ' . rtrim((string)envValue('DFQ_APP_URL', 'http://localhost/dfqexchange'), '/') . '/login.php',
        ]),
    ];
}

function emailTemplatePasswordReset($firstName, $resetLink, $minutes = 60) {
    $name = trim($firstName) !== '' ? trim($firstName) : 'there';
    $html = emailLayout(
        'Reset your password',
        '<p style="margin:0 0 14px 0;">Hi ' . h($name) . ',</p>'
        . '<p style="margin:0 0 14px 0;">We received a request to reset the password for your DFQ Exchange account.</p>'
        . emailButton('Reset my password', $resetLink)
        . '<p style="margin:0 0 12px 0;">This link expires in ' . (int)$minutes . ' minutes and can only be used once.</p>'
        . '<p style="margin:0 0 12px 0;font-size:13px;color:#5b6b82;">If the button does not work, copy this address into your browser:<br>'
        . '<a href="' . h($resetLink) . '" style="color:#062489;word-break:break-all;">' . h($resetLink) . '</a></p>'
        . '<p style="margin:0;font-size:13px;color:#5b6b82;">If you did not request a password reset, you can safely ignore this email. Your password will not change.</p>'
        . emailSignoff(),
        'Password reset requested'
    );

    return [
        'subject' => 'Reset your DFQ Exchange password',
        'html' => $html,
        'text' => emailTextShell('Reset your DFQ Exchange password', [
            'Hi ' . $name . ',',
            'We received a request to reset your DFQ Exchange password.',
            'Open this link to choose a new password (expires in ' . (int)$minutes . ' minutes):',
            $resetLink,
            'If you did not request this, ignore this email — your password will not change.',
        ]),
    ];
}

function emailTemplatePasswordChanged($firstName) {
    $name = trim($firstName) !== '' ? trim($firstName) : 'there';
    $support = (string)envValue('DFQ_SUPPORT_EMAIL', 'support@dfqexchange.com.ng');

    $html = emailLayout(
        'Your password was changed',
        '<p style="margin:0 0 14px 0;">Hi ' . h($name) . ',</p>'
        . '<p style="margin:0 0 14px 0;">The password for your DFQ Exchange account was changed successfully on ' . h(date('F j, Y \a\t g:i A')) . '.</p>'
        . '<p style="margin:0 0 14px 0;">If you made this change, no further action is needed. For security, all other active sessions have been signed out.</p>'
        . '<p style="margin:0;">If you did not make this change, contact us immediately at <a href="mailto:' . h($support) . '" style="color:#062489;font-weight:600;">' . h($support) . '</a>.</p>'
        . emailSignoff(),
        'Your password was changed'
    );

    return [
        'subject' => 'Your DFQ Exchange password was changed',
        'html' => $html,
        'text' => emailTextShell('Your DFQ Exchange password was changed', [
            'Hi ' . $name . ',',
            'Your password was changed on ' . date('F j, Y \a\t g:i A') . '.',
            'All other active sessions have been signed out.',
            'If this was not you, contact ' . $support . ' immediately.',
        ]),
    ];
}

function emailTemplateAccountStatus($firstName, $status) {
    $name = trim($firstName) !== '' ? trim($firstName) : 'there';
    $status = strtolower((string)$status);

    if ($status === 'active') {
        $subject = 'Your DFQ Exchange account is active';
        $message = 'Your account has been activated. You can now sign in, trade, and request payouts.';
        $cta = true;
    } elseif ($status === 'suspended') {
        $subject = 'Your DFQ Exchange account has been suspended';
        $message = 'Your account has been suspended and you can no longer sign in. If you believe this is a mistake, please contact support.';
        $cta = false;
    } else {
        $subject = 'Your DFQ Exchange account is under review';
        $message = 'Your account is being reviewed. We will email you once a decision has been made.';
        $cta = false;
    }

    $html = emailLayout(
        $subject,
        '<p style="margin:0 0 14px 0;">Hi ' . h($name) . ',</p>'
        . '<p style="margin:0 0 14px 0;">' . h($message) . '</p>'
        . ($cta ? emailButton('Sign in to DFQ Exchange', rtrim((string)envValue('DFQ_APP_URL', 'http://localhost/dfqexchange'), '/') . '/login.php') : '')
        . emailSignoff(),
        $subject
    );

    return [
        'subject' => $subject,
        'html' => $html,
        'text' => emailTextShell($subject, ['Hi ' . $name . ',', $message]),
    ];
}

/* --------------------------------------------------------------------------
 * Gift card emails
 * -------------------------------------------------------------------------- */

function emailTemplateGiftCardReceived($firstName, $brand, $cardValue, $payout, $submissionId) {
    $name = trim($firstName) !== '' ? trim($firstName) : 'there';

    $html = emailLayout(
        'We received your gift card',
        '<p style="margin:0 0 14px 0;">Hi ' . h($name) . ',</p>'
        . '<p style="margin:0 0 14px 0;">Your <strong>' . h($brand) . '</strong> gift card has been submitted for review.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;background:#f5f8ff;border-radius:10px;">'
        . '<tr><td style="padding:16px 18px;font-size:14px;line-height:1.8;color:#33445c;">'
        . '<strong>Reference:</strong> ' . h($submissionId) . '<br>'
        . '<strong>Card value:</strong> ' . h(formatCurrency($cardValue)) . '<br>'
        . '<strong>Expected payout:</strong> ' . h(formatCurrency($payout))
        . '</td></tr></table>'
        . '<p style="margin:0 0 14px 0;">Most reviews are completed within 24 hours. Once approved, the payout is credited to your USD wallet.</p>'
        . emailSignoff(),
        'Gift card received'
    );

    return [
        'subject' => 'We received your ' . $brand . ' gift card',
        'html' => $html,
        'text' => emailTextShell('We received your ' . $brand . ' gift card', [
            'Hi ' . $name . ',',
            'Reference: ' . $submissionId,
            'Card value: ' . formatCurrency($cardValue),
            'Expected payout: ' . formatCurrency($payout),
            'Most reviews are completed within 24 hours.',
        ]),
    ];
}

function emailTemplateGiftCardReviewed($firstName, $brand, $approved, $payout, $reason = '') {
    $name = trim($firstName) !== '' ? trim($firstName) : 'there';

    if ($approved) {
        $subject = 'Gift card approved — payment credited';
        $message = 'Your <strong>' . h($brand) . '</strong> gift card has been approved. '
            . h(formatCurrency($payout)) . ' has been credited to your USD wallet.';
    } else {
        $subject = 'Gift card submission rejected';
        $message = 'Unfortunately, your <strong>' . h($brand) . '</strong> gift card submission could not be approved.';
    }

    $html = emailLayout(
        $subject,
        '<p style="margin:0 0 14px 0;">Hi ' . h($name) . ',</p>'
        . '<p style="margin:0 0 14px 0;">' . $message . '</p>'
        . (!$approved && trim((string)$reason) !== ''
            ? '<p style="margin:0 0 14px 0;padding:12px 15px;background:#fff4f4;border-left:3px solid #d9534f;border-radius:6px;"><strong>Reason:</strong> ' . h($reason) . '</p>'
            : '')
        . ($approved ? emailButton('View my wallet', rtrim((string)envValue('DFQ_APP_URL', 'http://localhost/dfqexchange'), '/') . '/user-dashboard.php') : '')
        . emailSignoff(),
        $subject
    );

    $textLines = ['Hi ' . $name . ','];
    $textLines[] = $approved
        ? 'Your ' . $brand . ' gift card was approved. ' . formatCurrency($payout) . ' has been credited to your USD wallet.'
        : 'Your ' . $brand . ' gift card submission was rejected.';
    if (!$approved && trim((string)$reason) !== '') {
        $textLines[] = 'Reason: ' . $reason;
    }

    return [
        'subject' => $subject,
        'html' => $html,
        'text' => emailTextShell($subject, $textLines),
    ];
}

/* --------------------------------------------------------------------------
 * Transfer emails
 * -------------------------------------------------------------------------- */

function emailTemplateTransferReceived($firstName, $reference, $direction, $payoutAmount, $payoutCurrency) {
    $name = trim($firstName) !== '' ? trim($firstName) : 'there';
    $isDeposit = strtolower((string)$direction) === 'deposit';
    $subject = 'Transfer request received — ' . $reference;
    $label = $isDeposit ? 'crypto deposit' : 'withdrawal request';

    $html = emailLayout(
        'Transfer request received',
        '<p style="margin:0 0 14px 0;">Hi ' . h($name) . ',</p>'
        . '<p style="margin:0 0 14px 0;">We received your ' . h($label) . '. It is now queued for review by our team.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;background:#f5f8ff;border-radius:10px;">'
        . '<tr><td style="padding:16px 18px;font-size:14px;line-height:1.8;color:#33445c;">'
        . '<strong>Reference:</strong> ' . h($reference) . '<br>'
        . '<strong>Expected payout:</strong> ' . h(emailMoney($payoutAmount, $payoutCurrency)) . '<br>'
        . '<strong>Status:</strong> Under review'
        . '</td></tr></table>'
        . '<p style="margin:0 0 14px 0;">You can follow the progress from your dashboard. We will email you after each review stage.</p>'
        . emailSignoff(),
        'Transfer request received'
    );

    return [
        'subject' => $subject,
        'html' => $html,
        'text' => emailTextShell($subject, [
            'Hi ' . $name . ',',
            'We received your ' . $label . '.',
            'Reference: ' . $reference,
            'Expected payout: ' . emailMoney($payoutAmount, $payoutCurrency),
            'Status: Under review',
        ]),
    ];
}

function emailTemplateTransferReviewed($firstName, $reference, $approved, $notes = '') {
    $name = trim($firstName) !== '' ? trim($firstName) : 'there';
    $subject = $approved
        ? 'Transfer verified — ' . $reference
        : 'Transfer request rejected — ' . $reference;

    $message = $approved
        ? 'Your transfer has been verified and is now awaiting payout.'
        : 'Your transfer request could not be verified and has been rejected. Withdrawals are refunded to your wallet.';

    $html = emailLayout(
        $subject,
        '<p style="margin:0 0 14px 0;">Hi ' . h($name) . ',</p>'
        . '<p style="margin:0 0 14px 0;">' . h($message) . '</p>'
        . '<p style="margin:0 0 14px 0;"><strong>Reference:</strong> ' . h($reference) . '</p>'
        . (trim((string)$notes) !== ''
            ? '<p style="margin:0 0 14px 0;padding:12px 15px;background:#f5f8ff;border-left:3px solid #062489;border-radius:6px;"><strong>Note from our team:</strong> ' . h($notes) . '</p>'
            : '')
        . emailSignoff(),
        $subject
    );

    $textLines = ['Hi ' . $name . ',', $message, 'Reference: ' . $reference];
    if (trim((string)$notes) !== '') {
        $textLines[] = 'Note: ' . $notes;
    }

    return [
        'subject' => $subject,
        'html' => $html,
        'text' => emailTextShell($subject, $textLines),
    ];
}

function emailTemplateTransferPaid($firstName, $reference, $payoutAmount, $payoutCurrency) {
    $name = trim($firstName) !== '' ? trim($firstName) : 'there';
    $subject = 'Payout sent — ' . $reference;

    $html = emailLayout(
        'Your payout has been sent',
        '<p style="margin:0 0 14px 0;">Hi ' . h($name) . ',</p>'
        . '<p style="margin:0 0 14px 0;">Your transfer has been processed and the payout has been sent.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 16px 0;background:#f2fbf5;border-radius:10px;">'
        . '<tr><td style="padding:16px 18px;font-size:14px;line-height:1.8;color:#33445c;">'
        . '<strong>Reference:</strong> ' . h($reference) . '<br>'
        . '<strong>Amount:</strong> ' . h(emailMoney($payoutAmount, $payoutCurrency)) . '<br>'
        . '<strong>Status:</strong> Paid'
        . '</td></tr></table>'
        . '<p style="margin:0 0 14px 0;">If you have not received the funds, contact support with your reference number.</p>'
        . emailSignoff(),
        'Payout sent'
    );

    return [
        'subject' => $subject,
        'html' => $html,
        'text' => emailTextShell($subject, [
            'Hi ' . $name . ',',
            'Your transfer has been processed and the payout has been sent.',
            'Reference: ' . $reference,
            'Amount: ' . emailMoney($payoutAmount, $payoutCurrency),
            'Status: Paid',
        ]),
    ];
}

function emailTemplatePayoutAccountReviewed($firstName, $accountLabel, $approved, $reason = '') {
    $name = trim($firstName) !== '' ? trim($firstName) : 'there';
    $subject = $approved ? 'Payout account verified' : 'Payout account rejected';

    $html = emailLayout(
        $subject,
        '<p style="margin:0 0 14px 0;">Hi ' . h($name) . ',</p>'
        . '<p style="margin:0 0 14px 0;">Your payout account <strong>' . h($accountLabel) . '</strong> has been '
        . ($approved ? 'verified. You can now select it for payouts.' : 'rejected.') . '</p>'
        . (!$approved && trim((string)$reason) !== ''
            ? '<p style="margin:0 0 14px 0;padding:12px 15px;background:#fff4f4;border-left:3px solid #d9534f;border-radius:6px;"><strong>Reason:</strong> ' . h($reason) . '</p>'
            : '')
        . emailSignoff(),
        $subject
    );

    $textLines = [
        'Hi ' . $name . ',',
        'Your payout account ' . $accountLabel . ' has been ' . ($approved ? 'verified.' : 'rejected.'),
    ];
    if (!$approved && trim((string)$reason) !== '') {
        $textLines[] = 'Reason: ' . $reason;
    }

    return [
        'subject' => $subject,
        'html' => $html,
        'text' => emailTextShell($subject, $textLines),
    ];
}

/* --------------------------------------------------------------------------
 * Admin notifications
 * -------------------------------------------------------------------------- */

function emailTemplateAdminNewUser($firstName, $lastName, $email, $phone = '', $country = '') {
    $fullName = trim($firstName . ' ' . $lastName);
    $subject = 'New account registration — ' . $fullName;

    $html = emailLayout(
        'New account registration',
        '<p style="margin:0 0 14px 0;">A new user has registered and is awaiting activation.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f8ff;border-radius:10px;">'
        . '<tr><td style="padding:16px 18px;font-size:14px;line-height:1.8;color:#33445c;">'
        . '<strong>Name:</strong> ' . h($fullName) . '<br>'
        . '<strong>Email:</strong> ' . h($email) . '<br>'
        . '<strong>Phone:</strong> ' . h($phone !== '' ? $phone : '—') . '<br>'
        . '<strong>Country:</strong> ' . h($country !== '' ? $country : '—')
        . '</td></tr></table>'
        . emailButton('Review users', rtrim((string)envValue('DFQ_APP_URL', 'http://localhost/dfqexchange'), '/') . '/admin-dashboard.php'),
        'New registration'
    );

    return [
        'subject' => $subject,
        'html' => $html,
        'text' => emailTextShell($subject, [
            'Name: ' . $fullName,
            'Email: ' . $email,
            'Phone: ' . ($phone !== '' ? $phone : '—'),
            'Country: ' . ($country !== '' ? $country : '—'),
            'Status: pending activation',
        ]),
    ];
}

function emailTemplateAdminNewGiftCard($submissionId, $brand, $cardValue, $payout, $userName, $userEmail) {
    $subject = 'New gift card submission — ' . $brand;

    $html = emailLayout(
        'New gift card submission',
        '<p style="margin:0 0 14px 0;">A gift card is waiting for review.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f8ff;border-radius:10px;">'
        . '<tr><td style="padding:16px 18px;font-size:14px;line-height:1.8;color:#33445c;">'
        . '<strong>Reference:</strong> ' . h($submissionId) . '<br>'
        . '<strong>Brand:</strong> ' . h($brand) . '<br>'
        . '<strong>Card value:</strong> ' . h(formatCurrency($cardValue)) . '<br>'
        . '<strong>Payout:</strong> ' . h(formatCurrency($payout)) . '<br>'
        . '<strong>User:</strong> ' . h($userName) . ' (' . h($userEmail) . ')'
        . '</td></tr></table>'
        . emailButton('Review submissions', rtrim((string)envValue('DFQ_APP_URL', 'http://localhost/dfqexchange'), '/') . '/admin-dashboard.php'),
        'New gift card submission'
    );

    return [
        'subject' => $subject,
        'html' => $html,
        'text' => emailTextShell($subject, [
            'Reference: ' . $submissionId,
            'Brand: ' . $brand,
            'Card value: ' . formatCurrency($cardValue),
            'Payout: ' . formatCurrency($payout),
            'User: ' . $userName . ' (' . $userEmail . ')',
        ]),
    ];
}

function emailTemplateAdminNewTransfer($reference, $direction, $userName, $userEmail, $payoutAmount, $payoutCurrency) {
    $label = strtolower((string)$direction) === 'deposit' ? 'deposit' : 'withdrawal';
    $subject = 'New ' . $label . ' request — ' . $reference;

    $html = emailLayout(
        'New ' . ucfirst($label) . ' request',
        '<p style="margin:0 0 14px 0;">A ' . h($label) . ' request is waiting for review.</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f8ff;border-radius:10px;">'
        . '<tr><td style="padding:16px 18px;font-size:14px;line-height:1.8;color:#33445c;">'
        . '<strong>Reference:</strong> ' . h($reference) . '<br>'
        . '<strong>Type:</strong> ' . h(ucfirst($label)) . '<br>'
        . '<strong>Payout:</strong> ' . h(emailMoney($payoutAmount, $payoutCurrency)) . '<br>'
        . '<strong>User:</strong> ' . h($userName) . ' (' . h($userEmail) . ')'
        . '</td></tr></table>'
        . emailButton('Review transfers', rtrim((string)envValue('DFQ_APP_URL', 'http://localhost/dfqexchange'), '/') . '/admin-dashboard.php'),
        'New ' . $label . ' request'
    );

    return [
        'subject' => $subject,
        'html' => $html,
        'text' => emailTextShell($subject, [
            'Reference: ' . $reference,
            'Type: ' . ucfirst($label),
            'Payout: ' . emailMoney($payoutAmount, $payoutCurrency),
            'User: ' . $userName . ' (' . $userEmail . ')',
        ]),
    ];
}

<?php
/**
 * Consume a password reset token and set a new password.
 */
require_once 'includes/password_reset.php';

$token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$type = (string)($_GET['type'] ?? $_POST['type'] ?? 'user');
if (!PasswordReset::isValidType($type)) {
    $type = 'user';
}

$service = new PasswordReset();
$reset = $service->getValidReset($token, $type);

$error = '';
$success = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!$reset) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
    } elseif (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif ($password !== $confirm) {
        $error = 'The two passwords do not match.';
    } else {
        $result = $service->resetPassword($token, $type, $password);
        if ($result['success']) {
            $success = $result['message'];
            $done = true;
        } else {
            $error = $result['message'];
        }
    }
}

$loginUrl = $type === 'admin' ? 'admin-login.php' : 'login.php';
$loginLabel = $type === 'admin' ? 'Sign in to the admin panel' : 'Sign in to your account';
$displayName = $reset['display_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#101828">
    <meta name="robots" content="noindex">
    <title>Reset Password - DFQ Exchange</title>
    <link rel="apple-touch-icon" href="static/assets/img/apple-touch-icon.png">
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            padding: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1c2a3a;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background:
                radial-gradient(120% 75% at 50% 0%, rgba(79, 140, 255, 0.35) 0%, rgba(79, 140, 255, 0) 62%),
                linear-gradient(165deg, #04144f 0%, #062489 48%, #0a2ea8 100%);
            -webkit-font-smoothing: antialiased;
        }
        .card {
            width: 100%;
            max-width: 430px;
            background: #ffffff;
            border-radius: 18px;
            padding: 34px 30px 30px;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.35);
        }
        .brand { text-align: center; margin-bottom: 24px; }
        .brand img {
            width: 62px; height: 62px; border-radius: 16px; display: block; margin: 0 auto 14px;
            box-shadow: 0 10px 26px rgba(6, 36, 137, 0.25);
        }
        .brand-name { font-size: 20px; font-weight: 800; color: #062489; letter-spacing: .3px; }
        .brand-name span { font-weight: 300; }
        h1 { margin: 0 0 8px; font-size: 23px; color: #0b1f3a; }
        .lead { margin: 0 0 22px; font-size: 14px; line-height: 1.6; color: #5b6b82; }
        label { display: block; margin: 0 0 7px; font-size: 13px; font-weight: 600; color: #33445c; }
        input[type="password"] {
            width: 100%; padding: 13px 14px; font-size: 15px; color: #1c2a3a;
            background: #f8faff; border: 1px solid #d8e0ef; border-radius: 10px; outline: none;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        input[type="password"]:focus { border-color: #062489; box-shadow: 0 0 0 3px rgba(6, 36, 137, 0.14); }
        .field { margin-bottom: 16px; }
        .hint { margin: 9px 0 0; font-size: 12px; line-height: 1.55; color: #8a98ad; }
        button, .button {
            display: block; width: 100%; margin-top: 20px; padding: 14px 16px; border: 0; border-radius: 10px;
            background: #062489; color: #fff; font-size: 15px; font-weight: 700; cursor: pointer;
            text-align: center; text-decoration: none;
        }
        button:hover, .button:hover { background: #0a2ea8; }
        .alert {
            margin: 0 0 18px; padding: 13px 15px; border-radius: 10px;
            font-size: 13.5px; line-height: 1.55;
        }
        .alert-danger { background: #fdecec; color: #a12b2b; border: 1px solid #f5c6c6; }
        .alert-success { background: #edf9f0; color: #1e6b36; border: 1px solid #c6ecd2; }
        .back { margin: 20px 0 0; text-align: center; font-size: 13.5px; }
        .back a { color: #062489; font-weight: 600; text-decoration: none; }
        .back a:hover { text-decoration: underline; }
        .support { margin: 14px 0 0; text-align: center; font-size: 12px; color: #8a98ad; }
        .support a { color: #062489; text-decoration: none; }
    </style>
    <link rel="stylesheet" href="static/assets/css/dfq-app.css">
</head>
<body class="dfq-auth">
    <main class="card">
        <div class="brand">
            <img src="static/assets/img/app-icon-192.png" alt="DFQ Exchange">
            <div class="brand-name">DFQ <span>Exchange</span></div>
        </div>

        <?php if ($done): ?>
            <h1>Password updated</h1>
            <div class="alert alert-success" role="status"><?php echo h($success); ?></div>
            <p class="lead">For your security, all other active sessions have been signed out.</p>
            <a class="button" href="<?php echo h($loginUrl); ?>"><?php echo h($loginLabel); ?></a>
        <?php elseif (!$reset): ?>
            <h1>Link expired</h1>
            <div class="alert alert-danger" role="alert">
                This password reset link is invalid, expired, or has already been used.
                Reset links can only be used once and expire after <?php echo PasswordReset::TOKEN_TTL_MINUTES; ?> minutes.
            </div>
            <a class="button" href="forgot-password.php<?php echo $type === 'admin' ? '?type=admin' : ''; ?>">Request a new link</a>
            <p class="back"><a href="<?php echo h($loginUrl); ?>">&larr; <?php echo h($loginLabel); ?></a></p>
        <?php else: ?>
            <h1>Choose a new password</h1>
            <p class="lead">
                <?php echo $displayName !== '' ? 'Hi ' . h($displayName) . ', ' : ''; ?>
                enter a new password for your DFQ Exchange account.
            </p>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" role="alert"><?php echo h($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="reset-password.php">
                <?php echo csrfField(); ?>
                <input type="hidden" name="token" value="<?php echo h($token); ?>">
                <input type="hidden" name="type" value="<?php echo h($type); ?>">

                <div class="field">
                    <label for="password">New password</label>
                    <input type="password" id="password" name="password" autocomplete="new-password"
                           minlength="8" required>
                </div>
                <div class="field">
                    <label for="confirm_password">Confirm new password</label>
                    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password"
                           minlength="8" required>
                </div>

                <p class="hint">At least 8 characters, including an uppercase letter, a lowercase letter and a number.</p>
                <button type="submit">Update password</button>
            </form>

            <p class="back"><a href="<?php echo h($loginUrl); ?>">&larr; <?php echo h($loginLabel); ?></a></p>
        <?php endif; ?>

        <p class="support">Need help? <a href="mailto:<?php echo h(envValue('DFQ_SUPPORT_EMAIL', 'support@dfqexchange.com.ng')); ?>">Contact support</a></p>
    </main>
</body>
</html>

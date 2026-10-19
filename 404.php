<?php
require_once __DIR__ . '/includes/public_layout.php';
$code = (int)($_GET['code'] ?? 404);
$errors = [
    403 => ['Access denied', "You don't have permission to open this page."],
    404 => ['Page not found', "The page you're looking for doesn't exist or has moved."],
    500 => ['Something went wrong', 'We hit an unexpected problem. Please try again in a moment.'],
];
if (!isset($errors[$code])) { $code = 404; }
http_response_code($code);
[$heading, $text] = $errors[$code];
dfqPublicHeader($heading);
?>
<section class="pub-error">
    <div class="pub-error__code"><?php echo $code; ?></div>
    <h1><?php echo htmlspecialchars($heading); ?></h1>
    <p><?php echo htmlspecialchars($text); ?></p>
    <div class="actions">
        <a class="pub-btn" href="<?php echo htmlspecialchars(dfqBase()); ?>user-dashboard.php">Go to dashboard</a>
        <a class="pub-btn pub-btn--ghost" href="<?php echo htmlspecialchars(dfqBase()); ?>index.html">Home</a>
    </div>
</section>
<?php dfqPublicFooter(); ?>

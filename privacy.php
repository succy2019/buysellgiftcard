<?php
require_once __DIR__ . '/includes/public_layout.php';
dfqPublicHeader('Privacy Policy', 'How DFQ Exchange collects, uses and protects your data.');
?>
<h1>Privacy Policy</h1>
<p class="pub-meta">Last updated: <?php echo date('F j, Y', filemtime(__FILE__)); ?></p>
<div class="pub-card">
    <h2>What we collect</h2>
    <p>Account details (name, email, phone), identity and payout details you add (bank or wallet addresses), transaction and ledger records, gift card images and codes you submit, proofs of payment you upload, support messages, and technical data such as IP address and browser for security and fraud prevention.</p>
    <h2>How we use it</h2>
    <p>To run your account, process trades, gift cards, deposits and withdrawals, verify payouts, prevent fraud and abuse, meet legal obligations, provide support, and improve the service.</p>
    <h2>Sharing</h2>
    <p>We do not sell your personal data. We share it only with payment and infrastructure providers needed to deliver the service, and with authorities when the law requires.</p>
    <h2>Security</h2>
    <p>Passwords are stored hashed, sessions are protected, sensitive fields are encrypted, and forms are protected against cross-site request forgery. No system is perfectly secure, so use a strong unique password.</p>
    <h2>Retention</h2>
    <p>We keep financial records for as long as required by law and for legitimate business purposes, then delete or anonymise them.</p>
    <h2>Your choices</h2>
    <p>You can update your profile and payout accounts in the dashboard, and ask us to correct or delete data by contacting support; some records must be retained by law.</p>
    <h2>Cookies and storage</h2>
    <p>We use a session cookie to keep you signed in and local storage for the app install prompt. We do not use advertising trackers.</p>
    <h2>Contact</h2>
    <p>Email <a href="mailto:support@dfqexchange.com.ng">support@dfqexchange.com.ng</a>. See also our <a href="terms.php">Terms of Service</a>.</p>
</div>
<?php dfqPublicFooter(); ?>

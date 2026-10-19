<?php
/**
 * Shared chrome for public (signed-out) pages: legal pages and error pages.
 */
function dfqBase() {
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($dir, '/') . '/';
}

function dfqPublicHeader($title, $description = '') {
    $b = htmlspecialchars(dfqBase(), ENT_QUOTES, 'UTF-8');
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $d = htmlspecialchars($description ?: 'Buy and sell crypto and gift cards securely with DFQ Exchange.', ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<title>' . $t . ' - DFQ Exchange</title>'
        . '<meta name="description" content="' . $d . '">'
        . '<meta name="theme-color" content="#101828">'
        . '<link rel="manifest" href="' . $b . 'manifest.json">'
        . '<link rel="icon" href="' . $b . 'static/assets/img/fabicon.png">'
        . '<link rel="apple-touch-icon" href="' . $b . 'static/assets/img/apple-touch-icon.png">'
        . '<link rel="stylesheet" href="' . $b . 'static/assets/css/dfq-public.css">'
        . '</head><body class="pub">'
        . '<header class="pub-nav"><a class="pub-brand" href="' . $b . 'index.html"><img src="' . $b . 'static/assets/img/dfq-mark.svg" alt="" width="34" height="34"><span>DFQ <b>Exchange</b></span></a>'
        . '<nav><a href="' . $b . 'login.php">Sign in</a><a class="pub-btn" href="' . $b . 'register.php">Get started</a></nav></header>'
        . '<main class="pub-main">';
}

function dfqPublicFooter() {
    $b = htmlspecialchars(dfqBase(), ENT_QUOTES, 'UTF-8');
    echo '</main><footer class="pub-footer"><div>&copy; ' . date('Y') . ' DFQ Exchange. All rights reserved.</div>'
        . '<nav><a href="' . $b . 'terms.php">Terms</a><a href="' . $b . 'privacy.php">Privacy</a>'
        . '<a href="mailto:support@dfqexchange.com.ng">Support</a></nav></footer></body></html>';
}

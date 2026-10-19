<?php
require_once 'includes/user_auth.php';
require_once 'includes/user_dashboard.php';

$auth = new UserAuth();
$dashboard = new UserDashboard();

if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Get user data
$userData = $auth->getUserData($_SESSION['user_id']);
if (!$userData['success']) {
    header('Location: login.php');
    exit();
}

$user = $userData['data'];

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session security token expired. Please refresh the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'logout') {
            $auth->logout();
            header('Location: login.php');
            exit();
        }

        switch ($action) {
            case 'buy_crypto':
                $symbol = $_POST['crypto_symbol'] ?? '';
                $amount = (float)($_POST['amount'] ?? 0);
                $result = $dashboard->buyCryptocurrency($_SESSION['user_id'], $symbol, $amount);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'sell_crypto':
                $symbol = $_POST['crypto_symbol'] ?? '';
                $amount = (float)($_POST['amount'] ?? 0);
                $result = $dashboard->sellCryptocurrency($_SESSION['user_id'], $symbol, $amount);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'submit_gift_card':
                $brand = $_POST['gift_card_brand'] ?? '';
                $value = (float)($_POST['card_value'] ?? 0);
                $code = $_POST['gift_card_code'] ?? '';

                $result = $dashboard->submitGiftCard(
                    $_SESSION['user_id'],
                    $brand,
                    $value,
                    $code,
                    $_FILES['gift_card_image'] ?? null
                );

                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'submit_transfer':
                $result = $dashboard->createTransferRequest(
                    $_SESSION['user_id'],
                    [
                        'direction' => $_POST['direction'] ?? 'deposit',
                        'crypto_symbol' => $_POST['crypto_symbol'] ?? '',
                        'company_account_id' => $_POST['company_account_id'] ?? 0,
                        'crypto_amount' => $_POST['crypto_amount'] ?? 0,
                        'tx_hash' => $_POST['tx_hash'] ?? '',
                        'payout_currency' => $_POST['payout_currency'] ?? '',
                        'payout_account_id' => $_POST['payout_account_id'] ?? 0,
                        'amount' => $_POST['amount'] ?? 0,
                        'user_notes' => $_POST['user_notes'] ?? '',
                    ],
                    $_FILES['transfer_proof'] ?? null
                );

                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'add_payout_account':
                $result = $dashboard->addPayoutAccount($_SESSION['user_id'], $_POST);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'delete_payout_account':
                $result = $dashboard->deletePayoutAccount($_SESSION['user_id'], (int)($_POST['account_id'] ?? 0));
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'set_default_payout_account':
                $result = $dashboard->setDefaultPayoutAccount($_SESSION['user_id'], (int)($_POST['account_id'] ?? 0));
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'change_password':
                $result = $auth->changePassword(
                    $_SESSION['user_id'],
                    $_POST['current_password'] ?? '',
                    $_POST['new_password'] ?? ''
                );
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
}

// Get dashboard data (after any POST so figures are current)
$userData = $auth->getUserData($_SESSION['user_id']);
if ($userData['success']) {
    $user = $userData['data'];
}
$dashboardData = $dashboard->getDashboardData($_SESSION['user_id']);
$recentTransactions = $dashboard->getTransactionHistory(1, 5);
$cryptoHoldings = $dashboard->getCryptoHoldings($_SESSION['user_id']);
$currentRates = $dashboard->getCryptocurrencyRates();
$giftCardRates = $dashboard->getGiftCardRates();
$walletBalances = $dashboard->getWalletBalances($_SESSION['user_id']);
$payoutAccounts = $dashboard->getPayoutAccounts($_SESSION['user_id']);
$verifiedPayoutAccounts = $dashboard->getPayoutAccounts($_SESSION['user_id'], true);
$companyAccounts = $dashboard->getCompanyAccounts(true);
$transferRequests = $dashboard->getTransferRequests($_SESSION['user_id']);
$currentSection = $_GET['section'] ?? 'dashboard';
$userSections = ['dashboard', 'trading', 'giftcards', 'wallet', 'transfers', 'transactions', 'profile', 'support'];
if (!in_array($currentSection, $userSections, true)) {
    $currentSection = 'dashboard';
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>User Dashboard - DFQ Exchange</title>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- carousel CSS -->
    <link rel="stylesheet" href="static/assets/css/owl.carousel.min.css">
    <!--header icon CSS -->
    <link rel="icon" href="static/assets/img/fabicon.png">
    <!-- animations CSS -->
    <link rel="stylesheet" href="static/assets/css/animate.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="static/assets/css/bootstrap.min.css">
    <!-- mobile menu CSS -->
    <link rel="stylesheet" href="static/assets/css/slicknav.min.css">
    <!--css animation-->
    <link rel="stylesheet" href="static/assets/css/animation.css">
    <!-- style CSS -->
    <link rel="stylesheet" href="static/assets/css/style.css">
    <!-- responsive CSS -->
    <link rel="stylesheet" href="static/assets/css/responsive.css">
    <style>
        body {
            background-color: black !important;
        }
        .dashboard-area {
            min-height: 100vh;
            background: #062489;
            padding: 80px 0 50px;
        }
        .dashboard-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            margin-bottom: 30px;
        }
        .dashboard-header {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            margin-bottom: 30px;
        }
        .sidebar {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            height: fit-content;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 15px;
        }
        .sidebar-menu a {
            color: #cbe5ff;
            text-decoration: none;
            padding: 15px 20px;
            display: block;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(254, 153, 139, 0.2);
            color: #fff;
        }
        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
        }
        .balance-card {
            text-align: center;
            padding: 30px;
        }
        .balance-amount {
            font-size: 2.5rem;
            font-weight: bold;
            color: #fe998b;
            margin: 15px 0;
        }
        .balance-label {
            color: #cbe5ff;
            font-size: 1.1rem;
        }
        .quick-actions {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        .quick-action-btn {
            background: rgba(254, 153, 139, 0.1);
            border: 1px solid rgba(254, 153, 139, 0.3);
            color: #fe998b;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .quick-action-btn:hover {
            background: rgba(254, 153, 139, 0.2);
            color: #fff;
            text-decoration: none;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #fe998b;
            margin-bottom: 8px;
        }
        .stat-label {
            color: #cbe5ff;
            font-size: 0.9rem;
        }
        .user-info {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            gap: 15px;
        }
        .user-avatar {
            width: 50px;
            height: 50px;
            background: rgba(254, 153, 139, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fe998b;
            font-size: 1.5rem;
        }
        .transaction-table {
            overflow-x: auto;
        }
        .table {
            color: #fff;
            margin: 0;
        }
        .table th {
            border-color: rgba(255, 255, 255, 0.1);
            color: #cbe5ff;
            font-weight: 500;
        }
        .table td {
            border-color: rgba(255, 255, 255, 0.1);
        }
        .status-completed {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #fff;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #fe998b;
            box-shadow: 0 0 0 0.2rem rgba(254, 153, 139, 0.25);
            color: #fff;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .logout-btn {
            color: #dc3545 !important;
            border: 1px solid rgba(220, 53, 69, 0.3);
            margin-top: 20px;
        }
        .logout-btn:hover {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        .alert {
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff6b7a;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.3);
            color: #6bcf7f;
        }
    </style>
    <link rel="stylesheet" href="static/assets/css/dfq-app.css">
    <!-- PWA / install prompt -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#062489">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="DFQ Exchange">
    <link rel="apple-touch-icon" href="static/assets/img/apple-touch-icon.png">
    <link rel="stylesheet" href="static/assets/css/pwa-install.css">
    <script src="static/assets/js/pwa-install.js" defer></script>
</head>
<body class="dfq-app dfq-user">
    <!--header area start-->
    <div class="header-area wow fadeInDown header-absolate" id="nav">
        <div class="container">
            <div class="row">
                <div class="col-4 d-block d-lg-none">
                    <div class="mobile-menu"></div>
                </div>
                <div class="col-4 col-lg-2">
                    <div class="logo-area">
                        <a href="index.html"><img src="static/assets/img/logo-top.png" alt=""></a>
                    </div>
                </div>
                <div class="col-4 col-lg-8 d-none d-lg-block">
                    <div class="main-menu text-center">
                        <nav>
                            <ul id="slick-nav">
                                <li><a href="?section=dashboard">Dashboard</a></li>
                                <li><a href="?section=trading">Trading</a></li>
                                <li><a href="?section=wallet">Wallet</a></li>
                                <li><a href="?section=transfers">Transfers</a></li>
                                <li><a href="?section=transactions">Transactions</a></li>
                                <li><a href="?section=profile">Profile</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <div class="col-4 col-lg-2 text-right">
                    <form method="POST" style="display:inline;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="logibtn gradient-btn" style="border:0;">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!--header area end-->

    <!--dashboard area start-->
    <div class="dashboard-area wow fadeInUp">
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3">
                    <div class="sidebar">
                        <div class="user-info">
                            <div class="user-avatar">
                                <i class="fa fa-user"></i>
                            </div>
                            <div>
                                <h5 style="color: #fff; margin: 0;"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                                <small style="color: #cbe5ff;"><?php echo ucfirst($user['status']); ?> Member</small>
                            </div>
                        </div>
                        <ul class="sidebar-menu">
                            <li><a href="?section=dashboard" class="<?php echo $currentSection === 'dashboard' ? 'active' : ''; ?>" <?php echo $currentSection === 'dashboard' ? 'aria-current="page"' : ''; ?>><i class="fa fa-dashboard"></i>Dashboard</a></li>
                            <li><a href="?section=trading" class="<?php echo $currentSection === 'trading' ? 'active' : ''; ?>" <?php echo $currentSection === 'trading' ? 'aria-current="page"' : ''; ?>><i class="fa fa-exchange"></i>Trading</a></li>
                            <li><a href="?section=wallet" class="<?php echo $currentSection === 'wallet' ? 'active' : ''; ?>" <?php echo $currentSection === 'wallet' ? 'aria-current="page"' : ''; ?>><i class="fa fa-wallet"></i>Wallet</a></li>
                            <li><a href="?section=transfers" class="<?php echo $currentSection === 'transfers' ? 'active' : ''; ?>" <?php echo $currentSection === 'transfers' ? 'aria-current="page"' : ''; ?>><i class="fa fa-send"></i>Transfers</a></li>
                            <li><a href="?section=transactions" class="<?php echo $currentSection === 'transactions' ? 'active' : ''; ?>" <?php echo $currentSection === 'transactions' ? 'aria-current="page"' : ''; ?>><i class="fa fa-list"></i>Transactions</a></li>
                            <li><a href="?section=giftcards" class="<?php echo $currentSection === 'giftcards' ? 'active' : ''; ?>" <?php echo $currentSection === 'giftcards' ? 'aria-current="page"' : ''; ?>><i class="fa fa-gift"></i>Gift Cards</a></li>
                            <li><a href="?section=profile" class="<?php echo $currentSection === 'profile' ? 'active' : ''; ?>" <?php echo $currentSection === 'profile' ? 'aria-current="page"' : ''; ?>><i class="fa fa-user"></i>Profile</a></li>
                            <li><a href="?section=support" class="<?php echo $currentSection === 'support' ? 'active' : ''; ?>" <?php echo $currentSection === 'support' ? 'aria-current="page"' : ''; ?>><i class="fa fa-support"></i>Support</a></li>
                            <li>
                                <form method="POST" style="margin:0;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="logout">
                                    <button type="submit" class="logout-btn" style="background:none;border-top:0;border-left:0;border-right:0;width:100%;text-align:left;padding:15px 20px;cursor:pointer;">
                                        <i class="fa fa-sign-out"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Dashboard Section -->
                    <div id="dashboard-section" class="dashboard-section" style="display: <?php echo $currentSection === 'dashboard' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Welcome Back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Here's what's happening with your account today.</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <!-- Balance Card -->
                        <div class="dashboard-card balance-card">
                            <div class="balance-label">Total Balance</div>
                            <div class="balance-amount">$<?php echo number_format($user['balance'], 2); ?></div>
                            <div class="row" style="margin-top:20px;">
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-value"><?php echo formatNaira($walletBalances['NGN']); ?></div>
                                        <div class="stat-label">NGN Wallet</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-value"><?php echo number_format($walletBalances['USDT'], 2); ?> USDT</div>
                                        <div class="stat-label">USDT Wallet</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="stat-card">
                                        <div class="stat-value"><?php echo number_format($walletBalances['USD'], 2); ?> USD</div>
                                        <div class="stat-label">Trading Balance</div>
                                    </div>
                                </div>
                            </div>
                            <div class="quick-actions">
                                <a href="?section=transfers" class="quick-action-btn">
                                    <i class="fa fa-download"></i>
                                    Deposit Crypto
                                </a>
                                <a href="?section=transfers" class="quick-action-btn">
                                    <i class="fa fa-send"></i>
                                    Withdraw
                                </a>
                                <a href="?section=trading" class="quick-action-btn">
                                    <i class="fa fa-exchange"></i>
                                    Trade
                                </a>
                                <a href="?section=giftcards" class="quick-action-btn">
                                    <i class="fa fa-gift"></i>
                                    Gift Cards
                                </a>
                            </div>
                        </div>

                        <!-- Stats Grid -->
                        <div class="stats-grid">
                            <div class="stat-card">
                                <div class="stat-value"><?php echo $dashboardData['transaction_count'] ?? 0; ?></div>
                                <div class="stat-label">Completed Trades</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value">$<?php echo number_format($dashboardData['monthly_volume'] ?? 0, 0); ?></div>
                                <div class="stat-label">This Month Volume</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format($cryptoHoldings['BTC'] ?? 0, 4); ?> BTC</div>
                                <div class="stat-label">Bitcoin Holdings</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-value"><?php echo number_format($cryptoHoldings['ETH'] ?? 0, 2); ?> ETH</div>
                                <div class="stat-label">Ethereum Holdings</div>
                            </div>
                        </div>

                        <!-- Recent Transactions -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Recent Transactions</h3>
                            <div class="transaction-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentTransactions['transactions'])): ?>
                                            <?php foreach ($recentTransactions['transactions'] as $transaction): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($transaction['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['type_formatted']); ?></td>
                                                    <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                                    <td>
                                                        <span class="status-<?php echo $transaction['status']; ?>">
                                                            <?php echo ucfirst($transaction['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" style="text-align: center; color: #cbe5ff;">No transactions yet</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Trading Section -->
                    <div id="trading-section" class="dashboard-section" style="display: <?php echo $currentSection === 'trading' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Cryptocurrency Trading</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Buy and sell cryptocurrencies with competitive rates.</p>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="dashboard-card">
                                    <h3 style="color: #fff; margin-bottom: 20px;">Buy Cryptocurrency</h3>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="buy_crypto">
                                        <?php echo csrfField(); ?>
                                        <div class="form-group">
                                            <select name="crypto_symbol" class="form-control" required>
                                                <option value="">Select Cryptocurrency</option>
                                                <?php foreach ($currentRates as $rate): ?>
                                                    <option value="<?php echo $rate['symbol']; ?>">
                                                        <?php echo $rate['name']; ?> (<?php echo strtoupper($rate['symbol']); ?>) - $<?php echo number_format($rate['buy_rate'], 2); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <input type="number" name="amount" class="form-control" placeholder="Amount (USD)" step="0.01" min="1" required>
                                        </div>
                                        <button type="submit" class="gradient-btn" style="width: 100%;">Buy Now</button>
                                    </form>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="dashboard-card">
                                    <h3 style="color: #fff; margin-bottom: 20px;">Sell Cryptocurrency</h3>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="sell_crypto">
                                        <?php echo csrfField(); ?>
                                        <div class="form-group">
                                            <select name="crypto_symbol" class="form-control" required>
                                                <option value="">Select Cryptocurrency</option>
                                                <?php foreach ($cryptoHoldings as $symbol => $amount): ?>
                                                    <?php if ($amount > 0): ?>
                                                        <option value="<?php echo $symbol; ?>">
                                                            <?php echo strtoupper($symbol); ?> - <?php echo number_format($amount, 4); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <input type="number" name="amount" class="form-control" placeholder="Amount to Sell" step="0.00001" min="0.00001" required>
                                        </div>
                                        <button type="submit" class="gradient-btn" style="width: 100%;">Sell Now</button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Current Rates -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Current Exchange Rates</h3>
                            <div class="row">
                                <?php foreach (array_slice($currentRates, 0, 3) as $rate): ?>
                                    <div class="col-md-4">
                                        <div class="stat-card">
                                            <div class="stat-value">$<?php echo number_format($rate['buy_rate'], 2); ?></div>
                                            <div class="stat-label"><?php echo $rate['name']; ?> (<?php echo strtoupper($rate['symbol']); ?>)</div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Gift Cards Section -->
                    <div id="giftcards-section" class="dashboard-section" style="display: <?php echo $currentSection === 'giftcards' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Gift Card Exchange</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Exchange your gift cards for cash or cryptocurrency.</p>
                        </div>

                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Sell Gift Card</h3>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="submit_gift_card">
                                <?php echo csrfField(); ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <select name="gift_card_brand" class="form-control" required>
                                                <option value="">Select Gift Card Brand</option>
                                                <?php foreach ($giftCardRates as $brand): ?>
                                                    <option value="<?php echo $brand['brand_code']; ?>">
                                                        <?php echo $brand['brand_name']; ?> (<?php echo $brand['exchange_rate']; ?>%)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <input type="number" name="card_value" class="form-control" placeholder="Card Value (USD)" step="0.01" min="1" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <input type="text" name="gift_card_code" class="form-control" placeholder="Gift Card Code" required>
                                </div>
                                <div class="form-group">
                                    <input type="file" name="gift_card_image" class="form-control" accept="image/*" required>
                                    <small style="color: #cbe5ff;">Upload gift card image (JPG, PNG - Max 5MB)</small>
                                </div>
                                <button type="submit" class="gradient-btn" style="width: 100%;">Submit for Review</button>
                            </form>
                        </div>

                        <!-- Gift Card Rates -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Current Gift Card Rates</h3>
                            <div class="transaction-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Brand</th>
                                            <th>Rate</th>
                                            <th>Min Amount</th>
                                            <th>Max Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($giftCardRates as $brand): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($brand['brand_name']); ?></td>
                                                <td><?php echo $brand['exchange_rate']; ?>%</td>
                                                <td>$<?php echo number_format($brand['min_amount'], 0); ?></td>
                                                <td>$<?php echo number_format($brand['max_amount'], 0); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Wallet Section -->
                    <div id="wallet-section" class="dashboard-section" style="display: <?php echo $currentSection === 'wallet' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Wallet</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Manage your balances and cryptocurrency holdings.</p>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="dashboard-card">
                                    <div class="balance-label">NGN Balance</div>
                                    <div class="stat-value"><?php echo formatNaira($walletBalances['NGN']); ?></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="dashboard-card">
                                    <div class="balance-label">USDT Balance</div>
                                    <div class="stat-value"><?php echo number_format($walletBalances['USDT'], 2); ?> USDT</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="dashboard-card">
                                    <div class="balance-label">Trading Balance (USD)</div>
                                    <div class="stat-value">$<?php echo number_format($walletBalances['USD'], 2); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Your Holdings</h3>
                            <div class="row">
                                <?php $hasHoldings = false; ?>
                                <?php foreach ($cryptoHoldings as $symbol => $amount): ?>
                                    <?php if ($amount > 0): $hasHoldings = true; ?>
                                        <div class="col-md-4">
                                            <div class="stat-card">
                                                <div class="stat-value"><?php echo number_format($amount, 8); ?></div>
                                                <div class="stat-label"><?php echo strtoupper($symbol); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (!$hasHoldings): ?>
                                    <div class="col-12"><p style="color:#cbe5ff;">You do not hold any cryptocurrency yet.</p></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="dashboard-card" style="text-align:center;">
                            <a href="?section=transfers" class="gradient-btn">Deposit or Withdraw</a>
                        </div>
                    </div>

                    <!-- Transfers Section -->
                    <div id="transfers-section" class="dashboard-section" style="display: <?php echo $currentSection === 'transfers' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Deposits &amp; Withdrawals</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Send crypto to our company wallet or withdraw your balance in NGN or USDT.</p>
                        </div>

                        <!-- Company receiving accounts -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Company Receiving Accounts</h3>
                            <?php if (!empty($companyAccounts)): ?>
                                <div class="transaction-table">
                                    <table class="table">
                                        <thead><tr><th>Currency</th><th>Network</th><th>Wallet / Account</th><th>Instructions</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($companyAccounts as $account): ?>
                                                <?php if ($account['method'] !== 'crypto') continue; ?>
                                                <tr>
                                                    <td><?php echo h($account['currency']); ?></td>
                                                    <td><?php echo h($account['network'] ?: '—'); ?></td>
                                                    <td style="word-break:break-all;"><?php echo h($account['wallet_address']); ?>
                                                        <?php if ($account['qr_url']): ?>
                                                            <br><a href="<?php echo h($account['qr_url']); ?>" target="_blank" rel="noopener" style="color:#fe998b;">View QR</a>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo h($account['instructions'] ?: '—'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p style="color:#cbe5ff;">No receiving accounts are configured yet. Please contact support.</p>
                            <?php endif; ?>
                        </div>

                        <div class="row">
                            <!-- Deposit -->
                            <div class="col-lg-7">
                                <div class="dashboard-card">
                                    <h3 style="color: #fff; margin-bottom: 20px;">Deposit Crypto</h3>
                                    <form method="POST" enctype="multipart/form-data" id="deposit-form">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="submit_transfer">
                                        <input type="hidden" name="direction" value="deposit">
                                        <input type="hidden" name="crypto_symbol" id="deposit-crypto-symbol" value="">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Send to</label>
                                            <select name="company_account_id" id="company-account-select" class="form-control" required
                                                    onchange="document.getElementById('deposit-crypto-symbol').value = this.options[this.selectedIndex].dataset.symbol || '';">
                                                <option value="">Select receiving wallet</option>
                                                <?php foreach ($companyAccounts as $account): ?>
                                                    <?php if ($account['method'] !== 'crypto') continue; ?>
                                                    <option value="<?php echo (int)$account['id']; ?>" data-symbol="<?php echo h($account['currency']); ?>">
                                                        <?php echo h($account['display']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label style="color:#cbe5ff;">Amount sent</label>
                                                    <input type="number" name="crypto_amount" class="form-control" step="0.00000001" min="0" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label style="color:#cbe5ff;">Blockchain tx hash</label>
                                                    <input type="text" name="tx_hash" class="form-control" required>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Proof of transfer</label>
                                            <input type="file" name="transfer_proof" class="form-control" accept="image/*" required>
                                            <small style="color:#cbe5ff;">Screenshot of the successful transfer (JPG, PNG, GIF, WEBP - max 5MB)</small>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label style="color:#cbe5ff;">Pay me in</label>
                                                    <select name="payout_currency" class="form-control" id="deposit-payout-currency"
                                                            onchange="filterPayoutAccounts('deposit');">
                                                        <option value="NGN">Naira (NGN) — bank transfer</option>
                                                        <option value="USDT">USDT — crypto wallet</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label style="color:#cbe5ff;">Verified payout account</label>
                                                    <select name="payout_account_id" id="deposit-payout-account" class="form-control" required>
                                                        <?php foreach ($verifiedPayoutAccounts as $account): ?>
                                                            <option value="<?php echo (int)$account['id']; ?>" data-method="<?php echo h($account['method']); ?>">
                                                                <?php echo h($account['display']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Notes (optional)</label>
                                            <textarea name="user_notes" class="form-control" rows="2"></textarea>
                                        </div>
                                        <button type="submit" class="gradient-btn" style="width:100%;">Submit Deposit</button>
                                    </form>
                                </div>
                            </div>

                            <!-- Withdrawal -->
                            <div class="col-lg-5">
                                <div class="dashboard-card">
                                    <h3 style="color: #fff; margin-bottom: 20px;">Withdraw</h3>
                                    <form method="POST">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="submit_transfer">
                                        <input type="hidden" name="direction" value="withdrawal">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Withdraw from</label>
                                            <select name="payout_currency" class="form-control" id="withdraw-payout-currency"
                                                    onchange="filterPayoutAccounts('withdraw');">
                                                <option value="NGN">NGN balance</option>
                                                <option value="USDT">USDT balance</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Amount</label>
                                            <input type="number" name="amount" class="form-control" step="0.01" min="0" required>
                                        </div>
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Pay into</label>
                                            <select name="payout_account_id" id="withdraw-payout-account" class="form-control" required>
                                                <?php foreach ($verifiedPayoutAccounts as $account): ?>
                                                    <option value="<?php echo (int)$account['id']; ?>" data-method="<?php echo h($account['method']); ?>">
                                                        <?php echo h($account['display']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Notes (optional)</label>
                                            <textarea name="user_notes" class="form-control" rows="2"></textarea>
                                        </div>
                                        <button type="submit" class="gradient-btn" style="width:100%;">Request Withdrawal</button>
                                    </form>
                                    <p style="color:#cbe5ff;margin-top:15px;font-size:13px;">
                                        Withdrawals are debited immediately and refunded if rejected. You need a verified payout account — add one under Profile.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Transfer history -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Transfer History</h3>
                            <div class="transaction-table">
                                <table class="table">
                                    <thead>
                                        <tr><th>Reference</th><th>Type</th><th>Amount</th><th>Payout</th><th>Status</th><th>Date</th><th></th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($transferRequests)): ?>
                                            <?php foreach ($transferRequests as $request): ?>
                                                <tr>
                                                    <td><?php echo h($request['reference']); ?></td>
                                                    <td><?php echo $request['direction'] === 'deposit' ? 'Deposit' : 'Withdrawal'; ?></td>
                                                    <td><?php echo $request['crypto_amount_formatted']; ?></td>
                                                    <td><?php echo $request['payout_amount_formatted']; ?></td>
                                                    <td><span class="status-<?php echo h($request['status']); ?>"><?php echo h($request['status_label']); ?></span></td>
                                                    <td><?php echo h($request['date_formatted']); ?></td>
                                                    <td>
                                                        <?php if ($request['user_proof_url']): ?>
                                                            <a href="<?php echo h($request['user_proof_url']); ?>" target="_blank" rel="noopener" style="color:#fe998b;">Proof</a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="7" style="text-align:center;color:#cbe5ff;">No transfer requests yet</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions Section -->
                    <div id="transactions-section" class="dashboard-section" style="display: <?php echo $currentSection === 'transactions' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Transaction History</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">View all your trading activities.</p>
                        </div>
                        <div class="dashboard-card">
                            <div class="transaction-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Fee</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $allTransactions = $dashboard->getTransactionHistory(1, 20);
                                        if (!empty($allTransactions['transactions'])): 
                                        ?>
                                            <?php foreach ($allTransactions['transactions'] as $transaction): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y H:i', strtotime($transaction['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['type_formatted']); ?></td>
                                                    <td>$<?php echo number_format($transaction['amount'], 2); ?></td>
                                                    <td>$<?php echo number_format($transaction['fee'], 2); ?></td>
                                                    <td>
                                                        <span class="status-<?php echo $transaction['status']; ?>">
                                                            <?php echo ucfirst($transaction['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" style="text-align: center; color: #cbe5ff;">No transactions yet</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Section -->
                    <div id="profile-section" class="dashboard-section" style="display: <?php echo $currentSection === 'profile' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Profile Settings</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Update your account information and preferences.</p>
                        </div>
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Account Information</h3>
                            <div class="row">
                                <div class="col-md-6">
                                    <p style="color: #cbe5ff;"><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                                    <p style="color: #cbe5ff;"><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                    <p style="color: #cbe5ff;"><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p style="color: #cbe5ff;"><strong>Country:</strong> <?php echo htmlspecialchars($user['country']); ?></p>
                                    <p style="color: #cbe5ff;"><strong>Status:</strong> <?php echo ucfirst($user['status']); ?></p>
                                    <p style="color: #cbe5ff;"><strong>Member Since:</strong> <?php echo date('M d, Y', strtotime($user['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- Payout accounts -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Payout Accounts</h3>
                            <p style="color:#cbe5ff;">These are the bank accounts and USDT wallets we pay your withdrawals into. Accounts must be verified by the admin before use.</p>
                            <?php if (!empty($payoutAccounts)): ?>
                                <div class="transaction-table">
                                    <table class="table">
                                        <thead><tr><th>Account</th><th>Status</th><th>Default</th><th>Actions</th></tr></thead>
                                        <tbody>
                                            <?php foreach ($payoutAccounts as $account): ?>
                                                <tr>
                                                    <td><?php echo h($account['display']); ?></td>
                                                    <td>
                                                        <span class="status-<?php echo $account['status'] === 'verified' ? 'completed' : ($account['status'] === 'rejected' ? 'failed' : 'pending'); ?>">
                                                            <?php echo h(ucfirst($account['status'])); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo $account['is_default'] ? 'Yes' : 'No'; ?></td>
                                                    <td>
                                                        <?php if (!$account['is_default']): ?>
                                                            <form method="POST" style="display:inline;">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="action" value="set_default_payout_account">
                                                                <input type="hidden" name="account_id" value="<?php echo (int)$account['id']; ?>">
                                                                <button type="submit" class="quick-action-btn" style="padding:6px 12px;">Make default</button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Remove this payout account?');">
                                                            <?php echo csrfField(); ?>
                                                            <input type="hidden" name="action" value="delete_payout_account">
                                                            <input type="hidden" name="account_id" value="<?php echo (int)$account['id']; ?>">
                                                            <button type="submit" class="quick-action-btn" style="padding:6px 12px;color:#ff6b7a;">Remove</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p style="color:#cbe5ff;">You have not added any payout accounts yet.</p>
                            <?php endif; ?>

                            <hr style="border-color:rgba(255,255,255,0.1);">
                            <h4 style="color:#fff;margin-bottom:15px;">Add Payout Account</h4>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="add_payout_account">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Type</label>
                                            <select name="method" class="form-control" id="payout-method"
                                                    onchange="togglePayoutFields();">
                                                <option value="bank">Bank account (NGN)</option>
                                                <option value="crypto">USDT wallet</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Label (optional)</label>
                                            <input type="text" name="label" class="form-control" placeholder="e.g. Main account">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">&nbsp;</label>
                                            <div>
                                                <label style="color:#cbe5ff;font-weight:normal;">
                                                    <input type="checkbox" name="is_default" value="1"> Set as default
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="payout-bank-fields">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color:#cbe5ff;">Bank name</label>
                                                <input type="text" name="bank_name" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color:#cbe5ff;">Account name</label>
                                                <input type="text" name="account_name" class="form-control">
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color:#cbe5ff;">Account number</label>
                                                <input type="text" name="account_number" class="form-control" inputmode="numeric">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="payout-crypto-fields" style="display:none;">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label style="color:#cbe5ff;">USDT network</label>
                                                <select name="network" class="form-control">
                                                    <option value="TRC20">TRC20 (Tron)</option>
                                                    <option value="ERC20">ERC20 (Ethereum)</option>
                                                    <option value="BEP20">BEP20 (BNB Chain)</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="form-group">
                                                <label style="color:#cbe5ff;">Wallet address</label>
                                                <input type="text" name="wallet_address" class="form-control">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="gradient-btn">Add Account</button>
                            </form>
                        </div>

                        <!-- Change password -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Change Password</h3>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="change_password">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Current password</label>
                                            <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">New password</label>
                                            <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
                                            <small style="color:#cbe5ff;">At least 8 characters with upper case, lower case and a number.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">&nbsp;</label>
                                            <button type="submit" class="gradient-btn" style="width:100%;">Update Password</button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Support Section -->
                    <div id="support-section" class="dashboard-section" style="display: <?php echo $currentSection === 'support' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Support</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Get help with your account and trading activities.</p>
                        </div>
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Contact Support</h3>
                            <p style="color: #cbe5ff;">For assistance, please contact our support team:</p>
                            <p><strong>Email:</strong> <a href="mailto:support@dfqexchange.com.ng">support@dfqexchange.com.ng</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--dashboard area end-->

    <!-- jquery 2.2.4 js-->
    <script src="static/assets/js/jquery-2.2.4.min.js"></script>
    <!-- popper js-->
    <script src="static/assets/js/popper.js"></script>
    <!-- wow js-->
    <script src="static/assets/js/wow.min.js"></script>
    <!-- bootstrap js-->
    <script src="static/assets/js/bootstrap.min.js"></script>
    <!--mobile menu js-->
    <script src="static/assets/js/jquery.slicknav.min.js"></script>
    <script>
        function togglePayoutFields() {
            const method = document.getElementById('payout-method').value;
            document.getElementById('payout-bank-fields').style.display = method === 'bank' ? 'block' : 'none';
            document.getElementById('payout-crypto-fields').style.display = method === 'crypto' ? 'block' : 'none';
        }

        function filterPayoutAccounts(prefix) {
            const currency = document.getElementById(prefix + '-payout-currency').value;
            const method = currency === 'NGN' ? 'bank' : 'crypto';
            const select = document.getElementById(prefix + '-payout-account');
            let firstMatch = null;

            Array.from(select.options).forEach(function(option) {
                const matches = option.dataset.method === method;
                option.hidden = !matches;
                option.disabled = !matches;
                if (matches && firstMatch === null) {
                    firstMatch = option;
                }
            });

            if (firstMatch) {
                firstMatch.selected = true;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            filterPayoutAccounts('deposit');
            filterPayoutAccounts('withdraw');
            togglePayoutFields();
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>
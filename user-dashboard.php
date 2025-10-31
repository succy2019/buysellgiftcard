<?php
session_start();
require_once 'includes/user_auth.php';
require_once 'includes/user_dashboard.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$auth = new UserAuth();
$dashboard = new UserDashboard();

// Get user data
$userData = $auth->getUserData($_SESSION['user_id']);
if (!$userData['success']) {
    header('Location: login.php');
    exit();
}

$user = $userData['data'];

// Get dashboard data
$dashboardData = $dashboard->getDashboardData($_SESSION['user_id']);
$recentTransactions = $dashboard->getTransactionHistory($_SESSION['user_id'], 1, 5);
$cryptoHoldings = $dashboard->getCryptoHoldings($_SESSION['user_id']);
$currentRates = $dashboard->getCryptocurrencyRates();
$giftCardRates = $dashboard->getGiftCardRates();

// Handle logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: login.php');
    exit();
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'buy_crypto':
                $symbol = $_POST['crypto_symbol'] ?? '';
                $amount = (float)($_POST['amount'] ?? 0);
                $result = $dashboard->buyCryptocurrency($_SESSION['user_id'], $symbol, $amount);
                if ($result['success']) {
                    $success = $result['message'];
                    // Refresh data
                    $dashboardData = $dashboard->getDashboardData($_SESSION['user_id']);
                    $cryptoHoldings = $dashboard->getCryptoHoldings($_SESSION['user_id']);
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
                    // Refresh data
                    $dashboardData = $dashboard->getDashboardData($_SESSION['user_id']);
                    $cryptoHoldings = $dashboard->getCryptoHoldings($_SESSION['user_id']);
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'submit_gift_card':
                $brand = $_POST['gift_card_brand'] ?? '';
                $value = (float)($_POST['card_value'] ?? 0);
                $code = $_POST['gift_card_code'] ?? '';
                
                // Handle file upload
                $uploadResult = null;
                if (isset($_FILES['gift_card_image']) && $_FILES['gift_card_image']['error'] === UPLOAD_ERR_OK) {
                    $uploadResult = $dashboard->uploadGiftCardImage($_FILES['gift_card_image']);
                }
                
                if ($uploadResult && $uploadResult['success']) {
                    $result = $dashboard->submitGiftCard($_SESSION['user_id'], $brand, $value, $code, $uploadResult['file_path']);
                } else {
                    $result = ['success' => false, 'message' => 'Please upload a valid gift card image'];
                }
                
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>User Dashboard - FEX</title>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- carousel CSS -->
    <link rel="stylesheet" href="static/assets/css/owl.carousel.min.css">
    <!--header icon CSS -->
    <link rel="icon" href="static/assets/img/fabicon.png">
    <!-- animations CSS -->
    <link rel="stylesheet" href="static/assets/css/animate.min.css">
    <!-- font-awsome CSS -->
    <link rel="stylesheet" href="static/assets/css/font-awesome.min.css">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="static/assets/css/bootstrap.min.css">
    <!-- mobile menu CSS -->
    <link rel="stylesheet" href="static/assets/css/slicknav.min.css">
    <!--css animation-->
    <link rel="stylesheet" href="static/assets/css/animation.css">
    <!--css animation-->
    <link rel="stylesheet" href="static/assets/css/material-design-iconic-font.min.css">
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
</head>
<body>
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
                                <li><a href="#" class="active">Dashboard</a></li>
                                <li><a href="#" onclick="showSection('trading')">Trading</a></li>
                                <li><a href="#" onclick="showSection('wallet')">Wallet</a></li>
                                <li><a href="#" onclick="showSection('transactions')">Transactions</a></li>
                                <li><a href="#" onclick="showSection('profile')">Profile</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <div class="col-4 col-lg-2 text-right">
                    <a href="?logout=1" class="logibtn gradient-btn">Logout</a>
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
                            <li><a href="#" class="active" onclick="showSection('dashboard')"><i class="fa fa-dashboard"></i>Dashboard</a></li>
                            <li><a href="#" onclick="showSection('trading')"><i class="fa fa-exchange"></i>Trading</a></li>
                            <li><a href="#" onclick="showSection('wallet')"><i class="fa fa-wallet"></i>Wallet</a></li>
                            <li><a href="#" onclick="showSection('transactions')"><i class="fa fa-list"></i>Transactions</a></li>
                            <li><a href="#" onclick="showSection('giftcards')"><i class="fa fa-gift"></i>Gift Cards</a></li>
                            <li><a href="#" onclick="showSection('profile')"><i class="fa fa-user"></i>Profile</a></li>
                            <li><a href="#" onclick="showSection('support')"><i class="fa fa-support"></i>Support</a></li>
                            <li><a href="?logout=1" class="logout-btn"><i class="fa fa-sign-out"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Dashboard Section -->
                    <div id="dashboard-section" class="dashboard-section">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Welcome Back, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Here's what's happening with your account today.</p>
                        </div>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <!-- Balance Card -->
                        <div class="dashboard-card balance-card">
                            <div class="balance-label">Total Balance</div>
                            <div class="balance-amount">$<?php echo number_format($user['balance'], 2); ?></div>
                            <div class="quick-actions">
                                <a href="#" class="quick-action-btn" onclick="showSection('trading')">
                                    <i class="fa fa-plus"></i>
                                    Buy Crypto
                                </a>
                                <a href="#" class="quick-action-btn" onclick="showSection('trading')">
                                    <i class="fa fa-minus"></i>
                                    Sell Crypto
                                </a>
                                <a href="#" class="quick-action-btn" onclick="showSection('giftcards')">
                                    <i class="fa fa-gift"></i>
                                    Gift Cards
                                </a>
                                <a href="#" class="quick-action-btn" onclick="showSection('wallet')">
                                    <i class="fa fa-send"></i>
                                    Transfer
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
                    <div id="trading-section" class="dashboard-section" style="display: none;">
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
                    <div id="giftcards-section" class="dashboard-section" style="display: none;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Gift Card Exchange</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Exchange your gift cards for cash or cryptocurrency.</p>
                        </div>

                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Sell Gift Card</h3>
                            <form method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="submit_gift_card">
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
                    <div id="wallet-section" class="dashboard-section" style="display: none;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Wallet</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Manage your cryptocurrency holdings and transfers.</p>
                        </div>
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Your Holdings</h3>
                            <div class="row">
                                <?php foreach ($cryptoHoldings as $symbol => $amount): ?>
                                    <?php if ($amount > 0): ?>
                                        <div class="col-md-4">
                                            <div class="stat-card">
                                                <div class="stat-value"><?php echo number_format($amount, 4); ?></div>
                                                <div class="stat-label"><?php echo strtoupper($symbol); ?></div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Transactions Section -->
                    <div id="transactions-section" class="dashboard-section" style="display: none;">
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
                                        $allTransactions = $dashboard->getTransactionHistory($_SESSION['user_id'], 1, 20);
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
                    <div id="profile-section" class="dashboard-section" style="display: none;">
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
                    </div>

                    <!-- Support Section -->
                    <div id="support-section" class="dashboard-section" style="display: none;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Support</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Get help with your account and trading activities.</p>
                        </div>
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Contact Support</h3>
                            <p style="color: #cbe5ff;">For assistance, please contact our support team:</p>
                            <p style="color: #cbe5ff;"><strong>Email:</strong> support@fex.com</p>
                            <p style="color: #cbe5ff;"><strong>Live Chat:</strong> Available 24/7</p>
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
    <!-- main js-->
    <script src="static/assets/js/main.js"></script>
    
    <script>
        function showSection(sectionName) {
            // Hide all sections
            const sections = document.querySelectorAll('.dashboard-section');
            sections.forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected section
            document.getElementById(sectionName + '-section').style.display = 'block';
            
            // Update active menu item
            const menuItems = document.querySelectorAll('.sidebar-menu a:not(.logout-btn)');
            menuItems.forEach(item => {
                item.classList.remove('active');
            });
            
            // Find and activate the clicked menu item
            const activeItem = document.querySelector(`.sidebar-menu a[onclick="showSection('${sectionName}')"]`);
            if (activeItem) {
                activeItem.classList.add('active');
            }
        }

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
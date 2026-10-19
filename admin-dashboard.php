<?php
require_once 'includes/admin_auth.php';
require_once 'includes/admin_dashboard.php';
require_once 'includes/support.php';

$auth = new AdminAuth();
$dashboard = new AdminDashboard();
$support = new SupportService();

// Check if admin is logged in (validates the session server-side)
if (!$auth->isLoggedIn()) {
    header('Location: admin-login.php');
    exit();
}

// Get admin data
$adminData = $auth->getAdminData($_SESSION['admin_id']);
if (!$adminData['success']) {
    header('Location: admin-login.php');
    exit();
}

$admin = $adminData['data'];

// Get dashboard overview data
$overviewData = $dashboard->getDashboardOverview();
$stats = $overviewData['data']['stats'] ?? [];
$recentTransactions = $overviewData['data']['recent_transactions'] ?? [];
$pendingReviews = $overviewData['data']['pending_reviews'] ?? [];
$systemStatus = $overviewData['data']['system_status'] ?? [];

$error = '';
$success = '';
if (!empty($_SESSION['dfq_flash'])) {
    $success = (string)$_SESSION['dfq_flash'];
    unset($_SESSION['dfq_flash']);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'logout':
                $auth->logout();
                header('Location: admin-login.php');
                exit();

            case 'update_user_status':
                $userId = (int)($_POST['user_id'] ?? 0);
                $status = $_POST['status'] ?? '';
                $result = $dashboard->updateUserStatus($userId, $status);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'review_gift_card':
                $submissionId = (int)($_POST['submission_id'] ?? 0);
                $action = $_POST['review_action'] ?? '';
                $rejectionReason = $_POST['rejection_reason'] ?? '';
                $result = $dashboard->reviewGiftCardSubmission($submissionId, $action, $rejectionReason);
                if ($result['success']) {
                    $success = $result['message'];
                    // Refresh pending reviews
                    $overviewData = $dashboard->getDashboardOverview();
                    $pendingReviews = $overviewData['data']['pending_reviews'] ?? [];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'update_transaction':
                $result = $dashboard->updateTransactionStatus(
                    (int)($_POST['transaction_id'] ?? 0),
                    $_POST['transaction_action'] ?? ''
                );
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'save_company_account':
                $companyData = $_POST;
                $companyData['qr_code'] = $_FILES['qr_code'] ?? null;
                $result = $dashboard->saveCompanyAccount(
                    $companyData,
                    !empty($_POST['account_id']) ? (int)$_POST['account_id'] : null
                );
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'toggle_company_account':
                $result = $dashboard->toggleCompanyAccount((int)($_POST['account_id'] ?? 0));
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'review_payout_account':
                $result = $dashboard->reviewPayoutAccount(
                    (int)($_POST['account_id'] ?? 0),
                    $_POST['review_action'] ?? '',
                    $_POST['rejection_reason'] ?? ''
                );
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'verify_transfer':
                $result = $dashboard->verifyTransferRequest(
                    (int)($_POST['transfer_id'] ?? 0),
                    $_POST['review_action'] ?? '',
                    $_POST['admin_notes'] ?? ''
                );
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'process_transfer':
                $result = $dashboard->processTransferRequest(
                    (int)($_POST['transfer_id'] ?? 0),
                    $_POST,
                    $_FILES['admin_proof'] ?? null
                );
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'update_crypto_rates':
                $rates = [];
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'buy_rate_') === 0) {
                        $symbol = str_replace('buy_rate_', '', $key);
                        $rates[$symbol]['buy_rate'] = (float)$value;
                    } elseif (strpos($key, 'sell_rate_') === 0) {
                        $symbol = str_replace('sell_rate_', '', $key);
                        $rates[$symbol]['sell_rate'] = (float)$value;
                    }
                }
                $result = $dashboard->updateCryptoRates($rates);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'update_giftcard_rates':
                $rates = [];
                foreach ($_POST as $key => $value) {
                    if (strpos($key, 'rate_') === 0) {
                        $brandCode = str_replace('rate_', '', $key);
                        $rates[$brandCode] = (float)$value;
                    }
                }
                $result = $dashboard->updateGiftCardRates($rates);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'update_settings':
                $settings = [
                    'site_name' => $_POST['site_name'] ?? '',
                    'trading_fee_percentage' => $_POST['trading_fee_percentage'] ?? '',
                    'min_trade_amount' => $_POST['min_trade_amount'] ?? '',
                    'max_trade_amount' => $_POST['max_trade_amount'] ?? '',
                    'gift_card_processing_time' => $_POST['gift_card_processing_time'] ?? '',
                    'maintenance_mode' => $_POST['maintenance_mode'] ?? '0',
                    'usd_to_ngn_rate' => $_POST['usd_to_ngn_rate'] ?? '',
                    'transfer_fee_percentage' => $_POST['transfer_fee_percentage'] ?? '',
                    'min_transfer_usd' => $_POST['min_transfer_usd'] ?? '',
                    'max_transfer_usd' => $_POST['max_transfer_usd'] ?? ''
                ];

                $result = $dashboard->updateSystemSettings($settings);
                if ($result['success']) {
                    $success = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;

            case 'reply_ticket':
                $result = $support->addAdminReply($_SESSION['admin_id'], $_POST['ticket_id'] ?? '', $_POST['message'] ?? '', $_POST['status'] ?? null);
                if ($result['success']) { $success = $result['message']; } else { $error = $result['message']; }
                break;

            case 'update_ticket_status':
                $result = $support->updateStatus($_SESSION['admin_id'], $_POST['ticket_id'] ?? '', $_POST['status'] ?? '');
                if ($result['success']) { $success = $result['message']; } else { $error = $result['message']; }
                break;

            case 'generate_report':
                $reportType = $_POST['report_type'] ?? '';
                $startDate = $_POST['start_date'] ?? '';
                $endDate = $_POST['end_date'] ?? '';

                $result = $dashboard->generateReport($reportType, $startDate, $endDate);
                if ($result['success']) {
                    $reportRows = $result['data'];
                    if (($_POST['format'] ?? '') === 'csv') {
                        header('Content-Type: text/csv; charset=utf-8');
                        header('Content-Disposition: attachment; filename="dfq-' . preg_replace('/[^a-z_]/', '', $reportType) . '-report-' . date('Ymd') . '.csv"');
                        $out = fopen('php://output', 'w');
                        if ($reportRows) {
                            fputcsv($out, array_keys($reportRows[0]));
                            foreach ($reportRows as $row) {
                                fputcsv($out, array_map(function ($v) {
                                    $v = (string)$v;
                                    return preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v;
                                }, $row));
                            }
                        } else {
                            fputcsv($out, ['No data for the selected range']);
                        }
                        fclose($out);
                        exit();
                    }
                    $reportTitle = ucfirst(str_replace('_', ' ', $reportType)) . ' report: ' . $startDate . ' to ' . $endDate;
                    $success = 'Report generated: ' . count($reportRows) . ' row(s).';
                } else {
                    $error = $result['message'];
                }
                break;
        }
    }

    // Post/redirect/get so a refresh never repeats an admin action.
    if ($success !== '' && $error === '' && !isset($reportRows)) {
        $_SESSION['dfq_flash'] = $success;
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '#'));
        exit();
    }
}

// Get current page data based on section
$currentSection = $_GET['section'] ?? 'dashboard';
$adminSections = ['dashboard', 'users', 'transactions', 'giftcards', 'transfers', 'payouts', 'company', 'rates', 'support', 'reports', 'settings'];
if (!in_array($currentSection, $adminSections, true)) {
    $currentSection = 'dashboard';
}
$pageData = [];

switch ($currentSection) {
    case 'users':
        $page = (int)($_GET['page'] ?? 1);
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        $pageData = $dashboard->getUsers($page, 20, $search, $status);
        break;

    case 'transactions':
        $page = (int)($_GET['page'] ?? 1);
        $type = $_GET['type'] ?? '';
        $status = $_GET['status'] ?? '';
        $pageData = $dashboard->getTransactions($page, 20, $type, $status);
        break;

    case 'giftcards':
        $page = (int)($_GET['page'] ?? 1);
        $status = $_GET['status'] ?? '';
        $pageData = $dashboard->getGiftCardSubmissions($page, 20, $status);
        if (!empty($_GET['view'])) {
            $pageData['detail'] = $dashboard->getGiftCardSubmissionDetail((int)$_GET['view']);
        }
        break;

    case 'transfers':
        $page = (int)($_GET['page'] ?? 1);
        $status = $_GET['status'] ?? '';
        $direction = $_GET['direction'] ?? '';
        $pageData = $dashboard->getTransferRequests($page, 20, $status, $direction);
        $pageData['counts'] = $dashboard->getTransferStatusCounts();
        if (!empty($_GET['view'])) {
            $pageData['detail'] = $dashboard->getTransferRequest((int)$_GET['view']);
        }
        break;

    case 'payouts':
        $page = (int)($_GET['page'] ?? 1);
        $status = $_GET['status'] ?? '';
        $pageData = $dashboard->getPayoutAccounts($page, 20, $status);
        break;

    case 'company':
        $pageData['accounts'] = $dashboard->getCompanyAccounts(true);
        if (!empty($_GET['edit'])) {
            foreach ($pageData['accounts'] as $account) {
                if ((int)$account['id'] === (int)$_GET['edit']) {
                    $pageData['editing'] = $account;
                    break;
                }
            }
        }
        break;
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Admin Dashboard - DFQ Exchange</title>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

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
        .admin-dashboard-area {
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
        .admin-sidebar {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            height: fit-content;
        }
        .admin-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .admin-menu li {
            margin-bottom: 15px;
        }
        .admin-menu a {
            color: #cbe5ff;
            text-decoration: none;
            padding: 15px 20px;
            display: block;
            border-radius: 8px;
            transition: all 0.3s;
        }
        .admin-menu a:hover, .admin-menu a.active {
            background: rgba(254, 153, 139, 0.2);
            color: #fff;
        }
        .admin-menu i {
            margin-right: 10px;
            width: 20px;
        }
        .admin-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .admin-stat-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }
        .admin-stat-card.revenue { border-left: 4px solid #28a745; }
        .admin-stat-card.users { border-left: 4px solid #007bff; }
        .admin-stat-card.transactions { border-left: 4px solid #ffc107; }
        .admin-stat-card.pending { border-left: 4px solid #dc3545; }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            color: #fe998b;
            margin-bottom: 8px;
        }
        .stat-label {
            color: #cbe5ff;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        .stat-change {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 4px;
        }
        .stat-increase {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
        }
        .stat-decrease {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
        }
        .admin-info {
            display: flex;
            align-items: center;
            margin-bottom: 30px;
            gap: 15px;
        }
        .admin-avatar {
            width: 50px;
            height: 50px;
            background: rgba(220, 53, 69, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #dc3545;
            font-size: 1.5rem;
        }
        .admin-table {
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
        .status-completed, .status-verified {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        .status-pending, .status-unverified {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        .status-rejected, .status-suspended {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 0.85rem;
        }
        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.8rem;
            margin-right: 5px;
            display: inline-block;
        }
        .btn-view {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
            border: 1px solid rgba(23, 162, 184, 0.3);
        }
        .btn-approve {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        .btn-reject {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
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
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        .page-link {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #cbe5ff;
        }
        .page-link:hover {
            background: rgba(254, 153, 139, 0.2);
            border-color: #fe998b;
            color: #fff;
        }
        .page-item.active .page-link {
            background: #fe998b;
            border-color: #fe998b;
        }
    </style>
    <link rel="stylesheet" href="static/assets/css/dfq-app.css">
    <link rel="stylesheet" href="static/assets/css/dfq-premium.css">
    <script src="static/assets/js/dfq-shell.js" defer></script>
    <link rel="stylesheet" href="static/assets/css/pwa-install.css">
    <script src="static/assets/js/pwa-install.js" defer></script>
</head>
<body class="dfq-app dfq-admin">
    <!--header area start-->
    <div class="header-area wow fadeInDown header-absolate" id="nav">
        <div class="container">
            <div class="row">
                <div class="col-4 d-block d-lg-none">
                    <div class="mobile-menu"></div>
                </div>
                <div class="col-4 col-lg-2">
                    <div class="logo-area">
                        <a href="?section=dashboard" class="dfq-brand" aria-label="DFQ Exchange admin dashboard"><img src="static/assets/img/dfq-mark.svg" alt="" width="36" height="36"><span>DFQ <b>Admin</b></span></a>
                    </div>
                </div>
                <div class="col-4 col-lg-8 d-none d-lg-block">
                    <div class="main-menu text-center">
                        <nav>
                            <ul id="slick-nav">
                                <li><a href="?section=dashboard">Admin Panel</a></li>
                                <li><a href="?section=users">Users</a></li>
                                <li><a href="?section=transactions">Transactions</a></li>
                                <li><a href="?section=transfers">Transfers</a></li>
                                <li><a href="?section=giftcards">Gift Cards</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <div class="col-4 col-lg-2 text-right">
                    <form method="POST" style="display: inline;">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="logibtn gradient-btn">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!--header area end-->

    <!--admin dashboard area start-->
    <div class="admin-dashboard-area wow fadeInUp">
        <div class="container-fluid">
            <div class="row">
                <!-- Admin Sidebar -->
                <div class="col-lg-3">
                    <div class="admin-sidebar">
                        <div class="admin-info">
                            <div class="admin-avatar">
                                <i class="fa fa-user-shield"></i>
                            </div>
                            <div>
                                <h5 style="color: #fff; margin: 0;"><?php echo htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']); ?></h5>
                                <small style="color: #cbe5ff;"><?php echo ucfirst($admin['role']); ?> Admin</small>
                            </div>
                        </div>
                        <ul class="admin-menu">
                            <li><a href="?section=dashboard" class="<?php echo $currentSection === 'dashboard' ? 'active' : ''; ?>" <?php echo $currentSection === 'dashboard' ? 'aria-current="page"' : ''; ?>><i class="fa fa-dashboard"></i>Dashboard</a></li>
                            <li><a href="?section=users" class="<?php echo $currentSection === 'users' ? 'active' : ''; ?>" <?php echo $currentSection === 'users' ? 'aria-current="page"' : ''; ?>><i class="fa fa-users"></i>User Management</a></li>
                            <li><a href="?section=transactions" class="<?php echo $currentSection === 'transactions' ? 'active' : ''; ?>" <?php echo $currentSection === 'transactions' ? 'aria-current="page"' : ''; ?>><i class="fa fa-exchange"></i>Transactions</a></li>
                            <li><a href="?section=transfers" class="<?php echo $currentSection === 'transfers' ? 'active' : ''; ?>" <?php echo $currentSection === 'transfers' ? 'aria-current="page"' : ''; ?>><i class="fa fa-paper-plane"></i>Transfers</a></li>
                            <li><a href="?section=payouts" class="<?php echo $currentSection === 'payouts' ? 'active' : ''; ?>" <?php echo $currentSection === 'payouts' ? 'aria-current="page"' : ''; ?>><i class="fa fa-credit-card"></i>Payout Accounts</a></li>
                            <li><a href="?section=giftcards" class="<?php echo $currentSection === 'giftcards' ? 'active' : ''; ?>" <?php echo $currentSection === 'giftcards' ? 'aria-current="page"' : ''; ?>><i class="fa fa-gift"></i>Gift Cards</a></li>
                            <li><a href="?section=rates" class="<?php echo $currentSection === 'rates' ? 'active' : ''; ?>" <?php echo $currentSection === 'rates' ? 'aria-current="page"' : ''; ?>><i class="fa fa-line-chart"></i>Exchange Rates</a></li>
                            <li><a href="?section=company" class="<?php echo $currentSection === 'company' ? 'active' : ''; ?>" <?php echo $currentSection === 'company' ? 'aria-current="page"' : ''; ?>><i class="fa fa-university"></i>Company Accounts</a></li>
                            <li><a href="?section=support" class="<?php echo $currentSection === 'support' ? 'active' : ''; ?>" <?php echo $currentSection === 'support' ? 'aria-current="page"' : ''; ?>><i class="fa fa-support"></i>Support<?php $openSupport = $support->countOpen(); echo $openSupport ? ' <span class="badge badge-warning">' . (int)$openSupport . '</span>' : ''; ?></a></li>
                            <li><a href="?section=reports" class="<?php echo $currentSection === 'reports' ? 'active' : ''; ?>" <?php echo $currentSection === 'reports' ? 'aria-current="page"' : ''; ?>><i class="fa fa-bar-chart"></i>Reports</a></li>
                            <li><a href="?section=settings" class="<?php echo $currentSection === 'settings' ? 'active' : ''; ?>" <?php echo $currentSection === 'settings' ? 'aria-current="page"' : ''; ?>><i class="fa fa-cog"></i>Settings</a></li>
                            <li>
                                <form method="POST">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="logout">
                                    <button type="submit" class="logout-btn" style="background: none; border: 0; width: 100%; text-align: left; cursor: pointer;">
                                        <i class="fa fa-sign-out"></i>Logout
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Main Admin Content -->
                <div class="col-lg-9">
                    <!-- Global Alerts -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <!-- Admin Dashboard Section -->
                    <div id="admin-dashboard-section" class="admin-section" style="display: <?php echo $currentSection === 'dashboard' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">DFQ Exchange Admin Dashboard</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Monitor and manage your cryptocurrency trading platform.</p>
                        </div>

                        <!-- Admin Stats Grid -->
                        <div class="admin-stats-grid">
                            <div class="admin-stat-card revenue">
                                <div class="stat-value"><?php echo isset($stats['revenue']) ? $stats['revenue']['total_formatted'] : '$0'; ?></div>
                                <div class="stat-label">Total Revenue</div>
                                <div class="stat-change stat-increase">Monthly: <?php echo isset($stats['revenue']) ? $stats['revenue']['monthly_formatted'] : '$0'; ?></div>
                            </div>
                            <div class="admin-stat-card users">
                                <div class="stat-value"><?php echo $stats['users']['total_users'] ?? 0; ?></div>
                                <div class="stat-label">Total Users</div>
                                <div class="stat-change stat-increase">Active: <?php echo $stats['users']['active_users'] ?? 0; ?></div>
                            </div>
                            <div class="admin-stat-card transactions">
                                <div class="stat-value"><?php echo $stats['transactions']['total_transactions'] ?? 0; ?></div>
                                <div class="stat-label">Total Transactions</div>
                                <div class="stat-change stat-increase">Completed: <?php echo $stats['transactions']['completed_transactions'] ?? 0; ?></div>
                            </div>
                            <div class="admin-stat-card pending">
                                <div class="stat-value"><?php echo $stats['gift_cards']['pending_reviews'] ?? 0; ?></div>
                                <div class="stat-label">Pending Reviews</div>
                                <div class="stat-change stat-decrease">Gift Cards</div>
                            </div>
                        </div>

                        <!-- Recent Activity -->
                        <div class="row">
                            <div class="col-md-8">
                                <div class="dashboard-card">
                                    <h3 style="color: #fff; margin-bottom: 20px;">Recent Transactions</h3>
                                    <div class="admin-table">
                                        <table class="table">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Type</th>
                                                    <th>Amount</th>
                                                    <th>Status</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (!empty($recentTransactions)): ?>
                                                    <?php foreach ($recentTransactions as $transaction): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($transaction['user_email']); ?></td>
                                                            <td><?php echo htmlspecialchars($transaction['type_formatted']); ?></td>
                                                            <td><?php echo $transaction['amount_formatted']; ?></td>
                                                            <td><span class="status-<?php echo $transaction['status']; ?>"><?php echo ucfirst($transaction['status']); ?></span></td>
                                                            <td><?php echo $transaction['date_formatted']; ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <tr>
                                                        <td colspan="5" style="text-align: center; color: #cbe5ff;">No recent transactions</td>
                                                    </tr>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="dashboard-card">
                                    <h3 style="color: #fff; margin-bottom: 20px;">System Status</h3>
                                    <div style="margin-bottom: 20px;">
                                        <?php foreach ($systemStatus as $service => $status): ?>
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                <span style="color: #cbe5ff;"><?php echo ucwords(str_replace('_', ' ', $service)); ?></span>
                                                <span style="color: <?php echo $status === 'online' || $status === 'active' || $status === 'connected' || $status === 'optimal' ? '#28a745' : '#dc3545'; ?>">
                                                    <?php echo ucfirst($status); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <?php if (!empty($pendingReviews)): ?>
                                    <div class="dashboard-card">
                                        <h3 style="color: #fff; margin-bottom: 20px;">Pending Reviews</h3>
                                        <?php foreach (array_slice($pendingReviews, 0, 3) as $review): ?>
                                            <div style="border-bottom: 1px solid rgba(255,255,255,0.1); padding: 10px 0;">
                                                <div style="color: #cbe5ff; font-size: 0.9rem;"><?php echo htmlspecialchars($review['brand_name']); ?> - <?php echo $review['card_value_formatted']; ?></div>
                                                <div style="color: #fe998b; font-size: 0.8rem;"><?php echo htmlspecialchars($review['user_name']); ?></div>
                                                <div style="margin-top: 8px;">
                                                    <form method="POST" style="display: inline;">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="action" value="review_gift_card">
                                                        <input type="hidden" name="submission_id" value="<?php echo $review['id']; ?>">
                                                        <input type="hidden" name="review_action" value="approve">
                                                        <button type="submit" class="action-btn btn-approve" style="padding: 4px 8px; font-size: 0.7rem;">Approve</button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
                                                        <?php echo csrfField(); ?>
                                                        <input type="hidden" name="action" value="review_gift_card">
                                                        <input type="hidden" name="submission_id" value="<?php echo $review['id']; ?>">
                                                        <input type="hidden" name="review_action" value="reject">
                                                        <input type="hidden" name="rejection_reason" value="Invalid card">
                                                        <button type="submit" class="action-btn btn-reject" style="padding: 4px 8px; font-size: 0.7rem;">Reject</button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- User Management Section -->
                    <div id="admin-users-section" class="admin-section" style="display: <?php echo $currentSection === 'users' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">User Management</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Manage and monitor all platform users.</p>
                        </div>
                        
                        <!-- User Filters -->
                        <div class="dashboard-card">
                            <form method="GET" class="row">
                                <input type="hidden" name="section" value="users">
                                <div class="col-md-4">
                                    <input type="text" name="search" class="form-control" placeholder="Search users..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="active" <?php echo ($_GET['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="suspended" <?php echo ($_GET['status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                        <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="gradient-btn" style="width: 100%;">Filter</button>
                                </div>
                            </form>
                        </div>

                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">All Users</h3>
                            <div class="admin-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Name</th>
                                            <th>Email</th>
                                            <th>Country</th>
                                            <th>Balance</th>
                                            <th>Status</th>
                                            <th>Joined</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($pageData['users'])): ?>
                                            <?php foreach ($pageData['users'] as $user): ?>
                                                <tr>
                                                    <td>#<?php echo $user['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($user['country']); ?></td>
                                                    <td><?php echo $user['balance_formatted']; ?></td>
                                                    <td><span class="status-<?php echo $user['status']; ?>"><?php echo ucfirst($user['status']); ?></span></td>
                                                    <td><?php echo $user['join_date']; ?></td>
                                                    <td>
                                                        <form method="POST" style="display: inline;">
                                                            <?php echo csrfField(); ?>
                                                            <input type="hidden" name="action" value="update_user_status">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <?php if ($user['status'] === 'active'): ?>
                                                                <input type="hidden" name="status" value="suspended">
                                                                <button type="submit" class="action-btn btn-reject">Suspend</button>
                                                            <?php else: ?>
                                                                <input type="hidden" name="status" value="active">
                                                                <button type="submit" class="action-btn btn-approve">Activate</button>
                                                            <?php endif; ?>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" style="text-align: center; color: #cbe5ff;">No users found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if (!empty($pageData['pagination']) && $pageData['pagination']['pages'] > 1): ?>
                                <nav>
                                    <ul class="pagination">
                                        <?php for ($i = 1; $i <= $pageData['pagination']['pages']; $i++): ?>
                                            <li class="page-item <?php echo $i === $pageData['pagination']['page'] ? 'active' : ''; ?>">
                                                <a class="page-link" href="?section=users&page=<?php echo $i; ?>&search=<?php echo urlencode($_GET['search'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Transaction Management Section -->
                    <div id="admin-transactions-section" class="admin-section" style="display: <?php echo $currentSection === 'transactions' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Transaction Management</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Monitor and manage all platform transactions.</p>
                        </div>
                        
                        <!-- Transaction Filters -->
                        <div class="dashboard-card">
                            <form method="GET" class="row">
                                <input type="hidden" name="section" value="transactions">
                                <div class="col-md-3">
                                    <select name="type" class="form-control">
                                        <option value="">All Types</option>
                                        <option value="buy_crypto" <?php echo ($_GET['type'] ?? '') === 'buy_crypto' ? 'selected' : ''; ?>>Buy Crypto</option>
                                        <option value="sell_crypto" <?php echo ($_GET['type'] ?? '') === 'sell_crypto' ? 'selected' : ''; ?>>Sell Crypto</option>
                                        <option value="gift_card_exchange" <?php echo ($_GET['type'] ?? '') === 'gift_card_exchange' ? 'selected' : ''; ?>>Gift Card</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <option value="pending" <?php echo ($_GET['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                        <option value="processing" <?php echo ($_GET['status'] ?? '') === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                        <option value="completed" <?php echo ($_GET['status'] ?? '') === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                        <option value="failed" <?php echo ($_GET['status'] ?? '') === 'failed' ? 'selected' : ''; ?>>Failed</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="gradient-btn" style="width: 100%;">Filter</button>
                                </div>
                            </form>
                        </div>

                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">All Transactions</h3>
                            <div class="admin-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Type</th>
                                            <th>Amount</th>
                                            <th>Fee</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($pageData['transactions'])): ?>
                                            <?php foreach ($pageData['transactions'] as $transaction): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($transaction['transaction_id']); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['user_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['type_formatted']); ?></td>
                                                    <td><?php echo $transaction['amount_formatted']; ?></td>
                                                    <td><?php echo $transaction['fee_formatted']; ?></td>
                                                    <td><span class="status-<?php echo $transaction['status']; ?>"><?php echo ucfirst($transaction['status']); ?></span></td>
                                                    <td><?php echo $transaction['date_formatted']; ?></td>
                                                    <td>
                                                        <?php if (in_array($transaction['status'], ['pending', 'processing'], true)): ?>
                                                            <form method="POST" style="display: inline;">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="action" value="update_transaction">
                                                                <input type="hidden" name="transaction_id" value="<?php echo (int)$transaction['id']; ?>">
                                                                <input type="hidden" name="transaction_action" value="approve">
                                                                <button type="submit" class="action-btn btn-approve">Approve</button>
                                                            </form>
                                                            <form method="POST" style="display: inline;">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="action" value="update_transaction">
                                                                <input type="hidden" name="transaction_id" value="<?php echo (int)$transaction['id']; ?>">
                                                                <input type="hidden" name="transaction_action" value="cancel">
                                                                <button type="submit" class="action-btn btn-reject">Cancel</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span style="color: #cbe5ff;">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="8" style="text-align: center; color: #cbe5ff;">No transactions found</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if (!empty($pageData['pagination']) && $pageData['pagination']['pages'] > 1): ?>
                                <nav>
                                    <ul class="pagination">
                                        <?php for ($i = 1; $i <= $pageData['pagination']['pages']; $i++): ?>
                                            <li class="page-item <?php echo $i === $pageData['pagination']['page'] ? 'active' : ''; ?>">
                                                <a class="page-link" href="?section=transactions&page=<?php echo $i; ?>&type=<?php echo urlencode($_GET['type'] ?? ''); ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="admin-giftcards-section" class="admin-section" style="display: <?php echo $currentSection === 'giftcards' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Gift Card Management</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Review and manage gift card exchange requests.</p>
                        </div>

                        <?php if (!empty($pageData['detail'])): $detail = $pageData['detail']; ?>
                            <div class="dashboard-card">
                                <h3 style="color: #fff; margin-bottom: 20px;">Submission <?php echo h($detail['submission_id']); ?></h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p style="color:#cbe5ff;"><strong>User:</strong> <?php echo h($detail['user_name']); ?> (<?php echo h($detail['user_email']); ?>)</p>
                                        <p style="color:#cbe5ff;"><strong>Brand:</strong> <?php echo h($detail['brand_name']); ?></p>
                                        <p style="color:#cbe5ff;"><strong>Card value:</strong> <?php echo $detail['card_value_formatted']; ?></p>
                                        <p style="color:#cbe5ff;"><strong>Payout:</strong> <?php echo $detail['payout_amount_formatted']; ?></p>
                                        <p style="color:#cbe5ff;"><strong>Card code:</strong>
                                            <code style="background:#111;color:#7CFC00;padding:4px 8px;border-radius:4px;word-break:break-all;"><?php echo h($detail['card_code_decrypted'] !== false ? $detail['card_code_decrypted'] : '[unable to decrypt]'); ?></code>
                                        </p>
                                        <p style="color:#cbe5ff;"><strong>Status:</strong> <?php echo h(ucfirst($detail['status'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if (!empty($detail['card_image_url'])): ?>
                                            <a href="<?php echo h($detail['card_image_url']); ?>" target="_blank" rel="noopener">
                                                <img src="<?php echo h($detail['card_image_url']); ?>" alt="Gift card" style="max-width:100%;border-radius:8px;">
                                            </a>
                                        <?php else: ?>
                                            <p style="color:#fe998b;">No image was uploaded.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($detail['status'] === 'pending'): ?>
                                    <div style="margin-top:20px;">
                                        <form method="POST" style="display:inline;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="review_gift_card">
                                            <input type="hidden" name="submission_id" value="<?php echo (int)$detail['id']; ?>">
                                            <input type="hidden" name="review_action" value="approve">
                                            <button type="submit" class="action-btn btn-approve">Approve &amp; credit</button>
                                        </form>
                                        <form method="POST" style="display:inline;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="action" value="review_gift_card">
                                            <input type="hidden" name="submission_id" value="<?php echo (int)$detail['id']; ?>">
                                            <input type="hidden" name="review_action" value="reject">
                                            <input type="text" name="rejection_reason" class="form-control" placeholder="Reason" style="display:inline-block;width:240px;">
                                            <button type="submit" class="action-btn btn-reject">Reject</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                                <p style="margin-top:15px;"><a href="?section=giftcards" style="color:#fe998b;">← Back to submissions</a></p>
                            </div>
                        <?php endif; ?>

                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Pending Gift Card Reviews</h3>
                            <div class="admin-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>User</th>
                                            <th>Brand</th>
                                            <th>Value</th>
                                            <th>Payout</th>
                                            <th>Submitted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($pendingReviews)): ?>
                                            <?php foreach ($pendingReviews as $review): ?>
                                                <tr>
                                                    <td><a href="?section=giftcards&view=<?php echo (int)$review['id']; ?>" style="color:#fe998b;">#GC<?php echo (int)$review['id']; ?></a></td>
                                                    <td><?php echo h($review['user_name']); ?></td>
                                                    <td><?php echo h($review['brand_name']); ?></td>
                                                    <td><?php echo $review['card_value_formatted']; ?></td>
                                                    <td><?php echo $review['payout_amount_formatted']; ?></td>
                                                    <td><?php echo $review['time_ago']; ?></td>
                                                    <td>
                                                        <form method="POST" style="display: inline;">
                                                            <?php echo csrfField(); ?>
                                                            <input type="hidden" name="action" value="review_gift_card">
                                                            <input type="hidden" name="submission_id" value="<?php echo $review['id']; ?>">
                                                            <input type="hidden" name="review_action" value="approve">
                                                            <button type="submit" class="action-btn btn-approve">Approve</button>
                                                        </form>
                                                        <form method="POST" style="display: inline;">
                                                            <?php echo csrfField(); ?>
                                                            <input type="hidden" name="action" value="review_gift_card">
                                                            <input type="hidden" name="submission_id" value="<?php echo $review['id']; ?>">
                                                            <input type="hidden" name="review_action" value="reject">
                                                            <input type="hidden" name="rejection_reason" value="Invalid or used card">
                                                            <button type="submit" class="action-btn btn-reject">Reject</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" style="text-align: center; color: #cbe5ff;">No pending reviews</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Transfer Requests Section -->
                    <div id="admin-transfers-section" class="admin-section" style="display: <?php echo $currentSection === 'transfers' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Crypto Transfer Requests</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Verify user deposits and payouts, then upload proof of payment.</p>
                        </div>

                        <?php if (!empty($pageData['detail'])): $t = $pageData['detail']; ?>
                            <div class="dashboard-card">
                                <h3 style="color:#fff;margin-bottom:20px;"><?php echo h($t['reference']); ?> — <?php echo h($t['direction_label']); ?></h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p style="color:#cbe5ff;"><strong>User:</strong> <?php echo h($t['user_name']); ?> (<?php echo h($t['user_email']); ?>)</p>
                                        <p style="color:#cbe5ff;"><strong>Status:</strong> <?php echo h($t['status_label']); ?></p>
                                        <p style="color:#cbe5ff;"><strong><?php echo $t['direction'] === 'deposit' ? 'Crypto sent' : 'Amount requested'; ?>:</strong> <?php echo $t['crypto_amount_formatted']; ?></p>
                                        <p style="color:#cbe5ff;"><strong>USD value:</strong> <?php echo $t['amount_usd_formatted']; ?></p>
                                        <p style="color:#cbe5ff;"><strong>Fee:</strong> <?php echo $t['fee_formatted']; ?></p>
                                        <p style="color:#cbe5ff;"><strong>Payout:</strong> <?php echo $t['payout_amount_formatted']; ?> (<?php echo h($t['payout_currency']); ?>)</p>
                                        <p style="color:#cbe5ff;"><strong>Rate used:</strong> <?php echo h($t['rate_used']); ?></p>
                                        <p style="color:#cbe5ff;"><strong>User tx hash:</strong> <?php echo h($t['user_tx_hash']); ?></p>
                                        <p style="color:#cbe5ff;"><strong>Admin tx hash:</strong> <?php echo h($t['admin_tx_hash'] ?: '—'); ?></p>
                                        <p style="color:#cbe5ff;"><strong>User notes:</strong> <?php echo h($t['user_notes'] ?: '—'); ?></p>
                                        <p style="color:#cbe5ff;"><strong>Admin notes:</strong> <?php echo nl2br(h($t['admin_notes'] ?: '—')); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($t['direction'] === 'deposit'): ?>
                                            <p style="color:#cbe5ff;"><strong>Company account:</strong> <?php echo h($t['company_label']); ?></p>
                                            <p style="color:#cbe5ff;"><strong>Company wallet:</strong> <?php echo h($t['company_wallet']); ?></p>
                                        <?php endif; ?>
                                        <p style="color:#cbe5ff;"><strong>Payout to:</strong>
                                            <?php if ($t['payout_method'] === 'bank'): ?>
                                                <?php echo h($t['bank_name'] . ' • ' . $t['account_number'] . ' (' . $t['account_name'] . ')'); ?>
                                            <?php else: ?>
                                                <?php echo h($t['payout_wallet']); ?> (<?php echo h($t['payout_network']); ?>)
                                            <?php endif; ?>
                                        </p>
                                        <p style="color:#cbe5ff;"><strong>User proof:</strong>
                                            <?php if ($t['user_proof_url']): ?>
                                                <a href="<?php echo h($t['user_proof_url']); ?>" target="_blank" rel="noopener" style="color:#fe998b;">View proof</a>
                                            <?php else: ?>—<?php endif; ?>
                                        </p>
                                        <p style="color:#cbe5ff;"><strong>Payout proof:</strong>
                                            <?php if ($t['admin_proof_url']): ?>
                                                <a href="<?php echo h($t['admin_proof_url']); ?>" target="_blank" rel="noopener" style="color:#fe998b;">View proof</a>
                                            <?php else: ?>—<?php endif; ?>
                                        </p>
                                    </div>
                                </div>

                                <?php if (in_array($t['status'], ['proof_submitted', 'under_review'], true)): ?>
                                    <hr style="border-color:rgba(255,255,255,0.1);">
                                    <h4 style="color:#fff;">Review</h4>
                                    <form method="POST" class="row">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="verify_transfer">
                                        <input type="hidden" name="transfer_id" value="<?php echo (int)$t['id']; ?>">
                                        <div class="col-md-8">
                                            <input type="text" name="admin_notes" class="form-control" placeholder="Notes / rejection reason">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" name="review_action" value="verify" class="gradient-btn" style="width:100%;">Verify</button>
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" name="review_action" value="reject" class="action-btn btn-reject" style="width:100%;">Reject</button>
                                        </div>
                                    </form>
                                <?php endif; ?>

                                <?php if (in_array($t['status'], ['verified', 'processing'], true)): ?>
                                    <hr style="border-color:rgba(255,255,255,0.1);">
                                    <h4 style="color:#fff;">Process payout</h4>
                                    <form method="POST" enctype="multipart/form-data" class="row">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="process_transfer">
                                        <input type="hidden" name="transfer_id" value="<?php echo (int)$t['id']; ?>">
                                        <div class="col-md-4">
                                            <input type="text" name="admin_tx_hash" class="form-control" placeholder="Payout tx hash / bank reference" required>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="file" name="admin_proof" class="form-control" accept="image/*" required>
                                        </div>
                                        <div class="col-md-2">
                                            <input type="text" name="admin_notes" class="form-control" placeholder="Notes">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="submit" class="gradient-btn" style="width:100%;">Mark paid</button>
                                        </div>
                                    </form>
                                <?php endif; ?>

                                <p style="margin-top:15px;"><a href="?section=transfers" style="color:#fe998b;">← Back to transfers</a></p>
                            </div>
                        <?php endif; ?>

                        <div class="dashboard-card">
                            <form method="GET" class="row">
                                <input type="hidden" name="section" value="transfers">
                                <div class="col-md-3">
                                    <select name="direction" class="form-control">
                                        <option value="">All Directions</option>
                                        <option value="deposit" <?php echo ($_GET['direction'] ?? '') === 'deposit' ? 'selected' : ''; ?>>Deposits</option>
                                        <option value="withdrawal" <?php echo ($_GET['direction'] ?? '') === 'withdrawal' ? 'selected' : ''; ?>>Withdrawals</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <select name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <?php foreach (['proof_submitted', 'under_review', 'verified', 'processing', 'paid', 'rejected', 'cancelled'] as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo ($_GET['status'] ?? '') === $st ? 'selected' : ''; ?>>
                                                <?php echo ucwords(str_replace('_', ' ', $st)); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="gradient-btn" style="width:100%;">Filter</button>
                                </div>
                            </form>
                        </div>

                        <div class="dashboard-card">
                            <h3 style="color:#fff;margin-bottom:20px;">Requests</h3>
                            <div class="admin-table">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Ref</th>
                                            <th>User</th>
                                            <th>Type</th>
                                            <th>Crypto</th>
                                            <th>Payout</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($pageData['requests'])): ?>
                                            <?php foreach ($pageData['requests'] as $request): ?>
                                                <tr>
                                                    <td><?php echo h($request['reference']); ?></td>
                                                    <td><?php echo h($request['user_name']); ?></td>
                                                    <td><?php echo h($request['direction_label']); ?></td>
                                                    <td><?php echo $request['crypto_amount_formatted']; ?></td>
                                                    <td><?php echo $request['payout_amount_formatted']; ?></td>
                                                    <td><span class="status-<?php echo h($request['status']); ?>"><?php echo h($request['status_label']); ?></span></td>
                                                    <td><?php echo $request['date_formatted']; ?></td>
                                                    <td><a href="?section=transfers&view=<?php echo (int)$request['id']; ?>" class="action-btn btn-view">Open</a></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="8" style="text-align:center;color:#cbe5ff;">No transfer requests found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (!empty($pageData['pagination']['pages']) && $pageData['pagination']['pages'] > 1): ?>
                                <nav><ul class="pagination">
                                    <?php for ($i = 1; $i <= $pageData['pagination']['pages']; $i++): ?>
                                        <li class="page-item <?php echo $i === $pageData['pagination']['page'] ? 'active' : ''; ?>">
                                            <a class="page-link" href="?section=transfers&page=<?php echo $i; ?>&status=<?php echo urlencode($_GET['status'] ?? ''); ?>&direction=<?php echo urlencode($_GET['direction'] ?? ''); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                </ul></nav>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Payout Accounts Section -->
                    <div id="admin-payouts-section" class="admin-section" style="display: <?php echo $currentSection === 'payouts' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">User Payout Accounts</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Verify the bank accounts and wallets users want to be paid into.</p>
                        </div>
                        <div class="dashboard-card">
                            <form method="GET" class="row" style="margin-bottom:15px;">
                                <input type="hidden" name="section" value="payouts">
                                <div class="col-md-4">
                                    <select name="status" class="form-control">
                                        <option value="">All Status</option>
                                        <?php foreach (['pending', 'verified', 'rejected'] as $st): ?>
                                            <option value="<?php echo $st; ?>" <?php echo ($_GET['status'] ?? '') === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2"><button type="submit" class="gradient-btn" style="width:100%;">Filter</button></div>
                            </form>
                            <div class="admin-table">
                                <table class="table">
                                    <thead>
                                        <tr><th>User</th><th>Account</th><th>Status</th><th>Added</th><th>Actions</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($pageData['accounts'])): ?>
                                            <?php foreach ($pageData['accounts'] as $account): ?>
                                                <tr>
                                                    <td><?php echo h($account['user_name']); ?><br><small style="color:#cbe5ff;"><?php echo h($account['user_email']); ?></small></td>
                                                    <td><?php echo h($account['display']); ?><?php if ($account['is_default']): ?> <small style="color:#fe998b;">(default)</small><?php endif; ?></td>
                                                    <td><span class="status-<?php echo $account['status'] === 'verified' ? 'completed' : ($account['status'] === 'rejected' ? 'failed' : 'pending'); ?>"><?php echo h(ucfirst($account['status'])); ?></span></td>
                                                    <td><?php echo $account['created_formatted']; ?></td>
                                                    <td>
                                                        <?php if ($account['status'] === 'pending'): ?>
                                                            <form method="POST" style="display:inline;">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="action" value="review_payout_account">
                                                                <input type="hidden" name="account_id" value="<?php echo (int)$account['id']; ?>">
                                                                <input type="hidden" name="review_action" value="approve">
                                                                <button type="submit" class="action-btn btn-approve">Verify</button>
                                                            </form>
                                                            <form method="POST" style="display:inline;">
                                                                <?php echo csrfField(); ?>
                                                                <input type="hidden" name="action" value="review_payout_account">
                                                                <input type="hidden" name="account_id" value="<?php echo (int)$account['id']; ?>">
                                                                <input type="hidden" name="review_action" value="reject">
                                                                <input type="text" name="rejection_reason" class="form-control" placeholder="Reason" style="display:inline-block;width:140px;">
                                                                <button type="submit" class="action-btn btn-reject">Reject</button>
                                                            </form>
                                                        <?php else: ?>
                                                            <span style="color:#cbe5ff;">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" style="text-align:center;color:#cbe5ff;">No payout accounts found</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Company Accounts Section -->
                    <div id="admin-company-section" class="admin-section" style="display: <?php echo $currentSection === 'company' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Company Receiving Accounts</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Add the bank accounts and crypto wallets users transfer to.</p>
                        </div>

                        <?php $editing = $pageData['editing'] ?? null; ?>
                        <div class="dashboard-card">
                            <h3 style="color:#fff;margin-bottom:20px;"><?php echo $editing ? 'Edit Account' : 'Add Receiving Account'; ?></h3>
                            <form method="POST" enctype="multipart/form-data">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="save_company_account">
                                <?php if ($editing): ?><input type="hidden" name="account_id" value="<?php echo (int)$editing['id']; ?>"><?php endif; ?>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Label</label>
                                            <input type="text" name="label" class="form-control" required value="<?php echo h($editing['label'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Method</label>
                                            <select name="method" class="form-control" id="company-method">
                                                <option value="bank" <?php echo ($editing['method'] ?? '') === 'bank' ? 'selected' : ''; ?>>Bank (NGN)</option>
                                                <option value="crypto" <?php echo ($editing['method'] ?? '') === 'crypto' ? 'selected' : ''; ?>>Crypto wallet</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Active</label>
                                            <select name="is_active" class="form-control">
                                                <option value="1" <?php echo ($editing['is_active'] ?? 1) ? 'selected' : ''; ?>>Yes</option>
                                                <option value="0" <?php echo isset($editing['is_active']) && !$editing['is_active'] ? 'selected' : ''; ?>>No</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Currency</label>
                                            <select name="currency" class="form-control">
                                                <?php foreach (['NGN', 'BTC', 'ETH', 'USDT', 'LTC', 'XRP'] as $cur): ?>
                                                    <option value="<?php echo $cur; ?>" <?php echo ($editing['currency'] ?? '') === $cur ? 'selected' : ''; ?>><?php echo $cur; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Network (crypto)</label>
                                            <select name="network" class="form-control">
                                                <?php foreach (['', 'TRC20', 'ERC20', 'BEP20', 'Bitcoin', 'Litecoin', 'XRP'] as $net): ?>
                                                    <option value="<?php echo $net; ?>" <?php echo ($editing['network'] ?? '') === $net ? 'selected' : ''; ?>><?php echo $net === '' ? '—' : $net; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Wallet address (crypto)</label>
                                            <input type="text" name="wallet_address" class="form-control" value="<?php echo h($editing['wallet_address'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Bank name (NGN)</label>
                                            <input type="text" name="bank_name" class="form-control" value="<?php echo h($editing['bank_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Account name (NGN)</label>
                                            <input type="text" name="account_name" class="form-control" value="<?php echo h($editing['account_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Account number (NGN)</label>
                                            <input type="text" name="account_number" class="form-control" value="<?php echo h($editing['account_number'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">Instructions to users</label>
                                            <textarea name="instructions" class="form-control" rows="2"><?php echo h($editing['instructions'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label style="color:#cbe5ff;">QR code image (optional)</label>
                                            <input type="file" name="qr_code" class="form-control" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="gradient-btn"><?php echo $editing ? 'Update Account' : 'Add Account'; ?></button>
                                <?php if ($editing): ?>
                                    <a href="?section=company" class="action-btn btn-view" style="margin-left:10px;">Cancel</a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <div class="dashboard-card">
                            <h3 style="color:#fff;margin-bottom:20px;">Configured Accounts</h3>
                            <div class="admin-table">
                                <table class="table">
                                    <thead><tr><th>Label</th><th>Details</th><th>Status</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        <?php if (!empty($pageData['accounts'])): ?>
                                            <?php foreach ($pageData['accounts'] as $account): ?>
                                                <tr>
                                                    <td><?php echo h($account['label']); ?></td>
                                                    <td><?php echo h($account['display']); ?></td>
                                                    <td><span class="status-<?php echo $account['is_active'] ? 'completed' : 'cancelled'; ?>"><?php echo $account['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                                                    <td>
                                                        <a href="?section=company&edit=<?php echo (int)$account['id']; ?>" class="action-btn btn-view">Edit</a>
                                                        <form method="POST" style="display:inline;">
                                                            <?php echo csrfField(); ?>
                                                            <input type="hidden" name="action" value="toggle_company_account">
                                                            <input type="hidden" name="account_id" value="<?php echo (int)$account['id']; ?>">
                                                            <button type="submit" class="action-btn btn-reject"><?php echo $account['is_active'] ? 'Disable' : 'Enable'; ?></button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" style="text-align:center;color:#cbe5ff;">No receiving accounts yet. Add one above.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Placeholder sections -->
                    <div id="admin-rates-section" class="admin-section" style="display: <?php echo $currentSection === 'rates' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Exchange Rates Management</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Update cryptocurrency and gift card exchange rates.</p>
                        </div>
                        
                        <!-- Cryptocurrency Rates -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Cryptocurrency Rates</h3>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="update_crypto_rates">
                                <div class="admin-table">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Currency</th>
                                                <th>Buy Rate ($)</th>
                                                <th>Sell Rate ($)</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get current crypto rates
                                            $cryptoRates = $dashboard->getCryptocurrencyRates();
                                            foreach ($cryptoRates as $rate):
                                            ?>
                                                <tr>
                                                    <td><?php echo $rate['name']; ?> (<?php echo strtoupper($rate['symbol']); ?>)</td>
                                                    <td>
                                                        <input type="number" name="buy_rate_<?php echo $rate['symbol']; ?>" 
                                                               class="form-control" value="<?php echo $rate['buy_rate']; ?>" 
                                                               step="0.00000001" style="width: 150px;">
                                                    </td>
                                                    <td>
                                                        <input type="number" name="sell_rate_<?php echo $rate['symbol']; ?>" 
                                                               class="form-control" value="<?php echo $rate['sell_rate']; ?>" 
                                                               step="0.00000001" style="width: 150px;">
                                                    </td>
                                                    <td>
                                                        <span class="status-<?php echo $rate['is_active'] ? 'completed' : 'pending'; ?>">
                                                            <?php echo $rate['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="gradient-btn">Update Crypto Rates</button>
                            </form>
                        </div>

                        <!-- Gift Card Rates -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Gift Card Rates</h3>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="update_giftcard_rates">
                                <div class="admin-table">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Brand</th>
                                                <th>Exchange Rate (%)</th>
                                                <th>Min Amount</th>
                                                <th>Max Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            // Get current gift card rates
                                            $giftCardRates = $dashboard->getGiftCardRates();
                                            foreach ($giftCardRates as $brand):
                                            ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($brand['brand_name']); ?></td>
                                                    <td>
                                                        <input type="number" name="rate_<?php echo $brand['brand_code']; ?>" 
                                                               class="form-control" value="<?php echo $brand['exchange_rate']; ?>" 
                                                               step="0.01" min="0" max="100" style="width: 100px;">
                                                    </td>
                                                    <td>$<?php echo number_format($brand['min_amount'], 0); ?></td>
                                                    <td>$<?php echo number_format($brand['max_amount'], 0); ?></td>
                                                    <td>
                                                        <span class="status-<?php echo $brand['is_active'] ? 'completed' : 'pending'; ?>">
                                                            <?php echo $brand['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" class="gradient-btn">Update Gift Card Rates</button>
                            </form>
                        </div>
                    </div>

                    <div id="admin-support-section" class="admin-section" style="display: <?php echo $currentSection === 'support' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Support Tickets</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Answer customer requests and track their status.</p>
                        </div>
                        <?php if ($currentSection === 'support'): ?>
                        <?php
                        $tkStatus = $_GET['status'] ?? '';
                        $tkSearch = trim((string)($_GET['search'] ?? ''));
                        $adminTicket = !empty($_GET['ticket']) ? $support->getTicket((string)$_GET['ticket']) : null;
                        ?>
                        <?php if ($adminTicket): ?>
                            <div class="dashboard-card">
                                <a href="?section=support" class="btn dfq-ghost btn-sm" style="margin-bottom: 14px;">&larr; All tickets</a>
                                <h3 style="color: #fff; margin-bottom: 4px;"><?php echo h($adminTicket['subject']); ?></h3>
                                <p style="color: #cbe5ff; margin-bottom: 16px;">
                                    #<?php echo h($adminTicket['ticket_id']); ?> &middot;
                                    <?php echo h($adminTicket['first_name'] . ' ' . $adminTicket['last_name']); ?> (<?php echo h($adminTicket['email']); ?>) &middot;
                                    <?php echo h(SupportService::CATEGORIES[$adminTicket['category']] ?? 'General'); ?> &middot;
                                    <?php echo h(ucfirst($adminTicket['priority'])); ?> priority &middot;
                                    <strong><?php echo h(ucwords(str_replace('_', ' ', $adminTicket['status']))); ?></strong>
                                </p>
                                <div class="dfq-thread">
                                    <div class="dfq-msg dfq-msg--me"><div class="dfq-msg__meta">Customer &middot; <?php echo h(date('M d, H:i', strtotime($adminTicket['created_at']))); ?></div><?php echo nl2br(h($adminTicket['message'])); ?></div>
                                    <?php foreach ($adminTicket['replies'] as $reply): ?>
                                        <div class="dfq-msg <?php echo $reply['author_type'] === 'admin' ? 'dfq-msg--staff' : 'dfq-msg--me'; ?>">
                                            <div class="dfq-msg__meta"><?php echo $reply['author_type'] === 'admin' ? 'Support' : 'Customer'; ?> &middot; <?php echo h(date('M d, H:i', strtotime($reply['created_at']))); ?></div>
                                            <?php echo nl2br(h($reply['message'])); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <form method="POST" style="margin-top: 16px;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="reply_ticket">
                                    <input type="hidden" name="ticket_id" value="<?php echo h($adminTicket['ticket_id']); ?>">
                                    <textarea name="message" class="form-control" rows="3" maxlength="5000" placeholder="Write a reply to the customer" required></textarea>
                                    <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                                        <select name="status" class="form-control" style="max-width: 200px;" aria-label="Status after reply">
                                            <option value="in_progress">Reply &amp; mark in progress</option>
                                            <option value="resolved">Reply &amp; mark resolved</option>
                                            <option value="closed">Reply &amp; close</option>
                                        </select>
                                        <button type="submit" class="gradient-btn">Send reply</button>
                                    </div>
                                </form>
                                <form method="POST" style="margin-top: 12px; display: flex; gap: 10px; flex-wrap: wrap;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="action" value="update_ticket_status">
                                    <input type="hidden" name="ticket_id" value="<?php echo h($adminTicket['ticket_id']); ?>">
                                    <select name="status" class="form-control" style="max-width: 200px;" aria-label="Change status">
                                        <?php foreach (SupportService::STATUSES as $s): ?>
                                            <option value="<?php echo h($s); ?>" <?php echo $adminTicket['status'] === $s ? 'selected' : ''; ?>><?php echo h(ucwords(str_replace('_', ' ', $s))); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn dfq-ghost btn-sm">Update status</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <?php $adminTickets = $support->listAllTickets($tkStatus, $tkSearch); ?>
                            <div class="dashboard-card">
                                <form method="GET" class="row">
                                    <input type="hidden" name="section" value="support">
                                    <div class="col-md-5 form-group"><input type="text" name="search" class="form-control" placeholder="Search ticket, subject or email" value="<?php echo h($tkSearch); ?>"></div>
                                    <div class="col-md-4 form-group">
                                        <select name="status" class="form-control" onchange="this.form.submit()">
                                            <option value="">All statuses</option>
                                            <?php foreach (SupportService::STATUSES as $s): ?>
                                                <option value="<?php echo h($s); ?>" <?php echo $tkStatus === $s ? 'selected' : ''; ?>><?php echo h(ucwords(str_replace('_', ' ', $s))); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3 form-group"><button type="submit" class="gradient-btn" style="width:100%;">Filter</button></div>
                                </form>
                                <?php if (empty($adminTickets)): ?>
                                    <p style="color: #cbe5ff; text-align: center; margin: 0;">No tickets match.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table">
                                            <thead><tr><th>Ticket</th><th>Customer</th><th>Subject</th><th>Priority</th><th>Status</th><th>Updated</th></tr></thead>
                                            <tbody>
                                                <?php foreach ($adminTickets as $t): ?>
                                                    <tr>
                                                        <td><a href="?section=support&amp;ticket=<?php echo h(urlencode($t['ticket_id'])); ?>">#<?php echo h($t['ticket_id']); ?></a></td>
                                                        <td><?php echo h($t['first_name'] . ' ' . $t['last_name']); ?><br><small><?php echo h($t['email']); ?></small></td>
                                                        <td><?php echo h($t['subject']); ?></td>
                                                        <td><?php echo h(ucfirst($t['priority'])); ?></td>
                                                        <td><span class="status-<?php echo in_array($t['status'], ['resolved', 'closed'], true) ? 'completed' : 'pending'; ?>"><?php echo h(ucwords(str_replace('_', ' ', $t['status']))); ?></span></td>
                                                        <td><?php echo h(timeAgo($t['updated_at'])); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div id="admin-reports-section" class="admin-section" style="display: <?php echo $currentSection === 'reports' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Reports & Analytics</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Generate comprehensive platform reports.</p>
                        </div>
                        
                        <!-- Report Generation -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Generate Reports</h3>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="generate_report">
                                <div class="row">
                                    <div class="col-md-3">
                                        <select name="report_type" class="form-control" required>
                                            <option value="">Select Report Type</option>
                                            <option value="revenue">Revenue Report</option>
                                            <option value="users">User Activity Report</option>
                                            <option value="transactions">Transaction Summary</option>
                                            <option value="gift_cards">Gift Card Analysis</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" name="start_date" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="date" name="end_date" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="gradient-btn" style="width: 100%;">Generate Report</button>
                                        <button type="submit" name="format" value="csv" class="btn dfq-ghost btn-sm mt-2" style="width: 100%;">Download CSV</button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <?php if (isset($reportRows)): ?>
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;"><?php echo h($reportTitle ?? 'Report'); ?></h3>
                            <?php if (empty($reportRows)): ?>
                                <p style="color: #cbe5ff;">No data for the selected range.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead><tr>
                                            <?php foreach (array_keys($reportRows[0]) as $col): ?>
                                                <th><?php echo h(ucwords(str_replace('_', ' ', $col))); ?></th>
                                            <?php endforeach; ?>
                                        </tr></thead>
                                        <tbody>
                                            <?php foreach ($reportRows as $row): ?>
                                                <tr>
                                                    <?php foreach ($row as $val): ?>
                                                        <td><?php echo h((string)$val); ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Summary Statistics -->
                        <div class="row">
                            <div class="col-md-3">
                                <div class="dashboard-card">
                                    <h4 style="color: #fff;">Total Revenue</h4>
                                    <div class="stat-value"><?php echo isset($stats['revenue']) ? $stats['revenue']['total_formatted'] : '$0'; ?></div>
                                    <small style="color: #cbe5ff;">All time</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="dashboard-card">
                                    <h4 style="color: #fff;">Total Users</h4>
                                    <div class="stat-value"><?php echo $stats['users']['total_users'] ?? 0; ?></div>
                                    <small style="color: #cbe5ff;">Registered</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="dashboard-card">
                                    <h4 style="color: #fff;">Total Transactions</h4>
                                    <div class="stat-value"><?php echo $stats['transactions']['total_transactions'] ?? 0; ?></div>
                                    <small style="color: #cbe5ff;">All time</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="dashboard-card">
                                    <h4 style="color: #fff;">Gift Cards Processed</h4>
                                    <div class="stat-value"><?php echo $stats['gift_cards']['approved_submissions'] ?? 0; ?></div>
                                    <small style="color: #cbe5ff;">Approved</small>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Activity Chart Placeholder -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Platform Activity (Last 30 Days)</h3>
                            <div style="height: 300px; display: flex; align-items: center; justify-content: center; color: #cbe5ff;">
                                <div style="text-align: center;">
                                    <i class="fa fa-bar-chart" style="font-size: 48px; margin-bottom: 20px;"></i>
                                    <p>Advanced charts and analytics will be displayed here</p>
                                    <p><small>Integrate with Chart.js or similar library for detailed analytics</small></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="admin-settings-section" class="admin-section" style="display: <?php echo $currentSection === 'settings' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">System Settings</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Configure platform settings and preferences.</p>
                        </div>
                        
                        <!-- General Settings -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">General Settings</h3>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="update_settings">
                                <input type="hidden" name="usd_to_ngn_rate" value="<?php echo h(getSystemSetting('usd_to_ngn_rate', '1500')); ?>">
                                <input type="hidden" name="transfer_fee_percentage" value="<?php echo h(getSystemSetting('transfer_fee_percentage', '0.5')); ?>">
                                <input type="hidden" name="min_transfer_usd" value="<?php echo h(getSystemSetting('min_transfer_usd', '10')); ?>">
                                <input type="hidden" name="max_transfer_usd" value="<?php echo h(getSystemSetting('max_transfer_usd', '50000')); ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label style="color: #cbe5ff;">Site Name</label>
                                            <input type="text" name="site_name" class="form-control" 
                                                   value="<?php echo htmlspecialchars(getSystemSetting('site_name', 'DFQ Exchange')); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label style="color: #cbe5ff;">Trading Fee (%)</label>
                                            <input type="number" name="trading_fee_percentage" class="form-control" 
                                                   value="<?php echo getSystemSetting('trading_fee_percentage', '0.5'); ?>" 
                                                   step="0.01" min="0" max="10">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label style="color: #cbe5ff;">Minimum Trade Amount ($)</label>
                                            <input type="number" name="min_trade_amount" class="form-control" 
                                                   value="<?php echo getSystemSetting('min_trade_amount', '10.00'); ?>" 
                                                   step="0.01" min="1">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label style="color: #cbe5ff;">Maximum Trade Amount ($)</label>
                                            <input type="number" name="max_trade_amount" class="form-control" 
                                                   value="<?php echo getSystemSetting('max_trade_amount', '50000.00'); ?>" 
                                                   step="0.01" min="100">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label style="color: #cbe5ff;">Gift Card Processing Time (hours)</label>
                                            <input type="number" name="gift_card_processing_time" class="form-control" 
                                                   value="<?php echo getSystemSetting('gift_card_processing_time', '24'); ?>" 
                                                   min="1" max="168">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label style="color: #cbe5ff;">Maintenance Mode</label>
                                            <select name="maintenance_mode" class="form-control">
                                                <option value="0" <?php echo getSystemSetting('maintenance_mode', '0') == '0' ? 'selected' : ''; ?>>Disabled</option>
                                                <option value="1" <?php echo getSystemSetting('maintenance_mode', '0') == '1' ? 'selected' : ''; ?>>Enabled</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="gradient-btn">Update Settings</button>
                            </form>
                        </div>

                        <!-- Transfer Settings -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Transfer &amp; Payout Settings</h3>
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="update_settings">
                                <input type="hidden" name="site_name" value="<?php echo h(getSystemSetting('site_name', 'DFQ Exchange')); ?>">
                                <input type="hidden" name="trading_fee_percentage" value="<?php echo h(getSystemSetting('trading_fee_percentage', '1.00')); ?>">
                                <input type="hidden" name="min_trade_amount" value="<?php echo h(getSystemSetting('min_trade_amount', '10.00')); ?>">
                                <input type="hidden" name="max_trade_amount" value="<?php echo h(getSystemSetting('max_trade_amount', '50000.00')); ?>">
                                <input type="hidden" name="gift_card_processing_time" value="<?php echo h(getSystemSetting('gift_card_processing_time', '24')); ?>">
                                <input type="hidden" name="maintenance_mode" value="<?php echo h(getSystemSetting('maintenance_mode', '0')); ?>">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label style="color: #cbe5ff;">USD → NGN Rate</label>
                                            <input type="number" name="usd_to_ngn_rate" class="form-control"
                                                   value="<?php echo h(getSystemSetting('usd_to_ngn_rate', '1500')); ?>"
                                                   step="0.01" min="1">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label style="color: #cbe5ff;">Transfer Fee (%)</label>
                                            <input type="number" name="transfer_fee_percentage" class="form-control"
                                                   value="<?php echo h(getSystemSetting('transfer_fee_percentage', '0.5')); ?>"
                                                   step="0.01" min="0" max="100">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label style="color: #cbe5ff;">Minimum Transfer (USD)</label>
                                            <input type="number" name="min_transfer_usd" class="form-control"
                                                   value="<?php echo h(getSystemSetting('min_transfer_usd', '10')); ?>"
                                                   step="0.01" min="1">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label style="color: #cbe5ff;">Maximum Transfer (USD)</label>
                                            <input type="number" name="max_transfer_usd" class="form-control"
                                                   value="<?php echo h(getSystemSetting('max_transfer_usd', '50000')); ?>"
                                                   step="0.01" min="1">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="gradient-btn">Update Transfer Settings</button>
                            </form>
                        </div>

                        <!-- System Information -->
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">System Information</h3>
                            <div class="row">
                                <div class="col-md-6">
                                    <p style="color: #cbe5ff;"><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
                                    <p style="color: #cbe5ff;"><strong>MySQL Version:</strong>
                                        <?php echo h($dashboard->getDatabaseVersion() ?: 'Unable to fetch'); ?>
                                    </p>
                                    <p style="color: #cbe5ff;"><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p style="color: #cbe5ff;"><strong>Disk Space:</strong> 
                                        <?php 
                                        $bytes = disk_free_space(".");
                                        $si_prefix = array( 'B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB' );
                                        $base = 1024;
                                        $class = min((int)log($bytes , $base) , count($si_prefix) - 1);
                                        echo sprintf('%1.2f' , $bytes / pow($base,$class)) . ' ' . $si_prefix[$class] . ' free';
                                        ?>
                                    </p>
                                    <p style="color: #cbe5ff;"><strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?></p>
                                    <p style="color: #cbe5ff;"><strong>Upload Max Size:</strong> <?php echo ini_get('upload_max_filesize'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--admin dashboard area end-->

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
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);

        // Confirmation for critical actions
        document.querySelectorAll('.btn-reject').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to perform this action?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
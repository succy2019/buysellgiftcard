<?php
session_start();
require_once 'includes/admin_auth.php';
require_once 'includes/admin_dashboard.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin-login.php');
    exit();
}

$auth = new AdminAuth();
$dashboard = new AdminDashboard();

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

// Handle logout
if (isset($_GET['logout'])) {
    $auth->logout();
    header('Location: admin-login.php');
    exit();
}

$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
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
        }
    }
}

// Get current page data based on section
$currentSection = $_GET['section'] ?? 'dashboard';
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
        break;
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Admin Dashboard - FEX</title>
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
                                <li><a href="#" class="active">Admin Panel</a></li>
                                <li><a href="#" onclick="showAdminSection('dashboard')">Dashboard</a></li>
                                <li><a href="#" onclick="showAdminSection('users')">Users</a></li>
                                <li><a href="#" onclick="showAdminSection('transactions')">Transactions</a></li>
                                <li><a href="#" onclick="showAdminSection('giftcards')">Gift Cards</a></li>
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
                            <li><a href="#" class="<?php echo $currentSection === 'dashboard' ? 'active' : ''; ?>" onclick="showAdminSection('dashboard')"><i class="fa fa-dashboard"></i>Dashboard</a></li>
                            <li><a href="#" class="<?php echo $currentSection === 'users' ? 'active' : ''; ?>" onclick="showAdminSection('users')"><i class="fa fa-users"></i>User Management</a></li>
                            <li><a href="#" class="<?php echo $currentSection === 'transactions' ? 'active' : ''; ?>" onclick="showAdminSection('transactions')"><i class="fa fa-exchange"></i>Transactions</a></li>
                            <li><a href="#" class="<?php echo $currentSection === 'giftcards' ? 'active' : ''; ?>" onclick="showAdminSection('giftcards')"><i class="fa fa-gift"></i>Gift Cards</a></li>
                            <li><a href="#" class="<?php echo $currentSection === 'rates' ? 'active' : ''; ?>" onclick="showAdminSection('rates')"><i class="fa fa-line-chart"></i>Exchange Rates</a></li>
                            <li><a href="#" class="<?php echo $currentSection === 'reports' ? 'active' : ''; ?>" onclick="showAdminSection('reports')"><i class="fa fa-bar-chart"></i>Reports</a></li>
                            <li><a href="#" class="<?php echo $currentSection === 'settings' ? 'active' : ''; ?>" onclick="showAdminSection('settings')"><i class="fa fa-cog"></i>Settings</a></li>
                            <li><a href="?logout=1" class="logout-btn"><i class="fa fa-sign-out"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Main Admin Content -->
                <div class="col-lg-9">
                    <!-- Global Alerts -->
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>

                    <!-- Admin Dashboard Section -->
                    <div id="admin-dashboard-section" class="admin-section" style="display: <?php echo $currentSection === 'dashboard' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">FEX Admin Dashboard</h1>
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
                                                        <input type="hidden" name="action" value="review_gift_card">
                                                        <input type="hidden" name="submission_id" value="<?php echo $review['id']; ?>">
                                                        <input type="hidden" name="review_action" value="approve">
                                                        <button type="submit" class="action-btn btn-approve" style="padding: 4px 8px; font-size: 0.7rem;">Approve</button>
                                                    </form>
                                                    <form method="POST" style="display: inline;">
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

                    <!-- Other sections would be implemented similarly -->
                    <div id="admin-transactions-section" class="admin-section" style="display: <?php echo $currentSection === 'transactions' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Transaction Management</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Monitor and manage all platform transactions.</p>
                        </div>
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Recent Transactions</h3>
                            <p style="color: #cbe5ff;">Full transaction management interface coming soon...</p>
                        </div>
                    </div>

                    <div id="admin-giftcards-section" class="admin-section" style="display: <?php echo $currentSection === 'giftcards' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Gift Card Management</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Review and manage gift card exchange requests.</p>
                        </div>
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
                                                    <td>#GC<?php echo $review['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($review['user_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($review['brand_name']); ?></td>
                                                    <td><?php echo $review['card_value_formatted']; ?></td>
                                                    <td><?php echo $review['payout_amount_formatted']; ?></td>
                                                    <td><?php echo $review['time_ago']; ?></td>
                                                    <td>
                                                        <form method="POST" style="display: inline;">
                                                            <input type="hidden" name="action" value="review_gift_card">
                                                            <input type="hidden" name="submission_id" value="<?php echo $review['id']; ?>">
                                                            <input type="hidden" name="review_action" value="approve">
                                                            <button type="submit" class="action-btn btn-approve">Approve</button>
                                                        </form>
                                                        <form method="POST" style="display: inline;">
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

                    <!-- Placeholder sections -->
                    <div id="admin-rates-section" class="admin-section" style="display: <?php echo $currentSection === 'rates' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Exchange Rates Management</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Update cryptocurrency and gift card exchange rates.</p>
                        </div>
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Rate management interface coming soon...</h3>
                        </div>
                    </div>

                    <div id="admin-reports-section" class="admin-section" style="display: <?php echo $currentSection === 'reports' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">Reports & Analytics</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Generate comprehensive platform reports.</p>
                        </div>
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Reporting interface coming soon...</h3>
                        </div>
                    </div>

                    <div id="admin-settings-section" class="admin-section" style="display: <?php echo $currentSection === 'settings' ? 'block' : 'none'; ?>;">
                        <div class="dashboard-header">
                            <h1 style="color: #fff; margin: 0;">System Settings</h1>
                            <p style="color: #cbe5ff; margin: 10px 0 0 0;">Configure platform settings and preferences.</p>
                        </div>
                        <div class="dashboard-card">
                            <h3 style="color: #fff; margin-bottom: 20px;">Settings interface coming soon...</h3>
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
    <!-- main js-->
    <script src="static/assets/js/main.js"></script>
    
    <script>
        function showAdminSection(sectionName) {
            // Update URL
            window.location.href = '?section=' + sectionName;
        }

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
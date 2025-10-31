<?php
/**
 * API Endpoints for FEX Trading Platform
 * Handles AJAX requests for dynamic functionality
 */

session_start();
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

require_once 'includes/user_auth.php';
require_once 'includes/user_dashboard.php';
require_once 'includes/admin_auth.php';
require_once 'includes/admin_dashboard.php';

// Get request method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['endpoint'] ?? '';

// Response helper function
function apiResponse($success, $data = null, $message = '', $code = 200) {
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => time()
    ]);
    exit();
}

// Authentication helper
function requireAuth($type = 'user') {
    if ($type === 'user') {
        if (!isset($_SESSION['user_id'])) {
            apiResponse(false, null, 'Authentication required', 401);
        }
        return $_SESSION['user_id'];
    } elseif ($type === 'admin') {
        if (!isset($_SESSION['admin_id'])) {
            apiResponse(false, null, 'Admin authentication required', 401);
        }
        return $_SESSION['admin_id'];
    }
}

try {
    switch ($endpoint) {
        
        // User Authentication Endpoints
        case 'user/login':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Method not allowed', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                apiResponse(false, null, 'Email and password required', 400);
            }
            
            $auth = new UserAuth();
            $result = $auth->login($email, $password);
            apiResponse($result['success'], $result['data'] ?? null, $result['message']);
            break;
            
        case 'user/register':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Method not allowed', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $firstName = $input['first_name'] ?? '';
            $lastName = $input['last_name'] ?? '';
            $email = $input['email'] ?? '';
            $phone = $input['phone'] ?? '';
            $password = $input['password'] ?? '';
            $country = $input['country'] ?? '';
            
            if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
                apiResponse(false, null, 'Required fields missing', 400);
            }
            
            $auth = new UserAuth();
            $result = $auth->register($firstName, $lastName, $email, $phone, $password, $country);
            apiResponse($result['success'], $result['data'] ?? null, $result['message']);
            break;
            
        case 'user/logout':
            $auth = new UserAuth();
            $result = $auth->logout();
            apiResponse($result['success'], null, $result['message']);
            break;
            
        // User Dashboard Endpoints
        case 'user/dashboard':
            $userId = requireAuth('user');
            $dashboard = new UserDashboard();
            $data = $dashboard->getDashboardData($userId);
            apiResponse(true, $data);
            break;
            
        case 'user/balance':
            $userId = requireAuth('user');
            $dashboard = new UserDashboard();
            $balance = $dashboard->getUserBalance($userId);
            apiResponse(true, ['balance' => $balance]);
            break;
            
        case 'user/crypto-holdings':
            $userId = requireAuth('user');
            $dashboard = new UserDashboard();
            $holdings = $dashboard->getCryptoHoldings($userId);
            apiResponse(true, $holdings);
            break;
            
        case 'user/transactions':
            $userId = requireAuth('user');
            $page = (int)($_GET['page'] ?? 1);
            $limit = (int)($_GET['limit'] ?? 20);
            
            $dashboard = new UserDashboard();
            $transactions = $dashboard->getTransactionHistory($userId, $page, $limit);
            apiResponse(true, $transactions);
            break;
            
        case 'user/buy-crypto':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Method not allowed', 405);
            }
            
            $userId = requireAuth('user');
            $input = json_decode(file_get_contents('php://input'), true);
            $symbol = $input['symbol'] ?? '';
            $amount = (float)($input['amount'] ?? 0);
            
            if (empty($symbol) || $amount <= 0) {
                apiResponse(false, null, 'Invalid parameters', 400);
            }
            
            $dashboard = new UserDashboard();
            $result = $dashboard->buyCryptocurrency($userId, $symbol, $amount);
            apiResponse($result['success'], $result['data'] ?? null, $result['message']);
            break;
            
        case 'user/sell-crypto':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Method not allowed', 405);
            }
            
            $userId = requireAuth('user');
            $input = json_decode(file_get_contents('php://input'), true);
            $symbol = $input['symbol'] ?? '';
            $amount = (float)($input['amount'] ?? 0);
            
            if (empty($symbol) || $amount <= 0) {
                apiResponse(false, null, 'Invalid parameters', 400);
            }
            
            $dashboard = new UserDashboard();
            $result = $dashboard->sellCryptocurrency($userId, $symbol, $amount);
            apiResponse($result['success'], $result['data'] ?? null, $result['message']);
            break;
            
        case 'user/submit-giftcard':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Method not allowed', 405);
            }
            
            $userId = requireAuth('user');
            $brand = $_POST['brand'] ?? '';
            $value = (float)($_POST['value'] ?? 0);
            $code = $_POST['code'] ?? '';
            
            if (empty($brand) || $value <= 0 || empty($code)) {
                apiResponse(false, null, 'Invalid parameters', 400);
            }
            
            // Handle file upload
            $imagePath = '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $dashboard = new UserDashboard();
                $uploadResult = $dashboard->uploadGiftCardImage($_FILES['image']);
                if ($uploadResult['success']) {
                    $imagePath = $uploadResult['file_path'];
                } else {
                    apiResponse(false, null, 'Image upload failed', 400);
                }
            }
            
            $dashboard = new UserDashboard();
            $result = $dashboard->submitGiftCard($userId, $brand, $value, $code, $imagePath);
            apiResponse($result['success'], $result['data'] ?? null, $result['message']);
            break;
            
        // Public Endpoints
        case 'crypto-rates':
            $dashboard = new UserDashboard();
            $rates = $dashboard->getCryptocurrencyRates();
            apiResponse(true, $rates);
            break;
            
        case 'giftcard-rates':
            $dashboard = new UserDashboard();
            $rates = $dashboard->getGiftCardRates();
            apiResponse(true, $rates);
            break;
            
        // Admin Authentication Endpoints
        case 'admin/login':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Method not allowed', 405);
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $email = $input['email'] ?? '';
            $password = $input['password'] ?? '';
            
            if (empty($email) || empty($password)) {
                apiResponse(false, null, 'Email and password required', 400);
            }
            
            $auth = new AdminAuth();
            $result = $auth->login($email, $password);
            apiResponse($result['success'], $result['data'] ?? null, $result['message']);
            break;
            
        case 'admin/logout':
            $auth = new AdminAuth();
            $result = $auth->logout();
            apiResponse($result['success'], null, $result['message']);
            break;
            
        // Admin Dashboard Endpoints
        case 'admin/dashboard':
            $adminId = requireAuth('admin');
            $dashboard = new AdminDashboard();
            $data = $dashboard->getDashboardOverview();
            apiResponse($data['success'], $data['data'] ?? null, $data['message'] ?? '');
            break;
            
        case 'admin/users':
            $adminId = requireAuth('admin');
            $page = (int)($_GET['page'] ?? 1);
            $search = $_GET['search'] ?? '';
            $status = $_GET['status'] ?? '';
            
            $dashboard = new AdminDashboard();
            $users = $dashboard->getUsers($page, 20, $search, $status);
            apiResponse(true, $users);
            break;
            
        case 'admin/transactions':
            $adminId = requireAuth('admin');
            $page = (int)($_GET['page'] ?? 1);
            $type = $_GET['type'] ?? '';
            $status = $_GET['status'] ?? '';
            
            $dashboard = new AdminDashboard();
            $transactions = $dashboard->getTransactions($page, 20, $type, $status);
            apiResponse(true, $transactions);
            break;
            
        case 'admin/giftcards':
            $adminId = requireAuth('admin');
            $page = (int)($_GET['page'] ?? 1);
            $status = $_GET['status'] ?? '';
            
            $dashboard = new AdminDashboard();
            $submissions = $dashboard->getGiftCardSubmissions($page, 20, $status);
            apiResponse(true, $submissions);
            break;
            
        case 'admin/update-user-status':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Method not allowed', 405);
            }
            
            $adminId = requireAuth('admin');
            $input = json_decode(file_get_contents('php://input'), true);
            $userId = (int)($input['user_id'] ?? 0);
            $status = $input['status'] ?? '';
            
            if ($userId <= 0 || empty($status)) {
                apiResponse(false, null, 'Invalid parameters', 400);
            }
            
            $dashboard = new AdminDashboard();
            $result = $dashboard->updateUserStatus($userId, $status);
            apiResponse($result['success'], null, $result['message']);
            break;
            
        case 'admin/review-giftcard':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Method not allowed', 405);
            }
            
            $adminId = requireAuth('admin');
            $input = json_decode(file_get_contents('php://input'), true);
            $submissionId = (int)($input['submission_id'] ?? 0);
            $action = $input['action'] ?? '';
            $rejectionReason = $input['rejection_reason'] ?? '';
            
            if ($submissionId <= 0 || empty($action)) {
                apiResponse(false, null, 'Invalid parameters', 400);
            }
            
            $dashboard = new AdminDashboard();
            $result = $dashboard->reviewGiftCardSubmission($submissionId, $action, $rejectionReason);
            apiResponse($result['success'], null, $result['message']);
            break;
            
        case 'admin/update-crypto-rates':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Method not allowed', 405);
            }
            
            $adminId = requireAuth('admin');
            $input = json_decode(file_get_contents('php://input'), true);
            $rates = $input['rates'] ?? [];
            
            if (empty($rates)) {
                apiResponse(false, null, 'No rates provided', 400);
            }
            
            $dashboard = new AdminDashboard();
            $result = $dashboard->updateCryptoRates($rates);
            apiResponse($result['success'], null, $result['message']);
            break;
            
        case 'admin/update-giftcard-rates':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Method not allowed', 405);
            }
            
            $adminId = requireAuth('admin');
            $input = json_decode(file_get_contents('php://input'), true);
            $rates = $input['rates'] ?? [];
            
            if (empty($rates)) {
                apiResponse(false, null, 'No rates provided', 400);
            }
            
            $dashboard = new AdminDashboard();
            $result = $dashboard->updateGiftCardRates($rates);
            apiResponse($result['success'], null, $result['message']);
            break;
            
        case 'admin/generate-report':
            if ($method !== 'POST') {
                apiResponse(false, null, 'Method not allowed', 405);
            }
            
            $adminId = requireAuth('admin');
            $input = json_decode(file_get_contents('php://input'), true);
            $type = $input['type'] ?? '';
            $startDate = $input['start_date'] ?? '';
            $endDate = $input['end_date'] ?? '';
            
            if (empty($type) || empty($startDate) || empty($endDate)) {
                apiResponse(false, null, 'Missing required parameters', 400);
            }
            
            $dashboard = new AdminDashboard();
            $result = $dashboard->generateReport($type, $startDate, $endDate);
            apiResponse($result['success'], $result['data'] ?? null, $result['message'] ?? '');
            break;
            
        // System Status Endpoints
        case 'system/status':
            $status = [
                'server' => 'online',
                'api' => 'active',
                'database' => 'optimal',
                'timestamp' => time()
            ];
            apiResponse(true, $status);
            break;
            
        case 'system/health':
            // Basic health check
            try {
                $db = getDB();
                $db->query("SELECT 1");
                apiResponse(true, ['status' => 'healthy', 'database' => 'connected']);
            } catch (Exception $e) {
                apiResponse(false, ['status' => 'unhealthy', 'database' => 'disconnected'], 'Database connection failed');
            }
            break;
            
        default:
            apiResponse(false, null, 'Endpoint not found', 404);
            break;
    }
    
} catch (Exception $e) {
    error_log('API Error: ' . $e->getMessage());
    apiResponse(false, null, 'Internal server error', 500);
}
?>
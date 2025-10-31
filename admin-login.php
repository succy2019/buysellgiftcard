<?php
session_start();
require_once 'includes/admin_auth.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    header('Location: admin-dashboard.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $auth = new AdminAuth();
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            header('Location: admin-dashboard.php');
            exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Admin Login - FEX</title>
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
        .admin-login-area {
            min-height: 100vh;
            background: #062489;
            display: flex;
            align-items: center;
            padding: 80px 0 50px;
        }
        .admin-login-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 40px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border-left: 4px solid #dc3545;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            padding: 15px 20px;
            color: #fff;
            font-size: 16px;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
            color: #fff;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .admin-login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .admin-login-header h1 {
            color: #fff;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .admin-login-header p {
            color: #cbe5ff;
            font-size: 1.1rem;
        }
        .admin-login-header .admin-icon {
            font-size: 4rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .admin-login-footer {
            text-align: center;
            margin-top: 30px;
        }
        .admin-login-footer a {
            color: #dc3545;
            text-decoration: none;
        }
        .admin-login-footer a:hover {
            color: #fff;
        }
        .admin-btn {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            border: none;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 20px;
        }
        .admin-btn:hover {
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(220, 53, 69, 0.3);
        }
        .security-notice {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        .security-notice i {
            color: #dc3545;
            margin-right: 10px;
        }
        .security-notice span {
            color: #cbe5ff;
            font-size: 14px;
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
                                <li><a href="index.html">Home</a></li>
                                <li><a href="index.html#about">About</a></li>
                                <li><a href="index.html#roadmap">Roadmap</a></li>
                                <li><a href="index.html#team">Team</a></li>
                                <li><a href="index.html#contact">Contact</a></li>
                            </ul>
                        </nav>
                    </div>
                </div>
                <div class="col-4 col-lg-2 text-right">
                    <a href="login.php" class="logibtn gradient-btn">User Login</a>
                </div>
            </div>
        </div>
    </div>
    <!--header area end-->

    <!--admin login area start-->
    <div class="admin-login-area wow fadeInUp">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8 col-lg-6">
                    <div class="admin-login-card">
                        <div class="admin-login-header">
                            <div class="admin-icon">
                                <i class="fa fa-shield"></i>
                            </div>
                            <h1>Admin Access</h1>
                            <p>Administrative Portal for FEX Platform</p>
                        </div>
                        
                        <div class="security-notice">
                            <i class="fa fa-lock"></i>
                            <span>Authorized personnel only. All access is monitored and logged.</span>
                        </div>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="adminLoginForm">
                            <div class="form-group">
                                <input type="email" name="email" class="form-control" placeholder="Admin Email Address" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <input type="password" name="password" class="form-control" placeholder="Admin Password" required>
                            </div>
                            <button type="submit" class="admin-btn">Access Admin Panel</button>
                        </form>
                        <div class="admin-login-footer">
                            <p>Need access? <a href="#">Contact System Administrator</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--admin login area end-->

    <!--footer area start-->
    <div class="footera-area wow fadeInDown" style="padding: 30px 0;">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p style="margin: 0; color: #cbe5ff;">Copyright &copy;<script>document.write(new Date().getFullYear());</script> FEX. All rights reserved</p>
                </div>
            </div>
        </div>
    </div>
    <!--footer area end-->

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
        // Form validation
        document.getElementById('adminLoginForm').addEventListener('submit', function(e) {
            const email = document.querySelector('input[name="email"]').value;
            const password = document.querySelector('input[name="password"]').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please fill in all fields');
                return false;
            }
            
            if (!isValidEmail(email)) {
                e.preventDefault();
                alert('Please enter a valid email address');
                return false;
            }
        });
        
        function isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
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
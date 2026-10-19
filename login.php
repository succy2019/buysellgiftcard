<?php
require_once 'includes/user_auth.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: user-dashboard.php');
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Your session expired. Please refresh the page and try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please fill in all fields';
    } else {
        $auth = new UserAuth();
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            header('Location: user-dashboard.php');
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
    <title>Login - DFQ Exchange</title>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#101828">

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
        .login-area {
            min-height: 100vh;
            background: #062489;
            display: flex;
            align-items: center;
            padding: 80px 0 50px;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 40px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
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
            border-color: #fe998b;
            box-shadow: 0 0 0 0.2rem rgba(254, 153, 139, 0.25);
            color: #fff;
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .login-header h1 {
            color: #fff;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .login-header p {
            color: #cbe5ff;
            font-size: 1.1rem;
        }
        .login-footer {
            text-align: center;
            margin-top: 30px;
        }
        .login-footer a {
            color: #fe998b;
            text-decoration: none;
        }
        .login-footer a:hover {
            color: #fff;
        }
        .forgot-password {
            text-align: right;
            margin-top: 10px;
        }
        .forgot-password a {
            color: #cbe5ff;
            font-size: 14px;
        }
        .gradient-btn.login-btn {
            width: 100%;
            padding: 15px;
            font-size: 16px;
            margin-top: 20px;
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
    <link rel="stylesheet" href="static/assets/css/dfq-premium.css">
    <!-- PWA / install prompt -->
    <link rel="manifest" href="manifest.json">

    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="DFQ Exchange">
    <link rel="apple-touch-icon" href="static/assets/img/apple-touch-icon.png">
    <link rel="stylesheet" href="static/assets/css/pwa-install.css">
    <script src="static/assets/js/pwa-install.js" defer></script>
</head>
<body class="dfq-auth">
    <!--header area start-->
    <div class="header-area wow fadeInDown header-absolate" id="nav">
        <div class="container">
            <div class="row">
                <div class="col-4 d-block d-lg-none">
                    <div class="mobile-menu"></div>
                </div>
                <div class="col-4 col-lg-2">
                    <div class="logo-area">
                        <a href="index.html" class="dfq-brand" aria-label="DFQ Exchange home"><img src="static/assets/img/dfq-mark.svg" alt="" width="36" height="36"><span>DFQ <b>Exchange</b></span></a>
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
                    <a href="register.php" class="logibtn gradient-btn">Register</a>
                </div>
            </div>
        </div>
    </div>
    <!--header area end-->

    <!--login area start-->
    <div class="login-area wow fadeInUp">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8 col-lg-6">
                    <div class="login-card">
                        <div class="login-header">
                            <h1>Welcome Back</h1>
                            <p>Sign in to your DFQ Exchange trading account</p>
                        </div>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success" role="status"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>
                        
                        <form method="POST" id="loginForm">
                            <?php echo csrfField(); ?>
                            <div class="form-group">
                                <label for="login-email">Email address</label>
                                <input type="email" id="login-email" name="email" class="form-control" placeholder="you@example.com" autocomplete="email"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="login-password">Password</label>
                                <input type="password" id="login-password" name="password" class="form-control" autocomplete="current-password" required>
                            </div>
                            <div class="forgot-password">
                                <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                            </div>
                            <button type="submit" class="gradient-btn login-btn">Sign In</button>
                        </form>
                        <div class="login-footer">
                            <p>Don't have an account? <a href="register.php">Sign up here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--login area end-->

    <!--footer area start-->
    <div class="footera-area wow fadeInDown" style="padding: 30px 0;">
        <div class="container">
            <div class="row">
                <div class="col-12 text-center">
                    <p style="margin: 0; color: #cbe5ff;">Copyright &copy;<script>document.write(new Date().getFullYear());</script> DFQ Exchange. All rights reserved</p>
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
    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            const button = this.querySelector('button[type="submit"]');
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            button.textContent = 'Signing in…';
        });
    </script>
</body>
</html>
<?php
session_start();
require_once 'includes/user_auth.php';

$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: user-dashboard.php');
    exit();
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = $_POST['first_name'] ?? '';
    $lastName = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $country = $_POST['country'] ?? '';
    $terms = isset($_POST['terms']);
    
    // Validation
    $errors = [];
    
    if (empty($firstName)) $errors[] = 'First name is required';
    if (empty($lastName)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($phone)) $errors[] = 'Phone is required';
    if (empty($password)) $errors[] = 'Password is required';
    if (empty($confirmPassword)) $errors[] = 'Password confirmation is required';
    if (empty($country)) $errors[] = 'Country is required';
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    if (!empty($password) && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (!empty($password) && !empty($confirmPassword) && $password !== $confirmPassword) {
        $errors[] = 'Passwords do not match';
    }
    
    if (!$terms) {
        $errors[] = 'Please accept the Terms of Service and Privacy Policy';
    }
    
    if (!empty($errors)) {
        $error = implode(', ', $errors);
    } else {
        $auth = new UserAuth();
        $userData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'confirm_password' => $confirmPassword,
            'country' => $country
        ];
        
        $result = $auth->register($userData);
        
        if ($result['success']) {
            $success = 'Account created successfully! Please check your email to verify your account.';
            // Optionally auto-login the user
            // header('Location: user-dashboard.php');
            // exit();
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>Register - FEX</title>
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
        .register-area {
            min-height: 100vh;
            background: #062489;
            display: flex;
            align-items: center;
            padding: 80px 0 50px;
        }
        .register-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 40px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .form-group {
            margin-bottom: 20px;
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
        .form-control select {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        .form-control option {
            background: #062489;
            color: #fff;
        }
        .form-control option:disabled {
            color: rgba(255, 255, 255, 0.7);
        }
        .register-header {
            text-align: center;
            margin-bottom: 40px;
        }
        .register-header h1 {
            color: #fff;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        .register-header p {
            color: #cbe5ff;
            font-size: 1.1rem;
        }
        .register-footer {
            text-align: center;
            margin-top: 30px;
        }
        .register-footer a {
            color: #fe998b;
            text-decoration: none;
        }
        .register-footer a:hover {
            color: #fff;
        }
        .gradient-btn.register-btn {
            width: 100%;
            padding: 15px;
            font-size: 16px;
            margin-top: 20px;
        }
        .terms-checkbox {
            margin-top: 20px;
        }
        .terms-checkbox input[type="checkbox"] {
            margin-right: 10px;
        }
        .terms-checkbox label {
            color: #cbe5ff;
            font-size: 14px;
        }
        .terms-checkbox a {
            color: #fe998b;
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
                    <a href="login.php" class="logibtn gradient-btn">Login</a>
                </div>
            </div>
        </div>
    </div>
    <!--header area end-->

    <!--register area start-->
    <div class="register-area wow fadeInUp">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-md-8 col-lg-7">
                    <div class="register-card">
                        <div class="register-header">
                            <h1>Join FEX</h1>
                            <p>Create your account and start trading cryptocurrencies and gift cards</p>
                        </div>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success">
                                <?php echo htmlspecialchars($success); ?>
                                <br><a href="login.php" style="color: #6bcf7f; text-decoration: underline;">Click here to login</a>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="registerForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <input type="text" name="first_name" class="form-control" placeholder="First Name" 
                                               value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <input type="text" name="last_name" class="form-control" placeholder="Last Name" 
                                               value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <input type="email" name="email" class="form-control" placeholder="Email Address" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <input type="tel" name="phone" class="form-control" placeholder="Phone Number" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <select name="country" class="form-control" required>
                                    <option value="" disabled <?php echo empty($_POST['country']) ? 'selected' : ''; ?>>Select Country</option>
                                    <option value="US" <?php echo (($_POST['country'] ?? '') === 'US') ? 'selected' : ''; ?>>United States</option>
                                    <option value="UK" <?php echo (($_POST['country'] ?? '') === 'UK') ? 'selected' : ''; ?>>United Kingdom</option>
                                    <option value="CA" <?php echo (($_POST['country'] ?? '') === 'CA') ? 'selected' : ''; ?>>Canada</option>
                                    <option value="AU" <?php echo (($_POST['country'] ?? '') === 'AU') ? 'selected' : ''; ?>>Australia</option>
                                    <option value="DE" <?php echo (($_POST['country'] ?? '') === 'DE') ? 'selected' : ''; ?>>Germany</option>
                                    <option value="FR" <?php echo (($_POST['country'] ?? '') === 'FR') ? 'selected' : ''; ?>>France</option>
                                    <option value="NG" <?php echo (($_POST['country'] ?? '') === 'NG') ? 'selected' : ''; ?>>Nigeria</option>
                                    <option value="GH" <?php echo (($_POST['country'] ?? '') === 'GH') ? 'selected' : ''; ?>>Ghana</option>
                                    <option value="KE" <?php echo (($_POST['country'] ?? '') === 'KE') ? 'selected' : ''; ?>>Kenya</option>
                                    <option value="ZA" <?php echo (($_POST['country'] ?? '') === 'ZA') ? 'selected' : ''; ?>>South Africa</option>
                                </select>
                            </div>
                            <div class="terms-checkbox">
                                <label>
                                    <input type="checkbox" name="terms" required>
                                    I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>
                                </label>
                            </div>
                            <button type="submit" class="gradient-btn register-btn">Create Account</button>
                        </form>
                        <div class="register-footer">
                            <p>Already have an account? <a href="login.php">Sign in here</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!--register area end-->

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
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const firstName = document.querySelector('input[name="first_name"]').value.trim();
            const lastName = document.querySelector('input[name="last_name"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();
            const phone = document.querySelector('input[name="phone"]').value.trim();
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            const country = document.querySelector('select[name="country"]').value;
            const terms = document.querySelector('input[name="terms"]').checked;
            
            // Check all required fields
            if (!firstName) {
                e.preventDefault();
                alert('First name is required');
                document.querySelector('input[name="first_name"]').focus();
                return false;
            }
            
            if (!lastName) {
                e.preventDefault();
                alert('Last name is required');
                document.querySelector('input[name="last_name"]').focus();
                return false;
            }
            
            if (!email) {
                e.preventDefault();
                alert('Email is required');
                document.querySelector('input[name="email"]').focus();
                return false;
            }
            
            if (!phone) {
                e.preventDefault();
                alert('Phone number is required');
                document.querySelector('input[name="phone"]').focus();
                return false;
            }
            
            if (!password) {
                e.preventDefault();
                alert('Password is required');
                document.querySelector('input[name="password"]').focus();
                return false;
            }
            
            if (!confirmPassword) {
                e.preventDefault();
                alert('Please confirm your password');
                document.querySelector('input[name="confirm_password"]').focus();
                return false;
            }
            
            if (!country) {
                e.preventDefault();
                alert('Country is required');
                document.querySelector('select[name="country"]').focus();
                return false;
            }
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                document.querySelector('input[name="confirm_password"]').focus();
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long');
                document.querySelector('input[name="password"]').focus();
                return false;
            }
            
            if (!terms) {
                e.preventDefault();
                alert('Please accept the Terms of Service and Privacy Policy');
                document.querySelector('input[name="terms"]').focus();
                return false;
            }
        });
        
        // Real-time password confirmation check
        document.querySelector('input[name="confirm_password"]').addEventListener('input', function() {
            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.style.borderColor = '#dc3545';
            } else {
                this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
            }
        });
        
        // Add visual feedback for empty required fields
        document.querySelectorAll('input[required], select[required]').forEach(function(field) {
            field.addEventListener('blur', function() {
                if (!this.value.trim()) {
                    this.style.borderColor = '#dc3545';
                } else {
                    this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                }
            });
            
            field.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                }
            });
        });
    </script>
</body>
</html>
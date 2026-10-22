<?php
/*
Template Name: Login Page
*/
if (file_exists(__DIR__ . '/backend.php')) {
    require_once __DIR__ . '/backend.php';
} elseif (file_exists(__DIR__ . '/inc/backend.php')) {
    require_once __DIR__ . '/inc/backend.php';
}

// Handle Logout
if (isset($_GET['action']) && in_array($_GET['action'], ['logout', 'signout', 'switch_account'], true)) {
    if (function_exists('wp_logout')) {
        wp_logout();
    }
    if (function_exists('wp_clear_auth_cookie')) {
        wp_clear_auth_cookie();
    }
    setcookie('pcs_remember_user', '', time() - 3600, '/');
    setcookie('pcs_remember_role', '', time() - 3600, '/');
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['logged_in_user']);
        unset($_SESSION['pcs_user_id']);
        session_destroy();
    }
    if (!empty($_GET['redirect_to'])) {
        if (function_exists('pcs_safe_redirect')) {
            pcs_safe_redirect($_GET['redirect_to']);
        } else {
            header('Location: ' . $_GET['redirect_to']);
            exit;
        }
    }
    $login_url = function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php';
    $target = $login_url . (strpos($login_url, '?') !== false ? '&' : '?') . (isset($_GET['switched']) ? 'switched=true' : 'loggedout=true');
    if (function_exists('pcs_safe_redirect')) {
        pcs_safe_redirect($target);
    } else {
        header('Location: ' . $target);
        exit;
    }
}

$homeUrl = function_exists('pcs_url') ? pcs_url('home-page.php') : 'home-page.php';
$loginLogoUrl = 'images/logo.png?v=2';
if (function_exists('get_template_directory_uri')) {
    $loginLogoUrl = get_template_directory_uri() . '/images/logo.png?v=2';
} elseif (function_exists('get_stylesheet_directory_uri')) {
    $loginLogoUrl = get_stylesheet_directory_uri() . '/images/logo.png?v=2';
}

$loginError = '';
$currentUser = function_exists('get_current_user_profile') ? get_current_user_profile() : null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['email'])) {
    $user = authenticate_user($_POST['email'], $_POST['password'] ?? '');
    if ($user) {
        $redirectTo = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : '';
        if (!empty($redirectTo)) {
            header('Location: ' . $redirectTo);
            exit;
        }
        if ($user['role'] === 'MAKER') {
            $dest = function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php';
            header('Location: ' . $dest);
            exit;
        } else if ($user['role'] === 'CHECKER') {
            $dest = function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php';
            header('Location: ' . $dest);
            exit;
        } else if ($user['role'] === 'ADMIN') {
            $dest = function_exists('pcs_url') ? pcs_url('admin-dashboard.php') : 'admin-dashboard.php';
            header('Location: ' . $dest);
            exit;
        }
    } else {
        $loginError = 'Invalid credentials or user not found. Please verify your email / username.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - AI Compliance</title>
    
    <!-- Nhúng Google Fonts chuẩn theo Figma -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@1,500;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            min-height: 100vh;
            display: flex;
            background-color: #FAF8F5;
            color: #272D5E;
            overflow-x: hidden;
        }

        .login-container {
            display: flex;
            width: 100vw;
            min-height: 100vh;
        }

        /* ================= 1. CỘT TRÁI: BANNER NGHỆ THUẬT ================= */
        .brand-section {
            flex: 1.15;
            position: relative;
            background: linear-gradient(135deg, #F3F1FD 0%, #FAF8F5 50%, #E8EBF8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 80px;
            overflow: hidden;
        }

        /* Đốm gradient mờ nền bên trái */
        .brand-section::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            top: -120px;
            left: -120px;
            background: rgba(214, 211, 248, 0.65);
            filter: blur(80px);
            border-radius: 50%;
        }

        .brand-section::after {
            content: '';
            position: absolute;
            width: 520px;
            height: 520px;
            bottom: -120px;
            right: -60px;
            background: rgba(225, 236, 248, 0.65);
            filter: blur(90px);
            border-radius: 50%;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            max-width: 540px;
        }

        .brand-heading-1 {
            font-size: 44px;
            font-weight: 700;
            line-height: 1.2;
            color: #272D5E;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .brand-heading-2 {
            font-family: 'Playfair Display', serif;
            font-size: 46px;
            font-style: italic;
            font-weight: 500;
            color: #585F9E;
            line-height: 1.25;
            margin-bottom: 24px;
        }

        .brand-description {
            font-size: 15px;
            line-height: 1.65;
            color: #686C89;
            max-width: 440px;
        }

        /* ================= 2. CỘT PHẢI: KHUNG ĐĂNG NHẬP ================= */
        .form-section {
            flex: 1;
            background: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 48px;
            position: relative;
        }

        .form-box {
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 2;
        }

        /* Welcome Badge */
        .welcome-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: #6D74B4;
            margin-bottom: 12px;
        }

        .badge-point {
            width: 6px;
            height: 6px;
            background-color: #6D74B4;
            border-radius: 50%;
        }

        /* Header Form + Logo */
        .title-with-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .title-with-logo h2 {
            font-size: 32px;
            font-weight: 700;
            color: #272D5E;
            letter-spacing: -0.5px;
        }

        .company-logo {
            height: 32px;
            max-width: 120px;
            object-fit: contain;
            display: inline-block;
        }

        .subtitle-text {
            font-size: 14px;
            color: #686C89;
            line-height: 1.5;
            margin-bottom: 32px;
        }

        /* Input Styles */
        .field-group {
            margin-bottom: 20px;
        }

        .field-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #272D5E;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .custom-input {
            width: 100%;
            height: 50px;
            padding: 12px 46px 12px 16px;
            font-size: 14px;
            color: #272D5E;
            background: #FFFFFF;
            border: 1px solid #E2E4ED;
            border-radius: 12px;
            outline: none;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        }

        .custom-input:focus {
            border-color: #6D74B4;
            box-shadow: 0 0 0 3px rgba(109, 116, 180, 0.15);
        }

        /* Icons trong Input */
        .icon-adornment, .btn-toggle-eye {
            position: absolute;
            right: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #858AC9;
            background: none;
            border: none;
            padding: 0;
        }

        .btn-toggle-eye {
            cursor: pointer;
        }

        .btn-toggle-eye:hover {
            color: #272D5E;
        }

        /* Row Remember Me & Forgot Password */
        .action-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-top: 6px;
            margin-bottom: 26px;
            font-size: 13px;
        }

        .remember-label {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            user-select: none;
            color: #686C89;
        }

        .remember-label input[type="checkbox"] {
            width: 17px;
            height: 17px;
            accent-color: #272D5E;
            cursor: pointer;
            border-radius: 4px;
        }

        .forgot-pass-link {
            color: #6D74B4;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .forgot-pass-link:hover {
            color: #272D5E;
            text-decoration: underline;
        }

        /* Submit Button */
        .btn-login-submit {
            width: 100%;
            height: 52px;
            background: #272D5E;
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(39, 45, 94, 0.25);
            transition: all 0.2s ease;
        }

        .btn-login-submit:hover {
            background: #1C2145;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(39, 45, 94, 0.35);
        }

        .btn-login-submit:active {
            transform: translateY(0);
        }

        /* Footer Support */
        .help-support {
            text-align: center;
            font-size: 13px;
            color: #686C89;
            margin-top: 28px;
        }

        .help-support a {
            color: #272D5E;
            font-weight: 600;
            text-decoration: none;
            margin-left: 4px;
        }

        .help-support a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 960px) {
            .login-container {
                flex-direction: column;
            }
            .brand-section {
                padding: 60px 30px;
                text-align: center;
            }
            .brand-description {
                margin: 0 auto;
            }
            .form-section {
                padding: 40px 24px;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">

        <!-- ================= 1. CỘT TRÁI ================= -->
        <section class="brand-section">
            <div class="brand-content">
                <h1 class="brand-heading-1">Review with clarity.</h1>
                <div class="brand-heading-2">Approve with confidence.</div>
                <p class="brand-description">
                    A simpler way to review documents, surface compliance risks, and move work forward with confidence.
                </p>
            </div>
        </section>

        <!-- ================= 2. CỘT PHẢI ================= -->
        <section class="form-section">
            <div class="form-box">

                <div class="welcome-badge">
                    <span class="badge-point"></span>
                    <span>WELCOME BACK</span>
                </div>

                <div class="title-with-logo">
                    <h2>Sign in to</h2>
                    <a href="<?= esc_url($homeUrl) ?>" title="Back to homepage" style="text-decoration: none; display: inline-flex; align-items: center;">
                        <img src="<?= esc_url($loginLogoUrl) ?>" alt="BrandCompliance Logo" class="company-logo" onerror="this.onerror=null; this.style.display='none'; document.getElementById('loginLogoFallback').style.display='inline-flex';">
                        <span id="loginLogoFallback" style="display: none; align-items: center; gap: 8px; font-weight: 800; font-size: 20px; color: #1F2674;">
                            <span style="width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg, #373E8C, #4F56A6); color: #FFF; display: inline-flex; align-items: center; justify-content: center;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            </span>
                            BrandCompliance
                        </span>
                    </a>
                </div>
                <p class="subtitle-text">Access your review workspace and continue where you left off.</p>

                <?php if (!empty($loginError)): ?>
                    <div style="background: #FEF2F2; border: 1px solid #FCA5A5; color: #991B1B; padding: 12px 16px; border-radius: 10px; font-size: 13.5px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span><?= esc_html($loginError) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['switched']) || (isset($_GET['action']) && $_GET['action'] === 'switched')): ?>
                    <div style="background: #EEF2FF; border: 1px solid #C7D2FE; color: #373E8C; padding: 12px 16px; border-radius: 10px; font-size: 13.5px; margin-top: 14px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line></svg>
                        <span>You have switched accounts. Please sign in with an authorized account.</span>
                    </div>
                <?php elseif (isset($_GET['loggedout'])): ?>
                    <div style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; padding: 12px 16px; border-radius: 10px; font-size: 13.5px; margin-top: 14px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <span>You have been signed out successfully.</span>
                    </div>
                <?php elseif (isset($_GET['reason']) && $_GET['reason'] === 'login_required'): ?>
                    <div style="background: #FFFBEB; border: 1px solid #FDE68A; color: #92400E; padding: 12px 16px; border-radius: 10px; font-size: 13.5px; margin-top: 14px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                        <span>Please sign in with your credentials to access this restricted workspace.</span>
                    </div>
                <?php endif; ?>

                <form id="mainLoginForm" method="POST" action="">
                    
                    <!-- Email / Username -->
                    <div class="field-group">
                        <label class="field-label" for="userEmail">Email address or Username</label>
                        <div class="input-wrapper">
                            <input 
                                type="text" 
                                id="userEmail" 
                                name="email" 
                                class="custom-input" 
                                placeholder="e.g. maker, checker, admin or email" 
                                value=""
                                required
                            >
                            <span class="icon-adornment">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </span>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="field-group">
                        <label class="field-label" for="userPassword">Password</label>
                        <div class="input-wrapper">
                            <input 
                                type="password" 
                                id="userPassword" 
                                name="password" 
                                class="custom-input" 
                                placeholder="Enter password (or leave as default)" 
                                value="123456"
                            >
                            <button type="button" class="btn-toggle-eye" id="btnToggleEye" title="Toggle password visibility">
                                <svg id="svgEyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Remember me & Forgot Password -->
                    <div class="action-row">
                        <label class="remember-label" for="chkRemember">
                            <input type="checkbox" id="chkRemember" name="remember" checked>
                            <span>Remember me</span>
                        </label>
                        <a href="#" class="forgot-pass-link">Forgot password?</a>
                    </div>

                    <!-- Nút Submit -->
                    <button type="submit" class="btn-login-submit">
                        <span>Log In</span>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M5 12h14"/>
                            <path d="m12 5 7 7-7 7"/>
                        </svg>
                    </button>

                    <p class="help-support">
                        Need help accessing your account? 
                        <a href="#">Contact support</a>
                    </p>

                </form>

            </div>
        </section>

    </div>

    <!-- Javascript tương tác -->
    <script>
        // Tính năng click con mắt hiện / ẩn mật khẩu
        const toggleBtn = document.getElementById('btnToggleEye');
        const passInput = document.getElementById('userPassword');
        const eyeSvg = document.getElementById('svgEyeIcon');

        toggleBtn.addEventListener('click', function () {
            const isPass = passInput.type === 'password';
            passInput.type = isPass ? 'text' : 'password';

            if (isPass) {
                // Icon gạch chéo
                eyeSvg.innerHTML = `
                    <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/>
                    <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/>
                    <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/>
                    <line x1="2" x2="22" y1="2" y2="22"/>
                `;
            } else {
                // Icon con mắt bình thường
                eyeSvg.innerHTML = `
                    <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                    <circle cx="12" cy="12" r="3"/>
                `;
            }
        });

        // Nút bấm nhanh chuyển đổi Role theo tài khoản WordPress
        function quickFill(role) {
            try { localStorage.removeItem('currentUser'); } catch(e) {}
            const emailInput = document.getElementById('userEmail');
            const passInput = document.getElementById('userPassword');
            const form = document.getElementById('mainLoginForm');
            
            if (role === 'maker') {
                emailInput.value = 'maker';
            } else if (role === 'checker') {
                emailInput.value = 'checker';
            } else if (role === 'admin') {
                emailInput.value = 'admin';
            }
            passInput.value = '123456';
            form.submit();
        }

        // Xóa sạch cache localStorage cũ khi submit form để ưu tiên 100% dữ liệu WordPress thật
        document.getElementById('mainLoginForm').addEventListener('submit', function() {
            try { localStorage.removeItem('currentUser'); } catch(e) {}
        });
    </script>
</body>
</html>
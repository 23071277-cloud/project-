<?php
/**
 * Template Name: System - Access Restricted (403)
 * Description: Dedicated 403 Forbidden / Access Denied Page for Role-Based Access Control (RBAC)
 * Language: English
 * Features: Automatic logout and account switching, Return to authorized workspace, Clear role clearance diagnosis
 */

if (!defined('PCS_BACKEND_LOADED')) {
    if (file_exists(__DIR__ . '/backend.php')) {
        require_once __DIR__ . '/backend.php';
    } elseif (file_exists(__DIR__ . '/inc/backend.php')) {
        require_once __DIR__ . '/inc/backend.php';
    } elseif (function_exists('get_template_directory') && file_exists(get_template_directory() . '/backend.php')) {
        require_once get_template_directory() . '/backend.php';
    } elseif (function_exists('get_template_directory') && file_exists(get_template_directory() . '/inc/backend.php')) {
        require_once get_template_directory() . '/inc/backend.php';
    }
}

// 1. Safe Polyfills
if (!function_exists('esc_url')) {
    function esc_url($url) { return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags((string) $str)); }
}
if (!function_exists('home_url')) {
    function home_url($path = '/') { return $path; }
}

// 2. Handle Action: Log In with Another Account (Auto-logout current session first)
if (isset($_GET['action']) && in_array($_GET['action'], ['logout', 'switch_account', 'relogin'], true)) {
    if (function_exists('wp_logout')) {
        wp_logout();
    }
    if (function_exists('wp_clear_auth_cookie')) {
        wp_clear_auth_cookie();
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['logged_in_user']);
        unset($_SESSION['pcs_user_id']);
        session_destroy();
    }
    $login_url = function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php';
    $target = $login_url;
    if (function_exists('pcs_safe_redirect')) {
        pcs_safe_redirect($target);
    } else {
        header('Location: ' . $target);
        exit;
    }
}

// 3. Retrieve Session Diagnostics
$currentUser = function_exists('get_current_user_profile') ? get_current_user_profile() : null;
$rawCurrentRole = $currentUser ? strtoupper($currentUser['role'] ?? '') : '';

// Normalize current role label
$currentRole = 'GUEST';
$roleBadgeBg = '#F1F5F9';
$roleBadgeColor = '#475569';

if ($rawCurrentRole === 'ADMIN' || $rawCurrentRole === 'ADMINISTRATOR') {
    $currentRole = 'ADMIN';
    $roleBadgeBg = '#E6F4EA';
    $roleBadgeColor = '#137333';
} elseif ($rawCurrentRole === 'CHECKER' || $rawCurrentRole === 'EDITOR') {
    $currentRole = 'CHECKER';
    $roleBadgeBg = '#EEF2FF';
    $roleBadgeColor = '#3B428A';
} elseif ($rawCurrentRole === 'MAKER' || $rawCurrentRole === 'AUTHOR' || $rawCurrentRole === 'CONTRIBUTOR') {
    $currentRole = 'MAKER';
    $roleBadgeBg = '#F3E8FF';
    $roleBadgeColor = '#6C5CE7';
}

$requiredParam = isset($_GET['required']) ? sanitize_text_field($_GET['required']) : '';
$requiredRoles = array_filter(array_map('trim', explode(',', $requiredParam)));
if (empty($requiredRoles)) {
    $requiredRoles = ['AUTHORIZED PERSONNEL'];
}

// Determine return workspace URL
$workspaceUrl = function_exists('pcs_url') ? pcs_url('home-page.php') : 'home-page.php';
$workspaceLabel = 'Return to Homepage';

if ($currentRole === 'MAKER') {
    $workspaceUrl = function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php';
    $workspaceLabel = 'Return to Maker Dashboard';
} elseif ($currentRole === 'CHECKER') {
    $workspaceUrl = function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php';
    $workspaceLabel = 'Return to Checker Dashboard';
} elseif ($currentRole === 'ADMIN') {
    $workspaceUrl = function_exists('pcs_url') ? pcs_url('admin-dashboard.php') : 'admin-dashboard.php';
    $workspaceLabel = 'Return to Admin Dashboard';
} else {
    $workspaceUrl = function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php';
    $workspaceLabel = 'Go to Sign In';
}

$switchAccountUrl = (function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php') . '?action=logout&switched=true';
$homeUrl = function_exists('pcs_url') ? pcs_url('home-page.php') : 'home-page.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 Access Restricted - AI Compliance System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@1,500;1,600&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(180deg, #FAF8FF 0%, #F5F3FF 100%);
            color: #1E293B;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            overflow-x: hidden;
        }

        /* Top Minimal Header */
        .top-bar {
            height: 72px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            padding: 0 48px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .brand-link {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #373E8C 0%, #4F56A6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 10px rgba(55, 62, 140, 0.25);
        }

        .brand-title {
            font-size: 17px;
            font-weight: 700;
            color: #1E1B4B;
            letter-spacing: -0.01em;
        }

        .brand-subtitle {
            font-size: 11px;
            color: #64748B;
            font-weight: 500;
        }

        .header-session {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            padding: 6px 14px;
            border-radius: 999px;
            font-size: 13px;
            color: #334155;
            font-weight: 600;
        }

        .user-avatar-dot {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: <?= esc_attr($roleBadgeBg) ?>;
            color: <?= esc_attr($roleBadgeColor) ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10.5px;
            font-weight: 700;
        }

        /* Main Center Card */
        .page-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 24px;
        }

        .restricted-card {
            width: 100%;
            max-width: 680px;
            background: #FFFFFF;
            border-radius: 24px;
            border: 1px solid rgba(226, 232, 240, 0.9);
            box-shadow: 0 20px 45px -15px rgba(39, 45, 94, 0.12), 0 0 1px rgba(0, 0, 0, 0.05);
            padding: 44px 48px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .restricted-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, #E11D48 0%, #D97706 50%, #4F56A6 100%);
        }

        /* Shield Icon */
        .icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 20px;
            background: #FFF1F2;
            border: 1px solid #FECDD3;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 24px;
            box-shadow: 0 10px 25px -5px rgba(225, 29, 72, 0.15);
        }

        .icon-wrapper svg {
            width: 42px;
            height: 42px;
            color: #E11D48;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 999px;
            background: #FFF1F2;
            border: 1px solid #FECDD3;
            color: #BE123C;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 14px;
        }

        .status-badge::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #E11D48;
        }

        .heading {
            font-size: 30px;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.25;
            letter-spacing: -0.02em;
            margin-bottom: 10px;
        }

        .subheading {
            font-size: 15px;
            color: #64748B;
            line-height: 1.6;
            max-width: 540px;
            margin-bottom: 32px;
        }

        /* Comparison Diagnostic Grid */
        .diagnostic-grid {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 28px;
            text-align: left;
        }

        .diagnostic-card {
            background: #FAF8FF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 18px 20px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .diagnostic-label {
            font-size: 11px;
            font-weight: 700;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .diagnostic-user-name {
            font-size: 14.5px;
            font-weight: 700;
            color: #1E293B;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .diagnostic-user-meta {
            font-size: 12.5px;
            color: #64748B;
            margin-bottom: 6px;
        }

        .role-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            width: fit-content;
        }

        .role-pill-current {
            background: <?= esc_attr($roleBadgeBg) ?>;
            color: <?= esc_attr($roleBadgeColor) ?>;
            border: 1px solid rgba(0,0,0,0.06);
        }

        .role-pill-required {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
        }

        /* Regulatory Governance Box */
        .governance-notice {
            width: 100%;
            background: #F8FAFC;
            border: 1px dashed #CBD5E1;
            border-radius: 12px;
            padding: 16px 20px;
            margin-bottom: 32px;
            text-align: left;
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .governance-notice svg {
            width: 20px;
            height: 20px;
            color: #475569;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .governance-title {
            font-size: 12.5px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 3px;
        }

        .governance-desc {
            font-size: 12px;
            color: #64748B;
            line-height: 1.5;
        }

        /* Action Buttons */
        .action-row {
            width: 100%;
            display: flex;
            gap: 14px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 26px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #373E8C 0%, #4F56A6 100%);
            color: #FFFFFF;
            box-shadow: 0 4px 14px rgba(55, 62, 140, 0.3);
        }

        .btn-primary:hover {
            opacity: 0.94;
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(55, 62, 140, 0.4);
            color: #FFFFFF;
        }

        .btn-secondary {
            background: #FFFFFF;
            color: #334155;
            border: 1px solid #CBD5E1;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        .btn-secondary:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            color: #0F172A;
            transform: translateY(-1px);
        }

        /* Footer */
        .bottom-bar {
            padding: 20px 48px;
            border-top: 1px solid rgba(226, 232, 240, 0.6);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12.5px;
            color: #94A3B8;
        }

        .footer-link {
            color: #4F56A6;
            text-decoration: none;
            font-weight: 600;
        }

        .footer-link:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            .restricted-card {
                padding: 32px 24px;
            }
            .diagnostic-grid {
                grid-template-columns: 1fr;
            }
            .action-row {
                flex-direction: column;
                width: 100%;
            }
            .btn {
                width: 100%;
            }
            .top-bar {
                padding: 0 20px;
            }
            .bottom-bar {
                padding: 16px 20px;
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }
        }
    </style>
    <?php if (function_exists("wp_head")) wp_head(); ?>
</head>
<body>

    <!-- Minimal Sticky Top Bar -->
    <header class="top-bar">
        <a href="<?= esc_url($homeUrl) ?>" class="brand-link" title="Brand Compliance Homepage">
            <div class="brand-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
            </div>
            <div>
                <div class="brand-title">BrandCompliance</div>
                <div class="brand-subtitle">Automated Institutional Governance</div>
            </div>
        </a>

        <div class="header-session">
            <?php if ($currentUser): ?>
                <div class="user-pill">
                    <span class="user-avatar-dot"><?= esc_html($currentUser['initials'] ?? 'US') ?></span>
                    <span><?= esc_html($currentUser['name'] ?? 'User') ?></span>
                    <span class="role-pill role-pill-current" style="font-size: 11px; padding: 2px 8px;">
                        <?= esc_html($currentRole) ?>
                    </span>
                </div>
            <?php else: ?>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php') ?>" class="btn btn-secondary" style="padding: 7px 16px; font-size: 12.5px;">
                    Sign In
                </a>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Restricted Notice -->
    <main class="page-container">
        <div class="restricted-card">
            
            <!-- Shield Warning Icon -->
            <div class="icon-wrapper">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>

            <div class="status-badge">403 · Access Restricted</div>
            
            <h1 class="heading">Insufficient Permissions</h1>
            <p class="subheading">
                You do not have the designated security clearance to access this compliance workspace. 
                Under strict Maker-Checker segregation guidelines, cross-role actions are prohibited.
            </p>

            <!-- Diagnostic Grid: Active vs Required -->
            <div class="diagnostic-grid">
                <div class="diagnostic-card">
                    <span class="diagnostic-label">Active Account</span>
                    <div class="diagnostic-user-name">
                        <?= esc_html($currentUser['name'] ?? 'Not Logged In') ?>
                    </div>
                    <div class="diagnostic-user-meta">
                        <?= esc_html($currentUser['email'] ?? 'No active session') ?>
                    </div>
                    <div class="role-pill role-pill-current">
                        <?= esc_html($currentRole) ?>
                    </div>
                </div>

                <div class="diagnostic-card">
                    <span class="diagnostic-label">Required Role Clearance</span>
                    <div class="diagnostic-user-name" style="color: #991B1B;">
                        <?php echo esc_html(implode(' / ', $requiredRoles)); ?> Only
                    </div>
                    <div class="diagnostic-user-meta">
                        Clearance Level: Tier 1 Policy
                    </div>
                    <div class="role-pill role-pill-required">
                        <?= esc_html(implode(' & ', $requiredRoles)) ?>
                    </div>
                </div>
            </div>

            <!-- Regulatory Governance Statement -->
            <div class="governance-notice">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <div>
                    <div class="governance-title">Regulatory Segregation of Duties (MAS TRM Guidelines §4.2)</div>
                    <div class="governance-desc">
                        To maintain compliance integrity, Makers, Checkers, and Administrators operate in dedicated workspaces.
                        Switching roles requires signing into an account with corresponding authority.
                    </div>
                </div>
            </div>

            <!-- Action Buttons: Switch Account (Auto-logout) & Return Workspace -->
            <div class="action-row">
                <!-- Log In with Another Account (Clears Session & Redirects to Login) -->
                <a href="<?= esc_url($switchAccountUrl) ?>" class="btn btn-primary" id="btnSwitchAccount" title="Sign out of current account and sign in with authorized role">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                        <polyline points="10 17 15 12 10 7"/>
                        <line x1="15" y1="12" x2="3" y2="12"/>
                    </svg>
                    <span>Log In with Another Account</span>
                </a>

                <!-- Return to Authorized Workspace for current role -->
                <a href="<?= esc_url($workspaceUrl) ?>" class="btn btn-secondary" title="<?= esc_attr($workspaceLabel) ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                        <polyline points="9 22 9 12 15 12 15 22"/>
                    </svg>
                    <span><?= esc_html($workspaceLabel) ?></span>
                </a>
            </div>

        </div>
    </main>

    <!-- Bottom Footer -->
    <footer class="bottom-bar">
        <div>
            &copy; <?= date('Y') ?> BrandCompliance AI Platform. Institutional Regulatory System.
        </div>
        <div>
            Need clearance assistance? <a href="mailto:compliance-support@institution.com" class="footer-link">Contact Compliance Administrator</a>
        </div>
    </footer>

    <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body>
</html>

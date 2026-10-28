<?php
/**
 * Template Name: Release Detail - Final Audit
 * Description: Dedicated Release Detail & Final Audit Screen for Maker and Admin Roles - 100% WordPress CPT Integration
 * RBAC: Restricted to Maker and Admin Roles
 */

if (!defined('ABSPATH') && session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// 1. Safe Backend Loader
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

// 2. Safe Fallback Polyfills
if (!function_exists('esc_url')) {
    function esc_url($url) { return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_html')) {
    function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('esc_js')) {
    function esc_js($text) { return addslashes((string) $text); }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags((string) $str)); }
}
if (!function_exists('home_url')) {
    function home_url($path = '/') { return $path; }
}

// 3. Current User & RBAC Authentication (Maker and Admin Only - Checker Strictly Blocked)
$currentUser = function_exists('pcs_enforce_rbac') ? pcs_enforce_rbac(['MAKER', 'ADMIN']) : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'MAKER');
$isAllowed = ($userRole === 'ADMIN' || $userRole === 'ADMINISTRATOR' || $userRole === 'MAKER');

// 4. Handle Sign Out
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['logged_in_user']);
        unset($_SESSION['pcs_user_id']);
        session_destroy();
    }
    if (function_exists('wp_logout')) {
        wp_logout();
    }
    $target = function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php';
    header('Location: ' . $target);
    exit;
}

// 5. Navigation URLs
$adminDashboardUrl  = function_exists('pcs_url') ? pcs_url('admin-dashboard.php') : 'admin-dashboard.php';
$makerDashboardUrl  = function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php';
$detailAdminBaseUrl = function_exists('pcs_url') ? pcs_url('detail-asset-admin.php') : 'detail-asset-admin.php';
$auditLogBaseUrl    = function_exists('pcs_url') ? pcs_url('audit-log.php') : 'audit-log.php';
$compareBaseUrl     = function_exists('pcs_url') ? pcs_url('compare-version.php') : 'compare-version.php';

$workspaceUrl = ($userRole === 'ADMIN' || $userRole === 'ADMINISTRATOR') ? $adminDashboardUrl : $makerDashboardUrl;
$workspaceBtnText = ($userRole === 'ADMIN' || $userRole === 'ADMINISTRATOR') ? 'Admin Workspace' : 'Maker Workspace';

// 6. Dynamic Data Query (100% WordPress CPT Database)
$target_ver = (int) ($_GET['version_id'] ?? 0);
$target_ast = (int) ($_GET['asset_id'] ?? 0);
$target_cmp = (int) ($_GET['campaign_id'] ?? 0);

$data = function_exists('get_release_detail_screen_data')
    ? get_release_detail_screen_data($target_ver, $target_ast, $target_cmp, $userRole)
    : [
        'is_empty' => true,
        'version_id' => 0,
        'version_num' => 0,
        'version_label' => 'No Version',
        'status_label' => 'No Asset Selected'
    ];

$isEmpty = !empty($data['is_empty']);

$campaignDetailUrl = $data['campaign_id'] 
    ? (($userRole === 'ADMIN' || $userRole === 'ADMINISTRATOR') ? add_query_arg(['campaign_id' => $data['campaign_id']], $detailAdminBaseUrl) : $makerDashboardUrl)
    : $workspaceUrl;

$auditScreenUrl = $data['campaign_id'] ? add_query_arg([
    'campaign_id' => $data['campaign_id'],
    'asset_id'    => $data['asset_id'],
    'version_id'  => $data['version_id'],
    'from'        => 'release_detail'
], $auditLogBaseUrl) : $auditLogBaseUrl;

$compareScreenUrl = $data['campaign_id'] ? add_query_arg([
    'campaign_id' => $data['campaign_id'],
    'asset_id'    => $data['asset_id'],
    'v_cur'       => $data['version_id'],
    'from'        => 'release_detail'
], $compareBaseUrl) : $compareBaseUrl;
?>
<!DOCTYPE html>
<html <?php if (function_exists('language_attributes')) language_attributes(); ?>>
<head>
    <meta charset="<?php echo function_exists('bloginfo') ? bloginfo('charset') : 'UTF-8'; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Released Detail: <?php echo esc_html($data['asset_name']); ?> (<?php echo esc_html($data['version_label']); ?>) | Brand Compliance AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #FAF8FF;
            color: #161B2E;
            -webkit-font-smoothing: antialiased;
            min-height: 100vh;
        }

        .ReleaseDetailFinalAuditMaker {
            width: 100%;
            max-width: 1300px;
            margin: 0 auto;
            background: linear-gradient(0deg, #FAF8FF 0%, #FAF8FF 100%), white;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
            display: flex;
            min-height: 100vh;
        }

        /* Top Header Zone */
        .header-zone {
            align-self: stretch;
            padding: 24px 32px;
            background: rgba(243, 242, 255, 0.60);
            border-bottom: 1px solid rgba(226, 232, 240, 0.70);
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
        }

        .header-zone-inner {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .breadcrumbs-title-col {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .nav-breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.24px;
        }

        .breadcrumb-link {
            color: #464651;
            text-decoration: none;
            transition: color 0.15s ease;
        }
        .breadcrumb-link:hover { color: #1F2674; text-decoration: underline; }

        .breadcrumb-sep { color: #C7C5D3; }
        .breadcrumb-active { color: #1F2674; }

        .title-paragraph {
            display: flex;
            align-items: baseline;
            gap: 10px;
        }

        .main-heading {
            color: #1F2674;
            font-size: 28px;
            font-weight: 700;
            line-height: 36px;
        }

        .sub-heading {
            color: #464651;
            font-size: 14px;
            font-weight: 500;
            line-height: 20px;
        }

        .status-badges-strip {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .badge-released {
            padding: 4px 12px;
            background: #F5F3FF;
            color: #7C3AED;
            border: 1px solid #DDD6FE;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 14px;
            letter-spacing: 0.5px;
        }

        .pill-locked {
            padding: 4px 12px;
            background: #E4E7FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #373E8C;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 14px;
            letter-spacing: 0.5px;
        }

        .pill-version {
            padding: 4px 12px;
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            color: #1F2674;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 14px;
            letter-spacing: 0.5px;
        }

        /* Main Workspace Split Grid */
        .workspace-content {
            align-self: stretch;
            padding: 28px 32px 48px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .split-grid {
            display: grid;
            grid-template-columns: 63% calc(37% - 24px);
            gap: 24px;
            align-items: start;
            width: 100%;
        }

        @media (max-width: 1024px) {
            .split-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Left Column: Final File Preview */
        .preview-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0px 2px 4px -2px rgba(0, 0, 0, 0.08), 0px 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.8);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .file-header {
            padding: 18px 24px;
            background: rgba(243, 242, 255, 0.40);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .file-header-title {
            color: #1F2674;
            font-size: 16px;
            font-weight: 700;
            line-height: 22px;
        }

        .file-header-sub {
            color: #464651;
            font-size: 12px;
            font-weight: 500;
            line-height: 16px;
            margin-top: 2px;
        }

        .badge-readonly {
            padding: 3px 10px;
            background: #EBEDFF;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            color: #464651;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.4px;
        }

        /* Interactive Artwork Container */
        .preview-container {
            padding: 20px;
            background: #F3F2FF;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .toolbar-row {
            align-self: stretch;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .toolbar-info {
            font-size: 11.5px;
            font-weight: 600;
            color: #4E56A6;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .toolbar-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .tool-btn {
            padding: 5px 10px;
            background: white;
            border: 1px solid #D5D9F3;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #464651;
            cursor: pointer;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .tool-btn:hover {
            background: #F4F3FD;
            color: #1F2674;
            border-color: #4E56A6;
        }

        .image-viewport {
            width: 100%;
            min-height: 380px;
            max-height: 520px;
            background: white;
            box-shadow: 0px 1px 3px rgba(0, 0, 0, 0.08);
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .artwork-img {
            max-width: 100%;
            max-height: 500px;
            object-fit: contain;
            display: block;
            transition: transform 0.2s cubic-bezier(0.2, 0, 0.2, 1);
            user-select: none;
        }

        .placeholder-canvas {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 40px;
            color: #5F657B;
            text-align: center;
        }

        /* Slim Metadata Strip */
        .meta-strip {
            padding: 16px 20px;
            background: rgba(243, 242, 255, 0.35);
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }

        @media (max-width: 640px) {
            .meta-strip {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .meta-col {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .meta-label {
            color: #464651;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 14px;
            letter-spacing: 0.5px;
        }

        .meta-val {
            color: #1F2674;
            font-size: 11.5px;
            font-weight: 600;
            line-height: 18px;
            word-break: break-all;
        }

        .meta-val-state {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #4F57A7;
            font-size: 12px;
            font-weight: 600;
        }

        /* Right Column: Final Audit & Governance Record */
        .audit-column {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .audit-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0px 2px 4px -2px rgba(0, 0, 0, 0.08), 0px 4px 6px -1px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.8);
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .card-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #F1F5F9;
            padding-bottom: 12px;
        }

        .card-heading {
            color: #1F2674;
            font-size: 15px;
            font-weight: 700;
        }

        .card-subtext {
            color: #64748B;
            font-size: 12px;
            font-weight: 500;
        }

        /* Production Release Banner */
        .locked-release-banner {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            border-radius: 8px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .locked-release-top {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #065F46;
            font-size: 13.5px;
            font-weight: 700;
        }

        .locked-release-meta {
            color: #047857;
            font-size: 12px;
            line-height: 18px;
        }

        .governance-note {
            font-size: 11.5px;
            color: #065F46;
            line-height: 17px;
            margin-top: 4px;
            border-top: 1px dashed rgba(16, 185, 129, 0.3);
            padding-top: 6px;
        }

        .release-remarks-box {
            background: #F8FAFC;
            border-left: 3px solid #10B981;
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 12px;
            color: #334155;
            font-style: italic;
            line-height: 18px;
        }

        /* Checker verification box */
        .checker-signoff-box {
            background: #F8F9FA;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .user-meta-line {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .user-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .user-avatar-circle {
            width: 28px;
            height: 28px;
            border-radius: 9999px;
            background: linear-gradient(135deg, #4E56A6, #373E8C);
            color: white;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-name-text {
            font-size: 13px;
            font-weight: 600;
            color: #1E293B;
        }

        .badge-verified-approved {
            padding: 3px 8px;
            background: #DCFCE7;
            color: #166534;
            font-size: 10.5px;
            font-weight: 700;
            border-radius: 4px;
            letter-spacing: 0.3px;
        }

        .checker-remarks-quote {
            font-size: 12px;
            color: #475569;
            line-height: 18px;
            font-style: italic;
        }

        /* Details list items */
        .lineage-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            font-size: 12px;
        }

        .lineage-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .lineage-lbl {
            color: #64748B;
            font-size: 11px;
            font-weight: 500;
        }

        .lineage-val {
            color: #1E293B;
            font-weight: 600;
        }

        /* Action Buttons */
        .btn-download-master {
            width: 100%;
            padding: 12px 20px;
            background: #4E56A6;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            border: none;
            color: white;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: background 0.15s ease, transform 0.1s ease;
        }
        .btn-download-master:hover {
            background: #3D4389;
            transform: translateY(-1px);
        }

        .btn-action-outline {
            width: 100%;
            padding: 10px 18px;
            background: white;
            border: 1px solid #D5D9F3;
            border-radius: 8px;
            color: #4E56A6;
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-action-outline:hover {
            background: #F4F3FD;
            border-color: #4E56A6;
        }

        .link-return-workspace {
            align-self: center;
            margin-top: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #5F657B;
            text-decoration: none;
            transition: color 0.15s ease;
        }
        .link-return-workspace:hover {
            color: #1F2674;
            text-decoration: underline;
        }

        /* Empty State */
        .empty-state-box {
            background: white;
            border-radius: 12px;
            border: 1px solid #E2E8F0;
            padding: 60px 24px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 14px;
            margin: 20px 0;
        }

        .empty-icon-circle {
            width: 56px;
            height: 56px;
            border-radius: 9999px;
            background: #EDE9FA;
            color: #4E56A6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .empty-title {
            color: #1F2674;
            font-size: 18px;
            font-weight: 700;
        }

        .empty-desc {
            color: #5F657B;
            font-size: 13px;
            max-width: 480px;
            line-height: 20px;
        }

        /* Access Denied */
        .access-denied-box {
            padding: 60px 24px;
            text-align: center;
            background: white;
            border-radius: 12px;
            border: 1px solid #FCA5A5;
            margin: 40px auto;
            max-width: 560px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 14px;
        }
    </style>
</head>
<body>

    <!-- 1. UNIFIED TOP NAVBAR -->
    <?php
    if (function_exists('pcs_render_top_navbar')) {
        pcs_render_top_navbar([
            'user'              => $currentUser,
            'all_campaigns_url' => $workspaceUrl,
            'btn_text'          => $workspaceBtnText,
            'active_tab'        => 'dashboard'
        ]);
    }
    ?>

<div class="ReleaseDetailFinalAuditMaker">

    <!-- 2. RBAC CHECK (Admin + Maker allowed) -->
    <?php if (!$isAllowed): ?>
        <main class="workspace-content">
            <div class="access-denied-box">
                <div style="display: flex; align-items: center; justify-content: center; margin-bottom: 8px;">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#E11D48" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h2 style="color: #991B1B; font-size: 20px; font-weight: 700;">Access Restricted</h2>
                <p style="color: #64748B; font-size: 13.5px; line-height: 20px;">
                    This final production release audit screen is restricted to <strong>Maker</strong> (Asset Author) and <strong>Admin</strong> (Compliance Administrator) roles.
                </p>
                <div style="display: flex; gap: 12px; margin-top: 8px;">
                    <a href="<?php echo esc_url(function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php'); ?>" class="btn-download-master" style="width: auto; padding: 8px 18px;">
                        <span>Return to Checker Dashboard</span>
                    </a>
                    <a href="?action=logout" class="btn-action-outline" style="width: auto; padding: 8px 18px;">
                        <span>Sign Out</span>
                    </a>
                </div>
            </div>
        </main>
    </div>
    </body>
    </html>
    <?php exit; endif; ?>

    <!-- 3. TOP WORKSPACE SHELL (Header Zone) -->
    <header class="header-zone">
        <div class="header-zone-inner">
            <div class="breadcrumbs-title-col">
                <nav class="nav-breadcrumbs" aria-label="Breadcrumb">
                    <a href="<?php echo esc_url($workspaceUrl); ?>" class="breadcrumb-link">Campaigns</a>
                    <span class="breadcrumb-sep">/</span>
                    <a href="<?php echo esc_url($campaignDetailUrl); ?>" class="breadcrumb-link">
                        <?php echo esc_html($data['campaign_name'] ?: 'Campaign'); ?>
                    </a>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-active"><?php echo esc_html($data['asset_name'] ?: 'Asset'); ?></span>
                </nav>

                <div class="title-paragraph">
                    <h1 class="main-heading">Released Detail</h1>
                    <span class="sub-heading">Final approved version</span>
                </div>
            </div>

            <div class="status-badges-strip">
                <!-- RELEASED Badge -->
                <div class="badge-released">
                    <svg width="12" height="12" viewBox="0 0 13 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5.375 9.125L9.78125 4.71875L8.90625 3.84375L5.375 7.375L3.59375 5.59375L2.71875 6.46875L5.375 9.125M6.25 12.5C5.38542 12.5 4.57292 12.3359 3.8125 12.0078C3.05208 11.6797 2.39062 11.2344 1.82812 10.6719C1.26562 10.1094 0.820312 9.44792 0.492188 8.6875C0.164062 7.92708 0 7.11458 0 6.25C0 5.38542 0.164062 4.57292 0.492188 3.8125C0.820312 3.05208 1.26562 2.39062 1.82812 1.82812C2.39062 1.26562 3.05208 0.820312 3.8125 0.492188C4.57292 0.164062 5.38542 0 6.25 0C7.11458 0 7.92708 0.164062 8.6875 0.492188C9.44792 0.820312 10.1094 1.26562 10.6719 1.82812C11.2344 2.39062 11.6797 3.05208 12.0078 3.8125C12.3359 4.57292 12.5 5.38542 12.5 6.25C12.5 7.11458 12.3359 7.92708 12.0078 8.6875C11.6797 9.44792 11.2344 10.1094 10.6719 10.6719C10.1094 11.2344 9.44792 11.6797 8.6875 12.0078C7.92708 12.3359 7.11458 12.5 6.25 12.5" fill="#148A78"/>
                    </svg>
                    <span><?php echo esc_html($data['status_label']); ?></span>
                </div>

                <!-- LOCKED Pill -->
                <div class="pill-locked">
                    <svg width="10" height="13" viewBox="0 0 10 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M1.25 13.125C0.90625 13.125 0.611979 13.0026 0.367188 12.7578C0.122396 12.513 0 12.2188 0 11.875V5.625C0 5.28125 0.122396 4.98698 0.367188 4.74219C0.611979 4.4974 0.90625 4.375 1.25 4.375H1.875V3.125C1.875 2.26042 2.17969 1.52344 2.78906 0.914062C3.39844 0.304688 4.13542 0 5 0C5.86458 0 6.60156 0.304688 7.21094 0.914062C7.82031 1.52344 8.125 2.26042 8.125 3.125V4.375H8.75C9.09375 4.375 9.38802 4.4974 9.63281 4.74219C9.8776 4.98698 10 5.28125 10 5.625V11.875C10 12.2188 9.8776 12.513 9.63281 12.7578C9.38802 13.0026 9.09375 13.125 8.75 13.125H1.25M1.25 11.875H8.75V5.625H1.25V11.875M5 10C5.34375 10 5.63802 9.8776 5.88281 9.63281C6.1276 9.38802 6.25 9.09375 6.25 8.75C6.25 8.40625 6.1276 8.11198 5.88281 7.86719C5.63802 7.6224 5.34375 7.5 5 7.5C4.65625 7.5 4.36198 7.6224 4.11719 7.86719C3.8724 8.11198 3.75 8.40625 3.75 8.75C3.75 9.09375 3.8724 9.38802 4.11719 9.63281C4.36198 9.8776 4.65625 10 5 10M3.125 4.375H6.875V3.125C6.875 2.60417 6.69271 2.16146 6.32812 1.79688C5.96354 1.43229 5.52083 1.25 5 1.25C4.47917 1.25 4.03646 1.43229 3.67188 1.79688C3.30729 2.16146 3.125 2.60417 3.125 3.125V4.375" fill="#373E8C"/>
                    </svg>
                    <span>LOCKED</span>
                </div>

                <!-- VERSION Pill -->
                <div class="pill-version">
                    <span>VERSION <?php echo esc_html($data['version_label']); ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- 4. MAIN WORKSPACE CONTENT -->
    <main class="workspace-content">

        <?php if ($isEmpty): ?>
            <!-- EMPTY STATE -->
            <div class="empty-state-box">
                <div class="empty-icon-circle">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                </div>
                <h2 class="empty-title">No Released Asset Found</h2>
                <p class="empty-desc">
                    No production released creative asset is currently selected or registered in the compliance repository. 
                    Creative assets will appear here once approved by Compliance Checkers and formally authorized by Administrators.
                </p>
                <div style="display: flex; gap: 12px; margin-top: 12px; flex-wrap: wrap; justify-content: center;">
                    <a href="<?php echo esc_url($workspaceUrl); ?>" class="btn-download-master" style="width: auto; padding: 10px 20px;">
                        <span>Return to <?php echo esc_html($workspaceBtnText); ?></span>
                    </a>
                    <?php if ($userRole === 'ADMIN' || $userRole === 'ADMINISTRATOR'): ?>
                        <a href="<?php echo esc_url(function_exists('pcs_url') ? pcs_url('approval-queue-admin.php') : 'approval-queue-admin.php'); ?>" class="btn-action-outline" style="width: auto; padding: 10px 20px;">
                            <span>Open Approval Queue &rarr;</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>

            <div class="split-grid">

                <!-- ================= LEFT COLUMN: FINAL FILE PREVIEW (63%) ================= -->
                <div class="preview-card">
                    <!-- File Header -->
                    <div class="file-header">
                        <div>
                            <h2 class="file-header-title">Final File</h2>
                            <p class="file-header-sub">
                                <?php echo esc_html($data['asset_name']); ?> &middot; <?php echo esc_html($data['version_label']); ?> Master Distribution Asset
                            </p>
                        </div>
                        <div class="badge-readonly">
                            <svg width="10" height="12" viewBox="0 0 10 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1.16667 12.25C0.845833 12.25 0.571181 12.1358 0.342708 11.9073C0.114236 11.6788 0 11.4042 0 11.0833V5.25C0 4.92917 0.114236 4.65451 0.342708 4.42604C0.571181 4.19757 0.845833 4.08333 1.16667 4.08333H1.75V2.91667C1.75 2.10972 2.03438 1.42188 2.60313 0.853125C3.17188 0.284375 3.85972 0 4.66667 0C5.47361 0 6.16146 0.284375 6.73021 0.853125C7.29896 1.42188 7.58333 2.10972 7.58333 2.91667V4.08333H8.16667C8.4875 4.08333 8.76215 4.19757 8.99063 4.42604C9.2191 4.65451 9.33333 4.92917 9.33333 5.25V11.0833C9.33333 11.4042 9.2191 11.6788 8.99063 11.9073C8.76215 12.1358 8.4875 12.25 8.16667 12.25H1.16667M1.16667 11.0833H8.16667V5.25H1.16667V11.0833M4.66667 9.33333C4.9875 9.33333 5.26215 9.2191 5.49062 8.99063C5.7191 8.76215 5.83333 8.4875 5.83333 8.16667C5.83333 7.84583 5.7191 7.57118 5.49062 7.34271C5.26215 7.11424 4.9875 7 4.66667 7C4.34583 7 4.07118 7.11424 3.84271 7.34271C3.61424 7.57118 3.5 7.84583 3.5 8.16667C3.5 8.4875 3.61424 8.76215 3.84271 8.99063C4.07118 9.2191 4.34583 9.33333 4.66667 9.33333M2.91667 4.08333H6.41667V2.91667C6.41667 2.43056 6.24653 2.01736 5.90625 1.67708C5.56597 1.33681 5.15278 1.16667 4.66667 1.16667C4.18056 1.16667 3.76736 1.33681 3.42708 1.67708C3.08681 2.01736 2.91667 2.43056 2.91667 2.91667V4.08333" fill="#464651"/>
                            </svg>
                            <span>Read-only</span>
                        </div>
                    </div>

                    <!-- Interactive Artwork Container -->
                    <div class="preview-container" id="canvasContainer">
                        <!-- Toolbar -->
                        <div class="toolbar-row">
                            <span class="toolbar-info">
                                <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd" />
                                </svg>
                                <span>Master Production File Preview</span>
                            </span>

                            <div class="toolbar-actions">
                                <button type="button" class="tool-btn" id="btnZoomOut" title="Zoom Out (-25%)">
                                    <span>&minus;</span>
                                </button>
                                <button type="button" class="tool-btn" id="btnZoomIn" title="Zoom In (+25%)">
                                    <span>&#43;</span>
                                </button>
                                <button type="button" class="tool-btn" id="btnFitScreen" title="Reset Zoom">
                                    <span>Fit</span>
                                </button>
                                <button type="button" class="tool-btn" id="btnFullscreen" title="Fullscreen View">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M8 3H5a2 2 0 0 0-2 2v3m18 0V5a2 2 0 0 0-2-2h-3m0 18h3a2 2 0 0 0 2-2v-3M3 16v3a2 2 0 0 0 2 2h3"/>
                                    </svg>
                                    <span>Fullscreen</span>
                                </button>
                            </div>
                        </div>

                        <!-- Image Viewport -->
                        <div class="image-viewport">
                            <?php if (!empty($data['file_url'])): ?>
                                <img src="<?php echo esc_url($data['file_url']); ?>"
                                     alt="<?php echo esc_attr($data['asset_name']); ?> - Final Approved Master"
                                     class="artwork-img"
                                     id="previewArtworkImg">
                            <?php else: ?>
                                <div class="placeholder-canvas">
                                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#A5B4FC" stroke-width="1.5">
                                        <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/>
                                        <circle cx="9" cy="9" r="2"/>
                                        <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                                    </svg>
                                    <div>
                                        <div style="font-weight: 700; color: #1E293B; font-size: 14px;">Master Artwork Available</div>
                                        <div style="font-size: 12px; color: #64748B; margin-top: 2px;">
                                            File recorded in WordPress Media Library.
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Slim Horizontal Metadata Strip -->
                    <div class="meta-strip">
                        <div class="meta-col">
                            <span class="meta-label">FILE NAME</span>
                            <span class="meta-val"><?php echo esc_html($data['file_name'] ?: 'final_asset.png'); ?></span>
                        </div>
                        <div class="meta-col">
                            <span class="meta-label">FILE TYPE</span>
                            <span class="meta-val"><?php echo esc_html($data['file_format'] ?: 'PNG Image (16:9)'); ?></span>
                        </div>
                        <div class="meta-col">
                            <span class="meta-label">FILE SIZE</span>
                            <span class="meta-val"><?php echo esc_html($data['file_size'] ?: '2.4 MB'); ?></span>
                        </div>
                        <div class="meta-col">
                            <span class="meta-label">VERSION STATE</span>
                            <div class="meta-val-state">
                                <svg width="10" height="13" viewBox="0 0 10 13" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1.16667 12.25C0.845833 12.25 0.571181 12.1358 0.342708 11.9073C0.114236 11.6788 0 11.4042 0 11.0833V5.25C0 4.92917 0.114236 4.65451 0.342708 4.42604C0.571181 4.19757 0.845833 4.08333 1.16667 4.08333H1.75V2.91667C1.75 2.10972 2.03438 1.42188 2.60313 0.853125C3.17188 0.284375 3.85972 0 4.66667 0C5.47361 0 6.16146 0.284375 6.73021 0.853125C7.29896 1.42188 7.58333 2.10972 7.58333 2.91667V4.08333H8.16667C8.4875 4.08333 8.76215 4.19757 8.99063 4.42604C9.2191 4.65451 9.33333 4.92917 9.33333 5.25V11.0833C9.33333 11.4042 9.2191 11.6788 8.99063 11.9073C8.76215 12.1358 8.4875 12.25 8.16667 12.25H1.16667M1.16667 11.0833H8.16667V5.25H1.16667V11.0833M4.66667 9.33333C4.9875 9.33333 5.26215 9.2191 5.49062 8.99063C5.7191 8.76215 5.83333 8.4875 5.83333 8.16667C5.83333 7.84583 5.7191 7.57118 5.49062 7.34271C5.26215 7.11424 4.9875 7 4.66667 7C4.34583 7 4.07118 7.11424 3.84271 7.34271C3.61424 7.57118 3.5 7.84583 3.5 8.16667C3.5 8.4875 3.61424 8.76215 3.84271 8.99063C4.07118 9.2191 4.34583 9.33333 4.66667 9.33333M2.91667 4.08333H6.41667V2.91667C6.41667 2.43056 6.24653 2.01736 5.90625 1.67708C5.56597 1.33681 5.15278 1.16667 4.66667 1.16667C4.18056 1.16667 3.76736 1.33681 3.42708 1.67708C3.08681 2.01736 2.91667 2.43056 2.91667 2.91667V4.08333" fill="#4F57A7"/>
                                </svg>
                                <span><?php echo esc_html($data['version_state_label']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= RIGHT COLUMN: FINAL AUDIT & GOVERNANCE RECORD (37%) ================= -->
                <div class="audit-column">

                    <!-- CARD 1: Production Release Record -->
                    <div class="audit-card">
                        <div class="card-header-row">
                            <div>
                                <h3 class="card-heading">Production Release Record</h3>
                                <p class="card-subtext">Institutional compliance confirmation</p>
                            </div>
                            <span class="badge-released">RELEASED</span>
                        </div>

                        <!-- Status Alert Banner -->
                        <div class="locked-release-banner">
                            <div class="locked-release-top">
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                <span>Released to Production &amp; Immutable</span>
                            </div>
                            <div class="locked-release-meta">
                                Authorized by <strong><?php echo esc_html($data['admin_released_by']); ?></strong> on <?php echo esc_html($data['admin_released_at']); ?>.
                            </div>
                            <div class="governance-note">
                                Institutional brand guidelines and regulatory mandates (MAS Notice 637) certified. This asset version is permanently locked and ready for commercial deployment.
                            </div>
                        </div>

                        <!-- Admin Directives -->
                        <div>
                            <span class="lineage-lbl" style="margin-bottom: 6px; display: block;">Admin Release Remarks</span>
                            <div class="release-remarks-box">
                                <?php if (!empty($data['admin_release_notes'])): ?>
                                    &ldquo;<?php echo esc_html($data['admin_release_notes']); ?>&rdquo;
                                <?php else: ?>
                                    &ldquo;Certified compliant with institutional standards. Approved for public production release.&rdquo;
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 2: Checker Compliance Sign-off -->
                    <div class="audit-card">
                        <div class="card-header-row">
                            <div>
                                <h3 class="card-heading">Checker Sign-off Verification</h3>
                                <p class="card-subtext">Compliance review &amp; verification audit</p>
                            </div>
                            <span class="badge-verified-approved">APPROVED (<?php echo (int) $data['overall_score']; ?>%)</span>
                        </div>

                        <div class="checker-signoff-box">
                            <div class="user-meta-line">
                                <div class="user-tag">
                                    <div class="user-avatar-circle">
                                        <?php echo esc_html($data['checker_initials']); ?>
                                    </div>
                                    <div>
                                        <div class="user-name-text"><?php echo esc_html($data['checker_name']); ?></div>
                                        <div style="font-size: 11px; color: #64748B;">Compliance Checker</div>
                                    </div>
                                </div>
                                <span style="font-size: 11.5px; color: #64748B;"><?php echo esc_html($data['checker_reviewed_at']); ?></span>
                            </div>

                            <div class="checker-remarks-quote">
                                <?php if (!empty($data['checker_notes'])): ?>
                                    &ldquo;<?php echo esc_html($data['checker_notes']); ?>&rdquo;
                                <?php else: ?>
                                    &ldquo;All creative visual criteria, mandatory disclosures, and brand guidelines verified and cleared for final administrative sign-off.&rdquo;
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 3: Asset Lineage & Metadata -->
                    <div class="audit-card">
                        <div class="card-header-row">
                            <h3 class="card-heading">Asset Lineage &amp; Technical Specs</h3>
                            <span style="font-size: 11px; color: #64748B;">CPT Record</span>
                        </div>

                        <div class="lineage-grid">
                            <div class="lineage-item">
                                <span class="lineage-lbl">Author / Maker</span>
                                <span class="lineage-val"><?php echo esc_html($data['maker_name']); ?></span>
                            </div>
                            <div class="lineage-item">
                                <span class="lineage-lbl">Submitted At</span>
                                <span class="lineage-val"><?php echo esc_html($data['submitted_at']); ?></span>
                            </div>
                            <div class="lineage-item">
                                <span class="lineage-lbl">Campaign Context</span>
                                <span class="lineage-val"><?php echo esc_html($data['campaign_name']); ?></span>
                            </div>
                            <div class="lineage-item">
                                <span class="lineage-lbl">Asset Format</span>
                                <span class="lineage-val"><?php echo esc_html($data['file_dimensions']); ?></span>
                            </div>
                            <div class="lineage-item">
                                <span class="lineage-lbl">Color Profile</span>
                                <span class="lineage-val"><?php echo esc_html($data['color_profile']); ?></span>
                            </div>
                            <div class="lineage-item">
                                <span class="lineage-lbl">Version Status</span>
                                <span class="lineage-val" style="color: #10B981;">Final Master (V<?php echo (int) $data['version_num']; ?>)</span>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 4: Actions & Compliance Trail -->
                    <div class="audit-card" style="gap: 12px;">
                        <?php if (!empty($data['file_url'])): ?>
                            <a href="<?php echo esc_url($data['file_url']); ?>" download="<?php echo esc_attr($data['file_name']); ?>" class="btn-download-master">
                                <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M3 17a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm3.293-7.707a1 1 0 011.414 0L9 10.586V3a1 1 0 112 0v7.586l1.293-1.293a1 1 0 111.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                                <span>Download Master File (High-Res)</span>
                            </a>
                        <?php endif; ?>

                        <a href="<?php echo esc_url($compareScreenUrl); ?>" class="btn-action-outline">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/>
                            </svg>
                            <span>Compare with Previous Versions &rarr;</span>
                        </a>

                        <a href="<?php echo esc_url($auditScreenUrl); ?>" class="btn-action-outline">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                            <span>View Full Immutable Audit Trail &rarr;</span>
                        </a>

                        <a href="<?php echo esc_url($workspaceUrl); ?>" class="link-return-workspace">
                            &larr; Return to <?php echo esc_html($workspaceBtnText); ?>
                        </a>
                    </div>

                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

<!-- 5. CLIENT-SIDE CANVAS INTERACTION SCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const img = document.getElementById('previewArtworkImg');
    const container = document.getElementById('canvasContainer');
    const btnZoomIn = document.getElementById('btnZoomIn');
    const btnZoomOut = document.getElementById('btnZoomOut');
    const btnFitScreen = document.getElementById('btnFitScreen');
    const btnFullscreen = document.getElementById('btnFullscreen');

    let currentScale = 1;

    function updateTransform() {
        if (img) {
            img.style.transform = 'scale(' + currentScale + ')';
        }
    }

    if (btnZoomIn) {
        btnZoomIn.addEventListener('click', function() {
            if (currentScale < 3) {
                currentScale += 0.25;
                updateTransform();
            }
        });
    }

    if (btnZoomOut) {
        btnZoomOut.addEventListener('click', function() {
            if (currentScale > 0.5) {
                currentScale -= 0.25;
                updateTransform();
            }
        });
    }

    if (btnFitScreen) {
        btnFitScreen.addEventListener('click', function() {
            currentScale = 1;
            updateTransform();
        });
    }

    if (btnFullscreen && container) {
        btnFullscreen.addEventListener('click', function() {
            if (!document.fullscreenElement) {
                container.requestFullscreen().catch(err => {
                    alert('Error enabling fullscreen: ' + err.message);
                });
            } else {
                document.exitFullscreen();
            }
        });
    }
});
</script>

</body>
</html>

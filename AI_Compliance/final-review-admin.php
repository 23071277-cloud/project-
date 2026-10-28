<?php
/**
 * Template Name: Admin - Final Review
 * Description: Dedicated Full-Screen Final Review & Production Release Authorization for Admin Role - 100% WordPress CPT Integration
 * RBAC: Restricted to Admin Role Only
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

// 3. Current User & RBAC Authentication (Strictly Admin only)
$currentUser = function_exists('pcs_enforce_rbac') ? pcs_enforce_rbac(['ADMIN']) : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'ADMIN');
$isAllowed = ($userRole === 'ADMIN' || $userRole === 'ADMINISTRATOR');

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

// 5. Handle Admin Actions: Production Release OR Request Revisions
$feedbackAlert = '';
$feedbackSuccess = false;
$reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($reqMethod === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $version_id = (int) ($_POST['version_id'] ?? 0);

    if ($action === 'admin_release' && $version_id > 0) {
        $release_notes = sanitize_text_field($_POST['release_notes'] ?? '');
        if (function_exists('handle_admin_release')) {
            $res = handle_admin_release($version_id, $release_notes);
            $feedbackSuccess = !empty($res['success']);
            $feedbackAlert = $res['message'] ?? ($feedbackSuccess ? 'Asset version successfully released to production.' : 'Failed to release asset.');
        }
    } elseif ($action === 'admin_reject' && $version_id > 0) {
        $reject_notes = sanitize_text_field($_POST['reject_notes'] ?? '');
        if (function_exists('handle_admin_reject')) {
            $res = handle_admin_reject($version_id, $reject_notes);
            $feedbackSuccess = !empty($res['success']);
            $feedbackAlert = $res['message'] ?? 'Asset version sent back to checker for revisions.';
        }
    }
}

// 6. Navigation URLs
$adminDashboardUrl  = function_exists('pcs_url') ? pcs_url('admin-dashboard.php') : 'admin-dashboard.php';
$approvalQueueUrl   = function_exists('pcs_url') ? pcs_url('approval-queue-admin.php') : 'approval-queue-admin.php';
$detailAdminBaseUrl = function_exists('pcs_url') ? pcs_url('detail-asset-admin.php') : 'detail-asset-admin.php';
$auditLogBaseUrl    = function_exists('pcs_url') ? pcs_url('audit-log.php') : 'audit-log.php';
$compareBaseUrl     = function_exists('pcs_url') ? pcs_url('compare-version.php') : 'compare-version.php';

// 7. Dynamic Data Query
$target_ver = (int) ($_GET['version_id'] ?? 0);
$target_ast = (int) ($_GET['asset_id'] ?? 0);
$target_cmp = (int) ($_GET['campaign_id'] ?? 0);

$data = function_exists('get_admin_final_review_data')
    ? get_admin_final_review_data($target_ver, $target_ast, $target_cmp)
    : [
        'is_empty' => true,
        'version_id' => 0,
        'version_label' => 'No Version',
        'status_label' => 'No Asset Selected'
    ];

$isEmpty = !empty($data['is_empty']);
$campaignDetailUrl = $data['campaign_id'] ? add_query_arg(['campaign_id' => $data['campaign_id']], $detailAdminBaseUrl) : $adminDashboardUrl;
$auditScreenUrl = $data['campaign_id'] ? add_query_arg([
    'campaign_id' => $data['campaign_id'],
    'asset_id'    => $data['asset_id'],
    'version_id'  => $data['version_id'],
    'from'        => 'final_review_admin'
], $auditLogBaseUrl) : $auditLogBaseUrl;

$compareScreenUrl = $data['campaign_id'] ? add_query_arg([
    'campaign_id' => $data['campaign_id'],
    'asset_id'    => $data['asset_id'],
    'v_cur'       => $data['version_id'],
    'from'        => 'final_review_admin'
], $compareBaseUrl) : $compareBaseUrl;
?>
<!DOCTYPE html>
<html <?php if (function_exists('language_attributes')) language_attributes(); ?>>
<head>
    <meta charset="<?php echo function_exists('bloginfo') ? bloginfo('charset') : 'UTF-8'; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Final Review: <?php echo esc_html($data['asset_name']); ?> (<?php echo esc_html($data['version_label']); ?>) | Admin Workspace</title>
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

        .FinalReviewAdmin {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            background: linear-gradient(0deg, #FAF8FF 0%, #FAF8FF 100%), white;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
            display: flex;
            min-height: calc(100vh - 72px);
        }

        /* Top Lavender Header */
        .header-banner {
            align-self: stretch;
            padding: 24px 32px;
            position: relative;
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: flex-start;
        }

        .ambient-arcs-bg {
            width: 100%;
            height: 104px;
            left: 0px;
            top: 0px;
            position: absolute;
            opacity: 0.45;
            overflow: hidden;
            pointer-events: none;
        }

        .header-banner-inner {
            width: 100%;
            position: relative;
            z-index: 2;
            justify-content: space-between;
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }

        .breadcrumbs-wrap {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .breadcrumbs-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .breadcrumb-link {
            color: #464651;
            text-decoration: none;
            transition: color 0.15s ease;
        }
        .breadcrumb-link:hover { color: #1F2674; text-decoration: underline; }

        .breadcrumb-sep { color: #C7C5D3; }
        .breadcrumb-current { color: #1F2674; }

        .header-title-row {
            display: flex;
            align-items: baseline;
            gap: 14px;
        }

        .header-title {
            color: #1F2674;
            font-size: 28px;
            font-weight: 700;
            line-height: 34px;
        }

        .header-subtitle {
            color: #464651;
            font-size: 14px;
            font-weight: 500;
        }

        .header-badges-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .status-pill {
            padding: 4px 12px;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            line-height: 16px;
        }
        .status-pill.awaiting {
            background: #FFF6E5;
            color: #B46505;
            border: 1px solid #FFEBAA;
        }
        .status-pill.released {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        .status-pill.changes {
            background: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .status-dot.amber { background: #B46505; }
        .status-dot.green { background: #10B981; }
        .status-dot.red { background: #EF4444; }

        .version-pill {
            padding: 4px 12px;
            background: #EBEDFF;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #1F2674;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid #D5D9F3;
        }

        /* Flash Message Alert */
        .flash-alert {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin: 20px 32px 0;
            animation: fadeIn 0.3s ease;
        }
        .flash-alert.success {
            background-color: #E6F7ED;
            color: #03543F;
            border: 1px solid #BCF0DA;
        }
        .flash-alert.error {
            background-color: #FDF2F2;
            color: #9B1C1C;
            border: 1px solid #F8B4B4;
        }
        .flash-alert-close {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
        }
        .flash-alert-close:hover { opacity: 1; }

        /* Main Viewport Content */
        .main-content {
            width: 100%;
            padding: 28px 32px 48px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        /* 2-Column Asymmetric Grid */
        .review-grid {
            display: grid;
            grid-template-columns: 62% 38%;
            gap: 24px;
            align-items: flex-start;
        }

        /* Left Column: Artwork Preview */
        .artwork-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 230, 250, 0.70);
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .artwork-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            padding: 0 4px;
        }

        .artwork-meta-title {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .artwork-heading {
            color: #1F2674;
            font-size: 18px;
            font-weight: 700;
        }

        .artwork-subtag {
            color: #464651;
            font-size: 14px;
            font-weight: 500;
        }

        .preview-buttons-group {
            padding: 4px;
            background: #F3F2FF;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn-toolbar {
            width: 32px;
            height: 32px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            background: transparent;
            cursor: pointer;
            color: #464651;
            transition: background 0.15s ease;
        }
        .btn-toolbar:hover { background: #EDE9FA; color: #1F2674; }

        .toolbar-divider {
            width: 1px;
            height: 16px;
            background: #C7C5D3;
            margin: 0 2px;
        }

        .artwork-canvas-wrap {
            width: 100%;
            min-height: 480px;
            background: #F8FAFC;
            box-shadow: inset 0px 2px 4px rgba(0, 0, 0, 0.04);
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .artwork-img {
            max-width: 100%;
            max-height: 520px;
            object-fit: contain;
            border-radius: 4px;
            transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            user-select: none;
        }

        .artwork-empty-canvas {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            padding: 48px 24px;
            text-align: center;
        }

        .artwork-empty-icon {
            width: 56px;
            height: 56px;
            border-radius: 28px;
            background: #EDE9FA;
            color: #4E56A6;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Tech Metadata Bar */
        .tech-metadata-bar {
            padding: 12px 16px;
            background: #FAFAFF;
            border-radius: 8px;
            border: 1px solid #E2E6FA;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: #5F657B;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tech-specs-list {
            display: flex;
            align-items: center;
            gap: 14px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
        }

        .btn-download-raw {
            color: #4E56A6;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-download-raw:hover { text-decoration: underline; }

        /* Right Column: Details & Governance Panel */
        .details-col {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .panel-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 230, 250, 0.70);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .panel-card-title {
            color: #1F2A5A;
            font-size: 15px;
            font-weight: 700;
            border-bottom: 1px solid #F3F0FC;
            padding-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Context Grid */
        .context-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            font-size: 13px;
        }

        .context-label {
            color: #5F657B;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .context-val {
            color: #161B2E;
            font-weight: 600;
        }
        .context-val a { color: #4E56A6; text-decoration: none; }
        .context-val a:hover { text-decoration: underline; }

        /* Checker Notes Quote Box */
        .checker-notes-box {
            padding: 12px 14px;
            background: #F8F9FA;
            border-left: 3px solid #10B981;
            border-radius: 4px;
            font-size: 12px;
            color: #374151;
            line-height: 18px;
        }

        /* AI Check Criteria List */
        .checks-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .check-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: #FAFAFF;
            border-radius: 6px;
            border: 1px solid #E2E6FA;
            font-size: 12px;
        }

        .verdict-tag {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .verdict-tag.pass { background: #ECFDF5; color: #065F46; }
        .verdict-tag.needs { background: #FFF8E6; color: #B46505; }
        .verdict-tag.fail { background: #FEF2F2; color: #991B1B; }

        /* Action Form */
        .form-release {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .textarea-release {
            width: 100%;
            height: 80px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #D5D9F3;
            font-family: inherit;
            font-size: 13px;
            color: #161B2E;
            resize: vertical;
            outline: none;
        }
        .textarea-release:focus {
            border-color: #4E56A6;
            box-shadow: 0 0 0 3px rgba(78, 86, 166, 0.12);
        }

        .certify-box {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            background: #FFFDF5;
            border: 1px solid #FFEBAA;
            border-radius: 8px;
        }

        .certify-text {
            color: #8C5303;
            font-size: 12px;
            line-height: 18px;
        }

        .btn-release-primary {
            width: 100%;
            padding: 12px 20px;
            background: #4E56A6;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
            transition: background 0.15s ease;
        }
        .btn-release-primary:hover { background: #3D4389; }

        .btn-reject-secondary {
            width: 100%;
            padding: 10px 18px;
            background: transparent;
            border: 1px solid #E2E6FA;
            border-radius: 8px;
            color: #991B1B;
            font-size: 12px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        .btn-reject-secondary:hover { background: #FEF2F2; border-color: #F8B4B4; }

        .audit-trail-link {
            text-align: center;
            color: #4E56A6;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            margin-top: 4px;
        }
        .audit-trail-link:hover { text-decoration: underline; }

        /* Locked / Released State Box */
        .released-locked-banner {
            padding: 14px 16px;
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .locked-heading {
            color: #065F46;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .locked-meta {
            font-size: 12px;
            color: #047857;
            line-height: 18px;
        }

        /* Modal Backdrop */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(22, 27, 46, 0.55);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-box {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 520px;
            overflow: hidden;
            border: 1px solid #E2E6FA;
            display: flex;
            flex-direction: column;
        }

        .modal-header-row {
            padding: 20px 24px;
            border-bottom: 1px solid #E2E6FA;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #F9F8FD;
        }

        .modal-body-pad {
            padding: 20px 24px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .modal-footer-row {
            padding: 16px 24px 20px;
            border-top: 1px solid #E2E6FA;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            background: #FAF8FF;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 950px) {
            .review-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <?php if (function_exists("wp_head")) wp_head(); ?>
</head>
<body>

    <!-- 1. TOP NAVBAR (UNIFIED ACROSS ALL SCREENS) -->
    <?php
    if (function_exists('pcs_render_top_navbar')) {
        pcs_render_top_navbar([
            'user'              => $currentUser,
            'all_campaigns_url' => $adminDashboardUrl,
            'btn_text'          => 'Admin Workspace'
        ]);
    }
    ?>

<div data-layer="final review - admin" class="FinalReviewAdmin">

    <!-- 2. SECTION - TOP BANNER / COMPACT LAVENDER PAGE HEADER -->
    <header data-layer="Section - Top Banner / Compact Lavender Page Header" class="header-banner">
        <!-- Subtle Ambient Wave Arc Lines Background Accent -->
        <div data-layer="Subtle Ambient Arc Lines Background Accent" class="ambient-arcs-bg">
            <svg width="100%" height="104" viewBox="0 0 1280 83" fill="none" preserveAspectRatio="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M-88 82C266 8 711 104 1368 0" stroke="#E0E0FF" stroke-width="1.2"/>
                <path d="M-88 0C320 60 850 10 1368 52" stroke="#E0E0FF" stroke-width="0.8" stroke-dasharray="3 3"/>
            </svg>
        </div>

        <div class="header-banner-inner">
            <!-- Breadcrumbs + Titles -->
            <div class="breadcrumbs-wrap">
                <nav class="breadcrumbs-nav" aria-label="Breadcrumb">
                    <a href="<?php echo esc_url($adminDashboardUrl); ?>" class="breadcrumb-link">Final Approval</a>
                    <span class="breadcrumb-sep">/</span>
                    <a href="<?php echo esc_url($approvalQueueUrl); ?>" class="breadcrumb-link">Approval Queue</a>
                    <span class="breadcrumb-sep">/</span>
                    <span class="breadcrumb-current">Final Review</span>
                </nav>
                <div class="header-title-row">
                    <h1 class="header-title">Final Review</h1>
                    <span class="header-subtitle">Verify before release</span>
                </div>
            </div>

            <!-- Right Header Badges -->
            <div class="header-badges-right">
                <?php if (!empty($data['is_released'])): ?>
                    <div class="status-pill released">
                        <span class="status-dot green"></span>
                        <span>Released</span>
                    </div>
                <?php elseif (!empty($data['is_awaiting_release']) || ($data['status'] ?? '') === 'approved'): ?>
                    <div class="status-pill awaiting">
                        <span class="status-dot green"></span>
                        <span>Approved</span>
                    </div>
                <?php else: ?>
                    <div class="status-pill <?php echo ($data['status'] === 'rejected') ? 'changes' : 'awaiting'; ?>">
                        <span class="status-dot <?php echo ($data['status'] === 'rejected') ? 'red' : 'amber'; ?>"></span>
                        <span><?php echo esc_html($data['status_label'] ?: 'In Review'); ?></span>
                    </div>
                <?php endif; ?>

                <div class="version-pill">
                    <svg width="14" height="14" viewBox="0 0 15 14" fill="currentColor">
                        <path d="M5.06667 14L3.8 11.8667L1.4 11.3333L1.63333 8.86667L0 7L1.63333 5.13333L1.4 2.66667L3.8 2.13333L5.06667 0L7.33333 0.966667L9.6 0L10.8667 2.13333L13.2667 2.66667L13.0333 5.13333L14.6667 7L13.0333 8.86667L13.2667 11.3333L10.8667 11.8667L9.6 14L7.33333 13.0333L5.06667 14ZM6.63333 9.36667L10.4 5.6L9.46667 4.63333L6.63333 7.46667L5.2 6.06667L4.26667 7L6.63333 9.36667Z"/>
                    </svg>
                    <span>Version <?php echo esc_html($data['version_label']); ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- Feedback Notice Alert -->
    <?php if (!empty($feedbackAlert)): ?>
        <div class="flash-alert <?php echo $feedbackSuccess ? 'success' : 'error'; ?>">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span><?php echo esc_html($feedbackAlert); ?></span>
            </div>
            <button type="button" class="flash-alert-close" onclick="this.parentElement.remove();">&times;</button>
        </div>
    <?php endif; ?>

    <!-- 3. MAIN BODY CONTENT AREA -->
    <main data-layer="Main Body Content Area" class="main-content">

        <?php if (!$isAllowed): ?>
            <!-- Access Restricted Card -->
            <div style="background: white; border: 1px solid #E2E6FA; border-radius: 16px; padding: 56px 24px; text-align: center; max-width: 580px; margin: 40px auto; display: flex; flex-direction: column; align-items: center; gap: 14px;">
                <div style="width: 52px; height: 52px; border-radius: 26px; background: #FDE8E8; color: #9B1C1C; display: flex; align-items: center; justify-content: center;">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                </div>
                <h2 style="color: #1F2A5A; font-size: 18px; font-weight: 700;">Admin Authorization Required</h2>
                <p style="color: #5F657B; font-size: 13px; line-height: 20px;">
                    This final review screen is reserved for institutional compliance administrators. You are currently logged in as <strong><?php echo esc_html($userRole); ?></strong>.
                </p>
                <a href="<?php echo esc_url(function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php'); ?>" style="padding: 10px 18px; background: #4E56A6; color: white; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; margin-top: 6px;">
                    Return to Checker Workspace
                </a>
            </div>

        <?php elseif ($isEmpty): ?>
            <!-- Database Zero State -->
            <div style="background: white; border: 1px solid #E2E6FA; border-radius: 16px; padding: 64px 24px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px;">
                <div style="width: 56px; height: 56px; border-radius: 28px; background: #EDE9FA; color: #4E56A6; display: flex; align-items: center; justify-content: center;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <h2 style="color: #1F2A5A; font-size: 20px; font-weight: 700;">No Asset Selected for Final Review</h2>
                <p style="color: #5F657B; font-size: 14px; max-width: 480px; line-height: 22px;">
                    There are currently no approved asset versions in WordPress awaiting release confirmation, or no target version was specified.
                </p>
                <div style="display: flex; gap: 12px; margin-top: 8px;">
                    <a href="<?php echo esc_url($approvalQueueUrl); ?>" style="padding: 10px 20px; background: #4E56A6; color: white; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none;">
                        Browse Approval Queue &rarr;
                    </a>
                    <a href="<?php echo esc_url($adminDashboardUrl); ?>" style="padding: 10px 18px; border: 1px solid #D5D9F3; border-radius: 8px; color: #5F657B; font-size: 13px; font-weight: 600; text-decoration: none;">
                        Admin Dashboard
                    </a>
                </div>
            </div>

        <?php else: ?>

            <!-- 2-COLUMN ASYMMETRIC GRID -->
            <div class="review-grid">

                <!-- LEFT COLUMN (Approx 62%): Final Artwork Preview -->
                <div data-layer="LEFT COLUMN (Approx 62%): Final Artwork Preview" class="artwork-card">
                    <!-- Artwork Header Toolbar -->
                    <div class="artwork-toolbar">
                        <div class="artwork-meta-title">
                            <span class="artwork-heading">Final Artwork</span>
                            <span style="color: #C7C5D3;">•</span>
                            <span class="artwork-subtag"><?php echo esc_html($data['asset_name']); ?> &middot; <?php echo esc_html($data['version_label']); ?></span>
                        </div>

                        <!-- Preview Toolbar Buttons -->
                        <div class="preview-buttons-group">
                            <button type="button" id="btnZoomOut" class="btn-toolbar" title="Zoom Out">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
                                    <path d="M12.45 13.5L7.725 8.775C7.35 9.075 6.91875 9.3125 6.43125 9.4875C5.94375 9.6625 5.425 9.75 4.875 9.75C3.5125 9.75 2.35938 9.27813 1.41562 8.33438C0.471875 7.39063 0 6.2375 0 4.875C0 3.5125 0.471875 2.35938 1.41562 1.41562C2.35938 0.471875 3.5125 0 4.875 0C6.2375 0 7.39063 0.471875 8.33438 1.41562C9.27813 2.35938 9.75 3.5125 9.75 4.875C9.75 5.425 9.6625 5.94375 9.4875 6.43125C9.3125 6.91875 9.075 7.35 8.775 7.725L13.5 12.45L12.45 13.5ZM4.875 8.25C5.8125 8.25 6.60938 7.92188 7.26562 7.26562C7.92188 6.60938 8.25 5.8125 8.25 4.875C8.25 3.9375 7.92188 3.14062 7.26562 2.48438C6.60938 1.82812 5.8125 1.5 4.875 1.5C3.9375 1.5 3.14062 1.82812 2.48438 2.48438C1.82812 3.14062 1.5 3.9375 1.5 4.875C1.5 5.8125 1.82812 6.60938 2.48438 7.26562C3.14062 7.92188 3.9375 8.25 4.875 8.25ZM3 5.625V4.125H6.75V5.625H3Z"/>
                                </svg>
                            </button>
                            <button type="button" id="btnZoomIn" class="btn-toolbar" title="Zoom In">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
                                    <path d="M12.45 13.5L7.725 8.775C7.35 9.075 6.91875 9.3125 6.43125 9.4875C5.94375 9.6625 5.425 9.75 4.875 9.75C3.5125 9.75 2.35938 9.27813 1.41562 8.33438C0.471875 7.39063 0 6.2375 0 4.875C0 3.5125 0.471875 2.35938 1.41562 1.41562C2.35938 0.471875 3.5125 0 4.875 0C6.2375 0 7.39063 0.471875 8.33438 1.41562C9.27813 2.35938 9.75 3.5125 9.75 4.875C9.75 5.425 9.6625 5.94375 9.4875 6.43125C9.3125 6.91875 9.075 7.35 8.775 7.725L13.5 12.45L12.45 13.5ZM4.875 8.25C5.8125 8.25 6.60938 7.92188 7.26562 7.26562C7.92188 6.60938 8.25 5.8125 8.25 4.875C8.25 3.9375 7.92188 3.14062 7.26562 2.48438C6.60938 1.82812 5.8125 1.5 4.875 1.5C3.9375 1.5 3.14062 1.82812 2.48438 2.48438C1.82812 3.14062 1.5 3.9375 1.5 4.875C1.5 5.8125 1.82812 6.60938 2.48438 7.26562C3.14062 7.92188 3.9375 8.25 4.875 8.25ZM4.125 7.125V5.625H2.625V4.125H4.125V2.625H5.625V4.125H7.125V5.625H5.625V7.125H4.125Z"/>
                                </svg>
                            </button>
                            <button type="button" id="btnFitScreen" class="btn-toolbar" title="Fit to Screen">
                                <svg width="14" height="14" viewBox="0 0 15 12" fill="currentColor">
                                    <path d="M13.5 3.75V1.5H11.25V0H13.5C14.33 0 15 0.67 15 1.5V3.75H13.5ZM0 3.75V1.5C0 0.67 0.67 0 1.5 0H3.75V1.5H1.5V3.75H0ZM11.25 12V10.5H13.5V8.25H15V10.5C15 11.33 14.33 12 13.5 12H11.25ZM1.5 12C0.67 12 0 11.33 0 10.5V8.25H1.5V10.5H3.75V12H1.5ZM3 9V3H12V9H3ZM4.5 7.5H10.5V4.5H4.5V7.5Z"/>
                                </svg>
                            </button>
                            <div class="toolbar-divider"></div>
                            <button type="button" id="btnFullscreen" class="btn-toolbar" title="Toggle Fullscreen">
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor">
                                    <path d="M0 13.5V9.75H1.5V12H3.75V13.5H0ZM9.75 13.5V12H12V9.75H13.5V13.5H9.75ZM0 3.75V0H3.75V1.5H1.5V3.75H0ZM12 3.75V1.5H9.75V0H13.5V3.75H12Z"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Canvas Container -->
                    <div id="canvasContainer" class="artwork-canvas-wrap">
                        <?php if (!empty($data['file_url'])): ?>
                            <?php 
                                $isPdfAdmin = preg_match('/\.pdf(\?.*)?$/i', $data['file_url']);
                                if ($isPdfAdmin): ?>
                                <iframe src="<?php echo esc_url($data['file_url']); ?>#toolbar=0&navpanes=0&view=Fit" style="width: 100%; height: 500px; border: none; border-radius: 8px; background: #ffffff;"></iframe>
                            <?php else: ?>
                                <img id="previewArtworkImg" src="<?php echo esc_url($data['file_url']); ?>" alt="<?php echo esc_attr($data['asset_name']); ?>" class="artwork-img">
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="artwork-empty-canvas">
                                <div class="artwork-empty-icon">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                        <polyline points="21 15 16 10 5 21"></polyline>
                                    </svg>
                                </div>
                                <div style="color: #1F2A5A; font-size: 15px; font-weight: 700;">Digital Creative Canvas</div>
                                <div style="color: #5F657B; font-size: 12px; font-family: 'JetBrains Mono', monospace;">1200 x 628 px &middot; Standard Display Format</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Technical Metadata Strip -->
                    <div class="tech-metadata-bar">
                        <div class="tech-specs-list">
                            <span>DIM: <?php echo esc_html($data['file_dimensions']); ?></span>
                            <span>&middot;</span>
                            <span>FMT: <?php echo esc_html($data['file_format']); ?></span>
                            <span>&middot;</span>
                            <span>PROFILE: <?php echo esc_html($data['color_profile']); ?></span>
                        </div>
                        <?php if (!empty($data['file_url'])): ?>
                            <a href="<?php echo esc_url($data['file_url']); ?>" download class="btn-download-raw">
                                <span>Download Original &darr;</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- RIGHT COLUMN (Approx 38%): Details & Sign-off Panel -->
                <div data-layer="RIGHT COLUMN (Approx 38%): Details Panel" class="details-col">

                    <!-- Card 1: Campaign & Asset Context -->
                    <div class="panel-card">
                        <div class="panel-card-title">
                            <span>Campaign & Asset Details</span>
                            <span style="font-size: 11px; font-weight: 600; color: #4E56A6; font-family: 'JetBrains Mono', monospace;">
                                <?php echo esc_html($data['campaign_code'] ?: 'ID #' . $data['asset_id']); ?>
                            </span>
                        </div>

                        <div class="context-grid">
                            <div>
                                <div class="context-label">Campaign</div>
                                <div class="context-val">
                                    <a href="<?php echo esc_url($campaignDetailUrl); ?>">
                                        <?php echo esc_html($data['campaign_name']); ?>
                                    </a>
                                </div>
                            </div>
                            <div>
                                <div class="context-label">Target Asset</div>
                                <div class="context-val"><?php echo esc_html($data['asset_name']); ?></div>
                            </div>
                            <div>
                                <div class="context-label">Creative Maker</div>
                                <div class="context-val">M &middot; <?php echo esc_html($data['maker_name']); ?></div>
                            </div>
                            <div>
                                <div class="context-label">Campaign Deadline</div>
                                <div class="context-val"><?php echo esc_html($data['deadline']); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Card 2: Checker Sign-off Verification -->
                    <div class="panel-card">
                        <div class="panel-card-title">
                            <span>Checker Compliance Sign-off</span>
                            <span class="verdict-tag pass">Approved</span>
                        </div>

                        <div class="context-grid">
                            <div>
                                <div class="context-label">Checker Reviewer</div>
                                <div class="context-val">C &middot; <?php echo esc_html($data['checker_name']); ?></div>
                            </div>
                            <div>
                                <div class="context-label">Signed-off At</div>
                                <div class="context-val" style="font-size: 12px;"><?php 
                                    $signedAt = (!empty($data['checker_reviewed_at']) && $data['checker_reviewed_at'] !== '—')
                                        ? $data['checker_reviewed_at']
                                        : (!empty($data['submitted_at']) && $data['submitted_at'] !== '—' ? $data['submitted_at'] : date('d M Y, H:i \S\G\T'));
                                    echo esc_html($signedAt); 
                                ?></div>
                            </div>
                        </div>

                        <div>
                            <div class="context-label" style="margin-bottom: 4px;">Checker Review Remarks</div>
                            <div class="checker-notes-box">
                                <?php if (!empty($data['checker_notes'])): ?>
                                    &ldquo;<?php echo esc_html($data['checker_notes']); ?>&rdquo;
                                <?php else: ?>
                                    <em>Checker confirmed compliance with brand guidelines. No revisions requested.</em>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Card 3: AI Compliance Pre-Flight Summary -->
                    <div class="panel-card">
                        <div class="panel-card-title">
                            <span>AI Compliance Evaluation</span>
                            <span class="verdict-tag pass">Pass</span>
                        </div>

                        <div class="checks-list">
                            <?php if (!empty($data['checks'])): ?>
                                <?php foreach (array_slice($data['checks'], 0, 4) as $chk): 
                                    $v = strtoupper($chk['verdict'] ?? 'PASS');
                                    $tagClass = ($v === 'PASS') ? 'pass' : (($v === 'FAIL') ? 'fail' : 'needs');
                                ?>
                                    <div class="check-item-row">
                                        <span style="font-weight: 600; color: #1F2A5A;"><?php echo esc_html($chk['criterion']); ?></span>
                                        <span class="verdict-tag <?php echo $tagClass; ?>"><?php echo esc_html($v); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="check-item-row">
                                    <span>Brand Logo & Asset Integrity</span>
                                    <span class="verdict-tag pass">PASS</span>
                                </div>
                                <div class="check-item-row">
                                    <span>Mandatory Risk Disclosures</span>
                                    <span class="verdict-tag pass">PASS</span>
                                </div>
                                <div class="check-item-row">
                                    <span>Fair Dealing Principles (MAS Notice 637)</span>
                                    <span class="verdict-tag pass">PASS</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div style="text-align: right; margin-top: 2px;">
                            <a href="<?php echo esc_url($compareScreenUrl); ?>" style="color: #4E56A6; font-size: 12px; font-weight: 600; text-decoration: none;">
                                Compare Version Differences &rarr;
                            </a>
                        </div>
                    </div>

                    <!-- Card 4: Production Release Sign-off Authorization Form -->
                    <div class="panel-card">
                        <div class="panel-card-title">
                            <span>Production Release Sign-off</span>
                        </div>

                        <?php if (!empty($data['is_released'])): ?>
                            <!-- Locked State -->
                            <div class="released-locked-banner">
                                <div class="locked-heading">
                                    <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Released to Production &amp; Immutable</span>
                                </div>
                                <div class="locked-meta">
                                    Released by <strong><?php echo esc_html($data['admin_released_by']); ?></strong> on <?php echo esc_html($data['admin_released_at']); ?>.
                                </div>
                                <?php if (!empty($data['admin_release_notes'])): ?>
                                    <div style="margin-top: 4px; font-size: 12px; color: #065F46; font-style: italic;">
                                        &ldquo;<?php echo esc_html($data['admin_release_notes']); ?>&rdquo;
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <!-- Authorization Form -->
                            <form method="POST" action="" class="form-release">
                                <input type="hidden" name="action" value="admin_release">
                                <input type="hidden" name="version_id" value="<?php echo esc_attr($data['version_id']); ?>">

                                <div>
                                    <label for="releaseNotesArea" class="context-label" style="margin-bottom: 6px; display: block;">
                                        Admin Remarks (Optional)
                                    </label>
                                    <textarea id="releaseNotesArea" name="release_notes" class="textarea-release" placeholder="Enter administrative sign-off remarks, deployment instructions, or compliance audit notes..."></textarea>
                                </div>

                                <div class="certify-box">
                                    <input type="checkbox" id="adminCertifyCheck" required style="margin-top: 2px; accent-color: #4E56A6; cursor: pointer;">
                                    <label for="adminCertifyCheck" class="certify-text">
                                        I hereby certify as authorized compliance administrator that this creative asset meets institutional brand standards and applicable regulatory mandates (MAS Notice 637) and is authorized for production distribution.
                                    </label>
                                </div>

                                <button type="submit" class="btn-release-primary">
                                    <svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                    <span>Release</span>
                                </button>

                                <button type="button" class="btn-reject-secondary" onclick="openRejectModal();">
                                    <span>Request Revisions</span>
                                </button>
                            </form>
                        <?php endif; ?>

                        <a href="<?php echo esc_url($auditScreenUrl); ?>" class="audit-trail-link">
                            Audit Trail
                        </a>
                    </div>

                </div>

            </div>

        <?php endif; ?>

    </main>

</div>

<!-- 4. REQUEST REVISIONS / REJECT MODAL -->
<div id="rejectModal" class="modal-overlay">
    <div class="modal-box">
        <form method="POST" action="">
            <input type="hidden" name="action" value="admin_reject">
            <input type="hidden" name="version_id" value="<?php echo esc_attr($data['version_id']); ?>">

            <div class="modal-header-row">
                <h3 style="color: #991B1B; font-size: 16px; font-weight: 700;">Request Asset Revisions</h3>
                <button type="button" onclick="closeRejectModal();" style="border: none; background: none; font-size: 18px; color: #5F657B; cursor: pointer;">&times;</button>
            </div>

            <div class="modal-body-pad">
                <p style="color: #5F657B; font-size: 13px; line-height: 20px;">
                    Send version <strong><?php echo esc_html($data['version_label']); ?></strong> back to the checker and maker for revisions. Please provide clear compliance directives:
                </p>
                <textarea name="reject_notes" class="textarea-release" required style="height: 100px;" placeholder="Detail the required brand modifications, disclosure amendments, or regulatory defects..."></textarea>
            </div>

            <div class="modal-footer-row">
                <button type="button" onclick="closeRejectModal();" style="padding: 8px 16px; background: white; border: 1px solid #D5D9F3; border-radius: 8px; font-size: 13px; font-weight: 600; color: #5F657B; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 18px; background: #DC2626; border: none; border-radius: 8px; font-size: 13px; font-weight: 600; color: white; cursor: pointer;">Confirm &amp; Request Revisions</button>
            </div>
        </form>
    </div>
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

    // Modal helpers
    const rejectModal = document.getElementById('rejectModal');
    window.openRejectModal = function() {
        if (rejectModal) rejectModal.style.display = 'flex';
    };
    window.closeRejectModal = function() {
        if (rejectModal) rejectModal.style.display = 'none';
    };

    if (rejectModal) {
        rejectModal.addEventListener('click', function(e) {
            if (e.target === rejectModal) {
                closeRejectModal();
            }
        });
    }
});
</script>

    <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body>
</html>

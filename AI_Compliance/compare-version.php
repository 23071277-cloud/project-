<?php
/**
 * Template Name: Compare Versions - All Roles
 * Description: Unified artwork and AI criteria comparison screen for Maker, Checker, and Admin roles.
 * 100% Dynamic Database Integration: asset, asset_version, campaign, check_result, audit_log, wp_users
 */

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

// 2. Safe Fallback Polyfills for Standalone Execution
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

// 3. Current User & RBAC Authentication (All 3 Roles: Maker, Checker, Admin)
$currentUser = function_exists('pcs_enforce_rbac') ? pcs_enforce_rbac(['MAKER', 'CHECKER', 'ADMIN']) : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'CHECKER');
$allowedRoles = ['MAKER', 'CHECKER', 'ADMIN', 'AUTHOR', 'CONTRIBUTOR', 'EDITOR', 'ADMINISTRATOR'];
$isAllowed = in_array($userRole, $allowedRoles, true);

// 4. Query Parameters
$assetId    = (int) ($_GET['asset_id'] ?? 0);
$vPrevId    = (int) ($_GET['v_prev'] ?? 0);
$vCurId     = (int) ($_GET['v_cur'] ?? 0);
$campaignId = (int) ($_GET['campaign_id'] ?? 0);
$from       = sanitize_text_field($_GET['from'] ?? '');

// 5. Fetch Screen Data (100% Dynamic from WordPress Database)
$data = function_exists('get_compare_versions_screen_data')
    ? get_compare_versions_screen_data($assetId, $vPrevId, $vCurId, $campaignId, $userRole)
    : [
        'is_empty'      => true,
        'current_user'  => $currentUser,
        'user_role'     => $userRole,
        'asset_id'      => 0,
        'asset_name'    => '—',
        'asset_type'    => '—',
        'dimensions'    => '—',
        'campaign_id'   => 0,
        'campaign_name' => '—',
        'all_versions'  => [],
        'v_prev'        => null,
        'v_cur'         => null,
        'feedback'      => ['has_feedback' => false],
        'criteria_diff' => [],
        'return_url'    => 'checker-dashboard.php',
        'return_label'  => 'Return to Review Workspace',
    ];

$isEmpty      = !empty($data['is_empty']);
$assetName    = $data['asset_name'] ?? '—';
$campaignName = $data['campaign_name'] ?? '—';
$dimensions   = $data['dimensions'] ?? '—';
$allVersions  = $data['all_versions'] ?? [];
$vPrev        = $data['v_prev'] ?? null;
$vCur         = $data['v_cur'] ?? null;
$feedback     = $data['feedback'] ?? ['has_feedback' => false];
$criteriaDiff = $data['criteria_diff'] ?? [];
$returnUrl    = $data['return_url'] ?? '#';
$returnLabel  = $data['return_label'] ?? 'Return to Workspace';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compare Versions · <?= esc_html($assetName) ?> · AI Compliance</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400;1,500;1,700&display=swap" rel="stylesheet">
    <style>
        /* ================= 1. GLOBAL RESET & BASE ================= */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #FAF8FF;
            color: #161B2E;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }
        a {
            color: inherit;
            text-decoration: none;
        }
        button, select {
            font-family: inherit;
            cursor: pointer;
        }

        /* Top Navbar Wrapper */
        .top-navbar-wrapper {
            width: 100%;
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #FFFFFF;
            border-bottom: 1px solid #E2E6FA;
        }

        /* ================= 2. MAIN VIEWPORT CONTAINER ================= */
        .compare-page-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
            background: #FAF8FF;
            padding-bottom: 60px;
        }
        .main-content-slot {
            width: 100%;
            max-width: 1300px;
            padding: 28px 24px 40px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        /* ================= 3. SECTION 1: HEADER & NAVIGATION ================= */
        .header-navigation {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
            flex-wrap: wrap;
        }
        .nav-breadcrumbs-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .nav-breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            line-height: 19.5px;
            color: #5F657B;
        }
        .nav-breadcrumbs a {
            color: #5F657B;
            transition: color 0.15s;
        }
        .nav-breadcrumbs a:hover {
            color: #1F2A5A;
            text-decoration: underline;
        }
        .nav-breadcrumbs .separator {
            color: #C7C5D3;
        }
        .nav-breadcrumbs .crumb-active {
            color: #1F2A5A;
            font-weight: 600;
        }
        .title-campaign {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .title-campaign h1 {
            font-size: 28px;
            font-weight: 700;
            line-height: 35px;
            color: #161B2E;
            letter-spacing: -0.5px;
        }
        .campaign-subtitle {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            line-height: 21px;
            color: #5F657B;
        }
        .campaign-subtitle .dot-sep {
            color: #C7C5D3;
        }

        /* Top Right Return Button */
        .btn-return-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 9px 16px;
            background: #FFFFFF;
            border: 1px solid #E2E6FA;
            border-radius: 8px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            font-size: 13px;
            font-weight: 600;
            color: #1F2A5A;
            line-height: 19.5px;
            transition: all 0.15s ease;
        }
        .btn-return-action:hover {
            background: #F3F5FD;
            border-color: #CBD5E1;
            transform: translateY(-1px);
        }
        .btn-return-action svg {
            flex-shrink: 0;
            transition: transform 0.15s ease;
        }
        .btn-return-action:hover svg {
            transform: translateX(-2px);
        }

        /* ================= 4. SECTION 2: VERSION SELECTION & FEEDBACK ================= */
        .selection-and-feedback-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Version Selection Bar */
        .version-selection-bar {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: #F3F5FD;
            border: 1px solid #E2E6FA;
            border-radius: 12px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            flex-wrap: wrap;
            gap: 16px;
        }

        .selector-box-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .selector-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.55px;
            color: #5F657B;
        }

        /* Version Select Controls */
        .version-dropdown-control {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .version-select {
            appearance: none;
            -webkit-appearance: none;
            padding: 6px 36px 6px 14px;
            font-size: 13px;
            font-weight: 700;
            line-height: 22px;
            border-radius: 8px;
            border: 1px solid #E2E6FA;
            background: #FFFFFF;
            color: #1F2A5A;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .version-select:hover {
            border-color: #B4B9D8;
        }
        .version-select:focus {
            outline: none;
            border-color: #1F2A5A;
            box-shadow: 0 0 0 3px rgba(31, 42, 90, 0.12);
        }
        .version-select.current-select {
            background: #1F2A5A;
            color: #FFFFFF;
            border-color: #1F2A5A;
        }
        .version-select.current-select:focus {
            box-shadow: 0 0 0 3px rgba(31, 42, 90, 0.25);
        }
        .select-arrow {
            position: absolute;
            right: 12px;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Status Badge Pills */
        .status-badge-pill {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            line-height: 16.5px;
        }
        .badge-draft {
            background: #F1F5F9;
            color: #475569;
            border: 1px solid #CBD5E1;
        }
        .badge-scoring {
            background: #FFFBEB;
            color: #D97706;
            border: 1px solid #FCD34D;
        }
        .badge-in_review, .badge-in-review {
            background: #EEF2FF;
            color: #373E8C;
            border: 1px solid #C7D2FE;
        }
        .badge-rejected {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }
        .badge-approved {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        .badge-released {
            background: #F5F3FF;
            color: #7C3AED;
            border: 1px solid #DDD6FE;
        }

        /* Comparison Center Indicator */
        .comparison-indicator {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #1F2A5A;
            background: #FFFFFF;
            padding: 6px 16px;
            border-radius: 9999px;
            border: 1px solid #E2E6FA;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.03);
        }
        .comparison-indicator svg {
            color: #4E56A6;
            flex-shrink: 0;
        }

        /* Historical Review Feedback Strip (Preserved Feedback) */
        .feedback-strip-alert {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 20px;
            background: #FDEFF1;
            border: 1px solid #FECACA;
            border-radius: 12px;
            box-shadow: none;
            flex-wrap: wrap;
            gap: 12px;
        }
        .feedback-strip-left {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        .feedback-meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            line-height: 19.5px;
            flex-wrap: wrap;
        }
        .feedback-ver-status {
            font-weight: 700;
            color: #B83A4B;
        }
        .feedback-strip-right {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            line-height: 19.5px;
            color: #161B2E;
            font-style: italic;
        }
        .feedback-ref-label {
            font-weight: 600;
            color: #161B2E;
        }
        .feedback-note-text {
            font-weight: 400;
            color: #161B2E;
        }

        /* Neutral Feedback Strip (When no rejection note recorded) */
        .feedback-strip-neutral {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 18px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            font-size: 13px;
            color: #64748B;
            box-shadow: none;
        }

        /* ================= 5. SECTION 3: SIDE-BY-SIDE ARTWORK COMPARISON ================= */
        .artwork-comparison-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .section-header-row {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .section-header-row h2 {
            font-size: 18px;
            font-weight: 700;
            line-height: 27px;
            color: #161B2E;
        }
        .section-header-badge {
            font-size: 12px;
            font-weight: 500;
            color: #5F657B;
            background: #F3F5FD;
            padding: 4px 10px;
            border-radius: 6px;
            border: 1px solid #E2E6FA;
        }

        .artwork-grid {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
        }
        @media (max-width: 960px) {
            .artwork-grid {
                grid-template-columns: 1fr;
            }
        }

        .artwork-card {
            background: #FFFFFF;
            border: 1px solid #E2E6FA;
            border-radius: 12px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
        }
        .artwork-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-header-left {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .version-title {
            font-size: 16px;
            font-weight: 700;
            line-height: 24px;
            color: #161B2E;
        }
        .card-header-label {
            font-size: 12px;
            color: #5F657B;
        }

        /* Preview Canvas Viewport */
        .artwork-preview-box {
            width: 100%;
            height: 380px;
            background: #161B2E;
            border-radius: 8px;
            border: 1px solid #E2E6FA;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        .artwork-preview-box.archived-placeholder {
            background: #FAF8FF;
            flex-direction: column;
            padding: 30px;
            text-align: center;
            gap: 12px;
        }
        .artwork-preview-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            cursor: zoom-in;
            transition: transform 0.2s ease;
        }
        .artwork-preview-box img:hover {
            transform: scale(1.02);
        }

        .archived-icon-wrap {
            width: 48px;
            height: 48px;
            background: #EBEDFF;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #5F657B;
        }
        .archived-title {
            font-size: 14px;
            font-weight: 600;
            color: #161B2E;
            line-height: 21px;
        }
        .archived-desc {
            font-size: 12px;
            color: #5F657B;
            line-height: 18px;
            max-width: 320px;
        }

        /* Metadata & Action Footer */
        .artwork-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #E2E6FA;
            gap: 12px;
            flex-wrap: wrap;
        }
        .artwork-file-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .artwork-file-name {
            font-size: 12px;
            font-weight: 500;
            color: #161B2E;
            max-width: 280px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .artwork-file-meta {
            font-size: 11px;
            color: #5F657B;
        }

        .btn-view-full {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.15s ease;
            border: none;
        }
        .btn-view-full.active-btn {
            background: #1F2A5A;
            color: #FFFFFF;
        }
        .btn-view-full.active-btn:hover {
            background: #161E42;
            transform: translateY(-1px);
        }
        .btn-view-full.inactive-btn {
            background: #F3F5FD;
            color: #C7C5D3;
            cursor: not-allowed;
        }

        /* ================= 6. SECTION 4: AI CRITERIA DIFF MATRIX ================= */
        .criteria-diff-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .matrix-card {
            background: #FFFFFF;
            border: 1px solid #E2E6FA;
            border-radius: 12px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .matrix-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .matrix-table th {
            background: #F8FAFC;
            padding: 12px 18px;
            font-size: 11px;
            font-weight: 700;
            color: #5F657B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #E2E6FA;
        }
        .matrix-table td {
            padding: 16px 18px;
            font-size: 13px;
            line-height: 20px;
            color: #161B2E;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: top;
        }
        .matrix-table tr:last-child td {
            border-bottom: none;
        }
        .matrix-table tr:hover td {
            background: #FAF8FF;
        }

        .criterion-name-col {
            font-weight: 600;
            color: #161B2E;
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .criterion-rule-code {
            font-size: 11px;
            color: #64748B;
            font-weight: 400;
        }

        .verdict-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .verdict-tag.pass {
            background: #ECFDF5;
            color: #059669;
        }
        .verdict-tag.fail {
            background: #FEF2F2;
            color: #DC2626;
        }
        .verdict-tag.needs-look, .verdict-tag.needs_look {
            background: #FEF6E8;
            color: #B46505;
        }
        .verdict-tag.none {
            background: #F1F5F9;
            color: #64748B;
        }

        .delta-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 9px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
        }
        .delta-pill.resolved, .delta-pill.improved {
            background: #ECFDF5;
            color: #059669;
        }
        .delta-pill.regressed {
            background: #FEF2F2;
            color: #DC2626;
        }
        .delta-pill.maintained {
            background: #F0FDF4;
            color: #15803D;
        }
        .delta-pill.neutral {
            background: #F1F5F9;
            color: #64748B;
        }

        /* Empty State Containers */
        .empty-diff-state {
            padding: 48px 24px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            color: #5F657B;
        }
        .empty-diff-state svg {
            color: #CBD5E1;
        }
        .empty-diff-title {
            font-size: 16px;
            font-weight: 700;
            color: #161B2E;
        }
        .empty-diff-desc {
            font-size: 13px;
            max-width: 440px;
            line-height: 20px;
        }

        /* ================= 7. LIGHTBOX MODAL ================= */
        .lightbox-modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .lightbox-modal.active {
            display: flex;
        }
        .lightbox-content {
            position: relative;
            max-width: 90vw;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .lightbox-img {
            max-width: 100%;
            max-height: 85vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
        }
        .lightbox-caption {
            margin-top: 12px;
            font-size: 13px;
            color: #FFFFFF;
            font-weight: 500;
        }
        .lightbox-close-btn {
            position: absolute;
            top: -40px;
            right: 0;
            background: transparent;
            border: none;
            color: #FFFFFF;
            font-size: 28px;
            font-weight: 300;
            cursor: pointer;
            line-height: 1;
        }
        .lightbox-close-btn:hover {
            color: #CBD5E1;
        }
    </style>
    <?php if (function_exists("wp_head")) wp_head(); ?>
</head>
<body>

    <!-- ================= 1. UNIFIED TOP NAVBAR ================= -->
    <?php
    if (function_exists('pcs_render_top_navbar')) {
        pcs_render_top_navbar([
            'user'              => $currentUser,
            'all_campaigns_url' => $returnUrl,
            'btn_text'          => $returnLabel
        ]);
    }
    ?>

    <!-- ================= 2. MAIN COMPARE WRAPPER ================= -->
    <div class="compare-page-wrapper">
        <main class="main-content-slot">

            <!-- 1. Header & Navigation -->
            <section class="header-navigation">
                <div class="nav-breadcrumbs-group">
                    <nav class="nav-breadcrumbs" aria-label="Breadcrumb">
                        <a href="<?= esc_url($returnUrl) ?>">
                            <?php if ($userRole === 'MAKER'): ?>
                                Maker Workspace
                            <?php elseif ($userRole === 'ADMIN'): ?>
                                Admin Workspace
                            <?php else: ?>
                                Review Queue
                            <?php endif; ?>
                        </a>
                        <span class="separator">/</span>
                        <?php if ($assetName !== '—'): ?>
                            <span class="crumb-link"><?= esc_html($assetName) ?><?= $vCur ? ' · ' . esc_html($vCur['label']) : '' ?></span>
                            <span class="separator">/</span>
                        <?php endif; ?>
                        <span class="crumb-active">Compare Versions</span>
                    </nav>

                    <div class="title-campaign">
                        <h1>Compare Versions</h1>
                        <div class="campaign-subtitle">
                            <span><?= esc_html($campaignName) ?> · <?= esc_html($assetName) ?></span>
                            <span class="dot-sep">·</span>
                            <span><?= esc_html($dimensions) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Return Button -->
                <div>
                    <a href="<?= esc_url($returnUrl) ?>" class="btn-return-action">
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.86875 6.75L7.06875 10.95L6 12L0 6L6 0L7.06875 1.05L2.86875 5.25H12V6.75H2.86875V6.75" fill="#1F2A5A"/>
                        </svg>
                        <span><?= esc_html($returnLabel) ?></span>
                    </a>
                </div>
            </section>

            <?php if ($isEmpty): ?>
                <!-- EMPTY STATE: DATABASE RỖNG HOẶC CHƯA CÓ ASSET -->
                <div class="matrix-card">
                    <div class="empty-diff-state">
                        <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <path d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <div class="empty-diff-title">No Version Records in Database</div>
                        <p class="empty-diff-desc">
                            There are currently no uploaded assets or revisions recorded in your WordPress database.
                            When a Maker uploads multiple versions of an asset, visual diffs and compliance checks will be rendered here.
                        </p>
                        <a href="<?= esc_url($returnUrl) ?>" class="btn-return-action" style="margin-top: 12px;">
                            <span><?= esc_html($returnLabel) ?></span>
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <!-- 2. Refined Version Selection Bar & Previous Rejection Feedback Strip -->
                <section class="selection-and-feedback-section">
                    
                    <!-- Version Selection Bar -->
                    <div class="version-selection-bar">
                        <!-- Left: Previous Version Selector -->
                        <div class="selector-box-group">
                            <span class="selector-label">PREVIOUS:</span>
                            <?php if ($vPrev): ?>
                                <div class="version-dropdown-control">
                                    <select class="version-select" id="selectVPrev" onchange="changeVersionComparison()">
                                        <?php foreach ($allVersions as $v): ?>
                                            <option value="<?= (int) $v['id'] ?>" <?= $v['id'] === $vPrev['id'] ? 'selected' : '' ?>>
                                                <?= esc_html($v['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="select-arrow">
                                        <svg width="12" height="12" viewBox="0 0 20 20" fill="none">
                                            <path d="M5.85 7.8L9.75 11.7L13.65 7.8" stroke="#6B7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </div>
                                <span class="status-badge-pill badge-<?= sanitize_html_class($vPrev['status']) ?>">
                                    <?= esc_html($vPrev['status_label']) ?>
                                </span>
                            <?php else: ?>
                                <span style="font-size: 13px; color: #94A3B8; font-style: italic;">No previous version</span>
                            <?php endif; ?>
                        </div>

                        <!-- Center: Comparison Indicator -->
                        <div class="comparison-indicator">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/>
                            </svg>
                            <span><?= $vPrev ? esc_html($vPrev['label']) : '—' ?> vs <?= $vCur ? esc_html($vCur['label']) : '—' ?> Difference Analysis</span>
                        </div>

                        <!-- Right: Current Version Selector -->
                        <div class="selector-box-group">
                            <span class="selector-label">CURRENT:</span>
                            <?php if ($vCur): ?>
                                <span class="status-badge-pill badge-<?= sanitize_html_class($vCur['status']) ?>">
                                    <?= esc_html($vCur['status_label']) ?>
                                </span>
                                <div class="version-dropdown-control">
                                    <select class="version-select current-select" id="selectVCur" onchange="changeVersionComparison()">
                                        <?php foreach ($allVersions as $v): ?>
                                            <option value="<?= (int) $v['id'] ?>" <?= $v['id'] === $vCur['id'] ? 'selected' : '' ?>>
                                                <?= esc_html($v['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="select-arrow">
                                        <svg width="10" height="10" viewBox="0 0 20 20" fill="none">
                                            <path d="M5.85 7.8L9.75 11.7L13.65 7.8" stroke="#FFFFFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span style="font-size: 13px; color: #94A3B8; font-style: italic;">No current version</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Preserved Historical Review Feedback Strip -->
                    <?php if (!empty($feedback['has_feedback'])): ?>
                        <div class="feedback-strip-alert">
                            <div class="feedback-strip-left">
                                <svg width="15" height="17" viewBox="0 0 15 17" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7.5 13.3333C7.73611 13.3333 7.93403 13.2535 8.09375 13.0938C8.25347 12.934 8.33333 12.7361 8.33333 12.5C8.33333 12.2639 8.25347 12.066 8.09375 11.9062C7.93403 11.7465 7.73611 11.6667 7.5 11.6667C7.26389 11.6667 7.06597 11.7465 6.90625 11.9062C6.74653 12.066 6.66667 12.2639 6.66667 12.5C6.66667 12.7361 6.74653 12.934 6.90625 13.0938C7.06597 13.2535 7.26389 13.3333 7.5 13.3333M6.66667 10H8.33333V5H6.66667V10M1.66667 16.6667C1.20833 16.6667 0.815972 16.5035 0.489583 16.1771C0.163194 15.8507 0 15.4583 0 15V3.33333C0 2.875 0.163194 2.48264 0.489583 2.15625C0.815972 1.82986 1.20833 1.66667 1.66667 1.66667H5.16667C5.34722 1.16667 5.64931 0.763889 6.07292 0.458333C6.49653 0.152778 6.97222 0 7.5 0C8.02778 0 8.50347 0.152778 8.92708 0.458333C9.35069 0.763889 9.65278 1.16667 9.83333 1.66667H13.3333C13.7917 1.66667 14.184 1.82986 14.5104 2.15625C14.8368 2.48264 15 2.875 15 3.33333V15C15 15.4583 14.8368 15.8507 14.5104 16.1771C14.184 16.5035 13.7917 16.6667 13.3333 16.6667H1.66667M1.66667 15H13.3333V3.33333H1.66667V15" fill="#B83A4B"/>
                                </svg>
                                <div class="feedback-meta-row">
                                    <span class="feedback-ver-status"><?= esc_html($feedback['version_label']) ?> · <?= esc_html($feedback['status_label']) ?></span>
                                    <span style="color:#C7C5D3;">·</span>
                                    <span>Reviewer: <strong><?= esc_html($feedback['reviewer_name']) ?></strong></span>
                                    <span style="color:#C7C5D3;">·</span>
                                    <span><?= esc_html($feedback['date']) ?></span>
                                </div>
                            </div>
                            <div class="feedback-strip-right">
                                <span class="feedback-ref-label">Feedback Reference:</span>
                                <span class="feedback-note-text">“<?= esc_html($feedback['note']) ?>”</span>
                            </div>
                        </div>
                    <?php elseif ($vPrev): ?>
                        <div class="feedback-strip-neutral">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                            </svg>
                            <span>No formal rejection feedback recorded for <?= esc_html($vPrev['label']) ?> in audit logs.</span>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- 3. Side-by-Side Artwork Comparison -->
                <section class="artwork-comparison-section">
                    <div class="section-header-row">
                        <h2>Artwork Comparison</h2>
                        <span class="section-header-badge"><?= esc_html($dimensions) ?></span>
                    </div>

                    <div class="artwork-grid">
                        <!-- LEFT: Previous Artwork Container -->
                        <div class="artwork-card">
                            <div class="artwork-card-header">
                                <div class="card-header-left">
                                    <span class="version-title"><?= $vPrev ? esc_html($vPrev['label']) : '—' ?></span>
                                    <?php if ($vPrev): ?>
                                        <span class="status-badge-pill badge-<?= sanitize_html_class($vPrev['status']) ?>">
                                            <?= esc_html($vPrev['status_label']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="card-header-label">Previous Submitted Artwork</span>
                            </div>

                            <!-- Preview Viewport -->
                            <?php if ($vPrev && !empty($vPrev['file_url'])): ?>
                                <?php $isPrevPdf = preg_match('/\.pdf(\?.*)?$/i', $vPrev['file_url']); ?>
                                <div class="artwork-preview-box">
                                    <?php if ($isPrevPdf): ?>
                                        <iframe src="<?= esc_url($vPrev['file_url']) ?>#toolbar=0&navpanes=0&view=Fit" style="width: 100%; height: 100%; min-height: 280px; border: none; background: #fff;"></iframe>
                                    <?php else: ?>
                                        <img src="<?= esc_url($vPrev['file_url']) ?>" alt="Previous Version <?= esc_attr($vPrev['label']) ?>" onclick="openLightbox('<?= esc_url($vPrev['file_url']) ?>', '<?= esc_attr($vPrev['label']) ?> · <?= esc_attr($vPrev['file_name']) ?>')">
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="artwork-preview-box archived-placeholder">
                                    <div class="archived-icon-wrap">
                                        <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M19.775 21.2L17.475 18.9H4.3C3.75 18.9 3.27917 18.7042 2.8875 18.3125C2.49583 17.9208 2.3 17.45 2.3 16.9V3.725L0 1.4L1.4 0L21.2 19.8L19.775 21.2M4.3 16.9H15.475L13.475 14.9H5.3L8.3 10.9L10.3 13.625L11.15 12.575L4.3 5.725V16.9M20.3 16.075L18.3 14.075V2.9H7.125L5.125 0.9H18.3C18.85 0.9 19.3208 1.09583 19.7125 1.4875C20.1042 1.87917 20.3 2.35 20.3 2.9V16.075" fill="#5F657B"/>
                                        </svg>
                                    </div>
                                    <div class="archived-title">Preview unavailable</div>
                                    <div class="archived-desc">
                                        <?= $vPrev ? 'Previous artwork file archived in compliance record repository.' : 'No previous version has been uploaded for this asset yet.' ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Footer Metadata & Inactive/Active Button -->
                            <div class="artwork-card-footer">
                                <div class="artwork-file-info">
                                    <span class="artwork-file-name"><?= $vPrev ? esc_html($vPrev['file_name'] ?: 'File archived') : '—' ?></span>
                                    <span class="artwork-file-meta"><?= $vPrev ? esc_html($vPrev['file_format'] . ' · Submitted ' . $vPrev['submitted']) : 'No submission' ?></span>
                                </div>
                                <?php if ($vPrev && !empty($vPrev['file_url'])): ?>
                                    <button type="button" class="btn-view-full active-btn" onclick="openLightbox('<?= esc_url($vPrev['file_url']) ?>', '<?= esc_attr($vPrev['label']) ?> · <?= esc_attr($vPrev['file_name']) ?>')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <span>View Full Size</span>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-view-full inactive-btn" disabled>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <span>View Full Size</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- RIGHT: Current Artwork Container -->
                        <div class="artwork-card">
                            <div class="artwork-card-header">
                                <div class="card-header-left">
                                    <span class="version-title"><?= $vCur ? esc_html($vCur['label']) : '—' ?></span>
                                    <?php if ($vCur): ?>
                                        <span class="status-badge-pill badge-<?= sanitize_html_class($vCur['status']) ?>">
                                            <?= esc_html($vCur['status_label']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="card-header-label">Current Submitted Artwork</span>
                            </div>

                            <!-- Preview Viewport -->
                            <?php if ($vCur && !empty($vCur['file_url'])): ?>
                                <?php $isCurPdf = preg_match('/\.pdf(\?.*)?$/i', $vCur['file_url']); ?>
                                <div class="artwork-preview-box">
                                    <?php if ($isCurPdf): ?>
                                        <iframe src="<?= esc_url($vCur['file_url']) ?>#toolbar=0&navpanes=0&view=Fit" style="width: 100%; height: 100%; min-height: 280px; border: none; background: #fff;"></iframe>
                                    <?php else: ?>
                                        <img src="<?= esc_url($vCur['file_url']) ?>" alt="Current Version <?= esc_attr($vCur['label']) ?>" onclick="openLightbox('<?= esc_url($vCur['file_url']) ?>', '<?= esc_attr($vCur['label']) ?> · <?= esc_attr($vCur['file_name']) ?>')">
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="artwork-preview-box archived-placeholder">
                                    <div class="archived-icon-wrap">
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21 15 16 10 5 21"/>
                                        </svg>
                                    </div>
                                    <div class="archived-title">No visual file attached</div>
                                    <div class="archived-desc">Upload a revision in Maker Workspace to render artwork comparison.</div>
                                </div>
                            <?php endif; ?>

                            <!-- Footer Metadata & Active Button -->
                            <div class="artwork-card-footer">
                                <div class="artwork-file-info">
                                    <span class="artwork-file-name"><?= $vCur ? esc_html($vCur['file_name'] ?: 'File attached') : '—' ?></span>
                                    <span class="artwork-file-meta"><?= $vCur ? esc_html($vCur['file_format'] . ' · Submitted ' . $vCur['submitted']) : 'No submission' ?></span>
                                </div>
                                <?php if ($vCur && !empty($vCur['file_url'])): ?>
                                    <button type="button" class="btn-view-full active-btn" onclick="openLightbox('<?= esc_url($vCur['file_url']) ?>', '<?= esc_attr($vCur['label']) ?> · <?= esc_attr($vCur['file_name']) ?>')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <span>View Full Size</span>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-view-full inactive-btn" disabled>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <span>View Full Size</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 4. Section: AI Compliance Criteria Diff / Comparison Matrix -->
                <section class="criteria-diff-section">
                    <div class="section-header-row">
                        <div>
                            <h2>AI Compliance Criteria & Rule Diff</h2>
                            <p style="font-size: 13px; color: #5F657B; margin-top: 2px;">
                                Side-by-side compliance audit comparing automated pre-flight checks between versions.
                            </p>
                        </div>
                    </div>

                    <div class="matrix-card">
                        <?php if (!empty($criteriaDiff)): ?>
                            <table class="matrix-table">
                                <thead>
                                    <tr>
                                        <th style="width: 28%;">Compliance Criterion & Rule</th>
                                        <th style="width: 18%;">Previous (<?= $vPrev ? esc_html($vPrev['label']) : 'V_prev' ?>)</th>
                                        <th style="width: 18%;">Current (<?= $vCur ? esc_html($vCur['label']) : 'V_cur' ?>)</th>
                                        <th style="width: 16%;">Audit Delta</th>
                                        <th style="width: 20%;">Key Observation</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($criteriaDiff as $crit): ?>
                                        <tr>
                                            <td>
                                                <div class="criterion-name-col">
                                                    <span><?= esc_html($crit['name']) ?></span>
                                                    <span class="criterion-rule-code"><?= esc_html($crit['rule']) ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($crit['prev']['verdict'] !== '—'): ?>
                                                    <span class="verdict-tag <?= strtolower(str_replace(' ', '-', $crit['prev']['verdict'])) ?>">
                                                        <?= esc_html($crit['prev']['verdict']) ?>
                                                        <?= $crit['prev']['score'] !== null ? ' (' . (int)$crit['prev']['score'] . '%)' : '' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="verdict-tag none">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($crit['cur']['verdict'] !== '—'): ?>
                                                    <span class="verdict-tag <?= strtolower(str_replace(' ', '-', $crit['cur']['verdict'])) ?>">
                                                        <?= esc_html($crit['cur']['verdict']) ?>
                                                        <?= $crit['cur']['score'] !== null ? ' (' . (int)$crit['cur']['score'] . '%)' : '' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="verdict-tag none">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="delta-pill <?= sanitize_html_class($crit['delta_class']) ?>">
                                                    <?= esc_html($crit['delta_label']) ?>
                                                </span>
                                            </td>
                                            <td style="font-size: 12.5px; color: #475569;">
                                                <?= esc_html($crit['cur']['obs'] ?: ($crit['prev']['obs'] ?: '—')) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="empty-diff-state">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <div class="empty-diff-title">No AI Pre-flight Criteria Records Found</div>
                                <p class="empty-diff-desc">
                                    No automated pre-flight checks have been recorded for these versions yet in the WordPress database.
                                    When an AI scan runs, criterion scores and delta comparisons will populate here.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

            <?php endif; ?>

        </main>
    </div>

    <!-- ================= 8. LIGHTBOX MODAL ================= -->
    <div class="lightbox-modal" id="artworkLightbox" onclick="closeLightbox(event)">
        <div class="lightbox-content">
            <button type="button" class="lightbox-close-btn" onclick="closeLightboxDirect()">&times;</button>
            <img src="" alt="Full Artwork Preview" class="lightbox-img" id="lightboxImg">
            <iframe id="lightboxPdf" src="" style="display: none; width: 85vw; height: 75vh; border: none; border-radius: 6px; background: #fff;"></iframe>
            <div class="lightbox-caption" id="lightboxCaption"></div>
            <div id="lightboxPdfLinkWrap" style="display: none; margin-top: 8px; text-align: center;">
                <a id="lightboxPdfLink" href="#" target="_blank" rel="noopener noreferrer" style="color: #FFFFFF; font-weight: 700; text-decoration: underline; font-size: 13px;">Open PDF in new window ↗</a>
            </div>
        </div>
    </div>

    <!-- ================= 9. INTERACTION JAVASCRIPT ================= -->
    <script>
        function changeVersionComparison() {
            const vPrevSelect = document.getElementById('selectVPrev');
            const vCurSelect = document.getElementById('selectVCur');
            const assetId = '<?= (int) $assetId ?>';
            const campaignId = '<?= (int) $campaignId ?>';
            const from = '<?= esc_js($from) ?>';

            const vPrev = vPrevSelect ? vPrevSelect.value : '';
            const vCur = vCurSelect ? vCurSelect.value : '';

            let url = window.location.pathname + '?asset_id=' + encodeURIComponent(assetId);
            if (vPrev) url += '&v_prev=' + encodeURIComponent(vPrev);
            if (vCur) url += '&v_cur=' + encodeURIComponent(vCur);
            if (campaignId && campaignId !== '0') url += '&campaign_id=' + encodeURIComponent(campaignId);
            if (from) url += '&from=' + encodeURIComponent(from);

            window.location.href = url;
        }

        function openLightbox(fileUrl, caption) {
            const modal = document.getElementById('artworkLightbox');
            const img = document.getElementById('lightboxImg');
            const pdf = document.getElementById('lightboxPdf');
            const cap = document.getElementById('lightboxCaption');
            const pdfLinkWrap = document.getElementById('lightboxPdfLinkWrap');
            const pdfLink = document.getElementById('lightboxPdfLink');
            if (!modal) return;

            const isPdf = /\.pdf(\?.*)?$/i.test(fileUrl);
            if (isPdf) {
                if (img) { img.style.display = 'none'; img.src = ''; }
                if (pdf) {
                    pdf.style.display = 'block';
                    pdf.src = fileUrl + '#toolbar=1&view=FitH';
                }
                if (pdfLinkWrap && pdfLink) {
                    pdfLinkWrap.style.display = 'block';
                    pdfLink.href = fileUrl;
                }
            } else {
                if (pdf) { pdf.style.display = 'none'; pdf.src = ''; }
                if (pdfLinkWrap) { pdfLinkWrap.style.display = 'none'; }
                if (img) {
                    img.style.display = 'block';
                    img.src = fileUrl;
                }
            }

            if (cap) cap.innerText = caption || '';
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLightboxDirect() {
            const modal = document.getElementById('artworkLightbox');
            if (modal) modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        function closeLightbox(e) {
            if (e.target.id === 'artworkLightbox') {
                closeLightboxDirect();
            }
        }

        // Close on ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightboxDirect();
            }
        });
    </script>
    <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body>
</html>

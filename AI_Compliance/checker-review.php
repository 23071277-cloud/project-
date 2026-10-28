<?php
/**
 * Template Name: Checker Review Asset
 * Description: Realtime Checker Review Screen with Interactive Evidence Lens, AI Pre-flight Assessment, and Audit Trail.
 * 100% Dynamic Database Integration: asset_version, asset, campaign, check_result, audit_log, wp_users
 */

if (!defined('ABSPATH') && session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// 1. TỰ ĐỘNG NẠP BACKEND ENGINE
if (file_exists(__DIR__ . '/backend.php')) {
    require_once __DIR__ . '/backend.php';
} elseif (file_exists(__DIR__ . '/inc/backend.php')) {
    require_once __DIR__ . '/inc/backend.php';
} elseif (function_exists('get_template_directory') && file_exists(get_template_directory() . '/backend.php')) {
    require_once get_template_directory() . '/backend.php';
} elseif (function_exists('get_template_directory') && file_exists(get_template_directory() . '/inc/backend.php')) {
    require_once get_template_directory() . '/inc/backend.php';
}

// 2. XỬ LÝ LOGOUT
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

// 3. XỬ LÝ QUYẾT ĐỊNH DUYỆT (APPROVE / REJECT) CỦA CHECKER
$decisionAlert = '';
$decisionSuccess = false;
$reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($reqMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checker_decision') {
    $version_id = (int) ($_POST['version_id'] ?? 0);
    $decision = sanitize_text_field($_POST['decision'] ?? '');
    $review_note = sanitize_text_field($_POST['review_note'] ?? '');

    if (function_exists('handle_checker_decision')) {
        $res = handle_checker_decision($version_id, $decision, $review_note);
        $decisionAlert = $res['message'];
        $decisionSuccess = $res['success'];
    }
}

// 4. XỬ LÝ CHECKER OVERRIDE TIÊU CHÍ AI
if ($reqMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checker_override') {
    $result_id = (int) ($_POST['result_id'] ?? 0);
    $new_verdict = sanitize_text_field($_POST['new_verdict'] ?? '');
    $override_reason = sanitize_text_field($_POST['override_reason'] ?? '');

    if (function_exists('handle_checker_override')) {
        $res = handle_checker_override($result_id, $new_verdict, $override_reason);
        $decisionAlert = $res['message'];
        $decisionSuccess = $res['success'];
    }
}

// 5. TRUY VẤN DỮ LIỆU ĐỘNG TỪ WORDPRESS DATABASE
$target_ver = (int) ($_GET['version_id'] ?? 0);
$target_ast = (int) ($_GET['asset_id'] ?? 0);
$target_cmp = (int) ($_GET['campaign_id'] ?? 0);

$reviewData = function_exists('get_asset_review_screen_data')
    ? get_asset_review_screen_data($target_ver, $target_ast, $target_cmp)
    : [];

$currentUser = function_exists('pcs_enforce_rbac')
    ? pcs_enforce_rbac(['CHECKER'])
    : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'CHECKER');

// Navigation URLs
$checkerDashUrl  = function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php';
$detailAssetUrl  = function_exists('pcs_url') ? pcs_url('detail-asset-checker.php') : 'detail-asset-checker.php';
$homeUrl         = function_exists('pcs_url') ? pcs_url('home-page.php') : 'home-page.php';

// Safe variables strictly from Database
$campaignName    = !empty($reviewData['campaign_name']) ? $reviewData['campaign_name'] : 'No Campaign';
$assetName       = !empty($reviewData['asset_name']) ? $reviewData['asset_name'] : 'No Asset';
$versionLabel    = !empty($reviewData['version_label']) ? $reviewData['version_label'] : 'V1';
$status          = $reviewData['status'] ?? 'in_review';
$statusLabel     = !empty($reviewData['status_label']) ? $reviewData['status_label'] : 'In Review';
$priority        = !empty($reviewData['priority']) ? $reviewData['priority'] : 'Normal';
$submittedAt     = !empty($reviewData['submitted_at']) ? $reviewData['submitted_at'] : 'N/A';
$deadline        = !empty($reviewData['deadline']) ? $reviewData['deadline'] : 'No deadline';
$makerName       = !empty($reviewData['maker_name']) ? $reviewData['maker_name'] : 'Unassigned';
$fileUrl         = $reviewData['file_url'] ?? '';
$overallVerdict  = !empty($reviewData['overall_verdict']) ? $reviewData['overall_verdict'] : 'PENDING';
$overallScore    = $reviewData['overall_score'] ?? 0;
$markerCount     = count($reviewData['markers'] ?? []);
$checks          = $reviewData['checks'] ?? [];
$markers         = $reviewData['markers'] ?? [];
$prevVersions    = $reviewData['previous_versions'] ?? [];
$auditLogs       = $reviewData['audit_logs'] ?? [];

$curCmpId = (int) ($reviewData['campaign_id'] ?? $target_cmp);
$curAstId = (int) ($reviewData['asset_id'] ?? $target_ast);
$curVerId = (int) ($reviewData['version_id'] ?? $target_ver);
$auditScreenUrl = (function_exists('pcs_url') ? pcs_url('audit-log.php') : 'audit-log.php') . '?' . http_build_query([
    'campaign_id' => $curCmpId,
    'asset_id'    => $curAstId,
    'version_id'  => $curVerId,
    'from'        => 'checker_review'
]);

$compareScreenUrl = (function_exists('pcs_url') ? pcs_url('compare-version.php') : 'compare-version.php') . '?' . http_build_query([
    'campaign_id' => $curCmpId,
    'asset_id'    => $curAstId,
    'v_cur'       => $curVerId,
    'from'        => 'checker_review'
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Review Asset: <?= htmlspecialchars($assetName) ?> (<?= htmlspecialchars($versionLabel) ?>) | Checker Workspace</title>
    
    <!-- Google Fonts Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ================= 1. GLOBALS & RESETS ================= */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        html, body {
            margin: 0;
            min-height: 100%;
            background: linear-gradient(0deg, #FAF8FF 0%, #FAF8FF 100%), #FFFFFF;
            font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #161B2E;
            line-height: 1.5;
        }
        a {
            text-decoration: none;
            color: inherit;
        }
        button {
            font-family: inherit;
            cursor: pointer;
            border: none;
            background: none;
        }

        /* ================= 2. TOP NAVBAR (MATCHING DESIGN SYSTEM) ================= */
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            position: sticky;
            top: 0;
            width: 100%;
            height: 72px;
            background-color: #ffffff;
            border-bottom: 1px solid #E2E6FA;
            z-index: 100;
        }
        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 1280px;
            padding: 0 24px;
        }
        .header-left {
            display: flex;
            align-items: center;
            gap: 32px;
        }
        .brand-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 800;
            font-size: 20px;
            color: #1F2A5A;
            letter-spacing: -0.5px;
        }
        .brand-logo-badge {
            background: #1F2674;
            color: #FFFFFF;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 800;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .nav-item {
            font-size: 14px;
            font-weight: 500;
            color: #5F657B;
            transition: color 0.15s;
        }
        .nav-item:hover {
            color: #1F2A5A;
        }
        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .btn-back-queue {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            background: #F3F5FD;
            color: #4F56A6;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.15s;
        }
        .btn-back-queue:hover {
            background: #E8EBFD;
            color: #1F2674;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 8px;
            position: relative;
        }
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #39428E;
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
        }
        .user-meta {
            display: flex;
            flex-direction: column;
            text-align: left;
        }
        .user-name {
            font-size: 13px;
            font-weight: 700;
            color: #161B2E;
            line-height: 1.2;
        }
        .user-role-badge {
            font-size: 10px;
            font-weight: 700;
            color: #4F56A6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 8px;
            background: #FFFFFF;
            border: 1px solid #E2E6FA;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            min-width: 190px;
            padding: 8px;
            display: none;
            flex-direction: column;
            gap: 4px;
            z-index: 200;
        }
        .profile-dropdown.show {
            display: flex;
        }
        .profile-dropdown a {
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 500;
            color: #161B2E;
            border-radius: 6px;
            transition: background 0.15s;
        }
        .profile-dropdown a:hover {
            background: #F3F5FD;
        }

        /* ================= 3. MAIN WRAPPER ================= */
        .main-wrapper {
            width: 100%;
            max-width: 1280px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
        }

        /* Toast Alert */
        .toast-banner {
            margin: 20px 24px 0 24px;
            padding: 12px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        .toast-banner.success {
            background-color: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        .toast-banner.danger {
            background-color: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        /* ================= 4. SECTION HERO HEADER ================= */
        .section-hero-header {
            padding: 28px 24px 24px 24px;
            background: #FAF8FF;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 1px solid transparent;
        }
        .hero-title-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .hero-workspace-tag {
            color: #5F657B;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 16.5px;
            letter-spacing: 0.55px;
        }
        .hero-heading-row {
            display: flex;
            align-items: baseline;
            gap: 12px;
            flex-wrap: wrap;
        }
        .hero-heading-row h1 {
            color: #1F2A5A;
            font-size: 28px;
            font-weight: 800;
            line-height: 38px;
            letter-spacing: -0.5px;
        }
        .hero-subtitle {
            color: #5F657B;
            font-size: 16px;
            font-weight: 400;
            line-height: 24px;
        }
        .hero-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Status Pills */
        .pill-in-review {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: #ECEEFA;
            border-radius: 9999px;
            color: #4E56A6;
            font-size: 13px;
            font-weight: 600;
        }
        .pill-in-review .dot {
            width: 8px;
            height: 8px;
            background: #4E56A6;
            border-radius: 50%;
        }
        .pill-approved {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: #ECFDF5;
            border-radius: 9999px;
            color: #065F46;
            font-size: 13px;
            font-weight: 600;
        }
        .pill-approved .dot {
            width: 8px;
            height: 8px;
            background: #10B981;
            border-radius: 50%;
        }
        .pill-rejected {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: #FFE4E6;
            border-radius: 9999px;
            color: #9F1239;
            font-size: 13px;
            font-weight: 600;
        }
        .pill-rejected .dot {
            width: 8px;
            height: 8px;
            background: #F43F5E;
            border-radius: 50%;
        }
        .pill-priority {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            background: #FEF6E8;
            border-radius: 9999px;
            color: #B46505;
            font-size: 13px;
            font-weight: 600;
        }
        .pill-priority .dot {
            width: 8px;
            height: 8px;
            background: #B46505;
            border-radius: 50%;
        }
        .hero-divider {
            width: 1px;
            height: 20px;
            background: #E2E6FA;
            margin: 0 4px;
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 16px;
            background: #FFFFFF;
            border-radius: 8px;
            border: 1px solid #E2E6FA;
            color: #161B2E;
            font-size: 13px;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            transition: all 0.15s;
        }
        .btn-secondary:hover {
            background: #F8FAFD;
            border-color: #CBD5E1;
        }

        /* ================= 5. VERSION CONTEXT STRIP ================= */
        .section-version-strip {
            width: 100%;
            padding: 15px 24px 16px 24px;
            background: #F3F5FD;
            border-top: 1px solid #E2E6FA;
            border-bottom: 1px solid #E2E6FA;
        }
        .version-strip-grid {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 24px;
            align-items: center;
        }
        .context-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .context-label {
            color: #5F657B;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 16.5px;
            letter-spacing: 0.55px;
        }
        .context-value {
            color: #161B2E;
            font-size: 13px;
            font-weight: 600;
            line-height: 19.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .context-value.highlight-version {
            color: #1F2A5A;
            font-weight: 800;
        }

        /* ================= 6. MAIN 12-COLUMN REVIEW WORKSPACE ================= */
        .section-review-workspace {
            padding: 32px 24px 48px 24px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
            align-items: start;
        }

        /* ================= 7. LEFT COLUMN: ARTWORK AREA ================= */
        .left-column-artwork {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .artwork-card {
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid #E2E6FA;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .artwork-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .artwork-title-group h3 {
            color: #161B2E;
            font-size: 18px;
            font-weight: 700;
            line-height: 24px;
        }
        .artwork-title-group p {
            color: #464651;
            font-size: 12px;
            font-weight: 400;
            line-height: 16px;
        }
        .badge-marker-detected {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: #E6FAF7;
            border: 1px solid #BAF3EC;
            border-radius: 9999px;
            color: #0E7467;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }
        .badge-marker-detected .marker-dot {
            width: 8px;
            height: 8px;
            background: #0D9488;
            border-radius: 50%;
        }

        /* Interactive Canvas */
        .canvas-container {
            position: relative;
            background: #E4E7FF;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 380px;
            max-height: 520px;
        }
        .canvas-image {
            width: 100%;
            height: auto;
            max-height: 480px;
            object-fit: contain;
            display: block;
            border-radius: 8px;
            transition: transform 0.2s;
        }

        /* Interactive Evidence Overlay Markers */
        .evidence-overlay-box {
            position: absolute;
            border: 2px dashed #EF4444;
            background: rgba(239, 68, 68, 0.15);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            z-index: 10;
        }
        .evidence-overlay-box:hover {
            background: rgba(239, 68, 68, 0.3);
            border-color: #DC2626;
            box-shadow: 0 0 12px rgba(220, 38, 38, 0.4);
        }
        .evidence-overlay-box.pass-box {
            border-color: #10B981;
            background: rgba(16, 185, 129, 0.12);
        }
        .evidence-overlay-box.pass-box:hover {
            background: rgba(16, 185, 129, 0.25);
            border-color: #059669;
        }
        .overlay-tooltip {
            position: absolute;
            bottom: 105%;
            left: 50%;
            transform: translateX(-50%);
            background: #1E293B;
            color: #FFFFFF;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            white-space: nowrap;
            pointer-events: none;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        }

        /* Canvas Toolbar Controls */
        .canvas-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #F8FAFD;
            padding: 10px 16px;
            border-radius: 8px;
            border: 1px solid #E2E6FA;
        }
        .canvas-toolbar-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tool-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            transition: all 0.15s;
        }
        .tool-btn:hover {
            background: #F1F5F9;
            color: #0F172A;
        }
        .tool-btn.active {
            background: #1F2674;
            color: #FFFFFF;
            border-color: #1F2674;
        }

        /* Artwork Specs Footer */
        .artwork-specs-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 12px;
            border-top: 1px solid #F1F5F9;
            font-size: 12px;
            color: #64748B;
        }

        /* ================= 8. RIGHT COLUMN: AI PRE-FLIGHT & ACTIONS ================= */
        .right-column-assessment {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Assessment Card */
        .assessment-card {
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid #E2E6FA;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .assessment-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 16px;
            border-bottom: 1px solid #F1F5F9;
        }
        .assessment-title-group h2 {
            color: #161B2E;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .assessment-title-group span {
            color: #64748B;
            font-size: 12px;
        }
        .verdict-tag {
            padding: 6px 16px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .verdict-tag.verdict-fail {
            background: #FFE4E6;
            color: #BE123C;
            border: 1px solid #FECDD3;
        }
        .verdict-tag.verdict-pass {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        .verdict-tag.verdict-needs_look, .verdict-tag.verdict-needs-human-review {
            background: #FEF6E8;
            color: #B46505;
            border: 1px solid #FDE68A;
        }

        /* Criteria List */
        .criteria-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .criterion-card {
            border: 1px solid #E2E6FA;
            border-radius: 10px;
            padding: 16px;
            background: #FFFFFF;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: border-color 0.15s;
        }
        .criterion-card:hover {
            border-color: #CBD5E1;
        }
        .criterion-card.fail-card {
            border-left: 4px solid #F43F5E;
            background: #FFFBFB;
        }
        .criterion-card.pass-card {
            border-left: 4px solid #10B981;
        }
        .criterion-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .criterion-title-wrap {
            display: flex;
            flex-direction: column;
        }
        .criterion-name {
            font-size: 14px;
            font-weight: 700;
            color: #161B2E;
        }
        .criterion-rule-code {
            font-size: 11px;
            font-family: "JetBrains Mono", monospace;
            color: #64748B;
        }
        .score-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            font-family: "JetBrains Mono", monospace;
        }
        .score-pill.pass {
            background: #E8F7F3;
            color: #059669;
        }
        .score-pill.fail {
            background: #FFE4E6;
            color: #E11D48;
        }
        .score-pill.needs_look {
            background: #FEF6E8;
            color: #D97706;
        }
        .criterion-body {
            font-size: 12.5px;
            color: #475569;
            line-height: 1.6;
        }
        .criterion-quote {
            background: #F8FAFD;
            border-left: 2px solid #CBD5E1;
            padding: 8px 12px;
            border-radius: 0 6px 6px 0;
            font-size: 12px;
            color: #334155;
            font-style: italic;
            margin-top: 4px;
        }
        .criterion-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 8px;
            border-top: 1px dashed #E2E6FA;
            font-size: 11.5px;
        }
        .btn-override {
            padding: 4px 10px;
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-override:hover {
            background: #E2E8F0;
            color: #1E293B;
        }

        /* ================= 9. CHECKER NOTES & ACTION CARD ================= */
        .decision-card {
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid #E2E6FA;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .decision-card h3 {
            font-size: 16px;
            font-weight: 800;
            color: #161B2E;
        }
        .decision-card p {
            font-size: 12.5px;
            color: #64748B;
            margin-top: -8px;
        }
        .feedback-textarea {
            width: 100%;
            height: 120px;
            padding: 12px 16px;
            border-radius: 8px;
            border: 1px solid #CBD5E1;
            font-family: inherit;
            font-size: 13.5px;
            line-height: 1.5;
            color: #1E293B;
            resize: vertical;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .feedback-textarea:focus {
            border-color: #1F2674;
            box-shadow: 0 0 0 3px rgba(31, 38, 116, 0.12);
        }
        .decision-btn-row {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 6px;
        }
        .btn-reject {
            padding: 12px 24px;
            background: #FCA5A5;
            color: #7F1D1D;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.15s;
        }
        .btn-reject:hover {
            background: #F87171;
            color: #FFFFFF;
            box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
        }
        .btn-approve {
            padding: 12px 28px;
            background: #0D9488;
            color: #FFFFFF;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 1px 3px rgba(13, 148, 136, 0.2);
            transition: all 0.15s;
        }
        .btn-approve:hover {
            background: #0F766E;
            box-shadow: 0 2px 10px rgba(13, 148, 136, 0.35);
        }

        /* ================= 10. MODALS (COMPARE VERSIONS & AUDIT LOG & OVERRIDE) ================= */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.55);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-container {
            background: #FFFFFF;
            border-radius: 14px;
            width: 100%;
            max-width: 820px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 {
            font-size: 18px;
            font-weight: 800;
            color: #1F2A5A;
        }
        .modal-close-btn {
            font-size: 22px;
            color: #94A3B8;
            cursor: pointer;
            padding: 4px;
            line-height: 1;
        }
        .modal-close-btn:hover {
            color: #0F172A;
        }
        .modal-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Compare versions table */
        .compare-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .compare-card {
            border: 1px solid #E2E6FA;
            border-radius: 10px;
            padding: 16px;
            background: #F8FAFD;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .compare-card.current-compare {
            border: 2px solid #1F2674;
            background: #FFFFFF;
        }
        .compare-img-box {
            width: 100%;
            height: 180px;
            background: #E4E7FF;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .compare-img-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        /* Audit Timeline */
        .audit-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .audit-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding-bottom: 14px;
            border-bottom: 1px solid #F1F5F9;
        }
        .audit-dot {
            width: 10px;
            height: 10px;
            background: #1F2674;
            border-radius: 50%;
            margin-top: 6px;
            flex-shrink: 0;
        }
        .audit-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .audit-action {
            font-size: 13.5px;
            font-weight: 700;
            color: #161B2E;
        }
        .audit-meta {
            font-size: 12px;
            color: #64748B;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .section-review-workspace {
                grid-template-columns: 1fr;
            }
            .version-strip-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        @media (max-width: 600px) {
            .version-strip-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .section-hero-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
        }
    </style>
    <?php if (function_exists("wp_head")) wp_head(); ?>
</head>
<body>

    <!-- ================= 1. HEADER (100% UNIFIED TOP NAVBAR) ================= -->
    <?php 
    if (function_exists('pcs_render_top_navbar')) {
        pcs_render_top_navbar([
            'user'              => $currentUser,
            'all_campaigns_url' => $checkerDashUrl,
            'btn_text'          => 'All Campaigns'
        ]);
    } else {
        @include __DIR__ . '/inc/navbar.php';
    }
    ?>

    <!-- ================= 2. MAIN WRAPPER ================= -->
    <main class="main-wrapper">

        <?php if (!empty($decisionAlert)): ?>
            <div class="toast-banner <?= $decisionSuccess ? 'success' : 'danger' ?>">
                <span><?= htmlspecialchars($decisionAlert) ?></span>
            </div>
        <?php endif; ?>

        <?php if (empty($reviewData['version_id'])): ?>
            <div style="max-width: 680px; margin: 60px auto; background: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0; padding: 48px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <div style="width: 56px; height: 56px; border-radius: 50%; background: #EEF2FF; color: #4F46E5; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 16px;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                </div>
                <h2 style="font-size: 20px; font-weight: 700; color: #1E293B; margin-bottom: 8px;">No Asset Version Available for Review</h2>
                <p style="color: #64748B; font-size: 14px; margin-bottom: 24px; line-height: 1.6;">
                    There are currently no asset versions queued for review in this campaign. Once a Maker uploads artwork, it will appear here automatically.
                </p>
                <div style="display: flex; gap: 12px; justify-content: center;">
                    <a href="<?= esc_url($checkerDashUrl) ?>" style="padding: 10px 20px; background: #1F2A5A; color: #FFFFFF; font-weight: 600; border-radius: 8px; font-size: 14px; text-decoration: none;">
                        Return to Checker Dashboard
                    </a>
                    <a href="<?= esc_url($detailAssetUrl) ?>" style="padding: 10px 20px; background: #EEF2FF; color: #1F2A5A; font-weight: 600; border-radius: 8px; font-size: 14px; text-decoration: none;">
                        View Campaign Assets
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- ================= SECTION: HERO HEADER ================= -->
            <section class="section-hero-header">
                <div class="hero-title-group">
                    <span class="hero-workspace-tag">CHECKER WORKSPACE</span>
                    <div class="hero-heading-row">
                        <h1>Review Asset</h1>
                        <span class="hero-subtitle"><?= htmlspecialchars($assetName) ?> · <?= htmlspecialchars($versionLabel) ?> · <?= htmlspecialchars($campaignName) ?></span>
                    </div>
                </div>
                <div class="hero-actions">
                    <?php if ($status === 'approved' || $status === 'released'): ?>
                        <div class="pill-approved"><span class="dot"></span><span>Approved</span></div>
                    <?php elseif ($status === 'rejected'): ?>
                        <div class="pill-rejected"><span class="dot"></span><span>Rejected</span></div>
                    <?php else: ?>
                        <div class="pill-in-review"><span class="dot"></span><span>In Review</span></div>
                    <?php endif; ?>

                    <div class="pill-priority"><span class="dot"></span><span><?= htmlspecialchars($priority) ?></span></div>
                    <div class="hero-divider"></div>
                    <a href="<?= esc_url($compareScreenUrl) ?>" class="btn-secondary" style="text-decoration: none;">Compare Versions</a>
                    <a href="<?= esc_url($auditScreenUrl) ?>" class="btn-secondary" style="text-decoration: none;">View Audit</a>
                </div>
            </section>

            <!-- ================= SECTION: VERSION CONTEXT STRIP ================= -->
            <section class="section-version-strip">
                <div class="version-strip-grid">
                    <div class="context-item">
                        <span class="context-label">CAMPAIGN</span>
                        <span class="context-value" title="<?= htmlspecialchars($campaignName) ?>"><?= htmlspecialchars($campaignName) ?></span>
                    </div>
                    <div class="context-item">
                        <span class="context-label">ASSET</span>
                        <span class="context-value" title="<?= htmlspecialchars($assetName) ?>"><?= htmlspecialchars($assetName) ?></span>
                    </div>
                    <div class="context-item">
                        <span class="context-label">VERSION</span>
                        <span class="context-value highlight-version"><?= htmlspecialchars($versionLabel) ?></span>
                    </div>
                    <div class="context-item">
                        <span class="context-label">MAKER</span>
                        <span class="context-value"><?= htmlspecialchars($makerName) ?></span>
                    </div>
                    <div class="context-item">
                        <span class="context-label">SUBMITTED</span>
                        <span class="context-value"><?= htmlspecialchars($submittedAt) ?></span>
                    </div>
                    <div class="context-item">
                        <span class="context-label">DEADLINE</span>
                        <span class="context-value"><?= htmlspecialchars($deadline) ?></span>
                    </div>
                </div>
            </section>

            <!-- ================= SECTION: MAIN 12-COLUMN REVIEW WORKSPACE ================= -->
            <section class="section-review-workspace">

                <!-- LEFT COLUMN: ARTWORK AREA (6 COLS) -->
                <div class="left-column-artwork">
                    <div class="artwork-card">
                        <div class="artwork-header">
                            <div class="artwork-title-group">
                                <h3>Artwork</h3>
                                <p><?= htmlspecialchars($versionLabel) ?> · Submitted Artwork & Evidence Lens</p>
                            </div>
                            <div class="badge-marker-detected" id="markerBadge">
                                <span class="marker-dot"></span>
                                <span><?= (int) $markerCount ?> Markers Detected</span>
                            </div>
                        </div>

                        <!-- Signature Canvas with Interactive Evidence Overlays -->
                        <div class="canvas-container" id="canvasContainer">
                            <?php if (!empty($fileUrl)): ?>
                                <?php 
                                    $ext = strtolower(pathinfo(parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
                                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'])): 
                                ?>
                                    <img src="<?= esc_url($fileUrl) ?>" alt="Artwork Preview" class="canvas-image" id="artworkMainImage" />
                                <?php elseif ($ext === 'pdf'): ?>
                                    <iframe src="<?= esc_url($fileUrl) ?>#toolbar=0&navpanes=0&view=Fit" class="canvas-image" id="artworkMainPdf" style="width: 100%; height: 520px; border: none; border-radius: 8px; background: #ffffff;"></iframe>
                                    <div style="margin-top: 8px; text-align: right;">
                                        <a href="<?= esc_url($fileUrl) ?>" target="_blank" rel="noopener noreferrer" style="font-size: 12.5px; font-weight: 700; color: #4F57A7; text-decoration: underline;">Open PDF Full Screen ↗</a>
                                    </div>
                                <?php else: ?>
                                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 320px; color: #4F56A6; gap: 12px;">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                        <span style="font-weight: 700; font-size: 14px;">Document Attached</span>
                                        <a href="<?= esc_url($fileUrl) ?>" target="_blank" style="padding: 6px 14px; background: #1F2A5A; color: #FFF; border-radius: 6px; font-size: 12.5px; font-weight: 600;">Open Document ↗</a>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="width: 100%; height: 340px; background: #F1F4F9; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #64748B; border-radius: 8px; gap: 12px;">
                                    <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                    <p style="font-size: 14px; font-weight: 600; color: #161B2E;">No visual artwork file attached</p>
                                    <p style="font-size: 12.5px; color: #64748B;">Upload an image or document in Maker Workspace to enable visual review.</p>
                                </div>
                            <?php endif; ?>

                            <!-- Interactive Evidence Overlays: strictly dynamic from database -->
                            <?php if (!empty($markers)): ?>
                                <?php foreach ($markers as $idx => $m): 
                                    $mType = strtolower($m['type'] ?? 'fail');
                                    $boxClass = ($mType === 'pass') ? 'evidence-overlay-box pass-box' : 'evidence-overlay-box';
                                    $top = $m['y'] ?? 0;
                                    $left = $m['x'] ?? 0;
                                    $width = $m['w'] ?? 20;
                                    $height = $m['h'] ?? 15;
                                    $label = $m['label'] ?? 'Evidence Marker';
                                ?>
                                    <div class="<?= $boxClass ?>" id="overlayBox<?= $idx ?>" style="top: <?= (float)$top ?>%; left: <?= (float)$left ?>%; width: <?= (float)$width ?>%; height: <?= (float)$height ?>%;">
                                        <span class="overlay-tooltip"><?= htmlspecialchars($label) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Canvas Toolbar -->
                        <div class="canvas-toolbar">
                            <div class="canvas-toolbar-group">
                                <button type="button" class="tool-btn active" id="btnToggleOverlays" onclick="toggleOverlays()">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <span>Evidence Lens: Active</span>
                                </button>
                                <button type="button" class="tool-btn" onclick="zoomArtwork(1.15)">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/></svg>
                                    <span>Zoom</span>
                                </button>
                                <button type="button" class="tool-btn" onclick="resetZoom()">Reset</button>
                            </div>
                            <div class="canvas-toolbar-group">
                                <?php if (!empty($fileUrl)): ?>
                                    <a href="<?= esc_url($fileUrl) ?>" target="_blank" class="tool-btn">Open Original ↗</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Specs Footer -->
                        <div class="artwork-specs-row">
                            <span><strong>Dimensions:</strong> <?= htmlspecialchars($reviewData['file_dimensions'] ?? '1200 x 628 px') ?></span>
                            <span><strong>Format:</strong> <?= htmlspecialchars($reviewData['file_format'] ?? 'PNG Digital Banner') ?></span>
                            <span><strong>Uploaded By:</strong> <?= htmlspecialchars($makerName) ?></span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT COLUMN: AI PRE-FLIGHT ASSESSMENT & CHECKER DECISION (6 COLS) -->
                <div class="right-column-assessment">

                    <!-- 1. AI PRE-FLIGHT ASSESSMENT CARD -->
                    <div class="assessment-card">
                        <div class="assessment-header">
                            <div class="assessment-title-group">
                                <h2>AI Pre-flight Assessment</h2>
                                <span>Autonomous Model Verification · <?= htmlspecialchars($reviewData['model_info'] ?? 'AI Compliance Engine') ?></span>
                            </div>
                            <div>
                                <?php if (strtoupper($overallVerdict) === 'FAIL'): ?>
                                    <span class="verdict-tag verdict-fail">FAIL</span>
                                <?php elseif (strtoupper($overallVerdict) === 'PASS'): ?>
                                    <span class="verdict-tag verdict-pass">PASS</span>
                                <?php else: ?>
                                    <span class="verdict-tag verdict-needs_look">NEEDS REVIEW</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="criteria-list">
                            <?php if (!empty($checks)): ?>
                                <?php foreach ($checks as $chk): 
                                    $cKey   = $chk['key'] ?? 'criterion';
                                    $vUpper = strtoupper($chk['verdict'] ?? 'PASS');
                                    $score  = (int) ($chk['score'] ?? 0);
                                    $isFail = ($vUpper === 'FAIL');
                                    $isPass = ($vUpper === 'PASS');
                                ?>
                                    <div class="criterion-card <?= $isFail ? 'fail-card' : ($isPass ? 'pass-card' : '') ?>" id="criterion-<?= esc_attr($cKey) ?>">
                                        <div class="criterion-top">
                                            <div class="criterion-title-wrap">
                                                <span class="criterion-name"><?= htmlspecialchars($chk['name'] ?? ucwords(str_replace('_', ' ', $cKey))) ?></span>
                                                <span class="criterion-rule-code"><?= htmlspecialchars($chk['rule'] ?? '') ?></span>
                                            </div>
                                            <div class="score-pill <?= strtolower($vUpper) ?>">
                                                <span><?= $score ?>/100</span>
                                                <span>·</span>
                                                <span><?= $vUpper ?></span>
                                            </div>
                                        </div>
                                        <div class="criterion-body">
                                            <p><?= htmlspecialchars($chk['observation'] ?? '') ?></p>
                                            <?php if (!empty($chk['quote'])): ?>
                                                <div class="criterion-quote">"<?= htmlspecialchars($chk['quote']) ?>"</div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="criterion-footer">
                                            <span style="color: <?= $isFail ? '#E11D48' : '#059669' ?>; font-weight: 600;">
                                                <?= $isFail ? 'Non-compliant deviation identified' : 'Verified against Singapore financial rules' ?>
                                            </span>
                                            <button type="button" class="btn-override" onclick="openOverrideModal(<?= (int) ($chk['id'] ?? 0) ?>, '<?= esc_attr($chk['name'] ?? '') ?>', '<?= esc_attr($vUpper) ?>')">
                                                Override Verdict
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="padding: 32px; text-align: center; color: #64748B; background: #F8FAFD; border-radius: 8px;">
                                    <p style="font-weight: 700; font-size: 14px; margin-bottom: 4px; color: #1E293B;">No AI check results recorded</p>
                                    <p style="font-size: 13px;">Automated evaluation is pending or not yet executed for this asset version.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 2. CHECKER NOTES & DECISION FORM -->
                    <div class="decision-card">
                        <h3>Checker Notes & Feedback</h3>
                        <p>Provide compliance feedback, observations, or instructions (Mandatory if rejecting).</p>
                        
                        <form method="POST" id="checkerDecisionForm">
                            <input type="hidden" name="action" value="checker_decision" />
                            <input type="hidden" name="version_id" value="<?= (int) ($reviewData['version_id'] ?? 0) ?>" />
                            <input type="hidden" name="asset_id" value="<?= (int) ($reviewData['asset_id'] ?? 0) ?>" />
                            <input type="hidden" name="campaign_id" value="<?= (int) ($reviewData['campaign_id'] ?? 0) ?>" />

                            <textarea 
                                name="review_note" 
                                id="reviewNoteField" 
                                class="feedback-textarea" 
                                placeholder="Provide compliance feedback, observations, or instructions (Mandatory if rejecting)..."><?= htmlspecialchars($reviewData['review_note'] ?? '') ?></textarea>

                            <div class="decision-btn-row">
                                <button type="submit" name="decision" value="rejected" class="btn-reject" onclick="return confirmReject(event)">
                                    Reject / Request Changes
                                </button>
                                <button type="submit" name="decision" value="approved" class="btn-approve">
                                    Approve Asset
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </section>
        <?php endif; ?>

    </main>

    <!-- ================= MODAL 1: COMPARE VERSIONS ================= -->
    <div class="modal-overlay" id="compareModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Compare Versions · <?= htmlspecialchars($assetName) ?></h3>
                <span class="modal-close-btn" onclick="closeModal('compareModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p style="font-size: 13.5px; color: #475569;">
                    Compare the currently submitted <strong><?= htmlspecialchars($versionLabel) ?></strong> against previous revision rounds to ensure requested modifications were implemented by Maker.
                </p>
                <div class="compare-grid">
                    <!-- Current Version -->
                    <div class="compare-card current-compare">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 800; color: #1F2674;"><?= htmlspecialchars($versionLabel) ?> (Current)</span>
                            <span class="score-pill <?= strtolower($overallVerdict) ?>"><?= $overallScore ?>/100 · <?= $overallVerdict ?></span>
                        </div>
                        <div class="compare-img-box">
                            <?php 
                                $isCurPdf = (!empty($fileUrl) && preg_match('/\.pdf(\?.*)?$/i', $fileUrl));
                                if (!empty($fileUrl) && !$isCurPdf): ?>
                                <img src="<?= esc_url($fileUrl) ?>" alt="Current Version" />
                            <?php elseif ($isCurPdf): ?>
                                <iframe src="<?= esc_url($fileUrl) ?>#toolbar=0&navpanes=0&view=Fit" style="width: 100%; height: 200px; border: none; pointer-events: none; background: #fff;"></iframe>
                            <?php else: ?>
                                <span style="font-size: 12px; color: #64748B;"><?= htmlspecialchars($versionLabel) ?> Submitted Artwork</span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size: 12px; color: #64748B;">
                            <strong>Submitted:</strong> <?= htmlspecialchars($submittedAt) ?><br/>
                            <strong>Status:</strong> <?= htmlspecialchars($statusLabel) ?>
                        </div>
                    </div>

                    <!-- Previous Versions -->
                    <?php if (!empty($prevVersions)): ?>
                        <?php foreach ($prevVersions as $pVer): 
                            $pScore = (int) ($pVer['score'] ?? 0);
                            $pStatus = strtoupper($pVer['status'] ?? 'DRAFT');
                            $pStatusClass = ($pVer['status'] === 'approved') ? 'pass' : (($pVer['status'] === 'rejected') ? 'fail' : 'needs_look');
                            $isPrevPdf = (!empty($pVer['file_url']) && preg_match('/\.pdf(\?.*)?$/i', $pVer['file_url']));
                        ?>
                            <div class="compare-card">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span style="font-weight: 800; color: #64748B;"><?= htmlspecialchars($pVer['label'] ?? 'V1') ?> (Previous)</span>
                                    <span class="score-pill <?= $pStatusClass ?>"><?= $pScore ?>/100 · <?= $pStatus ?></span>
                                </div>
                                <div class="compare-img-box">
                                    <?php if (!empty($pVer['file_url']) && !$isPrevPdf): ?>
                                        <img src="<?= esc_url($pVer['file_url']) ?>" alt="Previous Version" />
                                    <?php elseif ($isPrevPdf): ?>
                                        <iframe src="<?= esc_url($pVer['file_url']) ?>#toolbar=0&navpanes=0&view=Fit" style="width: 100%; height: 200px; border: none; pointer-events: none; background: #fff;"></iframe>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: #64748B;"><?= htmlspecialchars($pVer['label'] ?? 'V1') ?> Artwork</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 12px; color: #64748B;">
                                    <strong>Submitted:</strong> <?= htmlspecialchars($pVer['submitted_at'] ?? 'N/A') ?><br/>
                                    <strong>Feedback:</strong> <em>"<?= htmlspecialchars($pVer['review_note'] ?: 'No notes recorded') ?>"</em>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px; background: #F8FAFD; border-radius: 8px; border: 1px dashed #CBD5E1; color: #64748B; text-align: center;">
                            <p style="font-weight: 700; font-size: 13.5px; color: #1E293B; margin-bottom: 4px;">Initial Version (<?= htmlspecialchars($versionLabel) ?>)</p>
                            <p style="font-size: 12.5px;">No previous versions have been submitted for this asset.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= MODAL 2: AUDIT TRAIL ================= -->
    <div class="modal-overlay" id="auditModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Audit Trail & Decision History</h3>
                <span class="modal-close-btn" onclick="closeModal('auditModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p style="font-size: 13.5px; color: #475569;">
                    Immutable compliance log for <strong><?= htmlspecialchars($assetName) ?></strong> (Asset #<?= (int) ($reviewData['asset_id'] ?? 0) ?>). All actions are permanently recorded for regulatory auditability.
                </p>
                <div class="audit-list">
                    <?php if (!empty($auditLogs)): ?>
                        <?php foreach ($auditLogs as $log): ?>
                            <div class="audit-item">
                                <div class="audit-dot"></div>
                                <div class="audit-info">
                                    <span class="audit-action"><?= htmlspecialchars(strtoupper($log['action'] ?? 'LOG')) ?> — <?= htmlspecialchars($log['note'] ?? '') ?></span>
                                    <span class="audit-meta">By <strong><?= htmlspecialchars($log['user'] ?? 'System') ?></strong> · <?= htmlspecialchars($log['time'] ?? '') ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 24px; text-align: center; color: #94A3B8; font-size: 13px;">
                            No audit records found for this version.
                        </div>
                    <?php endif; ?>
                </div>
                <div style="margin-top: 18px; border-top: 1px solid #E2E8F0; padding-top: 14px; text-align: right;">
                    <a href="<?= esc_url($auditScreenUrl) ?>" class="btn-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <span>Open Dedicated Audit Log Screen →</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- ================= MODAL 3: OVERRIDE CRITERION ================= -->
    <div class="modal-overlay" id="overrideModal">
        <div class="modal-container" style="max-width: 520px;">
            <div class="modal-header">
                <h3>Override AI Check Verdict</h3>
                <span class="modal-close-btn" onclick="closeModal('overrideModal')">&times;</span>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="checker_override" />
                <input type="hidden" name="result_id" id="overrideResultId" value="0" />
                <div class="modal-body">
                    <p style="font-size: 13px; color: #475569;">
                        As an authorized Checker, you may override the AI model's assessment. All overrides are recorded in the audit trail with your justification.
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1E293B;">Target Criterion:</label>
                        <input type="text" id="overrideCriterionName" readonly style="padding: 8px 12px; background: #F1F5F9; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; font-weight: 600;" />
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1E293B;">New Verdict:</label>
                        <select name="new_verdict" style="padding: 8px 12px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13.5px; font-weight: 600;">
                            <option value="PASS">PASS (Approve this rule)</option>
                            <option value="NEEDS LOOK">NEEDS LOOK (Requires attention)</option>
                            <option value="FAIL">FAIL (Rule violation confirmed)</option>
                        </select>
                    </div>
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <label style="font-size: 13px; font-weight: 700; color: #1E293B;">Override Justification Note (Required):</label>
                        <textarea name="override_reason" required style="height: 80px; padding: 10px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px; font-family: inherit;" placeholder="State rationale for overriding automated AI decision..."></textarea>
                    </div>
                </div>
                <div style="padding: 16px 24px; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; gap: 10px; background: #F8FAFD; border-radius: 0 0 14px 14px;">
                    <button type="button" class="btn-secondary" onclick="closeModal('overrideModal')">Cancel</button>
                    <button type="submit" class="btn-approve" style="padding: 8px 20px;">Save Override</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= JAVASCRIPT INTERACTIONS ================= -->
    <script>
        // Toggle user dropdown menu
        function toggleDropdown() {
            var menu = document.getElementById('userMenu');
            if (menu) {
                menu.classList.toggle('show');
            }
        }
        window.onclick = function(e) {
            if (!e.target.closest('.user-profile')) {
                var menu = document.getElementById('userMenu');
                if (menu && menu.classList.contains('show')) {
                    menu.classList.remove('show');
                }
            }
        };

        // Modal Open / Close
        function openModal(modalId) {
            var modal = document.getElementById(modalId);
            if (modal) modal.classList.add('active');
        }
        function closeModal(modalId) {
            var modal = document.getElementById(modalId);
            if (modal) modal.classList.remove('active');
        }

        // Toggle Evidence Lens Overlays
        var overlaysVisible = true;
        function toggleOverlays() {
            overlaysVisible = !overlaysVisible;
            var boxes = document.querySelectorAll('.evidence-overlay-box');
            boxes.forEach(function(b) {
                b.style.display = overlaysVisible ? 'block' : 'none';
            });
            var btn = document.getElementById('btnToggleOverlays');
            if (btn) {
                if (overlaysVisible) {
                    btn.classList.add('active');
                    btn.querySelector('span').textContent = 'Evidence Lens: Active';
                } else {
                    btn.classList.remove('active');
                    btn.querySelector('span').textContent = 'Evidence Lens: Hidden';
                }
            }
        }

        // Highlight matching criterion when overlay box is clicked
        function highlightCriterion(cKey) {
            var card = document.getElementById('criterion-' + cKey);
            if (card) {
                card.scrollIntoView({ behavior: 'smooth', block: 'center' });
                card.style.transition = 'box-shadow 0.3s, border-color 0.3s';
                card.style.boxShadow = '0 0 0 3px #1F2674';
                card.style.borderColor = '#1F2674';
                setTimeout(function() {
                    card.style.boxShadow = '';
                    card.style.borderColor = '';
                }, 1600);
            }
        }

        // Open Override Modal
        function openOverrideModal(resultId, name, verdict) {
            document.getElementById('overrideResultId').value = resultId;
            document.getElementById('overrideCriterionName').value = name;
            openModal('overrideModal');
        }

        // Zoom controls for artwork image
        var currentZoom = 1;
        function zoomArtwork(factor) {
            var img = document.getElementById('artworkMainImage');
            if (img) {
                currentZoom = Math.min(2.5, currentZoom * factor);
                img.style.transform = 'scale(' + currentZoom + ')';
            }
        }
        function resetZoom() {
            var img = document.getElementById('artworkMainImage');
            if (img) {
                currentZoom = 1;
                img.style.transform = 'scale(1)';
            }
        }

        // Validate rejection reason before submitting
        function confirmReject(e) {
            var note = document.getElementById('reviewNoteField').value.trim();
            if (!note) {
                alert('Feedback note is mandatory when rejecting or requesting changes. Please describe the specific required adjustments.');
                document.getElementById('reviewNoteField').focus();
                e.preventDefault();
                return false;
            }
            return true;
        }
    </script>
    <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body>
</html>

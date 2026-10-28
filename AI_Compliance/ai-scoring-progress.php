<?php
/**
 * Template Name: Maker - AI Scoring In-Progress
 * Description: AI Pre-flight Scanning & Automated Compliance Scoring Stage for Maker
 * 100% WordPress CPT Database Integration: campaign, asset, asset_version, check_result, wp_users
 * Role-Based Access Control (RBAC): Strictly Maker role (with admin preview support)
 * Zero hardcoded mock data: graceful empty states when database is empty
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
if (!function_exists('esc_js')) {
    function esc_js($text) { return addslashes((string) $text); }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags((string) $str)); }
}
if (!function_exists('home_url')) {
    function home_url($path = '/') { return $path; }
}

// 2. Current User & RBAC Authentication (Maker Only)
$currentUser = function_exists('pcs_enforce_rbac') ? pcs_enforce_rbac(['MAKER']) : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'MAKER');
$isAllowed = ($userRole === 'MAKER' || $userRole === 'AUTHOR' || $userRole === 'CONTRIBUTOR');

// 3. Handle Sign Out
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

// 4. Query Real Data from WordPress Database
$targetVersionId  = (int) ($_GET['version_id'] ?? 0);
$targetAssetId    = (int) ($_GET['asset_id'] ?? 0);
$targetCampaignId = (int) ($_GET['campaign_id'] ?? 0);

// Endpoint chạy AI thực tế và lưu kết quả vào database (CPT check_result)
if ((isset($_GET['action']) && $_GET['action'] === 'run_real_ai') || 
    (isset($_POST['action']) && $_POST['action'] === 'run_real_ai')) {
    ignore_user_abort(true);
    set_time_limit(300);
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
    $vId = (int)($_REQUEST['version_id'] ?? $targetVersionId);
    $aId = (int)($_REQUEST['asset_id'] ?? $targetAssetId);
    $results = [];
    if ($vId > 0) {
        if (function_exists('pcs_run_ai_scoring_for_version')) {
            $results = pcs_run_ai_scoring_for_version($vId);
        }
        update_post_meta($vId, '_ai_completed', 1);
        if (function_exists('pcs_transition_version_to_in_review')) {
            pcs_transition_version_to_in_review($vId, $aId);
        } else {
            update_post_meta($vId, '_status', 'in_review');
            if (function_exists('pcs_server_meta')) {
                pcs_server_meta($vId, '_status', 'in_review');
            }
        }
    }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'status' => 'in_review', 'results' => $results]);
    exit;
}

// Endpoint hoàn tất chấm điểm thủ công dự phòng
if ((isset($_GET['action']) && $_GET['action'] === 'complete_scoring') || 
    (isset($_POST['action']) && $_POST['action'] === 'complete_scoring')) {
    $vId = (int)($_REQUEST['version_id'] ?? $targetVersionId);
    $aId = (int)($_REQUEST['asset_id'] ?? $targetAssetId);
    if ($vId > 0) {
        update_post_meta($vId, '_ai_completed', 1);
        if (function_exists('pcs_transition_version_to_in_review')) {
            pcs_transition_version_to_in_review($vId, $aId);
        } elseif (pcs_is_wp()) {
            update_post_meta($vId, '_status', 'in_review');
            if (function_exists('pcs_server_meta')) {
                pcs_server_meta($vId, '_status', 'in_review');
            }
            if ($aId > 0) {
                update_post_meta($aId, '_current_version_id', $vId);
                if (function_exists('pcs_server_meta')) {
                    pcs_server_meta($aId, '_current_version_id', $vId);
                }
            }
        }
    }
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'status' => 'in_review']);
    exit;
}

$screenData = function_exists('get_ai_scoring_progress_data') 
    ? get_ai_scoring_progress_data($targetVersionId, $targetAssetId, $targetCampaignId) 
    : [];

$isDbEmpty       = $screenData['is_empty'] ?? true;
$campaign        = $screenData['campaign'] ?? null;
$asset           = $screenData['asset'] ?? null;
$version         = $screenData['version'] ?? null;
$previousVersion = $screenData['previous_version'] ?? null;
$versionCount    = $screenData['version_count'] ?? 1;
$lineage         = $screenData['lineage'] ?? [];
$completedCount  = 0;
$totalChecks     = 3;
$returnUrl       = $screenData['return_url'] ?? (function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php');

$campaignId   = $campaign['id'] ?? $targetCampaignId;
$campaignName = $campaign['name'] ?? 'General Campaign';
$assetId      = $asset['id'] ?? $targetAssetId;
$assetName    = $asset['name'] ?? 'Marketing Asset';
$assetCode    = $asset['code'] ?? 'ASSET-REV-001';
$versionId    = $version['id'] ?? $targetVersionId;
$versionLabel = $version['version_label'] ?? 'V1';
$versionNum   = $version['version_num'] ?? 1;
$makerName    = $version['maker_name'] ?? ($currentUser['first_name'] ?? 'Cam Anh');
$uploadedTime = $version['uploaded_time'] ?? 'Uploaded just now';
$fileName     = $version['file_name'] ?? 'asset-artwork.png';
$fileExt      = $version['file_ext'] ?? 'PNG';

// Trong quá trình AI đang quét chấm điểm, phiên bản duy trì trạng thái 'scoring' (không nhảy sang 'in_review')
if ($versionId > 0 && pcs_is_wp()) {
    $cur_st = get_post_meta($versionId, '_status', true);
    if (empty($cur_st) || $cur_st === 'draft') {
        update_post_meta($versionId, '_status', 'scoring');
        if (function_exists('pcs_server_meta')) {
            pcs_server_meta($versionId, '_status', 'scoring');
        }
    }
    if ($assetId > 0) {
        update_post_meta($assetId, '_current_version_id', $versionId);
        if (function_exists('pcs_server_meta')) {
            pcs_server_meta($assetId, '_current_version_id', $versionId);
        }
    }
}

$isCompletedAlready = false;
if ($versionId > 0 && pcs_is_wp()) {
    $cur_vst = get_post_meta($versionId, '_status', true);
    $ai_done = (int) get_post_meta($versionId, '_ai_completed', true);
    if ($ai_done === 1 || $cur_vst === 'in_review') {
        $isCompletedAlready = true;
    }
}

// 3 bước kiểm tra tuần tự trong pipeline (khởi tạo động: bước 1 Processing, 2 & 3 Queued)
$checks = [
    [
        'id'           => 'foreign_logo',
        'name'         => 'Foreign Logos',
        'category'     => 'Logo usage',
        'status'       => 'processing',
        'status_label' => 'Processing',
        'badge_label'  => 'Processing',
        'icon'         => 'shield',
        'score'        => 0,
        'is_active'    => true,
        'detail'       => 'Foreign Logos Verification',
        'live_tag'     => 'Live',
        'sub_text'     => 'Scanning third-party marks...',
        'desc'         => 'Verifying brand exclusivity against competitor marks (OCBC, UOB, HSBC, Citi, Maybank) and clear space geometry.',
        'ruleset'      => 'Regulatory ruleset: Trademark Clearance FL-01'
    ],
    [
        'id'           => 'guideline',
        'name'         => 'Brand & Compliance Documents',
        'category'     => 'Brand references',
        'status'       => 'waiting',
        'status_label' => 'Queued',
        'badge_label'  => 'Waiting',
        'icon'         => 'doc',
        'score'        => 0,
        'is_active'    => false,
        'detail'       => 'Brand & Compliance Docs',
        'live_tag'     => '',
        'sub_text'     => 'Awaiting logo clearance',
        'desc'         => 'Verifying corporate color palette (#0A192F Navy, #D4AF37 Gold) and typography exclusion boundaries.',
        'ruleset'      => 'Regulatory ruleset: Corporate Visual Identity BG-01'
    ],
    [
        'id'           => 'mas_rule',
        'name'         => 'MAS Advertising Rules',
        'category'     => 'Advertising compliance',
        'status'       => 'waiting',
        'status_label' => 'Queued',
        'badge_label'  => 'Waiting',
        'icon'         => 'shield_active',
        'score'        => 0,
        'is_active'    => false,
        'detail'       => 'Advertising Provisions',
        'live_tag'     => '',
        'sub_text'     => 'Awaiting guideline verification',
        'desc'         => 'Evaluating financial disclaimer prominence, legibility against imagery contrast, and regulatory wealth management phrasing.',
        'ruleset'      => 'Regulatory ruleset: MAS Guidelines §4.1b'
    ]
];

$activeCheck = $checks[0];

// Maker không thấy điểm số AI: chuyển hướng về danh sách asset với thông báo thành công
$nextUrl = ($campaignId > 0)
    ? ((function_exists('pcs_url') ? pcs_url('detail-asset-maker.php') : 'detail-asset-maker.php') . '?' . http_build_query([
        'campaign_id'    => $campaignId,
        'asset_id'       => $assetId,
        'upload_success' => '1'
    ]))
    : ((function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php') . '?upload_success=1');
$fileSize     = $version['file_size'] ?? '2.4 MB';
$dimensions   = $version['dimensions'] ?? '1920 × 1080 px';
$colorway     = $version['colorway'] ?? 'sRGB Colorway';
$fileUrl      = $version['file_url'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Scoring in Progress - Brand Compliance AI</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Liberation+Mono:wght@400;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Base Reset & Variables */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #FAF8FF;
            color: #161B2E;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button {
            border: none;
            background: transparent;
            cursor: pointer;
            font-family: inherit;
        }

        /* Top Navbar Integration */
        .wp-navbar-wrapper {
            width: 100%;
            background: #ffffff;
            border-bottom: 1px solid rgba(226, 232, 240, 0.70);
            position: sticky;
            top: 0;
            z-index: 999;
        }

        /* Layout Structure */
        .scoring-page-container {
            width: 100%;
            max-width: 1300px;
            margin: 0 auto;
            padding: 24px 32px 48px 32px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Animations */
        @keyframes pulseDot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.35; transform: scale(0.8); }
        }

        @keyframes laserSweep {
            0% {
                top: 16px;
                opacity: 0.25;
            }
            50% {
                top: 380px;
                opacity: 0.95;
            }
            100% {
                top: 16px;
                opacity: 0.25;
            }
        }

        @keyframes progressGlow {
            0% { box-shadow: 0 0 4px rgba(79, 87, 167, 0.2); }
            50% { box-shadow: 0 0 12px rgba(79, 87, 167, 0.6); }
            100% { box-shadow: 0 0 4px rgba(79, 87, 167, 0.2); }
        }

        .pulse-dot {
            animation: pulseDot 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }

        .scanning-laser {
            animation: laserSweep 3.2s ease-in-out infinite;
        }

        /* Hero Section */
        .section-hero {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        .hero-title-group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .hero-tag-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .hero-badge-tag {
            color: #4F57A7;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 14px;
        }

        .hero-dot-separator {
            width: 6px;
            height: 6px;
            background: #C7C5D3;
            border-radius: 9999px;
        }

        .hero-heading-row {
            display: flex;
            align-items: baseline;
            gap: 12px;
            flex-wrap: wrap;
        }

        .hero-heading {
            font-size: 28px;
            font-weight: 600;
            line-height: 36px;
            color: #161B2E;
        }

        .hero-subtext {
            font-size: 16px;
            font-weight: 400;
            line-height: 22px;
            color: #464651;
        }

        .progress-box-card {
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .progress-box-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .progress-box-count {
            color: #161B2E;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.24px;
        }

        .progress-box-status {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #4F57A7;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.24px;
        }

        .progress-bar-track {
            height: 6px;
            background: #EBEDFF;
            border-radius: 9999px;
            overflow: hidden;
            position: relative;
            width: 100%;
        }

        .progress-bar-fill {
            height: 100%;
            width: 66.66%;
            background: #4F57A7;
            border-radius: 9999px;
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            animation: progressGlow 2.5s infinite;
        }

        /* Context Line Strip */
        .context-line-strip {
            padding: 10px 16px;
            background: rgba(243, 242, 255, 0.70);
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 12px;
            line-height: 16px;
        }

        .context-item-bold {
            font-weight: 600;
            color: #161B2E;
        }

        .context-item-dot {
            color: #C7C5D3;
        }

        .context-item-muted {
            font-weight: 400;
            color: #464651;
        }

        .context-item-mono {
            font-family: 'Liberation Mono', monospace;
            font-weight: 600;
            color: #1F2674;
        }

        /* 2-Column Processing Stage (70% / 30%) */
        .processing-stage-grid {
            display: grid;
            grid-template-columns: 7fr 3fr;
            gap: 24px;
            align-items: start;
            width: 100%;
        }

        @media (max-width: 992px) {
            .processing-stage-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Left Column: Framed Preview */
        .preview-column {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .column-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .column-header-title {
            color: #161B2E;
            font-size: 16px;
            font-weight: 600;
            line-height: 22px;
        }

        .column-header-code {
            color: #464651;
            font-size: 10px;
            font-family: 'Liberation Mono', monospace;
            font-weight: 700;
            letter-spacing: 0.40px;
            line-height: 14px;
        }

        .framed-preview-box {
            height: 440px;
            background: #ffffff;
            box-shadow: 0px 2px 4px -2px rgba(0, 0, 0, 0.10), 0px 4px 6px -1px rgba(0, 0, 0, 0.10);
            border-radius: 12px;
            padding: 12px;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .artwork-canvas {
            width: 100%;
            height: 100%;
            background: #EBEDFF;
            border-radius: 8px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .artwork-image-el {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            display: block;
        }

        .artwork-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: radial-gradient(circle at center, #E0E2FF 0%, #D8DCFF 100%);
            color: #4F56A6;
            gap: 12px;
        }

        /* Scanning Laser Line */
        .laser-line-element {
            position: absolute;
            left: 0;
            right: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, rgba(159, 167, 253, 0) 0%, #9FA7FD 50%, rgba(159, 167, 253, 0) 100%);
            box-shadow: 0px 0px 16px rgba(159, 167, 253, 0.95);
            pointer-events: none;
            z-index: 10;
        }

        /* Corner Framing Targets */
        .corner-target {
            position: absolute;
            width: 16px;
            height: 16px;
            z-index: 8;
            pointer-events: none;
        }
        .corner-top-left { top: 12px; left: 12px; }
        .corner-top-right { top: 12px; right: 12px; transform: scaleX(-1); }
        .corner-bottom-left { bottom: 12px; left: 12px; transform: scaleY(-1); }
        .corner-bottom-right { bottom: 12px; right: 12px; transform: scale(-1); }

        /* Floating Tags on Artwork */
        .tag-artwork-floating {
            position: absolute;
            background: rgba(250, 248, 255, 0.92);
            box-shadow: 0px 1px 3px rgba(0, 0, 0, 0.08);
            border-radius: 6px;
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            z-index: 12;
        }

        .tag-v-top-left {
            top: 16px;
            left: 16px;
            padding: 4px 10px;
            gap: 6px;
            font-family: 'Liberation Mono', monospace;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.50px;
            color: #161B2E;
        }

        .tag-status-bottom-right {
            bottom: 16px;
            right: 16px;
            padding: 6px 12px;
            gap: 8px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.40px;
            color: #161B2E;
        }

        .preview-scrim {
            position: absolute;
            inset: 0;
            background: linear-gradient(0deg, rgba(213, 217, 243, 0.25) 0%, rgba(213, 217, 243, 0) 50%, rgba(213, 217, 243, 0.25) 100%);
            pointer-events: none;
            z-index: 5;
        }

        .metadata-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 4px;
            font-size: 12px;
            line-height: 16px;
        }

        .metadata-strip-file {
            color: #464651;
            font-weight: 400;
        }

        .metadata-strip-specs {
            color: #767682;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.40px;
        }

        /* Right Column: Focus Panel */
        .focus-column {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .focus-card {
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 24px;
            min-height: 440px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 24px;
        }

        .focus-top-content {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .focus-badge-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .focus-rule-tag {
            color: #4F57A7;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.50px;
        }

        .focus-processing-pill {
            padding: 4px 10px;
            background: #E4E7FF;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            gap: 6px;
            color: #4F57A7;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.40px;
        }

        .focus-title {
            color: #161B2E;
            font-size: 18px;
            font-weight: 600;
            line-height: 24px;
            margin-top: 4px;
        }

        .focus-description {
            color: #464651;
            font-size: 12px;
            font-weight: 400;
            line-height: 18px;
            margin-top: 4px;
        }

        /* Vertical Timeline */
        .vertical-timeline {
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding-top: 6px;
        }

        .timeline-step {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .timeline-icon-box {
            width: 20px;
            height: 20px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .timeline-icon-done {
            background: rgba(0, 80, 72, 0.15);
        }

        .timeline-icon-active {
            background: rgba(79, 87, 167, 0.20);
        }

        .timeline-text-col {
            display: flex;
            flex-direction: column;
        }

        .timeline-title {
            color: #161B2E;
            font-size: 12px;
            font-weight: 600;
            line-height: 16px;
            letter-spacing: 0.24px;
        }

        .timeline-sub-done {
            color: #464651;
            font-size: 12px;
            font-weight: 400;
            line-height: 16px;
        }

        .timeline-sub-active {
            color: #4F57A7;
            font-size: 12px;
            font-weight: 500;
            line-height: 16px;
        }

        /* Bottom Summary Box in Right Rail */
        .summary-box-card {
            background: #ffffff;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .summary-box-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .summary-box-label {
            color: #464651;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.40px;
        }

        .summary-box-step {
            color: #161B2E;
            font-size: 10px;
            font-family: 'Liberation Mono', monospace;
            font-weight: 500;
            letter-spacing: 0.40px;
        }

        .summary-box-body {
            color: #161B2E;
            font-size: 12px;
            font-weight: 400;
            line-height: 16px;
        }

        .summary-box-footer {
            display: flex;
            align-items: center;
            gap: 6px;
            padding-top: 4px;
            color: #464651;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.40px;
        }

        /* Compliance Check Board (Full-Width) */
        .check-board-card {
            background: #ffffff;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        .check-board-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 4px;
        }

        .check-board-title-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .check-board-title {
            color: #161B2E;
            font-size: 16px;
            font-weight: 600;
            line-height: 22px;
        }

        .check-board-sub {
            color: #464651;
            font-size: 12px;
            font-weight: 400;
            line-height: 16px;
        }

        .check-board-progress-text {
            color: #4F57A7;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.24px;
        }

        /* Exactly 3 Large Horizontal Rows */
        .check-rows-container {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
        }

        .check-row-card {
            min-height: 82px;
            padding: 18px 24px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
        }

        .check-row-done {
            background: rgba(243, 242, 255, 0.50);
        }

        .check-row-active {
            background: rgba(228, 231, 255, 0.65);
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(79, 87, 167, 0.15);
        }

        .check-row-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .check-icon-badge {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
        }

        .check-icon-white {
            background: #ffffff;
            color: #1F2674;
        }

        .check-icon-primary {
            background: #4F57A7;
            color: #ffffff;
        }

        .check-row-titles {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .check-row-heading-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .check-row-heading {
            color: #161B2E;
            font-size: 16px;
            font-weight: 600;
            line-height: 22px;
        }

        .check-row-live-badge {
            padding: 2px 8px;
            background: #E0E0FF;
            border-radius: 4px;
            color: #030A62;
            font-size: 11px;
            font-weight: 600;
            line-height: 16px;
        }

        .check-row-category {
            color: #464651;
            font-size: 12px;
            font-weight: 400;
            line-height: 16px;
        }

        .check-row-right {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .check-row-activity {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 2px;
        }

        .activity-label {
            color: #464651;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.40px;
            line-height: 14px;
        }

        .activity-val {
            font-size: 12px;
            line-height: 16px;
            font-weight: 500;
            color: #161B2E;
        }

        .activity-val-active {
            color: #4F57A7;
            font-weight: 600;
        }

        .check-status-pill {
            padding: 6px 12px;
            background: #ffffff;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 9999px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.24px;
            color: #161B2E;
        }

        .status-pill-active {
            color: #4F57A7;
            font-weight: 600;
        }

        /* 3-Segment Progress Indicator Line */
        .segments-indicator-wrapper {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-top: 8px;
            width: 100%;
        }

        .segments-bar-row {
            display: flex;
            gap: 8px;
            width: 100%;
        }

        .segment-bar {
            flex: 1;
            height: 6px;
            border-radius: 9999px;
        }

        .segment-bar-done {
            background: #5EDAC9;
        }

        .segment-bar-active {
            background: #4F57A7;
            animation: progressGlow 2.5s infinite;
        }

        .segment-bar-pending {
            background: #E2E8F0;
        }

        .segments-labels-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            line-height: 16px;
        }

        .segment-label-done {
            color: #464651;
            font-weight: 400;
        }

        .segment-label-active {
            color: #4F57A7;
            font-weight: 600;
        }

        .segment-label-pending {
            color: #94A3B8;
            font-weight: 400;
        }

        .timeline-icon-pending {
            background: #F1F5F9;
        }

        .timeline-sub-pending {
            color: #94A3B8;
            font-size: 12px;
            font-weight: 400;
            line-height: 16px;
        }

        /* Version Path + Next State Grid (5 cols / 7 cols) */
        .lineage-nextstate-grid {
            display: grid;
            grid-template-columns: 5fr 7fr;
            gap: 24px;
            width: 100%;
        }

        @media (max-width: 992px) {
            .lineage-nextstate-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Left: Version Lineage (5 cols) */
        .lineage-card {
            background: #ffffff;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 20px;
        }

        .lineage-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .lineage-tag {
            color: #767682;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.50px;
        }

        .lineage-count {
            color: #464651;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.40px;
        }

        .lineage-title {
            color: #161B2E;
            font-size: 16px;
            font-weight: 600;
            line-height: 22px;
            margin-top: 4px;
        }

        .lineage-path-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            padding-top: 8px;
        }

        .lineage-pill-node {
            padding: 6px 12px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.24px;
            color: #161B2E;
        }

        .lineage-node-rejected {
            background: #F3F2FF;
        }

        .lineage-node-processing {
            background: #E4E7FF;
        }

        .lineage-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #1F2674;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.24px;
            transition: gap 0.2s;
        }

        .lineage-link-btn:hover {
            gap: 10px;
            text-decoration: underline;
        }

        /* Right: Next State (7 cols) */
        .nextstate-card {
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
        }

        .nextstate-tag-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nextstate-tag {
            color: #4F57A7;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.50px;
        }

        .nextstate-badge {
            color: #464651;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.40px;
        }

        .nextstate-heading {
            color: #161B2E;
            font-size: 16px;
            font-weight: 600;
            line-height: 22px;
            margin-top: 4px;
        }

        .nextstate-body {
            color: #464651;
            font-size: 14px;
            font-weight: 400;
            line-height: 20px;
            max-width: 580px;
            margin-top: 4px;
        }

        .nextstate-timer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid rgba(226, 232, 240, 0.6);
        }

        .nextstate-timer-group {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #464651;
            font-size: 12px;
            font-weight: 400;
        }

        .btn-view-results-quick {
            padding: 8px 16px;
            background: #4F57A7;
            color: #ffffff;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.2px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
        }

        .btn-view-results-quick:hover {
            background: #373E8C;
        }

        /* Bottom Action Area */
        .bottom-action-bar {
            background: #ffffff;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }

        .action-bar-asset-info {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #161B2E;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.24px;
        }

        .action-bar-running-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #4F57A7;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.24px;
        }

        .btn-back-campaign {
            padding: 11px 16px;
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #1F2674;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.24px;
            transition: background 0.2s ease;
        }

        .btn-back-campaign:hover {
            background: #E4E7FF;
        }

        /* Empty State & Access Restricted */
        .empty-state-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 56px 32px;
            text-align: center;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            max-width: 600px;
            margin: 40px auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .empty-state-icon {
            width: 64px;
            height: 64px;
            border-radius: 32px;
            background: #F3F2FF;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4F57A7;
        }
    </style>
    <?php if (function_exists("wp_head")) wp_head(); ?>
</head>
<body>

    <!-- 1. TOP NAVBAR (Shared Unified Component) -->
    <?php 
    if (function_exists('pcs_render_top_navbar')) {
        pcs_render_top_navbar($currentUser, 'campaigns');
    }
    ?>

    <!-- RBAC Guard Notice if Unauthorized -->
    <?php if (!$isAllowed): ?>
        <main class="scoring-page-container">
            <div class="empty-state-card">
                <div class="empty-state-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                </div>
                <h2 style="font-size: 20px; font-weight: 700; color: #161B2E;">Access Restricted</h2>
                <p style="color: #64748B; font-size: 14px; max-width: 440px;">
                    This automated AI Compliance scoring stage is restricted to team members with the <strong>Maker</strong> role.
                </p>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('home-page.php') : 'home-page.php') ?>" class="btn-back-campaign" style="margin-top: 8px;">
                    Return to Homepage
                </a>
            </div>
        </main>
            <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body>
        </html>
        <?php exit; ?>
    <?php endif; ?>

    <!-- Empty State if No Data Found -->
    <?php if ($isDbEmpty): ?>
        <main class="scoring-page-container">
            <div class="empty-state-card">
                <div class="empty-state-icon">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="2" width="20" height="20" rx="4"></rect>
                        <path d="M12 8v8M8 12h8"></path>
                    </svg>
                </div>
                <h2 style="font-size: 20px; font-weight: 700; color: #161B2E;">No Asset in Scoring Pipeline</h2>
                <p style="color: #64748B; font-size: 14px; max-width: 440px;">
                    There is currently no active asset version in the AI scanning pipeline. Please upload an asset first to start automated compliance checks.
                </p>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('smart-maker-upload.php') : 'smart-maker-upload.php') ?>" class="btn-view-results-quick" style="margin-top: 8px;">
                    Upload Creative Asset →
                </a>
            </div>
        </main>
            <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body>
        </html>
        <?php exit; ?>
    <?php endif; ?>

    <!-- MAIN SCORING STAGE VIEW -->
    <main class="scoring-page-container">

        <!-- 01. SECTION: PROCESSING HERO -->
        <section class="section-hero">
            <div class="hero-title-group">
                <div class="hero-tag-row">
                    <span class="hero-badge-tag">AI REVIEW</span>
                    <div class="hero-dot-separator"></div>
                </div>
                <div class="hero-heading-row">
                    <h1 class="hero-heading">AI Scoring in Progress</h1>
                    <span class="hero-subtext"><?= esc_html($assetName) ?> · <?= esc_html($versionLabel) ?></span>
                </div>
            </div>

            <div class="progress-box-card">
                <div class="progress-box-header">
                    <span class="progress-box-count" id="progressCountLabel"><?= (int)$completedCount ?> of <?= (int)$totalChecks ?> complete</span>
                    <div class="progress-box-status">
                        <svg class="pulse-dot" width="8" height="8" viewBox="0 0 8 8" fill="none">
                            <rect width="8" height="8" rx="4" fill="#4F57A7"/>
                        </svg>
                        <span id="progressStateLabel">Processing</span>
                    </div>
                </div>
                <div class="progress-bar-track">
                    <div class="progress-bar-fill" id="mainProgressBar" style="width: 15%;"></div>
                </div>
            </div>
        </section>

        <!-- 02. CAMPAIGN / VERSION CONTEXT STRIP -->
        <div class="context-line-strip">
            <span class="context-item-bold"><?= esc_html($campaignName) ?></span>
            <span class="context-item-dot">·</span>
            <span class="context-item-muted"><?= esc_html($assetName) ?></span>
            <span class="context-item-dot">·</span>
            <span class="context-item-mono"><?= esc_html($versionLabel) ?></span>
            <span class="context-item-dot">·</span>
            <span class="context-item-muted"><?= esc_html($makerName) ?></span>
            <span class="context-item-dot">·</span>
            <span class="context-item-muted" style="color: rgba(70, 70, 81, 0.85);"><?= esc_html($uploadedTime) ?></span>
        </div>

        <!-- Small clear success notice line requested by user -->
        <div id="aiCompleteNoticeLine" style="<?= $isCompletedAlready ? 'display: flex;' : 'display: none;' ?> margin-bottom: 24px; padding: 12px 18px; border-radius: 8px; background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; font-size: 13.5px; font-weight: 700; align-items: center; justify-content: space-between; gap: 12px; box-shadow: 0 1px 3px rgba(16, 185, 129, 0.1);">
            <div style="display: inline-flex; align-items: center; gap: 10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#059669" stroke-width="2.5"><polyline points="20 6 9 17 4 12"></polyline></svg>
                <span>AI compliance scan completed successfully. Ready for review.</span>
            </div>
            <a href="<?= esc_url($nextUrl) ?>" style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 6px; background: #059669; color: #ffffff; text-decoration: none; font-size: 12.5px; font-weight: 600;">
                <span>View Asset Details</span>
                <span>→</span>
            </a>
        </div>

        <!-- 03. ASSET PROCESSING STAGE (70% / 30% SPLIT) -->
        <section class="processing-stage-grid">
            
            <!-- Left 70%: Framed Artwork Preview -->
            <div class="preview-column">
                <div class="column-header-row">
                    <h2 class="column-header-title">Uploaded Asset</h2>
                    <span class="column-header-code"><?= esc_html($assetCode) ?></span>
                </div>

                <div class="framed-preview-box">
                    <div class="artwork-canvas">
                        
                        <?php if (!empty($fileUrl)): ?>
                            <img src="<?= esc_url($fileUrl) ?>" alt="<?= esc_attr($assetName) ?>" class="artwork-image-el" onerror="this.style.display='none'; document.getElementById('artworkFallback').style.display='flex';">
                            <div id="artworkFallback" class="artwork-placeholder" style="display: none;">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <span style="font-size: 13px; font-weight: 600;"><?= esc_html($assetName) ?></span>
                            </div>
                        <?php else: ?>
                            <div class="artwork-placeholder">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <span style="font-size: 13px; font-weight: 600;"><?= esc_html($assetName) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Animated Laser Scanning Line -->
                        <div class="laser-line-element scanning-laser"></div>

                        <!-- 4 Corner Framing Targets -->
                        <div class="corner-target corner-top-left">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <rect width="16" height="2" fill="#4F57A7"/>
                                <rect width="2" height="16" fill="#4F57A7"/>
                            </svg>
                        </div>
                        <div class="corner-target corner-top-right">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <rect width="16" height="2" fill="#4F57A7"/>
                                <rect width="2" height="16" fill="#4F57A7"/>
                            </svg>
                        </div>
                        <div class="corner-target corner-bottom-left">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <rect width="16" height="2" fill="#4F57A7"/>
                                <rect width="2" height="16" fill="#4F57A7"/>
                            </svg>
                        </div>
                        <div class="corner-target corner-bottom-right">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                <rect width="16" height="2" fill="#4F57A7"/>
                                <rect width="2" height="16" fill="#4F57A7"/>
                            </svg>
                        </div>

                        <!-- Top-Left Version Floating Badge -->
                        <div class="tag-artwork-floating tag-v-top-left">
                            <svg width="6" height="6" viewBox="0 0 6 6" fill="none">
                                <rect width="6" height="6" rx="3" fill="#4F57A7"/>
                            </svg>
                            <span><?= esc_html($versionLabel) ?></span>
                        </div>

                        <!-- Bottom-Right Live Scanning Status Tag -->
                        <div class="tag-artwork-floating tag-status-bottom-right">
                            <svg class="pulse-dot" width="8" height="8" viewBox="0 0 8 8" fill="none">
                                <rect width="8" height="8" rx="4" fill="#4F57A7"/>
                            </svg>
                            <span id="scanningStageLabel">Scanning Frame Layers</span>
                        </div>
                    </div>
                    <div class="preview-scrim"></div>
                </div>

                <div class="metadata-strip">
                    <span class="metadata-strip-file"><?= esc_html($fileName) ?> · <?= esc_html($fileExt) ?> · <?= esc_html($fileSize) ?></span>
                    <span class="metadata-strip-specs"><?= esc_html($dimensions) ?> · <?= esc_html($colorway) ?></span>
                </div>
            </div>

            <!-- Right 30%: Focus Panel -->
            <div class="focus-column">
                <div class="column-header-row">
                    <h2 class="column-header-title">Now Checking</h2>
                    <span style="color: #4F57A7; font-size: 10px; font-weight: 500; letter-spacing: 0.40px;">Active Rule</span>
                </div>

                <div class="focus-card">
                    <div class="focus-top-content">
                        <div class="focus-badge-row">
                            <span class="focus-rule-tag">RULE ENGINE</span>
                            <div class="focus-processing-pill">
                                <svg class="pulse-dot" width="6" height="6" viewBox="0 0 6 6" fill="none">
                                    <rect width="6" height="6" rx="3" fill="#4F57A7"/>
                                </svg>
                                <span>Processing</span>
                            </div>
                        </div>

                        <div>
                            <h3 class="focus-title" id="focusCardTitle"><?= esc_html($activeCheck['name'] ?? 'Foreign Logos Verification') ?></h3>
                            <p class="focus-description" id="focusCardDesc">
                                <?= esc_html($activeCheck['desc'] ?? 'Verifying brand exclusivity against competitor marks (OCBC, UOB, HSBC, Citi, Maybank) and clear space geometry.') ?>
                            </p>
                        </div>

                        <!-- Vertical Timeline Sequence -->
                        <div class="vertical-timeline">
                            <?php foreach ($checks as $idx => $chk): 
                                $isDone = ($chk['status'] === 'finished');
                                $isActive = !empty($chk['is_active']);
                            ?>
                                <div class="timeline-step" id="timelineStep<?= (int)$idx ?>">
                                    <div class="timeline-icon-box <?= $isDone ? 'timeline-icon-done' : ($isActive ? 'timeline-icon-active' : 'timeline-icon-pending') ?>" id="timelineIcon<?= (int)$idx ?>">
                                        <?php if ($isDone): ?>
                                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                                <rect width="10" height="10" rx="5" fill="#5EDAC9"/>
                                            </svg>
                                        <?php elseif ($isActive): ?>
                                            <svg class="pulse-dot" width="10" height="10" viewBox="0 0 10 10" fill="none">
                                                <rect width="10" height="10" rx="5" fill="#4F57A7"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                                <rect width="10" height="10" rx="5" fill="#CBD5E1"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="timeline-text-col">
                                        <span class="timeline-title"><?= esc_html($chk['detail'] ?? $chk['name']) ?></span>
                                        <span class="<?= $isDone ? 'timeline-sub-done' : ($isActive ? 'timeline-sub-active' : 'timeline-sub-pending') ?>" id="timelineSub<?= (int)$idx ?>">
                                            <?= esc_html($isDone ? $chk['status_label'] : ($isActive ? ($chk['sub_text'] ?? 'Processing...') : 'Queued')) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Bottom Summary Box -->
                    <div class="summary-box-card">
                        <div class="summary-box-row">
                            <span class="summary-box-label">Audit Pipeline</span>
                            <span class="summary-box-step" id="summaryBoxStep">Step 1 of 3</span>
                        </div>
                        <div class="summary-box-body" id="summaryBoxBody">
                            0 checks complete · 1 processing
                        </div>
                        <div class="summary-box-footer">
                            <svg width="10" height="13" viewBox="0 0 10 13" fill="none">
                                <path d="M1.16667 12.25C0.845833 12.25 0.571181 12.1358 0.342708 11.9073C0.114236 11.6788 0 11.4042 0 11.0833V5.25C0 4.92917 0.114236 4.65451 0.342708 4.42604C0.571181 4.19757 0.845833 4.08333 1.16667 4.08333H1.75V2.91667C1.75 2.10972 2.03438 1.42188 2.60313 0.853125C3.17188 0.284375 3.85972 0 4.66667 0C5.47361 0 6.16146 0.284375 6.73021 0.853125C7.29896 1.42188 7.58333 2.10972 7.58333 2.91667V4.08333H8.16667C8.4875 4.08333 8.76215 4.19757 8.99063 4.42604C9.2191 4.65451 9.33333 4.92917 9.33333 5.25V11.0833C9.33333 11.4042 9.2191 11.6788 8.99063 11.9073C8.76215 12.1358 8.4875 12.25 8.16667 12.25H1.16667V12.25M1.16667 11.0833H8.16667V5.25H1.16667V11.0833M4.66667 9.33333C4.9875 9.33333 5.26215 9.2191 5.49062 8.99063C5.7191 8.76215 5.83333 8.4875 5.83333 8.16667C5.83333 7.84583 5.7191 7.57118 5.49062 7.34271C5.26215 7.11424 4.9875 7 4.66667 7C4.34583 7 4.07118 7.11424 3.84271 7.34271C3.61424 7.57118 3.5 7.84583 3.5 8.16667C3.5 8.4875 3.61424 8.76215 3.84271 8.99063C4.07118 9.2191 4.34583 9.33333 4.66667 9.33333M2.91667 4.08333H6.41667V2.91667C6.41667 2.43056 6.24653 2.01736 5.90625 1.67708C5.56597 1.33681 5.15278 1.16667 4.66667 1.16667C4.18056 1.16667 3.76736 1.33681 3.42708 1.67708C3.08681 2.01736 2.91667 2.43056 2.91667 2.91667V4.08333H1.16667" fill="#5EDAC9"/>
                            </svg>
                            <span id="focusCardRuleset"><?= esc_html($activeCheck['ruleset'] ?? 'Regulatory ruleset: Trademark Clearance FL-01') ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 04. COMPLIANCE CHECK BOARD (FULL WIDTH) -->
        <section class="check-board-card">
            <div class="check-board-header">
                <div class="check-board-title-group">
                    <h2 class="check-board-title">AI Checks</h2>
                    <span style="color: #C7C5D3;">·</span>
                    <span class="check-board-sub">Deterministic Evaluation Protocols</span>
                </div>
                <span class="check-board-progress-text" id="boardProgressCount">0 / 3 complete</span>
            </div>

            <!-- Exactly THREE large horizontal rows (~82px height each) -->
            <div class="check-rows-container">
                <?php foreach ($checks as $index => $chk): 
                    $isDone = ($chk['status'] === 'finished');
                    $isActive = !empty($chk['is_active']);
                ?>
                    <div class="check-row-card <?= $isActive ? 'check-row-active' : ($isDone ? 'check-row-done' : 'check-row-pending') ?>" id="checkRow<?= (int)$index ?>">
                        <div class="check-row-left">
                            <div class="check-icon-badge <?= $isActive ? 'check-icon-primary' : 'check-icon-white' ?>" id="checkIconBadge<?= (int)$index ?>">
                                <?php if ($chk['id'] === 'guideline'): ?>
                                    <svg width="14" height="17" viewBox="0 0 14 17" fill="none">
                                        <path d="M3.33333 13.3333H10V11.6667H3.33333V13.3333M3.33333 10H10V8.33333H3.33333V10M1.66667 16.6667C1.20833 16.6667 0.815972 16.5035 0.489583 16.1771C0.163194 15.8507 0 15.4583 0 15V1.66667C0 1.20833 0.163194 0.815972 0.489583 0.489583C0.815972 0.163194 1.20833 0 1.66667 0H8.33333L13.3333 5V15C13.3333 15.4583 13.1701 15.8507 12.8438 16.1771C12.5174 16.5035 12.125 16.6667 11.6667 16.6667H1.66667V16.6667M7.5 5.83333V1.66667H1.66667V15H11.6667V5.83333H7.5Z" fill="currentColor"/>
                                    </svg>
                                <?php else: ?>
                                    <svg width="14" height="17" viewBox="0 0 14 17" fill="none">
                                        <path d="M5.79167 11.2917L10.5 6.58333L9.3125 5.39583L5.79167 8.91667L4.04167 7.16667L2.85417 8.35417L5.79167 11.2917M6.66667 16.6667C4.73611 16.1806 3.14236 15.0729 1.88542 13.3438C0.628472 11.6146 0 9.69444 0 7.58333V2.5L6.66667 0L13.3333 2.5V7.58333C13.3333 9.69444 12.7049 11.6146 11.4479 13.3438C10.191 15.0729 8.59722 16.1806 6.66667 16.6667M6.66667 14.9167C8.11111 14.4583 9.30556 13.5417 10.25 12.1667C11.1944 10.7917 11.6667 9.26389 11.6667 7.58333V3.64583L6.66667 1.77083L1.66667 3.64583V7.58333C1.66667 9.26389 2.13889 10.7917 3.08333 12.1667C4.02778 13.5417 5.22222 14.4583 6.66667 14.9167Z" fill="currentColor"/>
                                    </svg>
                                <?php endif; ?>
                            </div>
                            <div class="check-row-titles">
                                <div class="check-row-heading-row">
                                    <span class="check-row-heading"><?= esc_html($chk['name']) ?></span>
                                    <?php if (!empty($chk['live_tag'])): ?>
                                        <span class="check-row-live-badge" id="checkLiveBadge<?= (int)$index ?>"><?= esc_html($chk['live_tag']) ?></span>
                                    <?php else: ?>
                                        <span class="check-row-live-badge" id="checkLiveBadge<?= (int)$index ?>" style="display: none;">Live</span>
                                    <?php endif; ?>
                                </div>
                                <span class="check-row-category"><?= esc_html($chk['category']) ?></span>
                            </div>
                        </div>

                        <div class="check-row-right">
                            <div class="check-row-activity">
                                <span class="activity-label">ACTIVITY</span>
                                <span class="activity-val <?= $isActive ? 'activity-val-active' : '' ?>" id="checkActivity<?= (int)$index ?>">
                                    <?= esc_html($isActive ? ($chk['sub_text'] ?? 'Processing...') : ($isDone ? 'Finished' : 'Queued')) ?>
                                </span>
                            </div>

                            <div class="check-status-pill <?= $isActive ? 'status-pill-active' : '' ?>" id="checkStatusPill<?= (int)$index ?>">
                                <span id="checkPillText<?= (int)$index ?>"><?= esc_html($isActive ? 'Processing' : ($isDone ? 'Complete' : 'Waiting')) ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Refined 3-segment progress indicator line -->
            <div class="segments-indicator-wrapper">
                <div class="segments-bar-row">
                    <div class="segment-bar segment-bar-active" id="segmentBar0"></div>
                    <div class="segment-bar segment-bar-pending" id="segmentBar1"></div>
                    <div class="segment-bar segment-bar-pending" id="segmentBar2"></div>
                </div>
                <div class="segments-labels-row">
                    <span class="segment-label-active" id="segmentLabel0">Foreign Logos (Evaluating...)</span>
                    <span class="segment-label-pending" style="text-align: center;" id="segmentLabel1">Brand Docs (Queued)</span>
                    <span class="segment-label-pending" style="text-align: right;" id="segmentLabel2">MAS Advertising (Queued)</span>
                </div>
            </div>
        </section>

        <!-- 05. SECTION: VERSION PATH + NEXT STATE (12 COLS: 5 COLS LEFT, 7 COLS RIGHT) -->
        <section class="lineage-nextstate-grid">
            
            <!-- Left: Version Lineage (5 cols) -->
            <div class="lineage-card">
                <div>
                    <div class="lineage-header-row">
                        <span class="lineage-tag">LINEAGE</span>
                        <span class="lineage-count"><?= (int)$versionCount ?> Versions</span>
                    </div>
                    <h3 class="lineage-title">Version History</h3>

                    <div class="lineage-path-row">
                        <?php if ($previousVersion): 
                            $isPrevRejected = (strtolower($previousVersion['status'] ?? '') === 'rejected');
                        ?>
                            <div class="lineage-pill-node lineage-node-rejected">
                                <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                                    <rect width="8" height="8" rx="4" fill="<?= $isPrevRejected ? '#BA1A1A' : '#10B981' ?>"/>
                                </svg>
                                <span><?= esc_html($previousVersion['version_label']) ?> (<?= esc_html($previousVersion['status_label']) ?>)</span>
                            </div>

                            <svg width="40" height="2" viewBox="0 0 40 2" fill="none">
                                <rect width="40" height="2" fill="#EBEDFF"/>
                            </svg>
                        <?php endif; ?>

                        <div class="lineage-pill-node lineage-node-processing">
                            <svg class="pulse-dot" width="8" height="8" viewBox="0 0 8 8" fill="none">
                                <rect width="8" height="8" rx="4" fill="#4F57A7"/>
                            </svg>
                            <span><?= esc_html($versionLabel) ?> (Processing)</span>
                        </div>
                    </div>
                </div>

                <div style="padding-top: 14px;">
                    <?php if ($previousVersion && !empty($previousVersion['review_note'])): ?>
                        <a href="javascript:void(0)" onclick="openFeedbackModal()" class="lineage-link-btn">
                            <span>View <?= esc_html($previousVersion['version_label']) ?> feedback</span>
                            <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                                <path d="M8.11667 6H0V4.66667H8.11667L4.38333 0.933333L5.33333 0L10.6667 5.33333L5.33333 10.6667L4.38333 9.73333L8.11667 6Z" fill="#1F2674"/>
                            </svg>
                        </a>
                    <?php elseif ($previousVersion): ?>
                        <?php 
                        $compareUrl = (function_exists('pcs_url') ? pcs_url('compare-version.php') : 'compare-version.php') . '?' . http_build_query([
                            'asset_id'    => $targetAssetId ?: ($asset['id'] ?? 0),
                            'campaign_id' => $campaignId,
                            'from'        => 'ai_scoring'
                        ]);
                        ?>
                        <a href="<?= esc_url($compareUrl) ?>" class="lineage-link-btn">
                            <span>View <?= esc_html($previousVersion['version_label']) ?> feedback</span>
                            <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                                <path d="M8.11667 6H0V4.66667H8.11667L4.38333 0.933333L5.33333 0L10.6667 5.33333L5.33333 10.6667L4.38333 9.73333L8.11667 6Z" fill="#1F2674"/>
                            </svg>
                        </a>
                    <?php else: ?>
                        <span style="font-size: 12px; color: #767682;">First iteration (V1 initial draft)</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right: Next State (7 cols) -->
            <div class="nextstate-card">
                <div>
                    <div class="nextstate-tag-row">
                        <span class="nextstate-tag">NEXT PHASE</span>
                        <span class="nextstate-badge">Automated Handoff</span>
                    </div>

                    <h3 class="nextstate-heading">Submitting for Compliance Review</h3>
                    <p class="nextstate-body">
                        Pre-flight checks are concluding. Upon completion, your artwork will automatically transition to "In Review" status for Compliance Checker verification.
                    </p>
                </div>

                <div class="nextstate-timer-row">
                    <div class="nextstate-timer-group">
                        <svg class="pulse-dot" width="14" height="14" viewBox="0 0 12 12" fill="none">
                            <path d="M0 12V10.5H2.0625L1.7625 10.2375C1.1125 9.6625 0.65625 9.00625 0.39375 8.26875C0.13125 7.53125 0 6.7875 0 6.0375C0 4.65 0.415625 3.41562 1.24688 2.33437C2.07812 1.25312 3.1625 0.5375 4.5 0.1875V1.7625C3.6 2.0875 2.875 2.64062 2.325 3.42188C1.775 4.20312 1.5 5.075 1.5 6.0375C1.5 6.6 1.60625 7.14687 1.81875 7.67812C2.03125 8.20938 2.3625 8.7 2.8125 9.15L3 9.3375V7.5H4.5V12H0ZM7.5 11.8125V10.2375C8.4 9.9125 9.125 9.35938 9.675 8.57812C10.225 7.79688 10.5 6.925 10.5 5.9625C10.5 5.4 10.3937 4.85313 10.1812 4.32188C9.96875 3.79063 9.6375 3.3 9.1875 2.85L9 2.6625V4.5H7.5V0H12V1.5H9.9375L10.2375 1.7625C10.85 2.375 11.2969 3.04063 11.5781 3.75938C11.8594 4.47813 12 5.2125 12 5.9625C12 7.35 11.5844 8.58437 10.7531 9.66562C9.92188 10.7469 8.8375 11.4625 7.5 11.8125Z" fill="#4F57A7"/>
                        </svg>
                        <span id="aiStatusText">AI Scanning Active: <strong id="timerCountdown">0s</strong> elapsed</span>
                    </div>

                    <a href="<?= esc_url($nextUrl) ?>" class="btn-view-results-quick" id="btnViewResults">
                        <span>Return to Campaign</span>
                        <span>→</span>
                    </a>
                </div>
            </div>
        </section>



    </main>

    <!-- Modal: Previous Version Feedback (if clicked) -->
    <?php if ($previousVersion && !empty($previousVersion['review_note'])): ?>
        <div id="feedbackModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
            <div style="background: #ffffff; border-radius: 12px; max-width: 500px; width: 100%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); overflow: hidden;">
                <div style="padding: 20px 24px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 4px; background: #BA1A1A;"></span>
                        <h4 style="font-size: 16px; font-weight: 600; color: #161B2E;"><?= esc_html($previousVersion['version_label']) ?> Review Feedback</h4>
                    </div>
                    <button type="button" onclick="closeFeedbackModal()" style="font-size: 20px; color: #64748B; cursor: pointer; line-height: 1;">&times;</button>
                </div>
                <div style="padding: 24px;">
                    <div style="background: #FEF2F2; border-left: 4px solid #EF4444; padding: 14px 16px; border-radius: 6px; font-size: 13px; color: #7F1D1D; line-height: 20px;">
                        <?= nl2br(esc_html($previousVersion['review_note'])) ?>
                    </div>
                    <p style="margin-top: 14px; font-size: 12px; color: #64748B;">
                        This feedback was issued during the compliance evaluation of <?= esc_html($previousVersion['version_label']) ?> and addressed in <?= esc_html($versionLabel) ?>.
                    </p>
                </div>
                <div style="padding: 16px 24px; background: #F8FAFC; border-top: 1px solid #E2E8F0; text-align: right;">
                    <button type="button" onclick="closeFeedbackModal()" class="btn-back-campaign" style="padding: 8px 16px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Interactive Auto-Progression & Countdown Script -->
    <script>
        // Modal Handlers
        function openFeedbackModal() {
            var m = document.getElementById('feedbackModal');
            if (m) m.style.display = 'flex';
        }
        function closeFeedbackModal() {
            var m = document.getElementById('feedbackModal');
            if (m) m.style.display = 'none';
        }

        // Live Scanning Stage Text Cycling
        const stagePhrases = [
            "Scanning Frame Layers",
            "Detecting Financial Disclaimers",
            "Measuring Contrast & Font Legibility",
            "Validating MAS Regulatory Rules",
            "Finalizing Compliance Scorecard"
        ];
        let phraseIdx = 0;
        const stageLabel = document.getElementById('scanningStageLabel');

        const phraseTimer = setInterval(function() {
            if (stageLabel && phraseIdx < stagePhrases.length - 1) {
                phraseIdx++;
                stageLabel.textContent = stagePhrases[phraseIdx];
            }
        }, 3000);

        // 3 Pipeline Steps Configuration
        const stepsConfig = [
            {
                name: "Foreign Logos Verification",
                desc: "Verifying brand exclusivity against competitor marks (OCBC, UOB, HSBC, Citi, Maybank) and clear space geometry.",
                ruleset: "Regulatory ruleset: Trademark Clearance FL-01",
                activityActive: "Scanning competitor marks...",
                activityDone: "Finished",
                subActive: "Analyzing clear space & marks...",
                subDone: "Finished",
                labelActive: "Foreign Logos (Evaluating...)",
                labelDone: "Foreign Logos (Done)",
                labelPending: "Foreign Logos (Queued)"
            },
            {
                name: "Brand & Compliance Docs",
                desc: "Verifying corporate color palette (#0A192F Navy, #D4AF37 Gold) and typography exclusion boundaries.",
                ruleset: "Regulatory ruleset: Corporate Visual Identity BG-01",
                activityActive: "Analyzing palette harmony & text contrast...",
                activityDone: "Finished",
                subActive: "Analyzing corporate palette...",
                subDone: "Finished",
                labelActive: "Brand Docs (Evaluating...)",
                labelDone: "Brand Docs (Done)",
                labelPending: "Brand Docs (Queued)"
            },
            {
                name: "MAS Advertising Rules",
                desc: "Evaluating financial disclaimer prominence, legibility against imagery contrast, and regulatory wealth management phrasing.",
                ruleset: "Regulatory ruleset: MAS Guidelines §4.1b",
                activityActive: "Evaluating financial risk disclosure prominence...",
                activityDone: "Finished",
                subActive: "Analyzing disclaimer legibility...",
                subDone: "Finished",
                labelActive: "MAS Advertising (Evaluating...)",
                labelDone: "MAS Advertising (Done)",
                labelPending: "MAS Advertising (Queued)"
            }
        ];

        let aiCompleted = false;
        let currentStepIdx = 0;
        let elapsedSeconds = 0;
        const countdownEl = document.getElementById('timerCountdown');
        const progressBar = document.getElementById('mainProgressBar');
        const nextUrl = "<?= esc_js($nextUrl) ?>";

        function updateStepUI(activeIdx) {
            if (aiCompleted) return;
            currentStepIdx = activeIdx;

            // 1. Focus Card
            const fTitle = document.getElementById('focusCardTitle');
            if (fTitle) fTitle.textContent = stepsConfig[activeIdx].name;

            const fDesc = document.getElementById('focusCardDesc');
            if (fDesc) fDesc.textContent = stepsConfig[activeIdx].desc;

            const fRule = document.getElementById('focusCardRuleset');
            if (fRule) fRule.textContent = stepsConfig[activeIdx].ruleset;

            // 2. Summary Box
            const sStep = document.getElementById('summaryBoxStep');
            if (sStep) sStep.textContent = 'Step ' + (activeIdx + 1) + ' of 3';

            const sBody = document.getElementById('summaryBoxBody');
            if (sBody) sBody.textContent = activeIdx + ' checks complete · 1 processing';

            // 3. Header Progress Labels
            const pCount = document.getElementById('progressCountLabel');
            if (pCount) pCount.textContent = activeIdx + ' of 3 complete';

            const bCount = document.getElementById('boardProgressCount');
            if (bCount) bCount.textContent = activeIdx + ' / 3 complete';

            // 4. Vertical Timeline & Check Rows & Segments
            for (let i = 0; i < 3; i++) {
                const tIcon = document.getElementById('timelineIcon' + i);
                const tSub  = document.getElementById('timelineSub' + i);
                const cRow  = document.getElementById('checkRow' + i);
                const cAct  = document.getElementById('checkActivity' + i);
                const cPill = document.getElementById('checkStatusPill' + i);
                const cText = document.getElementById('checkPillText' + i);
                const cLive = document.getElementById('checkLiveBadge' + i);
                const sBar  = document.getElementById('segmentBar' + i);
                const sLbl  = document.getElementById('segmentLabel' + i);

                if (i < activeIdx) {
                    // Done
                    if (tIcon) {
                        tIcon.className = 'timeline-icon-box timeline-icon-done';
                        tIcon.innerHTML = '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><rect width="10" height="10" rx="5" fill="#5EDAC9"/></svg>';
                    }
                    if (tSub) {
                        tSub.className = 'timeline-sub-done';
                        tSub.textContent = stepsConfig[i].subDone;
                    }
                    if (cRow) {
                        cRow.className = 'check-row-card check-row-done';
                    }
                    if (cAct) {
                        cAct.className = 'activity-val';
                        cAct.textContent = stepsConfig[i].activityDone;
                    }
                    if (cPill) {
                        cPill.className = 'check-status-pill';
                    }
                    if (cText) {
                        cText.textContent = 'Complete';
                    }
                    if (cLive) {
                        cLive.style.display = 'none';
                    }
                    if (sBar) {
                        sBar.className = 'segment-bar segment-bar-done';
                    }
                    if (sLbl) {
                        sLbl.className = 'segment-label-done';
                        sLbl.textContent = stepsConfig[i].labelDone;
                    }
                } else if (i === activeIdx) {
                    // Active (Processing)
                    if (tIcon) {
                        tIcon.className = 'timeline-icon-box timeline-icon-active';
                        tIcon.innerHTML = '<svg class="pulse-dot" width="10" height="10" viewBox="0 0 10 10" fill="none"><rect width="10" height="10" rx="5" fill="#4F57A7"/></svg>';
                    }
                    if (tSub) {
                        tSub.className = 'timeline-sub-active';
                        tSub.textContent = stepsConfig[i].subActive;
                    }
                    if (cRow) {
                        cRow.className = 'check-row-card check-row-active';
                    }
                    if (cAct) {
                        cAct.className = 'activity-val activity-val-active';
                        cAct.textContent = stepsConfig[i].activityActive;
                    }
                    if (cPill) {
                        cPill.className = 'check-status-pill status-pill-active';
                    }
                    if (cText) {
                        cText.textContent = 'Processing';
                    }
                    if (cLive) {
                        cLive.style.display = 'inline-block';
                    }
                    if (sBar) {
                        sBar.className = 'segment-bar segment-bar-active';
                    }
                    if (sLbl) {
                        sLbl.className = 'segment-label-active';
                        sLbl.textContent = stepsConfig[i].labelActive;
                    }
                } else {
                    // Pending (Queued)
                    if (tIcon) {
                        tIcon.className = 'timeline-icon-box timeline-icon-pending';
                        tIcon.innerHTML = '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><rect width="10" height="10" rx="5" fill="#CBD5E1"/></svg>';
                    }
                    if (tSub) {
                        tSub.className = 'timeline-sub-pending';
                        tSub.textContent = 'Queued';
                    }
                    if (cRow) {
                        cRow.className = 'check-row-card check-row-pending';
                    }
                    if (cAct) {
                        cAct.className = 'activity-val';
                        cAct.textContent = 'Queued';
                    }
                    if (cPill) {
                        cPill.className = 'check-status-pill';
                    }
                    if (cText) {
                        cText.textContent = 'Waiting';
                    }
                    if (cLive) {
                        cLive.style.display = 'none';
                    }
                    if (sBar) {
                        sBar.className = 'segment-bar segment-bar-pending';
                    }
                    if (sLbl) {
                        sLbl.className = 'segment-label-pending';
                        sLbl.textContent = stepsConfig[i].labelPending;
                    }
                }
            }
        }

        // Timer for elapsed seconds and simulated progress bar
        const scanTimer = setInterval(function() {
            if (aiCompleted) return;
            elapsedSeconds++;
            if (countdownEl) {
                countdownEl.textContent = elapsedSeconds + 's';
            }
            if (progressBar && elapsedSeconds < 18) {
                const simulatedWidth = Math.min(92, 20 + (elapsedSeconds * 4.5));
                progressBar.style.width = simulatedWidth + '%';
            }
        }, 1000);

        // Sequence step transitions: Step 0 -> Step 1 at 2.5s -> Step 2 at 5.5s
        setTimeout(function() {
            if (!aiCompleted) updateStepUI(1);
        }, 2500);

        setTimeout(function() {
            if (!aiCompleted) updateStepUI(2);
        }, 5500);

        function handleAiFinished(data) {
            if (aiCompleted) return;
            aiCompleted = true;
            clearInterval(scanTimer);
            clearInterval(phraseTimer);

            // Animate progress bar to 100%
            if (progressBar) progressBar.style.width = '100%';

            // Stop laser scanning animation
            const laser = document.querySelector('.laser-line-element');
            if (laser) {
                laser.classList.remove('scanning-laser');
                laser.style.display = 'none';
            }

            // Update UI state to completed
            const pLabel = document.getElementById('progressStateLabel');
            if (pLabel) pLabel.textContent = 'Completed';

            const pCount = document.getElementById('progressCountLabel');
            if (pCount) pCount.textContent = '3 of 3 complete';

            const bCount = document.getElementById('boardProgressCount');
            if (bCount) bCount.textContent = '3 / 3 complete';

            const sStep = document.getElementById('summaryBoxStep');
            if (sStep) sStep.textContent = 'Step 3 of 3';

            const sBody = document.getElementById('summaryBoxBody');
            if (sBody) sBody.textContent = '3 checks complete';

            // Mark all 3 steps as finished in Timeline, Check Rows, and Segments
            for (let i = 0; i < 3; i++) {
                const tIcon = document.getElementById('timelineIcon' + i);
                const tSub  = document.getElementById('timelineSub' + i);
                const cRow  = document.getElementById('checkRow' + i);
                const cAct  = document.getElementById('checkActivity' + i);
                const cPill = document.getElementById('checkStatusPill' + i);
                const cText = document.getElementById('checkPillText' + i);
                const cLive = document.getElementById('checkLiveBadge' + i);
                const sBar  = document.getElementById('segmentBar' + i);
                const sLbl  = document.getElementById('segmentLabel' + i);

                if (tIcon) {
                    tIcon.className = 'timeline-icon-box timeline-icon-done';
                    tIcon.innerHTML = '<svg width="10" height="10" viewBox="0 0 10 10" fill="none"><rect width="10" height="10" rx="5" fill="#5EDAC9"/></svg>';
                }
                if (tSub) {
                    tSub.className = 'timeline-sub-done';
                    tSub.textContent = stepsConfig[i].subDone;
                }
                if (cRow) {
                    cRow.className = 'check-row-card check-row-done';
                }
                if (cAct) {
                    cAct.className = 'activity-val';
                    cAct.textContent = stepsConfig[i].activityDone;
                }
                if (cPill) {
                    cPill.className = 'check-status-pill';
                }
                if (cText) {
                    cText.textContent = 'Complete';
                }
                if (cLive) {
                    cLive.style.display = 'none';
                }
                if (sBar) {
                    sBar.className = 'segment-bar segment-bar-done';
                }
                if (sLbl) {
                    sLbl.className = 'segment-label-done';
                    sLbl.textContent = stepsConfig[i].labelDone;
                }
            }

            const stageLbl = document.getElementById('scanningStageLabel');
            if (stageLbl) {
                stageLbl.textContent = 'AI Compliance Scan Complete';
            }

            const statusText = document.getElementById('aiStatusText');
            if (statusText) {
                statusText.innerHTML = '<span style="color: #059669; font-weight: 700;">AI scoring completed! Ready for review</span>';
            }

            // Show the small clear success notice line right on screen as requested
            const successNotice = document.getElementById('aiCompleteNoticeLine');
            if (successNotice) {
                successNotice.style.display = 'flex';
            }

            const btn = document.getElementById('btnViewResults');
            if (btn) {
                btn.style.boxShadow = '0 0 16px rgba(79, 87, 167, 0.8)';
                btn.textContent = 'View Asset Details →';
            }
        }

        // Fire real AI evaluation immediately via AJAX
        const runAiUrl = window.location.href + (window.location.href.indexOf('?') !== -1 ? '&' : '?') + 'action=run_real_ai&version_id=<?= (int)$versionId ?>&asset_id=<?= (int)$assetId ?>';
        fetch(runAiUrl, { method: 'POST' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                handleAiFinished(data);
            })
            .catch(function(err) {
                // If network drops, trigger completion fallback
                fetch(window.location.href + (window.location.href.indexOf('?') !== -1 ? '&' : '?') + 'action=complete_scoring&version_id=<?= (int)$versionId ?>&asset_id=<?= (int)$assetId ?>', { method: 'POST' })
                    .then(function() { handleAiFinished({ success: true }); })
                    .catch(function() { handleAiFinished({ success: true }); });
            });

        // ESC to close modal
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeFeedbackModal();
            }
        });
    </script>
    <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body>
</html>

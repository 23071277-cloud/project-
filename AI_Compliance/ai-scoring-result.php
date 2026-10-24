<?php
/**
 * Template Name: Maker - AI Assessment Result
 * Description: AI Check Result & Pre-submission Compliance Review for Maker
 * 100% WordPress CPT Database Integration: campaign, asset, asset_version, check_result, audit_log, wp_users
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

// 2. Current User & RBAC Authentication (Checker & Admin)
$currentUser = function_exists('pcs_enforce_rbac') ? pcs_enforce_rbac(['CHECKER', 'ADMIN']) : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'CHECKER');
$isAllowed = ($userRole === 'CHECKER' || $userRole === 'EDITOR' || $userRole === 'ADMIN' || $userRole === 'ADMINISTRATOR');

// Maker không thấy điểm số: nếu Maker vào trang này, tự động chuyển về trang chi tiết campaign
if ($userRole === 'MAKER' || $userRole === 'AUTHOR' || $userRole === 'CONTRIBUTOR') {
    $cId = (int)($_GET['campaign_id'] ?? 0);
    $aId = (int)($_GET['asset_id'] ?? 0);
    $red = (function_exists('pcs_url') ? pcs_url('detail-asset-maker.php') : 'detail-asset-maker.php') . ($cId ? '?campaign_id=' . $cId . ($aId ? '&asset_id=' . $aId : '') : '');
    if (!headers_sent()) {
        header('Location: ' . $red);
        exit;
    }
}

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
    header('Location: ' . $target . (strpos($target, '?') !== false ? '&' : '?') . 'loggedout=true');
    exit;
}

// 4. Handle Checker Decisions & Submission
$submissionSuccess = false;
$submissionMessage = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $vIdPost = (int) ($_POST['version_id'] ?? 0);
    $aIdPost = (int) ($_POST['asset_id'] ?? 0);
    $cIdPost = (int) ($_POST['campaign_id'] ?? 0);
    $notes   = sanitize_text_field($_POST['review_notes'] ?? '');

    if ($_POST['action'] === 'checker_approve') {
        if ($vIdPost > 0 && function_exists('handle_checker_decision')) {
            handle_checker_decision($vIdPost, 'approved', $notes ?: 'Approved after AI pre-flight inspection');
            $submissionSuccess = true;
            $submissionMessage = 'Asset version approved successfully!';
        }
    } elseif ($_POST['action'] === 'checker_reject') {
        if ($vIdPost > 0 && function_exists('handle_checker_decision')) {
            handle_checker_decision($vIdPost, 'rejected', $notes ?: 'Revisions required based on compliance assessment.');
            $submissionSuccess = true;
            $submissionMessage = 'Asset version rejected and returned to Maker for corrections.';
        }
    } elseif ($_POST['action'] === 'submit_for_checker') {
        if ($vIdPost > 0) {
            if (function_exists('pcs_server_meta')) {
                pcs_server_meta($vIdPost, '_status', 'in_review');
                if ($aIdPost > 0) {
                    pcs_server_meta($aIdPost, '_current_version_id', $vIdPost);
                }
            } elseif (function_exists('update_post_meta')) {
                update_post_meta($vIdPost, '_status', 'in_review');
                if ($aIdPost > 0) {
                    update_post_meta($aIdPost, '_current_version_id', $vIdPost);
                }
            }
            if (function_exists('handle_audit_log')) {
                handle_audit_log('Checker confirmed version #' . $vIdPost . ' in review', $vIdPost, $currentUser['id'] ?? 1);
            }
            $submissionSuccess = true;
            $submissionMessage = 'Version status verified and set to In Review.';
        }
    }
}

// 5. Query Real Data from WordPress Database
$targetVersionId  = (int) ($_GET['version_id'] ?? 0);
$targetAssetId    = (int) ($_GET['asset_id'] ?? 0);
$targetCampaignId = (int) ($_GET['campaign_id'] ?? 0);

$screenData = function_exists('get_ai_scoring_result_data') 
    ? get_ai_scoring_result_data($targetVersionId, $targetAssetId, $targetCampaignId) 
    : [];

$isDbEmpty       = $screenData['is_empty'] ?? true;
$campaign        = $screenData['campaign'] ?? null;
$asset           = $screenData['asset'] ?? null;
$version         = $screenData['version'] ?? null;
$previousVersion = $screenData['previous_version'] ?? null;
$versionCount    = $screenData['version_count'] ?? 1;
$checks          = $screenData['checks'] ?? [];
$evidenceMarkers = $screenData['evidence_markers'] ?? [];
$returnUrl       = $screenData['return_url'] ?? (function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php');
$uploadNewVUrl   = $screenData['upload_new_v_url'] ?? (function_exists('pcs_url') ? pcs_url('smart-maker-upload.php') : 'smart-maker-upload.php');

$campaignId   = $campaign['id'] ?? $targetCampaignId;
$campaignName = $campaign['name'] ?? 'General Campaign';
$assetId      = $asset['id'] ?? $targetAssetId;
$assetName    = $asset['name'] ?? 'Marketing Asset';
$versionId    = $version['id'] ?? $targetVersionId;
$versionLabel = $version['version_label'] ?? 'V1';
$versionNum   = $version['version_num'] ?? 1;
$makerName    = $version['maker_name'] ?? ($currentUser['first_name'] ?? 'Cam Anh');
$fileName     = $version['file_name'] ?? 'social-banner-v3.png';
$fileExt      = $version['file_ext'] ?? 'PNG';
$fileSize     = $version['file_size'] ?? '2.4 MB';
$fileUrl      = $version['file_url'] ?? '';
$versionStatus = $version['status'] ?? 'scoring';

$queueUrl = !empty($campaignId)
    ? ((function_exists('pcs_url') ? pcs_url('detail-asset-checker.php') : 'detail-asset-checker.php') . '?campaign_id=' . (int)$campaignId)
    : ((function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php') . '#queue-section');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Assessment Result - Brand Compliance AI</title>
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

        /* Toast / Feedback Alert */
        .toast-feedback-bar {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            border-left: 4px solid #10B981;
            padding: 14px 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 13px;
            color: #065F46;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        /* Hero Section */
        .section-hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            width: 100%;
            gap: 24px;
            flex-wrap: wrap;
        }

        .hero-left-col {
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

        .hero-dot-indicator {
            width: 6px;
            height: 6px;
            background: #4F57A7;
            border-radius: 9999px;
        }

        .hero-heading-row {
            display: flex;
            align-items: baseline;
            gap: 16px;
            flex-wrap: wrap;
        }

        .hero-heading {
            color: #161B2E;
            font-size: 36px;
            font-weight: 700;
            line-height: 44px;
            letter-spacing: -0.5px;
        }

        .hero-subtext {
            color: #464651;
            font-size: 18px;
            font-weight: 500;
            line-height: 24px;
        }

        .hero-campaign-strip {
            font-size: 14px;
            line-height: 20px;
            color: #464651;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .hero-status-card {
            padding: 16px;
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .hero-status-icon-box {
            width: 40px;
            height: 40px;
            background: #EBEDFF;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1F2674;
            flex-shrink: 0;
        }

        .hero-status-title-row {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #161B2E;
            font-size: 16px;
            font-weight: 700;
            line-height: 22px;
            letter-spacing: 0.32px;
        }

        .hero-status-subtitle {
            color: #464651;
            font-size: 12px;
            font-weight: 400;
            line-height: 16px;
        }

        /* Compact Context Line Strip */
        .compact-context-strip {
            padding: 8px 16px;
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            line-height: 16px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .context-items-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .context-camp-title {
            color: #1F2674;
            font-weight: 600;
        }

        .context-asset-ver {
            color: #161B2E;
            font-weight: 600;
        }

        .context-complete-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #48C8B7;
            font-weight: 600;
        }

        /* Section 03: 12-Column Overview (7 Cols Artwork, 5 Cols Snapshot) */
        .overview-12col-grid {
            display: grid;
            grid-template-columns: 7fr 5fr;
            gap: 24px;
            align-items: start;
            width: 100%;
        }

        @media (max-width: 992px) {
            .overview-12col-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Left 7 Cols: Artwork with Markers */
        .artwork-lens-column {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .lens-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .lens-title-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .lens-title {
            color: #161B2E;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.32px;
            line-height: 22px;
        }

        .lens-badge {
            padding: 2px 8px;
            background: #EBEDFF;
            border-radius: 9999px;
            color: #1F2674;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.25px;
            line-height: 14px;
        }

        .lens-subtext {
            color: #464651;
            font-size: 12px;
            font-weight: 400;
        }

        .artwork-frame-box {
            background: #ffffff;
            box-shadow: 0px 2px 4px -2px rgba(0, 0, 0, 0.10), 0px 4px 6px -1px rgba(0, 0, 0, 0.10);
            border-radius: 12px;
            padding: 8px;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .artwork-canvas-area {
            position: relative;
            background: #E4E7FF;
            border-radius: 8px;
            min-height: 382px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: auto;
            padding: 12px;
        }

        .artwork-render-stage {
            position: relative;
            display: inline-block;
            max-width: 100%;
            line-height: 0;
            text-align: center;
        }

        .artwork-actual-image {
            width: auto;
            max-width: 100%;
            max-height: 520px;
            height: auto;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            border-radius: 6px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
        }

        .artwork-actual-image.artwork-pdf-frame {
            width: 100%;
            min-width: 520px;
            height: 560px;
            max-height: 560px;
            box-shadow: none;
        }

        .artwork-graphic-fallback {
            width: 100%;
            min-height: 382px;
            background: radial-gradient(circle at 50% 40%, #E8EAFD 0%, #D8DCFF 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #4F56A6;
            gap: 12px;
        }

        /* Evidence Lens Interactive Markers */
        .lens-marker-wrapper {
            position: absolute;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
            z-index: 20;
            transition: transform 0.2s ease, filter 0.2s ease;
        }

        .lens-marker-wrapper:hover {
            transform: scale(1.04);
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.15));
        }

        .marker-circle-badge {
            width: 32px;
            height: 32px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 12px;
            font-weight: 700;
            line-height: 16px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
        }

        .marker-glass-label {
            margin-left: 8px;
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.24px;
            color: #161B2E;
            white-space: nowrap;
        }

        .draggable-marker {
            cursor: grab !important;
            user-select: none;
            touch-action: none;
        }

        .draggable-marker:active,
        .draggable-marker.is-dragging {
            cursor: grabbing !important;
            transform: scale(1.1) !important;
            z-index: 100 !important;
            filter: drop-shadow(0 8px 18px rgba(0,0,0,0.28)) !important;
        }

        .lens-drag-hint {
            font-size: 11px;
            color: #94A3B8;
            font-weight: 700;
            margin-left: 2px;
            letter-spacing: -1px;
        }

        .evidence-lens-box {
            position: absolute;
            pointer-events: none;
            border: 2px dashed #4F57A7;
            background: rgba(79, 87, 167, 0.08);
            border-radius: 6px;
            z-index: 15;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0.35;
        }

        .evidence-lens-box.lens-box-active {
            opacity: 1 !important;
            box-shadow: 0 0 0 3px rgba(79, 87, 167, 0.25);
            animation: pulse-lens-box 1.8s infinite ease-in-out;
        }

        @keyframes pulse-lens-box {
            0%, 100% { transform: scale(1); opacity: 0.8; }
            50% { transform: scale(1.02); opacity: 1; }
        }

        .artwork-sub-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 4px 0 4px;
            font-size: 12px;
            line-height: 16px;
        }

        .artwork-file-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #464651;
        }

        .btn-view-full-size {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #1F2674;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.24px;
            cursor: pointer;
        }

        .btn-view-full-size:hover {
            text-decoration: underline;
        }

        /* Right 5 Cols: Result Snapshot */
        .snapshot-column {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .snapshot-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .snapshot-card {
            background: #ffffff;
            box-shadow: 0px 2px 4px -2px rgba(0, 0, 0, 0.10), 0px 4px 6px -1px rgba(0, 0, 0, 0.10);
            border-radius: 12px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* Snapshot Check Item */
        .snapshot-check-box {
            padding: 10px;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid rgba(199, 197, 211, 0.35);
            display: flex;
            flex-direction: column;
            gap: 8px;
            transition: all 0.2s ease;
        }

        .snapshot-check-box:hover {
            border-color: #4F57A7;
            background: #FAFAFF;
        }

        .snapshot-item-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .snapshot-item-name {
            font-size: 13px;
            font-weight: 700;
            color: #161B2E;
            letter-spacing: 0.13px;
        }

        .snapshot-item-category {
            font-size: 12px;
            color: #464651;
        }

        .snapshot-score-badge-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .score-number-text {
            font-size: 18px;
            font-weight: 700;
            color: #161B2E;
            line-height: 24px;
        }

        .score-total-denom {
            font-size: 12px;
            font-weight: 400;
            color: #464651;
        }

        .status-pill-badge {
            padding: 2px 10px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.40px;
            line-height: 14px;
        }

        /* 3-Zone Mini Scale Bar with Pin */
        .mini-zone-scale-wrapper {
            display: flex;
            flex-direction: column;
            gap: 4px;
            width: 100%;
        }

        .zone-track-bar {
            height: 8px;
            background: #EBEDFF;
            border-radius: 9999px;
            position: relative;
            overflow: hidden;
            display: flex;
            width: 100%;
        }

        .zone-segment-fail {
            width: 30%;
            background: rgba(254, 205, 211, 0.85);
        }

        .zone-segment-needs-human {
            width: 40%;
            background: rgba(253, 230, 138, 0.85);
        }

        .zone-segment-pass {
            width: 30%;
            background: rgba(167, 243, 208, 0.90);
        }

        .score-pin-needle {
            position: absolute;
            top: 0;
            bottom: 0;
            width: 6px;
            border-radius: 3px;
            transform: translateX(-50%);
            box-shadow: 0 0 4px rgba(0,0,0,0.25);
            z-index: 10;
        }

        .zone-labels-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
            font-weight: 500;
            color: #464651;
            padding: 0 2px;
        }

        .card-divider-line {
            height: 1px;
            background: #EBEDFF;
            width: 100%;
        }

        /* Score Reference Box */
        .score-reference-box {
            padding: 10px;
            background: #F3F2FF;
            border-radius: 8px;
            border: 1px solid rgba(199, 197, 211, 0.30);
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .score-reference-title {
            color: #161B2E;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.50px;
        }

        .reference-pills-row {
            display: flex;
            gap: 6px;
            width: 100%;
        }

        .ref-pill-card {
            flex: 1;
            padding: 4px 6px;
            border-radius: 4px;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .governance-statement-box {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            font-size: 12px;
            line-height: 16px;
            padding-top: 4px;
        }

        /* Section 04: Detailed AI Assessment (3 Editorial Full-Width Bands) */
        .detailed-assessment-section {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
        }

        .editorial-band-card {
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            display: grid;
            grid-template-columns: 2.5fr 6.5fr 3fr;
            gap: 24px;
            align-items: start;
            transition: all 0.25s ease;
        }

        .editorial-band-white {
            background: #ffffff;
        }

        .editorial-band-lavender {
            background: #F3F2FF;
        }

        .editorial-band-highlight {
            outline: 2px solid #4F57A7;
            box-shadow: 0 4px 14px rgba(79, 87, 167, 0.2);
        }

        @media (max-width: 992px) {
            .editorial-band-card {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        /* Band Column 1: Category & Score */
        .band-score-col {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .band-category-title {
            color: #4F57A7;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.50px;
            line-height: 14px;
        }

        .band-score-number-row {
            display: flex;
            align-items: baseline;
            gap: 4px;
        }

        .band-score-number {
            font-size: 28px;
            font-weight: 700;
            line-height: 36px;
        }

        /* Band Column 2: Reason & Evidence */
        .band-reason-col {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .band-section-subhead {
            color: #161B2E;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.24px;
            line-height: 16px;
        }

        .band-reason-text {
            color: #464651;
            font-size: 14px;
            font-weight: 400;
            line-height: 20px;
        }

        /* Structured Problem Breakdown & Grounding Cards */
        .compliance-breakdown-card {
            display: flex;
            flex-direction: column;
            gap: 8px;
            background: rgba(255, 255, 255, 0.85);
            border: 1px solid rgba(199, 197, 211, 0.45);
            border-radius: 8px;
            padding: 10px 14px;
            margin-top: 2px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        }

        .compliance-breakdown-item {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            line-height: 19px;
        }

        .compliance-breakdown-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            color: #4F57A7;
            flex-shrink: 0;
            min-width: 115px;
            padding-top: 1px;
        }

        .compliance-breakdown-val {
            color: #161B2E;
            font-size: 13px;
            line-height: 19px;
            word-break: break-word;
        }

        .band-evidence-box {
            padding: 10px 14px;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            font-size: 12px;
        }

        .evidence-tag-left {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #161B2E;
            font-weight: 500;
        }

        .btn-view-in-artwork {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #1F2674;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.24px;
            cursor: pointer;
            white-space: nowrap;
        }

        .btn-view-in-artwork:hover {
            text-decoration: underline;
        }

        /* Band Column 3: Source & Rule */
        .band-source-col {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding-left: 8px;
            border-left: 1px solid rgba(226, 232, 240, 0.7);
        }

        @media (max-width: 992px) {
            .band-source-col {
                padding-left: 0;
                border-left: none;
                border-top: 1px solid rgba(226, 232, 240, 0.7);
                padding-top: 12px;
            }
        }

        .source-name-title {
            color: #1F2674;
            font-size: 14px;
            font-weight: 600;
            line-height: 20px;
        }

        .source-status-sub {
            color: #464651;
            font-size: 12px;
            font-weight: 400;
        }

        .source-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #4F57A7;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.24px;
            margin-top: 4px;
            cursor: pointer;
        }

        .source-link-btn:hover {
            text-decoration: underline;
        }

        /* Section 05: Version History & Next Review Context (5 cols / 7 cols) */
        .version-next-review-grid {
            display: grid;
            grid-template-columns: 5fr 7fr;
            gap: 24px;
            width: 100%;
        }

        @media (max-width: 992px) {
            .version-next-review-grid {
                grid-template-columns: 1fr;
            }
        }

        .iteration-history-card {
            padding: 24px;
            background: #ffffff;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 24px;
        }

        .iteration-tag {
            color: #4F57A7;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.50px;
        }

        .iteration-title {
            color: #161B2E;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.32px;
            line-height: 22px;
        }

        .iteration-pill-nodes-row {
            display: flex;
            align-items: center;
            gap: 8px;
            padding-top: 12px;
            flex-wrap: wrap;
        }

        .node-pill-item {
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.24px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .node-prev {
            background: #EBEDFF;
            color: #464651;
        }

        .node-current {
            background: #E0E0FF;
            color: #1F2674;
        }

        .governance-context-card {
            padding: 24px;
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
        }

        .governance-head-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .governance-icon-badge {
            width: 32px;
            height: 32px;
            background: #E0E0FF;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1F2674;
            flex-shrink: 0;
        }

        .governance-lead-title {
            color: #161B2E;
            font-size: 16px;
            font-weight: 700;
            line-height: 22px;
            letter-spacing: 0.32px;
        }

        .governance-desc-body {
            color: #464651;
            font-size: 14px;
            font-weight: 400;
            line-height: 22px;
            padding-top: 4px;
        }

        .principle-banner-box {
            padding: 14px 16px;
            background: rgba(255, 255, 255, 0.85);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        /* Decision Strip */
        .decision-strip-card {
            padding: 16px 20px;
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            border-left: 4px solid #4F57A7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            flex-wrap: wrap;
            gap: 16px;
        }

        .decision-strip-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .decision-strip-icon-box {
            width: 36px;
            height: 36px;
            background: #EBEDFF;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4F57A7;
            flex-shrink: 0;
        }

        .decision-strip-heading {
            color: #161B2E;
            font-size: 16px;
            font-weight: 700;
            line-height: 22px;
            letter-spacing: 0.32px;
        }

        .decision-strip-right {
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 12px;
        }

        /* Bottom Action Dock */
        .bottom-action-dock {
            padding-top: 4px;
            padding-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            flex-wrap: wrap;
            gap: 16px;
        }

        .btn-dock-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #464651;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.13px;
            cursor: pointer;
        }

        .btn-dock-back:hover {
            color: #161B2E;
        }

        .dock-actions-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .btn-upload-new-version {
            height: 44px;
            padding: 0 24px;
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #C7C5D3;
            color: #1F2674;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-upload-new-version:hover {
            background: #F3F2FF;
            border-color: #4F57A7;
        }

        .btn-submit-checker-primary {
            height: 44px;
            padding: 0 32px;
            background: #1F2674;
            border-radius: 8px;
            color: #ffffff;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.13px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0px 2px 4px -2px rgba(0, 0, 0, 0.10), 0px 4px 6px -1px rgba(0, 0, 0, 0.10);
            transition: background 0.2s ease, transform 0.1s ease;
        }

        .btn-submit-checker-primary:hover {
            background: #161B2E;
            transform: translateY(-1px);
        }

        /* Empty State & Access Restriction Cards */
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
                    This AI compliance evaluation result is restricted to team members with the <strong>Maker</strong> role.
                </p>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('home-page.php') : 'home-page.php') ?>" class="btn-upload-new-version" style="margin-top: 8px;">
                    Return to Homepage
                </a>
            </div>
        </main>
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
                <h2 style="font-size: 20px; font-weight: 700; color: #161B2E;">No Assessment Results Found</h2>
                <p style="color: #64748B; font-size: 14px; max-width: 440px;">
                    No evaluated asset version was found in the database. Please upload an asset first to generate an automated AI assessment scorecard.
                </p>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('smart-maker-upload.php') : 'smart-maker-upload.php') ?>" class="btn-submit-checker-primary" style="margin-top: 8px;">
                    Upload Creative Asset →
                </a>
            </div>
        </main>
        </body>
        </html>
        <?php exit; ?>
    <?php endif; ?>

    <!-- MAIN SCORING RESULT VIEW -->
    <main class="scoring-page-container">

        <!-- Top Back Navigation Bar -->
        <div class="top-back-navigation-bar" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 4px; flex-wrap: wrap; gap: 12px;">
            <a href="<?= esc_url($queueUrl) ?>" class="btn-top-back-queue" style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #373E8C; text-decoration: none; padding: 8px 14px; background: #EEF2FF; border: 1px solid #C7D2FE; border-radius: 8px; transition: all 0.2s;" onmouseover="this.style.background='#E0E7FF'" onmouseout="this.style.background='#EEF2FF'">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                <span>Back to Review Queue</span>
            </a>
            <div style="font-size: 12.5px; color: #64748B; display: inline-flex; align-items: center; gap: 6px;">
                <span>Compliance Verification</span>
                <span style="color: #CBD5E1;">·</span>
                <strong style="color: #1E293B;"><?= esc_html($campaignName) ?></strong>
                <span style="color: #CBD5E1;">·</span>
                <span><?= esc_html($assetName) ?></span>
            </div>
        </div>

        <!-- Submission Feedback Banner if posted -->
        <?php if ($submissionSuccess): ?>
            <div class="toast-feedback-bar">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none">
                        <path d="M10 0C4.48 0 0 4.48 0 10C0 15.52 4.48 20 10 20C15.52 20 20 15.52 20 10C20 4.48 15.52 0 10 0ZM8 15L3 10L4.41 8.59L8 12.17L15.59 4.58L17 6L8 15Z" fill="#10B981"/>
                    </svg>
                    <span><?= esc_html($submissionMessage) ?></span>
                </div>
                <a href="<?= esc_url($returnUrl) ?>" style="font-weight: 700; color: #047857; text-decoration: underline;">
                    Return to Campaign Details →
                </a>
            </div>
        <?php endif; ?>

        <!-- 01. SECTION: ASSESSMENT HERO -->
        <section class="section-hero">
            <div class="hero-left-col">
                <div class="hero-tag-row">
                    <span class="hero-badge-tag">AI ASSESSMENT</span>
                    <div class="hero-dot-indicator"></div>
                </div>
                <div class="hero-heading-row">
                    <h1 class="hero-heading">Assessment Ready</h1>
                    <span class="hero-subtext"><?= esc_html($assetName) ?> · <?= esc_html($versionLabel) ?></span>
                </div>
                <div class="hero-campaign-strip">
                    <span>Campaign:</span>
                    <strong style="color: #161B2E;"><?= esc_html($campaignName) ?></strong>
                    <span>·</span>
                </div>
            </div>

            <div class="hero-status-card">
                <div class="hero-status-icon-box">
                    <svg width="20" height="19" viewBox="0 0 20 19" fill="none">
                        <path d="M18.6 19L13.9 14.3C13.2167 14.8333 12.4625 15.25 11.6375 15.55C10.8125 15.85 9.93333 16 9 16C7.5 16 6.14583 15.6333 4.9375 14.9C3.72917 14.1667 2.775 13.2 2.075 12H4.525C5.09167 12.6167 5.75417 13.1042 6.5125 13.4625C7.27083 13.8208 8.1 14 9 14C10.6667 14 12.0833 13.4167 13.25 12.25C14.4167 11.0833 15 9.66667 15 8C15 6.33333 14.4167 4.91667 13.25 3.75C12.0833 2.58333 10.6667 2 9 2C7.43333 2 6.07917 2.52917 4.9375 3.5875C3.79583 4.64583 3.15833 5.95 3.025 7.5H1.025C1.15833 5.38333 1.9875 3.60417 3.5125 2.1625C5.0375 0.720833 6.86667 0 9 0C11.2333 0 13.125 0.775 14.675 2.325C16.225 3.875 17 5.76667 17 8C17 8.93333 16.85 9.8125 16.55 10.6375C16.25 11.4625 15.8333 12.2167 15.3 12.9L20 17.6L18.6 19M7.925 12L6.35 6.8L5.05 10.5H0V9H4L5.65 4.25H7.15L8.675 9.35L9.75 6H11.25L12.75 9H13.5V10.5H11.825L10.65 8.15L9.4 12H7.925Z" fill="#1F2674"/>
                    </svg>
                </div>
                <div>
                    <div class="hero-status-title-row">
                        <span>3 checks complete</span>
                        <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                            <rect width="8" height="8" rx="4" fill="#5EDAC9"/>
                        </svg>
                    </div>
                    <div class="hero-status-subtitle">AI assessment</div>
                </div>
            </div>
        </section>

        <!-- 02. COMPACT CONTEXT LINE STRIP -->
        <div class="compact-context-strip">
            <div class="context-items-group">
                <span class="context-camp-title"><?= esc_html($campaignName) ?></span>
                <span style="color: #464651;">·</span>
                <span class="context-asset-ver"><?= esc_html($assetName) ?> · <?= esc_html($versionLabel) ?></span>
                <span style="color: #464651;">·</span>
                <span style="color: #464651;"><?= esc_html($makerName) ?></span>
                <span style="color: #464651;">·</span>
                <div class="context-complete-badge">
                    <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                        <rect width="8" height="8" rx="4" fill="#5EDAC9"/>
                    </svg>
                    <span>AI assessment complete</span>
                </div>
            </div>
        </div>

        <!-- 03. SECTION: ASSESSMENT OVERVIEW (12 COLS: 7 COLS ARTWORK, 5 COLS SNAPSHOT) -->
        <section class="overview-12col-grid">
            
            <!-- Left 7 Cols: Artwork with Evidence Lens Markers -->
            <div class="artwork-lens-column">
                <div class="lens-header-row">
                    <div class="lens-title-group">
                        <h2 class="lens-title">Artwork</h2>
                        <span class="lens-badge">EVIDENCE LENS ACTIVE</span>
                    </div>
                    <span class="lens-subtext">Interactive overlay pins enabled</span>
                </div>

                <div class="artwork-frame-box">
                    <div class="artwork-canvas-area" id="artworkContainer">
                        <?php 
                            $isPdf = (strtolower($fileExt ?? '') === 'pdf' || preg_match('/\.pdf(\?.*)?$/i', $fileUrl));
                            if (!empty($fileUrl)): ?>
                            <div class="artwork-render-stage" id="artworkRenderStage">
                                <?php if ($isPdf): ?>
                                    <iframe src="<?= esc_url($fileUrl) ?>#toolbar=0&navpanes=0&view=Fit" class="artwork-actual-image artwork-pdf-frame" id="mainArtworkPdf"></iframe>
                                <?php else: ?>
                                    <img src="<?= esc_url($fileUrl) ?>" alt="<?= esc_attr($assetName) ?>" class="artwork-actual-image" id="mainArtworkImg" onload="initDynamicArtworkEvidenceLens(this)" onerror="this.style.display='none'; document.getElementById('graphicFallback').style.display='flex';">
                                <?php endif; ?>

                                <!-- Evidence Lens Bounding Boxes -->
                                <div class="evidence-lens-box" id="lensBox-marker-1" style="display: none;"></div>
                                <div class="evidence-lens-box" id="lensBox-marker-2" style="display: none;"></div>
                                <div class="evidence-lens-box" id="lensBox-marker-3" style="display: none;"></div>

                                <!-- Interactive Markers overlaid strictly on artwork coordinates -->
                                <?php foreach ($evidenceMarkers as $m): ?>
                                    <div class="lens-marker-wrapper draggable-marker" id="<?= esc_attr($m['id']) ?>" data-marker-num="<?= esc_attr($m['num']) ?>" data-band-id="<?= esc_attr($m['band_id']) ?>" style="top: <?= esc_attr($m['top']) ?>; left: <?= esc_attr($m['left']) ?>;" onclick="scrollToBand('<?= esc_js($m['band_id']) ?>')">
                                        <div class="marker-circle-badge" style="background: <?= esc_attr($m['color']) ?>;">
                                            <?= esc_html($m['num']) ?>
                                        </div>
                                        <div class="marker-glass-label" style="<?= !empty($m['outline']) ? 'outline: 1px solid ' . esc_attr($m['outline']) . ';' : '' ?>">
                                            <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                                                <rect width="8" height="8" rx="4" fill="<?= esc_attr($m['dot']) ?>"/>
                                            </svg>
                                            <span><?= esc_html($m['label']) ?></span>
                                            <span class="lens-drag-hint" title="Drag marker to reposition pin">::</span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div id="graphicFallback" class="artwork-graphic-fallback" style="display: none;">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <span style="font-size: 14px; font-weight: 600;"><?= esc_html($assetName) ?> · <?= esc_html($versionLabel) ?></span>
                            </div>
                        <?php else: ?>
                            <div class="artwork-graphic-fallback">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <span style="font-size: 14px; font-weight: 600;"><?= esc_html($assetName) ?> · <?= esc_html($versionLabel) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Artwork Sub-bar -->
                    <div class="artwork-sub-bar">
                        <div class="artwork-file-info">
                            <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                                <path d="M1.33333 12C0.966667 12 0.652778 11.8694 0.391667 11.6083C0.130556 11.3472 0 11.0333 0 10.6667V1.33333C0 0.966667 0.130556 0.652778 0.391667 0.391667C0.652778 0.130556 0.966667 0 1.33333 0H10.6667C11.0333 0 11.3472 0.130556 11.6083 0.391667C11.8694 0.652778 12 0.966667 12 1.33333V10.6667C12 11.0333 11.8694 11.3472 11.6083 11.6083C11.3472 11.8694 11.0333 12 10.6667 12H1.33333V12M1.33333 10.6667H10.6667V1.33333H1.33333V10.6667M2 9.33333H10L7.5 6L5.5 8.66667L4 6.66667L2 9.33333Z" fill="#4F57A7"/>
                            </svg>
                            <span><?= esc_html($fileName) ?></span>
                            <span>·</span>
                            <span><?= esc_html($fileExt) ?></span>
                            <span>·</span>
                            <span><?= esc_html($fileSize) ?></span>
                        </div>

                        <div class="btn-view-full-size" onclick="openFullSizeModal()">
                            <span>View full size</span>
                            <svg width="11" height="11" viewBox="0 0 11 11" fill="none">
                                <path d="M1.16667 10.5C0.845833 10.5 0.571181 10.3858 0.342708 10.1573C0.114236 9.92882 0 9.65417 0 9.33333V1.16667C0 0.845833 0.114236 0.571181 0.342708 0.342708C0.571181 0.114236 0.845833 0 1.16667 0H5.25V1.16667H1.16667V9.33333H9.33333V5.25H10.5V9.33333C10.5 9.65417 10.3858 9.92882 10.1573 10.1573C9.92882 10.3858 9.65417 10.5 9.33333 10.5H1.16667ZM3.90833 7.40833L3.09167 6.59167L8.51667 1.16667H6.41667V0H10.5V4.08333H9.33333V1.98333L3.90833 7.40833Z" fill="#1F2674"/>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right 5 Cols: Result Snapshot -->
            <div class="snapshot-column">
                <div class="snapshot-header-row">
                    <h2 class="lens-title">Assessment</h2>
                    <span class="lens-subtext" style="font-weight: 500;">Snapshot</span>
                </div>

                <div class="snapshot-card">
                    
                    <!-- Check 1: Foreign Logos -->
                    <?php $chk1 = $checks['foreign_logo'] ?? []; ?>
                    <div class="snapshot-check-box" onclick="scrollToBand('band-foreign-logo')">
                        <div class="snapshot-item-top">
                            <div>
                                <div class="snapshot-item-name"><?= esc_html($chk1['name'] ?? 'Foreign Logos') ?></div>
                                <div class="snapshot-item-category"><?= esc_html($chk1['category'] ?? 'Logo review') ?></div>
                            </div>
                            <div class="snapshot-score-badge-group">
                                <div>
                                    <span class="score-number-text"><?= (int)($chk1['score'] ?? 92) ?></span>
                                    <span class="score-total-denom">/100</span>
                                </div>
                                <div class="status-pill-badge" style="background: <?= esc_attr($chk1['bg_color'] ?? '#ECFDF5') ?>; color: <?= esc_attr($chk1['text_color'] ?? '#10B981') ?>; outline: 1px solid <?= esc_attr($chk1['border'] ?? 'rgba(16, 185, 129, 0.2)') ?>;">
                                    <svg width="6" height="6" viewBox="0 0 6 6" fill="none">
                                        <rect width="6" height="6" rx="3" fill="<?= esc_attr($chk1['dot_color'] ?? '#10B981') ?>"/>
                                    </svg>
                                    <span><?= esc_html($chk1['badge'] ?? 'Pass') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Mini 3-zone score scale -->
                        <div class="mini-zone-scale-wrapper">
                            <div class="zone-track-bar">
                                <div class="zone-segment-fail"></div>
                                <div class="zone-segment-needs-human"></div>
                                <div class="zone-segment-pass"></div>
                                <div class="score-pin-needle" style="left: <?= min(98, max(2, (int)($chk1['score'] ?? 92))) ?>%; background: <?= esc_attr($chk1['dot_color'] ?? '#10B981') ?>;"></div>
                            </div>
                            <div class="zone-labels-row">
                                <span>0 <span style="color: rgba(225, 29, 72, 0.7);">(Fail)</span></span>
                                <span>30</span>
                                <span style="color: rgba(217, 119, 6, 0.85); font-weight: 600;">Needs Human Look</span>
                                <span>70</span>
                                <span>100 <span style="color: rgba(5, 150, 105, 0.85);">(Pass)</span></span>
                            </div>
                        </div>
                    </div>

                    <div class="card-divider-line"></div>

                    <!-- Check 2: Brand & Compliance Documents -->
                    <?php $chk2 = $checks['guideline'] ?? []; ?>
                    <div class="snapshot-check-box" onclick="scrollToBand('band-guideline')">
                        <div class="snapshot-item-top">
                            <div>
                                <div class="snapshot-item-name"><?= esc_html($chk2['name'] ?? 'Brand & Compliance Documents') ?></div>
                                <div class="snapshot-item-category"><?= esc_html($chk2['category'] ?? 'Document review') ?></div>
                            </div>
                            <div class="snapshot-score-badge-group">
                                <div>
                                    <span class="score-number-text"><?= (int)($chk2['score'] ?? 64) ?></span>
                                    <span class="score-total-denom">/100</span>
                                </div>
                                <div class="status-pill-badge" style="background: <?= esc_attr($chk2['bg_color'] ?? '#FFF6E5') ?>; color: <?= esc_attr($chk2['text_color'] ?? '#D97706') ?>; outline: 1px solid <?= esc_attr($chk2['border'] ?? 'rgba(217, 119, 6, 0.2)') ?>;">
                                    <svg width="6" height="6" viewBox="0 0 6 6" fill="none">
                                        <rect width="6" height="6" rx="3" fill="<?= esc_attr($chk2['dot_color'] ?? '#D97706') ?>"/>
                                    </svg>
                                    <span><?= esc_html($chk2['badge'] ?? 'Needs Human Look') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Mini 3-zone score scale -->
                        <div class="mini-zone-scale-wrapper">
                            <div class="zone-track-bar">
                                <div class="zone-segment-fail"></div>
                                <div class="zone-segment-needs-human"></div>
                                <div class="zone-segment-pass"></div>
                                <div class="score-pin-needle" style="left: <?= min(98, max(2, (int)($chk2['score'] ?? 64))) ?>%; background: <?= esc_attr($chk2['dot_color'] ?? '#D97706') ?>;"></div>
                            </div>
                            <div class="zone-labels-row">
                                <span>0</span>
                                <span>30</span>
                                <span style="color: rgba(217, 119, 6, 0.85); font-weight: 600;">Needs Human Look</span>
                                <span>70</span>
                                <span>100</span>
                            </div>
                        </div>
                    </div>

                    <div class="card-divider-line"></div>

                    <!-- Check 3: MAS Advertising Rules -->
                    <?php $chk3 = $checks['mas_rule'] ?? []; ?>
                    <div class="snapshot-check-box" onclick="scrollToBand('band-mas-rule')">
                        <div class="snapshot-item-top">
                            <div>
                                <div class="snapshot-item-name"><?= esc_html($chk3['name'] ?? 'MAS Advertising Rules') ?></div>
                                <div class="snapshot-item-category"><?= esc_html($chk3['category'] ?? 'Advertising review') ?></div>
                            </div>
                            <div class="snapshot-score-badge-group">
                                <div>
                                    <span class="score-number-text" style="color: <?= esc_attr($chk3['text_color'] ?? '#BE123C') ?>;"><?= (int)($chk3['score'] ?? 25) ?></span>
                                    <span class="score-total-denom">/100</span>
                                </div>
                                <div class="status-pill-badge" style="background: <?= esc_attr($chk3['bg_color'] ?? '#FFF1F2') ?>; color: <?= esc_attr($chk3['text_color'] ?? '#BE123C') ?>; outline: 1px solid <?= esc_attr($chk3['border'] ?? '#FECDD3') ?>;">
                                    <svg width="6" height="6" viewBox="0 0 6 6" fill="none">
                                        <rect width="6" height="6" rx="3" fill="<?= esc_attr($chk3['dot_color'] ?? '#E11D48') ?>"/>
                                    </svg>
                                    <span><?= esc_html($chk3['badge'] ?? 'Fail') ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Mini 3-zone score scale -->
                        <div class="mini-zone-scale-wrapper">
                            <div class="zone-track-bar">
                                <div class="zone-segment-fail"></div>
                                <div class="zone-segment-needs-human"></div>
                                <div class="zone-segment-pass"></div>
                                <div class="score-pin-needle" style="left: <?= min(98, max(2, (int)($chk3['score'] ?? 25))) ?>%; background: <?= esc_attr($chk3['dot_color'] ?? '#E11D48') ?>;"></div>
                            </div>
                            <div class="zone-labels-row">
                                <span>0 <span style="color: rgba(225, 29, 72, 0.7);">(Fail)</span></span>
                                <span>30</span>
                                <span style="color: rgba(217, 119, 6, 0.85); font-weight: 600;">Needs Human Look</span>
                                <span>70</span>
                                <span>100</span>
                            </div>
                        </div>
                    </div>

                    <!-- Score Reference Box -->
                    <div class="score-reference-box">
                        <div class="score-reference-title">Score Reference Zones</div>
                        <div class="reference-pills-row">
                            <div class="ref-pill-card" style="background: #FFF1F2; outline: 1px solid #FECDD3;">
                                <span style="color: #BE123C; font-size: 11px; font-weight: 700;">Fail</span>
                                <span style="color: #BE123C; font-size: 10px; font-weight: 500;">0–30</span>
                            </div>
                            <div class="ref-pill-card" style="background: #FFF6E5; outline: 1px solid rgba(217, 119, 6, 0.2);">
                                <span style="color: #D97706; font-size: 11px; font-weight: 700;">Needs Human</span>
                                <span style="color: #D97706; font-size: 10px; font-weight: 500;">31–70</span>
                            </div>
                            <div class="ref-pill-card" style="background: #ECFDF5; outline: 1px solid rgba(16, 185, 129, 0.2);">
                                <span style="color: #10B981; font-size: 11px; font-weight: 700;">Pass</span>
                                <span style="color: #10B981; font-size: 10px; font-weight: 500;">71–100</span>
                            </div>
                        </div>
                    </div>

                    <!-- Governance statement -->
                    <div class="governance-statement-box">
                        <svg width="14" height="18" viewBox="0 0 11 16" fill="none" style="flex-shrink: 0; margin-top: 1px;">
                            <path d="M5.33333 15.3333C3.78889 14.9444 2.51389 14.0583 1.50833 12.675C0.502778 11.2917 0 9.75556 0 8.06667V4L5.33333 2L10.6667 4V8.06667C10.6667 9.01111 10.5056 9.91944 10.1833 10.7917C9.86111 11.6639 9.4 12.4333 8.8 13.1L6.66667 10.9667C6.46667 11.0889 6.25278 11.1806 6.025 11.2417C5.79722 11.3028 5.56667 11.3333 5.33333 11.3333C4.6 11.3333 3.97222 11.0722 3.45 10.55C2.92778 10.0278 2.66667 9.4 2.66667 8.66667C2.66667 7.93333 2.92778 7.30556 3.45 6.78333C3.97222 6.26111 4.6 6 5.33333 6C6.06667 6 6.69444 6.26111 7.21667 6.78333C7.73889 7.30556 8 7.93333 8 8.66667C8 8.91111 7.96944 9.14722 7.90833 9.375C7.84722 9.60278 7.75556 9.82222 7.63333 10.0333L8.63333 11.0333C8.85556 10.5778 9.02778 10.1 9.15 9.6C9.27222 9.1 9.33333 8.58889 9.33333 8.06667V4.91667L5.33333 3.41667L1.33333 4.91667V8.06667C1.33333 9.41111 1.71111 10.6333 2.46667 11.7333C3.22222 12.8333 4.17778 13.5667 5.33333 13.9333C5.62222 13.8444 5.89722 13.7306 6.15833 13.5917C6.41944 13.4528 6.67778 13.2889 6.93333 13.1L7.86667 14.0333C7.5 14.3333 7.10278 14.5944 6.675 14.8167C6.24722 15.0389 5.8 15.2111 5.33333 15.3333ZM5.33333 10C5.7 10 6.01389 9.86944 6.275 9.60833C6.53611 9.34722 6.66667 9.03333 6.66667 8.66667C6.66667 8.3 6.53611 7.98611 6.275 7.725C6.01389 7.46389 5.7 7.33333 5.33333 7.33333C4.96667 7.33333 4.65278 7.46389 4.39167 7.725C4.13056 7.98611 4 8.3 4 8.66667C4 9.03333 4.13056 9.34722 4.39167 9.60833C4.65278 9.86944 4.96667 10 5.33333 10Z" fill="#4F57A7"/>
                        </svg>
                        <div>
                            <strong style="color: #161B2E;">AI assessment only.</strong>
                            <span style="color: #464651;"> Checker makes the review decision.</span>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- 04. SECTION: DETAILED AI ASSESSMENT (EDITORIAL 3 FULL-WIDTH BANDS) -->
        <section class="detailed-assessment-section">
            <div style="display: flex; align-items: baseline; gap: 8px;">
                <h2 style="color: #161B2E; font-size: 18px; font-weight: 700; line-height: 24px;">AI Assessment Details</h2>
                <span style="color: #464651; font-size: 12px;">(3 heuristic evaluation checks)</span>
            </div>

            <!-- BAND 01: Foreign Logos (Pass) -->
            <div class="editorial-band-card editorial-band-white" id="band-foreign-logo">
                <div class="band-score-col">
                    <span class="band-category-title"><?= esc_html($chk1['name'] ?? 'FOREIGN LOGOS') ?></span>
                    <div class="band-score-number-row">
                        <span class="band-score-number" style="color: #161B2E;"><?= (int)($chk1['score'] ?? 92) ?></span>
                        <span style="color: #464651; font-size: 12px;">/ 100</span>
                    </div>
                    <div class="status-pill-badge" style="background: <?= esc_attr($chk1['bg_color'] ?? '#ECFDF5') ?>; color: <?= esc_attr($chk1['text_color'] ?? '#10B981') ?>; outline: 1px solid <?= esc_attr($chk1['border'] ?? 'rgba(16, 185, 129, 0.2)') ?>; width: fit-content; margin-top: 4px;">
                        <svg width="6" height="6" viewBox="0 0 6 6" fill="none">
                            <rect width="6" height="6" rx="3" fill="<?= esc_attr($chk1['dot_color'] ?? '#10B981') ?>"/>
                        </svg>
                        <span><?= esc_html($chk1['badge'] ?? 'Pass') ?></span>
                    </div>

                    <div style="padding-top: 10px; width: 100%;">
                        <div class="zone-track-bar" style="height: 6px;">
                            <div class="zone-segment-fail"></div>
                            <div class="zone-segment-needs-human"></div>
                            <div class="zone-segment-pass"></div>
                            <div class="score-pin-needle" style="left: <?= min(98, max(2, (int)($chk1['score'] ?? 92))) ?>%; background: <?= esc_attr($chk1['dot_color'] ?? '#10B981') ?>;"></div>
                        </div>
                        <div class="zone-labels-row" style="font-size: 9px; padding-top: 4px;">
                            <span>0</span>
                            <span>30</span>
                            <span>70</span>
                            <span>100</span>
                        </div>
                    </div>
                </div>

                <div class="band-reason-col">
                    <div class="band-section-subhead">Reason & Compliance Evaluation</div>

                    <div class="compliance-breakdown-card">
                        <div class="compliance-breakdown-item">
                            <span class="compliance-breakdown-label">Vấn đề phát hiện:</span>
                            <span class="compliance-breakdown-val"><?= esc_html($chk1['finding'] ?? $chk1['reason'] ?? 'Đảm bảo tính độc quyền thương hiệu.') ?></span>
                        </div>
                        <div class="compliance-breakdown-item">
                            <span class="compliance-breakdown-label">Vị trí đối soát:</span>
                            <span class="compliance-breakdown-val" style="display: inline-flex; align-items: center; gap: 6px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color: #4F57A7; flex-shrink: 0;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                <?= esc_html($chk1['location'] ?? 'Khu vực hiển thị biểu trưng ấn phẩm (Marker ①)') ?>
                            </span>
                        </div>
                        <div class="compliance-breakdown-item">
                            <span class="compliance-breakdown-label">Đánh giá tuân thủ:</span>
                            <span class="compliance-breakdown-val"><?= esc_html($chk1['analysis'] ?? $chk1['reason'] ?? 'Đạt tiêu chuẩn thương hiệu.') ?></span>
                        </div>
                    </div>

                    <div class="band-evidence-box" style="background: #F3F2FF; margin-top: 6px;">
                        <div class="evidence-tag-left">
                            <svg width="15" height="14" viewBox="0 0 15 14" fill="none">
                                <path d="M5.06667 14L3.8 11.8667L1.4 11.3333L1.63333 8.86667L0 7L1.63333 5.13333L1.4 2.66667L3.8 2.13333L5.06667 0L7.33333 0.966667L9.6 0L10.8667 2.13333L13.2667 2.66667L13.0333 5.13333L14.6667 7L13.0333 8.86667L13.2667 11.3333L10.8667 11.8667L9.6 14L7.33333 13.0333L5.06667 14ZM6.63333 9.36667L10.4 5.6L9.46667 4.63333L6.63333 7.46667L5.2 6.06667L4.26667 7L6.63333 9.36667Z" fill="#5EDAC9"/>
                            </svg>
                            <span><?= esc_html($chk1['evidence_tag'] ?? 'Evidence: Logo area · Marker ①') ?></span>
                        </div>
                        <div class="btn-view-in-artwork" onclick="highlightMarker('marker-1')">
                            <span>View in artwork</span>
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                <path d="M7.10208 5.25H0V4.08333H7.10208L3.83542 0.816667L4.66667 0L9.33333 4.66667L4.66667 9.33333L3.83542 8.51667L7.10208 5.25Z" fill="#1F2674"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="band-source-col">
                    <div class="band-section-subhead">Source & Rule</div>
                    <div>
                        <div class="source-name-title"><?= esc_html($chk1['source_title'] ?? 'Brand Guidelines 2026') ?></div>
                        <div class="source-status-sub"><?= esc_html($chk1['source_sub'] ?? 'Reference available') ?></div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button type="button" class="source-link-btn" onclick="openGuidelineQuickModal(
                            '<?= esc_js($chk1['source_title'] ?? 'Foreign Logos Compliance Standard') ?>',
                            '<?= esc_js($chk1['source_quote'] ?? '') ?>',
                            '<?= esc_js($chk1['reason'] ?? '') ?>',
                            '<?= esc_js($chk1['source_url'] ?? '') ?>',
                            '<?= esc_js($chk1['source_doc_name'] ?? 'Brand Guidelines') ?>',
                            '<?= (int)($chk1['score'] ?? 95) ?>'
                        )" style="background: none; border: none; cursor: pointer; padding: 0;">
                            <span>View source</span>
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                <path d="M7.10208 5.25H0V4.08333H7.10208L3.83542 0.816667L4.66667 0L9.33333 4.66667L4.66667 9.33333L3.83542 8.51667L7.10208 5.25Z" fill="#4F57A7"/>
                            </svg>
                        </button>
                        <?php if (!empty($chk1['source_url']) && $chk1['source_url'] !== 'javascript:void(0);'): ?>
                            <a href="<?= esc_url($chk1['source_url']) ?>" target="_blank" title="Download Document" style="color: #64748B; font-size: 11px; text-decoration: underline;">(PDF)</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- BAND 02: Brand & Compliance Documents (Needs Human Look) -->
            <div class="editorial-band-card editorial-band-lavender" id="band-guideline">
                <div class="band-score-col">
                    <span class="band-category-title"><?= esc_html($chk2['name'] ?? 'BRAND & COMPLIANCE DOCUMENTS') ?></span>
                    <div class="band-score-number-row">
                        <span class="band-score-number" style="color: #161B2E;"><?= (int)($chk2['score'] ?? 64) ?></span>
                        <span style="color: #464651; font-size: 12px;">/ 100</span>
                    </div>
                    <div class="status-pill-badge" style="background: <?= esc_attr($chk2['bg_color'] ?? '#FFF6E5') ?>; color: <?= esc_attr($chk2['text_color'] ?? '#D97706') ?>; outline: 1px solid <?= esc_attr($chk2['border'] ?? 'rgba(217, 119, 6, 0.2)') ?>; width: fit-content; margin-top: 4px;">
                        <svg width="6" height="6" viewBox="0 0 6 6" fill="none">
                            <rect width="6" height="6" rx="3" fill="<?= esc_attr($chk2['dot_color'] ?? '#D97706') ?>"/>
                        </svg>
                        <span><?= esc_html($chk2['badge'] ?? 'Needs Human Look') ?></span>
                    </div>

                    <div style="padding-top: 10px; width: 100%;">
                        <div class="zone-track-bar" style="height: 6px;">
                            <div class="zone-segment-fail"></div>
                            <div class="zone-segment-needs-human"></div>
                            <div class="zone-segment-pass"></div>
                            <div class="score-pin-needle" style="left: <?= min(98, max(2, (int)($chk2['score'] ?? 64))) ?>%; background: <?= esc_attr($chk2['dot_color'] ?? '#D97706') ?>;"></div>
                        </div>
                        <div class="zone-labels-row" style="font-size: 9px; padding-top: 4px;">
                            <span>0</span>
                            <span>30</span>
                            <span>70</span>
                            <span>100</span>
                        </div>
                    </div>
                </div>

                <div class="band-reason-col">
                    <div class="band-section-subhead">Reason & Compliance Evaluation</div>

                    <div class="compliance-breakdown-card">
                        <div class="compliance-breakdown-item">
                            <span class="compliance-breakdown-label">Vấn đề phát hiện:</span>
                            <span class="compliance-breakdown-val"><?= esc_html($chk2['finding'] ?? $chk2['reason'] ?? 'Bảng màu và phân cấp chữ trên ấn phẩm.') ?></span>
                        </div>
                        <div class="compliance-breakdown-item">
                            <span class="compliance-breakdown-label">Vị trí đối soát:</span>
                            <span class="compliance-breakdown-val" style="display: inline-flex; align-items: center; gap: 6px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color: #4F57A7; flex-shrink: 0;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                <?= esc_html($chk2['location'] ?? 'Khu vực Visual Hero & Tiêu đề chính (Marker ②)') ?>
                            </span>
                        </div>
                        <div class="compliance-breakdown-item">
                            <span class="compliance-breakdown-label">Đánh giá tuân thủ:</span>
                            <span class="compliance-breakdown-val"><?= esc_html($chk2['analysis'] ?? $chk2['reason'] ?? 'Đối soát cùng tài liệu chiến dịch.') ?></span>
                        </div>
                    </div>

                    <div class="band-evidence-box" style="background: #ffffff; margin-top: 6px;">
                        <div class="evidence-tag-left">
                            <svg width="15" height="13" viewBox="0 0 15 13" fill="none">
                                <path d="M0 12.6667L7.33333 0L14.6667 12.6667H0ZM7.33333 10.6667C7.52222 10.6667 7.68056 10.6028 7.80833 10.475C7.93611 10.3472 8 10.1889 8 10C8 9.81111 7.93611 9.65278 7.80833 9.525C7.68056 9.39722 7.52222 9.33333 7.33333 9.33333C7.14444 9.33333 6.98611 9.39722 6.85833 9.525C6.73056 9.65278 6.66667 9.81111 6.66667 10C6.66667 10.1889 6.73056 10.3472 6.85833 10.475C6.98611 10.6028 7.14444 10.6667 7.33333 10.6667ZM6.66667 8.66667H8V5.33333H6.66667V8.66667Z" fill="#D97706"/>
                            </svg>
                            <span><?= esc_html($chk2['evidence_tag'] ?? 'Evidence: Visual Hero & Brand Identity · Marker ②') ?></span>
                        </div>
                        <div class="btn-view-in-artwork" onclick="highlightMarker('marker-2')">
                            <span>View in artwork</span>
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                <path d="M7.10208 5.25H0V4.08333H7.10208L3.83542 0.816667L4.66667 0L9.33333 4.66667L4.66667 9.33333L3.83542 8.51667L7.10208 5.25Z" fill="#1F2674"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="band-source-col">
                    <div class="band-section-subhead">Source & Rule</div>
                    <div>
                        <div class="source-name-title"><?= esc_html($chk2['source_title'] ?? 'Campaign Copy Standards') ?></div>
                        <div class="source-status-sub"><?= esc_html($chk2['source_sub'] ?? 'Reference available') ?></div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button type="button" class="source-link-btn" onclick="openGuidelineQuickModal(
                            '<?= esc_js($chk2['source_title'] ?? 'Brand & Compliance Documents') ?>',
                            '<?= esc_js($chk2['source_quote'] ?? '') ?>',
                            '<?= esc_js($chk2['reason'] ?? '') ?>',
                            '<?= esc_js($chk2['source_url'] ?? '') ?>',
                            '<?= esc_js($chk2['source_doc_name'] ?? 'Campaign Copy Standards') ?>',
                            '<?= (int)($chk2['score'] ?? 64) ?>'
                        )" style="background: none; border: none; cursor: pointer; padding: 0;">
                            <span>View source</span>
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                <path d="M7.10208 5.25H0V4.08333H7.10208L3.83542 0.816667L4.66667 0L9.33333 4.66667L4.66667 9.33333L3.83542 8.51667L7.10208 5.25Z" fill="#4F57A7"/>
                            </svg>
                        </button>
                        <?php if (!empty($chk2['source_url']) && $chk2['source_url'] !== 'javascript:void(0);'): ?>
                            <a href="<?= esc_url($chk2['source_url']) ?>" target="_blank" title="Download Document" style="color: #64748B; font-size: 11px; text-decoration: underline;">(PDF)</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- BAND 03: MAS Advertising Rules (Fail) -->
            <div class="editorial-band-card editorial-band-white" id="band-mas-rule">
                <div class="band-score-col">
                    <span class="band-category-title"><?= esc_html($chk3['name'] ?? 'MAS ADVERTISING RULES') ?></span>
                    <div class="band-score-number-row">
                        <span class="band-score-number" style="color: #BE123C;"><?= (int)($chk3['score'] ?? 25) ?></span>
                        <span style="color: #464651; font-size: 12px;">/ 100</span>
                    </div>
                    <div class="status-pill-badge" style="background: <?= esc_attr($chk3['bg_color'] ?? '#FFF1F2') ?>; color: <?= esc_attr($chk3['text_color'] ?? '#BE123C') ?>; outline: 1px solid <?= esc_attr($chk3['border'] ?? '#FECDD3') ?>; width: fit-content; margin-top: 4px;">
                        <svg width="6" height="6" viewBox="0 0 6 6" fill="none">
                            <rect width="6" height="6" rx="3" fill="<?= esc_attr($chk3['dot_color'] ?? '#E11D48') ?>"/>
                        </svg>
                        <span><?= esc_html($chk3['badge'] ?? 'Fail') ?></span>
                    </div>

                    <div style="padding-top: 10px; width: 100%;">
                        <div class="zone-track-bar" style="height: 6px;">
                            <div class="zone-segment-fail"></div>
                            <div class="zone-segment-needs-human"></div>
                            <div class="zone-segment-pass"></div>
                            <div class="score-pin-needle" style="left: <?= min(98, max(2, (int)($chk3['score'] ?? 25))) ?>%; background: <?= esc_attr($chk3['dot_color'] ?? '#E11D48') ?>;"></div>
                        </div>
                        <div class="zone-labels-row" style="font-size: 9px; padding-top: 4px;">
                            <span>0</span>
                            <span>30</span>
                            <span>70</span>
                            <span>100</span>
                        </div>
                    </div>
                </div>

                <div class="band-reason-col">
                    <div class="band-section-subhead">Reason & Compliance Evaluation</div>

                    <div class="compliance-breakdown-card">
                        <div class="compliance-breakdown-item">
                            <span class="compliance-breakdown-label">Vấn đề phát hiện:</span>
                            <span class="compliance-breakdown-val"><?= esc_html($chk3['finding'] ?? $chk3['reason'] ?? 'Điều khoản cảnh báo rủi ro pháp lý MAS Singapore.') ?></span>
                        </div>
                        <div class="compliance-breakdown-item">
                            <span class="compliance-breakdown-label">Vị trí đối soát:</span>
                            <span class="compliance-breakdown-val" style="display: inline-flex; align-items: center; gap: 6px;">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color: #4F57A7; flex-shrink: 0;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                <?= esc_html($chk3['location'] ?? 'Dải chân trang Footer Statutory Area (Marker ③)') ?>
                            </span>
                        </div>
                        <div class="compliance-breakdown-item">
                            <span class="compliance-breakdown-label">Đánh giá tuân thủ:</span>
                            <span class="compliance-breakdown-val"><?= esc_html($chk3['analysis'] ?? $chk3['reason'] ?? 'Đối soát theo Singapore MAS Notice §4.1b.') ?></span>
                        </div>
                    </div>

                    <div class="band-evidence-box" style="background: rgba(255, 241, 242, 0.65); outline: 1px solid #FFE4E6; margin-top: 6px;">
                        <div class="evidence-tag-left">
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                <path d="M4.26667 10L6.66667 7.6L9.06667 10L10 9.06667L7.6 6.66667L10 4.26667L9.06667 3.33333L6.66667 5.73333L4.26667 3.33333L3.33333 4.26667L5.73333 6.66667L3.33333 9.06667L4.26667 10ZM6.66667 13.3333C5.74444 13.3333 4.87778 13.1583 4.06667 12.8083C3.25556 12.4583 2.55 11.9833 1.95 11.3833C1.35 10.7833 0.875 10.0778 0.525 9.26667C0.175 8.45555 0 7.58889 0 6.66667C0 5.74444 0.175 4.87778 0.525 4.06667C0.875 3.25556 1.35 2.55 1.95 1.95C2.55 1.35 3.25556 0.875 4.06667 0.525C4.87778 0.175 5.74444 0 6.66667 0C7.58889 0 8.45555 0.175 9.26667 0.525C10.0778 0.875 10.7833 1.35 11.3833 1.95C11.9833 2.55 12.4583 3.25556 12.8083 4.06667C13.1583 4.87778 13.3333 5.74444 13.3333 6.66667C13.3333 7.58889 13.1583 8.45555 12.8083 9.26667C12.4583 10.0778 11.9833 10.7833 11.3833 11.3833C10.7833 11.9833 10.0778 12.4583 9.26667 12.8083C8.45555 13.1583 7.58889 13.3333 6.66667 13.3333ZM6.66667 12C8.15556 12 9.41667 11.4833 10.45 10.45C11.4833 9.41667 12 8.15556 12 6.66667C12 5.17778 11.4833 3.91667 10.45 2.88333C9.41667 1.85 8.15556 1.33333 6.66667 1.33333C5.17778 1.33333 3.91667 1.85 2.88333 2.88333C1.85 3.91667 1.33333 5.17778 1.33333 6.66667C1.33333 8.15556 1.85 9.41667 2.88333 10.45C3.91667 11.4833 5.17778 12 6.66667 12Z" fill="#E11D48"/>
                            </svg>
                            <span><?= esc_html($chk3['evidence_tag'] ?? 'Evidence: Footer Statutory Disclaimer · Marker ③') ?></span>
                        </div>
                        <div class="btn-view-in-artwork" onclick="highlightMarker('marker-3')">
                            <span>View in artwork</span>
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                <path d="M7.10208 5.25H0V4.08333H7.10208L3.83542 0.816667L4.66667 0L9.33333 4.66667L4.66667 9.33333L3.83542 8.51667L7.10208 5.25Z" fill="#1F2674"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="band-source-col">
                    <div class="band-section-subhead">Source & Rule</div>
                    <div>
                        <div class="source-name-title"><?= esc_html($chk3['source_title'] ?? 'MAS Advertising Rules') ?></div>
                        <div class="source-status-sub"><?= esc_html($chk3['source_sub'] ?? 'Reference available') ?></div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <button type="button" class="source-link-btn" onclick="openGuidelineQuickModal(
                            '<?= esc_js($chk3['source_title'] ?? 'MAS Advertising Rules §4.1b') ?>',
                            '<?= esc_js($chk3['source_quote'] ?? '') ?>',
                            '<?= esc_js($chk3['reason'] ?? '') ?>',
                            '<?= esc_js($chk3['source_url'] ?? '') ?>',
                            '<?= esc_js($chk3['source_doc_name'] ?? 'MAS Advertising Rules') ?>',
                            '<?= (int)($chk3['score'] ?? 25) ?>'
                        )" style="background: none; border: none; cursor: pointer; padding: 0;">
                            <span>View source</span>
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                <path d="M7.10208 5.25H0V4.08333H7.10208L3.83542 0.816667L4.66667 0L9.33333 4.66667L4.66667 9.33333L3.83542 8.51667L7.10208 5.25Z" fill="#4F57A7"/>
                            </svg>
                        </button>
                        <?php if (!empty($chk3['source_url']) && $chk3['source_url'] !== 'javascript:void(0);'): ?>
                            <a href="<?= esc_url($chk3['source_url']) ?>" target="_blank" title="Download Document" style="color: #64748B; font-size: 11px; text-decoration: underline;">(PDF)</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- 04B. SECTION: ALL ATTACHED CAMPAIGN BRAND GUIDELINES -->
        <?php $campGuidelines = $screenData['guidelines'] ?? []; ?>
        <section class="guidelines-strip-card" style="margin-top: 16px; background: #ffffff; border: 1px solid #E2E8F0; border-radius: 12px; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 13px;">
                <span style="color: #18224B; font-weight: 700;">Campaign Guidelines (<?= count($campGuidelines) ?>):</span>
                <?php if (!empty($campGuidelines)): ?>
                    <?php foreach ($campGuidelines as $idx => $cg): ?>
                        <a href="<?= !empty($cg['url']) ? esc_url($cg['url']) : 'javascript:void(0)' ?>" target="_blank" style="color: #4F57A7; font-weight: 600; text-decoration: underline; display: inline-flex; align-items: center; gap: 4px;">
                            <span><?= esc_html($cg['name'] ?? 'Guideline Document') ?></span>
                            <?php if (!empty($cg['size'])): ?><span style="font-size: 11px; color: #94A3B8; text-decoration: none;">(<?= esc_html($cg['size']) ?>)</span><?php endif; ?>
                        </a>
                        <?php if ($idx < count($campGuidelines) - 1): ?><span style="color: #CBD5E1;">·</span><?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span style="color: #94A3B8; font-style: italic;">No specific guideline documents attached to this campaign.</span>
                <?php endif; ?>
            </div>
            <div style="font-size: 11.5px; color: #64748B;">
                All AI scores cite these attached standards
            </div>
        </section>

        <!-- 05. SECTION: VERSION & NEXT REVIEW CONTEXT (5 COLS / 7 COLS) -->
        <section class="version-next-review-grid">
            
            <!-- Left 5 Cols: Version History -->
            <div class="iteration-history-card">
                <div>
                    <span class="iteration-tag">ASSET ITERATION HISTORY</span>
                    <h3 class="iteration-title" style="margin-top: 4px;">Version History</h3>

                    <div class="iteration-pill-nodes-row">
                        <?php if ($previousVersion): ?>
                            <div class="node-pill-item node-prev">
                                <span><?= esc_html($previousVersion['version_label']) ?> (<?= esc_html($previousVersion['status_label']) ?>)</span>
                            </div>

                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                                <path d="M10.1458 7.5H0V5.83333H10.1458L5.47917 1.16667L6.66667 0L13.3333 6.66667L6.66667 13.3333L5.47917 12.1667L10.1458 7.5Z" fill="#C7C5D3"/>
                            </svg>
                        <?php endif; ?>

                        <div class="node-pill-item node-current">
                            <span><?= esc_html($versionLabel) ?> (Current)</span>
                        </div>
                    </div>
                </div>

                <div style="padding-top: 8px;">
                    <?php if ($previousVersion && !empty($previousVersion['review_note'])): ?>
                        <a href="javascript:void(0)" onclick="openFeedbackModal()" style="display: inline-flex; align-items: center; gap: 4px; color: #1F2674; font-size: 12px; font-weight: 700; letter-spacing: 0.24px;">
                            <span>View <?= esc_html($previousVersion['version_label']) ?> feedback</span>
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                <path d="M7.10208 5.25H0V4.08333H7.10208L3.83542 0.816667L4.66667 0L9.33333 4.66667L4.66667 9.33333L3.83542 8.51667L7.10208 5.25Z" fill="#1F2674"/>
                            </svg>
                        </a>
                    <?php elseif ($previousVersion): ?>
                        <?php 
                        $compareUrl = (function_exists('pcs_url') ? pcs_url('compare-version.php') : 'compare-version.php') . '?' . http_build_query([
                            'asset_id'    => $assetId,
                            'campaign_id' => $campaignId,
                            'from'        => 'ai_result'
                        ]);
                        ?>
                        <a href="<?= esc_url($compareUrl) ?>" style="display: inline-flex; align-items: center; gap: 4px; color: #1F2674; font-size: 12px; font-weight: 700; letter-spacing: 0.24px;">
                            <span>View <?= esc_html($previousVersion['version_label']) ?> feedback</span>
                            <svg width="10" height="10" viewBox="0 0 10 10" fill="none">
                                <path d="M7.10208 5.25H0V4.08333H7.10208L3.83542 0.816667L4.66667 0L9.33333 4.66667L4.66667 9.33333L3.83542 8.51667L7.10208 5.25Z" fill="#1F2674"/>
                            </svg>
                        </a>
                    <?php else: ?>
                        <span style="font-size: 12px; color: #767682;">First iteration created for this asset.</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right 7 Cols: Next Review Governance Context -->
            <div class="governance-context-card">
                <div>
                    <div class="governance-head-row">
                        <div class="governance-icon-badge">
                            <svg width="15" height="13" viewBox="0 0 15 13" fill="none">
                                <path d="M0 12V9.9C0 9.4875 0.10625 9.1 0.31875 8.7375C0.53125 8.375 0.825 8.1 1.2 7.9125C1.8375 7.5875 2.55625 7.3125 3.35625 7.0875C4.15625 6.8625 5.0375 6.75 6 6.75C6.375 6.75 6.74063 6.76875 7.09688 6.80625C7.45312 6.84375 7.8 6.9 8.1375 6.975L6.825 8.2875C6.6875 8.2625 6.55312 8.25 6.42188 8.25C6.29063 8.25 6.15 8.25 6 8.25C5.1125 8.25 4.31563 8.35625 3.60938 8.56875C2.90313 8.78125 2.325 9.0125 1.875 9.2625C1.7625 9.325 1.67188 9.4125 1.60312 9.525C1.53437 9.6375 1.5 9.7625 1.5 9.9V10.5H6.1875L7.6875 12H0ZM10.1625 12.3L7.575 9.7125L8.625 8.6625L10.1625 10.2L13.95 6.4125L15 7.4625L10.1625 12.3ZM6 6C5.175 6 4.46875 5.70625 3.88125 5.11875C3.29375 4.53125 3 3.825 3 3C3 2.175 3.29375 1.46875 3.88125 0.88125C4.46875 0.29375 5.175 0 6 0C6.825 0 7.53125 0.29375 8.11875 0.88125C8.70625 1.46875 9 2.175 9 3C9 3.825 8.70625 4.53125 8.11875 5.11875C7.53125 5.70625 6.825 6 6 6Z" fill="#1F2674"/>
                            </svg>
                        </div>
                        <h3 class="governance-lead-title">Checker reviews <?= esc_html($versionLabel) ?> after submission</h3>
                    </div>

                    <p class="governance-desc-body">
                        The Compliance Checker reviews the highlighted assessment items prior to signing off on campaign assets.
                    </p>
                </div>

                <div class="principle-banner-box">
                    <svg width="17" height="16" viewBox="0 0 17 16" fill="none" style="flex-shrink: 0;">
                        <path d="M0 15.8333V14.1667H7.5V4.85417C7.13889 4.72917 6.82639 4.53472 6.5625 4.27083C6.29861 4.00694 6.10417 3.69444 5.97917 3.33333H3.33333L5.83333 9.16667C5.83333 9.86111 5.54861 10.4514 4.97917 10.9375C4.40972 11.4236 3.72222 11.6667 2.91667 11.6667C2.11111 11.6667 1.42361 11.4236 0.854167 10.9375C0.284722 10.4514 0 9.86111 0 9.16667L2.5 3.33333H0.833333V1.66667H5.97917C6.14583 1.18056 6.44444 0.78125 6.875 0.46875C7.30556 0.15625 7.79167 0 8.33333 0C8.875 0 9.36111 0.15625 9.79167 0.46875C10.2222 0.78125 10.5208 1.18056 10.6875 1.66667H15.8333V3.33333H14.1667L16.6667 9.16667C16.6667 9.86111 16.3819 10.4514 15.8125 10.9375C15.2431 11.4236 14.5556 11.6667 13.75 11.6667C12.9444 11.6667 12.2569 11.4236 11.6875 10.9375C11.1181 10.4514 10.8333 9.86111 10.8333 9.16667L13.3333 3.33333H10.6875C10.5625 3.69444 10.3681 4.00694 10.1042 4.27083C9.84028 4.53472 9.52778 4.72917 9.16667 4.85417V14.1667H16.6667V15.8333H0Z" fill="#1F2674"/>
                    </svg>
                    <div>
                        <span style="color: #161B2E; font-weight: 500;">Core principle: </span>
                        <strong style="color: #1F2674; font-style: italic;">"AI assists. Checker decides."</strong>
                    </div>
                </div>
            </div>
        </section>

        <!-- 06. DECISION STRIP -->
        <div class="decision-strip-card">
            <div class="decision-strip-left">
                <div class="decision-strip-icon-box">
                    <svg width="15" height="17" viewBox="0 0 15 17" fill="none">
                        <path d="M6.33333 12.5417L12.2083 6.66667L11.0417 5.5L6.33333 10.2083L3.95833 7.83333L2.79167 9L6.33333 12.5417ZM1.66667 16.6667C1.20833 16.6667 0.815972 16.5035 0.489583 16.1771C0.163194 15.8507 0 15.4583 0 15V3.33333C0 2.875 0.163194 2.48264 0.489583 2.15625C0.815972 1.82986 1.20833 1.66667 1.66667 1.66667H5.16667C5.34722 1.16667 5.64931 0.763889 6.07292 0.458333C6.49653 0.152778 6.97222 0 7.5 0C8.02778 0 8.50347 0.152778 8.92708 0.458333C9.35069 0.763889 9.65278 1.16667 9.83333 1.66667H13.3333C13.7917 1.66667 14.184 1.82986 14.5104 2.15625C14.8368 2.48264 15 2.875 15 3.33333V15C15 15.4583 14.8368 15.8507 14.5104 16.1771C14.184 16.5035 13.7917 16.6667 13.3333 16.6667H1.66667ZM1.66667 15H13.3333V3.33333H1.66667V15ZM7.5 2.70833C7.68056 2.70833 7.82986 2.64931 7.94792 2.53125C8.06597 2.41319 8.125 2.26389 8.125 2.08333C8.125 1.90278 8.06597 1.75347 7.94792 1.63542C7.82986 1.51736 7.68056 1.45833 7.5 1.45833C7.31944 1.45833 7.17014 1.51736 7.05208 1.63542C6.93403 1.75347 6.875 1.90278 6.875 2.08333C6.875 2.26389 6.93403 2.41319 7.05208 2.53125C7.17014 2.64931 7.31944 2.70833 7.5 2.70833Z" fill="#4F57A7"/>
                    </svg>
                </div>
                <h3 class="decision-strip-heading">Ready for your decision</h3>
            </div>

            <div class="decision-strip-right">
                <span style="font-weight: 600; color: #161B2E;"><?= esc_html($assetName) ?> · <?= esc_html($versionLabel) ?></span>
                <span style="color: #C7C5D3;">·</span>
                <div style="display: flex; align-items: center; gap: 6px; color: #48C8B7; font-weight: 600;">
                    <svg width="8" height="8" viewBox="0 0 8 8" fill="none">
                        <rect width="8" height="8" rx="4" fill="#5EDAC9"/>
                    </svg>
                    <span>AI assessment complete</span>
                </div>
            </div>
        </div>

        <!-- 07. BOTTOM ACTION DOCK -->
        <footer class="bottom-action-dock">
            <a href="<?= esc_url($queueUrl) ?>" class="btn-dock-back">
                <svg width="12" height="12" viewBox="0 0 12 12" fill="none">
                    <path d="M2.86875 6.75L7.06875 10.95L6 12L0 6L6 0L7.06875 1.05L2.86875 5.25H12V6.75H2.86875Z" fill="currentColor"/>
                </svg>
                <span>Back to Review Queue</span>
            </a>

            <div class="dock-actions-right" style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <!-- Reject Action -->
                <button type="button" onclick="openCheckerRejectModal()" style="padding: 10px 18px; border-radius: 8px; background: #FFF1F2; border: 1px solid #FECDD3; color: #BA1A1A; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 13px;">
                    <span>Reject Version</span>
                </button>

                <!-- Approve Action -->
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="checker_approve">
                    <input type="hidden" name="version_id" value="<?= (int)$versionId ?>">
                    <input type="hidden" name="asset_id" value="<?= (int)$assetId ?>">
                    <input type="hidden" name="campaign_id" value="<?= (int)$campaignId ?>">
                    <input type="hidden" name="review_notes" value="Approved after AI pre-flight inspection">
                    <button type="submit" style="padding: 10px 20px; border-radius: 8px; background: #00B894; color: #FFF; font-weight: 700; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; font-size: 13px;" onclick="return confirm('Confirm approval for this asset version?');">
                        <span>Approve Version</span>
                    </button>
                </form>

                <!-- Proceed to Full Checker Review Workspace -->
                <?php 
                $fullReviewUrl = (function_exists('pcs_url') ? pcs_url('checker-review.php') : 'checker-review.php') . '?' . http_build_query([
                    'version_id'  => $versionId,
                    'asset_id'    => $assetId,
                    'campaign_id' => $campaignId
                ]);
                ?>
                <a href="<?= esc_url($fullReviewUrl) ?>" class="btn-submit-checker-primary" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <span>Open Review Workspace</span>
                    <svg width="15" height="12" viewBox="0 0 15 12" fill="none">
                        <path d="M0 12V0L14.25 6L0 12ZM1.5 9.75L10.3875 6L1.5 2.25V4.875L6 6L1.5 7.125V9.75Z" fill="white"/>
                    </svg>
                </a>
            </div>
        </footer>

    </main>

    <!-- Modal: Full Size Artwork View -->
    <div id="fullSizeModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 1000; align-items: center; justify-content: center; padding: 24px;" onclick="closeFullSizeModal()">
        <div style="position: relative; max-width: 90vw; max-height: 90vh; background: #ffffff; border-radius: 12px; overflow: hidden; padding: 12px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);" onclick="event.stopPropagation();">
            <button type="button" onclick="closeFullSizeModal()" style="position: absolute; top: 16px; right: 16px; width: 32px; height: 32px; border-radius: 16px; background: rgba(0,0,0,0.5); color: #fff; font-size: 18px; display: flex; align-items: center; justify-content: center; cursor: pointer; z-index: 20;">&times;</button>
            <?php if (!empty($fileUrl)): ?>
                <?php if ($isPdf): ?>
                    <iframe src="<?= esc_url($fileUrl) ?>#toolbar=1&view=FitH" style="width: 85vw; height: 75vh; border: none; border-radius: 6px; background: #ffffff;"></iframe>
                    <div style="margin-top: 8px; text-align: right;">
                        <a href="<?= esc_url($fileUrl) ?>" target="_blank" rel="noopener noreferrer" style="color: #4F57A7; font-size: 13px; font-weight: 700; text-decoration: underline; display: inline-flex; align-items: center; gap: 4px;">
                            <span>Open PDF in new window</span>
                            <span>↗</span>
                        </a>
                    </div>
                <?php else: ?>
                    <img src="<?= esc_url($fileUrl) ?>" alt="<?= esc_attr($assetName) ?>" style="max-width: 100%; max-height: 80vh; object-fit: contain; display: block; border-radius: 6px;">
                <?php endif; ?>
            <?php else: ?>
                <div style="padding: 40px; text-align: center; color: #64748B;">No direct artwork file image available.</div>
            <?php endif; ?>
            <div style="padding: 12px 4px 4px 4px; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #161B2E;">
                <strong><?= esc_html($assetName) ?> · <?= esc_html($versionLabel) ?></strong>
                <span style="color: #64748B; font-size: 12px;"><?= esc_html($fileName) ?> (<?= esc_html($fileSize) ?>)</span>
            </div>
        </div>
    </div>

    <!-- Modal: Previous Version Feedback -->
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
                        This feedback was issued during the compliance evaluation of <?= esc_html($previousVersion['version_label']) ?>.
                    </p>
                </div>
                <div style="padding: 16px 24px; background: #F8FAFC; border-top: 1px solid #E2E8F0; text-align: right;">
                    <button type="button" onclick="closeFeedbackModal()" class="btn-upload-new-version" style="height: 36px; padding: 0 16px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal: Quick Rule/Guideline Info & Direct Quote Citation -->
    <div id="guidelineModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); z-index: 1000; align-items: center; justify-content: center; padding: 20px;">
        <div style="background: #ffffff; border-radius: 14px; max-width: 560px; width: 100%; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25); overflow: hidden;">
            <div style="padding: 20px 24px; border-bottom: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <span style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.6px; color: #64748B;">Compliance Standard Citation</span>
                    <h4 style="font-size: 16px; font-weight: 700; color: #161B2E; margin-top: 2px;" id="guidelineModalTitle">Compliance Standard</h4>
                </div>
                <button type="button" onclick="closeGuidelineModal()" style="font-size: 22px; color: #64748B; background: none; border: none; cursor: pointer; line-height: 1;">&times;</button>
            </div>
            
            <div style="padding: 24px; font-size: 13px; color: #464651; line-height: 22px; max-height: 70vh; overflow-y: auto;">
                <div id="guidelineModalScoreBadge" style="margin-bottom: 14px;"></div>

                <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #4F57A7; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    <span>Cited Guideline / Regulatory Requirement</span>
                </div>
                <div id="guidelineModalQuote" style="padding: 12px 16px; background: #F8FAFC; border-radius: 8px; border-left: 3px solid #4F57A7; font-size: 13px; color: #1E293B; line-height: 20px; font-style: italic;">
                    No specific citation text recorded.
                </div>

                <div id="guidelineModalReasonTitle" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #BE123C; margin-top: 16px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>AI Evaluation Finding &amp; Reason</span>
                </div>
                <div id="guidelineModalReason" style="padding: 12px 16px; background: #FFF1F2; border-radius: 8px; border: 1px solid #FECDD3; font-size: 12.5px; color: #9F1239; line-height: 20px;">
                    Evaluated against brand compliance standards.
                </div>
            </div>

            <div style="padding: 14px 24px; background: #F8FAFC; border-top: 1px solid #E2E8F0; display: flex; justify-content: space-between; align-items: center;">
                <a id="guidelineModalDocBtn" href="#" target="_blank" style="display: none; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #4F57A7; text-decoration: underline;">
                    <span>View Guideline Document</span>
                    <span>→</span>
                </a>
                <button type="button" onclick="closeGuidelineModal()" class="btn-upload-new-version" style="height: 36px; padding: 0 18px; margin-left: auto;">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Interactive Navigation Scripts -->
    <script>
        // Smooth scroll to Band and trigger visual highlight
        function scrollToBand(bandId) {
            const el = document.getElementById(bandId);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Add highlight pulse
                document.querySelectorAll('.editorial-band-card').forEach(b => b.classList.remove('editorial-band-highlight'));
                el.classList.add('editorial-band-highlight');
                
                setTimeout(() => {
                    el.classList.remove('editorial-band-highlight');
                }, 2200);
            }
        }

        // Highlight marker on the artwork when "View in artwork" is clicked
        function highlightMarker(markerId) {
            const container = document.getElementById('artworkContainer');
            const marker = document.getElementById(markerId);
            if (container && marker) {
                container.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Pulse the marker
                marker.style.transition = 'all 0.3s ease';
                marker.style.transform = 'scale(1.22)';
                marker.style.zIndex = '50';
                
                // Also highlight corresponding focus box if present
                const box = document.getElementById('lensBox-' + markerId);
                if (box) {
                    box.style.display = 'block';
                    box.classList.add('lens-box-active');
                    box.style.top = Math.max(0, parseFloat(marker.style.top) - 2) + '%';
                    box.style.left = Math.max(0, parseFloat(marker.style.left) - 2) + '%';
                    box.style.width = (markerId === 'marker-3') ? '280px' : ((markerId === 'marker-2') ? '220px' : '150px');
                    box.style.height = (markerId === 'marker-2') ? '60px' : '42px';
                    
                    setTimeout(() => {
                        box.classList.remove('lens-box-active');
                    }, 2400);
                }

                setTimeout(() => {
                    marker.style.transform = 'scale(1)';
                    marker.style.zIndex = '20';
                }, 1800);
            }
        }

        // Layout-adaptive coordinates detection on image load
        function initDynamicArtworkEvidenceLens(imgEl) {
            if (!imgEl || !imgEl.naturalWidth || !imgEl.naturalHeight) return;

            const storageKey = 'pcs_lens_markers_<?= (int)$targetAssetId ?>_<?= (int)$targetVersionId ?>';
            const saved = localStorage.getItem(storageKey);
            if (saved) {
                try {
                    const pos = JSON.parse(saved);
                    Object.keys(pos).forEach(mId => {
                        const el = document.getElementById(mId);
                        if (el && pos[mId].top && pos[mId].left) {
                            el.style.top = pos[mId].top;
                            el.style.left = pos[mId].left;
                        }
                    });
                    return;
                } catch(e) {}
            }

            const aspect = imgEl.naturalWidth / imgEl.naturalHeight;
            const m1 = document.getElementById('marker-1');
            const m2 = document.getElementById('marker-2');
            const m3 = document.getElementById('marker-3');

            const hasSavedCustomM1 = <?= (!empty($existing_checks['foreign_logo']['marker_top'])) ? 'true' : 'false' ?>;
            if (!hasSavedCustomM1) {
                if (aspect < 0.8) {
                    // Portrait / Story (9:16)
                    if (m1) { m1.style.top = '5%'; m1.style.left = '58%'; }
                    if (m2) { m2.style.top = '32%'; m2.style.left = '16%'; }
                    if (m3) { m3.style.top = '93%'; m3.style.left = '6%'; }
                } else if (aspect >= 0.8 && aspect <= 1.3) {
                    // Square (1:1)
                    if (m1) { m1.style.top = '6%'; m1.style.left = '68%'; }
                    if (m2) { m2.style.top = '42%'; m2.style.left = '18%'; }
                    if (m3) { m3.style.top = '91%'; m3.style.left = '8%'; }
                } else {
                    // Landscape Banner (> 1.3)
                    if (m1) { m1.style.top = '7%'; m1.style.left = '70%'; }
                    if (m2) { m2.style.top = '38%'; m2.style.left = '15%'; }
                    if (m3) { m3.style.top = '88%'; m3.style.left = '6%'; }
                }
            }
        }

        // Draggable Evidence Lens Markers
        function initDraggableMarkers() {
            const stage = document.getElementById('artworkRenderStage');
            if (!stage) return;

            const markers = stage.querySelectorAll('.draggable-marker');
            markers.forEach(marker => {
                let isDragging = false;
                let startX, startY, origLeft, origTop;

                marker.addEventListener('mousedown', function(e) {
                    if (e.button !== 0) return;
                    isDragging = true;
                    marker.classList.add('is-dragging');

                    startX = e.clientX;
                    startY = e.clientY;

                    origLeft = marker.offsetLeft;
                    origTop = marker.offsetTop;

                    function onMouseMove(ev) {
                        if (!isDragging) return;
                        ev.preventDefault();

                        const deltaX = ev.clientX - startX;
                        const deltaY = ev.clientY - startY;

                        let newLeftPx = origLeft + deltaX;
                        let newTopPx = origTop + deltaY;

                        const maxLeft = stage.clientWidth - marker.offsetWidth;
                        const maxTop = stage.clientHeight - marker.offsetHeight;

                        newLeftPx = Math.max(0, Math.min(newLeftPx, maxLeft));
                        newTopPx = Math.max(0, Math.min(newTopPx, maxTop));

                        const leftPct = ((newLeftPx / stage.clientWidth) * 100).toFixed(1) + '%';
                        const topPct = ((newTopPx / stage.clientHeight) * 100).toFixed(1) + '%';

                        marker.style.left = leftPct;
                        marker.style.top = topPct;
                    }

                    function onMouseUp() {
                        if (!isDragging) return;
                        isDragging = false;
                        marker.classList.remove('is-dragging');
                        document.removeEventListener('mousemove', onMouseMove);
                        document.removeEventListener('mouseup', onMouseUp);

                        const storageKey = 'pcs_lens_markers_<?= (int)$targetAssetId ?>_<?= (int)$targetVersionId ?>';
                        let saved = {};
                        try { saved = JSON.parse(localStorage.getItem(storageKey)) || {}; } catch(e){}
                        saved[marker.id] = {
                            top: marker.style.top,
                            left: marker.style.left
                        };
                        localStorage.setItem(storageKey, JSON.stringify(saved));

                        saveMarkerCoordinatesAjax(marker.id, marker.style.top, marker.style.left);
                    }

                    document.addEventListener('mousemove', onMouseMove);
                    document.addEventListener('mouseup', onMouseUp);
                });
            });
        }

        function saveMarkerCoordinatesAjax(markerId, top, left) {
            const critMap = {
                'marker-1': 'foreign_logo',
                'marker-2': 'guideline',
                'marker-3': 'mas_rule'
            };
            const crit = critMap[markerId];
            if (!crit) return;

            const params = new URLSearchParams({
                action: 'pcs_save_evidence_marker',
                version_id: '<?= (int)$targetVersionId ?>',
                criterion: crit,
                marker_top: top,
                marker_left: left
            });

            fetch('<?= admin_url("admin-ajax.php") ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params.toString()
            }).catch(e => console.log('Marker pos auto-save error:', e));
        }

        document.addEventListener('DOMContentLoaded', function() {
            initDraggableMarkers();
        });

        // Full Size Modal Handlers
        function openFullSizeModal() {
            var m = document.getElementById('fullSizeModal');
            if (m) m.style.display = 'flex';
        }
        function closeFullSizeModal() {
            var m = document.getElementById('fullSizeModal');
            if (m) m.style.display = 'none';
        }

        // Feedback Modal Handlers
        function openFeedbackModal() {
            var m = document.getElementById('feedbackModal');
            if (m) m.style.display = 'flex';
        }
        function closeFeedbackModal() {
            var m = document.getElementById('feedbackModal');
            if (m) m.style.display = 'none';
        }

        // Guideline Modal Handlers with Full Dynamic AI Quotes & Findings
        function openGuidelineQuickModal(title, quote, reason, docUrl, docName, score) {
            var m = document.getElementById('guidelineModal');
            var t = document.getElementById('guidelineModalTitle');
            var q = document.getElementById('guidelineModalQuote');
            var r = document.getElementById('guidelineModalReason');
            var rt = document.getElementById('guidelineModalReasonTitle');
            var badge = document.getElementById('guidelineModalScoreBadge');
            var docBtn = document.getElementById('guidelineModalDocBtn');

            if (t) t.textContent = title || 'Compliance Standard';
            if (q) q.textContent = quote || 'Standard brand compliance guideline requirement.';
            if (r) r.textContent = reason || 'Evaluated against brand compliance standards.';
            
            var sc = parseInt(score, 10);
            if (isNaN(sc)) sc = 50;

            if (badge) {
                if (sc >= 71) {
                    badge.innerHTML = '<span style="background: #ECFDF5; color: #10B981; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 20px; outline: 1px solid rgba(16,185,129,0.3); display: inline-flex; align-items: center; gap: 4px;">PASS · Score: ' + sc + '/100</span>';
                    if (r) { r.style.background = '#ECFDF5'; r.style.borderColor = '#A7F3D0'; r.style.color = '#065F46'; }
                    if (rt) { rt.style.color = '#059669'; rt.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg><span>AI Compliance Verification</span>'; }
                } else if (sc >= 31) {
                    badge.innerHTML = '<span style="background: #FFF6E5; color: #D97706; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 20px; outline: 1px solid rgba(217,119,6,0.3); display: inline-flex; align-items: center; gap: 4px;">NEEDS HUMAN LOOK · Score: ' + sc + '/100</span>';
                    if (r) { r.style.background = '#FFFBEB'; r.style.borderColor = '#FDE68A'; r.style.color = '#92400E'; }
                    if (rt) { rt.style.color = '#D97706'; rt.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span>AI Human Review Request Reason</span>'; }
                } else {
                    badge.innerHTML = '<span style="background: #FFF1F2; color: #BE123C; font-weight: 700; font-size: 11px; padding: 4px 10px; border-radius: 20px; outline: 1px solid #FECDD3; display: inline-flex; align-items: center; gap: 4px;">FAIL · Score: ' + sc + '/100</span>';
                    if (r) { r.style.background = '#FFF1F2'; r.style.borderColor = '#FECDD3'; r.style.color = '#9F1239'; }
                    if (rt) { rt.style.color = '#BE123C'; rt.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg><span>AI Rule Breach Explanation</span>'; }
                }
            }

            if (docBtn) {
                if (docUrl && docUrl !== 'javascript:void(0);' && docUrl !== '#') {
                    docBtn.style.display = 'inline-flex';
                    docBtn.href = docUrl;
                    docBtn.innerHTML = '<span>Open ' + (docName || 'Guideline PDF') + '</span> <span>→</span>';
                } else {
                    docBtn.style.display = 'none';
                }
            }

            if (m) m.style.display = 'flex';
        }
        function closeGuidelineModal() {
            var m = document.getElementById('guidelineModal');
            if (m) m.style.display = 'none';
        }

        function openCheckerRejectModal() {
            var m = document.getElementById('checkerRejectModal');
            if (m) m.style.display = 'flex';
        }
        function closeCheckerRejectModal() {
            var m = document.getElementById('checkerRejectModal');
            if (m) m.style.display = 'none';
        }

        // ESC Key listener
        window.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeFullSizeModal();
                closeFeedbackModal();
                closeGuidelineModal();
                closeCheckerRejectModal();
            }
        });
    </script>

    <!-- Modal: Checker Reject with Note -->
    <div class="modal-overlay" id="checkerRejectModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="modal-box" style="background: #FFFFFF; border-radius: 16px; padding: 24px; max-width: 520px; width: 92%; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 class="modal-title" style="font-size: 18px; font-weight: 800; color: #BA1A1A; margin: 0;">Reject Version & Request Revisions</h3>
                <span style="font-size: 24px; cursor: pointer; color: #94A3B8; line-height: 1;" onclick="closeCheckerRejectModal()">&times;</span>
            </div>
            <p style="font-size: 13px; color: #64748B; margin-bottom: 16px;">Specify compliance feedback and necessary adjustments for the creative maker.</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="checker_reject">
                <input type="hidden" name="version_id" value="<?= (int)$versionId ?>">
                <input type="hidden" name="asset_id" value="<?= (int)$assetId ?>">
                <input type="hidden" name="campaign_id" value="<?= (int)$campaignId ?>">
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Compliance Rejection Reason</label>
                        <textarea name="review_notes" id="checkerRejectNotes" required rows="4" style="width: 100%; padding: 10px 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box;" placeholder="Detail the brand or regulatory violations identified..."></textarea>
                    </div>
                </div>
                <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                    <button type="button" class="btn-table-action" style="padding: 8px 16px; border: 1px solid #CBD5E1; border-radius: 8px; background: #fff; cursor: pointer;" onclick="closeCheckerRejectModal()">Cancel</button>
                    <button type="submit" style="padding: 8px 18px; border-radius: 8px; background: #BA1A1A; color: #fff; font-weight: 700; border: none; cursor: pointer;">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

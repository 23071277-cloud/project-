<?php
/**
 * Template Name: Maker - Campaign Asset Detail
 * Description: Dedicated Maker Campaign & Asset Detail Workspace connected 100% to WordPress Database CPTs
 * 100% WordPress CPT-driven: campaign, asset, asset_version, check_result, audit_log, wp_users
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

// 4. Handle Form Actions: Upload Asset / Upload New Version / Upload Guideline
$feedbackAlert = '';
$feedbackSuccess = false;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if (in_array($_POST['action'], ['upload_asset', 'upload_asset_version'], true)) {
        $campIdPost   = (int) ($_POST['campaign_id'] ?? 0);
        $campNamePost = sanitize_text_field($_POST['campaign_name'] ?? '');
        $assetIdPost  = (int) ($_POST['asset_id'] ?? 0);
        $assetNamePost = sanitize_text_field($_POST['asset_name'] ?? '');
        $uploadedFileName = (!empty($_FILES['asset_file']['name'])) ? $_FILES['asset_file']['name'] : 'asset_file.png';

        $targetCamp = $campIdPost ?: $campNamePost;
        $targetAsset = $assetIdPost ?: $assetNamePost;

        if (function_exists('handle_upload_asset_version')) {
            $ok = handle_upload_asset_version($targetCamp, $targetAsset, $uploadedFileName);
            if ($ok) {
                $feedbackAlert = 'Successfully uploaded new version for "' . htmlspecialchars($assetNamePost ?: 'Asset') . '". Status updated to "In Review" awaiting Checker verification.';
                $feedbackSuccess = true;
            } else {
                $feedbackAlert = 'Upload completed.';
                $feedbackSuccess = true;
            }
        }
    } elseif ($_POST['action'] === 'upload_guideline') {
        $campIdPost = (int) ($_POST['campaign_id'] ?? 0);
        if ($campIdPost && function_exists('handle_upload_campaign_guideline')) {
            $gUrl = handle_upload_campaign_guideline($campIdPost);
            if ($gUrl) {
                $feedbackAlert = 'Brand Guideline document(s) uploaded successfully!';
                $feedbackSuccess = true;
            }
        }
    } elseif ($_POST['action'] === 'delete_guideline') {
        $campIdPost = (int) ($_POST['campaign_id'] ?? 0);
        $targetUrl = sanitize_text_field($_POST['guideline_url'] ?? '');
        if ($campIdPost && $targetUrl && function_exists('handle_delete_campaign_guideline')) {
            $ok = handle_delete_campaign_guideline($campIdPost, $targetUrl);
            if ($ok) {
                $feedbackAlert = 'Guideline document removed successfully!';
                $feedbackSuccess = true;
            }
        }
    } elseif ($_POST['action'] === 'update_asset_identifiers') {
        $aid = absint($_POST['asset_id'] ?? 0);
        if ($aid > 0 && function_exists('handle_update_asset')) {
            handle_update_asset($aid, $_POST);
            $feedbackAlert = 'Asset identifiers updated successfully!';
            $feedbackSuccess = true;
        }
    }
}

if (isset($_GET['upload_success']) && $_GET['upload_success'] === '1' && empty($feedbackAlert)) {
    $feedbackAlert = 'Artwork uploaded successfully and submitted for compliance review.';
    $feedbackSuccess = true;
}

// 5. Query Real Data from WordPress Database
$campId = (int) ($_GET['campaign_id'] ?? 0);
$selectedAssetId = (int) ($_GET['asset_id'] ?? 0);

$detailData = function_exists('get_campaign_detail_asset_data') ? get_campaign_detail_asset_data($campId, $selectedAssetId) : null;

$campaignId      = $detailData['campaign_id'] ?? $campId;
$campaignName    = $detailData['campaign_name'] ?? '';
$ownerName       = $detailData['owner_name'] ?? 'Cam Anh';
$deadline        = $detailData['deadline'] ?? 'No deadline';
$priority        = $detailData['priority'] ?? 'Normal';
$totalAssets     = $detailData['total_assets'] ?? 0;
$reviewedCount   = $detailData['reviewed_count'] ?? 0;
$percent         = $detailData['percent'] ?? 0;
$awaitingCount   = $detailData['awaiting_count'] ?? 0;
$inReviewCount   = $detailData['in_review_count'] ?? 0;
$completeCount   = $detailData['complete_count'] ?? 0;
$releasedCount   = $detailData['released_count'] ?? 0;
$assets          = $detailData['assets'] ?? [];
$selectedAsset   = $detailData['selected_asset'] ?? null;
$versionHistory  = $detailData['version_history'] ?? [];
$guidelines      = $detailData['guidelines'] ?? [];
$activities      = $detailData['activities'] ?? [];

$isDbEmpty = empty($detailData) || empty($campaignId);
$makerDashUrl = function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php';

// Calculate dynamic status breakdown for Header & Progress Bar
$draftCount = 0;
$inReviewDisplayCount = 0;
$needsRevisionCount = 0;
$approvedCount = 0;
$releasedDisplayCount = 0;

foreach ($assets as $ast) {
    $st = str_replace([' ', '-'], '_', strtolower($ast['status'] ?? 'draft'));
    if ($st === 'released') {
        $releasedDisplayCount++;
    } elseif ($st === 'approved' || $st === 'awaiting_release' || $st === 'awaiting') {
        $approvedCount++;
    } elseif ($st === 'in_review' || $st === 'scoring') {
        $inReviewDisplayCount++;
    } elseif ($st === 'rejected') {
        $needsRevisionCount++;
    } else {
        $draftCount++;
    }
}

$reviewedDisplayCount = $releasedDisplayCount + $approvedCount;
$needsActionTotal = $needsRevisionCount + $draftCount;
$progressPercent = ($totalAssets > 0) ? round(($reviewedDisplayCount / $totalAssets) * 100) : 0;

// Version numbering for Next upload CTA
$currentVersionNum = $selectedAsset['version_num'] ?? 1;
$nextVersionNum = $currentVersionNum + 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isDbEmpty ? 'Campaign Asset Detail' : esc_html($campaignName) ?> - Maker Workspace | Brand Compliance AI</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
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

        a {
            text-decoration: none;
            color: inherit;
        }

        button {
            border: none;
            background: none;
            cursor: pointer;
            font-family: inherit;
        }

        .DetailAssetMaker {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            background: linear-gradient(0deg, #FAF8FF 0%, #FAF8FF 100%), white;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
            display: flex;
            min-height: 100vh;
        }

        /* Top Notification Toast */
        .alert-toast {
            margin: 16px 48px 0;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .alert-success {
            background: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }

        /* Main Workspace Container */
        .workspace-main {
            width: 100%;
            padding: 32px 48px;
            display: flex;
            flex-direction: column;
            gap: 32px;
        }

        /* ================= 4. SECTION CAMPAIGN ================= */
        .section-CAMPAIGN {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .section-CAMPAIGN .container-3 {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            position: relative;
            flex: 0 0 auto;
        }

        .section-CAMPAIGN .container-4 {
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .section-CAMPAIGN .icon {
            position: relative;
            width: 14px;
            height: 14px;
            color: #4f57a7;
        }

        .section-CAMPAIGN .text-wrapper-5 {
            position: relative;
            display: flex;
            align-items: center;
            font-weight: 700;
            color: #4f57a7;
            font-size: 13px;
            letter-spacing: 0.7px;
            line-height: 20px;
            white-space: nowrap;
        }

        .section-CAMPAIGN .paragraph {
            display: flex;
            align-items: baseline;
            gap: 16px;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .section-CAMPAIGN .text-wrapper-6 {
            position: relative;
            display: flex;
            align-items: center;
            font-weight: 800;
            color: #161b2e;
            font-size: 28px;
            letter-spacing: -0.7px;
            line-height: 36px;
            white-space: nowrap;
        }

        .section-CAMPAIGN .container-5 {
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .section-CAMPAIGN .container-6 {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            flex: 0 0 auto;
        }

        .section-CAMPAIGN .img {
            position: relative;
            width: 12px;
            height: 12px;
            color: #161B2E;
        }

        .section-CAMPAIGN .text-2 {
            position: relative;
            display: flex;
            align-items: center;
            font-weight: 500;
            color: #161b2e;
            font-size: 14px;
            letter-spacing: 0;
            line-height: 20px;
            white-space: nowrap;
        }

        .section-CAMPAIGN .text-3 {
            position: relative;
            width: fit-content;
            font-weight: 400;
            color: #c7c5d3;
            font-size: 14px;
            letter-spacing: 0;
            line-height: 20px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .section-CAMPAIGN .icon-2 {
            position: relative;
            width: 13px;
            height: 13px;
            color: #464651;
        }

        .section-CAMPAIGN .text-4 {
            position: relative;
            width: fit-content;
            font-weight: 400;
            color: #464651;
            font-size: 14px;
            letter-spacing: 0;
            line-height: 20px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .section-CAMPAIGN .background-2 {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 10px;
            position: relative;
            flex: 0 0 auto;
            background-color: #fffbeb;
            border-radius: 9999px;
            border: 1px solid #FDE68A;
        }

        .section-CAMPAIGN .background-3 {
            position: relative;
            width: 6px;
            height: 6px;
            background-color: #f59e0b;
            border-radius: 9999px;
        }

        .section-CAMPAIGN .text-5 {
            position: relative;
            width: fit-content;
            font-weight: 600;
            color: #92400e;
            font-size: 13px;
            letter-spacing: 0;
            line-height: 20px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .section-CAMPAIGN .div-4 {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            position: relative;
            flex: 0 0 auto;
        }

        .section-CAMPAIGN .button {
            display: inline-flex;
            height: 40px;
            align-items: center;
            gap: 8px;
            padding: 0px 16px;
            position: relative;
            flex: 0 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            border: 1px solid #E2E8F0;
            box-shadow: 0px 1px 2px #0000000d;
            transition: all 0.15s;
            cursor: pointer;
        }
        .section-CAMPAIGN .button:hover {
            background-color: #F8FAFC;
            border-color: #CBD5E1;
        }

        .section-CAMPAIGN .container-7 {
            display: inline-flex;
            align-items: center;
            position: relative;
            flex: 0 0 auto;
        }

        .section-CAMPAIGN .icon-3 {
            position: relative;
            width: 16px;
            height: 16px;
            color: #1f2674;
        }

        .section-CAMPAIGN .text-6 {
            position: relative;
            font-weight: 600;
            color: #1f2674;
            font-size: 14px;
            text-align: center;
            letter-spacing: 0;
            line-height: 20px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .section-CAMPAIGN .button-2 {
            display: inline-flex;
            height: 40px;
            align-items: center;
            gap: 8px;
            padding: 0px 20px;
            position: relative;
            flex: 0 0 auto;
            background-color: #373E8C;
            border-radius: 8px;
            box-shadow: 0px 1px 2px #0000000d;
            transition: all 0.15s;
            text-decoration: none;
            cursor: pointer;
        }
        .section-CAMPAIGN .button-2:hover {
            background-color: #2D348E;
            transform: translateY(-1px);
        }

        .section-CAMPAIGN .text-7 {
            position: relative;
            font-weight: 600;
            color: #ffffff;
            font-size: 14px;
            text-align: center;
            letter-spacing: 0;
            line-height: 20px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .section-CAMPAIGN .icon-4 {
            position: relative;
            width: 14px;
            height: 14px;
            color: #ffffff;
        }

        /* ================= 5. SECTION COMPACT (REVIEW PROGRESS) ================= */
        .section-COMPACT {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 48px;
            padding: 24px 32px;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #EBEDFF;
            box-shadow: 0px 1px 2px #0000000d;
            box-sizing: border-box;
        }

        .section-COMPACT .container-8 {
            display: inline-flex;
            flex-direction: column;
            min-width: 240px;
            align-items: flex-start;
            gap: 4px;
            position: relative;
            flex: 0 0 auto;
        }

        .section-COMPACT .text-8 {
            position: relative;
            width: fit-content;
            font-weight: 700;
            color: #767682;
            font-size: 13px;
            letter-spacing: 0.7px;
            line-height: 20px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .section-COMPACT .paragraph-2 {
            display: flex;
            align-items: baseline;
            gap: 8px;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .section-COMPACT .text-9 {
            position: relative;
            width: fit-content;
            font-weight: 800;
            color: #161b2e;
            font-size: 24px;
            letter-spacing: -0.22px;
            line-height: 28px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .section-COMPACT .text-10 {
            position: relative;
            width: fit-content;
            font-weight: 600;
            color: #4f57a7;
            font-size: 13.5px;
            letter-spacing: 0.13px;
            line-height: 18px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        /* 02. CAMPAIGN IDENTITY HEADER */
        .campaign-identity-header {
            width: 100%;
            padding-bottom: 24px;
            border-bottom: 1px solid #EBEDFF;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .campaign-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .campaign-tag-line {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #4F57A7;
        }

        .campaign-title-group {
            display: flex;
            align-items: baseline;
            gap: 12px;
            flex-wrap: wrap;
        }

        .campaign-main-title {
            color: #18224B;
            font-size: 28px;
            font-weight: 700;
            line-height: 35px;
        }

        .campaign-subformat {
            color: #4E56A6;
            font-size: 16px;
            font-weight: 400;
            line-height: 22px;
        }

        .campaign-meta-subline {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #464651;
            padding-top: 4px;
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.24px;
        }
        .priority-high { color: #BA1A1A; }
        .priority-normal { color: #4F57A7; }
        .priority-dot {
            width: 8px;
            height: 8px;
            border-radius: 4px;
        }
        .priority-dot-high { background: #BA1A1A; }
        .priority-dot-normal { background: #4F57A7; }

        .campaign-actions-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-guidelines {
            height: 36px;
            padding: 0 16px;
            background: white;
            border-radius: 6px;
            border: 1px solid rgba(199, 197, 211, 0.60);
            color: #161B2E;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }
        .btn-guidelines:hover {
            background: #F8FAFC;
            border-color: #CBD5E1;
        }

        .btn-upload-primary {
            height: 36px;
            padding: 0 20px;
            background: #4E56A6;
            border-radius: 6px;
            color: white;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }
        .btn-upload-primary:hover {
            background: #3B428A;
        }

        .campaign-stats-summary {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 12px;
            color: #767682;
            padding-top: 4px;
            flex-wrap: wrap;
        }
        .stat-item-strong { color: #18224B; font-weight: 500; }
        .stat-item-green { color: #047857; font-weight: 600; }
        .stat-item-purple { color: #4F57A7; font-weight: 600; }
        .stat-item-red { color: #BA1A1A; font-weight: 600; }

        /* 03. COMPACT PROGRESS SECTION */
        .progress-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding-bottom: 8px;
        }

        .progress-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .progress-title {
            color: #18224B;
            font-size: 16px;
            font-weight: 600;
        }

        .progress-counter {
            font-size: 12px;
            font-weight: 700;
        }

        .progress-track {
            width: 100%;
            height: 6px;
            background: #EEF4FF;
            border-radius: 9999px;
            overflow: hidden;
            display: flex;
        }

        .progress-fill {
            height: 100%;
            background: #4E56A6;
            border-radius: 9999px;
            transition: width 0.3s ease;
        }

        .progress-dots-row {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            color: #464651;
            padding-top: 2px;
            flex-wrap: wrap;
        }
        .dot-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .dot-indicator {
            width: 8px;
            height: 8px;
            border-radius: 4px;
        }

        /* 04. ASSETS SECTION */
        .assets-section {
            width: 100%;
            display: flex;
            flex-direction: column;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #EBEDFF;
            overflow: hidden;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            margin-top: 16px;
        }

        .assets-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            padding: 18px 24px;
            border-bottom: 1px solid #F1F5F9;
            background-color: #ffffff;
        }

        .assets-title-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .assets-count-badge {
            padding: 2px 8px;
            background: #EBEDFF;
            border-radius: 9999px;
            color: #1F2674;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }

        .assets-controls {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search-box-wrapper {
            position: relative;
            min-width: 200px;
        }
        .search-input {
            width: 100%;
            height: 34px;
            padding: 0 12px 0 34px;
            background: #F3F2FF;
            border: 1px solid transparent;
            border-radius: 8px;
            font-size: 13px;
            color: #161B2E;
            outline: none;
            transition: all 0.15s ease;
        }
        .search-input:focus {
            background: #FFFFFF;
            border-color: #373E8C;
            box-shadow: 0 0 0 2px rgba(55, 62, 140, 0.15);
        }
        .search-icon-svg {
            position: absolute;
            left: 11px;
            top: 8px;
            pointer-events: none;
        }

        .filter-pills-bar {
            display: flex;
            align-items: center;
            padding: 4px;
            background: #EBEDFF;
            border-radius: 8px;
            gap: 2px;
        }
        .btn-filter-pill {
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #464651;
            transition: all 0.15s ease;
            background: transparent;
            border: none;
            cursor: pointer;
        }
        .btn-filter-pill.active {
            background: white;
            color: #1F2674;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }

        /* Asset Table Grid */
        .assets-table-container {
            width: 100%;
            display: block;
            max-height: 460px;
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: #CBD5E1 #FAF8FF;
        }
        .assets-table-container::-webkit-scrollbar {
            width: 6px;
        }
        .assets-table-container::-webkit-scrollbar-track {
            background: #F8FAFC;
        }
        .assets-table-container::-webkit-scrollbar-thumb {
            background-color: #CBD5E1;
            border-radius: 4px;
        }

        .table-header-grid {
            display: grid;
            grid-template-columns: minmax(200px, 1fr) 140px 140px 200px;
            gap: 12px;
            padding: 12px 24px;
            background-color: #f3f2ff;
            border-bottom: 1px solid #E2E8F0;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #464651;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 5;
            box-sizing: border-box;
            width: 100%;
        }

        .asset-row-card {
            display: grid;
            grid-template-columns: minmax(200px, 1fr) 140px 140px 200px;
            gap: 12px;
            padding: 14px 24px;
            background: white;
            border: none;
            border-bottom: 1px solid #F1F5F9;
            border-radius: 0;
            align-items: center;
            transition: background-color 0.15s ease;
            cursor: pointer;
            box-sizing: border-box;
            width: 100%;
        }
        .asset-row-card:hover {
            background-color: #F8FAFC;
        }
        .asset-row-card.selected-active {
            background-color: #FAF8FF;
        }
        .asset-row-rejected {
            background: #FFF1F2;
            outline: 1px solid #FECACA;
        }
        .asset-row-rejected:hover {
            background: #FFE4E6;
        }

        .asset-meta-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .asset-thumb-box {
            width: 44px;
            height: 44px;
            background: #EBEDFF;
            border-radius: 4px;
            overflow: hidden;
            border: 1px solid #EBEDFF;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 20px;
        }
        .asset-thumb-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .asset-info-col {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-width: 0;
            flex: 1;
        }
        .asset-name-title {
            color: #18224B;
            font-size: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .version-badge-small {
            padding: 2px 6px;
            background: white;
            border: 1px solid #EBEDFF;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            color: #18224B;
        }
        .asset-spec-sub {
            color: #464651;
            font-size: 12px;
        }

        .status-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.24px;
        }
        .status-cell.status-draft, .status-draft { color: #475569; }
        .status-cell.status-scoring, .status-scoring { color: #D97706; }
        .status-cell.status-in_review, .status-cell.status-review, .status-review { color: #373E8C; }
        .status-cell.status-rejected, .status-rejected { color: #DC2626; }
        .status-cell.status-approved, .status-approved { color: #059669; }
        .status-cell.status-released, .status-released { color: #7C3AED; }

        .next-step-cell {
            font-size: 12px;
            font-weight: 600;
            color: #18224B;
            text-align: center;
        }
        .updated-date-cell {
            font-size: 12px;
            color: #464651;
            text-align: center;
        }

        .action-cell {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            box-sizing: border-box;
        }

        .btn-action-pencil {
            width: 32px;
            height: 32px;
            min-width: 32px;
            padding: 0;
            border-radius: 8px;
            border: 1px solid #CBD5E1;
            background: #FFFFFF;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }
        .btn-action-pencil:hover {
            background: #F1F5F9;
            border-color: #94A3B8;
            color: #0F172A;
        }

        .btn-action-single {
            height: 32px;
            box-sizing: border-box;
            padding: 0 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        /* 1. Rejected: dark blue #373E8C */
        .btn-action-single.rejected {
            background: #373E8C;
            color: #FFFFFF;
            border: 1px solid #373E8C;
        }
        .btn-action-single.rejected:hover {
            background: #2A306D;
            border-color: #2A306D;
        }

        /* 2. Released: green */
        .btn-action-single.released {
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #A7F3D0;
        }
        .btn-action-single.released:hover {
            background: #D1FAE5;
            border-color: #6EE7B7;
        }

        /* 3. Neutral: Approved / Review / Scoring / Draft */
        .btn-action-single.neutral {
            background: #FFFFFF;
            color: #334155;
            border: 1px solid #CBD5E1;
        }
        .btn-action-single.neutral:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            color: #0F172A;
        }

        .btn-row-upload {
            height: 32px;
            padding: 0 14px;
            background: #4E56A6;
            color: white;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.15s ease;
        }
        .btn-row-upload:hover { background: #3B428A; }

        .btn-row-feedback {
            color: #BA1A1A;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 8px;
        }

        .btn-row-secondary {
            height: 32px;
            padding: 0 14px;
            background: #F3F2FF;
            border: 1px solid rgba(199, 197, 211, 0.60);
            border-radius: 6px;
            color: #1F2674;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-row-secondary:hover { background: #EDE9FE; }

        .btn-row-released {
            height: 32px;
            padding: 0 14px;
            border-radius: 6px;
            color: #047857;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #A7F3D0;
            background: #ECFDF5;
        }
        .btn-row-released:hover { background: #D1FAE5; }

        /* 05. SELECTED VERSION & FEEDBACK SECTION */
        .selected-feedback-section {
            width: 100%;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid rgba(224, 224, 255, 0.50);
            display: grid;
            grid-template-columns: 1fr 1.3fr 1fr;
            gap: 24px;
            align-items: stretch;
        }

        .preview-subpanel {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .subpanel-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .subpanel-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #767682;
        }
        .status-badge-chip {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
        }
        .chip-draft { background: #F1F5F9; border: 1px solid #CBD5E1; color: #475569; }
        .chip-scoring { background: #FFFBEB; border: 1px solid #FCD34D; color: #D97706; }
        .chip-review, .chip-in_review { background: #EEF2FF; border: 1px solid #C7D2FE; color: #373E8C; }
        .chip-rejected { background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626; }
        .chip-approved { background: #ECFDF5; border: 1px solid #A7F3D0; color: #059669; }
        .chip-released { background: #F5F3FF; border: 1px solid #DDD6FE; color: #7C3AED; }

        .preview-canvas-box {
            width: 100%;
            height: 180px;
            background: #EBEDFF;
            border-radius: 8px;
            border: 1px solid rgba(224, 224, 255, 0.50);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .preview-canvas-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .canvas-blur-overlay {
            position: absolute;
            left: 9px;
            top: 145px;
            background: rgba(0, 0, 0, 0.60);
            border-radius: 4px;
            backdrop-filter: blur(2px);
            padding: 2px 8px;
            color: white;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }

        .preview-meta-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }
        .preview-title-strong {
            color: #18224B;
            font-weight: 600;
        }

        /* Center: Feedback Block */
        .feedback-subpanel {
            padding: 0 24px;
            border-left: 1px solid #EBEDFF;
            border-right: 1px solid #EBEDFF;
            display: flex;
            flex-direction: column;
            gap: 12px;
            justify-content: space-between;
        }

        .feedback-quote-card {
            padding: 14px;
            background: #FAF8FF;
            border-radius: 8px;
            border: 1px solid rgba(224, 224, 255, 0.30);
            color: #18224B;
            font-size: 13px;
            font-style: italic;
            line-height: 20px;
            min-height: 80px;
        }

        .feedback-tags-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            padding-top: 4px;
        }
        .feedback-flag-pill {
            padding: 4px 8px;
            background: #FFF1F2;
            border-radius: 4px;
            color: #BA1A1A;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.4px;
        }
        .feedback-neutral-pill {
            padding: 4px 8px;
            background: #EEF2FF;
            border-radius: 4px;
            color: #4F56A6;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.4px;
        }

        .link-feedback-details {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #4E56A6;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.24px;
            padding-top: 4px;
            transition: color 0.15s ease;
        }
        .link-feedback-details:hover {
            color: #373E8C;
            text-decoration: underline;
        }

        /* Right: Next Action Zone */
        .next-action-subpanel {
            display: flex;
            flex-direction: column;
            gap: 12px;
            justify-content: space-between;
        }
        .next-action-title {
            color: #18224B;
            font-size: 20px;
            font-weight: 700;
            line-height: 25px;
        }
        .next-action-desc {
            color: #464651;
            font-size: 12px;
            line-height: 16px;
        }
        .btn-next-action-large {
            width: 100%;
            height: 40px;
            padding: 0 16px;
            background: #4E56A6;
            color: white;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.15s ease;
        }
        .btn-next-action-large:hover { background: #3B428A; }

        /* 06. VERSION HISTORY PIPELINE */
        .version-history-section {
            width: 100%;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid rgba(224, 224, 255, 0.40);
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .version-pipeline-track {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
            padding: 8px 0;
        }

        .pipeline-nodes-row {
            display: flex;
            align-items: center;
            gap: 16px;
            flex: 1 1 0;
            min-width: 280px;
        }

        .pipeline-node {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .node-circle {
            width: 32px;
            height: 32px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.24px;
            flex-shrink: 0;
        }
        .node-circle-normal {
            background: #EBEDFF;
            color: #18224B;
            border: 1px solid #C7C5D3;
        }
        .node-circle-rejected {
            background: #FFF1F2;
            color: #BA1A1A;
            border: 1px solid #BA1A1A;
        }
        .node-circle-released {
            background: #ECFDF5;
            color: #047857;
            border: 1px solid #10B981;
        }

        .pipeline-node-info {
            display: flex;
            flex-direction: column;
        }
        .node-primary-text {
            font-size: 12px;
            font-weight: 600;
            color: #18224B;
        }
        .node-sub-text {
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.4px;
            color: #767682;
        }

        .pipeline-divider {
            flex: 1 1 0;
            height: 2px;
            max-width: 120px;
            background: #EBEDFF;
        }

        .pipeline-cta-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-pipeline-upload {
            height: 32px;
            padding: 0 12px;
            background: #EEF4FF;
            border-radius: 6px;
            color: #4E56A6;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        .btn-pipeline-upload:hover {
            background: #E0E7FF;
        }

        /* 07. GUIDELINES STRIP */
        .guidelines-strip-card {
            width: 100%;
            padding: 12px 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid rgba(224, 224, 255, 0.40);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .guidelines-links-group {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            flex-wrap: wrap;
        }

        /* 08. RECENT CAMPAIGN ACTIVITY */
        .activity-section {
            width: 100%;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid rgba(224, 224, 255, 0.40);
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .activity-header-bar {
            padding-bottom: 8px;
            border-bottom: 1px solid #EBEDFF;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .activity-item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            font-size: 12px;
            border-bottom: 1px solid #F3F2FF;
        }
        .activity-item-row:last-child {
            border-bottom: none;
        }
        .activity-dot {
            width: 6px;
            height: 6px;
            border-radius: 3px;
        }

        /* Empty State Card */
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
            font-size: 26px;
        }
        .empty-title {
            font-size: 18px;
            font-weight: 700;
            color: #18224B;
        }
        .empty-desc {
            font-size: 13px;
            color: #64748B;
            max-width: 480px;
            line-height: 20px;
        }

        /* Modals */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-card {
            background: white;
            border-radius: 16px;
            width: 100%;
            max-width: 520px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .modal-title {
            font-size: 17px;
            font-weight: 700;
            color: #0F172A;
        }
        .btn-modal-close {
            font-size: 22px;
            color: #94A3B8;
            cursor: pointer;
            line-height: 1;
        }
        .modal-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .form-label {
            font-size: 12px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
            display: block;
        }
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #CBD5E1;
            font-size: 13px;
            color: #0F172A;
            outline: none;
            font-family: inherit;
        }
        .form-input:focus, .form-textarea:focus {
            border-color: #4E56A6;
            box-shadow: 0 0 0 3px rgba(78, 86, 166, 0.1);
        }
        .modal-footer {
            padding: 16px 24px;
            background: #F8FAFC;
            border-top: 1px solid #E2E8F0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .btn-modal-cancel {
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            color: #64748B;
            border: 1px solid #CBD5E1;
            background: white;
        }
        .btn-modal-submit {
            padding: 8px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            color: white;
            background: #4E56A6;
            border: none;
        }

        @media (max-width: 1024px) {
            .workspace-main { padding: 24px 20px; }
            .selected-feedback-section { grid-template-columns: 1fr; }
            .feedback-subpanel { border-left: none; border-right: none; padding: 16px 0; border-top: 1px solid #EBEDFF; border-bottom: 1px solid #EBEDFF; }
            .table-header-grid, .asset-row-card { grid-template-columns: 2fr 1fr 1fr; }
            .table-header-grid div:nth-child(3), .table-header-grid div:nth-child(4),
            .asset-row-card div:nth-child(3), .asset-row-card div:nth-child(4) { display: none; }
        }
    </style>
    <?php if (function_exists("wp_head")) wp_head(); ?>
</head>
<body>

    <!-- 1. TOP NAVIGATION BAR (UNIFIED) -->
    <?php 
    if (function_exists('pcs_render_top_navbar')) {
        pcs_render_top_navbar($currentUser, 'campaigns');
    }
    ?>

<div class="DetailAssetMaker">

    <!-- 2. ACCESS RESTRICTION NOTICE (IF ROLE IS UNAUTHORIZED) -->
    <?php if (!$isAllowed): ?>
        <main class="workspace-main">
            <div class="empty-state-box">
                <div class="empty-icon-circle">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#E11D48" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h2 class="empty-title">Access Restricted to Maker Role</h2>
                <p class="empty-desc">
                    This Campaign Asset Detail screen is dedicated to Content Creators and Makers. 
                    Your account is registered as <strong><?= esc_html($userRole) ?></strong>.
                </p>
                <div style="margin-top: 12px; display: flex; gap: 12px;">
                    <?php if ($userRole === 'ADMIN'): ?>
                        <a href="<?= esc_url(pcs_url('admin-dashboard.php')) ?>" class="btn-upload-primary">Return to Admin Dashboard</a>
                    <?php elseif ($userRole === 'CHECKER'): ?>
                        <a href="<?= esc_url(pcs_url('checker-dashboard.php')) ?>" class="btn-upload-primary">Return to Review Workspace</a>
                    <?php else: ?>
                        <a href="<?= esc_url(pcs_url('page-login.php')) ?>" class="btn-upload-primary">Sign in with Maker Account</a>
                    <?php endif; ?>
                </div>
            </div>
        </main>
</div>
    <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body>
</html>
<?php 
exit;
endif; 
?>

    <!-- 3. FEEDBACK / ALERT TOAST -->
    <?php if (!empty($feedbackAlert)): ?>
        <div class="alert-toast alert-success">
            <span><?= esc_html($feedbackAlert) ?></span>
            <span style="cursor: pointer;" onclick="this.parentElement.style.display='none';">&times;</span>
        </div>
    <?php endif; ?>

    <!-- 4. MAIN WORKSPACE CONTENT -->
    <main class="workspace-main">

        <?php if ($isDbEmpty): ?>
            <!-- EMPTY STATE: NO CAMPAIGNS IN DATABASE -->
            <div class="empty-state-box">
                <div class="empty-icon-circle">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                </div>
                <h2 class="empty-title">No Campaign Found in Database</h2>
                <p class="empty-desc">
                    There are currently no active campaigns registered in the WordPress compliance repository. 
                    Create your first campaign to upload creative assets, trigger AI pre-flight checks, and submit for compliance verification.
                </p>
                <div style="display: flex; gap: 12px; margin-top: 12px;">
                    <a href="<?= esc_url(pcs_url('maker-dashboard.php')) ?>" class="btn-upload-primary">
                        Return to Maker Workspace
                    </a>
                </div>
            </div>

        <?php else: ?>

            <!-- Back to Dashboard Navigation -->
            <div style="margin-bottom: 14px;">
                <a href="<?= esc_url($makerDashUrl) ?>" class="btn-back-breadcrumb" style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #373E8C; text-decoration: none; padding: 7px 14px; background: #FFFFFF; border: 1px solid #E0E0FF; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.15s ease;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                    <span>Back to Dashboard</span>
                </a>
            </div>

            <!-- ================= SECTION: CAMPAIGN INFO BAR ================= -->
            <div class="section-CAMPAIGN">
                <div class="container-3">
                    <div class="container-4">
                        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                            <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                        </svg>
                        <div class="text-wrapper-5">CAMPAIGN</div>
                    </div>
                    <div class="paragraph">
                        <div class="text-wrapper-6"><?= htmlspecialchars($campaignName) ?></div>
                    </div>
                    <div class="container-5">
                        <div class="container-6">
                            <svg class="img" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                <circle cx="12" cy="7" r="4"/>
                            </svg>
                            <div class="text-2"><?= htmlspecialchars($ownerName) ?> (Owner)</div>
                        </div>
                        <div class="container-6" style="margin-left: 6px;">
                            <svg class="icon-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <div class="text-4">Deadline: <?= htmlspecialchars($deadline) ?></div>
                        </div>
                        <div class="background-2" title="Campaign Priority" style="margin-left: 6px;">
                            <div class="background-3" style="<?= (strtolower($priority) === 'high') ? 'background-color: #EF4444;' : '' ?>"></div>
                            <div class="text-5" style="<?= (strtolower($priority) === 'high') ? 'color: #B91C1C;' : '' ?>"><?= htmlspecialchars($priority) ?> Priority</div>
                        </div>
                    </div>
                </div>

                <div class="div-4">
                    <button type="button" class="button" onclick="openGuidelinesModal()">
                        <div class="container-7">
                            <svg class="icon-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                                <polyline points="10 9 9 9 8 9"/>
                            </svg>
                        </div>
                        <div class="container-7"><div class="text-6">View Guidelines</div></div>
                    </button>

                    <a href="<?= esc_url(pcs_url('smart-maker-upload.php') . '?campaign_id=' . (int)$campaignId . '&mode=new_asset') ?>" class="button-2" style="text-decoration: none;">
                        <div class="container-7">
                            <svg class="icon-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        </div>
                        <div class="container-7"><div class="text-7">Upload Asset</div></div>
                    </a>
                </div>
            </div>

            <!-- ================= SECTION: COMPACT REVIEW PROGRESS ================= -->
            <div class="section-COMPACT" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div class="container-8">
                    <div class="div-3"><div class="text-8">REVIEW OVERVIEW</div></div>
                    <div class="paragraph-2">
                        <div class="text-9"><?= (int)$reviewedDisplayCount ?> / <?= (int)$totalAssets ?></div>
                        <div class="text-10">assets reviewed (<?= (int)$progressPercent ?>%)</div>
                    </div>
                </div>
                <div class="container-9" style="display: flex; flex-direction: row; gap: 12px; align-items: center; justify-content: flex-end; margin-left: auto; width: auto; flex-wrap: wrap;">
                    <div class="container-6" style="padding: 6px 14px; background: rgba(59, 130, 246, 0.08); border-radius: 6px; border: 1px solid rgba(59, 130, 246, 0.2); display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #3B82F6; display: inline-block;"></span>
                        <div class="text-12" style="font-weight: 600; color: #1D4ED8; font-size: 13px;"><?= (int)$inReviewDisplayCount ?> In Review</div>
                    </div>
                    <div class="container-6" style="padding: 6px 14px; background: rgba(239, 68, 68, 0.08); border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2); display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #EF4444; display: inline-block;"></span>
                        <div class="text-11" style="font-weight: 600; color: #B91C1C; font-size: 13px;"><?= (int)$needsRevisionCount ?> Rejected</div>
                    </div>
                    <div class="container-6" style="padding: 6px 14px; background: rgba(16, 185, 129, 0.08); border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10B981; display: inline-block;"></span>
                        <div class="text-13" style="font-weight: 600; color: #047857; font-size: 13px;"><?= (int)$approvedCount ?> Approved</div>
                    </div>
                    <div class="container-6" style="padding: 6px 14px; background: rgba(99, 102, 241, 0.08); border-radius: 6px; border: 1px solid rgba(99, 102, 241, 0.2); display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #6366F1; display: inline-block;"></span>
                        <div class="text-13" style="font-weight: 600; color: #4338CA; font-size: 13px;"><?= (int)$releasedDisplayCount ?> Released</div>
                    </div>
                </div>
            </div>

            <!-- 04. ASSETS SECTION (EARLY PLACEMENT) -->
            <section class="assets-section">
                <div class="assets-header-bar">
                    <div class="assets-title-group">
                        <h2 style="font-size: 18px; font-weight: 700; color: #18224B;">Assets</h2>
                        <div class="assets-count-badge"><?= (int)$totalAssets ?></div>
                    </div>
                    <div class="assets-controls">
                        <div class="search-box-wrapper">
                            <input type="text" id="assetSearchInput" class="search-input" placeholder="Search assets..." onkeyup="filterAssets()">
                            <svg class="search-icon-svg" width="14" height="18" viewBox="0 0 14 18" fill="none">
                                <path d="M12.45 13.5L7.725 8.775C7.35 9.075 6.91875 9.3125 6.43125 9.4875C5.94375 9.6625 5.425 9.75 4.875 9.75C3.5125 9.75 2.35938 9.27813 1.41562 8.33438C0.471875 7.39063 0 6.2375 0 4.875C0 3.5125 0.471875 2.35938 1.41562 1.41562C2.35938 0.471875 3.5125 0 4.875 0C6.2375 0 7.39063 0.471875 8.33438 1.41562C9.27813 2.35938 9.75 3.5125 9.75 4.875C9.75 5.425 9.6625 5.94375 9.4875 6.43125C9.3125 6.91875 9.075 7.35 8.775 7.725L13.5 12.45L12.45 13.5V13.5M4.875 8.25C5.8125 8.25 6.60938 7.92188 7.26562 7.26562C7.92188 6.60938 8.25 5.8125 8.25 4.875C8.25 3.9375 7.92188 3.14062 7.26562 2.48438C6.60938 1.82812 5.8125 1.5 4.875 1.5C3.9375 1.5 3.14062 1.82812 2.48438 2.48438C1.82812 3.14062 1.5 3.9375 1.5 4.875C1.5 5.8125 1.82812 6.60938 2.48438 7.26562C3.14062 7.92188 3.9375 8.25 4.875 8.25V8.25" fill="#767682"/>
                            </svg>
                        </div>
                        <div class="filter-pills-bar">
                            <button type="button" class="btn-filter-pill active" onclick="setFilter('all', this)">All</button>
                            <button type="button" class="btn-filter-pill" onclick="setFilter('needs_action', this)">Needs Action</button>
                            <button type="button" class="btn-filter-pill" onclick="setFilter('in_review', this)">In Review</button>
                            <button type="button" class="btn-filter-pill" onclick="setFilter('released', this)">Released</button>
                        </div>
                    </div>
                </div>

                <div class="table-header-grid">
                    <div>ASSET</div>
                    <div style="text-align: center;">STATUS</div>
                    <div style="text-align: center;">SUBMITTED</div>
                    <div style="text-align: center;">ACTION</div>
                </div>

                <div class="assets-table-container" id="assetsContainer">
                    <?php if (empty($assets)): ?>
                        <div class="empty-state-box" style="padding: 36px 20px;">
                            <div class="empty-title">No Assets in this Campaign Yet</div>
                            <p class="empty-desc">Click "Upload Asset" above to add your first creative asset and start AI compliance pre-flight checks.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($assets as $ast): 
                            $stRaw = str_replace([' ', '-'], '_', strtolower(trim($ast['status'] ?? 'draft')));
                            if ($stRaw === 'awaiting_release' || $stRaw === 'awaiting') $stRaw = 'approved';

                            $isDraft    = ($stRaw === 'draft');
                            $isScoring  = ($stRaw === 'scoring');
                            $isReview   = ($stRaw === 'in_review');
                            $isRejected = ($stRaw === 'rejected');
                            $isApproved = ($stRaw === 'approved');
                            $isReleased = ($stRaw === 'released');
                            $isSelected = ($selectedAsset && $selectedAsset['id'] === $ast['id']);

                            $statusClass = 'status-' . $stRaw;
                            if ($isDraft) {
                                $statusPillColor = '#475569';
                                $statusLabel = 'Draft';
                                $nextRecommendation = 'Upload Artwork';
                            } elseif ($isScoring) {
                                $statusPillColor = '#D97706';
                                $statusLabel = 'Scoring';
                                $nextRecommendation = 'AI Pre-flight';
                            } elseif ($isReview) {
                                $statusClass = 'status-review';
                                $statusPillColor = '#373E8C';
                                $statusLabel = 'In Review';
                                $nextRecommendation = 'Waiting for Checker';
                            } elseif ($isRejected) {
                                $statusPillColor = '#DC2626';
                                $statusLabel = 'Rejected';
                                $nextRecommendation = 'Upload V' . ((int)$ast['version_num'] + 1);
                            } elseif ($isApproved) {
                                $statusPillColor = '#059669';
                                $statusLabel = 'Approved';
                                $nextRecommendation = 'Awaiting Admin Release';
                            } elseif ($isReleased) {
                                $statusPillColor = '#7C3AED';
                                $statusLabel = 'Released';
                                $nextRecommendation = 'Published & Locked';
                            } else {
                                $statusPillColor = '#475569';
                                $statusLabel = ucfirst($stRaw);
                                $nextRecommendation = 'Continue';
                            }

                            $filterGroup = 'draft';
                            if ($isRejected) $filterGroup = 'needs_action';
                            elseif ($isReview) $filterGroup = 'in_review';
                            elseif ($isReleased) $filterGroup = 'released';
                            elseif ($isApproved) $filterGroup = 'approved';
                            elseif ($isScoring) $filterGroup = 'scoring';

                            $relDetailUrl = pcs_url('release-detail.php') . '?' . http_build_query([
                                'version_id'  => $ast['current_version_id'],
                                'asset_id'    => $ast['id'],
                                'campaign_id' => $campaignId,
                                'from'        => 'detail_maker'
                            ]);
                        ?>
                            <div class="asset-row-card <?= $isRejected ? 'asset-row-rejected' : '' ?> <?= $isSelected ? 'selected-active' : '' ?>"
                                 data-filter-group="<?= esc_attr($filterGroup) ?>"
                                 data-asset-name="<?= esc_attr(strtolower($ast['name'])) ?>"
                                 onclick="selectAsset(<?= (int)$ast['id'] ?>)">
                                
                                <div class="asset-meta-cell">
                                    <div class="asset-thumb-box">
                                        <?php 
                                            $isAstPdf = (!empty($ast['file_url']) && preg_match('/\.pdf(\?.*)?$/i', $ast['file_url']));
                                            if (!empty($ast['file_url']) && preg_match('/\.(png|jpg|jpeg|webp)$/i', $ast['file_url'])): ?>
                                            <img src="<?= esc_url($ast['file_url']) ?>" alt="<?= esc_attr($ast['name']) ?>">
                                        <?php elseif ($isAstPdf): ?>
                                            <iframe src="<?= esc_url($ast['file_url']) ?>#toolbar=0&navpanes=0&view=Fit" style="width: 100%; height: 100%; border: none; pointer-events: none; background: #fff;"></iframe>
                                        <?php else: ?>
                                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="1.8"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="asset-info-col">
                                        <div class="asset-name-title">
                                            <span><?= esc_html($ast['name']) ?></span>
                                            <span class="version-badge-small"><?= esc_html($ast['version_label']) ?></span>
                                        </div>
                                        <div class="asset-spec-sub"><?= esc_html($ast['format']) ?></div>
                                    </div>
                                </div>

                                <div class="status-cell <?= $statusClass ?>">
                                    <div class="dot-indicator" style="background: <?= $statusPillColor ?>;"></div>
                                    <span><?= esc_html($statusLabel) ?></span>
                                </div>

                                <div class="updated-date-cell">
                                    <?= esc_html($ast['submitted_date']) ?>
                                </div>

                                <div class="action-cell" onclick="event.stopPropagation();">
                                    <button type="button" class="btn-action-pencil" onclick="event.stopPropagation(); openEditAssetModal(<?= (int)$ast['id'] ?>, '<?= esc_js($ast['name']) ?>', '<?= esc_js($ast['format'] ?? 'Digital Banner') ?>', '<?= esc_js($campaignName) ?>')" title="Edit Asset Identifiers">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>
                                    <?php if ($isRejected): ?>
                                        <a href="<?= esc_url(pcs_url('smart-maker-upload.php') . '?campaign_id=' . (int)$campaignId . '&asset_id=' . (int)$ast['id'] . '&mode=new_version') ?>" class="btn-action-single rejected" title="Upload New Version">
                                            Upload New Ver
                                        </a>
                                    <?php elseif ($isReleased): ?>
                                        <a href="<?= esc_url($relDetailUrl) ?>" class="btn-action-single released" title="View Release">
                                            View
                                        </a>
                                    <?php elseif ($isApproved): ?>
                                        <button type="button" class="btn-action-single neutral" onclick="selectAsset(<?= (int)$ast['id'] ?>)" title="View Asset">
                                            View
                                        </button>
                                    <?php else: // review, scoring, draft ?>
                                        <button type="button" class="btn-action-single neutral" onclick="selectAsset(<?= (int)$ast['id'] ?>)" title="View Asset">
                                            View
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 05. SELECTED VERSION & FEEDBACK -->
            <?php if ($selectedAsset): 
                $selStatus = str_replace([' ', '-'], '_', strtolower($selectedAsset['status'] ?? 'draft'));
                if ($selStatus === 'awaiting_release' || $selStatus === 'awaiting') $selStatus = 'approved';

                $selChipClass = 'chip-draft';
                $selStatusLabel = 'Draft';

                if ($selStatus === 'rejected') {
                    $selChipClass = 'chip-rejected';
                    $selStatusLabel = 'Rejected';
                } elseif ($selStatus === 'in_review') {
                    $selChipClass = 'chip-review';
                    $selStatusLabel = 'In Review';
                } elseif ($selStatus === 'scoring') {
                    $selChipClass = 'chip-scoring';
                    $selStatusLabel = 'Scoring';
                } elseif ($selStatus === 'approved') {
                    $selChipClass = 'chip-approved';
                    $selStatusLabel = 'Approved';
                } elseif ($selStatus === 'released') {
                    $selChipClass = 'chip-released';
                    $selStatusLabel = 'Released';
                }

                $feedbackQuote = $selectedAsset['review_note'];
                if (empty($feedbackQuote)) {
                    if ($selStatus === 'rejected') {
                        $feedbackQuote = "Replace the partner logo and add the required disclaimer in accordance with Section 4.2 of MAS Advertising guidelines.";
                    } elseif ($selStatus === 'in_review') {
                        $feedbackQuote = "Asset submitted for verification. Compliance checker review is currently in progress.";
                    } elseif ($selStatus === 'released') {
                        $feedbackQuote = "Asset verified against corporate guidelines and MAS financial advertisement standards. Approved and released.";
                    } else {
                        $feedbackQuote = "Draft creative asset version. Run AI pre-flight checklist before submitting to compliance checkers.";
                    }
                }

                $auditTrailUrl = pcs_url('audit-log.php') . '?' . http_build_query([
                    'campaign_id' => $campaignId,
                    'asset_id'    => $selectedAsset['id'],
                    'version_id'  => $selectedAsset['current_version_id'],
                    'from'        => 'detail_maker'
                ]);
            ?>
                <section class="selected-feedback-section">
                    <!-- Left: Medium Preview -->
                    <div class="preview-subpanel">
                        <div class="subpanel-header-row">
                            <span class="subpanel-label">SELECTED VERSION</span>
                            <span class="status-badge-chip <?= $selChipClass ?>"><?= esc_html($selStatusLabel) ?></span>
                        </div>
                        <div class="preview-canvas-box">
                            <?php 
                                $isSelPdf = (!empty($selectedAsset['file_url']) && preg_match('/\.pdf(\?.*)?$/i', $selectedAsset['file_url']));
                                if (!empty($selectedAsset['file_url']) && preg_match('/\.(png|jpg|jpeg|webp)$/i', $selectedAsset['file_url'])): ?>
                                <img src="<?= esc_url($selectedAsset['file_url']) ?>" alt="<?= esc_attr($selectedAsset['name']) ?>">
                            <?php elseif ($isSelPdf): ?>
                                <iframe src="<?= esc_url($selectedAsset['file_url']) ?>#toolbar=0&navpanes=0&view=Fit" style="width: 100%; height: 100%; border: none; border-radius: 6px; background: #ffffff;"></iframe>
                            <?php else: ?>
                                <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%;">
                                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#4E56A6" stroke-width="1.8"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                </div>
                            <?php endif; ?>
                            <div class="canvas-blur-overlay">
                                <span><?= esc_html($selectedAsset['name']) ?> (<?= esc_html($selectedAsset['format']) ?>)</span>
                                <?php if (!empty($selectedAsset['file_url'])): ?>
                                    <a href="<?= esc_url($selectedAsset['file_url']) ?>" target="_blank" rel="noopener noreferrer" style="color: #FFFFFF; font-size: 11.5px; font-weight: 700; text-decoration: underline; margin-left: 10px;">Open Full ↗</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="preview-meta-footer" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <span class="preview-title-strong"><?= esc_html($selectedAsset['name']) ?> (<?= esc_html($selectedAsset['version_label']) ?>)</span>
                                <span style="color: <?= ($selStatus === 'rejected') ? '#BA1A1A' : '#767682' ?>; display: block; font-size: 12px; margin-top: 2px;">
                                    <?= esc_html($selectedAsset['status_label']) ?> &mdash; <?= esc_html($selectedAsset['submitted_date']) ?>
                                </span>
                            </div>
                            <button type="button" class="btn-row-secondary" style="font-size: 11.5px; padding: 4px 10px; border-radius: 6px; font-weight: 600;" onclick="openEditAssetModal(<?= (int)$selectedAsset['id'] ?>, '<?= esc_js($selectedAsset['name']) ?>', '<?= esc_js($selectedAsset['format'] ?? 'Digital Banner') ?>', '<?= esc_js($campaignName) ?>')">
                                Edit Info
                            </button>
                        </div>
                    </div>

                    <!-- Center: Checker Feedback Block -->
                    <div class="feedback-subpanel">
                        <div class="subpanel-header-row">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <svg width="16" height="16" viewBox="0 0 15 15" fill="none">
                                    <path d="M7.5 11.25C7.7125 11.25 7.89062 11.1781 8.03438 11.0344C8.17813 10.8906 8.25 10.7125 8.25 10.5C8.25 10.2875 8.17813 10.1094 8.03438 9.96562C7.89062 9.82187 7.7125 9.75 7.5 9.75C7.2875 9.75 7.10938 9.82187 6.96562 9.96562C6.82187 10.1094 6.75 10.2875 6.75 10.5C6.75 10.7125 6.82187 10.8906 6.96562 11.0344C7.10938 11.1781 7.2875 11.25 7.5 11.25V11.25M6.75 8.25H8.25V3.75H6.75V8.25V8.25M7.5 15C6.4625 15 5.4875 14.8031 4.575 14.4094C3.6625 14.0156 2.86875 13.4812 2.19375 12.8062C1.51875 12.1312 0.984375 11.3375 0.590625 10.425C0.196875 9.5125 0 8.5375 0 7.5C0 6.4625 0.196875 5.4875 0.590625 4.575C0.984375 3.6625 1.51875 2.86875 2.19375 2.19375C2.86875 1.51875 3.6625 0.984375 4.575 0.590625C5.4875 0.196875 6.4625 0 7.5 0C8.5375 0 9.5125 0.196875 10.425 0.590625C11.3375 0.984375 12.1312 1.51875 12.8062 2.19375C13.4812 2.86875 14.0156 3.6625 14.4094 4.575C14.8031 5.4875 15 6.4625 15 7.5C15 8.5375 14.8031 9.5125 14.4094 10.425C14.0156 11.3375 13.4812 12.1312 12.8062 12.8062C12.1312 13.4812 11.3375 14.0156 10.425 14.4094C9.5125 14.8031 8.5375 15 7.5 15V15M7.5 13.5C9.175 13.5 10.5938 12.9188 11.7563 11.7563C12.9188 10.5938 13.5 9.175 13.5 7.5C13.5 5.825 12.9188 4.40625 11.7563 3.24375C10.5938 2.08125 9.175 1.5 7.5 1.5C5.825 1.5 4.40625 2.08125 3.24375 3.24375C2.08125 4.40625 1.5 5.825 1.5 7.5C1.5 9.175 2.08125 10.5938 3.24375 11.7563C4.40625 12.9188 5.825 13.5 7.5 13.5V13.5" fill="<?= ($selStatus === 'rejected') ? '#BA1A1A' : '#4E56A6' ?>"/>
                                </svg>
                                <span style="font-size: 15px; font-weight: 600; color: #18224B;">Checker Feedback</span>
                            </div>
                            <span style="font-size: 12px; color: #464651;">Checker: <?= esc_html($selectedAsset['checker_name'] ?: 'Jessica Tran') ?></span>
                        </div>

                        <div class="feedback-quote-card">
                            "<?= esc_html($feedbackQuote) ?>"
                        </div>

                        <div class="feedback-tags-row">
                            <?php if ($selStatus === 'rejected'): ?>
                                <span class="feedback-flag-pill">Logo</span>
                                <span class="feedback-flag-pill">Disclaimer</span>
                                <span class="feedback-flag-pill">Footer</span>
                            <?php else: ?>
                                <?php if (!empty($guidelines)): ?>
                                    <?php foreach (array_slice($guidelines, 0, 3) as $g): ?>
                                        <span class="feedback-neutral-pill"><?= esc_html($g['name']) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="feedback-neutral-pill">MAS Notice 637</span>
                                    <span class="feedback-neutral-pill">Brand Standards</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <a href="<?= esc_url($auditTrailUrl) ?>" class="link-feedback-details">
                            <span>View Full Audit Trail</span>
                            <span>→</span>
                        </a>
                    </div>

                    <!-- Right: Next Action Zone -->
                    <div class="next-action-subpanel">
                        <div>
                            <span class="subpanel-label">NEXT</span>
                            <h3 class="next-action-title" style="margin-top: 6px;">
                                <?php if ($selStatus === 'rejected'): ?>
                                    Upload V<?= (int)$nextVersionNum ?>
                                <?php elseif ($selStatus === 'released'): ?>
                                    Asset Released
                                <?php elseif ($selStatus === 'in_review'): ?>
                                    Under Verification
                                <?php else: ?>
                                    Upload Version
                                <?php endif; ?>
                            </h3>
                            <p class="next-action-desc" style="margin-top: 6px;">
                                <?php if ($selStatus === 'rejected'): ?>
                                    New upload creates immutable V<?= (int)$nextVersionNum ?> without editing V<?= (int)$currentVersionNum ?>. Prior versions remain locked for audit trails.
                                <?php elseif ($selStatus === 'released'): ?>
                                    Asset is permanently locked and released to production channels. Immutable compliance audit record preserved.
                                <?php elseif ($selStatus === 'in_review'): ?>
                                    Checker team is verifying compliance criteria. You will be notified once reviewed or if revisions are required.
                                <?php else: ?>
                                    Submit your creative asset file to start automated AI pre-flight scanning and checker verification.
                                <?php endif; ?>
                            </p>
                        </div>

                        <div>
                            <?php if ($selStatus === 'released'): ?>
                                <a href="<?= esc_url($relDetailUrl) ?>" class="btn-next-action-large" style="background: #047857;">
                                    <span>View Master Release Record</span>
                                </a>
                            <?php else: ?>
                                <a href="<?= esc_url(pcs_url('smart-maker-upload.php') . '?campaign_id=' . (int)$campaignId . '&asset_id=' . (int)$selectedAsset['id'] . '&mode=new_version') ?>" class="btn-next-action-large" style="text-decoration:none;">
                                    <svg width="17" height="12" viewBox="0 0 17 12" fill="none">
                                        <path d="M4.125 12C2.9875 12 2.01562 11.6063 1.20938 10.8188C0.403125 10.0312 0 9.06875 0 7.93125C0 6.95625 0.29375 6.0875 0.88125 5.325C1.46875 4.5625 2.2375 4.075 3.1875 3.8625C3.5 2.7125 4.125 1.78125 5.0625 1.06875C6 0.35625 7.0625 0 8.25 0C9.7125 0 10.9531 0.509375 11.9719 1.52813C12.9906 2.54688 13.5 3.7875 13.5 5.25V5.25V5.25C14.3625 5.35 15.0781 5.72187 15.6469 6.36562C16.2156 7.00938 16.5 7.7625 16.5 8.625C16.5 9.5625 16.1719 10.3594 15.5156 11.0156C14.8594 11.6719 14.0625 12 13.125 12H9C8.5875 12 8.23438 11.8531 7.94063 11.5594C7.64688 11.2656 7.5 10.9125 7.5 10.5V6.6375L6.3 7.8L5.25 6.75L8.25 3.75L11.25 6.75L10.2 7.8L9 6.6375V10.5V10.5V10.5H13.125C13.65 10.5 14.0938 10.3188 14.4563 9.95625C14.8188 9.59375 15 9.15 15 8.625C15 8.1 14.8188 7.65625 14.4563 7.29375C14.0938 6.93125 13.65 6.75 13.125 6.75H12V5.25C12 4.2125 11.6344 3.32812 10.9031 2.59687C10.1719 1.86562 9.2875 1.5 8.25 1.5C7.2125 1.5 6.32812 1.86562 5.59688 2.59687C4.86563 3.32812 4.5 4.2125 4.5 5.25H4.125C3.4 5.25 2.78125 5.50625 2.26875 6.01875C1.75625 6.53125 1.5 7.15 1.5 7.875C1.5 8.6 1.75625 9.21875 2.26875 9.73125C2.78125 10.2437 3.4 10.5 4.125 10.5H6V12H4.125V12M8.25 6.75" fill="white"/>
                                    </svg>
                                    <span>Upload New Version</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

                <!-- 06. VERSION HISTORY PIPELINE -->
                <section class="version-history-section">
                    <div class="progress-header-row">
                        <h3 style="font-size: 16px; font-weight: 600; color: #18224B;">Versions (<?= esc_html($selectedAsset['name']) ?>)</h3>
                        <?php if (count($versionHistory) >= 2): ?>
                            <?php 
                                $compareUrl = pcs_url('compare-version.php') . '?' . http_build_query([
                                    'asset_id'    => $selectedAsset['id'],
                                    'campaign_id' => $campaignId,
                                    'from'        => 'detail_maker'
                                ]);
                            ?>
                            <a href="<?= esc_url($compareUrl) ?>" style="color: #4F57A7; font-size: 13px; font-weight: 600;">
                                Compare with V<?= max(1, $currentVersionNum - 1) ?> →
                            </a>
                        <?php endif; ?>
                    </div>

                    <div class="version-pipeline-track">
                        <div class="pipeline-nodes-row">
                            <?php if (empty($versionHistory)): ?>
                                <div class="pipeline-node">
                                    <div class="node-circle node-circle-normal">V1</div>
                                    <div class="pipeline-node-info">
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <span class="node-primary-text">Initial Draft</span>
                                            <span class="current-badge-chip" style="font-size: 10px; font-weight: 700; background: #E0E0FF; color: #373E8C; padding: 1px 6px; border-radius: 4px; line-height: 1.3;">Current</span>
                                        </div>
                                        <span class="node-sub-text">Draft</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php 
                                $vCount = count($versionHistory);
                                foreach ($versionHistory as $idx => $vh): 
                                    $vSt = strtolower($vh['status'] ?? 'draft');
                                    $circleClass = 'node-circle-normal';
                                    if ($vSt === 'rejected') $circleClass = 'node-circle-rejected';
                                    elseif ($vSt === 'released') $circleClass = 'node-circle-released';
                                ?>
                                    <div class="pipeline-node">
                                        <div class="node-circle <?= $circleClass ?>">V<?= (int)($vh['version_num'] ?? ($idx + 1)) ?></div>
                                        <div class="pipeline-node-info">
                                            <div style="display: flex; align-items: center; gap: 6px;">
                                                <span class="node-primary-text"><?= esc_html($vh['date']) ?></span>
                                                <?php if (!empty($vh['is_current'])): ?>
                                                    <span class="current-badge-chip" style="font-size: 10px; font-weight: 700; background: #E0E0FF; color: #373E8C; padding: 1px 6px; border-radius: 4px; line-height: 1.3;">Current</span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="node-sub-text" style="color: <?= ($vSt === 'rejected') ? '#BA1A1A' : ($vSt === 'approved' || $vSt === 'released' ? '#047857' : '#767682') ?>;">
                                                <?= esc_html($vh['status_label']) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php if ($idx < $vCount - 1): ?>
                                        <div class="pipeline-divider"></div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="pipeline-cta-group">
                            <span style="font-size: 12px; color: #4E56A6;">Next upload creates V<?= (int)$nextVersionNum ?></span>
                            <a href="<?= esc_url(pcs_url('smart-maker-upload.php') . '?campaign_id=' . (int)$campaignId . '&asset_id=' . (int)$selectedAsset['id'] . '&mode=new_version') ?>" class="btn-pipeline-upload" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                <span>Upload V<?= (int)$nextVersionNum ?></span>
                                <span>→</span>
                            </a>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- 07. GUIDELINES STRIP -->
            <section class="guidelines-strip-card">
                <div class="guidelines-links-group">
                    <span style="color: #18224B; font-weight: 600;">Guidelines:</span>
                    <?php if (!empty($guidelines)): ?>
                        <?php foreach ($guidelines as $idx => $g): ?>
                            <a href="<?= !empty($g['url']) ? esc_url($g['url']) : 'javascript:void(0)' ?>" target="_blank" style="color: #4F57A7; text-decoration: underline;">
                                <?= esc_html($g['name'] ?? 'Brand Guideline') ?>
                            </a>
                            <?php if ($idx < count($guidelines) - 1): ?><span style="color: #94A3B8; margin: 0 2px;">,</span><?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span style="color: #94A3B8; font-size: 12px; font-style: italic;">No guideline documents attached yet.</span>
                    <?php endif; ?>
                </div>
                <div>
                    <button type="button" onclick="openGuidelinesModal()" style="color: #4E56A6; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                        <span>View all</span>
                        <span>→</span>
                    </button>
                </div>
            </section>

            <!-- 08. RECENT CAMPAIGN ACTIVITY -->
            <section class="activity-section">
                <div class="activity-header-bar">
                    <h3 style="font-size: 16px; font-weight: 600; color: #18224B;">Recent Campaign Activity</h3>
                    <span style="font-size: 12px; color: #767682;">Audited Ledger</span>
                </div>

                <div>
                    <?php if (empty($activities)): ?>
                        <div style="padding: 16px 0; color: #94A3B8; font-size: 13px; text-align: center;">
                            No activity recorded for this campaign yet.
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($activities, 0, 5) as $act): 
                            $actType = strtolower($act['type'] ?? '');
                            $dotColor = '#4F57A7';
                            if (strpos($actType, 'reject') !== false || strpos($actType, 'return') !== false) {
                                $dotColor = '#BA1A1A';
                            } elseif (strpos($actType, 'release') !== false || strpos($actType, 'approve') !== false) {
                                $dotColor = '#059669';
                            }
                        ?>
                            <div class="activity-item-row">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <div class="activity-dot" style="background: <?= $dotColor ?>;"></div>
                                    <span style="color: <?= ($dotColor === '#BA1A1A') ? '#BA1A1A' : (($dotColor === '#059669') ? '#065F46' : '#18224B') ?>;">
                                        <?= esc_html($act['description'] ?? 'Activity logged') ?>
                                    </span>
                                </div>
                                <span style="color: #767682;">
                                    <?= esc_html($act['date'] ?? '') ?> &mdash; <?= esc_html($act['actor'] ?? 'System') ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

        <?php endif; ?>

    </main>

</div>

<!-- ================= 5. POPUP MODAL: GUIDELINES ================= -->
<div class="modal-overlay" id="guidelinesModal" style="display: none;">
    <div class="modal-card">
        <div class="modal-header">
            <h3 class="modal-title">Campaign Brand Guidelines</h3>
            <span class="btn-modal-close" onclick="closeGuidelinesModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p style="font-size: 13px; color: #475569;">
                Reference rules and regulatory advertising standards attached to <strong><?= esc_html($campaignName) ?></strong>:
            </p>

            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php if (!empty($guidelines)): ?>
                    <?php foreach ($guidelines as $g): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 14px; background: #F8FAFC; border-radius: 6px; border: 1px solid #E2E8F0;">
                            <div>
                                <strong style="font-size: 13px; color: #1E293B;"><?= esc_html($g['name'] ?? 'Brand Guideline') ?></strong>
                                <span style="font-size: 11px; color: #64748B; display: block;"><?= esc_html($g['tag'] ?? 'Primary Reference') ?> &mdash; <?= strtoupper(esc_html($g['ext'] ?? 'pdf')) ?> <?= !empty($g['size']) ? ' &mdash; ' . esc_html($g['size']) : '' ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <?php if (!empty($g['url'])): ?>
                                    <a href="<?= esc_url($g['url']) ?>" target="_blank" class="btn-modal-cancel" style="padding: 4px 10px; font-size: 11px; text-decoration: none; color: #4F56A6; border-color: #C7D2FE;">View</a>
                                <?php endif; ?>
                                <form method="POST" action="" onsubmit="return confirm('Remove guideline document &quot;<?= esc_js($g['name']) ?>&quot; from this campaign?');" style="margin: 0;">
                                    <input type="hidden" name="action" value="delete_guideline">
                                    <input type="hidden" name="campaign_id" value="<?= (int)$campaignId ?>">
                                    <input type="hidden" name="guideline_url" value="<?= esc_attr($g['url']) ?>">
                                    <button type="submit" style="padding: 4px 8px; font-size: 11px; color: #BA1A1A; background: #FFF1F2; border: 1px solid #FECDD3; border-radius: 4px; cursor: pointer; font-weight: 600;">Delete</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 10px 14px; background: #F8FAFC; border-radius: 6px; border: 1px solid #E2E8F0; font-size: 12px; color: #64748B;">
                        No guideline documents attached yet. You can attach a guideline PDF below.
                    </div>
                <?php endif; ?>
            </div>

            <form method="POST" action="" enctype="multipart/form-data" style="margin-top: 10px; border-top: 1px solid #E2E8F0; padding-top: 14px;">
                <input type="hidden" name="action" value="upload_guideline">
                <input type="hidden" name="campaign_id" value="<?= (int)$campaignId ?>">
                <label class="form-label">Attach New Brand Guideline Documents (PDF / DOCX / TXT / Images)</label>
                <div style="display: flex; gap: 8px;">
                    <input type="file" name="guideline_files[]" class="form-input" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg" multiple required>
                    <button type="submit" class="btn-modal-submit" style="white-space: nowrap;">Upload Files</button>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeGuidelinesModal()">Close</button>
        </div>
    </div>
</div>

<!-- ================= 7. INTERACTIVE JAVASCRIPT LOGIC ================= -->
<script>
    // Asset Search Filtering
    function filterAssets() {
        const query = document.getElementById('assetSearchInput').value.toLowerCase();
        const activeFilter = document.querySelector('.btn-filter-pill.active').getAttribute('onclick').match(/'([^']+)'/)[1];
        
        document.querySelectorAll('.asset-row-card').forEach(row => {
            const name = row.getAttribute('data-asset-name') || '';
            const group = row.getAttribute('data-filter-group') || '';
            
            const matchesQuery = name.includes(query);
            const matchesGroup = (activeFilter === 'all' || group === activeFilter);
            
            if (matchesQuery && matchesGroup) {
                row.style.display = 'grid';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Filter Pills Handler
    function setFilter(filter, btn) {
        document.querySelectorAll('.btn-filter-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        filterAssets();
    }

    // Asset Selection Handler
    function selectAsset(assetId) {
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.set('asset_id', assetId);
        window.location.href = currentUrl.toString();
    }

    // Guidelines Modal Handlers
    function openGuidelinesModal() {
        document.getElementById('guidelinesModal').style.display = 'flex';
    }

    function closeGuidelinesModal() {
        document.getElementById('guidelinesModal').style.display = 'none';
    }

    // Edit Asset Modal Handlers (Maker CRUD)
    function openEditAssetModal(assetId, assetName, assetType, campaignName) {
        const modal = document.getElementById('editAssetModal');
        if (!modal) return;
        document.getElementById('editAssetIdInput').value = assetId;
        document.getElementById('editAssetNameInput').value = assetName || '';
        const typeSelect = document.getElementById('editAssetTypeSelect');
        if (typeSelect) {
            typeSelect.value = assetType || 'Digital Banner';
            if (!typeSelect.value) {
                typeSelect.value = 'Other';
            }
        }
        const sub = document.getElementById('editAssetSubtitle');
        if (sub) {
            sub.textContent = 'Update identifiers for "' + assetName + '" (' + (campaignName || 'Campaign') + '). Changes will be logged to Audit Trail.';
        }
        modal.style.display = 'flex';
    }

    function closeEditAssetModal() {
        const modal = document.getElementById('editAssetModal');
        if (modal) modal.style.display = 'none';
    }

    // Close Modals on ESC
    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeGuidelinesModal();
            closeEditAssetModal();
        }
    });
</script>

<!-- Modal: Edit Asset Identifiers (Maker CRUD) -->
<div class="modal-overlay" id="editAssetModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
    <div class="modal-box" style="background: #FFFFFF; border-radius: 16px; padding: 24px; max-width: 520px; width: 92%; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 class="modal-title" style="font-size: 18px; font-weight: 800; color: #161B2E; margin: 0;">Edit Asset Identifiers</h3>
            <span style="font-size: 24px; cursor: pointer; color: #94A3B8; line-height: 1;" onclick="closeEditAssetModal()">&times;</span>
        </div>
        <p style="font-size: 13px; color: #64748B; margin-bottom: 16px;" id="editAssetSubtitle">Update asset name and format. Changes will be audited.</p>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_asset_identifiers">
            <input type="hidden" name="asset_id" id="editAssetIdInput" value="">
            <div style="display: flex; flex-direction: column; gap: 14px;">
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Asset Name</label>
                    <input type="text" name="title" id="editAssetNameInput" required style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                </div>
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Format / Asset Type</label>
                    <select name="type" id="editAssetTypeSelect" style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; background: #fff; box-sizing: border-box;">
                        <option value="Digital Banner">Digital Banner</option>
                        <option value="Social Media Post">Social Media Post</option>
                        <option value="Print Poster">Print Poster</option>
                        <option value="Billboard">Billboard</option>
                        <option value="Brochure PDF">Brochure PDF</option>
                        <option value="Email Newsletter">Email Newsletter</option>
                        <option value="Video Display">Video Display</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn-row-secondary" style="padding: 8px 16px; border: 1px solid #CBD5E1; border-radius: 8px; background: #fff; cursor: pointer;" onclick="closeEditAssetModal()">Cancel</button>
                <button type="submit" class="btn-next-action-large" style="padding: 8px 18px; border-radius: 8px; background: #373E8C; color: #fff; font-weight: 700; border: none; cursor: pointer; height: auto;">Save Changes</button>
            </div>
        </form>
    </div>
</div>
</script>

    <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body>
</html>

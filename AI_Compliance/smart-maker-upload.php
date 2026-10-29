<?php
/**
 * Template Name: Maker - Smart Asset Upload Workspace
 * Description: Smart Maker Upload Workspace & AI Pre-flight Check Gateway
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

// 4. Handle Form Submission: Upload & Run AI Check
$feedbackAlert = '';
$feedbackSuccess = false;
$uploadedVersionId = 0;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'maker_smart_upload') {
    $campIdPost   = (int) ($_POST['campaign_id'] ?? 0);
    $campNamePost = sanitize_text_field($_POST['campaign_name'] ?? '');
    $uploadMode   = sanitize_text_field($_POST['upload_mode'] ?? 'new_asset');
    $assetIdPost  = (int) ($_POST['asset_id'] ?? 0);
    $assetNamePost = sanitize_text_field($_POST['asset_name'] ?? '');
    $assetTypePost = sanitize_text_field($_POST['asset_type'] ?? '');
    $versionNotes  = sanitize_text_field($_POST['version_notes'] ?? '');
    $uploadedFileName = (!empty($_FILES['asset_file']['name'])) ? $_FILES['asset_file']['name'] : 'asset_file.png';

    // Strict validation
    if ($uploadMode === 'new_asset') {
        if (empty(trim($assetNamePost))) {
            $feedbackAlert = 'Asset Name is required. Please enter an asset name before uploading.';
            $feedbackSuccess = false;
        } elseif (empty(trim($assetTypePost))) {
            $feedbackAlert = 'Asset Type is required. Please select an asset type before uploading.';
            $feedbackSuccess = false;
        }
    } elseif ($uploadMode === 'new_version') {
        if ($assetIdPost <= 0) {
            $feedbackAlert = 'Please select which asset you want to upload a new version for.';
            $feedbackSuccess = false;
        } elseif (empty(trim($assetTypePost))) {
            $feedbackAlert = 'Asset Type is required. Please select an asset type before uploading.';
            $feedbackSuccess = false;
        }
    }

    if (empty($feedbackAlert)) {
        $targetCamp = $campIdPost ?: $campNamePost;
        $targetAsset = ($uploadMode === 'new_version' && $assetIdPost > 0) ? $assetIdPost : $assetNamePost;

        if (function_exists('handle_upload_asset_version')) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }
            $res = handle_upload_asset_version($targetCamp, $targetAsset, $uploadedFileName, $versionNotes, $assetTypePost);
            $uploadedVersionId = (int)$res;
            if (function_exists('pcs_bump_sync_version')) {
                pcs_bump_sync_version();
            }
            if ($uploadedVersionId > 0) {
                $scoringBaseUrl = function_exists('pcs_url') ? pcs_url('ai-scoring-progress.php') : 'ai-scoring-progress.php';
                $sep = (strpos($scoringBaseUrl, '?') !== false) ? '&' : '?';
                $scoringUrl = $scoringBaseUrl . $sep . http_build_query([
                    'version_id'  => $uploadedVersionId,
                    'campaign_id' => $campIdPost,
                    'asset_id'    => $assetIdPost ?: (is_numeric($targetAsset) ? (int)$targetAsset : 0)
                ]);

                // Clean output buffers to guarantee immediate header redirection
                while (ob_get_level()) {
                    ob_end_clean();
                }

                if (function_exists('wp_redirect') && !headers_sent()) {
                    wp_redirect($scoringUrl);
                    exit;
                } elseif (!headers_sent()) {
                    header('Location: ' . $scoringUrl);
                    exit;
                } else {
                    echo '<script>window.location.replace(' . json_encode($scoringUrl) . ');</script>';
                    echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url($scoringUrl) . '"></noscript>';
                    exit;
                }
            } else {
                $feedbackAlert = 'Upload failed. Please ensure an Asset Name is specified and file is valid.';
                $feedbackSuccess = false;
            }
        }
    }
}

// 5. Query Real Data from WordPress Database
$campIdReq  = (int) ($_GET['campaign_id'] ?? 0);
$assetIdReq = (int) ($_GET['asset_id'] ?? 0);
$modeReq    = sanitize_text_field($_GET['mode'] ?? '');

$screenData = function_exists('get_smart_maker_upload_data') ? get_smart_maker_upload_data($campIdReq, $assetIdReq, $modeReq) : [];

$isDbEmpty        = $screenData['is_empty'] ?? true;
$campaign         = $screenData['campaign'] ?? null;
$assets           = $screenData['assets'] ?? [];
$selectedAsset    = $screenData['selected_asset'] ?? null;
$activeMode       = $screenData['mode'] ?? ($modeReq ?: 'new_asset');
$targetVersion    = $screenData['target_version'] ?? 1;
$previousVersion  = $screenData['previous_version'] ?? null;
$previousFeedback = $screenData['previous_feedback'] ?? [];
$guidelines       = $screenData['guidelines'] ?? [];
$returnUrl        = $screenData['return_url'] ?? (function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php');

$campaignId   = $campaign['id'] ?? $campIdReq;
$campaignName = $campaign['name'] ?? 'General Campaign';
$priority     = $campaign['priority'] ?? 'High Priority';
$deadline     = $campaign['deadline'] ?? 'No deadline';
$format       = $campaign['format'] ?? 'Print + Digital';
$ownerName    = $campaign['owner_name'] ?? 'Cam Anh';

$selAssetName = $selectedAsset['name'] ?? '';
$selAssetType = $selectedAsset['format'] ?? '';
$allCampaigns = function_exists('get_all_campaigns') ? get_all_campaigns() : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Compliance AI - Smart Maker Upload Workspace | Brand Compliance AI</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & Liberation Mono / JetBrains Mono -->
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

        .BrandComplianceAiSmartMakerUploadWorkspaceRefined {
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

        /* Top Notification Toast */
        .alert-toast {
            margin: 16px 24px 0;
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

        /* Main Workspace Slot */
        .workspace-slot {
            width: 100%;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* 01 Header & Mode Selector */
        .header-mode-selector {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            flex-wrap: wrap;
            gap: 16px;
        }
        .header-subtitle-tag {
            color: #4F57A7;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 14px;
            letter-spacing: 0.50px;
        }
        .header-main-heading {
            display: flex;
            align-items: baseline;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 4px;
        }
        .heading-title-text {
            color: #1F2674;
            font-size: 28px;
            font-weight: 700;
            line-height: 36px;
        }
        .heading-campaign-dot {
            color: #464651;
            font-size: 13px;
            font-weight: 500;
        }

        /* Segmented Switch */
        .segmented-switch {
            padding: 4px;
            background: #EBEDFF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
        .btn-switch-item {
            padding: 6px 16px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            line-height: 16px;
            letter-spacing: 0.24px;
            color: #464651;
            transition: all 0.15s ease;
            cursor: pointer;
        }
        .btn-switch-item.active {
            background: white;
            color: #373E8C;
            box-shadow: 0px 1px 4px rgba(22, 27, 46, 0.08);
            border-radius: 6px;
        }

        /* 02 Campaign Context Strip */
        .campaign-context-strip {
            width: 100%;
            height: 56px;
            padding: 0 24px;
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }
        .context-label-tag {
            color: #1F2674;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.50px;
        }
        .context-divider {
            color: #C7C5D3;
            font-size: 12px;
            font-weight: 600;
        }
        .context-strong {
            color: #161B2E;
            font-size: 12px;
            font-weight: 600;
        }
        .context-sub {
            color: #464651;
            font-size: 12px;
            font-weight: 600;
        }
        .priority-chip-amber {
            padding: 2px 10px;
            background: #FFF6E5;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #D97706;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: 0.40px;
        }

        /* 03 Main 12-Column Workspace Grid */
        .main-12-columns-grid {
            width: 100%;
            display: grid;
            grid-template-columns: 8fr 4fr;
            gap: 24px;
            align-items: flex-start;
        }

        /* LEFT 8 COLUMNS: Creation & Upload Form */
        .left-8-columns {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .panel-box {
            width: 100%;
            background: white;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            border: 1px solid rgba(226, 232, 240, 0.70);
        }

        .panel-heading {
            color: #1F2674;
            font-size: 16px;
            font-weight: 600;
            line-height: 22px;
        }

        .form-row-2col {
            display: flex;
            gap: 16px;
            width: 100%;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .input-label-tag {
            color: #464651;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.50px;
        }

        .field-container {
            height: 42px;
            padding: 0 14px;
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }
        .field-container:focus-within {
            background: white;
            border-color: #4E56A6;
            box-shadow: 0 0 0 2px rgba(78, 86, 166, 0.15);
        }
        .form-select-clean, .form-input-clean {
            width: 100%;
            height: 100%;
            background: transparent;
            border: none;
            outline: none;
            color: #161B2E;
            font-size: 14px;
            font-weight: 500;
            font-family: inherit;
        }
        .combobox-dropdown-menu {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15), 0 8px 10px -6px rgba(15, 23, 42, 0.1);
            z-index: 1000;
            overflow: hidden;
            text-align: left;
        }
        .combobox-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 13px;
            font-size: 13px;
            color: #1E293B;
            cursor: pointer;
            border-bottom: 1px solid #F1F5F9;
            transition: background 0.15s, color 0.15s;
        }
        .combobox-item:hover, .combobox-item.active {
            background: #EEF2FF;
            color: #373E8C;
        }
        .combobox-item:last-child {
            border-bottom: none;
        }

        /* Large Drop Zone (Hero) */
        .large-drop-zone {
            width: 100%;
            min-height: 310px;
            padding: 30px 24px;
            background: white;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            border: 2px dashed #C7D2FE;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 16px;
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
        }
        .large-drop-zone.has-file {
            cursor: default;
            padding: 18px;
            border-color: #A5B4FC;
            background: #FFFFFF;
        }
        .drop-zone-prompt,
        .drop-zone-prompt * {
            pointer-events: none;
        }
        .active-file-container,
        .active-file-container * {
            pointer-events: auto !important;
        }
        .large-drop-zone.drag-over {
            background: #F3F2FF;
            border-color: #4E56A6;
        }

        .cloud-circle-icon {
            width: 48px;
            height: 48px;
            background: #EBEDFF;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Active File Card */
        .integrated-file-card {
            width: 100%;
            padding: 12px 16px;
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid #E0E7FF;
        }
        .file-thumb-square {
            width: 56px;
            height: 40px;
            background: #DDE1FC;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 4px;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 18px;
        }
        .file-thumb-square img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .file-meta-specs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #464651;
            margin-top: 2px;
        }
        .file-status-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            color: #10B981;
            font-size: 12px;
            font-weight: 600;
        }

        /* Version Notes Textarea */
        .version-notes-textarea {
            width: 100%;
            min-height: 85px;
            padding: 14px 16px;
            background: #F3F2FF;
            border-radius: 8px;
            border: 1px solid transparent;
            font-family: inherit;
            font-size: 14px;
            color: #161B2E;
            outline: none;
            resize: vertical;
            transition: all 0.15s ease;
        }
        .version-notes-textarea:focus {
            background: white;
            border-color: #4E56A6;
            box-shadow: 0 0 0 2px rgba(78, 86, 166, 0.15);
        }

        /* RIGHT 4 COLUMNS: Continuous Context Rail */
        .right-4-columns-rail {
            width: 100%;
            padding: 24px;
            background: white;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(226, 232, 240, 0.70);
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .rail-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .rail-heading {
            color: #1F2674;
            font-size: 13px;
            font-weight: 600;
            line-height: 18px;
            letter-spacing: 0.13px;
        }
        .badge-red-soft {
            padding: 2px 8px;
            background: #FFDAD6;
            border-radius: 9999px;
            color: #93000A;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.40px;
        }

        .version-progression-box {
            padding: 14px;
            background: #F3F2FF;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .progression-sub {
            color: #767682;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.24px;
        }
        .badge-rejected-tiny {
            padding: 2px 6px;
            background: #FFF0F0;
            border-radius: 4px;
            color: #BA1A1A;
            font-size: 11px;
            font-weight: 600;
        }

        .horizontal-divider {
            width: 100%;
            height: 1px;
            background: #EBEDFF;
        }

        /* Previous Feedback List */
        .feedback-items-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .feedback-item-entry {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 12px;
            line-height: 16px;
            color: #161B2E;
        }
        .dot-red-indicator {
            width: 6px;
            height: 6px;
            border-radius: 3px;
            background: #BA1A1A;
            margin-top: 5px;
            flex-shrink: 0;
        }

        /* Applied Guidelines Strip in Rail */
        .guidelines-rail-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .guideline-rail-item {
            padding: 10px;
            background: #F3F2FF;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #161B2E;
            font-weight: 500;
        }

        /* Upload Summary Table */
        .summary-specs-grid {
            display: grid;
            grid-template-columns: 80px 1fr;
            row-gap: 8px;
            font-size: 12px;
        }
        .summary-label {
            color: #464651;
            font-weight: 600;
            letter-spacing: 0.24px;
        }
        .summary-val {
            color: #161B2E;
            font-weight: 500;
            text-align: right;
            word-break: break-all;
        }
        .summary-val-mono {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
        }

        .ai-banner-strip {
            padding: 10px;
            background: #EBEDFF;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #464651;
            font-style: italic;
            margin-top: 10px;
        }

        /* 04 Pre-Upload Ready Strip */
        .preupload-ready-strip {
            width: 100%;
            padding: 14px 24px;
            background: #F3F2FF;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .ready-breadcrumbs {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #161B2E;
            font-weight: 500;
        }

        /* 05 Bottom Action Bar */
        .bottom-action-bar {
            width: 100%;
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .btn-cancel-secondary {
            padding: 10px 20px;
            background: white;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 6px;
            border: 1px solid #CBD5E1;
            color: #161B2E;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .btn-cancel-secondary:hover {
            background: #F8FAFC;
        }
        .btn-submit-ai-check {
            padding: 10px 24px;
            background: #373E8C;
            box-shadow: 0px 2px 8px rgba(55, 62, 140, 0.25);
            border-radius: 6px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .btn-submit-ai-check:hover {
            background: #2D3370;
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

        @media (max-width: 1024px) {
            .main-12-columns-grid { grid-template-columns: 1fr; }
            .form-row-2col { flex-direction: column; }
            .preupload-ready-strip { flex-direction: column; align-items: flex-start; }
            .bottom-action-bar { flex-direction: column; align-items: stretch; }
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

<div class="BrandComplianceAiSmartMakerUploadWorkspaceRefined">

    <!-- 2. ACCESS RESTRICTION NOTICE (IF ROLE IS UNAUTHORIZED) -->
    <?php if (!$isAllowed): ?>
        <main class="workspace-slot">
            <div class="empty-state-box">
                <div class="empty-icon-circle">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#E11D48" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <h2 class="empty-title">Access Restricted to Maker Role</h2>
                <p class="empty-desc">
                    This Smart Upload Workspace is reserved for Creative Makers and Content Authors. 
                    Your account is registered as <strong><?= esc_html($userRole) ?></strong>.
                </p>
                <div style="margin-top: 12px; display: flex; gap: 12px;">
                    <?php if ($userRole === 'ADMIN'): ?>
                        <a href="<?= esc_url(pcs_url('admin-dashboard.php')) ?>" class="btn-submit-ai-check">Return to Admin Dashboard</a>
                    <?php elseif ($userRole === 'CHECKER'): ?>
                        <a href="<?= esc_url(pcs_url('checker-dashboard.php')) ?>" class="btn-submit-ai-check">Return to Review Workspace</a>
                    <?php else: ?>
                        <a href="<?= esc_url(pcs_url('page-login.php')) ?>" class="btn-submit-ai-check">Sign in with Maker Account</a>
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

    <!-- 3. NOTIFICATION / ALERT TOAST -->
    <?php if (!empty($feedbackAlert)): ?>
        <div class="alert-toast alert-success">
            <span><?= esc_html($feedbackAlert) ?></span>
            <div style="display: flex; align-items: center; gap: 12px;">
                <?php if ($uploadedVersionId > 0): ?>
                    <a href="<?= esc_url(pcs_url('detail-asset-maker.php') . '?campaign_id=' . $campaignId) ?>" style="color: #065F46; text-decoration: underline;">
                        View in Asset Workspace →
                    </a>
                <?php endif; ?>
                <span style="cursor: pointer;" onclick="this.parentElement.parentElement.style.display='none';">&times;</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- 4. MAIN WORKSPACE SLOT -->
    <main class="workspace-slot">

        <?php if ($isDbEmpty): ?>
            <!-- EMPTY STATE: NO CAMPAIGNS IN DATABASE -->
            <div class="empty-state-box">
                <div class="empty-icon-circle">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                </div>
                <h2 class="empty-title">No Campaign Found in Database</h2>
                <p class="empty-desc">
                    Creative assets must belong to an active campaign. Please create your first campaign in the Maker Dashboard before uploading assets.
                </p>
                <div style="display: flex; gap: 12px; margin-top: 12px;">
                    <a href="<?= esc_url(pcs_url('maker-dashboard.php')) ?>" class="btn-submit-ai-check">
                        Return to Maker Dashboard
                    </a>
                </div>
            </div>

        <?php else: ?>

            <form method="POST" action="" enctype="multipart/form-data" id="smartUploadForm">
                <input type="hidden" name="action" value="maker_smart_upload">
                <input type="hidden" name="campaign_id" value="<?= (int)$campaignId ?>">
                <input type="hidden" name="campaign_name" value="<?= esc_attr($campaignName) ?>">
                <input type="hidden" name="upload_mode" id="formUploadMode" value="<?= esc_attr($activeMode) ?>">

                <!-- 01 Header & Mode Selector -->
                <div class="header-mode-selector">
                    <div>
                        <div class="header-subtitle-tag">ASSET WORKSPACE</div>
                        <div class="header-main-heading">
                            <h1 class="heading-title-text">Create / Upload Asset</h1>
                            <span class="heading-campaign-dot">• <?= esc_html($campaignName) ?></span>
                        </div>
                    </div>
                    <div class="segmented-switch">
                        <button type="button" class="btn-switch-item <?= ($activeMode === 'new_asset') ? 'active' : '' ?>" id="btnSwitchNewAsset" onclick="switchMode('new_asset')">
                            New Asset
                        </button>
                        <button type="button" class="btn-switch-item <?= ($activeMode === 'new_version') ? 'active' : '' ?>" id="btnSwitchNewVersion" onclick="switchMode('new_version')">
                            New Version
                        </button>
                    </div>
                </div>

                <!-- Section - 02 Campaign Context Strip -->
                <section class="campaign-context-strip" style="margin-top: 24px;">
                    <span class="context-label-tag">CAMPAIGN</span>
                    <span class="context-divider">/</span>
                    <span class="context-strong"><?= esc_html($campaignName) ?></span>
                    <span class="context-divider">•</span>
                    <span class="context-sub"><?= esc_html($format) ?></span>
                    <span class="context-divider">•</span>
                    <span class="context-sub"><?= esc_html($deadline) ?></span>
                    <span class="context-divider">•</span>
                    <span class="context-strong"><?= esc_html($ownerName) ?></span>
                    <span class="context-divider">•</span>
                    <div class="priority-chip-amber">
                        <div style="width: 6px; height: 6px; border-radius: 3px; background: #D97706;"></div>
                        <span><?= esc_html($priority) ?></span>
                    </div>
                </section>

                <!-- 03 Main 12-Column Workspace Grid -->
                <div class="main-12-columns-grid" style="margin-top: 24px;">
                    
                    <!-- LEFT 8 COLUMNS: Creation & Upload Form -->
                    <div class="left-8-columns">
                        
                        <!-- Section A: Asset Details -->
                        <div class="panel-box">
                            <h2 class="panel-heading">Asset Details</h2>
                            <div class="form-row-2col">
                                <!-- Asset Name Field (Dynamic: Text input for New Asset OR Dropdown for New Version) -->
                                <div class="input-group" style="flex: 1.6;">
                                    <label class="input-label-tag" for="assetNameInput" id="assetFieldLabel"><?= ($activeMode === 'new_version') ? 'SELECT ASSET / ASSET NAME' : 'ASSET NAME' ?> <span style="color: #DC2626;">*</span></label>
                                    <div class="field-container" style="position: relative;">
                                        <div id="newAssetInputWrap" style="<?= ($activeMode === 'new_asset') ? '' : 'display: none;' ?> width: 100%;">
                                            <input type="text" name="asset_name" id="assetNameInput" class="form-input-clean" placeholder="e.g. Social Banner 1080x1080" value="<?= esc_attr($activeMode === 'new_asset' ? '' : $selAssetName) ?>" <?= ($activeMode === 'new_asset') ? 'required' : 'disabled' ?> oninput="updateSummary()">
                                        </div>
                                        <div id="newVersionSelectWrap" style="<?= ($activeMode === 'new_version') ? '' : 'display: none;' ?> width: 100%;">
                                            <div style="position: relative; width: 100%; display: flex; align-items: center;">
                                                <input type="text" 
                                                       id="assetSearchInput" 
                                                       class="form-input-clean" 
                                                       placeholder="<?= empty($assets) ? 'No assets in this campaign (Switch to New Asset)' : 'Type to filter or click to choose asset...' ?>" 
                                                       autocomplete="off"
                                                       value="<?= esc_attr($selectedAsset ? ($selectedAsset['name'] . ' (V' . $selectedAsset['version_count'] . ' · ' . ucfirst($selectedAsset['status']) . ')') : '') ?>"
                                                       <?= empty($assets) ? 'readonly' : '' ?>
                                                       onfocus="openAssetDropdown()" 
                                                       oninput="filterAssetDropdown(this.value)"
                                                       onclick="openAssetDropdown()">
                                                <input type="hidden" name="asset_id" id="selectedAssetId" value="<?= (int)($selectedAsset ? $selectedAsset['id'] : 0) ?>" <?= ($activeMode === 'new_version') ? 'required' : 'disabled' ?>>
                                                <div onclick="toggleAssetDropdown(event)" style="cursor: pointer; padding: 4px 6px; display: flex; align-items: center; color: #767682;" title="Toggle Assets Dropdown">
                                                    <svg width="10" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </div>
                                            </div>

                                            <!-- Custom Floating Dropdown Menu for Assets -->
                                            <div id="assetOptionsList" class="combobox-dropdown-menu" style="display: none;">
                                                <?php if (empty($assets)): ?>
                                                    <div style="padding: 14px 16px; font-size: 13px; color: #64748B; text-align: center; line-height: 1.5;">
                                                        No existing assets found in <strong><?= esc_html($campaignName) ?></strong>.<br>
                                                        <span style="font-size: 12px; color: #4F57A7; font-weight: 600; cursor: pointer; text-decoration: underline;" onclick="switchMode('new_asset')">+ Create a New Asset</span>
                                                    </div>
                                                <?php else: ?>
                                                    <div style="display: flex; align-items: center; justify-content: space-between; padding: 7px 14px 5px 14px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; background: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
                                                        <span>Assets in this Campaign</span>
                                                        <span style="background: #E2E8F0; color: #334155; padding: 1px 6px; border-radius: 10px; font-size: 10px;"><?= count($assets) ?></span>
                                                    </div>
                                                    <div id="assetItemsContainer" style="max-height: 230px; overflow-y: auto;">
                                                        <?php foreach ($assets as $ast): ?>
                                                            <div class="combobox-item <?= ($selectedAsset && $selectedAsset['id'] === $ast['id']) ? 'active' : '' ?>"
                                                                 data-id="<?= (int)$ast['id'] ?>"
                                                                 data-name="<?= esc_attr($ast['name']) ?>"
                                                                 data-format="<?= esc_attr($ast['format']) ?>"
                                                                 data-version-count="<?= (int)$ast['version_count'] ?>"
                                                                 data-status="<?= esc_attr($ast['status']) ?>"
                                                                 onclick="selectAssetItem(this)">
                                                                <div style="display: flex; flex-direction: column; gap: 2px;">
                                                                    <span class="combobox-item-title" style="font-size: 13.5px; font-weight: 600; color: #1E293B;"><?= esc_html($ast['name']) ?></span>
                                                                    <span style="font-size: 11.5px; color: #64748B;">Format: <?= esc_html($ast['format']) ?></span>
                                                                </div>
                                                                <div style="display: flex; align-items: center; gap: 6px;">
                                                                    <span style="font-size: 11px; font-weight: 700; color: #4F57A7; background: #EEF2FF; padding: 2px 7px; border-radius: 4px;">V<?= (int)$ast['version_count'] ?></span>
                                                                    <?php 
                                                                    $stBadgeBg = '#F1F5F9';
                                                                    $stBadgeColor = '#475569';
                                                                    $stLower = strtolower($ast['status']);
                                                                    if ($stLower === 'rejected') { $stBadgeBg = '#FEE2E2'; $stBadgeColor = '#991B1B'; }
                                                                    elseif ($stLower === 'in_review' || $stLower === 'in review') { $stBadgeBg = '#FEF3C7'; $stBadgeColor = '#92400E'; }
                                                                    elseif ($stLower === 'approved' || $stLower === 'released') { $stBadgeBg = '#DCFCE7'; $stBadgeColor = '#166534'; }
                                                                    ?>
                                                                    <span style="font-size: 10.5px; font-weight: 700; text-transform: uppercase; color: <?= $stBadgeColor ?>; background: <?= $stBadgeBg ?>; padding: 2px 6px; border-radius: 4px;"><?= esc_html($ast['status']) ?></span>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                    <div id="noMatchAssetMsg" style="display: none; padding: 14px 16px; font-size: 13px; color: #94A3B8; text-align: center;">
                                                        No matching assets found in this campaign
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Asset Type Field -->
                                <div class="input-group" style="flex: 1;">
                                    <label class="input-label-tag" for="assetTypeInput">ASSET TYPE <span style="color: #DC2626;">*</span></label>
                                    <div class="field-container" id="assetTypeFieldContainer" style="position: relative;">
                                        <div style="position: relative; width: 100%; display: flex; align-items: center;">
                                            <input type="text"
                                                   name="asset_type"
                                                   id="assetTypeInput"
                                                   class="form-input-clean"
                                                   placeholder="-- Select or type Asset Type --"
                                                   autocomplete="off"
                                                   value="<?= esc_attr($selAssetType) ?>"
                                                   required
                                                   onfocus="openTypeDropdown()"
                                                   oninput="filterTypeDropdown(this.value); updateSummary()"
                                                   onclick="openTypeDropdown()">
                                            <div onclick="toggleTypeDropdown(event)" style="cursor: pointer; padding: 4px 6px; display: flex; align-items: center; color: #767682;" title="Toggle Type Dropdown">
                                                <svg width="10" height="6" viewBox="0 0 10 6" fill="none"><path d="M1 1L5 5L9 1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                            </div>
                                        </div>

                                        <!-- Custom Floating Dropdown Menu for Asset Types -->
                                        <div id="typeOptionsList" class="combobox-dropdown-menu" style="display: none;">
                                            <?php 
                                            $standardTypes = [
                                                'Digital Ad',
                                                'Social Banner (1080x1080)',
                                                'Story / Vertical (9:16)',
                                                'Print Ad / Poster',
                                                'PDF Brochure',
                                                'Landing Page Asset'
                                            ];
                                            ?>
                                            <div style="padding: 7px 14px 5px 14px; font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; background: #F8FAFC; border-bottom: 1px solid #E2E8F0;">
                                                Select or filter Asset Type
                                            </div>
                                            <div id="typeItemsContainer" style="max-height: 200px; overflow-y: auto;">
                                                <?php foreach ($standardTypes as $stType): ?>
                                                    <div class="combobox-item <?= ($selAssetType === $stType) ? 'active' : '' ?>"
                                                         data-type="<?= esc_attr($stType) ?>"
                                                         onclick="selectTypeItem(this)">
                                                        <span class="combobox-type-title" style="font-size: 13px; font-weight: 600; color: #1E293B;"><?= esc_html($stType) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section B: Upload File (DOMINANT VISUAL HERO) -->
                        <div class="panel-box">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <h2 class="panel-heading">Upload File</h2>
                                <span style="font-size: 10px; font-weight: 700; letter-spacing: 0.40px; color: #464651;">
                                    Target version: <strong style="color: #1F2674;" id="targetVersionHeader">V<?= (int)$targetVersion ?></strong>
                                </span>
                            </div>

                            <div class="large-drop-zone" id="dropZoneArea" style="position: relative;">
                                <input type="file" name="asset_file" id="realFileInput" accept=".png,.jpg,.jpeg,.pdf,.webp" 
                                       style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; z-index: 10;" 
                                       onchange="handleFileSelect(this)">

                                <!-- 1. Empty State Prompt (Hidden when file is chosen) -->
                                <div class="drop-zone-prompt" id="dropZonePrompt" style="display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; width: 100%;">
                                    <div class="cloud-circle-icon">
                                        <svg width="26" height="19" viewBox="0 0 26 19" fill="none">
                                            <path d="M6.41667 18.6667C4.64722 18.6667 3.13542 18.0542 1.88125 16.8292C0.627083 15.6042 0 14.1069 0 12.3375C0 10.8208 0.456944 9.46945 1.37083 8.28333C2.28472 7.09722 3.48056 6.33889 4.95833 6.00833C5.44444 4.21944 6.41667 2.77083 7.875 1.6625C9.33333 0.554167 10.9861 0 12.8333 0C15.1083 0 17.0382 0.792361 18.6229 2.37708C20.2076 3.96181 21 5.89167 21 8.16667C22.3417 8.32222 23.4549 8.90069 24.3396 9.90208C25.2243 10.9035 25.6667 12.075 25.6667 13.4167C25.6667 14.875 25.1562 16.1146 24.1354 17.1354C23.1146 18.1562 21.875 18.6667 20.4167 18.6667H14C13.3583 18.6667 12.809 18.4382 12.3521 17.9813C11.8951 17.5243 11.6667 16.975 11.6667 16.3333V10.325L9.8 12.1333L8.16667 10.5L12.8333 5.83333L17.5 10.5L15.8667 12.1333L14 10.325V16.3333H20.4167C21.2333 16.3333 21.9236 16.0514 22.4875 15.4875C23.0514 14.9236 23.3333 14.2333 23.3333 13.4167C23.3333 12.6 23.0514 11.9097 22.4875 11.3458C21.9236 10.7819 21.2333 10.5 20.4167 10.5H18.6667V8.16667C18.6667 6.55278 18.0979 5.17708 16.9604 4.03958C15.8229 2.90208 14.4472 2.33333 12.8333 2.33333C11.2194 2.33333 9.84375 2.90208 8.70625 4.03958C7.56875 5.17708 7 6.55278 7 8.16667H6.41667C5.28889 8.16667 4.32639 8.56528 3.52917 9.3625C2.73194 10.1597 2.33333 11.1222 2.33333 12.25C2.33333 13.3778 2.73194 14.3403 3.52917 15.1375C4.32639 15.9347 5.28889 16.3333 6.41667 16.3333H9.33333V18.6667H6.41667V18.6667M12.8333 10.5" fill="#4F57A7"/>
                                        </svg>
                                    </div>
                                    <div style="text-align: center;">
                                        <div style="display: flex; align-items: center; justify-content: center; gap: 6px;">
                                            <span style="color: #1F2674; font-size: 18px; font-weight: 600;">Drop file here</span>
                                            <span style="color: #464651; font-size: 14px;">or</span>
                                            <span style="color: #4F57A7; font-size: 13px; font-weight: 600; text-decoration: underline;">choose file</span>
                                        </div>
                                        <p style="color: #767682; font-size: 12px; margin-top: 6px;">PDF • PNG • JPG • Max 50 MB</p>
                                    </div>
                                </div>

                                <!-- 2. Active File Preview Container (Expands to dashed frame) -->
                                <div class="active-file-container" id="activeFileContainer" style="display: none; width: 100%; flex-direction: column; align-items: center; gap: 14px; position: relative; z-index: 20;" onclick="event.stopPropagation();">
                                    <!-- Expanded visual frame across the dashed dropzone -->
                                    <div class="expanded-file-preview-box" id="expandedFilePreviewBox" style="width: 100%; height: 320px; max-height: 400px; background: #FAF8FF; border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; position: relative; border: 1px solid #E2E8F0;">
                                        <img id="expandedImgPreview" src="" alt="Artwork Preview" style="display: none; max-width: 100%; max-height: 100%; object-fit: contain; border-radius: 6px;">
                                        <iframe id="expandedPdfPreview" src="" style="display: none; width: 100%; height: 100%; border: none; border-radius: 6px; background: #ffffff;"></iframe>
                                    </div>

                                    <!-- Integrated Action Bar with Replace and Remove -->
                                    <div class="integrated-file-card" id="activeFileCard" style="width: 100%; position: relative; z-index: 25;" onclick="event.stopPropagation();">
                                        <div style="display: flex; align-items: center; gap: 14px;">
                                            <div class="file-thumb-square" id="fileThumbPreview">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                            </div>
                                            <div>
                                                <strong style="color: #161B2E; font-size: 13px; display: block;" id="cardFileName">file.png</strong>
                                                <div class="file-meta-specs">
                                                    <span id="cardFileFormat">PNG</span>
                                                    <span>•</span>
                                                    <span id="cardFileSize">0 KB</span>
                                                    <span>•</span>
                                                    <div class="file-status-pill">
                                                        <div style="width: 6px; height: 6px; border-radius: 3px; background: #10B981;"></div>
                                                        <span>Ready</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 16px; align-items: center;">
                                            <button type="button" class="btn-action-replace" style="color: #4F57A7; font-size: 12.5px; font-weight: 700; text-decoration: underline; cursor: pointer; padding: 4px 8px; border: none; background: none; position: relative; z-index: 30;" onclick="triggerReplaceFile(event)">Replace</button>
                                            <button type="button" class="btn-action-remove" style="color: #BA1A1A; font-size: 12.5px; font-weight: 700; text-decoration: underline; cursor: pointer; padding: 4px 8px; border: none; background: none; position: relative; z-index: 30;" onclick="triggerRemoveFile(event)">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Section 6: Version Notes -->
                        <div class="panel-box">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <label class="input-label-tag" for="versionNotesInput">NOTE (OPTIONAL)</label>
                                <span style="color: #464651; font-size: 12px;">Visible to Checker during compliance review</span>
                            </div>
                            <textarea name="version_notes" id="versionNotesInput" class="version-notes-textarea" placeholder="e.g., Updated partner logo to 2026 variant and added MAS disclosure block per Sarah Lin's feedback."></textarea>
                        </div>

                    </div>

                    <!-- RIGHT 4 COLUMNS: Continuous Context Rail -->
                    <aside class="right-4-columns-rail">
                        
                        <!-- Section 1: Version Progression -->
                        <div id="versionProgressionSection">
                            <div class="input-label-tag" style="margin-bottom: 8px;">VERSION</div>
                            <div class="version-progression-box">
                                <div>
                                    <div class="progression-sub">Previous</div>
                                    <div style="display: flex; align-items: center; gap: 6px; margin-top: 2px;">
                                        <strong style="font-size: 16px; color: #161B2E;" id="prevVerLabel">
                                            <?= $previousVersion ? esc_html($previousVersion['version_label']) : '—' ?>
                                        </strong>
                                        <?php if ($previousVersion): ?>
                                            <span class="badge-rejected-tiny" id="prevVerStatusBadge">
                                                <?= esc_html($previousVersion['status_label']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <svg width="16" height="8" viewBox="0 0 16 8" fill="none">
                                    <path d="M12.0833 7.5L10.8958 6.33333L12.6458 4.58333H0V2.91667H12.6458L10.9167 1.16667L12.1042 0L15.8333 3.75L12.0833 7.5Z" fill="#C7C5D3"/>
                                </svg>
                                <div style="text-align: right;">
                                    <div style="color: #4F57A7; font-size: 12px; font-weight: 600;">New Version</div>
                                    <strong style="color: #1F2674; font-size: 18px;" id="targetVersionRail">
                                        V<?= (int)$targetVersion ?>
                                    </strong>
                                </div>
                            </div>
                            <p style="color: #464651; font-size: 12px; margin-top: 8px;" id="progressionSubtitle">
                                New upload creates V<?= (int)$targetVersion ?> for review pipeline.
                            </p>
                        </div>

                        <div class="horizontal-divider"></div>

                        <!-- Section 2: Previous Feedback -->
                        <div id="previousFeedbackSection">
                            <div class="rail-section-header">
                                <h3 class="rail-heading">Previous Feedback</h3>
                                <span class="badge-red-soft" id="feedbackCountBadge">
                                    <?= count($previousFeedback) ?> Items
                                </span>
                            </div>
                            <div class="feedback-items-list" id="feedbackItemsList">
                                <?php if (empty($previousFeedback)): ?>
                                    <p style="color: #64748B; font-size: 12px;">No open compliance flags from previous review.</p>
                                <?php else: ?>
                                    <?php foreach ($previousFeedback as $fb): ?>
                                        <div class="feedback-item-entry">
                                            <div class="dot-red-indicator"></div>
                                            <div>
                                                <strong><?= esc_html($fb['tag']) ?>:</strong>
                                                <span><?= esc_html($fb['desc']) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($previousFeedback)): ?>
                                <div style="margin-top: 10px;">
                                    <a href="<?= esc_url(pcs_url('detail-asset-maker.php') . '?campaign_id=' . $campaignId) ?>" style="color: #4F57A7; font-size: 12px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                        <span>View Feedback</span>
                                        <span>→</span>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="horizontal-divider"></div>

                        <!-- Section 3: Applied Guidelines -->
                        <div>
                            <div class="rail-section-header">
                                <h3 class="rail-heading">Applied Guidelines</h3>
                            </div>
                            <div class="guidelines-rail-list">
                                <?php foreach ($guidelines as $g): ?>
                                    <div class="guideline-rail-item">
                                        <span><?= esc_html($g['name']) ?></span>
                                        <?php if (!empty($g['url'])): ?>
                                            <a href="<?= esc_url($g['url']) ?>" target="_blank" style="color: #4F57A7; font-size: 10px; font-weight: 600;">View →</a>
                                        <?php else: ?>
                                            <span style="color: #4F57A7; font-size: 10px; font-weight: 600;">View →</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="horizontal-divider"></div>

                        <!-- Section 4: Upload Summary -->
                        <div>
                            <div class="rail-section-header">
                                <h3 class="rail-heading">Upload Summary</h3>
                            </div>
                            <div class="summary-specs-grid">
                                <span class="summary-label">Campaign:</span>
                                <span class="summary-val"><?= esc_html($campaignName) ?></span>

                                <span class="summary-label">Asset:</span>
                                <span class="summary-val" id="summaryAssetName"><?= esc_html($selAssetName) ?></span>

                                <span class="summary-label">Version:</span>
                                <span class="summary-val" style="color: #1F2674; font-weight: 700;" id="summaryVersionNum">V<?= (int)$targetVersion ?></span>

                                <span class="summary-label">File:</span>
                                <span class="summary-val summary-val-mono" id="summaryFileName">No file selected</span>

                                <span class="summary-label">Status:</span>
                                <div class="summary-val" style="display: flex; align-items: center; justify-content: flex-end; gap: 4px;" id="summaryStatusRow">
                                    <div style="width: 6px; height: 6px; border-radius: 3px; background: #94A3B8;"></div>
                                    <span style="color: #64748B;">Waiting</span>
                                </div>
                            </div>

                            <div class="ai-banner-strip">
                                <svg width="15" height="15" viewBox="0 0 15 15" fill="none">
                                    <path d="M12 5.33333L11.1667 3.5L9.33333 2.66667L11.1667 1.83333L12 0L12.8333 1.83333L14.6667 2.66667L12.8333 3.5L12 5.33333M12 14.6667L11.1667 12.8333L9.33333 12L11.1667 11.1667L12 9.33333L12.8333 11.1667L14.6667 12L12.8333 12.8333L12 14.6667M5.33333 12.6667L3.66667 9L0 7.33333L3.66667 5.66667L5.33333 2L7 5.66667L10.6667 7.33333L7 9L5.33333 12.6667Z" fill="#005048"/>
                                </svg>
                                <span>AI check starts immediately after upload.</span>
                            </div>
                        </div>

                    </aside>

                </div>

                <!-- Section - 04 Pre-Upload Ready Strip -->
                <section class="preupload-ready-strip" style="margin-top: 24px;">
                    <div class="ready-breadcrumbs">
                        <span><?= esc_html($campaignName) ?></span>
                        <span style="color: #C7C5D3;">/</span>
                        <span id="stripAssetName"><?= esc_html($selAssetName) ?></span>
                        <span style="color: #C7C5D3;">/</span>
                        <strong style="color: #1F2674;" id="stripVersionNum">V<?= (int)$targetVersion ?></strong>
                    </div>

                    <div style="display: flex; align-items: center; gap: 8px;">
                        <svg width="11" height="14" viewBox="0 0 11 14" fill="none">
                            <path d="M2.66667 10.6667H8V9.33333H2.66667V10.6667ZM2.66667 8H8V6.66667H2.66667V8ZM1.33333 13.3333C0.966667 13.3333 0.652778 13.2028 0.391667 12.9417C0.130556 12.6806 0 12.3667 0 12V1.33333C0 0.966667 0.130556 0.652778 0.391667 0.391667C0.652778 0.130556 0.966667 0 1.33333 0H6.66667L10.6667 4V12C10.6667 12.3667 10.5361 12.6806 10.275 12.9417C10.0139 13.2028 9.7 13.3333 9.33333 13.3333H1.33333ZM6 4.66667V1.33333H1.33333V12H9.33333V4.66667H6Z" fill="#767682"/>
                        </svg>
                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 12px; color: #464651;" id="stripFileName">no file selected</span>
                    </div>

                    <div style="display: flex; align-items: center; gap: 6px;">
                        <div style="width: 8px; height: 8px; border-radius: 4px; background: #94A3B8;" id="stripStatusDot"></div>
                        <span style="font-size: 12px; font-weight: 600; color: #64748B;" id="stripStatusText">Waiting for File</span>
                    </div>
                </section>

                <!-- Footer - 05 Bottom Action Bar -->
                <div class="bottom-action-bar">
                    <div style="color: #464651; font-size: 12px;">
                        <span id="footerNextNotice">New upload creates V<?= (int)$targetVersion ?></span>
                    </div>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <a href="<?= esc_url($returnUrl) ?>" class="btn-cancel-secondary">Cancel</a>
                        <button type="submit" class="btn-submit-ai-check" id="btnSubmitCheck">
                            <svg width="17" height="16" viewBox="0 0 17 16" fill="none">
                                <path d="M5.7 15.75L4.275 13.35L1.575 12.75L1.8375 9.975L0 7.875L1.8375 5.775L1.575 3L4.275 2.4L5.7 0L8.25 1.0875L10.8 0L12.225 2.4L14.925 3L14.6625 5.775L16.5 7.875L14.6625 9.975L14.925 12.75L12.225 13.35L10.8 15.75L8.25 14.6625L5.7 15.75ZM7.4625 10.5375L11.7 6.3L10.65 5.2125L7.4625 8.4L5.85 6.825L4.8 7.875L7.4625 10.5375Z" fill="white"/>
                            </svg>
                            <span>Upload & Run AI Check</span>
                        </button>
                    </div>
                </div>

            </form>

        <?php endif; ?>

    </main>

</div>

<!-- ================= 6. INTERACTIVE CLIENT-SIDE SCRIPT ================= -->
<script>
    let currentMode = '<?= esc_js($activeMode) ?>';
    let hasFile = false;

    // Mode Switcher: New Asset vs New Version
    function switchMode(mode) {
        currentMode = mode;
        document.getElementById('formUploadMode').value = mode;

        const btnAsset = document.getElementById('btnSwitchNewAsset');
        const btnVer = document.getElementById('btnSwitchNewVersion');
        const newAssetWrap = document.getElementById('newAssetInputWrap');
        const newVerWrap = document.getElementById('newVersionSelectWrap');
        const feedbackSec = document.getElementById('previousFeedbackSection');
        const nameInput = document.getElementById('assetNameInput');
        const assetSearch = document.getElementById('assetSearchInput');
        const selectedAssetId = document.getElementById('selectedAssetId');
        const label = document.getElementById('assetFieldLabel');

        if (mode === 'new_asset') {
            btnAsset.classList.add('active');
            btnVer.classList.remove('active');
            newAssetWrap.style.display = 'block';
            newVerWrap.style.display = 'none';

            if (label) label.innerHTML = 'ASSET NAME <span style="color: #DC2626;">*</span>';
            if (nameInput) {
                nameInput.disabled = false;
                nameInput.required = true;
            }
            if (selectedAssetId) {
                selectedAssetId.disabled = true;
                selectedAssetId.required = false;
            }
            if (assetSearch) {
                assetSearch.disabled = true;
            }

            // Target version for brand new asset is V1
            setTargetVersion(1, '—', 'None');
            if (feedbackSec) feedbackSec.style.display = 'none';
        } else {
            btnVer.classList.add('active');
            btnAsset.classList.remove('active');
            newAssetWrap.style.display = 'none';
            newVerWrap.style.display = 'block';

            if (label) label.innerHTML = 'SELECT ASSET / ASSET NAME <span style="color: #DC2626;">*</span>';
            if (nameInput) {
                nameInput.disabled = true;
                nameInput.required = false;
            }
            if (selectedAssetId) {
                selectedAssetId.disabled = false;
                selectedAssetId.required = true;
            }
            if (assetSearch) {
                assetSearch.disabled = false;
            }

            if (selectedAssetId && parseInt(selectedAssetId.value, 10) > 0) {
                // Keep target version from current selected asset
            } else {
                setTargetVersion(1, '—', 'None');
            }
            if (feedbackSec) feedbackSec.style.display = 'block';
        }
        updateSummary();
    }

    window.addEventListener('DOMContentLoaded', function() {
        switchMode(currentMode);
    });

    // Combobox: Asset Dropdown Handlers
    function openAssetDropdown() {
        const list = document.getElementById('assetOptionsList');
        if (list) {
            list.style.display = 'block';
            const typeList = document.getElementById('typeOptionsList');
            if (typeList) typeList.style.display = 'none';
        }
    }
    function closeAssetDropdown() {
        const list = document.getElementById('assetOptionsList');
        if (list) list.style.display = 'none';
    }
    function toggleAssetDropdown(e) {
        if (e) e.stopPropagation();
        const list = document.getElementById('assetOptionsList');
        if (!list) return;
        if (list.style.display === 'block') {
            list.style.display = 'none';
        } else {
            openAssetDropdown();
            const input = document.getElementById('assetSearchInput');
            if (input) input.focus();
        }
    }
    function filterAssetDropdown(query) {
        openAssetDropdown();
        const term = (query || '').toLowerCase().trim();
        const container = document.getElementById('assetItemsContainer');
        if (!container) return;
        const items = container.querySelectorAll('.combobox-item');
        let visibleCount = 0;
        items.forEach(function(item) {
            const name = (item.getAttribute('data-name') || '').toLowerCase();
            const format = (item.getAttribute('data-format') || '').toLowerCase();
            const status = (item.getAttribute('data-status') || '').toLowerCase();
            if (!term || name.indexOf(term) !== -1 || format.indexOf(term) !== -1 || status.indexOf(term) !== -1) {
                item.style.display = 'flex';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });
        const noMsg = document.getElementById('noMatchAssetMsg');
        if (noMsg) {
            noMsg.style.display = (visibleCount === 0) ? 'block' : 'none';
        }
    }
    function selectAssetItem(elem) {
        const aid = elem.getAttribute('data-id');
        const name = elem.getAttribute('data-name');
        const format = elem.getAttribute('data-format');
        const vCount = parseInt(elem.getAttribute('data-version-count') || '1', 10);
        const status = elem.getAttribute('data-status') || 'draft';

        const selectedIdInput = document.getElementById('selectedAssetId');
        if (selectedIdInput) selectedIdInput.value = aid;

        const searchInput = document.getElementById('assetSearchInput');
        if (searchInput) searchInput.value = name + ' (V' + vCount + ' · ' + status.charAt(0).toUpperCase() + status.slice(1) + ')';

        // Mark active item in list
        const container = document.getElementById('assetItemsContainer');
        if (container) {
            container.querySelectorAll('.combobox-item').forEach(function(el) {
                el.classList.remove('active');
            });
            elem.classList.add('active');
        }

        closeAssetDropdown();

        // Auto-sync Asset Type if present
        if (format) {
            const typeInput = document.getElementById('assetTypeInput');
            if (typeInput) typeInput.value = format;
            const typeContainer = document.getElementById('typeItemsContainer');
            if (typeContainer) {
                typeContainer.querySelectorAll('.combobox-item').forEach(function(el) {
                    if (el.getAttribute('data-type') === format) el.classList.add('active');
                    else el.classList.remove('active');
                });
            }
        }

        setTargetVersion(vCount + 1, 'V' + vCount, status.charAt(0).toUpperCase() + status.slice(1));
        updateSummary();
    }

    // Combobox: Type Dropdown Handlers
    function openTypeDropdown() {
        const list = document.getElementById('typeOptionsList');
        if (list) {
            list.style.display = 'block';
            const assetList = document.getElementById('assetOptionsList');
            if (assetList) assetList.style.display = 'none';
        }
    }
    function closeTypeDropdown() {
        const list = document.getElementById('typeOptionsList');
        if (list) list.style.display = 'none';
    }
    function toggleTypeDropdown(e) {
        if (e) e.stopPropagation();
        const list = document.getElementById('typeOptionsList');
        if (!list) return;
        if (list.style.display === 'block') {
            list.style.display = 'none';
        } else {
            openTypeDropdown();
            const input = document.getElementById('assetTypeInput');
            if (input) input.focus();
        }
    }
    function filterTypeDropdown(query) {
        openTypeDropdown();
        const term = (query || '').toLowerCase().trim();
        const container = document.getElementById('typeItemsContainer');
        if (!container) return;
        const items = container.querySelectorAll('.combobox-item');
        items.forEach(function(item) {
            const type = (item.getAttribute('data-type') || '').toLowerCase();
            if (!term || type.indexOf(term) !== -1) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }
    function selectTypeItem(elem) {
        const type = elem.getAttribute('data-type');
        const input = document.getElementById('assetTypeInput');
        if (input) input.value = type;

        const container = document.getElementById('typeItemsContainer');
        if (container) {
            container.querySelectorAll('.combobox-item').forEach(function(el) {
                el.classList.remove('active');
            });
            elem.classList.add('active');
        }

        closeTypeDropdown();
        updateSummary();
    }

    // Close floating dropdown menus on outside click
    document.addEventListener('click', function(e) {
        const assetWrap = document.getElementById('newVersionSelectWrap');
        const assetList = document.getElementById('assetOptionsList');
        if (assetList && assetWrap && !assetWrap.contains(e.target)) {
            assetList.style.display = 'none';
        }

        const typeField = document.getElementById('assetTypeFieldContainer');
        const typeList = document.getElementById('typeOptionsList');
        if (typeList && typeField && !typeField.contains(e.target)) {
            typeList.style.display = 'none';
        }
    });

    function setTargetVersion(targetNum, prevLabel, prevStatus) {
        document.getElementById('targetVersionHeader').textContent = 'V' + targetNum;
        document.getElementById('targetVersionRail').textContent = 'V' + targetNum;
        document.getElementById('summaryVersionNum').textContent = 'V' + targetNum;
        document.getElementById('stripVersionNum').textContent = 'V' + targetNum;
        document.getElementById('footerNextNotice').textContent = 'New upload creates V' + targetNum;

        const prevLbl = document.getElementById('prevVerLabel');
        if (prevLbl) prevLbl.textContent = prevLabel;
        const prevBdg = document.getElementById('prevVerStatusBadge');
        if (prevBdg) prevBdg.textContent = prevStatus;
    }

    // File Selection & Drag & Drop Handling
    function handleDropZoneClick(e) {
        if (hasFile) return;
        var input = document.getElementById('realFileInput');
        if (input) input.click();
    }

    function triggerReplaceFile(e) {
        if (e) {
            e.stopPropagation();
            e.preventDefault();
        }
        var input = document.getElementById('realFileInput');
        if (input) {
            input.value = '';
            input.style.pointerEvents = 'auto';
            input.click();
        }
    }

    function triggerRemoveFile(e) {
        if (e) {
            e.stopPropagation();
            e.preventDefault();
        }
        removeSelectedFile();
    }

    function handleFileSelect(input) {
        var file = input.files && input.files[0];
        if (!file) return;

        hasFile = true;
        if (input) input.style.pointerEvents = 'none';
        var fileName = file.name;
        var fileSizeMB = (file.size / (1024 * 1024)).toFixed(1);
        var fileExt = fileName.split('.').pop().toUpperCase();
        var isPdf = (file.type === 'application/pdf' || fileExt === 'PDF');

        // Toggle dropzone states
        var dropArea = document.getElementById('dropZoneArea');
        var promptEl = document.getElementById('dropZonePrompt');
        var fileContainer = document.getElementById('activeFileContainer');
        var imgPreview = document.getElementById('expandedImgPreview');
        var pdfPreview = document.getElementById('expandedPdfPreview');

        if (dropArea) dropArea.classList.add('has-file');
        if (promptEl) promptEl.style.display = 'none';
        if (fileContainer) fileContainer.style.display = 'flex';

        // Render expanded preview spanning the dashed area
        if (isPdf) {
            if (imgPreview) { imgPreview.style.display = 'none'; imgPreview.src = ''; }
            if (pdfPreview) {
                pdfPreview.style.display = 'block';
                pdfPreview.src = URL.createObjectURL(file) + '#toolbar=0&navpanes=0&view=Fit';
            }
        } else {
            if (pdfPreview) { pdfPreview.style.display = 'none'; pdfPreview.src = ''; }
            if (imgPreview) {
                imgPreview.style.display = 'block';
                var reader = new FileReader();
                reader.onload = function(e) {
                    imgPreview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        }

        // Update active file card
        document.getElementById('cardFileName').textContent = fileName;
        document.getElementById('cardFileFormat').textContent = fileExt;
        document.getElementById('cardFileSize').textContent = fileSizeMB + ' MB';

        // Update thumbnail preview
        var thumbBox = document.getElementById('fileThumbPreview');
        if (!isPdf) {
            var readerThumb = new FileReader();
            readerThumb.onload = function(e) {
                thumbBox.innerHTML = '<img src="' + e.target.result + '" alt="Preview" style="width:100%;height:100%;object-fit:cover;">';
            };
            readerThumb.readAsDataURL(file);
        } else {
            thumbBox.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#DC2626" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
        }

        // Update Summary & Strip
        document.getElementById('summaryFileName').textContent = fileName;
        document.getElementById('summaryStatusRow').innerHTML = '<div style="width: 6px; height: 6px; border-radius: 3px; background: #10B981;"></div><span style="color: #10B981; font-weight: 600;">Ready</span>';

        document.getElementById('stripFileName').textContent = fileName;
        document.getElementById('stripStatusDot').style.background = '#10B981';
        document.getElementById('stripStatusText').textContent = 'Ready';
        document.getElementById('stripStatusText').style.color = '#10B981';
    }

    function removeSelectedFile() {
        var input = document.getElementById('realFileInput');
        if (input) {
            input.value = '';
            input.style.pointerEvents = 'auto';
        }
        hasFile = false;

        var dropArea = document.getElementById('dropZoneArea');
        var promptEl = document.getElementById('dropZonePrompt');
        var fileContainer = document.getElementById('activeFileContainer');
        var imgPreview = document.getElementById('expandedImgPreview');
        var pdfPreview = document.getElementById('expandedPdfPreview');

        if (dropArea) dropArea.classList.remove('has-file');
        if (promptEl) promptEl.style.display = 'flex';
        if (fileContainer) fileContainer.style.display = 'none';

        if (imgPreview) { imgPreview.src = ''; imgPreview.style.display = 'none'; }
        if (pdfPreview) { pdfPreview.src = ''; pdfPreview.style.display = 'none'; }

        document.getElementById('cardFileName').textContent = '';
        document.getElementById('cardFileFormat').textContent = '';
        document.getElementById('cardFileSize').textContent = '';

        document.getElementById('summaryFileName').textContent = 'No file selected';
        document.getElementById('summaryStatusRow').innerHTML = '<div style="width: 6px; height: 6px; border-radius: 3px; background: #94A3B8;"></div><span style="color: #64748B;">Waiting</span>';

        document.getElementById('stripFileName').textContent = 'no file selected';
        document.getElementById('stripStatusDot').style.background = '#94A3B8';
        document.getElementById('stripStatusText').textContent = 'Waiting for File';
        document.getElementById('stripStatusText').style.color = '#64748B';
    }

    // Prevent default browser behavior on drop outside dropzone (prevents opening files in tab)
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        window.addEventListener(eventName, function(e) {
            e.preventDefault();
        }, false);
    });

    // Drag & Drop Listeners
    const dropArea = document.getElementById('dropZoneArea');
    if (dropArea) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (e.dataTransfer) {
                    e.dataTransfer.dropEffect = 'copy';
                }
                dropArea.classList.add('drag-over');
            }, false);
        });

        dropArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            if (!e.relatedTarget || !dropArea.contains(e.relatedTarget)) {
                dropArea.classList.remove('drag-over');
            }
        }, false);

        dropArea.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.remove('drag-over');
            const dt = e.dataTransfer;
            if (dt && dt.files && dt.files.length > 0) {
                const input = document.getElementById('realFileInput');
                if (input) {
                    try {
                        input.files = dt.files;
                    } catch (err) {
                        try {
                            const transfer = new DataTransfer();
                            for (let i = 0; i < dt.files.length; i++) {
                                transfer.items.add(dt.files[i]);
                            }
                            input.files = transfer.files;
                        } catch (e2) {}
                    }
                    handleFileSelect(input);
                }
            }
        }, false);
    }

    // Dynamic Summary update
    function updateSummary() {
        let name = '';
        if (currentMode === 'new_asset') {
            name = document.getElementById('assetNameInput').value.trim() || 'New Asset';
        } else {
            const searchInput = document.getElementById('assetSearchInput');
            if (searchInput && searchInput.value) {
                name = searchInput.value.split(' (V')[0].trim() || 'Selected Asset';
            } else {
                name = 'Please select asset';
            }
        }
        const sumName = document.getElementById('summaryAssetName');
        if (sumName) sumName.textContent = name;
        const stripName = document.getElementById('stripAssetName');
        if (stripName) stripName.textContent = name;
    }

    // Client-side Validation & Immediate AI Scoring Transition on Submit
    const form = document.getElementById('smartUploadForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (currentMode === 'new_asset') {
                const nameInput = document.getElementById('assetNameInput');
                if (!nameInput || !nameInput.value.trim()) {
                    e.preventDefault();
                    alert('Please enter an Asset Name before uploading.');
                    if (nameInput) nameInput.focus();
                    return false;
                }
                const typeInput = document.getElementById('assetTypeInput');
                if (!typeInput || !typeInput.value.trim()) {
                    e.preventDefault();
                    alert('Please select or enter an Asset Type before uploading.');
                    if (typeInput) typeInput.focus();
                    return false;
                }
            } else {
                const selectedAssetId = document.getElementById('selectedAssetId');
                const aid = selectedAssetId ? parseInt(selectedAssetId.value, 10) : 0;
                if (!aid || aid <= 0) {
                    e.preventDefault();
                    alert('Please select which asset you want to upload a new version for.');
                    const assetSearch = document.getElementById('assetSearchInput');
                    if (assetSearch) {
                        assetSearch.focus();
                        openAssetDropdown();
                    }
                    return false;
                }
                const typeInput = document.getElementById('assetTypeInput');
                if (!typeInput || !typeInput.value.trim()) {
                    e.preventDefault();
                    alert('Please select or enter an Asset Type before uploading.');
                    if (typeInput) typeInput.focus();
                    return false;
                }
            }

            const fileInput = document.getElementById('realFileInput');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('Please select or drop an asset file (PNG, JPG, PDF) to run the AI compliance check.');
                document.getElementById('dropZoneArea').scrollIntoView({ behavior: 'smooth' });
                return false;
            }

            const btn = document.getElementById('btnSubmitCheck');
            if (btn) {
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.85';
                btn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="animation: spin 1s infinite linear; display: inline-block;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> <span>Opening AI Progress...</span>';
            }

            // Standard native POST submission to instantly transfer asset and open ai-scoring-progress
            return true;
        });
    }
</script>

    <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body>
</html>

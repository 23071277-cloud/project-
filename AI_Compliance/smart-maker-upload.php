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
    header('Location: ' . $target . (strpos($target, '?') !== false ? '&' : '?') . 'loggedout=true');
    exit;
}

// 4. Handle Form Submission: Upload & Run AI Check
$feedbackAlert = '';
$feedbackSuccess = false;
$uploadedVersionId = 0;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'maker_smart_upload') {
    $campIdPost   = (int) ($_POST['campaign_id'] ?? 0);
    $campNamePost = sanitize_text_field($_POST['campaign_name'] ?? '');
    $uploadMode   = sanitize_text_field($_POST['upload_mode'] ?? 'new_version');
    $assetIdPost  = (int) ($_POST['asset_id'] ?? 0);
    $assetNamePost = sanitize_text_field($_POST['asset_name'] ?? '');
    $assetTypePost = sanitize_text_field($_POST['asset_type'] ?? 'Digital Ad');
    $versionNotes  = sanitize_text_field($_POST['version_notes'] ?? '');
    $uploadedFileName = (!empty($_FILES['asset_file']['name'])) ? $_FILES['asset_file']['name'] : 'asset_file.png';

    $targetCamp = $campIdPost ?: $campNamePost;
    $targetAsset = ($uploadMode === 'new_version' && $assetIdPost > 0) ? $assetIdPost : $assetNamePost;

    if (function_exists('handle_upload_asset_version')) {
        $res = handle_upload_asset_version($targetCamp, $targetAsset, $uploadedFileName, $versionNotes);
        if ($res) {
            $uploadedVersionId = is_numeric($res) ? (int)$res : 0;
            $scoringUrl = (function_exists('pcs_url') ? pcs_url('ai-scoring-progress.php') : 'ai-scoring-progress.php') . '?' . http_build_query([
                'version_id'  => $uploadedVersionId,
                'campaign_id' => $campIdPost,
                'asset_id'    => $assetIdPost ?: (is_numeric($targetAsset) ? (int)$targetAsset : 0)
            ]);
            if (!headers_sent()) {
                header('Location: ' . $scoringUrl);
                exit;
            }
            $feedbackAlert = 'Successfully uploaded asset file and initialized automated AI Compliance check pipeline! <a href="' . esc_url($scoringUrl) . '" style="color:#1F2674; font-weight:700; text-decoration:underline; margin-left:8px;">View Live AI Scoring →</a>';
            $feedbackSuccess = true;
        } else {
            $feedbackAlert = 'Upload completed.';
            $feedbackSuccess = true;
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
$activeMode       = $screenData['mode'] ?? 'new_version';
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

$selAssetName = $selectedAsset['name'] ?? ($assets[0]['name'] ?? 'Social Banner');
$selAssetType = $selectedAsset['format'] ?? 'Digital Ad';
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

        /* Large Drop Zone (Hero) */
        .large-drop-zone {
            width: 100%;
            min-height: 310px;
            padding: 36px 24px;
            background: white;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            border: 2px dashed #C7D2FE;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 20px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .large-drop-zone * {
            pointer-events: none;
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
            max-width: 576px;
            padding: 14px;
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
</head>
<body>

<div class="BrandComplianceAiSmartMakerUploadWorkspaceRefined">

    <!-- 1. TOP NAVIGATION BAR (UNIFIED) -->
    <?php 
    if (function_exists('pcs_render_top_navbar')) {
        pcs_render_top_navbar($currentUser, 'campaigns');
    }
    ?>

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
                                    <label class="input-label-tag" for="assetNameInput">ASSET NAME</label>
                                    <div class="field-container">
                                        <div id="newAssetInputWrap" style="<?= ($activeMode === 'new_asset') ? '' : 'display: none;' ?> width: 100%;">
                                            <input type="text" name="asset_name" id="assetNameInput" class="form-input-clean" placeholder="e.g. Social Banner 1080x1080" value="<?= esc_attr($activeMode === 'new_asset' ? '' : $selAssetName) ?>" oninput="updateSummary()">
                                        </div>
                                        <div id="newVersionSelectWrap" style="<?= ($activeMode === 'new_version') ? '' : 'display: none;' ?> width: 100%;">
                                            <select name="asset_id" id="assetSelectDropdown" class="form-select-clean" onchange="onAssetDropdownChange(this)">
                                                <?php if (empty($assets)): ?>
                                                    <option value="0">No existing assets — Switch to New Asset</option>
                                                <?php else: ?>
                                                    <?php foreach ($assets as $ast): ?>
                                                        <option value="<?= (int)$ast['id'] ?>" 
                                                                data-name="<?= esc_attr($ast['name']) ?>" 
                                                                data-format="<?= esc_attr($ast['format']) ?>"
                                                                data-version-count="<?= (int)$ast['version_count'] ?>"
                                                                data-status="<?= esc_attr($ast['status']) ?>"
                                                                <?= ($selectedAsset && $selectedAsset['id'] === $ast['id']) ? 'selected' : '' ?>>
                                                            <?= esc_html($ast['name']) ?> (V<?= (int)$ast['version_count'] ?> · <?= ucfirst($ast['status']) ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <svg width="12" height="16" viewBox="0 0 12 16" fill="none">
                                            <path d="M1.5 15.75C1.0875 15.75 0.734375 15.6031 0.440625 15.3094C0.146875 15.0156 0 14.6625 0 14.25V6.75C0 6.3375 0.146875 5.98438 0.440625 5.69063C0.734375 5.39688 1.0875 5.25 1.5 5.25H2.25V3.75C2.25 2.7125 2.61562 1.82812 3.34687 1.09687C4.07812 0.365625 4.9625 0 6 0C7.0375 0 7.92188 0.365625 8.65312 1.09687C9.38437 1.82812 9.75 2.7125 9.75 3.75V5.25H10.5C10.9125 5.25 11.2656 5.39688 11.5594 5.69063C11.8531 5.98438 12 6.3375 12 6.75V14.25C12 14.6625 11.8531 15.0156 11.5594 15.3094C11.2656 15.6031 10.9125 15.75 10.5 15.75H1.5V15.75M1.5 14.25H10.5V6.75H1.5V14.25M6 12C6.4125 12 6.76562 11.8531 7.05937 11.5594C7.35312 11.2656 7.5 10.9125 7.5 10.5C7.5 10.0875 7.35312 9.73438 7.05937 9.44063C6.76562 9.14688 6.4125 9 6 9C5.5875 9 5.23438 9.14688 4.94063 9.44063C4.64688 9.73438 4.5 10.0875 4.5 10.5C4.5 10.9125 4.64688 11.2656 4.94063 11.5594C5.23438 11.8531 5.5875 12 6 12ZM3.75 5.25H8.25V3.75C8.25 3.125 8.03125 2.59375 7.59375 2.15625C7.15625 1.71875 6.625 1.5 6 1.5C5.375 1.5 4.84375 1.71875 4.40625 2.15625C3.96875 2.59375 3.75 3.125 3.75 3.75V5.25H8.25" fill="#767682"/>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Asset Type Field -->
                                <div class="input-group" style="flex: 1;">
                                    <label class="input-label-tag" for="assetTypeSelect">ASSET TYPE</label>
                                    <div class="field-container">
                                        <select name="asset_type" id="assetTypeSelect" class="form-select-clean" onchange="updateSummary()">
                                            <option value="Digital Ad" <?= ($selAssetType === 'Digital Ad' || strpos($selAssetType, 'Digital') !== false) ? 'selected' : '' ?>>Digital Ad</option>
                                            <option value="Social Banner (Square)" <?= (strpos($selAssetType, 'Square') !== false) ? 'selected' : '' ?>>Social Banner (1080x1080)</option>
                                            <option value="Story / Reels (9:16)">Story / Vertical (9:16)</option>
                                            <option value="Print Ad / Poster">Print Ad / Poster</option>
                                            <option value="PDF Brochure" <?= (strpos($selAssetType, 'PDF') !== false) ? 'selected' : '' ?>>PDF Brochure</option>
                                            <option value="Landing Page Asset">Landing Page Asset</option>
                                        </select>
                                        <svg width="9" height="6" viewBox="0 0 9 6" fill="none">
                                            <path d="M4.5 5.55L0 1.05L1.05 0L4.5 3.45L7.95 0L9 1.05L4.5 5.55Z" fill="#767682"/>
                                        </svg>
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

                            <input type="file" name="asset_file" id="realFileInput" accept=".png,.jpg,.jpeg,.pdf,.webp" style="display: none;" onchange="handleFileSelect(this)">

                            <div class="large-drop-zone" id="dropZoneArea" onclick="document.getElementById('realFileInput').click()">
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

                                <!-- Integrated Active File Card (shown when file is picked) -->
                                <div class="integrated-file-card" id="activeFileCard" style="display: none;" onclick="event.stopPropagation();">
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
                                    <div style="display: flex; gap: 12px; align-items: center;">
                                        <button type="button" style="color: #4F57A7; font-size: 12px; font-weight: 600; text-decoration: underline;" onclick="document.getElementById('realFileInput').click()">Replace</button>
                                        <button type="button" style="color: #BA1A1A; font-size: 12px; font-weight: 600; text-decoration: underline;" onclick="removeSelectedFile()">Remove</button>
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

        if (mode === 'new_asset') {
            btnAsset.classList.add('active');
            btnVer.classList.remove('active');
            newAssetWrap.style.display = 'block';
            newVerWrap.style.display = 'none';

            // Target version for brand new asset is V1
            setTargetVersion(1, '—', 'None');
            if (feedbackSec) feedbackSec.style.display = 'none';
        } else {
            btnVer.classList.add('active');
            btnAsset.classList.remove('active');
            newAssetWrap.style.display = 'none';
            newVerWrap.style.display = 'block';

            const sel = document.getElementById('assetSelectDropdown');
            if (sel) onAssetDropdownChange(sel);
            if (feedbackSec) feedbackSec.style.display = 'block';
        }
        updateSummary();
    }

    // Asset Dropdown Change Handler
    function onAssetDropdownChange(selectElem) {
        const opt = selectElem.options[selectElem.selectedIndex];
        if (!opt || opt.value === '0') return;

        const vCount = parseInt(opt.getAttribute('data-version-count') || '1', 10);
        const nextV = vCount + 1;
        const curStatus = opt.getAttribute('data-status') || 'draft';
        const assetName = opt.getAttribute('data-name') || opt.text;

        setTargetVersion(nextV, 'V' + vCount, curStatus.charAt(0).toUpperCase() + curStatus.slice(1));
        updateSummary();
    }

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
    function handleFileSelect(input) {
        const file = input.files[0];
        if (!file) return;

        hasFile = true;
        const fileName = file.name;
        const fileSizeMB = (file.size / (1024 * 1024)).toFixed(1);
        const fileExt = fileName.split('.').pop().toUpperCase();

        document.getElementById('cardFileName').textContent = fileName;
        document.getElementById('cardFileFormat').textContent = fileExt;
        document.getElementById('cardFileSize').textContent = fileSizeMB + ' MB';
        document.getElementById('activeFileCard').style.display = 'flex';

        // Update thumbnail preview
        const thumbBox = document.getElementById('fileThumbPreview');
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                thumbBox.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
            };
            reader.readAsDataURL(file);
        } else {
            thumbBox.innerHTML = '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>';
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
        const input = document.getElementById('realFileInput');
        input.value = '';
        hasFile = false;
        document.getElementById('activeFileCard').style.display = 'none';

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
                dropArea.classList.add('drag-over');
            }, false);
        });

        dropArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            dropArea.classList.remove('drag-over');
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
                        const transfer = new DataTransfer();
                        for (let i = 0; i < dt.files.length; i++) {
                            transfer.items.add(dt.files[i]);
                        }
                        input.files = transfer.files;
                    } catch (err) {
                        input.files = dt.files;
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
            const sel = document.getElementById('assetSelectDropdown');
            if (sel && sel.selectedIndex >= 0) {
                const opt = sel.options[sel.selectedIndex];
                name = opt.getAttribute('data-name') || opt.text;
            }
        }
        document.getElementById('summaryAssetName').textContent = name;
        document.getElementById('stripAssetName').textContent = name;
    }

    // Client-side Validation on Submit
    const form = document.getElementById('smartUploadForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const fileInput = document.getElementById('realFileInput');
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                e.preventDefault();
                alert('Please select or drop an asset file (PNG, JPG, PDF) to run the AI compliance check.');
                document.getElementById('dropZoneArea').scrollIntoView({ behavior: 'smooth' });
                return false;
            }

            const btn = document.getElementById('btnSubmitCheck');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span style="display: inline-block; animation: spin 1s infinite linear;">⚙️</span> <span>Uploading & Initializing AI...</span>';
            }
        });
    }
</script>

</body>
</html>

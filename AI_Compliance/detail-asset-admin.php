<?php
/**
 * Template Name: Detail Asset - Admin
 * Description: Realtime Admin Campaign & Asset Governance View connected to WordPress Database & CPTs (Production Release, Audit Trail, MAS Compliance)
 * 100% Dynamic Database Integration: Campaign, Assets, Versions, Checker Reviews, Guidelines, Audit Logs
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

// 3. Current User & RBAC Authentication (Strictly ADMIN only)
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

// 5. Handle Admin Actions (Production Release)
$releaseAlert = '';
$releaseSuccess = false;
$reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($reqMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_release') {
    $version_id = (int) ($_POST['version_id'] ?? 0);
    $release_notes = sanitize_text_field($_POST['release_notes'] ?? '');
    
    if (function_exists('handle_admin_release')) {
        $res = handle_admin_release($version_id, $release_notes);
        $releaseAlert = $res['message'];
        $releaseSuccess = $res['success'];
    }
} elseif ($reqMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_reject') {
    $version_id = (int) ($_POST['version_id'] ?? 0);
    $reject_notes = sanitize_text_field($_POST['reject_notes'] ?? ($_POST['release_notes'] ?? ''));
    
    if (function_exists('handle_admin_reject')) {
        $res = handle_admin_reject($version_id, $reject_notes);
        $releaseAlert = $res['message'];
        $releaseSuccess = $res['success'];
    }
} elseif ($reqMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_guideline') {
    $cId = absint($_POST['campaign_id'] ?? ($_GET['campaign_id'] ?? 0));
    if ($cId && function_exists('handle_upload_campaign_guideline')) {
        $uploaded = handle_upload_campaign_guideline($cId);
        if ($uploaded) {
            $releaseAlert = 'Guideline document(s) uploaded successfully!';
            $releaseSuccess = true;
        } else {
            $releaseAlert = 'Failed to upload guidelines. Check file formats.';
            $releaseSuccess = false;
        }
    }
} elseif ($reqMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_guideline') {
    $cId = absint($_POST['campaign_id'] ?? ($_GET['campaign_id'] ?? 0));
    $gTarget = sanitize_text_field($_POST['guideline_url'] ?? '');
    if ($cId && $gTarget && function_exists('handle_delete_campaign_guideline')) {
        $ok = handle_delete_campaign_guideline($cId, $gTarget);
        if ($ok) {
            $releaseAlert = 'Guideline document removed successfully!';
            $releaseSuccess = true;
        } else {
            $releaseAlert = 'Failed to remove guideline document.';
            $releaseSuccess = false;
        }
    }
} elseif ($reqMethod === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['update_campaign', 'update_campaign_team'], true)) {
    $campIdPost = absint($_POST['campaign_id'] ?? ($_GET['campaign_id'] ?? 0));
    $campTitle = sanitize_text_field($_POST['campaign_title'] ?? '');
    $campDeadline = sanitize_text_field($_POST['campaign_deadline'] ?? '');
    $campOwnerId = absint($_POST['owner_id'] ?? 0);
    $maker_ids = isset($_POST['maker_ids']) ? array_map('absint', (array) $_POST['maker_ids']) : [];
    $checker_ids = isset($_POST['checker_ids']) ? array_map('absint', (array) $_POST['checker_ids']) : [];
    if ($campIdPost && function_exists('handle_update_campaign')) {
        handle_update_campaign($campIdPost, [
            'title'       => $campTitle,
            'deadline'    => $campDeadline,
            'owner_id'    => $campOwnerId,
            'maker_ids'   => $maker_ids,
            'checker_ids' => $checker_ids
        ]);
        $releaseAlert = 'Campaign details and team assignments updated successfully!';
        $releaseSuccess = true;
    }
}

// 6. Query Campaign & Assets Data from WordPress Database
$campId = (int) ($_GET['campaign_id'] ?? 0);
$selectedAssetId = (int) ($_GET['asset_id'] ?? 0);
$detailData = function_exists('get_campaign_detail_asset_data') ? get_campaign_detail_asset_data($campId, $selectedAssetId) : null;

$campaignId          = (int) ($detailData['campaign_id'] ?? 0);
$campaignName        = $detailData['campaign_name'] ?? '—';
$ownerName           = $detailData['owner_name'] ?? '—';
$deadline            = $detailData['deadline'] ?? 'No deadline';
$deadlineIso         = $detailData['deadline_iso'] ?? '';
$deadlineRaw         = $detailData['deadline_raw'] ?? '';
$campaignOwnerId     = (int) ($detailData['owner_id'] ?? 0);
$campaignMakerIds    = (array) ($detailData['maker_ids'] ?? []);
$campaignCheckerIds  = (array) ($detailData['checker_ids'] ?? []);

$allAdminsList       = function_exists('get_all_users_for_picker') ? get_all_users_for_picker('ADMIN') : [];
$allMakersList       = function_exists('get_all_users_for_picker') ? get_all_users_for_picker('MAKER') : [];
$allCheckersList     = function_exists('get_all_users_for_picker') ? get_all_users_for_picker('CHECKER') : [];
$priority            = $detailData['priority'] ?? 'Normal';
$totalAssets         = (int) ($detailData['total_assets'] ?? 0);
$reviewedCount       = (int) ($detailData['reviewed_count'] ?? 0);
$awaitingCount       = (int) ($detailData['awaiting_count'] ?? 0);
$inReviewCount       = (int) ($detailData['in_review_count'] ?? 0);
$completeCount       = (int) ($detailData['complete_count'] ?? 0);
$awaitingReleaseCount= (int) ($detailData['awaiting_release_count'] ?? 0);
$releasedCount       = (int) ($detailData['released_count'] ?? 0);
$releasePercent      = (int) ($detailData['release_percent'] ?? 0);

$assets              = $detailData['assets'] ?? [];
$selectedAsset       = $detailData['selected_asset'] ?? null;
$versionHistory      = $detailData['version_history'] ?? [];
$guidelines          = $detailData['guidelines'] ?? [];
$activities          = $detailData['activities'] ?? [];

// Navigation URLs
$adminDashUrl   = function_exists('pcs_url') ? pcs_url('admin-dashboard.php') : 'admin-dashboard.php';
$auditLogUrl    = function_exists('pcs_url') ? pcs_url('audit-log.php') : 'audit-log.php';
$compareUrl     = function_exists('pcs_url') ? pcs_url('compare-version.php') : 'compare-version.php';
$homeUrl        = function_exists('pcs_url') ? pcs_url('home-page.php') : 'home-page.php';

// Check if database is completely empty
$isDbEmpty = ($campaignId === 0 && empty($assets));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= esc_html($campaignName) ?> - Campaign Governance | Admin Workspace</title>
    
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
            background: #F8FAFC;
            font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #0F172A;
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
        code, .font-mono {
            font-family: "JetBrains Mono", "Liberation Mono", monospace;
        }

        /* ================= 2. PAGE LAYOUT WRAPPER ================= */
        .detail-asset-admin {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            min-height: 100vh;
            background: #F8FAFC;
        }

        .main-container {
            width: 100%;
            max-width: 1280px;
            padding: 32px 32px 64px 32px;
            display: flex;
            flex-direction: column;
            gap: 28px;
        }

        /* Access Denied Card */
        .access-denied-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 48px 32px;
            text-align: center;
            max-width: 560px;
            margin: 60px auto;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .access-denied-icon {
            width: 56px;
            height: 56px;
            background: #FEE2E2;
            color: #DC2626;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        /* Toast Alert */
        .toast-banner {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 500;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .toast-banner.success {
            background: #ECFDF5;
            border: 1px solid #A7F3D0;
            color: #065F46;
        }
        .toast-banner.danger {
            background: #FEF2F2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }

        /* ================= 3. SECTION 2: CAMPAIGN HERO / GOVERNANCE HEADER ================= */
        .campaign-hero {
            display: flex;
            flex-direction: column;
            gap: 16px;
            padding-bottom: 28px;
            border-bottom: 1px solid #E2E8F0;
        }
        .campaign-hero-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }
        .campaign-category-tag {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.55px;
            text-transform: uppercase;
            color: #64748B;
            margin-bottom: 6px;
        }
        .campaign-title-heading {
            font-size: 30px;
            font-weight: 700;
            line-height: 36px;
            color: #1F2A5A;
        }
        .campaign-meta-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            font-size: 12px;
        }
        .campaign-owner {
            font-weight: 500;
            color: #1E293B;
        }
        .meta-dot {
            color: #CBD5E1;
            font-weight: 400;
        }
        .campaign-deadline {
            color: #475569;
        }
        .priority-badge {
            padding: 2px 8px;
            background: #FFFBEB;
            border: 1px solid rgba(253, 230, 138, 0.80);
            border-radius: 4px;
            color: #B45309;
            font-size: 11px;
            font-weight: 600;
            line-height: 16px;
        }
        .hero-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 40px;
            padding: 0 16px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            transition: all 0.15s ease;
        }
        .btn-secondary:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 40px;
            padding: 0 20px;
            background: #373E8C;
            border: 1px solid #373E8C;
            border-radius: 6px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            font-size: 12px;
            font-weight: 600;
            color: #FFFFFF;
            transition: all 0.15s ease;
        }
        .btn-primary:hover {
            background: #2D3373;
            border-color: #2D3373;
        }

        /* ================= 4. SECTION 3: RELEASE CONTROL BAND ================= */
        .release-control-band {
            background: #EEF0FB;
            border: 1px solid #D9DEF5;
            border-radius: 8px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .control-band-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .progress-group {
            display: flex;
            align-items: center;
            gap: 24px;
        }
        .progress-label {
            font-size: 14px;
            font-weight: 700;
            color: #1F2A5A;
        }
        .progress-track-lg {
            width: 288px;
            height: 8px;
            background: #FFFFFF;
            border: 1px solid #D9DEF5;
            border-radius: 9999px;
            overflow: hidden;
            position: relative;
        }
        .progress-fill-lg {
            height: 6px;
            background: #373E8C;
            border-radius: 9999px;
            transition: width 0.3s ease;
            position: absolute;
            top: 1px;
            left: 1px;
        }
        .progress-ratio {
            font-size: 14px;
            font-weight: 600;
            color: #0F172A;
        }
        .action-required-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            background: #FFFBEB;
            border: 1px solid rgba(253, 230, 138, 0.80);
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #B45309;
        }
        .action-required-badge.all-caught-up {
            background: #ECFDF5;
            border-color: #A7F3D0;
            color: #065F46;
        }
        .control-band-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            padding-top: 12px;
            border-top: 1px solid rgba(217, 222, 245, 0.70);
        }
        .breakdown-group {
            display: flex;
            align-items: center;
            gap: 16px;
            font-size: 12px;
        }
        .breakdown-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .breakdown-item strong {
            font-weight: 600;
            color: #1E293B;
        }
        .breakdown-item span {
            color: #475569;
            font-weight: 600;
        }
        .governance-notice-text {
            font-size: 11px;
            font-weight: 500;
            color: #64748B;
        }

        /* Status Dot Indicator */
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 4px;
            display: inline-block;
            flex-shrink: 0;
        }
        .dot-indigo { background: #373E8C; }
        .dot-amber  { background: #F5B544; }
        .dot-teal   { background: #2BB3A3; }
        .dot-red    { background: #EF4444; }

        /* ================= 5. SECTION 4: MAIN ASSET WORKSPACE ================= */
        .asset-workspace {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .workspace-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        .workspace-title-box {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .workspace-title {
            font-size: 18px;
            font-weight: 700;
            line-height: 28px;
            color: #0F172A;
        }
        .count-pill {
            padding: 2px 8px;
            background: rgba(226, 232, 240, 0.70);
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
        }
        .workspace-controls {
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        .filter-tabs {
            display: flex;
            align-items: center;
            gap: 4px;
            background: #F1F5F9;
            padding: 3px;
            border-radius: 6px;
        }
        .tab-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #64748B;
            transition: all 0.15s ease;
        }
        .tab-btn.active {
            background: #FFFFFF;
            color: #1F2A5A;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
        }
        .search-box {
            position: relative;
            display: flex;
            align-items: center;
        }
        .search-input {
            width: 220px;
            height: 32px;
            padding: 0 12px 0 32px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            font-family: inherit;
            font-size: 12px;
            color: #0F172A;
            outline: none;
            transition: all 0.15s ease;
        }
        .search-input:focus {
            border-color: #373E8C;
            box-shadow: 0 0 0 2px rgba(55, 62, 140, 0.1);
        }
        .search-icon-pos {
            position: absolute;
            left: 10px;
            pointer-events: none;
            display: flex;
            align-items: center;
            color: #94A3B8;
        }

        /* Workspace Table Container */
        .workspace-table-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .admin-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        .admin-table th {
            background: #F8FAFC;
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.55px;
            text-transform: uppercase;
            color: #64748B;
            border-bottom: 1px solid #E2E8F0;
            white-space: nowrap;
        }
        .admin-table td {
            padding: 14px 16px;
            font-size: 12px;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
        }
        .admin-table tbody tr {
            transition: background 0.12s ease;
            cursor: pointer;
        }
        .admin-table tbody tr:hover {
            background: #F8FAFC;
        }
        .admin-table tbody tr.active-row {
            background: #F0F4FF;
            box-shadow: inset 3px 0 0 #373E8C;
        }
        .admin-table tbody tr:last-child td {
            border-bottom: none;
        }

        .asset-title-box {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .asset-name {
            font-size: 14px;
            font-weight: 700;
            color: #1F2A5A;
        }
        .asset-format {
            font-size: 11px;
            color: #64748B;
        }
        .version-badge {
            font-family: "JetBrains Mono", monospace;
            font-size: 12px;
            font-weight: 600;
            color: #373E8C;
            background: #EEF0FB;
            padding: 2px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .user-cell {
            font-weight: 500;
            color: #1E293B;
            white-space: nowrap;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11.5px;
            font-weight: 600;
            white-space: nowrap;
        }
        .status-pill.draft {
            background: #F1F5F9;
            color: #475569;
            border: 1px solid #CBD5E1;
        }
        .status-pill.scoring {
            background: #FFFBEB;
            color: #D97706;
            border: 1px solid #FCD34D;
        }
        .status-pill.in-review, .status-pill.in_review {
            background: #EEF2FF;
            color: #373E8C;
            border: 1px solid #C7D2FE;
        }
        .status-pill.rejected {
            background: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }
        .status-pill.approved {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        .status-pill.released {
            background: #F5F3FF;
            color: #7C3AED;
            border: 1px solid #DDD6FE;
        }

        .release-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11.5px;
            font-weight: 600;
            white-space: nowrap;
        }
        .release-pill.awaiting {
            background: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        .release-pill.released {
            background: #F5F3FF;
            color: #7C3AED;
            border: 1px solid #DDD6FE;
        }
        .release-pill.pending {
            color: #94A3B8;
        }

        .btn-table-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            height: 30px;
            padding: 0 12px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            font-size: 11.5px;
            font-weight: 600;
            color: #334155;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .btn-table-action:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            color: #0F172A;
        }
        .btn-table-action.primary {
            background: #373E8C;
            border-color: #373E8C;
            color: #FFFFFF;
        }
        .btn-table-action.primary:hover {
            background: #2D3373;
            border-color: #2D3373;
        }

        /* ================= 6. SECTION 5: RELEASE SPOTLIGHT (3-COL PANEL) ================= */
        .release-spotlight-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            display: grid;
            grid-template-columns: 35% 33% 32%;
        }
        .spotlight-col {
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
        }
        .spotlight-col-1 {
            background: #FFFFFF;
        }
        .spotlight-col-2 {
            background: #FAF8FE;
            border-left: 1px solid #E2E8F0;
        }
        .spotlight-col-3 {
            background: rgba(248, 250, 252, 0.70);
            border-left: 1px solid #E2E8F0;
        }
        .spotlight-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .spotlight-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.55px;
            text-transform: uppercase;
            color: #64748B;
        }
        .spotlight-title {
            font-size: 16px;
            font-weight: 700;
            color: #1F2A5A;
            margin-top: 8px;
        }
        .spotlight-meta-sub {
            font-size: 12px;
            color: #64748B;
            margin-top: 2px;
        }
        .spotlight-author-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11.5px;
            color: #64748B;
            margin-top: 12px;
        }
        .pill-current {
            background: #EEF0FB;
            color: #373E8C;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 4px;
            letter-spacing: 0.50px;
        }
        .pill-action-req {
            background: #FFFBEB;
            border: 1px solid rgba(253, 230, 138, 0.80);
            color: #B45309;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 8px;
            border-radius: 4px;
            letter-spacing: 0.50px;
        }
        .pill-action-req.released {
            background: #ECFDF5;
            border-color: #A7F3D0;
            color: #065F46;
        }
        .decision-status-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #1F2A5A;
        }
        .decision-checker-info {
            font-size: 12px;
            color: #475569;
            margin-top: 4px;
        }
        .decision-footer-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #E2E8F0;
            font-size: 11.5px;
        }
        .override-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #B45309;
            font-weight: 600;
        }
        .override-badge.standard {
            color: #10B981;
        }
        .link-text-arrow {
            color: #373E8C;
            font-weight: 600;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: color 0.15s ease;
        }
        .link-text-arrow:hover {
            color: #1F2A5A;
            text-decoration: underline;
        }
        .spotlight-actions-row {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 14px;
        }

        /* ================= 7. SECTION 6: VERSION & RELEASE TRACE ================= */
        .version-trace-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .version-trace-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        .trace-title-group h3 {
            font-size: 14px;
            font-weight: 700;
            color: #0F172A;
        }
        .trace-title-group p {
            font-size: 12px;
            color: #64748B;
        }
        .trace-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .timeline-track-container {
            display: flex;
            align-items: center;
            gap: 16px;
            overflow-x: auto;
            padding: 8px 0;
        }
        .timeline-node {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }
        .node-step-circle {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
            background: #EEF0FB;
            color: #373E8C;
            border: 1px solid #CBD5E1;
        }
        .node-step-circle.active {
            background: #373E8C;
            color: #FFFFFF;
            border-color: #373E8C;
        }
        .node-step-circle.approved {
            background: #DCFCE7;
            color: #166534;
            border-color: #86EFAC;
        }
        .node-step-circle.released {
            background: #10B981;
            color: #FFFFFF;
            border-color: #10B981;
        }
        .node-content-box {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .node-title {
            font-size: 13px;
            font-weight: 700;
            color: #1F2A5A;
        }
        .node-date {
            font-size: 11px;
            color: #64748B;
        }
        .timeline-connector {
            width: 48px;
            height: 1px;
            background: #E2E8F0;
            flex-shrink: 0;
        }
        .timeline-footer-notice {
            font-size: 12px;
            font-weight: 500;
            color: #64748B;
            padding-top: 10px;
            border-top: 1px solid #F1F5F9;
        }

        /* ================= 8. SECTION 7: APPLICABLE GUIDELINES & CAMPAIGN ACTIVITY ================= */
        .split-grid-row {
            display: grid;
            grid-template-columns: 5fr 7fr;
            gap: 24px;
        }
        .split-card {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .split-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .split-card-title {
            font-size: 16px;
            font-weight: 700;
            color: #0F172A;
        }
        .split-table-box {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }

        .type-tag {
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }
        .type-tag.brand {
            background: #EEF0FB;
            color: #373E8C;
        }
        .type-tag.regulatory {
            background: #FEF3C7;
            color: #92400E;
        }
        .type-tag.internal {
            background: #F1F5F9;
            color: #475569;
        }
        .type-tag.review {
            background: #DCFCE7;
            color: #166534;
        }
        .type-tag.release {
            background: #E0E7FF;
            color: #4338CA;
        }
        .type-tag.upload {
            background: #F3E8FF;
            color: #6B21A8;
        }

        /* Empty State Box */
        .empty-state-box {
            padding: 36px 20px;
            text-align: center;
            color: #64748B;
            font-size: 13px;
        }
        .empty-state-icon {
            font-size: 24px;
            margin-bottom: 8px;
        }

        /* ================= USER PICKER MULTI-SELECT STYLES ================= */
        .user-picker-wrap {
            position: relative;
            width: 100%;
        }
        .user-picker-box {
            min-height: 44px;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 6px 10px;
            background: #FFFFFF;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 6px;
            cursor: text;
            transition: border-color 0.15s ease;
        }
        .user-picker-box:focus-within {
            border-color: #6366F1;
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.15);
        }
        .user-picker-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }
        .user-picker-tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #EEF2FF;
            color: #373E8C;
            border: 1px solid #C7D2FE;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        .user-picker-tag-remove {
            cursor: pointer;
            font-weight: 800;
            font-size: 14px;
            color: #6366F1;
            line-height: 1;
        }
        .user-picker-tag-remove:hover {
            color: #EF4444;
        }
        .user-picker-input {
            border: none;
            outline: none;
            font-size: 13px;
            font-family: inherit;
            flex: 1 1 120px;
            min-width: 100px;
            padding: 4px 0;
            background: transparent;
        }
        .user-picker-dropdown {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            padding: 6px;
        }
        .user-dropdown-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .user-dropdown-item:hover {
            background: #F8FAFC;
        }
        .user-dropdown-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            color: #FFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            flex-shrink: 0;
        }
        .user-dropdown-name {
            font-size: 13px;
            font-weight: 700;
            color: #0F172A;
        }
        .user-dropdown-email {
            font-size: 11.5px;
            color: #64748B;
        }

        /* ================= 9. MODALS ================= */
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
            padding: 20px;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-card {
            background: #FFFFFF;
            border-radius: 12px;
            width: 100%;
            max-width: 620px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: modalFadeIn 0.2s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.97) translateY(10px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
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
            color: #1F2A5A;
        }
        .btn-modal-close {
            font-size: 20px;
            color: #94A3B8;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            line-height: 1;
        }
        .btn-modal-close:hover {
            color: #0F172A;
            background: #F1F5F9;
        }
        .modal-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 18px;
            max-height: 75vh;
            overflow-y: auto;
        }
        .modal-asset-preview {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #E2E8F0;
            background: #F8FAFC;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .governance-warning-box {
            background: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: 8px;
            padding: 12px 16px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 12px;
            color: #92400E;
            line-height: 1.5;
        }
        .form-label {
            font-size: 12.5px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
            display: block;
        }
        .form-textarea {
            width: 100%;
            height: 80px;
            padding: 10px 14px;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            font-family: inherit;
            font-size: 12.5px;
            color: #0F172A;
            outline: none;
            resize: vertical;
        }
        .form-textarea:focus {
            border-color: #373E8C;
            box-shadow: 0 0 0 2px rgba(55, 62, 140, 0.1);
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .release-spotlight-card {
                grid-template-columns: 1fr;
            }
            .spotlight-col-2, .spotlight-col-3 {
                border-left: none;
                border-top: 1px solid #E2E8F0;
            }
            .split-grid-row {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 768px) {
            .main-container {
                padding: 16px 16px 40px 16px;
            }
            .campaign-hero-top {
                flex-direction: column;
            }
            .hero-actions {
                width: 100%;
                justify-content: flex-start;
            }
            .control-band-top, .control-band-bottom {
                flex-direction: column;
                align-items: flex-start;
            }
            .progress-track-lg {
                width: 100%;
            }
            .workspace-controls {
                width: 100%;
                justify-content: space-between;
            }
            .search-input {
                width: 100%;
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
            'all_campaigns_url' => $adminDashUrl,
            'btn_text'          => 'Admin Workspace'
        ]);
    } else {
        @include __DIR__ . '/navbar.php';
    }
    ?>

<div class="detail-asset-admin">

    <?php if (!$isAllowed): ?>
        <!-- RBAC ACCESS DENIED VIEW -->
        <div class="access-denied-card">
            <div class="access-denied-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <h2 style="font-size: 20px; font-weight: 700; color: #1F2A5A; margin-bottom: 8px;">Admin Access Restricted</h2>
            <p style="font-size: 13.5px; color: #64748B; margin-bottom: 24px;">This campaign governance screen requires administrator clearance under MAS regulatory compliance. Your current role is <strong><?= esc_html($userRole) ?></strong>.</p>
            <a href="<?= esc_url($adminDashUrl) ?>" class="btn-primary">Return to Workspace</a>
        </div>
    <?php else: ?>

        <!-- MAIN VIEWPORT CONTAINER -->
        <main class="main-container">

            <!-- Toast Feedback Notification -->
            <?php if (!empty($releaseAlert)): ?>
                <div class="toast-banner <?= $releaseSuccess ? 'success' : 'danger' ?>">
                    <span><?= esc_html($releaseAlert) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($isDbEmpty): ?>
                <!-- EMPTY STATE: DATABASE HAS NO CAMPAIGNS -->
                <div class="access-denied-card" style="border-style: dashed;">
                    <div class="empty-state-icon" style="margin-bottom: 16px;">
                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <h2 style="font-size: 19px; font-weight: 700; color: #1F2A5A; margin-bottom: 6px;">No Campaign Found in Database</h2>
                    <p style="font-size: 13.5px; color: #64748B; margin-bottom: 24px;">There are no active campaigns registered in WordPress yet. Create a new campaign from the Admin Dashboard to start the compliance pipeline.</p>
                    <a href="<?= esc_url($adminDashUrl) ?>" class="btn-primary">Open Admin Dashboard</a>
                </div>
            <?php else: ?>

                <!-- Back to Dashboard Navigation -->
                <div style="margin-bottom: 14px;">
                    <a href="<?= esc_url($adminDashUrl) ?>" class="btn-back-breadcrumb" style="display: inline-flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 700; color: #373E8C; text-decoration: none; padding: 7px 14px; background: #FFFFFF; border: 1px solid #E0E0FF; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.15s ease;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                        <span>Back to Dashboard</span>
                    </a>
                </div>

                <!-- ================= 2. CAMPAIGN HERO / GOVERNANCE HEADER ================= -->
                <section class="campaign-hero">
                    <div class="campaign-hero-top">
                        <div>
                            <div class="campaign-category-tag">CAMPAIGN · </div>
                            <h1 class="campaign-title-heading"><?= esc_html($campaignName) ?></h1>
                            <div class="campaign-meta-row">
                                <span class="campaign-owner"><?= esc_html($ownerName) ?></span>
                                <span class="meta-dot">·</span>
                                <span class="campaign-deadline"><?= esc_html($deadline) ?></span>
                                <span class="meta-dot">·</span>
                                <span class="priority-badge"><?= esc_html($priority) ?> Priority</span>
                            </div>
                        </div>
                        <div class="hero-actions">
                            <a href="<?= esc_url((function_exists('pcs_url') ? pcs_url('approval-queue-admin.php') : 'approval-queue-admin.php') . '?campaign_id=' . (int)$campaignId) ?>" class="btn-secondary" style="background: #F4F3FD; border-color: #D5D9F3; color: #4E56A6;">
                                <span>Approval Queue &rarr;</span>
                            </a>
                            <button type="button" class="btn-secondary" onclick="openGuidelinesModal()">
                                <span>Manage Guidelines</span>
                            </button>
                            <button type="button" class="btn-primary" onclick="openEditCampaignModal()">
                                <span>Edit Campaign</span>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- ================= 3. RELEASE CONTROL BAND (Full-width editorial pale lavender band) ================= -->
                <section class="release-control-band">
                    <div class="control-band-top">
                        <div class="progress-group">
                            <span class="progress-label">Release Progress</span>
                            <div class="progress-track-lg">
                                <div class="progress-fill-lg" style="width: <?= max(2, min(100, $releasePercent)) ?>%;"></div>
                            </div>
                            <span class="progress-ratio"><?= $releasedCount ?> of <?= $totalAssets ?> released</span>
                        </div>
                        <?php if ($awaitingReleaseCount > 0): ?>
                            <div class="action-required-badge">
                                <span class="status-dot dot-amber"></span>
                                <span><?= $awaitingReleaseCount ?> Admin action required</span>
                            </div>
                        <?php else: ?>
                            <div class="action-required-badge all-caught-up">
                                <span class="status-dot dot-teal"></span>
                                <span>All releases caught up</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="control-band-bottom">
                        <div class="breakdown-group">
                            <div class="breakdown-item">
                                <span class="status-dot dot-indigo"></span>
                                <strong><?= $awaitingReleaseCount ?></strong>
                                <span>Approved</span>
                            </div>
                            <span class="meta-dot">·</span>
                            <div class="breakdown-item">
                                <span class="status-dot dot-amber"></span>
                                <strong><?= $inReviewCount ?></strong>
                                <span>In Review</span>
                            </div>
                            <span class="meta-dot">·</span>
                            <div class="breakdown-item">
                                <span class="status-dot dot-teal"></span>
                                <strong><?= $releasedCount ?></strong>
                                <span>Released</span>
                            </div>
                        </div>
                        <div class="governance-notice-text">
                            Governance gate: Admin sign-off required prior to public asset distribution
                        </div>
                    </div>
                </section>

                <!-- ================= 4. MAIN ASSET WORKSPACE ================= -->
                <section class="asset-workspace">
                    <div class="workspace-header">
                        <div class="workspace-title-box">
                            <h2 class="workspace-title">Assets</h2>
                            <span class="count-pill" id="assetsCountBadge"><?= $totalAssets ?></span>
                        </div>
                        <div class="workspace-controls">
                            <div class="filter-tabs">
                                <button type="button" class="tab-btn active" data-filter="all">All</button>
                                <button type="button" class="tab-btn" data-filter="awaiting">Approved (<?= $awaitingReleaseCount ?>)</button>
                                <button type="button" class="tab-btn" data-filter="in_review">In Review (<?= $inReviewCount ?>)</button>
                                <button type="button" class="tab-btn" data-filter="released">Released (<?= $releasedCount ?>)</button>
                            </div>
                            <div class="search-box">
                                <span class="search-icon-pos">
                                    <svg width="12" height="12" viewBox="0 0 12 16" fill="none"><path d="M11.0667 12L6.86667 7.8C6.53333 8.06667 6.15 8.27778 5.71667 8.43333C5.28333 8.58889 4.82222 8.66667 4.33333 8.66667C3.12222 8.66667 2.09722 8.24722 1.25833 7.40833C0.419444 6.56944 0 5.54444 0 4.33333C0 3.12222 0.419444 2.09722 1.25833 1.25833C2.09722 0.419444 3.12222 0 4.33333 0C5.54444 0 6.56944 0.419444 7.40833 1.25833C8.24722 2.09722 8.66667 3.12222 8.66667 4.33333C8.66667 4.82222 8.58889 5.28333 8.43333 5.71667C8.27778 6.15 8.06667 6.53333 7.8 6.86667L12 11.0667L11.0667 12V12M4.33333 7.33333C5.16667 7.33333 5.875 7.04167 6.45833 6.45833C7.04167 5.875 7.33333 5.16667 7.33333 4.33333C7.33333 3.5 7.04167 2.79167 6.45833 2.20833C5.875 1.625 5.16667 1.33333 4.33333 1.33333C3.5 1.33333 2.79167 1.625 2.20833 2.20833C1.625 2.79167 1.33333 3.5 1.33333 4.33333C1.33333 5.16667 1.625 5.875 2.20833 6.45833C2.79167 7.04167 3.5 7.33333 4.33333 7.33333V7.33333" fill="#94A3B8"/></svg>
                                </span>
                                <input type="text" class="search-input" id="assetSearchInput" placeholder="Filter assets..." />
                            </div>
                        </div>
                    </div>

                    <div class="workspace-table-card">
                        <?php if (empty($assets)): ?>
                            <div class="empty-state-box">
                                <div class="empty-state-icon" style="color: #64748B;">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </div>
                                <p>No assets currently registered in this campaign.</p>
                            </div>
                        <?php else: ?>
                            <table class="admin-table" id="adminAssetsTable">
                                <thead>
                                    <tr>
                                        <th>ASSET</th>
                                        <th style="text-align: center;">VERSION</th>
                                        <th>MAKER</th>
                                        <th>CHECKER</th>
                                        <th style="text-align: center;">REVIEW STATUS</th>
                                        <th style="text-align: center;">RELEASE STATUS</th>
                                        <th style="text-align: center;">NEXT</th>
                                        <th style="text-align: center;">ACTION</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assets as $ast): 
                                        $isActive = ($selectedAsset && $selectedAsset['id'] === $ast['id']);
                                        $filterGroup = 'other';
                                        if ($ast['status'] === 'approved') $filterGroup = 'awaiting';
                                        elseif ($ast['status'] === 'in_review') $filterGroup = 'in_review';
                                        elseif ($ast['status'] === 'released') $filterGroup = 'released';

                                        // URL for Admin Final Review
                                        $adminFinalReviewUrl = (function_exists('pcs_url') ? pcs_url('final-review-admin.php') : 'final-review-admin.php') . '?' . http_build_query([
                                            'version_id'  => (int)$ast['current_version_id'],
                                            'asset_id'    => (int)$ast['id'],
                                            'campaign_id' => (int)$campaignId,
                                        ]);
                                    ?>
                                        <tr class="asset-row <?= $isActive ? 'active-row' : '' ?>"
                                            data-asset-id="<?= (int)$ast['id'] ?>"
                                            data-filter-group="<?= esc_attr($filterGroup) ?>"
                                            data-asset-name="<?= esc_attr($ast['name']) ?>"
                                            data-version-label="<?= esc_attr($ast['version_label']) ?>"
                                            data-format="<?= esc_attr($ast['format']) ?>"
                                            data-maker="<?= esc_attr($ast['maker_name']) ?>"
                                            data-submitted="<?= esc_attr($ast['submitted_full']) ?>"
                                            data-checker="<?= esc_attr($ast['checker_name']) ?>"
                                            data-reviewed="<?= esc_attr($ast['checker_reviewed_at']) ?>"
                                            data-review-status="<?= esc_attr($ast['review_status']) ?>"
                                            data-release-status="<?= esc_attr($ast['release_status']) ?>"
                                            data-next="<?= esc_attr($ast['next_label']) ?>"
                                            data-file-url="<?= esc_attr($ast['file_url']) ?>"
                                            data-version-id="<?= (int)$ast['current_version_id'] ?>"
                                            data-final-review-url="<?= esc_attr($adminFinalReviewUrl) ?>"
                                            data-review-note="<?= esc_attr($ast['review_note']) ?>"
                                            data-override="<?= $ast['override_recorded'] ? '1' : '0' ?>"
                                            onclick="selectAssetRow(this)">
                                            <td>
                                                <div class="asset-title-box">
                                                    <span class="asset-name"><?= esc_html($ast['name']) ?></span>
                                                    <span class="asset-format"><?= esc_html($ast['format']) ?></span>
                                                </div>
                                            </td>
                                            <td style="text-align: center;">
                                                <span class="version-badge"><?= esc_html($ast['version_label']) ?></span>
                                            </td>
                                            <td class="user-cell" style="white-space: nowrap;"><?= esc_html($ast['maker_name']) ?></td>
                                            <td class="user-cell" style="white-space: nowrap;"><?= esc_html($ast['checker_name']) ?></td>
                                            <td style="text-align: center;">
                                                <?php 
                                                $rSt = str_replace([' ', '-'], '_', strtolower(trim($ast['review_status'] ?? 'draft')));
                                                if ($rSt === 'awaiting_release' || $rSt === 'awaiting') $rSt = 'approved';
                                                if ($rSt === 'approved'): ?>
                                                    <span class="status-pill approved">
                                                        <span class="status-dot" style="background:#059669;"></span>
                                                        <span>Approved</span>
                                                    </span>
                                                <?php elseif ($rSt === 'in_review'): ?>
                                                    <span class="status-pill in-review">
                                                        <span class="status-dot" style="background:#373E8C;"></span>
                                                        <span>In Review</span>
                                                    </span>
                                                <?php elseif ($rSt === 'scoring'): ?>
                                                    <span class="status-pill scoring">
                                                        <span class="status-dot" style="background:#D97706;"></span>
                                                        <span>Scoring</span>
                                                    </span>
                                                <?php elseif ($rSt === 'rejected'): ?>
                                                    <span class="status-pill rejected">
                                                        <span class="status-dot" style="background:#DC2626;"></span>
                                                        <span>Rejected</span>
                                                    </span>
                                                <?php elseif ($rSt === 'released'): ?>
                                                    <span class="status-pill released">
                                                        <span class="status-dot" style="background:#7C3AED;"></span>
                                                        <span>Released</span>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-pill draft">
                                                        <span class="status-dot" style="background:#475569;"></span>
                                                        <span>Draft</span>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if ($ast['release_status'] === 'Awaiting Release' || $ast['release_status'] === 'Approved'): ?>
                                                    <span class="release-pill awaiting">Approved</span>
                                                <?php elseif ($ast['release_status'] === 'Released'): ?>
                                                    <span class="release-pill released">Released</span>
                                                <?php else: ?>
                                                    <span class="release-pill pending">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: center;">
                                                <span style="font-weight: 500; color: #64748B;"><?= esc_html($ast['next_label']) ?></span>
                                            </td>
                                            <td style="text-align: center; white-space: nowrap;" onclick="event.stopPropagation()">
                                                <div style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap;">
                                                    <?php if ($ast['status'] === 'approved'):
                                                        $targetVid = !empty($ast['current_version_id']) ? $ast['current_version_id'] : ($ast['version_id'] ?? 0);
                                                        $adminFinalRevUrl = pcs_url('final-review-admin.php') . '?' . http_build_query([
                                                            'version_id'  => $targetVid,
                                                            'asset_id'    => $ast['id'],
                                                            'campaign_id' => $campaignId
                                                        ]);
                                                    ?>
                                                        <a href="<?= esc_url($adminFinalRevUrl) ?>" class="btn-table-action primary" style="text-decoration: none; padding: 5px 12px; font-size: 11.5px; border-radius: 6px; white-space: nowrap;">
                                                            <span>Review &rarr;</span>
                                                        </a>
                                                    <?php elseif ($ast['status'] === 'released'): ?>
                                                        <?php 
                                                        $targetVid = !empty($ast['current_version_id']) ? $ast['current_version_id'] : ($ast['version_id'] ?? 0);
                                                        $relUrl = pcs_url('release-detail.php') . '?' . http_build_query([
                                                            'version_id'  => $targetVid,
                                                            'asset_id'    => $ast['id'],
                                                            'campaign_id' => $campaignId,
                                                            'from'        => 'admin_assets'
                                                        ]);
                                                        ?>
                                                        <a href="<?= esc_url($relUrl) ?>" class="btn-table-action" style="text-decoration: none; padding: 5px 12px; font-size: 11.5px; border-radius: 6px; white-space: nowrap;">
                                                            View Release
                                                        </a>
                                                    <?php elseif (!empty($ast['current_version_id'])): ?>
                                                        <a href="<?= esc_url($adminFinalReviewUrl) ?>" class="btn-table-action" style="padding: 5px 12px; font-size: 11.5px; border-radius: 6px; white-space: nowrap;">
                                                            View Version
                                                        </a>
                                                    <?php else: ?>
                                                        <span style="color: #94A3B8;">—</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- ================= 5. SECTION 5: RELEASE SPOTLIGHT (Architectural 3-column panel) ================= -->
                <?php if ($selectedAsset): 
                    $compUrl = $compareUrl . '?' . http_build_query([
                        'campaign_id' => $campaignId,
                        'asset_id'    => $selectedAsset['id'],
                        'from'        => 'admin'
                    ]);
                    $reviewLogUrl = $auditLogUrl . '?' . http_build_query([
                        'campaign_id' => $campaignId,
                        'asset_id'    => $selectedAsset['id'],
                        'version_id'  => $selectedAsset['current_version_id'],
                        'from'        => 'detail_admin'
                    ]);
                ?>
                    <section class="release-spotlight-card" id="releaseSpotlightSection">
                        <!-- Col 1 (35%): Selected Asset Metadata -->
                        <div class="spotlight-col spotlight-col-1">
                            <div>
                                <div class="spotlight-header-row">
                                    <span class="spotlight-label">SELECTED VERSION</span>
                                    <span class="pill-current">CURRENT VERSION</span>
                                </div>
                                <h3 class="spotlight-title" id="spotlightAssetTitle">
                                    <?= esc_html($selectedAsset['name']) ?> · <?= esc_html($selectedAsset['version_label']) ?>
                                </h3>
                                <p class="spotlight-meta-sub" id="spotlightAssetFormat"><?= esc_html($selectedAsset['format']) ?></p>
                            </div>
                            <div class="spotlight-author-row" id="spotlightAuthorRow">
                                <span><?= esc_html($selectedAsset['maker_name']) ?> · Maker</span>
                                <span class="meta-dot">·</span>
                                <span><?= esc_html($selectedAsset['submitted_full']) ?></span>
                            </div>
                        </div>

                        <!-- Col 2 (33%): Checker Decision -->
                        <div class="spotlight-col spotlight-col-2">
                            <div>
                                <div class="spotlight-header-row">
                                    <span class="spotlight-label">CHECKER DECISION</span>
                                </div>
                                <div class="decision-status-row" id="spotlightDecisionRow">
                                    <span class="status-dot <?= $selectedAsset['review_status'] === 'Approved' ? 'dot-teal' : ($selectedAsset['review_status'] === 'Rejected' ? 'dot-red' : 'dot-amber') ?>"></span>
                                    <span id="spotlightDecisionText"><?= esc_html($selectedAsset['review_status']) ?></span>
                                </div>
                                <p class="decision-checker-info" id="spotlightCheckerInfo">
                                    <?= esc_html($selectedAsset['checker_name']) ?> · <?= esc_html($selectedAsset['checker_reviewed_at'] ?: 'Review record logged') ?>
                                </p>
                            </div>
                            <div class="decision-footer-row">
                                <div class="override-badge <?= $selectedAsset['override_recorded'] ? '' : 'standard' ?>" id="spotlightOverrideBadge">
                                    <?php if ($selectedAsset['override_recorded']): ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        <span>Override recorded</span>
                                    <?php else: ?>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                                        <span>Standard compliance</span>
                                    <?php endif; ?>
                                </div>
                                <a href="<?= esc_url($reviewLogUrl) ?>" class="link-text-arrow" id="spotlightReviewRecordLink">
                                    <span>View Review Record →</span>
                                </a>
                            </div>
                        </div>

                        <!-- Col 3 (32%): Release Status & Action -->
                        <div class="spotlight-col spotlight-col-3">
                            <div>
                                <div class="spotlight-header-row">
                                    <span class="spotlight-label">RELEASE STATUS</span>
                                    <?php if ($selectedAsset['status'] === 'approved'): ?>
                                        <span class="pill-action-req" id="spotlightActionBadge">ACTION REQUIRED</span>
                                    <?php elseif ($selectedAsset['status'] === 'released'): ?>
                                        <span class="pill-action-req released" id="spotlightActionBadge">COMPLETED</span>
                                    <?php else: ?>
                                        <span class="pill-action-req" id="spotlightActionBadge" style="background: #F1F5F9; color: #64748B; border-color: #CBD5E1;">PENDING</span>
                                    <?php endif; ?>
                                </div>
                                <h3 class="spotlight-title" id="spotlightReleaseTitle"><?= esc_html($selectedAsset['release_status']) ?></h3>
                                <p class="spotlight-meta-sub" id="spotlightNextLabel">Next: <?= esc_html($selectedAsset['next_label']) ?></p>
                            </div>
                            <div class="spotlight-actions-row" id="spotlightActionsContainer">
                                <?php if ($selectedAsset['status'] === 'approved'): ?>
                                    <button type="button" class="btn-primary" style="height: 36px; padding: 0 16px;"
                                            onclick="openReleaseModal(<?= htmlspecialchars(json_encode([
                                                'version_id'    => $selectedAsset['current_version_id'],
                                                'asset_id'      => $selectedAsset['id'],
                                                'asset_name'    => $selectedAsset['name'],
                                                'version_label' => $selectedAsset['version_label'],
                                                'campaign_name' => $campaignName,
                                                'maker_name'    => $selectedAsset['maker_name'],
                                                'checker_name'  => $selectedAsset['checker_name'],
                                                'review_note'   => $selectedAsset['review_note'] ?: 'Asset verified against corporate guidelines and MAS financial advertisement standards. Ready for production release.',
                                                'file_url'      => $selectedAsset['file_url']
                                            ]), ENT_QUOTES, 'UTF-8') ?>)">
                                        Open Final Review
                                    </button>
                                <?php elseif ($selectedAsset['status'] === 'released'): ?>
                                    <?php 
                                    $relUrl = pcs_url('release-detail.php') . '?' . http_build_query([
                                        'version_id'  => $selectedAsset['version_id'],
                                        'asset_id'    => $selectedAsset['id'],
                                        'campaign_id' => $campaignId,
                                        'from'        => 'admin_assets'
                                    ]);
                                    ?>
                                    <a href="<?= esc_url($relUrl) ?>" class="btn-secondary" style="height: 36px; padding: 0 16px; text-decoration: none; display: inline-flex; align-items: center;">
                                        View Release Details
                                    </a>
                                <?php endif; ?>

                                <?php if (!empty($selectedAsset['file_url'])): ?>
                                    <a href="<?= esc_url($selectedAsset['file_url']) ?>" target="_blank" class="link-text-arrow">
                                        <span>View Version →</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </section>
                <?php endif; ?>

                <!-- ================= 6. SECTION 6: VERSION & RELEASE TRACE ================= -->
                <section class="version-trace-card">
                    <div class="version-trace-header">
                        <div class="trace-title-group">
                            <h3>Version & Release Trace</h3>
                            <p id="traceAssetSubtitle"><?= esc_html($selectedAsset['name'] ?? 'Asset') ?> audit timeline</p>
                        </div>
                        <div class="trace-actions">
                            <?php 
                            $histUrl = $auditLogUrl . '?' . http_build_query([
                                'campaign_id' => $campaignId,
                                'asset_id'    => $selectedAsset['id'] ?? 0,
                                'from'        => 'detail_admin'
                            ]);
                            $compBtnUrl = $compareUrl . '?' . http_build_query([
                                'campaign_id' => $campaignId,
                                'asset_id'    => $selectedAsset['id'] ?? 0,
                                'from'        => 'admin'
                            ]);
                            ?>
                            <a href="<?= esc_url($histUrl) ?>" class="link-text-arrow" id="traceHistoryLink">
                                <span>View Version History →</span>
                            </a>
                            <a href="<?= esc_url($compBtnUrl) ?>" class="btn-secondary" style="height: 32px; padding: 0 12px;" id="traceCompareBtn">
                                <span>Compare Versions →</span>
                            </a>
                        </div>
                    </div>

                    <div class="timeline-track-container" id="timelineTrackContainer">
                        <?php if (empty($versionHistory)): ?>
                            <div class="empty-state-box" style="padding: 16px 0;">
                                <span>No version nodes recorded yet for this asset.</span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($versionHistory as $idx => $v): 
                                $stepNum = $idx + 1;
                                $circleClass = 'node-step-circle';
                                if ($v['is_current']) $circleClass .= ' active';
                                if ($v['status'] === 'approved') $circleClass .= ' approved';
                                elseif ($v['status'] === 'released') $circleClass .= ' released';
                            ?>
                                <div class="timeline-node">
                                    <div class="<?= $circleClass ?>"><?= $stepNum ?></div>
                                    <div class="node-content-box">
                                        <span class="node-title"><?= esc_html($v['version_label']) ?> · <?= esc_html($v['status_label']) ?></span>
                                        <span class="node-date"><?= esc_html($v['date']) ?></span>
                                    </div>
                                </div>
                                <?php if ($idx < count($versionHistory) - 1): ?>
                                    <div class="timeline-connector"></div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="timeline-footer-notice" id="timelineFooterNotice">
                        <?php if ($selectedAsset && $selectedAsset['status'] === 'approved'): ?>
                            Checker approved · Awaiting Admin Release sign-off
                        <?php elseif ($selectedAsset && $selectedAsset['status'] === 'released'): ?>
                            Asset released to production · Governance locked under MAS FR-07
                        <?php elseif ($selectedAsset && $selectedAsset['status'] === 'in_review'): ?>
                            Currently in Checker review queue
                        <?php else: ?>
                            Draft version in progress
                        <?php endif; ?>
                    </div>
                </section>

                <!-- ================= 7. SECTION 7: APPLICABLE GUIDELINES (5 COLS) + CAMPAIGN ACTIVITY (7 COLS) ================= -->
                <section class="split-grid-row">
                    <!-- Left (5 cols): Applicable Guidelines -->
                    <div class="split-card">
                        <div class="split-card-header">
                            <h2 class="split-card-title">Applicable Guidelines</h2>
                            <button type="button" class="btn-secondary" style="height: 32px; padding: 0 12px; font-size: 11.5px;" onclick="openGuidelinesModal()">
                                <span>Manage Guidelines</span>
                            </button>
                        </div>
                        <div class="split-table-box">
                            <?php if (empty($guidelines)): ?>
                                <div class="empty-state-box">
                                    <div class="empty-state-icon">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8"><rect width="8" height="4" x="8" y="2" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>
                                    </div>
                                    <p>No guidelines attached to this campaign.</p>
                                </div>
                            <?php else: ?>
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>DOCUMENT</th>
                                            <th>TYPE</th>
                                            <th style="text-align: center;">ACTION</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($guidelines as $g): 
                                            $tag = $g['tag'] ?? 'Standard';
                                            $tagClass = 'internal';
                                            if (stripos($tag, 'brand') !== false) $tagClass = 'brand';
                                            elseif (stripos($tag, 'regulatory') !== false || stripos($tag, 'mas') !== false) $tagClass = 'regulatory';
                                        ?>
                                            <tr>
                                                <td style="font-weight: 600; color: #1F2A5A;"><?= esc_html($g['name']) ?></td>
                                                <td><span class="type-tag <?= $tagClass ?>"><?= esc_html($tag) ?></span></td>
                                                <td style="text-align: center;">
                                                    <?php if (!empty($g['url'])): ?>
                                                        <a href="<?= esc_url($g['url']) ?>" target="_blank" class="link-text-arrow">View</a>
                                                    <?php else: ?>
                                                        <span style="color: #94A3B8;">—</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right (7 cols): Campaign Activity Ledger -->
                    <div class="split-card">
                        <div class="split-card-header">
                            <h2 class="split-card-title">Campaign Activity</h2>
                            <a href="<?= esc_url($auditLogUrl . ($campaignId ? '?campaign_id=' . $campaignId . '&from=detail_admin' : '')) ?>" class="link-text-arrow">
                                <span>View Audit History →</span>
                            </a>
                        </div>
                        <div class="split-table-box">
                            <?php if (empty($activities)): ?>
                                <div class="empty-state-box">
                                    <div class="empty-state-icon">
                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    </div>
                                    <p>No audit activity recorded yet for this campaign.</p>
                                </div>
                            <?php else: ?>
                                <table class="admin-table">
                                    <thead>
                                        <tr>
                                            <th>DATE</th>
                                            <th>EVENT</th>
                                            <th>ACTOR</th>
                                            <th>TYPE</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (array_slice($activities, 0, 5) as $act): 
                                            $actType = $act['type'] ?? 'Governance';
                                            $typeClass = 'internal';
                                            if ($actType === 'Review') $typeClass = 'review';
                                            elseif ($actType === 'Release') $typeClass = 'release';
                                            elseif ($actType === 'Upload') $typeClass = 'upload';
                                        ?>
                                            <tr>
                                                <td style="white-space: nowrap; color: #64748B; font-size: 11.5px;"><?= esc_html($act['date_full'] ?? $act['date_time']) ?></td>
                                                <td style="font-weight: 500; color: #1E293B;"><?= esc_html($act['event'] ?? $act['text']) ?></td>
                                                <td class="user-cell" style="font-size: 11.5px;"><?= esc_html($act['actor'] ?? $act['actor_info']) ?></td>
                                                <td><span class="type-tag <?= $typeClass ?>"><?= esc_html($actType) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>

            <?php endif; // !$isDbEmpty ?>

        </main>
    <?php endif; // $isAllowed ?>

</div>

<!-- ================= MODAL 1: FINAL REVIEW & PRODUCTION RELEASE ================= -->
<div class="modal-overlay" id="releaseModal">
    <div class="modal-card">
        <form method="POST" action="" id="releaseForm">
            <input type="hidden" name="action" id="adminActionInput" value="admin_release" />
            <input type="hidden" name="version_id" id="modalVersionId" value="0" />

            <div class="modal-header">
                <h3 class="modal-title">Final Review & Production Release</h3>
                <span class="btn-modal-close" onclick="closeReleaseModal()">&times;</span>
            </div>

            <div class="modal-body">
                <div class="modal-asset-preview">
                    <div style="width: 44px; height: 44px; border-radius: 8px; background: #EEF0FB; display: flex; align-items: center; justify-content: center; color: #1F2A5A;">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div>
                        <h4 style="font-size: 14px; font-weight: 700; color: #1F2A5A;" id="modalAssetTitle">Asset Title</h4>
                        <div style="font-size: 12px; color: #64748B; display: flex; gap: 8px; align-items: center;">
                            <span id="modalCampaignTitle">Campaign</span>
                            <span>·</span>
                            <span class="version-badge" id="modalVersionBadge">V1</span>
                        </div>
                    </div>
                </div>

                <!-- Checker Verdict Breakdown -->
                <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <span style="font-size: 12px; font-weight: 600; color: #475569;">
                            Checker Review: <strong id="modalCheckerName">Checker</strong>
                        </span>
                        <span class="status-pill approved">VERIFIED & APPROVED</span>
                    </div>
                    <p style="font-size: 12.5px; color: #334155; font-style: italic;" id="modalReviewNotes">
                        "Asset verified against corporate guidelines and MAS financial advertisement standards. Ready for production release."
                    </p>
                </div>

                <!-- MAS Compliance Governance Warning -->
                <div class="governance-warning-box">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink: 0;"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>Releasing this asset will lock the version permanently in accordance with MAS compliance governance (FR-07). It cannot be modified or deleted.</span>
                </div>

                <!-- Release Notes Input -->
                <div>
                    <label class="form-label" for="releaseNotesTextarea">Admin Decision Notes (Feedback / Release Sign-off)</label>
                    <textarea class="form-textarea" name="release_notes" id="releaseNotesTextarea" placeholder="Enter release sign-off notes or revision feedback if rejecting..."></textarea>
                </div>
            </div>

            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                <div style="display: flex; gap: 8px; align-items: center;">
                    <a href="#" id="modalFinalReviewLink" class="btn-secondary" style="height: 36px; background: #F4F3FD; color: #4E56A6; border-color: #D5D9F3; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <span>Dedicated Final Review Canvas &rarr;</span>
                    </a>
                    <a href="#" id="modalCompareLink" class="btn-secondary" style="height: 36px;">
                        <span>Compare Versions</span>
                    </a>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" class="btn-secondary" style="height: 36px;" onclick="closeReleaseModal()">Cancel</button>
                    <button type="button" class="btn-secondary" style="height: 36px; color: #BA1A1A; border-color: #FECDD3; background: #FFF1F2; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;" onclick="submitAdminRejectDetail()">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <span>Reject / Request Revisions</span>
                    </button>
                    <button type="submit" class="btn-primary" style="height: 36px;" onclick="document.getElementById('adminActionInput').value='admin_release'">Release to Production</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ================= MODAL 2: VIEW RELEASE RECORD ================= -->
<div class="modal-overlay" id="viewReleaseModal">
    <div class="modal-card" style="max-width: 520px;">
        <div class="modal-header">
            <h3 class="modal-title">Production Release Record</h3>
            <span class="btn-modal-close" onclick="closeViewReleaseModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="modal-asset-preview">
                <div style="width: 44px; height: 44px; border-radius: 8px; background: #ECFDF5; color: #065F46; display: flex; align-items: center; justify-content: center;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#065F46" stroke-width="2"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </div>
                <div>
                    <h4 style="font-size: 14px; font-weight: 700; color: #1F2A5A;" id="viewRelTitle">Asset</h4>
                    <div style="font-size: 12px; color: #64748B; display: flex; gap: 8px; align-items: center;">
                        <span id="viewRelCampaign">Campaign</span>
                        <span>·</span>
                        <span class="version-badge" id="viewRelVersion">V1</span>
                    </div>
                </div>
            </div>

            <div style="background: #ECFDF5; border: 1px solid #A7F3D0; border-radius: 8px; padding: 14px 16px; font-size: 12px; color: #065F46; line-height: 1.6;">
                <p><strong>Status:</strong> Released to Production</p>
                <p><strong>Released By:</strong> <span id="viewRelAdmin">Admin</span></p>
                <p><strong>Timestamp:</strong> <span id="viewRelTimestamp">—</span></p>
                <p style="margin-top: 6px;">This asset version is locked under MAS regulatory compliance. All modifications require a new version iteration.</p>
            </div>
        </div>
        <div class="modal-footer" style="justify-content: flex-end;">
            <button type="button" class="btn-secondary" style="height: 36px;" onclick="closeViewReleaseModal()">Close</button>
        </div>
    </div>
</div>

<!-- ================= MODAL 3: MANAGE GUIDELINES ================= -->
<div class="modal-overlay" id="guidelinesModal">
    <div class="modal-card" style="max-width: 600px;">
        <div class="modal-header">
            <h3 class="modal-title">Campaign Governance Guidelines</h3>
            <span class="btn-modal-close" onclick="closeGuidelinesModal()">&times;</span>
        </div>
        <div class="modal-body">
            <p style="font-size: 13px; color: #64748B;">
                The following guidelines are active for <strong><?= esc_html($campaignName) ?></strong>. All AI pre-flight and checker verifications evaluate assets against these standards.
            </p>
            <div style="border: 1px solid #E2E8F0; border-radius: 8px; overflow: hidden;">
                <?php if (empty($guidelines)): ?>
                    <div class="empty-state-box">
                        <p>No guidelines currently configured.</p>
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>DOCUMENT</th>
                                <th>TYPE</th>
                                <th style="text-align: center;">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($guidelines as $g): ?>
                                <tr>
                                    <td style="font-weight: 600; color: #1F2A5A;"><?= esc_html($g['name']) ?></td>
                                    <td><span class="type-tag internal"><?= esc_html($g['tag'] ?? 'Standard') ?></span></td>
                                    <td style="text-align: center; white-space: nowrap;">
                                        <div style="display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                                            <?php if (!empty($g['url'])): ?>
                                                <a href="<?= esc_url($g['url']) ?>" target="_blank" class="link-text-arrow">View File</a>
                                            <?php else: ?>
                                                <span style="color: #94A3B8;">—</span>
                                            <?php endif; ?>
                                            <form method="POST" action="" onsubmit="return confirm('Remove guideline document &quot;<?= esc_js($g['name']) ?>&quot; from this campaign?');" style="margin: 0; display: inline;">
                                                <input type="hidden" name="action" value="delete_guideline">
                                                <input type="hidden" name="campaign_id" value="<?= (int)$campaignId ?>">
                                                <input type="hidden" name="guideline_url" value="<?= esc_attr($g['url']) ?>">
                                                <button type="submit" style="padding: 3px 8px; font-size: 11px; color: #BA1A1A; background: #FFF1F2; border: 1px solid #FECDD3; border-radius: 4px; cursor: pointer; font-weight: 600;">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Upload More Guidelines Form -->
            <div style="margin-top: 18px; padding-top: 16px; border-top: 1px solid #E2E8F0;">
                <label style="font-size: 12px; font-weight: 700; color: #1F2A5A; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">Attach Additional Guidelines</label>
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_guideline">
                    <input type="hidden" name="campaign_id" value="<?= (int)$campaignId ?>">
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <input type="file" name="guideline_files[]" class="form-input" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg" multiple required style="padding: 8px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px;">
                        <p style="font-size: 11.5px; color: #64748B; margin: 0;">Supports multiple documents (.pdf, .docx, .doc, .txt, .png, .jpg). These will be cited by AI and checkers.</p>
                        <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 4px;">
                            <button type="submit" class="btn-primary" style="height: 34px; padding: 0 16px; font-size: 12.5px;">+ Upload Guidelines</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="modal-footer" style="justify-content: flex-end;">
            <button type="button" class="btn-secondary" style="height: 36px;" onclick="closeGuidelinesModal()">Close</button>
        </div>
    </div>
</div>

<!-- ================= MODAL 4: EDIT CAMPAIGN DETAILS & TEAM ================= -->
<div class="modal-overlay" id="editCampaignModal">
    <div class="modal-card" style="max-width: 580px;">
        <div class="modal-header">
            <div>
                <h3 class="modal-title">Edit Campaign</h3>
                <p style="font-size: 13px; color: #64748B; margin-top: 2px;">Update campaign details and team assignments</p>
            </div>
            <span class="btn-modal-close" onclick="closeEditCampaignModal()">&times;</span>
        </div>
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_campaign">
            <input type="hidden" name="campaign_id" value="<?= (int)$campaignId ?>">
            
            <div class="modal-body" style="padding: 24px; display: flex; flex-direction: column; gap: 16px;">
                <!-- Edit Campaign Title -->
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Campaign Title</label>
                    <input type="text" name="campaign_title" id="editCampaignTitleInput" required value="<?= esc_attr($campaignName) ?>" style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; background: #fff; box-sizing: border-box;" placeholder="Enter campaign name">
                </div>

                <!-- Edit Deadline -->
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Target Deadline</label>
                    <input type="date" name="campaign_deadline" id="editCampaignDeadlineInput" value="<?= esc_attr($deadlineIso) ?>" style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; background: #fff; box-sizing: border-box;">
                </div>

                <!-- Edit Owner -->
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Campaign Owner</label>
                    <select name="owner_id" id="editCampaignOwnerSelect" required style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; background: #fff; box-sizing: border-box;">
                        <?php foreach ($allAdminsList as $adm): 
                            list($admId, $admName, $admEmail) = pcs_extract_user_info($adm);
                        ?>
                            <option value="<?= esc_attr($admId) ?>" <?= ($campaignOwnerId === (int)$admId) ? 'selected' : '' ?>>
                                <?= esc_html($admName) ?> (<?= esc_html($admEmail) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Edit Makers -->
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Assigned Maker(s)</label>
                    <div class="user-picker-wrap" id="editMakerPicker">
                        <div class="user-picker-box" onclick="focusPickerInput('editMakerPicker')">
                            <div class="user-picker-tags" id="editMakerPickerTags">
                                <?php foreach ($campaignMakerIds as $mId): 
                                    $u = function_exists('get_userdata') ? get_userdata($mId) : null;
                                    $mName = $u ? ($u->display_name ?: $u->user_login) : ('User #' . $mId);
                                ?>
                                    <span class="user-picker-tag" data-id="<?= esc_attr($mId) ?>">
                                        <span><?= esc_html($mName) ?></span>
                                        <span class="user-picker-tag-remove" onclick="removePickerUser('editMakerPicker', '<?= esc_attr($mId) ?>')">&times;</span>
                                        <input type="hidden" name="maker_ids[]" value="<?= esc_attr($mId) ?>">
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" class="user-picker-input" id="editMakerPickerInput" placeholder="Type to search Makers..." onfocus="openPickerDropdown('editMakerPicker')" oninput="filterPickerUsers('editMakerPicker', this.value)">
                        </div>
                        <div class="user-picker-dropdown" id="editMakerPickerDropdown" style="display: none;">
                            <?php foreach ($allMakersList as $mUser): 
                                list($uId, $uName, $uEmail) = pcs_extract_user_info($mUser);
                            ?>
                                <div class="user-dropdown-item" data-id="<?= esc_attr($uId) ?>" data-name="<?= esc_attr($uName) ?>" data-email="<?= esc_attr($uEmail) ?>" onclick="addPickerUser('editMakerPicker', '<?= esc_attr($uId) ?>', '<?= esc_js($uName) ?>', 'maker_ids[]')">
                                    <div class="user-dropdown-avatar" style="background: #373E8C;"><?= strtoupper(substr($uName, 0, 2)) ?></div>
                                    <div>
                                        <div class="user-dropdown-name"><?= esc_html($uName) ?></div>
                                        <div class="user-dropdown-email"><?= esc_html($uEmail) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Edit Checkers -->
                <div>
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Assigned Checker(s)</label>
                    <div class="user-picker-wrap" id="editCheckerPicker">
                        <div class="user-picker-box" onclick="focusPickerInput('editCheckerPicker')">
                            <div class="user-picker-tags" id="editCheckerPickerTags">
                                <?php foreach ($campaignCheckerIds as $cId): 
                                    $u = function_exists('get_userdata') ? get_userdata($cId) : null;
                                    $cName = $u ? ($u->display_name ?: $u->user_login) : ('User #' . $cId);
                                ?>
                                    <span class="user-picker-tag" data-id="<?= esc_attr($cId) ?>">
                                        <span><?= esc_html($cName) ?></span>
                                        <span class="user-picker-tag-remove" onclick="removePickerUser('editCheckerPicker', '<?= esc_attr($cId) ?>')">&times;</span>
                                        <input type="hidden" name="checker_ids[]" value="<?= esc_attr($cId) ?>">
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            <input type="text" class="user-picker-input" id="editCheckerPickerInput" placeholder="Type to search Checkers..." onfocus="openPickerDropdown('editCheckerPicker')" oninput="filterPickerUsers('editCheckerPicker', this.value)">
                        </div>
                        <div class="user-picker-dropdown" id="editCheckerPickerDropdown" style="display: none;">
                            <?php foreach ($allCheckersList as $cUser): 
                                list($uId, $uName, $uEmail) = pcs_extract_user_info($cUser);
                            ?>
                                <div class="user-dropdown-item" data-id="<?= esc_attr($uId) ?>" data-name="<?= esc_attr($uName) ?>" data-email="<?= esc_attr($uEmail) ?>" onclick="addPickerUser('editCheckerPicker', '<?= esc_attr($uId) ?>', '<?= esc_js($uName) ?>', 'checker_ids[]')">
                                    <div class="user-dropdown-avatar" style="background: #00B894;"><?= strtoupper(substr($uName, 0, 2)) ?></div>
                                    <div>
                                        <div class="user-dropdown-name"><?= esc_html($uName) ?></div>
                                        <div class="user-dropdown-email"><?= esc_html($uEmail) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn-secondary" style="height: 38px; padding: 0 16px;" onclick="closeEditCampaignModal()">Cancel</button>
                <button type="submit" class="btn-primary" style="height: 38px; padding: 0 18px;">Save Campaign Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= JAVASCRIPT CONTROLLERS ================= -->
<script>
// Tab Filtering Controller
document.querySelectorAll('.tab-btn').forEach(button => {
    button.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        this.classList.add('active');
        
        const filter = this.getAttribute('data-filter');
        const rows = document.querySelectorAll('#adminAssetsTable tbody tr.asset-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const group = row.getAttribute('data-filter-group');
            if (filter === 'all' || group === filter) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        const badge = document.getElementById('assetsCountBadge');
        if (badge) badge.textContent = visibleCount;
    });
});

// Live Search Controller
const searchInput = document.getElementById('assetSearchInput');
if (searchInput) {
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#adminAssetsTable tbody tr.asset-row');
        let visibleCount = 0;
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(query)) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });
        
        const badge = document.getElementById('assetsCountBadge');
        if (badge) badge.textContent = visibleCount;
    });
}

// Select Asset Row & Dynamically Update Spotlight & Trace
function selectAssetRow(row) {
    document.querySelectorAll('#adminAssetsTable tbody tr.asset-row').forEach(r => r.classList.remove('active-row'));
    row.classList.add('active-row');

    const assetId      = row.getAttribute('data-asset-id');
    const assetName    = row.getAttribute('data-asset-name');
    const versionLabel = row.getAttribute('data-version-label');
    const format       = row.getAttribute('data-format');
    const maker        = row.getAttribute('data-maker');
    const submitted    = row.getAttribute('data-submitted');
    const checker      = row.getAttribute('data-checker');
    const reviewed     = row.getAttribute('data-reviewed');
    const reviewStatus = row.getAttribute('data-review-status');
    const releaseStatus= row.getAttribute('data-release-status');
    const nextLabel    = row.getAttribute('data-next');
    const fileUrl      = row.getAttribute('data-file-url');
    const versionId    = row.getAttribute('data-version-id');
    const isOverride   = row.getAttribute('data-override') === '1';

    // Update URL query without full reload
    if (window.history && window.history.replaceState) {
        const newUrl = new URL(window.location.href);
        newUrl.searchParams.set('asset_id', assetId);
        window.history.replaceState({}, '', newUrl);
    }

    // Update Section 5 (Spotlight)
    const titleEl = document.getElementById('spotlightAssetTitle');
    if (titleEl) titleEl.textContent = assetName + ' · ' + versionLabel;

    const formatEl = document.getElementById('spotlightAssetFormat');
    if (formatEl) formatEl.textContent = format;

    const authorRow = document.getElementById('spotlightAuthorRow');
    if (authorRow) authorRow.innerHTML = '<span>' + escapeHtml(maker) + ' · Maker</span><span class="meta-dot">·</span><span>' + escapeHtml(submitted) + '</span>';

    const decisionText = document.getElementById('spotlightDecisionText');
    if (decisionText) decisionText.textContent = reviewStatus;

    const decisionDot = document.querySelector('#spotlightDecisionRow .status-dot');
    if (decisionDot) {
        decisionDot.className = 'status-dot ' + (reviewStatus === 'Approved' ? 'dot-teal' : (reviewStatus === 'Rejected' ? 'dot-red' : 'dot-amber'));
    }

    const checkerInfo = document.getElementById('spotlightCheckerInfo');
    if (checkerInfo) checkerInfo.textContent = checker + ' · ' + (reviewed || 'Review record logged');

    const overrideBadge = document.getElementById('spotlightOverrideBadge');
    if (overrideBadge) {
        if (isOverride) {
            overrideBadge.className = 'override-badge';
            overrideBadge.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg><span>Override recorded</span>';
        } else {
            overrideBadge.className = 'override-badge standard';
            overrideBadge.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg><span>Standard compliance</span>';
        }
    }

    const releaseTitle = document.getElementById('spotlightReleaseTitle');
    if (releaseTitle) releaseTitle.textContent = releaseStatus;

    const nextEl = document.getElementById('spotlightNextLabel');
    if (nextEl) nextEl.textContent = 'Next: ' + nextLabel;

    // Update Trace Subtitle
    const traceSub = document.getElementById('traceAssetSubtitle');
    if (traceSub) traceSub.textContent = assetName + ' audit timeline';

    // Update Action Buttons
    const actionsBox = document.getElementById('spotlightActionsContainer');
    if (actionsBox) {
        let html = '';
        if (reviewStatus === 'Approved' && releaseStatus === 'Awaiting Release') {
            html += '<button type="button" class="btn-primary" style="height: 36px; padding: 0 16px;" onclick="openReleaseModal(' + escapeAttr(JSON.stringify({
                version_id: versionId,
                asset_id: assetId,
                asset_name: assetName,
                version_label: versionLabel,
                campaign_name: '<?= esc_js($campaignName) ?>',
                maker_name: maker,
                checker_name: checker,
                review_note: row.getAttribute('data-review-note') || 'Asset verified against corporate guidelines and MAS financial advertisement standards. Ready for production release.',
                file_url: fileUrl
            })) + ')">Open Final Review</button>';
        } else if (releaseStatus === 'Released') {
            const relBase = '<?= (function_exists('pcs_url') ? pcs_url('release-detail.php') : 'release-detail.php') ?>';
            const relLink = relBase + '?version_id=' + encodeURIComponent(versionId) + '&asset_id=' + encodeURIComponent(assetId) + '&campaign_id=<?= (int)$campaignId ?>&from=admin_assets';
            html += '<a href="' + escapeAttr(relLink) + '" class="btn-secondary" style="height: 36px; padding: 0 16px; text-decoration: none; display: inline-flex; align-items: center;">View Release Details</a>';
        }
        if (fileUrl) {
            html += '<a href="' + escapeAttr(fileUrl) + '" target="_blank" class="link-text-arrow"><span>View Version →</span></a>';
        }
        actionsBox.innerHTML = html;
    }
}

// Modal Handlers
function openReleaseModal(data) {
    document.getElementById('modalVersionId').value = data.version_id || 0;
    document.getElementById('modalAssetTitle').textContent = data.asset_name || 'Asset';
    document.getElementById('modalCampaignTitle').textContent = data.campaign_name || 'Campaign';
    document.getElementById('modalVersionBadge').textContent = data.version_label || 'V1';
    document.getElementById('modalCheckerName').textContent = data.checker_name || 'Checker';
    document.getElementById('modalReviewNotes').textContent = '"' + (data.review_note || 'Asset verified against standards.') + '"';

    const compareLink = document.getElementById('modalCompareLink');
    if (compareLink) {
        compareLink.href = '<?= esc_js($compareUrl) ?>?campaign_id=<?= (int)$campaignId ?>&asset_id=' + encodeURIComponent(data.asset_id || 0) + '&from=admin';
    }

    const finalReviewLink = document.getElementById('modalFinalReviewLink');
    if (finalReviewLink) {
        const finalReviewBase = '<?= esc_js(function_exists('pcs_url') ? pcs_url('final-review-admin.php') : 'final-review-admin.php') ?>';
        finalReviewLink.href = finalReviewBase + '?version_id=' + encodeURIComponent(data.version_id || 0) + '&asset_id=' + encodeURIComponent(data.asset_id || 0) + '&campaign_id=<?= (int)$campaignId ?>';
    }

    document.getElementById('releaseModal').classList.add('active');
}
function closeReleaseModal() {
    document.getElementById('releaseModal').classList.remove('active');
}

function submitAdminRejectDetail() {
    var notesEl = document.getElementById('releaseNotesTextarea');
    var notes = notesEl ? notesEl.value.trim() : '';
    if (!notes) {
        notes = prompt('Please enter the reason for rejecting this asset and requesting revisions:');
        if (notes === null) return;
        if (!notes.trim()) {
            alert('Rejection feedback is required.');
            return;
        }
        if (notesEl) notesEl.value = notes;
    }
    var actEl = document.getElementById('adminActionInput');
    if (actEl) actEl.value = 'admin_reject';
    var formEl = document.getElementById('releaseForm');
    if (formEl) formEl.submit();
}

function openViewReleaseModal(data) {
    document.getElementById('viewRelTitle').textContent = data.asset_name || 'Asset';
    document.getElementById('viewRelCampaign').textContent = data.campaign_name || 'Campaign';
    document.getElementById('viewRelVersion').textContent = data.version_label || 'V1';
    document.getElementById('viewRelAdmin').textContent = data.released_by || 'Admin';
    document.getElementById('viewRelTimestamp').textContent = data.released_at || 'Recorded in Audit Log';

    document.getElementById('viewReleaseModal').classList.add('active');
}
function closeViewReleaseModal() {
    document.getElementById('viewReleaseModal').classList.remove('active');
}

function openGuidelinesModal() {
    document.getElementById('guidelinesModal').classList.add('active');
}
function closeGuidelinesModal() {
    document.getElementById('guidelinesModal').classList.remove('active');
}

function openEditCampaignModal() {
    const modal = document.getElementById('editCampaignModal');
    if (modal) modal.classList.add('active');
}
function closeEditCampaignModal() {
    const modal = document.getElementById('editCampaignModal');
    if (modal) modal.classList.remove('active');
}

// User Multi-Select Picker Functions
function focusPickerInput(pickerId) {
    const input = document.getElementById(pickerId + 'Input');
    if (input) input.focus();
}

function openPickerDropdown(pickerId) {
    document.querySelectorAll('.user-picker-dropdown').forEach(dd => {
        if (dd.id !== pickerId + 'Dropdown') dd.style.display = 'none';
    });
    const dd = document.getElementById(pickerId + 'Dropdown');
    if (dd) dd.style.display = 'block';
}

function filterPickerUsers(pickerId, query) {
    const dd = document.getElementById(pickerId + 'Dropdown');
    if (!dd) return;
    dd.style.display = 'block';
    const q = query.toLowerCase().trim();
    const items = dd.querySelectorAll('.user-dropdown-item');
    items.forEach(item => {
        const name = (item.getAttribute('data-name') || '').toLowerCase();
        const email = (item.getAttribute('data-email') || '').toLowerCase();
        item.style.display = (!q || name.includes(q) || email.includes(q)) ? 'flex' : 'none';
    });
}

function addPickerUser(pickerId, userId, userName, inputName) {
    const tagsContainer = document.getElementById(pickerId + 'Tags');
    const input = document.getElementById(pickerId + 'Input');
    const dd = document.getElementById(pickerId + 'Dropdown');
    if (!tagsContainer) return;

    if (tagsContainer.querySelector('[data-id="' + userId + '"]')) {
        if (dd) dd.style.display = 'none';
        if (input) input.value = '';
        return;
    }

    const tag = document.createElement('span');
    tag.className = 'user-picker-tag';
    tag.setAttribute('data-id', userId);
    tag.innerHTML = '<span>' + escapeHtml(userName) + '</span>' +
        '<span class="user-picker-tag-remove" onclick="removePickerUser(\'' + pickerId + '\', \'' + userId + '\')">&times;</span>' +
        '<input type="hidden" name="' + inputName + '" value="' + userId + '">';
    tagsContainer.appendChild(tag);

    if (input) input.value = '';
    if (dd) dd.style.display = 'none';
}

function removePickerUser(pickerId, userId) {
    const tagsContainer = document.getElementById(pickerId + 'Tags');
    if (!tagsContainer) return;
    const tag = tagsContainer.querySelector('[data-id="' + userId + '"]');
    if (tag) tag.remove();
}

// Close user picker dropdown when clicking outside
window.addEventListener('click', function(e) {
    if (!e.target.closest('.user-picker-wrap')) {
        document.querySelectorAll('.user-picker-dropdown').forEach(dd => dd.style.display = 'none');
    }
});

// Close modals with Escape key or click outside
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReleaseModal();
        closeViewReleaseModal();
        closeGuidelinesModal();
        closeEditCampaignModal();
    }
});
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) {
            closeReleaseModal();
            closeViewReleaseModal();
            closeGuidelinesModal();
            closeEditCampaignModal();
        }
    });
});

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
function escapeAttr(str) {
    if (!str) return '';
    return str.replace(/"/g, "&quot;");
}
</script>

    <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body>
</html>

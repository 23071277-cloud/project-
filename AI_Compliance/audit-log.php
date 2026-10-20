<?php
/**
 * Template Name: Audit Log - Checker & Admin
 * Description: Immutable Regulatory Compliance Audit Trail with Role-Based Access Control (Checker & Admin).
 * 100% Dynamic Database Integration: audit_log, asset_version, asset, campaign, wp_users
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
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return trim(strip_tags((string) $str)); }
}
if (!function_exists('home_url')) {
    function home_url($path = '/') { return $path; }
}

// 3. Current User & RBAC Authentication (Checker and Admin Only - Maker Strictly Blocked)
$currentUser = function_exists('pcs_enforce_rbac') ? pcs_enforce_rbac(['CHECKER', 'ADMIN']) : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'CHECKER');
$isAllowed = ($userRole === 'CHECKER' || $userRole === 'ADMIN' || $userRole === 'ADMINISTRATOR');

// 4. Input Parameters & Data Resolution
$assetId    = (int) ($_GET['asset_id'] ?? 0);
$versionId  = (int) ($_GET['version_id'] ?? 0);
$campaignId = (int) ($_GET['campaign_id'] ?? 0);
$viewMode   = (!empty($_GET['mode']) && $_GET['mode'] === 'all') ? 'all' : 'asset';
$fromSource = sanitize_text_field($_GET['from'] ?? '');

$auditData = function_exists('get_audit_log_screen_data') 
    ? get_audit_log_screen_data($assetId, $versionId, $campaignId, $viewMode)
    : [];

$campaignTitle    = $auditData['campaign_title'] ?? 'All Campaigns';
$assetTitle       = $auditData['asset_title'] ?? 'All Assets';
$versionLabel     = $auditData['version_label'] ?? '—';
$versionsStr      = $auditData['versions_str'] ?? 'None';
$versionsList     = $auditData['versions_list'] ?? [];
$currentState     = $auditData['current_state'] ?? 'In Review';
$currentStyle     = $auditData['current_style'] ?? ['color' => '#B46505', 'bg' => '#FEF6E8', 'border' => '#FAD79E', 'dot' => '#B46505'];
$events           = $auditData['events'] ?? [];
$totalEvents      = count($events);
$distinctVersions = $auditData['distinct_versions'] ?? [];
$distinctActions  = $auditData['distinct_actions'] ?? [];
$distinctActors   = $auditData['distinct_actors'] ?? [];

// 5. Source-Aware Return URL and Label
$returnUrl = '';
$returnLabel = 'Return to Review';

if ($fromSource === 'release_detail') {
    $returnUrl = pcs_url('release-detail.php') . ($versionId ? '?version_id=' . (int)$versionId . '&asset_id=' . (int)$assetId . '&campaign_id=' . (int)$campaignId : '');
    $returnLabel = 'Return to Released Detail';
} elseif ($fromSource === 'final_review_admin') {
    $returnUrl = pcs_url('final-review-admin.php') . ($versionId ? '?version_id=' . (int)$versionId . '&asset_id=' . (int)$assetId . '&campaign_id=' . (int)$campaignId : '');
    $returnLabel = 'Return to Final Review';
} elseif ($fromSource === 'approval_queue_admin') {
    $returnUrl = pcs_url('approval-queue-admin.php');
    $returnLabel = 'Return to Approval Queue';
} elseif ($fromSource === 'admin_dashboard') {
    $returnUrl = pcs_url('admin-dashboard.php');
    $returnLabel = 'Return to Dashboard';
} elseif ($fromSource === 'admin_review') {
    $returnUrl = pcs_url('final-review-admin.php') . ($versionId ? '?version_id=' . (int)$versionId . '&asset_id=' . (int)$assetId . '&campaign_id=' . (int)$campaignId : pcs_url('admin-dashboard.php'));
    $returnLabel = 'Return to Final Review';
} elseif ($fromSource === 'detail_admin') {
    $returnUrl = pcs_url('detail-asset-admin.php') . ($campaignId ? '?campaign_id=' . (int)$campaignId : '');
    $returnLabel = 'Return to Asset Details';
} elseif ($fromSource === 'detail_asset' || $fromSource === 'detail_checker') {
    $returnUrl = pcs_url('detail-asset-checker.php') . ($campaignId ? '?campaign_id=' . (int)$campaignId : '');
    $returnLabel = 'Return to Asset Details';
} elseif ($fromSource === 'detail_maker' || $fromSource === 'maker_detail') {
    $returnUrl = pcs_url('detail-asset-maker.php') . ($campaignId ? '?campaign_id=' . (int)$campaignId . ($assetId ? '&asset_id=' . (int)$assetId : '') : '');
    $returnLabel = 'Return to Asset Details';
} else {
    // Default to checker review if asset & version exist, otherwise checker dashboard
    if ($assetId > 0 && $versionId > 0) {
        $returnUrl = pcs_url('checker-review.php') . '?campaign_id=' . (int)$campaignId . '&asset_id=' . (int)$assetId . '&version_id=' . (int)$versionId;
        $returnLabel = 'Return to Review';
    } else {
        $returnUrl = pcs_url('checker-dashboard.php');
        $returnLabel = 'Return to Dashboard';
    }
}

// URLs for View Mode Switcher
$assetHistoryUrl = '?' . http_build_query(array_merge($_GET, ['mode' => 'asset']));
$allActivityUrl  = '?' . http_build_query(array_merge($_GET, ['mode' => 'all']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Audit Log - Brand Compliance AI</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ================= RESET & GENERAL CONFIG ================= */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(0deg, #FAF8FF 0%, #FAF8FF 100%), #FFFFFF;
            color: #0F172A;
            line-height: 1.5;
            min-height: 100vh;
            position: relative;
        }

        /* ================= TOP UNIFIED NAVBAR CONTAINER ================= */
        .top-navbar-wrapper {
            position: sticky;
            top: 0;
            z-index: 1000;
            background: #FFFFFF;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
        }

        /* ================= MAIN CONTAINER ================= */
        .main-viewport {
            width: 100%;
            max-width: 1300px;
            margin: 0 auto;
            padding: 24px 24px 60px 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        /* ================= B. AUDIT LOG PAGE HEADER ================= */
        .audit-page-header {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .breadcrumb-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 500;
            color: #5F657B;
        }

        .breadcrumb-nav a {
            color: #5F657B;
            text-decoration: none;
            transition: color 0.15s;
        }

        .breadcrumb-nav a:hover {
            color: #4E56A6;
        }

        .breadcrumb-sep {
            color: #CBD5E1;
        }

        .breadcrumb-current {
            color: #161B2E;
            font-weight: 600;
        }

        .header-title {
            color: #1F2A5A;
            font-size: 24px;
            font-weight: 700;
            line-height: 32px;
        }

        .header-subtitle {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: #5F657B;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .header-subtitle .sep {
            color: #CBD5E1;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Segmented Mode Switcher */
        .view-mode-control {
            display: inline-flex;
            background: #FFFFFF;
            padding: 4px;
            border-radius: 8px;
            border: 1px solid #E2E6FA;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            gap: 2px;
        }

        .mode-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #5F657B;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
            border: none;
            background: transparent;
        }

        .mode-btn:hover {
            color: #161B2E;
        }

        .mode-btn.active {
            background: #F3F5FD;
            color: #4E56A6;
            font-weight: 600;
        }

        /* Source-Aware Return Button */
        .btn-return-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 14px;
            background: #FFFFFF;
            border: 1px solid #E2E6FA;
            border-radius: 8px;
            color: #161B2E;
            font-size: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.15s ease;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.03);
        }

        .btn-return-action:hover {
            background: #F8FAFC;
            border-color: #CBD2EE;
            color: #4E56A6;
        }

        .btn-return-action svg {
            width: 14px;
            height: 14px;
            stroke: #5F657B;
            transition: stroke 0.15s;
        }

        .btn-return-action:hover svg {
            stroke: #4E56A6;
        }

        /* ================= C. AUDIT SCOPE AND CONTEXT STRIP ================= */
        .scope-context-strip {
            width: 100%;
            background: #FFFFFF;
            border: 1px solid #E2E6FA;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: 0px 1px 3px rgba(0, 0, 0, 0.02);
        }

        .scope-strip-left {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 24px;
        }

        .scope-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .scope-label {
            font-size: 10px;
            font-weight: 700;
            color: #5F657B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .scope-value {
            font-size: 12px;
            font-weight: 600;
            color: #161B2E;
        }

        .scope-value.mono {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 500;
        }

        .scope-divider {
            width: 1px;
            height: 24px;
            background: #E2E6FA;
        }

        .state-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            line-height: 16px;
        }

        .state-badge .state-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        /* ================= D. COMPACT SEARCH & FILTER TOOLBAR ================= */
        .filter-toolbar {
            width: 100%;
            background: #FFFFFF;
            border: 1px solid #E2E6FA;
            border-radius: 12px;
            padding: 14px;
            box-shadow: 0px 1px 3px rgba(0, 0, 0, 0.02);
        }

        .toolbar-grid {
            display: grid;
            grid-template-columns: 2fr 2fr 2fr 4fr 1.5fr;
            gap: 12px;
            align-items: center;
        }

        @media (max-width: 992px) {
            .toolbar-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 640px) {
            .toolbar-grid {
                grid-template-columns: 1fr;
            }
        }

        .filter-select {
            width: 100%;
            height: 34px;
            padding: 6px 12px;
            background: #F3F5FD;
            border: 1px solid #E2E6FA;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 500;
            color: #161B2E;
            outline: none;
            cursor: pointer;
            transition: border-color 0.15s, background 0.15s;
        }

        .filter-select:focus {
            border-color: #4E56A6;
            background: #FFFFFF;
        }

        .search-wrap {
            position: relative;
            width: 100%;
        }

        .search-input {
            width: 100%;
            height: 34px;
            padding: 6px 12px 6px 36px;
            background: #F3F5FD;
            border: 1px solid #E2E6FA;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 400;
            color: #161B2E;
            outline: none;
            transition: all 0.15s;
        }

        .search-input::placeholder {
            color: #5F657B;
        }

        .search-input:focus {
            background: #FFFFFF;
            border-color: #4E56A6;
            box-shadow: 0 0 0 2px rgba(78, 86, 166, 0.12);
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px;
            height: 15px;
            stroke: #5F657B;
            pointer-events: none;
        }

        .btn-clear-filters {
            width: 100%;
            height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 6px 12px;
            background: transparent;
            border: 1px solid transparent;
            border-radius: 8px;
            color: #4E56A6;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }

        .btn-clear-filters:hover {
            background: #F3F5FD;
            border-color: #E2E6FA;
        }

        /* ================= E. MAIN AUDIT EVENTS TABLE ================= */
        .table-card {
            width: 100%;
            background: #FFFFFF;
            border: 1px solid #E2E6FA;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0px 1px 3px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .audit-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .audit-table thead tr {
            background: #F3F5FD;
            border-bottom: 1px solid #E2E6FA;
        }

        .audit-table th {
            padding: 12px 16px;
            font-size: 11px;
            font-weight: 700;
            color: #5F657B;
            text-transform: uppercase;
            letter-spacing: 0.55px;
            white-space: nowrap;
        }

        .audit-table tbody tr {
            border-bottom: 1px solid #E2E6FA;
            transition: background 0.12s ease;
        }

        .audit-table tbody tr:last-child {
            border-bottom: none;
        }

        .audit-table tbody tr:hover {
            background: #F8FAFC;
        }

        .audit-table td {
            padding: 16px;
            vertical-align: middle;
            font-size: 12px;
        }

        /* Column specific styles */
        .col-timestamp {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            color: #5F657B;
            white-space: nowrap;
            width: 160px;
        }

        .col-actor {
            font-weight: 500;
            color: #161B2E;
            white-space: nowrap;
            width: 160px;
        }

        .col-role {
            width: 105px;
        }

        .role-pill {
            display: inline-flex;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 16px;
        }

        .role-pill.checker {
            background: #F3F5FD;
            border: 1px solid #CBD2EE;
            color: #1F2A5A;
        }

        .role-pill.system {
            background: #F1F5F9;
            border: 1px solid #CBD5E1;
            color: #475569;
        }

        .role-pill.maker {
            background: #F5F3FF;
            border: 1px solid #DDD6FE;
            color: #6D28D9;
        }

        .role-pill.admin {
            background: #EEF2FF;
            border: 1px solid #C7D2FE;
            color: #3730A3;
        }

        .col-action {
            min-width: 280px;
        }

        .action-title {
            color: #1F2A5A;
            font-size: 12px;
            font-weight: 600;
            line-height: 16px;
        }

        .action-sub {
            color: #5F657B;
            font-size: 11px;
            font-weight: 400;
            line-height: 16px;
            margin-top: 2px;
        }

        .col-version {
            width: 85px;
            text-align: center;
        }

        .version-badge {
            display: inline-flex;
            padding: 1px 8px;
            background: #EEF4FF;
            border-radius: 4px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            font-weight: 600;
            color: #4E56A6;
            line-height: 16px;
        }

        .col-outcome {
            width: 190px;
        }

        .col-details {
            width: 90px;
            text-align: right;
        }

        .btn-view-detail {
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 4px;
            color: #4E56A6;
            font-size: 12px;
            font-weight: 600;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 6px;
            border-radius: 4px;
            transition: all 0.12s;
        }

        .btn-view-detail:hover {
            color: #373E8C;
            background: #F3F5FD;
        }

        .btn-view-detail svg {
            width: 14px;
            height: 14px;
            stroke: currentColor;
        }

        /* Empty state row */
        .empty-row-td {
            padding: 48px 24px !important;
            text-align: center;
            color: #64748B;
        }

        .empty-icon {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .empty-title {
            font-size: 15px;
            font-weight: 600;
            color: #1E293B;
            margin-bottom: 4px;
        }

        .empty-desc {
            font-size: 12px;
            color: #64748B;
            max-width: 480px;
            margin: 0 auto;
        }

        /* ================= F. PAGINATION AND AUDIT METRICS FOOTER ================= */
        .table-footer {
            padding: 12px 20px;
            background: rgba(250, 248, 255, 0.6);
            border-top: 1px solid #E2E6FA;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }

        .footer-metrics {
            color: #5F657B;
        }

        .footer-metrics strong {
            color: #161B2E;
            font-weight: 600;
        }

        .pagination-controls {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 10px;
            background: #FFFFFF;
            border: 1px solid #E2E6FA;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
            color: #5F657B;
            cursor: pointer;
            transition: all 0.12s;
            text-decoration: none;
        }

        .page-btn:hover:not(:disabled) {
            background: #F3F5FD;
            border-color: #CBD2EE;
            color: #161B2E;
        }

        .page-btn:disabled, .page-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .page-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 4px 10px;
            background: #4E56A6;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #FFFFFF;
            min-width: 26px;
        }

        /* ================= ACCESS DENIED CARD (RBAC) ================= */
        .access-denied-wrapper {
            max-width: 580px;
            margin: 60px auto;
            background: #FFFFFF;
            border: 1px solid #FECACA;
            border-radius: 16px;
            padding: 36px 32px;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
        }

        .denied-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px auto;
            background: #FEF2F2;
            color: #DC2626;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .denied-title {
            font-size: 20px;
            font-weight: 700;
            color: #991B1B;
            margin-bottom: 8px;
        }

        .denied-message {
            font-size: 14px;
            color: #4B5563;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .user-tag {
            background: #F3F4F6;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 600;
            color: #1F2937;
            display: inline-block;
            margin-bottom: 24px;
        }

        .btn-denied-return {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #1F2937;
            color: #FFFFFF;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            transition: background 0.15s;
        }

        .btn-denied-return:hover {
            background: #374151;
        }

        /* ================= EVENT DETAIL MODAL ================= */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.5);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: #FFFFFF;
            width: 100%;
            max-width: 620px;
            border-radius: 14px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            border: 1px solid #E2E6FA;
            overflow: hidden;
            animation: modalFadeIn 0.18s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.97); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            padding: 16px 20px;
            background: #F8FAFC;
            border-bottom: 1px solid #E2E6FA;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-title {
            font-size: 15px;
            font-weight: 700;
            color: #1F2A5A;
        }

        .modal-log-id {
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            padding: 2px 6px;
            background: #EEF4FF;
            color: #4E56A6;
            border-radius: 4px;
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #64748B;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
        }

        .modal-close:hover {
            color: #0F172A;
            background: #E2E8F0;
        }

        .modal-body {
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            background: #FAF8FF;
            padding: 14px;
            border-radius: 8px;
            border: 1px solid #E2E6FA;
        }

        .grid-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .grid-item-label {
            font-size: 10px;
            font-weight: 700;
            color: #5F657B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .grid-item-val {
            font-size: 12px;
            font-weight: 600;
            color: #161B2E;
        }

        .notes-box {
            background: #FFFFFF;
            border: 1px solid #E2E6FA;
            border-radius: 8px;
            padding: 12px 14px;
        }

        .notes-label {
            font-size: 11px;
            font-weight: 700;
            color: #1F2A5A;
            margin-bottom: 4px;
        }

        .notes-text {
            font-size: 12px;
            color: #334155;
            line-height: 1.6;
            white-space: pre-line;
        }

        .modal-footer {
            padding: 14px 20px;
            background: #F8FAFC;
            border-top: 1px solid #E2E6FA;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-modal-close {
            padding: 7px 14px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            cursor: pointer;
        }

        .btn-modal-close:hover {
            background: #F1F5F9;
        }

        .btn-modal-action {
            padding: 7px 14px;
            background: #4E56A6;
            border: 1px solid #4E56A6;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #FFFFFF;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-modal-action:hover {
            background: #373E8C;
        }
    </style>
</head>
<body>

    <!-- ================= 1. UNIFIED TOP NAVBAR ================= -->
    <div class="top-navbar-wrapper">
        <?php
        if (function_exists('pcs_render_top_navbar')) {
            pcs_render_top_navbar([
                'user'               => $currentUser,
                'all_campaigns_url'  => $returnUrl,
                'btn_text'           => $returnLabel
            ]);
        }
        ?>
    </div>

    <?php if (!$isAllowed): ?>
        <!-- ================= RBAC ACCESS DENIED SCREEN ================= -->
        <main class="main-viewport">
            <div class="access-denied-wrapper">
                <div class="denied-icon">🔒</div>
                <h1 class="denied-title">403 Forbidden: Access Restricted</h1>
                <p class="denied-message">
                    The Regulatory Compliance Audit Trail contains restricted legal and operational records. Access is exclusively granted to authorized <strong>Checker</strong> and <strong>Admin</strong> compliance officers.
                </p>
                <div class="user-tag">
                    Current User: <strong><?= esc_html($currentUser['name'] ?? 'User') ?></strong> · Role: <strong><?= esc_html($userRole) ?></strong>
                </div>
                <div>
                    <a href="<?= esc_url(pcs_url('maker-dashboard.php')) ?>" class="btn-denied-return">
                        ← Return to Maker Workspace
                    </a>
                </div>
            </div>
        </main>
    <?php else: ?>
        <!-- ================= AUTHORIZED AUDIT LOG VIEWPORT ================= -->
        <main class="main-viewport">

            <!-- B. AUDIT LOG PAGE HEADER -->
            <header class="audit-page-header">
                <div class="header-left">
                    <nav class="breadcrumb-nav">
                        <a href="<?= esc_url(pcs_url('checker-dashboard.php')) ?>">Review Queue</a>
                        <?php if (!empty($assetTitle)): ?>
                            <span class="breadcrumb-sep">/</span>
                            <a href="<?= esc_url($returnUrl) ?>"><?= esc_html($assetTitle) ?><?= !empty($versionLabel) ? ' · ' . esc_html($versionLabel) : '' ?></a>
                        <?php endif; ?>
                        <span class="breadcrumb-sep">/</span>
                        <span class="breadcrumb-current">Audit Log</span>
                    </nav>
                    <h1 class="header-title">Audit Log</h1>
                    <div class="header-subtitle">
                        <?php if (!empty($campaignTitle) || !empty($assetTitle)): ?>
                            <?php if (!empty($campaignTitle)): ?><span><?= esc_html($campaignTitle) ?></span><?php endif; ?>
                            <?php if (!empty($campaignTitle) && !empty($assetTitle)): ?><span class="sep">·</span><?php endif; ?>
                            <?php if (!empty($assetTitle)): ?><span><?= esc_html($assetTitle) ?></span><?php endif; ?>
                            <?php if (!empty($versionLabel)): ?><span class="sep">·</span><span><?= esc_html($versionLabel) ?></span><?php endif; ?>
                        <?php else: ?>
                            <span>System-wide Regulatory Audit Trail</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="header-right">
                    <!-- Segmented Control for View Modes -->
                    <div class="view-mode-control" role="tablist">
                        <a href="<?= esc_url($assetHistoryUrl) ?>" class="mode-btn <?= ($viewMode === 'asset') ? 'active' : '' ?>">
                            Asset History
                        </a>
                        <a href="<?= esc_url($allActivityUrl) ?>" class="mode-btn <?= ($viewMode === 'all') ? 'active' : '' ?>">
                            All Activity
                        </a>
                    </div>

                    <!-- Source-Aware Return Action -->
                    <a href="<?= esc_url($returnUrl) ?>" class="btn-return-action">
                        <svg viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M5.83333 11.0832L1.75 6.99984M1.75 6.99984L5.83333 2.9165M1.75 6.99984H12.25" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span><?= esc_html($returnLabel) ?></span>
                    </a>
                </div>
            </header>

            <!-- C. AUDIT SCOPE AND CONTEXT STRIP -->
            <section class="scope-context-strip">
                <div class="scope-strip-left">
                    <div class="scope-item">
                        <span class="scope-label">CAMPAIGN</span>
                        <span class="scope-value"><?= !empty($campaignTitle) ? esc_html($campaignTitle) : '<span style="color: #94A3B8; font-weight: 400;">—</span>' ?></span>
                    </div>

                    <div class="scope-divider"></div>

                    <div class="scope-item">
                        <span class="scope-label">ASSET</span>
                        <span class="scope-value"><?= !empty($assetTitle) ? esc_html($assetTitle) : '<span style="color: #94A3B8; font-weight: 400;">—</span>' ?></span>
                    </div>

                    <div class="scope-divider"></div>

                    <div class="scope-item">
                        <span class="scope-label">RECORDED VERSIONS</span>
                        <span class="scope-value mono"><?= !empty($versionsStr) ? esc_html($versionsStr) : '<span style="color: #94A3B8; font-weight: 400;">—</span>' ?></span>
                    </div>

                    <div class="scope-divider"></div>

                    <div class="scope-item">
                        <span class="scope-label">CURRENT WORKFLOW STATE</span>
                        <?php if (!empty($currentState) && !empty($currentStyle)): ?>
                            <div class="state-badge" style="background: <?= esc_attr($currentStyle['bg']) ?>; border: 1px solid <?= esc_attr($currentStyle['border']) ?>; color: <?= esc_attr($currentStyle['color']) ?>;">
                                <span class="state-dot" style="background: <?= esc_attr($currentStyle['dot']) ?>;"></span>
                                <span><?= esc_html($currentState) ?></span>
                            </div>
                        <?php else: ?>
                            <span style="color: #94A3B8; font-weight: 400; font-size: 12px;">—</span>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- D. COMPACT SEARCH AND FILTER TOOLBAR -->
            <section class="filter-toolbar">
                <div class="toolbar-grid">
                    <!-- Version Filter -->
                    <div>
                        <select class="filter-select" id="filterVersion">
                            <option value="">All Versions</option>
                            <?php foreach ($distinctVersions as $dVer): ?>
                                <option value="<?= esc_attr($dVer) ?>"><?= esc_html($dVer) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Action Filter -->
                    <div>
                        <select class="filter-select" id="filterAction">
                            <option value="">All Actions</option>
                            <?php foreach ($distinctActions as $dAct): ?>
                                <option value="<?= esc_attr($dAct) ?>"><?= esc_html($dAct) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Actor Filter -->
                    <div>
                        <select class="filter-select" id="filterActor">
                            <option value="">All Actors</option>
                            <?php foreach ($distinctActors as $dActor): ?>
                                <option value="<?= esc_attr($dActor) ?>"><?= esc_html($dActor) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Search Input -->
                    <div class="search-wrap">
                        <svg class="search-icon" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14 14L10 10M11.3333 6.66667C11.3333 9.24227 9.24227 11.3333 6.66667 11.3333C4.09106 11.3333 2 9.24227 2 6.66667C2 4.09106 4.09106 2 6.66667 2C9.24227 2 11.3333 4.09106 11.3333 6.66667L14 14" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <input type="text" id="searchAuditInput" class="search-input" placeholder="Search events or actors..." />
                    </div>

                    <!-- Clear Filters Button -->
                    <div>
                        <button type="button" class="btn-clear-filters" id="btnClearFilters">Clear Filters</button>
                    </div>
                </div>
            </section>

            <!-- E. MAIN AUDIT EVENTS TABLE -->
            <section class="table-card">
                <div class="table-responsive">
                    <table class="audit-table" id="auditEventsTable">
                        <thead>
                            <tr>
                                <th>TIMESTAMP</th>
                                <th>ACTOR</th>
                                <th>ROLE</th>
                                <th>ACTION & DETAILS</th>
                                <th style="text-align: center;">VERSION</th>
                                <th>OUTCOME / STATE</th>
                                <th style="text-align: right;">DETAILS</th>
                            </tr>
                        </thead>
                        <tbody id="auditTableBody">
                            <?php if (empty($events)): ?>
                                <tr id="noEventsRow">
                                    <td colspan="7" class="empty-row-td">
                                        <div class="empty-icon">📜</div>
                                        <div class="empty-title">No Audit Events Recorded Yet</div>
                                        <p class="empty-desc">
                                            All compliance actions (uploads, AI evaluations, reviewer approvals, overrides, and production releases) are immutably logged into WordPress database automatically.
                                        </p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($events as $idx => $ev): 
                                    $roleClass = strtolower($ev['actor_role'] ?? 'system');
                                    $evJson = htmlspecialchars(json_encode($ev), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <tr class="event-row" 
                                        data-version="<?= esc_attr($ev['version_label']) ?>"
                                        data-action="<?= esc_attr($ev['action_title']) ?>"
                                        data-actor="<?= esc_attr($ev['actor_name']) ?>"
                                        data-search="<?= esc_attr(strtolower($ev['action_title'] . ' ' . $ev['action_subtitle'] . ' ' . $ev['actor_name'] . ' ' . $ev['note'])) ?>">
                                        
                                        <!-- 1. Timestamp -->
                                        <td class="col-timestamp"><?= esc_html($ev['timestamp']) ?></td>

                                        <!-- 2. Actor -->
                                        <td class="col-actor"><?= esc_html($ev['actor_name']) ?></td>

                                        <!-- 3. Role -->
                                        <td class="col-role">
                                            <span class="role-pill <?= esc_attr($roleClass) ?>">
                                                <?= esc_html($ev['actor_role']) ?>
                                            </span>
                                        </td>

                                        <!-- 4. Action & Details -->
                                        <td class="col-action">
                                            <div class="action-title"><?= esc_html($ev['action_title']) ?></div>
                                            <div class="action-sub"><?= esc_html($ev['action_subtitle']) ?></div>
                                        </td>

                                        <!-- 5. Version -->
                                        <td class="col-version">
                                            <?php if ($ev['version_label'] !== '—'): ?>
                                                <span class="version-badge"><?= esc_html($ev['version_label']) ?></span>
                                            <?php else: ?>
                                                <span style="color: #94A3B8;">—</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- 6. Outcome / State -->
                                        <td class="col-outcome">
                                            <div class="state-badge" style="background: <?= esc_attr($ev['style']['bg'] ?? '#EEF4FF') ?>; border: 1px solid <?= esc_attr($ev['style']['border'] ?? '#CBD2EE') ?>; color: <?= esc_attr($ev['style']['color'] ?? '#4E56A6') ?>;">
                                                <span class="state-dot" style="background: <?= esc_attr($ev['style']['dot'] ?? '#4E56A6') ?>;"></span>
                                                <span><?= esc_html($ev['outcome_state']) ?></span>
                                            </div>
                                        </td>

                                        <!-- 7. Details View Action -->
                                        <td class="col-details">
                                            <button type="button" class="btn-view-detail" onclick="openEventModal(<?= $evJson ?>)">
                                                <span>View</span>
                                                <svg viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M5.25 2.9165L9.33333 6.99984L5.25 11.0832" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                                                </svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- F. PAGINATION AND AUDIT METRICS FOOTER -->
                <div class="table-footer">
                    <div class="footer-metrics">
                        Showing <strong id="visibleCount"><?= $totalEvents ?></strong> of <strong id="totalCount"><?= $totalEvents ?></strong> recorded events
                    </div>
                    <div class="pagination-controls">
                        <button type="button" class="page-btn" id="btnPrevPage" disabled>Previous</button>
                        <span class="page-num" id="currentPageNum">1</span>
                        <button type="button" class="page-btn" id="btnNextPage" disabled>Next</button>
                    </div>
                </div>
            </section>

        </main>

        <!-- ================= EVENT DETAIL MODAL ================= -->
        <div class="modal-overlay" id="eventDetailModal" role="dialog" aria-modal="true">
            <div class="modal-box">
                <div class="modal-header">
                    <div class="modal-title-group">
                        <span class="modal-title">Compliance Audit Event</span>
                        <span class="modal-log-id" id="modalLogId">#0</span>
                    </div>
                    <button type="button" class="modal-close" onclick="closeEventModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="modal-grid">
                        <div class="grid-item">
                            <span class="grid-item-label">TIMESTAMP</span>
                            <span class="grid-item-val" id="modalTime">—</span>
                        </div>
                        <div class="grid-item">
                            <span class="grid-item-label">ACTOR & ROLE</span>
                            <span class="grid-item-val" id="modalActor">—</span>
                        </div>
                        <div class="grid-item">
                            <span class="grid-item-label">ACTION PERFORMED</span>
                            <span class="grid-item-val" id="modalAction">—</span>
                        </div>
                        <div class="grid-item">
                            <span class="grid-item-label">TARGET VERSION</span>
                            <span class="grid-item-val" id="modalVersion">—</span>
                        </div>
                        <div class="grid-item">
                            <span class="grid-item-label">STATE TRANSITION</span>
                            <span class="grid-item-val" id="modalTransition">—</span>
                        </div>
                        <div class="grid-item">
                            <span class="grid-item-label">AI ENGINE VERSION</span>
                            <span class="grid-item-val" id="modalAiEngine">Gemini 1.5 Pro</span>
                        </div>
                    </div>

                    <div class="notes-box">
                        <div class="notes-label">Action Notes & Feedback Justification:</div>
                        <div class="notes-text" id="modalNotes">No notes provided for this event.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-close" onclick="closeEventModal()">Close</button>
                    <a href="#" class="btn-modal-action" id="modalReviewLink" style="display: none;">
                        <span>Open in Review Workspace</span>
                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor">
                            <path d="M5.25 2.9165L9.33333 6.99984L5.25 11.0832" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        <!-- ================= REALTIME FILTER & PAGINATION SCRIPT ================= -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const searchInput   = document.getElementById('searchAuditInput');
                const filterVersion = document.getElementById('filterVersion');
                const filterAction  = document.getElementById('filterAction');
                const filterActor   = document.getElementById('filterActor');
                const btnClear      = document.getElementById('btnClearFilters');
                const visibleCount  = document.getElementById('visibleCount');
                const totalCount    = document.getElementById('totalCount');
                const rows          = Array.from(document.querySelectorAll('#auditTableBody tr.event-row'));
                
                const btnPrev       = document.getElementById('btnPrevPage');
                const btnNext       = document.getElementById('btnNextPage');
                const pageNumEl     = document.getElementById('currentPageNum');

                const PAGE_SIZE = 12;
                let currentPage = 1;
                let filteredRows = [...rows];

                function applyFilters() {
                    const q = (searchInput ? searchInput.value : '').toLowerCase().trim();
                    const v = (filterVersion ? filterVersion.value : '');
                    const a = (filterAction ? filterAction.value : '');
                    const u = (filterActor ? filterActor.value : '');

                    filteredRows = rows.filter(row => {
                        const rVersion = row.getAttribute('data-version') || '';
                        const rAction  = row.getAttribute('data-action') || '';
                        const rActor   = row.getAttribute('data-actor') || '';
                        const rSearch  = row.getAttribute('data-search') || '';

                        const matchVersion = !v || rVersion === v;
                        const matchAction  = !a || rAction === a;
                        const matchActor   = !u || rActor === u;
                        const matchQuery   = !q || rSearch.includes(q);

                        return matchVersion && matchAction && matchActor && matchQuery;
                    });

                    currentPage = 1;
                    renderPagination();
                }

                function renderPagination() {
                    const total = filteredRows.length;
                    if (visibleCount) visibleCount.textContent = total;

                    const totalPages = Math.ceil(total / PAGE_SIZE) || 1;
                    if (currentPage > totalPages) currentPage = totalPages;

                    const start = (currentPage - 1) * PAGE_SIZE;
                    const end = start + PAGE_SIZE;

                    rows.forEach(r => r.style.display = 'none');

                    filteredRows.slice(start, end).forEach(r => {
                        r.style.display = '';
                    });

                    if (pageNumEl) pageNumEl.textContent = currentPage;
                    if (btnPrev) btnPrev.disabled = (currentPage <= 1);
                    if (btnNext) btnNext.disabled = (currentPage >= totalPages);
                }

                if (searchInput) searchInput.addEventListener('input', applyFilters);
                if (filterVersion) filterVersion.addEventListener('change', applyFilters);
                if (filterAction) filterAction.addEventListener('change', applyFilters);
                if (filterActor) filterActor.addEventListener('change', applyFilters);

                if (btnClear) {
                    btnClear.addEventListener('click', function() {
                        if (searchInput) searchInput.value = '';
                        if (filterVersion) filterVersion.value = '';
                        if (filterAction) filterAction.value = '';
                        if (filterActor) filterActor.value = '';
                        applyFilters();
                    });
                }

                if (btnPrev) {
                    btnPrev.addEventListener('click', function() {
                        if (currentPage > 1) {
                            currentPage--;
                            renderPagination();
                        }
                    });
                }

                if (btnNext) {
                    btnNext.addEventListener('click', function() {
                        const totalPages = Math.ceil(filteredRows.length / PAGE_SIZE) || 1;
                        if (currentPage < totalPages) {
                            currentPage++;
                            renderPagination();
                        }
                    });
                }

                // Initial render
                applyFilters();
            });

            // Modal Handlers
            function openEventModal(ev) {
                if (!ev) return;
                const modal = document.getElementById('eventDetailModal');
                if (!modal) return;

                document.getElementById('modalLogId').textContent = '#' + (ev.id || '0');
                document.getElementById('modalTime').textContent = ev.timestamp || '—';
                document.getElementById('modalActor').textContent = (ev.actor_name || 'System') + ' (' + (ev.actor_role || 'SYSTEM') + ')';
                document.getElementById('modalAction').textContent = ev.action_title || '—';
                document.getElementById('modalVersion').textContent = ev.version_label || '—';
                
                const oldSt = ev.old_status ? ev.old_status.toUpperCase() : 'INITIAL';
                const newSt = ev.new_status ? ev.new_status.toUpperCase() : (ev.outcome_state || 'COMPLETED');
                document.getElementById('modalTransition').textContent = oldSt + ' → ' + newSt;
                
                const aiModel = ev.model_version || (ev.prompt_version ? 'v' + ev.prompt_version : 'Gemini 1.5 Pro');
                document.getElementById('modalAiEngine').textContent = aiModel;

                const notes = ev.note || ev.action_subtitle || 'No additional audit notes recorded for this event.';
                document.getElementById('modalNotes').textContent = notes;

                const reviewLink = document.getElementById('modalReviewLink');
                if (reviewLink && ev.asset_id && ev.version_id) {
                    reviewLink.style.display = 'inline-flex';
                    reviewLink.href = 'checker-review.php?campaign_id=<?= (int)$campaignId ?>&asset_id=' + ev.asset_id + '&version_id=' + ev.version_id;
                } else if (reviewLink) {
                    reviewLink.style.display = 'none';
                }

                modal.classList.add('active');
            }

            function closeEventModal() {
                const modal = document.getElementById('eventDetailModal');
                if (modal) modal.classList.remove('active');
            }

            // Close modal on escape or background click
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeEventModal();
            });
            document.addEventListener('click', function(e) {
                const modal = document.getElementById('eventDetailModal');
                if (modal && e.target === modal) closeEventModal();
            });
        </script>
    <?php endif; ?>

</body>
</html>

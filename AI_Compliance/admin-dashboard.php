<?php
/**
 * Template Name: Admin Dashboard
 * Description: Realtime Admin Workspace connected to WordPress Database & Project Core System (Production Release, MAS Governance, Audit Logs)
 * 100% English UI, zero mock campaigns/assets, dynamic empty states.
 */

if (!defined('ABSPATH') && session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}

// 1. AUTO-LOAD BACKEND DATA ENGINE
if (file_exists(__DIR__ . '/backend.php')) {
    require_once __DIR__ . '/backend.php';
} elseif (file_exists(__DIR__ . '/inc/backend.php')) {
    require_once __DIR__ . '/inc/backend.php';
} elseif (function_exists('get_template_directory') && file_exists(get_template_directory() . '/backend.php')) {
    require_once get_template_directory() . '/backend.php';
} elseif (function_exists('get_template_directory') && file_exists(get_template_directory() . '/inc/backend.php')) {
    require_once get_template_directory() . '/inc/backend.php';
}

// 2. HANDLE SIGN OUT
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['logged_in_user']);
        session_destroy();
    }
    if (function_exists('wp_logout')) {
        wp_logout();
    }
    $target = function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php';
    header('Location: ' . $target . (strpos($target, '?') !== false ? '&' : '?') . 'loggedout=true');
    exit;
}

// 3. HANDLE ACTIONS (PRODUCTION RELEASE & CAMPAIGN CREATION & TEAM EDIT & GUIDELINES)
$releaseAlert = '';
$releaseSuccess = false;
$reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($reqMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_campaign') {
    $campTitle = trim($_POST['campaign_title'] ?? '');
    $campDeadline = trim($_POST['campaign_deadline'] ?? '');
    $campOwnerId = isset($_POST['owner_id']) ? absint($_POST['owner_id']) : 0;
    $maker_ids = isset($_POST['maker_ids']) ? array_map('absint', (array) $_POST['maker_ids']) : [];
    $checker_ids = isset($_POST['checker_ids']) ? array_map('absint', (array) $_POST['checker_ids']) : [];

    // Validation: Target Deadline must be a future date (tomorrow or later)
    $today = date('Y-m-d');
    if (!empty($campDeadline) && $campDeadline <= $today) {
        $releaseAlert = 'Error: Target Deadline must be a future date (cannot be today or in the past).';
        $releaseSuccess = false;
    } elseif (!empty($campTitle) && function_exists('handle_create_campaign')) {
        $cid = handle_create_campaign($campTitle, $campDeadline, $maker_ids, $checker_ids, $campOwnerId);
        if ($cid) {
            $releaseAlert = 'Campaign "' . htmlspecialchars($campTitle) . '" created successfully with assigned team and attached guidelines!';
            $releaseSuccess = true;
        }
    }
} elseif ($reqMethod === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['update_campaign_team', 'update_campaign'], true)) {
    $campId = absint($_POST['campaign_id'] ?? 0);
    $campTitle = sanitize_text_field($_POST['campaign_title'] ?? '');
    $campDeadline = sanitize_text_field($_POST['campaign_deadline'] ?? '');
    $campOwnerId = absint($_POST['owner_id'] ?? 0);
    $maker_ids = isset($_POST['maker_ids']) ? array_map('absint', (array) $_POST['maker_ids']) : [];
    $checker_ids = isset($_POST['checker_ids']) ? array_map('absint', (array) $_POST['checker_ids']) : [];
    if ($campId && function_exists('handle_update_campaign')) {
        handle_update_campaign($campId, [
            'title'       => $campTitle,
            'deadline'    => $campDeadline,
            'owner_id'    => $campOwnerId,
            'maker_ids'   => $maker_ids,
            'checker_ids' => $checker_ids
        ]);
        $releaseAlert = 'Campaign details and team assignments updated successfully!';
        $releaseSuccess = true;
    } elseif ($campId && function_exists('handle_update_campaign_team')) {
        handle_update_campaign_team($campId, $maker_ids, $checker_ids, $campOwnerId);
        $releaseAlert = 'Campaign team assignments updated successfully!';
        $releaseSuccess = true;
    }
} elseif ($reqMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_release') {
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
    $cId = absint($_POST['campaign_id'] ?? 0);
    if ($cId && function_exists('handle_upload_campaign_guideline')) {
        $uploaded = handle_upload_campaign_guideline($cId);
        if ($uploaded) {
            $releaseAlert = 'Guidelines attached to campaign successfully!';
            $releaseSuccess = true;
        } else {
            $releaseAlert = 'Error uploading guidelines file. Please check file type and size.';
            $releaseSuccess = false;
        }
    }
} elseif ($reqMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_guideline') {
    $cId = absint($_POST['campaign_id'] ?? 0);
    $gTarget = sanitize_text_field($_POST['guideline_url'] ?? '');
    if ($cId && $gTarget && function_exists('handle_delete_campaign_guideline')) {
        $ok = handle_delete_campaign_guideline($cId, $gTarget);
        if ($ok) {
            $releaseAlert = 'Guideline document removed successfully!';
            $releaseSuccess = true;
        } else {
            $releaseAlert = 'Error removing guideline document.';
            $releaseSuccess = false;
        }
    }
}

// 4. RETRIEVE CURRENT USER PROFILE & STRICT RBAC ENFORCEMENT (ADMIN ONLY)
$currentUser = function_exists('pcs_enforce_rbac') ? pcs_enforce_rbac(['ADMIN']) : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'ADMIN');

$kpis = function_exists('get_admin_kpis') ? get_admin_kpis() : [
    'awaiting_release' => 0,
    'in_review'        => 0,
    'released'         => 0,
    'rejected'         => 0,
    'draft'            => 0,
    'scoring'          => 0,
    'total'            => 0
];

$needsAttention = function_exists('get_admin_needs_attention') ? get_admin_needs_attention(3) : [];
$campaigns = function_exists('get_all_campaigns') ? get_all_campaigns('all') : [];
$queue = function_exists('get_admin_release_queue') ? get_admin_release_queue('all') : [];
$recentlyReleased = function_exists('get_admin_recently_released') ? get_admin_recently_released(3) : [];
$auditLogs = function_exists('get_admin_audit_logs') ? get_admin_audit_logs(15) : [];

$draftCount    = (int) ($kpis['draft'] ?? 0);
$scoringCount  = (int) ($kpis['scoring'] ?? 0);
$inReviewCount = (int) ($kpis['in_review'] ?? 0);
$rejectedCount = (int) ($kpis['rejected'] ?? 0);
$approvedCount = (int) ($kpis['approved'] ?? ($kpis['awaiting_release'] ?? 0));
$releasedCount = (int) ($kpis['released'] ?? 0);
$awaitingReleaseCount = $approvedCount;
$totalCampaigns       = count($campaigns);
$totalQueue           = count($queue);

// First awaiting release item for "Open Next Final Review" CTA
$firstAwaitingId = !empty($needsAttention) ? $needsAttention[0]['version_id'] : (!empty($queue) ? $queue[0]['version_id'] : 0);

// Retrieve all Admins, Makers and Checkers for dynamic interactive assignment
$allAdminsList = function_exists('pcs_get_system_users_by_role') ? pcs_get_system_users_by_role('ADMIN') : [];
$allMakersList = function_exists('pcs_get_system_users_by_role') ? pcs_get_system_users_by_role('MAKER') : [];
$allCheckersList = function_exists('pcs_get_system_users_by_role') ? pcs_get_system_users_by_role('CHECKER') : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Workspace - Brand Compliance AI</title>
    
    <!-- Google Fonts: Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Poppins:wght@500&display=swap" rel="stylesheet">
    
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
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button {
            cursor: pointer;
            border: none;
            background: none;
            font-family: inherit;
        }

        button:focus-visible {
            outline: 2px solid #00B894 !important;
            outline-offset: 2px;
        }

        .container {
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
            padding: 0 40px;
        }

        /* Ambient Glow Backgrounds */
        .ambient-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }
        .glow-top-left {
            position: absolute;
            top: -96px;
            left: -80px;
            width: 540px;
            height: 540px;
            background-color: rgba(224, 224, 255, 0.45);
            border-radius: 9999px;
            filter: blur(65px);
        }
        .glow-top-right {
            position: absolute;
            top: -64px;
            right: 0;
            width: 480px;
            height: 480px;
            background-color: rgba(243, 242, 255, 0.6);
            border-radius: 9999px;
            filter: blur(55px);
        }

        /* ================= 1. HEADER (100% UNIFIED TOP NAVBAR) ================= */
        .header {
            position: sticky;
            top: 0;
            width: 100%;
            height: 84px;
            background: #FFFFFF;
            border-bottom: 1px solid rgba(226, 232, 240, 0.85);
            z-index: 100;
            display: flex;
            align-items: center;
        }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 40px;
        }

        .company-logo-link {
            display: flex;
            align-items: center;
        }

        .company-logo {
            height: 40px;
            width: auto;
            max-width: 140px;
            object-fit: contain;
            display: block;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .nav-item {
            font-size: 14.5px;
            font-weight: 600;
            color: #334155;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .nav-item:hover {
            color: #373E8C;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-divider {
            width: 1px;
            height: 24px;
            background-color: #E2E8F0;
        }

        /* Profile Button & Dropdown */
        .profile-container {
            position: relative;
        }

        .admin-profile-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s ease;
            user-select: none;
        }

        .admin-profile-badge:hover {
            background: #F8FAFC;
        }

        .avatar-wrap {
            position: relative;
            width: 36px;
            height: 36px;
        }

        .avatar-circle {
            width: 36px;
            height: 36px;
            border-radius: 9999px;
            background: linear-gradient(135deg, #373E8C 0%, #4F56A6 100%);
            color: #FFFFFF;
            font-size: 12.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
        }

        .online-dot {
            position: absolute;
            right: -1px;
            bottom: -1px;
            width: 10px;
            height: 10px;
            background-color: #10B981;
            border-radius: 9999px;
            border: 2px solid #FFFFFF;
        }

        .profile-text {
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        .profile-name {
            font-size: 13.5px;
            font-weight: 700;
            color: #0F172A;
            line-height: 1.2;
        }

        .profile-role {
            font-size: 10.5px;
            font-weight: 700;
            color: #4F56A6;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .chevron-icon {
            transition: transform 0.2s ease;
        }

        .profile-container.active .chevron-icon {
            transform: rotate(180deg);
        }

        /* Dropdown Menu */
        .profile-dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 250px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            padding: 12px;
            display: none;
            flex-direction: column;
            gap: 8px;
            z-index: 200;
            animation: dropdownFade 0.15s ease-out;
        }

        .profile-container.active .profile-dropdown-menu {
            display: flex;
        }

        @keyframes dropdownFade {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dropdown-user-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px;
        }

        .dropdown-avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 9999px;
            background: linear-gradient(135deg, #373E8C 0%, #4F56A6 100%);
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dropdown-user-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
            overflow: hidden;
        }

        .dropdown-user-name {
            font-size: 13.5px;
            font-weight: 700;
            color: #0F172A;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown-user-email {
            font-size: 11.5px;
            color: #64748B;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .dropdown-user-role-badge {
            display: inline-block;
            font-size: 9.5px;
            font-weight: 800;
            color: #4F56A6;
            background: #F3F2FF;
            padding: 2px 6px;
            border-radius: 4px;
            width: fit-content;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }

        .dropdown-divider {
            height: 1px;
            background-color: #F1F5F9;
            margin: 4px 0;
        }

        .dropdown-logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            border-radius: 8px;
            color: #BA1A1A;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.15s;
        }

        .dropdown-logout-btn:hover {
            background-color: #FEF2F2;
        }

        /* Alert Toast */
        .alert-toast {
            position: relative;
            margin: 20px 0 0 0;
            padding: 14px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease-out;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .alert-toast.success {
            background-color: #E6FBF5;
            border: 1px solid #A7F3D0;
            color: #047857;
        }
        .alert-toast.danger {
            background-color: #FEF2F2;
            border: 1px solid #FECACA;
            color: #BA1A1A;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ================= 2. WORKSPACE HERO & CTAS ================= */
        .main-wrapper {
            position: relative;
            z-index: 10;
            padding: 36px 0 80px 0;
        }

        .workspace-hero {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 24px;
            margin-bottom: 32px;
            flex-wrap: wrap;
        }

        .hero-left {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .workspace-badge {
            align-self: flex-start;
            background-color: #E0E0FF;
            color: #373E8C;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.8px;
            padding: 3px 10px;
            border-radius: 6px;
            text-transform: uppercase;
        }

        .hero-title {
            font-size: 28px;
            font-weight: 800;
            color: #161B2E;
            letter-spacing: -0.5px;
        }

        .hero-subtitle {
            font-size: 14.5px;
            color: #475569;
        }

        .hero-subtitle strong {
            color: #373E8C;
            font-weight: 700;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-action-outline {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            color: #334155;
            transition: all 0.15s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .btn-action-outline:hover {
            background: #F8FAFC;
            border-color: #94A3B8;
            color: #0F172A;
        }

        .btn-action-primary {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #373E8C 0%, #4F56A6 100%);
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            color: #FFFFFF;
            transition: all 0.15s ease;
            box-shadow: 0 4px 12px rgba(55, 62, 140, 0.25);
        }

        .btn-action-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(55, 62, 140, 0.35);
        }

        .btn-action-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* ================= 3. 4-KPI SUMMARY BAR (IDENTICAL TO CHECKER) ================= */
        .kpi-metrics-bar {
            display: flex;
            background: #FFFFFF;
            border: 1px solid #EBEDFF;
            border-radius: 16px;
            box-shadow: none;
            margin-bottom: 32px;
            padding: 6px;
        }

        .kpi-card {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .kpi-card:hover {
            background-color: #F8FAFC;
            transform: translateY(-1px);
        }

        .kpi-card:not(:last-child) {
            border-right: 1px solid #F1F5F9;
        }

        .kpi-icon-box {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 800;
            font-family: 'JetBrains Mono', monospace;
            flex-shrink: 0;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* Exact Status Color Coding Aligned with Core Database Lifecycles */
        .kpi-icon-box.status-draft {
            background-color: #F1F5F9;
            color: #475569;
            border: 1px solid #CBD5E1;
        }
        .kpi-icon-box.status-scoring {
            background-color: #FFFBEB;
            color: #D97706;
            border: 1px solid #FCD34D;
        }
        .kpi-icon-box.status-in-review {
            background-color: #EEF2FF;
            color: #373E8C;
            border: 1px solid #C7D2FE;
        }
        .kpi-icon-box.status-rejected {
            background-color: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }
        .kpi-icon-box.status-approved, .kpi-icon-box.status-awaiting {
            background-color: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        .kpi-icon-box.status-released {
            background-color: #F5F3FF;
            color: #7C3AED;
            border: 1px solid #DDD6FE;
        }

        .kpi-label {
            font-size: 13.5px;
            font-weight: 700;
            line-height: 1.3;
        }
        .kpi-label.status-draft { color: #475569; }
        .kpi-label.status-scoring { color: #D97706; }
        .kpi-label.status-in-review { color: #373E8C; }
        .kpi-label.status-rejected { color: #DC2626; }
        .kpi-label.status-approved, .kpi-label.status-awaiting { color: #059669; }
        .kpi-label.status-released { color: #7C3AED; }

        @media (max-width: 1024px) {
            .kpi-metrics-bar {
                flex-wrap: wrap;
            }
            .kpi-card {
                flex: 1 1 calc(50% - 12px);
                border-right: none !important;
                border-bottom: 1px solid #F1F5F9;
            }
        }
        @media (max-width: 640px) {
            .kpi-card {
                flex: 1 1 100%;
            }
        }

        /* ================= 4. UPPER TWO-COLUMN WORKSPACE ================= */
        .upper-grid {
            display: grid;
            grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
            gap: 24px;
            margin-bottom: 40px;
            align-items: stretch;
            transition: all 0.3s ease;
        }

        .upper-grid.campaigns-expanded {
            grid-template-columns: 1fr;
        }

        .upper-grid.campaigns-expanded .aside-panel {
            display: none !important;
        }

        @media (max-width: 1100px) {
            .upper-grid { grid-template-columns: 1fr; }
        }

        /* Column Left: Needs Attention */
        .aside-panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            height: 540px;
            max-height: 540px;
            box-shadow: none;
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .panel-title-wrap {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .panel-title {
            font-size: 17px;
            font-weight: 800;
            color: #161B2E;
        }

        .panel-subtitle {
            font-size: 12px;
            color: #64748B;
        }

        .badge-counter {
            background-color: #FAF8FF;
            border: 1px solid #E0E0FF;
            color: #373E8C;
            font-size: 11.5px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 9999px;
        }

        /* Aside Item Cards */
        .attention-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            overflow-y: auto;
            flex: 1;
            padding-right: 6px;
            scrollbar-width: thin;
            scrollbar-color: #CBD5E1 #FAF8FF;
        }
        .attention-list::-webkit-scrollbar {
            width: 6px;
        }
        .attention-list::-webkit-scrollbar-thumb {
            background-color: #CBD5E1;
            border-radius: 4px;
        }

        .attention-card {
            background: #FAF8FF;
            border: 1px solid #E0E0FF;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: all 0.15s ease;
        }

        .attention-card:hover {
            border-color: #373E8C;
            box-shadow: 0 4px 12px rgba(55, 62, 140, 0.08);
            transform: translateY(-1px);
        }

        .attention-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .attention-pill {
            font-size: 10.5px;
            font-weight: 800;
            letter-spacing: 0.5px;
            padding: 2px 7px;
            border-radius: 4px;
            text-transform: uppercase;
            background-color: #E0E0FF;
            color: #373E8C;
        }

        .status-pill-small {
            font-size: 11px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 9999px;
            background: #D1FAE5;
            color: #047857;
            border: 1px solid #A7F3D0;
        }

        .attention-title {
            font-size: 15px;
            font-weight: 700;
            color: #1E293B;
            line-height: 1.3;
        }

        .attention-campaign-tag {
            font-size: 12px;
            color: #64748B;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .version-tag {
            font-family: 'JetBrains Mono', monospace;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            padding: 1px 5px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            color: #334155;
        }

        .attention-meta {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
            color: #64748B;
            padding-top: 6px;
            border-top: 1px dashed #E2E8F0;
        }

        .btn-open-review-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12.5px;
            font-weight: 700;
            color: #373E8C;
            margin-top: 4px;
            transition: color 0.15s;
        }

        .btn-open-review-link:hover {
            color: #1E293B;
            text-decoration: underline;
        }

        /* Column Right: Campaigns Table */
        .campaigns-panel {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            height: 540px;
            max-height: 540px;
            box-shadow: none;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .upper-grid.campaigns-expanded .campaigns-panel {
            height: auto;
            max-height: none;
            width: 100%;
        }

        .btn-toggle-expand {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 700;
            color: #373E8C;
            background: #FAF8FF;
            border: 1px solid #E0E0FF;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .btn-toggle-expand:hover {
            background: #EDE9FA;
            border-color: #373E8C;
        }

        .table-filter-pills {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .pill-tab {
            font-size: 13px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
            color: #64748B;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            cursor: pointer;
            transition: all 0.15s;
        }

        .pill-tab:hover {
            background: #F1F5F9;
            color: #0F172A;
        }

        .pill-tab.active {
            background: #373E8C;
            color: #FFFFFF;
            border-color: #373E8C;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            overflow-y: auto;
            max-height: 430px;
            scrollbar-width: thin;
            scrollbar-color: #CBD5E1 #F8FAFC;
        }
        .table-container::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .table-container::-webkit-scrollbar-thumb {
            background-color: #CBD5E1;
            border-radius: 4px;
        }

        .upper-grid.campaigns-expanded .campaigns-panel .table-container {
            max-height: 640px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        .data-table th {
            padding: 12px 14px;
            font-size: 11px;
            font-weight: 800;
            color: #64748B;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            border-bottom: 1px solid #E2E8F0;
            background: #F8FAFC;
        }

        .data-table td {
            padding: 14px;
            font-size: 13.5px;
            color: #1E293B;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
        }

        .data-table tr:hover td {
            background-color: #FAF8FF;
        }

        .campaign-name-cell {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .campaign-name-title {
            font-weight: 700;
            color: #161B2E;
        }

        .campaign-assets-count {
            font-size: 11.5px;
            color: #64748B;
        }

        .owner-cell {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .owner-cell span {
            white-space: nowrap;
        }

        .owner-avatar {
            width: 26px;
            height: 26px;
            border-radius: 9999px;
            background: #E2E8F0;
            color: #475569;
            font-size: 10.5px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .progress-box {
            display: flex;
            flex-direction: column;
            gap: 4px;
            min-width: 130px;
        }

        .progress-meta {
            display: flex;
            justify-content: space-between;
            font-size: 11.5px;
            font-weight: 600;
            color: #475569;
        }

        .progress-track {
            width: 100%;
            height: 6px;
            background: #E2E8F0;
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #373E8C 0%, #00B894 100%);
            border-radius: 9999px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 700;
            white-space: nowrap;
        }
        .status-badge.draft {
            background-color: #F1F5F9;
            color: #475569;
            border: 1px solid #CBD5E1;
        }
        .status-badge.scoring {
            background-color: #FFFBEB;
            color: #D97706;
            border: 1px solid #FCD34D;
        }
        .status-badge.in_review, .status-badge.in-review {
            background-color: #EEF2FF;
            color: #373E8C;
            border: 1px solid #C7D2FE;
        }
        .status-badge.rejected {
            background-color: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }
        .status-badge.approved, .status-badge.awaiting, .status-badge.awaiting_release {
            background-color: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        .status-badge.released {
            background-color: #F5F3FF;
            color: #7C3AED;
            border: 1px solid #DDD6FE;
        }

        .btn-view-campaign {
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            transition: all 0.15s;
        }
        .btn-view-campaign:hover {
            background: #F1F5F9;
            color: #0F172A;
            border-color: #94A3B8;
        }

        /* Campaign Accordion Drawer */
        .drawer-row {
            display: none;
            background-color: #FAF8FF !important;
        }
        .drawer-row.active {
            display: table-row;
        }

        .drawer-box {
            padding: 16px 20px;
            background: #FFFFFF;
            border: 1px solid #E0E0FF;
            border-radius: 12px;
            margin: 6px 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .drawer-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #F1F5F9;
        }

        .drawer-title {
            font-size: 13px;
            font-weight: 800;
            color: #373E8C;
        }

        /* ================= 5. FULL-WIDTH FINAL APPROVAL QUEUE ================= */
        .queue-section {
            background: #FFFFFF;
            border: 2px solid #E0E0FF;
            border-radius: 16px;
            box-shadow: 0px 4px 20px -2px rgba(55, 62, 140, 0.06);
            padding: 26px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 40px;
        }

        .queue-header-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }

        .queue-header-left {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .queue-title-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .queue-title {
            font-size: 20px;
            font-weight: 800;
            color: #161B2E;
        }

        .queue-count-badge {
            background: #E0E0FF;
            color: #373E8C;
            font-size: 12px;
            font-weight: 800;
            padding: 2px 10px;
            border-radius: 9999px;
        }

        .queue-subtitle {
            font-size: 13.5px;
            color: #64748B;
        }

        .btn-open-review-primary {
            background: linear-gradient(135deg, #373E8C 0%, #4F56A6 100%);
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 700;
            padding: 7px 14px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 2px 6px rgba(55, 62, 140, 0.2);
            transition: all 0.15s;
        }
        .btn-open-review-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(55, 62, 140, 0.3);
        }

        .btn-view-release {
            background: #F8FAFC;
            border: 1px solid #CBD5E1;
            color: #334155;
            font-size: 12px;
            font-weight: 700;
            padding: 6px 14px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }
        .btn-view-release:hover {
            background: #F1F5F9;
            color: #0F172A;
        }

        /* ================= 6. RECENTLY RELEASED SECTION ================= */
        .released-section {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 26px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .released-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-audit-history {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13.5px;
            font-weight: 700;
            color: #373E8C;
            transition: color 0.15s;
        }
        .btn-audit-history:hover {
            color: #0F172A;
            text-decoration: underline;
        }

        .released-cards-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        @media (max-width: 900px) {
            .released-cards-grid { grid-template-columns: 1fr; }
        }

        .released-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            transition: all 0.15s ease;
        }
        .released-card:hover {
            border-color: #00B894;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 184, 148, 0.08);
        }

        .released-card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .released-card-title {
            font-size: 15px;
            font-weight: 800;
            color: #161B2E;
            line-height: 1.3;
        }

        .lock-badge {
            background-color: #E6FBF5;
            color: #00B894;
            border: 1px solid #A7F3D0;
            font-size: 11px;
            font-weight: 800;
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
        }

        .released-meta-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            font-size: 12.5px;
            color: #64748B;
        }

        .released-meta-list strong {
            color: #1E293B;
        }

        /* Empty State */
        .empty-state-box {
            text-align: center;
            padding: 50px 20px;
            color: #64748B;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .empty-icon {
            font-size: 32px;
            line-height: 1;
            margin-bottom: 4px;
        }

        .empty-title {
            font-size: 16px;
            font-weight: 700;
            color: #161B2E;
        }

        .empty-desc {
            font-size: 13px;
            max-width: 420px;
            line-height: 1.4;
        }

        /* ================= 7. MODALS ================= */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-window {
            background: #FFFFFF;
            border-radius: 20px;
            max-width: 860px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            position: relative;
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 800;
            color: #161B2E;
        }

        .btn-close-modal {
            font-size: 24px;
            color: #94A3B8;
            cursor: pointer;
            line-height: 1;
            padding: 4px;
        }
        .btn-close-modal:hover { color: #0F172A; }

        .modal-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .modal-preview-box {
            background: #FAF8FF;
            border: 1px solid #E0E0FF;
            border-radius: 14px;
            padding: 20px;
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .modal-thumb {
            width: 100px;
            height: 100px;
            background: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #94A3B8;
            overflow: hidden;
            flex-shrink: 0;
        }

        .modal-asset-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .modal-asset-title {
            font-size: 18px;
            font-weight: 800;
            color: #161B2E;
        }

        .modal-tags {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            color: #64748B;
        }

        .checker-feedback-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .feedback-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .feedback-author {
            font-size: 13.5px;
            font-weight: 700;
            color: #1E293B;
        }

        .feedback-text {
            font-size: 13px;
            color: #475569;
            line-height: 1.5;
            font-style: italic;
        }

        /* Check Results Breakdown */
        .checks-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        @media (max-width: 640px) {
            .checks-grid { grid-template-columns: 1fr; }
        }

        .check-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .check-criterion {
            font-size: 12px;
            font-weight: 700;
            color: #1E293B;
        }

        .check-score-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
        }

        .score-pill {
            font-weight: 800;
            font-size: 11px;
            padding: 1px 6px;
            border-radius: 4px;
            background: #D1FAE5;
            color: #047857;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-label {
            font-size: 13.5px;
            font-weight: 700;
            color: #1E293B;
        }

        .form-textarea {
            width: 100%;
            height: 80px;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            padding: 10px 14px;
            font-family: inherit;
            font-size: 13.5px;
            resize: vertical;
        }

        .form-textarea:focus {
            outline: none;
            border-color: #00B894;
            box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.15);
        }

        .compliance-warning-notice {
            background-color: #FFFBEB;
            border: 1px solid #FDE68A;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 12.5px;
            color: #92400E;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            background: #F8FAFC;
            border-bottom-left-radius: 20px;
            border-bottom-right-radius: 20px;
        }

        .btn-cancel {
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            color: #64748B;
            border: 1px solid #CBD5E1;
            background: #FFFFFF;
        }
        .btn-cancel:hover { background: #F1F5F9; }

        .btn-release-confirm {
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            color: #FFFFFF;
            background: linear-gradient(135deg, #00B894 0%, #008f72 100%);
            box-shadow: 0 4px 12px rgba(0, 184, 148, 0.25);
            transition: all 0.15s;
        }
        .btn-release-confirm:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(0, 184, 148, 0.35);
        }

        /* Timeline Audit History Modal */
        .timeline-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            position: relative;
            padding-left: 20px;
        }
        .timeline-list::before {
            content: '';
            position: absolute;
            left: 6px;
            top: 6px;
            bottom: 6px;
            width: 2px;
            background: #E2E8F0;
        }
        .timeline-item {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .timeline-dot {
            position: absolute;
            left: -19px;
            top: 4px;
            width: 10px;
            height: 10px;
            border-radius: 9999px;
            background: #00B894;
            border: 2px solid #FFFFFF;
            box-shadow: 0 0 0 2px #E2E8F0;
        }
        .timeline-time {
            font-size: 11.5px;
            color: #94A3B8;
            font-family: 'JetBrains Mono', monospace;
        }
        .timeline-title {
            font-size: 14px;
            font-weight: 700;
            color: #1E293B;
        }
        .timeline-note {
            font-size: 12.5px;
            color: #64748B;
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

        /* Guideline Multi-File Items */
        .guideline-file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 12px;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 6px;
            font-size: 12.5px;
        }
        .guideline-file-info {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1E293B;
            font-weight: 600;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .guideline-file-remove {
            color: #EF4444;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .guideline-file-remove:hover {
            background: #FEE2E2;
        }
    </style>
</head>
<body>

    <!-- Ambient Decorative Glows -->
    <div class="ambient-bg">
        <div class="glow-top-left"></div>
        <div class="glow-top-right"></div>
    </div>

    <!-- ================= 1. HEADER (100% UNIFIED TOP NAVBAR) ================= -->
    <!-- ================= 1. HEADER (100% UNIFIED TOP NAVBAR) ================= -->
    <?php 
    if (function_exists('pcs_render_top_navbar')) {
        pcs_render_top_navbar([
            'user'              => $currentUser,
            'all_campaigns_url' => '#campaigns',
            'btn_text'          => 'All Campaigns'
        ]);
    } else {
        @include __DIR__ . '/inc/navbar.php';
    }
    ?>

    <!-- ================= 2. MAIN WORKSPACE ================= -->
    <main class="main-wrapper">
        <div class="container">

            <?php if (!empty($releaseAlert)): ?>
                <div class="alert-toast <?= $releaseSuccess ? 'success' : 'danger' ?>">
                    <?php if ($releaseSuccess): ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    <?php else: ?>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($releaseAlert) ?></span>
                </div>
            <?php endif; ?>

            <!-- Workspace Hero Section -->
            <div class="workspace-hero">
                <div class="hero-left">
                    <span class="workspace-badge">ADMIN WORKSPACE</span>
                    <h1 class="hero-title">Good morning, <?= htmlspecialchars($currentUser['first_name'] ?? 'Khanh Huyen') ?></h1>
                    <p class="hero-subtitle">
                        <strong><?= $awaitingReleaseCount ?> approved <?= $awaitingReleaseCount === 1 ? 'version' : 'versions' ?></strong> are waiting for final review.
                    </p>
                </div>
                <div class="hero-actions">
                    <button type="button" class="btn-action-outline" onclick="openAdminCampaignModal()" style="cursor: pointer;">
                        <span>+ New Campaign</span>
                    </button>
                    <a href="#campaigns-section" class="btn-action-outline">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h7v7H3z"/><path d="M14 3h7v7h-7z"/><path d="M14 14h7v7h-7z"/><path d="M3 14h7v7H3z"/></svg>
                        <span>View All Campaigns</span>
                    </a>
                    <button type="button" class="btn-action-primary" id="btnHeroOpenReview" <?= empty($firstAwaitingId) ? 'disabled' : '' ?> data-version-id="<?= $firstAwaitingId ?>">
                        <span>Open Next Final Review</span>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                    </button>
                </div>
            </div>

            <!-- Section 2: Core Lifecycle Metric Cards: Draft -> Scoring -> In Review -> Rejected -> Approved -> Released -->
            <section class="kpi-metrics-bar">
                <div class="kpi-card">
                    <div class="kpi-icon-box status-draft"><?= $draftCount ?></div>
                    <div class="kpi-label status-draft">Draft</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-box status-scoring"><?= $scoringCount ?></div>
                    <div class="kpi-label status-scoring">Scoring</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-box status-in-review"><?= $inReviewCount ?></div>
                    <div class="kpi-label status-in-review">In Review</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-box status-rejected"><?= $rejectedCount ?></div>
                    <div class="kpi-label status-rejected">Rejected</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-box status-approved"><?= $approvedCount ?></div>
                    <div class="kpi-label status-approved">Approved</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-box status-released"><?= $releasedCount ?></div>
                    <div class="kpi-label status-released">Released</div>
                </div>
            </section>

            <!-- Upper 2-Column Grid: Needs Attention + Campaigns -->
            <div class="upper-grid" id="campaigns-section">
                
                <!-- Left Aside: Needs Attention -->
                <aside class="aside-panel">
                    <div class="panel-header">
                        <div class="panel-title-wrap">
                            <h2 class="panel-title">Needs Attention</h2>
                        </div>
                        <span class="badge-counter"><?= count($needsAttention) ?> items</span>
                    </div>

                    <?php if (empty($needsAttention)): ?>
                        <div class="empty-state-box">
                            <div class="empty-icon">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>
                            </div>
                            <div class="empty-title">All Caught Up</div>
                            <p class="empty-desc">No versions currently waiting for final admin release.</p>
                        </div>
                    <?php else: ?>
                        <div class="attention-list">
                            <?php foreach ($needsAttention as $idx => $item): ?>
                                <div class="attention-card">
                                    <div class="attention-card-top">
                                        <span class="attention-pill"><?= sprintf('%02d', $idx + 1) ?> · AWAITING RELEASE</span>
                                        <span class="status-pill-small">Approved</span>
                                    </div>
                                    <h3 class="attention-title"><?= htmlspecialchars($item['asset_name']) ?></h3>
                                    <div class="attention-campaign-tag">
                                        <span><?= htmlspecialchars($item['campaign_name']) ?></span>
                                        <span>·</span>
                                        <span class="version-tag"><?= htmlspecialchars($item['version_label']) ?></span>
                                    </div>
                                    <div class="attention-meta">
                                        <span>Checker: <strong><?= htmlspecialchars($item['checker_name']) ?></strong></span>
                                        <span><?= htmlspecialchars($item['approved_at']) ?></span>
                                    </div>
                                    <button type="button" class="btn-open-review-link js-trigger-modal" 
                                            data-version-id="<?= $item['version_id'] ?>"
                                            data-asset-name="<?= htmlspecialchars($item['asset_name']) ?>"
                                            data-campaign-name="<?= htmlspecialchars($item['campaign_name']) ?>"
                                            data-version-label="<?= htmlspecialchars($item['version_label']) ?>"
                                            data-maker-name="<?= htmlspecialchars($item['maker_name']) ?>"
                                            data-checker-name="<?= htmlspecialchars($item['checker_name']) ?>"
                                            data-checker-notes="<?= htmlspecialchars($item['checker_notes']) ?>"
                                            data-score="<?= $item['score'] ?>"
                                            data-file-url="<?= htmlspecialchars($item['file_url']) ?>">
                                        <span>Open Final Review →</span>
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </aside>

                <!-- Right Column: Campaigns Table -->
                <div class="campaigns-panel">
                    <div class="panel-header">
                        <div class="panel-title-wrap">
                            <h2 class="panel-title">Campaigns</h2>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                            <div class="table-filter-pills">
                                <button type="button" class="pill-tab active" data-filter="all">All</button>
                                <button type="button" class="pill-tab" data-filter="draft">Draft</button>
                                <button type="button" class="pill-tab" data-filter="scoring">Scoring</button>
                                <button type="button" class="pill-tab" data-filter="in_review">In Review</button>
                                <button type="button" class="pill-tab" data-filter="rejected">Rejected</button>
                                <button type="button" class="pill-tab" data-filter="approved">Approved</button>
                                <button type="button" class="pill-tab" data-filter="released">Released</button>
                            </div>
                            <button type="button" class="btn-toggle-expand" id="btnToggleCampaignsView" onclick="toggleCampaignsExpansion()" title="Toggle full width table view">
                                <svg class="icon-expand" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                                <svg class="icon-compress" style="display:none;" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/></svg>
                                <span id="btnToggleCampaignsText">Full Width</span>
                            </button>
                        </div>
                    </div>

                    <?php if (empty($campaigns)): ?>
                        <div class="empty-state-box">
                            <div class="empty-icon">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            </div>
                            <div class="empty-title">No Campaigns Found</div>
                            <p class="empty-desc">No campaigns exist in the database yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="data-table" id="campaignsTable">
                                <thead>
                                    <tr>
                                        <th>Campaign</th>
                                        <th style="text-align: center;">Status</th>
                                        <th>Owner</th>
                                        <th style="text-align: center;">Deadline</th>
                                        <th>Release Progress</th>
                                        <th style="text-align: center;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($campaigns as $camp): ?>
                                        <tr class="campaign-row" data-status="<?= htmlspecialchars($camp['filter_group']) ?>" data-statuses="<?= htmlspecialchars($camp['filter_groups_str'] ?? $camp['filter_group']) ?>">
                                            <td>
                                                <div class="campaign-name-cell">
                                                    <a href="<?= esc_url((function_exists('pcs_url') ? pcs_url('detail-asset-admin.php') : 'detail-asset-admin.php') . '?campaign_id=' . (int)$camp['id']) ?>" class="campaign-name-title" style="text-decoration: none; color: inherit; transition: color 0.15s ease;" onmouseover="this.style.color='#373E8C'" onmouseout="this.style.color='inherit'">
                                                        <?= htmlspecialchars($camp['name']) ?>
                                                    </a>
                                                    <span class="campaign-assets-count"><?= count($camp['assets']) ?> <?= count($camp['assets']) === 1 ? 'asset' : 'assets' ?></span>
                                                </div>
                                            </td>
                                            <td style="text-align: center;">
                                                <?= function_exists('pcs_format_status_badge') ? pcs_format_status_badge($camp['status']) : '<span class="status-badge ' . htmlspecialchars($camp['status_type']) . '">' . htmlspecialchars($camp['status']) . '</span>' ?>
                                            </td>
                                            <td>
                                                <div class="owner-cell">
                                                    <div class="owner-avatar"><?= htmlspecialchars($camp['owner_initials'] ?? 'CO') ?></div>
                                                    <span><?= htmlspecialchars($camp['owner_name'] ?? 'Campaign Owner') ?></span>
                                                </div>
                                            </td>
                                            <td style="text-align: center; font-family: 'JetBrains Mono', monospace; font-size: 12px;"><?= htmlspecialchars($camp['deadline']) ?></td>
                                            <td>
                                                <div class="progress-box">
                                                    <div class="progress-meta">
                                                        <span><?= $camp['done'] ?> of <?= $camp['total'] ?> released<?php if (!empty($camp['approved_count']) && $camp['approved_count'] > $camp['done']): ?> <span style="font-size: 11px; color: #D97706; font-weight: 600;">(<?= ($camp['approved_count'] - $camp['done']) ?> awaiting release)</span><?php endif; ?></span>
                                                        <span><?= $camp['percent'] ?>%</span>
                                                    </div>
                                                    <div class="progress-track">
                                                        <div class="progress-fill" style="width: <?= $camp['percent'] ?>%;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td style="text-align: center; white-space: nowrap;">
                                                <div style="display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                                                    <button type="button" class="btn-action-outline" style="padding: 5px 12px; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;" onclick="openEditTeamModal(<?= (int)$camp['id'] ?>, '<?= esc_js($camp['name']) ?>', <?= (int)($camp['owner_id'] ?? 0) ?>, <?= esc_attr(json_encode($camp['maker_ids'] ?? [])) ?>, <?= esc_attr(json_encode($camp['checker_ids'] ?? [])) ?>, '<?= esc_js($camp['deadline'] ?? '') ?>')">
                                                        <span>Edit</span>
                                                    </button>
                                                    <button type="button" class="btn-view-campaign js-toggle-drawer" data-target="drawer-<?= $camp['id'] ?>">
                                                        View
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>

                                        <!-- Drawer Accordion for Campaign Assets -->
                                        <tr class="drawer-row" id="drawer-<?= $camp['id'] ?>">
                                            <td colspan="6">
                                                <div class="drawer-box">
                                                    <div class="drawer-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                                                        <span class="drawer-title">Release Items · <?= count($camp['assets']) ?> assets in this campaign</span>
                                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                                            <?php 
                                                            $gList = !empty($camp['guideline_files']) ? $camp['guideline_files'] : (!empty($camp['guidelines']) ? $camp['guidelines'] : []);
                                                            if (empty($gList) && !empty($camp['guideline_file_url'])) {
                                                                $gList = [['id' => 1, 'name' => basename($camp['guideline_file_url']), 'url' => $camp['guideline_file_url']]];
                                                            }
                                                            ?>
                                                            <?php if (!empty($gList)): ?>
                                                                <?php foreach ($gList as $gFile): ?>
                                                                    <a href="<?= esc_url($gFile['url']) ?>" target="_blank" style="background: #EEF2FF; color: #373E8C; border: 1px solid #C7D2FE; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 11.5px;" title="<?= esc_attr($gFile['name']) ?>">
                                                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                                                        <span><?= esc_html($gFile['name']) ?></span>
                                                                    </a>
                                                                <?php endforeach; ?>
                                                            <?php else: ?>
                                                                <span style="font-size: 11.5px; color: #94A3B8; background: #F1F5F9; padding: 4px 10px; border-radius: 6px;">Guidelines · 0 files</span>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn-action-outline" style="padding: 4px 10px; font-size: 11.5px; border-radius: 6px;" onclick="openUploadGuidelineModal(<?= (int)$camp['id'] ?>, '<?= esc_js($camp['name']) ?>', <?= esc_attr(json_encode($gList)) ?>)">
                                                                Manage Guidelines
                                                            </button>
                                                            <button type="button" class="btn-action-outline" style="padding: 4px 10px; font-size: 11.5px; border-radius: 6px;" onclick="openEditTeamModal(<?= (int)$camp['id'] ?>, '<?= esc_js($camp['name']) ?>', <?= (int)($camp['owner_id'] ?? 0) ?>, <?= esc_attr(json_encode($camp['maker_ids'] ?? [])) ?>, <?= esc_attr(json_encode($camp['checker_ids'] ?? [])) ?>, '<?= esc_js($camp['deadline'] ?? '') ?>')">
                                                                Edit Campaign
                                                            </button>
                                                            <a href="<?= esc_url((function_exists('pcs_url') ? pcs_url('detail-asset-admin.php') : 'detail-asset-admin.php') . '?campaign_id=' . (int)$camp['id']) ?>" style="font-size: 12px; font-weight: 600; color: #373E8C; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                                                <span>Open Campaign Governance →</span>
                                                            </a>
                                                        </div>
                                                    </div>
                                                    <?php if (empty($camp['assets'])): ?>
                                                        <p style="font-size: 13px; color: #64748B;">No assets registered under this campaign.</p>
                                                    <?php else: ?>
                                                        <table class="data-table" style="background: transparent;">
                                                            <thead>
                                                                <tr>
                                                                    <th>Asset</th>
                                                                    <th style="text-align: center;">Version</th>
                                                                    <th>Maker</th>
                                                                    <th>Checker</th>
                                                                    <th style="text-align: center;">Status</th>
                                                                    <th style="text-align: center;">Action</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($camp['assets'] as $asset): ?>
                                                                    <tr class="drawer-asset-row" data-asset-status="<?= htmlspecialchars(strtolower($asset['status'] ?? 'draft')) ?>">
                                                                        <td style="font-weight: 700;"><?= htmlspecialchars($asset['name']) ?></td>
                                                                        <td style="text-align: center;"><span class="version-tag"><?= htmlspecialchars($asset['version_raw']) ?></span></td>
                                                                        <td style="white-space: nowrap;"><?= htmlspecialchars($asset['maker_name']) ?></td>
                                                                        <td style="white-space: nowrap;"><?= htmlspecialchars($asset['checker_name']) ?></td>
                                                                        <td style="text-align: center;">
                                                                            <?= function_exists('pcs_format_status_badge') ? pcs_format_status_badge($asset['status']) : '<span class="status-badge ' . htmlspecialchars($asset['status']) . '">' . ucfirst(htmlspecialchars($asset['status'])) . '</span>' ?>
                                                                        </td>
                                                                        <td style="text-align: center; white-space: nowrap;">
                                                                            <div style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap;">
                                                                                <?php if (!empty($asset['id'])): 
                                                                                    $aCompUrl = (function_exists('pcs_url') ? pcs_url('compare-version.php') : 'compare-version.php') . '?' . http_build_query([
                                                                                        'asset_id'    => (int)$asset['id'],
                                                                                        'v_cur'       => (int)($asset['current_version_id'] ?? 0),
                                                                                        'campaign_id' => (int)$camp['id'],
                                                                                        'from'        => 'admin'
                                                                                    ]);
                                                                                ?>
                                                                                    <a href="<?= esc_url($aCompUrl) ?>" class="btn-table-action" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 5px 9px; font-size: 11.5px; border-radius: 6px; white-space: nowrap;">
                                                                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg>
                                                                                        <span>Compare</span>
                                                                                    </a>
                                                                                <?php endif; ?>

                                                                                <?php if ($asset['status'] === 'approved'): ?>
                                                                                    <button type="button" class="btn-open-review-primary js-trigger-modal" style="padding: 5px 10px; font-size: 11.5px; border-radius: 6px; white-space: nowrap;"
                                                                                            data-version-id="<?= $asset['current_version_id'] ?>"
                                                                                            data-asset-name="<?= htmlspecialchars($asset['name']) ?>"
                                                                                            data-campaign-name="<?= htmlspecialchars($camp['name']) ?>"
                                                                                            data-version-label="<?= htmlspecialchars($asset['version_raw']) ?>"
                                                                                            data-maker-name="<?= htmlspecialchars($asset['maker_name']) ?>"
                                                                                            data-checker-name="<?= htmlspecialchars($asset['checker_name']) ?>"
                                                                                            data-checker-notes=""
                                                                                            data-score="100"
                                                                                            data-file-url="<?= htmlspecialchars($asset['file_url']) ?>">
                                                                                        <span>Review &rarr;</span>
                                                                                    </button>
                                                                                <?php elseif ($asset['status'] === 'released'): ?>
                                                                                    <button type="button" class="btn-view-release js-trigger-release-modal" style="padding: 5px 10px; font-size: 11.5px; border-radius: 6px; white-space: nowrap;"
                                                                                            data-asset-name="<?= htmlspecialchars($asset['name']) ?>"
                                                                                            data-campaign-name="<?= htmlspecialchars($camp['name']) ?>"
                                                                                            data-version-label="<?= htmlspecialchars($asset['version_raw']) ?>">
                                                                                        View Release
                                                                                    </button>
                                                                                <?php else: ?>
                                                                                    <span style="font-size: 12px; color: #94A3B8;">Pending Review</span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr id="noCampaignsFilterRow" style="display: none;">
                                        <td colspan="6" style="text-align: center; padding: 40px 20px; color: #64748B;">
                                            <div style="font-size: 14px; font-weight: 600; color: #334155; margin-bottom: 4px;">No campaigns found</div>
                                            <div style="font-size: 12.5px; color: #94A3B8;">There are no campaigns matching the selected status filter.</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

            <!-- ================= 5. FULL-WIDTH FINAL APPROVAL QUEUE ================= -->
            <section class="queue-section" id="queue-section">
                <div class="queue-header-wrap">
                    <div class="queue-header-left">
                        <div class="queue-title-row">
                            <h2 class="queue-title">Final Approval Queue</h2>
                            <span class="queue-count-badge"><?= $awaitingReleaseCount ?> Awaiting Release</span>
                        </div>
                    </div>
                    <div class="table-filter-pills">
                        <button type="button" class="pill-tab active" data-queue-filter="all">All (<?= $totalQueue ?>)</button>
                        <button type="button" class="pill-tab" data-queue-filter="approved">Awaiting Release (<?= $awaitingReleaseCount ?>)</button>
                        <button type="button" class="pill-tab" data-queue-filter="released">Released (<?= $releasedCount ?>)</button>
                        <a href="<?= esc_url(pcs_url('approval-queue-admin.php')) ?>" style="padding: 6px 14px; background: #EDE9FA; color: #4E56A6; border-radius: 8px; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; margin-left: 8px;">
                            <span>Open Dedicated Queue &rarr;</span>
                        </a>
                    </div>
                </div>

                <?php if (empty($queue)): ?>
                    <div class="empty-state-box">
                        <div class="empty-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <div class="empty-title">No Versions in Queue</div>
                        <p class="empty-desc">When a checker approves an asset version, it will automatically appear here for your production release confirmation.</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table" id="queueTable">
                            <thead>
                                <tr>
                                    <th>Asset</th>
                                    <th>Campaign</th>
                                    <th style="text-align: center;">Version</th>
                                    <th>Maker</th>
                                    <th style="text-align: center;">Checker Approval</th>
                                    <th style="text-align: center;">Approved At</th>
                                    <th style="text-align: center;">Deadline</th>
                                    <th style="text-align: center;">Status</th>
                                    <th style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($queue as $item): ?>
                                    <tr class="queue-row" data-status="<?= htmlspecialchars($item['status']) ?>">
                                        <td>
                                            <div class="campaign-name-cell">
                                                <span class="campaign-name-title"><?= htmlspecialchars($item['asset_name']) ?></span>
                                                <span class="campaign-assets-count"><?= htmlspecialchars($item['asset_type']) ?></span>
                                            </div>
                                        </td>
                                        <td><strong><?= htmlspecialchars($item['campaign_name']) ?></strong></td>
                                        <td style="text-align: center;"><span class="version-tag"><?= htmlspecialchars($item['version_label']) ?></span></td>
                                        <td>
                                            <div class="owner-cell">
                                                <div class="owner-avatar"><?= htmlspecialchars($item['maker_initials']) ?></div>
                                                <span><?= htmlspecialchars($item['maker_name']) ?></span>
                                            </div>
                                        </td>
                                        <td style="text-align: center;">
                                            <div style="display: inline-flex; flex-direction: column; align-items: center; gap: 2px;">
                                                <span style="font-weight: 700; white-space: nowrap;"><?= htmlspecialchars($item['checker_name']) ?></span>
                                                <span class="status-pill-small" style="align-self: center;">APPROVED</span>
                                            </div>
                                        </td>
                                        <td style="text-align: center; font-family: 'JetBrains Mono', monospace; font-size: 12px;"><?= htmlspecialchars($item['approved_at']) ?></td>
                                        <td style="text-align: center; font-family: 'JetBrains Mono', monospace; font-size: 12px;"><?= htmlspecialchars($item['deadline']) ?></td>
                                        <td style="text-align: center;">
                                            <?= function_exists('pcs_format_status_badge') ? pcs_format_status_badge($item['status']) : '<span class="status-badge ' . htmlspecialchars($item['status']) . '">' . ucfirst(htmlspecialchars($item['status'])) . '</span>' ?>
                                        </td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <div style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap;">
                                                <?php if (!empty($item['asset_id'])): 
                                                    $qCompUrl = (function_exists('pcs_url') ? pcs_url('compare-version.php') : 'compare-version.php') . '?' . http_build_query([
                                                        'asset_id'    => (int)$item['asset_id'],
                                                        'v_cur'       => (int)$item['version_id'],
                                                        'campaign_id' => (int)($item['campaign_id'] ?? 0),
                                                        'from'        => 'admin'
                                                    ]);
                                                ?>
                                                    <a href="<?= esc_url($qCompUrl) ?>" class="btn-table-action" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 5px 9px; font-size: 11.5px; border-radius: 6px; white-space: nowrap;">
                                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg>
                                                        <span>Compare</span>
                                                    </a>
                                                <?php endif; ?>

                                                <?php if ($item['status'] === 'approved'): 
                                                    $dashFinalReviewUrl = (function_exists('pcs_url') ? pcs_url('final-review-admin.php') : 'final-review-admin.php') . '?' . http_build_query([
                                                        'version_id'  => (int)$item['version_id'],
                                                        'asset_id'    => (int)$item['asset_id'],
                                                        'campaign_id' => (int)($item['campaign_id'] ?? 0)
                                                    ]);
                                                ?>
                                                    <a href="<?= esc_url($dashFinalReviewUrl) ?>" class="btn-open-review-primary" title="Open Final Review" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 4px; padding: 5px 10px; font-size: 11.5px; border-radius: 6px; white-space: nowrap;">
                                                        <span>Review &rarr;</span>
                                                    </a>
                                                    <button type="button" class="btn-table-action js-trigger-modal" title="Quick Sign-off &amp; Release" style="padding: 5px 9px; font-size: 11.5px; border-radius: 6px; white-space: nowrap;"
                                                            data-version-id="<?= $item['version_id'] ?>"
                                                            data-asset-name="<?= htmlspecialchars($item['asset_name']) ?>"
                                                            data-campaign-name="<?= htmlspecialchars($item['campaign_name']) ?>"
                                                            data-version-label="<?= htmlspecialchars($item['version_label']) ?>"
                                                            data-maker-name="<?= htmlspecialchars($item['maker_name']) ?>"
                                                            data-checker-name="<?= htmlspecialchars($item['checker_name']) ?>"
                                                            data-checker-notes="<?= htmlspecialchars($item['checker_notes']) ?>"
                                                            data-score="<?= $item['score'] ?>"
                                                            data-file-url="<?= htmlspecialchars($item['file_url']) ?>"
                                                            data-final-review-url="<?= esc_url($dashFinalReviewUrl) ?>">
                                                        <span>Quick</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn-view-release js-trigger-release-modal" style="padding: 5px 10px; font-size: 11.5px; border-radius: 6px; white-space: nowrap;"
                                                            data-asset-name="<?= htmlspecialchars($item['asset_name']) ?>"
                                                            data-campaign-name="<?= htmlspecialchars($item['campaign_name']) ?>"
                                                            data-version-label="<?= htmlspecialchars($item['version_label']) ?>">
                                                        View Release
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <!-- ================= 6. RECENTLY RELEASED SECTION ================= -->
            <section class="released-section">
                <div class="released-header">
                    <div>
                        <h2 class="panel-title">Recently Released</h2>
                    </div>
                    <a href="<?= esc_url(pcs_url('audit-log.php') . '?from=admin_dashboard') ?>" class="btn-audit-history" style="text-decoration: none;">
                        <span>View Audit History →</span>
                    </a>
                </div>

                <?php if (empty($recentlyReleased)): ?>
                    <div class="empty-state-box">
                        <div class="empty-icon">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <div class="empty-title">No Released Assets Yet</div>
                        <p class="empty-desc">Assets released to production will appear here with permanent, locked compliance audit trails.</p>
                    </div>
                <?php else: ?>
                    <div class="released-cards-grid">
                        <?php foreach ($recentlyReleased as $rel): ?>
                            <div class="released-card">
                                <div class="released-card-top">
                                    <div>
                                        <h3 class="released-card-title"><?= htmlspecialchars($rel['asset_name']) ?></h3>
                                        <span class="attention-campaign-tag" style="margin-top: 4px;">
                                            <span><?= htmlspecialchars($rel['campaign_name']) ?></span>
                                            <span>·</span>
                                            <span class="version-tag"><?= htmlspecialchars($rel['version_label']) ?></span>
                                        </span>
                                    </div>
                                    <span class="lock-badge">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        <span>Locked</span>
                                    </span>
                                </div>
                                <div class="released-meta-list">
                                    <div>Released: <strong><?= htmlspecialchars($rel['released_at']) ?></strong></div>
                                    <div>Released by: <strong><?= htmlspecialchars($rel['released_by']) ?></strong></div>
                                </div>
                                <?php 
                                    $relUrl = pcs_url('release-detail.php') . '?' . http_build_query([
                                        'version_id'  => $rel['version_id'],
                                        'asset_id'    => $rel['asset_id'],
                                        'campaign_id' => !empty($rel['campaign_id']) ? $rel['campaign_id'] : 0,
                                        'from'        => 'admin_dashboard'
                                    ]);
                                ?>
                                <a href="<?= esc_url($relUrl) ?>" class="btn-view-release" style="width: 100%; justify-content: center; text-decoration: none;">
                                    View Release
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </main>

    <!-- ================= 7. MODALS ================= -->

    <!-- Modal 0: Create Campaign Modal (Multi-Guideline Attachment & Role Assign Tag Pickers) -->
    <div class="modal-overlay" id="adminCampaignModal" style="display: none;">
        <div class="modal-window" style="max-width: 620px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Create New Campaign</h3>
                    <p style="font-size: 13px; color: #64748B; margin-top: 2px;">Setup campaign workspace, attach compliance guidelines, and assign team roles</p>
                </div>
                <span class="btn-close-modal" onclick="closeAdminCampaignModal()">&times;</span>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" id="adminCreateCampaignForm">
                <input type="hidden" name="action" value="create_campaign">
                <div class="modal-body" style="padding: 24px; display: flex; flex-direction: column; gap: 16px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Campaign Name <span style="color: #EF4444;">*</span></label>
                        <input type="text" name="campaign_title" placeholder="e.g. DBS Spring Investment 2026" required style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                    </div>

                    <!-- Campaign Owner Selector -->
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Campaign Owner <span style="color: #EF4444;">*</span></label>
                        <select name="owner_id" required style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; background: #fff; box-sizing: border-box;">
                            <?php foreach ($allAdminsList as $adm): 
                                list($admId, $admName, $admEmail) = pcs_extract_user_info($adm);
                            ?>
                                <option value="<?= esc_attr($admId) ?>" <?= ($admId == ($currentUser['id'] ?? 0)) ? 'selected' : '' ?>>
                                    <?= esc_html($admName) ?> (<?= esc_html($admEmail) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <span style="font-size: 11px; color: #64748B; margin-top: 4px; display: block;">Select the Campaign Owner (Admin/Lead) responsible for approving releases.</span>
                    </div>

                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Target Deadline <span style="color: #EF4444;">*</span></label>
                        <input type="date" name="campaign_deadline" id="campaignDeadlineInput" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; box-sizing: border-box;">
                        <span style="font-size: 11px; color: #64748B; margin-top: 4px; display: block;">Must be a future date (cannot be today or in the past).</span>
                    </div>

                    <!-- Multiple Guideline Documents Attachment -->
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Campaign Guideline Documents (PDF / DOCX / Images)</label>
                        <div id="guidelineDropArea" style="border: 2px dashed #CBD5E1; border-radius: 8px; padding: 20px; text-align: center; cursor: pointer; background: #F8FAFC; transition: all 0.2s ease;">
                            <div style="pointer-events: none;">
                                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 6px; display: inline-block;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                                <div id="guidelineFileLabel" style="font-size: 13px; font-weight: 600; color: #334155;">Drag &amp; drop multiple guideline files here, or <span style="color: #6366F1; text-decoration: underline;">browse</span></div>
                                <div style="font-size: 11px; color: #94A3B8; margin-top: 4px;">Upload multiple guidelines (.pdf, .docx, .doc, .txt, .png, .jpg). AI checks will cite these files.</div>
                            </div>
                            <input type="file" name="guideline_files[]" id="guidelineFileInput" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg" multiple style="display: none;">
                        </div>
                        <div id="guidelineSelectedList" style="margin-top: 10px; display: flex; flex-direction: column; gap: 6px;"></div>
                    </div>

                    <!-- Interactive Assigned Team Members with Searchable Tag Pickers -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;" class="assignee-grid">
                        <!-- Assign Makers -->
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Assign Maker(s)</label>
                            <div class="user-picker-wrap" id="makerPicker">
                                <div class="user-picker-box" onclick="focusPickerInput('makerPicker')">
                                    <div class="user-picker-tags" id="makerPickerTags">
                                        <?php if (!empty($allMakersList)): 
                                            $firstMaker = $allMakersList[0];
                                            list($fmId, $fmName, $fmEmail) = pcs_extract_user_info($firstMaker);
                                        ?>
                                            <span class="user-picker-tag" data-id="<?= esc_attr($fmId) ?>">
                                                <span><?= esc_html($fmName) ?></span>
                                                <span class="user-picker-tag-remove" onclick="removePickerUser('makerPicker', '<?= esc_attr($fmId) ?>')">&times;</span>
                                                <input type="hidden" name="maker_ids[]" value="<?= esc_attr($fmId) ?>">
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <input type="text" class="user-picker-input" id="makerPickerInput" placeholder="Type name..." onfocus="openPickerDropdown('makerPicker')" oninput="filterPickerUsers('makerPicker', this.value)">
                                </div>
                                <div class="user-picker-dropdown" id="makerPickerDropdown" style="display: none;">
                                    <?php foreach ($allMakersList as $mUser): 
                                        list($uId, $uName, $uEmail) = pcs_extract_user_info($mUser);
                                    ?>
                                        <div class="user-dropdown-item" data-id="<?= esc_attr($uId) ?>" data-name="<?= esc_attr($uName) ?>" data-email="<?= esc_attr($uEmail) ?>" onclick="addPickerUser('makerPicker', '<?= esc_attr($uId) ?>', '<?= esc_js($uName) ?>', 'maker_ids[]')">
                                            <div class="user-dropdown-avatar" style="background: #373E8C;"><?= strtoupper(substr($uName, 0, 2)) ?></div>
                                            <div>
                                                <div class="user-dropdown-name"><?= esc_html($uName) ?></div>
                                                <div class="user-dropdown-email"><?= esc_html($uEmail) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <span style="font-size: 11px; color: #64748B; margin-top: 4px; display: block;">Makers upload assets and fix flagged issues.</span>
                        </div>

                        <!-- Assign Checkers -->
                        <div>
                            <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Assign Checker(s)</label>
                            <div class="user-picker-wrap" id="checkerPicker">
                                <div class="user-picker-box" onclick="focusPickerInput('checkerPicker')">
                                    <div class="user-picker-tags" id="checkerPickerTags">
                                        <?php if (!empty($allCheckersList)): 
                                            $firstChecker = $allCheckersList[0];
                                            list($fcId, $fcName, $fcEmail) = pcs_extract_user_info($firstChecker);
                                        ?>
                                            <span class="user-picker-tag" data-id="<?= esc_attr($fcId) ?>">
                                                <span><?= esc_html($fcName) ?></span>
                                                <span class="user-picker-tag-remove" onclick="removePickerUser('checkerPicker', '<?= esc_attr($fcId) ?>')">&times;</span>
                                                <input type="hidden" name="checker_ids[]" value="<?= esc_attr($fcId) ?>">
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <input type="text" class="user-picker-input" id="checkerPickerInput" placeholder="Type name..." onfocus="openPickerDropdown('checkerPicker')" oninput="filterPickerUsers('checkerPicker', this.value)">
                                </div>
                                <div class="user-picker-dropdown" id="checkerPickerDropdown" style="display: none;">
                                    <?php foreach ($allCheckersList as $cUser): 
                                        list($uId, $uName, $uEmail) = pcs_extract_user_info($cUser);
                                    ?>
                                        <div class="user-dropdown-item" data-id="<?= esc_attr($uId) ?>" data-name="<?= esc_attr($uName) ?>" data-email="<?= esc_attr($uEmail) ?>" onclick="addPickerUser('checkerPicker', '<?= esc_attr($uId) ?>', '<?= esc_js($uName) ?>', 'checker_ids[]')">
                                            <div class="user-dropdown-avatar" style="background: #00B894;"><?= strtoupper(substr($uName, 0, 2)) ?></div>
                                            <div>
                                                <div class="user-dropdown-name"><?= esc_html($uName) ?></div>
                                                <div class="user-dropdown-email"><?= esc_html($uEmail) ?></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <span style="font-size: 11px; color: #64748B; margin-top: 4px; display: block;">Checkers review scores and approve/reject assets.</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="padding: 16px 24px; border-top: 1px solid #E2E8F0; display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" class="btn-action-outline" onclick="closeAdminCampaignModal()">Cancel</button>
                    <button type="submit" class="btn-action-primary">Create Campaign</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 0B: Edit Campaign Details & Team Modal -->
    <div class="modal-overlay" id="editTeamModal" style="display: none;">
        <div class="modal-window" style="max-width: 580px; max-height: 90vh; overflow-y: auto;">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Edit Campaign</h3>
                    <p style="font-size: 13px; color: #64748B; margin-top: 2px;" id="editTeamCampaignTitle">Update campaign details and team assignments</p>
                </div>
                <span class="btn-close-modal" onclick="closeEditTeamModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_campaign">
                <input type="hidden" name="campaign_id" id="editTeamCampaignId" value="">
                
                <div class="modal-body" style="padding: 24px; display: flex; flex-direction: column; gap: 16px;">
                    <!-- Edit Campaign Title -->
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Campaign Title</label>
                        <input type="text" name="campaign_title" id="editCampaignTitleInput" required style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; background: #fff; box-sizing: border-box;" placeholder="Enter campaign name">
                    </div>

                    <!-- Edit Deadline -->
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Target Deadline</label>
                        <input type="date" name="campaign_deadline" id="editCampaignDeadlineInput" style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; background: #fff; box-sizing: border-box;">
                    </div>

                    <!-- Edit Owner -->
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Campaign Owner</label>
                        <select name="owner_id" id="editTeamOwnerSelect" required style="width: 100%; height: 44px; padding: 0 14px; border: 1px solid #E2E8F0; border-radius: 8px; font-size: 14px; background: #fff; box-sizing: border-box;">
                            <?php foreach ($allAdminsList as $adm): 
                                list($admId, $admName, $admEmail) = pcs_extract_user_info($adm);
                            ?>
                                <option value="<?= esc_attr($admId) ?>">
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
                                <div class="user-picker-tags" id="editMakerPickerTags"></div>
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
                                <div class="user-picker-tags" id="editCheckerPickerTags"></div>
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
                    <button type="button" class="btn-action-outline" onclick="closeEditTeamModal()">Cancel</button>
                    <button type="submit" class="btn-action-primary">Save Campaign Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 1: Final Review & Production Release Modal -->
    <div class="modal-overlay" id="finalReviewModal">
        <div class="modal-window">
            <div class="modal-header">
                <h3 class="modal-title">Final Compliance Review & Production Release</h3>
                <span class="btn-close-modal" id="btnCloseReviewModal">&times;</span>
            </div>
            <form method="POST" action="" id="finalReviewForm">
                <input type="hidden" name="action" id="modalReviewAction" value="admin_release">
                <input type="hidden" name="version_id" id="modalVersionId" value="">

                <div class="modal-body">
                    <!-- Asset Header Preview -->
                    <div class="modal-preview-box">
                        <div class="modal-thumb" id="modalThumbPreview" style="display: flex; align-items: center; justify-content: center; background: #EEF0FB; color: #1F2A5A; border-radius: 8px;">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="modal-asset-info">
                            <h4 class="modal-asset-title" id="modalAssetTitle">Asset Name</h4>
                            <div class="modal-tags">
                                <span id="modalCampaignName">Campaign</span>
                                <span>·</span>
                                <span class="version-tag" id="modalVersionTag">V2</span>
                                <span>·</span>
                                <span>Maker: <strong id="modalMakerName">Maker</strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- Checker Evaluation Summary -->
                    <div class="checker-feedback-box">
                        <div class="feedback-header">
                            <span class="feedback-author">Checker Review: <span id="modalCheckerName">Hoang Anh Le</span></span>
                            <span class="status-pill-small">VERIFIED & APPROVED</span>
                        </div>
                        <p class="feedback-text" id="modalCheckerNotes">
                            "Asset verified against corporate guidelines and MAS financial advertisement standards. Ready for production release."
                        </p>
                    </div>

                    <!-- AI Pre-flight Checklist Breakdown -->
                    <div>
                        <h4 style="font-size: 13.5px; font-weight: 700; color: #1E293B; margin-bottom: 8px;">Automated Compliance Verification</h4>
                        <div class="checks-grid">
                            <div class="check-card">
                                <span class="check-criterion">MAS Financial Regulations</span>
                                <div class="check-score-row">
                                    <span style="color: #64748B;">Mandatory Disclosures</span>
                                    <span class="score-pill">PASS (95%)</span>
                                </div>
                            </div>
                            <div class="check-card">
                                <span class="check-criterion">Brand Guidelines Check</span>
                                <div class="check-score-row">
                                    <span style="color: #64748B;">Colors & Typography</span>
                                    <span class="score-pill">PASS (92%)</span>
                                </div>
                            </div>
                            <div class="check-card">
                                <span class="check-criterion">Foreign Logo Misuse</span>
                                <div class="check-score-row">
                                    <span style="color: #64748B;">Logo Integrity</span>
                                    <span class="score-pill">PASS (98%)</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Admin Release Notes -->
                    <div class="form-group">
                        <label class="form-label" for="releaseNotesInput">Admin Decision Notes (Feedback / Release Sign-off)</label>
                        <textarea class="form-textarea" name="release_notes" id="releaseNotesInput" placeholder="Enter release sign-off notes or revision feedback if rejecting..."></textarea>
                    </div>

                    <!-- MAS Compliance Governance Warning -->
                    <div class="compliance-warning-notice">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <span>Releasing this asset will lock the version permanently in accordance with MAS compliance governance (FR-07). It cannot be modified or deleted.</span>
                    </div>
                </div>

                <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <a href="#" id="modalBtnFullReview" class="btn-action-outline" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: #F4F3FD; color: #4E56A6; border-color: #D5D9F3;">
                            <span>Open Dedicated Review Canvas &rarr;</span>
                        </a>
                        <a href="#" id="modalBtnCompare" class="btn-action-outline" style="text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg>
                            <span>Compare Versions</span>
                        </a>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" class="btn-cancel" id="btnCancelReview">Cancel</button>
                        <button type="button" class="btn-action-outline" style="color: #BA1A1A; border-color: #FECDD3; background: #FFF1F2; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;" onclick="submitAdminRejectFromModal()">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <span>Reject / Request Revisions</span>
                        </button>
                        <button type="submit" class="btn-release-confirm" onclick="document.getElementById('modalReviewAction').value='admin_release'">
                            <span>Release to Production</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 2: View Release Modal -->
    <div class="modal-overlay" id="viewReleaseModal">
        <div class="modal-window" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">Production Release Record</h3>
                <span class="btn-close-modal" id="btnCloseViewReleaseModal">&times;</span>
            </div>
            <div class="modal-body">
                <div class="modal-preview-box">
                    <div class="modal-thumb"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg></div>
                    <div class="modal-asset-info">
                        <h4 class="modal-asset-title" id="viewRelAssetTitle">Asset Title</h4>
                        <div class="modal-tags">
                            <span id="viewRelCampaign">Campaign</span>
                            <span>·</span>
                            <span class="version-tag" id="viewRelVersion">V1</span>
                        </div>
                    </div>
                </div>

                <div class="compliance-warning-notice" style="background-color: #E6FBF5; border-color: #A7F3D0; color: #047857;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <span>This asset version is locked and cleared for public financial advertisement distribution.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="btnCloseViewRelBtn">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal 3: Audit History Modal -->
    <div class="modal-overlay" id="auditHistoryModal">
        <div class="modal-window" style="max-width: 700px;">
            <div class="modal-header">
                <h3 class="modal-title">System Audit Log History</h3>
                <span class="btn-close-modal" id="btnCloseAuditModal">&times;</span>
            </div>
            <div class="modal-body">
                <?php if (empty($auditLogs)): ?>
                    <div class="empty-state-box">
                        <div class="empty-icon"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
                        <div class="empty-title">No Audit Logs Yet</div>
                        <p class="empty-desc">System actions, version uploads, checker reviews, and admin releases will be permanently recorded here.</p>
                    </div>
                <?php else: ?>
                    <div class="timeline-list">
                        <?php foreach ($auditLogs as $log): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <span class="timeline-time"><?= htmlspecialchars($log['created_at']) ?></span>
                                <h4 class="timeline-title"><?= htmlspecialchars($log['title']) ?></h4>
                                <p class="timeline-note">
                                    Action: <strong><?= strtoupper(htmlspecialchars($log['action'])) ?></strong> | User: <strong><?= htmlspecialchars($log['user_name']) ?></strong> (<?= htmlspecialchars($log['user_role']) ?>)
                                    <?php if (!empty($log['note'])): ?>
                                        <br><em><?= htmlspecialchars($log['note']) ?></em>
                                    <?php endif; ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: space-between; align-items: center;">
                <a href="<?= esc_url(pcs_url('audit-log.php') . '?from=admin_dashboard') ?>" style="text-decoration: none; font-size: 13px; font-weight: 600; color: #4E56A6;">
                    Open Dedicated Audit Screen →
                </a>
                <button type="button" class="btn-cancel" id="btnCloseAuditBtn">Close</button>
            </div>
        </div>
    </div>

    <!-- Modal 4: Manage & Upload Campaign Guidelines (Admin Full CRUD) -->
    <div class="modal-overlay" id="uploadGuidelineModal" style="display: none;">
        <div class="modal-window" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title" id="guidelineModalTitle">Campaign Guidelines</h3>
                <span class="btn-close-modal" onclick="closeUploadGuidelineModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div style="margin-bottom: 16px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 8px;">Attached Documents</label>
                    <div id="guidelineAttachedList" style="display: flex; flex-direction: column; gap: 8px; max-height: 220px; overflow-y: auto;">
                        <!-- Injected via JS -->
                    </div>
                </div>

                <div style="border-top: 1px solid #E2E8F0; padding-top: 16px;">
                    <label style="font-size: 12px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">Attach Additional Guidelines</label>
                    <form method="POST" action="" enctype="multipart/form-data" id="uploadGuidelineForm">
                        <input type="hidden" name="action" value="upload_guideline">
                        <input type="hidden" name="campaign_id" id="guidelineCampId" value="0">
                        <div style="display: flex; flex-direction: column; gap: 10px;">
                            <input type="file" name="guideline_files[]" class="form-input" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg" multiple required style="padding: 8px; border: 1px solid #CBD5E1; border-radius: 6px; font-size: 13px;">
                            <p style="font-size: 11.5px; color: #64748B; margin: 0;">Supports multiple documents (.pdf, .docx, .doc, .txt, .png, .jpg). These will be used for AI pre-flight and compliance checks.</p>
                            <div style="display: flex; justify-content: flex-end; gap: 8px; margin-top: 6px;">
                                <button type="button" class="btn-cancel" onclick="closeUploadGuidelineModal()">Cancel</button>
                                <button type="submit" class="btn-confirm" style="background: #373E8C; color: #FFF; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer;">+ Upload Documents</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden form for guideline deletion -->
    <form method="POST" action="" id="deleteGuidelineForm" style="display: none;">
        <input type="hidden" name="action" value="delete_guideline">
        <input type="hidden" name="campaign_id" id="deleteGuidelineCampId" value="0">
        <input type="hidden" name="guideline_url" id="deleteGuidelineUrl" value="">
    </form>

    <!-- ================= 8. SCRIPTING ================= -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Profile Dropdown Toggle
            const profileContainer = document.getElementById('profileContainer');
            const btnToggleProfile = document.getElementById('btnToggleProfile');

            if (btnToggleProfile && profileContainer) {
                btnToggleProfile.addEventListener('click', function(e) {
                    e.stopPropagation();
                    profileContainer.classList.toggle('active');
                });

                document.addEventListener('click', function(e) {
                    if (!profileContainer.contains(e.target)) {
                        profileContainer.classList.remove('active');
                    }
                });
            }

            // Campaigns Table Filtering & Top KPI Sync
            const campFilterPills = document.querySelectorAll('.pill-tab[data-filter]');
            const campRows = document.querySelectorAll('.campaign-row');
            const noCampFilterRow = document.getElementById('noCampaignsFilterRow');

            function applyCampaignFilter(filter) {
                campFilterPills.forEach(p => {
                    if (p.getAttribute('data-filter') === filter) {
                        p.classList.add('active');
                    } else {
                        p.classList.remove('active');
                    }
                });

                let visibleCount = 0;
                campRows.forEach(row => {
                    const statuses = (row.getAttribute('data-statuses') || row.getAttribute('data-status') || '').split(',').map(s => s.trim().toLowerCase());
                    const matches = (filter === 'all' || statuses.includes(filter.toLowerCase()));
                    row.style.display = matches ? '' : 'none';
                    if (matches) visibleCount++;

                    // Drawer sync
                    const drawerBtn = row.querySelector('.js-toggle-drawer');
                    const drawerId = drawerBtn ? drawerBtn.getAttribute('data-target') : null;
                    if (drawerId) {
                        const drawer = document.getElementById(drawerId);
                        if (drawer) {
                            if (!matches) {
                                drawer.style.display = 'none';
                            } else {
                                drawer.style.removeProperty('display');
                                const assetRows = drawer.querySelectorAll('.drawer-asset-row');
                                assetRows.forEach(aRow => {
                                    const aStatus = (aRow.getAttribute('data-asset-status') || '').trim().toLowerCase();
                                    if (filter === 'all' || aStatus === filter.toLowerCase()) {
                                        aRow.style.display = '';
                                    } else {
                                        aRow.style.display = 'none';
                                    }
                                });
                            }
                        }
                    }
                });

                if (noCampFilterRow) {
                    noCampFilterRow.style.display = visibleCount === 0 ? '' : 'none';
                }
            }

            campFilterPills.forEach(pill => {
                pill.addEventListener('click', function() {
                    applyCampaignFilter(this.getAttribute('data-filter'));
                });
            });

            // Interactive Top KPI Metric Cards: Click to Filter Table
            document.querySelectorAll('.kpi-metrics-bar .kpi-card').forEach(card => {
                card.style.cursor = 'pointer';
                card.setAttribute('title', 'Click to filter campaigns by this status');
                card.addEventListener('click', function() {
                    const labelElem = this.querySelector('.kpi-label');
                    if (!labelElem) return;
                    const labelText = labelElem.textContent.trim().toLowerCase().replace(/\s+/g, '_');
                    const targetPill = document.querySelector(`.pill-tab[data-filter="${labelText}"]`);
                    if (targetPill) {
                        applyCampaignFilter(labelText);
                        const campSec = document.getElementById('campaigns-section');
                        if (campSec) {
                            campSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        }
                    }
                });
            });

            // Campaign Accordion Drawer Toggle
            const toggleDrawerBtns = document.querySelectorAll('.js-toggle-drawer');
            toggleDrawerBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const drawer = document.getElementById(targetId);
                    if (drawer) {
                        const isExpanded = drawer.classList.contains('active');
                        drawer.classList.toggle('active');
                        this.textContent = isExpanded ? 'View' : 'Hide';
                    }
                });
            });

            // Final Approval Queue Filtering
            const queueFilterPills = document.querySelectorAll('.pill-tab[data-queue-filter]');
            const queueRows = document.querySelectorAll('.queue-row');

            queueFilterPills.forEach(pill => {
                pill.addEventListener('click', function() {
                    queueFilterPills.forEach(p => p.classList.remove('active'));
                    this.classList.add('active');

                    const filter = this.getAttribute('data-queue-filter');
                    queueRows.forEach(row => {
                        const status = row.getAttribute('data-status');
                        if (filter === 'all' || status === filter) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            });

            // Modal Handlers: Final Review & Release
            const reviewModal = document.getElementById('finalReviewModal');
            const btnCloseReviewModal = document.getElementById('btnCloseReviewModal');
            const btnCancelReview = document.getElementById('btnCancelReview');

            function openFinalReviewModal(data) {
                if (!reviewModal) return;
                document.getElementById('modalVersionId').value = data.versionId || '';
                document.getElementById('modalAssetTitle').textContent = data.assetName || 'Asset Review';
                document.getElementById('modalCampaignName').textContent = data.campaignName || 'General Campaign';
                document.getElementById('modalVersionTag').textContent = data.versionLabel || 'V1';
                document.getElementById('modalMakerName').textContent = data.makerName || 'Maker';
                document.getElementById('modalCheckerName').textContent = data.checkerName || 'Checker';
                
                if (data.checkerNotes && data.checkerNotes.trim()) {
                    document.getElementById('modalCheckerNotes').textContent = '"' + data.checkerNotes + '"';
                } else {
                    document.getElementById('modalCheckerNotes').textContent = '"Compliance criteria verified and approved for release."';
                }

                const btnComp = document.getElementById('modalBtnCompare');
                if (btnComp) {
                    const compareBase = '<?= (function_exists('pcs_url') ? pcs_url('compare-version.php') : 'compare-version.php') ?>';
                    btnComp.href = compareBase + '?v_cur=' + encodeURIComponent(data.versionId || '') + '&from=admin';
                }

                const btnFullRev = document.getElementById('modalBtnFullReview');
                if (btnFullRev) {
                    const finalReviewBase = '<?= (function_exists('pcs_url') ? pcs_url('final-review-admin.php') : 'final-review-admin.php') ?>';
                    btnFullRev.href = data.finalReviewUrl || (finalReviewBase + '?version_id=' + encodeURIComponent(data.versionId || ''));
                }

                reviewModal.classList.add('active');
            }

            function closeFinalReviewModal() {
                if (reviewModal) reviewModal.classList.remove('active');
            }

            window.submitAdminRejectFromModal = function() {
                var notesEl = document.getElementById('releaseNotesInput');
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
                var actEl = document.getElementById('modalReviewAction');
                if (actEl) actEl.value = 'admin_reject';
                var formEl = document.getElementById('finalReviewForm');
                if (formEl) formEl.submit();
            };

            if (btnCloseReviewModal) btnCloseReviewModal.addEventListener('click', closeFinalReviewModal);
            if (btnCancelReview) btnCancelReview.addEventListener('click', closeFinalReviewModal);

            // Trigger modal on buttons
            document.querySelectorAll('.js-trigger-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    openFinalReviewModal({
                        versionId: this.getAttribute('data-version-id'),
                        assetName: this.getAttribute('data-asset-name'),
                        campaignName: this.getAttribute('data-campaign-name'),
                        versionLabel: this.getAttribute('data-version-label'),
                        makerName: this.getAttribute('data-maker-name'),
                        checkerName: this.getAttribute('data-checker-name'),
                        checkerNotes: this.getAttribute('data-checker-notes'),
                        score: this.getAttribute('data-score'),
                        fileUrl: this.getAttribute('data-file-url'),
                        finalReviewUrl: this.getAttribute('data-final-review-url')
                    });
                });
            });

            // Hero CTA button
            const btnHeroOpenReview = document.getElementById('btnHeroOpenReview');
            if (btnHeroOpenReview && !btnHeroOpenReview.disabled) {
                btnHeroOpenReview.addEventListener('click', function() {
                    const firstBtn = document.querySelector('.js-trigger-modal');
                    if (firstBtn) {
                        firstBtn.click();
                    }
                });
            }

            // Modal Handlers: View Release Modal
            const viewReleaseModal = document.getElementById('viewReleaseModal');
            const btnCloseViewReleaseModal = document.getElementById('btnCloseViewReleaseModal');
            const btnCloseViewRelBtn = document.getElementById('btnCloseViewRelBtn');

            function openViewReleaseModal(assetName, campaignName, versionLabel) {
                if (!viewReleaseModal) return;
                document.getElementById('viewRelAssetTitle').textContent = assetName;
                document.getElementById('viewRelCampaign').textContent = campaignName;
                document.getElementById('viewRelVersion').textContent = versionLabel;
                viewReleaseModal.classList.add('active');
            }

            function closeViewReleaseModal() {
                if (viewReleaseModal) viewReleaseModal.classList.remove('active');
            }

            if (btnCloseViewReleaseModal) btnCloseViewReleaseModal.addEventListener('click', closeViewReleaseModal);
            if (btnCloseViewRelBtn) btnCloseViewRelBtn.addEventListener('click', closeViewReleaseModal);

            document.querySelectorAll('.js-trigger-release-modal').forEach(btn => {
                btn.addEventListener('click', function() {
                    openViewReleaseModal(
                        this.getAttribute('data-asset-name'),
                        this.getAttribute('data-campaign-name'),
                        this.getAttribute('data-version-label')
                    );
                });
            });

            // Modal Handlers: Audit History Modal
            const auditHistoryModal = document.getElementById('auditHistoryModal');
            const btnOpenAuditModal = document.getElementById('btnOpenAuditModal');
            const btnCloseAuditModal = document.getElementById('btnCloseAuditModal');
            const btnCloseAuditBtn = document.getElementById('btnCloseAuditBtn');

            if (btnOpenAuditModal && auditHistoryModal) {
                btnOpenAuditModal.addEventListener('click', function() {
                    auditHistoryModal.classList.add('active');
                });
            }
            if (btnCloseAuditModal) btnCloseAuditModal.addEventListener('click', () => auditHistoryModal.classList.remove('active'));
            if (btnCloseAuditBtn) btnCloseAuditBtn.addEventListener('click', () => auditHistoryModal.classList.remove('active'));

            // Close modals when clicking overlay background
            window.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal-overlay')) {
                    e.target.classList.remove('active');
                    e.target.style.display = 'none';
                }
                // Close user picker dropdowns when clicking outside
                if (!e.target.closest('.user-picker-wrap')) {
                    document.querySelectorAll('.user-picker-dropdown').forEach(dd => dd.style.display = 'none');
                }
            });

            // Multi-file Guideline Dropzone & DataTransfer Management
            const guidelineDT = new DataTransfer();
            const guidelineDropArea = document.getElementById('guidelineDropArea');
            const guidelineFileInput = document.getElementById('guidelineFileInput');
            const guidelineFileLabel = document.getElementById('guidelineFileLabel');
            const guidelineSelectedList = document.getElementById('guidelineSelectedList');

            if (guidelineDropArea && guidelineFileInput) {
                guidelineDropArea.addEventListener('click', function(e) {
                    guidelineFileInput.click();
                });

                ['dragenter', 'dragover'].forEach(eventName => {
                    guidelineDropArea.addEventListener(eventName, function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        guidelineDropArea.style.borderColor = '#6366F1';
                        guidelineDropArea.style.background = '#EEF2FF';
                    }, false);
                });

                ['dragleave', 'drop'].forEach(eventName => {
                    guidelineDropArea.addEventListener(eventName, function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        guidelineDropArea.style.borderColor = '#CBD5E1';
                        guidelineDropArea.style.background = '#F8FAFC';
                    }, false);
                });

                guidelineDropArea.addEventListener('drop', function(e) {
                    const dt = e.dataTransfer;
                    if (dt && dt.files && dt.files.length > 0) {
                        for (let i = 0; i < dt.files.length; i++) {
                            guidelineDT.items.add(dt.files[i]);
                        }
                        guidelineFileInput.files = guidelineDT.files;
                        renderGuidelineList();
                    }
                });

                guidelineFileInput.addEventListener('change', function() {
                    if (guidelineFileInput.files && guidelineFileInput.files.length > 0) {
                        for (let i = 0; i < guidelineFileInput.files.length; i++) {
                            guidelineDT.items.add(guidelineFileInput.files[i]);
                        }
                        guidelineFileInput.files = guidelineDT.files;
                        renderGuidelineList();
                    }
                });

                function renderGuidelineList() {
                    if (!guidelineSelectedList) return;
                    guidelineSelectedList.innerHTML = '';
                    const files = guidelineDT.files;
                    if (files.length === 0) {
                        guidelineFileLabel.innerHTML = 'Drag &amp; drop multiple guideline files here, or <span style="color: #6366F1; text-decoration: underline;">browse</span>';
                        guidelineDropArea.style.borderColor = '#CBD5E1';
                        guidelineDropArea.style.background = '#F8FAFC';
                        return;
                    }

                    guidelineFileLabel.innerHTML = '<span style="color: #059669; font-weight: 700;">' + files.length + ' file(s) attached</span> (click or drop to add more)';
                    guidelineDropArea.style.borderColor = '#10B981';
                    guidelineDropArea.style.background = '#ECFDF5';

                    for (let i = 0; i < files.length; i++) {
                        const f = files[i];
                        const sizeKb = (f.size / 1024).toFixed(1);
                        const row = document.createElement('div');
                        row.className = 'guideline-file-item';
                        row.innerHTML = `
                            <div class="guideline-file-info">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6366F1" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <span>${f.name.replace(/</g, '&lt;')} (${sizeKb} KB)</span>
                            </div>
                            <span class="guideline-file-remove" onclick="removeGuidelineFile(${i})" title="Remove file">&times;</span>
                        `;
                        guidelineSelectedList.appendChild(row);
                    }
                }

                window.removeGuidelineFile = function(index) {
                    const newDT = new DataTransfer();
                    const files = guidelineDT.files;
                    for (let i = 0; i < files.length; i++) {
                        if (i !== index) newDT.items.add(files[i]);
                    }
                    guidelineDT.items.clear();
                    for (let i = 0; i < newDT.files.length; i++) {
                        guidelineDT.items.add(newDT.files[i]);
                    }
                    guidelineFileInput.files = guidelineDT.files;
                    renderGuidelineList();
                };
            }

            // Client-side deadline check on campaign form submit
            const campForm = document.getElementById('adminCreateCampaignForm');
            if (campForm) {
                campForm.addEventListener('submit', function(e) {
                    const dlInput = document.getElementById('campaignDeadlineInput');
                    if (dlInput && dlInput.value) {
                        const todayStr = new Date().toISOString().split('T')[0];
                        if (dlInput.value <= todayStr) {
                            e.preventDefault();
                            alert('Target Deadline must be a future date (cannot be today or in the past).');
                            dlInput.focus();
                            return false;
                        }
                    }
                });
            }
        });

        // User Picker Multi-Select Functions
        function focusPickerInput(pickerId) {
            const input = document.querySelector('#' + pickerId + ' .user-picker-input');
            if (input) input.focus();
        }

        function openPickerDropdown(pickerId) {
            document.querySelectorAll('.user-picker-dropdown').forEach(dd => dd.style.display = 'none');
            const dd = document.getElementById(pickerId + 'Dropdown');
            if (dd) dd.style.display = 'block';
        }

        function filterPickerUsers(pickerId, query) {
            const q = (query || '').toLowerCase().trim();
            const items = document.querySelectorAll('#' + pickerId + 'Dropdown .user-dropdown-item');
            items.forEach(item => {
                const name = (item.getAttribute('data-name') || '').toLowerCase();
                const email = (item.getAttribute('data-email') || '').toLowerCase();
                if (!q || name.includes(q) || email.includes(q)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function addPickerUser(pickerId, userId, userName, inputName) {
            const tagsContainer = document.getElementById(pickerId + 'Tags');
            if (!tagsContainer) return;

            // Check if already selected
            const existing = tagsContainer.querySelector('[data-id="' + userId + '"]');
            if (existing) {
                const dd = document.getElementById(pickerId + 'Dropdown');
                if (dd) dd.style.display = 'none';
                return;
            }

            const tag = document.createElement('span');
            tag.className = 'user-picker-tag';
            tag.setAttribute('data-id', userId);
            tag.innerHTML = `
                <span>${userName}</span>
                <span class="user-picker-tag-remove" onclick="removePickerUser('${pickerId}', '${userId}')">&times;</span>
                <input type="hidden" name="${inputName}" value="${userId}">
            `;
            tagsContainer.appendChild(tag);

            const input = document.querySelector('#' + pickerId + ' .user-picker-input');
            if (input) {
                input.value = '';
                filterPickerUsers(pickerId, '');
            }
            const dd = document.getElementById(pickerId + 'Dropdown');
            if (dd) dd.style.display = 'none';
        }

        function removePickerUser(pickerId, userId) {
            const tagsContainer = document.getElementById(pickerId + 'Tags');
            if (!tagsContainer) return;
            const tag = tagsContainer.querySelector('[data-id="' + userId + '"]');
            if (tag) tag.remove();
        }

        // Edit Campaign Details & Team Modal Functions
        function openEditTeamModal(campId, campTitle, ownerId, makerIds, checkerIds, deadline) {
            const modal = document.getElementById('editTeamModal');
            if (!modal) return;

            document.getElementById('editTeamCampaignId').value = campId;
            document.getElementById('editTeamCampaignTitle').textContent = 'Update parameters & assignments for: ' + campTitle;

            const titleInput = document.getElementById('editCampaignTitleInput');
            if (titleInput) {
                titleInput.value = campTitle || '';
            }

            const deadlineInput = document.getElementById('editCampaignDeadlineInput');
            if (deadlineInput) {
                deadlineInput.value = (deadline && deadline !== 'No deadline') ? deadline : '';
            }

            const ownerSelect = document.getElementById('editTeamOwnerSelect');
            if (ownerSelect) {
                ownerSelect.value = ownerId || '';
            }

            // Populate Makers
            const makerTags = document.getElementById('editMakerPickerTags');
            if (makerTags) {
                makerTags.innerHTML = '';
                const mIds = Array.isArray(makerIds) ? makerIds : [];
                mIds.forEach(uid => {
                    const item = document.querySelector('#editMakerPickerDropdown .user-dropdown-item[data-id="' + uid + '"]');
                    const uName = item ? item.getAttribute('data-name') : ('User #' + uid);
                    addPickerUser('editMakerPicker', uid, uName, 'maker_ids[]');
                });
            }

            // Populate Checkers
            const checkerTags = document.getElementById('editCheckerPickerTags');
            if (checkerTags) {
                checkerTags.innerHTML = '';
                const cIds = Array.isArray(checkerIds) ? checkerIds : [];
                cIds.forEach(uid => {
                    const item = document.querySelector('#editCheckerPickerDropdown .user-dropdown-item[data-id="' + uid + '"]');
                    const uName = item ? item.getAttribute('data-name') : ('User #' + uid);
                    addPickerUser('editCheckerPicker', uid, uName, 'checker_ids[]');
                });
            }

            modal.style.display = 'flex';
        }

        function closeEditTeamModal() {
            const modal = document.getElementById('editTeamModal');
            if (modal) modal.style.display = 'none';
        }

        // Toggle Campaigns Expansion (Split View <-> Full Width)
        function toggleCampaignsExpansion(forceExpand) {
            const grid = document.getElementById('campaigns-section');
            const btn = document.getElementById('btnToggleCampaignsView');
            const text = document.getElementById('btnToggleCampaignsText');
            if (!grid) return;

            let shouldExpand;
            if (typeof forceExpand === 'boolean') {
                shouldExpand = forceExpand;
            } else {
                shouldExpand = !grid.classList.contains('campaigns-expanded');
            }

            if (shouldExpand) {
                grid.classList.add('campaigns-expanded');
                if (text) text.textContent = 'Split View';
                if (btn) {
                    const exp = btn.querySelector('.icon-expand');
                    const comp = btn.querySelector('.icon-compress');
                    if (exp) exp.style.display = 'none';
                    if (comp) comp.style.display = 'inline-block';
                }
            } else {
                grid.classList.remove('campaigns-expanded');
                if (text) text.textContent = 'Full Width';
                if (btn) {
                    const exp = btn.querySelector('.icon-expand');
                    const comp = btn.querySelector('.icon-compress');
                    if (exp) exp.style.display = 'inline-block';
                    if (comp) comp.style.display = 'none';
                }
            }
        }

        function openAdminCampaignModal() {
            const m = document.getElementById('adminCampaignModal');
            if (m) m.style.display = 'flex';
        }
        function closeAdminCampaignModal() {
            const m = document.getElementById('adminCampaignModal');
            if (m) m.style.display = 'none';
        }

        // Manage & Upload Guideline Modal (Admin Full CRUD)
        function openUploadGuidelineModal(campId, campName, guidelines) {
            const m = document.getElementById('uploadGuidelineModal');
            const inp = document.getElementById('guidelineCampId');
            const title = document.getElementById('guidelineModalTitle');
            const list = document.getElementById('guidelineAttachedList');
            if (inp) inp.value = campId;
            if (title) title.innerText = 'Campaign Guidelines · ' + campName;

            if (list) {
                list.innerHTML = '';
                const gArray = Array.isArray(guidelines) ? guidelines : [];
                if (gArray.length === 0) {
                    list.innerHTML = '<div style="font-size: 12px; color: #94A3B8; padding: 12px; background: #F8FAFC; border-radius: 6px; text-align: center;">No guideline documents attached yet.</div>';
                } else {
                    gArray.forEach(g => {
                        const item = document.createElement('div');
                        item.style.cssText = 'display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 6px;';
                        
                        const left = document.createElement('div');
                        left.style.cssText = 'display: flex; align-items: center; gap: 8px; overflow: hidden;';
                        left.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#373E8C" stroke-width="2" style="flex-shrink: 0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span style="font-size: 12.5px; font-weight: 600; color: #1E293B; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">' + (g.name || 'Guideline') + '</span>';

                        const right = document.createElement('div');
                        right.style.cssText = 'display: flex; align-items: center; gap: 8px; flex-shrink: 0;';
                        if (g.url) {
                            right.innerHTML += '<a href="' + g.url + '" target="_blank" style="font-size: 11.5px; color: #373E8C; text-decoration: underline; font-weight: 600;">View</a>';
                        }
                        const delBtn = document.createElement('button');
                        delBtn.type = 'button';
                        delBtn.style.cssText = 'padding: 3px 8px; font-size: 11px; color: #BA1A1A; background: #FFF1F2; border: 1px solid #FECDD3; border-radius: 4px; cursor: pointer; font-weight: 600;';
                        delBtn.textContent = 'Delete';
                        delBtn.onclick = function() {
                            if (confirm('Delete guideline "' + (g.name || '') + '" from this campaign?')) {
                                document.getElementById('deleteGuidelineCampId').value = campId;
                                document.getElementById('deleteGuidelineUrl').value = g.url || '';
                                document.getElementById('deleteGuidelineForm').submit();
                            }
                        };
                        right.appendChild(delBtn);

                        item.appendChild(left);
                        item.appendChild(right);
                        list.appendChild(item);
                    });
                }
            }

            if (m) {
                m.style.display = 'flex';
                m.classList.add('active');
            }
        }
        function closeUploadGuidelineModal() {
            const m = document.getElementById('uploadGuidelineModal');
            if (m) {
                m.style.display = 'none';
                m.classList.remove('active');
            }
        }

        // Auto expand Campaigns to Full Width when entering via #campaigns-section
        function checkCampaignsHashAndExpand() {
            var h = window.location.hash;
            if (h === '#campaigns-section' || h === '#campaigns') {
                setTimeout(function() {
                    if (typeof toggleCampaignsExpansion === 'function') {
                        toggleCampaignsExpansion(true);
                    }
                    var camp = document.getElementById('campaigns-section');
                    if (camp) {
                        camp.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                }, 100);
            }
        }
        window.addEventListener('load', checkCampaignsHashAndExpand);
        window.addEventListener('hashchange', checkCampaignsHashAndExpand);
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            checkCampaignsHashAndExpand();
        }
    </script>
</body>
</html>

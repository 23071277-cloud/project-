<?php
/**
 * Template Name: Checker Dashboard
 * Description: Realtime Checker Workspace connected to WordPress Database & Project Core System (RBAC, AI Scoring, Review Queue)
 * 100% English UI, zero mock campaigns/assets, dynamic empty states.
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

// 1.1 TỰ ĐỘNG CHUYỂN SANG DETAIL ASSET VIEW NẾU CÓ PARAM CAMPAIGN_ID HOẶC VIEW=DETAIL
if (isset($_GET['campaign_id']) || (isset($_GET['view']) && $_GET['view'] === 'detail')) {
    if (file_exists(__DIR__ . '/detail-asset-checker.php')) {
        include __DIR__ . '/detail-asset-checker.php';
        exit;
    }
}

// 2. XỬ LÝ LOGOUT
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

// 3. XỬ LÝ QUYẾT ĐỊNH DUYỆT (APPROVE / REJECT) TỪ CHECKER
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

// 4. LẤY THÔNG TIN USER VÀ PHÂN QUYỀN RBAC (CHECKER ONLY)
$currentUser = function_exists('pcs_enforce_rbac') ? pcs_enforce_rbac(['CHECKER']) : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'CHECKER');

$kpis = function_exists('get_checker_kpis') ? get_checker_kpis() : [
    'in_review' => 0,
    'scoring'   => 0,
    'approved'  => 0,
    'rejected'  => 0,
    'draft'     => 0,
    'released'  => 0,
    'total'     => 0
];

$pendingList = function_exists('get_checker_pending_reviews') ? get_checker_pending_reviews(3) : [];
$campaigns = function_exists('get_all_campaigns') ? get_all_campaigns('all') : [];
$queue = function_exists('get_checker_review_queue') ? get_checker_review_queue('all') : [];

$inReviewCount = $kpis['in_review'];
$scoringCount  = $kpis['scoring'];
$approvedCount = $kpis['approved'];
$rejectedCount = $kpis['rejected'];
$totalCamps    = count($campaigns);
$totalQueue    = count($queue);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Checker Workspace - Brand Compliance AI</title>
    
    <!-- Google Fonts Plus Jakarta Sans & JetBrains Mono -->
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
            outline: 2px solid #4F56A6 !important;
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

        /* Checker Profile Button & Dropdown */
        .profile-container {
            position: relative;
        }

        .checker-profile-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: background 0.2s ease;
            user-select: none;
        }

        .checker-profile-badge:hover {
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
            flex-shrink: 0;
        }

        .dropdown-user-details {
            display: flex;
            flex-direction: column;
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
        }

        .dropdown-divider {
            height: 1px;
            background: #F1F5F9;
            margin: 4px 0;
        }

        .dropdown-logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            color: #EF4444;
            font-size: 13px;
            font-weight: 600;
            transition: background 0.15s;
        }

        .dropdown-logout-btn:hover {
            background: #FEF2F2;
        }

        /* ================= 2. MAIN CONTAINER ================= */
        .main-wrapper {
            position: relative;
            z-index: 10;
            padding-top: 32px;
            padding-bottom: 80px;
        }

        .alert-toast {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            font-weight: 600;
        }
        .alert-toast.success {
            background: #E8F8F0;
            border: 1px solid #A7F3D0;
            color: #065F46;
        }
        .alert-toast.danger {
            background: #FEE2E2;
            border: 1px solid #FECACA;
            color: #991B1B;
        }

        /* Greeting Hero */
        .workspace-hero {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .role-tagline {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.6px;
            color: #373E8C;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .hero-heading {
            font-size: 30px;
            font-weight: 800;
            color: #161B2E;
            letter-spacing: -0.7px;
            line-height: 1.25;
            margin-bottom: 6px;
        }

        .hero-subtitle {
            font-size: 14px;
            color: #64748B;
        }
        .hero-subtitle strong {
            color: #373E8C;
            font-weight: 700;
        }

        /* Section 2: 4-KPI Metric Cards (Aligned with Real System Statuses) */
        .kpi-metrics-bar {
            display: flex;
            background: #FFFFFF;
            border: 1px solid #EBEDFF;
            border-radius: 16px;
            box-shadow: 0px 4px 20px -4px rgba(79, 86, 166, 0.08);
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
            flex-shrink: 0;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* Exact Status Color Coding */
        .kpi-icon-box.status-in-review {
            background-color: #E0E0FF;
            color: #373E8C;
            border: 1px solid #D5D7FF;
        }
        .kpi-icon-box.status-scoring {
            background-color: #EFF6FF;
            color: #0284C7;
            border: 1px solid #BAE6FD;
        }
        .kpi-icon-box.status-approved {
            background-color: #D1FAE5;
            color: #047857;
            border: 1px solid #A7F3D0;
        }
        .kpi-icon-box.status-rejected {
            background-color: #FFDAD6;
            color: #BA1A1A;
            border: 1px solid #FFC7C1;
        }

        .kpi-label {
            font-size: 14px;
            font-weight: 700;
            line-height: 1.3;
        }
        .kpi-label.status-in-review { color: #373E8C; }
        .kpi-label.status-scoring { color: #0284C7; }
        .kpi-label.status-approved { color: #047857; }
        .kpi-label.status-rejected { color: #BA1A1A; }

        /* Section 3: 2-Column Grid (Aside Pending Reviews + Campaigns) */
        .workspace-grid {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 28px;
            margin-bottom: 32px;
            align-items: start;
        }

        /* --- ASIDE: PENDING REVIEW --- */
        .aside-card {
            background: #FFFFFF;
            border: 1px solid #EBEDFF;
            border-radius: 16px;
            box-shadow: 0px 2px 10px -2px rgba(55, 62, 140, 0.04), 0px 8px 30px -4px rgba(55, 62, 140, 0.07);
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .aside-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid #F1F5F9;
        }

        .aside-title-group {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .aside-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 800;
            color: #161B2E;
        }

        .pending-dot {
            width: 8px;
            height: 8px;
            background-color: #373E8C;
            border-radius: 9999px;
            display: inline-block;
        }

        .aside-subtitle {
            font-size: 12px;
            color: #94A3B8;
            font-weight: 500;
        }

        .aside-count-badge {
            background-color: #F3F2FF;
            color: #373E8C;
            font-size: 11.5px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 9999px;
        }

        .pending-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .pending-item-card {
            padding: 14px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            position: relative;
            background: #F6F5FF;
            border: 1px solid #E0E0FF;
            border-left: 4px solid #373E8C;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .pending-item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(55, 62, 140, 0.06);
        }

        .pending-card-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pending-title-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .pending-idx {
            background: rgba(255, 255, 255, 0.9);
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 800;
            color: #373E8C;
        }

        .pending-name {
            font-size: 13px;
            font-weight: 700;
            color: #0F172A;
        }

        /* Status Pills */
        .status-pill {
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: 0.5px;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .status-pill.in_review {
            background-color: #E0E0FF;
            color: #373E8C;
        }
        .status-pill.scoring {
            background-color: #EFF6FF;
            color: #0284C7;
        }
        .status-pill.approved {
            background-color: #D1FAE5;
            color: #047857;
        }
        .status-pill.rejected {
            background-color: #FFDAD6;
            color: #BA1A1A;
        }
        .status-pill.draft {
            background-color: #F1F5F9;
            color: #475569;
        }

        .pending-meta {
            font-size: 11px;
            color: #475569;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .pending-ver-badge {
            font-family: 'JetBrains Mono', monospace;
            font-weight: 700;
            color: #373E8C;
        }

        .pending-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 8px;
            border-top: 1px solid rgba(226, 232, 240, 0.6);
            margin-top: 2px;
        }

        .pending-time {
            font-size: 11px;
            font-weight: 600;
            color: #64748B;
        }

        .pending-action-btn {
            font-size: 11.5px;
            font-weight: 700;
            color: #373E8C;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: color 0.15s;
        }
        .pending-action-btn:hover {
            color: #4F56A6;
            text-decoration: underline;
        }

        /* --- RIGHT COLUMN: CAMPAIGNS CARD --- */
        .campaigns-card {
            background: #FFFFFF;
            border: 1px solid #EBEDFF;
            border-radius: 16px;
            box-shadow: 0px 2px 10px -2px rgba(55, 62, 140, 0.04), 0px 8px 30px -4px rgba(55, 62, 140, 0.07);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .campaigns-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 16px;
            border-bottom: 1px solid #F1F5F9;
        }

        .campaigns-heading-group {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .campaigns-heading {
            font-size: 18px;
            font-weight: 800;
            color: #161B2E;
            letter-spacing: -0.4px;
        }

        .campaigns-subheading {
            font-size: 12px;
            color: #64748B;
            font-weight: 500;
        }

        .filter-pills-bar {
            display: flex;
            align-items: center;
            gap: 4px;
            padding: 4px;
            background-color: #FAF8FF;
            border-radius: 12px;
            border: 1px solid #EBEDFF;
        }

        .filter-pill-btn {
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #64748B;
            transition: all 0.15s;
        }

        .filter-pill-btn.active {
            background-color: #FFFFFF;
            color: #373E8C;
            font-weight: 700;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
        }

        /* Table */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            text-align: left;
        }

        .custom-table th {
            font-size: 11px;
            font-weight: 700;
            color: #94A3B8;
            letter-spacing: 0.5px;
            padding: 12px 14px;
            border-bottom: 1px solid #F1F5F9;
            text-transform: uppercase;
        }

        .custom-table td {
            padding: 16px 14px;
            font-size: 13px;
            border-bottom: 1px solid #F8FAFC;
            vertical-align: middle;
        }

        .custom-table tr:hover td {
            background-color: #FAFCFF;
        }

        .campaign-name-cell {
            font-weight: 700;
            color: #0F172A;
            line-height: 1.3;
        }

        .asset-count-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background-color: #E0E0FF;
            color: #373E8C;
            font-size: 12px;
            font-weight: 700;
            border-radius: 9999px;
        }

        .deadline-text {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: #475569;
            font-weight: 500;
        }

        .progress-box {
            display: flex;
            align-items: center;
            gap: 8px;
            min-width: 130px;
        }

        .progress-bar-track {
            flex: 1;
            height: 6px;
            background-color: #F1F5F9;
            border-radius: 9999px;
            overflow: hidden;
        }

        .progress-bar-fill {
            height: 100%;
            border-radius: 9999px;
            background-color: #373E8C;
        }
        .progress-bar-fill.success { background-color: #10B981; }
        .progress-bar-fill.warning { background-color: #F59E0B; }
        .progress-bar-fill.danger { background-color: #BA1A1A; }

        .progress-label-text {
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            white-space: nowrap;
        }

        /* Status Badge */
        .status-pill-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
        }
        .status-pill-badge.in_review {
            background-color: #F3F2FF;
            color: #373E8C;
            border: 1px solid #E0E0FF;
        }
        .status-pill-badge.in_review .status-dot { background-color: #373E8C; }

        .status-pill-badge.scoring {
            background-color: #F0F9FF;
            color: #075985;
            border: 1px solid #BAE6FD;
        }
        .status-pill-badge.scoring .status-dot { background-color: #0284C7; }

        .status-pill-badge.rejected {
            background-color: #FFDAD6;
            color: #BA1A1A;
            border: 1px solid #FFC7C1;
        }
        .status-pill-badge.rejected .status-dot { background-color: #BA1A1A; }

        .status-pill-badge.approved {
            background-color: #D1FAE5;
            color: #047857;
            border: 1px solid #A7F3D0;
        }
        .status-pill-badge.approved .status-dot { background-color: #10B981; }

        .status-pill-badge.draft {
            background-color: #F1F5F9;
            color: #475569;
            border: 1px solid #E2E8F0;
        }
        .status-pill-badge.draft .status-dot { background-color: #64748B; }

        .status-dot {
            width: 6px;
            height: 6px;
            border-radius: 9999px;
        }

        .btn-view-campaign {
            padding: 4px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            color: #475569;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            transition: all 0.15s;
        }
        .btn-view-campaign:hover {
            background: #F1F5F9;
            color: #0F172A;
        }

        /* Nested Accordion for Assets */
        .nested-asset-row {
            background-color: #FAF8FF !important;
        }
        .nested-asset-box {
            background: #FFFFFF;
            border: 1px solid #E0E0FF;
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            margin: 4px 0;
        }
        .nested-asset-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #F1F5F9;
        }
        .nested-asset-title {
            font-size: 12px;
            font-weight: 800;
            color: #373E8C;
            letter-spacing: 0.5px;
        }

        /* AI Assessment Pills */
        .ai-pill {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .ai-pill.pass {
            background-color: #D1FAE5;
            color: #047857;
            border: 1px solid #A7F3D0;
        }
        .ai-pill.fail {
            background-color: #FFDAD6;
            color: #BA1A1A;
            border: 1px solid #FFC7C1;
        }
        .ai-pill.needs_review {
            background-color: #FFDEA5;
            color: #7B5800;
            border: 1px solid #FFE3A8;
        }

        .btn-table-open-review {
            background-color: #373E8C;
            color: #FFFFFF;
            font-size: 11px;
            font-weight: 700;
            padding: 5px 12px;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
            transition: background 0.15s, transform 0.1s;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-table-open-review:hover {
            background-color: #4F56A6;
            transform: translateY(-1px);
        }

        /* ================= 3. SECTION 4: FULL-WIDTH REVIEW QUEUE ================= */
        .queue-section {
            background: #FFFFFF;
            border: 2px solid #E0E0FF;
            border-radius: 16px;
            box-shadow: 0px 2px 10px -2px rgba(55, 62, 140, 0.04), 0px 8px 30px -4px rgba(55, 62, 140, 0.08);
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }

        .queue-top-gradient-bar {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #373E8C 0%, #4F56A6 50%, #E0E0FF 100%);
        }

        .queue-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 16px;
            border-bottom: 1px solid #F1F5F9;
        }

        .queue-title-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .queue-title {
            font-size: 20px;
            font-weight: 800;
            color: #161B2E;
            letter-spacing: -0.5px;
        }

        .queue-counter-badge {
            background-color: #E0E0FF;
            color: #373E8C;
            font-size: 12px;
            font-weight: 800;
            padding: 3px 10px;
            border-radius: 9999px;
            border: 1px solid #D5D7FF;
        }

        .queue-filters {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px;
            background-color: #FAF8FF;
            border-radius: 12px;
            border: 1px solid #EBEDFF;
        }

        .queue-filter-btn {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            transition: all 0.15s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .queue-filter-btn.active {
            background-color: #373E8C;
            color: #FFFFFF;
            font-weight: 700;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.08);
        }

        .queue-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .queue-table th {
            background-color: rgba(250, 248, 255, 0.7);
            font-size: 11px;
            font-weight: 700;
            color: #94A3B8;
            letter-spacing: 0.5px;
            padding: 14px 16px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            text-transform: uppercase;
        }

        .queue-table th:first-child { border-radius: 10px 0 0 10px; }
        .queue-table th:last-child { border-radius: 0 10px 10px 0; }

        .queue-table td {
            padding: 18px 16px;
            border-bottom: 1px solid #F1F5F9;
            vertical-align: middle;
            font-size: 13px;
        }

        .queue-asset-cell {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .asset-icon-box {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background-color: #F6F5FF;
            border: 1px solid #E0E0FF;
            color: #373E8C;
        }

        .asset-name-title {
            font-size: 14px;
            font-weight: 700;
            color: #0F172A;
            line-height: 1.25;
        }

        .asset-type-sub {
            font-size: 11px;
            color: #94A3B8;
            font-weight: 600;
        }

        .queue-campaign-title {
            font-size: 13px;
            font-weight: 600;
            color: #334155;
        }

        .version-tag {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 2px 8px;
            border-radius: 4px;
            background-color: #E0E0FF;
            color: #373E8C;
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            font-weight: 700;
        }

        .maker-cell-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .maker-mini-avatar {
            width: 22px;
            height: 22px;
            border-radius: 9999px;
            background: #E2E8F0;
            color: #334155;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .maker-name-text {
            font-size: 12.5px;
            color: #475569;
            font-weight: 500;
        }

        .btn-open-review-primary {
            padding: 8px 18px;
            background-color: #373E8C;
            color: #FFFFFF;
            font-size: 12px;
            font-weight: 700;
            border-radius: 10px;
            box-shadow: 0px 4px 14px rgba(55, 62, 140, 0.25);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s;
        }
        .btn-open-review-primary:hover {
            background-color: #4F56A6;
            transform: translateY(-1px);
            box-shadow: 0px 6px 18px rgba(55, 62, 140, 0.35);
        }

        /* Empty State */
        .empty-state-row td {
            text-align: center;
            padding: 60px 20px;
            color: #64748B;
        }
        .empty-icon {
            font-size: 36px;
            margin-bottom: 12px;
        }
        .empty-title {
            font-size: 16px;
            font-weight: 700;
            color: #161B2E;
            margin-bottom: 4px;
        }
        .empty-desc {
            font-size: 13.5px;
            color: #64748B;
        }

        /* ================= 4. MODAL ================= */
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
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .asset-preview-card {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .preview-img-box {
            width: 100%;
            height: 260px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .preview-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .ai-checks-column {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .check-card {
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 12px;
            background: #FFFFFF;
        }

        .check-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .check-name {
            font-size: 13px;
            font-weight: 700;
            color: #0F172A;
        }

        .check-quote {
            font-size: 11.5px;
            color: #64748B;
            line-height: 1.4;
        }

        .decision-form {
            grid-column: 1 / -1;
            display: flex;
            flex-direction: column;
            gap: 14px;
            padding-top: 16px;
            border-top: 1px solid #F1F5F9;
        }

        .form-label {
            font-size: 13px;
            font-weight: 700;
            color: #161B2E;
        }

        .review-textarea {
            width: 100%;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            padding: 12px;
            font-family: inherit;
            font-size: 13.5px;
            color: #0F172A;
            resize: vertical;
            min-height: 80px;
        }
        .review-textarea:focus {
            outline: none;
            border-color: #373E8C;
            box-shadow: 0 0 0 3px rgba(55, 62, 140, 0.12);
        }

        .decision-buttons {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }

        .btn-decision-reject {
            background-color: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FECACA;
            font-weight: 700;
            font-size: 13.5px;
            padding: 10px 20px;
            border-radius: 10px;
            transition: all 0.15s;
        }
        .btn-decision-reject:hover {
            background-color: #FCA5A5;
        }

        .btn-decision-approve {
            background-color: #059669;
            color: #FFFFFF;
            font-weight: 700;
            font-size: 13.5px;
            padding: 10px 22px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(5, 150, 105, 0.25);
            transition: all 0.15s;
        }
        .btn-decision-approve:hover {
            background-color: #047857;
            box-shadow: 0 6px 16px rgba(5, 150, 105, 0.35);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .workspace-grid {
                grid-template-columns: 1fr;
            }
            .kpi-metrics-bar {
                flex-wrap: wrap;
            }
            .kpi-card {
                flex: 1 1 calc(50% - 12px);
                border-right: none !important;
                border-bottom: 1px solid #F1F5F9;
            }
            .modal-body {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 20px;
            }
            .nav-links {
                display: none;
            }
            .kpi-card {
                flex: 1 1 100%;
            }
            .workspace-hero {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>
<body>

    <!-- Ambient Glowing Background -->
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

            <?php if (!empty($decisionAlert)): ?>
                <div class="alert-toast <?= $decisionSuccess ? 'success' : 'danger' ?>">
                    <span><?= $decisionSuccess ? '✓' : '⚠' ?></span>
                    <span><?= htmlspecialchars($decisionAlert) ?></span>
                </div>
            <?php endif; ?>

            <!-- Greeting Hero -->
            <section class="workspace-hero">
                <div>
                    <div class="role-tagline">CHECKER WORKSPACE</div>
                    <h1 class="hero-heading">Good morning, <?= htmlspecialchars($currentUser['first_name'] ?? 'Hoang Anh') ?></h1>
                    <p class="hero-subtitle">
                        <?php if ($inReviewCount > 0): ?>
                            <strong><?= $inReviewCount ?> versions</strong> are waiting for your review.
                        <?php else: ?>
                            All assigned reviews are up to date.
                        <?php endif; ?>
                    </p>
                </div>
            </section>

            <!-- Section 2: 4-KPI Metric Cards (Exact 4 System Actionable Statuses) -->
            <section class="kpi-metrics-bar">
                <div class="kpi-card">
                    <div class="kpi-icon-box status-in-review"><?= $inReviewCount ?></div>
                    <div class="kpi-label status-in-review">In Review</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-box status-scoring"><?= $scoringCount ?></div>
                    <div class="kpi-label status-scoring">Scoring</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-box status-approved"><?= $approvedCount ?></div>
                    <div class="kpi-label status-approved">Approved</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-icon-box status-rejected"><?= $rejectedCount ?></div>
                    <div class="kpi-label status-rejected">Rejected</div>
                </div>
            </section>

            <!-- Section 3: 2-Column Grid (Aside Pending Review + Campaigns Table) -->
            <div class="workspace-grid">
                
                <!-- Aside: Pending Review -->
                <aside class="aside-card">
                    <div class="aside-header">
                        <div class="aside-title-group">
                            <h2 class="aside-title">
                                <span class="pending-dot"></span>
                                <span>Pending Review</span>
                            </h2>
                            <p class="aside-subtitle">Assets currently awaiting your decision</p>
                        </div>
                        <span class="aside-count-badge"><?= count($pendingList) ?> items</span>
                    </div>

                    <div class="pending-list">
                        <?php if (!empty($pendingList)): ?>
                            <?php foreach ($pendingList as $idx => $p): ?>
                                <div class="pending-item-card">
                                    <div class="pending-card-top">
                                        <div class="pending-title-wrap">
                                            <span class="pending-idx"><?= str_pad($idx + 1, 2, '0', STR_PAD_LEFT) ?></span>
                                            <span class="pending-name"><?= htmlspecialchars($p['asset_name']) ?></span>
                                        </div>
                                        <span class="status-pill in_review">
                                            IN REVIEW
                                        </span>
                                    </div>
                                    <div class="pending-meta">
                                        <span><?= htmlspecialchars($p['campaign_name']) ?> ·</span>
                                        <span class="pending-ver-badge"><?= htmlspecialchars($p['version_label']) ?></span>
                                    </div>
                                    <div class="pending-footer">
                                        <span class="pending-time"><?= htmlspecialchars($p['submitted']) ?></span>
                                        <button class="pending-action-btn" onclick="openReviewModal(<?= htmlspecialchars(json_encode($p)) ?>)">
                                            <span>Open Review</span>
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: 40px 16px; text-align: center; color: #94A3B8;">
                                <div style="font-size: 28px; margin-bottom: 8px;">✓</div>
                                <p style="font-weight: 700; color: #161B2E; margin-bottom: 4px;">No pending reviews</p>
                                <p style="font-size: 12px;">All assets have been processed.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </aside>

                <!-- Right Column: Campaigns Table -->
                <section class="campaigns-card">
                    <div class="campaigns-top-bar">
                        <div class="campaigns-heading-group">
                            <h2 class="campaigns-heading">Campaigns</h2>
                            <p class="campaigns-subheading">Campaigns with reviews assigned to you</p>
                        </div>
                        <div class="filter-pills-bar">
                            <button class="filter-pill-btn active" onclick="filterCampaignRows('all', this)">All (<?= $totalCamps ?>)</button>
                            <button class="filter-pill-btn" onclick="filterCampaignRows('in_review', this)">In Review (<?= $inReviewCount ?>)</button>
                            <button class="filter-pill-btn" onclick="filterCampaignRows('approved', this)">Approved (<?= $approvedCount ?>)</button>
                            <button class="filter-pill-btn" onclick="filterCampaignRows('rejected', this)">Rejected (<?= $rejectedCount ?>)</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="custom-table" id="campaignsTable">
                            <thead>
                                <tr>
                                    <th>CAMPAIGN</th>
                                    <th style="text-align: center;">ASSETS TO REVIEW</th>
                                    <th>DEADLINE</th>
                                    <th>PROGRESS</th>
                                    <th>STATUS</th>
                                    <th style="text-align: right;">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($campaigns)): ?>
                                    <?php foreach ($campaigns as $camp): 
                                        $assetsToReview = 0;
                                        foreach ($camp['assets'] as $a) {
                                            if ($a['status'] === 'in_review') $assetsToReview++;
                                        }
                                        $statusKey = strtolower(str_replace(' ', '_', $camp['status']));
                                        if (!in_array($statusKey, ['in_review', 'scoring', 'approved', 'rejected', 'draft'])) {
                                            $statusKey = 'in_review';
                                        }
                                    ?>
                                        <tr class="camp-row" data-group="<?= $camp['filter_group'] ?>">
                                            <td class="campaign-name-cell"><?= htmlspecialchars($camp['name']) ?></td>
                                            <td style="text-align: center;">
                                                <span class="asset-count-badge"><?= $assetsToReview ?></span>
                                            </td>
                                            <td class="deadline-text"><?= htmlspecialchars($camp['deadline']) ?></td>
                                            <td>
                                                <div class="progress-box">
                                                    <div class="progress-bar-track">
                                                        <div class="progress-bar-fill <?= $camp['status_type'] ?>" style="width: <?= $camp['percent'] ?>%;"></div>
                                                    </div>
                                                    <span class="progress-label-text"><?= $camp['done'] ?>/<?= $camp['total'] ?> (<?= $camp['percent'] ?>%)</span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-pill-badge <?= $statusKey ?>">
                                                    <span class="status-dot"></span>
                                                    <span><?= htmlspecialchars($camp['status']) ?></span>
                                                </span>
                                            </td>
                                            <td style="text-align: right; white-space: nowrap;">
                                                <button class="btn-view-campaign" onclick="toggleCampaignAccordion(<?= $camp['id'] ?>, this)" style="margin-right: 6px;">
                                                    Items
                                                </button>
                                                <a href="?campaign_id=<?= $camp['id'] ?>" class="btn-view-campaign" style="background: #1F2674; color: #FFFFFF; display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">
                                                    View Detail →
                                                </a>
                                            </td>
                                        </tr>

                                        <!-- Nested Line Items Accordion -->
                                        <?php if (!empty($camp['assets'])): ?>
                                            <tr id="camp-accordion-<?= $camp['id'] ?>" class="nested-asset-row" style="display: none;">
                                                <td colspan="6">
                                                    <div class="nested-asset-box">
                                                        <div class="nested-asset-header">
                                                            <div class="nested-asset-title">REVIEW ITEMS · <?= count($camp['assets']) ?> assets registered</div>
                                                        </div>
                                                        <table class="custom-table" style="background: transparent;">
                                                            <thead>
                                                                <tr>
                                                                    <th style="padding: 8px 10px;">ASSET</th>
                                                                    <th style="padding: 8px 10px;">VERSION</th>
                                                                    <th style="padding: 8px 10px;">MAKER</th>
                                                                    <th style="padding: 8px 10px;">AI ASSESSMENT</th>
                                                                    <th style="padding: 8px 10px; text-align: right;">ACTION</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($camp['assets'] as $asset): 
                                                                    $aiPill = 'pass';
                                                                    $aiLabel = 'PASS';
                                                                    if ($asset['badge_type'] === 'danger') { $aiPill = 'fail'; $aiLabel = 'FAIL'; }
                                                                    elseif ($asset['badge_type'] === 'warning') { $aiPill = 'needs_review'; $aiLabel = 'NEEDS REVIEW'; }
                                                                ?>
                                                                    <tr>
                                                                        <td style="font-weight: 700; color: #0F172A;"><?= htmlspecialchars($asset['name']) ?></td>
                                                                        <td style="font-family: 'JetBrains Mono', monospace; font-weight: 700; color: #373E8C;">V<?= $asset['current_version_num'] ?></td>
                                                                        <td style="color: #475569; font-weight: 500;">Cam Anh Hoang</td>
                                                                        <td>
                                                                            <span class="ai-pill <?= $aiPill ?>"><?= $aiLabel ?></span>
                                                                        </td>
                                                                        <td style="text-align: right;">
                                                                            <button class="btn-table-open-review" onclick="openReviewModalFromAsset(<?= htmlspecialchars(json_encode($asset)) ?>, <?= htmlspecialchars(json_encode($camp['name'])) ?>)">
                                                                                Open Review
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>

                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="empty-state-row">
                                        <td colspan="6">
                                            <div class="empty-icon">📁</div>
                                            <p class="empty-title">No campaigns found</p>
                                            <p class="empty-desc">No active campaigns assigned for review.</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- ================= 3. SECTION 4: REVIEW QUEUE ================= -->
            <section class="queue-section">
                <div class="queue-top-gradient-bar"></div>

                <div class="queue-header">
                    <div class="queue-title-wrap">
                        <h2 class="queue-title">Review Queue</h2>
                        <span class="queue-counter-badge"><?= $inReviewCount ?> Pending Decisions</span>
                    </div>

                    <div class="queue-filters">
                        <button class="queue-filter-btn active" onclick="filterQueue('all', this)">All (<?= $totalQueue ?>)</button>
                        <button class="queue-filter-btn" onclick="filterQueue('in_review', this)">In Review (<?= $inReviewCount ?>)</button>
                        <button class="queue-filter-btn" onclick="filterQueue('scoring', this)">Scoring (<?= $scoringCount ?>)</button>
                        <button class="queue-filter-btn" onclick="filterQueue('oldest', this)">Oldest First</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="queue-table" id="queueTable">
                        <thead>
                            <tr>
                                <th>ASSET</th>
                                <th>CAMPAIGN</th>
                                <th style="text-align: center;">VERSION</th>
                                <th>MAKER</th>
                                <th>AI ASSESSMENT</th>
                                <th>SUBMITTED</th>
                                <th>DEADLINE</th>
                                <th>STATUS</th>
                                <th style="text-align: right;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($queue)): ?>
                                <?php foreach ($queue as $item): 
                                    $aiClass = 'pass';
                                    if ($item['ai_status'] === 'FAIL') $aiClass = 'fail';
                                    elseif ($item['ai_status'] === 'NEEDS REVIEW') $aiClass = 'needs_review';
                                ?>
                                    <tr class="queue-row" data-status="<?= $item['status'] ?>">
                                        <td>
                                            <div class="queue-asset-cell">
                                                <div class="asset-icon-box">
                                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>
                                                </div>
                                                <div>
                                                    <div class="asset-name-title"><?= htmlspecialchars($item['asset_name']) ?></div>
                                                    <div class="asset-type-sub"><?= htmlspecialchars($item['asset_type']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="queue-campaign-title"><?= htmlspecialchars($item['campaign_name']) ?></div>
                                        </td>
                                        <td style="text-align: center;">
                                            <span class="version-tag"><?= htmlspecialchars($item['version_label']) ?></span>
                                        </td>
                                        <td>
                                            <div class="maker-cell-wrap">
                                                <div class="maker-mini-avatar"><?= htmlspecialchars($item['maker_initials']) ?></div>
                                                <span class="maker-name-text"><?= htmlspecialchars($item['maker_name']) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="ai-pill <?= $aiClass ?>">
                                                <?php if ($aiClass === 'pass'): ?>
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
                                                <?php elseif ($aiClass === 'fail'): ?>
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                <?php else: ?>
                                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/></svg>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($item['ai_status']) ?></span>
                                            </span>
                                        </td>
                                        <td style="font-family: 'JetBrains Mono', monospace; font-size: 11.5px; color: #64748B;">
                                            <?= htmlspecialchars($item['submitted']) ?>
                                        </td>
                                        <td style="font-family: 'JetBrains Mono', monospace; font-size: 12px; font-weight: 700; color: #1E293B;">
                                            <?= htmlspecialchars($item['deadline']) ?>
                                        </td>
                                        <td>
                                            <span class="status-pill-badge in_review">
                                                <span class="status-dot"></span>
                                                <span>In Review</span>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <button class="btn-open-review-primary" onclick="openReviewModal(<?= htmlspecialchars(json_encode($item)) ?>)">
                                                <span>Open Review</span>
                                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="empty-state-row">
                                    <td colspan="9">
                                        <div class="empty-icon">📋</div>
                                        <p class="empty-title">No assets waiting for review</p>
                                        <p class="empty-desc">There are currently no assets in the review queue.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>

    <!-- ================= 4. REVIEW & DECISION MODAL ================= -->
    <div class="modal-overlay" id="reviewModal">
        <div class="modal-window">
            <div class="modal-header">
                <div>
                    <h3 class="modal-title" id="modalAssetTitle">Review Marketing Asset</h3>
                    <p style="font-size: 12px; color: #64748B;" id="modalAssetMeta">Campaign Name · Version</p>
                </div>
                <button class="btn-close-modal" onclick="closeReviewModal()">&times;</button>
            </div>
            
            <form method="POST" action="" class="modal-body">
                <input type="hidden" name="action" value="checker_decision">
                <input type="hidden" name="version_id" id="modalVersionId" value="0">
                <input type="hidden" name="decision" id="modalDecisionInput" value="approved">

                <!-- Left Column: Asset Preview -->
                <div class="asset-preview-card">
                    <span style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">Asset Preview</span>
                    <div class="preview-img-box">
                        <img id="modalPreviewImg" src="" alt="Asset Preview" class="preview-img" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'300\' height=\'200\' fill=\'%23F1F5F9\'><rect width=\'100%\' height=\'100%\'/><text x=\'50%\' y=\'50%\' dominant-baseline=\'middle\' text-anchor=\'middle\' fill=\'%2394A3B8\' font-size=\'14\'>No Preview Available</text></svg>'">
                    </div>
                    <div style="font-size: 12px; color: #475569;">
                        <strong>Submitted by:</strong> <span id="modalMakerName">Maker</span>
                    </div>
                </div>

                <!-- Right Column: AI Pre-flight Breakdown -->
                <div class="ai-checks-column">
                    <div style="display: flex; align-items: center; justify-content: space-between;">
                        <span style="font-size: 12px; font-weight: 700; color: #64748B; text-transform: uppercase;">AI Pre-flight Assessment</span>
                        <span class="ai-pill pass" id="modalOverallBadge">PASS</span>
                    </div>

                    <div id="modalChecksList" style="display: flex; flex-direction: column; gap: 10px;">
                        <!-- Injected via JavaScript -->
                    </div>
                </div>

                <!-- Bottom: Decision Form -->
                <div class="decision-form">
                    <label class="form-label" for="reviewNotes">Checker Notes & Feedback</label>
                    <textarea class="review-textarea" id="reviewNotes" name="review_note" placeholder="Provide compliance feedback, observations, or instructions (Mandatory if rejecting)..."></textarea>
                    
                    <div class="decision-buttons">
                        <button type="button" class="btn-decision-reject" onclick="submitDecision('rejected')">
                            Reject / Request Changes
                        </button>
                        <button type="button" class="btn-decision-approve" onclick="submitDecision('approved')">
                            Approve Asset
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= JAVASCRIPT ================= -->
    <script>
        // 1. Profile Dropdown Toggle
        const profileContainer = document.getElementById('profileContainer');
        const btnToggleProfile = document.getElementById('btnToggleProfile');

        if (btnToggleProfile) {
            btnToggleProfile.addEventListener('click', function(e) {
                e.stopPropagation();
                profileContainer.classList.toggle('active');
            });
        }

        document.addEventListener('click', function(e) {
            if (profileContainer && !profileContainer.contains(e.target)) {
                profileContainer.classList.remove('active');
            }
        });

        // 2. Toggle Campaign Nested Accordion
        function toggleCampaignAccordion(campId, btn) {
            const accordionRow = document.getElementById('camp-accordion-' + campId);
            if (!accordionRow) return;

            if (accordionRow.style.display === 'none' || accordionRow.style.display === '') {
                accordionRow.style.display = 'table-row';
                btn.textContent = 'Close';
            } else {
                accordionRow.style.display = 'none';
                btn.textContent = 'View Campaign';
            }
        }

        // 3. Filter Campaigns Table
        function filterCampaignRows(group, btn) {
            const container = btn.parentElement;
            container.querySelectorAll('.filter-pill-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const rows = document.querySelectorAll('.camp-row');
            rows.forEach(row => {
                const rowGroup = row.getAttribute('data-group');
                if (group === 'all' || rowGroup === group) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // 4. Filter Review Queue Table
        function filterQueue(status, btn) {
            const container = btn.parentElement;
            container.querySelectorAll('.queue-filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const rows = document.querySelectorAll('.queue-row');
            rows.forEach(row => {
                const rowStatus = row.getAttribute('data-status');
                if (status === 'all' || status === 'oldest' || rowStatus === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // 5. Review & Decision Modal
        const reviewModal = document.getElementById('reviewModal');

        function openReviewModal(item) {
            if (!item) return;

            document.getElementById('modalAssetTitle').textContent = item.asset_name || 'Marketing Asset';
            document.getElementById('modalAssetMeta').textContent = (item.campaign_name || 'Campaign') + ' · ' + (item.version_label || 'V1');
            document.getElementById('modalVersionId').value = item.version_id || 0;
            document.getElementById('modalMakerName').textContent = item.maker_name || 'Cam Anh Hoang';

            const previewImg = document.getElementById('modalPreviewImg');
            if (item.file_url) {
                previewImg.src = item.file_url;
            } else {
                previewImg.src = 'images/logo.png';
            }

            const overallBadge = document.getElementById('modalOverallBadge');
            overallBadge.textContent = item.ai_status || 'PASS';
            overallBadge.className = 'ai-pill ' + (item.ai_status === 'FAIL' ? 'fail' : (item.ai_status === 'NEEDS REVIEW' ? 'needs_review' : 'pass'));

            // Render AI Checks
            const checksList = document.getElementById('modalChecksList');
            checksList.innerHTML = '';

            const checks = item.checks && item.checks.length > 0 ? item.checks : [
                { criterion: 'Foreign Logo Misuse', score: 95, verdict: 'PASS', quote: 'No unauthorized third-party brand logos detected.' },
                { criterion: 'Brand Guidelines Check', score: 88, verdict: 'PASS', quote: 'Colors and typography align with corporate standards.' },
                { criterion: 'MAS Financial Regulations', score: 92, verdict: 'PASS', quote: 'Mandatory Singapore financial disclosures present.' }
            ];

            checks.forEach(chk => {
                const card = document.createElement('div');
                card.className = 'check-card';
                const verdictClass = chk.score >= 85 ? 'pass' : (chk.score >= 70 ? 'needs_review' : 'fail');
                card.innerHTML = `
                    <div class="check-card-header">
                        <span class="check-name">${chk.criterion}</span>
                        <span class="ai-pill ${verdictClass}">${chk.score}/100 · ${chk.verdict || (chk.score >= 85 ? 'PASS' : 'FLAGGED')}</span>
                    </div>
                    <p class="check-quote">${chk.quote || 'No specific deviations recorded.'}</p>
                `;
                checksList.appendChild(card);
            });

            document.getElementById('reviewNotes').value = '';
            reviewModal.classList.add('active');
        }

        function openReviewModalFromAsset(asset, campaignName) {
            openReviewModal({
                version_id: asset.current_version_id || 0,
                asset_name: asset.name,
                campaign_name: campaignName,
                version_label: 'V' + (asset.current_version_num || 1),
                maker_name: 'Cam Anh Hoang',
                file_url: asset.file_url,
                ai_status: asset.badge_type === 'danger' ? 'FAIL' : (asset.badge_type === 'warning' ? 'NEEDS REVIEW' : 'PASS'),
                checks: []
            });
        }

        function closeReviewModal() {
            reviewModal.classList.remove('active');
        }

        reviewModal.addEventListener('click', function(e) {
            if (e.target === reviewModal) {
                closeReviewModal();
            }
        });

        function submitDecision(decision) {
            const notes = document.getElementById('reviewNotes').value.trim();
            if (decision === 'rejected' && !notes) {
                alert('Please provide a review note explaining the rejection reason (compliance requirement).');
                document.getElementById('reviewNotes').focus();
                return;
            }

            document.getElementById('modalDecisionInput').value = decision;
            document.querySelector('#reviewModal form').submit();
        }
    </script>
</body>
</html>

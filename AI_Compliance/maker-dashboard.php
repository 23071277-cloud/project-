<?php
/*
Template Name: Maker Dashboard
*/

if (file_exists(__DIR__ . '/backend.php')) {
    require_once __DIR__ . '/backend.php';
} elseif (file_exists(__DIR__ . '/inc/backend.php')) {
    require_once __DIR__ . '/inc/backend.php';
} elseif (function_exists('get_template_directory') && file_exists(get_template_directory() . '/backend.php')) {
    require_once get_template_directory() . '/backend.php';
} elseif (function_exists('get_template_directory') && file_exists(get_template_directory() . '/inc/backend.php')) {
    require_once get_template_directory() . '/inc/backend.php';
}

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    if (function_exists('wp_logout')) {
        wp_logout();
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION['logged_in_user']);
        unset($_SESSION['pcs_user_id']);
        session_destroy();
    }
    $target = function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php';
    header('Location: ' . $target . (strpos($target, '?') !== false ? '&' : '?') . 'loggedout=true');
    exit;
}

$uploadAlert = '';

// Handle Campaign Creation
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_campaign') {
    $campTitle = trim($_POST['campaign_title'] ?? '');
    $campDeadline = trim($_POST['campaign_deadline'] ?? '');
    if (!empty($campTitle) && function_exists('handle_create_campaign')) {
        $cid = handle_create_campaign($campTitle, $campDeadline);
        if ($cid) {
            $uploadAlert = 'Campaign "' . htmlspecialchars($campTitle) . '" created successfully!';
        }
    }
}

// Handle Guideline Upload
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_guideline') {
    $campId = (int) ($_POST['campaign_id'] ?? 0);
    if ($campId && function_exists('handle_upload_campaign_guideline')) {
        $gUrl = handle_upload_campaign_guideline($campId);
        if ($gUrl) {
            $uploadAlert = 'Brand Guideline document uploaded successfully!';
        }
    }
}

// Handle Asset Version Upload
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_asset') {
    $campName = $_POST['campaign_name'] ?? 'Marketing Campaign';
    $assetName = $_POST['asset_name'] ?? 'Asset';
    $fileName = (!empty($_FILES['asset_file']['name'])) ? $_FILES['asset_file']['name'] : 'asset_file.png';
    
    $ok = handle_upload_asset_version($campName, $assetName, $fileName);
    if ($ok) {
        $uploadAlert = 'Successfully uploaded ' . htmlspecialchars($fileName) . ' for ' . htmlspecialchars($campName) . '! Status updated to "In Review" awaiting Checker review.';
    }
}

// 1. DỮ LIỆU ĐỘNG TỪ DATABASE THẬT VÀ PHÂN QUYỀN RBAC (MAKER ONLY)
$currentUser = function_exists('pcs_enforce_rbac') ? pcs_enforce_rbac(['MAKER']) : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'MAKER');
$comingUpList = get_coming_up_tasks();
$campaigns = get_all_campaigns();
$scheduleDays = get_realtime_weekly_schedule();

$totalCamps = count($campaigns);
$kpis = function_exists('get_checker_kpis') ? get_checker_kpis() : [];
$inReviewCount = $kpis['in_review'] ?? 0;
$rejectedCount = $kpis['rejected'] ?? 0;
$approvedCount = $kpis['approved'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maker Workspace - Brand Compliance AI</title>
    
    <!-- Google Fonts Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        body {
            background-color: #FAF8FF;
            color: #161B2E;
            min-height: 100vh;
            overflow-x: hidden;
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

        .container {
            max-width: 1320px;
            width: 100%;
            margin: 0 auto;
            padding: 0 32px;
        }

        /* ================= 1. HEADER (LOGO CHUẨN + PROFILE) ================= */
        .top-navbar {
            background: #FFFFFF;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            height: 80px;
            position: sticky;
            top: 0;
            z-index: 50;
            display: flex;
            align-items: center;
        }

        .nav-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 40px;
        }

        .brand-logo-img {
            height: 36px;
            width: auto;
            max-width: 140px;
            object-fit: contain;
            display: block;
        }

        .nav-menu {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .nav-link {
            font-size: 14.5px;
            font-weight: 600;
            color: #334155;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .nav-link:hover {
            color: #4F56A6;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .v-divider {
            width: 1px;
            height: 24px;
            background-color: #E2E8F0;
        }

        .user-profile-badge {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            position: relative;
            padding: 6px 12px;
            border-radius: 12px;
            transition: background 0.2s;
            user-select: none;
        }

        .user-profile-badge:hover {
            background: #F8FAFC;
        }

        .avatar-box {
            position: relative;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: linear-gradient(135deg, #373E8C 0%, #4F56A6 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 2px 6px rgba(55, 62, 140, 0.25);
        }

        .online-dot {
            position: absolute;
            bottom: 0px;
            right: 0px;
            width: 10px;
            height: 10px;
            background-color: #10B981;
            border: 2px solid #FFFFFF;
            border-radius: 50%;
        }

        .user-meta {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-size: 13px;
            font-weight: 700;
            color: #0F172A;
            line-height: 1.2;
        }

        .user-role-tag {
            font-size: 10.5px;
            font-weight: 700;
            color: #4F56A6;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .dropdown-chevron {
            transition: transform 0.2s ease;
            margin-left: 2px;
        }

        .user-profile-badge.open .dropdown-chevron {
            transform: rotate(180deg);
        }

        .profile-dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: #FFFFFF;
            min-width: 250px;
            border-radius: 16px;
            box-shadow: 0 12px 36px rgba(15, 23, 42, 0.12), 0 2px 6px rgba(15, 23, 42, 0.04);
            border: 1px solid #E2E8F0;
            padding: 12px;
            display: none;
            z-index: 1000;
        }

        .profile-dropdown-menu.show {
            display: block;
            animation: dropdownFadeIn 0.18s ease-out;
        }

        @keyframes dropdownFadeIn {
            from { opacity: 0; transform: translateY(-6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .dropdown-user-header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 6px 8px 12px;
        }

        .dropdown-avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
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
            margin-top: 1px;
        }

        .dropdown-user-role-badge {
            display: inline-block;
            align-self: flex-start;
            font-size: 10px;
            font-weight: 700;
            background: #EEF2FF;
            color: #4338CA;
            padding: 2px 8px;
            border-radius: 999px;
            margin-top: 4px;
            letter-spacing: 0.5px;
        }

        .dropdown-divider {
            height: 1px;
            background: #F1F5F9;
            margin: 6px 0;
        }

        .dropdown-logout-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            color: #DC2626;
            text-decoration: none;
            transition: all 0.15s;
            width: 100%;
            box-sizing: border-box;
        }

        .dropdown-logout-btn:hover {
            background: #FEF2F2;
            color: #B91C1C;
        }

        /* ================= 2. WORKSPACE HERO GREETING ================= */
        .workspace-hero {
            padding: 40px 0 28px;
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
        }

        .role-tagline {
            font-size: 11px;
            font-weight: 700;
            color: #5057A7;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .hero-heading {
            font-size: 32px;
            font-weight: 800;
            color: #161B2E;
            line-height: 1.2;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }

        .attention-notice {
            font-size: 14px;
            color: #64748B;
        }

        .hero-action-buttons {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-secondary {
            background: #FFFFFF;
            color: #373E8C;
            border: 1px solid #E0E0FF;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
            transition: all 0.2s;
        }

        .btn-secondary:hover {
            background: #F8FAFC;
            border-color: #373E8C;
        }

        .btn-primary {
            background: #373E8C;
            color: #FFFFFF;
            padding: 10px 22px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(55, 62, 140, 0.25);
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background: #2B3170;
            transform: translateY(-1px);
        }

        /* ================= 3. WORKSPACE 2-COLUMN GRID ================= */
        .workspace-grid {
            display: grid;
            grid-template-columns: 310px 1fr;
            gap: 28px;
            align-items: start;
            margin-bottom: 48px;
        }

        /* --- 3A. CỘT TRÁI: COMING UP CARD --- */
        .coming-up-card {
            background: #FFFFFF;
            border: 1px solid #EBEDFF;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 2px 8px rgba(31, 42, 90, 0.04);
            display: flex;
            flex-direction: column;
        }

        .card-header-title {
            font-size: 18px;
            font-weight: 700;
            color: #161B2E;
            padding-bottom: 16px;
            border-bottom: 1px solid #F3F2FF;
            margin-bottom: 16px;
        }

        .coming-item {
            padding: 14px 0;
            border-bottom: 1px solid #F8FAFC;
        }

        .coming-item:last-child {
            border-bottom: none;
        }

        .coming-item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .date-badge {
            font-size: 11.5px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 4px;
        }

        .date-badge.danger { background: #FFDAD6; color: #BA1A1A; }
        .date-badge.primary { background: #E0E0FF; color: #373E8C; }
        .date-badge.warning { background: #FFDEA5; color: #7B5800; }

        .status-pill-small {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .status-pill-small.danger { background: rgba(255, 218, 214, 0.6); color: #BA1A1A; }
        .status-pill-small.primary { background: rgba(224, 224, 255, 0.6); color: #373E8C; }
        .status-pill-small.warning { background: #F1F5F9; color: #64748B; }

        .coming-title {
            font-size: 14px;
            font-weight: 700;
            color: #161B2E;
            margin-bottom: 2px;
        }

        .coming-campaign {
            font-size: 12.5px;
            color: #64748B;
            margin-bottom: 8px;
        }

        .coming-link {
            font-size: 12px;
            font-weight: 600;
            color: #373E8C;
            text-align: right;
            display: block;
        }

        .coming-link:hover {
            text-decoration: underline;
        }

        .card-footer-strip {
            padding-top: 18px;
            border-top: 1px solid #F3F2FF;
            text-align: center;
            font-size: 12px;
            color: #94A3B8;
            font-weight: 600;
            margin-top: 12px;
        }

        /* --- 3B. CỘT PHẢI: BẢNG CAMPAIGNS --- */
        .campaigns-card {
            background: #FFFFFF;
            border: 1px solid #EBEDFF;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 2px 8px rgba(31, 42, 90, 0.04);
        }

        .campaigns-top-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 1px solid #F3F2FF;
            margin-bottom: 20px;
        }

        .campaigns-heading {
            font-size: 20px;
            font-weight: 700;
            color: #161B2E;
        }

        .filter-pills {
            display: flex;
            background: #F8FAFC;
            padding: 4px;
            border-radius: 10px;
            border: 1px solid #EBEDFF;
            gap: 4px;
        }

        .pill-btn {
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
            color: #64748B;
            transition: all 0.2s;
        }

        .pill-btn.active {
            background: #FFFFFF;
            color: #373E8C;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }

        /* Table Structure */
        .campaign-table {
            width: 100%;
            border-collapse: collapse;
        }

        .table-head {
            background: rgba(248, 250, 252, 0.85);
            border-radius: 8px;
        }

        .table-head th {
            padding: 12px 14px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748B;
            text-align: left;
        }

        .campaign-row {
            border-top: 1px solid #F3F2FF;
            transition: background 0.15s;
        }

        .campaign-row:hover {
            background-color: #FAFAFF;
        }

        .campaign-row td {
            padding: 18px 14px;
            font-size: 13.5px;
            vertical-align: middle;
        }

        .campaign-title-cell {
            font-weight: 700;
            color: #161B2E;
            max-width: 220px;
        }

        .deadline-cell {
            font-weight: 600;
            color: #161B2E;
            white-space: nowrap;
        }

        /* Progress Bar */
        .progress-box {
            min-width: 110px;
        }

        .progress-labels {
            display: flex;
            justify-content: space-between;
            font-size: 11px;
            color: #64748B;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .progress-track {
            height: 6px;
            background: #EBEDFF;
            border-radius: 9999px;
            overflow: hidden;
            width: 100%;
        }

        .progress-fill {
            height: 100%;
            border-radius: 9999px;
            background: #4F56A6;
        }

        .progress-fill.danger { background: #BA1A1A; }
        .progress-fill.success { background: #10B981; }

        /* Priority Tag */
        .priority-badge {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .priority-badge.high { color: #BA1A1A; }
        .priority-badge.normal { color: #64748B; }
        .priority-badge.low { color: #10B981; }

        .maker-task-text {
            font-size: 12px;
            font-weight: 600;
            color: #373E8C;
            max-width: 130px;
            line-height: 1.3;
        }

        /* Status Pills */
        .status-badge-lg {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 14px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            white-space: nowrap;
        }

        .status-badge-lg.primary { background: #E0E0FF; color: #373E8C; }
        .status-badge-lg.danger { background: #FFDAD6; color: #BA1A1A; }
        .status-badge-lg.success { background: #E8F8F0; color: #10B981; }
        .status-badge-lg.neutral { background: #F1F5F9; color: #64748B; }

        .btn-table-action {
            font-size: 13.5px;
            font-weight: 700;
            color: #373E8C;
            transition: color 0.2s;
        }

        .btn-table-action:hover {
            color: #1F2A5A;
            text-decoration: underline;
        }

        /* --- CỤM XỔ XUỐNG: ASSET LINE ITEMS NESTED BOX --- */
        .expanded-details-container {
            background: rgba(250, 248, 255, 0.7);
            border: 1px solid #F3F2FF;
            border-radius: 14px;
            padding: 20px;
            margin: 10px 0 16px;
        }

        .details-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid #F3F2FF;
            margin-bottom: 14px;
        }

        .details-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748B;
        }

        .guideline-tag {
            font-size: 12px;
            font-weight: 600;
            color: #373E8C;
            background: #F3F2FF;
            padding: 4px 10px;
            border-radius: 6px;
        }

        .asset-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            background: #FFFFFF;
            border: 1px solid #EBEDFF;
            border-radius: 10px;
            margin-bottom: 10px;
        }

        .asset-item-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .asset-name {
            font-size: 13.5px;
            font-weight: 700;
            color: #161B2E;
            min-width: 130px;
        }

        .asset-next-step {
            font-size: 12px;
            color: #64748B;
            font-weight: 500;
        }

        .asset-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-asset-outline {
            background: #FFFFFF;
            border: 1px solid #E0E0FF;
            color: #475569;
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-asset-outline:hover {
            border-color: #373E8C;
            color: #373E8C;
        }

        .btn-asset-solid {
            background: #373E8C;
            color: #FFFFFF;
            padding: 6px 16px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .btn-asset-solid:hover {
            background: #272D5E;
        }

        /* ================= 4. WEEKLY SCHEDULE BOARD ================= */
        .schedule-section {
            background: #FFFFFF;
            border: 1px solid #E4E7FF;
            border-radius: 18px;
            padding: 32px;
            margin-bottom: 60px;
            box-shadow: 0 2px 8px rgba(31, 42, 90, 0.04);
        }

        .schedule-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 20px;
            border-bottom: 1px solid #EBEDFF;
            margin-bottom: 24px;
        }

        .schedule-title {
            font-size: 22px;
            font-weight: 700;
            color: #161B2E;
        }

        .week-nav {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-week-nav {
            padding: 6px 14px;
            border-radius: 8px;
            border: 1px solid #EBEDFF;
            font-size: 12px;
            font-weight: 600;
            color: #64748B;
        }

        .week-indicator-pill {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #F3F2FF;
            border: 1px solid #D5D9F4;
            padding: 6px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            color: #161B2E;
        }

        .week-indicator-pill span.dot {
            width: 8px;
            height: 8px;
            background-color: #373E8C;
            border-radius: 50%;
        }

        /* Calendar 7 Days Grid */
        .calendar-grid {
            border: 1px solid #E0E0FF;
            border-radius: 12px;
            overflow: hidden;
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            margin-bottom: 20px;
        }

        .day-column {
            border-right: 1px solid #E0E0FF;
            min-height: 320px;
            display: flex;
            flex-direction: column;
            background: #FFFFFF;
        }

        .day-column:last-child {
            border-right: none;
        }

        .day-column.today {
            background: rgba(250, 248, 255, 0.6);
        }

        .day-column-header {
            padding: 14px 10px;
            border-bottom: 1px solid #E0E0FF;
            text-align: center;
            background: rgba(248, 250, 252, 0.7);
        }

        .day-column.today .day-column-header {
            background: rgba(243, 242, 255, 0.8);
        }

        .day-name {
            font-size: 11px;
            font-weight: 700;
            color: #94A3B8;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin-bottom: 2px;
        }

        .day-column.today .day-name {
            color: #373E8C;
        }

        .today-pill {
            background: #373E8C;
            color: #FFFFFF;
            font-size: 9px;
            font-weight: 700;
            padding: 1px 5px;
            border-radius: 9999px;
        }

        .day-number {
            font-size: 20px;
            font-weight: 700;
            color: #161B2E;
        }

        .day-column.today .day-number {
            color: #373E8C;
        }

        .day-column-body {
            padding: 10px;
            flex: 1;
        }

        /* Sticky Notes on Calendar */
        .task-sticky {
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 8px;
        }

        .task-sticky.danger {
            background: #FFF0F2;
            border: 1px solid #FFD6DC;
            color: #9B1C2E;
        }

        .task-sticky.primary {
            background: #F4F3FF;
            border: 1px solid #DFDCFE;
            color: #373E8C;
        }

        .sticky-title {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .sticky-sub {
            font-size: 11px;
            line-height: 1.3;
            opacity: 0.85;
        }

        /* Calendar Legend */
        .calendar-legend {
            display: flex;
            align-items: center;
            gap: 24px;
            padding-top: 16px;
            border-top: 1px solid #F3F2FF;
            font-size: 12px;
            color: #161B2E;
            font-weight: 600;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .legend-dot.today { background-color: #373E8C; }
        .legend-dot.revision { background-color: #BA1A1A; }
        .legend-dot.ready { background-color: #4F56A6; }
        .legend-dot.due { background-color: #FFDEA5; border: 1px solid rgba(123, 88, 0, 0.3); }
        .legend-dot.approved { background-color: #10B981; }

        /* ================= 5. POPUP MODAL: UPLOAD ASSET ================= */
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

        .modal-box {
            background: #FFFFFF;
            border-radius: 20px;
            width: 100%;
            max-width: 540px;
            padding: 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .modal-title {
            font-size: 22px;
            font-weight: 700;
            color: #161B2E;
            margin-bottom: 6px;
        }

        .modal-desc {
            font-size: 13.5px;
            color: #64748B;
            margin-bottom: 24px;
        }

        .form-row {
            margin-bottom: 18px;
        }

        .form-row label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #161B2E;
            margin-bottom: 6px;
        }

        .form-select, .form-input {
            width: 100%;
            height: 44px;
            padding: 10px 14px;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }

        .dropzone-box {
            border: 2px dashed #C7CBF6;
            background: #F8F9FE;
            border-radius: 14px;
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .dropzone-box:hover {
            border-color: #373E8C;
            background: #F1F3FD;
        }

        .modal-actions {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }

        /* ================= 6. RESPONSIVE ================= */
        @media (max-width: 1100px) {
            .workspace-grid {
                grid-template-columns: 1fr;
            }
            .calendar-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }

        @media (max-width: 768px) {
            .top-navbar .nav-menu {
                display: none;
            }
            .workspace-hero {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            .calendar-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

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

    <main class="container">

        <?php if (!empty($uploadAlert)): ?>
            <div style="background: #E8F8F0; border: 1px solid #A7F3D0; color: #065F46; padding: 14px 20px; border-radius: 12px; margin-top: 20px; font-size: 14px; font-weight: 600; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 18px;">✓</span>
                <span><?= $uploadAlert ?></span>
            </div>
        <?php endif; ?>

        <!-- ================= 2. WORKSPACE GREETING ================= -->
        <section class="workspace-hero">
            <div>
                <div class="role-tagline">MAKER WORKSPACE</div>
                <h1 class="hero-heading">Good morning, <span id="displayGreetingName"><?= htmlspecialchars($currentUser['first_name'] ?? 'Cam Anh') ?></span></h1>
                <p class="attention-notice"><?= count($comingUpList) > 0 ? count($comingUpList) . ' items need your attention.' : 'All items are up to date.' ?></p>
            </div>
            <div class="hero-action-buttons">
                <button class="btn-secondary" onclick="openCreateCampaignModal()">+ New Campaign</button>
                <button class="btn-primary" id="btnOpenUploadModal">Upload Asset</button>
            </div>
        </section>

        <!-- ================= 3. WORKSPACE 2-COLUMN GRID ================= -->
        <div class="workspace-grid">

            <!-- --- CỘT TRÁI (ASIDE): COMING UP --- -->
            <aside class="coming-up-card">
                <h2 class="card-header-title">Coming Up</h2>
                
                <div class="coming-items-list">
                    <?php if (!empty($comingUpList)): ?>
                        <?php foreach ($comingUpList as $item): ?>
                            <div class="coming-item">
                                <div class="coming-item-top">
                                    <span class="date-badge <?= $item['badge_type'] ?>"><?= $item['date'] ?></span>
                                    <span class="status-pill-small <?= $item['badge_type'] ?>"><?= $item['badge'] ?></span>
                                </div>
                                <h3 class="coming-title"><?= $item['title'] ?></h3>
                                <p class="coming-campaign"><?= $item['campaign'] ?></p>
                                <a href="javascript:void(0)" onclick="openUploadFor('<?= $item['campaign'] ?>')" class="coming-link">Detail</a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 36px 16px; text-align: center; color: #94A3B8; font-size: 13.5px;">
                            No pending tasks.
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer-strip">
                    <?= count($comingUpList) ?> Tasks Total
                </div>
            </aside>

            <!-- --- CỘT PHẢI: BẢNG CAMPAIGNS --- -->
            <section class="campaigns-card">
                <div class="campaigns-top-bar">
                    <h2 class="campaigns-heading">Campaigns</h2>
                    <div class="filter-pills">
                        <button class="pill-btn active" onclick="filterCampaigns('all', this)">All (<?= $totalCamps ?>)</button>
                        <button class="pill-btn" onclick="filterCampaigns('in_review', this)">In Review (<?= $inReviewCount ?>)</button>
                        <button class="pill-btn" onclick="filterCampaigns('rejected', this)">Rejected (<?= $rejectedCount ?>)</button>
                        <button class="pill-btn" onclick="filterCampaigns('approved', this)">Approved (<?= $approvedCount ?>)</button>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table class="campaign-table">
                        <thead class="table-head">
                            <tr>
                                <th>CAMPAIGN</th>
                                <th>DEADLINE</th>
                                <th>PROGRESS</th>
                                <th>STATUS</th>
                                <th style="text-align: right;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($campaigns)): ?>
                                <?php foreach ($campaigns as $camp): ?>
                                    <tr class="campaign-row" data-group="<?= $camp['filter_group'] ?>" data-statuses="<?= implode(' ', $camp['asset_statuses'] ?? [$camp['filter_group']]) ?>" data-camp-id="<?= $camp['id'] ?>">
                                        <td class="campaign-title-cell"><?= htmlspecialchars($camp['name']) ?></td>
                                        <td class="deadline-cell"><?= htmlspecialchars($camp['deadline']) ?></td>
                                        <td>
                                            <div class="progress-box">
                                                <div class="progress-labels">
                                                    <span><?= $camp['done'] ?> / <?= $camp['total'] ?></span>
                                                    <span><?= $camp['percent'] ?>%</span>
                                                </div>
                                                <div class="progress-track">
                                                    <div class="progress-fill <?= $camp['status_type'] ?>" style="width: <?= $camp['percent'] ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge-lg <?= $camp['status_type'] ?>">
                                                <?= htmlspecialchars($camp['status']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align: right;">
                                            <?php if (!empty($camp['assets'])): ?>
                                                <button class="btn-table-action" onclick="toggleDetails(<?= $camp['id'] ?>, this)">
                                                    <?= $camp['expanded'] ? 'Close' : 'Open' ?>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn-table-action" onclick="openUploadFor('<?= esc_js($camp['name']) ?>', 'V1')">+ Add Asset</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <!-- ASSET LINE ITEMS DROPDOWN -->
                                    <?php if (!empty($camp['assets'])): ?>
                                        <tr id="details-row-<?= $camp['id'] ?>" class="details-row-container" data-camp-id="<?= $camp['id'] ?>" style="<?= $camp['expanded'] ? '' : 'display: none;' ?>">
                                            <td colspan="5" style="padding: 0 0 16px 0;">
                                                <div class="expanded-details-container">
                                                    <div class="details-header" style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                                                        <span class="details-title">ASSET LINE ITEMS</span>
                                                        <div style="display: flex; align-items: center; gap: 8px;">
                                                            <?php if (!empty($camp['guideline_file_url'])): ?>
                                                                <a href="<?= esc_url($camp['guideline_file_url']) ?>" target="_blank" class="guideline-tag" style="background: #EEF2FF; color: #4F56A6; border: 1px solid #C7D2FE; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 6px; font-weight: 600;">
                                                                    <span>📄 Guidelines (1 file - View)</span>
                                                                </a>
                                                            <?php else: ?>
                                                                <span class="guideline-tag">Guidelines · 0 files</span>
                                                            <?php endif; ?>
                                                            <button type="button" class="btn-table-action" style="padding: 4px 10px; font-size: 11.5px; border-radius: 6px;" onclick="openUploadGuidelineModal(<?= $camp['id'] ?>, '<?= esc_js($camp['name']) ?>')">
                                                                + Upload Guidelines
                                                            </button>
                                                            <a href="<?= esc_url(pcs_url('detail-asset-maker.php') . '?campaign_id=' . $camp['id']) ?>" class="btn-table-action" style="padding: 4px 10px; font-size: 11.5px; border-radius: 6px; background: #4E56A6; color: white; text-decoration: none;">
                                                                View Campaign Details →
                                                            </a>
                                                        </div>
                                                    </div>

                                                    <?php foreach ($camp['assets'] as $asset): ?>
                                                        <div class="asset-item-row" style="flex-direction: column; align-items: stretch; gap: 8px; padding: 12px 16px; border-bottom: 1px solid #F1F5F9;">
                                                            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                                                <div class="asset-item-left" style="display: flex; align-items: center; gap: 10px;">
                                                                    <span class="asset-name" style="font-weight: 700; color: #1E293B;"><?= htmlspecialchars($asset['name']) ?></span>
                                                                    <span class="status-pill-small <?= $asset['badge_type'] ?>"><?= htmlspecialchars($asset['version']) ?></span>
                                                                    <span class="asset-next-step" style="font-size: 12px; color: #64748B;"><?= htmlspecialchars($asset['next_step']) ?></span>
                                                                </div>
                                                                <div class="asset-actions" style="display: flex; align-items: center; gap: 8px;">
                                                                    <?php if ($asset['status'] === 'rejected'): ?>
                                                                        <button class="btn-asset-solid" style="background: #4F56A6; color: #FFF; font-weight: 700;" onclick="openUploadFor('<?= esc_js($camp['name']) ?>', 'V<?= $asset['current_version_num'] + 1 ?>', '<?= esc_js($asset['name']) ?>')">
                                                                            Upload V<?= $asset['current_version_num'] + 1 ?>
                                                                        </button>
                                                                    <?php elseif ($asset['status'] === 'draft'): ?>
                                                                        <button class="btn-asset-solid" onclick="openUploadFor('<?= esc_js($camp['name']) ?>', 'V1', '<?= esc_js($asset['name']) ?>')">
                                                                            Upload Artwork
                                                                        </button>
                                                                    <?php endif; ?>

                                                                    <?php if (!empty($asset['file_url'])): ?>
                                                                        <a href="<?= esc_url($asset['file_url']) ?>" target="_blank" class="btn-asset-outline" style="text-decoration: none; display: inline-flex; align-items: center;">View File</a>
                                                                    <?php endif; ?>

                                                                    <?php if (!empty($asset['id']) && (int)($asset['current_version_num'] ?? 1) > 1): ?>
                                                                        <?php 
                                                                        $mCompUrl = (function_exists('pcs_url') ? pcs_url('compare-version.php') : 'compare-version.php') . '?' . http_build_query([
                                                                            'asset_id'    => (int)$asset['id'],
                                                                            'campaign_id' => (int)$camp['id'],
                                                                            'from'        => 'maker'
                                                                        ]);
                                                                        ?>
                                                                        <a href="<?= esc_url($mCompUrl) ?>" class="btn-asset-outline" style="text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 3h5v5M4 20L21 3M21 16v5h-5M15 15l6 6M4 4l5 5"/></svg>
                                                                            <span>Compare</span>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>

                                                            <?php if ($asset['status'] === 'rejected' && !empty($asset['review_note'])): ?>
                                                                <div style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: 8px; padding: 10px 14px; font-size: 13px; color: #991B1B; display: flex; align-items: flex-start; gap: 8px;">
                                                                    <span style="font-size: 16px; line-height: 1.2;">💬</span>
                                                                    <div>
                                                                        <strong style="color: #7F1D1D;">Checker Feedback:</strong> <?= htmlspecialchars($asset['review_note']) ?>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 60px 20px; color: #64748B;">
                                        <div style="font-size: 36px; margin-bottom: 12px;">📁</div>
                                        <p style="font-size: 16px; font-weight: 700; color: #161B2E; margin-bottom: 4px;">No campaigns found</p>
                                        <p style="font-size: 13.5px; color: #64748B;">No active campaigns in the system.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- ================= 4. WEEKLY SCHEDULE BOARD ================= -->
        <section class="schedule-section">
            <div class="schedule-header">
                <h2 class="schedule-title">Schedule</h2>
                <div class="week-nav">
                    <button class="btn-week-nav" onclick="alert('Previous week')">Previous Week</button>
                    <div class="week-indicator-pill">
                        <span class="dot"></span>
                        <span><?= !empty($scheduleDays) ? $scheduleDays[0]['date'] . '–' . $scheduleDays[6]['date'] . ' ' . date('M Y') : 'This Week' ?></span>
                    </div>
                    <button class="btn-week-nav" onclick="alert('Next week')">Next Week</button>
                </div>
            </div>

            <div class="calendar-grid">
                <?php foreach ($scheduleDays as $day): ?>
                    <div class="day-column <?= $day['today'] ? 'today' : '' ?>">
                        <div class="day-column-header">
                            <div class="day-name">
                                <span><?= $day['day'] ?></span>
                                <?php if ($day['today']): ?>
                                    <span class="today-pill">TODAY</span>
                                <?php endif; ?>
                            </div>
                            <div class="day-number"><?= $day['date'] ?></div>
                        </div>

                        <div class="day-column-body">
                            <?php if (!empty($day['task'])): ?>
                                <div class="task-sticky <?= $day['task']['type'] ?>">
                                    <div class="sticky-title"><?= $day['task']['title'] ?></div>
                                    <div class="sticky-sub"><?= $day['task']['campaign'] ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Legend Bar -->
            <div class="calendar-legend">
                <div class="legend-item"><span class="legend-dot today"></span> Today</div>
                <div class="legend-item"><span class="legend-dot revision"></span> Revision</div>
                <div class="legend-item"><span class="legend-dot ready"></span> Ready</div>
                <div class="legend-item"><span class="legend-dot due"></span> Due</div>
                <div class="legend-item"><span class="legend-dot approved"></span> Approved</div>
            </div>
        </section>

    </main>

    <!-- ================= 5. POPUP MODAL: CREATE CAMPAIGN ================= -->
    <div class="modal-overlay" id="createCampaignModal" style="display: none;">
        <div class="modal-box">
            <h3 class="modal-title">Create New Campaign</h3>
            <p class="modal-desc">Enter campaign details and optionally attach Brand Guidelines.</p>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_campaign">

                <div class="form-row">
                    <label>Campaign Name</label>
                    <input type="text" class="form-input" name="campaign_title" placeholder="e.g. Autumn Investment 2026" required>
                </div>

                <div class="form-row">
                    <label>Target Deadline</label>
                    <input type="date" class="form-input" name="campaign_deadline" value="<?= date('Y-m-d', strtotime('+14 days')) ?>" required>
                </div>

                <div class="form-row">
                    <label>Brand Guidelines Document (PDF - Optional)</label>
                    <input type="file" class="form-input" name="guideline_file" accept=".pdf,.doc,.docx" style="padding: 8px 12px; font-size: 13px;">
                    <p style="font-size: 11.5px; color: #94A3B8; margin-top: 4px;">Upload guideline document to guide automated AI compliance scoring.</p>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeCreateCampaignModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Create Campaign</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= 6. POPUP MODAL: UPLOAD GUIDELINE ================= -->
    <div class="modal-overlay" id="uploadGuidelineModal" style="display: none;">
        <div class="modal-box">
            <h3 class="modal-title" id="guidelineModalTitle">Upload Brand Guidelines</h3>
            <p class="modal-desc">Attach brand guidelines or compliance rulebook for this campaign.</p>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_guideline">
                <input type="hidden" name="campaign_id" id="guidelineCampId" value="">

                <div class="form-row">
                    <label>Select Guideline Document (PDF, DOCX)</label>
                    <input type="file" name="guideline_file" class="form-input" accept=".pdf,.doc,.docx" required style="padding: 8px 12px;">
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeUploadGuidelineModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Upload Document</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= 7. POPUP MODAL: UPLOAD ASSET ================= -->
    <div class="modal-overlay" id="uploadModal">
        <div class="modal-box">
            <h3 class="modal-title" id="modalTitle">Upload New Asset Version</h3>
            <p class="modal-desc">Upload asset artwork to initiate automated AI Pre-flight Check.</p>

            <form id="uploadForm" method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_asset">

                <div class="form-row">
                    <label>Campaign</label>
                    <select class="form-select" name="campaign_name" id="selectCampaign" required>
                        <?php if (!empty($campaigns)): ?>
                            <?php foreach ($campaigns as $c): ?>
                                <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="Autumn Investment 2026">Autumn Investment 2026 (Auto-create)</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-row">
                    <label>Asset Name</label>
                    <input type="text" class="form-input" id="inputAssetName" name="asset_name" value="Product Brochure" placeholder="e.g. Product Brochure, Social Banner, Landing Page" required>
                </div>

                <div class="form-row">
                    <label>Version</label>
                    <input type="text" class="form-input" id="inputVersion" name="version" value="V1" readonly style="background: #F8FAFC;">
                </div>

                <div class="form-row">
                    <label>Artwork Asset (Image or PDF)</label>
                    <div class="dropzone-box" id="fileDropArea" onclick="document.getElementById('fileInput').click()" style="cursor: pointer; position: relative;">
                        <input type="file" name="asset_file" id="fileInput" accept="image/*,.pdf" style="display: none;" required>
                        
                        <!-- Empty Dropzone State -->
                        <div id="dropzoneEmpty">
                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#4F56A6" stroke-width="2" style="margin: 0 auto 8px;">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="17 8 12 3 7 8"/>
                                <line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            <p style="font-size: 13.5px; font-weight: 600; color: #161B2E;" id="fileNameDisplay">Click to browse or drag and drop files here</p>
                            <p style="font-size: 12px; color: #94A3B8; margin-top: 4px;">Supports PNG, JPG, PDF (up to 50MB)</p>
                        </div>

                        <!-- Selected File Preview Container (Image / PDF) -->
                        <div id="filePreviewContainer" style="display: none; flex-direction: column; align-items: center; justify-content: center; width: 100%;">
                            <img id="imagePreview" src="" alt="Asset Preview" style="max-height: 180px; max-width: 100%; border-radius: 8px; object-fit: contain; box-shadow: 0 4px 12px rgba(0,0,0,0.08); display: none; margin-bottom: 8px;">
                            
                            <div id="pdfPreview" style="display: none; align-items: center; gap: 8px; padding: 12px 16px; background: #FFF; border-radius: 8px; border: 1px solid #E2E8F0; margin-bottom: 8px;">
                                <span style="font-size: 24px;">📄</span>
                                <div style="text-align: left;">
                                    <div id="pdfFileName" style="font-size: 13px; font-weight: 600; color: #1E293B;">document.pdf</div>
                                    <div id="pdfFileSize" style="font-size: 11px; color: #64748B;">PDF File ready</div>
                                </div>
                            </div>

                            <div style="display: flex; gap: 8px; align-items: center;">
                                <span style="font-size: 12px; font-weight: 600; color: #4F56A6; background: #EEF2FF; padding: 4px 10px; border-radius: 6px;">Click to change file if wrong</span>
                                <button type="button" onclick="event.stopPropagation(); resetFileUpload();" style="font-size: 11.5px; color: #DC2626; text-decoration: underline; background: none; border: none; cursor: pointer;">Remove</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" class="btn-primary" id="btnSubmitUpload">Run AI Pre-flight Check →</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================= 8. JAVASCRIPT ================= -->
    <script>
        // 1. Profile Dropdown Menu & Logout Toggle
        const profileBadge = document.getElementById('userProfileBadge');
        const dropdownMenu = document.getElementById('profileDropdownMenu');

        profileBadge?.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownMenu?.classList.toggle('show');
            profileBadge?.classList.toggle('open');
        });

        document.addEventListener('click', function(e) {
            if (!profileBadge?.contains(e.target)) {
                dropdownMenu?.classList.remove('show');
                profileBadge?.classList.remove('open');
            }
        });

        // 2. Toggle Asset Line Items
        function toggleDetails(rowId, btn) {
            const row = document.getElementById('details-row-' + rowId);
            if (row) {
                const isHidden = row.style.display === 'none';
                row.style.display = isHidden ? '' : 'none';
                btn.innerText = isHidden ? 'Close' : 'Open';
            }
        }

        // 3. Filter Pills (All / In Review / Rejected / Approved)
        function filterCampaigns(group, btn) {
            document.querySelectorAll('.pill-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const rows = document.querySelectorAll('.campaign-row');
            rows.forEach(r => {
                const statuses = (r.getAttribute('data-statuses') || r.getAttribute('data-group') || '').split(' ');
                const campId = r.getAttribute('data-camp-id');
                const detailsRow = document.getElementById('details-row-' + campId);
                
                if (group === 'all' || statuses.includes(group)) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                    if (detailsRow) {
                        detailsRow.style.display = 'none';
                    }
                }
            });
        }

        // 4. Create Campaign Modal
        function openCreateCampaignModal() {
            const m = document.getElementById('createCampaignModal');
            if (m) m.style.display = 'flex';
        }
        function closeCreateCampaignModal() {
            const m = document.getElementById('createCampaignModal');
            if (m) m.style.display = 'none';
        }

        // 5. Upload Guideline Modal
        function openUploadGuidelineModal(campId, campName) {
            const m = document.getElementById('uploadGuidelineModal');
            const inp = document.getElementById('guidelineCampId');
            const title = document.getElementById('guidelineModalTitle');
            if (inp) inp.value = campId;
            if (title) title.innerText = 'Upload Guidelines - ' + campName;
            if (m) m.style.display = 'flex';
        }
        function closeUploadGuidelineModal() {
            const m = document.getElementById('uploadGuidelineModal');
            if (m) m.style.display = 'none';
        }

        // 6. Upload Asset Modal & Preview Logic
        const modal = document.getElementById('uploadModal');
        const fileInput = document.getElementById('fileInput');
        const fileNameDisplay = document.getElementById('fileNameDisplay');
        const emptyDropzone = document.getElementById('dropzoneEmpty');
        const previewContainer = document.getElementById('filePreviewContainer');
        const imgPreview = document.getElementById('imagePreview');
        const pdfPreview = document.getElementById('pdfPreview');

        document.getElementById('btnOpenUploadModal')?.addEventListener('click', () => {
            const firstCamp = document.getElementById('selectCampaign')?.value || 'Campaign';
            openUploadFor(firstCamp, 'V1');
        });

        function resetFileUpload() {
            if (fileInput) fileInput.value = '';
            if (emptyDropzone) emptyDropzone.style.display = 'block';
            if (previewContainer) previewContainer.style.display = 'none';
            if (imgPreview) {
                imgPreview.src = '';
                imgPreview.style.display = 'none';
            }
            if (pdfPreview) {
                pdfPreview.style.display = 'none';
            }
        }

        function openUploadFor(campaignName, version = 'V1', assetName = '') {
            const select = document.getElementById('selectCampaign');
            if (select && campaignName) {
                select.value = campaignName;
            }
            const nameInput = document.getElementById('inputAssetName');
            if (nameInput && assetName) {
                nameInput.value = assetName;
            }
            document.getElementById('inputVersion').value = version;
            document.getElementById('modalTitle').innerText = 'Upload ' + version + (campaignName ? ' - ' + campaignName : '');
            resetFileUpload();
            modal.classList.add('active');
        }

        function closeUploadModal() {
            modal.classList.remove('active');
        }

        fileInput?.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                if (emptyDropzone) emptyDropzone.style.display = 'none';
                if (previewContainer) previewContainer.style.display = 'flex';

                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        if (imgPreview) {
                            imgPreview.src = e.target.result;
                            imgPreview.style.display = 'block';
                        }
                        if (pdfPreview) pdfPreview.style.display = 'none';
                    };
                    reader.readAsDataURL(file);
                } else if (file.type === 'application/pdf' || file.name.endsWith('.pdf')) {
                    if (imgPreview) imgPreview.style.display = 'none';
                    if (pdfPreview) {
                        pdfPreview.style.display = 'flex';
                        document.getElementById('pdfFileName').innerText = file.name;
                        document.getElementById('pdfFileSize').innerText = (file.size / (1024 * 1024)).toFixed(2) + ' MB · PDF Document';
                    }
                }
            }
        });

        document.getElementById('uploadForm')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('btnSubmitUpload');
            btn.innerText = 'Scanning & uploading version...';
            btn.style.opacity = '0.7';
        });
    </script>
</body>
</html>

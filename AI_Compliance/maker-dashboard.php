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

// Handle Guideline Upload & Delete (Full CRUD)
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_guideline') {
        $campId = (int) ($_POST['campaign_id'] ?? 0);
        if ($campId && function_exists('handle_upload_campaign_guideline')) {
            $gUrl = handle_upload_campaign_guideline($campId);
            if ($gUrl) {
                $uploadAlert = 'Brand Guideline document(s) uploaded successfully!';
            }
        }
    } elseif ($_POST['action'] === 'delete_guideline') {
        $campId = (int) ($_POST['campaign_id'] ?? 0);
        $targetUrl = sanitize_text_field($_POST['guideline_url'] ?? '');
        if ($campId && $targetUrl && function_exists('handle_delete_campaign_guideline')) {
            $ok = handle_delete_campaign_guideline($campId, $targetUrl);
            if ($ok) {
                $uploadAlert = 'Guideline document deleted successfully!';
            }
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
} elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_asset_identifiers') {
    $aid = absint($_POST['asset_id'] ?? 0);
    if ($aid > 0 && function_exists('handle_update_asset')) {
        handle_update_asset($aid, $_POST);
        $uploadAlert = 'Asset identifiers updated successfully!';
    }
}

if (isset($_GET['upload_success']) && $_GET['upload_success'] === '1' && empty($uploadAlert)) {
    $uploadAlert = 'Asset uploaded successfully and submitted for compliance review.';
}

// 1. DỮ LIỆU ĐỘNG TỪ DATABASE THẬT VÀ PHÂN QUYỀN RBAC (MAKER ONLY)
$currentUser = function_exists('pcs_enforce_rbac') ? pcs_enforce_rbac(['MAKER']) : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'MAKER');
$comingUpList = get_coming_up_tasks();
$campaigns = get_all_campaigns();

$weekOffset = isset($_GET['week_offset']) ? (int) $_GET['week_offset'] : 0;
$scheduleData = function_exists('get_realtime_weekly_schedule') ? get_realtime_weekly_schedule($weekOffset) : [];
$scheduleDays = $scheduleData['days'] ?? $scheduleData;
$weekLabel = $scheduleData['week_label'] ?? (!empty($scheduleDays[0]) ? $scheduleDays[0]['date'] . '–' . $scheduleDays[6]['date'] . ' ' . date('M Y') : 'This Week');

if (!function_exists('pcs_render_weekly_schedule_block')) {
function pcs_render_weekly_schedule_block($weekOffset, $scheduleDays, $weekLabel) {
    ob_start();
    ?>
    <section class="schedule-section" id="schedule-section">
        <div class="schedule-header">
            <h2 class="schedule-title">Schedule</h2>
            <div class="week-nav" style="display: inline-flex; align-items: center; gap: 8px;">
                <button type="button" onclick="navigateScheduleWeek(0, event)" class="btn-week-nav <?= ($weekOffset === 0) ? 'active' : '' ?>" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; cursor: pointer; <?= ($weekOffset === 0) ? 'background: #373E8C; color: #FFFFFF; border-color: #373E8C;' : 'background: #FFFFFF; color: #4F56A6;' ?>">Current Week</button>
                <button type="button" onclick="navigateScheduleWeek(<?= $weekOffset - 1 ?>, event)" class="btn-week-nav" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">Previous Week</button>
                <div class="week-indicator-pill-wrap" style="position: relative; display: inline-block;">
                    <div class="week-indicator-pill" onclick="toggleCalendarMonthPopover(event)" style="min-width: 145px; text-align: center; justify-content: center; cursor: pointer; user-select: none; display: inline-flex; align-items: center; gap: 6px;" title="Click to pick a specific date">
                        <span><?= esc_html($weekLabel) ?></span>
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="opacity: 0.7;"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                    </div>
                    <div id="calendarMonthPopover" class="calendar-month-popover" style="display: none; position: absolute; top: calc(100% + 8px); left: 50%; transform: translateX(-50%); z-index: 1000; width: 280px; background: #FFFFFF; border-radius: 12px; border: 1px solid #E2E8F0; box-shadow: 0 10px 30px rgba(0,0,0,0.15); padding: 14px; box-sizing: border-box;">
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                            <button type="button" onclick="changeMonthInPicker(-1, event)" style="background: none; border: 1px solid #E2E8F0; border-radius: 6px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #334155;">&lsaquo;</button>
                            <span id="popoverMonthYear" style="font-size: 13.5px; font-weight: 700; color: #0F172A;"></span>
                            <button type="button" onclick="changeMonthInPicker(1, event)" style="background: none; border: 1px solid #E2E8F0; border-radius: 6px; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #334155;">&rsaquo;</button>
                        </div>
                        <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center; font-size: 11px; font-weight: 700; color: #64748B; margin-bottom: 6px;">
                            <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
                        </div>
                        <div id="popoverDaysGrid" style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; text-align: center;"></div>
                        <div id="popoverSelectedDateFooter" style="margin-top: 10px; padding-top: 8px; border-top: 1px solid #F1F5F9; font-size: 11.5px; color: #64748B; text-align: center; font-weight: 500;"></div>
                    </div>
                </div>
                <button type="button" onclick="navigateScheduleWeek(<?= $weekOffset + 1 ?>, event)" class="btn-week-nav" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center; cursor: pointer;">Next Week</button>
            </div>
        </div>

        <div class="calendar-grid">
            <?php foreach ($scheduleDays as $day): ?>
                <div class="day-column <?= (!empty($day['today'])) ? 'today' : '' ?>">
                    <div class="day-column-header">
                        <div class="day-name">
                            <span><?= esc_html($day['day']) ?></span>
                            <?php if (!empty($day['today'])): ?>
                                <span class="today-pill">TODAY</span>
                            <?php endif; ?>
                        </div>
                        <div class="day-number"><?= esc_html($day['date']) ?></div>
                    </div>

                    <div class="day-column-body">
                        <?php if (!empty($day['tasks'])): ?>
                            <?php foreach ($day['tasks'] as $t): ?>
                                <div class="task-sticky <?= esc_attr($t['type']) ?>">
                                    <div class="sticky-title"><?= esc_html($t['title']) ?></div>
                                    <div class="sticky-sub"><?= esc_html($t['campaign']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php elseif (!empty($day['task'])): ?>
                            <div class="task-sticky <?= esc_attr($day['task']['type']) ?>">
                                <div class="sticky-title"><?= esc_html($day['task']['title']) ?></div>
                                <div class="sticky-sub"><?= esc_html($day['task']['campaign']) ?></div>
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
    <?php
    return ob_get_clean();
}
}

if (isset($_GET['action']) && $_GET['action'] === 'pcs_get_schedule_week') {
    $html = pcs_render_weekly_schedule_block($weekOffset, $scheduleDays, $weekLabel);
    header('Content-Type: application/json');
    echo json_encode([
        'success'     => true,
        'html'        => $html,
        'week_offset' => $weekOffset,
        'week_label'  => $weekLabel
    ]);
    exit;
}

$kpis = function_exists('get_maker_kpis') ? get_maker_kpis((int)($currentUser['id'] ?? 0)) : (function_exists('get_checker_kpis') ? get_checker_kpis() : [
    'draft'     => 0,
    'scoring'   => 0,
    'in_review' => 0,
    'rejected'  => 0,
    'approved'  => 0,
    'released'  => 0,
    'total'     => 0
]);

$draftCount    = (int) ($kpis['draft'] ?? 0);
$scoringCount  = (int) ($kpis['scoring'] ?? 0);
$inReviewCount = (int) ($kpis['in_review'] ?? 0);
$rejectedCount = (int) ($kpis['rejected'] ?? 0);
$approvedCount = (int) ($kpis['approved'] ?? ($kpis['awaiting_release'] ?? 0));
$releasedCount = (int) ($kpis['released'] ?? 0);

$totalCamps = count($campaigns);

// Phân loại trạng thái chuẩn theo Maker: Action Required & Awaiting Review
$actionRequiredCount = 0;
$awaitingReviewCount = 0;

foreach ($campaigns as &$camp) {
    $hasAction = false;
    $hasAwaiting = false;
    if (empty($camp['assets'])) {
        $hasAction = true; // Chưa có asset nào -> Maker cần upload V1
    } else {
        foreach ($camp['assets'] as $ast) {
            if ($ast['status'] === 'rejected' || $ast['status'] === 'draft') {
                $hasAction = true;
            } elseif ($ast['status'] === 'in_review' || $ast['status'] === 'scoring') {
                $hasAwaiting = true;
            }
        }
    }
    $camp['has_action_required'] = $hasAction;
    $camp['has_awaiting_review'] = $hasAwaiting;

    if ($hasAction) $actionRequiredCount++;
    if ($hasAwaiting) $awaitingReviewCount++;
}
unset($camp);
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

        /* ================= CORE LIFECYCLE METRIC CARDS ================= */
        /* Draft -> Scoring -> In Review -> Rejected -> Approved -> Released */
        .kpi-metrics-bar {
            display: flex;
            background: #FFFFFF;
            border: 1px solid #EBEDFF;
            border-radius: 16px;
            box-shadow: none;
            margin-bottom: 32px;
            padding: 6px;
            overflow-x: auto;
        }

        .kpi-card {
            flex: 1;
            min-width: 130px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 16px;
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
        .kpi-icon-box.status-approved {
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
        .kpi-label.status-approved { color: #059669; }
        .kpi-label.status-released { color: #7C3AED; }

        /* ================= 3. WORKSPACE 2-COLUMN GRID ================= */
        .workspace-grid {
            display: grid;
            grid-template-columns: minmax(280px, 320px) minmax(0, 1fr);
            gap: 24px;
            align-items: stretch;
            margin-bottom: 48px;
            transition: all 0.3s ease;
        }

        .workspace-grid.campaigns-expanded {
            grid-template-columns: 1fr;
        }

        .workspace-grid.campaigns-expanded .coming-up-card {
            display: none !important;
        }

        /* --- 3A. CỘT TRÁI: COMING UP CARD --- */
        .coming-up-card {
            background: #FFFFFF;
            border: 1px solid #EBEDFF;
            border-radius: 16px;
            padding: 20px;
            box-shadow: none;
            display: flex;
            flex-direction: column;
            height: 540px;
            max-height: 540px;
            overflow: hidden;
        }

        .coming-items-list {
            overflow-y: auto;
            flex: 1;
            padding-right: 6px;
            display: flex;
            flex-direction: column;
            gap: 12px;
            scrollbar-width: thin;
            scrollbar-color: #CBD5E1 #FAF8FF;
        }
        .coming-items-list::-webkit-scrollbar {
            width: 6px;
        }
        .coming-items-list::-webkit-scrollbar-thumb {
            background-color: #CBD5E1;
            border-radius: 4px;
        }

        .card-header-title {
            font-size: 18px;
            font-weight: 700;
            color: #161B2E;
            padding-bottom: 16px;
            border-bottom: 1px solid #F3F2FF;
            margin-bottom: 16px;
        }

        .coming-item-card {
            padding: 14px;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            position: relative;
            background: #FAF9FF;
            border: 1px solid #E0E0FF;
            box-shadow: none !important;
            transition: background 0.15s, border-color 0.15s;
        }
        .coming-item-card:hover {
            background: #F3F1FF;
            border-color: #CBD5E1;
            box-shadow: none !important;
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
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .status-pill-small.danger,
        .status-pill-small.rejected { background: #FEF2F2; color: #DC2626; border: 1px solid #FECACA; }
        .status-pill-small.primary { background: rgba(224, 224, 255, 0.6); color: #373E8C; }
        .status-pill-small.warning { background: #FEF3C7; color: #D97706; border: 1px solid #FDE68A; }
        .status-pill-small.draft,
        .status-pill-small.neutral { background: #F1F5F9; color: #475569; border: 1px solid #CBD5E1; }
        .status-pill-small.success,
        .status-pill-small.approved,
        .status-pill-small.released { background: #ECFDF5; color: #059669; border: 1px solid #A7F3D0; }

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
            box-shadow: none;
            display: flex;
            flex-direction: column;
            height: 540px;
            max-height: 540px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .workspace-grid.campaigns-expanded .campaigns-card {
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
        .campaigns-card .table-responsive {
            overflow-x: auto;
            overflow-y: auto;
            flex: 1;
            max-height: 430px;
            border-radius: 8px;
            border: 1px solid #EBEDFF;
            scrollbar-width: thin;
            scrollbar-color: #CBD5E1 #FAF8FF;
        }
        .campaigns-card .table-responsive::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        .campaigns-card .table-responsive::-webkit-scrollbar-thumb {
            background-color: #CBD5E1;
            border-radius: 4px;
        }
        .workspace-grid.campaigns-expanded .campaigns-card .table-responsive {
            max-height: 640px;
        }

        .campaign-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
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
            position: sticky;
            top: 0;
            z-index: 10;
            background: #F8FAFC;
            box-shadow: 0 1px 0 #E2E8F0;
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
            box-shadow: none;
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
        /* Sticky Notes on Calendar: Flat clean frame with thin uniform colored border (No border-left chunky wedge, No box-shadow) */
        .task-sticky {
            padding: 10px 12px;
            border-radius: 8px;
            box-shadow: none !important;
            margin-bottom: 8px;
            transition: background 0.15s, border-color 0.15s;
        }
        .task-sticky:hover {
            transform: none;
            box-shadow: none !important;
        }

        .task-sticky.revision, .task-sticky.danger {
            background: #FEF2F2;
            border: 1px solid #FCA5A5;
            color: #991B1B;
        }

        .task-sticky.ready, .task-sticky.primary {
            background: #F5F3FF;
            border: 1px solid #C4B5FD;
            color: #373E8C;
        }

        .task-sticky.due, .task-sticky.warning {
            background: #FFFBEB;
            border: 1px solid #FCD34D;
            color: #92400E;
        }

        .task-sticky.approved, .task-sticky.success {
            background: #ECFDF5;
            border: 1px solid #86EFAC;
            color: #065F46;
        }

        .task-sticky.today {
            background: #EFF6FF;
            border: 1px solid #93C5FD;
            color: #1E3A8A;
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
        .legend-dot.due { background-color: #F6C87E; }
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

        .dropzone-box.drag-over {
            border-color: #373E8C !important;
            background: #EEF2FF !important;
        }

        .dropzone-box * {
            pointer-events: none;
        }

        .dropzone-box button, .dropzone-box a {
            pointer-events: auto;
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
                <a href="<?= esc_url(pcs_url('smart-maker-upload.php')) ?>" class="btn-primary" style="display: inline-flex; align-items: center; justify-content: center; text-decoration: none;">Upload Asset</a>
            </div>
        </section>

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

        <!-- ================= 3. WORKSPACE 2-COLUMN GRID ================= -->
        <div class="workspace-grid">

            <!-- --- CỘT TRÁI (ASIDE): COMING UP --- -->
            <aside class="coming-up-card">
                <div class="coming-up-header" style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 12px; border-bottom: 1px solid #F1F5F9; margin-bottom: 14px;">
                    <div style="display: flex; flex-direction: column; gap: 2px;">
                        <h2 style="font-size: 16px; font-weight: 800; color: #161B2E; margin: 0;">
                            Coming Up
                        </h2>
                        <p style="font-size: 12px; color: #94A3B8; font-weight: 500; margin: 0;">Upcoming asset deadlines & tasks</p>
                    </div>
                    <span style="background-color: #F3F2FF; color: #373E8C; font-size: 11.5px; font-weight: 700; padding: 2px 8px; border-radius: 9999px;"><?= count($comingUpList) ?> tasks</span>
                </div>
                
                <div class="coming-items-list">
                    <?php if (!empty($comingUpList)): ?>
                        <?php foreach ($comingUpList as $idx => $item): ?>
                            <?php
                                $itemCampId = !empty($item['campaign_id']) ? (int)$item['campaign_id'] : 0;
                                $itemAssetId = !empty($item['asset_id']) ? (int)$item['asset_id'] : 0;
                                $itemDetailUrl = !empty($item['url']) ? $item['url'] : (pcs_url('detail-asset-maker.php') . ($itemCampId ? '?campaign_id=' . $itemCampId . ($itemAssetId ? '&asset_id=' . $itemAssetId : '') : ''));
                                $badgeClass = $item['badge_type'] ?? 'rejected';
                                $badgeLabel = $item['badge'] ?? 'Rejected';
                                $actionLabel = !empty($item['action_label']) ? $item['action_label'] : 'Detail';
                            ?>
                            <div class="coming-item-card">
                                <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px;">
                                    <div style="display: flex; align-items: center; gap: 8px; min-width: 0;">
                                        <span style="background: rgba(255, 255, 255, 0.9); padding: 1px 6px; border-radius: 4px; font-size: 11px; font-weight: 800; color: #373E8C; flex-shrink: 0;"><?= str_pad($idx + 1, 2, '0', STR_PAD_LEFT) ?></span>
                                        <span style="font-size: 13.5px; font-weight: 700; color: #0F172A; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?= esc_html($item['title']) ?></span>
                                    </div>
                                    <span class="status-pill-small <?= esc_attr($badgeClass) ?>" style="flex-shrink: 0; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px; text-transform: uppercase;">
                                        <?= esc_html($badgeLabel) ?>
                                    </span>
                                </div>
                                <div style="font-size: 12px; color: #64748B; font-weight: 500; display: flex; align-items: center; gap: 6px;">
                                    <span><?= esc_html($item['campaign']) ?></span>
                                </div>
                                <div style="display: flex; align-items: center; justify-content: space-between; padding-top: 6px; border-top: 1px dashed rgba(55, 62, 140, 0.15); margin-top: 2px;">
                                    <span style="font-size: 12px; font-weight: 500; color: #64748B;">
                                        <?= esc_html($item['date']) ?>
                                    </span>
                                    <a href="<?= esc_url($itemDetailUrl) ?>" style="font-size: 12px; font-weight: 700; color: #373E8C; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                        <span><?= esc_html($actionLabel) ?></span>
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 40px 16px; text-align: center; color: #94A3B8;">
                            <p style="font-weight: 700; color: #161B2E; margin-bottom: 4px;">No pending tasks</p>
                            <p style="font-size: 12px;">All assets and deadlines are up to date.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-footer-strip">
                    <?= count($comingUpList) ?> Tasks Total
                </div>
            </aside>

            <!-- --- CỘT PHẢI: BẢNG CAMPAIGNS --- -->
            <section class="campaigns-card" id="campaigns-section">
                <div class="campaigns-top-bar">
                    <h2 class="campaigns-heading">Campaigns</h2>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <div class="filter-pills">
                            <button class="pill-btn active" onclick="filterCampaigns('all', this)">All (<?= $totalCamps ?>)</button>
                            <button class="pill-btn" onclick="filterCampaigns('action_required', this)">Action Required (<?= $actionRequiredCount ?>)</button>
                            <button class="pill-btn" onclick="filterCampaigns('awaiting_review', this)">Awaiting Review (<?= $awaitingReviewCount ?>)</button>
                        </div>
                        <button type="button" class="btn-toggle-expand" id="btnToggleMakerCampaigns" onclick="toggleMakerCampaignsExpansion()" title="Toggle full width table view">
                            <svg class="icon-expand" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>
                            <svg class="icon-compress" style="display:none;" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 14h6v6M20 10h-6V4M14 10l7-7M3 21l7-7"/></svg>
                            <span id="btnToggleMakerText">Full Width</span>
                        </button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="campaign-table">
                        <thead class="table-head">
                            <tr>
                                <th>CAMPAIGN</th>
                                <th style="text-align: center;">DEADLINE</th>
                                <th>PROGRESS</th>
                                <th style="text-align: center;">STATUS</th>
                                <th style="text-align: center;">ACTION</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($campaigns)): ?>
                                <?php foreach ($campaigns as $camp): ?>
                                    <tr class="campaign-row" data-camp-id="<?= $camp['id'] ?>" data-action-required="<?= $camp['has_action_required'] ? '1' : '0' ?>" data-awaiting-review="<?= $camp['has_awaiting_review'] ? '1' : '0' ?>">
                                        <td class="campaign-title-cell">
                                            <a href="<?= esc_url(pcs_url('detail-asset-maker.php') . '?campaign_id=' . $camp['id']) ?>" class="campaign-name-link" style="color: #1E293B; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;" title="View Campaign Details">
                                                <span><?= htmlspecialchars($camp['name']) ?></span>
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#64748B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.6;"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                                            </a>
                                        </td>
                                        <td class="deadline-cell" style="text-align: center;"><?= htmlspecialchars($camp['deadline']) ?></td>
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
                                        <td style="text-align: center;">
                                            <?= function_exists('pcs_format_status_badge') ? pcs_format_status_badge($camp['status']) : '<span class="status-badge-lg neutral">' . htmlspecialchars($camp['status']) . '</span>' ?>
                                        </td>
                                        <td style="text-align: center; white-space: nowrap;">
                                            <div style="display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                                                <a href="<?= esc_url(pcs_url('detail-asset-maker.php') . '?campaign_id=' . (int)$camp['id']) ?>" class="btn-table-action" style="display: inline-flex; align-items: center; justify-content: center; padding: 6px 14px; font-size: 12px; font-weight: 600; text-decoration: none; border-radius: 8px; border: 1px solid #CBD5E1; background: #FFFFFF; color: #334155; transition: all 0.2s; height: 32px; box-sizing: border-box;">
                                                    Open
                                                </a>
                                                <a href="<?= esc_url(pcs_url('smart-maker-upload.php') . '?campaign_id=' . $camp['id'] . '&mode=new_asset') ?>" style="display: inline-flex; align-items: center; justify-content: center; gap: 4px; padding: 6px 14px; border-radius: 8px; background: #373E8C; color: #FFFFFF; font-size: 12px; font-weight: 600; text-decoration: none; border: none; white-space: nowrap; height: 32px; box-sizing: border-box; transition: background 0.2s;" onmouseover="this.style.background='#2A306D'" onmouseout="this.style.background='#373E8C'">+ Add Asset</a>
                                            </div>
                                        </td>
                                    </tr>



                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 60px 20px; color: #64748B;">
                                        <div style="margin-bottom: 12px;">
                                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                                        </div>
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
        <?= pcs_render_weekly_schedule_block($weekOffset, $scheduleDays, $weekLabel) ?>

    </main>

    <!-- ================= 5. POPUP MODAL: MANAGE & UPLOAD GUIDELINES ================= -->
    <div class="modal-overlay" id="uploadGuidelineModal" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px);">
        <div class="modal-box" style="background: #FFFFFF; border-radius: 16px; padding: 24px; max-width: 580px; width: 92%; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h3 class="modal-title" id="guidelineModalTitle" style="font-size: 18px; font-weight: 800; color: #161B2E; margin: 0;">Campaign Brand Guidelines</h3>
                <span style="font-size: 24px; cursor: pointer; color: #94A3B8; line-height: 1;" onclick="closeUploadGuidelineModal()">&times;</span>
            </div>
            <p class="modal-desc" style="font-size: 13px; color: #64748B; margin-bottom: 16px;">View, attach, or remove brand guideline documents and compliance rulebooks for this campaign.</p>

            <!-- Attached Guidelines List -->
            <div style="margin-bottom: 20px;">
                <label style="display: block; font-size: 12px; font-weight: 700; color: #161B2E; margin-bottom: 8px;">CURRENTLY ATTACHED GUIDELINES</label>
                <div id="guidelineAttachedList" style="display: flex; flex-direction: column; gap: 8px; max-height: 200px; overflow-y: auto;">
                    <!-- Injected via JS -->
                </div>
            </div>

            <!-- Upload More Guidelines -->
            <form method="POST" action="" enctype="multipart/form-data" style="border-top: 1px solid #F1F5F9; padding-top: 16px;">
                <input type="hidden" name="action" value="upload_guideline">
                <input type="hidden" name="campaign_id" id="guidelineCampId" value="">

                <div class="form-row" style="margin-bottom: 16px;">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Attach More Guideline Documents (PDF, DOCX, TXT, Images)</label>
                    <input type="file" name="guideline_files[]" class="form-input" accept=".pdf,.doc,.docx,.txt,.png,.jpg,.jpeg" multiple required style="padding: 8px 12px; width: 100%; border: 1px solid #CBD5E1; border-radius: 8px;">
                </div>

                <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-secondary" style="padding: 8px 16px; border-radius: 8px; border: 1px solid #CBD5E1; background: #F8FAFC; cursor: pointer; font-weight: 600;" onclick="closeUploadGuidelineModal()">Close</button>
                    <button type="submit" class="btn-primary" style="padding: 8px 16px; border-radius: 8px; background: #373E8C; color: #FFFFFF; border: none; cursor: pointer; font-weight: 700;">Upload Files</button>
                </div>
            </form>

            <!-- Hidden delete form -->
            <form id="deleteGuidelineForm" method="POST" action="" style="display: none;">
                <input type="hidden" name="action" value="delete_guideline">
                <input type="hidden" name="campaign_id" id="deleteGuidelineCampId" value="">
                <input type="hidden" name="guideline_url" id="deleteGuidelineUrl" value="">
            </form>
        </div>
    </div>

    <!-- ================= 8. JAVASCRIPT ================= -->
    <script>
        // Auto-expand Campaigns if loaded with #campaigns-section
        if (window.location.hash === '#campaigns-section') {
            setTimeout(function() {
                if (typeof toggleCampaignsExpansion === 'function') {
                    toggleCampaignsExpansion(true);
                }
            }, 100);
        }

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

        // 3. Filter Pills (All / Action Required / Awaiting Review)
        function filterCampaigns(group, btn) {
            document.querySelectorAll('.filter-pills .pill-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const rows = document.querySelectorAll('.campaign-row');
            rows.forEach(r => {
                const hasAction = r.getAttribute('data-action-required') === '1';
                const hasAwaiting = r.getAttribute('data-awaiting-review') === '1';

                let show = false;
                if (group === 'all') {
                    show = true;
                } else if (group === 'action_required') {
                    show = hasAction;
                } else if (group === 'awaiting_review') {
                    show = hasAwaiting;
                }

                r.style.display = show ? '' : 'none';
            });
        }

        // 4. Manage & Upload Guideline Modal (Full CRUD)
        function openUploadGuidelineModal(campId, campName, guidelines) {
            const m = document.getElementById('uploadGuidelineModal');
            const inp = document.getElementById('guidelineCampId');
            const title = document.getElementById('guidelineModalTitle');
            const list = document.getElementById('guidelineAttachedList');
            if (inp) inp.value = campId;
            if (title) title.innerText = 'Campaign Guidelines - ' + campName;

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
                        left.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#4F56A6" stroke-width="2" style="flex-shrink: 0;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg><span style="font-size: 12.5px; font-weight: 600; color: #1E293B; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">' + (g.name || 'Guideline') + '</span>';

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

            if (m) m.style.display = 'flex';
        }
        function closeUploadGuidelineModal() {
            const m = document.getElementById('uploadGuidelineModal');
            if (m) m.style.display = 'none';
        }

        // Prevent default drag/drop behaviors across entire window to prevent browser from navigating away
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            window.addEventListener(eventName, function(e) {
                e.preventDefault();
            }, false);
        });

        // Toggle Maker Campaigns Expansion (Split View <-> Full Width)
        function toggleMakerCampaignsExpansion(forceExpand) {
            const grid = document.querySelector('.workspace-grid');
            const btn = document.getElementById('btnToggleMakerCampaigns');
            const text = document.getElementById('btnToggleMakerText');
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
        // Edit Asset Modal Functions
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

        // Smooth schedule navigation via AJAX (no reload, zero flash)
        function navigateScheduleWeek(offset, e) {
            if (e) {
                e.preventDefault();
                e.stopPropagation();
            }
            var schedSec = document.getElementById('schedule-section');
            if (!schedSec) return;

            var calGrid = schedSec.querySelector('.calendar-grid');
            if (calGrid) calGrid.style.opacity = '0.35';

            var url = window.location.pathname + '?action=pcs_get_schedule_week&week_offset=' + encodeURIComponent(offset) + '&t=' + Date.now();
            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data && data.success && data.html) {
                        var temp = document.createElement('div');
                        temp.innerHTML = data.html;
                        var newSec = temp.querySelector('#schedule-section') || temp.firstElementChild;
                        if (newSec) {
                            schedSec.innerHTML = newSec.innerHTML;
                        }
                        try {
                            var newUrl = window.location.pathname + (offset !== 0 ? '?week_offset=' + offset : '') + '#schedule-section';
                            window.history.replaceState({ week_offset: offset }, '', newUrl);
                        } catch(err) {}
                    }
                })
                .catch(function() {
                    window.location.href = '?week_offset=' + offset + '#schedule-section';
                });
        }

        // Calendar Month Picker Module
        var pickerCurrentYear = <?= (int)date('Y') ?>;
        var pickerCurrentMonth = <?= (int)date('n') ?> - 1; // 0-indexed
        var pickerSelectedDate = new Date();

        function toggleCalendarMonthPopover(e) {
            if (e) {
                e.stopPropagation();
                e.preventDefault();
            }
            var pop = document.getElementById('calendarMonthPopover');
            if (!pop) return;
            var isShown = pop.style.display === 'block';
            if (isShown) {
                pop.style.display = 'none';
            } else {
                pop.style.display = 'block';
                renderCalendarMonthGrid(pickerCurrentYear, pickerCurrentMonth);
            }
        }

        function closeCalendarMonthPopover() {
            var pop = document.getElementById('calendarMonthPopover');
            if (pop) pop.style.display = 'none';
        }

        function changeMonthInPicker(delta, e) {
            if (e) {
                e.stopPropagation();
                e.preventDefault();
            }
            pickerCurrentMonth += delta;
            if (pickerCurrentMonth < 0) {
                pickerCurrentMonth = 11;
                pickerCurrentYear--;
            } else if (pickerCurrentMonth > 11) {
                pickerCurrentMonth = 0;
                pickerCurrentYear++;
            }
            renderCalendarMonthGrid(pickerCurrentYear, pickerCurrentMonth);
        }

        function renderCalendarMonthGrid(year, month) {
            var monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
            var headerEl = document.getElementById('popoverMonthYear');
            if (headerEl) headerEl.textContent = monthNames[month] + ' ' + year;

            var gridEl = document.getElementById('popoverDaysGrid');
            if (!gridEl) return;
            gridEl.innerHTML = '';

            var firstDayIndex = new Date(year, month, 1).getDay(); // 0 is Sunday
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var prevMonthDays = new Date(year, month, 0).getDate();

            // Previous month trailing days
            for (var p = firstDayIndex - 1; p >= 0; p--) {
                var prevDayNum = prevMonthDays - p;
                var pCell = document.createElement('div');
                pCell.style.cssText = 'padding: 6px 0; font-size: 11.5px; color: #CBD5E1; border-radius: 6px; user-select: none;';
                pCell.textContent = prevDayNum;
                gridEl.appendChild(pCell);
            }

            // Current month days
            var today = new Date();
            for (var d = 1; d <= daysInMonth; d++) {
                (function(dayNum) {
                    var cell = document.createElement('button');
                    cell.type = 'button';
                    var isToday = (today.getFullYear() === year && today.getMonth() === month && today.getDate() === dayNum);
                    var isSelected = (pickerSelectedDate.getFullYear() === year && pickerSelectedDate.getMonth() === month && pickerSelectedDate.getDate() === dayNum);

                    var bg = isSelected ? '#373E8C' : (isToday ? '#EEF2FF' : 'transparent');
                    var color = isSelected ? '#FFFFFF' : (isToday ? '#373E8C' : '#1E293B');
                    var fw = (isSelected || isToday) ? '700' : '500';

                    cell.style.cssText = 'padding: 6px 0; font-size: 12px; font-weight: ' + fw + '; color: ' + color + '; background: ' + bg + '; border: none; border-radius: 6px; cursor: pointer; width: 100%; transition: background 0.15s; font-family: inherit;';
                    cell.textContent = dayNum;

                    cell.onmouseover = function() {
                        if (!isSelected) cell.style.background = '#F1F5F9';
                    };
                    cell.onmouseout = function() {
                        if (!isSelected) cell.style.background = bg;
                    };

                    cell.onclick = function(ev) {
                        if (ev) ev.stopPropagation();
                        selectCalendarDate(year, month, dayNum);
                    };

                    gridEl.appendChild(cell);
                })(d);
            }

            var footerEl = document.getElementById('popoverSelectedDateFooter');
            if (footerEl) {
                var opt = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                footerEl.textContent = pickerSelectedDate.toLocaleDateString('en-US', opt);
            }
        }

        function selectCalendarDate(year, month, day) {
            pickerSelectedDate = new Date(year, month, day);
            var target = new Date(year, month, day);
            var today = new Date();

            var targetDow = target.getDay() || 7; // 1 (Mon) - 7 (Sun)
            var targetMon = new Date(target);
            targetMon.setDate(target.getDate() - (targetDow - 1));
            targetMon.setHours(0,0,0,0);

            var todayDow = today.getDay() || 7;
            var currentMon = new Date(today);
            currentMon.setDate(today.getDate() - (todayDow - 1));
            currentMon.setHours(0,0,0,0);

            var diffWeeks = Math.round((targetMon - currentMon) / (7 * 24 * 60 * 60 * 1000));
            closeCalendarMonthPopover();
            navigateScheduleWeek(diffWeeks);
        }

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.week-indicator-pill-wrap')) {
                closeCalendarMonthPopover();
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
                    <button type="button" class="btn-table-action" style="padding: 8px 16px; border: 1px solid #CBD5E1; border-radius: 8px; background: #fff; cursor: pointer;" onclick="closeEditAssetModal()">Cancel</button>
                    <button type="submit" class="btn-asset-solid" style="padding: 8px 18px; border-radius: 8px; background: #373E8C; color: #fff; font-weight: 700; border: none; cursor: pointer;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

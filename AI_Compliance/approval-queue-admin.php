<?php
/**
 * Template Name: Approval Queue - Admin
 * Description: Dedicated Standalone Approval Queue Screen for Admin Role - 100% CPT WordPress Database Integration
 * RBAC: Restricted to Admin Role Only
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

// 3. Current User & RBAC Authentication (Admin Only)
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
    header('Location: ' . $target . (strpos($target, '?') !== false ? '&' : '?') . 'loggedout=true');
    exit;
}

// 5. Handle Admin Action: Production Release
$releaseAlert = '';
$releaseSuccess = false;
$reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($reqMethod === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_release') {
    $version_id = (int) ($_POST['version_id'] ?? 0);
    $release_notes = sanitize_text_field($_POST['release_notes'] ?? '');
    if ($version_id > 0 && function_exists('handle_admin_release')) {
        $res = handle_admin_release($version_id, $release_notes);
        if (!empty($res['success'])) {
            $releaseSuccess = true;
            $releaseAlert = 'Version V' . ($res['version_num'] ?? '') . ' was successfully signed off and released to production!';
        } else {
            $releaseAlert = $res['message'] ?? 'Unable to complete production release.';
        }
    }
}

// 6. Navigation URLs
$adminDashboardUrl = function_exists('pcs_url') ? pcs_url('admin-dashboard.php') : 'admin-dashboard.php';
$finalReviewBaseUrl = function_exists('pcs_url') ? pcs_url('final-review-admin.php') : 'final-review-admin.php';
$detailAdminBaseUrl = function_exists('pcs_url') ? pcs_url('detail-asset-admin.php') : 'detail-asset-admin.php';
$auditLogBaseUrl = function_exists('pcs_url') ? pcs_url('audit-log.php') : 'audit-log.php';

// 7. Dynamic Data Query: Approval Queue Items
$rawQueue = function_exists('get_admin_release_queue') ? get_admin_release_queue('awaiting_release') : [];

$needsAttentionItems = [];
$otherPendingItems = [];
$uniqueCheckers = [];

foreach ($rawQueue as $item) {
    $chk = trim($item['checker_name'] ?? '');
    if ($chk !== '' && $chk !== '—' && !in_array($chk, $uniqueCheckers, true)) {
        $uniqueCheckers[] = $chk;
    }

    // Determine if item requires urgent attention (<= 14 days or overdue)
    $days = $item['days_remaining'] ?? null;
    $dueSoon = !empty($item['due_soon']) || ($days !== null && $days <= 14);

    if ($dueSoon) {
        $needsAttentionItems[] = $item;
    } else {
        $otherPendingItems[] = $item;
    }
}

$totalAwaiting = count($rawQueue);
$needsAttentionCount = count($needsAttentionItems);
$otherPendingCount = count($otherPendingItems);
sort($uniqueCheckers);
?>
<!DOCTYPE html>
<html <?php if (function_exists('language_attributes')) language_attributes(); ?>>
<head>
    <meta charset="<?php echo function_exists('bloginfo') ? bloginfo('charset') : 'UTF-8'; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Queue - Admin Workspace | BrandCompliance</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
            -moz-osx-font-smoothing: grayscale;
            min-height: 100vh;
        }

        .ApprovalQueueAdmin {
            width: 100%;
            max-width: 1400px;
            margin: 0 auto;
            background: linear-gradient(0deg, #FAF8FF 0%, #FAF8FF 100%), white;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
            display: flex;
            min-height: calc(100vh - 72px);
        }

        .main-viewport {
            align-self: stretch;
            min-height: 800px;
            padding: 24px 28px 48px;
            background: #FAF8FF;
            flex-direction: column;
            justify-content: flex-start;
            align-items: stretch;
            display: flex;
            gap: 24px;
        }

        /* Flash Message Alert */
        .flash-alert {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 8px;
            animation: fadeIn 0.3s ease;
        }
        .flash-alert.success {
            background-color: #E6F7ED;
            color: #03543F;
            border: 1px solid #BCF0DA;
        }
        .flash-alert.error {
            background-color: #FDF2F2;
            color: #9B1C1C;
            border: 1px solid #F8B4B4;
        }
        .flash-alert-close {
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
            color: inherit;
            opacity: 0.6;
        }
        .flash-alert-close:hover { opacity: 1; }

        /* Section 1: Hero Intro */
        .section-intro {
            align-self: stretch;
            min-height: 130px;
            padding: 28px 32px;
            position: relative;
            background: #F3F0FC;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border-radius: 16px;
            justify-content: space-between;
            align-items: center;
            display: flex;
        }

        .concentric-arcs {
            width: 384px;
            height: 132px;
            right: 0px;
            top: 0px;
            position: absolute;
            overflow: hidden;
            pointer-events: none;
        }

        .hero-left {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .hero-tag {
            color: #4E56A6;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 16.5px;
            letter-spacing: 1.1px;
        }

        .hero-title {
            color: #1F2A5A;
            font-size: 28px;
            font-weight: 700;
            line-height: 32px;
        }

        .hero-subtitle {
            color: #5F657B;
            font-size: 13px;
            font-weight: 400;
            line-height: 19.5px;
        }

        .hero-metrics {
            position: relative;
            z-index: 2;
            padding-left: 32px;
            border-left: 1px rgba(213, 217, 243, 0.70) solid;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
        }

        .metric-counter-row {
            display: flex;
            align-items: baseline;
            gap: 8px;
        }

        .metric-num {
            color: #1F2A5A;
            font-size: 28px;
            font-weight: 700;
            line-height: 28px;
        }

        .metric-label {
            color: #5F657B;
            font-size: 12px;
            font-weight: 500;
            line-height: 18px;
        }

        .attention-pill {
            padding: 3px 10px;
            background: #FEF3E2;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #B46505;
            font-size: 11px;
            font-weight: 600;
            line-height: 16.5px;
        }

        .dot-amber {
            width: 6px;
            height: 6px;
            border-radius: 3px;
            background: #B46505;
            display: inline-block;
        }

        /* Section 2: Filters Toolbar */
        .filters-toolbar {
            align-self: stretch;
            padding: 12px;
            background: white;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(226, 230, 250, 0.70);
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            display: flex;
            flex-wrap: wrap;
        }

        .toolbar-left {
            flex: 1 1 0;
            min-width: 300px;
            justify-content: flex-start;
            align-items: center;
            gap: 10px;
            display: flex;
            flex-wrap: wrap;
        }

        .search-box {
            width: 320px;
            position: relative;
            display: inline-flex;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #6B7280;
            pointer-events: none;
            display: flex;
            align-items: center;
        }

        .search-input {
            width: 100%;
            height: 38px;
            padding: 8px 12px 8px 36px;
            background: white;
            border: 1px solid rgba(226, 230, 250, 0.80);
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            color: #161B2E;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .search-input:focus {
            border-color: #4E56A6;
            box-shadow: 0 0 0 3px rgba(78, 86, 166, 0.12);
        }

        .filter-select-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
        }

        .filter-select {
            height: 38px;
            padding: 8px 32px 8px 12px;
            background: white;
            border: 1px solid rgba(226, 230, 250, 0.80);
            border-radius: 8px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 500;
            color: #161B2E;
            cursor: pointer;
            outline: none;
            appearance: none;
            -webkit-appearance: none;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.03);
            transition: border-color 0.15s ease;
        }

        .filter-select:focus {
            border-color: #4E56A6;
        }

        .select-caret {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #5F657B;
            display: flex;
            align-items: center;
        }

        .clear-filters-btn {
            height: 38px;
            padding: 8px 14px;
            border-radius: 8px;
            border: 1px solid rgba(78, 86, 166, 0.20);
            background: transparent;
            color: #4E56A6;
            font-size: 12px;
            font-family: inherit;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }

        .clear-filters-btn:hover {
            background: #EDE9FA;
            border-color: #4E56A6;
        }

        /* Section 3: Continuous Table */
        .table-card {
            align-self: stretch;
            background: white;
            overflow: hidden;
            border-radius: 12px;
            border: 1px solid rgba(226, 230, 250, 0.70);
            box-shadow: 0px 1px 3px rgba(0, 0, 0, 0.04);
            display: flex;
            flex-direction: column;
        }

        .table-scroll {
            width: 100%;
            overflow-x: auto;
        }

        .queue-table {
            width: 100%;
            min-width: 1040px;
            border-collapse: collapse;
            text-align: left;
        }

        .queue-thead tr {
            background: #F4F3FD;
            border-bottom: 1px solid #E2E6FA;
        }

        .queue-th {
            padding: 12px 16px;
            color: #5F657B;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.55px;
            white-space: nowrap;
        }
        .queue-th.col-asset { width: 30%; padding-left: 20px; }
        .queue-th.col-version { width: 8%; text-align: center; }
        .queue-th.col-users { width: 20%; }
        .queue-th.col-approved { width: 16%; text-align: center; }
        .queue-th.col-deadline { width: 10%; text-align: center; }
        .queue-th.col-status { width: 14%; text-align: center; }
        .queue-th.col-action { width: 16%; text-align: center; }

        /* Section Subheaders */
        .sub-header-row td {
            background: #F4F3FC;
            padding: 10px 20px;
            border-left: 3px solid #4E56A6;
            border-top: 1px solid #E2E6FA;
            border-bottom: 1px solid #E2E6FA;
        }

        .sub-header-title {
            color: #4E56A6;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.55px;
        }

        /* Table Data Rows */
        .queue-row {
            border-top: 1px solid #F3F0FC;
            transition: background-color 0.12s ease;
        }

        .queue-row:hover {
            background-color: #FAF9FE;
        }

        .queue-td {
            padding: 16px;
            vertical-align: middle;
            color: #161B2E;
            font-size: 13px;
        }

        .queue-td.col-asset {
            padding-left: 20px;
        }

        .asset-link {
            color: #1F2A5A;
            font-size: 14px;
            font-weight: 700;
            line-height: 19.5px;
            text-decoration: none;
            display: inline-block;
            transition: color 0.15s ease;
        }

        .asset-link:hover {
            color: #4E56A6;
            text-decoration: underline;
        }

        .campaign-name-row {
            color: #5F657B;
            font-size: 12px;
            font-weight: 400;
            line-height: 16.5px;
            margin-top: 2px;
        }

        .version-badge {
            display: inline-flex;
            padding: 4px 10px;
            background: #EDE9FA;
            border-radius: 6px;
            color: #4E56A6;
            font-size: 12px;
            font-weight: 600;
            line-height: 16px;
        }

        .user-line-maker {
            color: #161B2E;
            font-size: 12px;
            font-weight: 500;
            line-height: 16px;
        }

        .user-line-checker {
            color: #5F657B;
            font-size: 12px;
            font-weight: 400;
            line-height: 16px;
            margin-top: 2px;
        }

        .approved-time-text {
            color: #5F657B;
            font-size: 12px;
            font-weight: 400;
            line-height: 18px;
            white-space: nowrap;
        }

        .deadline-cell-content {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }

        .deadline-date-text {
            color: #1F2A5A;
            font-size: 13px;
            font-weight: 600;
            line-height: 16px;
        }

        .deadline-pill-due-soon {
            padding: 2px 8px;
            background: #FFF8E6;
            border-radius: 9999px;
            border: 1px solid #FFEBAA;
            color: #B46505;
            font-size: 10px;
            font-weight: 700;
            line-height: 16px;
            white-space: nowrap;
        }

        .deadline-pill-overdue {
            padding: 2px 8px;
            background: #FDF2F2;
            border-radius: 9999px;
            border: 1px solid #F8B4B4;
            color: #9B1C1C;
            font-size: 10px;
            font-weight: 700;
            line-height: 16px;
            white-space: nowrap;
        }

        .deadline-days-muted {
            color: #5F657B;
            font-size: 11px;
            font-weight: 500;
            line-height: 16px;
        }

        .status-pill-awaiting {
            padding: 4px 10px;
            background: #FFF8E6;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #B46505;
            font-size: 11px;
            font-weight: 500;
            line-height: 16px;
            white-space: nowrap;
        }

        .btn-open-review {
            padding: 8px 16px;
            background: #4E56A6;
            box-shadow: 0px 1px 2px rgba(0, 0, 0, 0.05);
            border-radius: 8px;
            border: none;
            color: white;
            font-size: 12px;
            font-family: inherit;
            font-weight: 600;
            line-height: 16px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            text-decoration: none;
            transition: background 0.15s ease, transform 0.1s ease;
            white-space: nowrap;
        }

        .btn-open-review:hover {
            background: #3D4389;
            transform: translateY(-1px);
        }

        .btn-quick-release {
            padding: 8px 12px;
            background: #E8F5E9;
            border: 1px solid #BBF7D0;
            border-radius: 8px;
            color: #166534;
            font-size: 11.5px;
            font-family: inherit;
            font-weight: 600;
            line-height: 16px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none;
            transition: background 0.15s ease, transform 0.1s ease;
            white-space: nowrap;
        }

        .btn-quick-release:hover {
            background: #DCFCE7;
            color: #15803D;
            transform: translateY(-1px);
        }

        .queue-td.col-version { text-align: center; }
        .queue-td.col-approved { text-align: center; }
        .queue-td.col-deadline { text-align: center; }
        .queue-td.col-status { text-align: center; }
        .queue-td.col-action {
            text-align: center;
        }

        /* Empty State */
        .empty-state-card {
            padding: 56px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            gap: 14px;
        }

        .empty-state-icon {
            width: 52px;
            height: 52px;
            border-radius: 26px;
            background: #EDE9FA;
            color: #4E56A6;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 4px;
        }

        .empty-state-title {
            color: #1F2A5A;
            font-size: 18px;
            font-weight: 700;
        }

        .empty-state-desc {
            color: #5F657B;
            font-size: 13px;
            max-width: 460px;
            line-height: 20px;
        }

        .empty-state-btn {
            margin-top: 6px;
            padding: 10px 20px;
            background: #4E56A6;
            color: white;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.15s ease;
        }

        .empty-state-btn:hover {
            background: #3D4389;
        }

        .section-empty-row td {
            padding: 24px 20px;
            text-align: center;
            color: #5F657B;
            font-size: 13px;
            background: #FAFAFF;
        }

        /* Access Restricted State */
        .access-restricted-card {
            padding: 64px 32px;
            background: white;
            border-radius: 16px;
            border: 1px solid #E2E6FA;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            max-width: 600px;
            margin: 40px auto;
        }

        /* Modal Styles */
        .modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(22, 27, 46, 0.55);
            backdrop-filter: blur(4px);
            z-index: 9999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeIn 0.2s ease;
        }

        .modal-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            width: 100%;
            max-width: 600px;
            overflow: hidden;
            border: 1px solid #E2E6FA;
            display: flex;
            flex-direction: column;
            animation: scaleIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .modal-header {
            padding: 24px 28px 18px;
            border-bottom: 1px solid #E2E6FA;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: #F9F8FD;
        }

        .modal-title {
            color: #1F2A5A;
            font-size: 18px;
            font-weight: 700;
        }

        .modal-subtitle {
            color: #5F657B;
            font-size: 13px;
            margin-top: 4px;
        }

        .modal-close-btn {
            background: none;
            border: none;
            font-size: 20px;
            color: #5F657B;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
        }
        .modal-close-btn:hover { background: #EDE9FA; color: #1F2A5A; }

        .modal-body {
            padding: 24px 28px;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .modal-summary-box {
            background: #F4F3FD;
            border-radius: 10px;
            padding: 16px;
            border: 1px solid #E2E6FA;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .modal-summary-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .modal-summary-label {
            color: #5F657B;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .modal-summary-value {
            color: #1F2A5A;
            font-size: 13px;
            font-weight: 600;
        }

        .modal-textarea-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .modal-label {
            color: #161B2E;
            font-size: 13px;
            font-weight: 600;
        }

        .modal-textarea {
            width: 100%;
            height: 84px;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #D5D9F3;
            font-family: inherit;
            font-size: 13px;
            color: #161B2E;
            resize: vertical;
            outline: none;
        }
        .modal-textarea:focus { border-color: #4E56A6; box-shadow: 0 0 0 3px rgba(78, 86, 166, 0.12); }

        .modal-certify-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            background: #FFFDF5;
            border: 1px solid #FFEBAA;
            border-radius: 8px;
        }

        .modal-certify-text {
            color: #8C5303;
            font-size: 12px;
            line-height: 18px;
        }

        .modal-footer {
            padding: 18px 28px 24px;
            border-top: 1px solid #E2E6FA;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #FAF8FF;
            gap: 12px;
        }

        .modal-btn-cancel {
            padding: 10px 18px;
            background: white;
            border: 1px solid #D5D9F3;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: #5F657B;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .modal-btn-cancel:hover { background: #F4F3FD; color: #1F2A5A; }

        .modal-actions-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-btn-inspect {
            padding: 10px 16px;
            background: #EDE9FA;
            border: 1px solid #D5D9F3;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: #4E56A6;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }
        .modal-btn-inspect:hover { background: #DFD9F7; }

        .modal-btn-confirm {
            padding: 10px 20px;
            background: #4E56A6;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            color: white;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
            transition: background 0.15s ease;
        }
        .modal-btn-confirm:hover { background: #3D4389; }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes scaleIn {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        @media (max-width: 900px) {
            .section-intro {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
            .hero-metrics {
                padding-left: 0;
                border-left: none;
                align-items: flex-start;
            }
            .toolbar-left {
                width: 100%;
            }
            .search-box {
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- 1. TOP NAVBAR (UNIFIED ACROSS ALL SCREENS) -->
    <?php
    if (function_exists('pcs_render_top_navbar')) {
        pcs_render_top_navbar([
            'user'              => $currentUser,
            'all_campaigns_url' => $adminDashboardUrl,
            'btn_text'          => 'Admin Workspace'
        ]);
    }
    ?>

<div data-layer="approval queue - admin" class="ApprovalQueueAdmin">

    <!-- 2. MAIN VIEWPORT CONTENT -->
    <main data-layer="Main" class="main-viewport">

        <!-- Flash Notice / Alert -->
        <?php if (!empty($releaseAlert)): ?>
            <div class="flash-alert <?php echo $releaseSuccess ? 'success' : 'error'; ?>">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                        <?php if ($releaseSuccess): ?>
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        <?php else: ?>
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        <?php endif; ?>
                    </svg>
                    <span><?php echo esc_html($releaseAlert); ?></span>
                </div>
                <button type="button" class="flash-alert-close" onclick="this.parentElement.remove();">&times;</button>
            </div>
        <?php endif; ?>

        <?php if (!$isAllowed): ?>
            <!-- RBAC Access Restricted State -->
            <div class="access-restricted-card">
                <div style="width: 56px; height: 56px; border-radius: 28px; background: #FDE8E8; color: #9B1C1C; display: flex; align-items: center; justify-content: center;">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line></svg>
                </div>
                <h2 style="color: #1F2A5A; font-size: 20px; font-weight: 700;">Admin Access Required</h2>
                <p style="color: #5F657B; font-size: 14px; max-width: 440px; line-height: 22px;">
                    This dedicated Approval Queue is restricted to compliance administrators. You are currently logged in as <strong><?php echo esc_html($userRole); ?></strong>.
                </p>
                <div style="display: flex; gap: 12px; margin-top: 8px;">
                    <a href="<?php echo esc_url(function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php'); ?>" class="empty-state-btn">
                        Go to Checker Workspace
                    </a>
                    <a href="<?php echo esc_url(function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php'); ?>" style="padding: 10px 18px; border: 1px solid #D5D9F3; border-radius: 8px; color: #5F657B; font-size: 13px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center;">
                        Switch Account
                    </a>
                </div>
            </div>
        <?php else: ?>

            <!-- SECTION 1: COMPACT LAVENDER PAGE INTRODUCTION -->
            <section data-layer="Section - 1. Compact Lavender Page Introduction" class="section-intro">
                <!-- Decorative SVG Concentric Arcs -->
                <div data-layer="SVG - Concentric Framing Decorative Arcs" class="concentric-arcs">
                    <div data-svg-wrapper style="position: absolute; right: 0; top: 0;">
                        <svg width="280" height="132" viewBox="0 0 280 132" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path opacity="0.45" d="M120 -18.857C120 43.588 171.62 94.286 235.2 94.286C298.78 94.286 350.4 43.588 350.4 -18.857" stroke="#E2E6FA" stroke-width="1.427"/>
                            <path opacity="0.6" d="M160 -18.857C160 30.588 201.62 70.286 255.2 70.286C308.78 70.286 350.4 30.588 350.4 -18.857" stroke="#E2E6FA" stroke-width="1.427"/>
                            <path opacity="0.8" d="M200 -18.857C200 15.588 226.62 42.286 265.2 42.286C303.78 42.286 330.4 15.588 330.4 -18.857" stroke="#E2E6FA" stroke-width="1.427"/>
                            <path d="M235 -18.857C235 2 248.5 16 270 16C291.5 16 305 2 305 -18.857" stroke="#E2E6FA" stroke-width="1.9" stroke-opacity="0.7"/>
                        </svg>
                    </div>
                </div>

                <!-- Left Content -->
                <div class="hero-left">
                    <div class="hero-tag">ADMIN WORKSPACE</div>
                    <h1 class="hero-title">Approval Queue</h1>
                    <div class="hero-subtitle">Awaiting final release</div>
                </div>

                <!-- Right Metrics -->
                <div class="hero-metrics">
                    <div class="metric-counter-row">
                        <span id="heroTotalCounter" class="metric-num"><?php echo (int) $totalAwaiting; ?></span>
                        <span class="metric-label">Approved</span>
                    </div>
                    <div class="attention-pill">
                        <span class="dot-amber"></span>
                        <span id="heroAttentionLabel"><?php echo (int) $needsAttentionCount; ?> Needs Attention</span>
                    </div>
                </div>
            </section>

            <!-- SECTION 2: QUEUE SEARCH AND FILTERS TOOLBAR -->
            <section data-layer="Section - 2. Queue Search and Filters Toolbar" class="filters-toolbar">
                <div class="toolbar-left">
                    <!-- Search Input -->
                    <div class="search-box">
                        <div class="search-icon">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M7.33333 12.6667C10.2789 12.6667 12.6667 10.2789 12.6667 7.33333C12.6667 4.38781 10.2789 2 7.33333 2C4.38781 2 2 4.38781 2 7.33333C2 10.2789 4.38781 12.6667 7.33333 12.6667Z" stroke="#6B7280" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M14 14L11.1 11.1" stroke="#6B7280" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <input type="text" id="queueSearchInput" class="search-input" placeholder="Search campaign or asset..." autocomplete="off">
                    </div>

                    <!-- Dropdown 1: Deadline Filter -->
                    <div class="filter-select-wrapper">
                        <select id="deadlineFilter" class="filter-select">
                            <option value="all">All Deadlines</option>
                            <option value="due_soon">Due Soon (&le; 14d)</option>
                            <option value="overdue">Overdue</option>
                            <option value="due_later">Due Later (&gt; 14d)</option>
                        </select>
                        <div class="select-caret">
                            <svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 1L5 5L9 1" stroke="#5F657B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Dropdown 2: Checker Filter -->
                    <div class="filter-select-wrapper">
                        <select id="checkerFilter" class="filter-select">
                            <option value="all">All Checkers</option>
                            <?php foreach ($uniqueCheckers as $chk): ?>
                                <option value="<?php echo esc_attr($chk); ?>"><?php echo esc_html($chk); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="select-caret">
                            <svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 1L5 5L9 1" stroke="#5F657B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>

                    <!-- Dropdown 3: Sort Order -->
                    <div class="filter-select-wrapper">
                        <select id="sortOrder" class="filter-select">
                            <option value="earliest_deadline">Earliest Deadline</option>
                            <option value="latest_deadline">Latest Deadline</option>
                            <option value="recently_approved">Recently Approved</option>
                            <option value="asset_name">Asset Name A-Z</option>
                        </select>
                        <div class="select-caret">
                            <svg width="10" height="6" viewBox="0 0 10 6" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M1 1L5 5L9 1" stroke="#5F657B" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Clear Filters Button -->
                <button type="button" id="clearFiltersBtn" class="clear-filters-btn">
                    <svg width="12" height="12" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M5 10C3.60417 10 2.42188 9.51562 1.45312 8.54688C0.484375 7.57812 0 6.39583 0 5C0 3.60417 0.484375 2.42188 1.45312 1.45312C2.42188 0.484375 3.60417 0 5 0C5.71875 0 6.40625 0.148438 7.0625 0.445312C7.71875 0.742188 8.28125 1.16667 8.75 1.71875V0H10V4.375H5.625V3.125H8.25C7.91667 2.54167 7.46094 2.08333 6.88281 1.75C6.30469 1.41667 5.67708 1.25 5 1.25C3.95833 1.25 3.07292 1.61458 2.34375 2.34375C1.61458 3.07292 1.25 3.95833 1.25 5C1.25 6.04167 1.61458 6.92708 2.34375 7.65625C3.07292 8.38542 3.95833 8.75 5 8.75C5.80208 8.75 6.52604 8.52083 7.17188 8.0625C7.81771 7.60417 8.27083 7 8.53125 6.25H9.84375C9.55208 7.35417 8.95833 8.25521 8.0625 8.95312C7.16667 9.65104 6.14583 10 5 10V10" fill="#4E56A6"/>
                    </svg>
                    <span>Clear Filters</span>
                </button>
            </section>

            <!-- SECTION 3: MAIN APPROVAL QUEUE - SINGLE CONTINUOUS TABLE -->
            <section data-layer="3. MAIN APPROVAL QUEUE" class="table-card">
                <?php if ($totalAwaiting === 0): ?>
                    <!-- Database Zero State: No assets awaiting release -->
                    <div class="empty-state-card">
                        <div class="empty-state-icon">
                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <h2 class="empty-state-title">No Assets Awaiting Release</h2>
                        <p class="empty-state-desc">
                            All creative assets in WordPress have been signed off and released to production, or no checker approvals are currently pending.
                        </p>
                        <a href="<?php echo esc_url($adminDashboardUrl); ?>" class="empty-state-btn">
                            <span>Return to Admin Workspace</span>
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="5" y1="12" x2="19" y2="12"></line>
                                <polyline points="12 5 19 12 12 19"></polyline>
                            </svg>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-scroll">
                        <table class="queue-table" id="approvalQueueTable">
                            <thead class="queue-thead">
                                <tr>
                                    <th class="queue-th col-asset">ASSET / CAMPAIGN</th>
                                    <th class="queue-th col-version">VERSION</th>
                                    <th class="queue-th col-users">MAKER / CHECKER</th>
                                    <th class="queue-th col-approved">APPROVED AT</th>
                                    <th class="queue-th col-deadline">DEADLINE</th>
                                    <th class="queue-th col-status">STATUS</th>
                                    <th class="queue-th col-action">ACTION</th>
                                </tr>
                            </thead>
                            <tbody>

                                <!-- SUB-SECTION 1: NEEDS ATTENTION -->
                                <tr class="sub-header-row" id="needsAttentionSubHeader">
                                    <td colspan="7">
                                        <div class="sub-header-title" id="needsAttentionTitle">
                                            NEEDS ATTENTION &middot; <span id="needsAttentionCountSpan"><?php echo (int) $needsAttentionCount; ?></span> ITEMS
                                        </div>
                                    </td>
                                </tr>

                                <?php if (!empty($needsAttentionItems)): ?>
                                    <?php foreach ($needsAttentionItems as $item): 
                                        $cId = (int) ($item['campaign_id'] ?? 0);
                                        $aId = (int) ($item['asset_id'] ?? 0);
                                        $vId = (int) ($item['version_id'] ?? 0);
                                        $detailUrl = $cId && $aId ? add_query_arg(['campaign_id' => $cId, 'asset_id' => $aId], $detailAdminBaseUrl) : '#';
                                        $finalReviewUrl = add_query_arg([
                                            'version_id'  => $vId,
                                            'asset_id'    => $aId,
                                            'campaign_id' => $cId
                                        ], $finalReviewBaseUrl);
                                        $days = $item['days_remaining'] ?? null;
                                        $isOverdue = ($days !== null && $days < 0);
                                    ?>
                                        <tr class="queue-row queue-item"
                                            data-group="needs_attention"
                                            data-asset-name="<?php echo esc_attr(strtolower($item['asset_name'] ?? '')); ?>"
                                            data-campaign-name="<?php echo esc_attr(strtolower($item['campaign_name'] ?? '')); ?>"
                                            data-maker-name="<?php echo esc_attr(strtolower($item['maker_name'] ?? '')); ?>"
                                            data-checker-name="<?php echo esc_attr(strtolower($item['checker_name'] ?? '')); ?>"
                                            data-deadline-raw="<?php echo esc_attr($item['deadline_raw'] ?? ''); ?>"
                                            data-days-remaining="<?php echo esc_attr($days !== null ? $days : 9999); ?>"
                                            data-due-soon="<?php echo !empty($item['due_soon']) ? '1' : '0'; ?>"
                                            data-overdue="<?php echo $isOverdue ? '1' : '0'; ?>"
                                            data-approved-timestamp="<?php echo esc_attr(strtotime($item['approved_at_full'] ?? '') ?: 0); ?>">
                                            
                                            <td class="queue-td col-asset">
                                                <a href="<?php echo esc_url($detailUrl); ?>" class="asset-link">
                                                    <?php echo esc_html($item['asset_name'] ?: '—'); ?>
                                                </a>
                                                <div class="campaign-name-row">
                                                    <?php echo esc_html($item['campaign_name'] ?: '—'); ?>
                                                    <?php if (!empty($item['campaign_code'])): ?>
                                                        &middot; <?php echo esc_html($item['campaign_code']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td class="queue-td col-version">
                                                <span class="version-badge">
                                                    <?php echo esc_html($item['version_label'] ?: 'V1'); ?>
                                                </span>
                                            </td>

                                            <td class="queue-td col-users">
                                                <div class="user-line-maker">
                                                    M &middot; <?php echo esc_html($item['maker_name'] ?: '—'); ?>
                                                </div>
                                                <div class="user-line-checker">
                                                    C &middot; <?php echo esc_html($item['checker_name'] ?: '—'); ?>
                                                </div>
                                            </td>

                                            <td class="queue-td col-approved">
                                                <span class="approved-time-text">
                                                    <?php echo esc_html($item['approved_at_full'] ?: '—'); ?>
                                                </span>
                                            </td>

                                            <td class="queue-td col-deadline">
                                                <div class="deadline-cell-content">
                                                    <span class="deadline-date-text">
                                                        <?php echo esc_html($item['deadline'] ?: '—'); ?>
                                                    </span>
                                                    <?php if ($isOverdue): ?>
                                                        <span class="deadline-pill-overdue">
                                                            Overdue (<?php echo abs((int)$days); ?>d)
                                                        </span>
                                                    <?php elseif ($days !== null && $days <= 14): ?>
                                                        <span class="deadline-pill-due-soon">
                                                            Due Soon (<?php echo (int) $days; ?>d)
                                                        </span>
                                                    <?php elseif ($days !== null): ?>
                                                        <span class="deadline-days-muted">
                                                            (<?php echo (int) $days; ?>d)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td class="queue-td col-status">
                                                <span class="status-pill-awaiting">
                                                    <span class="dot-amber"></span>
                                                    <span>Approved</span>
                                                </span>
                                            </td>

                                            <td class="queue-td col-action">
                                                <div style="display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                                                    <a href="<?php echo esc_url($finalReviewUrl); ?>" class="btn-open-review" title="Open Final Review" style="padding: 6px 12px; font-size: 12px; white-space: nowrap;">
                                                        <span>Review &rarr;</span>
                                                    </a>
                                                    <button type="button" class="btn-quick-release btn-launch-release" title="Quick Sign-off &amp; Release" style="padding: 6px 12px; font-size: 12px; white-space: nowrap;"
                                                            data-version-id="<?php echo esc_attr($vId); ?>"
                                                            data-asset-id="<?php echo esc_attr($aId); ?>"
                                                            data-campaign-id="<?php echo esc_attr($cId); ?>"
                                                            data-asset-name="<?php echo esc_attr($item['asset_name'] ?? ''); ?>"
                                                            data-campaign-name="<?php echo esc_attr($item['campaign_name'] ?? ''); ?>"
                                                            data-version-label="<?php echo esc_attr($item['version_label'] ?? ''); ?>"
                                                            data-maker-name="<?php echo esc_attr($item['maker_name'] ?? ''); ?>"
                                                            data-checker-name="<?php echo esc_attr($item['checker_name'] ?? ''); ?>"
                                                            data-checker-notes="<?php echo esc_attr($item['checker_notes'] ?? ''); ?>"
                                                            data-score="<?php echo esc_attr($item['score'] ?? 100); ?>"
                                                            data-detail-url="<?php echo esc_url($detailUrl); ?>"
                                                            data-final-review-url="<?php echo esc_url($finalReviewUrl); ?>">
                                                        <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                        </svg>
                                                        <span>Quick</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="section-empty-row" id="needsAttentionEmptyRow">
                                        <td colspan="7">No urgent assets requiring attention at this time.</td>
                                    </tr>
                                <?php endif; ?>

                                <!-- SUB-SECTION 2: OTHER PENDING -->
                                <tr class="sub-header-row" id="otherPendingSubHeader">
                                    <td colspan="7">
                                        <div class="sub-header-title" id="otherPendingTitle">
                                            OTHER PENDING &middot; <span id="otherPendingCountSpan"><?php echo (int) $otherPendingCount; ?></span> ITEMS
                                        </div>
                                    </td>
                                </tr>

                                <?php if (!empty($otherPendingItems)): ?>
                                    <?php foreach ($otherPendingItems as $item): 
                                        $cId = (int) ($item['campaign_id'] ?? 0);
                                        $aId = (int) ($item['asset_id'] ?? 0);
                                        $vId = (int) ($item['version_id'] ?? 0);
                                        $detailUrl = $cId && $aId ? add_query_arg(['campaign_id' => $cId, 'asset_id' => $aId], $detailAdminBaseUrl) : '#';
                                        $finalReviewUrl = add_query_arg([
                                            'version_id'  => $vId,
                                            'asset_id'    => $aId,
                                            'campaign_id' => $cId
                                        ], $finalReviewBaseUrl);
                                        $days = $item['days_remaining'] ?? null;
                                        $isOverdue = ($days !== null && $days < 0);
                                    ?>
                                        <tr class="queue-row queue-item"
                                            data-group="other_pending"
                                            data-asset-name="<?php echo esc_attr(strtolower($item['asset_name'] ?? '')); ?>"
                                            data-campaign-name="<?php echo esc_attr(strtolower($item['campaign_name'] ?? '')); ?>"
                                            data-maker-name="<?php echo esc_attr(strtolower($item['maker_name'] ?? '')); ?>"
                                            data-checker-name="<?php echo esc_attr(strtolower($item['checker_name'] ?? '')); ?>"
                                            data-deadline-raw="<?php echo esc_attr($item['deadline_raw'] ?? ''); ?>"
                                            data-days-remaining="<?php echo esc_attr($days !== null ? $days : 9999); ?>"
                                            data-due-soon="<?php echo !empty($item['due_soon']) ? '1' : '0'; ?>"
                                            data-overdue="<?php echo $isOverdue ? '1' : '0'; ?>"
                                            data-approved-timestamp="<?php echo esc_attr(strtotime($item['approved_at_full'] ?? '') ?: 0); ?>">
                                            
                                            <td class="queue-td col-asset">
                                                <a href="<?php echo esc_url($detailUrl); ?>" class="asset-link">
                                                    <?php echo esc_html($item['asset_name'] ?: '—'); ?>
                                                </a>
                                                <div class="campaign-name-row">
                                                    <?php echo esc_html($item['campaign_name'] ?: '—'); ?>
                                                    <?php if (!empty($item['campaign_code'])): ?>
                                                        &middot; <?php echo esc_html($item['campaign_code']); ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td class="queue-td col-version">
                                                <span class="version-badge">
                                                    <?php echo esc_html($item['version_label'] ?: 'V1'); ?>
                                                </span>
                                            </td>

                                            <td class="queue-td col-users">
                                                <div class="user-line-maker">
                                                    M &middot; <?php echo esc_html($item['maker_name'] ?: '—'); ?>
                                                </div>
                                                <div class="user-line-checker">
                                                    C &middot; <?php echo esc_html($item['checker_name'] ?: '—'); ?>
                                                </div>
                                            </td>

                                            <td class="queue-td col-approved">
                                                <span class="approved-time-text">
                                                    <?php echo esc_html($item['approved_at_full'] ?: '—'); ?>
                                                </span>
                                            </td>

                                            <td class="queue-td col-deadline">
                                                <div class="deadline-cell-content">
                                                    <span class="deadline-date-text">
                                                        <?php echo esc_html($item['deadline'] ?: '—'); ?>
                                                    </span>
                                                    <?php if ($days !== null): ?>
                                                        <span class="deadline-days-muted">
                                                            (<?php echo (int) $days; ?>d)
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <td class="queue-td col-status">
                                                <span class="status-pill-awaiting">
                                                    <span class="dot-amber"></span>
                                                    <span>Approved</span>
                                                </span>
                                            </td>

                                            <td class="queue-td col-action">
                                                <div style="display: inline-flex; align-items: center; justify-content: center; gap: 8px;">
                                                    <a href="<?php echo esc_url($finalReviewUrl); ?>" class="btn-open-review" title="Open Final Review" style="padding: 6px 12px; font-size: 12px; white-space: nowrap;">
                                                        <span>Review &rarr;</span>
                                                    </a>
                                                    <button type="button" class="btn-quick-release btn-launch-release" title="Quick Sign-off &amp; Release" style="padding: 6px 12px; font-size: 12px; white-space: nowrap;"
                                                            data-version-id="<?php echo esc_attr($vId); ?>"
                                                            data-asset-id="<?php echo esc_attr($aId); ?>"
                                                            data-campaign-id="<?php echo esc_attr($cId); ?>"
                                                            data-asset-name="<?php echo esc_attr($item['asset_name'] ?? ''); ?>"
                                                            data-campaign-name="<?php echo esc_attr($item['campaign_name'] ?? ''); ?>"
                                                            data-version-label="<?php echo esc_attr($item['version_label'] ?? ''); ?>"
                                                            data-maker-name="<?php echo esc_attr($item['maker_name'] ?? ''); ?>"
                                                            data-checker-name="<?php echo esc_attr($item['checker_name'] ?? ''); ?>"
                                                            data-checker-notes="<?php echo esc_attr($item['checker_notes'] ?? ''); ?>"
                                                            data-score="<?php echo esc_attr($item['score'] ?? 100); ?>"
                                                            data-detail-url="<?php echo esc_url($detailUrl); ?>"
                                                            data-final-review-url="<?php echo esc_url($finalReviewUrl); ?>">
                                                        <svg width="13" height="13" viewBox="0 0 20 20" fill="currentColor">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                        </svg>
                                                        <span>Quick</span>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr class="section-empty-row" id="otherPendingEmptyRow">
                                        <td colspan="7">No other pending items in queue.</td>
                                    </tr>
                                <?php endif; ?>

                                <!-- Universal Filter No-Match Notice Row (hidden by default) -->
                                <tr id="noResultsFilterRow" style="display: none;">
                                    <td colspan="7" style="padding: 48px 24px; text-align: center; color: #5F657B;">
                                        <div style="font-size: 15px; font-weight: 600; color: #1F2A5A; margin-bottom: 6px;">No Matching Approval Items</div>
                                        <div style="font-size: 13px; margin-bottom: 14px;">No assets match your search and filter criteria.</div>
                                        <button type="button" class="clear-filters-btn" onclick="document.getElementById('clearFiltersBtn').click();" style="margin: 0 auto;">
                                            Reset Filter Criteria
                                        </button>
                                    </td>
                                </tr>

                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

        <?php endif; ?>

    </main>

</div>

<!-- 3. PRODUCTION RELEASE MODAL -->
<div id="releaseModal" class="modal-backdrop">
    <div class="modal-card">
        <form method="POST" action="">
            <input type="hidden" name="action" value="admin_release">
            <input type="hidden" name="version_id" id="modalVersionIdInput" value="">

            <div class="modal-header">
                <div>
                    <h3 class="modal-title">Production Release Sign-off</h3>
                    <div class="modal-subtitle">Review checker approval remarks and authorize asset distribution.</div>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeReleaseModal();">&times;</button>
            </div>

            <div class="modal-body">
                <!-- Summary Grid -->
                <div class="modal-summary-box">
                    <div class="modal-summary-item">
                        <span class="modal-summary-label">Campaign</span>
                        <span class="modal-summary-value" id="modalCampaignName">—</span>
                    </div>
                    <div class="modal-summary-item">
                        <span class="modal-summary-label">Target Asset</span>
                        <span class="modal-summary-value" id="modalAssetName">—</span>
                    </div>
                    <div class="modal-summary-item">
                        <span class="modal-summary-label">Version & AI Score</span>
                        <span class="modal-summary-value" id="modalVersionLabel">—</span>
                    </div>
                    <div class="modal-summary-item">
                        <span class="modal-summary-label">Checker Reviewer</span>
                        <span class="modal-summary-value" id="modalCheckerName">—</span>
                    </div>
                </div>

                <!-- Checker remarks readout -->
                <div id="modalCheckerNotesContainer" style="display: none; padding: 10px 12px; background: #F8F9FA; border-left: 3px solid #4E56A6; border-radius: 4px; font-size: 12px; color: #4B5563;">
                    <div style="font-weight: 600; color: #1F2A5A; margin-bottom: 2px;">Checker Sign-off Notes:</div>
                    <div id="modalCheckerNotesText"></div>
                </div>

                <!-- Admin Release Notes -->
                <div class="modal-textarea-group">
                    <label class="modal-label" for="releaseNotesTextarea">Admin Governance & Release Remarks (Optional)</label>
                    <textarea class="modal-textarea" id="releaseNotesTextarea" name="release_notes" placeholder="Enter administrative authorization remarks, release justification, or deployment directives..."></textarea>
                </div>

                <!-- Compliance Certification Checkbox -->
                <div class="modal-certify-row">
                    <input type="checkbox" id="complianceCertifyCheck" required style="margin-top: 2px; accent-color: #4E56A6; cursor: pointer;">
                    <label for="complianceCertifyCheck" class="modal-certify-text">
                        I hereby certify as authorized compliance administrator that this asset meets institutional brand guidelines and applicable regulatory disclosure mandates (MAS Notice 637).
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="modal-btn-cancel" onclick="closeReleaseModal();">Cancel</button>
                <div class="modal-actions-right">
                    <a id="modalDedicatedReviewBtn" href="#" class="modal-btn-inspect" style="color: #4E56A6; border-color: #D5D9F3; background: #F4F3FD;">
                        <span>Open Full Review Canvas &rarr;</span>
                    </a>
                    <a id="modalInspectAssetBtn" href="#" class="modal-btn-inspect">
                        <span>Inspect Full Audit Trail &rarr;</span>
                    </a>
                    <button type="submit" class="modal-btn-confirm">
                        <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <span>Confirm & Release to Production</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 4. CLIENT-SIDE INTERACTIVITY SCRIPT -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('queueSearchInput');
    const deadlineFilter = document.getElementById('deadlineFilter');
    const checkerFilter = document.getElementById('checkerFilter');
    const sortOrder = document.getElementById('sortOrder');
    const clearBtn = document.getElementById('clearFiltersBtn');

    const table = document.getElementById('approvalQueueTable');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('.queue-item'));
    const needsAttSubHeader = document.getElementById('needsAttentionSubHeader');
    const otherPendSubHeader = document.getElementById('otherPendingSubHeader');
    const needsAttEmptyRow = document.getElementById('needsAttentionEmptyRow');
    const otherPendEmptyRow = document.getElementById('otherPendingEmptyRow');
    const noResultsRow = document.getElementById('noResultsFilterRow');

    const heroTotal = document.getElementById('heroTotalCounter');
    const heroAttention = document.getElementById('heroAttentionLabel');
    const needsCountSpan = document.getElementById('needsAttentionCountSpan');
    const otherCountSpan = document.getElementById('otherPendingCountSpan');

    function applyFilters() {
        const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const dlVal = deadlineFilter ? deadlineFilter.value : 'all';
        const chkVal = checkerFilter ? checkerFilter.value.trim().toLowerCase() : 'all';

        let visibleNeeds = 0;
        let visibleOther = 0;

        rows.forEach(function(row) {
            const asset = row.getAttribute('data-asset-name') || '';
            const campaign = row.getAttribute('data-campaign-name') || '';
            const maker = row.getAttribute('data-maker-name') || '';
            const checker = row.getAttribute('data-checker-name') || '';
            const group = row.getAttribute('data-group') || '';
            const isDueSoon = row.getAttribute('data-due-soon') === '1';
            const isOverdue = row.getAttribute('data-overdue') === '1';
            const daysRemaining = parseInt(row.getAttribute('data-days-remaining') || '9999', 10);

            // Search filter
            let matchSearch = true;
            if (query !== '') {
                matchSearch = asset.includes(query) || campaign.includes(query) || maker.includes(query) || checker.includes(query);
            }

            // Deadline filter
            let matchDeadline = true;
            if (dlVal === 'due_soon') {
                matchDeadline = isDueSoon && !isOverdue;
            } else if (dlVal === 'overdue') {
                matchDeadline = isOverdue;
            } else if (dlVal === 'due_later') {
                matchDeadline = !isOverdue && daysRemaining > 14 && daysRemaining < 9000;
            }

            // Checker filter
            let matchChecker = true;
            if (chkVal !== 'all' && chkVal !== '') {
                matchChecker = checker === chkVal;
            }

            if (matchSearch && matchDeadline && matchChecker) {
                row.style.display = '';
                if (group === 'needs_attention') {
                    visibleNeeds++;
                } else {
                    visibleOther++;
                }
            } else {
                row.style.display = 'none';
            }
        });

        // Toggle subheaders & empty state messages
        if (needsAttSubHeader) needsAttSubHeader.style.display = visibleNeeds > 0 ? '' : 'none';
        if (otherPendSubHeader) otherPendSubHeader.style.display = visibleOther > 0 ? '' : 'none';

        if (needsAttEmptyRow) needsAttEmptyRow.style.display = (visibleNeeds === 0 && rows.some(r => r.getAttribute('data-group') === 'needs_attention')) ? '' : 'none';
        if (otherPendEmptyRow) otherPendEmptyRow.style.display = (visibleOther === 0 && rows.some(r => r.getAttribute('data-group') === 'other_pending')) ? '' : 'none';

        if (noResultsRow) {
            noResultsRow.style.display = (visibleNeeds === 0 && visibleOther === 0) ? '' : 'none';
        }

        // Update counts in UI
        if (needsCountSpan) needsCountSpan.textContent = visibleNeeds;
        if (otherCountSpan) otherCountSpan.textContent = visibleOther;
        if (heroTotal) heroTotal.textContent = visibleNeeds + visibleOther;
        if (heroAttention) heroAttention.textContent = visibleNeeds + ' Needs Attention';
    }

    function applySort() {
        const sortVal = sortOrder ? sortOrder.value : 'earliest_deadline';
        const tbody = table.querySelector('tbody');
        if (!tbody) return;

        const needsRows = rows.filter(r => r.getAttribute('data-group') === 'needs_attention');
        const otherRows = rows.filter(r => r.getAttribute('data-group') === 'other_pending');

        function sortFn(a, b) {
            if (sortVal === 'earliest_deadline') {
                const da = parseInt(a.getAttribute('data-days-remaining') || '9999', 10);
                const db = parseInt(b.getAttribute('data-days-remaining') || '9999', 10);
                return da - db;
            } else if (sortVal === 'latest_deadline') {
                const da = parseInt(a.getAttribute('data-days-remaining') || '9999', 10);
                const db = parseInt(b.getAttribute('data-days-remaining') || '9999', 10);
                return db - da;
            } else if (sortVal === 'recently_approved') {
                const ta = parseInt(a.getAttribute('data-approved-timestamp') || '0', 10);
                const tb = parseInt(b.getAttribute('data-approved-timestamp') || '0', 10);
                return tb - ta;
            } else if (sortVal === 'asset_name') {
                const na = a.getAttribute('data-asset-name') || '';
                const nb = b.getAttribute('data-asset-name') || '';
                return na.localeCompare(nb);
            }
            return 0;
        }

        needsRows.sort(sortFn);
        otherRows.sort(sortFn);

        // Re-insert into DOM in order
        if (needsAttSubHeader) {
            let refNode = needsAttSubHeader;
            needsRows.forEach(function(row) {
                refNode.after(row);
                refNode = row;
            });
        }

        if (otherPendSubHeader) {
            let refNode = otherPendSubHeader;
            otherRows.forEach(function(row) {
                refNode.after(row);
                refNode = row;
            });
        }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilters);
    if (deadlineFilter) deadlineFilter.addEventListener('change', applyFilters);
    if (checkerFilter) checkerFilter.addEventListener('change', applyFilters);
    if (sortOrder) sortOrder.addEventListener('change', function() {
        applySort();
        applyFilters();
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            if (deadlineFilter) deadlineFilter.value = 'all';
            if (checkerFilter) checkerFilter.value = 'all';
            if (sortOrder) sortOrder.value = 'earliest_deadline';
            applySort();
            applyFilters();
        });
    }

    // Modal Interaction
    const modal = document.getElementById('releaseModal');
    const modalVerInput = document.getElementById('modalVersionIdInput');
    const modalCampaign = document.getElementById('modalCampaignName');
    const modalAsset = document.getElementById('modalAssetName');
    const modalVersion = document.getElementById('modalVersionLabel');
    const modalChecker = document.getElementById('modalCheckerName');
    const modalInspectBtn = document.getElementById('modalInspectAssetBtn');
    const modalReviewBtn = document.getElementById('modalDedicatedReviewBtn');
    const modalNotesContainer = document.getElementById('modalCheckerNotesContainer');
    const modalNotesText = document.getElementById('modalCheckerNotesText');

    window.closeReleaseModal = function() {
        if (modal) modal.style.display = 'none';
        const checkbox = document.getElementById('complianceCertifyCheck');
        if (checkbox) checkbox.checked = false;
    };

    document.querySelectorAll('.btn-launch-release').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const vid = this.getAttribute('data-version-id');
            const asset = this.getAttribute('data-asset-name');
            const campaign = this.getAttribute('data-campaign-name');
            const ver = this.getAttribute('data-version-label');
            const chk = this.getAttribute('data-checker-name');
            const chkNotes = this.getAttribute('data-checker-notes');
            const score = this.getAttribute('data-score') || '100';
            const detailUrl = this.getAttribute('data-detail-url');
            const finalReviewUrl = this.getAttribute('data-final-review-url');

            if (modalVerInput) modalVerInput.value = vid;
            if (modalCampaign) modalCampaign.textContent = campaign || '—';
            if (modalAsset) modalAsset.textContent = asset || '—';
            if (modalVersion) modalVersion.textContent = (ver || 'V1') + ' · ' + score + '% AI Score';
            if (modalChecker) modalChecker.textContent = chk || '—';
            if (modalInspectBtn) modalInspectBtn.href = detailUrl || '#';
            if (modalReviewBtn) modalReviewBtn.href = finalReviewUrl || '#';

            if (modalNotesContainer && modalNotesText) {
                if (chkNotes && chkNotes.trim() !== '') {
                    modalNotesText.textContent = chkNotes;
                    modalNotesContainer.style.display = 'block';
                } else {
                    modalNotesContainer.style.display = 'none';
                }
            }

            if (modal) modal.style.display = 'flex';
        });
    });

    // Close on backdrop click
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeReleaseModal();
            }
        });
    }
});
</script>

</body>
</html>

<?php
/**
 * Template Name: Detail Asset Checker
 * Description: Realtime Checker Asset Detail View for a Campaign connected to WordPress Database & CPTs
 * 100% Dynamic Database Integration: Campaign, Assets, Versions, AI Pre-flight, Guidelines, Audit Log
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

// 2. XỬ LÝ LOGOUT
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

// 3. XỬ LÝ QUYẾT ĐỊNH DUYỆT (APPROVE / REJECT) CỦA CHECKER HOẶC SỬA ASSET CỦA MAKER
$decisionAlert = '';
$decisionSuccess = false;
$reqMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($reqMethod === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'checker_decision') {
        $version_id = (int) ($_POST['version_id'] ?? 0);
        $decision = sanitize_text_field($_POST['decision'] ?? '');
        $review_note = sanitize_text_field($_POST['review_note'] ?? '');
        
        if (function_exists('handle_checker_decision')) {
            $res = handle_checker_decision($version_id, $decision, $review_note);
            $decisionAlert = $res['message'];
            $decisionSuccess = $res['success'];
        }
    } elseif ($_POST['action'] === 'update_asset_identifiers') {
        $aid = absint($_POST['asset_id'] ?? 0);
        if ($aid > 0 && function_exists('handle_update_asset')) {
            handle_update_asset($aid, $_POST);
            $decisionAlert = 'Asset identifiers updated successfully!';
            $decisionSuccess = true;
        }
    }
}

// 4. LẤY THÔNG TIN USER HIỆN TẠI VÀ PHÂN QUYỀN RBAC (CHECKER, ADMIN, MAKER)
$currentUser = function_exists('pcs_enforce_rbac') ? pcs_enforce_rbac(['CHECKER', 'ADMIN', 'MAKER']) : (function_exists('get_current_user_profile') ? get_current_user_profile() : null);
$userRole = strtoupper($currentUser['role'] ?? 'CHECKER');

// 5. TRUY VẤN DỮ LIỆU THỰC TỪ DATABASE CHO CAMPAIGN VÀ CÁC ASSETS
$campId = (int) ($_GET['campaign_id'] ?? 0);
$selectedAssetId = (int) ($_GET['asset_id'] ?? 0);
$detailData = function_exists('get_campaign_detail_asset_data') ? get_campaign_detail_asset_data($campId, $selectedAssetId) : null;

$campaignName    = $detailData['campaign_name'] ?? 'Campaign Asset Detail';
$ownerName       = $detailData['owner_name'] ?? 'Cam Anh';
$deadline        = $detailData['deadline'] ?? 'No deadline';
$priority        = $detailData['priority'] ?? 'High Priority';
$assets          = $detailData['assets'] ?? [];
$totalAssets     = $detailData['total_assets'] ?? count($assets);
$inReviewCount   = 0;
$rejectedCount   = 0;
$approvedCount   = 0;
$releasedCount   = 0;
foreach ($assets as $ast) {
    $st = str_replace([' ', '-'], '_', strtolower(trim($ast['status'] ?? 'draft')));
    if ($st === 'in_review') $inReviewCount++;
    elseif ($st === 'rejected') $rejectedCount++;
    elseif ($st === 'approved' || $st === 'awaiting_release') $approvedCount++;
    elseif ($st === 'released') $releasedCount++;
}
$reviewedCount   = $rejectedCount + $approvedCount + $releasedCount;
$percent         = $totalAssets > 0 ? round(($reviewedCount / $totalAssets) * 100) : 0;
$selectedAsset   = $detailData['selected_asset'] ?? null;
$versionHistory  = $detailData['version_history'] ?? [];
$guidelines      = $detailData['guidelines'] ?? [];
$activities      = $detailData['activities'] ?? [];

$checkerDashUrl = function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php';
$homeUrl        = function_exists('pcs_url') ? pcs_url('home-page.php') : 'home-page.php';
$aiScoringResultUrl = function_exists('pcs_url') ? pcs_url('ai-scoring-result.php') : (function_exists('home_url') ? home_url('/ai-scoring-result.php') : 'ai-scoring-result.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($campaignName) ?> - Asset Details | Checker Workspace</title>
    
    <!-- Google Fonts Plus Jakarta Sans & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* ================= 1. GLOBALS & RESETS ================= */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        html, body {
            margin: 0px;
            min-height: 100%;
            background: #FAF8FF;
            font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #161B2E;
            line-height: 1.5;
        }
        button:focus-visible {
            outline: 2px solid #4A90E2 !important;
            outline: -webkit-focus-ring-color auto 5px !important;
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

        /* ================= 2. STYLE LAYOUT CLASSES ================= */
        .detail-asset-checker {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            background: linear-gradient(0deg, rgba(250, 248, 255, 1) 0%, rgba(250, 248, 255, 1) 100%),
                        linear-gradient(0deg, rgba(255, 255, 255, 1) 0%, rgba(255, 255, 255, 1) 100%);
            min-height: 100vh;
            width: 100%;
            overflow-x: hidden;
        }

        .detail-asset-checker .header {
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 100%;
            height: 95px;
            align-items: center;
            position: sticky;
            top: 0;
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0b2;
            z-index: 100;
        }

        .detail-asset-checker .container {
            display: flex;
            height: 92px;
            align-items: center;
            justify-content: space-between;
            padding: 0px 48px;
            position: relative;
            align-self: stretch;
            width: 100%;
            max-width: 1440px;
            margin: 0 auto;
        }

        .detail-asset-checker .LEFT-SECTION-logo {
            display: inline-flex;
            align-items: center;
            gap: 24px;
            position: relative;
            flex: 0 0 auto;
        }

        .detail-asset-checker .link-brandcompliance {
            flex-direction: column;
            align-items: flex-start;
            padding: 0px 24px 0px 0px;
            display: inline-flex;
            position: relative;
            flex: 0 0 auto;
        }

        .detail-asset-checker .div {
            align-items: center;
            display: inline-flex;
            position: relative;
            flex: 0 0 auto;
        }

        .detail-asset-checker .div-2 {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            position: relative;
            flex: 0 0 auto;
        }

        .detail-asset-checker .image {
            position: relative;
            height: 48px;
            width: auto;
            object-fit: contain;
        }

        .detail-asset-checker .navigation-links {
            position: relative;
            display: inline-flex;
            align-items: center;
            gap: 28px;
            height: 38.5px;
        }

        .detail-asset-checker .link-nav-item,
        .detail-asset-checker .link-nav-item-wrapper,
        .detail-asset-checker .link-nav-item-about {
            display: inline-flex;
            align-items: center;
        }

        .detail-asset-checker .text-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            font-weight: 600;
            color: #334155;
            font-size: 15px;
            letter-spacing: 0;
            line-height: 22.5px;
            white-space: nowrap;
            transition: color 0.15s;
        }
        .detail-asset-checker .text-wrapper:hover {
            color: #1F2674;
        }

        .detail-asset-checker .dropdown-trigger {
            display: inline-flex;
            align-items: center;
        }

        .detail-asset-checker .button-menu {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 0px;
            position: relative;
            flex: 0 0 auto;
        }

        .detail-asset-checker .div-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .detail-asset-checker .text {
            font-weight: 600;
            color: #334155;
            font-size: 15px;
            letter-spacing: 0;
            line-height: 22.5px;
            white-space: nowrap;
        }

        .detail-asset-checker .SVG {
            position: relative;
            width: 16px;
            height: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .detail-asset-checker .vector {
            width: 10px;
            height: 10px;
        }

        .detail-asset-checker .right-header-checker {
            display: inline-flex;
            align-items: center;
            gap: 16px;
            position: relative;
            flex: 0 0 auto;
        }

        .detail-asset-checker .live-AI-pre-flight {
            position: relative;
            width: 1px;
            height: 24px;
            background-color: #e2e8f0;
        }

        .detail-asset-checker .checker-profile {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 0px 0px 0px 4px;
            position: relative;
            flex: 0 0 auto;
            cursor: pointer;
        }

        .detail-asset-checker .background {
            display: flex;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            position: relative;
            border-radius: 9999px;
            background: linear-gradient(35deg, rgba(55, 62, 140, 1) 0%, rgba(79, 86, 166, 1) 100%);
        }

        .detail-asset-checker .overlay-shadow {
            position: absolute;
            top: 0;
            left: 0;
            width: 36px;
            height: 36px;
            background-color: #ffffff01;
            border-radius: 9999px;
            box-shadow: 0px 1px 2px #0000000d, 0px 0px 0px 2px #ffffff;
        }

        .detail-asset-checker .text-wrapper-2 {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #ffffff;
            font-size: 12px;
            letter-spacing: 0;
            line-height: 16px;
            white-space: nowrap;
        }

        .detail-asset-checker .overlay-shadow-wrapper {
            display: flex;
            flex-direction: column;
            width: 10px;
            height: 10px;
            align-items: flex-end;
            justify-content: flex-end;
            position: absolute;
            right: 0;
            bottom: 0;
            background-color: #10b981;
            border-radius: 9999px;
        }

        .detail-asset-checker .overlay-shadow-2 {
            position: relative;
            width: 10px;
            height: 10px;
            background-color: #ffffff01;
            border-radius: 9999px;
            box-shadow: 0px 0px 0px 2px #ffffff;
        }

        .detail-asset-checker .div-3 {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .detail-asset-checker .text-wrapper-3 {
            position: relative;
            display: flex;
            align-items: center;
            font-weight: 700;
            color: #0f172a;
            font-size: 13px;
            letter-spacing: 0;
            line-height: 16px;
            white-space: nowrap;
        }

        .detail-asset-checker .text-wrapper-4 {
            position: relative;
            display: flex;
            align-items: center;
            font-weight: 700;
            color: #4f56a6;
            font-size: 10px;
            letter-spacing: 0.5px;
            line-height: 14px;
            white-space: nowrap;
        }

        /* Profile Dropdown */
        .profile-dropdown {
            position: absolute;
            top: 52px;
            right: 0;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            padding: 12px;
            min-width: 200px;
            display: none;
            z-index: 200;
        }
        .profile-dropdown.active {
            display: block;
        }
        .profile-dropdown a {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            border-radius: 8px;
        }
        .profile-dropdown a:hover {
            background-color: #F8FAFC;
            color: #0F172A;
        }

        /* ================= 3. MAIN WORKSPACE ================= */
        .detail-asset-checker .main {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1px 0px 48px;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 1;
            background-color: #faf8ff;
        }

        .detail-asset-checker .container-2 {
            display: flex;
            flex-direction: column;
            max-width: 1440px;
            align-items: flex-start;
            gap: 28px;
            padding: 32px 48px;
            position: relative;
            width: 100%;
            flex: 0 0 auto;
        }

        /* Toast Alert */
        .toast-banner {
            width: 100%;
            padding: 14px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }
        .toast-banner.success {
            background-color: #ECFDF5;
            color: #065F46;
            border: 1px solid #A7F3D0;
        }
        .toast-banner.danger {
            background-color: #FEF2F2;
            color: #991B1B;
            border: 1px solid #FECACA;
        }

        /* ================= 4. SECTION CAMPAIGN ================= */
        .detail-asset-checker .section-CAMPAIGN {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .detail-asset-checker .container-3 {
            display: inline-flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            position: relative;
            flex: 0 0 auto;
        }

        .detail-asset-checker .container-4 {
            display: flex;
            align-items: center;
            gap: 6px;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .detail-asset-checker .icon {
            position: relative;
            width: 14px;
            height: 14px;
            color: #4f57a7;
        }

        .detail-asset-checker .text-wrapper-5 {
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

        .detail-asset-checker .paragraph {
            display: flex;
            align-items: baseline;
            gap: 16px;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .detail-asset-checker .text-wrapper-6 {
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

        .detail-asset-checker .container-5 {
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .detail-asset-checker .container-6 {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            position: relative;
            flex: 0 0 auto;
        }

        .detail-asset-checker .img {
            position: relative;
            width: 12px;
            height: 12px;
            color: #161B2E;
        }

        .detail-asset-checker .text-2 {
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

        .detail-asset-checker .text-3 {
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

        .detail-asset-checker .icon-2 {
            position: relative;
            width: 13px;
            height: 13px;
            color: #464651;
        }

        .detail-asset-checker .text-4 {
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

        .detail-asset-checker .background-2 {
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

        .detail-asset-checker .background-3 {
            position: relative;
            width: 6px;
            height: 6px;
            background-color: #f59e0b;
            border-radius: 9999px;
        }

        .detail-asset-checker .text-5 {
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

        .detail-asset-checker .div-4 {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            position: relative;
            flex: 0 0 auto;
        }

        .detail-asset-checker .button {
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
        }
        .detail-asset-checker .button:hover {
            background-color: #F8FAFC;
            border-color: #CBD5E1;
        }

        .detail-asset-checker .container-7 {
            display: inline-flex;
            align-items: center;
            position: relative;
            flex: 0 0 auto;
        }

        .detail-asset-checker .icon-3 {
            position: relative;
            width: 16px;
            height: 16px;
            color: #1f2674;
        }

        .detail-asset-checker .text-6 {
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

        .detail-asset-checker .button-2 {
            display: inline-flex;
            height: 40px;
            align-items: center;
            gap: 8px;
            padding: 0px 20px;
            position: relative;
            flex: 0 0 auto;
            background-color: #1f2674;
            border-radius: 8px;
            box-shadow: 0px 1px 2px #0000000d;
            transition: all 0.15s;
        }
        .detail-asset-checker .button-2:hover {
            background-color: #2D348E;
            transform: translateY(-1px);
        }

        .detail-asset-checker .text-7 {
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

        .detail-asset-checker .icon-4 {
            position: relative;
            width: 12px;
            height: 12px;
            color: #ffffff;
        }

        /* ================= 5. SECTION COMPACT (REVIEW PROGRESS) ================= */
        .detail-asset-checker .section-COMPACT {
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
        }

        .detail-asset-checker .container-8 {
            display: inline-flex;
            flex-direction: column;
            min-width: 240px;
            align-items: flex-start;
            gap: 4px;
            position: relative;
            flex: 0 0 auto;
        }

        .detail-asset-checker .text-8 {
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

        .detail-asset-checker .paragraph-2 {
            display: flex;
            align-items: baseline;
            gap: 8px;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .detail-asset-checker .text-9 {
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

        .detail-asset-checker .text-10 {
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

        .detail-asset-checker .container-9 {
            display: flex !important;
            flex-direction: row !important;
            max-width: none !important;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            position: relative;
            margin-left: auto;
            flex-wrap: wrap;
        }

        .detail-asset-checker .background-wrapper {
            position: relative;
            align-self: stretch;
            width: 100%;
            height: 8px;
            background-color: #e4e7ff;
            border-radius: 9999px;
            overflow: hidden;
        }

        .detail-asset-checker .background-4 {
            height: 100%;
            background-color: #1f2674;
            border-radius: 9999px;
            transition: width 0.3s ease;
        }

        .detail-asset-checker .container-10 {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
        }

        .detail-asset-checker .background-5 {
            position: relative;
            width: 8px;
            height: 8px;
            background-color: #4f57a7;
            border-radius: 9999px;
        }

        .detail-asset-checker .text-11 {
            position: relative;
            width: fit-content;
            font-weight: 600;
            color: #4f57a7;
            font-size: 13.5px;
            letter-spacing: 0;
            line-height: 20px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .detail-asset-checker .background-6 {
            position: relative;
            width: 8px;
            height: 8px;
            background-color: #f59e0b;
            border-radius: 9999px;
        }

        .detail-asset-checker .text-12 {
            position: relative;
            width: fit-content;
            font-weight: 600;
            color: #b45309;
            font-size: 13.5px;
            letter-spacing: 0;
            line-height: 20px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .detail-asset-checker .background-7 {
            position: relative;
            width: 8px;
            height: 8px;
            background-color: #0d9488;
            border-radius: 9999px;
        }

        .detail-asset-checker .text-13 {
            position: relative;
            width: fit-content;
            font-weight: 600;
            color: #0f766e;
            font-size: 13.5px;
            letter-spacing: 0;
            line-height: 20px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        /* ================= 6. SECTION ASSETS (TABLE) ================= */
        .detail-asset-checker .section-ASSETS {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #EBEDFF;
            overflow: hidden;
            box-shadow: 0px 1px 2px #0000000d;
        }

        .detail-asset-checker .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 24px;
            position: relative;
            align-self: stretch;
            width: 100%;
            flex: 0 0 auto;
            background-color: #ffffff;
            border-bottom: 1px solid #F1F5F9;
        }

        .detail-asset-checker .text-14 {
            position: relative;
            width: fit-content;
            font-weight: 700;
            color: #161b2e;
            font-size: 16px;
            letter-spacing: 0;
            line-height: 22px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .detail-asset-checker .background-8 {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            position: relative;
            flex: 0 0 auto;
            background-color: #e0e0ff;
            border-radius: 9999px;
        }

        .detail-asset-checker .text-15 {
            position: relative;
            width: fit-content;
            font-weight: 700;
            color: #1f2674;
            font-size: 11px;
            letter-spacing: 0.4px;
            line-height: 14px;
            display: flex;
            align-items: center;
            white-space: nowrap;
        }

        .detail-asset-checker .background-9 {
            display: inline-flex;
            align-items: center;
            gap: 2px;
            padding: 4px;
            position: relative;
            flex: 0 0 auto;
            background-color: #ebedff;
            border-radius: 8px;
        }

        .detail-asset-checker .button-3,
        .detail-asset-checker .button-4 {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 5px 12px;
            position: relative;
            flex: 0 0 auto;
            border-radius: 6px;
            font-size: 13.5px;
            font-weight: 600;
            transition: all 0.15s;
        }
        .detail-asset-checker .button-3 {
            background-color: #ffffff;
            color: #1F2674;
            box-shadow: 0px 1px 2px rgba(0,0,0,0.05);
        }
        .detail-asset-checker .button-4 {
            color: #464651;
        }
        .detail-asset-checker .button-4:hover {
            color: #1F2674;
        }

        .detail-asset-checker .text-16 {
            position: relative;
            font-weight: 600;
            color: inherit;
            font-size: 13.5px;
            text-align: center;
            white-space: nowrap;
        }

        .detail-asset-checker .input-box-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .detail-asset-checker .input {
            display: flex;
            align-items: center;
            width: 200px;
            height: 34px;
            padding: 0 12px 0 34px;
            position: relative;
            background-color: #f3f2ff;
            border-radius: 8px;
            border: 1px solid transparent;
            font-family: inherit;
            font-size: 13px;
            color: #161B2E;
            outline: none;
            transition: all 0.15s;
        }
        .detail-asset-checker .input:focus {
            background-color: #FFFFFF;
            border-color: #373E8C;
            box-shadow: 0 0 0 2px rgba(55, 62, 140, 0.15);
        }

        .detail-asset-checker .icon-5 {
            position: absolute;
            left: 11px;
            width: 14px;
            height: 14px;
            color: #767682;
            pointer-events: none;
        }

        /* TABLE GRID - 6 COLUMNS: ASSET (flexible), VERSION, AI ASSESSMENT, STATUS, SUBMITTED, ACTION */
        .detail-asset-checker .table-header {
            display: grid;
            grid-template-columns: minmax(180px, 2.2fr) 85px 145px 135px 145px 175px;
            align-items: center;
            padding: 12px 24px;
            background-color: #f3f2ff;
            border-bottom: 1px solid #E2E8F0;
            gap: 12px;
            width: 100%;
            align-self: stretch;
            box-sizing: border-box;
            position: sticky;
            top: 0;
            z-index: 5;
        }

        .detail-asset-checker .text-17 {
            font-weight: 700;
            color: #464651;
            font-size: 12px;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        .detail-asset-checker .text-18 {
            font-weight: 700;
            color: #464651;
            font-size: 12px;
            letter-spacing: 0.7px;
            text-align: center;
            text-transform: uppercase;
        }

        /* ROW TYPES */
        .detail-asset-checker .asset-table-row {
            display: grid;
            grid-template-columns: minmax(180px, 2.2fr) 85px 145px 135px 145px 175px;
            align-items: center;
            padding: 14px 24px;
            border-bottom: 1px solid #F1F5F9;
            transition: background-color 0.15s;
            cursor: pointer;
            gap: 12px;
            width: 100%;
            align-self: stretch;
            box-sizing: border-box;
        }
        .detail-asset-checker .asset-table-row:hover {
            background-color: #F8FAFC;
        }

        #assetsTableBody {
            width: 100%;
            align-self: stretch;
            box-sizing: border-box;
            max-height: 460px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        #assetsTableBody::-webkit-scrollbar {
            width: 6px;
        }
        #assetsTableBody::-webkit-scrollbar-track {
            background: #F8FAFC;
        }
        #assetsTableBody::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 4px;
        }
        #assetsTableBody::-webkit-scrollbar-thumb:hover {
            background: #94A3B8;
        }

        .detail-asset-checker .asset-table-row.row-selected {
            background: linear-gradient(90deg, rgba(224, 224, 255, 0.35) 0%, rgba(224, 224, 255, 0.05) 100%), #FFFFFF;
            outline: 1px solid #4F56A6;
        }

        .detail-asset-checker .asset-table-row.row-rejected {
            background-color: #FFF8F8;
            outline: 1px solid #FECACA;
        }

        .detail-asset-checker .container-18 {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
            overflow: hidden;
        }

        .detail-asset-checker .container-18 .div-2 {
            min-width: 0;
            flex: 1;
            overflow: hidden;
        }

        .detail-asset-checker .container-wrapper {
            display: flex;
            width: 44px;
            height: 44px;
            align-items: center;
            justify-content: center;
            background-color: #ebedff;
            border-radius: 8px;
            flex-shrink: 0;
            color: #1F2674;
        }

        .detail-asset-checker .text-19 {
            font-weight: 700;
            color: #161b2e;
            font-size: 14px;
            line-height: 1.4;
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: normal;
        }

        .detail-asset-checker .background-10 {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            background-color: #ebedff;
            border-radius: 4px;
        }

        .detail-asset-checker .text-20 {
            font-family: "JetBrains Mono", monospace;
            font-weight: 700;
            color: #1f2674;
            font-size: 13px;
        }

        .detail-asset-checker .background-14 {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            background-color: #e4e7ff;
            border-radius: 4px;
        }

        .detail-asset-checker .text-23 {
            font-family: "JetBrains Mono", monospace;
            font-weight: 600;
            color: #464651;
            font-size: 13px;
        }

        /* AI Badges */
        .ai-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            width: fit-content;
        }
        .ai-pill.pass {
            background-color: #f0fdfa;
            color: #115e59;
            border: 1px solid #CCFBF1;
        }
        .ai-pill.pass .dot {
            width: 6px;
            height: 6px;
            background-color: #0d9488;
            border-radius: 9999px;
        }
        .ai-pill.needs-look {
            background-color: #fffbeb;
            color: #92400e;
            border: 1px solid #FDE68A;
        }
        .ai-pill.needs-look .dot {
            width: 6px;
            height: 6px;
            background-color: #f59e0b;
            border-radius: 9999px;
        }
        .ai-pill.fail {
            background-color: #fff1f2;
            color: #9f1239;
            border: 1px solid #FECDD3;
        }
        .ai-pill.fail .dot {
            width: 6px;
            height: 6px;
            background-color: #e11d48;
            border-radius: 9999px;
        }
        .ai-pill.scoring {
            background-color: #fffbeb;
            color: #b45309;
            border: 1px solid #FDE68A;
        }
        .ai-pill.scoring .dot {
            width: 6px;
            height: 6px;
            background-color: #d97706;
            border-radius: 9999px;
        }

        /* Status Badges */
        .status-pill-table {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 600;
            width: fit-content;
        }
        .status-pill-table.draft {
            background-color: #F1F5F9;
            color: #475569;
            border: 1px solid #CBD5E1;
        }
        .status-pill-table.draft .dot {
            width: 6px;
            height: 6px;
            background-color: #475569;
            border-radius: 9999px;
        }
        .status-pill-table.scoring {
            background-color: #FFFBEB;
            color: #D97706;
            border: 1px solid #FCD34D;
        }
        .status-pill-table.scoring .dot {
            width: 6px;
            height: 6px;
            background-color: #D97706;
            border-radius: 9999px;
        }
        .status-pill-table.in_review, .status-pill-table.awaiting_review {
            background-color: #EEF2FF;
            color: #373E8C;
            border: 1px solid #C7D2FE;
        }
        .status-pill-table.in_review .dot, .status-pill-table.awaiting_review .dot {
            width: 6px;
            height: 6px;
            background-color: #373E8C;
            border-radius: 9999px;
        }
        .status-pill-table.approved {
            background-color: #ECFDF5;
            color: #059669;
            border: 1px solid #A7F3D0;
        }
        .status-pill-table.approved .dot {
            width: 6px;
            height: 6px;
            background-color: #059669;
            border-radius: 9999px;
        }
        .status-pill-table.rejected {
            background-color: #FEF2F2;
            color: #DC2626;
            border: 1px solid #FECACA;
        }
        .status-pill-table.rejected .dot {
            width: 6px;
            height: 6px;
            background-color: #DC2626;
            border-radius: 9999px;
        }
        .status-pill-table.released {
            background-color: #F5F3FF;
            color: #7C3AED;
            border: 1px solid #DDD6FE;
        }
        .status-pill-table.released .dot {
            width: 6px;
            height: 6px;
            background-color: #7C3AED;
            border-radius: 9999px;
        }

        .detail-asset-checker .text-wrapper-8 {
            font-weight: 600;
            color: #161b2e;
            font-size: 13.5px;
        }

        .detail-asset-checker .text-wrapper-9 {
            font-weight: 400;
            color: #64748B;
            font-size: 13px;
        }

        .detail-asset-checker .text-21 {
            font-weight: 700;
            color: #1f2674;
            font-size: 12.5px;
            letter-spacing: 0.5px;
        }
        .detail-asset-checker .text-26 {
            font-weight: 600;
            color: #464651;
            font-size: 12.5px;
        }
        .detail-asset-checker .text-29 {
            font-weight: 600;
            color: #767682;
            font-size: 12.5px;
        }

        .table-action-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
        }

        .btn-table-edit {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #FFFFFF;
            border: 1px solid #CBD5E1;
            border-radius: 6px;
            color: #475569;
            transition: all 0.15s ease;
            cursor: pointer;
            padding: 0;
            flex-shrink: 0;
        }
        .btn-table-edit:hover {
            background-color: #F1F5F9;
            border-color: #94A3B8;
            color: #1F2674;
            transform: translateY(-1px);
        }

        .table-action-cell .btn-table-review,
        .table-action-cell .btn-table-view {
            width: 76px;
            height: 32px;
            box-sizing: border-box;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: 13px;
            font-weight: 700;
            border-radius: 6px;
            text-align: center;
            text-decoration: none;
            flex-shrink: 0;
            box-shadow: 0px 1px 2px rgba(0,0,0,0.08);
            transition: all 0.15s;
        }

        .btn-table-review {
            background-color: #1f2674;
            color: #ffffff;
        }
        .btn-table-review:hover {
            background-color: #2D348E;
            transform: translateY(-1px);
        }

        .btn-table-view {
            background-color: #ebedff;
            color: #161b2e;
            font-weight: 600;
        }
        .btn-table-view:hover {
            background-color: #DDE0FF;
        }

        .btn-table-diff {
            width: 42px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            font-size: 13px;
            font-weight: 600;
            color: #4f57a7;
            flex-shrink: 0;
            background: none;
            border: none;
            cursor: pointer;
            transition: color 0.15s;
        }
        .btn-table-diff:hover {
            color: #1F2674;
            text-decoration: underline;
        }

        /* ================= 7. SECTION SELECTED VERSION ================= */
        .detail-asset-checker .section-SELECTED {
            display: grid !important;
            grid-template-columns: 1.15fr 1.35fr 1fr !important;
            align-items: stretch !important;
            gap: 24px !important;
            padding: 24px;
            position: relative;
            align-self: stretch;
            width: 100%;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #EBEDFF;
            box-shadow: 0px 1px 2px #0000000d;
            box-sizing: border-box;
        }

        .detail-asset-checker .zone-version {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 16px;
            position: relative;
            align-self: stretch;
        }

        .detail-asset-checker .container-22 {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
            position: relative;
            align-self: stretch;
            width: 100%;
        }

        .detail-asset-checker .background-22 {
            display: inline-flex;
            padding: 2px 8px;
            background-color: #ebedff;
            border-radius: 9999px;
        }

        .detail-asset-checker .text-31 {
            font-weight: 700;
            color: #1f2674;
            font-size: 12.5px;
        }

        .detail-asset-checker .text-wrapper-10 {
            font-weight: 800;
            color: #161b2e;
            font-size: 18px;
            line-height: 24px;
            margin-top: 2px;
        }

        .detail-asset-checker .background-23 {
            display: flex;
            height: 180px;
            align-items: center;
            justify-content: center;
            position: relative;
            align-self: stretch;
            width: 100%;
            background-color: #F8FAFC;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #E2E8F0;
        }

        .detail-asset-checker .preview-asset-img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .detail-asset-checker .overlay-2 {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            position: absolute;
            left: 8px;
            bottom: 8px;
            background-color: rgba(43, 47, 68, 0.85);
            border-radius: 4px;
        }

        .detail-asset-checker .p {
            font-weight: 500;
            color: #eff0ff;
            font-size: 12px;
            line-height: 16px;
        }

        /* ZONE AI PRE-FLIGHT */
        .detail-asset-checker .zone-AI-pre-flight {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 18px;
            position: relative;
            align-self: stretch;
            background-color: rgba(243, 242, 255, 0.6);
            border-radius: 10px;
            border: 1px solid #E0E0FF;
            gap: 12px;
        }

        .detail-asset-checker .container-23 {
            display: flex;
            height: 26px;
            align-items: center;
            justify-content: space-between;
            position: relative;
            align-self: stretch;
            width: 100%;
        }

        .detail-asset-checker .icon-9 {
            width: 18px;
            height: 18px;
            color: #1F2674;
        }

        .detail-asset-checker .text-32 {
            font-weight: 500;
            color: #767682;
            font-size: 12.5px;
        }

        .detail-asset-checker .div-7 {
            display: flex;
            flex-direction: column;
            gap: 8px;
            width: 100%;
        }

        .detail-asset-checker .check {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 14px;
            background-color: #ffffff;
            border-radius: 8px;
            border: 1px solid #EBEDFF;
        }

        .detail-asset-checker .check-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-asset-checker .icon-10 {
            width: 16px;
            height: 16px;
            color: #B45309;
        }
        .detail-asset-checker .icon-11 {
            width: 16px;
            height: 16px;
            color: #0F766E;
        }

        /* ZONE CHECKER NEXT ACTION */
        .detail-asset-checker .zone-checker-next {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 18px;
            position: relative;
            align-self: stretch;
            background-color: #F3F2FF;
            border-radius: 10px;
            border: 1px solid #D5D7FF;
            gap: 16px;
        }

        .detail-asset-checker .text-wrapper-11 {
            font-weight: 700;
            color: #4f57a7;
            font-size: 13px;
            letter-spacing: 0.7px;
            text-transform: uppercase;
        }

        .detail-asset-checker .button-10 {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            width: 100%;
            background-color: #1f2674;
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            border-radius: 8px;
            box-shadow: 0px 2px 4px rgba(31, 38, 116, 0.2);
            transition: all 0.15s;
        }
        .detail-asset-checker .button-10:hover {
            background-color: #2D348E;
            transform: translateY(-1px);
        }

        .detail-asset-checker .button-11 {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8px;
            width: 100%;
            margin-top: 6px;
        }

        .detail-asset-checker .text-35 {
            font-weight: 600;
            color: #4f57a7;
            font-size: 13.5px;
            transition: color 0.15s;
        }
        .detail-asset-checker .text-35:hover {
            color: #1F2674;
            text-decoration: underline;
        }

        /* ================= 8. SECTION SPLIT (TIMELINE + GUIDELINES) ================= */
        .detail-asset-checker .element-SPLIT-SECTION {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            width: 100%;
        }

        .detail-asset-checker .version-history-col {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #EBEDFF;
            padding: 24px;
            box-shadow: 0px 1px 2px #0000000d;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .detail-asset-checker .container-24 {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
        }

        /* Timeline styling */
        .timeline-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            padding: 16px 8px;
            width: 100%;
        }
        .timeline-line {
            position: absolute;
            top: 32px;
            left: 30px;
            right: 30px;
            height: 2px;
            background-color: #E2E8F0;
            z-index: 1;
        }

        .timeline-node {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            gap: 6px;
        }

        .timeline-badge {
            width: 32px;
            height: 32px;
            border-radius: 9999px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #FFFFFF;
            border: 2px solid #CBD5E1;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
        }
        .timeline-badge.current {
            background-color: #1F2674;
            border-color: #1F2674;
            color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(31, 38, 116, 0.2);
        }
        .timeline-badge.approved {
            background-color: #D1FAE5;
            border-color: #10B981;
            color: #065F46;
        }
        .timeline-badge.rejected {
            background-color: #FFE4E6;
            border-color: #F43F5E;
            color: #9F1239;
        }

        .timeline-label {
            font-weight: 700;
            font-size: 13px;
            color: #161B2E;
        }
        .timeline-sub {
            font-size: 11.5px;
            color: #64748B;
            white-space: nowrap;
        }

        .detail-asset-checker .guidelines-strip-col {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #EBEDFF;
            padding: 24px;
            box-shadow: 0px 1px 2px #0000000d;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .detail-asset-checker .text-42 {
            font-weight: 600;
            color: #4f57a7;
            font-size: 13.5px;
            cursor: pointer;
        }
        .detail-asset-checker .text-42:hover {
            text-decoration: underline;
        }

        .detail-asset-checker .guideline-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 14px;
            background-color: #f3f2ff;
            border-radius: 8px;
            border: 1px solid #EBEDFF;
            transition: background-color 0.15s;
        }
        .detail-asset-checker .guideline-row:hover {
            background-color: #EAE8FF;
        }

        /* ================= 9. SECTION RECENT (AUDIT LOG) ================= */
        .detail-asset-checker .section-RECENT {
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 16px;
            padding: 24px;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #EBEDFF;
            box-shadow: 0px 1px 2px #0000000d;
        }

        .detail-asset-checker .horizontal-border {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 12px;
            border-bottom: 1px solid #ebedff;
            width: 100%;
        }

        .detail-asset-checker .activity-list {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .detail-asset-checker .activity-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0px;
            border-bottom: 1px solid #ebedff;
        }
        .detail-asset-checker .activity-item:last-child {
            border-bottom: none;
        }

        .detail-asset-checker .activity-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .detail-asset-checker .text-43 {
            font-family: "JetBrains Mono", monospace;
            font-weight: 500;
            color: #767682;
            font-size: 12px;
            width: 100px;
        }

        .detail-asset-checker .text-44 {
            font-weight: 600;
            color: #161b2e;
            font-size: 13.5px;
        }

        .detail-asset-checker .text-45 {
            font-weight: 500;
            color: #64748B;
            font-size: 13px;
        }

        /* ================= 10. MODAL DIALOGS ================= */
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
        .modal-card {
            background: #FFFFFF;
            border-radius: 16px;
            max-width: 680px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            animation: modalFadeIn 0.2s ease-out;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }
        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h3 {
            font-size: 18px;
            font-weight: 800;
            color: #161B2E;
        }
        .btn-modal-close {
            font-size: 24px;
            color: #94A3B8;
            cursor: pointer;
            line-height: 1;
            padding: 4px;
        }
        .btn-modal-close:hover { color: #0F172A; }
        .modal-body {
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .modal-field label {
            display: block;
            font-size: 13px;
            font-weight: 700;
            color: #334155;
            margin-bottom: 6px;
        }
        .modal-textarea {
            width: 100%;
            height: 100px;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #CBD5E1;
            font-family: inherit;
            font-size: 13.5px;
            resize: vertical;
            outline: none;
        }
        .modal-textarea:focus {
            border-color: #1F2674;
            box-shadow: 0 0 0 2px rgba(31, 38, 116, 0.15);
        }
        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #F1F5F9;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
            background: #F8FAFC;
        }
        .btn-modal-reject {
            padding: 10px 18px;
            background-color: #FFE4E6;
            color: #BE123C;
            border: 1px solid #FECDD3;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-modal-reject:hover {
            background-color: #FECDD3;
        }
        .btn-modal-approve {
            padding: 10px 22px;
            background-color: #10B981;
            color: #FFFFFF;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s;
            box-shadow: 0 1px 3px rgba(16, 185, 129, 0.2);
        }
        .btn-modal-approve:hover {
            background-color: #059669;
        }
    </style>
</head>
<body>

    <!-- ================= 1. HEADER (100% UNIFIED TOP NAVBAR) ================= -->
    <?php 
    if (function_exists('pcs_render_top_navbar')) {
        pcs_render_top_navbar([
            'user'              => $currentUser,
            'all_campaigns_url' => $checkerDashUrl,
            'btn_text'          => 'All Campaigns'
        ]);
    } else {
        @include __DIR__ . '/inc/navbar.php';
    }
    ?>

<div class="detail-asset-checker">

    <!-- ================= 2. MAIN CONTAINER ================= -->
    <div class="main">
        <div class="container-2">

            <?php if (!empty($decisionAlert)): ?>
                <div class="toast-banner <?= $decisionSuccess ? 'success' : 'danger' ?>">
                    <span><?= htmlspecialchars($decisionAlert) ?></span>
                </div>
            <?php endif; ?>

            <!-- Back to Dashboard -->
            <div class="back-nav-row" style="margin-bottom: 12px; align-self: flex-start;">
                <a href="<?= esc_url($checkerDashUrl) ?>" class="btn-back-to-dash" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 700; color: #373E8C; text-decoration: none; padding: 6px 14px; background: #FFFFFF; border: 1px solid #E0E0FF; border-radius: 8px; box-shadow: 0 1px 2px rgba(0,0,0,0.04); transition: all 0.15s;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
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
                        <div class="text-3">•</div>
                        <div class="container-6">
                            <svg class="icon-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <div class="text-4">Deadline: <?= htmlspecialchars($deadline) ?></div>
                        </div>
                        <div class="text-3">•</div>
                        <div class="background-2" title="Campaign Priority">
                            <div class="background-3"></div>
                            <div class="text-5"><?= htmlspecialchars($priority) ?> Priority</div>
                        </div>
                    </div>
                </div>

                <div class="div-4">
                    <button class="button" onclick="openGuidelinesModal()">
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
                </div>
            </div>

            <!-- ================= SECTION: COMPACT REVIEW PROGRESS ================= -->
            <div class="section-COMPACT" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div class="container-8">
                    <div class="div-3"><div class="text-8">REVIEW OVERVIEW</div></div>
                    <div class="paragraph-2">
                        <div class="text-9"><?= $reviewedCount ?> / <?= $totalAssets ?></div>
                        <div class="text-10">assets reviewed (<?= $percent ?>%)</div>
                    </div>
                </div>
                <div class="container-9" style="display: flex; flex-direction: row; gap: 12px; align-items: center; justify-content: flex-end; margin-left: auto; width: auto; flex-wrap: wrap;">
                    <div class="container-6" style="padding: 6px 14px; background: rgba(59, 130, 246, 0.08); border-radius: 6px; border: 1px solid rgba(59, 130, 246, 0.2); display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #3B82F6; display: inline-block;"></span>
                        <div class="text-12" style="font-weight: 600; color: #1D4ED8; font-size: 13px;"><?= $inReviewCount ?> In Review</div>
                    </div>
                    <div class="container-6" style="padding: 6px 14px; background: rgba(239, 68, 68, 0.08); border-radius: 6px; border: 1px solid rgba(239, 68, 68, 0.2); display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #EF4444; display: inline-block;"></span>
                        <div class="text-11" style="font-weight: 600; color: #B91C1C; font-size: 13px;"><?= $rejectedCount ?> Rejected</div>
                    </div>
                    <div class="container-6" style="padding: 6px 14px; background: rgba(16, 185, 129, 0.08); border-radius: 6px; border: 1px solid rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #10B981; display: inline-block;"></span>
                        <div class="text-13" style="font-weight: 600; color: #047857; font-size: 13px;"><?= $approvedCount ?> Approved</div>
                    </div>
                    <div class="container-6" style="padding: 6px 14px; background: rgba(99, 102, 241, 0.08); border-radius: 6px; border: 1px solid rgba(99, 102, 241, 0.2); display: flex; align-items: center; gap: 8px;">
                        <span style="width: 8px; height: 8px; border-radius: 50%; background: #6366F1; display: inline-block;"></span>
                        <div class="text-13" style="font-weight: 600; color: #4338CA; font-size: 13px;"><?= $releasedCount ?> Released</div>
                    </div>
                </div>
            </div>

            <!-- ================= SECTION: ASSETS (TABLE) ================= -->
            <div class="section-ASSETS">
                <div class="section-header">
                    <div class="div-4">
                        <div class="div-2"><div class="text-14">Assets</div></div>
                        <div class="background-8"><div class="text-15"><?= count($assets) ?> items</div></div>
                    </div>
                    <div class="div-4">
                        <div class="background-9">
                            <button class="button-3 filter-btn active" onclick="filterAssetTab('all', this)">All (<?= count($assets) ?>)</button>
                            <button class="button-4 filter-btn" onclick="filterAssetTab('need_review', this)">Need Review (<?= $inReviewCount ?>)</button>
                            <button class="button-4 filter-btn" onclick="filterAssetTab('rejected', this)">Rejected (<?= $rejectedCount ?>)</button>
                            <button class="button-4 filter-btn" onclick="filterAssetTab('approved', this)">Approved (<?= $approvedCount ?>)</button>
                            <button class="button-4 filter-btn" onclick="filterAssetTab('released', this)">Released (<?= $releasedCount ?>)</button>
                        </div>
                        <div class="input-box-wrapper">
                            <svg class="icon-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <circle cx="11" cy="11" r="8"/>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input class="input" type="text" placeholder="Filter assets..." oninput="filterAssetsText(this.value)" />
                        </div>
                    </div>
                </div>

                <div class="table-header">
                    <div class="text-17" style="text-align: left;">ASSET</div>
                    <div class="text-17" style="text-align: center;">VERSION</div>
                    <div class="text-17" style="text-align: center;">AI ASSESSMENT</div>
                    <div class="text-17" style="text-align: center;">STATUS</div>
                    <div class="text-17" style="text-align: center;">SUBMITTED</div>
                    <div class="text-18" style="text-align: center;">ACTION</div>
                </div>

                <div id="assetsTableBody" style="width: 100%;">
                    <?php if (!empty($assets)): ?>
                        <?php foreach ($assets as $ast): 
                            $isSelected = ($selectedAsset && $ast['id'] === $selectedAsset['id']);
                            $isRejected = ($ast['status'] === 'rejected');
                            $rowClass = $isSelected ? 'row-selected' : ($isRejected ? 'row-rejected' : '');
                            
                            $aiPillClass = 'needs-look';
                            if ($ast['ai_verdict'] === 'Pass') $aiPillClass = 'pass';
                            elseif ($ast['ai_verdict'] === 'Fail') $aiPillClass = 'fail';
                            elseif ($ast['ai_verdict'] === 'Scoring') $aiPillClass = 'scoring';

                            $stRaw = str_replace([' ', '-'], '_', strtolower(trim($ast['status'] ?? 'draft')));
                            if ($stRaw === 'awaiting_release' || $stRaw === 'awaiting') $stRaw = 'approved';

                            $statusPillClass = $stRaw;
                            $statusLabelText = ucfirst(str_replace('_', ' ', $stRaw));
                            if ($stRaw === 'in_review') $statusLabelText = 'In Review';
                        ?>
                            <div class="asset-table-row <?= $rowClass ?>" 
                                 data-id="<?= $ast['id'] ?>"
                                 data-name="<?= strtolower(htmlspecialchars($ast['name'])) ?>"
                                 data-status="<?= $ast['status'] ?>"
                                 onclick="selectAssetRow(<?= $ast['id'] ?>)">
                                
                                <!-- ASSET -->
                                <div class="container-18">
                                    <div class="container-wrapper">
                                        <?php if (strpos(strtolower($ast['format']), 'pdf') !== false): ?>
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                                <polyline points="14 2 14 8 20 8"/>
                                                <line x1="16" y1="13" x2="8" y2="13"/>
                                                <line x1="16" y1="17" x2="8" y2="17"/>
                                            </svg>
                                        <?php else: ?>
                                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                                <circle cx="8.5" cy="8.5" r="1.5"/>
                                                <polyline points="21 15 16 10 5 21"/>
                                            </svg>
                                        <?php endif; ?>
                                    </div>
                                    <div class="div-2">
                                        <div class="text-19"><?= htmlspecialchars(html_entity_decode((string)$ast['name'], ENT_QUOTES, 'UTF-8')) ?></div>
                                        <div class="text-4"><?= htmlspecialchars($ast['format']) ?></div>
                                    </div>
                                </div>

                                <!-- VERSION -->
                                <div style="display: flex; justify-content: center; align-items: center; text-align: center;">
                                    <span class="<?= $isSelected ? 'background-10 text-20' : 'background-14 text-23' ?>">
                                        <?= htmlspecialchars($ast['version_label']) ?>
                                    </span>
                                </div>

                                <!-- AI ASSESSMENT -->
                                <div style="display: flex; justify-content: center; align-items: center; text-align: center;">
                                    <span class="ai-pill <?= $aiPillClass ?>">
                                        <span class="dot"></span>
                                        <span><?= htmlspecialchars($ast['ai_verdict']) ?></span>
                                    </span>
                                </div>

                                <!-- STATUS -->
                                <div style="display: flex; justify-content: center; align-items: center; text-align: center;">
                                    <span class="status-pill-table <?= $statusPillClass ?>">
                                        <span class="dot"></span>
                                        <span><?= htmlspecialchars($statusLabelText) ?></span>
                                    </span>
                                </div>

                                <!-- SUBMITTED -->
                                <div class="div-3" style="display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center;">
                                    <div class="text-wrapper-8"><?= htmlspecialchars($ast['submitted_date']) ?></div>
                                    <div class="text-wrapper-9" style="white-space: nowrap;"><?= htmlspecialchars($ast['maker_name']) ?></div>
                                </div>

                                <!-- ACTION -->
                                <div class="table-action-cell" onclick="event.stopPropagation();">
                                    <button type="button" class="btn-table-edit" title="Edit Asset" onclick="event.stopPropagation(); openEditAssetNameModal(<?= (int)$ast['id'] ?>, '<?= esc_js($ast['name']) ?>', '<?= esc_js($ast['format']) ?>')">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>
                                    <?php 
                                    $sep = (strpos($aiScoringResultUrl, '?') !== false) ? '&' : '?';
                                    $rowReviewUrl = esc_url($aiScoringResultUrl . $sep . 'version_id=' . (int)($ast['current_version_id'] ?? 0) . '&asset_id=' . (int)$ast['id'] . '&campaign_id=' . (int)$campId);
                                    ?>
                                    <?php if ($ast['status'] === 'in_review'): ?>
                                        <a href="<?= $rowReviewUrl ?>" class="btn-table-review">Review</a>
                                    <?php else: ?>
                                        <a href="<?= $rowReviewUrl ?>" class="btn-table-view">View</a>
                                    <?php endif; ?>
                                    <button type="button" class="btn-table-diff" onclick="event.stopPropagation(); openDiffModal(<?= htmlspecialchars(json_encode($ast)) ?>)">Diff</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 48px; text-align: center; color: #64748B;">
                            <p style="font-size: 16px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">No assets registered for this campaign</p>
                            <p style="font-size: 13.5px;">Assets uploaded by Makers will appear here automatically.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ================= SECTION: SELECTED VERSION ================= -->
            <?php if ($selectedAsset): 
                $selAiClass = 'needs-look';
                if ($selectedAsset['ai_verdict'] === 'Pass') $selAiClass = 'pass';
                elseif ($selectedAsset['ai_verdict'] === 'Fail') $selAiClass = 'fail';
                elseif ($selectedAsset['ai_verdict'] === 'Scoring') $selAiClass = 'scoring';
            ?>
                <div class="section-SELECTED" id="selectedVersionSection">
                    <!-- 1. ZONE VERSION & PREVIEW -->
                    <div class="zone-version">
                        <div class="container-22">
                            <div class="container-10">
                                <div class="text-8">SELECTED VERSION</div>
                                <div class="background-22">
                                    <div class="text-31"><?= htmlspecialchars($selectedAsset['status_label']) ?></div>
                                </div>
                            </div>
                            <div class="text-wrapper-10"><?= htmlspecialchars($selectedAsset['name']) ?> • <?= htmlspecialchars($selectedAsset['version_label']) ?></div>
                            <div class="text-wrapper-9">Submitted <?= htmlspecialchars($selectedAsset['submitted_full']) ?> by <?= htmlspecialchars($selectedAsset['maker_name']) ?></div>
                        </div>

                        <div class="background-23">
                            <?php if (!empty($selectedAsset['file_url'])): ?>
                                <?php 
                                    $ext = strtolower(pathinfo(parse_url($selectedAsset['file_url'], PHP_URL_PATH), PATHINFO_EXTENSION));
                                    if (in_array($ext, ['png', 'jpg', 'jpeg', 'webp', 'svg'])): 
                                ?>
                                    <img src="<?= htmlspecialchars($selectedAsset['file_url']) ?>" alt="Asset Preview" class="preview-asset-img" />
                                <?php elseif ($ext === 'pdf'): ?>
                                    <iframe src="<?= htmlspecialchars($selectedAsset['file_url']) ?>#toolbar=0&navpanes=0&view=Fit" class="preview-asset-img" style="width: 100%; height: 260px; border: none; border-radius: 6px; pointer-events: none; background: #ffffff;"></iframe>
                                <?php else: ?>
                                    <div style="display: flex; flex-direction: column; align-items: center; gap: 8px; color: #4F56A6;">
                                        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                            <polyline points="14 2 14 8 20 8"/>
                                        </svg>
                                        <span style="font-weight: 700; font-size: 13px;">Document Ready</span>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; align-items: center; gap: 8px; color: #94A3B8;">
                                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                        <circle cx="8.5" cy="8.5" r="1.5"/>
                                        <polyline points="21 15 16 10 5 21"/>
                                    </svg>
                                    <span style="font-size: 13px; font-weight: 500;">Asset Visual Canvas</span>
                                </div>
                            <?php endif; ?>
                            <div class="overlay-2"><p class="p">Asset Preview (<?= htmlspecialchars($selectedAsset['format']) ?>)</p></div>
                        </div>
                    </div>

                    <!-- 2. ZONE AI PRE-FLIGHT -->
                    <div class="zone-AI-pre-flight">
                        <div class="container-23">
                            <div class="container-6">
                                <svg class="icon-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                                </svg>
                                <div class="text-19">AI Pre-Flight Check</div>
                            </div>
                            <div class="text-32">Advisory Only</div>
                        </div>

                        <div class="div-7">
                            <?php if (!empty($selectedAsset['ai_checks'])): ?>
                                <?php foreach ($selectedAsset['ai_checks'] as $chk): 
                                    $isPass = (strpos(strtoupper($chk['verdict']), 'PASS') !== false);
                                    $isFail = (strpos(strtoupper($chk['verdict']), 'FAIL') !== false);
                                    $chkClass = $isPass ? 'pass' : ($isFail ? 'fail' : 'needs-look');
                                ?>
                                    <div class="check">
                                        <div class="check-left">
                                            <?php if ($isPass): ?>
                                                <svg class="icon-11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                                                    <polyline points="22 4 12 14.01 9 11.01"/>
                                                </svg>
                                            <?php else: ?>
                                                <svg class="icon-10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                                    <circle cx="12" cy="12" r="10"/>
                                                    <line x1="12" y1="8" x2="12" y2="12"/>
                                                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                                                </svg>
                                            <?php endif; ?>
                                            <div class="text-19" style="font-size: 13.5px;"><?= htmlspecialchars($chk['criterion']) ?></div>
                                        </div>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <?php if (isset($chk['score']) && $chk['score'] !== null): ?>
                                                <span style="font-size: 12px; font-weight: 700; color: #475569; font-family: 'JetBrains Mono', monospace;"><?= (int)$chk['score'] ?>/100</span>
                                            <?php endif; ?>
                                            <span class="ai-pill <?= $chkClass ?>">
                                                <span class="dot"></span>
                                                <span><?= htmlspecialchars($chk['verdict']) ?></span>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div style="margin-top: 12px; padding-top: 10px; border-top: 1px dashed #E2E8F0; display: flex; justify-content: flex-end;">
                                    <a href="<?= esc_url($aiScoringResultUrl . ((strpos($aiScoringResultUrl, '?') !== false) ? '&' : '?') . 'version_id=' . (int)($selectedAsset['current_version_id'] ?? 0) . '&asset_id=' . (int)$selectedAsset['id'] . '&campaign_id=' . (int)$campId) ?>" style="font-size: 12px; font-weight: 700; color: #4F56A6; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                        <span>View Full AI Report →</span>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div style="padding: 18px; text-align: center; color: #64748B; font-size: 13px; background: #F8FAFC; border-radius: 8px;">
                                    AI pre-flight check has not been run yet (Pending evaluation).
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 3. ZONE CHECKER NEXT ACTION -->
                    <div class="zone-checker-next">
                        <div class="container-22">
                            <div class="text-wrapper-11">NEXT ACTION</div>
                            <div class="text-wrapper-10">Review <?= htmlspecialchars($selectedAsset['version_label']) ?></div>
                            <p style="font-size: 13px; color: #475569; margin-top: 4px;">
                                <?php if ($selectedAsset['status'] === 'in_review'): ?>
                                    Awaiting your compliance sign-off or change requests.
                                <?php elseif ($selectedAsset['status'] === 'rejected'): ?>
                                    Revision requested. Feedback delivered to Maker.
                                <?php else: ?>
                                    Asset marked as approved. Ready for admin production release.
                                <?php endif; ?>
                            </p>
                        </div>

                        <div style="width: 100%;">
                            <button class="button-10" onclick="openReviewModal(<?= htmlspecialchars(json_encode($selectedAsset)) ?>)">
                                <span>Review AI Result</span>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </button>
                            <button class="button-11" onclick="openDiffModal(<?= htmlspecialchars(json_encode($selectedAsset)) ?>)">
                                <span class="text-35">Compare with previous versions →</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ================= SECTION: SPLIT (VERSIONS TIMELINE + GUIDELINES) ================= -->
            <div class="element-SPLIT-SECTION">
                
                <!-- Col 1: Version History -->
                <div class="version-history-col">
                    <div class="container-24">
                        <div class="text-14">Versions</div>
                        <?php if (count($versionHistory) > 1): ?>
                            <button class="btn-table-diff" onclick="openDiffModal(<?= htmlspecialchars(json_encode($selectedAsset)) ?>)">
                                Compare V<?= $versionHistory[0]['version_num'] ?> / V<?= end($versionHistory)['version_num'] ?>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="timeline-container">
                        <?php if (!empty($versionHistory)): ?>
                            <div class="timeline-line"></div>
                            <?php foreach ($versionHistory as $vh): 
                                $bClass = $vh['is_current'] ? 'current' : ($vh['status'] === 'approved' || $vh['status'] === 'released' ? 'approved' : ($vh['status'] === 'rejected' ? 'rejected' : ''));
                            ?>
                                <div class="timeline-node">
                                    <div class="timeline-badge <?= $bClass ?>">
                                        V<?= $vh['version_num'] ?>
                                    </div>
                                    <span class="timeline-label">V<?= $vh['version_num'] ?><?= $vh['is_current'] ? ' (Current)' : '' ?></span>
                                    <span class="timeline-sub"><?= htmlspecialchars($vh['date_status']) ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: 16px; text-align: center; color: #94A3B8; font-size: 13px;">
                                No version history recorded.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Asset Reference -->
                    <div style="background: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px; display: flex; align-items: center; justify-content: space-between; margin-top: auto;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; background: #EBEDFF; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #1F2674;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1F2674" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                            </div>
                            <div>
                                <div style="font-size: 13px; font-weight: 700; color: #161B2E;"><?= htmlspecialchars($selectedAsset['name'] ?? 'Asset Reference') ?></div>
                                <div style="font-size: 11.5px; color: #64748B;">Audit Trail ID: #<?= $selectedAsset['current_version_id'] ?? 0 ?></div>
                            </div>
                        </div>
                        <span style="font-size: 12px; font-weight: 600; color: #10B981;">Synced</span>
                    </div>
                </div>

                <!-- Col 2: Applicable Guidelines -->
                <div class="guidelines-strip-col">
                    <div class="container-10">
                        <div class="text-14">Applicable Guidelines</div>
                        <div class="text-42" onclick="openGuidelinesModal()">View all (<?= count($guidelines) ?>) →</div>
                    </div>

                    <div class="div-7">
                        <?php if (!empty($guidelines)): ?>
                            <?php foreach ($guidelines as $g): ?>
                                <div class="guideline-row">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 30px; height: 30px; border-radius: 6px; background: #EBEDFF; display: flex; align-items: center; justify-content: center; color: #1F2674;">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                        </div>
                                        <div class="text-2" style="font-size: 13.5px;"><?= htmlspecialchars($g['name']) ?></div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span style="font-size: 12px; font-weight: 600; color: #767682;"><?= htmlspecialchars($g['tag']) ?></span>
                                        <?php if (!empty($g['url']) && $g['url'] !== '#'): ?>
                                            <a href="<?= esc_url($g['url']) ?>" target="_blank" style="font-size: 11.5px; color: #1F2674; font-weight: 600; text-decoration: underline;">View</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: 24px; text-align: center; color: #94A3B8; font-size: 13px; background: #F8FAFC; border-radius: 8px;">
                                0 guidelines attached to this campaign.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- ================= SECTION: RECENT REVIEW ACTIVITY (AUDIT LOG) ================= -->
            <div class="section-RECENT">
                <div class="horizontal-border" style="display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div class="text-14">Review Activity</div>
                        <div class="text-32">Audited Log</div>
                    </div>
                    <a href="<?= esc_url(pcs_url('audit-log.php') . '?campaign_id=' . (int)$campId . '&asset_id=' . (int)($selectedAsset['id'] ?? 0) . '&from=detail_asset') ?>" style="font-size: 12px; font-weight: 600; color: #4E56A6; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                        <span>View Full Audit Log →</span>
                    </a>
                </div>

                <div class="activity-list">
                    <?php if (!empty($activities)): ?>
                        <?php foreach ($activities as $act): ?>
                            <div class="activity-item">
                                <div class="activity-left">
                                    <div class="text-43"><?= htmlspecialchars($act['date_time']) ?></div>
                                    <div class="text-44"><?= htmlspecialchars($act['text']) ?></div>
                                </div>
                                <div class="text-45"><?= htmlspecialchars($act['actor_info']) ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="padding: 20px; text-align: center; color: #94A3B8; font-size: 13px;">
                            No audit activity recorded yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ================= 11. REVIEW DECISION MODAL (APPROVE / REJECT) ================= -->
<div class="modal-overlay" id="reviewModal">
    <div class="modal-card">
        <form method="POST" action="">
            <input type="hidden" name="action" value="checker_decision">
            <input type="hidden" name="version_id" id="modalVersionId" value="0">
            <input type="hidden" name="campaign_id" value="<?= $campId ?>">

            <div class="modal-header">
                <div>
                    <h3 id="modalAssetTitle">Review Asset</h3>
                    <p style="font-size: 12.5px; color: #64748B;" id="modalAssetMeta">Campaign Asset Verification</p>
                </div>
                <div class="btn-modal-close" onclick="closeReviewModal()">×</div>
            </div>

            <div class="modal-body">
                <div style="background: #F3F2FF; border: 1px solid #D5D7FF; border-radius: 8px; padding: 14px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px;">
                        <span style="font-weight: 700; font-size: 13px; color: #1F2674;">AI Pre-Flight Verification Summary</span>
                        <span class="ai-pill pass" id="modalAiBadge"><span class="dot"></span><span>PASS</span></span>
                    </div>
                    <p style="font-size: 12px; color: #475569;" id="modalAiSummary">
                        Foreign logo misuse scanning, brand typography, and MAS compliance disclaimers verified.
                    </p>
                    <a id="modalInspectScorecardLink" href="#" style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #1F2674; text-decoration: underline; margin-top: 8px;">
                        <span>Open Full AI Scorecard & Evidence Lens</span>
                        <span>↗</span>
                    </a>
                </div>

                <div class="modal-field">
                    <label for="reviewNoteInput">Checker Notes & Feedback (Mandatory if Rejecting)</label>
                    <textarea class="modal-textarea" id="reviewNoteInput" name="review_note" placeholder="Provide clear compliance feedback, required revisions, or approval remarks..."></textarea>
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" name="decision" value="rejected" class="btn-modal-reject">
                    Reject / Request Changes
                </button>
                <button type="submit" name="decision" value="approved" class="btn-modal-approve">
                    Approve Asset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ================= 12. GUIDELINES MODAL ================= -->
<div class="modal-overlay" id="guidelinesModal">
    <div class="modal-card">
        <div class="modal-header">
            <h3>Applicable Campaign Guidelines</h3>
            <div class="btn-modal-close" onclick="closeGuidelinesModal()">×</div>
        </div>
        <div class="modal-body">
            <div class="div-7">
                <?php if (!empty($guidelines)): ?>
                    <?php foreach ($guidelines as $g): ?>
                        <div class="guideline-row" style="background: #FFFFFF; border: 1px solid #E2E8F0;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; background: #EBEDFF; border-radius: 6px; color: #1F2674;">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                </span>
                                <div>
                                    <div style="font-weight: 700; font-size: 14px; color: #161B2E;"><?= htmlspecialchars($g['name']) ?></div>
                                    <div style="font-size: 12px; color: #64748B;">Classification: <?= htmlspecialchars($g['tag']) ?></div>
                                </div>
                            </div>
                            <?php if (!empty($g['url']) && $g['url'] !== '#'): ?>
                                <a href="<?= htmlspecialchars($g['url']) ?>" target="_blank" style="padding: 6px 14px; background: #1F2674; color: #FFFFFF; font-size: 12.5px; font-weight: 700; border-radius: 6px;">
                                    Download
                                </a>
                            <?php else: ?>
                                <span style="font-size: 12px; color: #94A3B8; font-weight: 600;">System Standard</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="padding: 24px; text-align: center; color: #94A3B8; font-size: 13px;">
                        0 guidelines attached to this campaign.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ================= 13. VERSION DIFF MODAL ================= -->
<div class="modal-overlay" id="diffModal">
    <div class="modal-card" style="max-width: 820px;">
        <div class="modal-header">
            <h3>Version Comparison & Diff</h3>
            <div class="btn-modal-close" onclick="closeDiffModal()">×</div>
        </div>
        <div class="modal-body">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div style="border: 1px solid #E2E8F0; border-radius: 8px; padding: 14px; background: #F8FAFC;">
                    <div style="font-weight: 700; font-size: 14px; color: #64748B; margin-bottom: 8px;">Previous Version</div>
                    <div style="height: 180px; background: #E2E8F0; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #64748B; font-size: 13px;">
                        Previous Artwork / Copy
                    </div>
                </div>
                <div style="border: 1px solid #D5D7FF; border-radius: 8px; padding: 14px; background: #F6F5FF;">
                    <div style="font-weight: 700; font-size: 14px; color: #1F2674; margin-bottom: 8px;">Current Version</div>
                    <div style="height: 180px; background: #EBEDFF; border-radius: 6px; display: flex; align-items: center; justify-content: center; color: #1F2674; font-size: 13px;">
                        Current Revision (AI Verified)
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ================= 14. CLIENTSIDE CONTROLLERS ================= -->
<script>
function toggleProfileDropdown() {
    const el = document.getElementById('profileDropdown');
    if (el) el.classList.toggle('active');
}
window.addEventListener('click', function(e) {
    if (!e.target.closest('.checker-profile')) {
        const el = document.getElementById('profileDropdown');
        if (el) el.classList.remove('active');
    }
});

function selectAssetRow(assetId) {
    sessionStorage.setItem('detail_checker_scroll_y', window.scrollY);
    const tableEl = document.getElementById('assetsTableBody');
    if (tableEl) {
        sessionStorage.setItem('detail_checker_table_scroll_y', tableEl.scrollTop);
    }
    const url = new URL(window.location.href);
    url.searchParams.set('asset_id', assetId);
    window.location.href = url.toString();
}

document.addEventListener('DOMContentLoaded', function() {
    const savedTableY = sessionStorage.getItem('detail_checker_table_scroll_y');
    if (savedTableY !== null) {
        const tableEl = document.getElementById('assetsTableBody');
        if (tableEl) {
            tableEl.scrollTop = parseInt(savedTableY, 10);
        }
        sessionStorage.removeItem('detail_checker_table_scroll_y');
    }

    const savedY = sessionStorage.getItem('detail_checker_scroll_y');
    if (savedY !== null) {
        window.scrollTo({
            top: parseInt(savedY, 10),
            behavior: 'instant'
        });
        sessionStorage.removeItem('detail_checker_scroll_y');
    }
});

function filterAssetTab(filter, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => {
        b.classList.remove('button-3');
        b.classList.add('button-4');
    });
    btn.classList.remove('button-4');
    btn.classList.add('button-3');

    const rows = document.querySelectorAll('.asset-table-row');
    rows.forEach(r => {
        const st = (r.getAttribute('data-status') || '').toLowerCase().replace(/[\s-]/g, '_');
        if (filter === 'all') {
            r.style.display = 'grid';
        } else if (filter === 'need_review' || filter === 'in_review') {
            r.style.display = (st === 'in_review') ? 'grid' : 'none';
        } else if (filter === 'rejected') {
            r.style.display = (st === 'rejected') ? 'grid' : 'none';
        } else if (filter === 'approved') {
            r.style.display = (st === 'approved' || st === 'awaiting_release') ? 'grid' : 'none';
        } else if (filter === 'released') {
            r.style.display = (st === 'released') ? 'grid' : 'none';
        }
    });
}

function filterAssetsText(query) {
    const q = query.trim().toLowerCase();
    const rows = document.querySelectorAll('.asset-table-row');
    rows.forEach(r => {
        const name = r.getAttribute('data-name') || '';
        r.style.display = name.includes(q) ? 'grid' : 'none';
    });
}

const aiScoringBaseUrl = '<?= esc_url($aiScoringResultUrl) ?>';
function openReviewModal(asset) {
    if (!asset) return;
    const vId = asset.current_version_id || asset.version_id || 0;
    const aId = asset.id || asset.asset_id || 0;
    const cId = asset.campaign_id || <?= (int)$campId ?>;
    const sep = aiScoringBaseUrl.includes('?') ? '&' : '?';
    window.location.href = aiScoringBaseUrl + sep + 'version_id=' + vId + '&asset_id=' + aId + (cId ? '&campaign_id=' + cId : '');
}

function closeReviewModal() {
    const el = document.getElementById('reviewModal');
    if (el) el.classList.remove('active');
}

function openEditAssetNameModal(assetId, assetName, assetFormat) {
    document.getElementById('editAssetIdInput').value = assetId;
    document.getElementById('editAssetNameInput').value = assetName;
    const typeSelect = document.getElementById('editAssetTypeInput');
    if (typeSelect) {
        let found = false;
        for (let i = 0; i < typeSelect.options.length; i++) {
            if (typeSelect.options[i].value === assetFormat) {
                typeSelect.selectedIndex = i;
                found = true;
                break;
            }
        }
        if (!found && assetFormat) {
            const opt = new Option(assetFormat, assetFormat, true, true);
            typeSelect.add(opt);
        }
    }
    const modal = document.getElementById('editAssetNameModal');
    if (modal) modal.style.display = 'flex';
}

function closeEditAssetNameModal() {
    const modal = document.getElementById('editAssetNameModal');
    if (modal) modal.style.display = 'none';
}

function openGuidelinesModal() {
    document.getElementById('guidelinesModal').classList.add('active');
}
function closeGuidelinesModal() {
    document.getElementById('guidelinesModal').classList.remove('active');
}

function openDiffModal(asset) {
    const aid = (asset && asset.id) ? asset.id : '<?= (int) ($selectedAsset['id'] ?? 0) ?>';
    const cid = '<?= (int) ($campId ?? 0) ?>';
    const compareUrl = '<?= (function_exists('pcs_url') ? pcs_url('compare-version.php') : 'compare-version.php') ?>';
    window.location.href = compareUrl + '?asset_id=' + encodeURIComponent(aid) + '&campaign_id=' + encodeURIComponent(cid) + '&from=detail_checker';
}
function closeDiffModal() {
    const el = document.getElementById('diffModal');
    if (el) el.classList.remove('active');
}
</script>

    <!-- ================= MODAL: EDIT ASSET IDENTIFIERS ================= -->
    <div id="editAssetNameModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); z-index: 10000; align-items: center; justify-content: center;">
        <div style="background: #FFFFFF; border-radius: 12px; width: 100%; max-width: 480px; padding: 24px; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04); position: relative; margin: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <h3 style="font-size: 18px; font-weight: 800; color: #161B2E; margin: 0;">Edit Asset Identifiers</h3>
                <button type="button" style="font-size: 24px; cursor: pointer; color: #94A3B8; line-height: 1; border: none; background: none;" onclick="closeEditAssetNameModal()">&times;</button>
            </div>
            <p style="font-size: 13px; color: #64748B; margin-bottom: 16px;">Update asset name and format. Changes will be audited in compliance log.</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update_asset_identifiers">
                <input type="hidden" name="asset_id" id="editAssetIdInput" value="">
                <div style="display: flex; flex-direction: column; gap: 14px;">
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Asset Name</label>
                        <input type="text" name="title" id="editAssetNameInput" required style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box; outline: none;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 13px; font-weight: 700; color: #161B2E; margin-bottom: 6px;">Asset Format / Channel</label>
                        <select name="type" id="editAssetTypeInput" style="width: 100%; height: 42px; padding: 0 12px; border: 1px solid #CBD5E1; border-radius: 8px; font-size: 14px; box-sizing: border-box; background: #FFF; outline: none;">
                            <option value="PNG • Image">PNG • Image</option>
                            <option value="JPG • Image">JPG • Image</option>
                            <option value="PDF Document">PDF Document</option>
                            <option value="Social Story (9:16)">Social Story (9:16)</option>
                            <option value="Display Banner (300x250)">Display Banner (300x250)</option>
                            <option value="Marketing Asset">Marketing Asset</option>
                        </select>
                    </div>
                    <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 10px;">
                        <button type="button" onclick="closeEditAssetNameModal()" style="padding: 10px 16px; border: 1px solid #CBD5E1; border-radius: 8px; background: #FFF; color: #475569; font-weight: 600; font-size: 13.5px; cursor: pointer;">Cancel</button>
                        <button type="submit" style="padding: 10px 20px; border: none; border-radius: 8px; background: #1F2674; color: #FFF; font-weight: 700; font-size: 13.5px; cursor: pointer;">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

</body>
</html>

<?php
/**
 * Unified Top Navbar Component
 * 100% Consistent across all pages: Home, Maker, Checker, Admin, Detail, Review
 * Powered by WordPress Database: wp_users display_name, email, role, initials
 */

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!isset($currentUser) || empty($currentUser)) {
    if (function_exists('get_current_user_profile')) {
        $currentUser = get_current_user_profile();
    }
    if (empty($currentUser)) {
        $currentUser = [
            'name'         => 'Guest',
            'first_name'   => 'Guest',
            'email'        => '',
            'role'         => 'GUEST',
            'initials'     => 'GU',
            'avatar_bg'    => '#64748B',
            'avatar_color' => '#FFFFFF'
        ];
    }
}

// User Notifications Data (Realtime Cross-Role Hub)
$unavUserId = (int) ($currentUser['id'] ?? 0);

// Xu ly doc thong bao ngay lap tuc va dong bo khi click chuyen trang (chong hoan toan bounce-back)
if (!empty($_GET['notif_read']) && function_exists('pcs_mark_notification_read')) {
    $nr_id = (int) $_GET['notif_read'];
    if ($nr_id > 0) {
        pcs_mark_notification_read($nr_id, $unavUserId);
    }
}

$unavNotifications = ($unavUserId > 0 && function_exists('pcs_get_user_notifications')) ? pcs_get_user_notifications($unavUserId, 50) : [];
$unavUnreadCount = ($unavUserId > 0 && function_exists('pcs_get_unread_notifications_count')) ? pcs_get_unread_notifications_count($unavUserId) : 0;

// Sort strictly newest first (DESC)
if (!empty($unavNotifications)) {
    usort($unavNotifications, function($a, $b) {
        $ta = !empty($a['created_at']) ? strtotime($a['created_at']) : 0;
        $tb = !empty($b['created_at']) ? strtotime($b['created_at']) : 0;
        if ($ta === $tb) {
            return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
        }
        return $tb <=> $ta;
    });
}

$unavTotalCount = count($unavNotifications);
$unavUnreadLoaded = 0;
foreach ($unavNotifications as $n) {
    if (empty($n['is_read'])) $unavUnreadLoaded++;
}
$unavUnreadCount = $unavUnreadLoaded;
$unavReadCount = max(0, $unavTotalCount - $unavUnreadCount);

// Logo URL
$unavLogoUrl = 'images/logo.png?v=2';
if (function_exists('get_template_directory_uri')) {
    $unavLogoUrl = get_template_directory_uri() . '/images/logo.png?v=2';
} elseif (function_exists('get_stylesheet_directory_uri')) {
    $unavLogoUrl = get_stylesheet_directory_uri() . '/images/logo.png?v=2';
}

// Home URL
$unavHomeUrl = function_exists('pcs_url') ? pcs_url('home-page.php') : 'home-page.php';

// Current Script / Page Detection
$unavCurScript = basename(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
if (empty($unavCurScript) || $unavCurScript === 'index.php') {
    $unavCurScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
}

// Determine if we are on Home page
$unavIsHome = false;
if (!empty($options['is_home']) || !empty($is_home) || !empty($isHome)) {
    $unavIsHome = true;
} elseif (function_exists('is_front_page') && (is_front_page() || is_home())) {
    $unavIsHome = true;
} elseif (function_exists('is_page') && is_page(['home', 'homepage', 'home-page'])) {
    $unavIsHome = true;
} elseif (function_exists('is_page_template') && (is_page_template('home-page.php') || is_page_template('page-home.php'))) {
    $unavIsHome = true;
} elseif (function_exists('get_queried_object')) {
    $qObj = get_queried_object();
    if ($qObj && isset($qObj->post_name) && in_array($qObj->post_name, ['home', 'homepage', 'home-page'], true)) {
        $unavIsHome = true;
    }
}

if (!$unavIsHome) {
    $unavReqUri = strtolower($_SERVER['REQUEST_URI'] ?? '');
    $unavReqPath = trim(parse_url($unavReqUri, PHP_URL_PATH) ?: '', '/');
    if (
        $unavReqPath === '' || 
        $unavReqPath === 'home' || 
        $unavReqPath === 'homepage' || 
        preg_match('#(^|/)home(/|$)#i', $unavReqPath) || 
        $unavCurScript === 'home-page.php' || 
        $unavCurScript === 'index.php'
    ) {
        $unavIsHome = true;
    }
}

// Normalized role
$unavRole = strtoupper($currentUser['role'] ?? '');
if (empty($unavRole) || $unavRole === 'GUEST' || $unavRole === 'USER') {
    if (strpos($unavCurScript, 'admin') !== false) {
        $unavRole = 'ADMIN';
    } elseif (strpos($unavCurScript, 'checker') !== false) {
        $unavRole = 'CHECKER';
    } elseif (strpos($unavCurScript, 'maker') !== false) {
        $unavRole = 'MAKER';
    } else {
        $unavRole = 'GUEST';
    }
}

// Check if Guest / not logged in
$unavIsGuest = (empty($currentUser['id']) || $unavRole === 'GUEST' || empty($unavUserId));

// If on Home page OR user is a guest, ALWAYS show the "Log in" button (never show bell or avatar)
$unavShowLoginBtn = ($unavIsHome || $unavIsGuest);

// Back to All Campaigns URL
if (!isset($allCampaignsUrl) || empty($allCampaignsUrl)) {
    if ($unavRole === 'MAKER') {
        $allCampaignsUrl = function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php';
    } elseif ($unavRole === 'ADMIN') {
        $allCampaignsUrl = function_exists('pcs_url') ? pcs_url('admin-dashboard.php') : 'admin-dashboard.php';
    } else {
        $allCampaignsUrl = function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php';
    }
}
$unavCampaignsBtnText = !empty($campaignsBtnText) ? $campaignsBtnText : 'All Campaigns';

// Logo URL Target: User request:
// "Khi ấn vào cái logo ở các trang khác thì vẫn giữ là chuyển trang về trang dashboard hay gì đó, nhưng riêng cái logo ở trang login thì sẽ là ảnh tĩnh, không chuyển hướng sang trang nào hết"
if ($unavIsHome) {
    $unavRoleDashboardUrl = $unavHomeUrl;
} elseif ($unavRole === 'ADMIN') {
    $unavRoleDashboardUrl = function_exists('pcs_url') ? pcs_url('admin-dashboard.php') : 'admin-dashboard.php';
} elseif ($unavRole === 'MAKER') {
    $unavRoleDashboardUrl = function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php';
} elseif ($unavRole === 'CHECKER') {
    $unavRoleDashboardUrl = function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php';
} else {
    $unavRoleDashboardUrl = $unavHomeUrl;
}

// Avatar Background Color
$unavAvatarBg = $currentUser['avatar_bg'] ?? '#3B428A';
if ($unavRole === 'CHECKER') {
    $unavAvatarBg = '#3B428A';
} elseif ($unavRole === 'MAKER') {
    $unavAvatarBg = '#6C5CE7';
} elseif ($unavRole === 'ADMIN') {
    $unavAvatarBg = '#00B894';
}

// Determine logout URL:
if ($unavIsHome) {
    $unavLogoutUrl = (function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php') . '?action=logout&redirect_to=' . urlencode($unavHomeUrl);
} else {
    $unavLogoutUrl = (function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php') . '?action=logout';
}
?>
<!-- INLINE UNIFIED NAVBAR STYLES (Self-contained, Bulletproof) -->
<style>
.unified-top-navbar {
    width: 100% !important;
    height: 72px !important;
    min-height: 72px !important;
    background: #FFFFFF !important;
    border-bottom: 1px solid #ECEBF5 !important;
    position: sticky !important;
    top: 0 !important;
    left: 0 !important;
    z-index: 9999 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    box-sizing: border-box !important;
    margin: 0 !important;
    padding: 0 !important;
    font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02) !important;
}

.unified-top-navbar * {
    box-sizing: border-box !important;
}

.unified-top-navbar .unav-container {
    width: 100% !important;
    max-width: 1400px !important;
    height: 100% !important;
    padding: 0 28px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
}

.unified-top-navbar .unav-left {
    display: flex !important;
    align-items: center !important;
    flex: 0 0 auto !important;
    justify-content: flex-start !important;
}

.unified-top-navbar .unav-logo-link {
    display: flex !important;
    align-items: center !important;
    text-decoration: none !important;
    line-height: 1 !important;
    cursor: pointer !important;
    transition: opacity 0.15s ease !important;
}

.unified-top-navbar .unav-logo-link:hover {
    opacity: 0.88 !important;
}

.unified-top-navbar .unav-logo-img {
    height: 38px !important;
    width: auto !important;
    display: block !important;
    object-fit: contain !important;
}

.unified-top-navbar .unav-nav-center {
    display: flex !important;
    align-items: center !important;
    justify-content: flex-start !important;
    gap: 10px !important;
    flex: 1 1 auto !important;
    margin-left: 36px !important;
}

.unified-top-navbar .unav-nav-item {
    font-size: 14px !important;
    font-weight: 600 !important;
    color: #475569 !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    padding: 8px 16px !important;
    border-radius: 8px !important;
    transition: all 0.15s ease !important;
    cursor: pointer !important;
    white-space: nowrap !important;
    line-height: 1.2 !important;
}

.unified-top-navbar .unav-nav-item:hover {
    color: #1F2674 !important;
    background: #F1F5F9 !important;
}

.unified-top-navbar .unav-nav-item.active {
    color: #373E8C !important;
    font-weight: 700 !important;
    background: #EEF2FF !important;
}

.unified-top-navbar .unav-right {
    display: flex !important;
    align-items: center !important;
    gap: 16px !important;
    flex: 0 0 auto !important;
    justify-content: flex-end !important;
    margin-left: auto !important;
}

.unified-top-navbar .unav-btn-login {
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 6px !important;
    padding: 9px 24px !important;
    background: #373E8C !important;
    color: #FFFFFF !important;
    font-size: 14px !important;
    font-weight: 700 !important;
    border-radius: 10px !important;
    text-decoration: none !important;
    border: none !important;
    cursor: pointer !important;
    transition: all 0.18s ease !important;
    box-shadow: 0 2px 6px rgba(55, 62, 140, 0.2) !important;
}

.unified-top-navbar .unav-btn-login:hover {
    background: #2B3170 !important;
    color: #FFFFFF !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 4px 12px rgba(55, 62, 140, 0.3) !important;
}

.unified-top-navbar .unav-btn-campaigns {
    display: inline-flex !important;
    align-items: center !important;
    gap: 6px !important;
    padding: 7px 14px !important;
    background: #F1EFFF !important;
    color: #4F56A6 !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    text-decoration: none !important;
    border: none !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
}

.unified-top-navbar .unav-btn-campaigns:hover {
    background: #E5E1FA !important;
    color: #3B428A !important;
}

.unified-top-navbar .unav-divider {
    width: 1px !important;
    height: 28px !important;
    background: #E2E8F0 !important;
    margin: 0 4px !important;
}

/* Notification Bell & Dropdown Styles */
.unified-top-navbar .unav-notif-wrap {
    position: relative !important;
}

.unified-top-navbar .unav-notif-btn {
    position: relative !important;
    width: 38px !important;
    height: 38px !important;
    border-radius: 8px !important;
    background: #F8FAFC !important;
    border: 1px solid #E2E8F0 !important;
    color: #475569 !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
    outline: none !important;
}

.unified-top-navbar .unav-notif-btn:hover {
    background: #EEF2F6 !important;
    color: #1F2674 !important;
    border-color: #CBD5E1 !important;
}

.unified-top-navbar .unav-notif-btn.active {
    background: #EEF2FF !important;
    color: #4F56A6 !important;
    border-color: #C7D2FE !important;
}

.unified-top-navbar .unav-notif-badge {
    position: absolute !important;
    top: -4px !important;
    right: -4px !important;
    background: #EF4444 !important;
    color: #FFFFFF !important;
    font-size: 10px !important;
    font-weight: 800 !important;
    min-width: 17px !important;
    height: 17px !important;
    padding: 0 4px !important;
    border-radius: 9px !important;
    display: inline-flex;
    align-items: center !important;
    justify-content: center !important;
    border: 2px solid #FFFFFF !important;
    box-shadow: 0 1px 3px rgba(239, 68, 68, 0.4) !important;
    line-height: 1 !important;
    pointer-events: none !important;
    transition: transform 0.15s ease !important;
}

.unified-top-navbar .unav-notif-badge.is-hidden,
.unified-top-navbar .unav-notif-badge[style*="display: none"],
.unified-top-navbar .unav-notif-badge[style*="display:none"] {
    display: none !important;
}

.unified-top-navbar .unav-notif-dropdown {
    position: absolute !important;
    top: calc(100% + 10px) !important;
    right: 0 !important;
    width: 390px !important;
    max-width: 92vw !important;
    background: #FFFFFF !important;
    border: 1px solid #E2E8F0 !important;
    border-radius: 14px !important;
    box-shadow: 0 12px 30px -5px rgba(15, 23, 42, 0.12), 0 8px 10px -6px rgba(15, 23, 42, 0.08) !important;
    display: none !important;
    flex-direction: column !important;
    z-index: 10001 !important;
    overflow: hidden !important;
}

.unified-top-navbar .unav-notif-dropdown.active {
    display: flex !important;
    animation: unavFadeIn 0.15s ease-out !important;
}

.unified-top-navbar .unav-notif-header {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 14px 16px 10px 16px !important;
    background: #FFFFFF !important;
}

.unified-top-navbar .unav-notif-title {
    font-size: 13.5px !important;
    font-weight: 700 !important;
    color: #0F172A !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
}

.unified-top-navbar .unav-notif-count-pill {
    background: #EEF2FF !important;
    color: #4F56A6 !important;
    font-size: 11px !important;
    font-weight: 700 !important;
    padding: 2px 7px !important;
    border-radius: 10px !important;
}

.unified-top-navbar .unav-notif-mark-all {
    background: none !important;
    border: none !important;
    color: #4F56A6 !important;
    font-size: 11.5px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    padding: 3px 6px !important;
    border-radius: 4px !important;
    transition: background 0.12s ease !important;
}

.unified-top-navbar .unav-notif-mark-all:hover {
    background: #F1EFFF !important;
    text-decoration: none !important;
}

/* Tabs: All / Unread / Read */
.unified-top-navbar .unav-notif-tabs {
    display: flex !important;
    align-items: center !important;
    gap: 6px !important;
    padding: 0 16px 10px 16px !important;
    border-bottom: 1px solid #F1F5F9 !important;
    background: #FFFFFF !important;
}

.unified-top-navbar .unav-notif-tab {
    background: #F8FAFC !important;
    border: 1px solid #E2E8F0 !important;
    padding: 5px 11px !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    color: #64748B !important;
    border-radius: 20px !important;
    cursor: pointer !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    transition: all 0.15s ease !important;
    outline: none !important;
}

.unified-top-navbar .unav-notif-tab:hover {
    background: #F1F5F9 !important;
    color: #1E293B !important;
    border-color: #CBD5E1 !important;
}

.unified-top-navbar .unav-notif-tab.active {
    background: #F1EFFF !important;
    color: #4F56A6 !important;
    border-color: #C7D2FE !important;
    font-weight: 700 !important;
}

.unified-top-navbar .unav-tab-badge {
    font-size: 10px !important;
    font-weight: 700 !important;
    padding: 1px 6px !important;
    border-radius: 10px !important;
    background: #E2E8F0 !important;
    color: #475569 !important;
    line-height: 1.2 !important;
}

.unified-top-navbar .unav-notif-tab.active .unav-tab-badge {
    background: #4F56A6 !important;
    color: #FFFFFF !important;
}

.unified-top-navbar .unav-tab-badge.highlight {
    background: #FEE2E2 !important;
    color: #DC2626 !important;
}

.unified-top-navbar .unav-notif-tab.active .unav-tab-badge.highlight {
    background: #EF4444 !important;
    color: #FFFFFF !important;
}

.unified-top-navbar .unav-notif-list {
    max-height: 380px !important;
    overflow-y: auto !important;
    display: flex !important;
    flex-direction: column !important;
    background: #FFFFFF !important;
    scrollbar-width: thin;
    scrollbar-color: #CBD5E1 #F8FAFC;
}

.unified-top-navbar .unav-notif-list::-webkit-scrollbar {
    width: 6px;
}
.unified-top-navbar .unav-notif-list::-webkit-scrollbar-track {
    background: #F8FAFC;
}
.unified-top-navbar .unav-notif-list::-webkit-scrollbar-thumb {
    background: #CBD5E1;
    border-radius: 3px;
}

.unified-top-navbar .unav-notif-empty {
    padding: 36px 20px !important;
    text-align: center !important;
    display: flex;
    flex-direction: column !important;
    align-items: center !important;
    gap: 10px !important;
    color: #94A3B8 !important;
    font-size: 12.5px !important;
}

.unified-top-navbar .unav-notif-empty.is-hidden,
.unified-top-navbar .unav-notif-empty[style*="display: none"],
.unified-top-navbar .unav-notif-empty[style*="display:none"] {
    display: none !important;
}

.unified-top-navbar .unav-notif-item-wrap {
    position: relative !important;
    display: flex !important;
    align-items: stretch !important;
    border-bottom: 1px solid #F1F5F9 !important;
    transition: background 0.15s ease !important;
}

.unified-top-navbar .unav-notif-item-wrap.is-unread {
    background: #F8FAFD !important;
}

.unified-top-navbar .unav-notif-item-wrap.is-read {
    background: #FFFFFF !important;
}

.unified-top-navbar .unav-notif-item-wrap:hover {
    background: #F8FAFC !important;
}

.unified-top-navbar .unav-notif-item-wrap.is-unread:hover {
    background: #F1F3FF !important;
}

.unified-top-navbar .unav-notif-item {
    display: flex !important;
    align-items: flex-start !important;
    gap: 12px !important;
    padding: 12px 14px 12px 16px !important;
    text-decoration: none !important;
    color: inherit !important;
    flex: 1 !important;
    min-width: 0 !important;
    cursor: pointer !important;
}

.unified-top-navbar .unav-notif-quick-read {
    position: absolute !important;
    right: 12px !important;
    top: 12px !important;
    width: 22px !important;
    height: 22px !important;
    border-radius: 50% !important;
    background: #FFFFFF !important;
    border: 1px solid #CBD5E1 !important;
    color: #64748B !important;
    display: none !important;
    align-items: center !important;
    justify-content: center !important;
    cursor: pointer !important;
    transition: all 0.15s ease !important;
    z-index: 2 !important;
    outline: none !important;
}

.unified-top-navbar .unav-notif-item-wrap.is-unread:hover .unav-notif-quick-read {
    display: flex !important;
}

.unified-top-navbar .unav-notif-quick-read:hover {
    background: #4F56A6 !important;
    border-color: #4F56A6 !important;
    color: #FFFFFF !important;
    transform: scale(1.1) !important;
}

.unified-top-navbar .unav-notif-icon-box {
    width: 32px !important;
    height: 32px !important;
    border-radius: 8px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    flex-shrink: 0 !important;
    background: #F1F5F9 !important;
    color: #64748B !important;
    margin-top: 1px !important;
}

.unified-top-navbar .unav-notif-icon-box.approved,
.unified-top-navbar .unav-notif-icon-box.released {
    background: #ECFDF5 !important;
    color: #059669 !important;
}

.unified-top-navbar .unav-notif-icon-box.rejected {
    background: #FEF2F2 !important;
    color: #DC2626 !important;
}

.unified-top-navbar .unav-notif-icon-box.review_ready,
.unified-top-navbar .unav-notif-icon-box.in_review,
.unified-top-navbar .unav-notif-icon-box.awaiting_release {
    background: #FEF3C7 !important;
    color: #D97706 !important;
}

.unified-top-navbar .unav-notif-icon-box.team_assigned,
.unified-top-navbar .unav-notif-icon-box.team_update,
.unified-top-navbar .unav-notif-icon-box.campaign {
    background: #EEF2FF !important;
    color: #4F56A6 !important;
}

.unified-top-navbar .unav-notif-content {
    flex: 1 !important;
    min-width: 0 !important;
}

.unified-top-navbar .unav-notif-item-title {
    font-size: 12px !important;
    color: #1E293B !important;
    margin-bottom: 2px !important;
    line-height: 1.35 !important;
}

.unified-top-navbar .unav-notif-item-wrap.is-unread .unav-notif-item-title {
    font-weight: 700 !important;
    color: #0B132B !important;
}

.unified-top-navbar .unav-notif-item-wrap.is-read .unav-notif-item-title {
    font-weight: 600 !important;
    color: #475569 !important;
}

.unified-top-navbar .unav-notif-item-msg {
    font-size: 11.5px !important;
    color: #64748B !important;
    line-height: 1.4 !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    display: -webkit-box !important;
    -webkit-line-clamp: 2 !important;
    -webkit-box-orient: vertical !important;
}

.unified-top-navbar .unav-notif-item-wrap.is-unread .unav-notif-item-msg {
    color: #334155 !important;
}

.unified-top-navbar .unav-notif-item-time {
    font-size: 10.5px !important;
    color: #94A3B8 !important;
    margin-top: 4px !important;
    font-weight: 500 !important;
}

.unified-top-navbar .unav-notif-unread-dot {
    width: 7px !important;
    height: 7px !important;
    border-radius: 50% !important;
    background: #4F56A6 !important;
    flex-shrink: 0 !important;
    margin-top: 6px !important;
}

.unified-top-navbar .unav-profile-wrap {
    position: relative !important;
}

.unified-top-navbar .unav-profile-badge {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    cursor: pointer !important;
    user-select: none !important;
    padding: 4px 6px !important;
    border-radius: 8px !important;
    transition: background 0.15s ease !important;
}

.unified-top-navbar .unav-profile-badge:hover {
    background: #F8FAFC !important;
}

.unified-top-navbar .unav-avatar-box {
    position: relative !important;
    width: 38px !important;
    height: 38px !important;
    border-radius: 50% !important;
    background: <?= htmlspecialchars($unavAvatarBg) ?> !important;
    color: #FFFFFF !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    font-weight: 700 !important;
    font-size: 13px !important;
    letter-spacing: 0.5px !important;
    flex-shrink: 0 !important;
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08) !important;
}

.unified-top-navbar .unav-online-dot {
    position: absolute !important;
    bottom: -1px !important;
    right: -1px !important;
    width: 10px !important;
    height: 10px !important;
    border-radius: 50% !important;
    background: #10B981 !important;
    border: 2px solid #FFFFFF !important;
}

.unified-top-navbar .unav-user-info {
    display: flex !important;
    flex-direction: column !important;
    align-items: flex-start !important;
    line-height: 1.25 !important;
}

.unified-top-navbar .unav-user-name {
    font-size: 13.5px !important;
    font-weight: 700 !important;
    color: #161B2E !important;
    white-space: nowrap !important;
}

.unified-top-navbar .unav-user-role {
    font-size: 11px !important;
    font-weight: 700 !important;
    color: #4F56A6 !important;
    letter-spacing: 0.5px !important;
    text-transform: uppercase !important;
    white-space: nowrap !important;
}

.unified-top-navbar .unav-dropdown-menu {
    display: none;
    position: absolute !important;
    top: calc(100% + 10px) !important;
    right: 0 !important;
    min-width: 230px !important;
    background: #FFFFFF !important;
    border-radius: 10px !important;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05) !important;
    border: 1px solid #E2E8F0 !important;
    padding: 12px !important;
    z-index: 10000 !important;
}

.unified-top-navbar .unav-dropdown-menu.active {
    display: block !important;
    animation: unavFadeIn 0.15s ease-out !important;
}

@keyframes unavFadeIn {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
}

.unified-top-navbar .unav-dropdown-header {
    padding-bottom: 10px !important;
    border-bottom: 1px solid #F1F5F9 !important;
    margin-bottom: 8px !important;
}

.unified-top-navbar .unav-dropdown-name {
    font-size: 13px !important;
    font-weight: 700 !important;
    color: #0F172A !important;
}

.unified-top-navbar .unav-dropdown-email {
    font-size: 11.5px !important;
    color: #64748B !important;
    word-break: break-all !important;
}

.unified-top-navbar .unav-dropdown-role-pill {
    display: inline-block !important;
    margin-top: 5px !important;
    padding: 2px 8px !important;
    background: #F1EFFF !important;
    color: #4F56A6 !important;
    font-size: 10.5px !important;
    font-weight: 700 !important;
    border-radius: 4px !important;
}

.unified-top-navbar .unav-dropdown-link {
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    padding: 8px 10px !important;
    font-size: 12.5px !important;
    color: #334155 !important;
    text-decoration: none !important;
    border-radius: 6px !important;
    transition: background 0.12s ease !important;
}

.unified-top-navbar .unav-dropdown-link:hover {
    background: #F8FAFC !important;
    color: #1F2674 !important;
}

.unified-top-navbar .unav-dropdown-link.logout {
    color: #BE123C !important;
    font-weight: 600 !important;
}

.unified-top-navbar .unav-dropdown-link.logout:hover {
    background: #FFF1F2 !important;
}

/* Mobile & iPad Responsiveness */
@media (max-width: 1024px) {
    .unified-top-navbar .unav-nav-center {
        gap: 6px !important;
        margin-left: 20px !important;
    }
    .unified-top-navbar .unav-nav-item {
        padding: 6px 10px !important;
        font-size: 13px !important;
    }
}
@media (max-width: 860px) {
    .unified-top-navbar .unav-nav-center {
        display: none !important;
    }
    .unified-top-navbar .unav-left {
        flex: 1 !important;
        min-width: 0 !important;
    }
    .unified-top-navbar .unav-right {
        flex: 0 0 auto !important;
        margin-left: auto !important;
        min-width: 0 !important;
    }
}
@media (max-width: 480px) {
    .unified-top-navbar .unav-container {
        padding: 0 14px !important;
    }
    .unified-top-navbar .unav-user-info {
        display: none !important;
    }
    .unified-top-navbar .unav-logo-img {
        height: 30px !important;
    }
    .unified-top-navbar .unav-btn-login {
        padding: 7px 16px !important;
        font-size: 13px !important;
    }
}
</style>

<header class="unified-top-navbar">
    <div class="unav-container">
        <!-- 1. Left: Logo -->
        <div class="unav-left">
            <a href="<?= esc_url($unavRoleDashboardUrl) ?>" class="unav-logo-link" title="<?= esc_attr($unavRole === 'GUEST' ? 'Brand Compliance' : 'Dashboard') ?>">
                <img src="<?= esc_url($unavLogoUrl) ?>" alt="BrandCompliance Logo" class="unav-logo-img">
            </a>
        </div>

        <!-- 2. Dynamic Role-Based Links (Left-aligned, evenly spaced) -->
        <nav class="unav-nav-center">
            <?php if ($unavIsHome): ?>
                <a href="<?= esc_url($unavHomeUrl) ?>" class="unav-nav-item">
                    <span>Product</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
                </a>
                <a href="<?= esc_url($unavHomeUrl) ?>#standards" class="unav-nav-item">Standards</a>
                <a href="<?= esc_url($unavHomeUrl) ?>#success-stories" class="unav-nav-item">Success Stories</a>
                <a href="<?= esc_url($unavHomeUrl) ?>#about" class="unav-nav-item">About</a>
            <?php elseif ($unavRole === 'ADMIN'): ?>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('admin-dashboard.php') : 'admin-dashboard.php') ?>" class="unav-nav-item <?= ($unavCurScript === 'admin-dashboard.php' && strpos($_SERVER['REQUEST_URI'] ?? '', '#') === false) ? 'active' : '' ?>">Dashboard</a>
                <a href="<?= esc_url((function_exists('pcs_url') ? pcs_url('admin-dashboard.php') : 'admin-dashboard.php') . '#campaigns-section') ?>" class="unav-nav-item <?= ($unavCurScript === 'detail-asset-admin.php') ? 'active' : '' ?>">Campaign</a>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('approval-queue-admin.php') : 'approval-queue-admin.php') ?>" class="unav-nav-item <?= in_array($unavCurScript, ['approval-queue-admin.php', 'final-review-admin.php', 'release-detail.php'], true) ? 'active' : '' ?>">Final Approval</a>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('audit-log.php') : 'audit-log.php') ?>" class="unav-nav-item <?= ($unavCurScript === 'audit-log.php') ? 'active' : '' ?>">Audit History</a>
            <?php elseif ($unavRole === 'CHECKER'): ?>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php') ?>" class="unav-nav-item <?= ($unavCurScript === 'checker-dashboard.php' && strpos($_SERVER['REQUEST_URI'] ?? '', '#') === false) ? 'active' : '' ?>">Dashboard</a>
                <a href="<?= esc_url((function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php') . '#queue-section') ?>" class="unav-nav-item">Review Queue</a>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('checker-review.php') : 'checker-review.php') ?>" class="unav-nav-item <?= in_array($unavCurScript, ['checker-review.php', 'detail-asset-checker.php', 'compare-version.php'], true) ? 'active' : '' ?>">My Review</a>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('audit-log.php') : 'audit-log.php') ?>" class="unav-nav-item <?= ($unavCurScript === 'audit-log.php') ? 'active' : '' ?>">Audit History</a>
            <?php elseif ($unavRole === 'MAKER'): ?>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php') ?>" class="unav-nav-item <?= ($unavCurScript === 'maker-dashboard.php' && strpos($_SERVER['REQUEST_URI'] ?? '', '#') === false) ? 'active' : '' ?>">Dashboard</a>
                <a href="<?= esc_url((function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php') . '#campaigns-section') ?>" class="unav-nav-item">My Campaigns</a>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('detail-asset-maker.php') : 'detail-asset-maker.php') ?>" class="unav-nav-item <?= in_array($unavCurScript, ['detail-asset-maker.php', 'smart-maker-upload.php'], true) ? 'active' : '' ?>">My Assets</a>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('ai-scoring-result.php') : 'ai-scoring-result.php') ?>" class="unav-nav-item <?= in_array($unavCurScript, ['ai-scoring-result.php', 'ai-scoring-progress.php'], true) ? 'active' : '' ?>">Review Results</a>
            <?php else: ?>
                <a href="<?= esc_url($unavHomeUrl) ?>" class="unav-nav-item">
                    <span>Product</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
                </a>
                <a href="<?= esc_url($unavHomeUrl) ?>#standards" class="unav-nav-item">Standards</a>
                <a href="<?= esc_url($unavHomeUrl) ?>#success-stories" class="unav-nav-item">Success Stories</a>
                <a href="<?= esc_url($unavHomeUrl) ?>#about" class="unav-nav-item">About</a>
            <?php endif; ?>
        </nav>

        <!-- 3. Right: Actions (Log in OR Notification Bell + User Profile) -->
        <div class="unav-right">
            <?php if ($unavShowLoginBtn): ?>
                <a href="<?= esc_url(function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php') ?>" class="unav-btn-login">
                    <span>Log in</span>
                </a>
            <?php else: ?>
                <!-- Unified Notification Bell & Dropdown -->
                <div class="unav-notif-wrap">
                    <button type="button" class="unav-notif-btn" id="unavNotifBtn" onclick="toggleUnifiedNotifDropdown(event)" title="Notifications" aria-expanded="false">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                        </svg>
                        <span class="unav-notif-badge <?= $unavUnreadCount > 0 ? '' : 'is-hidden' ?>" id="unavNotifBadge" style="<?= $unavUnreadCount > 0 ? '' : 'display:none;' ?>"><?= $unavUnreadCount > 99 ? '99+' : $unavUnreadCount ?></span>
                    </button>

                    <div class="unav-notif-dropdown" id="unifiedNotifDropdown">
                        <div class="unav-notif-header">
                            <div class="unav-notif-title">
                                <span>Notifications</span>
                                <span class="unav-notif-count-pill" id="unavNotifPill" style="<?= $unavUnreadCount > 0 ? '' : 'display:none;' ?>"><?= $unavUnreadCount ?> new</span>
                            </div>
                            <button type="button" class="unav-notif-mark-all" id="unavMarkAllBtn" style="<?= $unavUnreadCount > 0 ? '' : 'display:none;' ?>" onclick="markAllNotificationsRead(<?= (int)$unavUserId ?>)">
                                Mark all as read
                            </button>
                        </div>

                        <!-- 3-Filter Tabs: All / Unread / Read -->
                        <div class="unav-notif-tabs" id="unavNotifTabs">
                            <button type="button" class="unav-notif-tab active" data-tab="all" onclick="filterNotifTab('all', this)">
                                <span>All</span>
                                <span class="unav-tab-badge" id="notifTabCountAll"><?= $unavTotalCount ?></span>
                            </button>
                            <button type="button" class="unav-notif-tab" data-tab="unread" onclick="filterNotifTab('unread', this)">
                                <span>Unread</span>
                                <span class="unav-tab-badge highlight" id="notifTabCountUnread"><?= $unavUnreadCount ?></span>
                            </button>
                            <button type="button" class="unav-notif-tab" data-tab="read" onclick="filterNotifTab('read', this)">
                                <span>Read</span>
                                <span class="unav-tab-badge" id="notifTabCountRead"><?= $unavReadCount ?></span>
                            </button>
                        </div>

                        <div class="unav-notif-list" id="unavNotifList">
                            <div class="unav-notif-empty <?= empty($unavNotifications) ? '' : 'is-hidden' ?>" id="unavNotifEmpty" style="<?= empty($unavNotifications) ? '' : 'display: none;' ?>">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#94A3B8" stroke-width="1.8">
                                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                                </svg>
                                <span id="unavNotifEmptyMsg">All caught up! No notifications yet.</span>
                            </div>

                            <?php if (!empty($unavNotifications)): ?>
                                <?php foreach ($unavNotifications as $notif): 
                                    $targetUrl = !empty($notif['link']) ? $notif['link'] : '';
                                    $isUnread = empty($notif['is_read']);
                                    $type = $notif['type'] ?? 'info';
                                ?>
                                    <div class="unav-notif-item-wrap <?= $isUnread ? 'is-unread' : 'is-read' ?>" 
                                         data-status="<?= $isUnread ? 'unread' : 'read' ?>" 
                                         data-notif-id="<?= (int)$notif['id'] ?>">
                                        <a href="<?= esc_url($targetUrl ?: 'javascript:void(0)') ?>" 
                                           class="unav-notif-item" 
                                           onclick="handleNotificationClick(event, <?= (int)$notif['id'] ?>, '<?= esc_js($targetUrl) ?>', this)">
                                            <div class="unav-notif-icon-box <?= esc_attr($type) ?>">
                                                <?php if ($type === 'approved' || $type === 'released'): ?>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                                <?php elseif ($type === 'rejected'): ?>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                                <?php elseif ($type === 'review_ready' || $type === 'in_review' || $type === 'awaiting_release'): ?>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                                <?php elseif ($type === 'team_assigned' || $type === 'team_update' || $type === 'campaign'): ?>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                                <?php else: ?>
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                                <?php endif; ?>
                                            </div>
                                            <div class="unav-notif-content">
                                                <div class="unav-notif-item-title"><?= esc_html($notif['title']) ?></div>
                                                <div class="unav-notif-item-msg"><?= esc_html($notif['message']) ?></div>
                                                <div class="unav-notif-item-time"><?= esc_html($notif['time_ago']) ?></div>
                                            </div>
                                            <?php if ($isUnread): ?>
                                                <span class="unav-notif-unread-dot" title="Unread"></span>
                                            <?php endif; ?>
                                        </a>
                                        <?php if ($isUnread): ?>
                                            <button type="button" class="unav-notif-quick-read" title="Mark as read" onclick="quickMarkNotifRead(event, <?= (int)$notif['id'] ?>, this)">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="unav-profile-wrap">
                    <div class="unav-profile-badge" onclick="toggleUnifiedProfileDropdown(event)">
                        <div class="unav-avatar-box">
                            <span><?= htmlspecialchars($currentUser['initials'] ?? 'US') ?></span>
                            <span class="unav-online-dot"></span>
                        </div>
                        <div class="unav-user-info">
                            <span class="unav-user-name"><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></span>
                            <span class="unav-user-role"><?= htmlspecialchars($currentUser['role'] ?? 'USER') ?></span>
                        </div>
                    </div>

                    <!-- Dropdown Menu (User Profile & Sign Out Only) -->
                    <div class="unav-dropdown-menu" id="unifiedProfileDropdown">
                        <div class="unav-dropdown-header">
                            <div class="unav-dropdown-name"><?= htmlspecialchars($currentUser['name'] ?? 'User') ?></div>
                            <div class="unav-dropdown-email"><?= htmlspecialchars($currentUser['email'] ?? '') ?></div>
                            <span class="unav-dropdown-role-pill"><?= htmlspecialchars($currentUser['role'] ?? 'USER') ?></span>
                        </div>
                        <a href="<?= esc_url($unavLogoutUrl) ?>" class="unav-dropdown-link logout">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            <span>Sign Out</span>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>

<script>
var unavUserId = <?= (int)$unavUserId ?>;
var currentUnreadCount = <?= (int)$unavUnreadCount ?>;
var totalNotifCount = <?= (int)$unavTotalCount ?>;
var activeNotifTab = 'all';
var pcsAjaxUrl = '<?= esc_js(function_exists('admin_url') ? admin_url('admin-ajax.php') : (function_exists('pcs_url') ? pcs_url('backend.php') : 'backend.php')) ?>';

function getNotifAjaxUrl(action, params) {
    var base = pcsAjaxUrl || window.location.pathname;
    var query = (base.indexOf('?') !== -1 ? '&' : '?') + 'action=' + encodeURIComponent(action);
    if (params) {
        for (var k in params) {
            if (params.hasOwnProperty(k)) {
                query += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            }
        }
    }
    return base + query + '&t=' + Date.now();
}

function toggleUnifiedProfileDropdown(e) {
    if (e) e.stopPropagation();
    const notifMenu = document.getElementById('unifiedNotifDropdown');
    const notifBtn = document.getElementById('unavNotifBtn');
    if (notifMenu) notifMenu.classList.remove('active');
    if (notifBtn) notifBtn.classList.remove('active');
    const menu = document.getElementById('unifiedProfileDropdown');
    if (menu) {
        menu.classList.toggle('active');
    }
}

function toggleUnifiedNotifDropdown(e) {
    if (e) e.stopPropagation();
    const profMenu = document.getElementById('unifiedProfileDropdown');
    if (profMenu) profMenu.classList.remove('active');
    const notifMenu = document.getElementById('unifiedNotifDropdown');
    const notifBtn = document.getElementById('unavNotifBtn');
    if (notifMenu) {
        var isOpen = notifMenu.classList.toggle('active');
        if (notifBtn) {
            if (isOpen) notifBtn.classList.add('active');
            else notifBtn.classList.remove('active');
        }
    }
}

function filterNotifTab(tab, btnEl) {
    activeNotifTab = tab;
    var tabs = document.querySelectorAll('.unav-notif-tab');
    tabs.forEach(function(t) { t.classList.remove('active'); });
    if (btnEl) btnEl.classList.add('active');

    var items = document.querySelectorAll('.unav-notif-item-wrap');
    var visibleCount = 0;

    items.forEach(function(it) {
        var status = it.getAttribute('data-status') || 'read';
        var match = false;
        if (tab === 'all') match = true;
        else if (tab === 'unread' && status === 'unread') match = true;
        else if (tab === 'read' && status === 'read') match = true;

        if (match) {
            it.style.setProperty('display', 'flex', 'important');
            visibleCount++;
        } else {
            it.style.setProperty('display', 'none', 'important');
        }
    });

    var emptyBox = document.getElementById('unavNotifEmpty');
    var emptyMsg = document.getElementById('unavNotifEmptyMsg');
    if (emptyBox) {
        if (visibleCount === 0) {
            emptyBox.classList.remove('is-hidden');
            emptyBox.style.setProperty('display', 'flex', 'important');
            if (emptyMsg) {
                if (tab === 'unread') emptyMsg.textContent = "You're all caught up! No unread notifications.";
                else if (tab === 'read') emptyMsg.textContent = "No read notifications yet.";
                else emptyMsg.textContent = "All caught up! No notifications yet.";
            }
        } else {
            emptyBox.classList.add('is-hidden');
            emptyBox.style.setProperty('display', 'none', 'important');
        }
    }
}

function updateNotifBadges(unreadDelta) {
    if (typeof unreadDelta === 'number') {
        currentUnreadCount = Math.max(0, currentUnreadCount - unreadDelta);
    }
    
    // Update Bell Badge
    var badge = document.getElementById('unavNotifBadge');
    if (badge) {
        if (currentUnreadCount > 0) {
            badge.classList.remove('is-hidden');
            badge.style.setProperty('display', 'inline-flex', 'important');
            badge.textContent = currentUnreadCount > 99 ? '99+' : currentUnreadCount;
        } else {
            badge.classList.add('is-hidden');
            badge.style.setProperty('display', 'none', 'important');
        }
    }

    // Update Header Pill
    var pill = document.getElementById('unavNotifPill');
    if (pill) {
        if (currentUnreadCount > 0) {
            pill.style.display = 'inline-block';
            pill.textContent = currentUnreadCount + ' new';
        } else {
            pill.style.display = 'none';
        }
    }

    // Update Header Mark All Button
    var markBtn = document.getElementById('unavMarkAllBtn');
    if (markBtn) {
        markBtn.style.display = (currentUnreadCount > 0) ? 'inline-block' : 'none';
    }

    // Update Tab Badges
    var tabUnread = document.getElementById('notifTabCountUnread');
    if (tabUnread) tabUnread.textContent = currentUnreadCount;

    var tabRead = document.getElementById('notifTabCountRead');
    if (tabRead) {
        var readCount = Math.max(0, totalNotifCount - currentUnreadCount);
        tabRead.textContent = readCount;
    }
}

function markItemReadLocally(wrap) {
    if (!wrap) return;
    wrap.classList.remove('is-unread');
    wrap.classList.add('is-read');
    wrap.setAttribute('data-status', 'read');
    var itemLink = wrap.querySelector('.unav-notif-item');
    if (itemLink) {
        itemLink.classList.remove('unread');
        itemLink.classList.add('read');
    }
    var dot = wrap.querySelector('.unav-notif-unread-dot');
    if (dot) dot.remove();
    var qBtn = wrap.querySelector('.unav-notif-quick-read');
    if (qBtn) qBtn.remove();
}

function handleNotificationClick(e, notifId, targetUrl, el) {
    var wrap = el ? el.closest('.unav-notif-item-wrap') : null;
    var wasUnread = wrap && wrap.getAttribute('data-status') === 'unread';

    if (wasUnread) {
        markItemReadLocally(wrap);
        updateNotifBadges(1);

        // Async AJAX fallback
        if (notifId && unavUserId) {
            var params = new URLSearchParams();
            params.append('action', 'pcs_mark_notif_read');
            params.append('notif_id', notifId);
            params.append('user_id', unavUserId);

            try {
                fetch(pcsAjaxUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString(),
                    keepalive: true
                });
            } catch(err) {}
        }
    }

    if (targetUrl && targetUrl !== '#' && targetUrl !== 'javascript:void(0)') {
        e.preventDefault();
        
        // Them notif_read vao targetUrl de trang dich danh dau doc DONG BO trong PHP ngay truoc khi render
        if (wasUnread && notifId) {
            var sep = targetUrl.indexOf('?') !== -1 ? '&' : '?';
            targetUrl = targetUrl + sep + 'notif_read=' + encodeURIComponent(notifId);
        }
        
        window.location.href = targetUrl;
    }
}

function quickMarkNotifRead(e, notifId, btnEl) {
    if (e) {
        e.stopPropagation();
        e.preventDefault();
    }
    var wrap = btnEl ? btnEl.closest('.unav-notif-item-wrap') : null;
    if (!wrap) return;

    markItemReadLocally(wrap);
    updateNotifBadges(1);

    if (notifId && unavUserId) {
        var params = new URLSearchParams();
        params.append('action', 'pcs_mark_notif_read');
        params.append('notif_id', notifId);
        params.append('user_id', unavUserId);

        fetch(pcsAjaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params.toString()
        }).then(function(r) { return r.json(); })
          .then(function(data) {
              if (data && typeof data.unread_count !== 'undefined') {
                  currentUnreadCount = data.unread_count;
                  updateNotifBadges(0);
              }
          }).catch(function(err) {});
    }

    if (activeNotifTab === 'unread') {
        wrap.style.setProperty('display', 'none', 'important');
        var currentActiveBtn = document.querySelector('.unav-notif-tab.active');
        filterNotifTab('unread', currentActiveBtn);
    }
}

function markAllNotificationsRead(userId) {
    var targetId = userId || unavUserId;
    if (!targetId) return;

    var wraps = document.querySelectorAll('.unav-notif-item-wrap.is-unread');
    wraps.forEach(function(w) {
        markItemReadLocally(w);
    });

    currentUnreadCount = 0;
    updateNotifBadges();

    var params = new URLSearchParams();
    params.append('action', 'pcs_mark_all_notifs_read');
    params.append('user_id', targetId);

    fetch(pcsAjaxUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    }).catch(function(err) {});

    if (activeNotifTab === 'unread') {
        var currentActiveBtn = document.querySelector('.unav-notif-tab.active');
        filterNotifTab('unread', currentActiveBtn);
    }
}

// Don sach tham so notif_read tren thanh dia chi URL
if (window.history && window.history.replaceState && window.location.search.indexOf('notif_read=') !== -1) {
    try {
        var cleanUrl = window.location.href.replace(/([?&])notif_read=\d+(&|$)/, function(m, p1, p2) {
            return (p1 === '?' && p2 === '&') ? '?' : (p1 === '?' ? '' : p2);
        });
        window.history.replaceState({}, document.title, cleanUrl);
    } catch(e) {}
}

window.addEventListener('click', function(e) {
    if (!e.target.closest('.unav-profile-wrap')) {
        const menu = document.getElementById('unifiedProfileDropdown');
        if (menu) {
            menu.classList.remove('active');
        }
    }
    if (!e.target.closest('.unav-notif-wrap')) {
        const notifMenu = document.getElementById('unifiedNotifDropdown');
        const notifBtn = document.getElementById('unavNotifBtn');
        if (notifMenu) {
            notifMenu.classList.remove('active');
        }
        if (notifBtn) {
            notifBtn.classList.remove('active');
        }
    }
});
</script>

<!-- Realtime Cross-Role Data Sync Client -->
<script id="pcs-realtime-sync-script">
(function() {
    var lastKnownSync = <?= (int) (function_exists('pcs_get_sync_version') ? pcs_get_sync_version() : time()) ?>;
    var isChecking = false;
    var lastCheckTime = Date.now();

    function checkSyncState(forceReload) {
        if (isChecking) return;
        var activeEl = document.activeElement;
        var isEditing = activeEl && (activeEl.tagName === 'INPUT' || activeEl.tagName === 'TEXTAREA' || activeEl.isContentEditable);
        if (isEditing && !forceReload) return;

        isChecking = true;
        var checkUrl = window.location.pathname + '?action=pcs_sync_heartbeat&t=' + Date.now();
        fetch(checkUrl, { cache: 'no-store' })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                isChecking = false;
                lastCheckTime = Date.now();
                if (data && data.last_sync && data.last_sync > lastKnownSync) {
                    lastKnownSync = data.last_sync;
                    showSyncToast();
                    setTimeout(function() {
                        window.location.reload();
                    }, 500);
                }
            })
            .catch(function() {
                isChecking = false;
            });
    }

    function showSyncToast() {
        if (document.getElementById('pcs-sync-toast')) return;
        var toast = document.createElement('div');
        toast.id = 'pcs-sync-toast';
        toast.style.cssText = 'position:fixed;bottom:24px;right:24px;background:#18224B;color:#ffffff;padding:10px 18px;border-radius:8px;font-size:12.5px;font-weight:600;display:flex;align-items:center;gap:8px;box-shadow:0 10px 25px rgba(0,0,0,0.15);z-index:999999;transition:all 0.3s ease;';
        toast.innerHTML = '<span style="width:8px;height:8px;background:#10B981;border-radius:50%;display:inline-block;"></span> Updating live data...';
        document.body.appendChild(toast);
    }

    // 1. Tự động kiểm tra ngay khi chuyển đổi tab / role (tab active trở lại)
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            checkSyncState(false);
        }
    });

    // 2. Kiểm tra khi cửa sổ nhận focus
    window.addEventListener('focus', function() {
        if (Date.now() - lastCheckTime > 3000) {
            checkSyncState(false);
        }
    });

    // 3. Heartbeat ngầm định kỳ mỗi 12 giây
    setInterval(function() {
        checkSyncState(false);
    }, 12000);
})();
</script>

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

// Logo URL
$unavLogoUrl = 'images/logo.png?v=2';
if (function_exists('get_template_directory_uri')) {
    $unavLogoUrl = get_template_directory_uri() . '/images/logo.png?v=2';
} elseif (function_exists('get_stylesheet_directory_uri')) {
    $unavLogoUrl = get_stylesheet_directory_uri() . '/images/logo.png?v=2';
}

// Home URL
$unavHomeUrl = function_exists('pcs_url') ? pcs_url('home-page.php') : 'home-page.php';

// Back to All Campaigns URL
$unavRole = strtoupper($currentUser['role'] ?? 'CHECKER');
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

// Logo URL Target: User request: Clicking logo should navigate to their role dashboard
$unavRoleDashboardUrl = $allCampaignsUrl;
if (!empty($currentUser['role'])) {
    if ($unavRole === 'ADMIN') {
        $unavRoleDashboardUrl = function_exists('pcs_url') ? pcs_url('admin-dashboard.php') : 'admin-dashboard.php';
    } elseif ($unavRole === 'MAKER') {
        $unavRoleDashboardUrl = function_exists('pcs_url') ? pcs_url('maker-dashboard.php') : 'maker-dashboard.php';
    } else {
        $unavRoleDashboardUrl = function_exists('pcs_url') ? pcs_url('checker-dashboard.php') : 'checker-dashboard.php';
    }
} else {
    $unavRoleDashboardUrl = $unavHomeUrl;
}

// Avatar Background Color
$unavAvatarBg = $currentUser['avatar_bg'] ?? '#3B428A';
if ($unavRole === 'CHECKER') {
    $unavAvatarBg = '#3B428A'; // Exact match to user reference screenshot
} elseif ($unavRole === 'MAKER') {
    $unavAvatarBg = '#6C5CE7';
} elseif ($unavRole === 'ADMIN') {
    $unavAvatarBg = '#00B894';
}

// Determine logout URL:
// User rule: "tất cả các role khi đăng xuất ở những trang không phải là home, thì phải trả về trang login. lúc đăng xuất sẽ bắt buộc phải về trang login"
$unavIsHome = false;
if (function_exists('is_front_page') && (is_front_page() || is_home())) {
    $unavIsHome = true;
} else {
    $unavReqUri = strtolower($_SERVER['REQUEST_URI'] ?? '');
    if (strpos($unavReqUri, 'home') !== false || trim(parse_url($unavReqUri, PHP_URL_PATH), '/') === '') {
        $unavIsHome = true;
    }
}

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
    gap: 36px !important;
}

.unified-top-navbar .unav-logo-link {
    display: flex !important;
    align-items: center !important;
    text-decoration: none !important;
    line-height: 1 !important;
}

.unified-top-navbar .unav-logo-img {
    height: 38px !important;
    width: auto !important;
    display: block !important;
    object-fit: contain !important;
}

.unified-top-navbar .unav-nav-links {
    display: flex !important;
    align-items: center !important;
    gap: 28px !important;
}

.unified-top-navbar .unav-nav-item {
    font-size: 14px !important;
    font-weight: 600 !important;
    color: #475569 !important;
    text-decoration: none !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 5px !important;
    transition: color 0.15s ease !important;
    cursor: pointer !important;
}

.unified-top-navbar .unav-nav-item:hover {
    color: #1F2674 !important;
}

.unified-top-navbar .unav-right {
    display: flex !important;
    align-items: center !important;
    gap: 16px !important;
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
@media (max-width: 900px) {
    .unified-top-navbar .unav-nav-links {
        display: none !important;
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
}
</style>

<header class="unified-top-navbar">
    <div class="unav-container">
        <!-- Left: Logo & Links -->
        <div class="unav-left">
            <a href="<?= esc_url($unavRoleDashboardUrl) ?>" class="unav-logo-link" title="Dashboard">
                <img src="<?= esc_url($unavLogoUrl) ?>" alt="ICTS Logo" class="unav-logo-img">
            </a>
            <nav class="unav-nav-links">
                <a href="javascript:void(0)" class="unav-nav-item">
                    <span>Product</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m6 9 6 6 6-6"/></svg>
                </a>
                <a href="<?= esc_url($unavHomeUrl) ?>#standards" class="unav-nav-item">Standards</a>
                <a href="<?= esc_url($unavHomeUrl) ?>#success-stories" class="unav-nav-item">Success Stories</a>
                <a href="<?= esc_url($unavHomeUrl) ?>#about" class="unav-nav-item">About</a>
            </nav>
        </div>

        <!-- Right: Profile Badge (Only Avatar and Name/Role) -->
        <div class="unav-right">
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
        </div>
    </div>
</header>

<script>
function toggleUnifiedProfileDropdown(e) {
    if (e) e.stopPropagation();
    const menu = document.getElementById('unifiedProfileDropdown');
    if (menu) {
        menu.classList.toggle('active');
    }
}
window.addEventListener('click', function(e) {
    if (!e.target.closest('.unav-profile-wrap')) {
        const menu = document.getElementById('unifiedProfileDropdown');
        if (menu) {
            menu.classList.remove('active');
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

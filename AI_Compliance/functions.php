<?php
/**
 * AI Compliance WordPress Theme Functions
 * Integrates directly with Project Core System Plugin (project-core-system.php)
 */

if (!defined('ABSPATH')) {
    // If accessed directly outside WordPress, exit or handle standalone
}

/**
 * Robust helper to extract user ID, display name, and email from either WP_User object or array
 */
if (!function_exists('pcs_extract_user_info')) {
    function pcs_extract_user_info($user) {
        if (is_object($user)) {
            $id = $user->ID ?? ($user->id ?? 0);
            $name = $user->display_name ?? ($user->name ?? ($user->user_login ?? 'User #' . $id));
            $email = $user->user_email ?? ($user->email ?? '');
            return [(int)$id, (string)$name, (string)$email];
        } elseif (is_array($user)) {
            $id = $user['ID'] ?? ($user['id'] ?? 0);
            $name = $user['display_name'] ?? ($user['name'] ?? ($user['user_login'] ?? 'User #' . $id));
            $email = $user['user_email'] ?? ($user['email'] ?? '');
            return [(int)$id, (string)$name, (string)$email];
        }
        return [0, 'User', ''];
    }
}

// 1. Tự động kiểm tra và nạp plugin Project Core nếu chưa được kích hoạt
add_action('after_setup_theme', 'ai_compliance_setup_core_system');
function ai_compliance_setup_core_system() {
    // Tìm nạp file project-core-system.php nếu nằm ở thư mục gốc hoặc mu-plugins
    $possible_paths = [
        ABSPATH . 'wp-content/plugins/project-core-system.php',
        dirname(get_template_directory()) . '/../project-core-system.php',
        ABSPATH . 'project-core-system.php',
    ];

    foreach ($possible_paths as $path) {
        if (!function_exists('pcs_register_custom_post_types') && file_exists($path)) {
            require_once $path;
            break;
        }
    }

    // Nạp các module phụ nếu có (queue, ai scoring)
    $queue_paths = [
        dirname(get_template_directory()) . '/../pcs-queue-versioning.php',
        ABSPATH . 'wp-content/plugins/pcs-queue-versioning.php',
    ];
    foreach ($queue_paths as $qpath) {
        if (!function_exists('pcs_process_new_upload') && file_exists($qpath)) {
            require_once $qpath;
            break;
        }
    }

    $poc_paths = [
        dirname(get_template_directory()) . '/../brand-compliance-ai/ai-compliance-poc.php',
        dirname(get_template_directory()) . '/../ai-compliance-poc.php',
        get_template_directory() . '/../brand-compliance-ai/ai-compliance-poc.php',
        get_template_directory() . '/../ai-compliance-poc.php',
        defined('ABSPATH') ? ABSPATH . 'wp-content/plugins/brand-compliance-ai/ai-compliance-poc.php' : '',
        defined('ABSPATH') ? ABSPATH . 'wp-content/plugins/ai-compliance-poc.php' : '',
    ];
    foreach ($poc_paths as $poc) {
        if (!function_exists('pcs_call_gemini_api') && file_exists($poc)) {
            require_once $poc;
            break;
        }
    }

    $api_paths = [
        dirname(get_template_directory()) . '/../pcs-rest-api-campaign.php',
        ABSPATH . 'wp-content/plugins/pcs-rest-api-campaign.php',
    ];
    foreach ($api_paths as $api) {
        if (!function_exists('pcs_register_campaign_endpoints') && file_exists($api)) {
            require_once $api;
            break;
        }
    }
}

// 2. Nạp Backend Integration Engine (Hỗ trợ cả khi file nằm ở thư mục gốc hoặc trong /inc/)
$backend_paths = [
    get_template_directory() . '/backend.php',
    get_template_directory() . '/inc/backend.php',
    __DIR__ . '/backend.php',
    __DIR__ . '/inc/backend.php',
];
foreach ($backend_paths as $bpath) {
    if (file_exists($bpath)) {
        require_once $bpath;
        break;
    }
}

// 3. Khởi tạo PHP Session an toàn trong WordPress
add_action('init', 'ai_compliance_init_session');
function ai_compliance_init_session() {
    if (!session_id() && !headers_sent()) {
        @session_start();
    }
}

// 4. Template Filter: Tự động điều hướng và router thông minh cho các trang hệ thống
add_filter('template_include', 'ai_compliance_template_router');
function ai_compliance_template_router($template) {
    $req_uri = strtolower($_SERVER['REQUEST_URI'] ?? '');

    // Nếu request chứa ai-scoring-result (kể cả lồng dưới checker-dashboard), phục vụ trực tiếp ai-scoring-result.php
    if (strpos($req_uri, 'ai-scoring-result') !== false) {
        $scoring_file = get_template_directory() . '/ai-scoring-result.php';
        if (!file_exists($scoring_file)) {
            $scoring_file = __DIR__ . '/ai-scoring-result.php';
        }
        if (file_exists($scoring_file)) {
            return $scoring_file;
        }
    }

    // Nếu request chứa final-review (final-review, final-review-admin), phục vụ trực tiếp final-review-admin.php
    if (strpos($req_uri, 'final-review') !== false) {
        $fr_file = get_template_directory() . '/final-review-admin.php';
        if (!file_exists($fr_file)) {
            $fr_file = __DIR__ . '/final-review-admin.php';
        }
        if (file_exists($fr_file)) {
            return $fr_file;
        }
    }

    // Nếu request chứa detail-asset-2, phục vụ trực tiếp detail-asset-maker.php
    if (strpos($req_uri, 'detail-asset-2') !== false) {
        $dam_file = get_template_directory() . '/detail-asset-maker.php';
        if (!file_exists($dam_file)) {
            $dam_file = __DIR__ . '/detail-asset-maker.php';
        }
        if (file_exists($dam_file)) {
            return $dam_file;
        }
    }

    // Nếu request chứa solution, phục vụ trực tiếp page-solution.php
    if (strpos($req_uri, 'solution') !== false) {
        $sol_file = get_template_directory() . '/page-solution.php';
        if (!file_exists($sol_file)) {
            $sol_file = __DIR__ . '/page-solution.php';
        }
        if (file_exists($sol_file)) {
            return $sol_file;
        }
    }

    // Nếu request chứa about-us hoặc about, phục vụ trực tiếp page-about-us.php
    if (strpos($req_uri, 'about-us') !== false || strpos($req_uri, 'about') !== false) {
        $about_file = get_template_directory() . '/page-about-us.php';
        if (!file_exists($about_file)) {
            $about_file = __DIR__ . '/page-about-us.php';
        }
        if (file_exists($about_file)) {
            return $about_file;
        }
    }

    $denied_file = get_template_directory() . '/access-denied.php';
    if (!file_exists($denied_file)) {
        $denied_file = __DIR__ . '/access-denied.php';
    }

    if (is_404() && file_exists($denied_file)) {
        return $denied_file;
    }

    if ((strpos($req_uri, 'access-denied') !== false || strpos($req_uri, '403') !== false || strpos($req_uri, 'unauthorized') !== false) && file_exists($denied_file)) {
        return $denied_file;
    }

    return $template;
}


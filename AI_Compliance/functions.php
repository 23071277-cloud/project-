<?php
/**
 * AI Compliance WordPress Theme Functions
 * Integrates directly with Project Core System Plugin (project-core-system.php)
 */

if (!defined('ABSPATH')) {
    // If accessed directly outside WordPress, exit or handle standalone
}

// 1. Tự động kiểm tra và nạp plugin Project Core nếu chưa được kích hoạt
add_action('after_setup_theme', 'ai_compliance_setup_core_system');
function ai_compliance_setup_core_system() {
    // Tìm nạp file project-core-system.php nếu nằm ở thư mục gốc hoặc mu-plugins
    $possible_paths = [
        ABSPATH . 'wp-content/plugins/project-core-system.php',
        dirname(get_template_directory()) . '/../project-core-system.php',
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
        dirname(get_template_directory()) . '/../ai-compliance-poc.php',
        dirname(get_template_directory()) . '/../pcs_call_gemini_api.php',
    ];
    foreach ($poc_paths as $poc) {
        if (!function_exists('pcs_call_gemini_api') && file_exists($poc)) {
            require_once $poc;
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


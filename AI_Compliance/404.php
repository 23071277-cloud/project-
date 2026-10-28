<?php
/**
 * 404 Not Found & Access Restricted Template
 * Template Name: 404 Not Found
 */

if (!defined('PCS_BACKEND_LOADED')) {
    if (file_exists(__DIR__ . '/backend.php')) {
        require_once __DIR__ . '/backend.php';
    } elseif (function_exists('get_template_directory') && file_exists(get_template_directory() . '/backend.php')) {
        require_once get_template_directory() . '/backend.php';
    }
}

if (function_exists('status_header')) {
    status_header(404);
} else {
    header('HTTP/1.1 404 Not Found');
}

if (!isset($_GET['required']) || empty($_GET['required'])) {
    $_GET['required'] = 'EXISTING RESOURCE OR CLEARANCE';
}

$accessDeniedPath = __DIR__ . '/access-denied.php';
if (!file_exists($accessDeniedPath) && function_exists('get_template_directory')) {
    $accessDeniedPath = get_template_directory() . '/access-denied.php';
}

if (file_exists($accessDeniedPath)) {
    include $accessDeniedPath;
} else {
    echo '<!DOCTYPE html><html><head><title>404 Not Found</title>    <?php if (function_exists("wp_head")) wp_head(); ?>
</head><body style="font-family:sans-serif;text-align:center;padding:60px;">';
    echo '<h1>404 · Page Not Found</h1><p>The requested page could not be located or clearance is restricted.</p>';
    echo '<p><a href="' . esc_url(function_exists('home_url') ? home_url('/') : '/') . '">Return to Homepage</a></p>';
    echo '    <?php if (function_exists("wp_footer")) wp_footer(); ?>
</body></html>';
}
exit;

<?php
/**
 * Plugin Name: Brand Compliance AI Platform
 * Plugin URI: https://example.com/brand-compliance-ai
 * Description: Nền tảng Maker-Checker hỗ trợ bởi AI (Google Gemini Multimodal Vision API & Real PDF Documents) kiểm định tuân thủ quy chuẩn quảng cáo MAS Singapore và Brand Guidelines theo đặc tả INS3281.
 * Version: 2.6.0
 * Author: Brand Compliance AI Team (Members 1, 2, 3)
 * Author URI: https://example.com
 * License: GPL-2.0+
 * Text Domain: brand-compliance-ai
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Khai báo mặc định Gemini API Key đã kiểm định xác thực từ Google
if ( ! defined( 'GEMINI_API_KEY' ) ) {
    define( 'GEMINI_API_KEY', base64_decode( 'QVEuQWI4Uk42S0JCakZKRUZmaXdqVlM4WEpDc2xtV1E3a1gtZjdEVzdCekx3dHdrUi1KZ2c=' ) );
}

// 1. Nạp Engine AI (Member 3)
require_once __DIR__ . '/ai-compliance-poc.php';

// 2. Nạp Backend lưu trữ Database CPT check_result và chuẩn bị dữ liệu (Member 2)
require_once __DIR__ . '/backend.php';

// 2.1 Nạp Cài đặt & Chuẩn đoán Gemini AI API Key
require_once __DIR__ . '/gemini-settings.php';

// 3. Đăng ký Shortcodes hiển thị giao diện kết quả chấm điểm [ai_compliance_result] / [ai_scoring_result]
add_shortcode( 'ai_compliance_result', function( $atts ) {
    ob_start();
    include __DIR__ . '/ai-scoring-result.php';
    return ob_get_clean();
});

add_shortcode( 'ai_scoring_result', function( $atts ) {
    ob_start();
    include __DIR__ . '/ai-scoring-result.php';
    return ob_get_clean();
});

// 4. Thêm Menu quản trị trên thanh công cụ WordPress
add_action( 'admin_menu', function() {
    add_menu_page(
        'Compliance Review',
        'Compliance Review',
        'manage_options',
        'brand-compliance-review',
        function() {
            include __DIR__ . '/ai-scoring-result.php';
        },
        'dashicons-clipboard',
        30
    );

    // Menu phụ bên dưới Check Results
    add_submenu_page(
        'edit.php?post_type=check_result',
        'Maker-Checker Compliance Review',
        'Compliance Review',
        'manage_options',
        'brand-compliance-review',
        function() {
            include __DIR__ . '/ai-scoring-result.php';
        }
    );

    // Menu Cài đặt Gemini AI API Key
    add_submenu_page(
        'brand-compliance-review',
        'Gemini AI Settings',
        'Gemini AI Settings',
        'manage_options',
        'gemini-ai-settings',
        'pcs_render_gemini_settings_page'
    );

    add_submenu_page(
        'edit.php?post_type=check_result',
        'Gemini AI Settings',
        'Gemini AI Settings',
        'manage_options',
        'gemini-ai-settings',
        'pcs_render_gemini_settings_page'
    );
});

// 5. Thêm liên kết thao tác nhanh trực tiếp trong trang Plugins (wp-admin/plugins.php)
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function( $links ) {
    $test_link = '<a href="' . admin_url( 'admin.php?page=ai-compliance-poc' ) . '" style="color:#2563eb; font-weight:700;">Chạy thử AI (Test PoC)</a>';
    $settings_link = '<a href="' . admin_url( 'admin.php?page=gemini-ai-settings' ) . '">Cài đặt API Key</a>';
    array_unshift( $links, $test_link, $settings_link );
    return $links;
});

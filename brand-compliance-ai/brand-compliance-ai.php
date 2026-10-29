<?php
/**
 * Plugin Name: Brand Compliance AI Platform
 * Plugin URI: https://example.com/brand-compliance-ai
 * Description: Nền tảng Maker-Checker hỗ trợ bởi AI (Google Gemini Vision API & Heuristic Engine) kiểm định tuân thủ quy chuẩn quảng cáo MAS Singapore và Brand Guidelines theo đặc tả INS3281.
 * Version: 2.5.0
 * Author: Brand Compliance AI Team (Members 1, 2, 3)
 * Author URI: https://example.com
 * License: GPL-2.0+
 * Text Domain: brand-compliance-ai
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Nạp Engine AI (Member 3)
require_once __DIR__ . '/ai-compliance-poc.php';

// 2. Nạp Backend lưu trữ Database CPT check_result và chuẩn bị dữ liệu (Member 2)
require_once __DIR__ . '/backend.php';

// 3. Đăng ký Shortcode hiển thị giao diện kết quả chấm điểm [ai_scoring_result]
add_shortcode( 'ai_compliance_result', function( $atts ) {
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
        31
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
});

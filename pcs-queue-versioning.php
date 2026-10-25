<?php
/**
 * Plugin Name: Background Queue & Versioning
 * Description: Quản lý vòng đời Version (n+1) và xử lý Background Job gọi AI chấm điểm.
 * Version: 1.0.0
 * Author: hoàng anh
 */

if (!defined('ABSPATH')) exit;

// 1. TẠO VERSION MỚI VÀ KÍCH HOẠT QUEUE (CHUYỂN SANG SCORING)
function pcs_process_new_upload( $asset_id, $file_url, $maker_id ) { 
    // Bước 1: Tạo bản ghi Asset Version mới trên Database
    $version_post_id = wp_insert_post([
        'post_type'   => 'asset_version',
        'post_status' => 'publish',
        'post_title'  => 'New Version - Asset #' . $asset_id,
        'post_author' => $maker_id,
    ]);
    if ( is_wp_error( $version_post_id ) || !$version_post_id ) {
        return false;
    }

    // Bước 2: Cập nhật Meta Fields cho Version 
    update_post_meta( $version_post_id, '_asset_id', $asset_id );
    update_post_meta( $version_post_id, '_file_url', $file_url );

    // Bước 3: Chuyển vòng đời quy trình draft -> scoring
    update_post_meta( $version_post_id, '_status', 'draft' );
    update_post_meta( $version_post_id, '_status', 'scoring' );

    // Bước 4: ĐẨY TASK VÀO BACKGROUND JOB QUEUE bằng Action Scheduler
    if ( function_exists( 'as_enqueue_async_action' ) ) {
        as_enqueue_async_action( 'pcs_background_ai_scoring_job', array(
            'version_id'     => $version_post_id,
            'asset_id'       => $asset_id
        ));
    }

    return $version_post_id;
}

// 2. BACKGROUND WORKER: XỬ LÝ GỌI AI NGẦM
// Trạng thái: scoring -> in_review
add_action( 'pcs_background_ai_scoring_job', 'pcs_execute_ai_scoring', 10, 2 );

function pcs_execute_ai_scoring( $version_id, $asset_id ) {
    // Lấy URL ảnh từ Version để chuẩn bị gửi cho AI
    $file_url = get_post_meta($version_id, '_file_url', true);
    $criteria = ['foreign_logo', 'guideline', 'mas_rule'];
    
    // THÔNG TIN MODEL VÀ PROMPT 
    $model_version = 'gemini-1.5-flash'; 
    $prompt_version = 'v1.2';            

    // Yêu cầu file hàm gọi API (đảm bảo nạp AI Engine chuẩn v2.5.0)
    if ( ! function_exists('pcs_call_gemini_api') ) {
        if ( file_exists( plugin_dir_path( __FILE__ ) . 'brand-compliance-ai/ai-compliance-poc.php' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'brand-compliance-ai/ai-compliance-poc.php';
        } elseif ( file_exists( plugin_dir_path( __FILE__ ) . 'ai-compliance-poc.php' ) ) {
            require_once plugin_dir_path( __FILE__ ) . 'ai-compliance-poc.php';
        }
    }

    foreach ($criteria as $criterion) {      
        
        // Gọi hàm gọi Gemini API
        $ai_response = pcs_call_gemini_api($file_url, $criterion, $prompt_version); 
        $ai_data = json_decode($ai_response, true); 
        
        // Trích xuất dữ liệu trả về từ JSON (có fallback dự phòng nếu lỗi)
        $real_ai_score = isset($ai_data['score']) ? (int) $ai_data['score'] : 0;
        $quote = isset($ai_data['source_quote']) ? $ai_data['source_quote'] : '';    
        
        $raw_json = $ai_response;  // Lưu nguyên JSON gốc để tracking
        
        // Tạo bản ghi Check Result ĐỘC LẬP cho từng tiêu chí
        $result_id = wp_insert_post([
            'post_type'   => 'check_result',
            'post_status' => 'publish',
            'post_title'  => 'AI Score: ' . $criterion . ' (Version #'.$version_id.')',
            'post_author' => 0, // 0 = Hệ thống/AI chấm
        ]);

        $verd = ($real_ai_score >= 71) ? 'PASS' : (($real_ai_score >= 31) ? 'NEEDS LOOK' : 'FAIL');

        update_post_meta( $result_id, '_version_id', $version_id );
        update_post_meta( $result_id, '_criterion', $criterion );
        update_post_meta( $result_id, '_checker_type', 'ai' );
        update_post_meta( $result_id, '_is_override', '0' );
        update_post_meta( $result_id, '_prompt_version', $prompt_version );
        update_post_meta( $result_id, '_model_version', $model_version );
        update_post_meta( $result_id, '_raw_json', wp_slash($raw_json) );
        update_post_meta( $result_id, '_verdict', $verd );
        update_post_meta( $result_id, '_ai_score', $real_ai_score );
        update_post_meta( $result_id, '_ai_verdict', $verd );
        
        // Lưu quote TRƯỚC score để kích hoạt logic chống ảo giác của Hook pcs_recompute_verdict
        update_post_meta( $result_id, '_source_quote', $quote );
        update_post_meta( $result_id, '_score', $real_ai_score );

        // Lưu thông tin chi tiết phục vụ hiển thị Tiếng Anh và trích dẫn số trang PDF
        if (!empty($ai_data['finding'])) {
            update_post_meta( $result_id, '_finding', sanitize_text_field($ai_data['finding']) );
        }
        if (!empty($ai_data['analysis'])) {
            update_post_meta( $result_id, '_analysis', sanitize_text_field($ai_data['analysis']) );
        }
        if (!empty($ai_data['reason'])) {
            update_post_meta( $result_id, '_reason', sanitize_text_field($ai_data['reason']) );
        }

        $reg_ref = $ai_data['regulatory_reference'] ?? [];
        if (!empty($reg_ref['document'])) {
            update_post_meta( $result_id, '_regulatory_doc', sanitize_text_field($reg_ref['document']) );
        }
        if (!empty($reg_ref['section'])) {
            update_post_meta( $result_id, '_regulatory_section', sanitize_text_field($reg_ref['section']) );
        }
        if (!empty($reg_ref['page_number'])) {
            update_post_meta( $result_id, '_page_number', intval($reg_ref['page_number']) );
        }
        if (!empty($reg_ref['page_context'])) {
            update_post_meta( $result_id, '_page_context', sanitize_textarea_field($reg_ref['page_context']) );
        }
        if (!empty($reg_ref['rule_code'])) {
            update_post_meta( $result_id, '_rule', sanitize_text_field($reg_ref['rule_code']) );
        }
    }

    // CHUYỂN VÒNG ĐỜI SANG 'in_review' ĐỂ CHECKER VÀO DUYỆT
    update_post_meta( $version_id, '_status', 'in_review' );
}
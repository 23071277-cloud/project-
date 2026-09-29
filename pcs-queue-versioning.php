<?php
if (!defined('ABSPATH')) exit;

// 1. TẠO VERSION MỚI (n+1) VÀ KÍCH HOẠT QUEUE (CHUYỂN SANG SCORING)
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

    // Bước 3: Chuyển vòng đời đúng quy trình (draft -> scoring)
    update_post_meta( $version_post_id, '_status', 'draft' );
    update_post_meta( $version_post_id, '_status', 'scoring' );

    // Bước 4: ĐẨY TASK VÀO BACKGROUND JOB QUEUE (Action Scheduler)
    if ( function_exists( 'as_enqueue_async_action' ) ) {
        as_enqueue_async_action( 'pcs_background_ai_scoring_job', array(
            'version_id'     => $version_post_id,
            'asset_id'       => $asset_id
        ));
    }

    return $version_post_id;
}


// 2. BACKGROUND WORKER: XỬ LÝ GỌI AI NGẦM KHÔNG NGHẼN SERVER
// Trạng thái vòng đời: scoring -> in_review
add_action( 'pcs_background_ai_scoring_job', 'pcs_execute_ai_scoring', 10, 2 );

function pcs_execute_ai_scoring( $version_id, $asset_id ) {
    // Lấy URL ảnh từ Version để chuẩn bị gửi cho AI
    $file_url = get_post_meta($version_id, '_file_url', true);

    // 3 Tiêu chí bắt buộc phải có theo Rule R7
    $criteria = ['foreign_logo', 'guideline', 'mas_rule'];
    
    // THÔNG TIN MODEL VÀ PROMPT 
    $model_version = 'mock-gpt-4o-mini'; // điền thật vào
    $prompt_version = 'v1.2';            // điền thật vào

    foreach ($criteria as $criterion) {      
        $ai_response = pcs_call_openai_api($file_url, $criterion, $prompt_version); // check tên hàm gọi API
        $ai_data = json_decode($ai_response, true); 
        
        // -- ĐOẠN NÀY MOCK THÔI --
        $mock_ai_score = rand(20, 95); 
        $mock_quote = ($mock_ai_score <= 30) ? 'Trích dẫn giả lập Điều X Guidelines cho ' . $criterion : '';
        $mock_ai_json = json_encode([
            'reason' => 'Đây là dữ liệu test tự động từ Background Worker cho tiêu chí: ' . $criterion,
            'quotes' => $mock_quote,
            'score'  => $mock_ai_score
        ]);
        
        // Gán dữ liệu vào biến chuẩn bị lưu DB
        $real_ai_score = $mock_ai_score; // sửa thành $ai_data['score'])
        $quote         = $mock_quote;    // sửa thành $ai_data['quotes'])
        $raw_json      = $mock_ai_json;  // sửa thành biến $ai_response nguyên gốc từ LLM
        
        // TỪ ĐOẠN NÀY KO SỬA GÌ NỮA NHƯNG CỨ XEM LẠI CHO CHẮC
        
        // Tạo bản ghi Check Result ĐỘC LẬP cho từng tiêu chí
        $result_id = wp_insert_post([
            'post_type'   => 'check_result',
            'post_status' => 'publish',
            'post_title'  => 'AI Score: ' . $criterion . ' (Version #'.$version_id.')',
            'post_author' => 0, // 0 = Hệ thống/AI chấm
        ]);

        update_post_meta( $result_id, '_version_id', $version_id );
        update_post_meta( $result_id, '_criterion', $criterion );
        update_post_meta( $result_id, '_checker_type', 'ai' );
        update_post_meta( $result_id, '_is_override', '0' );
        update_post_meta( $result_id, '_prompt_version', $prompt_version );
        update_post_meta( $result_id, '_model_version', $model_version );
        update_post_meta( $result_id, '_raw_json', wp_slash($raw_json) );
        
        // Phải lưu quote TRƯỚC score để kích hoạt logic chống ảo giác của Hook pcs_recompute_verdict
        update_post_meta( $result_id, '_source_quote', $quote );
        update_post_meta( $result_id, '_score', $real_ai_score );
    }

    // CHUYỂN VÒNG ĐỜI SANG 'in_review' ĐỂ CHECKER VÀO DUYỆT
    update_post_meta( $version_id, '_status', 'in_review' );
}
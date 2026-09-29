<?php
if (!defined('ABSPATH')) exit;

// 1. TẠO VERSION MỚI (n+1) VÀ KÍCH HOẠT QUEUE CHUYỂN SANG SCORING
function pcs_process_new_upload( $asset_id, $file_url, $maker_id ) { 
    // Bước 1: Tạo bản ghi Asset Version, tự động tính max + 1
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
    // Tự sinh _version_number, set _uploaded_by, cập nhật _current_version_id cho asset
    update_post_meta( $version_post_id, '_asset_id', $asset_id );
    update_post_meta( $version_post_id, '_file_url', $file_url );

    // Bước 3: 
    // Chuyển vòng đời theo quy trình draft -> scoring
    update_post_meta( $version_post_id, '_status', 'draft' );
    // Đổi sang scoring để bắt đầu AI chấm và kích hoạt Audit Log
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
// Trạng thái vòng đời: scoring -> in_review, được gọi tự động bởi Action Scheduler (chạy ẩn phía sau).
add_action( 'pcs_background_ai_scoring_job', 'pcs_execute_ai_scoring', 10, 2 );

function pcs_execute_ai_scoring( $version_id, $asset_id ) {
    
    // Giả lập hệ thống AI mất 5 giây để đọc và phân tích ảnh
    sleep(5); 
    // 3 Tiêu chí bắt buộc phải có theo Rule R7 để được vào in_review
    $criteria = ['foreign_logo', 'guideline', 'mas_rule'];
    $model_version = 'mock-gpt-4o-mini';
    $prompt_version = 'v1.2';

    foreach ($criteria as $criterion) {
        $mock_ai_score = rand(20, 95); 
        // Logic ảo giác: Nếu score <= 30 (fail) thì AI phải link ra được trích dẫn
        $quote = ($mock_ai_score <= 30) ? 'Trích dẫn giả lập Điều X Guidelines cho ' . $criterion : '';
        
        $mock_ai_json  = json_encode([
            'reason' => 'Đây là dữ liệu test tự động từ Background Worker cho tiêu chí: ' . $criterion,
            'quotes' => $quote
        ]);

        // Tạo bản ghi Check Result ĐỘC LẬP cho từng tiêu chí
        $result_id = wp_insert_post([
            'post_type'   => 'check_result',
            'post_status' => 'publish',
            'post_title'  => 'AI Score: ' . $criterion . ' (Version #'.$version_id.')',
            'post_author' => 0, // 0 = Hệ thống/AI chấm
        ]);

        // Lưu thông tin Meta (Bắt buộc phải có đủ meta theo Rule R11)
        update_post_meta( $result_id, '_version_id', $version_id );
        update_post_meta( $result_id, '_criterion', $criterion );
        update_post_meta( $result_id, '_checker_type', 'ai' );
        update_post_meta( $result_id, '_is_override', '0' );
        update_post_meta( $result_id, '_prompt_version', $prompt_version );
        update_post_meta( $result_id, '_model_version', $model_version );
        update_post_meta( $result_id, '_raw_json', wp_slash($mock_ai_json) );
        
        // Cực kỳ quan trọng: Phải lưu quote TRƯỚC score.
        // Khi lưu score, Hook pcs_recompute_verdict của Core sẽ chạy để tính Verdict (pass/needs_look/fail)
        update_post_meta( $result_id, '_source_quote', $quote );
        update_post_meta( $result_id, '_score', $mock_ai_score );
    }

    // CHUYỂN VÒNG ĐỜI SANG 'in_review' ĐỂ CHECKER VÀO DUYỆT
    update_post_meta( $version_id, '_status', 'in_review' );
}
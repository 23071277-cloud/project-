<?php
if (!defined('ABSPATH')) exit;

// 1. HÀM BỔ TRỢ: TÌM SỐ VERSION LỚN NHẤT HIỆN TẠI CỦA ASSET
function pcs_get_latest_version_no( $asset_id ) {
    $args = array(
        'post_type'      => 'asset_version',
        'posts_per_page' => 1,
        'meta_query'     => array(
            array(
                'key'     => '_asset_id',
                'value'   => $asset_id,
                'compare' => '='
            ),
        ),
        'meta_key'       => '_version_number',
        'orderby'        => 'meta_value_num',
        'order'          => 'DESC',
        'fields'         => 'ids' // Chỉ lấy ID thay vì toàn bộ object, tiết kiệm bộ nhớ tối ưu hiệu năng
    );

    $query = new WP_Query( $args );
    if ( $query->have_posts() ) {
        $latest_post_id = $query->posts[0];
        return (int) get_post_meta( $latest_post_id, '_version_number', true );
    }
    
    return 0; // Lần đầu upload
}

// 2. CẤU TRÚC QUẢN TRỊ VÒNG ĐỜI (VERSIONING n+1) & KÍCH HOẠT QUEUE
// Trạng thái: draft/rejected -> scoring
// Người làm Task 4 sẽ gọi hàm này sau khi file vật lý được tải lên xong.

function pcs_process_new_upload( $asset_id, $file_url, $maker_id ) { 
    // Bước 1: Tính toán version_no (n+1)
    $new_version_no = pcs_get_latest_version_no( $asset_id ) + 1;

    // Bước 2: Tạo bản ghi Asset Version mới trên Database
    $version_post_id = wp_insert_post([
        'post_type'   => 'asset_version',
        'post_status' => 'publish',
        'post_title'  => 'Version ' . $new_version_no . ' - Asset #' . $asset_id,
        'post_author' => $maker_id,
    ]);
    if ( is_wp_error( $version_post_id ) || !$version_post_id ) {
        return false;
    }

    // Bước 3: Cập nhật Meta Fields cho Version 
    update_post_meta( $version_post_id, '_asset_id', $asset_id );
    update_post_meta( $version_post_id, '_version_number', $new_version_no );
    update_post_meta( $version_post_id, '_file_url', $file_url );

    // Bước 4: Đổi trạng thái Asset cha thành 'scoring' 
    // Logic vòng đời: Chuyển thẳng sang trạng thái chờ chấm điểm
    update_post_meta( $asset_id, '_overall_status', 'scoring' );

    // Bước 5: Ghi Log bằng hàm pcs_log có sẵn trong hệ thống
    pcs_log( 'upload_version', $asset_id, 'Maker (ID: '.$maker_id.') tải lên version v' . $new_version_no );

    // Bước 6: ĐẨY TASK VÀO BACKGROUND JOB QUEUE (Action Scheduler)
    if ( function_exists( 'as_enqueue_async_action' ) ) {
        as_enqueue_async_action( 'pcs_background_ai_scoring_job', array(
            'version_id'     => $version_post_id,
            'asset_id'       => $asset_id,
            'version_number' => $new_version_no
        ));
    }

    return $version_post_id;
}

// 3. BACKGROUND WORKER: XỬ LÝ GỌI AI NGẦM KHÔNG NGHẼN SERVER
// Trạng thái: scoring -> in_review
// Hàm này được gọi tự động bởi Action Scheduler (chạy ẩn phía sau).

add_action( 'pcs_background_ai_scoring_job', 'pcs_execute_ai_scoring', 10, 3 );
function pcs_execute_ai_scoring( $version_id, $asset_id, $version_number ) {
    
    // Giả lập hệ thống AI mất 5 giây để đọc và phân tích ảnh
    sleep(5); 
    
    // Mock Data kết quả AI: Điểm random và nội dung giải thích 
    // Thành viên làm Task 3 sẽ thay phần này bằng API thật gọi OpenAI/Claude
    $mock_ai_score = rand(20, 95); 
    $mock_ai_json  = json_encode([
        'reason' => 'Đây là dữ liệu test tự động từ Background Worker chạy qua Action Scheduler.',
        'quotes' => 'Trích dẫn Điều 3 MAS Guidelines.'
    ]);

    // Tạo bản ghi Check Result để lưu kết quả chấm điểm của AI
    $result_id = wp_insert_post([
        'post_type'   => 'check_result',
        'post_status' => 'publish',
        'post_title'  => 'AI Score for Version ' . $version_number . ' (Asset #'.$asset_id.')',
        'post_author' => 0, // 0 = Hệ thống/AI chấm
    ]);

    // Lưu thông tin Meta của Check Result
    update_post_meta( $result_id, '_version_id', $version_id );
    update_post_meta( $result_id, '_score', $mock_ai_score );
    update_post_meta( $result_id, '_raw_json', wp_slash($mock_ai_json) );

    // CHUYỂN VÒNG ĐỜI SANG 'in_review' ĐỂ CHECKER VÀO DUYỆT
    // Logic vòng đời: Sau khi AI chấm xong, đẩy sang để con người thẩm định
    update_post_meta( $asset_id, '_overall_status', 'in_review' );

    // Ghi Log hệ thống xác nhận AI chấm xong
    pcs_log( 'ai_scored', $asset_id, 'AI chấm điểm xong v' . $version_number . ' (Điểm: '.$mock_ai_score.'). Đã chuyển trạng thái sang in_review.' );
}
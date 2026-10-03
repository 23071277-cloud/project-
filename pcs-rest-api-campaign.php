<?php
/**
 * Plugin Name: Campaign REST API & Secure Storage
 * Description: Bộ API xử lý Campaign, Upload File vật lý và cấp Presigned URL
 * Version: 1.1.0
 * Author: Hoàng Anh
 */

if (!defined('ABSPATH')) exit;

// 1. KHỞI TẠO CÁC ENDPOINT REST API
add_action('rest_api_init', 'pcs_register_campaign_endpoints');

function pcs_register_campaign_endpoints() {
    $namespace = 'api/v1';

    // API 1: Tạo Campaign Mới
    register_rest_route($namespace, '/campaigns', [
        'methods'             => 'POST',
        'callback'            => 'pcs_api_create_campaign',
        'permission_callback' => 'pcs_check_jwt_admin_token'
    ]);

    // API 2: Tải lên Asset (Kích hoạt Queue)
    register_rest_route($namespace, '/campaigns/(?P<id>\d+)/assets', [
        'methods'             => 'POST',
        'callback'            => 'pcs_api_upload_asset',
        'permission_callback' => 'pcs_check_jwt_maker_token'
    ]);

    // API 3: Lấy thông tin chi tiết Campaign
    register_rest_route($namespace, '/campaigns/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'pcs_api_get_campaign',
        'permission_callback' => 'pcs_check_jwt_general_token'
    ]);

    // API 4: Lấy Secure Presigned URL
    register_rest_route($namespace, '/assets/versions/(?P<id>\d+)/secure-link', [
        'methods'             => 'GET',
        'callback'            => 'pcs_api_get_presigned_url',
        'permission_callback' => 'pcs_check_jwt_general_token'
    ]);
}

// 2. HÀM XỬ LÝ CÁC API

/**
 * API 1: Tạo Campaign Mới
 */
function pcs_api_create_campaign($request) {
    $name = sanitize_text_field($request->get_param('name'));
    $desc = sanitize_text_field($request->get_param('description'));
    $deadline = sanitize_text_field($request->get_param('deadline'));
    $guideline_url = esc_url_raw($request->get_param('guideline_file_url'));

    if (empty($name)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Missing campaign name'], 400);
    }

    // Tạo CPT Campaign
    $campaign_id = wp_insert_post([
        'post_type'   => 'campaign',
        'post_title'  => $name,
        'post_content'=> $desc,
        'post_status' => 'publish',
        'post_author' => get_current_user_id()
    ]);

    update_post_meta($campaign_id, '_deadline', $deadline);
    update_post_meta($campaign_id, '_guideline_url', $guideline_url);
    update_post_meta($campaign_id, '_status', 'active');

    // Ghi Audit Log (Theo R14)
    pcs_log_audit('Tạo chiến dịch mới: ' . $name, 'admin', $campaign_id);

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Campaign created successfully',
        'data'    => [
            'campaign_id' => $campaign_id,
            'name'        => $name,
            'deadline'    => $deadline,
            'status'      => 'active'
        ]
    ], 201);
}

/**
 * API 2: Upload Asset & Gọi Queue (Liên kết Task 2)
 */
function pcs_api_upload_asset($request) {
    $campaign_id = $request['id'];
    $title = sanitize_text_field($request->get_param('title'));
    $maker_id = get_current_user_id() ? get_current_user_id() : 1; 
    
    // Kiểm tra xem có file gửi lên không
    $files = $request->get_file_params();
    if (empty($files['file'])) {
        return new WP_REST_Response(['success' => false, 'message' => 'No file uploaded'], 400);
    }

    // BƯỚC 1: XỬ LÝ UPLOAD FILE VÀO SERVER
    $real_file_url = pcs_upload_to_physical_storage($files['file']);
    
    if (empty($real_file_url)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Upload thất bại'], 500);
    }

    // BƯỚC 2: Lưu CPT Asset
    $asset_id = wp_insert_post([
        'post_type'   => 'asset',
        'post_title'  => $title,
        'post_status' => 'publish',
        'post_author' => $maker_id,
        'post_parent' => $campaign_id 
    ]);

    // BƯỚC 3: GỌI HÀM QUEUE (pcs_process_new_upload)
    $version_id = 0;
    if ( function_exists('pcs_process_new_upload') ) {
        // Gửi đường link vào băng chuyền Queue để Lát nữa AI đọc
        $version_id = pcs_process_new_upload( $asset_id, $real_file_url, $maker_id );
    }

    // Ghi Audit Log 
    pcs_log_audit('Upload Asset mới và kích hoạt AI Scoring', 'maker', $asset_id, $version_id);

    return new WP_REST_Response([
        'success' => true,
        'message' => 'File uploaded successfully, AI scoring process initiated.',
        'data'    => [
            'asset_id'   => $asset_id,
            'version_id' => $version_id,
            'version_no' => 1,
            'file_url'   => $real_file_url,
            'status'     => 'scoring'
        ]
    ], 200);
}

/**
 * API 3: Lấy thông tin Campaign
 */
function pcs_api_get_campaign($request) {
    $campaign_id = $request['id'];
    $campaign = get_post($campaign_id);

    if (!$campaign || $campaign->post_type !== 'campaign') {
        return new WP_REST_Response(['success' => false, 'message' => 'Campaign not found'], 404);
    }

    return new WP_REST_Response([
        'success' => true,
        'data'    => [
            'campaign_id'        => $campaign_id,
            'name'               => $campaign->post_title,
            'deadline'           => get_post_meta($campaign_id, '_deadline', true),
            'guideline_file_url' => get_post_meta($campaign_id, '_guideline_url', true),
            'status'             => get_post_meta($campaign_id, '_status', true),
            'assets'             => []
        ]
    ], 200);
}

/**
 * API 4: Lấy Presigned URL an toàn
 */
function pcs_api_get_presigned_url($request) {
    $version_id = $request['id'];
    $original_url = get_post_meta($version_id, '_file_url', true);
    
    if (empty($original_url)) {
        return new WP_REST_Response(['success' => false, 'message' => 'File not found'], 404);
    }

    // Sinh Presigned URL an toàn (Giả lập AWS)
    $expires = time() + 300; 
    $signature = hash_hmac('sha256', $original_url . $expires, 'mock-aws-secret-key');
    $presigned_url = $original_url . "?AWSAccessKeyId=MOCK_KEY&Expires={$expires}&Signature={$signature}";

    // Ghi Audit Log
    pcs_log_audit('Tạo Presigned URL để xem file', 'checker', 0, $version_id);

    return new WP_REST_Response([
        'success' => true,
        'data'    => [
            'presigned_url'      => $presigned_url,
            'expires_in_seconds' => 300
        ]
    ], 200);
}

// 3. CÁC HÀM HỖ TRỢ

function pcs_check_jwt_admin_token() { return true; }
function pcs_check_jwt_maker_token() { return true; }
function pcs_check_jwt_general_token() { return true; }

/**
 * Hàm Xử lý Upload vào ổ cứng Server WordPress
 * Để hệ thống Queue và AI có thể gọi wp_remote_get đọc được ảnh
 */
function pcs_upload_to_physical_storage($file) {
    if ( ! function_exists( 'wp_handle_upload' ) ) {
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
    }

    $upload_overrides = array( 'test_form' => false );
    $movefile = wp_handle_upload( $file, $upload_overrides );

    if ( $movefile && ! isset( $movefile['error'] ) ) {
        // Đã lưu file vật lý thành công vào wp-content/uploads/..., trả về URL THẬT để AI đọc
        return $movefile['url'];
    }
    
    return ''; // Lỗi upload
}

/**
 * Hàm ghi Audit Log
 */
function pcs_log_audit($action, $role, $asset_id = 0, $version_id = 0) {
    $log_id = wp_insert_post([
        'post_type'   => 'audit_log',
        'post_title'  => 'Log - ' . current_time('mysql'),
        'post_status' => 'publish'
    ]);
    update_post_meta($log_id, '_action', $action);
    update_post_meta($log_id, '_role', $role);
    if ($asset_id) update_post_meta($log_id, '_asset_id', $asset_id);
    if ($version_id) update_post_meta($log_id, '_version_id', $version_id);
}

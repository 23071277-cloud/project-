<?php
/**
 * Plugin Name: Campaign REST API & Secure Storage
 * Description: REST API cho Campaign, upload asset/version (n+1) và lưu file an toàn (thư mục private + link ký số có hạn).
 * Version: 2.0.0
 * Author: Hoàng Long (dựa trên bản test flow của Hoàng Anh)
 *
 * Phụ thuộc / Depends on:
 *  - Project Core (Thúy): CPT, role, pcs_campaign_role(), pcs_log(), các bộ chặn meta.
 *  - Background Queue & Versioning (Hoàng Anh): pcs_process_new_upload().
 */

if (!defined('ABSPATH')) exit;

// Giới hạn upload (MB) và loại file được nhận (đề chỉ yêu cầu ảnh + PDF)
// Upload size limit (MB) and accepted types (the brief only requires images + PDF)
if (!defined('PCS_MAX_UPLOAD_MB')) define('PCS_MAX_UPLOAD_MB', 20);
if (!defined('PCS_LINK_TTL'))      define('PCS_LINK_TTL', 300); // thời gian sống của link ký số (giây) / signed link lifetime (s)

function pcs_allowed_mimes() {
    return [
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'webp'         => 'image/webp',
        'pdf'          => 'application/pdf',
    ];
}


// =========================================================================
// 1. KHAI BÁO ENDPOINT / ROUTES  (namespace: /wp-json/api/v1)
// =========================================================================
add_action('rest_api_init', 'pcs_register_campaign_endpoints');

function pcs_register_campaign_endpoints() {
    $ns = 'api/v1';

    // Danh sách campaign mà user thuộc về (Admin thấy tất cả)
    register_rest_route($ns, '/campaigns', [
        [
            'methods'             => 'GET',
            'callback'            => 'pcs_api_list_campaigns',
            'permission_callback' => 'pcs_perm_logged_in',
        ],
        // API 1: Tạo campaign (Admin) - có thể kèm file guideline
        [
            'methods'             => 'POST',
            'callback'            => 'pcs_api_create_campaign',
            'permission_callback' => 'pcs_perm_admin_only',
        ],
    ]);

    // API 3: Chi tiết campaign + danh sách asset + tiến độ
    register_rest_route($ns, '/campaigns/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'pcs_api_get_campaign',
        'permission_callback' => 'pcs_perm_campaign_member',
    ]);

    // Gắn / thay file guideline cho campaign (Admin)
    register_rest_route($ns, '/campaigns/(?P<id>\d+)/guideline', [
        'methods'             => 'POST',
        'callback'            => 'pcs_api_upload_guideline',
        'permission_callback' => 'pcs_perm_admin_only',
    ]);

    // API 2: Maker tạo asset mới + version v1
    register_rest_route($ns, '/campaigns/(?P<id>\d+)/assets', [
        'methods'             => 'POST',
        'callback'            => 'pcs_api_upload_asset',
        'permission_callback' => 'pcs_perm_campaign_maker',
    ]);

    // Maker upload version mới (n+1) cho asset đã có, ví dụ sau khi bị reject
    register_rest_route($ns, '/assets/(?P<id>\d+)/versions', [
        'methods'             => 'POST',
        'callback'            => 'pcs_api_upload_version',
        'permission_callback' => 'pcs_perm_asset_maker',
    ]);

    // API 4: Lấy link ký số có hạn để xem file của version (giữ tên route cũ)
    register_rest_route($ns, '/assets/versions/(?P<id>\d+)/secure-link', [
        'methods'             => 'GET',
        'callback'            => 'pcs_api_get_presigned_url',
        'permission_callback' => 'pcs_perm_version_member',
    ]);

    // Trả file thật. Không cần đăng nhập vì chữ ký trong URL chính là giấy phép
    // (để dùng được trong <img src> / <iframe>), nhưng chữ ký được kiểm tra trong permission_callback.
    // Streams the actual file. The URL signature is the credential (works in <img>/<iframe>),
    // and it is verified in the permission_callback.
    register_rest_route($ns, '/files/(?P<kind>version|guideline)/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'pcs_api_stream_file',
        'permission_callback' => 'pcs_perm_signed_link',
    ]);
}


// =========================================================================
// 2. HÀM XỬ LÝ API / HANDLERS
// =========================================================================

/**
 * GET /campaigns - campaign mà user đang đăng nhập thuộc về
 */
function pcs_api_list_campaigns($request) {
    $uid = get_current_user_id();
    $ids = get_posts([
        'post_type'   => 'campaign',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields'      => 'ids',
        'orderby'     => 'date',
        'order'       => 'DESC',
    ]);
    $out = [];
    foreach ($ids as $cid) {
        $role = pcs_campaign_role($cid, $uid);
        if ($role === '') continue; // không thuộc campaign thì không thấy / not a member: hidden
        $c = pcs_campaign_summary($cid);
        $c['my_role'] = $role;
        $out[] = $c;
    }
    return new WP_REST_Response(['success' => true, 'data' => $out], 200);
}

/**
 * API 1: POST /campaigns - tạo campaign (Admin)
 * Body (form-data hoặc JSON): name*, description, deadline* (ISO 8601 hoặc Y-m-d\TH:i),
 * owner_id, maker_ids[], checker_ids[], guideline (file PDF, chỉ khi gửi form-data)
 */
function pcs_api_create_campaign($request) {
    $name = sanitize_text_field((string) $request->get_param('name'));
    $desc = sanitize_textarea_field((string) $request->get_param('description'));
    if ($name === '') {
        return pcs_api_error('missing_name', 'Thiếu tên campaign (name).', 400);
    }

    $deadline = pcs_normalize_deadline($request->get_param('deadline'));
    if (is_wp_error($deadline)) return $deadline;

    // Thành viên phải đúng vai trò tài khoản (R17) / members must hold the matching account role
    $makers   = pcs_parse_user_ids($request->get_param('maker_ids'), 'maker');
    $checkers = pcs_parse_user_ids($request->get_param('checker_ids'), 'checker');
    if (is_wp_error($makers))   return $makers;
    if (is_wp_error($checkers)) return $checkers;
    if (array_intersect($makers, $checkers)) {
        return pcs_api_error('member_conflict', 'Một người không thể vừa là Maker vừa là Checker trong cùng campaign.', 400);
    }

    $owner_id = absint($request->get_param('owner_id')) ?: get_current_user_id();
    if (!get_userdata($owner_id)) {
        return pcs_api_error('bad_owner', 'owner_id không tồn tại.', 400);
    }

    // Kiểm tra file guideline TRƯỚC khi tạo campaign để không sinh campaign dở dang
    // Validate the guideline file BEFORE creating the campaign, so no half-built campaign is left behind
    $files     = $request->get_file_params();
    $guideline = !empty($files['guideline']) ? $files['guideline'] : null;
    if ($guideline) {
        $check = pcs_validate_upload($guideline, ['pdf' => 'application/pdf']);
        if (is_wp_error($check)) return $check;
    }

    $campaign_id = wp_insert_post([
        'post_type'    => 'campaign',
        'post_title'   => $name,
        'post_content' => $desc,
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
    ], true);
    if (is_wp_error($campaign_id)) {
        return pcs_api_error('create_failed', 'Không tạo được campaign: ' . $campaign_id->get_error_message(), 500);
    }

    // Dùng đúng tên meta của core (Thúy) / use the exact meta keys defined by core
    update_post_meta($campaign_id, '_campaign_deadline', $deadline);
    update_post_meta($campaign_id, '_owner_id', $owner_id);
    update_post_meta($campaign_id, '_maker_ids', implode(',', $makers));
    update_post_meta($campaign_id, '_checker_ids', implode(',', $checkers));

    if ($guideline) {
        $att = pcs_store_guideline($campaign_id, $guideline);
        if (is_wp_error($att)) {
            wp_delete_post($campaign_id, true); // hoàn tác / roll back
            return $att;
        }
    }

    pcs_log('campaign_created', 0, 0, '', 'active', 'Campaign #' . $campaign_id . ': ' . $name);

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Campaign created successfully',
        'data'    => pcs_campaign_summary($campaign_id),
    ], 201);
}

/**
 * POST /campaigns/{id}/guideline - gắn hoặc thay file guideline (Admin), field form-data: guideline
 */
function pcs_api_upload_guideline($request) {
    $campaign_id = absint($request['id']);
    if (get_post_type($campaign_id) !== 'campaign') {
        return pcs_api_error('not_found', 'Campaign không tồn tại.', 404);
    }
    $files = $request->get_file_params();
    if (empty($files['guideline'])) {
        return pcs_api_error('no_file', 'Thiếu file guideline (field: guideline).', 400);
    }
    $check = pcs_validate_upload($files['guideline'], ['pdf' => 'application/pdf']);
    if (is_wp_error($check)) return $check;

    $old = (int) get_post_meta($campaign_id, '_guideline_file_id', true);
    $att = pcs_store_guideline($campaign_id, $files['guideline']);
    if (is_wp_error($att)) return $att;

    // File cũ vẫn giữ lại (không xóa) để tra cứu lịch sử / the old file is kept for history
    pcs_log('guideline_updated', 0, 0, '', '', 'Campaign #' . $campaign_id . ': guideline #' . $old . ' -> #' . $att);

    return new WP_REST_Response(['success' => true, 'data' => pcs_campaign_summary($campaign_id)], 200);
}

/**
 * API 2: POST /campaigns/{id}/assets - Maker tạo asset mới + version v1, rồi đưa vào hàng đợi AI
 * form-data: file* (ảnh/PDF), title
 */
function pcs_api_upload_asset($request) {
    $campaign_id = absint($request['id']);
    $maker_id    = get_current_user_id(); // permission_callback đã bắt buộc đăng nhập / login already enforced
    $title       = sanitize_text_field((string) $request->get_param('title'));

    if (!function_exists('pcs_process_new_upload')) {
        return pcs_api_error('queue_missing', 'Plugin Background Queue & Versioning chưa được kích hoạt.', 500);
    }

    $files = $request->get_file_params();
    if (empty($files['file'])) {
        return pcs_api_error('no_file', 'Thiếu file (field: file).', 400);
    }
    $check = pcs_validate_upload($files['file']);
    if (is_wp_error($check)) return $check;

    $path = pcs_store_private_file($files['file']);
    if (is_wp_error($path)) return $path;

    $asset_id = wp_insert_post([
        'post_type'   => 'asset',
        'post_title'  => $title !== '' ? $title : sanitize_file_name($files['file']['name']),
        'post_status' => 'publish',
        'post_author' => $maker_id,
        'post_parent' => $campaign_id, // giữ để tương thích bản test của Hoàng Anh / kept for compatibility
    ], true);
    if (is_wp_error($asset_id)) {
        @unlink($path);
        return pcs_api_error('create_failed', 'Không tạo được asset.', 500);
    }

    // Core (Thúy) tìm campaign của asset qua meta _campaign_id, không qua post_parent
    // Core looks up an asset's campaign via _campaign_id meta, not post_parent
    update_post_meta($asset_id, '_campaign_id', $campaign_id);

    $version_id = pcs_create_version($asset_id, $path, $maker_id);
    if (is_wp_error($version_id)) {
        wp_delete_post($asset_id, true);
        return $version_id;
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => 'File uploaded successfully, AI scoring process initiated in background.',
        'data'    => pcs_version_payload($version_id),
    ], 201);
}

/**
 * POST /assets/{id}/versions - Maker upload version n+1 cho asset đã có (FR-01, R4, R5)
 * form-data: file*
 */
function pcs_api_upload_version($request) {
    $asset_id = absint($request['id']);
    $maker_id = get_current_user_id();

    if (!function_exists('pcs_process_new_upload')) {
        return pcs_api_error('queue_missing', 'Plugin Background Queue & Versioning chưa được kích hoạt.', 500);
    }

    $files = $request->get_file_params();
    if (empty($files['file'])) {
        return pcs_api_error('no_file', 'Thiếu file (field: file).', 400);
    }
    $check = pcs_validate_upload($files['file']);
    if (is_wp_error($check)) return $check;

    $path = pcs_store_private_file($files['file']);
    if (is_wp_error($path)) return $path;

    $version_id = pcs_create_version($asset_id, $path, $maker_id);
    if (is_wp_error($version_id)) return $version_id;

    return new WP_REST_Response([
        'success' => true,
        'message' => 'New version uploaded, AI scoring process initiated in background.',
        'data'    => pcs_version_payload($version_id),
    ], 201);
}

/**
 * API 3: GET /campaigns/{id} - chi tiết + asset + tiến độ
 * Maker chỉ thấy asset của chính mình; Checker/Admin thấy tất cả.
 * Makers see only their own assets; Checkers/Admins see all.
 */
function pcs_api_get_campaign($request) {
    $campaign_id = absint($request['id']);
    $uid         = get_current_user_id();
    $role        = pcs_campaign_role($campaign_id, $uid);

    $data = pcs_campaign_summary($campaign_id);
    $data['my_role'] = $role;

    $query = [
        'post_type'   => 'asset',
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields'      => 'ids',
        'orderby'     => 'date',
        'order'       => 'DESC',
        'meta_key'    => '_campaign_id',
        'meta_value'  => (string) $campaign_id,
    ];
    if ($role === 'maker') $query['author'] = $uid;

    $progress = array_fill_keys(pcs_valid_statuses(), 0);
    $assets   = [];
    foreach (get_posts($query) as $asset_id) {
        $vid    = (int) get_post_meta($asset_id, '_current_version_id', true);
        $status = $vid ? (get_post_meta($vid, '_status', true) ?: 'draft') : 'draft';
        if (isset($progress[$status])) $progress[$status]++;
        $assets[] = [
            'asset_id'           => $asset_id,
            'title'              => get_the_title($asset_id),
            'created_by'         => (int) get_post_field('post_author', $asset_id),
            'current_version_id' => $vid,
            'current_version_no' => $vid ? (int) get_post_meta($vid, '_version_number', true) : 0,
            'status'             => $status,
        ];
    }

    $data['assets']   = $assets;
    $data['progress'] = $progress;
    // Nhóm gọn theo slide đề bài: waiting / in review / released
    $data['progress_summary'] = [
        'waiting'   => $progress['draft'] + $progress['scoring'] + $progress['rejected'],
        'in_review' => $progress['in_review'] + $progress['approved'],
        'released'  => $progress['released'],
    ];

    return new WP_REST_Response(['success' => true, 'data' => $data], 200);
}

/**
 * API 4: GET /assets/versions/{id}/secure-link - link ký số có hạn PCS_LINK_TTL giây
 * Giữ nguyên khóa trả về cũ (presigned_url, expires_in_seconds) để frontend không phải sửa.
 * Keeps the old response keys so the frontend does not need to change.
 */
function pcs_api_get_presigned_url($request) {
    $version_id = absint($request['id']);
    if (pcs_version_file_path($version_id) === '') {
        return pcs_api_error('file_missing', 'Không tìm thấy file của version này.', 404);
    }
    pcs_log('file_link_issued', pcs_version_asset($version_id), $version_id, '', '', 'Cấp link xem file ' . PCS_LINK_TTL . 's');

    return new WP_REST_Response([
        'success' => true,
        'data'    => [
            'presigned_url'      => pcs_signed_file_url('version', $version_id, get_current_user_id()),
            'expires_in_seconds' => PCS_LINK_TTL,
        ],
    ], 200);
}

/**
 * GET /files/{kind}/{id}?uid=&exp=&sig= - trả nội dung file (đã qua kiểm chữ ký)
 */
function pcs_api_stream_file($request) {
    $kind = $request['kind'];
    $id   = absint($request['id']);
    $path = $kind === 'version' ? pcs_version_file_path($id) : pcs_guideline_file_path($id);
    if ($path === '') {
        return pcs_api_error('file_missing', 'Không tìm thấy file.', 404);
    }

    $type = wp_check_filetype($path, pcs_allowed_mimes());
    $mime = $type['type'] ?: 'application/octet-stream';

    // Xóa mọi output đệm để file không bị lẫn ký tự lạ / drop buffered output so the file is not corrupted
    while (ob_get_level()) ob_end_clean();
    nocache_headers();
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . basename($path) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}


// =========================================================================
// 3. KIỂM QUYỀN (server-side) / PERMISSION CHECKS
// WordPress gọi các hàm này TRƯỚC callback. Trả true = cho qua, trả WP_Error = chặn với mã HTTP trong 'status'.
// WordPress runs these BEFORE the callback. true = allow, WP_Error = reject with the HTTP code in 'status'.
// =========================================================================

// Lỗi chuẩn: 401 khi chưa đăng nhập, 403 khi đã đăng nhập nhưng không đủ quyền
// Standard error: 401 when not logged in, 403 when logged in but not allowed
function pcs_perm_error($message) {
    return new WP_Error('pcs_forbidden', $message, ['status' => rest_authorization_required_code()]);
}

// Cần plugin Project Core (pcs_campaign_role) để biết vai trò trong campaign
// Requires Project Core (pcs_campaign_role) to resolve per-campaign roles
function pcs_perm_core_ready() {
    if (function_exists('pcs_campaign_role')) return true;
    return new WP_Error('pcs_core_missing', 'Plugin Project Core chưa được kích hoạt.', ['status' => 500]);
}

function pcs_perm_logged_in($request) {
    if (!is_user_logged_in()) return pcs_perm_error('Bạn cần đăng nhập.');
    return pcs_perm_core_ready();
}

// Chỉ Admin (R1) / Admin only (R1)
function pcs_perm_admin_only($request) {
    if (!is_user_logged_in()) return pcs_perm_error('Bạn cần đăng nhập.');
    if (!current_user_can('administrator')) return pcs_perm_error('Chỉ Admin được thực hiện thao tác này.');
    return pcs_perm_core_ready();
}

// Maker của đúng campaign (hoặc Admin), campaign còn hạn (R2, R3, R4, R17)
// Maker of this campaign (or Admin), campaign not past its deadline
function pcs_perm_campaign_maker($request) {
    $ok = pcs_perm_logged_in($request);
    if ($ok !== true) return $ok;

    $campaign_id = absint($request['id']);
    if (get_post_type($campaign_id) !== 'campaign') {
        return new WP_Error('pcs_not_found', 'Campaign không tồn tại.', ['status' => 404]);
    }
    $role = pcs_campaign_role($campaign_id, get_current_user_id());
    if (!in_array($role, ['admin', 'maker'], true)) {
        return pcs_perm_error('Bạn không phải Maker của campaign này.');
    }
    if (pcs_campaign_is_closed($campaign_id)) {
        return pcs_perm_error('Campaign đã quá deadline, không nhận thêm file.');
    }
    return true;
}

// Upload version n+1: người tạo asset + Maker của campaign (pcs_can_add_version của core),
// campaign còn hạn, version hiện tại đang draft hoặc rejected (R4)
// New version: asset creator + campaign Maker (core's pcs_can_add_version), campaign open,
// current version is draft or rejected (R4)
function pcs_perm_asset_maker($request) {
    $ok = pcs_perm_logged_in($request);
    if ($ok !== true) return $ok;

    $asset_id = absint($request['id']);
    if (get_post_type($asset_id) !== 'asset') {
        return new WP_Error('pcs_not_found', 'Asset không tồn tại.', ['status' => 404]);
    }
    if (!pcs_can_add_version($asset_id, get_current_user_id())) {
        return pcs_perm_error('Chỉ người tạo asset (Maker của campaign) mới upload được version mới.');
    }
    if (pcs_campaign_is_closed(pcs_asset_campaign($asset_id))) {
        return pcs_perm_error('Campaign đã quá deadline, không nhận thêm file.');
    }
    $vid = (int) get_post_meta($asset_id, '_current_version_id', true);
    if ($vid) {
        $status = get_post_meta($vid, '_status', true) ?: 'draft';
        if (!in_array($status, ['draft', 'rejected'], true)) {
            // 409 Conflict: yêu cầu hợp lệ nhưng xung đột với trạng thái hiện tại
            return new WP_Error('pcs_conflict', 'Version hiện tại đang ở trạng thái "' . $status . '": chỉ upload version mới khi version trước là draft hoặc rejected.', ['status' => 409]);
        }
    }
    return true;
}

// Thành viên campaign (Maker/Checker) hoặc Admin / campaign member or Admin
function pcs_perm_campaign_member($request) {
    $ok = pcs_perm_logged_in($request);
    if ($ok !== true) return $ok;

    $campaign_id = absint($request['id']);
    if (get_post_type($campaign_id) !== 'campaign') {
        return new WP_Error('pcs_not_found', 'Campaign không tồn tại.', ['status' => 404]);
    }
    if (pcs_campaign_role($campaign_id, get_current_user_id()) === '') {
        return pcs_perm_error('Bạn không thuộc campaign này.');
    }
    return true;
}

// Thành viên của campaign chứa version đó / member of the version's campaign
function pcs_perm_version_member($request) {
    $ok = pcs_perm_logged_in($request);
    if ($ok !== true) return $ok;

    $version_id = absint($request['id']);
    if (get_post_type($version_id) !== 'asset_version') {
        return new WP_Error('pcs_not_found', 'Version không tồn tại.', ['status' => 404]);
    }
    if (!pcs_user_can_view_version($version_id, get_current_user_id())) {
        return pcs_perm_error('Bạn không có quyền xem file của version này.');
    }
    return true;
}

// Link ký số: chữ ký đúng, chưa hết hạn, và người được cấp link VẪN còn quyền
// Signed link: valid signature, not expired, and the user it was issued to STILL has access
function pcs_perm_signed_link($request) {
    $ready = pcs_perm_core_ready();
    if ($ready !== true) return $ready;

    $kind = (string) $request['kind'];
    $id   = absint($request['id']);
    $uid  = absint($request->get_param('uid'));
    $exp  = absint($request->get_param('exp'));
    $sig  = (string) $request->get_param('sig');

    $denied = new WP_Error('pcs_link_invalid', 'Link không hợp lệ hoặc đã hết hạn.', ['status' => 403]);
    if (!$uid || !$exp || $sig === '') return $denied;
    if ($exp < time() || $exp > time() + PCS_LINK_TTL) return $denied;
    // hash_equals so sánh thời gian cố định, chống đoán chữ ký theo thời gian phản hồi
    // hash_equals compares in constant time, so the signature cannot be guessed from response timing
    if (!hash_equals(pcs_link_signature($kind, $id, $uid, $exp), $sig)) return $denied;

    if ($kind === 'version') {
        if (get_post_type($id) !== 'asset_version' || !pcs_user_can_view_version($id, $uid)) return $denied;
    } else {
        if (get_post_type($id) !== 'campaign' || pcs_campaign_role($id, $uid) === '') return $denied;
    }
    return true;
}


// =========================================================================
// 4. LƯU FILE AN TOÀN / SECURE FILE STORAGE
// File asset và guideline nằm trong uploads/private/pcs-assets/:
//  - Apache (XAMPP): bị chặn bởi file .htaccess tự tạo ("Require all denied").
//  - Pantheon (nginx): thư mục wp-content/uploads/private không truy cập trực tiếp được từ web.
// Người dùng chỉ xem file qua link ký số; worker AI đọc file trực tiếp trên ổ đĩa.
// Asset and guideline files live in uploads/private/pcs-assets/: blocked by .htaccess on Apache,
// and Pantheon does not serve wp-content/uploads/private. Users only get files through signed links;
// the AI worker reads them straight from disk.
// =========================================================================

function pcs_private_base() {
    $up = wp_upload_dir(null, false);
    return trailingslashit($up['basedir']) . 'private/pcs-assets';
}

// Tạo thư mục và các file chặn truy cập nếu chưa có / create the folder and deny files if missing
function pcs_ensure_private_dir() {
    $base = pcs_private_base();
    if (!wp_mkdir_p($base)) {
        return pcs_api_error('storage_unavailable', 'Không tạo được thư mục lưu file.', 500);
    }
    $root = dirname($base); // uploads/private
    $deny = "# Brand Compliance: chặn truy cập trực tiếp / deny direct access\n"
          . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
          . "<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n";
    foreach ([$root, $base] as $dir) {
        if (!file_exists($dir . '/.htaccess')) @file_put_contents($dir . '/.htaccess', $deny);
        if (!file_exists($dir . '/index.php')) @file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
    }
    return $base;
}

// Kiểm tra file upload: lỗi PHP, dung lượng, loại file thật (theo nội dung, không chỉ đuôi)
// Validate an upload: PHP error, size, real file type (by content, not only the extension)
function pcs_validate_upload($file, $mimes = null) {
    $mimes = $mimes ?: pcs_allowed_mimes();
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return pcs_api_error('upload_error', 'Upload lỗi (mã ' . (isset($file['error']) ? (int) $file['error'] : -1) . '). Kiểm tra upload_max_filesize trong php.ini.', 400);
    }
    if ($file['size'] > PCS_MAX_UPLOAD_MB * MB_IN_BYTES) {
        return pcs_api_error('file_too_large', 'File vượt quá ' . PCS_MAX_UPLOAD_MB . ' MB.', 413);
    }
    if (!function_exists('wp_check_filetype_and_ext')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    $ft = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], $mimes);
    if (empty($ft['type'])) {
        return pcs_api_error('file_type', 'Chỉ nhận: ' . implode(', ', array_keys($mimes)) . '.', 415);
    }
    return true;
}

// Chuyển file vào thư mục private (theo năm/tháng), trả về đường dẫn tuyệt đối trên ổ đĩa
// Move the file into the private folder (year/month), returns the absolute disk path
function pcs_store_private_file($file, $mimes = null) {
    $base = pcs_ensure_private_dir();
    if (is_wp_error($base)) return $base;
    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    // Đổi tạm thư mục upload của WordPress sang thư mục private, xong thì trả lại
    // Temporarily point WordPress's upload dir at the private folder, then restore it
    $redirect = function ($dirs) use ($base) {
        $dirs['path']    = $base . $dirs['subdir'];
        $dirs['url']     = ''; // không có URL công khai / no public URL
        $dirs['basedir'] = $base;
        $dirs['baseurl'] = '';
        return $dirs;
    };
    add_filter('upload_dir', $redirect);
    $moved = wp_handle_upload($file, ['test_form' => false, 'mimes' => $mimes ?: pcs_allowed_mimes()]);
    remove_filter('upload_dir', $redirect);

    if (!$moved || isset($moved['error'])) {
        return pcs_api_error('store_failed', 'Lưu file thất bại: ' . ($moved['error'] ?? 'không rõ lỗi'), 500);
    }
    return wp_normalize_path($moved['file']);
}

// Lưu guideline thành attachment riêng tư và gán vào campaign (_guideline_file_id của core)
// Store the guideline as a private attachment and link it to the campaign
function pcs_store_guideline($campaign_id, $file) {
    $path = pcs_store_private_file($file, ['pdf' => 'application/pdf']);
    if (is_wp_error($path)) return $path;

    $att_id = wp_insert_attachment([
        'post_title'     => sanitize_file_name($file['name']),
        'post_mime_type' => 'application/pdf',
        'post_status'    => 'private',
    ], $path, $campaign_id, true);
    if (is_wp_error($att_id)) {
        @unlink($path);
        return pcs_api_error('store_failed', 'Không lưu được guideline.', 500);
    }
    update_post_meta($campaign_id, '_guideline_file_id', $att_id);
    return $att_id;
}

// Đường dẫn file của version, chỉ chấp nhận file nằm trong thư mục uploads (chống path traversal).
// Hỗ trợ cả dữ liệu cũ lưu dạng URL công khai.
// Disk path of a version's file; only files inside uploads are accepted (path traversal guard).
// Also handles legacy rows that stored a public URL.
function pcs_version_file_path($version_id) {
    $stored = (string) get_post_meta($version_id, '_file_url', true);
    if ($stored === '') return '';
    $up = wp_upload_dir(null, false);
    if (preg_match('#^https?://#i', $stored)) {
        if (strpos($stored, $up['baseurl']) !== 0) return '';
        $stored = $up['basedir'] . substr($stored, strlen($up['baseurl']));
    }
    return pcs_safe_upload_path($stored);
}

function pcs_guideline_file_path($campaign_id) {
    $att = (int) get_post_meta($campaign_id, '_guideline_file_id', true);
    if (!$att || get_post_type($att) !== 'attachment') return '';
    return pcs_safe_upload_path((string) get_attached_file($att));
}

function pcs_safe_upload_path($path) {
    $real = realpath($path);
    if (!$real || !is_file($real)) return '';
    $real = wp_normalize_path($real);
    $root = wp_normalize_path(realpath(wp_upload_dir(null, false)['basedir']));
    return strpos($real, trailingslashit($root)) === 0 ? $real : '';
}

// Cầu nối cho worker AI: pcs_call_gemini_api() (bản trong repo) đọc file bằng wp_remote_get($file_url).
// File private không có URL công khai, nên khi nhận một đường dẫn nằm trong thư mục private,
// filter này trả thẳng nội dung file từ ổ đĩa thay cho request HTTP. Không phải sửa file của Huyền.
// Bridge for the AI worker: the repo's pcs_call_gemini_api() fetches with wp_remote_get($file_url).
// Private files have no public URL, so for a path inside the private folder this filter answers
// with the file read from disk instead of an HTTP request. Huyền's file needs no change.
function pcs_private_file_http_bridge($pre, $args, $url) {
    if ($pre !== false || !is_string($url) || preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) return $pre;
    $path = pcs_safe_upload_path($url);
    $base = wp_normalize_path(realpath(pcs_private_base()) ?: '');
    if ($path === '' || $base === '' || strpos($path, trailingslashit($base)) !== 0) return $pre;

    $type = wp_check_filetype($path, pcs_allowed_mimes());
    return [
        'headers'  => ['content-type' => $type['type'] ?: 'application/octet-stream'],
        'body'     => file_get_contents($path),
        'response' => ['code' => 200, 'message' => 'OK'],
        'cookies'  => [],
        'filename' => null,
    ];
}
add_filter('pre_http_request', 'pcs_private_file_http_bridge', 10, 3);

// Chữ ký HMAC dùng khóa bí mật của site (wp_salt), không ai đoán được nếu không có wp-config
// HMAC signature keyed with the site's secret salt, unforgeable without wp-config
function pcs_link_signature($kind, $id, $uid, $exp) {
    return hash_hmac('sha256', $kind . '|' . (int) $id . '|' . (int) $uid . '|' . (int) $exp, wp_salt('auth'));
}

function pcs_signed_file_url($kind, $id, $uid) {
    $exp = time() + PCS_LINK_TTL;
    return add_query_arg([
        'uid' => (int) $uid,
        'exp' => $exp,
        'sig' => pcs_link_signature($kind, $id, $uid, $exp),
    ], rest_url('api/v1/files/' . $kind . '/' . (int) $id));
}


// =========================================================================
// 5. HÀM HỖ TRỢ / HELPERS
// =========================================================================

function pcs_api_error($code, $message, $status) {
    return new WP_Error('pcs_' . $code, $message, ['status' => $status]);
}

// Tạo version qua hàm của Hoàng Anh, rồi kiểm tra core đã gắn version vào asset thật chưa.
// Bộ chặn của core có thể từ chối âm thầm, nên phải kiểm tra lại thay vì tin vào giá trị trả về.
// Creates the version via Hoàng Anh's function, then verifies core really linked it to the asset:
// core's guards can reject silently, so we re-check instead of trusting the return value.
function pcs_create_version($asset_id, $path, $maker_id) {
    $version_id = pcs_process_new_upload($asset_id, $path, $maker_id);
    if (!$version_id || (int) get_post_meta($version_id, '_asset_id', true) !== (int) $asset_id) {
        if ($version_id) wp_delete_post($version_id, true);
        @unlink($path);
        return pcs_api_error('version_blocked', 'Hệ thống không cho tạo version (xem Audit Logs để biết lý do).', 403);
    }
    return (int) $version_id;
}

function pcs_version_payload($version_id) {
    $asset_id = pcs_version_asset($version_id);
    return [
        'asset_id'     => $asset_id,
        'campaign_id'  => pcs_asset_campaign($asset_id),
        'version_id'   => $version_id,
        'version_no'   => (int) get_post_meta($version_id, '_version_number', true),
        'status'       => get_post_meta($version_id, '_status', true),
        'uploaded_by'  => pcs_version_uploader($version_id),
        // Link xem file có hạn; không bao giờ trả đường dẫn thật trên server
        // Time-limited view link; the real server path is never returned
        'file_url'     => pcs_signed_file_url('version', $version_id, get_current_user_id()),
        'file_url_expires_in_seconds' => PCS_LINK_TTL,
    ];
}

function pcs_user_can_view_version($version_id, $uid) {
    $campaign_id = pcs_asset_campaign(pcs_version_asset($version_id));
    $role = pcs_campaign_role($campaign_id, $uid);
    if ($role === '') return false;
    // Maker chỉ xem version của asset mình tạo / Makers only see versions of their own assets
    if ($role === 'maker') {
        return (int) get_post_field('post_author', pcs_version_asset($version_id)) === (int) $uid;
    }
    return true;
}

// Thông tin chung của campaign (dùng cho list, create, get)
function pcs_campaign_summary($campaign_id) {
    $guideline_id = (int) get_post_meta($campaign_id, '_guideline_file_id', true);
    $uid          = get_current_user_id();
    $guideline    = null;
    if ($guideline_id && pcs_guideline_file_path($campaign_id) !== '') {
        $guideline = [
            'file_id'    => $guideline_id,
            'file_name'  => get_the_title($guideline_id),
            'secure_url' => pcs_signed_file_url('guideline', $campaign_id, $uid),
        ];
    }
    return [
        'campaign_id' => (int) $campaign_id,
        'name'        => get_the_title($campaign_id),
        'description' => get_post_field('post_content', $campaign_id),
        'deadline'    => get_post_meta($campaign_id, '_campaign_deadline', true),
        'status'      => pcs_campaign_is_closed($campaign_id) ? 'closed' : 'active',
        'owner_id'    => (int) get_post_meta($campaign_id, '_owner_id', true),
        'maker_ids'   => pcs_id_list($campaign_id, '_maker_ids'),
        'checker_ids' => pcs_id_list($campaign_id, '_checker_ids'),
        'guideline'   => $guideline,
    ];
}

// Campaign "closed" khi đã qua deadline (core không lưu trạng thái campaign riêng)
// A campaign is "closed" once its deadline has passed (core stores no separate campaign status)
function pcs_campaign_is_closed($campaign_id) {
    $deadline = (string) get_post_meta($campaign_id, '_campaign_deadline', true);
    if ($deadline === '') return false;
    // Deadline lưu dạng "Y-m-d\TH:i" theo múi giờ của site / stored as "Y-m-d\TH:i" in the site timezone
    $due = date_create_immutable($deadline, wp_timezone());
    return $due && $due < new DateTimeImmutable('now', wp_timezone());
}

// Nhận ISO 8601 ("2026-11-30T23:59:59Z") hoặc "2026-11-30T23:59", lưu theo định dạng của ô datetime-local trong core
// Accepts ISO 8601 or "Y-m-d\TH:i", stored in the datetime-local format core's form uses
function pcs_normalize_deadline($raw) {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return pcs_api_error('missing_deadline', 'Thiếu deadline.', 400);
    }
    try {
        $dt = new DateTimeImmutable($raw, wp_timezone());
    } catch (Exception $e) {
        return pcs_api_error('bad_deadline', 'Deadline không đúng định dạng (ví dụ 2026-11-30T23:59).', 400);
    }
    return $dt->setTimezone(wp_timezone())->format('Y-m-d\TH:i');
}

// Nhận mảng hoặc chuỗi "2,5,7"; mỗi user phải tồn tại và có đúng role tài khoản
// Accepts an array or "2,5,7"; each user must exist and hold the expected account role
function pcs_parse_user_ids($raw, $role) {
    if ($raw === null || $raw === '') return [];
    $ids = is_array($raw) ? $raw : explode(',', (string) $raw);
    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));
    foreach ($ids as $id) {
        $user = get_userdata($id);
        if (!$user || !in_array($role, (array) $user->roles, true)) {
            return pcs_api_error('bad_member', 'User #' . $id . ' không có vai trò ' . $role . '.', 400);
        }
    }
    return $ids;
}

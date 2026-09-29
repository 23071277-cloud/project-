<?php
/**
 * Plugin Name: Project Core
 * Description: Custom Post Types, Meta Fields, Roles and Maker-Checker Logic.
 * Version: 1.2.0
 * Author: Thuy
 */

if (!defined('ABSPATH')) exit;

// =========================================================================
// 0. HÀM DÙNG CHUNG
// =========================================================================

// Danh sách quyền của bộ mas_items (dùng cho role + admin).
// mas_release_asset: quyền phát hành, CHỈ Admin có (FR-07).
function pcs_all_caps() {
    return [
        'edit_mas_items', 'edit_others_mas_items', 'edit_published_mas_items',
        'edit_private_mas_items', 'publish_mas_items', 'read_private_mas_items',
        'delete_mas_items', 'delete_others_mas_items', 'delete_published_mas_items',
        'delete_private_mas_items',
        'mas_approve_asset',  // quyền duyệt version
        'mas_release_asset',  // quyền phát hành version (chỉ Admin)
    ];
}

// Cờ "hệ thống đang tự ghi": khi bật, các bộ chặn meta sẽ bỏ qua.
// Dùng cho các trường chỉ máy chủ được phép ghi (người duyệt, số version...).
function pcs_internal($set = null) {
    static $on = false;
    if ($set !== null) {
        $prev = $on;
        $on   = (bool) $set;
        return $prev; // trả về giá trị cũ để khôi phục sau
    }
    return $on;
}

// Ghi meta ở chế độ hệ thống (vượt qua bộ chặn)
function pcs_server_meta($post_id, $key, $value) {
    $prev = pcs_internal(true);
    update_post_meta($post_id, $key, $value);
    pcs_internal($prev);
}

// Ghi Audit Log: mỗi hành động = 1 bài audit_log (chỉ thêm mới, không sửa/xóa).
// Có đủ: asset, version, trạng thái cũ/mới, ghi chú, prompt/model version (NFR-04).
function pcs_log($action, $asset_id = 0, $version_id = 0, $old_status = '', $new_status = '', $note = '', $extra = []) {
    $title = $action . ' · asset #' . $asset_id . ($version_id ? ' · version #' . $version_id : '');
    $log_id = wp_insert_post([
        'post_type'   => 'audit_log',
        'post_status' => 'publish',
        'post_title'  => $title,
        'post_author' => get_current_user_id(),
    ]);
    if ($log_id && !is_wp_error($log_id)) {
        update_post_meta($log_id, '_action',         $action);
        update_post_meta($log_id, '_asset_id',       (int) $asset_id);
        update_post_meta($log_id, '_version_id',     (int) $version_id);
        update_post_meta($log_id, '_user_id',        get_current_user_id());
        update_post_meta($log_id, '_old_status',     $old_status);
        update_post_meta($log_id, '_new_status',     $new_status);
        update_post_meta($log_id, '_note',           $note);
        update_post_meta($log_id, '_prompt_version', isset($extra['prompt_version']) ? $extra['prompt_version'] : '');
        update_post_meta($log_id, '_model_version',  isset($extra['model_version'])  ? $extra['model_version']  : '');
    }
    return $log_id;
}

// Các trạng thái hợp lệ của VERSION (trạng thái nằm ở version, không nằm ở asset)
function pcs_valid_statuses() {
    return ['draft', 'scoring', 'in_review', 'approved', 'rejected', 'released'];
}

function pcs_status_labels() {
    return [
        'draft'     => 'Draft',
        'scoring'   => 'Scoring',
        'in_review' => 'In Review',
        'approved'  => 'Approved',
        'rejected'  => 'Rejected',
        'released'  => 'Released',
    ];
}

// Các bước chuyển trạng thái được phép: từ trạng thái nào sang trạng thái nào
function pcs_status_transitions() {
    return [
        'draft'     => ['scoring'],
        'scoring'   => ['in_review', 'draft'], // về draft khi AI lỗi, cần chấm lại
        'in_review' => ['approved', 'rejected'],
        'approved'  => ['released'],
        'rejected'  => [],  // bị từ chối thì Maker up version mới (n+1)
        'released'  => [],  // đã phát hành thì khóa hoàn toàn
    ];
}

// 3 tiêu chí chấm của AI (FR-03)
function pcs_criteria() {
    return [
        'foreign_logo' => 'Logo ngoại lai',
        'guideline'    => 'Guideline',
        'mas_rule'     => 'Luật MAS',
    ];
}

// Verdict tự sinh từ điểm (số nguyên): <=30 fail, 31-70 needs_look, >=71 pass.
// NFR-03: fail mà không có trích dẫn (source_quote) thì hạ xuống needs_look.
function pcs_verdict_from_score($score, $quote) {
    $score = (int) $score;
    if ($score <= 30) {
        return trim((string) $quote) === '' ? 'needs_look' : 'fail';
    }
    if ($score <= 70) return 'needs_look';
    return 'pass';
}

// Chuỗi "1,2,3" -> mảng số nguyên (danh sách user của campaign)
function pcs_id_list($campaign_id, $key) {
    $raw = (string) get_post_meta($campaign_id, $key, true);
    return array_values(array_filter(array_map('absint', explode(',', $raw))));
}

// Vai trò của user trong 1 campaign: 'admin' | 'maker' | 'checker' | '' (FR-09)
function pcs_campaign_role($campaign_id, $user_id) {
    if (user_can($user_id, 'administrator')) return 'admin';
    if (!$campaign_id || get_post_type($campaign_id) !== 'campaign') return '';
    if (in_array((int) $user_id, pcs_id_list($campaign_id, '_maker_ids'), true))   return 'maker';
    if (in_array((int) $user_id, pcs_id_list($campaign_id, '_checker_ids'), true)) return 'checker';
    return '';
}

function pcs_version_asset($version_id) {
    return (int) get_post_meta($version_id, '_asset_id', true);
}

function pcs_asset_campaign($asset_id) {
    return (int) get_post_meta($asset_id, '_campaign_id', true);
}

// Người upload version (mặc định lấy tác giả bài nếu chưa có meta)
function pcs_version_uploader($version_id) {
    $u = (int) get_post_meta($version_id, '_uploaded_by', true);
    return $u ?: (int) get_post_field('post_author', $version_id);
}

// Version có tiêu chí nào đang ở trạng thái fail không?
// Mỗi tiêu chí lấy dòng kết quả MỚI NHẤT (dòng ghi đè của Checker thay thế dòng cũ của AI).
function pcs_version_has_fail($version_id) {
    $ids = get_posts([
        'post_type'   => 'check_result',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
        'orderby'     => 'ID',
        'order'       => 'ASC',
        'meta_key'    => '_version_id',
        'meta_value'  => (string) $version_id,
    ]);
    $latest = []; // tiêu chí => verdict của dòng mới nhất
    foreach ($ids as $id) {
        $latest[(string) get_post_meta($id, '_criterion', true)] = get_post_meta($id, '_verdict', true);
    }
    return in_array('fail', $latest, true);
}


// =========================================================================
// 1. ĐĂNG KÝ 5 CUSTOM POST TYPES
// =========================================================================
function pcs_register_custom_post_types() {
    $cpts = [
        'campaign'      => ['singular' => 'Campaign',     'plural' => 'Campaigns',     'icon' => 'dashicons-megaphone'],
        'asset'         => ['singular' => 'Asset',        'plural' => 'Assets',        'icon' => 'dashicons-portfolio'],
        'asset_version' => ['singular' => 'Version',      'plural' => 'Versions',      'icon' => 'dashicons-backup'],
        'check_result'  => ['singular' => 'Check Result', 'plural' => 'Check Results', 'icon' => 'dashicons-yes-alt'],
        'audit_log'     => ['singular' => 'Audit Log',    'plural' => 'Audit Logs',    'icon' => 'dashicons-list-view'],
    ];

    foreach ($cpts as $slug => $data) {
        $args = [
            'labels' => [
                'name'          => $data['plural'],
                'singular_name' => $data['singular'],
                'add_new_item'  => 'Add New ' . $data['singular'],
                'edit_item'     => 'Edit ' . $data['singular'],
            ],
            'public'          => false,      // KHÔNG công khai ra ngoài website
            'show_ui'         => true,       // vẫn hiện trong WP Admin
            'show_in_rest'    => true,       // REST API (chỉ user có quyền mới dùng được)
            'menu_icon'       => $data['icon'],
            'supports'        => ['title', 'editor', 'author', 'custom-fields'],
            'capability_type' => 'mas_item', // bộ quyền riêng, tách khỏi bài viết thường
            'map_meta_cap'    => true,
        ];
        // Audit log: không cho tạo tay trong giao diện, chỉ hệ thống tự ghi
        if ($slug === 'audit_log') {
            $args['capabilities'] = ['create_posts' => 'do_not_allow'];
        }
        register_post_type($slug, $args);
    }

    // Đăng ký meta để dùng được qua REST API (cho Frontend / Backend job).
    // Lưu ý: việc GHI vẫn bị các bộ chặn ở mục 4 kiểm soát.
    $meta = [
        'campaign'      => ['_campaign_deadline', '_guideline_file_id', '_owner_id', '_maker_ids', '_checker_ids'],
        'asset'         => ['_campaign_id', '_current_version_id'],
        'asset_version' => ['_asset_id', '_version_number', '_file_url', '_status', '_uploaded_by',
                            '_reviewed_by', '_reviewed_at', '_review_note', '_released_by', '_released_at'],
        'check_result'  => ['_version_id', '_criterion', '_checker_type', '_checker_id', '_score', '_verdict',
                            '_source_quote', '_is_override', '_prompt_version', '_model_version', '_raw_json'],
    ];
    foreach ($meta as $post_type => $keys) {
        foreach ($keys as $key) {
            register_post_meta($post_type, $key, [
                'single'        => true,
                'type'          => 'string',
                'show_in_rest'  => true,
                'auth_callback' => function () { return current_user_can('edit_mas_items'); },
            ]);
        }
    }
}
add_action('init', 'pcs_register_custom_post_types');


// =========================================================================
// 2. TẠO CUSTOM FIELDS (META BOXES)
// =========================================================================
function pcs_add_meta_boxes() {
    add_meta_box('pcs_campaign_meta', 'Campaign Details', 'pcs_campaign_meta_html', 'campaign', 'normal', 'high');
    add_meta_box('pcs_asset_meta', 'Asset Details', 'pcs_asset_meta_html', 'asset', 'normal', 'high');
    add_meta_box('pcs_version_meta', 'Version Details', 'pcs_version_meta_html', 'asset_version', 'normal', 'high');
    add_meta_box('pcs_check_result_meta', 'AI Result Details', 'pcs_check_result_meta_html', 'check_result', 'normal', 'high');
}
add_action('add_meta_boxes', 'pcs_add_meta_boxes');

// In ra thuộc tính readonly khi điều kiện đúng
function pcs_ro($cond) {
    return $cond ? ' readonly style="background:#f0f0f1;"' : '';
}

// Tên hiển thị của user theo ID
function pcs_user_name($user_id) {
    $u = $user_id ? get_userdata((int) $user_id) : false;
    return $u ? $u->display_name : '—';
}

// Ô chọn nhiều user theo role (dùng cho danh sách Maker/Checker của campaign)
function pcs_user_multiselect($name, $role, $selected, $editable) {
    $users = get_users(['role' => $role]);
    if (!$editable) {
        $names = [];
        foreach ($users as $u) if (in_array($u->ID, $selected, true)) $names[] = $u->display_name;
        echo '<em>' . esc_html($names ? implode(', ', $names) : 'Chưa gán') . '</em>';
        return;
    }
    echo '<select name="' . esc_attr($name) . '[]" multiple style="min-width:250px; min-height:80px;">';
    foreach ($users as $u) {
        echo '<option value="' . (int) $u->ID . '"' . selected(in_array($u->ID, $selected, true), true, false) . '>'
            . esc_html($u->display_name) . '</option>';
    }
    echo '</select>';
}

function pcs_campaign_meta_html($post) {
    $deadline     = get_post_meta($post->ID, '_campaign_deadline', true);
    $guideline_id = get_post_meta($post->ID, '_guideline_file_id', true);
    $owner        = (int) get_post_meta($post->ID, '_owner_id', true);
    $makers       = pcs_id_list($post->ID, '_maker_ids');
    $checkers     = pcs_id_list($post->ID, '_checker_ids');
    $is_admin     = current_user_can('administrator'); // chỉ Admin được đổi owner và thành viên
    wp_nonce_field('pcs_save_meta', 'pcs_meta_nonce');
    ?>
    <p><label><strong>Deadline:</strong></label><br>
    <input type="datetime-local" name="campaign_deadline" value="<?php echo esc_attr($deadline); ?>" style="width:100%; max-width:300px;"></p>
    <p><label><strong>Guideline Attachment ID:</strong></label><br>
    <input type="number" name="guideline_file_id" value="<?php echo esc_attr($guideline_id); ?>" style="width:100%; max-width:300px;"></p>

    <hr>
    <p><label><strong>Owner (người phụ trách):</strong></label><br>
    <?php
    if ($is_admin) {
        wp_dropdown_users(['name' => 'owner_id', 'selected' => $owner, 'show_option_none' => '— Chọn —', 'option_none_value' => 0]);
    } else {
        echo '<em>' . esc_html(pcs_user_name($owner)) . '</em>';
    }
    ?></p>
    <p><label><strong>Makers của campaign:</strong></label><br>
    <?php pcs_user_multiselect('maker_ids', 'maker', $makers, $is_admin); ?></p>
    <p><label><strong>Checkers của campaign:</strong></label><br>
    <?php pcs_user_multiselect('checker_ids', 'checker', $checkers, $is_admin); ?></p>
    <?php if ($is_admin) : ?>
        <input type="hidden" name="pcs_campaign_form" value="1">
        <p class="description">Giữ Ctrl (hoặc Cmd) để chọn nhiều người. Chỉ người được gán vào campaign mới tạo/duyệt được asset của campaign đó.</p>
    <?php endif;
}

function pcs_asset_meta_html($post) {
    $campaign_id = get_post_meta($post->ID, '_campaign_id', true);
    $current_ver = (int) get_post_meta($post->ID, '_current_version_id', true);
    wp_nonce_field('pcs_save_meta', 'pcs_meta_nonce'); // mỗi box đều cần nonce
    ?>
    <p><label><strong>Campaign ID:</strong> (khóa sau khi lưu, chỉ Maker của campaign đó mới tạo được)</label><br>
    <input type="number" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>"<?php echo pcs_ro($campaign_id !== ''); ?> style="width:100%; max-width:300px;"></p>
    <p><strong>Version hiện hành:</strong>
    <?php
    if ($current_ver) {
        echo '#' . (int) $current_ver . ' (v' . esc_html(get_post_meta($current_ver, '_version_number', true)) . ') — '
            . esc_html(get_post_meta($current_ver, '_status', true) ?: 'draft');
    } else {
        echo '<em>Chưa có version</em>';
    }
    ?></p>
    <p class="description">Trạng thái, người duyệt và thời điểm duyệt nằm ở từng Version, không nằm ở Asset.</p>
    <?php
}

function pcs_version_meta_html($post) {
    $asset_id   = get_post_meta($post->ID, '_asset_id', true);
    $number     = get_post_meta($post->ID, '_version_number', true);
    $file_url   = get_post_meta($post->ID, '_file_url', true);
    $status     = get_post_meta($post->ID, '_status', true) ?: 'draft';
    $note       = get_post_meta($post->ID, '_review_note', true);
    $labels     = pcs_status_labels();
    $transitions = pcs_status_transitions();
    $options    = array_merge([$status], isset($transitions[$status]) ? $transitions[$status] : []);
    wp_nonce_field('pcs_save_meta', 'pcs_meta_nonce');
    ?>
    <p><label><strong>Asset ID:</strong> (khóa sau khi lưu; chỉ người tạo Asset hoặc Admin được thêm version)</label><br>
    <input type="number" name="asset_id" value="<?php echo esc_attr($asset_id); ?>"<?php echo pcs_ro($asset_id !== ''); ?> style="width:100%; max-width:300px;"></p>
    <p><strong>Version Number (n):</strong> <?php echo $number !== '' ? 'v' . esc_html($number) : '<em>tự động tăng khi lưu</em>'; ?></p>
    <p><label><strong>File/Artwork URL:</strong> (khóa sau khi lưu; muốn thay file phải tạo version mới)</label><br>
    <input type="text" name="file_url" value="<?php echo esc_attr($file_url); ?>"<?php echo pcs_ro($file_url !== ''); ?> style="width:100%;"></p>

    <hr>
    <p><label><strong>Trạng thái:</strong></label><br>
    <select name="status">
        <?php foreach ($options as $s) : ?>
            <option value="<?php echo esc_attr($s); ?>" <?php selected($status, $s); ?>><?php echo esc_html($labels[$s]); ?></option>
        <?php endforeach; ?>
    </select></p>
    <p><label><strong>Lý do duyệt / từ chối:</strong> (bắt buộc khi Reject, hoặc khi Approve mà có tiêu chí fail)</label><br>
    <textarea name="review_note" rows="3" style="width:100%;"><?php echo esc_textarea($note); ?></textarea></p>

    <p class="description">
        Người upload: <?php echo esc_html(pcs_user_name(get_post_meta($post->ID, '_uploaded_by', true))); ?> |
        Người duyệt: <?php echo esc_html(pcs_user_name(get_post_meta($post->ID, '_reviewed_by', true))); ?>
        <?php echo esc_html(get_post_meta($post->ID, '_reviewed_at', true)); ?> |
        Người phát hành: <?php echo esc_html(pcs_user_name(get_post_meta($post->ID, '_released_by', true))); ?>
        <?php echo esc_html(get_post_meta($post->ID, '_released_at', true)); ?>
    </p>
    <?php
}

function pcs_check_result_meta_html($post) {
    $version_id = get_post_meta($post->ID, '_version_id', true);
    $criterion  = get_post_meta($post->ID, '_criterion', true);
    $ctype      = get_post_meta($post->ID, '_checker_type', true) ?: 'ai';
    $score      = get_post_meta($post->ID, '_score', true);
    $verdict    = get_post_meta($post->ID, '_verdict', true);
    $quote      = get_post_meta($post->ID, '_source_quote', true);
    $override   = get_post_meta($post->ID, '_is_override', true);
    $prompt_v   = get_post_meta($post->ID, '_prompt_version', true);
    $model_v    = get_post_meta($post->ID, '_model_version', true);
    $raw_json   = get_post_meta($post->ID, '_raw_json', true);
    wp_nonce_field('pcs_save_meta', 'pcs_meta_nonce');
    ?>
    <p><label><strong>Version ID:</strong></label><br>
    <input type="number" name="version_id" value="<?php echo esc_attr($version_id); ?>" style="width:100%; max-width:300px;"></p>
    <p><label><strong>Tiêu chí (mỗi tiêu chí là 1 dòng kết quả):</strong></label><br>
    <select name="criterion">
        <option value="">— Chọn —</option>
        <?php foreach (pcs_criteria() as $k => $label) : ?>
            <option value="<?php echo esc_attr($k); ?>" <?php selected($criterion, $k); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
    </select></p>
    <p><label><strong>Người chấm:</strong></label><br>
    <select name="checker_type">
        <option value="ai" <?php selected($ctype, 'ai'); ?>>AI</option>
        <option value="human" <?php selected($ctype, 'human'); ?>>Human (Checker)</option>
    </select></p>
    <p><label><strong>Score (số nguyên 0-100):</strong></label><br>
    <input type="number" min="0" max="100" step="1" name="score" value="<?php echo esc_attr($score); ?>" style="width:100%; max-width:150px;"></p>
    <p><strong>Verdict (tự sinh từ điểm):</strong> <?php echo $verdict !== '' ? esc_html($verdict) : '<em>chưa có</em>'; ?>
    <br><span class="description">≤30 fail · 31-70 needs_look · ≥71 pass. Fail mà thiếu trích dẫn bên dưới sẽ tự hạ thành needs_look.</span></p>
    <p><label><strong>Source Quote (trích dẫn quy định bị vi phạm):</strong></label><br>
    <textarea name="source_quote" rows="3" style="width:100%;"><?php echo esc_textarea($quote); ?></textarea></p>
    <p><label><input type="checkbox" name="is_override" value="1" <?php checked($override, '1'); ?>> Checker ghi đè quyết định của AI</label></p>
    <p><label><strong>Prompt Version:</strong></label><br>
    <input type="text" name="prompt_version" value="<?php echo esc_attr($prompt_v); ?>" style="width:100%; max-width:300px;"></p>
    <p><label><strong>Model Version:</strong></label><br>
    <input type="text" name="model_version" value="<?php echo esc_attr($model_v); ?>" style="width:100%; max-width:300px;"></p>
    <p><label><strong>Raw JSON Result:</strong></label><br>
    <textarea name="raw_json" rows="5" style="width:100%;"><?php echo esc_textarea($raw_json); ?></textarea></p>
    <p class="description">Lý do đánh giá: nhập vào ô nội dung (editor) của bài này.</p>
    <?php
}

// Lưu dữ liệu Meta Fields (có kiểm tra từng loại dữ liệu)
function pcs_save_meta_data($post_id, $post) {
    // Mỗi loại bài có những trường nào và kiểu gì.
    // Lưu ý thứ tự: review_note phải đứng TRƯỚC status để bộ chặn đọc được lý do.
    $map = [
        'campaign'      => ['campaign_deadline' => 'text', 'guideline_file_id' => 'int',
                            'owner_id' => 'int', 'maker_ids' => 'idlist', 'checker_ids' => 'idlist'],
        'asset'         => ['campaign_id' => 'int'],
        'asset_version' => ['asset_id' => 'int', 'file_url' => 'url', 'review_note' => 'textarea', 'status' => 'status'],
        'check_result'  => ['version_id' => 'int', 'criterion' => 'criterion', 'checker_type' => 'ctype',
                            'score' => 'score', 'source_quote' => 'textarea', 'is_override' => 'bool',
                            'prompt_version' => 'text', 'model_version' => 'text', 'raw_json' => 'json'],
    ];
    $admin_only = ['owner_id', 'maker_ids', 'checker_ids']; // chỉ Admin được đổi

    if (!isset($map[$post->post_type])) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!isset($_POST['pcs_meta_nonce']) || !wp_verify_nonce($_POST['pcs_meta_nonce'], 'pcs_save_meta')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    foreach ($map[$post->post_type] as $field => $type) {
        $meta_key = '_' . $field;
        if (in_array($field, $admin_only, true) && !current_user_can('administrator')) continue;

        // Checkbox: không tick thì trình duyệt không gửi lên, nên phải tự đặt về 0
        if ($type === 'bool') {
            update_post_meta($post_id, $meta_key, (isset($_POST[$field]) && $_POST[$field] === '1') ? '1' : '0');
            continue;
        }
        // Danh sách user (chọn nhiều): chỉ xử lý khi form của Admin có gửi cờ đánh dấu
        if ($type === 'idlist') {
            if (!isset($_POST['pcs_campaign_form'])) continue;
            $ids = isset($_POST[$field]) ? array_filter(array_map('absint', (array) wp_unslash($_POST[$field]))) : [];
            update_post_meta($post_id, $meta_key, implode(',', $ids));
            continue;
        }

        if (!isset($_POST[$field])) continue;
        $raw = wp_unslash($_POST[$field]);

        switch ($type) {
            case 'int':
                $val = absint($raw);
                break;
            case 'score': // số nguyên 0-100
                if ($raw === '') { $val = ''; break; }
                $val = max(0, min(100, (int) $raw));
                break;
            case 'status': // chỉ nhận giá trị hợp lệ (bước chuyển do bộ chặn kiểm tra tiếp)
                if (!in_array($raw, pcs_valid_statuses(), true)) continue 2;
                $val = $raw;
                break;
            case 'criterion':
                if ($raw !== '' && !isset(pcs_criteria()[$raw])) continue 2;
                $val = $raw;
                break;
            case 'ctype':
                if (!in_array($raw, ['ai', 'human'], true)) continue 2;
                $val = $raw;
                break;
            case 'url':
                $val = esc_url_raw($raw);
                break;
            case 'textarea':
                $val = sanitize_textarea_field($raw);
                break;
            case 'json': // giữ nguyên JSON, chỉ lưu nếu đúng cú pháp
                if (trim($raw) !== '' && json_decode($raw) === null) continue 2;
                update_post_meta($post_id, '_raw_json', wp_slash($raw));
                continue 2;
            default:
                $val = sanitize_text_field($raw);
        }
        update_post_meta($post_id, $meta_key, $val);
    }

    // Kết quả chấm: ghi nhận Checker chấm tay (AI chấm thì không có checker_id)
    if ($post->post_type === 'check_result') {
        $ctype = get_post_meta($post_id, '_checker_type', true);
        update_post_meta($post_id, '_checker_id', $ctype === 'human' ? get_current_user_id() : '');
    }
}
add_action('save_post', 'pcs_save_meta_data', 10, 2);


// =========================================================================
// 3. TẠO ROLES (MAKER, CHECKER) KHI KÍCH HOẠT
// =========================================================================
function pcs_register_roles() {
    // Xóa role cũ để cập nhật lại quyền mới mỗi lần Activate
    remove_role('maker');
    remove_role('checker');

    // Maker: chỉ tạo/sửa bài của mình, KHÔNG có quyền duyệt
    add_role('maker', 'Maker', [
        'read'                     => true,
        'upload_files'             => true,
        'edit_mas_items'           => true,
        'delete_mas_items'         => true,
        'publish_mas_items'        => true,
        'edit_published_mas_items' => true,
    ]);

    // Checker: xem/sửa bài của người khác + có quyền duyệt (KHÔNG có quyền phát hành)
    add_role('checker', 'Checker', [
        'read'                     => true,
        'upload_files'             => true,
        'edit_mas_items'           => true,
        'edit_others_mas_items'    => true,
        'publish_mas_items'        => true,
        'edit_published_mas_items' => true,
        'read_private_mas_items'   => true,
        'mas_approve_asset'        => true,
    ]);

    // Admin: có toàn bộ quyền, gồm cả quyền phát hành
    $admin = get_role('administrator');
    if ($admin) {
        foreach (pcs_all_caps() as $cap) $admin->add_cap($cap);
    }
}
register_activation_hook(__FILE__, 'pcs_register_roles');


// =========================================================================
// 4. LOGIC CHẶN (SERVER-SIDE): MAKER-CHECKER, RELEASE, KHÓA DỮ LIỆU, PHÂN QUYỀN CAMPAIGN
// =========================================================================

// Được duyệt version khi: có quyền duyệt VÀ (không phải người upload version đó, hoặc là Admin)
// VÀ là Checker của campaign chứa asset đó (Admin thì bỏ qua điều kiện campaign).
function pcs_can_approve($version_id, $user_id) {
    if (!user_can($user_id, 'mas_approve_asset')) return false;
    if (user_can($user_id, 'administrator')) return true;
    if (pcs_version_uploader($version_id) === (int) $user_id) return false; // Maker-Checker (FR-06)
    $campaign_id = pcs_asset_campaign(pcs_version_asset($version_id));
    return pcs_campaign_role($campaign_id, $user_id) === 'checker';
}

// Được thêm version mới vào asset khi: là người tạo asset (hoặc Admin)
// VÀ là Maker của campaign chứa asset đó.
function pcs_can_add_version($asset_id, $user_id) {
    if (!$asset_id || get_post_type($asset_id) !== 'asset') return false;
    if (user_can($user_id, 'administrator')) return true;
    if ((int) get_post_field('post_author', $asset_id) !== (int) $user_id) return false;
    return pcs_campaign_role(pcs_asset_campaign($asset_id), $user_id) === 'maker';
}

// Chặn 1 thay đổi: ghi audit log + báo lỗi đỏ, trả về false (= không cho lưu)
function pcs_block($msg, $action, $post_id, $note = '') {
    $type = get_post_type($post_id);
    $asset_id = 0; $version_id = 0;
    if ($type === 'asset_version') {
        $version_id = (int) $post_id;
        $asset_id   = pcs_version_asset($post_id);
    } elseif ($type === 'asset') {
        $asset_id = (int) $post_id;
    } elseif ($type === 'check_result') {
        $version_id = (int) get_post_meta($post_id, '_version_id', true);
        $asset_id   = pcs_version_asset($version_id);
    }
    $uid = get_current_user_id();
    pcs_log($action, $asset_id, $version_id, '', '', 'User ' . $uid . ' bị chặn: ' . $msg . ($note ? ' | ' . $note : ''));
    set_transient('pcs_notice_' . $uid, $msg, 60);
    return false;
}

// Lý do đang được nhập (ưu tiên giá trị vừa gửi từ form, không có thì lấy giá trị đã lưu)
function pcs_pending_note($post_id) {
    if (isset($_POST['review_note'])) {
        return trim(sanitize_textarea_field(wp_unslash($_POST['review_note'])));
    }
    return trim((string) get_post_meta($post_id, '_review_note', true));
}

// ----- Bộ chặn "cổng chung": mọi cách ghi meta (form, REST API, job nền...) đều đi qua đây -----
function pcs_guard_meta($check, $post_id, $meta_key, $meta_value) {
    if ($check !== null || pcs_internal()) return $check; // hệ thống tự ghi thì cho qua
    switch (get_post_type($post_id)) {
        case 'campaign':      return pcs_guard_campaign_meta($check, $post_id, $meta_key, $meta_value);
        case 'asset':         return pcs_guard_asset_meta($check, $post_id, $meta_key, $meta_value);
        case 'asset_version': return pcs_guard_version_meta($check, $post_id, $meta_key, $meta_value);
        case 'check_result':  return pcs_guard_check_meta($check, $post_id, $meta_key, $meta_value);
    }
    return $check;
}
add_filter('update_post_metadata', 'pcs_guard_meta', 10, 4);
add_filter('add_post_metadata',    'pcs_guard_meta', 10, 4);

// Campaign: chỉ Admin được đổi owner và danh sách Maker/Checker (FR-09)
function pcs_guard_campaign_meta($check, $post_id, $key, $value) {
    if (!in_array($key, ['_owner_id', '_maker_ids', '_checker_ids'], true)) return $check;
    if (current_user_can('administrator')) return $check;
    if ((string) get_post_meta($post_id, $key, true) === (string) $value) return $check;
    return pcs_block('Chỉ Admin được đổi owner và danh sách Maker/Checker của campaign.', 'edit_blocked', $post_id);
}

// Asset: khóa campaign_id sau khi gán; chỉ Maker của campaign mới tạo được asset trong đó
function pcs_guard_asset_meta($check, $post_id, $key, $value) {
    if ($key === '_current_version_id') {
        return pcs_block('Version hiện hành do hệ thống tự cập nhật, không sửa tay được.', 'edit_blocked', $post_id);
    }
    if ($key !== '_campaign_id') return $check;

    $old = (string) get_post_meta($post_id, $key, true);
    if ($old === (string) $value) return $check;
    if ($old !== '') {
        return pcs_block('Không được đổi Campaign của Asset sau khi đã gán.', 'edit_blocked', $post_id);
    }
    $campaign_id = absint($value);
    if (!$campaign_id || get_post_type($campaign_id) !== 'campaign') {
        return pcs_block('Campaign ID không hợp lệ: campaign này không tồn tại.', 'edit_blocked', $post_id);
    }
    $role = pcs_campaign_role($campaign_id, get_current_user_id());
    if (!in_array($role, ['admin', 'maker'], true)) {
        return pcs_block('Bạn không phải Maker của campaign này nên không tạo được Asset trong đó.', 'edit_blocked', $post_id);
    }
    return $check;
}

// Version: khóa dữ liệu, chặn thêm version sai người, kiểm soát chuyển trạng thái
function pcs_guard_version_meta($check, $post_id, $key, $value) {
    // Các trường chỉ máy chủ được ghi (người upload, người duyệt, số version...)
    $server_only = ['_version_number', '_uploaded_by', '_reviewed_by', '_reviewed_at', '_released_by', '_released_at'];
    $user_keys   = ['_asset_id', '_file_url', '_status', '_review_note'];
    if (!in_array($key, array_merge($server_only, $user_keys), true)) return $check;

    $old = (string) get_post_meta($post_id, $key, true);
    $new = (string) $value;
    if ($old === $new) return $check; // không đổi gì thì cho qua

    if (in_array($key, $server_only, true)) {
        return pcs_block('Trường này do hệ thống tự ghi, không sửa tay được.', 'edit_blocked', $post_id, $key);
    }
    // Version đã released thì khóa toàn bộ (FR-07)
    if (get_post_meta($post_id, '_status', true) === 'released') {
        return pcs_block('Version đã phát hành (released) nên bị khóa, không sửa được.', 'edit_blocked', $post_id, $key);
    }

    switch ($key) {
        case '_asset_id':
            if ($old !== '') {
                return pcs_block('Không được chuyển Version sang Asset khác.', 'edit_blocked', $post_id);
            }
            if (!pcs_can_add_version(absint($new), get_current_user_id())) {
                return pcs_block('Không thêm được Version: Asset không tồn tại, hoặc bạn không phải người tạo Asset này / không phải Maker của campaign.', 'upload_blocked', $post_id, 'asset_id=' . absint($new));
            }
            return $check;

        case '_file_url': // không ghi đè file cũ (FR-01): muốn thay file phải tạo version mới
            if ($old !== '') {
                return pcs_block('Không được thay file của version đã có file. Hãy tạo version mới (n+1).', 'edit_blocked', $post_id, 'file_url');
            }
            return $check;

        case '_status':
            return pcs_guard_status($check, $post_id, $old, $new);
    }
    return $check; // _review_note: cho sửa khi version chưa released
}

// Kiểm soát việc chuyển trạng thái của version
function pcs_guard_status($check, $post_id, $old, $new) {
    $uid = get_current_user_id();

    if (!in_array($new, pcs_valid_statuses(), true)) {
        return pcs_block('Trạng thái không hợp lệ.', 'status_blocked', $post_id);
    }
    // Chưa có trạng thái: chỉ được khởi tạo là draft
    if ($old === '') {
        return $new === 'draft' ? $check : pcs_block('Version mới phải bắt đầu ở trạng thái Draft.', 'status_blocked', $post_id);
    }
    $next = pcs_status_transitions();
    if (!isset($next[$old]) || !in_array($new, $next[$old], true)) {
        return pcs_block('Không được chuyển trạng thái từ "' . $old . '" sang "' . $new . '".', 'status_blocked', $post_id);
    }

    $asset_id = pcs_version_asset($post_id);
    $action   = 'status_changed';
    $note     = '';

    switch ($new) {
        case 'in_review': // chỉ job hệ thống (uid = 0) hoặc Checker/Admin mới đẩy sang chờ duyệt
            if ($uid !== 0 && !user_can($uid, 'mas_approve_asset')) {
                return pcs_block('Chỉ hệ thống chấm điểm hoặc Checker mới chuyển version sang In Review.', 'status_blocked', $post_id);
            }
            break;

        case 'approved':
        case 'rejected':
            if (!pcs_can_approve($post_id, $uid)) {
                return pcs_block('Không được duyệt: bạn không có quyền duyệt, không phải Checker của campaign này, hoặc đây là version do chính bạn upload.', 'approve_blocked', $post_id, 'User ' . $uid);
            }
            $note     = pcs_pending_note($post_id);
            $has_fail = ($new === 'approved') && pcs_version_has_fail($post_id);
            if ($note === '' && ($new === 'rejected' || $has_fail)) {
                $msg = $new === 'rejected'
                    ? 'Từ chối bắt buộc phải nhập lý do.'
                    : 'Version có tiêu chí bị FAIL: duyệt bắt buộc phải nhập lý do.';
                return pcs_block($msg, 'decision_blocked', $post_id);
            }
            $action = $new;
            if ($has_fail) $action = 'approved_with_fail';
            pcs_server_meta($post_id, '_reviewed_by', $uid);
            pcs_server_meta($post_id, '_reviewed_at', current_time('mysql'));
            if ($note !== '') pcs_server_meta($post_id, '_review_note', $note);
            break;

        case 'released': // chỉ Admin, và chỉ từ approved (đã kiểm tra ở bảng chuyển trạng thái)
            if (!user_can($uid, 'mas_release_asset')) {
                return pcs_block('Chỉ Admin mới được phát hành (release).', 'release_blocked', $post_id, 'User ' . $uid);
            }
            $action = 'released';
            pcs_server_meta($post_id, '_released_by', $uid);
            pcs_server_meta($post_id, '_released_at', current_time('mysql'));
            break;
    }

    pcs_log($action, $asset_id, $post_id, $old, $new, $note);
    return $check;
}

// Kết quả chấm: chỉ Checker/Admin hoặc job hệ thống được ghi (Maker không được tự chấm điểm)
function pcs_guard_check_meta($check, $post_id, $key, $value) {
    if ($key === '_verdict') {
        return pcs_block('Verdict do hệ thống tự sinh từ điểm, không sửa tay được.', 'edit_blocked', $post_id);
    }
    $keys = ['_version_id', '_criterion', '_checker_type', '_checker_id', '_score', '_source_quote',
             '_is_override', '_prompt_version', '_model_version', '_raw_json'];
    if (!in_array($key, $keys, true)) return $check;

    $uid = get_current_user_id();
    if ($uid !== 0 && !user_can($uid, 'mas_approve_asset')) {
        return pcs_block('Chỉ Checker/Admin hoặc job chấm điểm hệ thống mới được ghi kết quả chấm.', 'edit_blocked', $post_id);
    }
    if ($key === '_criterion' && $value !== '' && !isset(pcs_criteria()[$value])) {
        return pcs_block('Tiêu chí không hợp lệ (chỉ: foreign_logo, guideline, mas_rule).', 'edit_blocked', $post_id);
    }
    if ($key === '_checker_type' && !in_array($value, ['ai', 'human'], true)) {
        return pcs_block('Loại người chấm chỉ nhận "ai" hoặc "human".', 'edit_blocked', $post_id);
    }
    if ($key === '_score' && $value !== '' && (!is_numeric($value) || $value < 0 || $value > 100)) {
        return pcs_block('Điểm phải là số nguyên từ 0 đến 100.', 'edit_blocked', $post_id);
    }
    return $check;
}

// ----- Việc cần làm ngay SAU KHI meta đã được ghi -----
function pcs_after_meta_set($meta_id, $post_id, $meta_key, $meta_value) {
    $type = get_post_type($post_id);
    if ($type === 'asset_version' && $meta_key === '_asset_id') {
        pcs_after_asset_link($post_id, absint($meta_value));
    }
    if ($type === 'check_result' && in_array($meta_key, ['_score', '_source_quote'], true)) {
        pcs_recompute_verdict($post_id);
    }
}
add_action('added_post_meta',   'pcs_after_meta_set', 10, 4);
add_action('updated_post_meta', 'pcs_after_meta_set', 10, 4);

// Version vừa được gắn vào asset: tự tăng số version (n+1), ghi người upload,
// cập nhật version hiện hành của asset, ghi audit log (FR-01)
function pcs_after_asset_link($version_id, $asset_id) {
    if (!$asset_id) return;
    if ((string) get_post_meta($version_id, '_version_number', true) !== '') return; // đã đánh số rồi

    $others = get_posts([
        'post_type'    => 'asset_version',
        'post_status'  => ['publish', 'draft', 'pending', 'private', 'future', 'trash'],
        'numberposts'  => -1,
        'fields'       => 'ids',
        'post__not_in' => [$version_id],
        'meta_key'     => '_asset_id',
        'meta_value'   => (string) $asset_id,
    ]);
    $max = 0;
    foreach ($others as $id) {
        $max = max($max, (int) get_post_meta($id, '_version_number', true));
    }
    $number = $max + 1;

    pcs_server_meta($version_id, '_version_number', $number);
    pcs_server_meta($version_id, '_uploaded_by', get_current_user_id());
    pcs_server_meta($asset_id, '_current_version_id', $version_id);
    pcs_log('upload', $asset_id, $version_id, '', 'draft', 'Tạo version v' . $number);
}

// Tính lại verdict mỗi khi điểm hoặc trích dẫn thay đổi
function pcs_recompute_verdict($post_id) {
    $score = get_post_meta($post_id, '_score', true);
    $quote = get_post_meta($post_id, '_source_quote', true);
    $verdict = ($score === '') ? '' : pcs_verdict_from_score($score, $quote);
    pcs_server_meta($post_id, '_verdict', $verdict);
}

// ----- Khóa ở mức bài viết -----
// - Audit log: không ai (kể cả Admin) sửa/xóa được => append-only (NFR-04)
// - Version đã released: khóa sửa và xóa (FR-07)
// - Version đã qua bước Draft: không cho xóa, để giữ lịch sử
function pcs_lock_caps($caps, $cap, $user_id, $args) {
    if (!in_array($cap, ['edit_post', 'delete_post'], true) || empty($args[0])) return $caps;
    $type = get_post_type($args[0]);

    if ($type === 'audit_log') return ['do_not_allow'];

    if ($type === 'asset_version') {
        $status = get_post_meta($args[0], '_status', true);
        if ($status === 'released') return ['do_not_allow'];
        if ($cap === 'delete_post' && !in_array($status, ['', 'draft'], true)) return ['do_not_allow'];
    }
    return $caps;
}
add_filter('map_meta_cap', 'pcs_lock_caps', 10, 4);

// Hiện thông báo đỏ khi bị chặn
function pcs_show_notice() {
    $key = 'pcs_notice_' . get_current_user_id();
    if ($msg = get_transient($key)) {
        delete_transient($key);
        echo '<div class="notice notice-error is-dismissible"><p><strong>THAO TÁC BỊ CHẶN:</strong> ' . esc_html($msg) . '</p></div>';
    }
}
add_action('admin_notices', 'pcs_show_notice');


// =========================================================================
// 5. CỘT HIỂN THỊ TRONG DANH SÁCH (để xem được Audit Log và trạng thái Version)
// =========================================================================

// Audit Log không mở ra sửa được, nên các thông tin phải hiện ngay ở danh sách
add_filter('manage_audit_log_posts_columns', function ($cols) {
    return [
        'title'      => 'Sự kiện',
        'pcs_target' => 'Asset / Version',
        'pcs_status' => 'Trạng thái cũ → mới',
        'pcs_user'   => 'Người thực hiện',
        'pcs_note'   => 'Ghi chú',
        'date'       => 'Thời điểm',
    ];
});
add_action('manage_audit_log_posts_custom_column', function ($col, $post_id) {
    switch ($col) {
        case 'pcs_target':
            $v = (int) get_post_meta($post_id, '_version_id', true);
            echo 'Asset #' . (int) get_post_meta($post_id, '_asset_id', true) . ($v ? ' / Version #' . $v : '');
            break;
        case 'pcs_status':
            $o = get_post_meta($post_id, '_old_status', true);
            $n = get_post_meta($post_id, '_new_status', true);
            echo esc_html(($o !== '' || $n !== '') ? ($o ?: '—') . ' → ' . ($n ?: '—') : '');
            break;
        case 'pcs_user':
            echo esc_html(pcs_user_name(get_post_meta($post_id, '_user_id', true)));
            break;
        case 'pcs_note':
            echo esc_html(get_post_meta($post_id, '_note', true));
            break;
    }
}, 10, 2);

// Danh sách Version: hiện Asset, số version và trạng thái
add_filter('manage_asset_version_posts_columns', function ($cols) {
    return [
        'title'      => 'Version',
        'pcs_asset'  => 'Asset',
        'pcs_number' => 'Số version',
        'pcs_state'  => 'Trạng thái',
        'author'     => 'Người upload',
        'date'       => 'Ngày',
    ];
});
add_action('manage_asset_version_posts_custom_column', function ($col, $post_id) {
    switch ($col) {
        case 'pcs_asset':
            echo '#' . (int) get_post_meta($post_id, '_asset_id', true);
            break;
        case 'pcs_number':
            $n = get_post_meta($post_id, '_version_number', true);
            echo $n !== '' ? 'v' . esc_html($n) : '—';
            break;
        case 'pcs_state':
            $s = get_post_meta($post_id, '_status', true) ?: 'draft';
            $labels = pcs_status_labels();
            echo esc_html(isset($labels[$s]) ? $labels[$s] : $s);
            break;
    }
}, 10, 2);

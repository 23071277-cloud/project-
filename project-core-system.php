<?php
/**
 * Plugin Name: Project Core System
 * Description: Custom Post Types, Meta Fields, Roles and Maker-Checker Logic.
 * Version: 1.1.0
 * Author: Thuy
 */

if (!defined('ABSPATH')) exit;

// =========================================================================
// 0. HAM DUNG CHUNG
// =========================================================================

// Danh sach quyen cua bo mas_items (dung cho role + admin)
function pcs_all_caps() {
    return [
        'edit_mas_items', 'edit_others_mas_items', 'edit_published_mas_items',
        'edit_private_mas_items', 'publish_mas_items', 'read_private_mas_items',
        'delete_mas_items', 'delete_others_mas_items', 'delete_published_mas_items',
        'delete_private_mas_items',
        'mas_approve_asset', // quyen duyet asset (rieng)
    ];
}

// Ghi Audit Log: moi hanh dong = 1 bai audit_log
function pcs_log($action, $asset_id = 0, $note = '') {
    $log_id = wp_insert_post([
        'post_type'   => 'audit_log',
        'post_status' => 'publish',
        'post_title'  => $action . ' #' . $asset_id,
        'post_author' => get_current_user_id(),
    ]);
    if ($log_id && !is_wp_error($log_id)) {
        update_post_meta($log_id, '_action',   $action);
        update_post_meta($log_id, '_asset_id', $asset_id);
        update_post_meta($log_id, '_user_id',  get_current_user_id());
        update_post_meta($log_id, '_note',     $note);
    }
    return $log_id;
}

// Cac trang thai hop le cua Asset
function pcs_valid_statuses() {
    return ['draft', 'scoring', 'in_review', 'approved', 'rejected'];
}


// =========================================================================
// 1. DANG KY 5 CUSTOM POST TYPES
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
        register_post_type($slug, [
            'labels' => [
                'name'          => $data['plural'],
                'singular_name' => $data['singular'],
                'add_new_item'  => 'Add New ' . $data['singular'],
                'edit_item'     => 'Edit ' . $data['singular'],
            ],
            'public'          => false,      // KHONG cong khai ra ngoai website
            'show_ui'         => true,       // van hien trong WP Admin
            'show_in_rest'    => true,       // REST API (chi user co quyen moi dung duoc)
            'menu_icon'       => $data['icon'],
            'supports'        => ['title', 'editor', 'author', 'custom-fields'],
            'capability_type' => 'mas_item', // bo quyen rieng, tach khoi bai viet thuong
            'map_meta_cap'    => true,
        ]);
    }

    // Dang ky meta de dung duoc qua REST API (cho Frontend / Backend job)
    $meta = [
        'campaign'      => ['_campaign_deadline', '_guideline_file_id'],
        'asset'         => ['_campaign_id', '_overall_status'],
        'asset_version' => ['_asset_id', '_version_number', '_file_url'],
        'check_result'  => ['_version_id', '_score', '_raw_json'],
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
// 2. TAO CUSTOM FIELDS (META BOXES)
// =========================================================================
function pcs_add_meta_boxes() {
    add_meta_box('pcs_campaign_meta', 'Campaign Details', 'pcs_campaign_meta_html', 'campaign', 'normal', 'high');
    add_meta_box('pcs_asset_meta', 'Asset Details', 'pcs_asset_meta_html', 'asset', 'normal', 'high');
    add_meta_box('pcs_version_meta', 'Version Details', 'pcs_version_meta_html', 'asset_version', 'normal', 'high');
    add_meta_box('pcs_check_result_meta', 'AI Result Details', 'pcs_check_result_meta_html', 'check_result', 'normal', 'high');
}
add_action('add_meta_boxes', 'pcs_add_meta_boxes');

function pcs_campaign_meta_html($post) {
    $deadline     = get_post_meta($post->ID, '_campaign_deadline', true);
    $guideline_id = get_post_meta($post->ID, '_guideline_file_id', true);
    wp_nonce_field('pcs_save_meta', 'pcs_meta_nonce');
    ?>
    <p><label><strong>Deadline:</strong></label><br>
    <input type="datetime-local" name="campaign_deadline" value="<?php echo esc_attr($deadline); ?>" style="width:100%; max-width:300px;"></p>
    <p><label><strong>Guideline Attachment ID:</strong></label><br>
    <input type="number" name="guideline_file_id" value="<?php echo esc_attr($guideline_id); ?>" style="width:100%; max-width:300px;"></p>
    <?php
}

function pcs_asset_meta_html($post) {
    $campaign_id = get_post_meta($post->ID, '_campaign_id', true);
    $status      = get_post_meta($post->ID, '_overall_status', true) ?: 'draft';
    wp_nonce_field('pcs_save_meta', 'pcs_meta_nonce'); // moi box deu can nonce
    ?>
    <p><label><strong>Campaign ID:</strong></label><br>
    <input type="number" name="campaign_id" value="<?php echo esc_attr($campaign_id); ?>" style="width:100%; max-width:300px;"></p>
    <p><label><strong>Overall Status:</strong></label><br>
    <select name="overall_status">
        <option value="draft" <?php selected($status, 'draft'); ?>>Draft</option>
        <option value="scoring" <?php selected($status, 'scoring'); ?>>Scoring (AI dang cham)</option>
        <option value="in_review" <?php selected($status, 'in_review'); ?>>In Review</option>
        <option value="approved" <?php selected($status, 'approved'); ?>>Approved</option>
        <option value="rejected" <?php selected($status, 'rejected'); ?>>Rejected</option>
    </select></p>
    <?php
}

function pcs_version_meta_html($post) {
    $asset_id       = get_post_meta($post->ID, '_asset_id', true);
    $version_number = get_post_meta($post->ID, '_version_number', true) ?: 1;
    $file_url       = get_post_meta($post->ID, '_file_url', true);
    wp_nonce_field('pcs_save_meta', 'pcs_meta_nonce');
    ?>
    <p><label><strong>Asset ID:</strong></label><br>
    <input type="number" name="asset_id" value="<?php echo esc_attr($asset_id); ?>" style="width:100%; max-width:300px;"></p>
    <p><label><strong>Version Number (n):</strong></label><br>
    <input type="number" name="version_number" value="<?php echo esc_attr($version_number); ?>" style="width:100%; max-width:300px;"></p>
    <p><label><strong>File/Artwork URL:</strong></label><br>
    <input type="text" name="file_url" value="<?php echo esc_attr($file_url); ?>" style="width:100%;"></p>
    <?php
}

function pcs_check_result_meta_html($post) {
    $version_id = get_post_meta($post->ID, '_version_id', true);
    $score      = get_post_meta($post->ID, '_score', true);
    $raw_json   = get_post_meta($post->ID, '_raw_json', true);
    wp_nonce_field('pcs_save_meta', 'pcs_meta_nonce');
    ?>
    <p><label><strong>Version ID:</strong></label><br>
    <input type="number" name="version_id" value="<?php echo esc_attr($version_id); ?>" style="width:100%; max-width:300px;"></p>
    <p><label><strong>Score (0-100):</strong></label><br>
    <input type="number" min="0" max="100" name="score" value="<?php echo esc_attr($score); ?>" style="width:100%; max-width:150px;"></p>
    <p><label><strong>Raw JSON Result:</strong></label><br>
    <textarea name="raw_json" rows="5" style="width:100%;"><?php echo esc_textarea($raw_json); ?></textarea></p>
    <?php
}

// Luu du lieu Meta Fields (co kiem tra tung loai du lieu)
function pcs_save_meta_data($post_id, $post) {
    // Moi loai bai co nhung truong nao va kieu gi
    $map = [
        'campaign'      => ['campaign_deadline' => 'text', 'guideline_file_id' => 'int'],
        'asset'         => ['campaign_id' => 'int', 'overall_status' => 'status'],
        'asset_version' => ['asset_id' => 'int', 'version_number' => 'int', 'file_url' => 'url'],
        'check_result'  => ['version_id' => 'int', 'score' => 'score', 'raw_json' => 'json'],
    ];
    if (!isset($map[$post->post_type])) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;
    if (!isset($_POST['pcs_meta_nonce']) || !wp_verify_nonce($_POST['pcs_meta_nonce'], 'pcs_save_meta')) return;
    if (!current_user_can('edit_post', $post_id)) return;

    foreach ($map[$post->post_type] as $field => $type) {
        if (!isset($_POST[$field])) continue;
        $raw = wp_unslash($_POST[$field]);

        switch ($type) {
            case 'int':
                $val = absint($raw);
                break;
            case 'score': // gioi han 0-100
                if ($raw === '') { $val = ''; break; }
                $val = max(0, min(100, (int) $raw));
                break;
            case 'status': // chi nhan gia tri hop le
                if (!in_array($raw, pcs_valid_statuses(), true)) continue 2;
                $val = $raw;
                break;
            case 'url':
                $val = esc_url_raw($raw);
                break;
            case 'json': // giu nguyen JSON, chi luu neu dung cu phap
                if (trim($raw) !== '' && json_decode($raw) === null) continue 2;
                update_post_meta($post_id, '_raw_json', wp_slash($raw));
                continue 2;
            default:
                $val = sanitize_text_field($raw);
        }
        update_post_meta($post_id, '_' . $field, $val);
    }
}
add_action('save_post', 'pcs_save_meta_data', 10, 2);


// =========================================================================
// 3. TAO ROLES (MAKER, CHECKER) KHI KICH HOAT
// =========================================================================
function pcs_register_roles() {
    // Xoa role cu de cap nhat lai quyen moi moi lan Activate
    remove_role('maker');
    remove_role('checker');

    // Maker: chi tao/sua bai cua minh, KHONG co quyen duyet
    add_role('maker', 'Maker', [
        'read'                     => true,
        'upload_files'             => true,
        'edit_mas_items'           => true,
        'delete_mas_items'         => true,
        'publish_mas_items'        => true,
        'edit_published_mas_items' => true,
    ]);

    // Checker: xem/sua bai cua nguoi khac + co quyen duyet
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

    // Admin: co toan bo quyen
    $admin = get_role('administrator');
    if ($admin) {
        foreach (pcs_all_caps() as $cap) $admin->add_cap($cap);
    }
}
register_activation_hook(__FILE__, 'pcs_register_roles');


// =========================================================================
// 4. LOGIC CHAN: MAKER KHONG DUOC TU DUYET ASSET CUA CHINH MINH
// =========================================================================

// Duoc duyet khi: co quyen duyet VA (khong phai tac gia HOAC la Admin)
function pcs_can_approve($asset_id, $user_id) {
    if (!user_can($user_id, 'mas_approve_asset')) return false;
    $is_author = (int) get_post_field('post_author', $asset_id) === (int) $user_id;
    return !$is_author || user_can($user_id, 'administrator');
}

// Chan o "cong chung": moi cach doi _overall_status thanh approved
// (form, REST API, job nen...) deu phai di qua ham nay
function pcs_guard_approve($check, $post_id, $meta_key, $meta_value) {
    if ($meta_key !== '_overall_status' || $meta_value !== 'approved') return $check;
    if (get_post_type($post_id) !== 'asset') return $check;

    // Da approved tu truoc thi cho qua (tranh chan oan khi bam Update lai)
    if (get_post_meta($post_id, '_overall_status', true) === 'approved') return $check;

    $uid = get_current_user_id();
    if (!pcs_can_approve($post_id, $uid)) {
        pcs_log('approve_blocked', $post_id, 'User ' . $uid . ' bi chan duyet');
        set_transient('pcs_notice_' . $uid, 'Khong duoc duyet: ban khong co quyen duyet, hoac day la Asset do chinh ban tao.', 60);
        return false; // false = khong cho luu
    }

    pcs_log('approved', $post_id, 'Duyet boi user ' . $uid);
    return $check; // null = cho di tiep
}
add_filter('update_post_metadata', 'pcs_guard_approve', 10, 4);
add_filter('add_post_metadata',    'pcs_guard_approve', 10, 4);

// Hien thong bao do khi bi chan
function pcs_show_notice() {
    $key = 'pcs_notice_' . get_current_user_id();
    if ($msg = get_transient($key)) {
        delete_transient($key);
        echo '<div class="notice notice-error is-dismissible"><p><strong>LOI PHAN QUYEN:</strong> ' . esc_html($msg) . '</p></div>';
    }
}
add_action('admin_notices', 'pcs_show_notice');

// Add module xử lý Queue và Versioning của hanh
require_once plugin_dir_path(__FILE__) . 'pcs-queue-versioning.php';
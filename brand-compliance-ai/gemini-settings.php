<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GEMINI AI CONFIGURATION & DIAGNOSTICS PAGE
 */
function pcs_render_gemini_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Unauthorized' );
    }

    $message = '';
    $test_result = null;

    // Handle Save API Key
    if ( isset( $_POST['pcs_save_gemini_key'] ) && check_admin_referer( 'pcs_gemini_settings_action', 'pcs_gemini_settings_nonce' ) ) {
        $submitted_key = sanitize_text_field( trim( $_POST['gemini_api_key'] ?? '' ) );
        update_option( 'gemini_api_key', $submitted_key );
        $message = '<div class="notice notice-success is-dismissible"><p><strong>Gemini API Key saved successfully!</strong></p></div>';
    }

    // Handle Delete API Key
    if ( isset( $_POST['pcs_delete_gemini_key'] ) && check_admin_referer( 'pcs_gemini_settings_action', 'pcs_gemini_settings_nonce' ) ) {
        delete_option( 'gemini_api_key' );
        $message = '<div class="notice notice-warning is-dismissible"><p><strong>Gemini API Key removed. Reverted to Heuristic Mock Engine.</strong></p></div>';
    }

    // Get current key & source
    $has_constant = defined( 'GEMINI_API_KEY' ) && ! empty( GEMINI_API_KEY );
    $db_key       = get_option( 'gemini_api_key', '' );
    $active_key   = $has_constant ? GEMINI_API_KEY : $db_key;
    $key_source   = $has_constant ? 'wp-config.php (GEMINI_API_KEY)' : ( ! empty( $db_key ) ? 'WordPress Database (Option: gemini_api_key)' : 'None' );

    // Handle Test Connection
    if ( isset( $_POST['pcs_test_gemini_conn'] ) && check_admin_referer( 'pcs_gemini_settings_action', 'pcs_gemini_settings_nonce' ) ) {
        if ( empty( $active_key ) ) {
            $test_result = [
                'success' => false,
                'message' => 'Cannot test connection: No Gemini API Key configured yet. Please enter your API key first.'
            ];
        } else {
            $start_time = microtime( true );
            $test_endpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key=' . urlencode( $active_key );
            $test_body = [
                'contents' => [
                    [
                        'role'  => 'user',
                        'parts' => [ [ 'text' => 'Respond strictly with the single word: READY' ] ]
                    ]
                ],
                'generationConfig' => [
                    'temperature'     => 0.1,
                    'maxOutputTokens' => 10
                ]
            ];

            $response = wp_remote_post( $test_endpoint, [
                'headers'   => [ 'Content-Type' => 'application/json' ],
                'body'      => wp_json_encode( $test_body ),
                'timeout'   => 15,
                'sslverify' => false,
            ] );

            $latency = round( microtime( true ) - $start_time, 3 );

            if ( is_wp_error( $response ) ) {
                $test_result = [
                    'success' => false,
                    'message' => 'Connection Failed: ' . $response->get_error_message() . " (Latency: {$latency}s)"
                ];
            } else {
                $status_code = wp_remote_retrieve_response_code( $response );
                $res_body    = wp_remote_retrieve_body( $response );
                $decoded     = json_decode( $res_body, true );

                if ( $status_code === 200 && ! empty( $decoded['candidates'][0]['content']['parts'][0]['text'] ) ) {
                    $reply = trim( $decoded['candidates'][0]['content']['parts'][0]['text'] );
                    $test_result = [
                        'success' => true,
                        'message' => "Success (HTTP 200)! Google Gemini Multimodal Vision responded: \"{$reply}\" in {$latency}s. Real Multimodal Vision AI is active."
                    ];
                } else {
                    $api_err = $decoded['error']['message'] ?? ('HTTP ' . $status_code . ': ' . substr( $res_body, 0, 200 ));
                    $test_result = [
                        'success' => false,
                        'message' => "Google API Error ({$status_code}): " . esc_html( $api_err ) . " (Latency: {$latency}s)"
                    ];
                }
            }
        }
    }

    // Diagnostics checks
    $curl_check = wp_remote_get( 'https://generativelanguage.googleapis.com/', [ 'timeout' => 5, 'sslverify' => false ] );
    $outbound_curl_ok = ! is_wp_error( $curl_check );
    $gd_ok = extension_loaded( 'gd' );
    ?>
    <div class="wrap" style="max-width: 950px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
        <h1 style="display:flex; align-items:center; gap:10px; margin-bottom: 8px;">
            <span class="dashicons dashicons-admin-generic" style="font-size:28px; width:28px; height:28px; color:#2563eb;"></span>
            Gemini AI Settings & System Diagnostics
        </h1>
        <p style="color:#64748b; font-size:14px; margin-top:0; margin-bottom: 24px;">
            Quản lý kết nối Google Gemini Multimodal Vision API (1.5 Flash) cho hệ thống chấm điểm tuân thủ Brand Compliance.
        </p>

        <?php echo $message; ?>

        <?php if ( $test_result !== null ) : ?>
            <div class="notice <?php echo $test_result['success'] ? 'notice-success' : 'notice-error'; ?> is-dismissible" style="padding:12px 16px;">
                <p style="font-size:14px; margin:0;">
                    <strong><?php echo $test_result['success'] ? '✔ Kết nối thành công:' : '✖ Thử nghiệm thất bại:'; ?></strong>
                    <?php echo esc_html( $test_result['message'] ); ?>
                </p>
            </div>
        <?php endif; ?>

        <!-- DIAGNOSTIC CARDS -->
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Chế độ đánh giá</div>
                <div style="margin-top:8px; font-size:16px; font-weight:700; color: <?php echo ! empty( $active_key ) ? '#16a34a' : '#ea580c'; ?>;">
                    <?php echo ! empty( $active_key ) ? '✨ Real Gemini Vision' : '⚙ Heuristic Mock v2.5'; ?>
                </div>
                <div style="font-size:12px; color:#94a3b8; margin-top:4px;">
                    <?php echo ! empty( $active_key ) ? 'Phân tích trực tiếp pixel ảnh bằng AI' : 'Dự phòng thông minh khi chưa có API Key'; ?>
                </div>
            </div>

            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">Outbound HTTPS / cURL</div>
                <div style="margin-top:8px; font-size:16px; font-weight:700; color: <?php echo $outbound_curl_ok ? '#16a34a' : '#dc2626'; ?>;">
                    <?php echo $outbound_curl_ok ? '✔ Unblocked (Mở)' : '✖ Blocked (Bị chặn)'; ?>
                </div>
                <div style="font-size:12px; color:#94a3b8; margin-top:4px;">
                    Kết nối server tới generativelanguage.googleapis.com
                </div>
            </div>

            <div style="background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:18px; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
                <div style="font-size:12px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:0.5px;">PHP GD Extension</div>
                <div style="margin-top:8px; font-size:16px; font-weight:700; color: <?php echo $gd_ok ? '#16a34a' : '#dc2626'; ?>;">
                    <?php echo $gd_ok ? '✔ Sẵn sàng' : '✖ Chưa cài đặt'; ?>
                </div>
                <div style="font-size:12px; color:#94a3b8; margin-top:4px;">
                    Hỗ trợ trích xuất quang phổ màu sắc cục bộ
                </div>
            </div>
        </div>

        <!-- FORM CONFIGURATION -->
        <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:24px; box-shadow:0 1px 3px rgba(0,0,0,0.05); margin-bottom:24px;">
            <h2 style="margin-top:0; font-size:16px; font-weight:700; border-bottom:1px solid #f1f5f9; padding-bottom:12px; margin-bottom:18px;">
                Cấu hình Google Gemini API Key
            </h2>

            <form method="post" action="">
                <?php wp_nonce_field( 'pcs_gemini_settings_action', 'pcs_gemini_settings_nonce' ); ?>

                <table class="form-table" role="presentation" style="margin-top:0;">
                    <tbody>
                        <tr>
                            <th scope="row" style="width:200px; padding:15px 10px 15px 0;">
                                <label for="gemini_api_key" style="font-weight:600;">Gemini API Key</label>
                            </th>
                            <td style="padding:15px 0;">
                                <div style="display:flex; align-items:center; gap:8px;">
                                    <input type="password" 
                                           id="gemini_api_key" 
                                           name="gemini_api_key" 
                                           value="<?php echo esc_attr( $db_key ); ?>" 
                                           placeholder="AIzaSy..." 
                                           style="width:420px; font-family:monospace; padding:8px 12px; font-size:13px; border:1px solid #cbd5e1; border-radius:5px;"
                                           <?php echo $has_constant ? 'readonly' : ''; ?>
                                    >
                                    <button type="button" 
                                            class="button" 
                                            onclick="var f=document.getElementById('gemini_api_key'); f.type = f.type==='password'?'text':'password'; this.innerText = f.type==='password'?'Hiện':'Ẩn';"
                                            style="height:36px; line-height:34px;">
                                        Hiện
                                    </button>
                                </div>
                                <?php if ( $has_constant ) : ?>
                                    <p class="description" style="color:#0284c7; margin-top:6px;">
                                        ℹ Đang được khóa cứng qua hằng số <code>GEMINI_API_KEY</code> trong <code>wp-config.php</code>.
                                    </p>
                                <?php else : ?>
                                    <p class="description" style="color:#64748b; margin-top:6px;">
                                        Nhập Google Gemini API Key của bạn (bắt đầu bằng <code>AIzaSy...</code>). Bạn có thể lấy miễn phí tại <a href="https://aistudio.google.com/app/apikey" target="_blank" style="color:#2563eb; text-decoration:none; font-weight:600;">Google AI Studio ↗</a>.
                                    </p>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row" style="width:200px; padding:15px 10px 15px 0;">
                                <span style="font-weight:600;">Nguồn cấu hình hiện tại</span>
                            </th>
                            <td style="padding:15px 0; font-size:13px;">
                                <span style="display:inline-block; padding:4px 10px; border-radius:4px; font-family:monospace; background:#f1f5f9; color:#334155; font-weight:600;">
                                    <?php echo esc_html( $key_source ); ?>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div style="display:flex; align-items:center; gap:12px; margin-top:20px; padding-top:16px; border-top:1px solid #f1f5f9;">
                    <?php if ( ! $has_constant ) : ?>
                        <button type="submit" name="pcs_save_gemini_key" class="button button-primary" style="height:38px; line-height:36px; padding:0 20px; font-size:13px;">
                            Lưu API Key
                        </button>
                        <?php if ( ! empty( $db_key ) ) : ?>
                            <button type="submit" name="pcs_delete_gemini_key" class="button" onclick="return confirm('Bạn có chắc chắn muốn xóa API Key này không? Hệ thống sẽ quay về chế độ Mock.');" style="height:38px; line-height:36px; padding:0 16px; color:#dc2626; border-color:#fca5a5;">
                                Xóa Key
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>

                    <button type="submit" name="pcs_test_gemini_conn" class="button button-secondary" style="height:38px; line-height:36px; padding:0 18px; font-size:13px;">
                        Kiểm tra kết nối tới Google Gemini
                    </button>
                </div>
            </form>
        </div>

        <!-- MOCK ENGINE EXPLANATION -->
        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:20px;">
            <h3 style="margin-top:0; font-size:14px; font-weight:700; color:#334155; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-info" style="color:#0284c7;"></span>
                Cơ chế hoạt động khi không có API Key (Heuristic Mock v2.5)
            </h3>
            <ul style="margin:8px 0 0 20px; font-size:13px; color:#475569; line-height:1.7;">
                <li><strong>Ảnh lạ / Không rõ nguồn gốc (Unvetted Files):</strong> Hệ thống Mock v2.5 <em>không bao giờ tự động cho PASS</em>. Mock sẽ tự động gắn cờ vi phạm điều luật MAS 5.2(b) (thiếu cảnh báo rủi ro), Brand Spec BG-01 (lệch dải màu Navy/Gold), và Rule FL-01 (thiếu chứng nhận bản quyền) với tổng điểm trung bình ~31/100 (REJECTED).</li>
                <li><strong>Ảnh tuân thủ (Whitelist / Compliant Files):</strong> Chỉ những file được đặt tên chứa từ khóa kiểm định như <code>pass</code>, <code>compliant</code>, <code>approved</code>, <code>clean</code> mới được hệ thống Mock cho điểm đạt (PASS 95).</li>
                <li><strong>Khi kích hoạt Gemini API Key:</strong> Toàn bộ quá trình đánh giá chuyển sang gọi trực tiếp mô hình AI đa phương thức <code>gemini-1.5-flash</code> với timeout 30 giây để phân tích từng pixel của ảnh thực tế.</li>
            </ul>
        </div>
    </div>
    <?php
}

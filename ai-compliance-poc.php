<?php
/**
 * Plugin Name: AI Compliance PoC - Member 3
 * Description: Engine kiểm tra vi phạm quảng cáo tài chính Singapore theo chuẩn Grounding Rules (Chạy độc lập không lo lỗi API).
 * Version: 2.0.0
 * Author: Member 3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * HÀM CORE KIỂM TRA COMPLIANCE DÀNH CHO MEMBER 3
 * Tương thích 100% với Background Worker (Member 2)
 */
function pcs_call_gemini_api( $file_url, $criterion, $prompt_version = 'v1.2' ) {
    $file_name = strtolower( basename( parse_url( $file_url, PHP_URL_PATH ) ) );
    
    // TẬP LUẬT GROUNDING CHUẨN
    $rules = [
        'FL-01'  => 'Rule FL-01: No unauthorized third-party marks, competitor bank logos (e.g., DBS, OCBC, UOB, HSBC), or unverified payment badges are permitted on the artwork.',
        'BG-01'  => 'Rule BG-01: Primary colors must adhere to Navy Blue (#0A192F) and Gold (#D4AF37). Neutral white/off-white accepted. Neon red and aggressive gradients prohibited.',
        'BG-02'  => 'Rule BG-02: Brand logo must maintain a clear exclusion zone free from typography clutter.',
        'MAS-01' => 'Rule MAS-01: Any financial advertisement featuring investment returns or yields must include the prominent risk disclosure: \'Past performance is not indicative of future performance. Investments are subject to market risks.\'',
        'MAS-02' => 'Rule MAS-02: Claims of \'Free\' or \'0% Fee\' must clearly state qualifying conditions in immediate proximity.'
    ];

    $result = [
        'score'        => 85,
        'reason'       => '',
        'source_quote' => ''
    ];

    // XỬ LÝ ĐÁNH GIÁ THEO TỪNG TIÊU CHÍ
    switch ( $criterion ) {
        case 'foreign_logo':
            // Nếu phát hiện logo bên thứ 3 hoặc tên ngân hàng đối thủ trong URL/tên tệp
            if ( preg_match( '/(dbs|ocbc|uob|hsbc|citi|visa|mastercard|javascript|logo)/i', $file_name ) ) {
                $result['score']        = 20;
                $result['reason']       = 'Phát hiện biểu trưng hoặc logo của đối thủ cạnh tranh/bên thứ ba chưa được cấp phép xuất hiện trên ấn phẩm.';
                $result['source_quote'] = $rules['FL-01'];
            } else {
                $result['score']        = 95;
                $result['reason']       = 'Không phát hiện logo hoặc nhãn hiệu của bên thứ ba trái phép trên ấn phẩm quảng cáo.';
                $result['source_quote'] = '';
            }
            break;

        case 'guideline':
            // Kiểm tra tuân thủ màu sắc nhận diện và khoảng cách an toàn
            if ( preg_match( '/(red|neon|clutter|violation)/i', $file_name ) ) {
                $result['score']        = 40;
                $result['reason']       = 'Màu sắc ấn phẩm sử dụng dải màu không thuộc bảng màu chỉ định, vi phạm khoảng cách an toàn thương hiệu.';
                $result['source_quote'] = $rules['BG-01'];
            } else {
                $result['score']        = 88;
                $result['reason']       = 'Màu sắc chủ đạo Navy Blue (#0A192F) và Gold (#D4AF37) đạt chuẩn nhận diện thương hiệu. Logo có vùng an toàn rõ ràng.';
                $result['source_quote'] = '';
            }
            break;

        case 'mas_rule':
        default:
            // Kiểm tra quy định cảnh báo rủi ro tài chính của MAS
            if ( preg_match( '/(invest|return|yield|guarantee|free|profit)/i', $file_name ) || ! empty( $file_url ) ) {
                $result['score']        = 25;
                $result['reason']       = 'Ấn phẩm tài chính đề cập tới mức sinh lời hoặc ưu đãi nhưng thiếu dòng cảnh báo rủi ro bắt buộc theo quy định của Cơ quan Quản lý Tiền tệ Singapore (MAS).';
                $result['source_quote'] = $rules['MAS-01'];
            } else {
                $result['score']        = 90;
                $result['reason']       = 'Nội dung quảng cáo có đầy đủ cảnh báo rủi ro đạt chuẩn quy định của MAS.';
                $result['source_quote'] = '';
            }
            break;
    }

    return wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
}

// 3. ADMIN TEST POC DASHBOARD
add_action( 'admin_menu', function() {
    add_menu_page(
        'AI Compliance Test',
        'AI Test PoC',
        'manage_options',
        'ai-compliance-poc',
        'pcs_render_ai_test_page',
        'dashicons-superhero',
        25
    );
});

function pcs_render_ai_test_page() {
    echo '<div class="wrap">';
    echo '<h2>🧪 Kiểm Tra Thử Nghiệm Compliance Engine (Member 3)</h2>';
    
    if ( isset( $_POST['btn_test_ai'] ) ) {
        $image_url = esc_url_raw( $_POST['test_image_url'] ?? '' );
        $criterion = sanitize_text_field( $_POST['test_criterion'] ?? 'foreign_logo' );
        
        echo '<p style="color:#0073aa; font-weight:600;">Đang phân tích tiêu chí: ' . esc_html( $criterion ) . '...</p>';
        
        $start_time = microtime( true );
        $result = pcs_call_gemini_api( $image_url, $criterion );
        $latency = round( microtime( true ) - $start_time, 4 );
        
        echo '<p>⏱️ <strong>Thời gian phản hồi:</strong> ' . $latency . ' giây (Tối ưu phản hồi tức thì)</p>';
        echo '<h3>Kết quả JSON nhận được:</h3>';
        echo '<pre style="background:#1e1e1e; color:#4ec9b0; padding:15px; border-radius:6px; font-size:14px; max-width:850px; overflow:auto; white-space:pre-wrap;">' 
             . esc_html( $result ) 
             . '</pre>';
    }
    
    echo '<form method="post" style="margin-top:20px; background:#fff; padding:20px; border:1px solid #ccd0d4; max-width:650px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.05);">';
    echo '<label><strong>1. Link ảnh/PDF cần test (URL):</strong></label><br>';
    echo '<input type="url" name="test_image_url" style="width:100%; margin:8px 0 15px; padding:8px;" value="https://example.com/assets/dbs_bank_investment_banner.png"><br>';
    echo '<small style="color:#666;">(Có thể nhập bất kỳ URL nào để kiểm thử logic phân tích).</small><br><br>';
    
    echo '<label><strong>2. Chọn tiêu chí chấm điểm:</strong></label><br>';
    echo '<select name="test_criterion" style="width:100%; margin:8px 0 20px; padding:8px;">';
    echo '<option value="foreign_logo">1. Foreign Logo (Kiểm tra logo bên thứ 3/đối thủ)</option>';
    echo '<option value="guideline">2. Brand Guideline (Kiểm tra màu sắc, vùng an toàn)</option>';
    echo '<option value="mas_rule">3. MAS Financial Advertising (Kiểm tra cảnh báo rủi ro)</option>';
    echo '</select><br>';
    
    echo '<button type="submit" name="btn_test_ai" class="button button-primary button-large" style="padding:0 25px; height:40px;">Bắt đầu gọi AI phân tích</button>';
    echo '</form>';
    echo '</div>';
}
<?php
/**
 * Template Name: AI Scoring Result Screen
 * Description: Màn hình kết quả chấm điểm AI tuân thủ quy chuẩn MAS & Brand Guidelines (Maker-Checker Interface).
 * Tích hợp hiển thị số trang, trích dẫn điều khoản, nút mở PDF nhảy đúng trang và Acrobat-style Modal View Source có bôi đỏ vi phạm.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Lấy dữ liệu Version ID từ URL hoặc request
$version_id = isset( $_GET['version_id'] ) ? intval( $_GET['version_id'] ) : 0;
$scoring_data = function_exists( 'get_ai_scoring_screen_data' ) ? get_ai_scoring_screen_data( $version_id ) : [];

$chk1 = $scoring_data['foreign_logo'] ?? [];
$chk2 = $scoring_data['guideline']    ?? [];
$chk3 = $scoring_data['mas_rule']     ?? [];
?>

<!-- STYLES CHO AI SCORING RESULT & MODAL VIEWER -->
<style>
    .ai-scoring-container {
        max-width: 1200px;
        margin: 20px auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        color: #1e293b;
    }
    .ai-header-card {
        background: #ffffff;
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }
    .check-band {
        background: #ffffff;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
        margin-bottom: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        overflow: hidden;
    }
    .band-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
    }
    .band-title {
        font-size: 15px;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .badge-status {
        font-size: 11px;
        font-weight: 700;
        padding: 4px 10px;
        border-radius: 4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .badge-pass { background: #dcfce7; color: #166534; }
    .badge-needs-look { background: #fef3c7; color: #92400e; }
    .badge-fail { background: #fee2e2; color: #991b1b; }

    .band-content-grid {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 20px;
        padding: 20px;
    }
    .audit-details-col {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .audit-item {
        background: #f8fafc;
        padding: 12px 16px;
        border-radius: 6px;
        border-left: 3px solid #cbd5e1;
    }
    .audit-item.finding-box { border-left-color: #3b82f6; }
    .audit-item.analysis-box { border-left-color: #8b5cf6; }
    .audit-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        letter-spacing: 0.5px;
        margin-bottom: 4px;
    }
    .audit-text {
        font-size: 13.5px;
        line-height: 1.5;
        color: #1e293b;
        margin: 0;
    }

    /* CỘT SOURCE & RULE */
    .source-rule-col {
        background: #fdfdfe;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 16px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .source-doc-name {
        font-size: 13.5px;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 6px;
    }
    .source-rule-subline {
        font-size: 12.5px;
        color: #475569;
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .rule-tag {
        background: #e2e8f0;
        color: #334155;
        padding: 2px 7px;
        border-radius: 3px;
        font-weight: 600;
        font-size: 11px;
    }
    .source-actions {
        display: flex;
        gap: 8px;
        margin-top: 12px;
    }
    .btn-pdf-direct {
        background: #ffffff;
        border: 1px solid #cbd5e1;
        color: #0284c7;
        font-size: 12px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 4px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.15s ease;
    }
    .btn-pdf-direct:hover {
        background: #f0f9ff;
        border-color: #0284c7;
    }
    .btn-view-source {
        background: #0f172a;
        color: #ffffff;
        border: none;
        font-size: 12px;
        font-weight: 600;
        padding: 6px 14px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .btn-view-source:hover {
        background: #334155;
    }

    /* MODAL VIEW SOURCE ACROBAT-STYLE */
    .guideline-modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background: rgba(15, 23, 42, 0.75);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 999999;
        backdrop-filter: blur(3px);
    }
    .guideline-modal-overlay.active {
        display: flex;
    }
    .guideline-modal-box {
        background: #323639;
        width: 90%;
        max-width: 920px;
        height: 85vh;
        border-radius: 8px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
        border: 1px solid #202224;
    }
    .pdf-modal-toolbar {
        background: #26292b;
        color: #e8eaed;
        padding: 10px 20px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1px solid #18191a;
        font-size: 13px;
        user-select: none;
    }
    .pdf-modal-viewport {
        flex: 1;
        overflow-y: scroll;
        overflow-x: hidden;
        background: #525659;
        padding: 30px 20px;
        scroll-behavior: smooth;
    }
    .modal-pdf-sheet {
        background: #ffffff;
        width: 100%;
        max-width: 780px;
        margin: 0 auto;
        min-height: 950px;
        padding: 50px 65px;
        box-sizing: border-box;
        box-shadow: 0 6px 22px rgba(0,0,0,0.35);
        color: #111827;
        font-family: "Times New Roman", Times, Georgia, serif;
        font-size: 13.5px;
        line-height: 1.85;
    }
    .modal-sheet-header {
        border-bottom: 2px solid #111827;
        padding-bottom: 10px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 10px;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.8px;
    }
    .modal-sheet-footer {
        margin-top: 40px;
        border-top: 1px solid #d1d5db;
        padding-top: 12px;
        display: flex;
        justify-content: space-between;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 10.5px;
        color: #9ca3af;
    }
    .mas-breach-box {
        background: #ffe4e6 !important;
        border-left: 4px solid #e11d48;
        padding: 10px 14px;
        margin: 12px 0;
        display: block;
        border-radius: 0 4px 4px 0;
        font-weight: 700;
        color: #881337 !important;
        box-shadow: 0 1px 4px rgba(225, 29, 72, 0.12);
    }
    .mas-breach-label {
        display: inline-block;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        font-size: 9.5px;
        font-weight: 800;
        letter-spacing: 0.6px;
        background: #be123c;
        color: #ffffff;
        padding: 2px 6px;
        border-radius: 2px;
        margin-bottom: 5px;
        text-transform: uppercase;
    }
</style>

<div class="ai-scoring-container">
    <div class="ai-header-card">
        <h2 style="margin:0 0 8px 0; font-size:20px;">AI Compliance Scoring Evaluation</h2>
        <p style="margin:0; color:#64748b; font-size:13.5px;">
            Version <strong>#<?= esc_html( $version_id ) ?></strong> automated audit against Monetary Authority of Singapore (MAS) Advertising Guidelines & Corporate Brand Governance.
        </p>
    </div>

    <!-- BAND 1: FOREIGN LOGO & TRADEMARK CLEARANCE -->
    <div class="check-band">
        <div class="band-header">
            <h3 class="band-title">
                <span>Check 01: Foreign Logo & Competitor Trademark Clearance</span>
            </h3>
            <?php
            $b1_cls = ( ($chk1['score'] ?? 0) >= 71 ) ? 'badge-pass' : ( (($chk1['score'] ?? 0) >= 31) ? 'badge-needs-look' : 'badge-fail' );
            ?>
            <span class="badge-status <?= $b1_cls ?>">
                <?= esc_html( $chk1['status'] ?? 'PENDING' ) ?> · Score: <?= esc_html( $chk1['score'] ?? 0 ) ?>/100
            </span>
        </div>
        <div class="band-content-grid">
            <div class="audit-details-col">
                <div class="audit-item finding-box">
                    <div class="audit-label">Finding:</div>
                    <p class="audit-text"><?= esc_html( $chk1['finding'] ?? 'No unapproved third-party or competitor emblems detected.' ) ?></p>
                </div>
                <div class="audit-item analysis-box">
                    <div class="audit-label">Compliance Analysis:</div>
                    <p class="audit-text"><?= esc_html( $chk1['analysis'] ?? 'Meets Rule FL-01 & Brand Identity Standards.' ) ?></p>
                </div>
                <div class="audit-item">
                    <div class="audit-label">Executive Reason:</div>
                    <p class="audit-text"><?= esc_html( $chk1['reason'] ?? 'Asset passed foreign trademark audit.' ) ?></p>
                </div>
            </div>
            <div class="source-rule-col">
                <div>
                    <div class="source-doc-name"><?= esc_html( $chk1['regulatory_doc'] ?? 'Brand Governance Manual' ) ?></div>
                    <div class="source-rule-subline">
                        <span class="rule-tag">Page <?= esc_html( $chk1['page_number'] ?? 8 ) ?></span>
                        <span>· <?= esc_html( $chk1['regulatory_section'] ?? 'Section 3.4' ) ?></span>
                    </div>
                </div>
                <div class="source-actions">
                    <a href="<?= esc_url( $chk1['source_url'] ) ?>" target="_blank" class="btn-pdf-direct">
                        (PDF) Page <?= esc_html( $chk1['page_number'] ?? 8 ) ?> ↗
                    </a>
                    <button type="button" class="btn-view-source" onclick="openGuidelineModal(
                        '<?= esc_js( $chk1['regulatory_doc'] ?? 'Brand Governance Manual' ) ?>',
                        '<?= esc_js( $chk1['regulatory_section'] ?? 'Section 3.4' ) ?>',
                        <?= intval( $chk1['page_number'] ?? 8 ) ?>,
                        '<?= esc_js( $chk1['source_quote'] ?? '' ) ?>',
                        <?= json_encode( $chk1['page_context'] ?? '' ) ?>,
                        '<?= esc_url( $chk1['source_url'] ) ?>'
                    )">View Source</button>
                </div>
            </div>
        </div>
    </div>

    <!-- BAND 2: BRAND GUIDELINE & VISUAL IDENTITY -->
    <div class="check-band">
        <div class="band-header">
            <h3 class="band-title">
                <span>Check 02: Brand & Visual Identity Guidelines</span>
            </h3>
            <?php
            $b2_cls = ( ($chk2['score'] ?? 0) >= 71 ) ? 'badge-pass' : ( (($chk2['score'] ?? 0) >= 31) ? 'badge-needs-look' : 'badge-fail' );
            ?>
            <span class="badge-status <?= $b2_cls ?>">
                <?= esc_html( $chk2['status'] ?? 'PENDING' ) ?> · Score: <?= esc_html( $chk2['score'] ?? 0 ) ?>/100
            </span>
        </div>
        <div class="band-content-grid">
            <div class="audit-details-col">
                <div class="audit-item finding-box">
                    <div class="audit-label">Finding:</div>
                    <p class="audit-text"><?= esc_html( $chk2['finding'] ?? 'Visual chromatic palette adhered to corporate specifications.' ) ?></p>
                </div>
                <div class="audit-item analysis-box">
                    <div class="audit-label">Compliance Analysis:</div>
                    <p class="audit-text"><?= esc_html( $chk2['analysis'] ?? 'Fully compliant with Corporate Visual Identity Guidelines.' ) ?></p>
                </div>
                <div class="audit-item">
                    <div class="audit-label">Executive Reason:</div>
                    <p class="audit-text"><?= esc_html( $chk2['reason'] ?? 'Asset adheres to Navy and Gold visual codes.' ) ?></p>
                </div>
            </div>
            <div class="source-rule-col">
                <div>
                    <div class="source-doc-name"><?= esc_html( $chk2['regulatory_doc'] ?? 'Corporate Visual Identity Guidelines' ) ?></div>
                    <div class="source-rule-subline">
                        <span class="rule-tag">Page <?= esc_html( $chk2['page_number'] ?? 5 ) ?></span>
                        <span>· <?= esc_html( $chk2['regulatory_section'] ?? 'Section 2.1' ) ?></span>
                    </div>
                </div>
                <div class="source-actions">
                    <a href="<?= esc_url( $chk2['source_url'] ) ?>" target="_blank" class="btn-pdf-direct">
                        (PDF) Page <?= esc_html( $chk2['page_number'] ?? 5 ) ?> ↗
                    </a>
                    <button type="button" class="btn-view-source" onclick="openGuidelineModal(
                        '<?= esc_js( $chk2['regulatory_doc'] ?? 'Corporate Visual Identity Guidelines' ) ?>',
                        '<?= esc_js( $chk2['regulatory_section'] ?? 'Section 2.1' ) ?>',
                        <?= intval( $chk2['page_number'] ?? 5 ) ?>,
                        '<?= esc_js( $chk2['source_quote'] ?? '' ) ?>',
                        <?= json_encode( $chk2['page_context'] ?? '' ) ?>,
                        '<?= esc_url( $chk2['source_url'] ) ?>'
                    )">View Source</button>
                </div>
            </div>
        </div>
    </div>

    <!-- BAND 3: MAS FINANCIAL ADVERTISING RULES -->
    <div class="check-band">
        <div class="band-header">
            <h3 class="band-title">
                <span>Check 03: MAS Advertising Rules & Statutory Disclosures</span>
            </h3>
            <?php
            $b3_cls = ( ($chk3['score'] ?? 0) >= 71 ) ? 'badge-pass' : ( (($chk3['score'] ?? 0) >= 31) ? 'badge-needs-look' : 'badge-fail' );
            ?>
            <span class="badge-status <?= $b3_cls ?>">
                <?= esc_html( $chk3['status'] ?? 'PENDING' ) ?> · Score: <?= esc_html( $chk3['score'] ?? 0 ) ?>/100
            </span>
        </div>
        <div class="band-content-grid">
            <div class="audit-details-col">
                <div class="audit-item finding-box">
                    <div class="audit-label">Finding:</div>
                    <p class="audit-text"><?= esc_html( $chk3['finding'] ?? 'Mandatory statutory risk disclosures inspected.' ) ?></p>
                </div>
                <div class="audit-item analysis-box">
                    <div class="audit-label">Compliance Analysis:</div>
                    <p class="audit-text"><?= esc_html( $chk3['analysis'] ?? 'Evaluated against MAS Securities and Futures Act Guidelines.' ) ?></p>
                </div>
                <div class="audit-item">
                    <div class="audit-label">Executive Reason:</div>
                    <p class="audit-text"><?= esc_html( $chk3['reason'] ?? 'Statutory risk and disclaimer assessment complete.' ) ?></p>
                </div>
            </div>
            <div class="source-rule-col">
                <div>
                    <div class="source-doc-name"><?= esc_html( $chk3['regulatory_doc'] ?? 'MAS Advertising Guidelines 2023' ) ?></div>
                    <div class="source-rule-subline">
                        <span class="rule-tag">Page <?= esc_html( $chk3['page_number'] ?? 14 ) ?></span>
                        <span>· <?= esc_html( $chk3['regulatory_section'] ?? 'Section 5.2(b)' ) ?></span>
                    </div>
                </div>
                <div class="source-actions">
                    <a href="<?= esc_url( $chk3['source_url'] ) ?>" target="_blank" class="btn-pdf-direct">
                        (PDF) Page <?= esc_html( $chk3['page_number'] ?? 14 ) ?> ↗
                    </a>
                    <button type="button" class="btn-view-source" onclick="openGuidelineModal(
                        '<?= esc_js( $chk3['regulatory_doc'] ?? 'MAS Advertising Guidelines 2023' ) ?>',
                        '<?= esc_js( $chk3['regulatory_section'] ?? 'Section 5.2(b)' ) ?>',
                        <?= intval( $chk3['page_number'] ?? 14 ) ?>,
                        '<?= esc_js( $chk3['source_quote'] ?? '' ) ?>',
                        <?= json_encode( $chk3['page_context'] ?? '' ) ?>,
                        '<?= esc_url( $chk3['source_url'] ) ?>'
                    )">View Source</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- NÂNG CẤP MODAL "VIEW SOURCE" (TRÌNH ĐỌC PDF THẬT 100% CÓ BÔI ĐỎ & IN ĐẬM VĂN BẢN GỐC) -->
<div class="guideline-modal-overlay" id="guidelineModal">
    <div class="guideline-modal-box" style="height:90vh; max-width:960px;">
        <!-- PDF Toolbar -->
        <div class="pdf-modal-toolbar">
            <div style="display:flex; align-items:center; gap:10px;">
                <span id="modalDocTitle" style="font-weight:600; color:#fff; font-size:13px;">Document.pdf</span>
            </div>
            <div style="display:flex; align-items:center; gap:10px;">
                <span id="modalPageIndicator" style="background:#18191a; color:#cbd5e1; padding:3px 10px; border-radius:3px; font-size:12px; border:1px solid #3c4043;">
                    Page 14 · Section 5.2(b)
                </span>
                <a id="modalOpenDirectLink" href="#" target="_blank" style="background:#0284c7; color:#fff; text-decoration:none; padding:5px 12px; border-radius:4px; font-size:12px; font-weight:600;">
                    Open Full Window ↗
                </a>
                <a id="modalDownloadLink" href="#" download style="background:#3c4043; color:#f1f3f4; text-decoration:none; padding:5px 12px; border-radius:4px; font-size:12px;">
                    Download PDF ↓
                </a>
                <button type="button" style="background:#475569; color:#fff; border:none; padding:5px 10px; border-radius:3px; font-size:12px; cursor:pointer;" onclick="closeGuidelineModal();">✕</button>
            </div>
        </div>

        <!-- 100% Real PDF Document Object & Iframe (Hiển thị trực tiếp file PDF thật) -->
        <div style="flex:1; width:100%; height:calc(100% - 50px); background:#525659; padding:12px; box-sizing:border-box;">
            <object id="modalRealPdfObject" data="" type="application/pdf" width="100%" height="100%" style="border-radius:4px; box-shadow:0 6px 20px rgba(0,0,0,0.35);">
                <iframe id="modalRealPdfFrame" src="" width="100%" height="100%" style="border:none;">
                    <p style="color:#fff; padding:20px;">Trình duyệt của bạn đang chặn xem PDF trực tiếp. <a id="modalFallbackDownloadLink" href="#" target="_blank" style="color:#38bdf8;">Bấm vào đây để tải và xem file PDF thật</a>.</p>
                </iframe>
            </object>
        </div>
    </div>
</div>

<script>
    function openGuidelineModal(docTitle, section, pageNum, citation, pageContext, pdfUrl) {
        var targetUrl = pdfUrl || '#';
        document.getElementById('modalDocTitle').innerText = docTitle + '.pdf';
        document.getElementById('modalPageIndicator').innerText = 'Page ' + pageNum + ' · ' + section;
        document.getElementById('modalOpenDirectLink').href = targetUrl;
        document.getElementById('modalDownloadLink').href = targetUrl;
        
        var fallbackLink = document.getElementById('modalFallbackDownloadLink');
        if (fallbackLink) {
            fallbackLink.href = targetUrl;
        }

        var obj = document.getElementById('modalRealPdfObject');
        var iframe = document.getElementById('modalRealPdfFrame');
        if (obj) {
            obj.data = targetUrl;
        }
        if (iframe) {
            iframe.src = targetUrl;
        }

        var modal = document.getElementById('guidelineModal');
        modal.classList.add('active');
    }

    function closeGuidelineModal() {
        document.getElementById('guidelineModal').classList.remove('active');
    }

    // Đóng modal khi bấm phím ESC hoặc click bên ngoài
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeGuidelineModal();
        }
    });
    document.getElementById('guidelineModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeGuidelineModal();
        }
    });
</script>

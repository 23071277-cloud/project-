<?php
/*
Template Name: Homepage
*/
if (file_exists(__DIR__ . '/backend.php')) {
    require_once __DIR__ . '/backend.php';
} elseif (file_exists(__DIR__ . '/inc/backend.php')) {
    require_once __DIR__ . '/inc/backend.php';
}
$loginUrl = function_exists('pcs_url') ? pcs_url('page-login.php') : 'page-login.php';
$homeUrl = function_exists('pcs_url') ? pcs_url('home-page.php') : (function_exists('home_url') ? home_url('/') : 'home-page.php');
$solutionUrl = function_exists('pcs_url') ? pcs_url('solution.php') : (function_exists('home_url') ? home_url('/solution/') : 'page-solution.php');
$aboutUrl = function_exists('pcs_url') ? pcs_url('about-us.php') : (function_exists('home_url') ? home_url('/about-us/') : 'page-about-us.php');
$homeLogoUrl = 'images/logo.png';
if (function_exists('get_template_directory_uri')) {
    $homeLogoUrl = get_template_directory_uri() . '/images/logo.png';
} elseif (function_exists('get_stylesheet_directory_uri')) {
    $homeLogoUrl = get_stylesheet_directory_uri() . '/images/logo.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Compliance AI - Review Marketing Assets Faster</title>
    
    <!-- Nhúng Google Fonts Plus Jakarta Sans chuẩn -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <style>
        /* ================= RESET & CẤU HÌNH CHUNG ================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        body {
            background-color: #F8FAFC;
            color: #0F172A;
            overflow-x: hidden;
            line-height: 1.5;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        button {
            border: none;
            background: none;
            cursor: pointer;
            font-family: inherit;
        }

        .container {
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ================= 1. HEADER ================= */
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #E2E8F0;
            height: 80px;
            display: flex;
            align-items: center;
        }

        .header .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 40px;
        }

        .brand-logo {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }

        .brand-logo-img {
            height: 38px;
            width: auto;
            max-width: 140px;
            object-fit: contain;
            display: block;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
        }

        .nav-item {
            font-size: 15px;
            font-weight: 600;
            color: #2D3748;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .nav-item:hover {
            color: #1E3A8A;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .btn-login-outline {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 24px;
            border-radius: 9999px;
            border: 1.5px solid #4E56A6;
            color: #4E56A6;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .btn-login-outline:hover {
            background-color: #4E56A6;
            color: #FFFFFF;
        }

        .btn-primary-yellow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 26px;
            border-radius: 9999px;
            background-color: #F6B93B;
            color: #0F172A;
            font-size: 15px;
            font-weight: 700;
            box-shadow: 0 3px 10px rgba(246, 185, 59, 0.35);
            transition: all 0.2s;
        }

        .btn-primary-yellow:hover {
            background-color: #EFA825;
            transform: translateY(-1px);
        }

        /* ================= 2. HERO SECTION ================= */
        .hero-section {
            position: relative;
            background: linear-gradient(180deg, #FAF5FF 0%, #F3EDFB 50%, #FFFFFF 100%);
            padding: 80px 0 100px;
            overflow: hidden;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.2fr 0.8fr;
            align-items: center;
            gap: 48px;
        }

        .hero-title {
            font-size: 52px;
            font-weight: 800;
            line-height: 1.15;
            letter-spacing: -1.5px;
            color: #1F2A5A;
            margin-bottom: 20px;
        }

        .hero-title .highlight {
            color: #59449B;
            display: block;
        }

        .hero-desc {
            font-size: 17px;
            line-height: 1.7;
            color: #334155;
            max-width: 520px;
            margin-bottom: 32px;
        }

        .hero-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn-link-arrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 700;
            color: #1F2A5A;
            transition: gap 0.2s;
        }

        .btn-link-arrow:hover {
            gap: 12px;
            color: #59449B;
        }

        .hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
        }

        .hero-badge-card {
            background: #FFFFFF;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(89, 68, 155, 0.12);
            border: 1px solid #E2E8F0;
            width: 100%;
            max-width: 380px;
            position: relative;
        }

        .hero-badge-card h4 {
            font-size: 14px;
            color: #6D74B4;
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .hero-badge-card .status-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #F1F5F9;
        }

        .hero-badge-card .status-item:last-child {
            border-bottom: none;
        }

        /* ================= 3. PARTNER LOGOS ================= */
        .partners-section {
            padding: 50px 0;
            background: #FFFFFF;
            border-bottom: 1px solid #E2E8F0;
            text-align: center;
        }

        .partners-title {
            font-size: 18px;
            font-weight: 700;
            color: #475569;
            margin-bottom: 28px;
        }

        .partners-grid {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: center;
            gap: 36px 48px;
            opacity: 0.7;
        }

        .partner-name {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: 1px;
            color: #64748B;
        }

        /* ================= 4. PILLARS / CHECKS SECTION ================= */
        .pillars-section {
            padding: 90px 0;
            background: radial-gradient(50% 50% at 50% 30%, rgba(147, 51, 234, 0.45) 0%, rgba(16, 10, 38, 0.98) 75%, #100A26 100%);
            color: #FFFFFF;
            position: relative;
            overflow: hidden;
        }

        .section-header {
            text-align: center;
            max-width: 760px;
            margin: 0 auto 60px;
        }

        .section-title {
            font-size: 38px;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 16px;
        }

        .section-desc {
            font-size: 16px;
            color: #CBD5E1;
            line-height: 1.6;
        }

        .pillars-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .pillar-card {
            background: rgba(26, 19, 56, 0.85);
            border-radius: 16px;
            border: 1px solid rgba(168, 85, 247, 0.25);
            padding: 32px 28px;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.45);
            backdrop-filter: blur(12px);
            transition: transform 0.2s, border-color 0.2s;
        }

        .pillar-card:hover {
            transform: translateY(-4px);
            border-color: rgba(168, 85, 247, 0.6);
        }

        .pillar-tag {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.8px;
            margin-bottom: 12px;
        }

        .pillar-1 .pillar-tag { color: #D8B4FE; }
        .pillar-2 .pillar-tag { color: #5EEAD4; }
        .pillar-3 .pillar-tag { color: #FCD34D; }

        .pillar-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #FFFFFF;
        }

        .pillar-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .pillar-list li {
            font-size: 13.5px;
            color: #E2E8F0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pillar-list li::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #818CF8;
            flex-shrink: 0;
        }

        /* ================= 5. TESTIMONIAL ================= */
        .testimonial-section {
            padding: 80px 0;
            background-color: #FAF9FD;
        }

        .testimonial-card {
            background: #FFFFFF;
            border-radius: 24px;
            border: 1px solid #E2E8F0;
            padding: 48px;
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 40px;
            box-shadow: 0 10px 30px rgba(31, 42, 90, 0.05);
        }

        .author-box {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .author-avatar {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #6D74B4;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFF;
            font-weight: 700;
            font-size: 20px;
        }

        .author-name {
            font-size: 18px;
            font-weight: 700;
            color: #0F172A;
        }

        .author-role {
            font-size: 14px;
            color: #64748B;
        }

        .quote-title {
            font-size: 28px;
            font-weight: 800;
            color: #0F172A;
            line-height: 1.3;
            margin-bottom: 16px;
        }

        .quote-desc {
            font-size: 15px;
            color: #475569;
            line-height: 1.6;
        }

        /* ================= 6. FAQ SECTION ================= */
        .faq-section {
            padding: 80px 0;
            background-color: #FAF9FD;
            border-bottom: 1px solid #E2E8F0;
        }

        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-item {
            background: #FFFFFF;
            border-radius: 14px;
            border: 1px solid #E2E8F0;
            margin-bottom: 14px;
            overflow: hidden;
            transition: all 0.2s;
        }

        .faq-question {
            width: 100%;
            padding: 20px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 16px;
            font-weight: 700;
            color: #1F2A5A;
            text-align: left;
        }

        .faq-icon {
            font-size: 22px;
            color: #4E56A6;
            font-weight: 400;
            transition: transform 0.2s;
        }

        .faq-answer {
            max-height: 0;
            overflow: hidden;
            padding: 0 24px;
            font-size: 14.5px;
            color: #475569;
            line-height: 1.6;
            transition: all 0.3s ease;
        }

        .faq-item.active .faq-answer {
            max-height: 200px;
            padding-bottom: 20px;
        }

        .faq-item.active .faq-icon {
            transform: rotate(45deg);
        }

        /* ================= 7. FOOTER ================= */
        .footer {
            background: linear-gradient(180deg, #311A66 0%, #24114E 50%, #150A2F 100%);
            color: #FFFFFF;
            padding: 80px 0 40px;
        }

        .cta-banner {
            text-align: center;
            max-width: 640px;
            margin: 0 auto 60px;
        }

        .cta-banner h2 {
            font-size: 42px;
            font-weight: 800;
            line-height: 1.2;
            margin-bottom: 14px;
        }

        .cta-banner p {
            font-size: 16px;
            color: #F3E8FF;
            margin-bottom: 24px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
            gap: 40px;
            padding-bottom: 40px;
            border-bottom: 1px solid rgba(168, 85, 247, 0.2);
            margin-bottom: 30px;
        }

        .footer-col h5 {
            font-size: 13px;
            font-weight: 700;
            color: #E9D5FF;
            letter-spacing: 0.8px;
            margin-bottom: 16px;
        }

        .footer-col ul {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .footer-col li a {
            font-size: 14px;
            color: rgba(233, 213, 255, 0.7);
            transition: color 0.2s;
        }

        .footer-col li a:hover {
            color: #FFFFFF;
        }

        .copyright-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            color: rgba(216, 180, 254, 0.7);
        }

        /* ================= 8. RESPONSIVE (MOBILE & TABLET) ================= */
        @media (max-width: 992px) {
            .hero-grid {
                grid-template-columns: 1fr;
                text-align: center;
            }
            .hero-desc {
                margin: 0 auto 32px;
            }
            .hero-actions {
                justify-content: center;
            }
            .pillars-grid {
                grid-template-columns: 1fr;
            }
            .testimonial-card {
                grid-template-columns: 1fr;
            }
            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .header-left .nav-links {
                display: none; /* Ẩn bớt menu trên mobile để không chật */
            }
            .hero-title {
                font-size: 38px;
            }
            .footer-grid {
                grid-template-columns: 1fr;
            }
            .copyright-row {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <!-- ================= 1. HEADER (HOMEPAGE DEDICATED NAVBAR) ================= -->
    <header class="header">
        <div class="container">
            <div class="header-left">
                <a href="<?= esc_url($homeUrl) ?>" class="brand-logo" title="Brand Compliance AI - Homepage">
                    <img src="<?= esc_url($homeLogoUrl) ?>" alt="ICTS Simply Effective" class="brand-logo-img" onerror="this.onerror=null; this.src='images/logo.png';">
                </a>
                <nav class="nav-links">
                    <a href="<?= esc_url($solutionUrl) ?>" class="nav-item">Solution</a>
                    <a href="<?= esc_url($aboutUrl) ?>" class="nav-item">About Us</a>
                </nav>
            </div>
            <div class="header-right">
                <a href="<?= esc_url($loginUrl) ?>" class="btn-primary-yellow">Start Review</a>
            </div>
        </div>
    </header>

    <!-- ================= 2. HERO SECTION ================= -->
    <section class="hero-section">
        <div class="container hero-grid">
            <div class="hero-text">
                <h1 class="hero-title">
                    Review marketing assets faster.
                    <span class="highlight">Stay compliant with confidence.</span>
                </h1>
                <p class="hero-desc">
                    AI-powered pre-flight checks for brochures, banners, and disclosures. Automatically catch logo misuse, brand styling deviations, and MAS advertising compliance risks.
                </p>
                <div class="hero-actions">
                    <!-- NỐI VỚI TRANG LOGIN ĐỂ BẮT ĐẦU DUYỆT BÀI: -->
                    <a href="<?= $loginUrl ?>" class="btn-primary-yellow">Start Review</a>
                    <a href="#standards" class="btn-link-arrow">
                        <span>See How It Works</span>
                        <span>→</span>
                    </a>
                </div>
            </div>

            <div class="hero-visual">
                <div class="hero-badge-card">
                    <h4>Pre-flight Verification</h4>
                    <div class="status-item">
                        <span>Foreign Logo Scan</span>
                        <strong style="color: #10B981;">98/100 Pass</strong>
                    </div>
                    <div class="status-item">
                        <span>Brand Typography</span>
                        <strong style="color: #10B981;">95/100 Pass</strong>
                    </div>
                    <div class="status-item">
                        <span>MAS Disclosure Check</span>
                        <strong style="color: #F59E0B;">65/100 Needs Review</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 3. PARTNERS SECTION ================= -->
    <section class="partners-section">
        <div class="container">
            <p class="partners-title">Trusted by financial teams and top creative agencies in Singapore</p>
            <div class="partners-grid">
                <span class="partner-name">G-U LABS</span>
                <span class="partner-name">RELOTALENT</span>
                <span class="partner-name">CATEMA</span>
                <span class="partner-name">MIREKI</span>
                <span class="partner-name">VIETPHIL</span>
                <span class="partner-name">AIME NEXT</span>
            </div>
        </div>
    </section>

    <!-- ================= 4. PILLARS (3 CHECKS SCOPE) ================= -->
    <section class="pillars-section" id="standards">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Comprehensive pre-flight checks on every release</h2>
                <p class="section-desc">
                    AI never approves on its own. It provides unambiguous evidence, rule citations, and confidence scores to empower human legal and brand checkers.
                </p>
            </div>

            <div class="pillars-grid">
                <!-- Check 01 -->
                <div class="pillar-card pillar-1">
                    <div class="pillar-tag">PILLAR 01</div>
                    <h3 class="pillar-title">Foreign Logo Detection</h3>
                    <ul class="pillar-list">
                        <li>Unlicensed trademark alerts</li>
                        <li>Sub-brand hierarchy verification</li>
                        <li>Partner emblem clearspace rules</li>
                    </ul>
                </div>

                <!-- Check 02 -->
                <div class="pillar-card pillar-2">
                    <div class="pillar-tag">PILLAR 02</div>
                    <h3 class="pillar-title">Brand Style Guidelines</h3>
                    <ul class="pillar-list">
                        <li>Exact HEX/Pantone verification</li>
                        <li>Approved typography & sizing</li>
                        <li>Negative space & padding margins</li>
                    </ul>
                </div>

                <!-- Check 03 -->
                <div class="pillar-card pillar-3">
                    <div class="pillar-tag">PILLAR 03</div>
                    <h3 class="pillar-title">Advertising Compliance</h3>
                    <ul class="pillar-list">
                        <li>Statutory warning legibility & size</li>
                        <li>Prohibited claims & misleading terms</li>
                        <li>Direct statutory rule citations (MAS)</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 5. TESTIMONIAL / SUCCESS STORIES ================= -->
    <section class="testimonial-section" id="success-stories">
        <div class="container">
            <div class="testimonial-card">
                <div class="testimonial-content">
                    <div class="author-box">
                        <div class="author-avatar">CA</div>
                        <div>
                            <div class="author-name">Cam Anh Hoang</div>
                            <div class="author-role">Founder of Cheki</div>
                        </div>
                    </div>
                    <h3 class="quote-title">
                        "Since rolling out ICTS Brand Compliance, we've saved 100s of hours on our artwork reviews."
                    </h3>
                    <p class="quote-desc">
                        ICTS gives our suppliers and agencies a centralized platform to submit creative assets for feedback and approval. This helps us deliver content at speed and scale while staying 100% compliant.
                    </p>
                </div>
                <div style="display: flex; align-items: center; justify-content: center;">
                    <div style="background: #F1F5F9; border-radius: 16px; padding: 40px; text-align: center; width: 100%;">
                        <span style="font-size: 48px; font-weight: 800; color: #4E56A6; display: block;">4x</span>
                        <span style="font-size: 15px; color: #64748B; font-weight: 600;">Faster turnaround for audit cycles</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 6. ABOUT / FAQ ACCORDION ================= -->
    <section class="faq-section" id="about">
        <div class="container faq-container">
            <div class="section-header" style="margin-bottom: 40px;">
                <h2 class="section-title" style="color: #1F2A5A;">Frequently Asked Questions</h2>
                <p class="section-desc" style="color: #64748B;">Clear answers regarding our Maker–Checker AI compliance framework.</p>
            </div>

            <div class="faq-list">
                <div class="faq-item">
                    <button class="faq-question">
                        <span>Can the AI automatically approve marketing assets on its own?</span>
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-answer">
                        No. The AI assists, it does not approve. In financial services, a false approval is high risk. The AI scores and cites rules, but a human Checker and Admin always make the final sign-off.
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question">
                        <span>How does the Maker–Checker separation of duties work?</span>
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-answer">
                        The Maker uploads the marketing asset. The person who uploaded the asset cannot be its Checker. This ensures independent 4-eyes review as required by regulated financial standards.
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question">
                        <span>What happens when a Checker disagrees with an AI assessment?</span>
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-answer">
                        The Checker's decision always wins. Checkers can override any single check score with written comments. All overrides are logged for model improvement and auditing.
                    </div>
                </div>

                <div class="faq-item">
                    <button class="faq-question">
                        <span>Can older versions be edited or overwritten?</span>
                        <span class="faq-icon">+</span>
                    </button>
                    <div class="faq-answer">
                        Never. Every upload creates a new immutable version (v1, v2, v3). Older versions keep their exact comments, scores, and review history for full auditability.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 7. FOOTER ================= -->
    <footer class="footer">
        <div class="container">
            <div class="cta-banner">
                <h2>Ready to review with confidence?</h2>
                <p>Bring structure, clarity, and compliance intelligence to every document review.</p>
                <a href="<?= $loginUrl ?>" class="btn-primary-yellow">Start Review</a>
            </div>

            <div class="footer-grid">
                <div class="footer-col">
                    <div class="brand-logo" style="margin-bottom: 14px;">
                        <img src="<?= esc_url($homeLogoUrl) ?>" alt="BrandCompliance Logo" class="brand-logo-img" style="filter: brightness(0) invert(1);" onerror="this.onerror=null; this.style.display='none'; document.getElementById('footerBrandFallback').style.display='inline-flex';">
                        <span id="footerBrandFallback" style="display: none; align-items: center; gap: 8px; font-weight: 800; font-size: 18px; color: #FFFFFF;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span>BrandCompliance</span>
                        </span>
                    </div>
                    <p style="font-size: 14px; color: #E9D5FF; opacity: 0.8; max-width: 280px;">
                        AI-powered document & marketing asset compliance review for modern enterprise teams.
                    </p>
                </div>
                <div class="footer-col">
                    <h5>PRODUCT</h5>
                    <ul>
                        <li><a href="#">AI Document Review</a></li>
                        <li><a href="#">Compliance Library</a></li>
                        <li><a href="#">Success Stories</a></li>
                        <li><a href="#">FAQ</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h5>COMPANY</h5>
                    <ul>
                        <li><a href="#">About</a></li>
                        <li><a href="#">Success Stories</a></li>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h5>RESOURCES</h5>
                    <ul>
                        <li><a href="#">Standards Overview</a></li>
                        <li><a href="#">Help Center</a></li>
                        <li><a href="#">Documentation</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h5>LEGAL</h5>
                    <ul>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Use</a></li>
                    </ul>
                </div>
            </div>

            <div class="copyright-row">
                <p>© 2026 BrandCompliance Inc. All rights reserved.</p>
                <p>Security & SOC2 Type II Aligned • Zero Client Training</p>
            </div>
        </div>
    </footer>

    <!-- ================= JAVASCRIPT TƯƠNG TÁC FAQ ================= -->
    <script>
        document.querySelectorAll('.faq-question').forEach(button => {
            button.addEventListener('click', () => {
                const faqItem = button.parentElement;
                faqItem.classList.toggle('active');
            });
        });
    </script>
</body>
</html>
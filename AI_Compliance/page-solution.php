<?php
/*
Template Name: Solution
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
    <title>Solution - Brand Compliance AI</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&family=Liberation+Mono:wght@400;700&display=swap" rel="stylesheet">

    <style>
        /* ================= RESET & CẤU HÌNH TOÀN CỤC ================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        body {
            background-color: #FAF8FF;
            color: #0F172A;
            overflow-x: hidden;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .container {
            max-width: 1240px;
            width: 100%;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* ================= 1. DEDICATED HOMEPAGE/SOLUTION NAVBAR ================= */
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(226, 232, 240, 0.85);
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
            color: #334155;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .nav-item:hover, .nav-item.active {
            color: #1F2674;
            font-weight: 700;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .btn-primary-yellow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 44px;
            padding: 10px 28px;
            border-radius: 9999px;
            background-color: #F6B93B;
            color: #0F172A;
            font-size: 15px;
            font-weight: 600;
            box-shadow: 0 2px 6px rgba(246, 185, 59, 0.35);
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary-yellow:hover {
            background-color: #EFA825;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(246, 185, 59, 0.45);
        }

        /* ================= 2. HERO SECTION ================= */
        .solution-hero {
            position: relative;
            background: linear-gradient(180deg, #1F2674 0%, #2A256B 70%, #3B2A8C 100%);
            padding: 72px 0 84px;
            color: #FFFFFF;
            overflow: hidden;
            text-align: center;
        }

        .hero-glow-1 {
            position: absolute;
            width: 380px;
            height: 380px;
            left: -120px;
            top: -120px;
            background: #4F57A7;
            opacity: 0.35;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
        }

        .hero-glow-2 {
            position: absolute;
            width: 320px;
            height: 320px;
            right: -60px;
            top: 40px;
            background: #9FA7FD;
            opacity: 0.25;
            border-radius: 50%;
            filter: blur(80px);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 2;
            max-width: 820px;
            margin: 0 auto;
        }

        .hero-title {
            font-size: 44px;
            font-weight: 700;
            line-height: 1.22;
            letter-spacing: -0.8px;
            margin-bottom: 16px;
        }

        .hero-title .highlight-purple {
            color: #C084FC;
            display: inline;
        }

        .hero-subtitle {
            font-size: 16px;
            line-height: 1.65;
            color: #DDE1FC;
            max-width: 620px;
            margin: 0 auto;
            font-weight: 400;
        }

        /* ================= 3. SECTION: CAPABILITIES ARCHITECTURE ================= */
        .capabilities-section {
            padding: 64px 0 48px;
        }

        .section-tag {
            text-align: center;
            color: #4F57A7;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }

        .section-heading {
            text-align: center;
            color: #1F2674;
            font-size: 28px;
            font-weight: 700;
            letter-spacing: -0.4px;
            margin-bottom: 40px;
        }

        .capabilities-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            max-width: 1040px;
            margin: 0 auto;
        }

        .capability-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            display: flex;
            align-items: flex-start;
            gap: 20px;
            transition: all 0.2s ease;
        }

        .capability-card:hover {
            box-shadow: 0 4px 14px rgba(31, 38, 116, 0.08);
            transform: translateY(-2px);
            border-color: #CBD5E1;
        }

        .capability-icon-box {
            width: 60px;
            height: 60px;
            background: #E4E7FF;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.03);
        }

        .capability-info {
            flex: 1;
        }

        .capability-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .capability-title {
            color: #1F2674;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.35;
        }

        .capability-code {
            color: #64748B;
            font-family: 'Liberation Mono', monospace;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .capability-desc {
            color: #464651;
            font-size: 13.5px;
            line-height: 1.55;
            font-weight: 400;
        }

        /* ================= 4. SECTION: SYSTEM OUTCOMES (STATS) ================= */
        .outcomes-section {
            padding: 20px 0 48px;
        }

        .outcomes-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            padding: 32px 28px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            max-width: 1040px;
            margin: 0 auto;
        }

        .outcomes-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .outcomes-tag {
            color: #4F57A7;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .outcomes-title {
            color: #1F2674;
            font-size: 20px;
            font-weight: 700;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            text-align: center;
        }

        .stat-item {
            padding: 8px 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .stat-number {
            color: #1F2674;
            font-size: 34px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .stat-label {
            color: #464651;
            font-size: 13.5px;
            font-weight: 500;
            line-height: 1.4;
            margin-bottom: 12px;
            min-height: 38px;
        }

        .stat-bar {
            width: 32px;
            height: 4px;
            border-radius: 2px;
        }

        .bar-yellow { background: #F5A623; }
        .bar-indigo { background: #4F57A7; }
        .bar-teal   { background: #14B8A6; }
        .bar-navy   { background: #1F2674; }

        /* ================= 5. SECTION: 4-LAYER INTEGRATION STACK ================= */
        .stack-section {
            padding: 24px 0 54px;
        }

        .stack-wrapper {
            background: rgba(233, 213, 255, 0.45);
            border: 1px solid #E9D5FF;
            border-radius: 28px;
            padding: 44px 36px;
            max-width: 1040px;
            margin: 0 auto;
        }

        .stack-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .stack-tag {
            color: #1F2674;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 6px;
        }

        .stack-title {
            color: #1F2674;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .stack-subtitle {
            color: #464651;
            font-size: 14px;
            font-weight: 400;
        }

        .stack-layers {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .layer-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            transition: all 0.2s ease;
        }

        .layer-card.highlight {
            background: #F6B93B;
            border-color: #EFA825;
            box-shadow: 0 4px 14px rgba(246, 185, 59, 0.25);
        }

        .layer-left {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .layer-num {
            font-size: 22px;
            font-weight: 800;
            color: #4F57A7;
            width: 32px;
            flex-shrink: 0;
        }

        .layer-card.highlight .layer-num {
            color: #1F2674;
        }

        .layer-title {
            font-size: 15.5px;
            font-weight: 700;
            color: #1F2674;
            margin-bottom: 3px;
        }

        .layer-card.highlight .layer-title {
            color: #0F172A;
        }

        .layer-desc {
            font-size: 12.5px;
            color: #464651;
            line-height: 1.45;
        }

        .layer-card.highlight .layer-desc {
            color: #1E293B;
        }

        .layer-badge {
            padding: 4px 10px;
            background: #EBEDFF;
            color: #1F2674;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.4px;
            border-radius: 6px;
            white-space: nowrap;
        }

        .layer-card.highlight .layer-badge {
            background: #1F2674;
            color: #FFFFFF;
        }

        /* ================= 6. SECTION: 4-STAGE DEPLOYMENT ROADMAP ================= */
        .roadmap-section {
            padding: 20px 0 54px;
        }

        .roadmap-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            max-width: 1040px;
            margin: 0 auto;
        }

        .roadmap-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 20px 18px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .roadmap-top {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }

        .roadmap-icon-box {
            width: 44px;
            height: 44px;
            background: #E4E7FF;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .roadmap-title {
            font-size: 15px;
            font-weight: 700;
            color: #1F2674;
            line-height: 1.25;
        }

        .roadmap-desc {
            font-size: 12.5px;
            color: #464651;
            line-height: 1.5;
        }

        /* ================= 7. SECTION: SOVEREIGN GUARANTEE HIGHLIGHT ================= */
        .guarantee-section {
            padding: 20px 0 54px;
        }

        .guarantee-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            padding: 36px 36px;
            box-shadow: 0 2px 8px rgba(31, 38, 116, 0.06);
            display: flex;
            align-items: center;
            gap: 32px;
            max-width: 1040px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
        }

        .guarantee-left-badge {
            width: 104px;
            height: 104px;
            background: #373E8C;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            flex-shrink: 0;
            box-shadow: 0 8px 20px rgba(55, 62, 140, 0.25);
        }

        .guarantee-sub-badge {
            position: absolute;
            bottom: -6px;
            right: -6px;
            width: 32px;
            height: 32px;
            background: #FFFFFF;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        }

        .guarantee-info {
            flex: 1;
        }

        .guarantee-top-pills {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .pill-blue {
            padding: 3px 10px;
            background: #E4E7FF;
            color: #1F2674;
            border-radius: 9999px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }

        .pill-teal {
            padding: 3px 10px;
            background: #003731;
            color: #FFFFFF;
            border-radius: 9999px;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.4px;
        }

        .guarantee-title {
            color: #1F2674;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .guarantee-desc {
            color: #464651;
            font-size: 14.5px;
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .guarantee-features {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .feature-chip {
            padding: 4px 12px;
            background: #F3F2FF;
            border-radius: 9999px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            font-weight: 700;
            color: #464651;
        }

        .chip-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .chip-dot.teal { background: #005048; }
        .chip-dot.indigo { background: #373E8C; }
        .chip-dot.blue { background: #4F57A7; }

        /* ================= 8. SECTION: CTA BANNER ================= */
        .cta-section {
            padding: 24px 0 64px;
        }

        .cta-box {
            background: linear-gradient(135deg, #1F2674 0%, #4F57A7 50%, #7C3AED 100%);
            border-radius: 24px;
            padding: 56px 32px;
            text-align: center;
            color: #FFFFFF;
            max-width: 1040px;
            margin: 0 auto;
            box-shadow: 0 10px 30px rgba(31, 38, 116, 0.2);
            position: relative;
            overflow: hidden;
        }

        .cta-title {
            font-size: 34px;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.5px;
        }

        .cta-subtitle {
            font-size: 15.5px;
            color: #DDE1FC;
            max-width: 580px;
            margin: 0 auto 28px;
            line-height: 1.6;
        }

        /* ================= 9. FOOTER ================= */
        .footer {
            background: #110B29;
            color: #FFFFFF;
            padding: 40px 0 24px;
            margin-top: auto;
        }

        .footer-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13.5px;
            color: #A78BFA;
        }

        .footer-brand {
            font-weight: 700;
            color: #FFFFFF;
        }

        /* ================= 10. RESPONSIVE ================= */
        @media (max-width: 960px) {
            .capabilities-grid, .stats-grid, .roadmap-grid {
                grid-template-columns: 1fr;
            }
            .guarantee-card {
                flex-direction: column;
                text-align: center;
            }
            .guarantee-top-pills, .guarantee-features {
                justify-content: center;
            }
            .header-left {
                gap: 20px;
            }
            .hero-title {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>

    <!-- ================= 1. DEDICATED HEADER / NAVBAR ================= -->
    <header class="header">
        <div class="container">
            <div class="header-left">
                <a href="<?= esc_url($homeUrl) ?>" class="brand-logo" title="Brand Compliance AI - Homepage">
                    <img src="<?= esc_url($homeLogoUrl) ?>" alt="ICTS Simply Effective" class="brand-logo-img" onerror="this.onerror=null; this.src='images/logo.png';">
                </a>
                <nav class="nav-links">
                    <a href="<?= esc_url($solutionUrl) ?>" class="nav-item active">Solution</a>
                    <a href="<?= esc_url($aboutUrl) ?>" class="nav-item">About Us</a>
                </nav>
            </div>
            <div class="header-right">
                <a href="<?= esc_url($loginUrl) ?>" class="btn-primary-yellow">Start Review</a>
            </div>
        </div>
    </header>

    <!-- ================= 2. HERO SECTION ================= -->
    <section class="solution-hero">
        <div class="hero-glow-1"></div>
        <div class="hero-glow-2"></div>
        <div class="container hero-content">
            <h1 class="hero-title">
                Boost your competitiveness <br/>
                <span class="highlight-purple">with AI applications</span>
            </h1>
            <p class="hero-subtitle">
                Adopting AI in your business can save you up to 50% of operation costs and increase at least 30% in your net profit.
            </p>
        </div>
    </section>

    <!-- ================= 3. CAPABILITIES ARCHITECTURE SECTION ================= -->
    <section class="capabilities-section">
        <div class="container">
            <div class="section-tag">CAPABILITIES ARCHITECTURE</div>
            <h2 class="section-heading">What We Deliver</h2>

            <div class="capabilities-grid">
                <!-- Block 01 -->
                <div class="capability-card">
                    <div class="capability-icon-box">
                        <svg width="22" height="27" viewBox="0 0 22 27" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M10.6667 26.6667C7.57778 25.8889 5.02778 24.1167 3.01667 21.35C1.00556 18.5833 0 15.5111 0 12.1333V4L10.6667 0L21.3333 4V12.1333C21.3333 15.5111 20.3278 18.5833 18.3167 21.35C16.3056 24.1167 13.7556 25.8889 10.6667 26.6667V26.6667M10.6667 23.8667C12.9778 23.1333 14.8889 21.6667 16.4 19.4667C17.9111 17.2667 18.6667 14.8222 18.6667 12.1333V5.83333L10.6667 2.83333L2.66667 5.83333V12.1333C2.66667 14.8222 3.42222 17.2667 4.93333 19.4667C6.44444 21.6667 8.35556 23.1333 10.6667 23.8667V23.8667M8 18.6667H13.3333C13.7111 18.6667 14.0278 18.5389 14.2833 18.2833C14.5389 18.0278 14.6667 17.7111 14.6667 17.3333V13.3333C14.6667 12.9556 14.5389 12.6389 14.2833 12.3833C14.0278 12.1278 13.7111 12 13.3333 12V10.6667C13.3333 9.93333 13.0722 9.30556 12.55 8.78333C12.0278 8.26111 11.4 8 10.6667 8C9.93333 8 9.30556 8.26111 8.78333 8.78333C8.26111 9.30556 8 9.93333 8 10.6667V12C7.62222 12 7.30556 12.1278 7.05 12.3833C6.79444 12.6389 6.66667 12.9556 6.66667 13.3333V17.3333C6.66667 17.7111 6.79444 18.0278 7.05 18.2833C7.30556 18.5389 7.62222 18.6667 8 18.6667M9.33333 12V10.6667C9.33333 10.2889 9.46111 9.97222 9.71667 9.71667C9.97222 9.46111 10.2889 9.33333 10.6667 9.33333C11.0444 9.33333 11.3611 9.46111 11.6167 9.71667C11.8722 9.97222 12 10.2889 12 10.6667V12H9.33333" fill="#373E8C"/>
                        </svg>
                    </div>
                    <div class="capability-info">
                        <div class="capability-header">
                            <span class="capability-title">Air-Gapped Foundation</span>
                            <span class="capability-code">01 // ISO</span>
                        </div>
                        <p class="capability-desc">Dedicated on-premises or VPC compute nodes guaranteeing zero asset sharing and full data perimeter sovereignty.</p>
                    </div>
                </div>

                <!-- Block 02 -->
                <div class="capability-card">
                    <div class="capability-icon-box">
                        <svg width="30" height="29" viewBox="0 0 32 31" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8 30.6667C6.88889 30.6667 5.94444 30.2778 5.16667 29.5C4.38889 28.7222 4 27.7778 4 26.6667C4 25.5556 4.38889 24.6111 5.16667 23.8333C5.94444 23.0556 6.88889 22.6667 8 22.6667C8.31111 22.6667 8.6 22.7 8.86667 22.7667C9.13333 22.8333 9.38889 22.9222 9.63333 23.0333L11.5333 20.6667C10.9111 19.9778 10.4778 19.2 10.2333 18.3333C9.98889 17.4667 9.93333 16.6 10.0667 15.7333L7.36667 14.8333C6.98889 15.3889 6.51111 15.8333 5.93333 16.1667C5.35556 16.5 4.71111 16.6667 4 16.6667C2.88889 16.6667 1.94444 16.2778 1.16667 15.5C0.388889 14.7222 0 13.7778 0 12.6667C0 11.5556 0.388889 10.6111 1.16667 9.83333C1.94444 9.05556 2.88889 8.66667 4 8.66667C5.11111 8.66667 6.05556 9.05556 6.83333 9.83333C7.61111 10.6111 8 11.5556 8 12.6667V12.9333L10.7 13.8667C11.1444 13.0667 11.7389 12.3889 12.4833 11.8333C13.2278 11.2778 14.0667 10.9222 15 10.7667V7.86667C14.1333 7.62222 13.4167 7.15 12.85 6.45C12.2833 5.75 12 4.93333 12 4C12 2.88889 12.3889 1.94444 13.1667 1.16667C13.9444 0.388889 14.8889 0 16 0C17.1111 0 18.0556 0.388889 18.8333 1.16667C19.6111 1.94444 20 2.88889 20 4C20 4.93333 19.7111 5.75 19.1333 6.45C18.5556 7.15 17.8444 7.62222 17 7.86667V10.7667C17.9333 10.9222 18.7722 11.2778 19.5167 11.8333C20.2611 12.3889 20.8556 13.0667 21.3 13.8667L24 12.9333V12.6667C24 11.5556 24.3889 10.6111 25.1667 9.83333C25.9444 9.05556 26.8889 8.66667 28 8.66667C29.1111 8.66667 30.0556 9.05556 30.8333 9.83333C31.6111 10.6111 32 11.5556 32 12.6667C32 13.7778 31.6111 14.7222 30.8333 15.5C30.0556 16.2778 29.1111 16.6667 28 16.6667C27.2889 16.6667 26.6389 16.5 26.05 16.1667C25.4611 15.8333 24.9889 15.3889 24.6333 14.8333L21.9333 15.7333C22.0667 16.6 22.0111 17.4611 21.7667 18.3167C21.5222 19.1722 21.0889 19.9556 20.4667 20.6667L22.3667 23C22.6111 22.8889 22.8667 22.8056 23.1333 22.75C23.4 22.6944 23.6889 22.6667 24 22.6667C25.1111 22.6667 26.0556 23.0556 26.8333 23.8333C27.6111 24.6111 28 25.5556 28 26.6667C28 27.7778 27.6111 28.7222 26.8333 29.5C26.0556 30.2778 25.1111 30.6667 24 30.6667C22.8889 30.6667 21.9444 30.2778 21.1667 29.5C20.3889 28.7222 20 27.7778 20 26.6667C20 26.2222 20.0722 25.7944 20.2167 25.3833C20.3611 24.9722 20.5556 24.6 20.8 24.2667L18.9 21.9C17.9889 22.4111 17.0167 22.6667 15.9833 22.6667C14.95 22.6667 13.9778 22.4111 13.0667 21.9L11.2 24.2667C11.4444 24.6 11.6389 24.9722 11.7833 25.3833C11.9278 25.7944 12 26.2222 12 26.6667C12 27.7778 11.6111 28.7222 10.8333 29.5C10.0556 30.2778 9.11111 30.6667 8 30.6667" fill="#373E8C"/>
                        </svg>
                    </div>
                    <div class="capability-info">
                        <div class="capability-header">
                            <span class="capability-title">Autonomous Workflow Mesh</span>
                            <span class="capability-code">02 // RT</span>
                        </div>
                        <p class="capability-desc">Multi-agent routing meshes that intercept and validate brand assets across Figma, DAM, and ad ad-servers instantly.</p>
                    </div>
                </div>

                <!-- Block 03 -->
                <div class="capability-card">
                    <div class="capability-icon-box">
                        <svg width="24" height="27" viewBox="0 0 24 27" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.86667 23.8C1.95556 22.7333 1.25 21.5389 0.75 20.2167C0.25 18.8944 0 17.4889 0 16C0 12.6667 1.16667 9.83333 3.5 7.5C5.83333 5.16667 8.66667 4 12 4C12.0444 4 12.0889 4 12.1333 4C12.1778 4 12.2222 4 12.2667 4L10.1333 1.86667L12 0L17.3333 5.33333L12 10.6667L10.1 8.76667L12.2 6.66667C12.1556 6.66667 12.1222 6.66667 12.1 6.66667C12.0778 6.66667 12.0444 6.66667 12 6.66667C9.42222 6.66667 7.22222 7.57778 5.4 9.4C3.57778 11.2222 2.66667 13.4222 2.66667 16C2.66667 17.1333 2.85 18.2 3.21667 19.2C3.58333 20.2 4.1 21.1 4.76667 21.9L2.86667 23.8M10.6667 23.3333C10.6667 22.8222 10.4944 22.3167 10.15 21.8167C9.80556 21.3167 9.42222 20.7944 9 20.25C8.57778 19.7056 8.19444 19.1389 7.85 18.55C7.50556 17.9611 7.33333 17.3333 7.33333 16.6667C7.33333 15.3778 7.78889 14.2778 8.7 13.3667C9.61111 12.4556 10.7111 12 12 12C13.2889 12 14.3889 12.4556 15.3 13.3667C16.2111 14.2778 16.6667 15.3778 16.6667 16.6667C16.6667 17.3333 16.4944 17.9611 16.15 18.55C15.8056 19.1389 15.4222 19.7056 15 20.25C14.5778 20.7944 14.1944 21.3167 13.85 21.8167C13.5056 22.3167 13.3333 22.8222 13.3333 23.3333H10.6667M10.6667 26.6667V24.6667H13.3333V26.6667H10.6667M21.1333 23.8L19.2333 21.9C19.9 21.1 20.4167 20.2 20.7833 19.2C21.15 18.2 21.3333 17.1333 21.3333 16C21.3333 14.5333 21.0278 13.1722 20.4167 11.9167C19.8056 10.6611 18.9667 9.61111 17.9 8.76667L19.8 6.86667C21.0889 7.97778 22.1111 9.31667 22.8667 10.8833C23.6222 12.45 24 14.1556 24 16C24 17.4889 23.75 18.8944 23.25 20.2167C22.75 21.5389 22.0444 22.7333 21.1333 23.8" fill="#373E8C"/>
                        </svg>
                    </div>
                    <div class="capability-info">
                        <div class="capability-header">
                            <span class="capability-title">Proprietary Fine-Tuning</span>
                            <span class="capability-code">03 // ADAPT</span>
                        </div>
                        <p class="capability-desc">Domain LoRA adaptations and customized token encoders trained strictly on your brand guidelines and legal mandates.</p>
                    </div>
                </div>

                <!-- Block 04 -->
                <div class="capability-card">
                    <div class="capability-icon-box">
                        <svg width="27" height="22" viewBox="0 0 27 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M7.76667 16.5L14.3667 9.9L12.4667 8L7.76667 12.7333L5.86667 10.8333L4 12.7333L7.76667 16.5M16.5333 16.5L18.6667 14.3667L20.8 16.5L22.6667 14.6333L20.5333 12.5L22.6667 10.3667L20.8 8.5L18.6667 10.6333L16.5333 8.5L14.6667 10.3667L16.8 12.5L14.6667 14.6333L16.5333 16.5M2.66667 21.3333C1.93333 21.3333 1.30556 21.0722 0.783333 20.55C0.261111 20.0278 0 19.4 0 18.6667V2.66667C0 1.93333 0.261111 1.30556 0.783333 0.783333C1.30556 0.261111 1.93333 0 2.66667 0H10.6667L13.3333 2.66667H24C24.7333 2.66667 25.3611 2.92778 25.8833 3.45C26.4056 3.97222 26.6667 4.6 26.6667 5.33333V18.6667C26.6667 19.4 26.4056 20.0278 25.8833 20.55C25.3611 21.0722 24.7333 21.3333 24 21.3333H2.66667V21.3333M2.66667 18.6667H24V5.33333H12.2333L9.56667 2.66667H2.66667V18.6667" fill="#373E8C"/>
                        </svg>
                    </div>
                    <div class="capability-info">
                        <div class="capability-header">
                            <span class="capability-title">Deterministic Guardrails</span>
                            <span class="capability-code">04 // GATE</span>
                        </div>
                        <p class="capability-desc">Binary rule evaluation trees eliminating hallucination risks across sensitive regulatory and IP disclosure bounds.</p>
                    </div>
                </div>

                <!-- Block 05 -->
                <div class="capability-card">
                    <div class="capability-icon-box">
                        <svg width="22" height="27" viewBox="0 0 22 27" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M8.73333 21.6L15.6333 13.3333H10.3L11.2667 5.76667L5.1 14.6667H9.73333L8.73333 21.6M5.33333 26.6667L6.66667 17.3333H0L12 0H14.6667L13.3333 10.6667H21.3333L8 26.6667H5.33333" fill="#373E8C"/>
                        </svg>
                    </div>
                    <div class="capability-info">
                        <div class="capability-header">
                            <span class="capability-title">Sub-40ms Inference</span>
                            <span class="capability-code">05 // PERF</span>
                        </div>
                        <p class="capability-desc">TensorRT-optimized neural verifiers deployed at the regional edge for real-time validation without authoring lag.</p>
                    </div>
                </div>

                <!-- Block 06 -->
                <div class="capability-card">
                    <div class="capability-icon-box">
                        <svg width="27" height="24" viewBox="0 0 27 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.66667 24L0 17.3333L6.66667 10.6667L8.56667 12.5333L5.1 16H25.3333V18.6667H5.1L8.56667 22.1333L6.66667 24M20 13.3333L18.1 11.4667L21.5667 8H1.33333V5.33333H21.5667L18.1 1.86667L20 0L26.6667 6.66667L20 13.3333" fill="#373E8C"/>
                        </svg>
                    </div>
                    <div class="capability-info">
                        <div class="capability-header">
                            <span class="capability-title">Seamless Legacy Connect</span>
                            <span class="capability-code">06 // SYNC</span>
                        </div>
                        <p class="capability-desc">Native bi-directional REST, GraphQL, and event-stream hooks into SAP, Adobe Experience Cloud, and Salesforce.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 4. SYSTEM OUTCOMES SECTION (STATS) ================= -->
    <section class="outcomes-section">
        <div class="container">
            <div class="outcomes-card">
                <div class="outcomes-header">
                    <div class="outcomes-tag">SYSTEM OUTCOMES</div>
                    <h3 class="outcomes-title">Measurable Returns from Day Zero</h3>
                </div>
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number">10x</div>
                        <div class="stat-label">Faster Workflow Execution</div>
                        <div class="stat-bar bar-yellow"></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">In-house Data Sovereignty</div>
                        <div class="stat-bar bar-indigo"></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">&lt; 40ms</div>
                        <div class="stat-label">Real-time Query Latency</div>
                        <div class="stat-bar bar-teal"></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">Zero</div>
                        <div class="stat-label">Third-party Training Leakage</div>
                        <div class="stat-bar bar-navy"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 5. THE 4-LAYER INTEGRATION STACK ================= -->
    <section class="stack-section">
        <div class="container">
            <div class="stack-wrapper">
                <div class="stack-header">
                    <div class="stack-tag">EXECUTION PIPELINE</div>
                    <h2 class="stack-title">The 4-Layer Foundation</h2>
                    <p class="stack-subtitle">Deterministic flow for every single creative evaluation.</p>
                </div>
                <div class="stack-layers">
                    <!-- Layer 01 -->
                    <div class="layer-card">
                        <div class="layer-left">
                            <span class="layer-num">01</span>
                            <div>
                                <h4 class="layer-title">Ingestion & Data Sanitation</h4>
                                <p class="layer-desc">Payload hashing, PII stripping, zero-trust perimeter tokenization.</p>
                            </div>
                        </div>
                        <span class="layer-badge">mTLS Handshake</span>
                    </div>

                    <!-- Layer 02 -->
                    <div class="layer-card">
                        <div class="layer-left">
                            <span class="layer-num">02</span>
                            <div>
                                <h4 class="layer-title">Policy & Compliance Verification</h4>
                                <p class="layer-desc">Semantic rule alignment, palette validation, legal trademark checks.</p>
                            </div>
                        </div>
                        <span class="layer-badge">Active Parser</span>
                    </div>

                    <!-- Layer 03 (Highlight) -->
                    <div class="layer-card highlight">
                        <div class="layer-left">
                            <span class="layer-num">03</span>
                            <div>
                                <h4 class="layer-title">Private Model Orchestration</h4>
                                <p class="layer-desc">Local multimodal inference within Nitro Enclaves or secure VMs.</p>
                            </div>
                        </div>
                        <span class="layer-badge">Hardware Enclave</span>
                    </div>

                    <!-- Layer 04 -->
                    <div class="layer-card">
                        <div class="layer-left">
                            <span class="layer-num">04</span>
                            <div>
                                <h4 class="layer-title">Immutable Audit & Release</h4>
                                <p class="layer-desc">Cryptographic signature appending, audit trail ledgering, final webhooks.</p>
                            </div>
                        </div>
                        <span class="layer-badge">Signed Verdict</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 6. 4-STAGE DEPLOYMENT ROADMAP ================= -->
    <section class="roadmap-section">
        <div class="container">
            <div class="section-tag">IMPLEMENTATION ROADMAP</div>
            <h2 class="section-heading">Seamless 4-Stage Deployment</h2>

            <div class="roadmap-grid">
                <!-- Step 01 -->
                <div class="roadmap-card">
                    <div class="roadmap-top">
                        <div class="roadmap-icon-box">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M5.5 14.5L12.5 12.5L14.5 5.5L7.5 7.5L5.5 14.5M10 11.5C9.58333 11.5 9.22917 11.3542 8.9375 11.0625C8.64583 10.7708 8.5 10.4167 8.5 10C8.5 9.58333 8.64583 9.22917 8.9375 8.9375C9.22917 8.64583 9.58333 8.5 10 8.5C10.4167 8.5 10.7708 8.64583 11.0625 8.9375C11.3542 9.22917 11.5 9.58333 11.5 10C11.5 10.4167 11.3542 10.7708 11.0625 11.0625C10.7708 11.3542 10.4167 11.5 10 11.5M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20M10 18C12.2167 18 14.1042 17.2208 15.6625 15.6625C17.2208 14.1042 18 12.2167 18 10C18 7.78333 17.2208 5.89583 15.6625 4.3375C14.1042 2.77917 12.2167 2 10 2C7.78333 2 5.89583 2.77917 4.3375 4.3375C2.77917 5.89583 2 7.78333 2 10C2 12.2167 2.77917 14.1042 4.3375 15.6625C5.89583 17.2208 7.78333 18 10 18" fill="#1F2674"/>
                            </svg>
                        </div>
                        <h4 class="roadmap-title">Discovery &<br/>Mapping</h4>
                    </div>
                    <p class="roadmap-desc">Full taxonomy inventory and legacy DAM connection topology analysis.</p>
                </div>

                <!-- Step 02 -->
                <div class="roadmap-card">
                    <div class="roadmap-top">
                        <div class="roadmap-icon-box">
                            <svg width="18" height="20" viewBox="0 0 18 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 20L0 15V5L9 0L18 5V15L9 20M6.1 7.25C6.48333 6.85 6.925 6.54167 7.425 6.325C7.925 6.10833 8.45 6 9 6C9.55 6 10.075 6.10833 10.575 6.325C11.075 6.54167 11.5167 6.85 11.9 7.25L14.9 5.575L9 2.3L3.1 5.575L6.1 7.25M8 17.15V13.875C7.1 13.6417 6.375 13.1667 5.825 12.45C5.275 11.7333 5 10.9167 5 10C5 9.81667 5.00833 9.64583 5.025 9.4875C5.04167 9.32917 5.075 9.16667 5.125 9L2 7.25V13.825L8 17.15M9 12C9.55 12 10.0208 11.8042 10.4125 11.4125C10.8042 11.0208 11 10.55 11 10C11 9.45 10.8042 8.97917 10.4125 8.5875C10.0208 8.19583 9.55 8 9 8C8.45 8 7.97917 8.19583 7.5875 8.5875C7.19583 8.97917 7 9.45 7 10C7 10.55 7.19583 11.0208 7.5875 11.4125C7.97917 11.8042 8.45 12 9 12M10 17.15L16 13.825V7.25L12.875 9C12.925 9.16667 12.9583 9.32917 12.975 9.4875C12.9917 9.64583 13 9.81667 13 10C13 10.9167 12.725 11.7333 12.175 12.45C11.625 13.1667 10.9 13.6417 10 13.875V17.15" fill="#1F2674"/>
                            </svg>
                        </div>
                        <h4 class="roadmap-title">Private Enclave<br/>Build</h4>
                    </div>
                    <p class="roadmap-desc">Zero-knowledge compute clusters stood up in your secure cloud footprint.</p>
                </div>

                <!-- Step 03 -->
                <div class="roadmap-card">
                    <div class="roadmap-top">
                        <div class="roadmap-icon-box">
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M8 18V12H10V14H18V16H10V18H8M0 16V14H6V16H0M4 12V10H0V8H4V6H6V12H4M8 10V8H18V10H8M12 6V0H14V2H18V4H14V6H12M0 4V2H10V4H0" fill="#1F2674"/>
                            </svg>
                        </div>
                        <h4 class="roadmap-title">Domain<br/>Adaptation</h4>
                    </div>
                    <p class="roadmap-desc">Fine-tuning rule checkpoints to legal precedents and style specifications.</p>
                </div>

                <!-- Step 04 -->
                <div class="roadmap-card">
                    <div class="roadmap-top">
                        <div class="roadmap-icon-box">
                            <svg width="16" height="20" viewBox="0 0 16 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M6.95 13.55L12.6 7.9L11.175 6.475L6.95 10.7L4.85 8.6L3.425 10.025L6.95 13.55M8 20C5.68333 19.4167 3.77083 18.0875 2.2625 16.0125C0.754167 13.9375 0 11.6333 0 9.1V3L8 0L16 3V9.1C16 11.6333 15.2458 13.9375 13.7375 16.0125C12.2292 18.0875 10.3167 19.4167 8 20M8 17.9C9.73333 17.35 11.1667 16.25 12.3 14.6C13.4333 12.95 14 11.1167 14 9.1V4.375L8 2.125L2 4.375V9.1C2 11.1167 2.56667 12.95 3.7 14.6C4.83333 16.25 6.26667 17.35 8 17.9" fill="#1F2674"/>
                            </svg>
                        </div>
                        <h4 class="roadmap-title">Continuous<br/>Governance</h4>
                    </div>
                    <p class="roadmap-desc">Live inline gateway activation with cryptographic telemetry dashboards.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 7. SOVEREIGN COMPLIANCE GUARANTEE SECTION ================= -->
    <section class="guarantee-section">
        <div class="container">
            <div class="guarantee-card">
                <div class="guarantee-left-badge">
                    <svg width="48" height="46" viewBox="0 0 50 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.1 47.25L12.825 40.05L4.725 38.25L5.5125 29.925L0 23.625L5.5125 17.325L4.725 9L12.825 7.2L17.1 0L24.75 3.2625L32.4 0L36.675 7.2L44.775 9L43.9875 17.325L49.5 23.625L43.9875 29.925L44.775 38.25L36.675 40.05L32.4 47.25L24.75 43.9875L17.1 47.25M19.0125 41.5125L24.75 39.0375L30.6 41.5125L33.75 36.1125L39.9375 34.65L39.375 28.35L43.5375 23.625L39.375 18.7875L39.9375 12.4875L33.75 11.1375L30.4875 5.7375L24.75 8.2125L18.9 5.7375L15.75 11.1375L9.5625 12.4875L10.125 18.7875L5.9625 23.625L10.125 28.35L9.5625 34.7625L15.75 36.1125L19.0125 41.5125M22.3875 31.6125L35.1 18.9L31.95 15.6375L22.3875 25.2L17.55 20.475L14.4 23.625L22.3875 31.6125" fill="white"/>
                    </svg>
                    <div class="guarantee-sub-badge">
                        <svg width="12" height="15" viewBox="0 0 12 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M1.5 15.75C1.0875 15.75 0.734375 15.6031 0.440625 15.3094C0.146875 15.0156 0 14.6625 0 14.25V6.75C0 6.3375 0.146875 5.98438 0.440625 5.69063C0.734375 5.39688 1.0875 5.25 1.5 5.25H2.25V3.75C2.25 2.7125 2.61562 1.82812 3.34687 1.09687C4.07812 0.365625 4.9625 0 6 0C7.0375 0 7.92188 0.365625 8.65312 1.09687C9.38437 1.82812 9.75 2.7125 9.75 3.75V5.25H10.5C10.9125 5.25 11.2656 5.39688 11.5594 5.69063C11.8531 5.98438 12 6.3375 12 6.75V14.25C12 14.6625 11.8531 15.0156 11.5594 15.3094C11.2656 15.6031 10.9125 15.75 10.5 15.75H1.5M1.5 14.25H10.5V6.75H1.5V14.25M6 12C6.4125 12 6.76562 11.8531 7.05937 11.5594C7.35312 11.2656 7.5 10.9125 7.5 10.5C7.5 10.0875 7.35312 9.73438 7.05937 9.44063C6.76562 9.14688 6.4125 9 6 9C5.5875 9 5.23438 9.14688 4.94063 9.44063C4.64688 9.73438 4.5 10.0875 4.5 10.5C4.5 10.9125 4.64688 11.2656 4.94063 11.5594C5.23438 11.8531 5.5875 12 6 12M3.75 5.25H8.25V3.75C8.25 3.125 8.03125 2.59375 7.59375 2.15625C7.15625 1.71875 6.625 1.5 6 1.5C5.375 1.5 4.84375 1.71875 4.40625 2.15625C3.96875 2.59375 3.75 3.125 3.75 3.75V5.25" fill="#1F2674"/>
                        </svg>
                    </div>
                </div>

                <div class="guarantee-info">
                    <div class="guarantee-top-pills">
                        <span class="pill-blue">Core Protocol v4.2</span>
                        <span class="pill-teal">Certified Zero Egress</span>
                    </div>
                    <h3 class="guarantee-title">The Sovereign Compliance Guarantee</h3>
                    <p class="guarantee-desc">Enterprise creative engines process millions of multi-modal assets monthly without a single byte leaving your designated cryptographic tenancy. Auditable proofs are generated inline for legal clearance.</p>
                    <div class="guarantee-features">
                        <span class="feature-chip">
                            <span class="chip-dot teal"></span>
                            <span>SOC2 Type II Ready</span>
                        </span>
                        <span class="feature-chip">
                            <span class="chip-dot indigo"></span>
                            <span>Zero Data Egress</span>
                        </span>
                        <span class="feature-chip">
                            <span class="chip-dot blue"></span>
                            <span>Multi-Model Routing</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 8. CTA BANNER SECTION ================= -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-box">
                <h2 class="cta-title">Ready to deploy sovereign AI?</h2>
                <p class="cta-subtitle">Connect directly with our solutions engineering team for architecture mapping and pre-flight validation workflows.</p>
                <a href="<?= esc_url($loginUrl) ?>" class="btn-primary-yellow">Start Review</a>
            </div>
        </div>
    </section>

    <!-- ================= 9. FOOTER ================= -->
    <footer class="footer">
        <div class="container footer-content">
            <span class="footer-brand">Brand Compliance AI</span>
            <span>&copy; <?= date('Y') ?> ICTS. All rights reserved. Automated pre-flight compliance & regulatory risk governance.</span>
        </div>
    </footer>

</body>
</html>

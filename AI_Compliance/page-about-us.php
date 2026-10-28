<?php
/*
Template Name: About Us
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
$heroImageUrl = 'images/about-hero.png';
if (function_exists('get_template_directory_uri')) {
    $homeLogoUrl = get_template_directory_uri() . '/images/logo.png';
    $heroImageUrl = get_template_directory_uri() . '/images/about-hero.png';
} elseif (function_exists('get_stylesheet_directory_uri')) {
    $homeLogoUrl = get_stylesheet_directory_uri() . '/images/logo.png';
    $heroImageUrl = get_stylesheet_directory_uri() . '/images/about-hero.png';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Brand Compliance AI</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

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

        /* ================= 1. DEDICATED HOMEPAGE/ABOUT US NAVBAR ================= */
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

        .btn-start-review {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #F6B93B;
            color: #1F2674;
            font-size: 14px;
            font-weight: 700;
            padding: 10px 24px;
            border-radius: 9999px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(246, 185, 59, 0.25);
            border: 1px solid rgba(246, 185, 59, 0.5);
        }

        .btn-start-review:hover {
            background-color: #E5A820;
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(246, 185, 59, 0.35);
        }

        /* ================= 2. HERO SECTION ================= */
        .hero-section {
            position: relative;
            padding: 50px 0 80px 0;
            background: linear-gradient(180deg, #FFFFFF 0%, #FAF8FF 100%);
            overflow: hidden;
            text-align: center;
        }

        .wave-divider {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            overflow: hidden;
            line-height: 0;
            pointer-events: none;
            opacity: 0.7;
        }

        .wave-divider svg {
            position: relative;
            display: block;
            width: 100%;
            height: 60px;
        }

        .ambient-glow {
            position: absolute;
            top: 10%;
            left: 50%;
            transform: translateX(-50%);
            width: 800px;
            height: 400px;
            background: radial-gradient(circle, rgba(79, 87, 167, 0.12) 0%, rgba(250, 248, 255, 0) 70%);
            filter: blur(50px);
            pointer-events: none;
            z-index: 0;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 980px;
            margin: 0 auto;
        }

        .editorial-overline {
            display: inline-flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .overline-divider {
            width: 40px;
            height: 1px;
            background-color: #4F57A7;
            display: inline-block;
        }

        .overline-text {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.15em;
            color: #4F57A7;
            text-transform: uppercase;
        }

        .hero-title {
            font-size: clamp(34px, 4.5vw, 56px);
            font-weight: 800;
            line-height: 1.15;
            color: #1F2674;
            letter-spacing: -0.025em;
            margin-bottom: 20px;
        }

        .hero-subtitle {
            font-size: 18px;
            line-height: 1.6;
            color: #475569;
            max-width: 740px;
            margin: 0 auto 40px auto;
        }

        /* Stats Strip */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 20px;
            padding: 24px 32px;
            box-shadow: 0 10px 30px -10px rgba(31, 38, 116, 0.08);
            margin-bottom: 50px;
        }

        .stat-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px 12px;
            border-right: 1px solid rgba(226, 232, 240, 0.7);
        }

        .stat-card:last-child {
            border-right: none;
        }

        .stat-value {
            font-size: clamp(26px, 2.5vw, 36px);
            font-weight: 800;
            color: #1F2674;
            line-height: 1.1;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .stat-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            color: #64748B;
            text-transform: uppercase;
            text-align: center;
            line-height: 1.4;
        }

        /* Hero Image Container */
        .hero-image-wrap {
            position: relative;
            max-width: 1040px;
            margin: 0 auto;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(31, 38, 116, 0.15), 0 0 0 1px rgba(79, 87, 167, 0.15);
            background: #FFFFFF;
        }

        .hero-image {
            width: 100%;
            height: auto;
            display: block;
            object-fit: cover;
        }

        /* ================= 3. OUR STORY & FOUNDATION SECTION ================= */
        .story-section {
            padding: 90px 0;
            background: #FAF8FF;
        }

        .section-header {
            text-align: center;
            max-width: 720px;
            margin: 0 auto 56px auto;
        }

        .section-tag {
            display: inline-block;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #4F57A7;
            background: rgba(79, 87, 167, 0.1);
            padding: 6px 16px;
            border-radius: 9999px;
            margin-bottom: 16px;
        }

        .section-heading {
            font-size: clamp(28px, 3.2vw, 40px);
            font-weight: 800;
            color: #1F2674;
            line-height: 1.25;
            letter-spacing: -0.02em;
            margin-bottom: 16px;
        }

        .section-desc {
            font-size: 16px;
            color: #64748B;
            line-height: 1.65;
        }

        .story-grid {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 48px;
            align-items: center;
            margin-bottom: 60px;
        }

        .story-text {
            font-size: 16px;
            line-height: 1.75;
            color: #334155;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .story-text p strong {
            color: #1F2674;
        }

        .story-callout {
            background: #FFFFFF;
            border-left: 4px solid #1F2674;
            padding: 28px 32px;
            border-radius: 0 16px 16px 0;
            box-shadow: 0 10px 25px -5px rgba(31, 38, 116, 0.05);
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            border-right: 1px solid rgba(226, 232, 240, 0.8);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }

        .callout-quote {
            font-size: 18px;
            font-weight: 600;
            color: #1F2674;
            line-height: 1.5;
            margin-bottom: 12px;
            font-style: italic;
        }

        .callout-author {
            font-size: 13px;
            font-weight: 700;
            color: #4F57A7;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        /* ================= 4. CORE VALUES SECTION ================= */
        .values-section {
            padding: 80px 0;
            background: #FFFFFF;
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .value-card {
            background: #FAF8FF;
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 20px;
            padding: 36px 30px;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .value-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 32px -8px rgba(31, 38, 116, 0.1);
            border-color: rgba(79, 87, 167, 0.3);
        }

        .value-icon-box {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            background: linear-gradient(135deg, #1F2674 0%, #4F57A7 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            margin-bottom: 24px;
            box-shadow: 0 8px 16px rgba(31, 38, 116, 0.2);
        }

        .value-title {
            font-size: 20px;
            font-weight: 700;
            color: #1F2674;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .value-desc {
            font-size: 15px;
            line-height: 1.65;
            color: #64748B;
        }

        /* ================= 5. ENGINEERING SANCTUARY / CAPABILITIES ================= */
        .capabilities-section {
            padding: 90px 0;
            background: #FAF8FF;
        }

        .caps-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 28px;
        }

        .cap-card {
            background: #FFFFFF;
            border: 1px solid rgba(226, 232, 240, 0.85);
            border-radius: 18px;
            padding: 32px;
            display: flex;
            gap: 20px;
            align-items: flex-start;
            transition: all 0.2s ease;
        }

        .cap-card:hover {
            border-color: rgba(79, 87, 167, 0.35);
            box-shadow: 0 12px 28px -6px rgba(31, 38, 116, 0.08);
        }

        .cap-number {
            font-size: 16px;
            font-weight: 800;
            color: #4F57A7;
            background: rgba(79, 87, 167, 0.1);
            padding: 8px 14px;
            border-radius: 10px;
            letter-spacing: -0.02em;
            line-height: 1;
            flex-shrink: 0;
        }

        .cap-content h4 {
            font-size: 18px;
            font-weight: 700;
            color: #1F2674;
            margin-bottom: 8px;
        }

        .cap-content p {
            font-size: 14.5px;
            line-height: 1.6;
            color: #64748B;
        }

        /* ================= 6. DUAL OFFICES / LOCATIONS SECTION ================= */
        .locations-section {
            padding: 90px 0;
            background: #FFFFFF;
            border-top: 1px solid rgba(226, 232, 240, 0.8);
        }

        .offices-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 36px;
        }

        .office-card {
            background: #FAF8FF;
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 20px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 10px 25px -5px rgba(31, 38, 116, 0.06);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .office-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px -8px rgba(31, 38, 116, 0.12);
            border-color: rgba(79, 87, 167, 0.3);
        }

        .office-card-header {
            padding: 28px 30px 20px 30px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
        }

        .office-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #1F2674;
            background: rgba(31, 38, 116, 0.08);
            padding: 4px 12px;
            border-radius: 9999px;
            margin-bottom: 12px;
        }

        .office-title {
            font-size: 22px;
            font-weight: 800;
            color: #1F2674;
            line-height: 1.25;
        }

        .office-details {
            padding: 24px 30px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            flex-grow: 1;
        }

        .detail-item {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .detail-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: rgba(79, 87, 167, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4F57A7;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .detail-info {
            font-size: 14.5px;
            line-height: 1.5;
            color: #334155;
        }

        .detail-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748B;
            margin-bottom: 2px;
        }

        .detail-value a {
            color: #1F2674;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .detail-value a:hover {
            color: #4F57A7;
            text-decoration: underline;
        }

        /* Map Embed Container */
        .map-container {
            width: 100%;
            height: 220px;
            background: #E2E8F0;
            position: relative;
            overflow: hidden;
            border-top: 1px solid rgba(226, 232, 240, 0.8);
        }

        .map-iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }

        .office-card-footer {
            padding: 18px 30px;
            background: #FFFFFF;
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .btn-map-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 13.5px;
            font-weight: 700;
            color: #1F2674;
            transition: color 0.2s ease;
        }

        .btn-map-link:hover {
            color: #4F57A7;
        }

        /* ================= 7. CTA BANNER SECTION ================= */
        .cta-section {
            padding: 70px 0;
            background: #FAF8FF;
        }

        .cta-box {
            background: linear-gradient(135deg, #1F2674 0%, #2A3396 100%);
            border-radius: 24px;
            padding: 60px 48px;
            text-align: center;
            color: #FFFFFF;
            box-shadow: 0 20px 40px -10px rgba(31, 38, 116, 0.3);
            position: relative;
            overflow: hidden;
        }

        .cta-box::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(246, 185, 59, 0.2) 0%, rgba(246, 185, 59, 0) 70%);
            pointer-events: none;
        }

        .cta-title {
            font-size: clamp(28px, 3.5vw, 40px);
            font-weight: 800;
            letter-spacing: -0.02em;
            margin-bottom: 16px;
        }

        .cta-subtitle {
            font-size: 16px;
            color: #E2E8F0;
            max-width: 640px;
            margin: 0 auto 32px auto;
            line-height: 1.6;
        }

        .btn-primary-yellow {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #F6B93B;
            color: #1F2674;
            font-size: 15px;
            font-weight: 700;
            padding: 14px 36px;
            border-radius: 9999px;
            text-decoration: none;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(246, 185, 59, 0.35);
        }

        .btn-primary-yellow:hover {
            background-color: #E5A820;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(246, 185, 59, 0.45);
        }

        /* ================= 8. FOOTER ================= */
        .footer {
            background-color: #FFFFFF;
            border-top: 1px solid rgba(226, 232, 240, 0.85);
            padding: 32px 0;
            margin-top: auto;
        }

        .footer-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 14px;
            color: #64748B;
        }

        .footer-brand {
            font-weight: 700;
            color: #1F2674;
        }

        /* ================= RESPONSIVE ================= */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 24px;
            }
            .stat-card:nth-child(2) {
                border-right: none;
            }
            .story-grid {
                grid-template-columns: 1fr;
            }
            .values-grid {
                grid-template-columns: 1fr;
            }
            .offices-grid {
                grid-template-columns: 1fr;
            }
            .caps-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .header-left {
                gap: 20px;
            }
            .nav-links {
                gap: 16px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
                padding: 20px;
            }
            .stat-card {
                border-right: none;
                border-bottom: 1px solid rgba(226, 232, 240, 0.7);
                padding: 16px 0;
            }
            .stat-card:last-child {
                border-bottom: none;
            }
            .footer-content {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <!-- ================= 1. DEDICATED HEADER NAVBAR ================= -->
    <header class="header">
        <div class="container">
            <div class="header-left">
                <a href="<?= esc_url($homeUrl) ?>" class="brand-logo" title="Back to Homepage">
                    <img src="<?= esc_url($homeLogoUrl) ?>" alt="ICTS Simply Effective" class="brand-logo-img" onerror="this.onerror=null; this.src='images/logo.png';">
                </a>
                <nav class="nav-links">
                    <a href="<?= esc_url($solutionUrl) ?>" class="nav-item">Solution</a>
                    <a href="<?= esc_url($aboutUrl) ?>" class="nav-item active">About Us</a>
                </nav>
            </div>
            <div class="header-right">
                <a href="<?= esc_url($loginUrl) ?>" class="btn-start-review">Start Review</a>
            </div>
        </div>
    </header>

    <!-- ================= 2. HERO SECTION ================= -->
    <section class="hero-section">
        <!-- SVG Bezier wave divider from Figma -->
        <div class="wave-divider">
            <svg viewBox="0 0 1530 126" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                <path d="M0 13.6464L51 27.2889C102 40.4214 204 68.4714 306 74.8464C408 81.2214 510 68.4714 612 47.6889C714 27.6714 816 -0.37863 918 0.00387048C1020 -0.37863 1122 27.6714 1224 47.6889C1326 68.4714 1428 81.2214 1479 88.4889L1530 95.2464V125.846H1479C1428 125.846 1326 125.846 1224 125.846C1122 125.846 1020 125.846 918 125.846C816 125.846 714 125.846 612 125.846C510 125.846 408 125.846 306 125.846C204 125.846 102 125.846 51 125.846H0V13.6464Z" fill="#FAF8FF"/>
            </svg>
        </div>

        <div class="ambient-glow"></div>

        <div class="container hero-content">
            <!-- Fluid Editorial Overline -->
            <div class="editorial-overline">
                <span class="overline-divider"></span>
                <span class="overline-text">ENGINEERING SANCTUARY &middot; EST. 2016</span>
                <span class="overline-divider"></span>
            </div>

            <!-- Hero Headline -->
            <h1 class="hero-title">Crafting certainty in code and human ambition.</h1>

            <!-- Subtitle -->
            <p class="hero-subtitle">
                A software boutique focused on cutting-edge technologies, cost-effective engineering, and genuine partnership across global frontiers.
            </p>

            <!-- Key Stat Highlights -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value">20+</div>
                    <div class="stat-label">Global Nations Served</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">98%</div>
                    <div class="stat-label">Sprint Predictability</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">120+</div>
                    <div class="stat-label">Shipped Enterprise Platforms</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">Top 3</div>
                    <div class="stat-label">GoodFirms Verified Leader</div>
                </div>
            </div>

            <!-- Hero Image Banner -->
            <div class="hero-image-wrap">
                <img src="<?= esc_url($heroImageUrl) ?>" alt="ICTS Engineering Sanctuary" class="hero-image" onerror="this.onerror=null; this.src='images/about-hero.png';">
            </div>
        </div>
    </section>

    <!-- ================= 3. OUR STORY & VISION SECTION ================= -->
    <section class="story-section">
        <div class="container">
            <div class="section-header">
                <span class="section-tag">WHO WE ARE</span>
                <h2 class="section-heading">Pioneering the Next Era of AI Brand Governance</h2>
                <p class="section-desc">From our engineering sanctuary in Hanoi to multinational creative hubs, we turn complex regulatory and visual guidelines into automated mathematical certainty.</p>
            </div>

            <div class="story-grid">
                <div class="story-text">
                    <p>
                        Founded in <strong>2016</strong>, ICTS started with a clear engineering premise: complex software problems deserve simple, robust engineering. Over nearly a decade, we have partnered with forward-thinking enterprises across North America, Europe, Japan, and Southeast Asia to design and ship mission-critical digital systems.
                    </p>
                    <p>
                        As Generative AI accelerates creative production into unprecedented volumes, global marketing and compliance teams face an existential challenge: <em>how to scale multi-channel content velocity without diluting brand equity or triggering costly regulatory non-compliance?</em>
                    </p>
                    <p>
                        <strong>Brand Compliance AI</strong> is our definitive solution. By bridging state-of-the-art multimodal vision algorithms with deterministic rule extraction, we transform lengthy static guideline PDFs into an always-on, high-throughput pre-flight verification engine.
                    </p>
                </div>

                <div class="story-callout">
                    <div class="callout-quote">
                        "True innovation does not replace human creators. It gives them the speed and confidence to take bold creative risks without fearing compliance failure."
                    </div>
                    <div class="callout-author">&mdash; ICTS Engineering Leadership</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 4. CORE VALUES SECTION ================= -->
    <section class="values-section">
        <div class="container">
            <div class="section-header">
                <span class="section-tag">OUR CORE PRINCIPLES</span>
                <h2 class="section-heading">Engineered on Trust, Precision, and Partnership</h2>
                <p class="section-desc">Three foundational pillars that guide every line of code and customer interaction.</p>
            </div>

            <div class="values-grid">
                <!-- Value 1 -->
                <div class="value-card">
                    <div class="value-icon-box">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <polyline points="12 6 12 12 16 14"></polyline>
                        </svg>
                    </div>
                    <h3 class="value-title">Mathematical Precision</h3>
                    <p class="value-desc">
                        Brand guidelines are not approximations. We evaluate color codes, minimum clearspace boundaries, contrast ratios, and mandatory legal disclaimers down to the sub-pixel level.
                    </p>
                </div>

                <!-- Value 2 -->
                <div class="value-card">
                    <div class="value-icon-box">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"></path>
                        </svg>
                    </div>
                    <h3 class="value-title">Amplifying Human Ambition</h3>
                    <p class="value-desc">
                        We liberate Makers and Checkers from tedious, repetitive manual checks. Automated validation allows designers to focus on artistic expression and strategic storytelling.
                    </p>
                </div>

                <!-- Value 3 -->
                <div class="value-card">
                    <div class="value-icon-box">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <h3 class="value-title">Radical Transparency & Sovereignty</h3>
                    <p class="value-desc">
                        Zero black-box ambiguity. Every score is supported by line-level guideline citations, visual bounding boxes, and an immutable audit trail within your private enterprise perimeter.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 5. ENGINEERING SANCTUARY / CAPABILITIES ================= -->
    <section class="capabilities-section">
        <div class="container">
            <div class="section-header">
                <span class="section-tag">ENGINEERING SANCTUARY</span>
                <h2 class="section-heading">High-Performance Architecture</h2>
                <p class="section-desc">Designed to integrate seamlessly into your enterprise production ecosystem.</p>
            </div>

            <div class="caps-grid">
                <div class="cap-card">
                    <div class="cap-number">01</div>
                    <div class="cap-content">
                        <h4>Multimodal Vision Pipeline</h4>
                        <p>Simultaneous raster and vector parsing to detect logo placements, clearspace violations, and contrast degradation across high-res artwork.</p>
                    </div>
                </div>

                <div class="cap-card">
                    <div class="cap-number">02</div>
                    <div class="cap-content">
                        <h4>Context-Aware Rule Parsing</h4>
                        <p>Dynamic NLP engine that ingests PDF guidelines and converts regulatory legalese into deterministic, machine-evaluable checks.</p>
                    </div>
                </div>

                <div class="cap-card">
                    <div class="cap-number">03</div>
                    <div class="cap-content">
                        <h4>Maker &amp; Checker Collaboration</h4>
                        <p>Streamlined submission workflows allowing designers to receive instant feedback and revise assets before final Checker authorization.</p>
                    </div>
                </div>

                <div class="cap-card">
                    <div class="cap-number">04</div>
                    <div class="cap-content">
                        <h4>Cryptographic Audit Records</h4>
                        <p>Immutable event logging and timestamped evaluation certificates providing compliance proof for legal and regulatory audits.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 6. DUAL OFFICES / LOCATIONS ================= -->
    <section class="locations-section">
        <div class="container">
            <div class="section-header">
                <span class="section-tag">GLOBAL HUBS</span>
                <h2 class="section-heading">Our Engineering &amp; Business Locations</h2>
                <p class="section-desc">Visit our primary research lab in Hanoi or connect with our international partnership gateway in Singapore.</p>
            </div>

            <div class="offices-grid">
                <!-- Office 1: Hanoi Headquarters -->
                <div class="office-card">
                    <div class="office-card-header">
                        <span class="office-badge">Headquarters &middot; AI Lab</span>
                        <h3 class="office-title">Hanoi Tech Hub (Vietnam)</h3>
                    </div>

                    <div class="office-details">
                        <div class="detail-item">
                            <div class="detail-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                            </div>
                            <div class="detail-info">
                                <div class="detail-label">Address</div>
                                <div class="detail-value">
                                    Floor 4, Golden Field Building, 24 Nguyen Co Thach, Nam Tu Liem, Hanoi, Vietnam
                                </div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                            </div>
                            <div class="detail-info">
                                <div class="detail-label">Direct Phone</div>
                                <div class="detail-value"><a href="tel:+842437958686">+84 (0) 24 3795 8686</a></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                            </div>
                            <div class="detail-info">
                                <div class="detail-label">Email Inquiries</div>
                                <div class="detail-value"><a href="mailto:compliance@icts.io">compliance@icts.io</a></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                            </div>
                            <div class="detail-info">
                                <div class="detail-label">Working Hours</div>
                                <div class="detail-value">Mon &ndash; Fri: 08:30 &ndash; 18:00 (GMT+7)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Interactive Google Maps Embed (No plugin required, zero latency) -->
                    <div class="map-container">
                        <iframe
                            class="map-iframe"
                            title="ICTS Hanoi Location Map"
                            src="https://maps.google.com/maps?q=Golden%20Field%20Building%2C%2024%20Nguyen%20Co%20Thach%2C%20Nam%20Tu%20Liem%2C%20Hanoi%2C%20Vietnam&t=&z=15&ie=UTF8&iwloc=&output=embed"
                            loading="lazy"
                            allowfullscreen>
                        </iframe>
                    </div>

                    <div class="office-card-footer">
                        <a href="https://maps.google.com/?q=Golden+Field+Building,+24+Nguyen+Co+Thach,+Nam+Tu+Liem,+Hanoi,+Vietnam" target="_blank" rel="noopener noreferrer" class="btn-map-link">
                            <span>Open in Google Maps</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Office 2: Singapore Gateway -->
                <div class="office-card">
                    <div class="office-card-header">
                        <span class="office-badge">Regional Gateway &middot; APAC</span>
                        <h3 class="office-title">Singapore Global Hub</h3>
                    </div>

                    <div class="office-details">
                        <div class="detail-item">
                            <div class="detail-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                    <circle cx="12" cy="10" r="3"></circle>
                                </svg>
                            </div>
                            <div class="detail-info">
                                <div class="detail-label">Address</div>
                                <div class="detail-value">
                                    68 Circular Road, #02-01, Singapore 049422
                                </div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                                </svg>
                            </div>
                            <div class="detail-info">
                                <div class="detail-label">Direct Phone</div>
                                <div class="detail-value"><a href="tel:+6567018200">+65 6701 8200</a></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                                    <polyline points="22,6 12,13 2,6"></polyline>
                                </svg>
                            </div>
                            <div class="detail-info">
                                <div class="detail-label">Email Inquiries</div>
                                <div class="detail-value"><a href="mailto:global@icts.io">global@icts.io</a></div>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-icon">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="10"></circle>
                                    <polyline points="12 6 12 12 16 14"></polyline>
                                </svg>
                            </div>
                            <div class="detail-info">
                                <div class="detail-label">Working Hours</div>
                                <div class="detail-value">Mon &ndash; Fri: 09:00 &ndash; 18:00 (SGT)</div>
                            </div>
                        </div>
                    </div>

                    <!-- Interactive Google Maps Embed (No plugin required, zero latency) -->
                    <div class="map-container">
                        <iframe
                            class="map-iframe"
                            title="ICTS Singapore Location Map"
                            src="https://maps.google.com/maps?q=68%20Circular%20Road%2C%20Singapore%20049422&t=&z=15&ie=UTF8&iwloc=&output=embed"
                            loading="lazy"
                            allowfullscreen>
                        </iframe>
                    </div>

                    <div class="office-card-footer">
                        <a href="https://maps.google.com/?q=68+Circular+Road,+Singapore+049422" target="_blank" rel="noopener noreferrer" class="btn-map-link">
                            <span>Open in Google Maps</span>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                <polyline points="15 3 21 3 21 9"></polyline>
                                <line x1="10" y1="14" x2="21" y2="3"></line>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 7. CTA BANNER SECTION ================= -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-box">
                <h2 class="cta-title">Ready to experience certainty in brand compliance?</h2>
                <p class="cta-subtitle">Deploy our automated pre-flight review workflow to protect brand equity, prevent regulatory penalties, and empower creators.</p>
                <a href="<?= esc_url($loginUrl) ?>" class="btn-primary-yellow">Start Review</a>
            </div>
        </div>
    </section>

    <!-- ================= 8. FOOTER ================= -->
    <footer class="footer">
        <div class="container footer-content">
            <span class="footer-brand">Brand Compliance AI</span>
            <span>&copy; <?= date('Y') ?> ICTS. All rights reserved. Automated pre-flight compliance & regulatory risk governance.</span>
        </div>
    </footer>

</body>
</html>

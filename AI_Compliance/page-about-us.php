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
$handsImageUrl = 'images/about-hands.png';
$leader1Url = 'images/leader_1.png';
$leader2Url = 'images/leader_2.png';
$leader3Url = 'images/leader_3.png';
$mapHanoiUrl = 'images/map_hanoi.png';
$mapSingUrl = 'images/map_singapore.png';

if (function_exists('get_template_directory_uri')) {
    $homeLogoUrl = get_template_directory_uri() . '/images/logo.png';
    $heroImageUrl = get_template_directory_uri() . '/images/about-hero.png';
    $handsImageUrl = get_template_directory_uri() . '/images/about-hands.png';
    $leader1Url = get_template_directory_uri() . '/images/leader_1.png';
    $leader2Url = get_template_directory_uri() . '/images/leader_2.png';
    $leader3Url = get_template_directory_uri() . '/images/leader_3.png';
    $mapHanoiUrl = get_template_directory_uri() . '/images/map_hanoi.png';
    $mapSingUrl = get_template_directory_uri() . '/images/map_singapore.png';
} elseif (function_exists('get_stylesheet_directory_uri')) {
    $homeLogoUrl = get_stylesheet_directory_uri() . '/images/logo.png';
    $heroImageUrl = get_stylesheet_directory_uri() . '/images/about-hero.png';
    $handsImageUrl = get_stylesheet_directory_uri() . '/images/about-hands.png';
    $leader1Url = get_stylesheet_directory_uri() . '/images/leader_1.png';
    $leader2Url = get_stylesheet_directory_uri() . '/images/leader_2.png';
    $leader3Url = get_stylesheet_directory_uri() . '/images/leader_3.png';
    $mapHanoiUrl = get_stylesheet_directory_uri() . '/images/map_hanoi.png';
    $mapSingUrl = get_stylesheet_directory_uri() . '/images/map_singapore.png';
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
        /* ================= RESET & CAU HINH TOAN CUC ================= */
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

        /* ================= 3. BOXLESS PHILOSOPHY / ENGINEERING DOCTRINE ================= */
        .doctrine-section {
            padding: 90px 0 80px 0;
            background: #FFFFFF;
            border-top: 1px solid rgba(226, 232, 240, 0.8);
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }

        .doctrine-header {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 48px;
            align-items: center;
            margin-bottom: 70px;
        }

        .doctrine-headline-box .doctrine-overline {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .doctrine-overline .bar {
            width: 32px;
            height: 2px;
            background: #4F57A7;
            display: inline-block;
        }

        .doctrine-overline .text {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: #4F57A7;
            text-transform: uppercase;
        }

        .doctrine-heading {
            font-size: clamp(28px, 3.2vw, 42px);
            font-weight: 800;
            color: #1F2674;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .quote-callout {
            border-left: 3px solid #4F57A7;
            padding-left: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .quote-text {
            font-size: 16.5px;
            font-weight: 600;
            font-style: italic;
            color: #1F2674;
            line-height: 1.55;
        }

        .quote-author-row {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .author-avatar-badge {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #EDE9FE;
            color: #7C3AED;
            font-weight: 800;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(124, 58, 237, 0.15);
        }

        .author-info .author-name {
            font-size: 15px;
            font-weight: 700;
            color: #1F2674;
        }

        .author-info .author-title {
            font-size: 12.5px;
            color: #64748B;
            font-weight: 500;
        }

        /* Organic Flow: Illustration + 3 Story Pillars */
        .doctrine-flow-grid {
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            gap: 60px;
            align-items: center;
        }

        .doctrine-illustration-wrap {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .doctrine-hands-img {
            max-width: 400px;
            width: 100%;
            height: auto;
            display: block;
            filter: drop-shadow(0 15px 30px rgba(79, 87, 167, 0.1));
        }

        .pillars-list {
            display: flex;
            flex-direction: column;
            gap: 36px;
        }

        .pillar-item {
            display: flex;
            gap: 22px;
            align-items: flex-start;
        }

        .pillar-num {
            font-size: 32px;
            font-weight: 800;
            color: #C084FC;
            line-height: 1;
            letter-spacing: -0.03em;
            flex-shrink: 0;
            padding-top: 2px;
        }

        .pillar-content h3 {
            font-size: 17.5px;
            font-weight: 700;
            color: #7C3AED;
            margin-bottom: 8px;
            line-height: 1.35;
        }

        .pillar-content p {
            font-size: 14.5px;
            line-height: 1.65;
            color: #475569;
        }

        /* ================= 4. SECTION 5: LEADERSHIP COLLECTIVE ================= */
        .leadership-section {
            padding: 90px 0;
            background: #FAF8FF;
        }

        .leadership-header {
            text-align: center;
            max-width: 720px;
            margin: 0 auto 56px auto;
        }

        .leadership-overline {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: #4F57A7;
            text-transform: uppercase;
            margin-bottom: 12px;
            display: inline-block;
        }

        .leadership-title {
            font-size: clamp(28px, 3.2vw, 42px);
            font-weight: 800;
            color: #1F2674;
            line-height: 1.2;
            letter-spacing: -0.02em;
            margin-bottom: 14px;
        }

        .leadership-subtitle {
            font-size: 16px;
            color: #64748B;
            line-height: 1.6;
        }

        .leaders-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .leader-card {
            background: #FFFFFF;
            border: 1px solid rgba(226, 232, 240, 0.9);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 28px -6px rgba(31, 38, 116, 0.07);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .leader-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 36px -8px rgba(31, 38, 116, 0.12);
            border-color: rgba(79, 87, 167, 0.35);
        }

        .leader-photo-wrap {
            width: 100%;
            height: 250px;
            background: #E2E8F0;
            overflow: hidden;
            position: relative;
        }

        .leader-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .leader-info-box {
            padding: 24px;
            display: flex;
            flex-direction: column;
            flex-grow: 1;
        }

        .leader-meta-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 12px;
        }

        .leader-name {
            font-size: 18px;
            font-weight: 800;
            color: #1F2674;
            line-height: 1.3;
        }

        .leader-role {
            font-size: 12.5px;
            font-weight: 600;
            color: #4F57A7;
            margin-top: 2px;
        }

        .leader-ext-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: rgba(79, 87, 167, 0.08);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4F57A7;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .leader-card:hover .leader-ext-icon {
            background: #1F2674;
            color: #FFFFFF;
        }

        .leader-bio {
            font-size: 13.5px;
            line-height: 1.6;
            color: #64748B;
        }

        /* ================= 5. VALUES THAT ANCHOR CODE (DEEP PURPLE WAVES) ================= */
        .anchor-values-section {
            position: relative;
            background: linear-gradient(180deg, #7E22CE 0%, #6B21A8 100%);
            color: #FFFFFF;
            padding: 100px 0;
            overflow: hidden;
        }

        .purple-wave-top {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            line-height: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .purple-wave-top svg {
            width: 100%;
            height: 60px;
            display: block;
        }

        .purple-wave-bottom {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            line-height: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .purple-wave-bottom svg {
            width: 100%;
            height: 60px;
            display: block;
        }

        .anchor-header {
            text-align: center;
            max-width: 760px;
            margin: 0 auto 56px auto;
            position: relative;
            z-index: 2;
        }

        .anchor-title {
            font-size: clamp(28px, 3.5vw, 44px);
            font-weight: 800;
            color: #FFFFFF;
            line-height: 1.2;
            letter-spacing: -0.02em;
            margin-bottom: 16px;
        }

        .anchor-subtitle {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
        }

        .anchor-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 28px;
            position: relative;
            z-index: 2;
        }

        .anchor-card {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .anchor-icon-wrap {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.16);
            backdrop-filter: blur(8px);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #FFFFFF;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.25);
        }

        .anchor-card-title {
            font-size: 19px;
            font-weight: 700;
            color: #FFFFFF;
            margin-bottom: 10px;
        }

        .anchor-card-desc {
            font-size: 13.5px;
            line-height: 1.65;
            color: rgba(255, 255, 255, 0.85);
        }

        /* ================= 6. DUAL-ANCHOR PRESENCE: ENGINEERING HUBS & ALLIANCES ================= */
        .hubs-section {
            padding: 90px 0 100px 0;
            background: #FFFFFF;
        }

        .hubs-header-split {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 40px;
            margin-bottom: 50px;
        }

        .hubs-title-group .hubs-overline {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.12em;
            color: #7C3AED;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }

        .hubs-heading {
            font-size: clamp(26px, 3vw, 38px);
            font-weight: 800;
            color: #1F2674;
            line-height: 1.2;
            letter-spacing: -0.02em;
        }

        .hubs-subtitle-wrap {
            max-width: 440px;
        }

        .hubs-subtitle {
            font-size: 14.5px;
            color: #64748B;
            line-height: 1.55;
        }

        .hubs-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 36px;
        }

        .hub-card {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .hub-map-frame {
            position: relative;
            height: 250px;
            border-radius: 20px;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.95);
            box-shadow: 0 12px 30px -8px rgba(31, 38, 116, 0.1);
            background: #F1F5F9;
        }

        .hub-map-iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }

        .hub-badge {
            position: absolute;
            top: 14px;
            left: 14px;
            z-index: 10;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(8px);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.06em;
            color: #1F2674;
            padding: 5px 14px;
            border-radius: 9999px;
            border: 1px solid rgba(226, 232, 240, 0.8);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.06);
        }

        .hub-floating-card {
            position: absolute;
            bottom: 12px;
            left: 12px;
            right: 12px;
            z-index: 10;
            background: rgba(255, 255, 255, 0.96);
            backdrop-filter: blur(10px);
            padding: 14px 18px;
            border-radius: 14px;
            border: 1px solid rgba(226, 232, 240, 0.85);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            box-shadow: 0 6px 18px rgba(31, 38, 116, 0.08);
        }

        .hub-text-block .hub-name {
            font-size: 15px;
            font-weight: 800;
            color: #1F2674;
            line-height: 1.3;
            margin-bottom: 2px;
        }

        .hub-text-block .hub-address {
            font-size: 12px;
            color: #64748B;
            line-height: 1.4;
        }

        .hub-link-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            background: #1F2674;
            color: #FFFFFF;
            flex-shrink: 0;
            transition: all 0.2s ease;
        }

        .hub-link-btn:hover {
            background: #4F57A7;
            transform: scale(1.05);
        }

        .hub-features-row {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 0 8px;
        }

        .hub-feat-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 12.5px;
            font-weight: 600;
            color: #475569;
        }

        .hub-feat-item svg {
            color: #7C3AED;
            flex-shrink: 0;
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
            .doctrine-header {
                grid-template-columns: 1fr;
                gap: 32px;
            }
            .doctrine-flow-grid {
                grid-template-columns: 1fr;
            }
            .leaders-grid {
                grid-template-columns: 1fr;
            }
            .anchor-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .hubs-grid {
                grid-template-columns: 1fr;
            }
            .hubs-header-split {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
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
            .anchor-grid {
                grid-template-columns: 1fr;
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

    <!-- ================= 3. BOXLESS PHILOSOPHY / ENGINEERING DOCTRINE ================= -->
    <section class="doctrine-section">
        <div class="container">
            <div class="doctrine-header">
                <div class="doctrine-headline-box">
                    <div class="doctrine-overline">
                        <span class="bar"></span>
                        <span class="text">ENGINEERING DOCTRINE</span>
                    </div>
                    <h2 class="doctrine-heading">A lean solution is always<br/>better than a fancy one.</h2>
                </div>

                <div class="quote-callout">
                    <div class="quote-text">
                        &ldquo;We strip unnecessary technical ballast. What remains is pristine latency, predictable maintenance, and pure product velocity.&rdquo;
                    </div>
                    <div class="quote-author-row">
                        <div class="author-avatar-badge">T</div>
                        <div class="author-info">
                            <div class="author-name">Jessica Tran</div>
                            <div class="author-title">Founder &amp; Chief Architect, ICTS Custom Software</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="doctrine-flow-grid">
                <div class="doctrine-illustration-wrap">
                    <img src="<?= esc_url($handsImageUrl) ?>" alt="ICTS Team Synergy" class="doctrine-hands-img" onerror="this.onerror=null; this.src='images/about-hands.png';">
                </div>

                <div class="pillars-list">
                    <!-- Pillar 01 -->
                    <div class="pillar-item">
                        <div class="pillar-num">01</div>
                        <div class="pillar-content">
                            <h3>Extended Tech Hub for Global Digital Agencies</h3>
                            <p>We operate as transparent, embedded engineering squads that seamlessly adopt your Git protocols, CI/CD pipelines, and internal Jira cadences without cultural friction.</p>
                        </div>
                    </div>

                    <!-- Pillar 02 -->
                    <div class="pillar-item">
                        <div class="pillar-num">02</div>
                        <div class="pillar-content">
                            <h3>Acceleration Wing for High-Velocity Ventures</h3>
                            <p>When time-to-market defines survival, we build production MVPs and core engines with rigorous code hygiene, ensuring you never have to throw away v1 when scaling to series B.</p>
                        </div>
                    </div>

                    <!-- Pillar 03 -->
                    <div class="pillar-item">
                        <div class="pillar-num">03</div>
                        <div class="pillar-content">
                            <h3>Uncompromising Architectural Fidelity</h3>
                            <p>From event-driven microservices to zero-trust cloud security, we hold every deliverable against Swiss-level benchmarks of uptime, maintainability, and auditability.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 4. SECTION 5: LEADERSHIP COLLECTIVE ================= -->
    <section class="leadership-section">
        <div class="container">
            <div class="leadership-header">
                <span class="leadership-overline">MINDS BEHIND THE ENGINE</span>
                <h2 class="leadership-title">The Leadership Collective</h2>
                <p class="leadership-subtitle">Steered by seasoned practitioners who code, strategize, and uphold uncompromising code standards.</p>
            </div>

            <div class="leaders-grid">
                <!-- Leader 1 -->
                <div class="leader-card">
                    <div class="leader-photo-wrap">
                        <img src="<?= esc_url($leader1Url) ?>" alt="Jessica Tran" class="leader-photo" onerror="this.onerror=null; this.src='images/leader_1.png';">
                    </div>
                    <div class="leader-info-box">
                        <div class="leader-meta-row">
                            <div>
                                <h3 class="leader-name">Jessica Tran</h3>
                                <div class="leader-role">Chief Executive Officer &amp; Founder</div>
                            </div>
                            <div class="leader-ext-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </div>
                        </div>
                        <p class="leader-bio">15+ years steering international digital products. Pioneered ICTS's hybrid delivery methodology connecting ASEAN engineering talent with Western enterprise standards.</p>
                    </div>
                </div>

                <!-- Leader 2 -->
                <div class="leader-card">
                    <div class="leader-photo-wrap">
                        <img src="<?= esc_url($leader2Url) ?>" alt="Rachel Le" class="leader-photo" onerror="this.onerror=null; this.src='images/leader_2.png';">
                    </div>
                    <div class="leader-info-box">
                        <div class="leader-meta-row">
                            <div>
                                <h3 class="leader-name">Rachel Le</h3>
                                <div class="leader-role">Chief Marketing Officer</div>
                            </div>
                            <div class="leader-ext-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </div>
                        </div>
                        <p class="leader-bio">Drives strategic global market expansion and client partnership governance. Fosters relationships with enterprise founders across DACH, Australia, and North America.</p>
                    </div>
                </div>

                <!-- Leader 3 -->
                <div class="leader-card">
                    <div class="leader-photo-wrap">
                        <img src="<?= esc_url($leader3Url) ?>" alt="Julia Nguyen" class="leader-photo" onerror="this.onerror=null; this.src='images/leader_3.png';">
                    </div>
                    <div class="leader-info-box">
                        <div class="leader-meta-row">
                            <div>
                                <h3 class="leader-name">Julia Nguyen</h3>
                                <div class="leader-role">Chief Technology Officer</div>
                            </div>
                            <div class="leader-ext-icon">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </div>
                        </div>
                        <p class="leader-bio">Former distributed systems lead. Oversees ICTS cloud engineering practices, zero-trust cloud frameworks, and proprietary automated code quality validators.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= 5. VALUES THAT ANCHOR CODE (DEEP PURPLE WAVES) ================= -->
    <section class="anchor-values-section">
        <!-- SVG waves on top and bottom -->
        <div class="purple-wave-top">
            <svg viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                <path d="M0 0C240 40 480 60 720 40C960 20 1200 40 1440 0V60H0V0Z" fill="#7E22CE"/>
            </svg>
        </div>

        <div class="container">
            <div class="anchor-header">
                <h2 class="anchor-title">Values that anchor every single line of code.</h2>
                <p class="anchor-subtitle">No vague aphorisms. Just operational discipline built for decades of sustained product resilience.</p>
            </div>

            <div class="anchor-grid">
                <!-- Value 1: Integrity -->
                <div class="anchor-card">
                    <div class="anchor-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <polyline points="9 12 11 14 15 10"></polyline>
                        </svg>
                    </div>
                    <h3 class="anchor-card-title">Integrity</h3>
                    <p class="anchor-card-desc">Accurate, transparent, and direct communication in every circumstance. When a deadline faces headwinds or an architecture exhibits flaws, we state facts clearly and suggest verified remediation paths.</p>
                </div>

                <!-- Value 2: Communication -->
                <div class="anchor-card">
                    <div class="anchor-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </div>
                    <h3 class="anchor-card-title">Communication</h3>
                    <p class="anchor-card-desc">Proactive collaboration acts as the dependable backbone of every sprint. Daily async recaps, live demo checkpoints, and crystal-clear pull request notes ensure no ambiguity ever lingers.</p>
                </div>

                <!-- Value 3: Technology -->
                <div class="anchor-card">
                    <div class="anchor-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="4" width="16" height="16" rx="2" ry="2"></rect>
                            <rect x="9" y="9" width="6" height="6"></rect>
                            <line x1="9" y1="1" x2="9" y2="4"></line>
                            <line x1="15" y1="1" x2="15" y2="4"></line>
                            <line x1="9" y1="20" x2="9" y2="23"></line>
                            <line x1="15" y1="20" x2="15" y2="23"></line>
                            <line x1="20" y1="9" x2="23" y2="9"></line>
                            <line x1="20" y1="14" x2="23" y2="14"></line>
                            <line x1="1" y1="9" x2="4" y2="9"></line>
                            <line x1="1" y1="14" x2="4" y2="14"></line>
                        </svg>
                    </div>
                    <h3 class="anchor-card-title">Technology</h3>
                    <p class="anchor-card-desc">Modern cloud native architectures and custom core engines built for frictionless horizontal scale. We write testable, modular codebases designed to outlast individual developers and tech fads.</p>
                </div>

                <!-- Value 4: Smile -->
                <div class="anchor-card">
                    <div class="anchor-icon-wrap">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                            <line x1="9" y1="9" x2="9.01" y2="9"></line>
                            <line x1="15" y1="9" x2="15.01" y2="9"></line>
                        </svg>
                    </div>
                    <h3 class="anchor-card-title">Smile</h3>
                    <p class="anchor-card-desc">Human empathy, quiet dedication, and lasting partnership with every founder. Beyond unit tests, software is built by people for people; positive camaraderie elevates engineering outcomes.</p>
                </div>
            </div>
        </div>

        <div class="purple-wave-bottom">
            <svg viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none">
                <path d="M0 60C240 20 480 0 720 20C960 40 1200 20 1440 60H0Z" fill="#FFFFFF"/>
            </svg>
        </div>
    </section>

    <!-- ================= 6. DUAL-ANCHOR PRESENCE: ENGINEERING HUBS & ALLIANCES ================= -->
    <section class="hubs-section">
        <div class="container">
            <div class="hubs-header-split">
                <div class="hubs-title-group">
                    <span class="hubs-overline">DUAL-ANCHOR PRESENCE</span>
                    <h2 class="hubs-heading">Engineering Hubs &amp; Alliances</h2>
                </div>
                <div class="hubs-subtitle-wrap">
                    <p class="hubs-subtitle">Strategically rooted in Hanoi's high-tech talent corridor with executive liaison presence in downtown Singapore.</p>
                </div>
            </div>

            <div class="hubs-grid">
                <!-- Location 1: Hanoi Delivery Headquarters -->
                <div class="hub-card">
                    <div class="hub-map-frame">
                        <iframe
                            class="hub-map-iframe"
                            title="Hanoi Delivery Headquarters Map"
                            src="https://maps.google.com/maps?q=Tr%C6%B0%E1%BB%9Dng+Qu%E1%BB%91c+t%E1%BA%BF+-+%C4%90%E1%BA%A1i+h%E1%BB%8Dc+Qu%E1%BB%91c+gia+H%C3%A0+N%E1%BB%99i%2C+1+Tr%E1%BB%8Bnh+V%C4%83n+B%C3%B4%2C+Nam+T%E1%BB%AB+Li%C3%AAm%2C+H%C3%A0+N%E1%BB%99i&t=&z=16&ie=UTF8&iwloc=&output=embed"
                            loading="lazy"
                            allowfullscreen>
                        </iframe>
                        <div class="hub-floating-card">
                            <div class="hub-text-block">
                                <div class="hub-name">Hanoi Delivery Headquarters</div>
                                <div class="hub-address">Floor 4, AMDI Group Building, So 1 Trinh Van Bo, Nam Tu Liem, Hanoi, Vietnam &bull; Trường Quốc tế - ĐHQGHN</div>
                            </div>
                            <a href="https://maps.app.goo.gl/aYj9aFHP8vTFGrcj7" target="_blank" rel="noopener noreferrer" class="hub-link-btn" title="Open in Google Maps">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div class="hub-features-row">
                        <div class="hub-feat-item">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                <polyline points="9 12 11 14 15 10"></polyline>
                            </svg>
                            <span>ISO 27001 Certified Facilities</span>
                        </div>
                        <div class="hub-feat-item">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <polyline points="12 6 12 12 16 14"></polyline>
                            </svg>
                            <span>GMT+7 Timezone Coverage</span>
                        </div>
                    </div>
                </div>

                <!-- Location 2: Singapore Commercial Hub -->
                <div class="hub-card">
                    <div class="hub-map-frame">
                        <iframe
                            class="hub-map-iframe"
                            title="Singapore Commercial Hub Map"
                            src="https://maps.google.com/maps?q=Hiap+Hoe+Building%2C+Zhongshan+Park%2C+Singapore&t=&z=16&ie=UTF8&iwloc=&output=embed"
                            loading="lazy"
                            allowfullscreen>
                        </iframe>
                        <div class="hub-floating-card">
                            <div class="hub-text-block">
                                <div class="hub-name">Singapore Commercial Hub</div>
                                <div class="hub-address">Hiap Hoe Building, Zhongshan Park &bull; Global Client Representation</div>
                            </div>
                            <a href="https://maps.google.com/?q=Hiap+Hoe+Building,+Zhongshan+Park,+Singapore" target="_blank" rel="noopener noreferrer" class="hub-link-btn" title="Open in Google Maps">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                                    <polyline points="15 3 21 3 21 9"></polyline>
                                    <line x1="10" y1="14" x2="21" y2="3"></line>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div class="hub-features-row">
                        <div class="hub-feat-item">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="2" y1="12" x2="22" y2="12"></line>
                                <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                            </svg>
                            <span>APAC Executive Engagement</span>
                        </div>
                        <div class="hub-feat-item">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                                <line x1="6" y1="12" x2="18" y2="12"></line>
                            </svg>
                            <span>Multi-Currency Contracting</span>
                        </div>
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

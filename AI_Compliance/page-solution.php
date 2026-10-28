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
$solutionUrl = function_exists('home_url') ? home_url('/solution/') : 'page-solution.php';
$aboutUrl = function_exists('home_url') ? home_url('/about-us/') : 'page-about-us.php';
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">

    <style>
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        .container {
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Dedicated Header */
        .header {
            position: sticky;
            top: 0;
            z-index: 100;
            background: rgba(255, 255, 255, 0.98);
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

        .nav-item:hover, .nav-item.active {
            color: #1E3A8A;
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
            padding: 10px 28px;
            border-radius: 9999px;
            background-color: #F6B93B;
            color: #0F172A;
            font-size: 15px;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(246, 185, 59, 0.35);
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary-yellow:hover {
            background-color: #EFA825;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(246, 185, 59, 0.45);
        }

        /* Content Container (Ready for Figma UI) */
        .main-content {
            flex: 1;
            padding: 60px 0;
        }

        .solution-placeholder-card {
            background: #FFFFFF;
            border-radius: 20px;
            border: 1px dashed #CBD5E1;
            padding: 60px 40px;
            text-align: center;
            max-width: 800px;
            margin: 40px auto;
        }

        .solution-placeholder-card h1 {
            font-size: 32px;
            font-weight: 800;
            color: #1F2A5A;
            margin-bottom: 16px;
        }

        .solution-placeholder-card p {
            font-size: 16px;
            color: #64748B;
            line-height: 1.6;
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
                    <a href="<?= esc_url($solutionUrl) ?>" class="nav-item active">Solution</a>
                    <a href="<?= esc_url($aboutUrl) ?>" class="nav-item">About Us</a>
                </nav>
            </div>
            <div class="header-right">
                <a href="<?= esc_url($loginUrl) ?>" class="btn-primary-yellow">Start Review</a>
            </div>
        </div>
    </header>

    <!-- ================= 2. MAIN CONTENT AREA (READY FOR FIGMA UI) ================= -->
    <main class="main-content">
        <div class="container">
            <div class="solution-placeholder-card">
                <h1>Our Solution Workspace</h1>
                <p>Ready to receive Figma UI code integration.</p>
            </div>
        </div>
    </main>

</body>
</html>

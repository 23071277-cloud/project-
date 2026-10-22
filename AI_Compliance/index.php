<?php
if (function_exists('is_404') && is_404() && file_exists(__DIR__ . '/404.php')) {
    include __DIR__ . '/404.php';
    exit;
}

if (file_exists(__DIR__ . '/home-page.php')) {
    require_once __DIR__ . '/home-page.php';
}
<?php
/**
 * Safe Proxy to main backend.php
 * Prevents duplicate function redeclaration when both backend.php and inc/backend.php exist.
 */
require_once dirname(__DIR__) . '/backend.php';

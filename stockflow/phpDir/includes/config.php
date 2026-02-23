<?php
// ============================================================
// includes/config.php — Configuration constants
// ============================================================

// Try to load from a local config file first (most reliable)
$configFile = __DIR__ . '/config.local.php';
if (file_exists($configFile)) {
    require_once $configFile;
} else {
    // Fallback to environment variables
    $supabaseUrl = getenv('SUPABASE_URL') ?: ($_SERVER['SUPABASE_URL'] ?? '');
    $supabaseAnonKey = getenv('SUPABASE_ANON_KEY') ?: ($_SERVER['SUPABASE_ANON_KEY'] ?? '');

    if ($supabaseUrl && $supabaseAnonKey) {
        define('SUPABASE_URL', $supabaseUrl);
        define('SUPABASE_ANON_KEY', $supabaseAnonKey);
    }
}

// Validate configuration
if (!defined('SUPABASE_URL') || !defined('SUPABASE_ANON_KEY')) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'error' => 'Server configuration error',
        'hint' => 'Create phpDir/includes/config.local.php with SUPABASE_URL and SUPABASE_ANON_KEY'
    ]);
    exit;
}

// Site configuration
if (!defined('SITE_URL')) {
    define('SITE_URL', 'http://localhost:5173');
}

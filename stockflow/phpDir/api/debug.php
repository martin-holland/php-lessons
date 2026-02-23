<?php
// ============================================================
// API: /api/debug.php — Debug endpoint (REMOVE IN PRODUCTION)
// ============================================================

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/json_response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/role.php';

$user = validate_token();
$role = get_user_role($user['id'], $user['token']);

$debug = [
    'user_id' => $user['id'],
    'user_email' => $user['email'],
    'user_role' => $role,
];

// Check user role record
try {
    $roleRecord = supabase_request(
        'GET',
        'user_roles?user_id=eq.' . $user['id'] . '&select=*',
        null,
        $user['token']
    );
    $debug['role_record'] = $roleRecord;
} catch (Exception $e) {
    $debug['role_error'] = $e->getMessage();
}

// Check if we can read products
try {
    $products = supabase_request(
        'GET',
        'products?select=id,name&limit=3',
        null,
        $user['token']
    );
    $debug['products_sample'] = $products;
    $debug['products_count'] = count($products);
} catch (Exception $e) {
    $debug['products_error'] = $e->getMessage();
}

// Check if we can read categories
try {
    $categories = supabase_request(
        'GET',
        'categories?select=id,name',
        null,
        $user['token']
    );
    $debug['categories'] = $categories;
} catch (Exception $e) {
    $debug['categories_error'] = $e->getMessage();
}

$debug['supabase_url'] = SUPABASE_URL;
$debug['token_preview'] = substr($user['token'], 0, 50) . '...';

json_success($debug);

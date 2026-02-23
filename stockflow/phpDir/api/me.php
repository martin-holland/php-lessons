<?php
// ============================================================
// API: /api/me.php — Get current user info and role
// ============================================================

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/json_response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/role.php';

$user = validate_token();
$role = get_user_role($user['id'], $user['token']);

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    json_error('Method not allowed', 405);
}

json_success([
    'id' => $user['id'],
    'email' => $user['email'],
    'role' => $role,
]);

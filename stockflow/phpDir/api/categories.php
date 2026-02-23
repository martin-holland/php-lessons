<?php
// ============================================================
// API: /api/categories.php — Category list
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

switch ($method) {
    case 'GET':
        try {
            $categories = supabase_request(
                'GET',
                'categories?order=name.asc',
                null,
                $user['token']
            );
            json_success($categories);
        } catch (Exception $e) {
            json_error('Failed to fetch categories: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        require_admin_or_manager($role);

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['name'])) {
                json_error('Category name is required', 400);
            }

            $created = supabase_request('POST', 'categories', [
                'name' => $input['name'],
            ], $user['token']);

            json_success($created, 201);
        } catch (Exception $e) {
            json_error('Failed to create category: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Method not allowed', 405);
}

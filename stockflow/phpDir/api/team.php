<?php
// ============================================================
// API: /api/team.php — User role management (admin only)
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
        // Only admins can see all user roles
        require_admin($role);

        try {
            $members = supabase_request(
                'GET',
                'user_roles?select=id,user_id,role,created_at',
                null,
                $user['token']
            );
            json_success($members);
        } catch (Exception $e) {
            json_error('Failed to fetch team members: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
    case 'PATCH':
        // Update user role (admin only)
        require_admin($role);

        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            json_error('User role ID is required', 400);
        }

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['role']) || !in_array($input['role'], ['admin', 'manager', 'staff'])) {
                json_error('Valid role is required', 400);
            }

            $updated = supabase_request(
                'PATCH',
                'user_roles?id=eq.' . $id,
                ['role' => $input['role']],
                $user['token']
            );

            json_success($updated[0] ?? $updated);
        } catch (Exception $e) {
            json_error('Failed to update user role: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        // Remove user role (admin only)
        require_admin($role);

        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            json_error('User role ID is required', 400);
        }

        try {
            supabase_request(
                'DELETE',
                'user_roles?id=eq.' . $id,
                null,
                $user['token']
            );

            json_success(['message' => 'User role removed']);
        } catch (Exception $e) {
            json_error('Failed to remove user role: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Method not allowed', 405);
}

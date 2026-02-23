<?php
// ============================================================
// API: /api/order.php — Single order CRUD
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
$id = $_GET['id'] ?? '';

if (empty($id)) {
    json_error('Order ID is required', 400);
}

switch ($method) {
    case 'GET':
        try {
            // Get order with items
            $order = supabase_request(
                'GET',
                'orders?id=eq.' . $id,
                null,
                $user['token']
            );

            if (empty($order)) {
                json_error('Order not found', 404);
            }

            $items = supabase_request(
                'GET',
                'order_items?order_id=eq.' . $id . '&order=created_at.asc',
                null,
                $user['token']
            );

            $result = $order[0] ?? $order;
            $result['items'] = $items;

            json_success($result);
        } catch (Exception $e) {
            json_error('Failed to fetch order: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
    case 'PATCH':
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            // Only allow status updates (and require manager/admin for non-draft)
            if (isset($input['status']) && !in_array($input['status'], ['draft', 'confirmed', 'fulfilled', 'cancelled'])) {
                json_error('Invalid status', 400);
            }

            $update_data = [];
            if (isset($input['status'])) {
                // Non-staff can update status
                if ($role === 'staff' && $input['status'] !== 'draft') {
                    require_admin_or_manager($role);
                }
                $update_data['status'] = $input['status'];
            }
            if (isset($input['notes'])) {
                $update_data['notes'] = $input['notes'];
            }

            $updated = supabase_request(
                'PATCH',
                'orders?id=eq.' . $id,
                $update_data,
                $user['token']
            );

            json_success($updated[0] ?? $updated);
        } catch (Exception $e) {
            json_error('Failed to update order: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Method not allowed', 405);
}

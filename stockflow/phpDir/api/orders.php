<?php
// ============================================================
// API: /api/orders.php — Order list and creation
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
            $endpoint = 'orders?order=created_at.desc';

            // Optional filters
            if (!empty($_GET['status'])) {
                $endpoint .= '&status=eq.' . urlencode($_GET['status']);
            }

            $orders = supabase_request('GET', $endpoint, null, $user['token']);
            json_success($orders);
        } catch (Exception $e) {
            json_error('Failed to fetch orders: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['customer_name'])) json_error('Customer name is required', 400);
            if (empty($input['items']) || !is_array($input['items'])) json_error('Order items are required', 400);

            // Calculate total
            $total = 0;
            foreach ($input['items'] as $item) {
                $total += ($item['unit_price'] ?? 0) * ($item['quantity'] ?? 0);
            }

            // Create order
            $order = supabase_request('POST', 'orders', [
                'customer_name' => $input['customer_name'],
                'status' => $input['status'] ?? 'draft',
                'total_amount' => $total,
                'notes' => $input['notes'] ?? '',
                'created_by' => $user['id'],
            ], $user['token']);

            $orderId = $order[0]['id'] ?? $order['id'];

            // Create order items
            foreach ($input['items'] as $item) {
                supabase_request('POST', 'order_items', [
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'product_name' => $item['product_name'],
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => (float) $item['unit_price'],
                    'line_total' => (float) ($item['quantity'] * $item['unit_price']),
                ], $user['token']);
            }

            json_success($order, 201);
        } catch (Exception $e) {
            json_error('Failed to create order: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Method not allowed', 405);
}

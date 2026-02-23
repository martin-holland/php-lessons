<?php
// ============================================================
// API: /api/stock-movement.php — Record stock changes
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
        // Get stock movements for a product
        $product_id = $_GET['product_id'] ?? '';

        try {
            $endpoint = 'stock_movements?order=created_at.desc&limit=50';
            if ($product_id) {
                $endpoint .= '&product_id=eq.' . $product_id;
            }

            $movements = supabase_request('GET', $endpoint, null, $user['token']);
            json_success($movements);
        } catch (Exception $e) {
            json_error('Failed to fetch stock movements: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['product_id'])) json_error('Product ID is required', 400);
            if (!isset($input['quantity']) || $input['quantity'] == 0) json_error('Quantity is required', 400);
            if (empty($input['movement_type'])) json_error('Movement type is required', 400);

            $quantity = (int) $input['quantity'];
            $type = $input['movement_type'];

            // Validate movement type
            if (!in_array($type, ['in', 'out', 'adjustment'])) {
                json_error('Invalid movement type', 400);
            }

            // For 'out' movements, quantity should be negative
            if ($type === 'out' && $quantity > 0) {
                $quantity = -$quantity;
            }

            // Insert stock movement record
            $movement = supabase_request('POST', 'stock_movements', [
                'product_id' => $input['product_id'],
                'quantity' => $quantity,
                'movement_type' => $type,
                'reason' => $input['reason'] ?? '',
                'notes' => $input['notes'] ?? '',
                'created_by' => $user['id'],
            ], $user['token']);

            // Get current product stock
            $product = supabase_request(
                'GET',
                'products?id=eq.' . $input['product_id'] . '&select=stock_quantity',
                null,
                $user['token']
            );

            if (empty($product)) {
                json_error('Product not found', 404);
            }

            $newQuantity = ($product[0]['stock_quantity'] ?? 0) + $quantity;
            if ($newQuantity < 0) $newQuantity = 0;

            // Update product stock
            supabase_request(
                'PATCH',
                'products?id=eq.' . $input['product_id'],
                ['stock_quantity' => $newQuantity],
                $user['token']
            );

            json_success($movement, 201);
        } catch (Exception $e) {
            json_error('Failed to record stock movement: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Method not allowed', 405);
}

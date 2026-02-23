<?php
// ============================================================
// API: /api/product.php — Single product CRUD
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
    json_error('Product ID is required', 400);
}

switch ($method) {
    case 'GET':
        try {
            $product = supabase_request(
                'GET',
                'products?id=eq.' . $id . '&select=*,categories(name)',
                null,
                $user['token']
            );
            json_success($product);
        } catch (Exception $e) {
            json_error('Failed to fetch product: ' . $e->getMessage(), 500);
        }
        break;

    case 'PUT':
    case 'PATCH':
        require_admin_or_manager($role);

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $update_data = [];
            $allowed = ['name', 'sku', 'description', 'price', 'category_id', 'stock_quantity', 'reorder_threshold', 'supplier', 'image_url', 'status'];

            foreach ($allowed as $field) {
                if (isset($input[$field])) {
                    $update_data[$field] = $input[$field];
                }
            }

            if (isset($update_data['price'])) {
                $update_data['price'] = (float) $update_data['price'];
            }
            if (isset($update_data['stock_quantity'])) {
                $update_data['stock_quantity'] = (int) $update_data['stock_quantity'];
            }

            $updated = supabase_request(
                'PATCH',
                'products?id=eq.' . $id,
                $update_data,
                $user['token']
            );
            json_success($updated[0] ?? $updated);
        } catch (Exception $e) {
            json_error('Failed to update product: ' . $e->getMessage(), 500);
        }
        break;

    case 'DELETE':
        require_admin($role);

        try {
            supabase_request(
                'DELETE',
                'products?id=eq.' . $id,
                null,
                $user['token']
            );
            json_success(['message' => 'Product deleted']);
        } catch (Exception $e) {
            json_error('Failed to delete product: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Method not allowed', 405);
}

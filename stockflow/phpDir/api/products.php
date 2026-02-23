<?php
// ============================================================
// API: /api/products.php — Product list and creation
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
            $endpoint = 'products?select=*,categories(name)&order=name.asc';

            // Optional filters
            if (!empty($_GET['search'])) {
                $search = urlencode($_GET['search']);
                $endpoint .= '&name=ilike.*' . $search . '*';
            }
            if (!empty($_GET['status'])) {
                $endpoint .= '&status=eq.' . urlencode($_GET['status']);
            }
            if (!empty($_GET['category'])) {
                $endpoint .= '&category_id=eq.' . urlencode($_GET['category']);
            }

            $products = supabase_request('GET', $endpoint, null, $user['token']);
            json_success($products);
        } catch (Exception $e) {
            json_error('Failed to fetch products: ' . $e->getMessage(), 500);
        }
        break;

    case 'POST':
        require_admin_or_manager($role);

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['name'])) json_error('Product name is required', 400);
            if (empty($input['sku'])) json_error('SKU is required', 400);
            if (!isset($input['price']) || $input['price'] < 0) json_error('Valid price required', 400);

            $product_data = [
                'name'            => $input['name'],
                'sku'             => $input['sku'],
                'price'           => (float) $input['price'],
                'description'     => $input['description'] ?? '',
                'category_id'     => $input['category_id'] ?? null,
                'stock_quantity'  => (int) ($input['stock_quantity'] ?? 0),
                'reorder_threshold' => (int) ($input['reorder_threshold'] ?? 10),
                'supplier'        => $input['supplier'] ?? '',
                'image_url'       => $input['image_url'] ?? null,
                'status'          => 'active',
            ];

            $created = supabase_request('POST', 'products', $product_data, $user['token']);
            json_success($created, 201);
        } catch (Exception $e) {
            json_error('Failed to create product: ' . $e->getMessage(), 500);
        }
        break;

    default:
        json_error('Method not allowed', 405);
}

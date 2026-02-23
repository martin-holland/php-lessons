<?php
// ============================================================
// API: /api/inventory.php — Stock overview
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

try {
    // Get products with stock info
    $products = supabase_request(
        'GET',
        'products?status=eq.active&select=id,name,sku,stock_quantity,reorder_threshold,category_id,categories(name)&order=stock_quantity.asc',
        null,
        $user['token']
    );

    // Add stock status to each product
    $inventory = array_map(function ($product) {
        $qty = $product['stock_quantity'];
        $threshold = $product['reorder_threshold'];

        if ($qty === 0) {
            $status = 'out_of_stock';
        } elseif ($qty <= $threshold) {
            $status = 'low_stock';
        } else {
            $status = 'in_stock';
        }

        return array_merge($product, ['stock_status' => $status]);
    }, $products);

    // Summary stats
    $summary = [
        'total_products' => count($inventory),
        'out_of_stock' => count(array_filter($inventory, fn($p) => $p['stock_status'] === 'out_of_stock')),
        'low_stock' => count(array_filter($inventory, fn($p) => $p['stock_status'] === 'low_stock')),
        'in_stock' => count(array_filter($inventory, fn($p) => $p['stock_status'] === 'in_stock')),
    ];

    json_success([
        'products' => $inventory,
        'summary' => $summary,
    ]);
} catch (Exception $e) {
    json_error('Failed to fetch inventory: ' . $e->getMessage(), 500);
}

<?php
// ============================================================
// API: /api/dashboard.php — Dashboard statistics
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
    // Get products count
    $products = supabase_request(
        'GET',
        'products?status=eq.active&select=id,stock_quantity,reorder_threshold',
        null,
        $user['token']
    );

    $totalProducts = count($products);
    $lowStock = count(array_filter($products, fn($p) => $p['stock_quantity'] <= $p['reorder_threshold'] && $p['stock_quantity'] > 0));
    $outOfStock = count(array_filter($products, fn($p) => $p['stock_quantity'] === 0));

    // Get recent orders
    $recentOrders = supabase_request(
        'GET',
        'orders?order=created_at.desc&limit=5',
        null,
        $user['token']
    );

    // Get order stats
    $allOrders = supabase_request(
        'GET',
        'orders?select=status,total_amount',
        null,
        $user['token']
    );

    $totalOrders = count($allOrders);
    $pendingOrders = count(array_filter($allOrders, fn($o) => $o['status'] === 'draft' || $o['status'] === 'confirmed'));
    $totalRevenue = array_sum(array_map(fn($o) => $o['status'] === 'fulfilled' ? (float)$o['total_amount'] : 0, $allOrders));

    // Get low stock products
    $lowStockProducts = supabase_request(
        'GET',
        'products?status=eq.active&stock_quantity=lte.10&order=stock_quantity.asc&limit=5&select=id,name,sku,stock_quantity,reorder_threshold',
        null,
        $user['token']
    );

    json_success([
        'stats' => [
            'total_products' => $totalProducts,
            'low_stock' => $lowStock,
            'out_of_stock' => $outOfStock,
            'total_orders' => $totalOrders,
            'pending_orders' => $pendingOrders,
            'total_revenue' => $totalRevenue,
        ],
        'recent_orders' => $recentOrders,
        'low_stock_products' => $lowStockProducts,
        'user_role' => $role,
    ]);
} catch (Exception $e) {
    json_error('Failed to fetch dashboard data: ' . $e->getMessage(), 500);
}

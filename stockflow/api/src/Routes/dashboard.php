<?php

/**
 * Dashboard Routes — SOLVED
 *
 * EXERCISE 7: Aggregate data into dashboard summaries
 *
 * This is the capstone exercise — it combines everything:
 * pre-processing, date handling, and data aggregation.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/dashboard/summary — Dashboard overview (authenticated)
// ============================================================
// EXERCISE 7: SOLVED
// ============================================================

$app->get('/api/dashboard/summary', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Fetch all products and orders
    $products = $auth->query('products', ['select' => '*']);
    $orders = $auth->query('orders', ['select' => '*']);

    // --- INVENTORY STATS ---
    $totalProducts = count($products);
    $outOfStockCount = 0;
    $lowStockCount = 0;
    $totalValue = 0;
    $lowStockProducts = [];

    foreach ($products as $product) {
        $qty = (int)$product['stock_quantity'];
        $threshold = (int)$product['reorder_threshold'];
        $price = (float)$product['price'];

        $totalValue += $price * $qty;

        if ($qty <= 0) {
            $outOfStockCount++;
        } elseif ($qty <= $threshold) {
            $lowStockCount++;
            $lowStockProducts[] = [
                'name' => $product['name'],
                'stock_quantity' => $qty,
                'reorder_threshold' => $threshold,
            ];
        }
    }

    // Sort low stock products by urgency (lowest stock first)
    usort($lowStockProducts, function ($a, $b) {
        return $a['stock_quantity'] - $b['stock_quantity'];
    });

    // Take top 5 most urgent
    $lowStockProducts = array_slice($lowStockProducts, 0, 5);

    // --- ORDER STATS ---
    $totalOrders = count($orders);
    $ordersByStatus = [
        'draft' => 0,
        'confirmed' => 0,
        'fulfilled' => 0,
        'cancelled' => 0,
    ];
    $totalRevenue = 0;

    foreach ($orders as $order) {
        $status = $order['status'];
        if (isset($ordersByStatus[$status])) {
            $ordersByStatus[$status]++;
        }

        // Only count fulfilled orders as revenue
        if ($status === 'fulfilled') {
            $totalRevenue += (float)$order['total_amount'];
        }
    }

    // --- BUILD RESPONSE ---
    $summary = [
        'inventory' => [
            'total_products' => $totalProducts,
            'total_value' => round($totalValue, 2),
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
        ],
        'orders' => [
            'total_orders' => $totalOrders,
            'by_status' => $ordersByStatus,
            'total_revenue' => round($totalRevenue, 2),
        ],
        'low_stock_products' => $lowStockProducts,
    ];

    $response->getBody()->write(json_encode($summary));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());

<?php

/**
 * Stock Movement Routes — SOLVED
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 3: Date/time recording for stock movements
 * (Dashboard analytics are in dashboard.php — Exercise 7)
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/stock/movements — List stock movements (authenticated)
// ============================================================
// EXERCISE 3 (Step 2): SOLVED
// ============================================================

$app->get('/api/stock/movements', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Read optional product_id filter
    $params = $request->getQueryParams();
    $productId = $params['product_id'] ?? null;

    $queryParams = [
        'select' => '*,products(name,sku)',
        'order' => 'created_at.desc'
    ];

    if ($productId) {
        $queryParams['product_id'] = 'eq.' . $productId;
    }

    $movements = $auth->query('stock_movements', $queryParams);

    // Post-process: format dates and flatten product name
    $processed = array_map(function ($movement) {
        $dates = formatRelativeDate($movement['created_at']);

        return [
            'id' => $movement['id'],
            'product_id' => $movement['product_id'],
            'product_name' => $movement['products']['name'] ?? 'Unknown',
            'product_sku' => $movement['products']['sku'] ?? '',
            'quantity' => $movement['quantity'],
            'movement_type' => $movement['movement_type'],
            'reason' => $movement['reason'] ?? '',
            'notes' => $movement['notes'] ?? '',
            'created_at' => $movement['created_at'],
            'created_date' => $dates['created_date'],
            'created_ago' => $dates['created_ago'],
        ];
    }, $movements);

    $response->getBody()->write(json_encode($processed));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/stock/movements — Record a stock movement (authenticated)
// ============================================================
// EXERCISE 3 (Step 3): SOLVED
// ============================================================

$app->post('/api/stock/movements', function (Request $request, Response $response) {

    $body = $request->getParsedBody();

    // --- PRE-PROCESSING ---
    // Validate required fields
    if (empty($body['product_id'])) {
        $response->getBody()->write(json_encode(['error' => 'Product ID is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $quantity = (int)($body['quantity'] ?? 0);
    if ($quantity <= 0) {
        $response->getBody()->write(json_encode(['error' => 'Quantity must be greater than 0']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $movementType = $body['movement_type'] ?? '';
    $validTypes = ['in', 'out', 'adjustment'];
    if (!in_array($movementType, $validTypes)) {
        $response->getBody()->write(json_encode([
            'error' => 'Movement type must be one of: in, out, adjustment'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Fetch current product to get stock_quantity
    $products = $auth->query('products', [
        'id' => 'eq.' . $body['product_id'],
        'select' => 'id,name,stock_quantity'
    ]);

    if (empty($products)) {
        $response->getBody()->write(json_encode(['error' => 'Product not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $product = $products[0];
    $currentStock = (int)$product['stock_quantity'];

    // For "out" movements, check enough stock exists
    if ($movementType === 'out' && $quantity > $currentStock) {
        $response->getBody()->write(json_encode([
            'error' => "Not enough stock. Current: $currentStock, requested: $quantity"
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Calculate new stock quantity
    if ($movementType === 'in') {
        $newStock = $currentStock + $quantity;
    } elseif ($movementType === 'out') {
        $newStock = $currentStock - $quantity;
    } else {
        // adjustment — set directly
        $newStock = $quantity;
    }

    // --- INSERT MOVEMENT ---
    $movement = $auth->insert('stock_movements', [
        'product_id' => $body['product_id'],
        'quantity' => $quantity,
        'movement_type' => $movementType,
        'reason' => trim($body['reason'] ?? ''),
        'notes' => trim($body['notes'] ?? ''),
    ]);

    // Update product's stock_quantity
    $auth->update('products', 'id=eq.' . $body['product_id'], [
        'stock_quantity' => $newStock
    ]);

    // --- POST-PROCESSING ---
    $response->getBody()->write(json_encode([
        'message' => 'Stock movement recorded',
        'data' => $movement,
        'new_stock_quantity' => $newStock
    ]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());

<?php

/**
 * Orders Routes — SOLVED
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 3: Date/time handling (timestamps, relative dates)
 * - Exercise 6: CRUD operations for orders and order items
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// Helper function to format dates (used by orders and stock routes)
function formatRelativeDate(string $isoDate): array {
    $timestamp = strtotime($isoDate);
    $daysAgo = (int)floor((time() - $timestamp) / 86400);

    if ($daysAgo === 0) {
        $relative = 'Today';
    } elseif ($daysAgo === 1) {
        $relative = 'Yesterday';
    } else {
        $relative = $daysAgo . ' days ago';
    }

    return [
        'created_date' => date('j M Y, H:i', $timestamp),
        'created_ago' => $relative,
        'age_days' => $daysAgo,
    ];
}

// ============================================================
// GET /api/orders — List orders (authenticated)
// ============================================================
// EXERCISE 3 + 6 (Step 1): SOLVED
// ============================================================

$app->get('/api/orders', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // --- PRE-PROCESSING (Exercise 6 Step 1) ---
    $params = $request->getQueryParams();
    $status = $params['status'] ?? null;

    $queryParams = [
        'order' => 'created_at.desc'
    ];

    if ($status) {
        $queryParams['status'] = 'eq.' . $status;
    }

    $orders = $auth->query('orders', $queryParams);

    // --- POST-PROCESSING (Exercise 3) ---
    $processed = array_map(function ($order) {
        $dates = formatRelativeDate($order['created_at']);

        return array_merge($order, [
            'created_date' => $dates['created_date'],
            'created_ago' => $dates['created_ago'],
            'age_days' => $dates['age_days'],
            'total_amount' => number_format((float)$order['total_amount'], 2),
        ]);
    }, $orders);

    $response->getBody()->write(json_encode($processed));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());


// ============================================================
// GET /api/orders/{id} — Get single order with items (authenticated)
// ============================================================
// EXERCISE 6 (Step 2): SOLVED
// ============================================================

$app->get('/api/orders/{id}', function (Request $request, Response $response, array $args) {

    $id = $args['id'];
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Fetch the order
    $orders = $auth->query('orders', [
        'id' => 'eq.' . $id
    ]);

    if (empty($orders)) {
        $response->getBody()->write(json_encode([
            'error' => 'Order not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $order = $orders[0];

    // Fetch order items
    $items = $auth->query('order_items', [
        'order_id' => 'eq.' . $id,
        'select' => '*'
    ]);

    // Apply date formatting (Exercise 3)
    $dates = formatRelativeDate($order['created_at']);
    $order['created_date'] = $dates['created_date'];
    $order['created_ago'] = $dates['created_ago'];
    $order['age_days'] = $dates['age_days'];
    $order['total_amount'] = number_format((float)$order['total_amount'], 2);
    $order['items'] = $items;

    $response->getBody()->write(json_encode($order));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/orders — Create an order with items (authenticated)
// ============================================================
// EXERCISE 6 (Step 3): SOLVED
// ============================================================

$app->post('/api/orders', function (Request $request, Response $response) {

    $body = $request->getParsedBody();

    // --- PRE-PROCESSING ---
    // Validate customer_name
    if (empty($body['customer_name'])) {
        $response->getBody()->write(json_encode([
            'error' => 'Customer name is required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Validate items
    if (empty($body['items']) || !is_array($body['items'])) {
        $response->getBody()->write(json_encode([
            'error' => 'At least one item is required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Validate each item
    foreach ($body['items'] as $item) {
        if (empty($item['product_id']) || !isset($item['quantity']) || !isset($item['unit_price'])) {
            $response->getBody()->write(json_encode([
                'error' => 'Each item must have product_id, quantity, and unit_price'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    // --- CREATE THE ORDER ---
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Step 1: Insert the order (total_amount = 0 for now)
    $order = $auth->insert('orders', [
        'customer_name' => trim($body['customer_name']),
        'notes' => trim($body['notes'] ?? ''),
        'status' => 'draft',
        'total_amount' => 0
    ]);
    $orderId = $order[0]['id'];

    // Step 2: Insert each item and calculate total
    $totalAmount = 0;
    foreach ($body['items'] as $item) {
        $lineTotal = (float)$item['quantity'] * (float)$item['unit_price'];
        $totalAmount += $lineTotal;

        $auth->insert('order_items', [
            'order_id' => $orderId,
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'] ?? '',
            'quantity' => (int)$item['quantity'],
            'unit_price' => (float)$item['unit_price'],
            'line_total' => $lineTotal
        ]);
    }

    // Step 3: Update the order with the calculated total
    $auth->update('orders', 'id=eq.' . $orderId, [
        'total_amount' => $totalAmount
    ]);

    // --- POST-PROCESSING ---
    $response->getBody()->write(json_encode([
        'message' => 'Order created successfully',
        'data' => [
            'id' => $orderId,
            'customer_name' => trim($body['customer_name']),
            'status' => 'draft',
            'total_amount' => number_format($totalAmount, 2),
        ]
    ]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// PUT /api/orders/{id}/status — Update order status (authenticated)
// ============================================================
// EXERCISE 6 (Step 4): SOLVED
// ============================================================

$app->put('/api/orders/{id}/status', function (Request $request, Response $response, array $args) {

    $id = $args['id'];
    $body = $request->getParsedBody();
    $newStatus = $body['status'] ?? null;

    // Validate new status value
    $validStatuses = ['draft', 'confirmed', 'fulfilled', 'cancelled'];
    if (!$newStatus || !in_array($newStatus, $validStatuses)) {
        $response->getBody()->write(json_encode([
            'error' => 'Invalid status. Must be one of: draft, confirmed, fulfilled, cancelled'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Fetch current order to check current status
    $orders = $auth->query('orders', [
        'id' => 'eq.' . $id
    ]);

    if (empty($orders)) {
        $response->getBody()->write(json_encode([
            'error' => 'Order not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $currentStatus = $orders[0]['status'];

    // Define valid state transitions
    $validTransitions = [
        'draft' => ['confirmed', 'cancelled'],
        'confirmed' => ['fulfilled', 'cancelled'],
    ];

    // Check if the transition is valid
    $allowed = $validTransitions[$currentStatus] ?? [];
    if (!in_array($newStatus, $allowed)) {
        $response->getBody()->write(json_encode([
            'error' => "Cannot change status from '$currentStatus' to '$newStatus'"
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Update the status
    $updated = $auth->update('orders', 'id=eq.' . $id, [
        'status' => $newStatus
    ]);

    $response->getBody()->write(json_encode([
        'message' => "Order status changed to '$newStatus'",
        'data' => $updated
    ]));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());

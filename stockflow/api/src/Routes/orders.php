<?php

/**
 * Orders Route
 *
 * Replaces 13-orders.php. The original did two things:
 * 1. Queried Supabase for orders (sorted by created_at desc)
 * 2. Rendered an HTML table
 *
 * This endpoint only does #1 and returns JSON.
 * React will handle #2 (rendering the table).
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

$app->get('/api/orders', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Same query as the original 13-orders.php line 8:
    // $orders = $auth->query('orders', ['order' => 'created_at.desc']);
    $orders = $auth->query('orders', [
        'order' => 'created_at.desc'
    ]);

    $response->getBody()->write(json_encode($orders));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());

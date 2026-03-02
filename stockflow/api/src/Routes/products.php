<?php

/**
 * Products Route
 *
 * Replaces 12-products.php. The original did two things:
 * 1. Queried Supabase for products (with category join)
 * 2. Rendered an HTML table
 *
 * This endpoint only does #1 and returns JSON.
 * React will handle #2 (rendering the table).
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

$app->get('/api/products', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Same query as the original 12-products.php line 8-11:
    // $products = $auth->query('products', ['select' => '*,categories(name)', 'order' => 'name.asc']);
    $products = $auth->query('products', [
        'select' => '*,categories(name)',
        'order' => 'name.asc'
    ]);

    $response->getBody()->write(json_encode($products));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());

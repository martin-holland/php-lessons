<?php

/**
 * AI Routes — Gemini Integration — SOLVED
 *
 * EXERCISE 8: Use the GeminiAI class to add AI-powered features
 *
 * The GeminiAI class is already built (src/AI/GeminiAI.php).
 * These routes fetch real data and send it to Gemini with prompts.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\AI\GeminiAI;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// POST /api/ai/describe — Generate a product description
// ============================================================
// EXERCISE 8 (Step 1): SOLVED
// ============================================================

$app->post('/api/ai/describe', function (Request $request, Response $response) {

    $body = $request->getParsedBody();
    $productId = $body['product_id'] ?? null;

    if (!$productId) {
        $response->getBody()->write(json_encode(['error' => 'product_id is required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Fetch the product from Supabase
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $products = $auth->query('products', [
        'id' => 'eq.' . $productId,
        'select' => '*,categories(name)'
    ]);

    if (empty($products)) {
        $response->getBody()->write(json_encode(['error' => 'Product not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $product = $products[0];
    $categoryName = $product['categories']['name'] ?? 'General';

    // Build prompt
    $prompt = "Write a short product description (2-3 sentences) for a product called: " . $product['name'] . ". "
            . "Category: " . $categoryName . ". "
            . "Price: " . number_format((float)$product['price'], 2) . " EUR. "
            . "Make it engaging and suitable for an e-commerce product listing.";

    try {
        $ai = new GeminiAI();
        $description = $ai->ask($prompt);

        $response->getBody()->write(json_encode([
            'description' => $description
        ]));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => 'AI generation failed: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());


// ============================================================
// POST /api/ai/stock-advice — Get AI advice on stock levels
// ============================================================
// EXERCISE 8 (Step 2): SOLVED
// ============================================================

$app->post('/api/ai/stock-advice', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Fetch all products
    $products = $auth->query('products', [
        'select' => 'name,stock_quantity,reorder_threshold',
        'status' => 'eq.active'
    ]);

    // Filter to low-stock products in PHP
    $lowStock = array_filter($products, function ($p) {
        return (int)$p['stock_quantity'] <= (int)$p['reorder_threshold'];
    });

    if (empty($lowStock)) {
        $response->getBody()->write(json_encode([
            'advice' => 'All products are well-stocked. No reorders needed at this time.',
            'products' => []
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Build prompt
    $lines = [];
    foreach ($lowStock as $p) {
        $lines[] = "- " . $p['name'] . ": " . $p['stock_quantity'] . " in stock, threshold: " . $p['reorder_threshold'];
    }

    $prompt = "These products are running low on stock. For each, suggest a reorder quantity based on the current stock and threshold. Give a brief recommendation for each:\n\n"
            . implode("\n", $lines)
            . "\n\nKeep recommendations concise and practical.";

    try {
        $ai = new GeminiAI();
        $advice = $ai->ask($prompt);

        $response->getBody()->write(json_encode([
            'advice' => $advice,
            'products' => array_values($lowStock)
        ]));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => 'AI generation failed: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());


// ============================================================
// POST /api/ai/summarize-orders — Summarize recent orders
// ============================================================
// EXERCISE 8 (Step 3 — Stretch): SOLVED
// ============================================================

$app->post('/api/ai/summarize-orders', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Fetch recent orders
    $orders = $auth->query('orders', [
        'select' => '*',
        'order' => 'created_at.desc',
        'limit' => 20
    ]);

    if (empty($orders)) {
        $response->getBody()->write(json_encode([
            'summary' => 'No orders found to summarize.'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    // Build prompt with order data
    $lines = [];
    foreach ($orders as $o) {
        $date = date('j M Y', strtotime($o['created_at']));
        $lines[] = "- $date | " . $o['customer_name'] . " | Status: " . $o['status'] . " | Total: " . number_format((float)$o['total_amount'], 2) . " EUR";
    }

    $prompt = "Here are the recent orders for our inventory management system. Summarize the trends, identify any patterns, and provide a brief business insight:\n\n"
            . implode("\n", $lines)
            . "\n\nKeep the summary to 3-5 sentences.";

    try {
        $ai = new GeminiAI();
        $summary = $ai->ask($prompt);

        $response->getBody()->write(json_encode([
            'summary' => $summary
        ]));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Exception $e) {
        $response->getBody()->write(json_encode([
            'error' => 'AI generation failed: ' . $e->getMessage()
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }

})->add(new AuthMiddleware());

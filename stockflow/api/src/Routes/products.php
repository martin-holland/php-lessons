<?php

/**
 * Products Routes — SOLVED
 *
 * EXERCISES IN THIS FILE:
 * - Exercise 1: Pre-process product data (stock status, formatted prices)
 * - Exercise 2: Add search and filtering via query parameters
 * - Exercise 4: Full CRUD operations (create, update, delete)
 * - Exercise 5: Image upload to Supabase Storage
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// ============================================================
// GET /api/products — List products (public)
// ============================================================
// EXERCISE 1 + 2: SOLVED
// ============================================================

$app->get('/api/products', function (Request $request, Response $response) {

    $auth = new SupabaseAuth();

    // --- PRE-PROCESSING (Exercise 2) ---
    $params = $request->getQueryParams();
    $search = $params['search'] ?? null;
    $category = $params['category'] ?? null;
    $status = $params['status'] ?? null;
    $sort = $params['sort'] ?? 'name';
    $order = $params['order'] ?? 'asc';
    $page = (int)($params['page'] ?? 1);
    $limit = (int)($params['limit'] ?? 50);

    // Build query parameters for Supabase
    $queryParams = [
        'select' => '*,categories(name)',
        'order' => $sort . '.' . $order,
        'limit' => $limit,
        'offset' => ($page - 1) * $limit
    ];

    // Add search filter (case-insensitive name match)
    if ($search) {
        $queryParams['name'] = 'ilike.%' . $search . '%';
    }

    // Add status filter
    if ($status) {
        $queryParams['status'] = 'eq.' . $status;
    }

    $products = $auth->query('products', $queryParams);

    // --- POST-PROCESSING (Exercise 1) ---
    $processed = array_map(function ($product) {
        // Calculate stock status
        if ($product['stock_quantity'] <= 0) {
            $stockStatus = 'out_of_stock';
        } elseif ($product['stock_quantity'] <= $product['reorder_threshold']) {
            $stockStatus = 'low_stock';
        } else {
            $stockStatus = 'in_stock';
        }

        return [
            'id' => $product['id'],
            'name' => $product['name'],
            'sku' => $product['sku'],
            'price' => number_format((float)$product['price'], 2),
            'description' => $product['description'] ?? '',
            'stock_quantity' => $product['stock_quantity'],
            'stock_status' => $stockStatus,
            'category_name' => $product['categories']['name'] ?? 'Uncategorized',
            'category_id' => $product['category_id'] ?? null,
            'image_url' => $product['image_url'] ?? null,
            'status' => $product['status'],
        ];
    }, $products);

    // Filter by category name in PHP (since category is a joined table)
    if ($category) {
        $processed = array_values(array_filter($processed, function ($p) use ($category) {
            return $p['category_name'] === $category;
        }));
    }

    $response->getBody()->write(json_encode($processed));
    return $response->withHeader('Content-Type', 'application/json');
});


// ============================================================
// GET /api/products/{id} — Get single product (public)
// ============================================================
// EXERCISE 4 (Step 1): SOLVED
// ============================================================

$app->get('/api/products/{id}', function (Request $request, Response $response, array $args) {

    $id = $args['id'];
    $auth = new SupabaseAuth();

    $products = $auth->query('products', [
        'id' => 'eq.' . $id,
        'select' => '*,categories(name)'
    ]);

    if (empty($products)) {
        $response->getBody()->write(json_encode([
            'error' => 'Product not found'
        ]));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }

    $product = $products[0];

    // Apply same post-processing as the list route
    if ($product['stock_quantity'] <= 0) {
        $stockStatus = 'out_of_stock';
    } elseif ($product['stock_quantity'] <= $product['reorder_threshold']) {
        $stockStatus = 'low_stock';
    } else {
        $stockStatus = 'in_stock';
    }

    $result = [
        'id' => $product['id'],
        'name' => $product['name'],
        'sku' => $product['sku'],
        'price' => number_format((float)$product['price'], 2),
        'description' => $product['description'] ?? '',
        'stock_quantity' => $product['stock_quantity'],
        'stock_status' => $stockStatus,
        'category_name' => $product['categories']['name'] ?? 'Uncategorized',
        'category_id' => $product['category_id'] ?? null,
        'image_url' => $product['image_url'] ?? null,
        'status' => $product['status'],
    ];

    $response->getBody()->write(json_encode($result));
    return $response->withHeader('Content-Type', 'application/json');

});


// ============================================================
// POST /api/products — Create a product (admin/manager only)
// ============================================================
// EXERCISE 4 (Step 2): SOLVED
// ============================================================

$app->post('/api/products', function (Request $request, Response $response) {

    // --- PRE-PROCESSING ---
    $body = $request->getParsedBody();

    // Validate required fields
    if (empty($body['name']) || empty($body['sku']) || !isset($body['price'])) {
        $response->getBody()->write(json_encode([
            'error' => 'Name, SKU, and price are required'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Sanitize and prepare data
    $data = [
        'name' => trim($body['name']),
        'sku' => trim($body['sku']),
        'price' => (float)$body['price'],
        'description' => trim($body['description'] ?? ''),
        'status' => 'active',
    ];

    // Include optional fields if sent
    if (!empty($body['category_id'])) {
        $data['category_id'] = $body['category_id'];
    }
    if (!empty($body['image_url'])) {
        $data['image_url'] = $body['image_url'];
    }
    if (isset($body['stock_quantity'])) {
        $data['stock_quantity'] = (int)$body['stock_quantity'];
    }
    if (isset($body['reorder_threshold'])) {
        $data['reorder_threshold'] = (int)$body['reorder_threshold'];
    }

    // --- QUERY SUPABASE ---
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $created = $auth->insert('products', $data);

    // --- POST-PROCESSING ---
    $response->getBody()->write(json_encode([
        'message' => 'Product created successfully',
        'data' => $created
    ]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// PUT /api/products/{id} — Update a product (admin/manager only)
// ============================================================
// EXERCISE 4 (Step 3): SOLVED
// ============================================================

$app->put('/api/products/{id}', function (Request $request, Response $response, array $args) {

    $id = $args['id'];
    $body = $request->getParsedBody();

    // Build only the fields that were actually sent
    $data = [];
    if (isset($body['name']))              $data['name'] = trim($body['name']);
    if (isset($body['sku']))               $data['sku'] = trim($body['sku']);
    if (isset($body['price']))             $data['price'] = (float)$body['price'];
    if (isset($body['description']))       $data['description'] = trim($body['description']);
    if (isset($body['category_id']))       $data['category_id'] = $body['category_id'];
    if (isset($body['status']))            $data['status'] = $body['status'];
    if (isset($body['image_url']))         $data['image_url'] = $body['image_url'];
    if (isset($body['stock_quantity']))    $data['stock_quantity'] = (int)$body['stock_quantity'];
    if (isset($body['reorder_threshold'])) $data['reorder_threshold'] = (int)$body['reorder_threshold'];

    // Nothing to update?
    if (empty($data)) {
        $response->getBody()->write(json_encode([
            'error' => 'No fields to update'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // --- QUERY SUPABASE ---
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $updated = $auth->update('products', 'id=eq.' . $id, $data);

    // --- POST-PROCESSING ---
    $response->getBody()->write(json_encode([
        'message' => 'Product updated successfully',
        'data' => $updated
    ]));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// DELETE /api/products/{id} — Delete a product (admin only)
// ============================================================
// EXERCISE 4 (Step 4): SOLVED — using soft delete (archive)
// ============================================================

$app->delete('/api/products/{id}', function (Request $request, Response $response, array $args) {

    $id = $args['id'];

    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    // Soft delete — set status to 'archived' instead of removing data
    $auth->update('products', 'id=eq.' . $id, [
        'status' => 'archived'
    ]);

    $response->getBody()->write(json_encode([
        'message' => 'Product archived successfully'
    ]));
    return $response->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());


// ============================================================
// POST /api/products/upload-image — Upload a product image (authenticated)
// ============================================================
// EXERCISE 5: SOLVED
// ============================================================

$app->post('/api/products/upload-image', function (Request $request, Response $response) {

    $files = $request->getUploadedFiles();
    $file = $files['image'] ?? null;

    // --- PRE-PROCESSING ---
    // Check that a file was uploaded
    if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
        $response->getBody()->write(json_encode([
            'error' => 'No file uploaded or upload error'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($file->getClientMediaType(), $allowedTypes)) {
        $response->getBody()->write(json_encode([
            'error' => 'Invalid file type. Allowed: JPEG, PNG, WebP, GIF'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Validate file size (max 5MB)
    if ($file->getSize() > 5 * 1024 * 1024) {
        $response->getBody()->write(json_encode([
            'error' => 'File too large. Maximum size: 5MB'
        ]));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }

    // Generate unique filename
    $filename = uniqid() . '-' . $file->getClientFilename();

    // --- UPLOAD TO SUPABASE STORAGE ---
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $fileData = (string) $file->getStream();
    $auth->uploadFile('product-images', $filename, $fileData, $file->getClientMediaType());

    $publicUrl = $auth->getPublicUrl('product-images', $filename);

    // --- POST-PROCESSING ---
    $response->getBody()->write(json_encode(['image_url' => $publicUrl]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');

})->add(new AuthMiddleware());

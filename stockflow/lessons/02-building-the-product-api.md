# Lesson 2: Building the Product API

## Learning Objectives

By the end of this lesson, you will:
1. Implement REST API patterns for CRUD operations
2. Handle different HTTP methods in a single file
3. Read and validate JSON request bodies
4. Apply role-based permissions to endpoints
5. Build a complete Product management API

---

## Part 1: Understanding REST APIs

### What is REST?

REST (Representational State Transfer) is a pattern for designing APIs. It uses HTTP methods to indicate what action to perform:

| HTTP Method | Action | Example |
|-------------|--------|---------|
| GET | Read | Get list of products |
| POST | Create | Add a new product |
| PUT/PATCH | Update | Modify a product |
| DELETE | Remove | Delete a product |

### REST URL Patterns

```
GET    /api/products.php        → List all products
POST   /api/products.php        → Create new product

GET    /api/product.php?id=5    → Get product #5
PUT    /api/product.php?id=5    → Update product #5
DELETE /api/product.php?id=5    → Delete product #5
```

**Notice:** We use two files:
- `products.php` (plural) - for collections (list, create)
- `product.php` (singular) - for individual items (get, update, delete)

---

## Part 2: Building the Categories API

Let's start simple with categories before tackling products.

### Step 1: Create categories.php

Create `phpDir/api/categories.php`:

```php
<?php
// ============================================================
// API: /api/categories.php — Category list and creation
// ============================================================

// Include our helper files
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/json_response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/role.php';

// Authenticate the user
$user = validate_token();
$role = get_user_role($user['id'], $user['token']);

// Get the HTTP method
$method = $_SERVER['REQUEST_METHOD'];

// Handle different methods
switch ($method) {
    case 'GET':
        // Anyone can view categories
        handleGetCategories($user['token']);
        break;

    case 'POST':
        // Only admin/manager can create
        require_admin_or_manager($role);
        handleCreateCategory($user['token']);
        break;

    default:
        json_error('Method not allowed', 405);
}

// ============================================================
// Handler Functions
// ============================================================

function handleGetCategories(string $token): void {
    try {
        $categories = supabase_request(
            'GET',
            'categories?order=name.asc',
            null,
            $token
        );
        json_success($categories);
    } catch (Exception $e) {
        json_error('Failed to fetch categories: ' . $e->getMessage(), 500);
    }
}

function handleCreateCategory(string $token): void {
    try {
        // Read JSON from request body
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate input
        if (empty($input['name'])) {
            json_error('Category name is required', 400);
        }

        // Create the category
        $created = supabase_request('POST', 'categories', [
            'name' => $input['name'],
        ], $token);

        json_success($created, 201);
    } catch (Exception $e) {
        json_error('Failed to create category: ' . $e->getMessage(), 500);
    }
}
```

**Key Concepts Explained:**

1. **`switch ($method)`** - Different code for each HTTP method
2. **`file_get_contents('php://input')`** - Reads JSON request body
3. **`json_decode(..., true)`** - Converts JSON string to PHP array
4. **`require_admin_or_manager($role)`** - Permission check before action

### Understanding php://input

```php
// When React sends this:
fetch('/api/categories.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: 'Electronics' })
});

// PHP receives it in php://input (not $_POST!)
$rawBody = file_get_contents('php://input');
// $rawBody = '{"name":"Electronics"}'

$data = json_decode($rawBody, true);
// $data = ['name' => 'Electronics']
```

**Why not $_POST?**
- `$_POST` only works for form-encoded data
- JSON requests require `php://input`

---

## Part 3: Building the Products List API

### Step 2: Create products.php

Create `phpDir/api/products.php`:

```php
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
        handleListProducts($user['token']);
        break;

    case 'POST':
        require_admin_or_manager($role);
        handleCreateProduct($user['token']);
        break;

    default:
        json_error('Method not allowed', 405);
}

// ============================================================
// GET: List Products
// ============================================================
function handleListProducts(string $token): void {
    try {
        // Build the query
        // select=*,categories(name) joins the category name
        $endpoint = 'products?select=*,categories(name)&order=name.asc';

        // Handle optional filters from query string
        // Example: /api/products.php?status=active&search=keyboard

        if (!empty($_GET['search'])) {
            // ilike = case-insensitive LIKE
            $search = urlencode($_GET['search']);
            $endpoint .= '&name=ilike.*' . $search . '*';
        }

        if (!empty($_GET['status'])) {
            $endpoint .= '&status=eq.' . urlencode($_GET['status']);
        }

        if (!empty($_GET['category'])) {
            $endpoint .= '&category_id=eq.' . urlencode($_GET['category']);
        }

        $products = supabase_request('GET', $endpoint, null, $token);
        json_success($products);

    } catch (Exception $e) {
        json_error('Failed to fetch products: ' . $e->getMessage(), 500);
    }
}

// ============================================================
// POST: Create Product
// ============================================================
function handleCreateProduct(string $token): void {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate required fields
        if (empty($input['name'])) {
            json_error('Product name is required', 400);
        }
        if (empty($input['sku'])) {
            json_error('SKU is required', 400);
        }
        if (!isset($input['price']) || $input['price'] < 0) {
            json_error('Valid price is required', 400);
        }

        // Build the product data
        $productData = [
            'name'             => $input['name'],
            'sku'              => $input['sku'],
            'price'            => (float) $input['price'],
            'description'      => $input['description'] ?? '',
            'category_id'      => $input['category_id'] ?? null,
            'stock_quantity'   => (int) ($input['stock_quantity'] ?? 0),
            'reorder_threshold'=> (int) ($input['reorder_threshold'] ?? 10),
            'supplier'         => $input['supplier'] ?? '',
            'status'           => 'active',
        ];

        $created = supabase_request('POST', 'products', $productData, $token);
        json_success($created, 201);

    } catch (Exception $e) {
        json_error('Failed to create product: ' . $e->getMessage(), 500);
    }
}
```

**Understanding PostgREST Query Syntax:**

Supabase uses PostgREST. Here are common query patterns:

```php
// Filter by exact value
'products?status=eq.active'

// Filter by partial match (case-insensitive)
'products?name=ilike.*keyboard*'

// Select specific columns
'products?select=id,name,price'

// Join related table
'products?select=*,categories(name)'

// Order results
'products?order=created_at.desc'

// Limit results
'products?limit=10'

// Combine filters
'products?status=eq.active&order=name.asc&limit=10'
```

---

## Part 4: Single Product CRUD

### Step 3: Create product.php

Create `phpDir/api/product.php`:

```php
<?php
// ============================================================
// API: /api/product.php — Single product CRUD
// ============================================================
// Handles: GET, PUT/PATCH, DELETE for individual products
// Requires: ?id=UUID parameter

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/json_response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/role.php';

$user = validate_token();
$role = get_user_role($user['id'], $user['token']);

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? '';

// ID is required for all operations
if (empty($id)) {
    json_error('Product ID is required', 400);
}

switch ($method) {
    case 'GET':
        handleGetProduct($id, $user['token']);
        break;

    case 'PUT':
    case 'PATCH':
        require_admin_or_manager($role);
        handleUpdateProduct($id, $user['token']);
        break;

    case 'DELETE':
        require_admin($role);  // Only admins can delete
        handleDeleteProduct($id, $user['token']);
        break;

    default:
        json_error('Method not allowed', 405);
}

// ============================================================
// GET: Fetch Single Product
// ============================================================
function handleGetProduct(string $id, string $token): void {
    try {
        $product = supabase_request(
            'GET',
            'products?id=eq.' . $id . '&select=*,categories(name)',
            null,
            $token
        );

        // Supabase returns an array, even for single items
        if (empty($product)) {
            json_error('Product not found', 404);
        }

        json_success($product[0]);
    } catch (Exception $e) {
        json_error('Failed to fetch product: ' . $e->getMessage(), 500);
    }
}

// ============================================================
// PUT/PATCH: Update Product
// ============================================================
function handleUpdateProduct(string $id, string $token): void {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        // Only include fields that were provided
        $updateData = [];
        $allowedFields = [
            'name', 'sku', 'description', 'price', 'category_id',
            'stock_quantity', 'reorder_threshold', 'supplier', 'status'
        ];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateData[$field] = $input[$field];
            }
        }

        // Type casting for numeric fields
        if (isset($updateData['price'])) {
            $updateData['price'] = (float) $updateData['price'];
        }
        if (isset($updateData['stock_quantity'])) {
            $updateData['stock_quantity'] = (int) $updateData['stock_quantity'];
        }

        if (empty($updateData)) {
            json_error('No valid fields to update', 400);
        }

        $updated = supabase_request(
            'PATCH',
            'products?id=eq.' . $id,
            $updateData,
            $token
        );

        json_success($updated[0] ?? $updated);
    } catch (Exception $e) {
        json_error('Failed to update product: ' . $e->getMessage(), 500);
    }
}

// ============================================================
// DELETE: Remove Product
// ============================================================
function handleDeleteProduct(string $id, string $token): void {
    try {
        supabase_request(
            'DELETE',
            'products?id=eq.' . $id,
            null,
            $token
        );

        json_success(['message' => 'Product deleted']);
    } catch (Exception $e) {
        json_error('Failed to delete product: ' . $e->getMessage(), 500);
    }
}
```

**Permission Levels:**

```
Role        | View | Create | Update | Delete
------------|------|--------|--------|--------
Staff       | ✅   | ❌     | ❌     | ❌
Manager     | ✅   | ✅     | ✅     | ❌
Admin       | ✅   | ✅     | ✅     | ✅
```

---

## Part 5: Dashboard API

### Step 4: Create dashboard.php

Create `phpDir/api/dashboard.php`:

```php
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

try {
    // Fetch products for statistics
    $products = supabase_request(
        'GET',
        'products?status=eq.active&select=id,stock_quantity,reorder_threshold',
        null,
        $user['token']
    );

    // Calculate product stats
    $totalProducts = count($products);

    $lowStock = count(array_filter($products, function($p) {
        return $p['stock_quantity'] <= $p['reorder_threshold']
            && $p['stock_quantity'] > 0;
    }));

    $outOfStock = count(array_filter($products, function($p) {
        return $p['stock_quantity'] === 0;
    }));

    // Fetch recent orders
    $recentOrders = supabase_request(
        'GET',
        'orders?order=created_at.desc&limit=5',
        null,
        $user['token']
    );

    // Fetch all orders for statistics
    $allOrders = supabase_request(
        'GET',
        'orders?select=status,total_amount',
        null,
        $user['token']
    );

    $totalOrders = count($allOrders);

    $pendingOrders = count(array_filter($allOrders, function($o) {
        return in_array($o['status'], ['draft', 'confirmed']);
    }));

    $totalRevenue = array_sum(array_map(function($o) {
        return $o['status'] === 'fulfilled' ? (float)$o['total_amount'] : 0;
    }, $allOrders));

    // Fetch low stock products for alerts
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
```

**PHP Array Functions Used:**

```php
// count() - Count elements
$total = count($products);

// array_filter() - Keep items that pass test
$lowStock = array_filter($products, function($p) {
    return $p['stock_quantity'] <= 10;
});

// array_map() - Transform each item
$prices = array_map(function($p) {
    return $p['price'];
}, $products);

// array_sum() - Add all values
$total = array_sum($prices);
```

---

## Exercises

### Exercise 1: Create the Inventory API

Create `phpDir/api/inventory.php` that:
- Returns all products with stock status (in_stock, low_stock, out_of_stock)
- Includes a summary count of each status

<details>
<summary>Solution</summary>

```php
<?php
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/supabase.php';
require_once __DIR__ . '/../includes/json_response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/role.php';

$user = validate_token();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

try {
    $products = supabase_request(
        'GET',
        'products?status=eq.active&select=id,name,sku,stock_quantity,reorder_threshold,categories(name)&order=stock_quantity.asc',
        null,
        $user['token']
    );

    // Add stock status to each product
    $inventory = array_map(function($p) {
        if ($p['stock_quantity'] === 0) {
            $status = 'out_of_stock';
        } elseif ($p['stock_quantity'] <= $p['reorder_threshold']) {
            $status = 'low_stock';
        } else {
            $status = 'in_stock';
        }
        $p['stock_status'] = $status;
        return $p;
    }, $products);

    $summary = [
        'total' => count($inventory),
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
```
</details>

### Exercise 2: Add Search to Products

Modify `products.php` to support these query parameters:
- `?min_price=50` - Products priced at least €50
- `?max_price=100` - Products priced at most €100

<details>
<summary>Hint</summary>

PostgREST operators:
- `gte` = greater than or equal
- `lte` = less than or equal

Example: `products?price=gte.50&price=lte.100`
</details>

### Exercise 3: Stock Movement API

Create `phpDir/api/stock-movement.php` that:
- GET: Returns recent stock movements for a product
- POST: Records a new stock movement and updates product quantity

Think about:
- What fields does a stock movement need?
- How do you update the product's stock_quantity?

---

## Summary

In this lesson, you learned:

1. **REST Patterns** - Using HTTP methods for CRUD operations
2. **Request Bodies** - Reading JSON with `php://input`
3. **Query Parameters** - Filtering with `$_GET`
4. **Validation** - Checking required fields
5. **Role-Based Access** - Different permissions per role
6. **PostgREST Syntax** - Filtering, joining, ordering

### Files Created

```
phpDir/
└── api/
    ├── categories.php    ✅
    ├── products.php      ✅
    ├── product.php       ✅
    └── dashboard.php     ✅
```

### API Summary

| Endpoint | Method | Auth | Permission | Description |
|----------|--------|------|------------|-------------|
| /api/me.php | GET | ✅ | Any | Get current user |
| /api/categories.php | GET | ✅ | Any | List categories |
| /api/categories.php | POST | ✅ | Admin/Manager | Create category |
| /api/products.php | GET | ✅ | Any | List products |
| /api/products.php | POST | ✅ | Admin/Manager | Create product |
| /api/product.php?id=X | GET | ✅ | Any | Get product |
| /api/product.php?id=X | PUT | ✅ | Admin/Manager | Update product |
| /api/product.php?id=X | DELETE | ✅ | Admin | Delete product |
| /api/dashboard.php | GET | ✅ | Any | Get statistics |

### Next Lesson

In Lesson 3, we'll build the React frontend that consumes these APIs.

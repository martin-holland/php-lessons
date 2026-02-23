# Lesson 1: PHP API Fundamentals

## Learning Objectives

By the end of this lesson, you will:
1. Understand the difference between traditional PHP and API-based PHP
2. Create JSON responses instead of HTML
3. Handle different HTTP methods (GET, POST, PUT, DELETE)
4. Implement proper error handling for APIs
5. Set up CORS for cross-origin requests
6. Create reusable helper functions

---

## Part 1: Traditional PHP vs API PHP

### What You Already Know (Lesson 11)

In your authentication lesson, PHP generated complete HTML pages:

```php
<?php
// Traditional PHP - renders HTML
$notes = $auth->query('user_notes');
?>
<!DOCTYPE html>
<html>
<body>
    <?php foreach ($notes as $note): ?>
        <div class="note">
            <h3><?= htmlspecialchars($note['title']) ?></h3>
        </div>
    <?php endforeach; ?>
</body>
</html>
```

### The API Approach (What We'll Learn)

API PHP returns pure data - no HTML:

```php
<?php
// API PHP - returns JSON data
$notes = $auth->query('user_notes');

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $notes
]);
```

### Why Use APIs?

| Traditional PHP | API PHP |
|-----------------|---------|
| PHP renders HTML | JavaScript renders HTML |
| Full page refresh | Instant updates |
| One technology | Flexible frontends |
| Tied to one design | React, Vue, mobile apps |

---

## Part 2: Setting Up the Include Files

We'll create helper files that every API endpoint will use. These go in `phpDir/includes/`.

### Step 1: Create the Config File

Create `phpDir/includes/config.php`:

```php
<?php
// ============================================================
// includes/config.php — Application Configuration
// ============================================================
// This file loads your Supabase credentials securely.
// NEVER commit real credentials to Git!

// First, try to load from config.local.php (for development)
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require_once $localConfig;
    return; // Skip the rest if local config exists
}

// Otherwise, load from environment variables (for Docker/production)
$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_ANON_KEY');

if ($supabaseUrl && $supabaseKey) {
    define('SUPABASE_URL', $supabaseUrl);
    define('SUPABASE_ANON_KEY', $supabaseKey);
    define('SITE_URL', getenv('SITE_URL') ?: 'http://localhost:5173');
} else {
    // No configuration found
    http_response_code(500);
    die(json_encode(['error' => 'Server configuration missing']));
}
```

**Discussion Questions:**
1. Why do we check for `config.local.php` first?
2. What's the difference between `define()` and a regular variable?
3. Why use `getenv()` for Docker environments?

### Step 2: Create the JSON Response Helper

Create `phpDir/includes/json_response.php`:

```php
<?php
// ============================================================
// includes/json_response.php — Standardized JSON Responses
// ============================================================
// All our API responses follow the same format.
// This makes the frontend code simpler and more predictable.

/**
 * Send a success response
 *
 * @param mixed $data    The data to return
 * @param int   $status  HTTP status code (default 200)
 */
function json_success($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');

    echo json_encode([
        'success' => true,
        'data' => $data
    ]);
    exit;
}

/**
 * Send an error response
 *
 * @param string $message  Error message
 * @param int    $status   HTTP status code (default 400)
 */
function json_error(string $message, int $status = 400): void {
    http_response_code($status);
    header('Content-Type: application/json');

    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}
```

**Key Concepts:**
- `http_response_code()` - Sets the HTTP status (200, 400, 404, 500, etc.)
- `header('Content-Type: application/json')` - Tells browsers this is JSON
- `exit` - Stops script execution after sending response

### Step 3: Create the CORS Handler

Create `phpDir/includes/cors.php`:

```php
<?php
// ============================================================
// includes/cors.php — Cross-Origin Resource Sharing
// ============================================================
// When React (localhost:5173) calls PHP (localhost:8080),
// the browser blocks it for security. CORS headers allow it.

// Allow requests from our React frontend
header('Access-Control-Allow-Origin: http://localhost:5173');

// Allow credentials (cookies, auth headers)
header('Access-Control-Allow-Credentials: true');

// Allow these HTTP methods
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

// Allow these headers in requests
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
// Browsers send OPTIONS before POST/PUT/DELETE to check if it's allowed
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
```

**Understanding CORS:**
```
Browser Security Rule:
  JavaScript on site A cannot call APIs on site B

Our Situation:
  React runs on localhost:5173
  PHP runs on localhost:8080
  → Different ports = different "sites"

Solution:
  PHP tells the browser "localhost:5173 is allowed"
  via Access-Control-Allow-Origin header
```

### Step 4: Create the Supabase Client

Create `phpDir/includes/supabase.php`:

```php
<?php
// ============================================================
// includes/supabase.php — Supabase REST API Client
// ============================================================
// This is similar to SupabaseAuth from Lesson 11, but simpler.
// It just makes HTTP requests to Supabase's REST API.

require_once __DIR__ . '/config.php';

/**
 * Make a request to Supabase REST API
 *
 * @param string      $method   HTTP method (GET, POST, PATCH, DELETE)
 * @param string      $endpoint API endpoint (e.g., "products?id=eq.5")
 * @param array|null  $data     Request body for POST/PATCH
 * @param string|null $token    User's access token for RLS
 *
 * @return array Decoded JSON response
 */
function supabase_request(
    string $method,
    string $endpoint,
    ?array $data = null,
    ?string $token = null
): array {
    // Build the full URL
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;

    // Set up headers
    $headers = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
    ];

    // Add user's token for Row Level Security
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    // For POST requests, ask Supabase to return the created row
    if ($method === 'POST') {
        $headers[] = 'Prefer: return=representation';
    }

    // Initialize cURL
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 30,
    ]);

    // Add request body for POST/PATCH
    if ($data !== null && in_array($method, ['POST', 'PATCH', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Handle cURL errors
    if ($error) {
        throw new Exception("Connection error: " . $error);
    }

    // Parse response
    $decoded = json_decode($response, true) ?? [];

    // Handle Supabase errors
    if ($httpCode >= 400) {
        $errorMsg = $decoded['message'] ?? $decoded['error'] ?? "Request failed";
        throw new Exception($errorMsg);
    }

    return $decoded;
}
```

**Comparing to Lesson 11:**

| SupabaseAuth (Lesson 11) | supabase_request (Now) |
|--------------------------|------------------------|
| Class-based | Function-based |
| Stores session state | Stateless |
| Handles OAuth flow | Just makes API calls |
| Renders HTML on error | Throws exceptions |

---

## Part 3: Authentication for APIs

### Step 5: Create the Auth Helper

Create `phpDir/includes/auth.php`:

```php
<?php
// ============================================================
// includes/auth.php — JWT Token Validation
// ============================================================
// In Lesson 11, we used sessions. For APIs, we use JWT tokens.
// The React app sends the token in the Authorization header.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/json_response.php';

/**
 * Get the Authorization header from the request
 *
 * Apache sometimes puts it in different places, so we check multiple.
 */
function get_authorization_header(): string {
    // Standard location
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }

    // Apache with mod_rewrite
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // Apache function
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            return $headers['Authorization'];
        }
    }

    return '';
}

/**
 * Validate the JWT token and return user info
 *
 * @return array User data with 'id', 'email', and 'token'
 */
function validate_token(): array {
    $authHeader = get_authorization_header();

    // Check if header exists
    if (empty($authHeader)) {
        json_error('Missing Authorization header', 401);
    }

    // Extract the token (format: "Bearer eyJhbG...")
    if (!preg_match('/Bearer\s+(.+)$/i', $authHeader, $matches)) {
        json_error('Invalid Authorization header format', 401);
    }

    $token = $matches[1];

    // Validate token with Supabase
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => SUPABASE_URL . '/auth/v1/user',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $token,
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        json_error('Invalid or expired token', 401);
    }

    $user = json_decode($response, true);

    if (!isset($user['id'])) {
        json_error('Could not verify user', 401);
    }

    return [
        'id' => $user['id'],
        'email' => $user['email'] ?? '',
        'token' => $token,
    ];
}
```

**Session vs Token Authentication:**

```
SESSION (Lesson 11):
  1. User logs in
  2. PHP creates session, stores on server
  3. Browser gets session cookie
  4. Cookie sent automatically with every request

TOKEN (API):
  1. User logs in via React
  2. Supabase returns JWT token
  3. React stores token in memory
  4. React sends token in Authorization header

Why tokens for APIs?
  - Stateless (no server-side storage)
  - Works across different servers
  - Mobile apps can use same API
```

### Step 6: Create the Role Helper

Create `phpDir/includes/role.php`:

```php
<?php
// ============================================================
// includes/role.php — Role-Based Access Control
// ============================================================
// Users have roles: admin, manager, or staff
// Different roles can do different things

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/json_response.php';

/**
 * Get the user's role from the database
 */
function get_user_role(string $userId, string $token): string {
    try {
        $result = supabase_request(
            'GET',
            'user_roles?user_id=eq.' . $userId . '&select=role',
            null,
            $token
        );

        // Handle different response formats
        if (isset($result['role'])) {
            return $result['role'];
        }
        if (isset($result[0]['role'])) {
            return $result[0]['role'];
        }

        return 'staff'; // Default role
    } catch (Exception $e) {
        return 'staff';
    }
}

/**
 * Require admin or manager role
 */
function require_admin_or_manager(string $role): void {
    if ($role === 'staff') {
        json_error('Admin or Manager role required', 403);
    }
}

/**
 * Require admin role
 */
function require_admin(string $role): void {
    if ($role !== 'admin') {
        json_error('Admin role required', 403);
    }
}
```

**Understanding HTTP Status Codes:**

| Code | Meaning | When to Use |
|------|---------|-------------|
| 200 | OK | Request succeeded |
| 201 | Created | New resource created |
| 400 | Bad Request | Invalid input data |
| 401 | Unauthorized | Not logged in |
| 403 | Forbidden | Logged in but not allowed |
| 404 | Not Found | Resource doesn't exist |
| 500 | Server Error | Something broke |

---

## Part 4: Your First API Endpoint

### Step 7: Create a Test Endpoint

Create `phpDir/api/me.php`:

```php
<?php
// ============================================================
// API: /api/me.php — Get Current User Info
// ============================================================
// A simple endpoint to test our authentication setup.
// Returns the logged-in user's info and role.

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/json_response.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/role.php';

// Validate the user's token
$user = validate_token();

// Get their role
$role = get_user_role($user['id'], $user['token']);

// Return the info
json_success([
    'id' => $user['id'],
    'email' => $user['email'],
    'role' => $role,
]);
```

**Testing with curl:**

```bash
# First, get a token by logging in via the React app
# Then test the endpoint:

curl http://localhost:8080/api/me.php \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"

# Expected response:
{
  "success": true,
  "data": {
    "id": "abc123...",
    "email": "you@example.com",
    "role": "admin"
  }
}
```

---

## Exercises

### Exercise 1: Create a Health Check Endpoint

Create `phpDir/api/health.php` that:
- Doesn't require authentication
- Returns `{ "status": "ok", "timestamp": "2024-..." }`

<details>
<summary>Solution</summary>

```php
<?php
require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/json_response.php';

json_success([
    'status' => 'ok',
    'timestamp' => date('c'),
]);
```
</details>

### Exercise 2: Add Request Method Check

Modify the `/api/me.php` endpoint to only accept GET requests. Return a 405 error for other methods.

<details>
<summary>Solution</summary>

```php
<?php
require_once __DIR__ . '/../includes/cors.php';
// ... other includes ...

// Check HTTP method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_error('Method not allowed', 405);
}

$user = validate_token();
// ... rest of the code ...
```
</details>

### Exercise 3: Error Handling Practice

What HTTP status code should each situation return?

1. User tries to delete a product but isn't logged in
2. User is logged in but tries to access admin-only page
3. User sends invalid JSON in request body
4. Database connection fails
5. User requests a product that doesn't exist

<details>
<summary>Answers</summary>

1. 401 Unauthorized
2. 403 Forbidden
3. 400 Bad Request
4. 500 Internal Server Error
5. 404 Not Found
</details>

---

## Summary

In this lesson, you learned:

1. **API vs Traditional PHP** - APIs return JSON, not HTML
2. **JSON Responses** - Standardized format with success/error
3. **CORS** - Allowing cross-origin requests from React
4. **Token Authentication** - Using JWT instead of sessions
5. **Role-Based Access** - Checking permissions before actions
6. **HTTP Status Codes** - Communicating results correctly

### Files Created

```
phpDir/
└── includes/
    ├── config.php          ✅
    ├── json_response.php   ✅
    ├── cors.php            ✅
    ├── supabase.php        ✅
    ├── auth.php            ✅
    └── role.php            ✅
```

### Next Lesson

In Lesson 2, we'll use these helpers to build the complete Product API with full CRUD operations.

# StockFlow: Migrating a PHP Application to a Modern API Architecture

## Presentation Overview

This document walks through the migration of a traditional server-rendered PHP application into a modern architecture where PHP serves as a REST API backend, designed to pair with a React frontend.

We cover **what changed**, **why it changed**, and **how the original code maps to the new code** — with side-by-side comparisons at each step.

---

## 1. The Starting Point: What We Had

### Original Application

A PHP learning application running on Docker (Apache + MySQL), serving HTML pages directly from PHP files. Each page is a self-contained `.php` file that handles its own logic, database queries, and HTML rendering.

**Technology stack:**
- PHP 8.0 on Apache (via Docker)
- Supabase (cloud PostgreSQL + Auth + Row Level Security)
- Google OAuth for authentication
- Google Gemini API for AI features
- Bootstrap 3 for styling
- Docker Compose for local development

**How a request worked:**

```
Browser requests /12-products.php
  → Apache finds the file 12-products.php on disk
  → PHP runs the file top to bottom
  → File queries Supabase, builds HTML, prints it
  → Browser receives a complete HTML page
```

### Key Files in the Original App

```
phpDir/src/
├── index.php                  # Home page
├── 12-products.php            # Products page (query + HTML table)
├── 13-orders.php              # Orders page (query + HTML table)
├── 11-authentication.php      # Auth page (login + notes CRUD + HTML)
├── ai-integration.php         # AI story generator (API call + HTML)
├── functions.php              # Navigation helper
├── auth/
│   ├── SupabaseAuth.php       # Supabase client (345 lines)
│   └── callback.php           # OAuth callback handler (189 lines)
├── includes/
│   ├── header.php             # HTML head + Bootstrap
│   └── footer.php             # Closing HTML + scripts
├── css/style.css              # Styles
└── .env                       # API keys
```

### The Problem With This Architecture

Each PHP file is doing **two completely different jobs**:

1. **Backend logic** — authenticating, querying the database, processing data
2. **Frontend rendering** — building HTML tables, formatting dates, escaping output

This coupling means:
- You can't reuse the backend logic from a mobile app or different frontend
- You can't deploy the frontend independently (e.g. on a CDN)
- Testing requires rendering full HTML pages
- Every change touches both concerns

---

## 2. The Goal: What We're Building

### New Architecture

```
┌─────────────────────────────────────────────────┐
│                  BROWSER                         │
│  ┌───────────────────────────────────────────┐   │
│  │         React SPA (future)                │   │
│  │  Sends HTTP requests, receives JSON       │   │
│  └─────────────────┬─────────────────────────┘   │
└─────────────────────┼───────────────────────────┘
                      │ JSON over HTTP
                      ▼
┌─────────────────────────────────────────────────┐
│              PHP REST API (Slim Framework)        │
│  Single entry point → routes → JSON responses    │
│  No HTML, no sessions, no rendering              │
└─────────────────────┬───────────────────────────┘
                      │ cURL
                      ▼
┌─────────────────────────────────────────────────┐
│                SUPABASE (cloud)                   │
│  PostgreSQL + Auth + Row Level Security           │
└─────────────────────────────────────────────────┘
```

**The key principle:** PHP's only job is now to receive HTTP requests and return JSON. It no longer builds HTML. The frontend (React, or even a test page) handles all rendering.

### New File Structure

```
stockflow/
├── api/
│   ├── public/
│   │   ├── index.php              # Single entry point (front controller)
│   │   └── .htaccess              # Routes all requests to index.php
│   ├── src/
│   │   ├── Auth/
│   │   │   └── SupabaseAuth.php   # Stateless Supabase client (166 lines)
│   │   ├── Middleware/
│   │   │   └── AuthMiddleware.php # Token extraction (44 lines)
│   │   └── Routes/
│   │       ├── auth.php           # Login URL, user info, logout
│   │       ├── products.php       # GET /api/products
│   │       └── orders.php         # GET /api/orders
│   ├── vendor/                    # Composer dependencies (auto-generated)
│   ├── .env                       # API keys (not committed)
│   ├── .env.example               # Template (safe to commit)
│   └── composer.json              # Dependencies
├── client/                        # React frontend (future)
└── .gitignore
```

---

## 3. Routing: File-Based vs Front Controller

### Original: One File Per Page

In the original app, Apache served PHP files directly. The URL **is** the file path:

```
URL                       → File on disk
/12-products.php          → phpDir/src/12-products.php
/13-orders.php            → phpDir/src/13-orders.php
/11-authentication.php    → phpDir/src/11-authentication.php
```

No routing logic exists — Apache just finds the matching file and runs it.

### New: Single Entry Point

Every request now goes through one file: `api/public/index.php`. Apache can't find files matching URLs like `/api/products`, so `.htaccess` redirects everything:

**`.htaccess` (4 lines that change everything):**
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f    # Not a real file?
RewriteCond %{REQUEST_FILENAME} !-d    # Not a real directory?
RewriteRule ^ index.php [QSA,L]        # Send to index.php
```

**The Slim Framework** inside `index.php` then matches the URL to the right handler:

```
URL                → Slim matches → Handler file
GET /api/products  → $app->get('/api/products', ...) → Routes/products.php
GET /api/orders    → $app->get('/api/orders', ...)   → Routes/orders.php
GET /api/auth/user → $app->get('/api/auth/user', ...)→ Routes/auth.php
```

### Why This Matters

- **Clean URLs**: `/api/products` instead of `/12-products.php`
- **Centralized middleware**: CORS headers, error handling, and auth checks apply to all routes from one place
- **API conventions**: URLs describe resources (`/products`, `/orders`) not files
- **Flexibility**: Adding a new endpoint means adding a route, not creating a new file and worrying about includes

---

## 4. Dependencies: Manual Includes vs Composer

### Original: Manual `require` Statements

Every PHP file had to explicitly include what it needed:

```php
// 12-products.php
require_once "auth/SupabaseAuth.php";   // Load the auth class
include "functions.php";                // Load navigation helper
include "includes/header.php";          // Load HTML header
// ... page content ...
include "includes/footer.php";          // Load HTML footer
```

If the file path changed, every `require` broke. If you added a new class, every file that used it needed a new `require`.

### New: Composer Autoloading

**`composer.json`** declares dependencies and a class-to-directory mapping:

```json
{
    "require": {
        "slim/slim": "^4.0",
        "slim/psr7": "^1.0",
        "vlucas/phpdotenv": "^5.0"
    },
    "autoload": {
        "psr-4": {
            "StockFlow\\": "src/"
        }
    }
}
```

After running `composer install`, any class can be used anywhere with a `use` statement:

```php
use StockFlow\Auth\SupabaseAuth;    // Autoloads src/Auth/SupabaseAuth.php
use StockFlow\Middleware\AuthMiddleware; // Autoloads src/Middleware/AuthMiddleware.php
```

No `require` statements. Composer maps `StockFlow\Auth\SupabaseAuth` → `src/Auth/SupabaseAuth.php` automatically.

### The Three Dependencies

| Package             | What it does                          | Replaces                                      |
| ------------------- | ------------------------------------- | --------------------------------------------- |
| `slim/slim`         | Routes URLs to PHP functions          | Apache file-based routing                     |
| `slim/psr7`         | HTTP request/response objects         | Raw `$_GET`, `$_POST`, `echo`                 |
| `vlucas/phpdotenv`  | Reads `.env` files into `$_ENV`       | Manual file parsing in `SupabaseAuth::loadEnv()` |

---

## 5. Environment Variables: Manual Parsing vs phpdotenv

### Original: Custom `.env` Parser (30+ lines)

The original `SupabaseAuth` class manually parsed the `.env` file:

```php
// Original SupabaseAuth.php — loadEnv() method
private function loadEnv() {
    $envPath = dirname(__DIR__) . '/.env';

    if (!file_exists($envPath)) {
        throw new Exception("Missing .env file!");
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;    // Skip comments

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2); // Split on first =
            $key = trim($key);
            $value = trim($value);

            switch ($key) {                               // Manual mapping
                case 'SUPABASE_URL':
                    $this->supabaseUrl = $value;
                    break;
                case 'SUPABASE_ANON_KEY':
                    $this->supabaseKey = $value;
                    break;
                case 'SITE_URL':
                    $this->siteUrl = $value;
                    break;
            }
        }
    }
}
```

This works but has limitations: no support for quoted values, multiline values, or variable references. Every new env variable needs a new `case` in the switch.

### New: Two Lines in `index.php`

```php
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();
```

Now `$_ENV['SUPABASE_URL']` is available everywhere. The SupabaseAuth constructor shrinks to:

```php
public function __construct()
{
    $this->supabaseUrl = $_ENV['SUPABASE_URL'];
    $this->supabaseKey = $_ENV['SUPABASE_ANON_KEY'];
    $this->siteUrl = $_ENV['SITE_URL'];
}
```

### Environment Variables: Original vs New

| Variable            | Original          | New                    | Notes                                  |
| ------------------- | ----------------- | ---------------------- | -------------------------------------- |
| `SUPABASE_URL`      | Present           | Present (unchanged)    |                                        |
| `SUPABASE_ANON_KEY` | Present           | Present (unchanged)    |                                        |
| `SITE_URL`          | Present           | Present (unchanged)    | Used for OAuth redirect URLs           |
| `CLIENT_URL`        | **Did not exist** | `http://localhost:5173`| New — tells CORS middleware which domain to allow |
| `GEMINI_API_KEY`    | Present           | Present (unchanged)    |                                        |

The only addition is `CLIENT_URL`. The original app didn't need CORS because PHP served both the page and the data (same origin). With a separate React frontend on a different port, the browser blocks cross-origin requests unless the server explicitly allows them.

---

## 6. Authentication: Sessions vs Stateless Tokens

This is the most significant architectural change.

### Original: Session-Based Authentication

The original `SupabaseAuth` (345 lines) used PHP sessions to persist login state:

```php
// Original constructor — starts a session and checks for stored tokens
public function __construct($debug = true) {
    $this->debug = $debug;
    if (session_status() === PHP_SESSION_NONE) {
        session_start();                              // Start PHP session
    }
    $this->loadEnv();
    if (isset($_SESSION['supabase_access_token'])) {  // Check session
        $this->accessToken = $_SESSION['supabase_access_token'];
        $this->user = $_SESSION['supabase_user'] ?? null;
    }
}

// Original handleCallback — stores tokens in session
public function handleCallback($accessToken, $refreshToken = null) {
    $_SESSION['supabase_access_token'] = $accessToken;  // Store in session
    if ($refreshToken) {
        $_SESSION['supabase_refresh_token'] = $refreshToken;
    }
    $this->accessToken = $accessToken;
    $user = $this->getUser();
    if ($user) {
        $_SESSION['supabase_user'] = $user;              // Store in session
    }
    return $user;
}

// Original logout — clears session
public function logout() {
    if ($this->accessToken) {
        $this->makeRequest('POST', '/auth/v1/logout');
    }
    unset($_SESSION['supabase_access_token']);            // Clear session
    unset($_SESSION['supabase_refresh_token']);
    unset($_SESSION['supabase_user']);
    $this->accessToken = null;
    $this->user = null;
}
```

**How sessions work:** PHP creates a file on the server (in `/tmp/`) containing serialized data. A cookie (`PHPSESSID`) links the browser to that file. On each request, PHP reads the file and restores `$_SESSION`.

**Why this is a problem for deployment:**
- Session files are stored on one server — if you scale to multiple servers, sessions don't follow the user
- Serverless platforms (Vercel, Render free tier) can restart containers at any time, destroying session files
- Sessions are a server-side concept — a React frontend running on a different domain can't share a PHP session

### New: Stateless Token-Based Authentication (166 lines)

The new `SupabaseAuth` has no sessions. The token arrives on every request via the HTTP `Authorization` header:

```php
// New constructor — just reads env vars, no session
public function __construct()
{
    $this->supabaseUrl = $_ENV['SUPABASE_URL'];
    $this->supabaseKey = $_ENV['SUPABASE_ANON_KEY'];
    $this->siteUrl = $_ENV['SITE_URL'];
}

// New method — token is set per-request, not stored
public function setToken(string $token): void
{
    $this->accessToken = $token;
}

// New logout — just calls Supabase, no session to clear
public function logout(): void
{
    if ($this->accessToken) {
        $this->makeRequest('POST', '/auth/v1/logout');
    }
}
```

**The flow:**

```
React stores token in localStorage
  → Every API request includes: Authorization: Bearer <token>
    → AuthMiddleware extracts the token from the header
      → Route handler calls $auth->setToken($token)
        → SupabaseAuth forwards the token to Supabase
          → Supabase validates it and returns data
```

No server-side state. No session files. The API can restart, scale, or move servers without breaking authentication.

### What Was Removed From SupabaseAuth

| Feature                          | Lines | Why removed                             |
| -------------------------------- | ----- | --------------------------------------- |
| `session_start()` + `$_SESSION`  | ~15   | Stateless API — token comes per-request |
| `loadEnv()` manual parser        | ~35   | phpdotenv handles this in index.php     |
| `handleCallback()`               | ~15   | React handles the callback client-side  |
| `isLoggedIn()` / `getCurrentUser()` | ~10 | Relied on session state                 |
| `$debug`, `$logs[]`, `getLogs()` | ~25   | Debug logging for development only      |
| `renderLogs()`                   | ~15   | HTML rendering doesn't belong in an API |
| **Total removed**                | **~115** |                                      |

| Feature                          | Lines | Status                                  |
| -------------------------------- | ----- | --------------------------------------- |
| `makeRequest()` cURL logic       | ~40   | Kept — almost identical                 |
| `query()`, `insert()`, `delete()`| ~15   | Kept — identical                        |
| `getGoogleSignInUrl()`           | ~8    | Kept — identical                        |
| `getUser()`                      | ~10   | Kept — identical                        |
| `logout()`                       | ~5    | Kept — simplified                       |
| `setToken()`                     | ~4    | **New** — replaces session token storage|

**Result:** 345 lines → 166 lines. The core database and auth logic is unchanged. Only the session/state management and developer tooling were removed.

---

## 7. The Middleware Pattern

### Original: Auth Checks Scattered Across Pages

Each page independently checked if the user was logged in:

```php
// 12-products.php
require_once "auth/SupabaseAuth.php";
$auth = new SupabaseAuth();

// Auth check is mixed into the HTML
<?php if ($auth->isLoggedIn()) : ?>
    <p style="color: green;">Logged in as <?php echo htmlspecialchars($auth->getCurrentUser()['email'] ?? 'Guest'); ?></p>
<?php else: ?>
    <p style="color: red;">You are not logged in - <a href="11-authentication.php">Login here</a></p>
<?php endif; ?>
```

Every page repeated this pattern. If you forgot the check, the page was unprotected.

### New: Centralized AuthMiddleware (44 lines)

```php
class AuthMiddleware implements MiddlewareInterface
{
    public function process(Request $request, Handler $handler): Response
    {
        $authHeader = $request->getHeaderLine('Authorization');

        if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
            $response = new SlimResponse();
            $response->getBody()->write(json_encode(['error' => 'No token provided']));
            return $response
                ->withStatus(401)
                ->withHeader('Content-Type', 'application/json');
        }

        $token = substr($authHeader, 7);
        $request = $request->withAttribute('token', $token);

        return $handler->handle($request);
    }
}
```

Routes opt-in to authentication by adding the middleware:

```php
// Protected — middleware runs first, checks for token
$app->get('/api/products', function (...) { ... })->add(new AuthMiddleware());

// Public — no middleware, anyone can call this
$app->get('/api/auth/login-url', function (...) { ... });
```

**Key design decision:** The middleware does NOT validate the token. It only checks that a token exists. Supabase validates the token when `SupabaseAuth::makeRequest()` forwards it in the `Authorization: Bearer` header. If the token is expired or forged, Supabase returns a 401.

This means our PHP code has one source of truth for token validation: Supabase itself. We don't need to decode JWTs, check expiry times, or manage signing keys.

---

## 8. Route Comparisons: Before and After

### Products: 80 Lines → 32 Lines

**Original `12-products.php`** — mixed logic and rendering:

```php
<?php
require_once "auth/SupabaseAuth.php";
$auth = new SupabaseAuth();

try {
    $products = $auth->query('products', [
        'select' => '*,categories(name)',
        'order' => 'name.asc'
    ]);
} catch (Exception $e) {
    $products = [];
    $error = $e->getMessage();
}

include "functions.php";
include "includes/header.php";
echo "<h1>Products Example</h1>";
?>
<section class="content">
<aside class="col-xs-4"><?php Navigation(); ?></aside>
<article class="main-content col-xs-8">
<h1>Products</h1>
<?php if ($auth->isLoggedIn()) : ?>
    <p style="color: green;">Logged in as <?php echo htmlspecialchars($auth->getCurrentUser()['email']); ?></p>
<?php else: ?>
    <p style="color: red;">Not logged in</p>
<?php endif; ?>
<?php if (!empty($products)): ?>
<table style="width: 100%; border-collapse: collapse;">
    <thead><tr style="background-color: #F5F5F5;">
        <th>Name</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th>
    </tr></thead>
    <tbody>
    <?php foreach ($products as $product) : ?>
        <tr>
            <td><?php echo htmlspecialchars($product['name']); ?></td>
            <td><?php echo htmlspecialchars($product['sku']); ?></td>
            <td><?php echo htmlspecialchars($product['categories']['name']); ?></td>
            <td><?php echo number_format($product['price'], 2); ?></td>
            <td><?php echo htmlspecialchars($product['stock_quantity']); ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>
</article></section>
<?php include "includes/footer.php"; ?>
```

**New `Routes/products.php`** — data only:

```php
$app->get('/api/products', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));

    $products = $auth->query('products', [
        'select' => '*,categories(name)',
        'order' => 'name.asc'
    ]);

    $response->getBody()->write(json_encode($products));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());
```

**What's the same:** The Supabase query is identical — `'select' => '*,categories(name)'` and `'order' => 'name.asc'`.

**What's gone:** All HTML rendering (table, headers, navigation, footer, auth status display). React handles this now.

**What's new:** `AuthMiddleware` handles the auth check. `json_encode` replaces HTML output.

### Orders: Same Pattern

The orders endpoint follows the exact same transformation. The Supabase query (`'order' => 'created_at.desc'`) is unchanged. Only the HTML rendering was removed.

### Auth: 189-Line Callback → Client-Side

The biggest simplification. The original `callback.php` was 189 lines of PHP, HTML, CSS, and JavaScript that:
1. Rendered a loading spinner page
2. Used JavaScript to extract tokens from the URL fragment
3. Submitted tokens to itself via a hidden form POST
4. Stored tokens in `$_SESSION`
5. Redirected to the main app

In the new architecture, there is no PHP callback endpoint. The entire OAuth flow is handled client-side:
1. React calls `GET /api/auth/login-url` to get the Google OAuth URL
2. Browser redirects to Google, then Supabase, then back to React
3. React extracts the token from the URL fragment with JavaScript
4. React stores it in `localStorage`
5. React sends it as `Authorization: Bearer <token>` on every API call

---

## 9. CORS: Why It Matters Now

### Why the Original App Didn't Need CORS

In the original app, PHP served both the HTML page and the data. The browser loaded `12-products.php` from `localhost:8005`, and that page queried Supabase from the server side. The browser never made cross-origin requests — everything came from the same server.

### Why the New App Requires CORS

With a React frontend on `localhost:5173` calling a PHP API on `localhost:8005`, the browser sees two different origins. By default, browsers block these cross-origin requests as a security measure.

The CORS middleware in `index.php` tells the browser: "Requests from this origin are allowed":

```php
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $_ENV['CLIENT_URL'] ?? '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});
```

Additionally, before making certain cross-origin requests (POST, DELETE, or any request with custom headers), the browser sends an automatic **preflight OPTIONS request** to ask permission. This handler catches those:

```php
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;   // Just return 200 — the CORS headers are added by the middleware above
});
```

---

## 10. The Front Controller: `index.php`

This is the single file that handles every request. Here is the complete file with annotations:

```php
<?php
// 1. AUTOLOADER
// One require replaces all manual require_once statements.
// Composer maps class names to files: StockFlow\Auth\SupabaseAuth → src/Auth/SupabaseAuth.php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

// 2. ENVIRONMENT
// Reads api/.env into $_ENV. Replaces the 35-line loadEnv() method.
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// 3. APPLICATION
$app = AppFactory::create();

// 4. BUILT-IN MIDDLEWARE
// BodyParsing: JSON request bodies → $request->getParsedBody()
// ErrorMiddleware: Exceptions → proper HTTP error responses (not raw stack traces)
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// 5. CORS — allows React (different origin) to call this API
$app->options('/{routes:.+}', function ($request, $response) {
    return $response;
});
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $_ENV['CLIENT_URL'] ?? '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

// 6. ROUTES — each file registers its own endpoints on $app
require __DIR__ . '/../src/Routes/auth.php';
require __DIR__ . '/../src/Routes/products.php';
require __DIR__ . '/../src/Routes/orders.php';

// 7. RUN — Slim reads the URL, finds the matching route, sends the response
$app->run();
```

### Request Lifecycle Comparison

```
ORIGINAL:
  Browser → Apache → finds 12-products.php → PHP runs it → HTML response

NEW:
  Browser → Apache → .htaccess → index.php → Slim boots
    → CORS middleware runs
    → URL matched to route (/api/products)
    → AuthMiddleware checks for Bearer token
    → Route handler queries Supabase
    → JSON response
```

---

## 11. Local Development Setup

### Prerequisites (macOS)

```bash
brew install php        # PHP 8.5 (or latest)
brew install composer   # PHP package manager (like npm for Node)
```

**Why Homebrew?** Composer can also be installed as a standalone `.phar` file, but Homebrew:
- Installs it globally (just type `composer` anywhere)
- Manages updates centrally (`brew update && brew upgrade`)
- Avoids the `php composer.phar install` verbosity

### Running the API Locally

```bash
cd stockflow/api
composer install                     # Install dependencies (one time)
php -S localhost:8005 -t public/     # Start PHP's built-in dev server
```

No Docker required for development. The built-in server uses `.htaccess` and routes everything through `index.php` automatically.

### Verified Test Results

A test page (`api/public/test.php`) confirmed the full chain works:

| Test                     | Result                        |
| ------------------------ | ----------------------------- |
| Environment variables    | All 4 loaded correctly        |
| SupabaseAuth autoloading | Class found and instantiated  |
| Google OAuth URL         | Valid Supabase URL generated  |
| Supabase product query   | 20 products returned with category joins |

---

## 12. API Endpoints Summary

| Endpoint              | Method | Auth     | Replaces                   | Response                      |
| --------------------- | ------ | -------- | -------------------------- | ----------------------------- |
| `/api/auth/login-url` | GET    | Public   | Part of 11-authentication  | `{"url": "https://..."}` |
| `/api/auth/user`      | GET    | Required | Part of 11-authentication  | User object from Supabase     |
| `/api/auth/logout`    | POST   | Required | Part of 11-authentication  | `{"message": "Logged out"}`|
| `/api/products`       | GET    | Required | 12-products.php (80 lines) | Array of product objects      |
| `/api/orders`         | GET    | Required | 13-orders.php (77 lines)   | Array of order objects        |

---

## 13. Code Size Comparison

| Component             | Original     | New          | Change          |
| --------------------- | ------------ | ------------ | --------------- |
| SupabaseAuth class    | 345 lines    | 166 lines    | -52% (removed sessions, logging, HTML) |
| Products page/route   | 80 lines     | 32 lines     | -60% (removed HTML rendering) |
| Orders page/route     | 77 lines     | 31 lines     | -60% (removed HTML rendering) |
| Auth callback         | 189 lines    | 0 lines      | -100% (moved to React client-side) |
| Auth middleware        | 0 lines      | 44 lines     | New (centralized auth checking) |
| Front controller      | 0 lines      | 59 lines     | New (replaces file-based routing) |
| .htaccess             | 0 lines      | 4 lines      | New (URL rewriting) |
| **Total PHP**         | **~691 lines** | **~336 lines** | **-51%** |

The new codebase is roughly half the size, despite adding new infrastructure (middleware, front controller, CORS). The reduction comes from removing HTML rendering and session management — concerns that now belong to the frontend.

---

## 14. Key Architectural Decisions and Why

### Why Slim Framework (not Laravel)?

Laravel is PHP's most popular framework, but it includes an ORM, templating engine, queue system, and dozens of other features we don't need. Slim gives us just routing and middleware — the two things we actually need. Our API is a thin layer between React and Supabase; a full framework would add complexity without benefit.

### Why stateless (not sessions)?

Sessions store state on the server. This breaks when:
- The server restarts (session files lost)
- You scale to multiple servers (session only exists on one)
- You deploy to serverless platforms (no persistent filesystem)

Stateless means every request carries its own authentication (the Bearer token). The server doesn't need to remember anything between requests. This is how most modern APIs work.

### Why does PHP not validate the token?

Our `AuthMiddleware` checks that a token **exists** but doesn't verify it's valid. Supabase does that validation when we forward the token. This is deliberate:
- Supabase is already the source of truth for authentication
- Validating JWTs in PHP would require knowing Supabase's signing keys
- If Supabase says the token is invalid (returns 401), we pass that through
- One validation point means one place to debug auth issues

### Why keep PHP at all?

With Supabase providing a JavaScript client, React could talk to the database directly. PHP earns its place for:
- **Hiding API keys** (Gemini key shouldn't be in browser JavaScript)
- **Server-side business logic** (complex validations, data aggregation)
- **Learning value** (understanding how backend APIs work)

### Why CORS is needed now but wasn't before

Same-origin policy: browsers block requests between different origins (domain + port). The original app served everything from `localhost:8005`. The new setup has React on `:5173` and PHP on `:8005` — different origins. CORS headers explicitly permit this cross-origin communication.

---

## 15. What Comes Next

### Remaining API Steps
- Dockerfile for containerized deployment
- docker-compose.yml for local development orchestration

### React Frontend (Part B)
- Scaffold with Vite + TypeScript
- Build API service layer
- Build pages: Login, Products, Orders
- Implement client-side OAuth callback

### Deployment (Part C)
- PHP API → Render (Docker web service)
- React → Vercel or Render Static Site
- Production hardening: CORS lockdown, environment variables, rate limiting

---

## Appendix: File Reference

| File | Purpose | Lines |
| ---- | ------- | ----- |
| `api/composer.json` | Declares dependencies and autoloading | 14 |
| `api/public/.htaccess` | Routes all requests to index.php | 4 |
| `api/public/index.php` | Front controller: boots Slim, CORS, loads routes | 59 |
| `api/.env` | Real API keys (never committed) | 18 |
| `api/.env.example` | Template with placeholders (safe to commit) | 18 |
| `api/src/Auth/SupabaseAuth.php` | Stateless Supabase client (cURL) | 166 |
| `api/src/Middleware/AuthMiddleware.php` | Extracts Bearer token from header | 44 |
| `api/src/Routes/auth.php` | Login URL, user info, logout endpoints | 58 |
| `api/src/Routes/products.php` | GET /api/products endpoint | 32 |
| `api/src/Routes/orders.php` | GET /api/orders endpoint | 31 |
| `api/public/test.php` | Development verification page (delete before deploy) | ~70 |
| `stockflow/.gitignore` | Prevents secrets and generated files from git | 8 |

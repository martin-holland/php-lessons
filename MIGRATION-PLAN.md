# Migration Plan: PHP Backend + React Frontend

## Current State Summary

Your app is a **server-rendered PHP application** where PHP handles both logic and HTML output. It uses:

- **Supabase** as the database (queried via PHP cURL / PostgREST)
- **Supabase Auth** for Google OAuth
- **Google Gemini** for AI features
- **Docker** (PHP-Apache + MySQL + phpMyAdmin) for local dev
- **File-based routing** (each page is a `.php` file)
- **Bootstrap 3** for styling

The app has real functionality: authentication, products listing, orders display, notes CRUD, and AI story generation.

---

## The Architecture: PHP API Backend + React SPA Frontend

```
┌──────────────────────────────────────────────────────────────┐
│                        BROWSER                               │
│  ┌────────────────────────────────────────────────────────┐  │
│  │              React SPA (Vite + TypeScript)              │  │
│  │  - React Router for navigation                         │  │
│  │  - Fetch/Axios calls to PHP API                        │  │
│  │  - Tailwind CSS or similar for styling                 │  │
│  └──────────────────────┬─────────────────────────────────┘  │
└─────────────────────────┼────────────────────────────────────┘
                          │ HTTP (JSON)
                          ▼
┌──────────────────────────────────────────────────────────────┐
│                    PHP REST API                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │  Slim Framework (lightweight PHP router)                │  │
│  │  - /api/auth/*      → Auth endpoints                   │  │
│  │  - /api/products    → Products CRUD                    │  │
│  │  - /api/orders      → Orders CRUD                      │  │
│  │  - /api/ai/generate → AI story generation              │  │
│  │  - /api/notes       → Notes CRUD                       │  │
│  └──────────────────────┬─────────────────────────────────┘  │
│                         │ cURL (as you already do)           │
└─────────────────────────┼────────────────────────────────────┘
                          │
                          ▼
┌──────────────────────────────────────────────────────────────┐
│                     SUPABASE (cloud)                         │
│  - PostgreSQL database                                       │
│  - Auth (Google OAuth)                                       │
│  - Row Level Security                                        │
└──────────────────────────────────────────────────────────────┘
```

---

## Step-by-Step Migration Plan

### Phase 1: Restructure the Repository

**Goal:** Separate PHP and React into distinct directories with independent tooling.

```
project-root/
├── api/                        # PHP backend
│   ├── public/
│   │   └── index.php           # Single entry point (front controller)
│   ├── src/
│   │   ├── Auth/
│   │   │   └── SupabaseAuth.php    # Your existing class (cleaned up)
│   │   ├── AI/
│   │   │   └── GeminiAI.php        # Your existing class
│   │   ├── Middleware/
│   │   │   └── AuthMiddleware.php   # Checks JWT on protected routes
│   │   └── Routes/
│   │       ├── auth.php
│   │       ├── products.php
│   │       ├── orders.php
│   │       ├── notes.php
│   │       └── ai.php
│   ├── .env                    # API keys (never committed)
│   ├── composer.json           # Dependencies (slim/slim, vlucas/phpdotenv)
│   └── Dockerfile
├── client/                     # React frontend
│   ├── src/
│   │   ├── components/
│   │   ├── pages/
│   │   ├── hooks/
│   │   ├── services/           # API client functions
│   │   ├── App.tsx
│   │   └── main.tsx
│   ├── package.json
│   ├── vite.config.ts
│   └── Dockerfile              # (for Render deployment)
├── docker-compose.yml          # Local dev: both services
└── README.md
```

**Tasks:**
1. Create `api/` and `client/` directories
2. Move and refactor PHP code into `api/`
3. Scaffold React app in `client/` with Vite

---

### Phase 2: Build the PHP REST API

**Goal:** Convert your existing PHP pages into JSON API endpoints.

#### 2a. Set up Slim Framework

```bash
cd api
composer init
composer require slim/slim slim/psr7 vlucas/phpdotenv
```

Slim is deliberately minimal — it just routes HTTP requests to your functions. This means your existing SupabaseAuth logic stays almost identical.

#### 2b. Create the API entry point

`api/public/index.php` becomes a single front controller:

```php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// CORS middleware (so React can call the API)
$app->add(function ($request, $handler) {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', $_ENV['CLIENT_URL'] ?? '*')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

// Load route files
require __DIR__ . '/../src/Routes/auth.php';
require __DIR__ . '/../src/Routes/products.php';
require __DIR__ . '/../src/Routes/orders.php';
require __DIR__ . '/../src/Routes/notes.php';
require __DIR__ . '/../src/Routes/ai.php';

$app->run();
```

#### 2c. Convert each page to an API route

**Example: Orders (from your current 13-orders.php)**

```php
// src/Routes/orders.php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

$app->get('/api/orders', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();

    // Get token from Authorization header (sent by React)
    $authHeader = $request->getHeaderLine('Authorization');
    if (!$authHeader) {
        $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $token = str_replace('Bearer ', '', $authHeader);
    $auth->setToken($token);  // New method to set token from header

    $orders = $auth->query('orders', ['order' => 'created_at.desc']);

    $response->getBody()->write(json_encode($orders));
    return $response->withHeader('Content-Type', 'application/json');
});
```

#### 2d. Endpoints to build

| Current Page               | API Endpoint          | Method | Auth Required |
| -------------------------- | --------------------- | ------ | ------------- |
| 11-authentication.php      | `/api/auth/login-url` | GET    | No            |
| callback.php               | `/api/auth/callback`  | POST   | No            |
| 11-authentication.php      | `/api/auth/user`      | GET    | Yes           |
| 11-authentication.php      | `/api/auth/logout`    | POST   | Yes           |
| 12-products.php            | `/api/products`       | GET    | Yes           |
| 13-orders.php              | `/api/orders`         | GET    | Yes           |
| 11-authentication.php      | `/api/notes`          | GET    | Yes           |
| 11-authentication.php      | `/api/notes`          | POST   | Yes           |
| 11-authentication.php      | `/api/notes/{id}`     | DELETE | Yes           |
| ai-integration.php         | `/api/ai/generate`    | POST   | No            |

---

### Phase 3: Build the React Frontend

**Goal:** Replace the PHP-rendered HTML with a React SPA.

#### 3a. Scaffold with Vite

```bash
cd client
npm create vite@latest . -- --template react-ts
npm install react-router-dom axios
```

#### 3b. Create an API service layer

```typescript
// src/services/api.ts
const API_BASE = import.meta.env.VITE_API_URL || 'http://localhost:8005/api';

async function fetchApi(endpoint: string, options: RequestInit = {}) {
  const token = localStorage.getItem('supabase_token');
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
  };
  const res = await fetch(`${API_BASE}${endpoint}`, { ...options, headers });
  if (!res.ok) throw new Error(`API error: ${res.status}`);
  return res.json();
}

export const api = {
  getOrders: () => fetchApi('/orders'),
  getProducts: () => fetchApi('/products'),
  getNotes: () => fetchApi('/notes'),
  addNote: (data: { title: string; content: string }) =>
    fetchApi('/notes', { method: 'POST', body: JSON.stringify(data) }),
  deleteNote: (id: string) =>
    fetchApi(`/notes/${id}`, { method: 'DELETE' }),
  generateStory: (genre: string) =>
    fetchApi('/ai/generate', { method: 'POST', body: JSON.stringify({ genre }) }),
  getLoginUrl: () => fetchApi('/auth/login-url'),
};
```

#### 3c. Pages to build (mapping from current PHP)

| React Page          | Replaces PHP File           | Features                     |
| ------------------- | --------------------------- | ---------------------------- |
| `pages/Home.tsx`    | home.php                    | Welcome page                 |
| `pages/Login.tsx`   | 11-authentication.php       | Google sign-in button        |
| `pages/Callback.tsx`| auth/callback.php           | Handle OAuth redirect        |
| `pages/Products.tsx`| 12-products.php             | Products table               |
| `pages/Orders.tsx`  | 13-orders.php               | Orders table                 |
| `pages/Notes.tsx`   | 11-authentication.php       | Notes CRUD                   |
| `pages/AIStory.tsx` | ai-integration.php          | Genre select + story display |

#### 3d. Auth flow in React

1. User clicks "Sign in with Google"
2. React calls `GET /api/auth/login-url` → gets Supabase OAuth URL
3. Browser redirects to Google → Supabase → your callback URL
4. `Callback.tsx` extracts tokens from URL fragment
5. Sends tokens to `POST /api/auth/callback` (or stores them directly)
6. Token stored in `localStorage`, sent with every API request

---

### Phase 4: Local Development Setup

**Goal:** Run both services locally with Docker Compose.

```yaml
# docker-compose.yml
version: '3.8'
services:
  api:
    build:
      context: ./api
      dockerfile: Dockerfile
    ports:
      - "8005:80"
    volumes:
      - ./api:/var/www/html
    environment:
      - CLIENT_URL=http://localhost:5173

  client:
    image: node:20-alpine
    working_dir: /app
    command: npm run dev -- --host
    ports:
      - "5173:5173"
    volumes:
      - ./client:/app
    environment:
      - VITE_API_URL=http://localhost:8005/api
```

**Updated PHP Dockerfile:**

```dockerfile
FROM php:8.2-apache
RUN apt-get update && apt-get install -y unzip curl
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN a2enmod rewrite
RUN docker-php-ext-install pdo pdo_mysql
COPY . /var/www/html/
RUN composer install --no-dev
```

Note: The MySQL + phpMyAdmin services are removed since you use Supabase as your primary database. Add them back if you still want local MySQL for learning.

---

### Phase 5: Deployment

#### Option A: Render (Recommended for this stack)

**Backend (PHP API) → Render Web Service (Docker)**

1. Create a `render.yaml` (Infrastructure as Code):
   ```yaml
   services:
     - type: web
       name: php-api
       runtime: docker
       dockerfilePath: ./api/Dockerfile
       envVars:
         - key: SUPABASE_URL
           sync: false
         - key: SUPABASE_ANON_KEY
           sync: false
         - key: GEMINI_API_KEY
           sync: false
         - key: CLIENT_URL
           sync: false
   ```
2. Render detects the Dockerfile, builds, and deploys
3. Set environment variables in the Render dashboard
4. Your API is live at `https://php-api-xxxx.onrender.com`

**Frontend (React) → Render Static Site**

1. Add to `render.yaml`:
   ```yaml
     - type: web
       name: client
       runtime: static
       buildCommand: cd client && npm install && npm run build
       staticPublishPath: ./client/dist
       envVars:
         - key: VITE_API_URL
           value: https://php-api-xxxx.onrender.com/api
   ```
2. Render builds the React app and serves the static files
3. React SPA is live at `https://client-xxxx.onrender.com`

**Cost:** Render free tier gives you a web service + static site. The free web service sleeps after 15 minutes of inactivity (cold starts of ~30s).

#### Option B: Vercel (Frontend) + Render (Backend)

- **React on Vercel**: Ideal — Vercel is built for frontend frameworks. Zero config for Vite.
- **PHP on Render**: Docker-based web service as above.
- This splits hosting across two platforms, but each service is on its best-fit host.

#### Option C: Vercel for everything (with caveats)

Vercel has experimental PHP runtime support via `vercel-php`, but:
- It is community-maintained, not official
- Limited to serverless functions (no persistent sessions)
- Your `SupabaseAuth` relies on `$_SESSION` which won't work in serverless
- Not recommended for this project

#### Option D: Railway or Fly.io

Both support Docker natively and keep services running (no cold starts on paid plans). Similar to Render but with different pricing models.

---

### Phase 6: Production Hardening

Before deploying for real:

1. **Environment variables**: Move all secrets to platform env vars (never `.env` in production)
2. **CORS**: Lock down `Access-Control-Allow-Origin` to your actual frontend domain
3. **HTTPS**: Both Render and Vercel provide this automatically
4. **Auth tokens**: Switch from PHP `$_SESSION` to stateless JWT validation
   - React stores the Supabase token in `localStorage`
   - PHP API validates the token on each request (no server-side session)
   - This is critical for deployment since serverless/stateless hosting can't rely on PHP sessions
5. **Rate limiting**: Add basic rate limiting on the AI endpoint
6. **Error handling**: Return proper JSON error responses, never PHP stack traces

---

## Decision: Do You Even Need PHP Here?

This is the most important question. Here's the honest assessment:

### Architecture Option 1: React + PHP API + Supabase (your plan)

```
React  →  PHP API  →  Supabase
```

### Architecture Option 2: React + Supabase directly (no PHP)

```
React  →  Supabase JS Client (direct)
```

Supabase provides an official JavaScript client (`@supabase/supabase-js`) that handles auth, database queries, and RLS — everything your `SupabaseAuth.php` class does. React could call Supabase directly without PHP in the middle.

**The PHP layer only adds value if:**
- You need server-side business logic (calculations, validation the client shouldn't do)
- You need to hide API keys (Gemini key shouldn't be in frontend code)
- You want to aggregate multiple API calls into one
- You're learning PHP backend development (legitimate educational goal)

**For your AI integration specifically**, PHP is genuinely useful — you don't want the Gemini API key exposed in frontend JavaScript. A PHP endpoint that proxies AI requests is good practice.

---

## My Opinion on This Stack

### Benefits

1. **Separation of concerns**: Frontend and backend can evolve independently. A React dev and a PHP dev can work in parallel.

2. **Your existing PHP knowledge transfers**: Your `SupabaseAuth` class and cURL patterns translate directly into API endpoints. The learning curve is manageable.

3. **Supabase is doing the heavy lifting**: Auth, database, RLS — the hardest parts are already handled. PHP becomes a thin API layer, which keeps complexity low.

4. **Docker deployment works on Render**: Your existing Docker knowledge directly applies. Render makes Docker deployment straightforward.

5. **React is industry-standard**: The skills transfer to jobs, other projects, and the ecosystem is massive.

### Problems / Risks

1. **PHP is a friction point for deployment**. Modern hosting platforms (Vercel, Netlify, Cloudflare Pages) are built around Node.js/serverless. PHP needs Docker, which means Render, Railway, or Fly.io. This limits your options and free tiers.

2. **PHP sessions don't work in stateless hosting**. Your current `SupabaseAuth` uses `$_SESSION` extensively. You'll need to refactor to stateless JWT-based auth where the token comes from the `Authorization` header on every request. This is a significant change to your existing auth flow.

3. **The PHP layer might be mostly pass-through**. If most endpoints just proxy requests to Supabase, you're adding latency and complexity for little benefit. Each request goes: `React → PHP → Supabase → PHP → React` instead of `React → Supabase`.

4. **Two build systems, two runtimes, two deployments**. You need Composer + npm, PHP + Node, and two separate deploy pipelines. More moving parts = more things to debug.

5. **Cold starts on free hosting**. Render's free Docker services sleep after inactivity. A PHP API waking up takes 10-30 seconds, which is a poor user experience. (Supabase direct calls from React wouldn't have this problem.)

6. **CORS complexity**. Cross-origin requests between frontend and backend domains require careful configuration, especially with credentials/cookies. This is a common source of frustration.

### My Recommendation

**If the goal is learning PHP as a backend language:** Go for it. The architecture is valid and educational. Use Slim Framework to keep it lightweight. Deploy backend on Render (Docker) and frontend on Vercel.

**If the goal is building a production app with the least friction:** Drop PHP. Use `React + Supabase JS client` directly, with a few Vercel Edge Functions (or Supabase Edge Functions) for anything that needs a server (like the Gemini API proxy). Everything deploys on Vercel's free tier with zero cold starts.

**The pragmatic middle ground:** Start with the PHP API architecture for learning, but use the `@supabase/supabase-js` client directly in React for auth and simple queries. Keep PHP only for the endpoints that genuinely need a server (AI generation, complex business logic). This gives you the learning value without the overhead on every request.

---

## Build Steps (Detailed)

All work lives in `stockflow/` so it can be deployed independently from the learning repo.

### Part A: PHP API Backend

| Step | File(s)                          | Task                                              | Status |
| ---- | -------------------------------- | ------------------------------------------------- | ------ |
| 1    | `api/composer.json`              | Define dependencies (Slim, PSR-7, phpdotenv)      | Done   |
| 2    | `api/public/.htaccess`           | Apache URL rewriting to front controller           | Done   |
| 3    | `api/public/index.php`           | Front controller: load Slim, CORS, route files     | Done   |
| 4    | `api/.env` + `.env.example` + `.gitignore` | Environment variables for Supabase + Gemini | Done   |
| 5    | `api/src/Auth/SupabaseAuth.php`  | Port to stateless (no `$_SESSION`, token via header)| Done  |
| 6    | `api/src/Middleware/AuthMiddleware.php` | Extract Bearer token, reject unauthenticated | Done  |
| 7    | `api/src/Routes/auth.php`        | Login URL, user info, logout                       | Done   |
| 8    | `api/src/Routes/products.php`    | GET /api/products                                  | Done   |
| 9    | `api/src/Routes/orders.php`      | GET /api/orders                                    | Done   |
| ~~10~~ | ~~`api/src/Routes/notes.php`~~ | ~~GET, POST, DELETE /api/notes~~                   | Skipped |
| ~~11~~ | ~~`api/src/AI/GeminiAI.php` + `api/src/Routes/ai.php`~~ | ~~Port Gemini class + AI endpoint~~ | Skipped |
| 12   | `api/Dockerfile`                 | Docker image for PHP API                           |        |
| 13   | `stockflow/docker-compose.yml`   | Local dev orchestration (API + client)             |        |

### Part B: React Frontend (future)

| Step | Task                                              | Status |
| ---- | ------------------------------------------------- | ------ |
| 14   | Scaffold React app with Vite + TypeScript          |        |
| 15   | Build API service layer (`services/api.ts`)        |        |
| 16   | Build auth flow (Login, Callback pages)            |        |
| 17   | Build data pages (Products, Orders, Notes)         |        |
| 18   | Build AI story page                                |        |
| 19   | Routing with React Router                          |        |
| 20   | Styling (Tailwind or similar)                      |        |

### Part C: Deployment

| Step | Task                                              | Status |
| ---- | ------------------------------------------------- | ------ |
| 21   | Deploy PHP API to Render (Docker web service)      |        |
| 22   | Deploy React to Vercel or Render Static            |        |
| 23   | Production hardening (CORS lockdown, env vars, rate limiting) |  |

---

## Step Notes

### Step 1: `api/composer.json`

Defines the project's PHP dependencies (like `package.json` in Node):

- **`slim/slim ^4.0`** — Lightweight routing framework. Maps URLs like `/api/orders` to PHP functions. Minimal by design — no ORM, no templating, just routing and middleware.
- **`slim/psr7 ^1.0`** — Provides the HTTP Request/Response objects that Slim uses. PSR-7 is a PHP standard interface; this is Slim's implementation of it.
- **`vlucas/phpdotenv ^5.0`** — Reads `.env` files into `$_ENV`. Replaces the manual file parsing in the original `SupabaseAuth.php`.

The `autoload` section sets up PSR-4 autoloading: `"StockFlow\\" → "src/"` means `use StockFlow\Auth\SupabaseAuth` automatically loads `src/Auth/SupabaseAuth.php` — no `require` statements needed.

### Step 2: `api/public/.htaccess`

This file tells Apache to use the **front controller pattern** — routing all requests through a single `index.php` file instead of mapping URLs to individual PHP files.

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

**Why we need this:** In the original app, each URL maps to a real file (`/12-products.php` → the file `12-products.php`). In the new API, a request to `/api/products` has no matching file on disk — there's no `products` file in `public/`. Without `.htaccess`, Apache returns a 404.

**How it works line by line:**

1. `RewriteEngine On` — Enables Apache's mod_rewrite module
2. `RewriteCond %{REQUEST_FILENAME} !-f` — Only apply the rule if the URL does NOT match a real file (`!-f` = not a file). This allows actual static files (images, CSS) to still be served directly.
3. `RewriteCond %{REQUEST_FILENAME} !-d` — Same check for directories (`!-d` = not a directory)
4. `RewriteRule ^ index.php [QSA,L]` — Send everything else to `index.php`. Flags: `QSA` preserves query string parameters (`?key=value`), `L` stops processing further rules.

**The result:** Apache delegates URL handling to PHP. Slim (inside `index.php`) reads the actual URL from the request and calls the matching route function. This is how every modern PHP framework works (Laravel, Symfony, etc).

### Step 3: `api/public/index.php` — The Front Controller

This is the single file that every request hits (thanks to `.htaccess`). It does 7 things in order:

```php
<?php
// 1. Load Composer's autoloader
require __DIR__ . '/../vendor/autoload.php';

// 2. Load .env variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// 3. Create the Slim app
$app = AppFactory::create();

// 4. Add built-in middleware (body parsing + error handling)
$app->addBodyParsingMiddleware();
$app->addErrorMiddleware(true, true, true);

// 5. CORS middleware + OPTIONS preflight handler
// 6. Load route files (auth, products, orders, notes, ai)
// 7. $app->run() — start handling the request
```

**Request lifecycle — before vs after:**

```
BEFORE (original app):
  Browser requests /12-products.php
    → Apache finds the file 12-products.php
    → PHP runs that file top to bottom
    → HTML is printed and sent back

AFTER (Slim API):
  Browser requests /api/products
    → Apache can't find a file called "api/products"
    → .htaccess sends the request to index.php
    → index.php boots Slim
    → Slim reads the URL "/api/products" and method "GET"
    → Slim finds the matching route in Routes/products.php
    → CORS middleware runs (adds headers)
    → Your route function runs (queries Supabase, returns JSON)
    → Slim sends the response
```

**Key concepts in this file:**

1. **Composer autoloader** (`vendor/autoload.php`) — Replaces all manual `require_once` statements. Composer generates a map of class names to file paths so that `new SupabaseAuth()` automatically loads `src/Auth/SupabaseAuth.php`. This is the PSR-4 mapping we defined in `composer.json`.

2. **phpdotenv** (`Dotenv\Dotenv::createImmutable`) — Reads `api/.env` and makes values available via `$_ENV['SUPABASE_URL']` etc. Replaces the manual `file()` + `explode()` parsing in the original `SupabaseAuth::loadEnv()`.

3. **Body parsing middleware** — Automatically decodes JSON request bodies into arrays. In the original app you'd use `json_decode(file_get_contents('php://input'))`. With this middleware, `$request->getParsedBody()` just works.

4. **Error middleware** — Catches uncaught exceptions and returns proper HTTP error responses instead of raw PHP stack traces. The three `true` arguments enable: displayErrorDetails, logErrors, logErrorDetails (set to `false` in production).

5. **CORS middleware** — The most important new concept. In the original app, PHP served both the HTML page and the data, so the browser never blocked anything (same origin). Once React runs on a different port or domain (e.g. `localhost:5173` calling `localhost:8005`), the browser enforces **Cross-Origin Resource Sharing** and blocks the request unless the server explicitly allows it with these headers:
   - `Access-Control-Allow-Origin` — Which domain can call the API
   - `Access-Control-Allow-Headers` — Which HTTP headers the client can send (we need `Content-Type` and `Authorization`)
   - `Access-Control-Allow-Methods` — Which HTTP methods are permitted

6. **OPTIONS preflight** — Before making a cross-origin POST/DELETE, the browser sends a "preflight" OPTIONS request to ask permission. The `$app->options('/{routes:.+}', ...)` handler catches these and returns 200 immediately so the browser proceeds with the real request.

7. **Route loading** — Each `require` pulls in a file that registers its routes on `$app`. This keeps the entry point clean while organising endpoints by feature. Slim holds all the routes in memory and matches the incoming URL against them.

### Step 4: `api/.env`, `.env.example`, and `.gitignore`

Three files created for this step:

**`api/.env`** — The real environment file with actual keys. Never committed to git.

**`api/.env.example`** — A template with placeholder values. Safe to commit so anyone cloning the repo knows which variables are needed.

**`stockflow/.gitignore`** — Prevents secrets and generated files from being committed.

**Why `.env` lives in `api/` not `stockflow/` root:**

The path is determined by `index.php`:
```php
// index.php lives at:    api/public/index.php
// __DIR__         =      api/public/
// __DIR__ . '/..' =      api/          ← phpdotenv looks here for .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
```

Each service owns its own `.env` because they have different needs:
- **`api/.env`** holds secrets (Supabase key, Gemini key) that must stay server-side
- **`client/.env`** (later) will only hold `VITE_API_URL` — no secrets, since all browser code is visible to users via dev tools

In production (Render, Vercel), there are no `.env` files at all — you set environment variables in each platform's dashboard instead.

**Variables compared to the original:**

| Variable          | Original `.env`         | New `api/.env`          | Notes                           |
| ----------------- | ----------------------- | ----------------------- | ------------------------------- |
| `SUPABASE_URL`    | Same                    | Same                    | Unchanged                       |
| `SUPABASE_ANON_KEY` | Same                 | Same                    | Unchanged                       |
| `SITE_URL`        | `http://localhost:8005` | `http://localhost:8005` | Now used for OAuth callbacks    |
| `CLIENT_URL`      | Did not exist           | `http://localhost:5173` | **New** — used by CORS middleware to allow React to call the API |
| `GEMINI_API_KEY`  | Same                    | Same                    | Unchanged                       |

The only new variable is `CLIENT_URL`. In the original app CORS wasn't needed because PHP served both HTML and data (same origin). Now that React runs on a separate port/domain, `index.php` reads `CLIENT_URL` to set the `Access-Control-Allow-Origin` header.

### Steps 5-9: SupabaseAuth, Middleware, and Routes

These five files were built together because they form a chain:

```
HTTP Request
  → AuthMiddleware (extracts token from header)
    → Route handler (creates SupabaseAuth, sets token, queries Supabase)
      → JSON Response
```

#### Step 5: `api/src/Auth/SupabaseAuth.php` — Stateless Supabase Client

**What changed from the original (`phpDir/src/auth/SupabaseAuth.php`):**

| Original                        | New                              | Why                                  |
| ------------------------------- | -------------------------------- | ------------------------------------ |
| `session_start()` in constructor | Removed entirely                | Stateless API — no sessions          |
| `$_SESSION` for token storage   | `setToken()` method             | Token comes from request header      |
| `loadEnv()` manual file parsing | Reads `$_ENV` directly          | phpdotenv in index.php handles this  |
| `handleCallback()` with session | Removed                         | React handles the callback client-side |
| `isLoggedIn()` / `getCurrentUser()` | Removed                     | These relied on session state        |
| `debug`, `logs[]`, `renderLogs()` | Removed                       | Debug HTML doesn't belong in an API  |
| `makeRequest()` cURL logic      | **Kept — almost identical**     | This is the core, it works well      |
| `query()`, `insert()`, `delete()` | **Kept — identical**           | Database operations unchanged        |
| `getGoogleSignInUrl()`          | **Kept — identical**            | Just builds a URL string             |
| `getUser()`                     | **Kept — identical**            | Fetches user from Supabase           |
| `logout()`                      | **Kept — simplified**           | Just calls Supabase, no session cleanup |

The class is now ~140 lines (down from ~345). The `namespace StockFlow\Auth` line at the top is new — this is what lets Composer's autoloader find the file via `use StockFlow\Auth\SupabaseAuth`.

#### Step 6: `api/src/Middleware/AuthMiddleware.php` — Token Extraction

This is new — the original app didn't need it because `$_SESSION` handled auth state.

The middleware is deliberately simple. It does **not** validate the token itself. Its only job:
1. Check the `Authorization` header exists and starts with `Bearer `
2. Extract the token string
3. Attach it to the request via `$request->withAttribute('token', $token)`
4. If no header → return 401 JSON immediately

Supabase validates the token when `SupabaseAuth::makeRequest()` forwards it. If the token is expired or invalid, Supabase returns a 401 and we pass that through. This keeps our code simple — one source of truth for token validation (Supabase).

Routes opt-in to auth by chaining `->add(new AuthMiddleware())`:
```php
$app->get('/api/products', function (...) { ... })->add(new AuthMiddleware());
$app->get('/api/auth/login-url', function (...) { ... }); // no middleware = public
```

#### Step 7: `api/src/Routes/auth.php` — Authentication Endpoints

Three endpoints, all returning JSON:

| Endpoint               | Method | Auth | Purpose                            |
| ---------------------- | ------ | ---- | ---------------------------------- |
| `/api/auth/login-url`  | GET    | No   | Returns `{"url": "https://..."}` — the Google OAuth URL |
| `/api/auth/user`       | GET    | Yes  | Returns user object from Supabase  |
| `/api/auth/logout`     | POST   | Yes  | Invalidates token at Supabase      |

**Key simplification:** The original `callback.php` (189 lines of PHP + HTML + JavaScript) is gone. In the new architecture, the OAuth callback is handled entirely by React:
1. Supabase redirects to the React app with tokens in the URL fragment
2. React's `Callback.tsx` page extracts the tokens with JavaScript
3. React stores the token in `localStorage`
4. React sends the token in the `Authorization` header on every API call

PHP never sees the token during login — only when React makes subsequent API requests.

#### Steps 8-9: `api/src/Routes/products.php` and `orders.php`

These are the simplest files in the project. Each is essentially:

```php
$app->get('/api/endpoint', function ($request, $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $data = $auth->query('table_name', [/* same params as original */]);
    $response->getBody()->write(json_encode($data));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());
```

**Compared to the originals:** The Supabase query is identical — same table, same parameters. The only difference is the original rendered an HTML table (50+ lines of `<?php foreach ... ?>` mixed with `<td>` tags). Now we just return the raw JSON and React will handle the rendering.

| Original                         | New endpoint       | Query (unchanged)                                  |
| -------------------------------- | ------------------ | -------------------------------------------------- |
| `12-products.php` (80 lines)     | `GET /api/products`| `products` with `select=*,categories(name)&order=name.asc` |
| `13-orders.php` (77 lines)       | `GET /api/orders`  | `orders` with `order=created_at.desc`              |

### Local Development Prerequisites: PHP + Composer via Homebrew

Before running `composer install`, you need both PHP and Composer available on your machine. On macOS, Homebrew makes this simple.

#### Installing PHP (if not already present)

```bash
brew install php
```

This installs the latest PHP (we got 8.5.2). Verify with:
```bash
php -v
# PHP 8.5.2 (cli) ...
```

#### Installing Composer

Composer is PHP's package manager — equivalent to `npm` for Node. It reads `composer.json` and installs dependencies into a `vendor/` folder, plus generates an autoloader so you never need manual `require` statements.

```bash
brew install composer
```

Verify with:
```bash
composer --version
# Composer version 2.9.5
```

#### Why this matters for local development

Without Composer via Homebrew, the alternatives are more cumbersome:
- **Download `composer.phar` manually** — works but you run it as `php composer.phar install` instead of just `composer install`, and you have to manage the file yourself
- **Run Composer inside Docker** — adds a Docker dependency just to install packages; slower iteration loop
- **Use the PHP built-in server** — only works if dependencies are already installed

With Homebrew, both tools are managed centrally (`brew update` keeps them current) and available globally from any terminal. This also means you can run the PHP built-in dev server directly:

```bash
cd stockflow/api
composer install                     # install dependencies
php -S localhost:8005 -t public/     # start dev server
```

No Docker needed for development. Docker becomes a deployment/CI concern only.

### Verification: Test Page (`api/public/test.php`)

After installing dependencies, we created a test page to verify the full chain works before building more on top. The test page checks four things in order:

1. **Environment variables** — All 4 `.env` values loaded correctly via phpdotenv
2. **SupabaseAuth class** — Composer autoloading finds `src/Auth/SupabaseAuth.php` via the PSR-4 namespace mapping
3. **Google OAuth URL** — `getGoogleSignInUrl()` builds a valid Supabase OAuth URL
4. **Supabase queries** — When given a valid token, `query()` fetches real data (20 products with category joins confirmed working)

Run it with:
```bash
cd stockflow/api
php -S localhost:8005 -t public/
# Open http://localhost:8005/test.php
```

This test page is for development only and should be deleted before deploying.

### PHP 8.5 Note: `curl_close()` Deprecation

During testing, PHP 8.5 showed this warning:

```
Deprecated: Function curl_close() is deprecated since 8.5, as it has no effect since PHP 8.0
```

The original `SupabaseAuth.php` used `curl_close($ch)` to clean up cURL handles. Since PHP 8.0, cURL handles are objects (not resources) and are automatically cleaned up when they go out of scope — just like any other PHP object. The explicit `curl_close()` call became a no-op in 8.0 and is now formally deprecated in 8.5.

**Fix:** Simply remove the `curl_close($ch)` line. The handle is freed automatically when `makeRequest()` returns. This is the same pattern used in the new `SupabaseAuth.php`.

<?php

/**
 * Auth Routes
 *
 * These replace the auth logic from 11-authentication.php and auth/callback.php.
 * The key difference: no sessions, no HTML. Just JSON in, JSON out.
 *
 * OAuth flow with React frontend:
 * 1. React calls GET /api/auth/login-url → gets the Google OAuth URL
 * 2. React redirects the browser to that URL
 * 3. User logs in with Google → Supabase redirects to React callback page
 * 4. React extracts tokens from the URL fragment (client-side)
 * 5. React stores the token in localStorage and sends it with every API call
 *
 * Note: The callback is handled entirely in React now (steps 3-5).
 * PHP never sees the token during login — it only receives it later
 * in the Authorization header when React makes API calls.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use StockFlow\Auth\SupabaseAuth;
use StockFlow\Middleware\AuthMiddleware;

// Public — no auth needed. Returns the URL to redirect to for Google login.
$app->get('/api/auth/login-url', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $url = $auth->getGoogleSignInUrl();

    $response->getBody()->write(json_encode(['url' => $url]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Protected — requires token. Returns the current user's info from Supabase.
$app->get('/api/auth/user', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $user = $auth->getUser();

    if (!$user) {
        $response->getBody()->write(json_encode(['error' => 'Invalid token']));
        return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
    }

    $response->getBody()->write(json_encode($user));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());

// Protected — tells Supabase to invalidate the token.
$app->post('/api/auth/logout', function (Request $request, Response $response) {
    $auth = new SupabaseAuth();
    $auth->setToken($request->getAttribute('token'));
    $auth->logout();

    $response->getBody()->write(json_encode(['message' => 'Logged out']));
    return $response->withHeader('Content-Type', 'application/json');
})->add(new AuthMiddleware());

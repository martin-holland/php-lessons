<?php
// ============================================================
// includes/auth.php — JWT token validation via Supabase
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/json_response.php';

/**
 * Get the Authorization header from the request.
 * Apache can be tricky with passing headers to PHP.
 */
function get_authorization_header(): string {
    // Method 1: Standard $_SERVER
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        return $_SERVER['HTTP_AUTHORIZATION'];
    }

    // Method 2: Apache redirect (mod_rewrite)
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    }

    // Method 3: apache_request_headers function
    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) {
            return $headers['Authorization'];
        }
        if (isset($headers['authorization'])) {
            return $headers['authorization'];
        }
    }

    return '';
}

/**
 * Validate the JWT token with Supabase and return user info.
 * This calls Supabase's /auth/v1/user endpoint to verify the token.
 */
function validate_token(): array {
    $authHeader = get_authorization_header();

    if (empty($authHeader)) {
        json_error('Missing Authorization header', 401);
    }

    if (strpos($authHeader, 'Bearer ') !== 0) {
        json_error('Invalid Authorization header format (expected: Bearer <token>)', 401);
    }

    $token = substr($authHeader, 7);

    if (empty($token)) {
        json_error('Empty token', 401);
    }

    // Verify token with Supabase
    $ch = curl_init(SUPABASE_URL . '/auth/v1/user');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'apikey: ' . SUPABASE_ANON_KEY,
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("Supabase auth error: " . $curlError);
        json_error('Authentication service unavailable', 503);
    }

    if ($httpCode === 401) {
        json_error('Invalid or expired token', 401);
    }

    if ($httpCode !== 200) {
        error_log("Supabase auth returned HTTP $httpCode: $response");
        json_error('Authentication failed', 401);
    }

    $user = json_decode($response, true);

    if (!$user || !isset($user['id'])) {
        error_log("Invalid Supabase user response: $response");
        json_error('Invalid token response', 401);
    }

    return [
        'id' => $user['id'],
        'email' => $user['email'] ?? '',
        'token' => $token,
    ];
}

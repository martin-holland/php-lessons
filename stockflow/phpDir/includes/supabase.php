<?php
// ============================================================
// includes/supabase.php — Supabase REST API wrapper
// ============================================================

require_once __DIR__ . '/config.php';

/**
 * @return mixed
 */
function supabase_request(string $method, string $endpoint, ?array $data = null, string $token = '') {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;

    $headers = [
        'apikey: ' . SUPABASE_ANON_KEY,
        'Content-Type: application/json',
        'Prefer: return=representation',
    ];

    // Use the user's JWT token for RLS, or fall back to anon key
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    } else {
        $headers[] = 'Authorization: Bearer ' . SUPABASE_ANON_KEY;
    }

    // For single-record responses (GET by ID), ask for singular
    if ($method === 'GET' && strpos($endpoint, 'id=eq.') !== false) {
        $headers[] = 'Accept: application/vnd.pgrst.object+json';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
    ]);

    switch (strtoupper($method)) {
        case 'POST':
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            break;
        case 'PATCH':
        case 'PUT':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            break;
        case 'DELETE':
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
            break;
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        throw new Exception("Supabase request failed: " . $curlError);
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 400) {
        $msg = $decoded['message'] ?? $decoded['error'] ?? "HTTP $httpCode";
        throw new Exception("Supabase error ($httpCode): " . $msg);
    }

    return $decoded;
}

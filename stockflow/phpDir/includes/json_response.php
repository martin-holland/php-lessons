<?php
// ============================================================
// includes/json_response.php — JSON response helpers
// ============================================================

function json_success($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['data' => $data]);
    exit;
}

function json_error(string $message, int $code = 400, ?array $details = null): void {
    http_response_code($code);
    header('Content-Type: application/json');
    $response = ['error' => $message];
    if ($details) {
        $response['details'] = $details;
    }
    echo json_encode($response);
    exit;
}

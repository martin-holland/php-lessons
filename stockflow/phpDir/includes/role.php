<?php
// ============================================================
// includes/role.php — Role-based access helpers (no tenants)
// ============================================================

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/supabase.php';
require_once __DIR__ . '/json_response.php';

/**
 * Get the current user's role from user_roles table.
 * Returns 'staff' if no role is assigned.
 */
function get_user_role(string $userId, string $token): string {
    try {
        $result = supabase_request(
            'GET',
            'user_roles?user_id=eq.' . $userId . '&select=role',
            null,
            $token
        );

        // Handle both array response [{"role":"admin"}] and object response {"role":"admin"}
        if (is_array($result)) {
            if (isset($result['role'])) {
                return $result['role'];
            } elseif (isset($result[0]['role'])) {
                return $result[0]['role'];
            }
        }

        // Default to staff if no role found
        return 'staff';
    } catch (Exception $e) {
        error_log('Failed to get user role: ' . $e->getMessage());
        return 'staff';
    }
}

/**
 * Require admin or manager role, otherwise return 403.
 */
function require_admin_or_manager(string $role): void {
    if ($role === 'staff') {
        json_error('Insufficient permissions. Admin or Manager role required.', 403);
    }
}

/**
 * Require admin role, otherwise return 403.
 */
function require_admin(string $role): void {
    if ($role !== 'admin') {
        json_error('Insufficient permissions. Admin role required.', 403);
    }
}

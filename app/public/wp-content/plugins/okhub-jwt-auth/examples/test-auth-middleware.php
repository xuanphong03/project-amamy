<?php

/**
 * Test AuthMiddleware with JWT Authentication
 * 
 * This file demonstrates how to test AuthMiddleware
 * with JWT token authentication
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test endpoint to verify AuthMiddleware works
 */
add_action('rest_api_init', function () {
    register_rest_route('okhub-test/v1', '/auth-test', array(
        'methods' => 'GET',
        'callback' => 'test_auth_middleware_callback',
        'permission_callback' => 'AuthMiddleware::check',
        'args' => []
    ));
});

/**
 * Test endpoint with specific capability check
 */
add_action('rest_api_init', function () {
    register_rest_route('okhub-test/v1', '/admin-test', array(
        'methods' => 'GET',
        'callback' => 'test_admin_middleware_callback',
        'permission_callback' => function ($request) {
            return AuthMiddleware::check($request, 'manage_options');
        },
        'args' => []
    ));
});

/**
 * Test endpoint with custom logic
 */
add_action('rest_api_init', function () {
    register_rest_route('okhub-test/v1', '/custom-test', array(
        'methods' => 'GET',
        'callback' => 'test_custom_middleware_callback',
        'permission_callback' => function ($request) {
            // First check basic auth
            $auth_result = AuthMiddleware::check($request);
            if (is_wp_error($auth_result)) {
                return $auth_result;
            }

            // Additional custom checks
            $user = AuthMiddleware::getCurrentUser();
            if (!$user) {
                return new WP_Error('no_user', 'No user found', array('status' => 401));
            }

            // Check if user has specific role
            if (!AuthMiddleware::hasRole('administrator') && !AuthMiddleware::hasRole('editor')) {
                return new WP_Error('insufficient_role', 'User must be admin or editor', array('status' => 403));
            }

            return true;
        },
        'args' => []
    ));
});

/**
 * Callback for basic auth test
 */
function test_auth_middleware_callback($request)
{
    $user = AuthMiddleware::getCurrentUser();

    return array(
        'success' => true,
        'message' => 'AuthMiddleware working correctly!',
        'user_id' => $user->ID,
        'user_name' => $user->display_name,
        'user_roles' => $user->roles,
        'is_authenticated' => AuthMiddleware::isAuthenticated(),
        'is_admin' => AuthMiddleware::isAdmin(),
        'is_editor' => AuthMiddleware::isEditor()
    );
}

/**
 * Callback for admin test
 */
function test_admin_middleware_callback($request)
{
    $user = AuthMiddleware::getCurrentUser();

    return array(
        'success' => true,
        'message' => 'Admin access granted!',
        'user_id' => $user->ID,
        'user_name' => $user->display_name,
        'has_manage_options' => AuthMiddleware::hasCapability('manage_options')
    );
}

/**
 * Callback for custom test
 */
function test_custom_middleware_callback($request)
{
    $user = AuthMiddleware::getCurrentUser();

    return array(
        'success' => true,
        'message' => 'Custom auth logic working!',
        'user_id' => $user->ID,
        'user_name' => $user->display_name,
        'user_roles' => $user->roles,
        'is_admin' => AuthMiddleware::isAdmin(),
        'is_editor' => AuthMiddleware::isEditor()
    );
}

/**
 * Usage Instructions:
 * 
 * 1. First, get a JWT token by logging in via the plugin's auth endpoint:
 *    POST /wp-json/okhub-jwt/v1/auth/login
 *    Body: {"email": "your@email.com", "password": "your_password"}
 * 
 * 2. Use the access_token from the response in Authorization header:
 *    GET /wp-json/okhub-test/v1/auth-test
 *    Headers: Authorization: Bearer <access_token>
 * 
 * 3. Test different endpoints:
 *    - /wp-json/okhub-test/v1/auth-test (basic auth)
 *    - /wp-json/okhub-test/v1/admin-test (admin only)
 *    - /wp-json/okhub-test/v1/custom-test (custom logic)
 */

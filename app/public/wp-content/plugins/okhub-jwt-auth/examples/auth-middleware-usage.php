<?php

/**
 * AuthMiddleware Usage Examples
 * 
 * This file demonstrates how to use AuthMiddleware class
 * in WordPress REST API permission_callback with JWT authentication
 * 
 * IMPORTANT: This middleware uses JWT token authentication from OkhubJwtAuth plugin
 * Make sure to include Authorization header: "Bearer <your_jwt_token>"
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example 1: Basic JWT authentication check
 * Only check if user is authenticated via JWT token
 * 
 * Usage: GET /wp-json/myplugin/v1/basic-auth
 * Headers: Authorization: Bearer <jwt_token>
 * 
 * Note: For REST API, always pass $request parameter to AuthMiddleware::check()
 */
add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/basic-auth', array(
        'methods' => 'GET',
        'callback' => 'my_basic_auth_callback',
        'permission_callback' => 'AuthMiddleware::check'
    ));
});

/**
 * Example 2: Check specific capability
 * Check if user has 'edit_posts' capability
 */
add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/edit-posts', array(
        'methods' => 'GET',
        'callback' => 'my_edit_posts_callback',
        'permission_callback' => function ($request) {
            return AuthMiddleware::check($request, 'edit_posts');
        }
    ));
});

/**
 * Example 3: Check admin capability
 * Check if user is admin
 */
add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/admin-only', array(
        'methods' => 'GET',
        'callback' => 'my_admin_callback',
        'permission_callback' => function ($request) {
            return AuthMiddleware::check($request, 'manage_options');
        }
    ));
});

/**
 * Example 4: Using in theme functions
 * Can be used anywhere in theme or plugin
 */
function my_theme_function()
{
    // Check if user is authenticated
    if (AuthMiddleware::isAuthenticated()) {
        $user = AuthMiddleware::getCurrentUser();
        echo 'Welcome, ' . $user->display_name;
    }

    // Check specific capability
    if (AuthMiddleware::hasCapability('edit_posts')) {
        echo 'You can edit posts';
    }

    // Check role
    if (AuthMiddleware::hasRole('administrator')) {
        echo 'You are an admin';
    }

    // For theme functions, use checkForTheme method
    $auth_result = AuthMiddleware::checkForTheme('edit_posts');
    if (!is_wp_error($auth_result)) {
        echo 'User has edit_posts capability';
    }
}

/**
 * Example 5: Advanced usage with custom logic
 */
add_action('rest_api_init', function () {
    register_rest_route('myplugin/v1', '/custom-auth', array(
        'methods' => 'GET',
        'callback' => 'my_custom_callback',
        'permission_callback' => function ($request) {
            // First check basic auth
            $auth_result = AuthMiddleware::check();
            if (is_wp_error($auth_result)) {
                return $auth_result;
            }

            // Additional custom checks
            $user_id = AuthMiddleware::getCurrentUserId();
            $user_roles = AuthMiddleware::getCurrentUserRoles();

            // Custom business logic
            if (in_array('subscriber', $user_roles) && $user_id < 100) {
                return new WP_Error(
                    'custom_restriction',
                    'This user is restricted',
                    array('status' => 403)
                );
            }

            return true;
        }
    ));
});

// Callback functions for examples
function my_basic_auth_callback($request)
{
    $user = AuthMiddleware::getCurrentUser();
    return array(
        'message' => 'Basic auth successful',
        'user_id' => $user->ID,
        'user_name' => $user->display_name
    );
}

function my_edit_posts_callback($request)
{
    return array(
        'message' => 'You have edit_posts capability',
        'user_roles' => AuthMiddleware::getCurrentUserRoles()
    );
}

function my_admin_callback($request)
{
    return array(
        'message' => 'Admin access granted',
        'is_admin' => AuthMiddleware::isAdmin()
    );
}

function my_custom_callback($request)
{
    return array(
        'message' => 'Custom auth successful',
        'user_id' => AuthMiddleware::getCurrentUserId(),
        'user_roles' => AuthMiddleware::getCurrentUserRoles()
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
 * 
 * 4. For REST API permission_callback:
 *    - Use: AuthMiddleware::check($request, $capability)
 *    - Or: function($request) { return AuthMiddleware::check($request, 'capability'); }
 * 
 * 5. For theme functions:
 *    - Use: AuthMiddleware::checkForTheme($capability)
 *    - Or: AuthMiddleware::isAuthenticated(), AuthMiddleware::getCurrentUser()
 */
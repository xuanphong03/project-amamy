<?php

/**
 * AuthMiddleware Class
 * 
 * Global authentication middleware for WordPress REST API
 * Uses JWT token authentication from OkhubJwtAuth plugin
 * Can be used directly in permission_callback
 * 
 * @package OkhubJwtAuth
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AuthMiddleware
{
    /**
     * Current user instance
     * 
     * @var WP_User|null
     */
    private static $current_user = null;

    /**
     * AuthService instance
     * 
     * @var \OkhubJwtAuth\Services\AuthService|null
     */
    private static $auth_service = null;

    /**
     * Get AuthService instance
     * 
     * @return \OkhubJwtAuth\Services\AuthService
     */
    private static function getAuthService()
    {
        if (self::$auth_service === null) {
            // Try to get AuthService from global variable or plugin instance
            global $okhub_jwt_auth_service;

            if (isset($okhub_jwt_auth_service) && $okhub_jwt_auth_service instanceof \OkhubJwtAuth\Services\AuthService) {
                self::$auth_service = $okhub_jwt_auth_service;
            } else {
                // Fallback: create new instance
                $tokenService = new \OkhubJwtAuth\Services\TokenService();
                $userService = new \OkhubJwtAuth\Services\UserService();
                $emailService = new \OkhubJwtAuth\Services\EmailService();
                self::$auth_service = new \OkhubJwtAuth\Services\AuthService($tokenService, $userService, $emailService);
            }
        }

        return self::$auth_service;
    }

    /**
     * Check authentication and return result for permission_callback
     * 
     * @param WP_REST_Request $request The request object
     * @param string $capability Optional capability to check
     * @return bool|WP_Error
     */
    public static function check($request = null, $capability = 'read')
    {
        // Get token from current request
        $token = self::getTokenFromCurrentRequest();

        if (!$token) {
            return new WP_Error(
                'jwt_auth_no_token',
                __('Access token is required', 'okhub-jwt-auth'),
                array('status' => 401)
            );
        }

        // Verify JWT token using AuthService
        $authService = self::getAuthService();
        $result = $authService->verifyToken($token);

        if (!$result['success']) {
            return new WP_Error(
                'jwt_auth_invalid_token',
                $result['message'] ?? __('Invalid or expired token', 'okhub-jwt-auth'),
                array('status' => 401)
            );
        }

        // Set current user from token
        $user_id = $result['user_id'];
        self::$current_user = get_user_by('ID', $user_id);

        if (!self::$current_user) {
            return new WP_Error(
                'jwt_auth_user_not_found',
                __('User not found', 'okhub-jwt-auth'),
                array('status' => 401)
            );
        }

        // Check capability if provided
        if ($capability && !self::hasCapability($capability)) {
            return new WP_Error(
                'insufficient_permissions',
                __('Insufficient permissions', 'okhub-jwt-auth'),
                array('status' => 403)
            );
        }

        return true;
    }

    /**
     * Check authentication with specific capability
     * 
     * @param WP_REST_Request $request The request object
     * @param string $capability Capability to check
     * @return bool|WP_Error
     */
    public static function checkWithCapability($request, $capability)
    {
        return self::check($request, $capability);
    }

    /**
     * Check authentication for theme functions (no request parameter)
     * 
     * @param string $capability Optional capability to check
     * @return bool|WP_Error
     */
    public static function checkForTheme($capability = 'read')
    {
        return self::check(null, $capability);
    }

    /**
     * Get JWT token from current request
     * 
     * @return string|null
     */
    private static function getTokenFromCurrentRequest()
    {
        // Try to get token from Authorization header
        $auth_header = null;

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            $auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : null;
        } else {
            // Fallback for servers that don't support getallheaders()
            $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : null;
        }

        if (!$auth_header) {
            return null;
        }

        // Extract token from "Bearer <token>" format
        if (preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Check if user is authenticated via JWT token
     * 
     * @return bool
     */
    public static function isAuthenticated()
    {
        $token = self::getTokenFromCurrentRequest();
        if (!$token) {
            return false;
        }

        $authService = self::getAuthService();
        $result = $authService->verifyToken($token);

        return $result['success'];
    }

    /**
     * Get current authenticated user from JWT token
     * 
     * @return WP_User|null
     */
    public static function getCurrentUser()
    {
        if (self::$current_user === null) {
            $token = self::getTokenFromCurrentRequest();
            if ($token) {
                $authService = self::getAuthService();
                $result = $authService->verifyToken($token);

                if ($result['success']) {
                    self::$current_user = get_user_by('ID', $result['user_id']);
                }
            }
        }

        return self::$current_user;
    }

    /**
     * Set current user from JWT token
     * 
     * @return void
     */
    private static function setCurrentUser()
    {
        if (self::$current_user === null) {
            $token = self::getTokenFromCurrentRequest();
            if ($token) {
                $authService = self::getAuthService();
                $result = $authService->verifyToken($token);

                if ($result['success']) {
                    self::$current_user = get_user_by('ID', $result['user_id']);
                }
            }
        }
    }

    /**
     * Check if current user has specific capability
     * 
     * @param string $capability Capability to check
     * @return bool
     */
    public static function hasCapability($capability)
    {
        $user = self::getCurrentUser();

        if (!$user || $user->ID === 0) {
            return false;
        }

        return user_can($user, $capability);
    }

    /**
     * Get user ID of current authenticated user
     * 
     * @return int
     */
    public static function getCurrentUserId()
    {
        $user = self::getCurrentUser();
        return $user ? $user->ID : 0;
    }

    /**
     * Get user roles of current authenticated user
     * 
     * @return array
     */
    public static function getCurrentUserRoles()
    {
        $user = self::getCurrentUser();

        if (!$user || $user->ID === 0) {
            return array();
        }

        return $user->roles;
    }

    /**
     * Check if current user has specific role
     * 
     * @param string $role Role to check
     * @return bool
     */
    public static function hasRole($role)
    {
        $user = self::getCurrentUser();

        if (!$user || $user->ID === 0) {
            return false;
        }

        return in_array($role, $user->roles, true);
    }

    /**
     * Check if current user is admin
     * 
     * @return bool
     */
    public static function isAdmin()
    {
        return self::hasCapability('manage_options');
    }

    /**
     * Check if current user is editor
     * 
     * @return bool
     */
    public static function isEditor()
    {
        return self::hasCapability('edit_posts');
    }

    /**
     * Check if current user is author
     * 
     * @return bool
     */
    public static function isAuthor()
    {
        return self::hasCapability('edit_published_posts');
    }

    /**
     * Check if current user is subscriber
     * 
     * @return bool
     */
    public static function isSubscriber()
    {
        return self::hasRole('subscriber');
    }

    /**
     * Reset current user (useful for testing)
     * 
     * @return void
     */
    public static function reset()
    {
        self::$current_user = null;
    }
}

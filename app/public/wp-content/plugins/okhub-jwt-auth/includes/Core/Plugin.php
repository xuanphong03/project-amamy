<?php

namespace OkhubJwtAuth\Core;

use OkhubJwtAuth\Services\AuthService;
use OkhubJwtAuth\Services\TokenService;
use OkhubJwtAuth\Services\UserService;
use OkhubJwtAuth\Services\EmailService;
use OkhubJwtAuth\Services\SessionService;
use OkhubJwtAuth\Services\SocialLoginService;
use OkhubJwtAuth\Admin\AdminMenu;
use OkhubJwtAuth\Api\RestApi;

/**
 * Main plugin class
 */
class Plugin
{
    private $authService;
    private $tokenService;
    private $userService;
    private $emailService;
    private $sessionService;
    private $socialLoginService;

    public function __construct()
    {
        $this->initHooks();
    }

    /**
     * Initialize services
     */
    public function initServices()
    {
        try {
            $this->sessionService = new SessionService();
            $this->tokenService = new TokenService($this->sessionService);
            $this->userService = new UserService();
            $this->emailService = new EmailService();
            $this->authService = new AuthService(
                $this->tokenService,
                $this->userService,
                $this->emailService,
                $this->sessionService
            );

            // Initialize Social Login Service
            $this->socialLoginService = new SocialLoginService(
                $this->authService,
                $this->userService,
                $this->tokenService,
                $this->sessionService
            );
        } catch (\Exception $e) {
            error_log('Okhub JWT Auth: Failed to initialize services: ' . $e->getMessage());
        }
    }

    /**
     * Initialize WordPress hooks
     */
    private function initHooks()
    {
        // Initialize services and other components after WordPress is ready
        add_action('init', [$this, 'initServices']);
        add_action('init', [$this, 'initAdmin']);
        add_action('init', [$this, 'initApi']);

        // Add JWT authentication to WordPress
        add_filter('authenticate', [$this, 'authenticateJwt'], 10, 3);
        add_filter('determine_current_user', [$this, 'determineCurrentUser'], 10, 1);

        // Add custom endpoints
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Add custom login/logout redirects
        add_action('wp_login', [$this, 'onUserLogin'], 10, 2);
        add_action('wp_logout', [$this, 'onUserLogout']);

        // Add automatic cleanup of expired tokens
        add_action('wp_scheduled_delete', [$this, 'cleanupExpiredTokens']);
        add_action('init', [$this, 'scheduleCleanup']);
    }

    /**
     * Initialize admin functionality
     */
    public function initAdmin()
    {
        if (is_admin()) {
            new AdminMenu();
        }
    }

    /**
     * Initialize REST API
     */
    public function initApi()
    {
        if ($this->authService) {
            new RestApi($this->authService, $this->socialLoginService);
        }
    }

    /**
     * JWT authentication filter
     */
    public function authenticateJwt($user, $username, $password)
    {
        // Skip if already authenticated
        if ($user instanceof \WP_User) {
            return $user;
        }

        // Check if services are initialized
        if (!$this->authService) {
            // Try to initialize services if not already done
            $this->initServices();
            if (!$this->authService) {
                return $user;
            }
        }

        // Check for JWT token in headers
        $token = $this->getJwtTokenFromHeaders();
        if ($token) {
            try {
                $result = $this->authService->verifyToken($token);
                if ($result && isset($result['success']) && $result['success']) {
                    // Handle both 'data' and direct 'user_id' in response
                    $user_id = null;
                    if (isset($result['data']['user_id'])) {
                        $user_id = $result['data']['user_id'];
                    } elseif (isset($result['user_id'])) {
                        $user_id = $result['user_id'];
                    }
                    
                    if ($user_id) {
                        $user = \get_user_by('ID', $user_id);
                        if ($user) {
                            return $user;
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log('Okhub JWT Auth: Error in authenticateJwt: ' . $e->getMessage());
            } catch (\Error $e) {
                error_log('Okhub JWT Auth: Fatal Error in authenticateJwt: ' . $e->getMessage());
            }
        }

        return $user;
    }

    /**
     * Determine current user from JWT token
     */
    public function determineCurrentUser($user)
    {
        // Skip if already determined
        if ($user instanceof \WP_User) {
            return $user;
        }

        // Check if services are initialized
        if (!$this->authService) {
            // Try to initialize services if not already done
            $this->initServices();
            if (!$this->authService) {
                return $user;
            }
        }

        // Check for JWT token in headers
        $token = $this->getJwtTokenFromHeaders();
        if ($token) {
            try {
                $result = $this->authService->verifyToken($token);
                if ($result && isset($result['success']) && $result['success']) {
                    // Handle both 'data' and direct 'user_id' in response
                    $user_id = null;
                    if (isset($result['data']['user_id'])) {
                        $user_id = $result['data']['user_id'];
                    } elseif (isset($result['user_id'])) {
                        $user_id = $result['user_id'];
                    }
                    
                    if ($user_id) {
                        $user = \get_user_by('ID', $user_id);
                        if ($user) {
                            return $user;
                        }
                    }
                }
            } catch (\Exception $e) {
                error_log('Okhub JWT Auth: Error in determineCurrentUser: ' . $e->getMessage());
            } catch (\Error $e) {
                error_log('Okhub JWT Auth: Fatal Error in determineCurrentUser: ' . $e->getMessage());
            }
        }

        return $user;
    }

    /**
     * Get JWT token from request headers
     */
    private function getJwtTokenFromHeaders()
    {
        $headers = getallheaders();

        // Check Authorization header
        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
            if (preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
                return $matches[1];
            }
        }

        // Check for custom header
        if (isset($headers['X-JWT-Token'])) {
            return $headers['X-JWT-Token'];
        }

        return null;
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes()
    {
        // Routes will be registered by RestApi class
    }

    /**
     * Handle user login
     */
    public function onUserLogin($user_login, $user)
    {
        // Note: JWT tokens and sessions are now handled by AuthService
        // This method is kept for backward compatibility
    }

    /**
     * Handle user logout
     */
    public function onUserLogout()
    {
        // Clear JWT tokens from user meta
        $user_id = \get_current_user_id();
        if ($user_id) {
            \delete_user_meta($user_id, '_jwt_tokens');
        }
    }

    /**
     * Schedule cleanup of expired tokens
     */
    public function scheduleCleanup()
    {
        // Use WordPress built-in wp_scheduled_delete event (runs daily)
        // This is more efficient than custom cron events
    }

    /**
     * Cleanup expired tokens from blacklist
     */
    public function cleanupExpiredTokens()
    {
        if ($this->tokenService) {
            $this->tokenService->cleanupExpiredTokens();
        }

        if ($this->sessionService) {
            $this->sessionService->cleanupExpiredSessions();
            $this->sessionService->cleanupOrphanedSessions();
        }
    }
}

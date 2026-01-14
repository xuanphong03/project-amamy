<?php

namespace OkhubJwtAuth\Api;

use OkhubJwtAuth\Services\AuthService;
use OkhubJwtAuth\Services\SocialLoginService;

/**
 * REST API endpoints for JWT authentication
 */
class RestApi
{
    private $authService;
    private $socialLoginService;
    private $namespace = 'okhub-jwt/v1';

    public function __construct(AuthService $authService, ?SocialLoginService $socialLoginService = null)
    {
        $this->authService = $authService;
        $this->socialLoginService = $socialLoginService;
        $this->initRoutes();
    }

    /**
     * Initialize REST API routes
     */
    private function initRoutes()
    {
        \add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    /**
     * Register REST API routes
     */
    public function registerRoutes()
    {
        // Authentication endpoints - RESTful design
        \register_rest_route($this->namespace, '/auth/login', [
            'methods' => 'POST',
            'callback' => [$this, 'login'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => false,
                    'type' => 'string',
                    'format' => 'email',
                    'sanitize_callback' => 'sanitize_email'
                ],
                'username' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_user'
                ],
                'password' => [
                    'required' => true,
                    'type' => 'string',
                    'minLength' => 1
                ]
            ],
            'validate_callback' => [$this, 'validateLoginRequest']
        ]);

        \register_rest_route($this->namespace, '/auth/register', [
            'methods' => 'POST',
            'callback' => [$this, 'register'],
            'permission_callback' => '__return_true',
            'args' => [
                'username' => [
                    'required' => true,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_user',
                    'minLength' => 3
                ],
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'email',
                    'sanitize_callback' => 'sanitize_email'
                ],
                'password' => [
                    'required' => true,
                    'type' => 'string',
                    'minLength' => 6
                ],
                'first_name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ]
            ]
        ]);

        \register_rest_route($this->namespace, '/auth/refresh', [
            'methods' => 'POST',
            'callback' => [$this, 'refreshToken'],
            'permission_callback' => '__return_true',
            'args' => [
                'refresh_token' => [
                    'required' => true,
                    'type' => 'string'
                ]
            ]
        ]);

        \register_rest_route($this->namespace, '/auth/logout', [
            'methods' => 'POST',
            'callback' => [$this, 'logout'],
            'permission_callback' => [$this, 'checkAuth'],
            'args' => []
        ]);

        \register_rest_route($this->namespace, '/auth/logout-all', [
            'methods' => 'POST',
            'callback' => [$this, 'logoutAllDevices'],
            'permission_callback' => [$this, 'checkAuth'],
            'args' => []
        ]);

        // User management endpoints - RESTful design
        \register_rest_route($this->namespace, '/users/me', [
            'methods' => 'GET',
            'callback' => [$this, 'getCurrentUser'],
            'permission_callback' => [$this, 'checkAuth'],
            'args' => []
        ]);

        \register_rest_route($this->namespace, '/users/me/sessions', [
            'methods' => 'GET',
            'callback' => [$this, 'getUserSessions'],
            'permission_callback' => [$this, 'checkAuth'],
            'args' => []
        ]);

        \register_rest_route($this->namespace, '/users/me/profile', [
            'methods' => 'PUT',
            'callback' => [$this, 'updateProfile'],
            'permission_callback' => [$this, 'checkAuth'],
            'args' => [
                'first_name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'last_name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'phone' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'gender' => [
                    'required' => false,
                    'type' => 'string',
                    'enum' => ['male', 'female', 'other']
                ],
                'date_of_birth' => [
                    'required' => false,
                    'type' => 'string',
                    'pattern' => '^\d{4}-\d{2}-\d{2}$'
                ]
            ]
        ]);

        \register_rest_route($this->namespace, '/users/me/password', [
            'methods' => 'PUT',
            'callback' => [$this, 'changePassword'],
            'permission_callback' => [$this, 'checkAuth'],
            'args' => [
                'current_password' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'new_password' => [
                    'required' => true,
                    'type' => 'string',
                    'minLength' => 6
                ]
            ]
        ]);

        // Password reset endpoints - RESTful design
        \register_rest_route($this->namespace, '/auth/password/forgot', [
            'methods' => 'POST',
            'callback' => [$this, 'forgotPassword'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'email',
                    'sanitize_callback' => 'sanitize_email'
                ]
            ]
        ]);

        \register_rest_route($this->namespace, '/auth/password/reset', [
            'methods' => 'POST',
            'callback' => [$this, 'resetPassword'],
            'permission_callback' => '__return_true',
            'args' => [
                'token' => [
                    'required' => true,
                    'type' => 'string'
                ],
                'new_password' => [
                    'required' => true,
                    'type' => 'string',
                    'minLength' => 6
                ]
            ]
        ]);

        \register_rest_route($this->namespace, '/auth/password/validate-token', [
            'methods' => 'GET',
            'callback' => [$this, 'validateResetToken'],
            'permission_callback' => '__return_true',
            'args' => [
                'token' => [
                    'required' => true,
                    'type' => 'string'
                ]
            ]
        ]);

        // OTP Password reset endpoints - RESTful design
        \register_rest_route($this->namespace, '/auth/password/otp/request', [
            'methods' => 'POST',
            'callback' => [$this, 'forgotPasswordWithOtp'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'email',
                    'sanitize_callback' => 'sanitize_email'
                ]
            ]
        ]);

        \register_rest_route($this->namespace, '/auth/password/otp/reset', [
            'methods' => 'POST',
            'callback' => [$this, 'resetPasswordWithOtp'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'email',
                    'sanitize_callback' => 'sanitize_email'
                ],
                'otp_code' => [
                    'required' => true,
                    'type' => 'string',
                    'pattern' => '^[0-9]{6}$'
                ],
                'new_password' => [
                    'required' => true,
                    'type' => 'string',
                    'minLength' => 6
                ]
            ]
        ]);

        \register_rest_route($this->namespace, '/auth/password/otp/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'verifyOtp'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'email',
                    'sanitize_callback' => 'sanitize_email'
                ],
                'otp_code' => [
                    'required' => true,
                    'type' => 'string',
                    'pattern' => '^[0-9]{6}$'
                ]
            ]
        ]);

        // Registration OTP verification endpoints
        \register_rest_route($this->namespace, '/auth/register/verify', [
            'methods' => 'POST',
            'callback' => [$this, 'verifyRegistrationOtp'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'email',
                    'sanitize_callback' => 'sanitize_email'
                ],
                'otp_code' => [
                    'required' => true,
                    'type' => 'string',
                    'pattern' => '^[0-9]{6}$'
                ]
            ]
        ]);

        \register_rest_route($this->namespace, '/auth/register/resend-otp', [
            'methods' => 'POST',
            'callback' => [$this, 'resendRegistrationOtp'],
            'permission_callback' => '__return_true',
            'args' => [
                'email' => [
                    'required' => true,
                    'type' => 'string',
                    'format' => 'email',
                    'sanitize_callback' => 'sanitize_email'
                ]
            ]
        ]);

        // Social Login endpoint - RESTful design
        \register_rest_route($this->namespace, '/auth/social/login', [
            'methods' => 'POST',
            'callback' => [$this, 'socialLogin'],
            'permission_callback' => '__return_true',
            'args' => [
                'provider' => [
                    'required' => true,
                    'type' => 'string',
                    'enum' => ['google'],
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                // Token-based authentication (preferred)
                'idToken' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'accessToken' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'code' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                // Fallback data (less secure)
                'email' => [
                    'required' => false,
                    'type' => 'string',
                    'format' => 'email',
                    'sanitize_callback' => 'sanitize_email'
                ],
                'googleId' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'name' => [
                    'required' => false,
                    'type' => 'string',
                    'sanitize_callback' => 'sanitize_text_field'
                ],
                'picture' => [
                    'required' => false,
                    'type' => 'string',
                    'format' => 'uri',
                    'sanitize_callback' => 'esc_url_raw'
                ]
            ]
        ]);
    }

    /**
     * Validate login request
     */
    public function validateLoginRequest($request)
    {
        $email = $request->get_param('email');
        $username = $request->get_param('username');

        // At least one of email or username must be provided
        if (!$email && !$username) {
            return new \WP_Error('missing_credentials', 'Email or username is required', ['status' => 400]);
        }

        // If username login is disabled, email is required
        $enableUsernameLogin = \get_option('okhub_jwt_enable_username_login', false);
        if (!$enableUsernameLogin && !$email) {
            return new \WP_Error('email_required', 'Email is required when username login is disabled', ['status' => 400]);
        }

        return true;
    }

    /**
     * Check if user is authenticated
     */
    public function checkAuth($request)
    {
        $token = $this->getTokenFromRequest($request);
        if (!$token) {
            return new \WP_Error('jwt_auth_no_token', 'Access token is required', ['status' => 401]);
        }

        $result = $this->authService->verifyToken($token);
        if (!$result['success']) {
            return new \WP_Error('jwt_auth_invalid_token', $result['message'] ?? 'Invalid or expired token', ['status' => 401]);
        }

        return true;
    }

    /**
     * Get token from request
     */
    private function getTokenFromRequest($request)
    {
        $auth_header = $request->get_header('Authorization');
        if ($auth_header && preg_match('/Bearer\s+(.*)$/i', $auth_header, $matches)) {
            return $matches[1];
        }

        return $request->get_param('token');
    }

    /**
     * Get current user ID from token
     */
    private function getCurrentUserId($request)
    {
        $token = $this->getTokenFromRequest($request);
        $result = $this->authService->verifyToken($token);

        if ($result['success']) {
            return $result['user_id'];
        }

        return 0;
    }

    /**
     * Login endpoint
     */
    public function login($request)
    {
        $email = $request->get_param('email');
        $username = $request->get_param('username');
        $password = $request->get_param('password');

        // Use username if provided and enabled, otherwise use email
        $enableUsernameLogin = \get_option('okhub_jwt_enable_username_login', false);

        if ($enableUsernameLogin && $username) {
            $result = $this->authService->loginWithUsername($username, $password);
        } else {
            $result = $this->authService->login($email, $password);
        }

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('login_failed', $result['message'], ['status' => 401]);
        }
    }

    /**
     * Register endpoint
     */
    public function register($request)
    {
        $username = $request->get_param('username');
        $email = $request->get_param('email');
        $password = $request->get_param('password');
        $firstName = $request->get_param('first_name', '');
        $customer_code = $request->get_param('customer_code', '');
        // Validate required fields
        if (!$username || !$email || !$password) {
            return new \WP_Error('missing_fields', 'Username, email and password are required', ['status' => 400]);
        }

        // Check if username login is enabled
        $enableUsernameLogin = \get_option('okhub_jwt_enable_username_login', false);
        if ($enableUsernameLogin && !$username) {
            return new \WP_Error('missing_username', 'Username is required when username login is enabled', ['status' => 400]);
        }
        if ($customer_code) {
            $customer_code = sanitize_text_field($customer_code);
            $customer_code_exists = get_term_by('name', $customer_code, 'customer_code');
            if (!$customer_code_exists) {
                return new \WP_Error('customer_code_exists', 'Customer code not found', ['status' => 400]);
            }
        }

        $result = $this->authService->register($username, $email, $password, $firstName, $customer_code);

        if ($result['success']) {
            return new \WP_REST_Response($result, 201);
        } else {
            return new \WP_Error('registration_failed', $result['message'], ['status' => 400]);
        }
    }

    function wp_find_post_by_title($title, $post_type = 'post')
    {
        global $wpdb;
        $post_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s AND post_status = 'publish' LIMIT 1",
            $title,
            $post_type
        ));
        return $post_id ? get_post($post_id) : null;
    }

    /**
     * Refresh token endpoint
     */
    public function refreshToken($request)
    {
        $refreshToken = $request->get_param('refresh_token');

        $result = $this->authService->refreshToken($refreshToken);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('refresh_failed', $result['message'], ['status' => 401]);
        }
    }

    /**
     * Logout endpoint
     */
    public function logout($request)
    {
        $token = $this->getTokenFromRequest($request);

        $result = $this->authService->logout($token);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('logout_failed', $result['message'], ['status' => 400]);
        }
    }

    /**
     * Get current user endpoint
     */
    public function getCurrentUser($request)
    {
        $token = $this->getTokenFromRequest($request);

        $result = $this->authService->getCurrentUser($token);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('user_not_found', $result['message'], ['status' => 404]);
        }
    }

    /**
     * Update profile endpoint
     */
    public function updateProfile($request)
    {
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return new \WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $data = $request->get_params();
        $customer_code = $request->get_param('customer_code', '');
        if ($customer_code) {
            $customer_code = sanitize_text_field($customer_code);
            $customer_code_exists = get_term_by('name', $customer_code, 'customer_code');
            if (!$customer_code_exists) {
                return new \WP_Error('customer_code_exists', 'Customer code not found', ['status' => 400]);
            }
        }
        unset($data['token']); // Remove token from data

        $result = $this->authService->updateProfile($userId, $data);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('update_failed', $result['message'], ['status' => 400]);
        }
    }

    /**
     * Change password endpoint
     */
    public function changePassword($request)
    {
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return new \WP_Error('unauthorized', 'Unauthorized', ['status' => 401]);
        }

        $currentPassword = $request->get_param('current_password');
        $newPassword = $request->get_param('new_password');

        $result = $this->authService->changePassword($userId, $currentPassword, $newPassword);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('password_change_failed', $result['message'], ['status' => 400]);
        }
    }

    /**
     * Forgot password endpoint
     */
    public function forgotPassword($request)
    {
        $email = $request->get_param('email');

        $result = $this->authService->forgotPassword($email);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('forgot_password_failed', $result['message'], ['status' => 400]);
        }
    }

    /**
     * Reset password endpoint
     */
    public function resetPassword($request)
    {
        $token = $request->get_param('token');
        $newPassword = $request->get_param('new_password');

        $result = $this->authService->resetPassword($token, $newPassword);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('reset_password_failed', $result['message'], ['status' => 400]);
        }
    }

    /**
     * Validate reset token endpoint
     */
    public function validateResetToken($request)
    {
        $token = $request->get_param('token');

        $result = $this->authService->validateResetToken($token);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('invalid_token', $result['message'], ['status' => 400]);
        }
    }

    /**
     * Forgot password with OTP endpoint
     */
    public function forgotPasswordWithOtp($request)
    {
        $email = $request->get_param('email');

        $result = $this->authService->forgotPasswordWithOtp($email);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('otp_failed', $result['message'], ['status' => $result['status'] ?? 400]);
        }
    }

    /**
     * Reset password with OTP endpoint
     */
    public function resetPasswordWithOtp($request)
    {
        $email = $request->get_param('email');
        $otpCode = $request->get_param('otp_code');
        $newPassword = $request->get_param('new_password');

        $result = $this->authService->resetPasswordWithOtp($email, $otpCode, $newPassword);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('reset_failed', $result['message'], ['status' => $result['status'] ?? 400]);
        }
    }

    /**
     * Verify OTP endpoint
     */
    public function verifyOtp($request)
    {
        $email = $request->get_param('email');
        $otpCode = $request->get_param('otp_code');

        $result = $this->authService->verifyOtp($email, $otpCode);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('otp_invalid', $result['message'], ['status' => $result['status'] ?? 400]);
        }
    }

    /**
     * Verify registration OTP endpoint
     */
    public function verifyRegistrationOtp($request)
    {
        $email = $request->get_param('email');
        $otpCode = $request->get_param('otp_code');

        $result = $this->authService->verifyRegistrationOtp($email, $otpCode);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('registration_verification_failed', $result['message'], ['status' => $result['status'] ?? 400]);
        }
    }

    /**
     * Resend registration OTP endpoint
     */
    public function resendRegistrationOtp($request)
    {
        $email = $request->get_param('email');

        $result = $this->authService->resendRegistrationOtp($email);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error('resend_otp_failed', $result['message'], ['status' => $result['status'] ?? 400]);
        }
    }

    /**
     * Get user sessions endpoint
     */
    public function getUserSessions($request)
    {
        $token = $this->getTokenFromRequest($request);
        if (!$token) {
            return new \WP_Error('unauthorized', 'Token không hợp lệ', ['status' => 401]);
        }

        $result = $this->authService->getUserSessions($token);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_REST_Response($result, $result['status'] ?? 400);
        }
    }

    /**
     * Logout all devices endpoint
     */
    public function logoutAllDevices($request)
    {
        $token = $this->getTokenFromRequest($request);
        if (!$token) {
            return new \WP_Error('unauthorized', 'Token không hợp lệ', ['status' => 401]);
        }

        $result = $this->authService->logoutAllDevices($token);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_REST_Response($result, $result['status'] ?? 400);
        }
    }

    /**
     * Social Login endpoint
     */
    public function socialLogin($request)
    {
        // Check if social login service is available
        if (!$this->socialLoginService) {
            return new \WP_Error('service_unavailable', 'Social login service is not available', ['status' => 503]);
        }

        // Get request data
        $data = $request->get_params();

        // Process Google login
        $result = $this->socialLoginService->processGoogleLogin($data);

        if ($result['success']) {
            return new \WP_REST_Response($result, 200);
        } else {
            return new \WP_Error($result['code'] ?? 'social_login_failed', $result['message'], ['status' => $result['status'] ?? 400]);
        }
    }
}

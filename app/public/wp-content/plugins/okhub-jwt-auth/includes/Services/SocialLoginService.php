<?php

namespace OkhubJwtAuth\Services;

/**
 * Social Login Service - Handle Google Login Integration
 * 
 * Handles 3 scenarios:
 * 1. New user → register
 * 2. Existing Google user → login
 * 3. Existing local user → merge Google account
 */
class SocialLoginService
{
    private $authService;
    private $userService;
    private $tokenService;
    private $sessionService;
    private $googleOAuthService;

    public function __construct(
        AuthService $authService,
        UserService $userService,
        TokenService $tokenService,
        ?SessionService $sessionService = null
    ) {
        $this->authService = $authService;
        $this->userService = $userService;
        $this->tokenService = $tokenService;
        $this->sessionService = $sessionService;

        // Initialize Google OAuth Service if credentials are configured
        if (GoogleOAuthService::isConfigured()) {
            try {
                $this->googleOAuthService = new GoogleOAuthService();
            } catch (\Exception $e) {
                error_log('Failed to initialize Google OAuth Service: ' . $e->getMessage());
                $this->googleOAuthService = null;
            }
        }
    }

    /**
     * Process Google Login
     * 
     * @param array $data
     * @return array
     */
    public function processGoogleLogin($data)
    {
        // Check if social login is enabled
        if (!get_option('okhub_jwt_enable_social_login', false)) {
            return $this->errorResponse('Social login is disabled', 403);
        }

        // Check if Google OAuth is configured
        if (!$this->googleOAuthService) {
            return $this->errorResponse('Google OAuth credentials not configured', 500);
        }

        // Validate required fields
        $validation = $this->validateGoogleData($data);
        if (!$validation['valid']) {
            return $this->errorResponse($validation['message'], 400);
        }

        // Verify Google token
        $googleUserInfo = $this->verifyGoogleToken($data);
        if (!$googleUserInfo) {
            return $this->errorResponse('Invalid Google token or verification failed', 401);
        }

        $email = $googleUserInfo['email'];
        $googleId = $googleUserInfo['user_id'];
        $name = $googleUserInfo['name'];
        $picture = $googleUserInfo['picture'] ?? '';

        // Check if user exists by email
        $user = $this->userService->findByEmail($email);

        if (!$user) {
            // Scenario 1: New user → register
            return $this->registerNewGoogleUser($email, $googleId, $name, $picture);
        }

        // Check if user is already a Google user
        $isGoogleUser = $this->isGoogleUser($user->ID);
        $storedGoogleId = $this->getGoogleId($user->ID);

        if ($isGoogleUser && $storedGoogleId === $googleId) {
            // Scenario 2: Existing Google user → login
            return $this->loginGoogleUser($user);
        }

        if (!$isGoogleUser) {
            // Check if account merge is allowed
            if (!get_option('okhub_jwt_allow_account_merge', true)) {
                return $this->errorResponse('Account merging is disabled', 403);
            }

            // Scenario 3: Existing local user → merge Google account
            return $this->mergeGoogleAccount($user, $googleId, $name, $picture);
        }

        // Google ID mismatch - security issue
        return $this->errorResponse('Google account không khớp với tài khoản hiện tại', 409);
    }

    /**
     * Verify Google token
     * 
     * @param array $data
     * @return array|false
     */
    private function verifyGoogleToken($data)
    {
        // Try to verify as ID token first
        if (isset($data['idToken']) && !empty($data['idToken']) && $this->googleOAuthService) {
            $userInfo = $this->googleOAuthService->verifyIdToken($data['idToken']);
            if ($userInfo) {
                return $userInfo;
            }
        }

        // Try to verify as access token
        if (isset($data['accessToken']) && !empty($data['accessToken']) && $this->googleOAuthService) {
            $userInfo = $this->googleOAuthService->verifyAccessToken($data['accessToken']);
            if ($userInfo) {
                return $userInfo;
            }
        }

        // Try to verify as authorization code
        if (isset($data['code']) && !empty($data['code']) && $this->googleOAuthService) {
            $userInfo = $this->googleOAuthService->verifyAuthorizationCode($data['code']);
            if ($userInfo) {
                return $userInfo;
            }
        }

        // Fallback: if no token provided, use provided data (less secure)
        if (isset($data['googleId']) && isset($data['email'])) {
            return [
                'user_id' => $data['googleId'],
                'email' => $data['email'],
                'name' => $data['name'] ?? '',
                'picture' => $data['picture'] ?? '',
                'email_verified' => true // Assume verified for fallback
            ];
        }

        return false;
    }

    /**
     * Validate Google login data
     * 
     * @param array $data
     * @return array
     */
    private function validateGoogleData($data)
    {
        // Check for required provider
        if (!isset($data['provider']) || $data['provider'] !== 'google') {
            return [
                'valid' => false,
                'message' => 'Only Google provider is supported'
            ];
        }

        // Check for at least one token type or fallback data
        $hasToken = isset($data['idToken']) || isset($data['accessToken']) || isset($data['code']);
        $hasFallbackData = isset($data['googleId']) && isset($data['email']);

        if (!$hasToken && !$hasFallbackData) {
            return [
                'valid' => false,
                'message' => 'Either token (idToken, accessToken, code) or fallback data (googleId, email) is required'
            ];
        }

        // If using fallback data, validate required fields
        if ($hasFallbackData) {
            $requiredFields = ['email', 'googleId', 'name'];

            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty($data[$field])) {
                    return [
                        'valid' => false,
                        'message' => "Field '{$field}' is required for fallback mode"
                    ];
                }
            }

            // Validate email format
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return [
                    'valid' => false,
                    'message' => 'Invalid email format'
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Register new Google user
     * 
     * @param string $email
     * @param string $googleId
     * @param string $name
     * @param string $picture
     * @return array
     */
    private function registerNewGoogleUser($email, $googleId, $name, $picture)
    {
        // Generate unique username from email
        $username = $this->generateUsernameFromEmail($email);

        // Generate random password (not used for Google login)
        $randomPassword = wp_generate_password(12, true, true);

        // Prepare user data
        $userData = [
            'username' => $username,
            'email' => $email,
            'password' => $randomPassword,
            'first_name' => $name,
            'display_name' => $name
        ];

        // WordPress hook: pre_google_user_registration
        $userData = \apply_filters('okhub_jwt_pre_google_user_registration', $userData);

        // Create user
        $user = $this->userService->create($userData);

        if (\is_wp_error($user)) {
            return $this->errorResponse('Không thể tạo tài khoản: ' . $user->get_error_message());
        }

        // Save Google-specific user meta
        $this->saveGoogleUserMeta($user->ID, $googleId, $name, $picture);

        // Auto verify email for Google users (if enabled)
        if (get_option('okhub_jwt_auto_verify_google_email', true)) {
            $this->autoVerifyEmail($user->ID);
        }

        // Generate tokens
        $sessionId = null;
        if ($this->sessionService) {
            $sessionId = $this->sessionService->generateSessionId();
        }

        $tokens = $this->tokenService->generateTokens($user->ID, $sessionId);
        $userInfo = $this->userService->getPublicInfo($user);

        // Create session
        if ($this->sessionService && $sessionId) {
            $deviceInfo = $this->sessionService->getDeviceInfo();
            $this->sessionService->createSession($user->ID, $sessionId, $deviceInfo, $tokens);
        }

        // WordPress action: google_user_registered
        \do_action('okhub_jwt_google_user_registered', $user, $googleId);

        return [
            'success' => true,
            'message' => 'Đăng ký tài khoản Google thành công',
            'data' => $userInfo,
            'token' => $this->formatTokenResponse($tokens)
        ];
    }

    /**
     * Login existing Google user
     * 
     * @param \WP_User $user
     * @return array
     */
    private function loginGoogleUser($user)
    {
        // Check if user is blocked
        if ($this->isUserBlocked($user)) {
            return $this->errorResponse('Tài khoản đã bị khóa', 403);
        }

        // Auto verify email for Google users (if enabled)
        if (get_option('okhub_jwt_auto_verify_google_email', true)) {
            $this->autoVerifyEmail($user->ID);
        }

        // Generate tokens
        $sessionId = null;
        if ($this->sessionService) {
            $sessionId = $this->sessionService->generateSessionId();
        }

        $tokens = $this->tokenService->generateTokens($user->ID, $sessionId);
        $userInfo = $this->userService->getPublicInfo($user);

        // Create session
        if ($this->sessionService && $sessionId) {
            $deviceInfo = $this->sessionService->getDeviceInfo();
            $this->sessionService->createSession($user->ID, $sessionId, $deviceInfo, $tokens);
        }

        // WordPress action: google_user_login
        \do_action('okhub_jwt_google_user_login', $user);

        return [
            'success' => true,
            'message' => 'Đăng nhập Google thành công',
            'data' => $userInfo,
            'token' => $this->formatTokenResponse($tokens)
        ];
    }

    /**
     * Merge Google account with existing local user
     * 
     * @param \WP_User $user
     * @param string $googleId
     * @param string $name
     * @param string $picture
     * @return array
     */
    private function mergeGoogleAccount($user, $googleId, $name, $picture)
    {
        // Save Google-specific user meta
        $this->saveGoogleUserMeta($user->ID, $googleId, $name, $picture);

        // Auto verify email for Google users (if enabled)
        if (get_option('okhub_jwt_auto_verify_google_email', true)) {
            $this->autoVerifyEmail($user->ID);
        }

        // Update display name if not set
        if (empty($user->display_name) || $user->display_name === $user->user_login) {
            wp_update_user([
                'ID' => $user->ID,
                'display_name' => $name
            ]);
        }

        // Generate tokens
        $sessionId = null;
        if ($this->sessionService) {
            $sessionId = $this->sessionService->generateSessionId();
        }

        $tokens = $this->tokenService->generateTokens($user->ID, $sessionId);
        $userInfo = $this->userService->getPublicInfo($user);

        // Create session
        if ($this->sessionService && $sessionId) {
            $deviceInfo = $this->sessionService->getDeviceInfo();
            $this->sessionService->createSession($user->ID, $sessionId, $deviceInfo, $tokens);
        }

        // WordPress action: google_account_merged
        \do_action('okhub_jwt_google_account_merged', $user, $googleId);

        return [
            'success' => true,
            'message' => 'Liên kết tài khoản Google thành công',
            'data' => $userInfo,
            'token' => $this->formatTokenResponse($tokens)
        ];
    }

    /**
     * Save Google user meta
     * 
     * @param int $userId
     * @param string $googleId
     * @param string $name
     * @param string $picture
     */
    private function saveGoogleUserMeta($userId, $googleId, $name, $picture)
    {
        update_user_meta($userId, 'google_id', $googleId);
        update_user_meta($userId, 'provider', 'google');
        update_user_meta($userId, 'is_google_user', true);

        if (!empty($picture)) {
            update_user_meta($userId, 'google_picture', $picture);
        }

        if (!empty($name)) {
            update_user_meta($userId, 'google_name', $name);
        }
    }

    /**
     * Check if user is a Google user
     * 
     * @param int $userId
     * @return bool
     */
    private function isGoogleUser($userId)
    {
        return (bool) get_user_meta($userId, 'is_google_user', true);
    }

    /**
     * Get Google ID for user
     * 
     * @param int $userId
     * @return string|null
     */
    private function getGoogleId($userId)
    {
        return get_user_meta($userId, 'google_id', true);
    }

    /**
     * Check if user is blocked
     * 
     * @param \WP_User $user
     * @return bool
     */
    private function isUserBlocked($user)
    {
        // Check if user is blocked by WordPress
        if ($user->user_status !== '0') {
            return true;
        }

        // Check custom block status
        $isBlocked = get_user_meta($user->ID, 'is_blocked', true);
        return (bool) $isBlocked;
    }

    /**
     * Auto verify email for Google users
     * 
     * @param int $userId
     */
    private function autoVerifyEmail($userId)
    {
        // Use UserService to verify email (consistent with other verification methods)
        $this->userService->verifyEmail($userId);

        // Also set verification timestamp
        update_user_meta($userId, 'email_verified_at', current_time('mysql'));
    }

    /**
     * Generate username from email
     * 
     * @param string $email
     * @return string
     */
    private function generateUsernameFromEmail($email)
    {
        $username = strtolower(explode('@', $email)[0]);
        $baseUsername = $username;
        $counter = 1;

        // Check if username exists and create unique username
        while ($this->userService->usernameExists($username)) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Format token response to match expected format
     * 
     * @param array $tokens
     * @return array
     */
    private function formatTokenResponse($tokens)
    {
        // Decode tokens to get payload
        $accessPayload = $this->decodeTokenPayload($tokens['access_token']);
        $refreshPayload = $this->decodeTokenPayload($tokens['refresh_token']);

        return [
            'accessToken' => $tokens['access_token'],
            'refreshToken' => $tokens['refresh_token'],
            'refreshPayload' => $refreshPayload,
            'accessPayload' => $accessPayload
        ];
    }

    /**
     * Decode JWT token to get payload
     * 
     * @param string $token
     * @return array
     */
    private function decodeTokenPayload($token)
    {
        try {
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);

                // Format dates to ISO format
                if (isset($payload['iat'])) {
                    $payload['iat'] = date('c', $payload['iat']);
                }
                if (isset($payload['exp'])) {
                    $payload['exp'] = date('c', $payload['exp']);
                }

                return $payload;
            }
        } catch (\Exception $e) {
            // Return empty array if decoding fails
        }

        return [];
    }

    /**
     * Helper method for error response
     * 
     * @param string $message
     * @param int $code
     * @return array
     */
    private function errorResponse($message, $code = 400)
    {
        return [
            'success' => false,
            'code' => $this->getErrorCode($code, $message),
            'message' => $message,
            'status' => $code
        ];
    }

    /**
     * Get error code based on HTTP status and message
     * 
     * @param int $status
     * @param string $message
     * @return string
     */
    private function getErrorCode($status, $message = '')
    {
        switch ($status) {
            case 400:
                if (strpos($message, 'required') !== false) {
                    return 'missing_fields';
                }
                if (strpos($message, 'Invalid') !== false) {
                    return 'invalid_data';
                }
                return 'bad_request';
            case 403:
                return 'account_blocked';
            case 409:
                return 'account_conflict';
            default:
                return 'error';
        }
    }
}

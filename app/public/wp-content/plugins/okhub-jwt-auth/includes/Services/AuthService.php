<?php

namespace OkhubJwtAuth\Services;

/**
 * Main authentication service
 */
class AuthService
{
    private $tokenService;
    private $userService;
    private $emailService;
    private $resetTokenService;
    private $otpService;
    private $sessionService;

    public function __construct(
        TokenService $tokenService,
        UserService $userService,
        EmailService $emailService,
        ?SessionService $sessionService = null
    ) {
        $this->tokenService = $tokenService;
        $this->userService = $userService;
        $this->emailService = $emailService;
        $this->sessionService = $sessionService;
        $this->resetTokenService = new ResetTokenService();
        $this->otpService = new OtpService();

        // Create password reset tables if not exists
        $this->resetTokenService->createTableIfNotExists();
        $this->otpService->createTableIfNotExists();
    }

    /**
     * User login with email and password
     */
    public function login($email, $password, $deviceInfo = null)
    {
        // Find user by email
        $user = $this->userService->findByEmail($email);
        if (!$user) {
            return $this->errorResponse('Email không tồn tại trong hệ thống', 404);
        }

        // Verify password
        if (!$this->userService->verifyPassword($user->ID, $password)) {
            return $this->errorResponse('Mật khẩu không chính xác', 401);
        }

        // Check if email verification is enabled and user is verified
        $emailVerificationEnabled = \get_option('okhub_jwt_enable_email_verification', true);
        if ($emailVerificationEnabled && !$this->userService->isEmailVerified($user->ID)) {
            return $this->errorResponse('Vui lòng xác thực email trước khi đăng nhập', 403);
        }

        return $this->processLoginSuccess($user, $deviceInfo);
    }

    /**
     * User login with username and password
     */
    public function loginWithUsername($username, $password, $deviceInfo = null)
    {
        // Find user by username
        $user = $this->userService->findByUsername($username);
        if (!$user) {
            return $this->errorResponse('Username không tồn tại trong hệ thống', 404);
        }

        // Verify password
        if (!$this->userService->verifyPassword($user->ID, $password)) {
            return $this->errorResponse('Mật khẩu không chính xác', 401);
        }

        return $this->processLoginSuccess($user, $deviceInfo);
    }

    /**
     * Process successful login
     */
    private function processLoginSuccess($user, $deviceInfo = null)
    {
        // Generate session ID first
        $sessionId = null;
        if ($this->sessionService) {
            $sessionId = $this->sessionService->generateSessionId();
        }

        // Generate tokens with session_id
        $tokens = $this->tokenService->generateTokens($user->ID, $sessionId);
        $userInfo = $this->userService->getPublicInfo($user);

        // WordPress hook: pre_session_creation
        $sessionData = [
            'user_id' => $user->ID,
            'session_id' => $sessionId,
            'device_info' => $deviceInfo ?: $this->sessionService->getDeviceInfo(),
            'tokens' => $tokens
        ];
        $sessionData = \apply_filters('okhub_jwt_pre_session_creation', $sessionData);

        // Create session for multi-device support
        if ($this->sessionService && $sessionData['session_id']) {
            $this->sessionService->createSession(
                $sessionData['user_id'],
                $sessionData['session_id'],
                $sessionData['device_info'],
                $sessionData['tokens']
            );
        }

        // WordPress action: session_created
        \do_action('okhub_jwt_session_created', $sessionData);

        // Prepare response data
        $responseData = [
            'success' => true,
            'message' => 'Login successfully',
            'data' => $userInfo,
            'token' => $this->formatTokenResponse($tokens)
        ];

        // WordPress hook: post_login_success
        $responseData = \apply_filters('okhub_jwt_post_login_success', $responseData);

        // WordPress action: login_success
        \do_action('okhub_jwt_login_success', $responseData, $user);

        return $responseData;
    }

    /**
     * Refresh access token
     */
    public function refreshToken($refreshToken)
    {
        $tokens = $this->tokenService->refreshAccessToken($refreshToken);

        if ($tokens) {
            return [
                'success' => true,
                'message' => 'Token refreshed successfully',
                'token' => $this->formatTokenResponse($tokens)
            ];
        }

        return $this->errorResponse('Refresh token không hợp lệ hoặc đã hết hạn', 401);
    }

    /**
     * Verify access token
     */
    public function verifyToken($token)
    {
        // Check if token is blacklisted
        if ($this->tokenService->isTokenBlacklisted($token)) {
            return $this->errorResponse('Token đã bị vô hiệu hóa');
        }

        $decoded = $this->tokenService->validateAccessToken($token);

        if ($decoded) {
            return [
                'success' => true,
                'user_id' => $decoded['user_id'],
                'payload' => $decoded
            ];
        }

        return $this->errorResponse('Token không hợp lệ hoặc đã hết hạn');
    }

    /**
     * Get current user info
     */
    public function getCurrentUser($token)
    {
        $verifyResult = $this->verifyToken($token);

        if (!$verifyResult['success']) {
            return $verifyResult;
        }

        $user = $this->userService->findById($verifyResult['user_id']);
        if (!$user) {
            return $this->errorResponse('User không tồn tại', 404);
        }

        return [
            'success' => true,
            'data' => $this->userService->getPublicInfo($user)
        ];
    }

    /**
     * User registration
     */
    public function register($username, $email, $password, $firstName = '', $customer_code = '')
    {
        // Check if username already exists
        if ($this->userService->usernameExists($username)) {
            return $this->errorResponse('Username đã được sử dụng', 422);
        }

        // Check if email already exists
        if ($this->userService->emailExists($email)) {
            return $this->errorResponse('Email đã được sử dụng', 422);
        }

        // Prepare user data
        $userData = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'first_name' => $firstName,
            'customer_code' => $customer_code
        ];

        // WordPress hook: pre_user_registration
        $userData = \apply_filters('okhub_jwt_pre_user_registration', $userData);

        // Check if email verification is enabled
        $emailVerificationEnabled = \get_option('okhub_jwt_enable_email_verification', true);

        // Create user with verification status based on setting
        $user = $this->userService->create($userData, !$emailVerificationEnabled);

        if (\is_wp_error($user)) {
            return $this->errorResponse($user->get_error_message());
        }

        // WordPress hook: post_user_registration
        $user = \apply_filters('okhub_jwt_post_user_registration', $user);

        // WordPress action: user_registered
        \do_action('okhub_jwt_user_registered', $user);

        // If email verification is enabled, send OTP
        if ($emailVerificationEnabled) {
            // Generate OTP for email verification
            $otpResult = $this->otpService->generateOtp($email, 'registration', $user->ID);
            if (!$otpResult['success']) {
                return $this->errorResponse('Không thể tạo mã OTP xác thực', 500);
            }

            // Send OTP verification email
            $emailSent = $this->emailService->sendOtpEmail($email, $otpResult['otp'], 'registration');
            if (!$emailSent) {
                return $this->errorResponse('Không thể gửi email xác thực', 500);
            }

            // Return success without tokens (user needs to verify email first)
            $userInfo = $this->userService->getPublicInfo($user);
            $userInfo['requires_verification'] = true;

            return [
                'success' => true,
                'message' => 'Đăng ký thành công. Vui lòng kiểm tra email để xác thực tài khoản.',
                'data' => $userInfo
            ];
        } else {
            // Email verification disabled - generate tokens immediately
            $tokens = $this->tokenService->generateTokens($user->ID);
            $userInfo = $this->userService->getPublicInfo($user);

            // Send welcome email if enabled
            if (\get_option('okhub_jwt_enable_welcome_email', true)) {
                $this->emailService->sendWelcomeEmail($email, $username);
            }

            return [
                'success' => true,
                'message' => 'Đăng ký thành công',
                'data' => $userInfo,
                'token' => $this->formatTokenResponse($tokens)
            ];
        }
    }

    /**
     * Verify OTP for user registration
     */
    public function verifyRegistrationOtp($email, $otpCode)
    {
        // Check if email verification is enabled
        $emailVerificationEnabled = \get_option('okhub_jwt_enable_email_verification', true);
        if (!$emailVerificationEnabled) {
            return $this->errorResponse('Email verification is disabled', 403);
        }

        // Verify OTP
        $otpValid = $this->otpService->validateOtp($email, $otpCode);
        if (!$otpValid) {
            return $this->errorResponse('Mã OTP không hợp lệ hoặc đã hết hạn', 400);
        }

        // Find user by email
        $user = $this->userService->findByEmail($email);
        if (!$user) {
            return $this->errorResponse('Người dùng không tồn tại', 404);
        }

        // Check if user is already verified
        if ($this->userService->isEmailVerified($user->ID)) {
            return $this->errorResponse('Tài khoản đã được xác thực', 400);
        }

        // Check if user is a Google user (Google users don't need OTP verification)
        $isGoogleUser = \get_user_meta($user->ID, 'is_google_user', true);
        if ($isGoogleUser) {
            return $this->errorResponse('Tài khoản Google không cần xác thực OTP', 400);
        }

        // Mark user as verified
        $verified = $this->userService->verifyEmail($user->ID);
        if (!$verified) {
            return $this->errorResponse('Không thể xác thực tài khoản', 500);
        }

        // Generate tokens for verified user
        $tokens = $this->tokenService->generateTokens($user->ID);
        $userInfo = $this->userService->getPublicInfo($user);

        // WordPress action: email_verified
        \do_action('okhub_jwt_email_verified', $user);

        return [
            'success' => true,
            'message' => 'Xác thực email thành công',
            'data' => $userInfo,
            'token' => $this->formatTokenResponse($tokens)
        ];
    }

    /**
     * Resend OTP for registration verification
     */
    public function resendRegistrationOtp($email)
    {
        // Check if email verification is enabled
        $emailVerificationEnabled = \get_option('okhub_jwt_enable_email_verification', true);
        if (!$emailVerificationEnabled) {
            return $this->errorResponse('Email verification is disabled', 403);
        }

        // Check if user exists
        $user = $this->userService->findByEmail($email);
        if (!$user) {
            return $this->errorResponse('Email không tồn tại trong hệ thống', 404);
        }

        // Check if user is already verified
        if ($this->userService->isEmailVerified($user->ID)) {
            return $this->errorResponse('Tài khoản đã được xác thực', 400);
        }

        // Check if user is a Google user (Google users don't need OTP verification)
        $isGoogleUser = \get_user_meta($user->ID, 'is_google_user', true);
        if ($isGoogleUser) {
            return $this->errorResponse('Tài khoản Google không cần xác thực OTP', 400);
        }

        // Generate new OTP
        $otpResult = $this->otpService->generateOtp($email, 'registration', $user->ID);
        if (!$otpResult['success']) {
            return $this->errorResponse('Không thể tạo mã OTP mới', 500);
        }

        // Send OTP email
        $emailSent = $this->emailService->sendOtpEmail($email, $otpResult['otp'], 'registration');
        if (!$emailSent) {
            return $this->errorResponse('Không thể gửi email xác thực', 500);
        }

        return [
            'success' => true,
            'message' => 'Mã OTP mới đã được gửi đến email của bạn'
        ];
    }

    /**
     * Generate username from email
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
     * User logout
     */
    public function logout($token)
    {
        $decoded = $this->tokenService->validateAccessToken($token);
        if ($decoded) {
            $userId = $decoded['user_id'];
            $sessionId = $decoded['session_id'] ?? null;

            // Always blacklist current access token
            $this->tokenService->blacklistToken($token, $userId);

            if ($sessionId && $this->sessionService) {
                // Logout specific session/device using session_id from token
                $this->revokeRefreshTokenFromSession($sessionId, $userId);

                return [
                    'success' => true,
                    'message' => 'Đăng xuất thiết bị thành công'
                ];
            } else {
                // Logout current token only (no session_id)
                // Clean up any orphaned sessions and tokens
                $this->cleanupOrphanedData($userId);

                return [
                    'success' => true,
                    'message' => 'Đăng xuất thành công'
                ];
            }
        }

        return [
            'success' => true,
            'message' => 'Đăng xuất thành công'
        ];
    }

    /**
     * Get user active sessions
     */
    public function getUserSessions($token)
    {
        if (!$this->sessionService) {
            return $this->errorResponse('Session service not available', 500);
        }

        $decoded = $this->tokenService->validateAccessToken($token);
        if (!$decoded) {
            return $this->errorResponse('Token không hợp lệ', 401);
        }

        $userId = $decoded['user_id'];
        $currentSessionId = $decoded['session_id'] ?? null;

        // Clean up expired and orphaned sessions first (but preserve current session)
        $expiredCount = $this->sessionService->cleanupExpiredSessionsForUser($userId);
        $orphanedCount = $this->sessionService->cleanupOrphanedSessionsForUser($userId, $currentSessionId);



        // Get all sessions (including inactive ones)
        $sessions = $this->sessionService->getAllUserSessions($userId);

        // Add current session indicator and status
        if ($sessions) {
            foreach ($sessions as &$session) {
                $session->is_current = ($session->session_id === $currentSessionId);
                $session->status = $session->is_active ? 'active' : 'inactive';
                $session->is_expired = (strtotime($session->expires_at) < strtotime(\current_time('mysql')));
            }
        }

        return [
            'success' => true,
            'data' => $sessions
        ];
    }

    /**
     * Force logout all devices
     */
    public function logoutAllDevices($token)
    {
        if (!$this->sessionService) {
            return $this->errorResponse('Session service not available', 500);
        }

        $decoded = $this->tokenService->validateAccessToken($token);
        if (!$decoded) {
            return $this->errorResponse('Token không hợp lệ', 401);
        }

        $userId = $decoded['user_id'];
        $sessionId = $decoded['session_id'] ?? null;

        // Revoke all tokens from all sessions first
        $this->revokeAllTokensFromSessions($userId);

        // Delete all sessions completely
        $this->sessionService->deleteAllUserSessions($userId);

        // Blacklist current token
        $this->tokenService->blacklistToken($token, $userId);

        return [
            'success' => true,
            'message' => 'Đã đăng xuất tất cả thiết bị'
        ];
    }

    /**
     * Revoke refresh token from session
     */
    private function revokeRefreshTokenFromSession($sessionId, $userId)
    {
        if (!$this->sessionService) {
            return false;
        }

        // Get session details
        $session = $this->sessionService->getSessionById($sessionId);
        if (!$session) {
            return false;
        }

        // Revoke both access and refresh tokens from session
        if ($session->access_token) {
            $this->tokenService->blacklistToken($session->access_token, $userId);
        }

        if ($session->refresh_token) {
            $this->tokenService->blacklistToken($session->refresh_token, $userId);
        }

        // Delete the session completely from database
        $this->sessionService->deleteSession($sessionId, $userId);

        return true;
    }

    /**
     * Revoke all tokens from all user sessions
     */
    private function revokeAllTokensFromSessions($userId)
    {
        if (!$this->sessionService) {
            return false;
        }

        // Get all active sessions for user
        $sessions = $this->sessionService->getUserSessions($userId);

        // Batch blacklist tokens for better performance
        $tokensToBlacklist = [];
        foreach ($sessions as $session) {
            if ($session->access_token) {
                $tokensToBlacklist[] = $session->access_token;
            }
            if ($session->refresh_token) {
                $tokensToBlacklist[] = $session->refresh_token;
            }
        }

        // Batch blacklist all tokens at once
        if (!empty($tokensToBlacklist)) {
            $this->tokenService->batchBlacklistTokens($tokensToBlacklist, $userId);
        }

        return true;
    }

    /**
     * Forgot password
     */
    public function forgotPassword($email)
    {
        // Find user by email
        $user = $this->userService->findByEmail($email);
        if (!$user) {
            // Don't return error to prevent email enumeration attack
            return [
                'success' => true,
                'message' => 'Nếu email tồn tại trong hệ thống, bạn sẽ nhận được link đặt lại mật khẩu'
            ];
        }

        // Create reset token
        $resetToken = $this->resetTokenService->createResetToken($user->ID);

        // Send email
        $emailSent = $this->emailService->sendResetPasswordEmail($email, $resetToken);

        if (!$emailSent) {
            return $this->errorResponse('Không thể gửi email. Vui lòng thử lại sau.');
        }

        return [
            'success' => true,
            'message' => 'Link đặt lại mật khẩu đã được gửi đến email của bạn'
        ];
    }

    /**
     * Forgot password with OTP
     */
    public function forgotPasswordWithOtp($email)
    {
        // Check if OTP reset is enabled
        if (!\get_option('okhub_jwt_enable_otp_reset', false)) {
            return $this->errorResponse('OTP password reset is disabled', 403);
        }

        // Find user by email
        $user = $this->userService->findByEmail($email);
        if (!$user) {
            // Don't return error to prevent email enumeration attack
            return [
                'success' => true,
                'message' => 'Nếu email tồn tại trong hệ thống, bạn sẽ nhận được mã OTP'
            ];
        }

        // Clean up expired OTPs first
        $this->otpService->cleanupExpiredOtps();

        // Check if user can request new OTP
        if (!$this->otpService->canRequestNewOtp($email)) {
            $remainingTime = $this->otpService->getOtpRemainingTime($email);
            return $this->errorResponse("Vui lòng đợi {$remainingTime} giây trước khi yêu cầu mã OTP mới", 429);
        }

        // Generate OTP
        $otpResult = $this->otpService->generateOtp($email, 'password_reset', $user->ID);

        if (!$otpResult['success']) {
            return $this->errorResponse('Không thể tạo mã OTP. Vui lòng thử lại sau.');
        }

        // Send OTP email
        $emailSent = $this->emailService->sendOtpEmail($email, $otpResult['otp'], 'password_reset');

        if (!$emailSent) {
            return $this->errorResponse('Không thể gửi email. Vui lòng thử lại sau.');
        }

        return [
            'success' => true,
            'message' => 'Mã OTP đã được gửi đến email của bạn',
            'data' => [
                'expires_in' => \get_option('okhub_jwt_otp_expire', 300),
                'max_attempts' => \get_option('okhub_jwt_otp_max_attempts', 3)
            ]
        ];
    }

    /**
     * Verify OTP and reset password
     */
    public function resetPasswordWithOtp($email, $otpCode, $newPassword)
    {
        // Check if OTP reset is enabled
        if (!\get_option('okhub_jwt_enable_otp_reset', false)) {
            return $this->errorResponse('OTP password reset is disabled', 403);
        }

        // Validate password
        if (strlen($newPassword) < 6) {
            return $this->errorResponse('Mật khẩu phải có ít nhất 6 ký tự', 422);
        }

        // Validate OTP
        $userId = $this->otpService->validateOtp($email, $otpCode);
        if (!$userId) {
            // Increment attempts
            $this->otpService->incrementOtpAttempts($email, $otpCode);
            return $this->errorResponse('Mã OTP không hợp lệ, đã hết hạn hoặc đã sử dụng', 400);
        }

        // Get user info
        $user = $this->userService->findById($userId);
        if (!$user) {
            return $this->errorResponse('Người dùng không tồn tại', 404);
        }

        // Update password
        $passwordResult = $this->userService->changePassword($userId, $newPassword);
        if (\is_wp_error($passwordResult)) {
            return $this->errorResponse('Không thể cập nhật mật khẩu', 500);
        }

        // Mark OTP as used
        $this->otpService->markOtpAsUsed($email, $otpCode);

        // Send password changed notification
        if (\get_option('okhub_jwt_enable_password_changed_email', true)) {
            $this->emailService->sendPasswordChangedEmail($email);
        }

        return [
            'success' => true,
            'message' => 'Mật khẩu đã được đặt lại thành công'
        ];
    }

    /**
     * Verify OTP only (without resetting password)
     */
    public function verifyOtp($email, $otpCode)
    {
        // Check if OTP reset is enabled
        if (!\get_option('okhub_jwt_enable_otp_reset', false)) {
            return $this->errorResponse('OTP password reset is disabled', 403);
        }

        // Validate OTP
        $userId = $this->otpService->validateOtp($email, $otpCode);
        if (!$userId) {
            // Increment attempts
            $this->otpService->incrementOtpAttempts($email, $otpCode);
            return $this->errorResponse('Mã OTP không hợp lệ, đã hết hạn hoặc đã sử dụng', 400);
        }

        // Don't mark OTP as used here - let it be used for password reset
        // Only mark as used when password is actually reset successfully

        return [
            'success' => true,
            'message' => 'Mã OTP hợp lệ',
            'data' => [
                'user_id' => $userId,
                'verified' => true
            ]
        ];
    }

    /**
     * Reset password with token
     */
    public function resetPassword($token, $newPassword)
    {
        // Validate password
        if (strlen($newPassword) < 6) {
            return $this->errorResponse('Mật khẩu phải có ít nhất 6 ký tự', 422);
        }

        // Validate token
        $userId = $this->resetTokenService->validateResetToken($token);
        if (!$userId) {
            return $this->errorResponse('Token không hợp lệ hoặc đã hết hạn', 400);
        }

        // Get user info
        $user = $this->userService->findById($userId);
        if (!$user) {
            return $this->errorResponse('User không tồn tại', 404);
        }

        // Update password
        $result = $this->userService->changePassword($userId, $newPassword);
        if (\is_wp_error($result)) {
            return $this->errorResponse('Không thể cập nhật mật khẩu');
        }

        // Mark token as used
        $this->resetTokenService->markTokenAsUsed($token);

        // Send password changed notification email
        $this->emailService->sendPasswordChangedEmail($user->user_email);

        return [
            'success' => true,
            'message' => 'Mật khẩu đã được đặt lại thành công'
        ];
    }

    /**
     * Validate reset token
     */
    public function validateResetToken($token)
    {
        $userId = $this->resetTokenService->validateResetToken($token);

        if (!$userId) {
            return $this->errorResponse('Token không hợp lệ hoặc đã hết hạn', 400);
        }

        $user = $this->userService->findById($userId);
        if (!$user) {
            return $this->errorResponse('User không tồn tại', 404);
        }

        return [
            'success' => true,
            'valid' => true,
            'user_email' => $user->user_email
        ];
    }

    /**
     * Change password for logged in user
     */
    public function changePassword($userId, $currentPassword, $newPassword)
    {
        // Validate new password
        if (strlen($newPassword) < 6) {
            return $this->errorResponse('Mật khẩu mới phải có ít nhất 6 ký tự', 422);
        }

        // Get user info
        $user = $this->userService->findById($userId);
        if (!$user) {
            return $this->errorResponse('User không tồn tại', 404);
        }

        // Verify current password
        if (!$this->userService->verifyPassword($userId, $currentPassword)) {
            return $this->errorResponse('Mật khẩu hiện tại không chính xác', 401);
        }

        // Check if new password is different from current
        if ($this->userService->verifyPassword($userId, $newPassword)) {
            return $this->errorResponse('Mật khẩu mới không được trùng với mật khẩu hiện tại', 422);
        }

        // Update password
        $result = $this->userService->changePassword($userId, $newPassword);
        if (\is_wp_error($result)) {
            return $this->errorResponse('Không thể cập nhật mật khẩu');
        }

        // Send password changed notification email
        $this->emailService->sendPasswordChangedEmail($user->user_email);

        return [
            'success' => true,
            'message' => 'Mật khẩu đã được thay đổi thành công'
        ];
    }

    /**
     * Update user profile
     */
    public function updateProfile($userId, $data)
    {
        // Get user info
        $user = $this->userService->findById($userId);
        if (!$user) {
            return $this->errorResponse('User không tồn tại', 404);
        }

        // Update profile
        $result = $this->userService->updateProfile($userId, $data);
        if (\is_wp_error($result)) {
            return $this->errorResponse('Không thể cập nhật thông tin user: ' . $result->get_error_message());
        }

        // Get updated user info
        $updatedUser = $this->userService->findById($userId);
        $userInfo = $this->userService->getPublicInfo($updatedUser);

        return [
            'success' => true,
            'user' => $userInfo,
            'message' => 'Thông tin đã được cập nhật thành công'
        ];
    }

    /**
     * Helper method for success response
     */
    private function successResponse($data)
    {
        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Helper method for error response
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
     */
    private function getErrorCode($status, $message = '')
    {
        switch ($status) {
            case 400:
                if (strpos($message, 'email') !== false && strpos($message, 'sử dụng') !== false) {
                    return 'email_exists';
                }
                if (strpos($message, 'mật khẩu') !== false && strpos($message, 'ít nhất') !== false) {
                    return 'password_too_short';
                }
                if (strpos($message, 'Token') !== false) {
                    return 'invalid_token';
                }
                return 'bad_request';
            case 401:
                if (strpos($message, 'mật khẩu') !== false && strpos($message, 'chính xác') !== false) {
                    return 'incorrect_password';
                }
                if (strpos($message, 'Refresh token') !== false) {
                    return 'invalid_refresh_token';
                }
                if (strpos($message, 'Token') !== false) {
                    return 'invalid_token';
                }
                return 'unauthorized';
            case 404:
                if (strpos($message, 'User') !== false) {
                    return 'user_not_found';
                }
                if (strpos($message, 'Email') !== false) {
                    return 'email_not_found';
                }
                return 'not_found';
            case 422:
                return 'validation_error';
            default:
                return 'error';
        }
    }

    /**
     * Format token response to match expected format
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
     * Clean up orphaned data to maintain database consistency
     */
    private function cleanupOrphanedData($userId)
    {
        if (!$this->sessionService) {
            return false;
        }

        // Get all sessions for user (including inactive ones)
        $sessions = $this->sessionService->getAllUserSessions($userId);

        if ($sessions) {
            foreach ($sessions as $session) {
                // Blacklist any remaining tokens
                if ($session->access_token) {
                    $this->tokenService->blacklistToken($session->access_token, $userId);
                }
                if ($session->refresh_token) {
                    $this->tokenService->blacklistToken($session->refresh_token, $userId);
                }
            }

            // Delete all sessions for this user
            $this->sessionService->deleteAllUserSessions($userId);
        }

        // Clear any remaining user meta
        \delete_user_meta($userId, '_jwt_tokens');

        return true;
    }
}
<?php

namespace OkhubJwtAuth\Services;

/**
 * OTP service for password reset
 */
class OtpService
{
    private $expireTime;
    private $maxAttempts;

    public function __construct()
    {
        $this->expireTime = \get_option('okhub_jwt_otp_expire', 300); // 5 minutes default
        // Ensure expireTime is at least 300 seconds (5 minutes)
        if ($this->expireTime < 300) {
            $this->expireTime = 300;
        }
        $this->maxAttempts = \get_option('okhub_jwt_otp_max_attempts', 3); // 3 attempts default
    }

    /**
     * Create OTP table if not exists
     */
    public function createTableIfNotExists()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'okhub_password_otps';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                email varchar(255) NOT NULL,
                otp_code varchar(6) NOT NULL,
                expires_at datetime NOT NULL,
                attempts int(3) DEFAULT 0,
                used tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY user_id (user_id),
                KEY email (email),
                KEY otp_code (otp_code),
                KEY expires_at (expires_at)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            \dbDelta($sql);
        }
    }

    /**
     * Generate OTP for user
     */
    public function generateOtp($email, $type = 'password_reset', $userId = 0)
    {
        global $wpdb;

        // Clean up expired OTPs first
        $this->cleanupExpiredOtps();

        // Generate 6-digit OTP
        $otpCode = $this->generateOtpCode();

        // Calculate expiration time (use same timezone as created_at)
        $currentTime = \current_time('mysql');
        $expireTime = \get_option('okhub_jwt_otp_expire', 300); // Get from admin settings, default 300 seconds
        // Calculate expires_at by adding expireTime to currentTime
        $expiresAt = date('Y-m-d H:i:s', strtotime($currentTime) + $expireTime);

        // For registration, use userId if provided, otherwise 0
        // Note: resendRegistrationOtp provides real userId, only initial registration might not have userId

        // Insert OTP into database
        $result = $wpdb->insert(
            $wpdb->prefix . 'okhub_password_otps',
            [
                'user_id' => $userId,
                'email' => $email,
                'otp_code' => $otpCode,
                'expires_at' => $expiresAt,
                'attempts' => 0
            ],
            ['%d', '%s', '%s', '%s', '%d']
        );

        if ($result === false) {
            return ['success' => false, 'message' => 'Không thể tạo mã OTP'];
        }

        return ['success' => true, 'otp' => $otpCode];
    }

    /**
     * Validate OTP
     */
    public function validateOtp($email, $otpCode)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_password_otps';
        $now = \current_time('mysql'); // Use same timezone as expires_at

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, expires_at, attempts, used FROM $table 
                 WHERE email = %s AND otp_code = %s 
                 ORDER BY created_at DESC LIMIT 1",
                $email,
                $otpCode
            )
        );

        if (!$result) {
            // Debug log for missing OTP
            error_log("OTP Debug - No OTP found for email: $email, code: $otpCode");
            return false;
        }

        // Debug OTP details
        error_log("OTP Debug - Found OTP: user_id={$result->user_id}, expires_at={$result->expires_at}, attempts={$result->attempts}, used={$result->used}");

        // Check if OTP is expired (compare UTC timestamps)
        $expiresTimestamp = strtotime($result->expires_at);
        $nowTimestamp = strtotime($now);

        // Debug log for time comparison
        error_log("OTP Debug - Email: $email, Expires: $result->expires_at ($expiresTimestamp), Now: $now ($nowTimestamp), Diff: " . ($expiresTimestamp - $nowTimestamp) . " seconds");

        if ($expiresTimestamp < $nowTimestamp) {
            return false;
        }

        // Check if OTP is already used
        if ($result->used) {
            return false;
        }

        // Check if max attempts exceeded
        if ($result->attempts >= $this->maxAttempts) {
            return false;
        }

        return $result->user_id;
    }

    /**
     * Increment OTP attempts
     */
    public function incrementOtpAttempts($email, $otpCode)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_password_otps';

        $result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE $table SET attempts = attempts + 1 
                 WHERE email = %s AND otp_code = %s AND used = 0",
                $email,
                $otpCode
            )
        );

        return $result !== false;
    }

    /**
     * Mark OTP as used
     */
    public function markOtpAsUsed($email, $otpCode)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_password_otps';

        $result = $wpdb->update(
            $table,
            ['used' => 1],
            [
                'email' => $email,
                'otp_code' => $otpCode
            ],
            ['%d'],
            ['%s', '%s']
        );

        return $result !== false;
    }

    /**
     * Generate 6-digit OTP code
     */
    private function generateOtpCode()
    {
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Clean up expired OTPs
     */
    public function cleanupExpiredOtps()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_password_otps';
        $now = \current_time('mysql'); // Use same timezone as expires_at

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE expires_at < %s",
                $now
            )
        );
    }

    /**
     * Get OTP info for user
     */
    public function getOtpInfo($email)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_password_otps';
        $now = \current_time('mysql');

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table 
                 WHERE email = %s AND expires_at > %s AND used = 0 
                 ORDER BY created_at DESC LIMIT 1",
                $email,
                $now
            )
        );
    }

    /**
     * Revoke all OTPs for a user
     */
    public function revokeUserOtps($userId)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_password_otps';

        $result = $wpdb->update(
            $table,
            ['used' => 1],
            ['user_id' => $userId],
            ['%d'],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get remaining time for OTP
     */
    public function getOtpRemainingTime($email)
    {
        $otpInfo = $this->getOtpInfo($email);

        if (!$otpInfo) {
            return 0;
        }

        // Return remaining time for new OTP request (60 seconds from last request)
        $lastRequestTime = strtotime($otpInfo->created_at);
        $currentTime = \current_time('timestamp'); // Use WordPress timezone
        $timeDiff = $currentTime - $lastRequestTime;

        return max(0, 60 - $timeDiff);
    }

    /**
     * Check if user can request new OTP
     */
    public function canRequestNewOtp($email)
    {
        // Clean up expired OTPs first
        $this->cleanupExpiredOtps();

        $otpInfo = $this->getOtpInfo($email);

        if (!$otpInfo) {
            return true;
        }

        // Check if max attempts reached
        if ($otpInfo->attempts >= $this->maxAttempts) {
            return false;
        }

        // Allow new OTP request after 60 seconds from last request
        $lastRequestTime = strtotime($otpInfo->created_at);
        $currentTime = \current_time('timestamp'); // Use WordPress timezone
        $timeDiff = $currentTime - $lastRequestTime;



        if ($timeDiff >= 60) { // 60 seconds
            return true;
        }

        return false;
    }
}

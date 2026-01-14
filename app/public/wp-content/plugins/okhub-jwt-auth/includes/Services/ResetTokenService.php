<?php

namespace OkhubJwtAuth\Services;

/**
 * Password reset token service
 */
class ResetTokenService
{
    private $expireTime;

    public function __construct()
    {
        $this->expireTime = \get_option('okhub_jwt_password_reset_expire', 3600); // 1 hour default
    }

    /**
     * Create password reset table if not exists
     */
    public function createTableIfNotExists()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'okhub_password_resets';

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();

            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                token varchar(255) NOT NULL,
                expires_at datetime NOT NULL,
                used tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY token (token),
                KEY user_id (user_id),
                KEY expires_at (expires_at)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            \dbDelta($sql);
        }
    }

    /**
     * Create reset token for user
     */
    public function createResetToken($userId)
    {
        global $wpdb;

        // Clean up expired tokens first
        $this->cleanupExpiredTokens();

        // Generate unique token
        $token = $this->generateUniqueToken();

        // Calculate expiration time in WordPress timezone
        $expiresAt = \current_time('mysql', false, time() + $this->expireTime);

        // Insert token into database
        $result = $wpdb->insert(
            $wpdb->prefix . 'okhub_password_resets',
            [
                'user_id' => $userId,
                'token' => $token,
                'expires_at' => $expiresAt
            ],
            ['%d', '%s', '%s']
        );

        if ($result === false) {
            return false;
        }

        return $token;
    }

    /**
     * Validate reset token
     */
    public function validateResetToken($token)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_password_resets';
        $now = \current_time('mysql');

        $result = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT user_id, expires_at, used FROM $table WHERE token = %s",
                $token
            )
        );

        if (!$result) {
            return false;
        }

        // Check if token is expired (compare WordPress timezone with current WordPress time)
        if (strtotime($result->expires_at) < strtotime(\current_time('mysql'))) {
            return false;
        }

        // Check if token is already used
        if ($result->used) {
            return false;
        }

        return $result->user_id;
    }

    /**
     * Mark token as used
     */
    public function markTokenAsUsed($token)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_password_resets';

        $result = $wpdb->update(
            $table,
            ['used' => 1],
            ['token' => $token],
            ['%d'],
            ['%s']
        );

        return $result !== false;
    }

    /**
     * Generate unique token
     */
    private function generateUniqueToken()
    {
        global $wpdb;

        do {
            $token = \wp_generate_password(32, false);

            // Check if token already exists
            $exists = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}okhub_password_resets WHERE token = %s",
                    $token
                )
            );
        } while ($exists);

        return $token;
    }

    /**
     * Clean up expired tokens
     */
    public function cleanupExpiredTokens()
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_password_resets';
        $now = \current_time('mysql');

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM $table WHERE expires_at < %s",
                $now
            )
        );
    }

    /**
     * Get all reset tokens for a user
     */
    public function getUserResetTokens($userId)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_password_resets';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE user_id = %d ORDER BY created_at DESC",
                $userId
            )
        );
    }

    /**
     * Revoke all reset tokens for a user
     */
    public function revokeUserTokens($userId)
    {
        global $wpdb;

        $table = $wpdb->prefix . 'okhub_password_resets';

        $result = $wpdb->update(
            $table,
            ['used' => 1],
            ['user_id' => $userId],
            ['%d'],
            ['%d']
        );

        return $result !== false;
    }
}

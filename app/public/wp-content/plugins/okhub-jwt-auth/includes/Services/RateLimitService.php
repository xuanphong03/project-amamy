<?php

namespace OkhubJwtAuth\Services;

/**
 * Rate Limiting Service for Security
 */
class RateLimitService
{
    private $table;
    private $maxAttempts;
    private $windowSeconds;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'okhub_rate_limits';
        $this->maxAttempts = \get_option('okhub_jwt_rate_limit_attempts', 5);
        $this->windowSeconds = \get_option('okhub_jwt_rate_limit_window', 300); // 5 minutes

        $this->createTableIfNotExists();
    }

    /**
     * Create rate limiting table if not exists
     */
    private function createTableIfNotExists()
    {
        global $wpdb;

        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table}'") == $this->table;
        if (!$table_exists) {
            $charset_collate = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$this->table} (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                identifier varchar(255) NOT NULL,
                action varchar(50) NOT NULL,
                attempts int(11) DEFAULT 1,
                first_attempt datetime DEFAULT CURRENT_TIMESTAMP,
                last_attempt datetime DEFAULT CURRENT_TIMESTAMP,
                blocked_until datetime NULL,
                PRIMARY KEY (id),
                UNIQUE KEY identifier_action (identifier, action),
                KEY last_attempt (last_attempt),
                KEY blocked_until (blocked_until)
            ) $charset_collate;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * Check if action is allowed for identifier
     */
    public function isAllowed($identifier, $action)
    {
        global $wpdb;

        // Clean up old records
        $this->cleanupOldRecords();

        // Check if currently blocked
        $blocked = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE identifier = %s AND action = %s AND blocked_until > NOW()",
                $identifier,
                $action
            )
        );

        if ($blocked) {
            return false;
        }

        // Get current attempt record
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE identifier = %s AND action = %s",
                $identifier,
                $action
            )
        );

        $now = \current_time('mysql');
        $windowStart = date('Y-m-d H:i:s', strtotime($now) - $this->windowSeconds);

        if (!$record) {
            // First attempt
            $wpdb->insert(
                $this->table,
                [
                    'identifier' => $identifier,
                    'action' => $action,
                    'attempts' => 1,
                    'first_attempt' => $now,
                    'last_attempt' => $now
                ],
                ['%s', '%s', '%d', '%s', '%s']
            );
            return true;
        }

        // Check if within window
        if (strtotime($record->first_attempt) < strtotime($windowStart)) {
            // Reset window
            $wpdb->update(
                $this->table,
                [
                    'attempts' => 1,
                    'first_attempt' => $now,
                    'last_attempt' => $now,
                    'blocked_until' => null
                ],
                ['id' => $record->id],
                ['%d', '%s', '%s', '%s'],
                ['%d']
            );
            return true;
        }

        // Check if max attempts exceeded
        if ($record->attempts >= $this->maxAttempts) {
            // Block for window duration
            $blockedUntil = date('Y-m-d H:i:s', strtotime($now) + $this->windowSeconds);
            $wpdb->update(
                $this->table,
                [
                    'attempts' => $record->attempts + 1,
                    'last_attempt' => $now,
                    'blocked_until' => $blockedUntil
                ],
                ['id' => $record->id],
                ['%d', '%s', '%s'],
                ['%d']
            );
            return false;
        }

        // Increment attempts
        $wpdb->update(
            $this->table,
            [
                'attempts' => $record->attempts + 1,
                'last_attempt' => $now
            ],
            ['id' => $record->id],
            ['%d', '%s'],
            ['%d']
        );

        return true;
    }

    /**
     * Get remaining attempts for identifier
     */
    public function getRemainingAttempts($identifier, $action)
    {
        global $wpdb;

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE identifier = %s AND action = %s",
                $identifier,
                $action
            )
        );

        if (!$record) {
            return $this->maxAttempts;
        }

        $now = \current_time('mysql');
        $windowStart = date('Y-m-d H:i:s', strtotime($now) - $this->windowSeconds);

        // Check if within window
        if (strtotime($record->first_attempt) < strtotime($windowStart)) {
            return $this->maxAttempts;
        }

        return max(0, $this->maxAttempts - $record->attempts);
    }

    /**
     * Get block time remaining for identifier
     */
    public function getBlockTimeRemaining($identifier, $action)
    {
        global $wpdb;

        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT blocked_until FROM {$this->table} WHERE identifier = %s AND action = %s AND blocked_until > NOW()",
                $identifier,
                $action
            )
        );

        if (!$record) {
            return 0;
        }

        return max(0, strtotime($record->blocked_until) - time());
    }

    /**
     * Clean up old records
     */
    private function cleanupOldRecords()
    {
        global $wpdb;

        $cutoff = date('Y-m-d H:i:s', time() - ($this->windowSeconds * 2));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table} WHERE last_attempt < %s",
                $cutoff
            )
        );
    }

    /**
     * Reset attempts for identifier
     */
    public function resetAttempts($identifier, $action)
    {
        global $wpdb;

        return $wpdb->delete(
            $this->table,
            [
                'identifier' => $identifier,
                'action' => $action
            ],
            ['%s', '%s']
        );
    }
}

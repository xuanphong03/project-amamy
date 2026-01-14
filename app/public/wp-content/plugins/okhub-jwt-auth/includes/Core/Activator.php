<?php

namespace OkhubJwtAuth\Core;

/**
 * Plugin activation handler
 */
class Activator
{
    /**
     * Run activation tasks
     */
    public static function activate()
    {
        // Create database tables
        self::createTables();

        // Set default options
        self::setDefaultOptions();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create necessary database tables
     */
    private static function createTables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Password reset tokens table
        $table_name = $wpdb->prefix . 'okhub_password_resets';
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
        dbDelta($sql);

        // JWT blacklist table (for logout functionality)
        $blacklist_table = $wpdb->prefix . 'okhub_jwt_blacklist';
        $blacklist_sql = "CREATE TABLE $blacklist_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            token_hash varchar(255) NOT NULL,
            user_id bigint(20) NOT NULL,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY user_id (user_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";

        dbDelta($blacklist_sql);

        // JWT sessions table (for multi-device support)
        $sessions_table = $wpdb->prefix . 'okhub_jwt_sessions';
        $sessions_sql = "CREATE TABLE $sessions_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            session_id varchar(64) NOT NULL,
            device_info text,
            access_token_hash varchar(255),
            refresh_token_hash varchar(255),
            access_token text,
            refresh_token text,
            expires_at datetime NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            last_used datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY expires_at (expires_at),
            KEY is_active (is_active)
        ) $charset_collate;";

        dbDelta($sessions_sql);
    }

    /**
     * Set default plugin options
     */
    private static function setDefaultOptions()
    {
        $defaults = [
            'jwt_secret' => wp_generate_password(64, false),
            'jwt_expire' => 3600, // 1 hour
            'jwt_refresh_expire' => 604800, // 7 days
            'enable_refresh_tokens' => true,
            'enable_password_reset' => true,
            'password_reset_expire' => 3600, // 1 hour
        ];

        foreach ($defaults as $key => $value) {
            if (get_option('okhub_jwt_' . $key) === false) {
                update_option('okhub_jwt_' . $key, $value);
            }
        }
    }
}

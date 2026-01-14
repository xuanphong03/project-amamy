<?php

namespace OkhubJwtAuth\Core;

/**
 * Plugin uninstall handler
 */
class Uninstaller
{
    /**
     * Run uninstall tasks
     */
    public static function uninstall()
    {
        // Check if user has permission
        if (!current_user_can('activate_plugins')) {
            return;
        }

        // Remove database tables
        self::removeTables();
        
        // Remove plugin options
        self::removeOptions();
        
        // Remove user meta
        self::removeUserMeta();
    }

    /**
     * Remove database tables
     */
    private static function removeTables()
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'okhub_password_resets',
            $wpdb->prefix . 'okhub_jwt_blacklist'
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
    }

    /**
     * Remove plugin options
     */
    private static function removeOptions()
    {
        $options = [
            'okhub_jwt_secret',
            'okhub_jwt_expire',
            'okhub_jwt_refresh_expire',
            'okhub_jwt_enable_refresh_tokens',
            'okhub_jwt_enable_password_reset',
            'okhub_jwt_password_reset_expire'
        ];

        foreach ($options as $option) {
            delete_option($option);
        }
    }

    /**
     * Remove user meta data
     */
    private static function removeUserMeta()
    {
        global $wpdb;
        
        $wpdb->delete(
            $wpdb->usermeta,
            ['meta_key' => '_jwt_tokens'],
            ['%s']
        );
    }
}

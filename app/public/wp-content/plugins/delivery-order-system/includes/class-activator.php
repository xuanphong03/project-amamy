<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin activation
 */
class Delivery_Order_System_Activator
{
    /**
     * Activate the plugin
     */
    public static function activate()
    {
        // Set default options
        self::set_default_options();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options()
    {
        $default_options = array(
            'delivery_order_system_version' => DELIVERY_ORDER_SYSTEM_VERSION,
            'delivery_order_system_settings' => array(
                'enable_notifications' => true,
                'default_status' => 'pending',
            ),
        );

        foreach ($default_options as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}

<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Fired during plugin deactivation
 */
class Delivery_Order_System_Deactivator
{
    /**
     * Deactivate the plugin
     */
    public static function deactivate()
    {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Clear scheduled events if any
        wp_clear_scheduled_hook('delivery_order_system_daily_task');
    }
}


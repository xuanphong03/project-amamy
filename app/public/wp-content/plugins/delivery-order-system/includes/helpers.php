<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Helper functions for the plugin
 */

/**
 * Get plugin option
 *
 * @param string $option_name Option name
 * @param mixed  $default     Default value if option doesn't exist
 * @return mixed
 */
function delivery_order_system_get_option($option_name, $default = false)
{
    return get_option('delivery_order_system_' . $option_name, $default);
}

/**
 * Update plugin option
 *
 * @param string $option_name Option name
 * @param mixed  $value      Option value
 * @return bool
 */
function delivery_order_system_update_option($option_name, $value)
{
    return update_option('delivery_order_system_' . $option_name, $value);
}


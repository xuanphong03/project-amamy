<?php

/**
 * Plugin Name: Okhub JWT Authentication
 * Plugin URI: https://okhub.tech
 * Description: JWT Authentication plugin for WordPress with advanced features
 * Version: 1.0.0
 * Author: Okhub Team
 * Author URI: https://okhub.tech
 * License: GPL v2 or later
 * Text Domain: okhub-jwt-auth
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('OKHUB_JWT_AUTH_VERSION', '1.0.0');
define('OKHUB_JWT_AUTH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OKHUB_JWT_AUTH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('OKHUB_JWT_AUTH_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Load Composer autoloader
if (file_exists(OKHUB_JWT_AUTH_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once OKHUB_JWT_AUTH_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Fallback autoloader if Composer is not available
    spl_autoload_register(function ($class) {
        $prefix = 'OkhubJwtAuth\\';
        $base_dir = OKHUB_JWT_AUTH_PLUGIN_DIR . 'includes/';

        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }

        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

        if (file_exists($file)) {
            require $file;
        }
    });
}

// Initialize plugin
function okhub_jwt_auth_init()
{
	// Load AuthMiddleware class
    require_once OKHUB_JWT_AUTH_PLUGIN_DIR . 'includes/AuthMiddleware.php';
	
    // Check if required classes exist
    if (!class_exists('OkhubJwtAuth\\Core\\Plugin')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>Okhub JWT Auth Plugin: Required classes not found. Please check plugin installation.</p></div>';
        });
        return;
    }

    // Load text domain
    load_plugin_textdomain('okhub-jwt-auth', false, dirname(OKHUB_JWT_AUTH_PLUGIN_BASENAME) . '/languages');

    try {
        // Initialize main plugin class
        new OkhubJwtAuth\Core\Plugin();
    } catch (Exception $e) {
        add_action('admin_notices', function () use ($e) {
            echo '<div class="notice notice-error"><p>Okhub JWT Auth Plugin Error: ' . esc_html($e->getMessage()) . '</p></div>';
        });
    }
}
add_action('plugins_loaded', 'okhub_jwt_auth_init');

// Activation hook
register_activation_hook(__FILE__, 'okhub_jwt_auth_activate');

// Deactivation hook
register_deactivation_hook(__FILE__, 'okhub_jwt_auth_deactivate');

// Uninstall hook
register_uninstall_hook(__FILE__, 'okhub_jwt_auth_uninstall');

/**
 * Plugin activation function
 */
function okhub_jwt_auth_activate() {
    try {
        // Create necessary database tables
        if (class_exists('OkhubJwtAuth\\Core\\Activator')) {
            OkhubJwtAuth\Core\Activator::activate();
        }
    } catch (Exception $e) {
        wp_die('Plugin activation failed: ' . $e->getMessage());
    }
}

/**
 * Plugin deactivation function
 */
function okhub_jwt_auth_deactivate() {
    try {
        // Cleanup if needed
        if (class_exists('OkhubJwtAuth\\Core\\Deactivator')) {
            OkhubJwtAuth\Core\Deactivator::deactivate();
        }
    } catch (Exception $e) {
        // Log error but don't stop deactivation
        error_log('Okhub JWT Auth deactivation error: ' . $e->getMessage());
    }
}

/**
 * Plugin uninstall function
 */
function okhub_jwt_auth_uninstall() {
    try {
        // Remove all plugin data
        if (class_exists('OkhubJwtAuth\\Core\\Uninstaller')) {
            OkhubJwtAuth\Core\Uninstaller::uninstall();
        }
    } catch (Exception $e) {
        // Log error but don't stop uninstall
        error_log('Okhub JWT Auth uninstall error: ' . $e->getMessage());
    }
}

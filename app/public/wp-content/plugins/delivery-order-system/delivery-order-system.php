<?php

/**
 * Plugin Name: Delivery Order System
 * Description: Quản lý vận chuyển đơn hàng
 * Version: 1.0.1
 * Author: LÊ MINH TÂM
 * Author URI: https://www.facebook.com/kokorolee
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: delivery-order-system
 */

if (! defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('DELIVERY_ORDER_SYSTEM_VERSION', '1.0.0');
define('DELIVERY_ORDER_SYSTEM_PATH', plugin_dir_path(__FILE__));
define('DELIVERY_ORDER_SYSTEM_URL', plugin_dir_url(__FILE__));
define('DELIVERY_ORDER_SYSTEM_BASENAME', plugin_basename(__FILE__));

// Load configuration constants first
require_once DELIVERY_ORDER_SYSTEM_PATH . 'config/constants.php';

// Load Composer autoloader for PSR-4
if (file_exists(DELIVERY_ORDER_SYSTEM_PATH . 'vendor/autoload.php')) {
    require_once DELIVERY_ORDER_SYSTEM_PATH . 'vendor/autoload.php';
}

// Include required files
require_once DELIVERY_ORDER_SYSTEM_PATH . 'includes/class-activator.php';
require_once DELIVERY_ORDER_SYSTEM_PATH . 'includes/class-deactivator.php';
require_once DELIVERY_ORDER_SYSTEM_PATH . 'includes/class-plugin.php';

/**
 * Register activation and deactivation hooks
 */
register_activation_hook(__FILE__, array('Delivery_Order_System_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Delivery_Order_System_Deactivator', 'deactivate'));

/**
 * Main function to run the plugin
 */
function run_delivery_order_system()
{
    $plugin = new Delivery_Order_System_Plugin();
    $plugin->run();
}
run_delivery_order_system();

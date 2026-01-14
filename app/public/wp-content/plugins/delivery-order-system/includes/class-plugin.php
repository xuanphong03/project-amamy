<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Main plugin class
 */
class Delivery_Order_System_Plugin
{
    /**
     * The loader that's responsible for maintaining and registering all hooks
     *
     * @var Delivery_Order_System_Plugin_Loader
     */
    protected $loader;

    /**
     * The unique identifier of this plugin
     *
     * @var string
     */
    protected $plugin_name;

    /**
     * The current version of the plugin
     *
     * @var string
     */
    protected $version;

    /**
     * Define the core functionality of the plugin
     */
    public function __construct()
    {
        $this->plugin_name = 'delivery-order-system';
        $this->version = DELIVERY_ORDER_SYSTEM_VERSION;

        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin
     */
    private function load_dependencies()
    {
        require_once DELIVERY_ORDER_SYSTEM_PATH . 'includes/class-loader.php';
        require_once DELIVERY_ORDER_SYSTEM_PATH . 'includes/helpers.php';
        require_once DELIVERY_ORDER_SYSTEM_PATH . 'includes/class-pdf-bill.php';
        require_once DELIVERY_ORDER_SYSTEM_PATH . 'includes/class-send-mail.php';
        require_once DELIVERY_ORDER_SYSTEM_PATH . 'includes/class-delivery-post-type.php';
        // Payment classes are autoloaded via PSR-4
        require_once DELIVERY_ORDER_SYSTEM_PATH . 'admin/class-admin.php';
        require_once DELIVERY_ORDER_SYSTEM_PATH . 'public/class-public.php';

        $this->loader = new Delivery_Order_System_Plugin_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     */
    private function define_admin_hooks()
    {
        $admin = new Delivery_Order_System_Plugin_Admin($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $admin, 'enqueue_scripts');
        $this->loader->add_action('admin_menu', $admin, 'add_admin_menu');
        $this->loader->add_action('wp_ajax_preview_delivery_bill', $admin, 'handle_preview_bill');
        $this->loader->add_action('wp_ajax_delivery_order_system_cleanup_pdfs', 'Delivery_Order_System_PDF_Bill', 'cleanup_old_pdfs');

        // Cleanup old PDF files weekly
        $this->loader->add_action('wp_scheduled_delete', 'Delivery_Order_System_PDF_Bill', 'cleanup_old_pdfs');

        // Initialize Delivery Post Type handler
        new Delivery_Order_System_Delivery_Post_Type();
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     */
    private function define_public_hooks()
    {
        $public = new Delivery_Order_System_Plugin_Public($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $public, 'enqueue_scripts');

        // Register IPN REST API endpoint
        add_action('rest_api_init', array('DeliveryOrderSystem\Payment\IPN_Handler', 'register_rest_route'));
    }

    /**
     * Run the loader to execute all of the hooks with WordPress
     */
    public function run()
    {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of WordPress
     *
     * @return string The name of the plugin
     */
    public function get_plugin_name()
    {
        return $this->plugin_name;
    }

    /**
     * Retrieve the version number of the plugin
     *
     * @return string The version number of the plugin
     */
    public function get_version()
    {
        return $this->version;
    }
}

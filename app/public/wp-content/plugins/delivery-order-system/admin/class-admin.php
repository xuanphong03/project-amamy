<?php

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The admin-specific functionality of the plugin
 */
class Delivery_Order_System_Plugin_Admin
{
    /**
     * The ID of this plugin
     *
     * @var string
     */
    private $plugin_name;

    /**
     * The version of this plugin
     *
     * @var string
     */
    private $version;

    /**
     * Initialize the class and set its properties
     *
     * @param string $plugin_name The name of this plugin
     * @param string $version     The version of this plugin
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area
     */
    public function enqueue_styles()
    {
        wp_enqueue_style(
            $this->plugin_name,
            DELIVERY_ORDER_SYSTEM_URL . 'admin/css/admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Register the JavaScript for the admin area
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script(
            $this->plugin_name,
            DELIVERY_ORDER_SYSTEM_URL . 'admin/js/admin.js',
            array('jquery'),
            $this->version,
            false
        );

        wp_localize_script(
            $this->plugin_name,
            'deliveryOrderSystem',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('delivery_order_system_nonce'),
            )
        );
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        // Add submenu to delivery post type menu
        add_submenu_page(
            'edit.php?post_type=delivery',
            __('Delivery Settings', 'delivery-order-system'),
            __('Settings', 'delivery-order-system'),
            'manage_options',
            'delivery-order-system-settings',
            array($this, 'display_settings_page')
        );
    }


    /**
     * Display orders page
     */
    public function display_orders_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }
?>
        <div class="wrap">
            <h1><?php _e('All Delivery Orders', 'delivery-order-system'); ?></h1>
            <p><?php _e('Orders management page. This will be implemented based on your requirements.', 'delivery-order-system'); ?>
            </p>
        </div>
<?php
    }

    /**
     * Display settings page
     */
    public function display_settings_page()
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Handle cleanup action
        if (isset($_POST['cleanup_pdfs']) && check_admin_referer('cleanup_pdfs_action')) {
            if (!class_exists('Delivery_Order_System_PDF_Bill')) {
                require_once DELIVERY_ORDER_SYSTEM_PATH . 'includes/class-pdf-bill.php';
            }

            $deleted_count = Delivery_Order_System_PDF_Bill::cleanup_old_pdfs(0); // Delete all old files
            $message = sprintf(__('Cleaned up %d old PDF files.', 'delivery-order-system'), $deleted_count);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }
?>
        <div class="wrap">
            <h1><?php _e('Delivery Order System Settings', 'delivery-order-system'); ?></h1>

            <div class="card">
                <h2><?php _e('PDF File Management', 'delivery-order-system'); ?></h2>
                <p><?php _e('Manage generated PDF files for delivery bills.', 'delivery-order-system'); ?></p>

                <h3><?php _e('Storage Information', 'delivery-order-system'); ?></h3>
                <ul>
                    <li><strong><?php _e('Storage Directory:', 'delivery-order-system'); ?></strong> <code>/wp-content/uploads/delivery-bills/</code></li>
                    <li><strong><?php _e('Storage Type:', 'delivery-order-system'); ?></strong> <?php _e('Temporary (files older than 7 days are automatically deleted)', 'delivery-order-system'); ?></li>
                    <li><strong><?php _e('Cleanup Schedule:', 'delivery-order-system'); ?></strong> <?php _e('Weekly (via WordPress scheduled tasks)', 'delivery-order-system'); ?></li>
                </ul>

                <h3><?php _e('Manual Cleanup', 'delivery-order-system'); ?></h3>
                <p><?php _e('Delete all PDF files older than 7 days immediately.', 'delivery-order-system'); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field('cleanup_pdfs_action'); ?>
                    <p>
                        <input type="submit" name="cleanup_pdfs" class="button button-primary"
                               value="<?php esc_attr_e('Clean Up Old PDF Files', 'delivery-order-system'); ?>"
                               onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete old PDF files?', 'delivery-order-system'); ?>');">
                    </p>
                </form>
            </div>
        </div>
<?php
    }

    /**
     * Handle PDF preview request via AJAX/Direct
     */
    public function handle_preview_bill()
    {
        // Check permissions
        if (! current_user_can('edit_posts')) {
            wp_die(__('Permission denied.', 'delivery-order-system'));
        }

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        $ma_van_don_id = isset($_GET['ma_van_don_id']) ? absint($_GET['ma_van_don_id']) : 0;

        if (! $post_id || ! $ma_van_don_id) {
            wp_die(__('Missing parameters.', 'delivery-order-system'));
        }

        // We can just include the preview file here
        $preview_file = DELIVERY_ORDER_SYSTEM_PATH . 'preview/preview-bill.php';
        if (file_exists($preview_file)) {
            // Define a constant to prevent double loading of wp-load.php if needed
            if (!defined('PREVIEW_MODE_AJAX')) {
                define('PREVIEW_MODE_AJAX', true);
            }
            include $preview_file;
            exit;
        } else {
            wp_die(__('Preview file not found.', 'delivery-order-system'));
        }
    }
}

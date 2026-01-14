<?php defined( 'ABSPATH' ) || exit;
/*
  Plugin Name: WP Sheet Editor - Post Types (Premium)
  Description: Bulk edit posts and pages easily using a beautiful spreadsheet inside WordPress.
  Version: 2.25.17
  Update URI: https://api.freemius.com
  Author: WP Sheet Editor
  Author URI: https://wpsheeteditor.com/?utm_source=wp-admin&utm_medium=plugins-list&utm_campaign=posts
  Plugin URI: https://wpsheeteditor.com/extensions/posts-pages-post-types-spreadsheet/?utm_source=wp-admin&utm_medium=plugins-list&utm_campaign=posts
  License:     GPL2
  License URI: https://www.gnu.org/licenses/gpl-2.0.html
  WC requires at least: 4.0
  WC tested up to: 9.5
  Text Domain: vg_sheet_editor_posts
  Domain Path: /lang
  @fs_premium_only /modules/user-path/send-user-path.php, /modules/acf/, /modules/advanced-filters/, /modules/columns-renaming/, /modules/custom-post-types/, /modules/formulas/, /modules/custom-columns/, /modules/spreadsheet-setup/, /modules/woocommerce/, /modules/universal-sheet/, /modules/yoast-seo/, /modules/wpml/, /modules/posts-templates/, /modules/columns-manager/,  /modules/wp-sheet-editor/inc/integrations/notifier.php,/modules/wp-sheet-editor/inc/integrations/extensions.json,  /whats-new/
  @fs_free_only /inc/custom-post-types.php
 */

if (isset($_GET['wpse_troubleshoot8987'])) {
	return;
}
if (!defined('ABSPATH')) {
	exit;
}
if (function_exists('vgse_freemius')) {
	vgse_freemius()->set_basename(true, __FILE__);
}


if (!defined('VGSE_MAIN_FILE')) {
	define('VGSE_MAIN_FILE', __FILE__);
}
if (!defined('VGSE_DIST_DIR')) {
	define('VGSE_DIST_DIR', __DIR__);
}
require_once 'inc/freemius-init.php';
if (!vgse_freemius()->can_use_premium_code__premium_only()) {
	$post_types_path = __DIR__ . '/inc/custom-post-types.php';
	if (file_exists($post_types_path)) {
		require_once $post_types_path;
	}
}

if (!class_exists('WP_Sheet_Editor_Dist')) {

	class WP_Sheet_Editor_Dist {

		static private $instance = false;
		var $modules_controller = null;
		public $textname = 'vg_sheet_editor_posts';
		var $sheets_bootstrap = null;

		private function __construct() {
			
		}

		/**
		 * Creates or returns an instance of this class.
		 */
		static function get_instance() {
			if (null == WP_Sheet_Editor_Dist::$instance) {
				WP_Sheet_Editor_Dist::$instance = new WP_Sheet_Editor_Dist();
				WP_Sheet_Editor_Dist::$instance->init();
			}
			return WP_Sheet_Editor_Dist::$instance;
		}

		function notify_wrong_core_version() {
			$plugin_data = get_plugin_data(__FILE__, false, false);
			?>
			<div class="notice notice-error">
				<p><?php _e('Please update the WP Sheet Editor plugin and all its extensions to the latest version. The features of the plugin "' . $plugin_data['Name'] . '" will be disabled temporarily because it is the newest version and it conflicts with old versions of other WP Sheet Editor plugins. The features will be enabled automatically after you install the updates.', WP_Sheet_Editor_Dist::get_instance()->textname); ?></p>
			</div>
			<?php
		}

		function init() {
			require_once __DIR__ . '/modules/init.php';
			$this->modules_controller = new WP_Sheet_Editor_CORE_Modules_Init(__DIR__, vgse_freemius());
			add_action('plugins_loaded', array($this, 'late_init'));
			// After core has initialized
			add_filter('vg_sheet_editor/after_init', array($this, 'after_core_init'));
			add_action('init', array($this, 'after_init'));
			
			add_action(
				'before_woocommerce_init',
				function() {
					if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
						$main_file  = __FILE__;
						$parent_dir = dirname( dirname( $main_file ) );
						$new_path   = str_replace( $parent_dir, '', $main_file );
						$new_path   = wp_normalize_path( ltrim( $new_path, '\\/' ) );
						\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $new_path, true );
					}
				}
			);
		}

		function after_init() {
			load_plugin_textdomain($this->textname, false, basename(dirname(__FILE__)) . '/lang/');
		}

		/**
		 * Redirect to welcome page after plugin activation
		 */
		function redirect_to_welcome_page() {
			if (is_multisite() && !is_super_admin()) {
				return;
			}
			// Bail if no activation redirect
			$flag_key = 'vgse_welcome_redirect';
			$flag = get_option($flag_key, '');

			if ($flag === 'no') {
				return;
			}
			update_option($flag_key, 'no');

			// Disable "whats new" redirect
			update_option('vgse_hide_whats_new_' . VGSE()->version, 'yes');

			// Bail if activating from network, or bulk			
			if (is_network_admin() || isset($_GET['activate-multi'])) {
				return;
			}
			if( ! empty($_GET['vg_sheet_editor_setup'])){
				return;
			}

			$welcome_url = esc_url(add_query_arg(array('page' => 'vg_sheet_editor_setup'), admin_url('admin.php')));
			wp_redirect($welcome_url);
			exit();
		}

		function after_core_init() {
			if (version_compare(VGSE()->version, '2.25.17') < 0) {
				add_action('admin_notices', array($this, 'notify_wrong_core_version'));
				return;
			}
			add_action('admin_init', array($this, 'redirect_to_welcome_page'));
			// Enable admin pages in case "frontend sheets" addon disabled them
			add_filter('vg_sheet_editor/register_admin_pages', '__return_true', 11);

			// We register early. So plugins for specific post types can overwrite the toolbar item.
			add_action('vg_sheet_editor/editor/before_init', array($this, 'register_toolbar_items'), 9);

			// Set up posts editor.
			// Allow to bootstrap editor manually, later.
			if (!apply_filters('vg_sheet_editor/bootstrap/manual_init', false)) {
				$this->sheets_bootstrap = new WP_Sheet_Editor_Bootstrap();
			}
			add_action('admin_init', array($this, 'disable_free_plugins_when_premium_active'), 1);
		}

		function register_toolbar_items($editor) {
			if (!$editor->provider->is_post_type) {
				return;
			}
			if (!WP_Sheet_Editor_Helpers::current_user_can('install_plugins')) {
				return;
			}
			$editor->args['toolbars']->register_item('wpse_license', array(
				'type' => 'button',
				'content' => __('My license', WP_Sheet_Editor_Dist::get_instance()->textname),
				'extra_html_attributes' => ' target="_blank" ',
				'url' => vgse_freemius()->get_account_url(),
				'toolbar_key' => 'secondary',
				'allow_in_frontend' => false,
				'fs_id' => vgse_freemius()->get_id()
					), $editor->args['provider']);
		}

		function disable_free_plugins_when_premium_active() {
			$free_plugins_path = array(
				'wp-sheet-editor-bulk-spreadsheet-editor-for-posts-and-pages/wp-sheet-editor.php',
				'wp-sheet-editor-woocommerce-inventory/woocommerce-inventory.php'
			);
			$premium_plugins_path = array(
				'wp-sheet-editor-bulk-spreadsheet-editor-for-posts-and-pages-premium/wp-sheet-editor.php',
				'wp-sheet-editor-premium/wp-sheet-editor.php',
			);
			$is_premium_active = false;

			foreach ($premium_plugins_path as $relative_path) {
				if (is_plugin_active($relative_path)) {
					$is_premium_active = true;
					break;
				}
			}
			if ($is_premium_active) {
				foreach ($free_plugins_path as $relative_path) {
					$path = wp_normalize_path(WP_PLUGIN_DIR . '/' . $relative_path);
					if (is_plugin_active($relative_path)) {
						deactivate_plugins(plugin_basename($path));
					}
				}
			}
		}

		function late_init() {
			if (function_exists('vgse_freemius')) {
				if (vgse_freemius()->can_use_premium_code__premium_only()) {
					add_filter('vg_sheet_editor/whats_new_page/items', array($this, 'add_whats_new_items__premium_only'));
				}
			}

			add_filter('vg_sheet_editor/allowed_post_types', array($this, 'enable_basic_post_types'));
		}

		function enable_basic_post_types($post_types) {
			if (!isset($post_types['post'])) {
				$post_types['post'] = 'Posts';
			}
			if (!isset($post_types['page'])) {
				$post_types['page'] = 'Page';
			}
			return $post_types;
		}

		function add_whats_new_items__premium_only($items) {
			$path = __DIR__ . '/whats-new/' . VGSE()->version . '.php';

			if (!file_exists($path)) {
				return $items;
			}
			include $path;

			return array_merge($items, $pro_items);
		}

		function __set($name, $value) {
			$this->$name = $value;
		}

		function __get($name) {
			return $this->$name;
		}

	}

}
WP_Sheet_Editor_Dist::get_instance();

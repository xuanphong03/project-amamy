<?php defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_Sheet_Editor_CPTs' ) ) {

	/**
	 * Use the spreadsheet editor to edit all the posts from any custom post type.
	 */
	class WP_Sheet_Editor_CPTs {

		private static $instance = false;

		private function __construct() {

		}

		/**
		 * Creates or returns an instance of this class.
		 */
		static function get_instance() {
			if ( null == self::$instance ) {
				self::$instance = new WP_Sheet_Editor_CPTs();
				self::$instance->init();
			}
			return self::$instance;
		}

		function init() {
			add_filter( 'vg_sheet_editor/allowed_post_types', array( $this, 'allow_all_post_types' ) );
			add_filter( 'vg_sheet_editor/api/blacklisted_post_types', array( $this, 'remove_post_type_from_exclusion_list' ) );
			add_filter( 'vg_sheet_editor/options_page/options', array( $this, 'add_settings_page_options' ) );
		}

		/**
		 * Add fields to options page
		 * @param array $sections
		 * @return array
		 */
		function add_settings_page_options( $sections ) {
			$sections['misc']['fields'][] = array(
				'id'    => 'cpts_whitelisted_private_post_types',
				'type'  => 'text',
				'title' => __( 'Whitelisted private post types', 'vg_sheet_editor' ),
				'desc'  => __( 'There are some post types in WordPress that are private, they don\'t appear in the wp-admin as post types and they\'re used for "system" purposes. For example, the navigation menus are a post type. We exclude those post types by default to not clutter your spreadsheets list with unnecessary options, but you can use enter the post type keys here separated with commas and we\'ll generate sheets for those post types.', 'vg_sheet_editor' ),
			);
			return $sections;
		}

		public function remove_post_type_from_exclusion_list( $excluded_post_type_keys ) {
			$whitelisted_post_types = VGSE()->get_option( 'cpts_whitelisted_private_post_types', '' );
			if ( ! empty( $whitelisted_post_types ) ) {
				$whitelisted_post_types = array_map( 'trim', explode( ',', $whitelisted_post_types ) );
				$excluded_post_type_keys = array_diff( $excluded_post_type_keys, $whitelisted_post_types );
			}
			return $excluded_post_type_keys;
		}

		/**
		 * Allow all custom post types
		 * @param array $allowed_post_types
		 * @return array
		 */
		function allow_all_post_types( $allowed_post_types ) {

			$current_post_types = isset( VGSE()->options['be_post_types'] ) ? VGSE()->options['be_post_types'] : array();

			if ( empty( $current_post_types ) || ! is_array( $current_post_types ) ) {
				$current_post_types = array();
			}

			$new_current_post_types = array();
			foreach ( $current_post_types as $current_post_type ) {
				$new_current_post_types[ $current_post_type ] = $current_post_type;
			}
			$all_post_types = apply_filters( 'vg_sheet_editor/custom_post_types/get_all_post_types', VGSE()->helpers->get_all_post_types() );
			// We used to exclude post types with own sheet here but we stopped
			// because the bundle already has the list of post types without own sheet
			$allowed = ( ! empty( VGSE()->bundles['custom_post_types'] ) ) ? VGSE()->bundles['custom_post_types']['post_types'] : array();
			foreach ( $all_post_types as $post_type ) {
				if ( ! in_array( $post_type->name, $allowed, true ) ) {
					continue;
				}
				$allowed_post_types[ $post_type->name ] = $post_type->label;
			}
			$allowed_post_types = wp_parse_args( $allowed_post_types, $new_current_post_types );

			return $allowed_post_types;
		}

		function __set( $name, $value ) {
			$this->$name = $value;
		}

		function __get( $name ) {
			return $this->$name;
		}

	}

	add_action( 'vg_sheet_editor/initialized', 'vgse_cpt_init' );

	function vgse_cpt_init() {
		WP_Sheet_Editor_CPTs::get_instance();
	}
}

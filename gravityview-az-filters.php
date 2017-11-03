<?php
/**
 * Plugin Name: GravityView A-Z Filters Extension
 * Plugin URI: https://gravityview.co/extensions/a-z-filter/
 * Description: Alphabetically filter your entries by letters of the alphabet.
 * Version: 1.0.8
 * Author: Katz Web Services, Inc.
 * Author URI: https://gravityview.co
 * Author Email: admin@gravityview.co
 * Requires at least: 3.8
 * Tested up to: 4.8.3
 * Text Domain: gravityview-az-filters
 * Domain Path: languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action( 'plugins_loaded', 'gv_extension_az_entry_filtering_load' );

/**
 * Wrapper function to make sure GravityView_Extension has loaded
 * @return void
 */
function gv_extension_az_entry_filtering_load() {

	if( !class_exists( 'GravityView_Extension' ) ) {

		if( class_exists('GravityView_Plugin') && is_callable(array('GravityView_Plugin', 'include_extension_framework')) ) {
			GravityView_Plugin::include_extension_framework();
		} else {
			// We prefer to use the one bundled with GravityView, but if it doesn't exist, go here.
			include_once plugin_dir_path( __FILE__ ) . 'lib/class-gravityview-extension.php';
		}
	}

	/**
	 * A-Z Entry Filter Widget Extension
	 *
	 * @extends GravityView_Extension
	 */
	class GravityView_A_Z_Entry_Filter_Extension extends GravityView_Extension {

		protected $_title = 'A-Z Filters';

<<<<<<< HEAD
		protected $_version = '1.0.8';
=======
		protected $_version = '1.0.7';

		protected $_item_id = 266;
>>>>>>> origin/develop

		protected $_text_domain = 'gravityview-az-filters';

		protected $_min_gravityview_version = '1.1.7';

		protected $_path = __FILE__;

		public function add_hooks() {

			// Load widget
			add_action( 'init', array( $this, 'register_az_entry_filter_widget' ) );

			// Print Styles
			add_action( 'wp_enqueue_scripts', array( $this, 'print_styles' ) );

			// Admin styles
			add_action( 'admin_enqueue_scripts', array( $this, 'print_scripts'));

			add_filter( 'gravityview_noconflict_scripts', array( $this, 'register_noconflict') );

			define( 'GRAVITYVIEW_AZ_FILTER_PATH', plugin_dir_path( __FILE__ ) );

			define( 'GRAVITYVIEW_AZ_FILTER_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
		}

		/**
		 * Load the widget files
		 * @return void
		 */
		public function register_az_entry_filter_widget() {

			// 1.7.5.1+
			if( is_callable( array( 'GravityView_Plugin', 'include_widget_class' ) ) ) {
				GravityView_Plugin::include_widget_class();
			}
			// 1.7.5
			else if( file_exists( GRAVITYVIEW_DIR . 'includes/widgets/register-gravityview-widgets.php' ) ) {
				include_once( GRAVITYVIEW_DIR . 'includes/widgets/register-gravityview-widgets.php' );
			}
			// Before 1.7.5
			else {
				include_once( GRAVITYVIEW_DIR . 'includes/default-widgets.php' );
			}

			// Load widget extension
			include_once( GRAVITYVIEW_AZ_FILTER_PATH . 'widget/gravityview-a-z-entry-filter-widget.php' );
		}

		/**
		 * Enable the script in no-conflict mode
		 *
		 * @param  array $scripts_or_styles array of scripts to be loaded
		 * @return array                    Modified array
		 */
		function register_noconflict( $scripts_or_styles ) {

			$scripts_or_styles[] = 'gravityview-az-filters';

			return $scripts_or_styles;
		}

		/**
		 * Output the script that dynamically loads the fields for the widget settings
		 * @param  string $hook $pagenow page name
		 * @return void
		 */
		function print_scripts( $hook ) {

			if( !gravityview_is_admin_page($hook, 'single') ) { return; }

			$script_debug = (defined('SCRIPT_DEBUG') && SCRIPT_DEBUG) ? '' : '.min';

			wp_enqueue_script( 'gravityview-az-filters', GRAVITYVIEW_AZ_FILTER_URL . '/assets/js/az-search-widget-admin'.$script_debug.'.js', array('jquery') );

			wp_localize_script( 'gravityview-az-filters', 'gvAZVar', array(
				'nonce' => wp_create_nonce( 'gravityview_ajaxviews')
			) );

		}

		/**
		 * Print CSS on front-end when widget is loaded
		 * @return void
		 */
		function print_styles() {

			// Need to filter the CSS to load only when required.
			wp_enqueue_style( 'gravityview_az_entry_filter', GRAVITYVIEW_AZ_FILTER_URL . '/assets/css/gravityview-az-filters.css' );
		}

	} // GravityView_A_Z_Entry_Filter_Extension

	new GravityView_A_Z_Entry_Filter_Extension;

}

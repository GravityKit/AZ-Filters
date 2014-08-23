<?php
/**
 * Plugin Name: GravityView A-Z Entry Filter
 * Plugin URI: https://gravityview.co
 * Description: Filters your entries via the alphabet.
 * Version: 1.0.0
 * Author: Sebastien Dumont
 * Author URI: http://www.sebastiendumont.com
 * Author Email: mailme@sebastiendumont.com
 * Requires at least: 3.8
 * Tested up to: 4.0 beta4
 * Text Domain: gravity-view-az-entry-filter
 * Domain Path: languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action( 'plugins_loaded', 'gv_extension_az_entry_filtering_load' );

/**
 * Wrapper function to make sure GravityView_Extension has loaded
 * @return void
 */
function gv_extension_az_entry_filtering_load() {

	// We prefer to use the one bundled with GravityView, but if it doesn't exist, go here.
	if( !class_exists( 'GravityView_Extension' ) ) {
		include_once plugin_dir_path( __FILE__ ) . 'lib/class-gravityview-extension.php';
	}

	/**
	 * A-Z Entry Filter Widget Extension
	 *
	 * @extends GravityView_Extension
	 */
	class GravityView_A_Z_Entry_Filter_Extension extends GravityView_Extension {

		protected $_title = 'A-Z Entry Filter';

		protected $_version = '1.0.0';

		protected $_text_domain = 'gravity-view-az-entry-filter';

		protected $_min_gravityview_version = '1.1.5';

		protected $_author = 'Sebastien Dumont';

		protected $_path = __FILE__;

		public function __construct() {
			// Load widget
			add_action( 'init', array( $this, 'register_az_entry_filter_widget' ) );
			// Print Styles
			add_action( 'wp_enqueue_scripts', array( $this, 'print_styles' ) );

			define( 'GV_AZ_Entry_Filter_Plugin_Dir_Path', plugin_dir_path( __FILE__ ) );
			define( 'GV_AZ_Entry_Filter_Plugin_URL', untrailingslashit( plugins_url( '/', __FILE__ ) ) );
		}

		public function register_az_entry_filter_widget() {
			// Load the widget class
			require_once( GRAVITYVIEW_DIR . 'includes/default-widgets.php');
			// Load widget extension
			include_once( GV_AZ_Entry_Filter_Plugin_Dir_Path . 'widget/gravityview-a-z-entry-filter-widget.php' );
		}

		/**
		* This prints a little bit of CSS to keep the filter 
		* clean on the front-end of the site.
		* Only called when GravityView is detected on the page.
		*/
		function print_styles() {
			// Need to filter the CSS to load only when required.

			wp_enqueue_style( 'gravityview_az_entry_filter_style', GV_AZ_Entry_Filter_Plugin_URL . '/assets/css/gravityview-az-entry-filter.css' );
		}

	} // GravityView_A_Z_Entry_Filter_Extension
	new GravityView_A_Z_Entry_Filter_Extension;

}
?>
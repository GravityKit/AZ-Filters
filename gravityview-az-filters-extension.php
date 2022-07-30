<?php
namespace GV;

/** If this file is called directly, abort. */
if ( ! defined( 'GRAVITYVIEW_DIR' ) ) {
	die();
}

/**
 * A-Z Entry Filter Widget Extension
 *
 * @extends \GV\Extension
 */
class A_Z_Entry_Filter_Extension extends Extension {

	protected $_min_gravityview_version = '2.0-dev';

	public function __construct() {

		$this->_title         = 'A-Z Filters';
		$this->_version       = GRAVITYVIEW_AZ_FILTER_VERSION;
		$this->_text_domain   = 'gravityview-az-filters';
		$this->_path          = __FILE__ ;
		$this->_item_id       = 266;
		$this->plugin_file    = GRAVITYVIEW_AZ_FILTER_FILE;
		$this->plugin_version = GRAVITYVIEW_AZ_FILTER_VERSION;

		parent::__construct();
	}

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
		// Load widget extension
		include_once( GRAVITYVIEW_AZ_FILTER_PATH . 'widget/gravityview-a-z-entry-filter-widget.php' );
	}

	/**
	 * Enable the script in no-conflict mode
	 *
	 * @param  array $scripts_or_styles array of scripts to be loaded
	 * @return array                    Modified array
	 */
	public function register_noconflict( $scripts_or_styles ) {
		$scripts_or_styles []= 'gravityview-az-filters';
		return $scripts_or_styles;
	}

	/**
	 * Output the script that dynamically loads the fields for the widget settings
	 * @param  string $hook $pagenow page name
	 * @return void
	 */
	public function print_scripts( $hook ) {
		if ( ! gravityview()->request->is_admin( $hook, 'single' ) ) {
			return;
		}

		$script_debug = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';

		wp_enqueue_script( 'gravityview-az-filters', GRAVITYVIEW_AZ_FILTER_URL . '/assets/js/az-search-widget-admin'.$script_debug.'.js', array( 'jquery' ) );

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

} // A_Z_Entry_Filter_Extension

new A_Z_Entry_Filter_Extension;

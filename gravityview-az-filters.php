<?php
/**
 * Plugin Name: GravityView - A-Z Filters Extension
 * Plugin URI: https://gravityview.co/extensions/a-z-filter/
 * Description: Filter your entries by letters of the alphabet.
 * Version: 1.2
 * Author: GravityView
 * Author URI: https://gravityview.co
 * Author Email: hello@gravityview.co
 * Requires at least: 3.8
 * Tested up to: 4.9.5
 * Text Domain: gravityview-az-filters
 * Domain Path: languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_action( 'plugins_loaded', 'gv_extension_az_entry_filtering_load' );

/**
 * A simple loader that works with old PHP versions.
 *
 * @return void
 */
function gv_extension_az_entry_filtering_load() {
	if ( ! class_exists( '\GV\Extension' ) ) {
		add_action( 'admin_notices', 'gv_extension_az_entry_filtering_noload' );
		return;
	}

	if ( ! class_exists( '\GV\A_Z_Entry_Filter_Extension' ) ) {
		require plugin_dir_path( __FILE__ ) . 'gravityview-az-filters-extension.php';

		new \GV\A_Z_Entry_Filter_Extension( __FILE__, '1.2' );
	}
}

/**
 * Outputs a loader warning notice.
 *
 * @return void
 */
function gv_extension_az_entry_filtering_noload() {
	echo esc_html( 'GravityView A-Z Filters Extension was not loaded. GravityView 2.0 core files not found!' );
}

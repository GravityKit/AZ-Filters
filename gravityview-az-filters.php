<?php
/**
 * Plugin Name:         GravityView - A-Z Filters Extension
 * Plugin URI:          https://www.gravitykit.com/extensions/a-z-filter/
 * Description:         Filter your entries by letters of the alphabet.
 * Version:             1.3.5
 * Author:              GravityKit
 * Author URI:          https://www.gravitykit.com
 * Text Domain:         gravityview-az-filters
 * License:             GPLv3 or later
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.en.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/** @since 1.2 */
define( 'GRAVITYVIEW_AZ_FILTER_VERSION', '1.3.5' );

/** @since 1.3.2 */
define( 'GRAVITYVIEW_AZ_FILTER_FILE', __FILE__ );

add_action( 'plugins_loaded', function () {
	if ( class_exists( 'GV\Extension' ) ) {
		return;
	}

	add_action( 'admin_notices', function () {
		$message = wpautop(
			strtr(
				esc_html_x( '[extension] requires [link][plugin][/link] to work. Please install and activate [plugin].', 'gravityview-az-filters' ),
				[
					'[extension]' => 'GravityView A-Z Filters Extension',
					'[plugin]'    => 'GravityView',
					'[link]'      => '<a href="https://www.gravitykit.com/products/gravityview/">',
					'[/link]'     => '</a>',
				]
			)
		);

		echo "<div class='error' style='padding: 1.25em 0 1.25em 1em;'>$message</div>";
	} );
} );

// Load the extension & register it with Foundation, which enables translations and other features.
add_action( 'gravityview/loaded', function () {
	if ( ! class_exists( 'GV\A_Z_Entry_Filter_Extension' ) ) {
		require __DIR__ . '/gravityview-az-filters-extension.php';
	}

	if ( ! class_exists( 'GravityKit\GravityView\Foundation\Core' ) ) {
		return;
	}

	GravityKit\GravityView\Foundation\Core::register( __FILE__ );
} );

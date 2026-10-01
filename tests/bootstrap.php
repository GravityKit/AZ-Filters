<?php

ob_start();

$gv_plugin_dir            = getenv( 'GV_PLUGIN_DIR' ) ?: '/tmp/gravityview';
$gv_plugin_test_bootstrap = $gv_plugin_dir . '/tests/bootstrap.php';

// Required for unit tests in WP 5.8+.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );
}

if ( ! file_exists( $gv_plugin_test_bootstrap ) ) {
	exit( "Couldn't find gravityview/tests/bootstrap.php\n" );
}

// GravityView's bootstrap loads GravityForms and GravityView (and fires gravityview/loaded).
require_once $gv_plugin_test_bootstrap;

// The plugin registers on gravityview/loaded, which already fired above, so bootstrap it by hand.
if ( ! defined( 'GRAVITYVIEW_AZ_FILTER_VERSION' ) ) {
	require_once dirname( __DIR__ ) . '/gravityview-az-filters.php';
}

if ( ! defined( 'GRAVITYVIEW_AZ_FILTER_PATH' ) ) {
	define( 'GRAVITYVIEW_AZ_FILTER_PATH', dirname( __DIR__ ) . '/' );
}

if ( ! class_exists( 'GV\A_Z_Entry_Filter_Extension' ) ) {
	require_once dirname( __DIR__ ) . '/gravityview-az-filters-extension.php';
}

// The extension normally includes this on `init`; load it directly so the widget
// constructor runs and registers its search hooks.
if ( ! class_exists( 'GV\Widget_A_Z_Entry_Filter' ) ) {
	require_once dirname( __DIR__ ) . '/widget/gravityview-a-z-entry-filter-widget.php';
}

ob_end_clean();

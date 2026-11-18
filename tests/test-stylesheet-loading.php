<?php

defined( 'DOING_GRAVITYVIEW_TESTS' ) || exit;

/**
 * The A-Z Filters stylesheet loads only on pages that show the letter links.
 *
 * @covers \GV\A_Z_Entry_Filter_Extension::print_styles
 * @covers \GV\Widget_A_Z_Entry_Filter::render_alphabet_letters
 */
class GV_AZ_Stylesheet_Loading_Test extends GV_UnitTestCase {

	private const HANDLE = 'gravityview_az_entry_filter';

	public function tearDown(): void {
		wp_dequeue_style( self::HANDLE );

		parent::tearDown();
	}

	public function test_page_without_letter_links_does_not_load_the_stylesheet() {
		do_action( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_style_is( self::HANDLE, 'registered' ) );
		$this->assertFalse( wp_style_is( self::HANDLE, 'enqueued' ) );
	}

	public function test_letter_links_load_the_stylesheet() {
		do_action( 'wp_enqueue_scripts' );

		$letters = ( new \GV\Widget_A_Z_Entry_Filter() )->render_alphabet_letters();

		$this->assertStringContainsString( 'gravityview-az-filter', $letters );
		$this->assertTrue( wp_style_is( self::HANDLE, 'enqueued' ) );
	}
}

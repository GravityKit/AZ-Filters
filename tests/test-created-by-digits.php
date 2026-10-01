<?php

defined( 'DOING_GRAVITYVIEW_TESTS' ) || exit;

/**
 * Created By (user) A-Z filtering matches entries by author display name. The 0-9
 * bucket must match authors whose display name starts with a digit, not authors
 * whose user ID starts with a digit.
 */
class GV_AZ_Created_By_Digits_Test extends GV_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		$_GET  = array();
		$_POST = array();
	}

	public function tearDown(): void {
		$_GET  = array();
		$_POST = array();

		parent::tearDown();
	}

	private function make_view( $form ) {
		global $post;

		$post = $this->factory->view->create_and_get( array(
			'form_id'     => $form['id'],
			'template_id' => 'table',
			'fields'      => array(
				'directory_table-columns' => array(
					wp_generate_password( 4, false ) => array( 'id' => 'id', 'label' => 'Entry ID' ),
				),
			),
			'widgets'     => array(
				'header_top' => array(
					array( 'id' => 'az_filter', 'filter_field' => 'created_by' ),
				),
			),
			'settings'    => array( 'page_size' => 25, 'show_only_approved' => false ),
		) );

		return \GV\View::from_post( $post );
	}

	public function test_zero_nine_bucket_created_by_matches_display_name_not_user_id() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		// One author whose display name starts with a digit, one whose does not.
		$digit_user  = $this->factory->user->create( array( 'display_name' => '3M Company' ) );
		$letter_user = $this->factory->user->create( array( 'display_name' => 'Alice Smith' ) );

		$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', 'created_by' => $digit_user, '16' => 'x' ) );
		$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', 'created_by' => $letter_user, '16' => 'y' ) );

		$view = $this->make_view( $form );

		$_GET['letter'] = '0-9';

		$entries = $view->get_entries( gravityview()->request );

		$this->assertEquals( 1, $entries->total(), 'Only the entry by the digit-display-name author should match the 0-9 bucket.' );

		$rows = $entries->all();
		$this->assertSame( (string) $digit_user, (string) $rows[0]['created_by'], 'The matched entry must be the one authored by "3M Company".' );
	}

	public function test_letter_bucket_created_by_still_matches_display_name() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		$alice = $this->factory->user->create( array( 'display_name' => 'Alice Smith' ) );
		$bob   = $this->factory->user->create( array( 'display_name' => 'Bob Jones' ) );

		$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', 'created_by' => $alice, '16' => 'x' ) );
		$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', 'created_by' => $bob, '16' => 'y' ) );

		$view = $this->make_view( $form );

		$_GET['letter'] = 'a';

		$entries = $view->get_entries( gravityview()->request );

		$this->assertEquals( 1, $entries->total(), 'Letter "a" should match only the author whose display name starts with A.' );

		$rows = $entries->all();
		$this->assertSame( (string) $alice, (string) $rows[0]['created_by'] );
	}
}

<?php

defined( 'DOING_GRAVITYVIEW_TESTS' ) || exit;

/**
 * Edge cases around the 0-9 bucket that the Created By fix did not take.
 *
 * The fix reshaped how the bucket picks its prefixes, so a normal form field must
 * still match on its own values (the created_by branch must not capture it), and a
 * Created By bucket that matches no author must return nothing rather than everything.
 *
 * @see https://linear.app/gravitykit/issue/GVAZ-9
 */
class GV_AZ_Digit_Bucket_Test extends GV_UnitTestCase {

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

	private function make_view( $form, $filter_field ) {
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
					array( 'id' => 'az_filter', 'filter_field' => $filter_field ),
				),
			),
			'settings'    => array( 'page_size' => 25, 'show_only_approved' => false ),
		) );

		return \GV\View::from_post( $post );
	}

	public function test_zero_nine_bucket_on_a_form_field_matches_field_values() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		foreach ( array( '3rd Street', '7th Avenue', 'Boston' ) as $city ) {
			$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', '16' => $city ) );
		}

		$view = $this->make_view( $form, '16' );

		$_GET['letter'] = '0-9';

		$entries = $view->get_entries( gravityview()->request );

		$this->assertEquals( 2, $entries->total(), 'The 0-9 bucket on a normal field must match the field values starting with a digit.' );

		$matched = array();

		foreach ( $entries->all() as $entry ) {
			$matched[] = $entry['16'];
		}

		sort( $matched );

		$this->assertSame( array( '3rd Street', '7th Avenue' ), $matched, 'The Created By branch must not capture a normal form field.' );
	}

	public function test_zero_nine_bucket_created_by_without_digit_display_names_matches_nothing() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		// Every author's display name starts with a letter, so the 0-9 bucket has no
		// author to match. User IDs are numeric, so a lookup against the ID column
		// instead of the display name would match all of these.
		$authors = array(
			$this->factory->user->create( array( 'display_name' => 'Alice Smith' ) ),
			$this->factory->user->create( array( 'display_name' => 'Bob Jones' ) ),
			$this->factory->user->create( array( 'display_name' => 'Carol White' ) ),
		);

		foreach ( $authors as $user_id ) {
			$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', 'created_by' => $user_id, '16' => 'x' ) );
		}

		$view = $this->make_view( $form, 'created_by' );

		$_GET['letter'] = '0-9';

		$entries = $view->get_entries( gravityview()->request );

		$this->assertEquals( 0, $entries->total(), 'A 0-9 bucket that matches no display name must return no entries, not every entry.' );
	}
}

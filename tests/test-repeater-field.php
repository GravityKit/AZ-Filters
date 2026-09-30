<?php

defined( 'DOING_GRAVITYVIEW_TESTS' ) || exit;

/**
 * Gravity Forms Repeater fields and the A-Z filter.
 *
 * A repeater stores nothing under its own field ID: every row value is its own
 * gf_entry_meta row keyed by the sub-field (or input) ID. So the repeater itself can
 * never be an A-Z field, while a text sub-field can: an entry matches a letter when
 * any of its rows starts with that letter.
 */
class GV_AZ_Repeater_Field_Test extends GV_UnitTestCase {

	const REPEATER    = 10;
	const COMPANY     = 11;
	const NAME        = 12;
	const NOTES       = 13;
	const NESTED      = 20;
	const CONTACT     = 21;
	const TOP_LEVEL   = 1;

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

	/**
	 * A form with a top-level text field and a repeater holding a text field, a Name
	 * field, a Paragraph field and a nested repeater with its own text field.
	 */
	private function make_form() {
		$form_id = GFAPI::add_form( array(
			'title'  => 'A-Z Repeater ' . wp_generate_password( 6, false ),
			'fields' => array(
				array( 'type' => 'text', 'id' => self::TOP_LEVEL, 'label' => 'Title' ),
				array(
					'type'   => 'repeater',
					'id'     => self::REPEATER,
					'label'  => 'Companies',
					'fields' => array(
						array( 'type' => 'text', 'id' => self::COMPANY, 'label' => 'Company' ),
						array(
							'type'   => 'name',
							'id'     => self::NAME,
							'label'  => 'Owner',
							'inputs' => array(
								array( 'id' => self::NAME . '.3', 'label' => 'First' ),
								array( 'id' => self::NAME . '.6', 'label' => 'Last' ),
							),
						),
						array( 'type' => 'textarea', 'id' => self::NOTES, 'label' => 'Notes' ),
						array(
							'type'   => 'repeater',
							'id'     => self::NESTED,
							'label'  => 'Contacts',
							'fields' => array(
								array( 'type' => 'text', 'id' => self::CONTACT, 'label' => 'Contact' ),
							),
						),
					),
				),
			),
		) );

		$this->assertIsInt( $form_id, 'The repeater form must be created.' );

		return GFAPI::get_form( $form_id );
	}

	/**
	 * Adds an entry; $rows is the hydrated repeater value.
	 */
	private function add_entry( $form, $title, array $rows ) {
		$entry = array(
			'form_id'       => $form['id'],
			'status'        => 'active',
			self::TOP_LEVEL => $title,
		);

		if ( $rows ) {
			$entry[ self::REPEATER ] = $rows;
		}

		$entry_id = GFAPI::add_entry( $entry );

		$this->assertIsInt( $entry_id, 'The repeater entry must be created.' );

		return $entry_id;
	}

	/**
	 * Four entries: two rows, a single row, an empty repeater, and two rows that both
	 * start with A (to prove the entry is not returned twice).
	 */
	private function seed_entries( $form ) {
		return array(
			'apple_banana' => $this->add_entry( $form, 'one', array(
				array(
					(string) self::COMPANY     => 'Apple',
					self::NAME . '.3'          => 'Mary',
					self::NAME . '.6'          => 'Jones',
					(string) self::NESTED      => array( array( (string) self::CONTACT => 'Quinn' ) ),
				),
				array(
					(string) self::COMPANY     => 'Banana',
					self::NAME . '.3'          => 'Ned',
					self::NAME . '.6'          => 'Kim',
				),
			) ),
			'cherry'       => $this->add_entry( $form, 'two', array(
				array(
					(string) self::COMPANY     => 'Cherry',
					self::NAME . '.3'          => 'Olga',
					(string) self::NESTED      => array(
						array( (string) self::CONTACT => 'Rita' ),
						array( (string) self::CONTACT => 'Quentin' ),
					),
				),
			) ),
			'empty'        => $this->add_entry( $form, 'three', array() ),
			'avocado'      => $this->add_entry( $form, 'four', array(
				array( (string) self::COMPANY => 'Avocado' ),
				array( (string) self::COMPANY => 'apricot' ),
			) ),
		);
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
					array( 'id' => 'az_filter', 'filter_field' => (string) $filter_field ),
				),
			),
			'settings'    => array( 'page_size' => 25, 'show_only_approved' => false ),
		) );

		return \GV\View::from_post( $post );
	}

	private function entry_ids_for_letter( $view, $letter ) {
		$_GET['letter'] = $letter;

		$ids = array();

		foreach ( $view->get_entries( gravityview()->request )->all() as $entry ) {
			$ids[] = (int) $entry['id'];
		}

		sort( $ids );

		return $ids;
	}

	private function widget() {
		return new \GV\Widget_A_Z_Entry_Filter();
	}

	public function test_widget_field_list_excludes_the_repeater_itself() {
		$form = $this->make_form();

		$choices = $this->widget()->get_filter_fields( $form['id'] );

		$this->assertArrayNotHasKey( (string) self::REPEATER, $choices, 'A repeater has no value of its own and must not be offered as the A-Z field.' );
		$this->assertArrayNotHasKey( (string) self::NESTED, $choices, 'A nested repeater must not be offered either.' );
	}

	public function test_widget_field_list_offers_text_sub_fields_and_skips_blocked_sub_field_types() {
		$form = $this->make_form();

		$choices = $this->widget()->get_filter_fields( $form['id'] );

		$this->assertArrayHasKey( (string) self::TOP_LEVEL, $choices );
		$this->assertArrayHasKey( (string) self::COMPANY, $choices, 'A text field inside a repeater can be an A-Z field.' );
		$this->assertArrayHasKey( self::NAME . '.3', $choices, 'Inputs of a multi-input field inside a repeater can be A-Z fields.' );
		$this->assertArrayHasKey( (string) self::CONTACT, $choices, 'A text field inside a nested repeater can be an A-Z field.' );
		$this->assertArrayNotHasKey( (string) self::NOTES, $choices, 'The Paragraph block applies inside a repeater too.' );
	}

	public function test_ajax_field_list_for_the_widget_excludes_the_repeater() {
		$form = $this->make_form();

		// The widget's admin script asks GravityView for the sortable fields and flags the request.
		$_POST['gv_az_filter_fields'] = '1';

		$html = gravityview_get_sortable_fields( $form['id'] );

		$this->assertStringNotContainsString( 'value="' . self::REPEATER . '"', $html, 'The A-Z field dropdown must not list the repeater.' );
		$this->assertStringNotContainsString( 'value="' . self::NESTED . '"', $html, 'The A-Z field dropdown must not list a nested repeater.' );
		$this->assertStringContainsString( 'value="' . self::COMPANY . '"', $html, 'The A-Z field dropdown keeps text sub-fields.' );
	}

	public function test_sortable_field_list_outside_the_widget_is_unchanged() {
		$form = $this->make_form();

		$without_flag = gravityview_get_sortable_fields( $form['id'] );

		$this->assertStringContainsString( 'value="' . self::REPEATER . '"', $without_flag, 'GravityView\'s own sort dropdown is not the A-Z filter\'s to change.' );
	}

	public function test_text_sub_field_matches_entries_where_any_row_starts_with_the_letter() {
		$form    = $this->make_form();
		$entries = $this->seed_entries( $form );
		$view    = $this->make_view( $form, self::COMPANY );

		$this->assertSame(
			array( $entries['apple_banana'], $entries['avocado'] ),
			$this->entry_ids_for_letter( $view, 'a' ),
			'Letter A matches "Apple" (row 1 of 2) and "Avocado"/"apricot", once each.'
		);

		$this->assertSame( array( $entries['apple_banana'] ), $this->entry_ids_for_letter( $view, 'b' ), 'Letter B matches the second row of the first entry.' );
		$this->assertSame( array( $entries['cherry'] ), $this->entry_ids_for_letter( $view, 'c' ) );
		$this->assertSame( array(), $this->entry_ids_for_letter( $view, 'z' ), 'No row starts with Z, and the empty repeater never matches.' );
	}

	public function test_multi_input_sub_field_matches_by_row() {
		$form    = $this->make_form();
		$entries = $this->seed_entries( $form );
		$view    = $this->make_view( $form, self::NAME . '.3' );

		$this->assertSame( array( $entries['apple_banana'] ), $this->entry_ids_for_letter( $view, 'n' ), 'First name "Ned" is in row 2 of the first entry.' );
		$this->assertSame( array( $entries['cherry'] ), $this->entry_ids_for_letter( $view, 'o' ) );
	}

	public function test_nested_repeater_sub_field_matches() {
		$form    = $this->make_form();
		$entries = $this->seed_entries( $form );
		$view    = $this->make_view( $form, self::CONTACT );

		$this->assertSame(
			array( $entries['apple_banana'], $entries['cherry'] ),
			$this->entry_ids_for_letter( $view, 'q' ),
			'"Quinn" and "Quentin" live in nested repeater rows.'
		);
		$this->assertSame( array( $entries['cherry'] ), $this->entry_ids_for_letter( $view, 'r' ) );
	}

	public function test_widget_saved_with_a_repeater_does_not_hide_every_entry() {
		$form    = $this->make_form();
		$entries = $this->seed_entries( $form );
		$view    = $this->make_view( $form, self::REPEATER );

		$all = array_values( $entries );
		sort( $all );

		$this->assertSame( $all, $this->entry_ids_for_letter( $view, 'a' ), 'A repeater cannot be filtered by letter, so the widget must not filter instead of returning nothing.' );
	}

	public function test_letter_counts_as_a_search_only_for_a_filterable_field() {
		$form = $this->make_form();

		$repeater_view = $this->make_view( $form, self::REPEATER );
		$sub_view      = $this->make_view( $form, self::COMPANY );

		$widget = $this->widget();

		$this->assertSame( array(), $widget->register_search_argument( array(), array( 'letter' => 'a' ), $repeater_view ), 'A widget set to a repeater does not filter, so "Hide entries until search" must stay hidden.' );
		$this->assertArrayHasKey( 'letter', $widget->register_search_argument( array(), array( 'letter' => 'a' ), $sub_view ), 'A widget set to a repeater sub-field does filter.' );
	}
}

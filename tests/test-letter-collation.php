<?php

defined( 'DOING_GRAVITYVIEW_TESTS' ) || exit;

/**
 * The letter conditions are lowercased (and optionally collated) without touching any
 * other condition in the query: a Search Bar search on the same View keeps its own
 * matching, the rewrite does not stack across multiple Views on one page, and the
 * Created By display-name lookup matches names the same way field values match.
 */
class GV_AZ_Letter_Collation_Test extends GV_UnitTestCase {

	/**
	 * @var callable|null Hook added during a test, removed in tearDown.
	 */
	private $query_action;

	/**
	 * @var callable|null Spy on the generated SQL, removed in tearDown.
	 */
	private $sql_spy;

	/**
	 * @var callable|null Spy on wpdb queries, removed in tearDown.
	 */
	private $wpdb_spy;

	public function setUp(): void {
		parent::setUp();

		$_GET  = array();
		$_POST = array();
	}

	public function tearDown(): void {
		$_GET  = array();
		$_POST = array();

		remove_all_filters( 'gravityview/az_filter/collation' );

		if ( $this->query_action ) {
			remove_action( 'gravityview/view/query', $this->query_action, 20 );
			$this->query_action = null;
		}

		if ( $this->sql_spy ) {
			remove_filter( 'gform_gf_query_sql', $this->sql_spy, 100 );
			$this->sql_spy = null;
		}

		if ( $this->wpdb_spy ) {
			remove_filter( 'query', $this->wpdb_spy );
			$this->wpdb_spy = null;
		}

		parent::tearDown();
	}

	private function make_view( $form, $filter_field = '16' ) {
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

	/**
	 * Captures every generated WHERE clause, normalized so LIKE wildcards read as literal %.
	 */
	private function spy_on_sql( array &$captured ) {
		$this->sql_spy = function ( $sql ) use ( &$captured ) {
			global $wpdb;

			$captured[] = $wpdb->remove_placeholder_escape( $sql['where'] );

			return $sql;
		};

		add_filter( 'gform_gf_query_sql', $this->sql_spy, 100 );
	}

	public function test_letter_matching_is_lowercased_and_case_insensitive() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		foreach ( array( 'Boston', 'Austin', 'boise' ) as $city ) {
			$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', '16' => $city ) );
		}

		$view = $this->make_view( $form );

		$_GET['letter'] = 'b';

		$captured = array();
		$this->spy_on_sql( $captured );

		$entries = $view->get_entries( gravityview()->request );

		$this->assertEquals( 2, $entries->total(), '"Boston" and "boise" must match the letter "b"; "Austin" must not.' );

		$this->assertNotEmpty( $captured );
		$where = end( $captured );

		$this->assertStringContainsString( 'LOWER( `m', $where, 'The letter comparison must lowercase its own column.' );
		$this->assertStringContainsString( "LIKE 'b%'", $where );
		$this->assertStringNotContainsString( 'COLLATE', $where, 'No COLLATE without the collation override.' );
	}

	public function test_collation_and_lowercasing_do_not_bleed_into_other_conditions() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		// Matches both the letter "b" and a search for "Foo".
		$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', '16' => 'Boston', '4' => 'Foo@example.com' ) );
		// Matches the search but not the letter.
		$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', '16' => 'Austin', '4' => 'Foo@example.com' ) );

		$view = $this->make_view( $form );

		add_filter( 'gravityview/az_filter/collation', static function () {
			return 'utf8mb4_bin';
		} );

		// A second component (like the Search Bar) narrowing the same query on another field.
		$this->query_action = function ( &$query ) {
			$query_parts = $query->_introspect();

			$search = new \GF_Query_Condition(
				new \GF_Query_Column( '4' ),
				\GF_Query_Condition::LIKE,
				new \GF_Query_Literal( '%Foo%' )
			);

			$query->where( \GF_Query_Condition::_and( $query_parts['where'], $search ) );
		};
		add_action( 'gravityview/view/query', $this->query_action, 20 );

		$_GET['letter'] = 'b';

		$captured = array();
		$this->spy_on_sql( $captured );

		$entries = $view->get_entries( gravityview()->request );

		// If the rewrite bled into the search condition, the forced binary collation would
		// stop '%Foo%' from matching the lowercased column and return zero entries.
		$this->assertEquals( 1, $entries->total(), 'The search on another field must keep its own matching.' );

		$this->assertNotEmpty( $captured );
		$where = end( $captured );

		$this->assertStringContainsString( "COLLATE utf8mb4_bin LIKE 'b%'", $where, 'The letter comparison must be collated.' );
		$this->assertStringContainsString( "LIKE '%Foo%'", $where );
		$this->assertStringNotContainsString( "COLLATE utf8mb4_bin LIKE '%Foo%'", $where, 'The search comparison must not be collated.' );
	}

	public function test_rewrite_does_not_stack_across_multiple_view_queries() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', '16' => 'Boston' ) );
		$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', '16' => 'Austin' ) );

		add_filter( 'gravityview/az_filter/collation', static function () {
			return 'utf8mb4_bin';
		} );

		$_GET['letter'] = 'b';

		$captured = array();
		$this->spy_on_sql( $captured );

		// Two Views on the same request, like a page with two shortcodes.
		$first  = $this->make_view( $form )->get_entries( gravityview()->request );
		$second = $this->make_view( $form )->get_entries( gravityview()->request );

		$this->assertEquals( 1, $first->total() );
		$this->assertEquals( 1, $second->total() );

		$this->assertCount( 2, $captured );

		foreach ( $captured as $where ) {
			$this->assertSame( 1, substr_count( $where, 'COLLATE utf8mb4_bin' ), 'Each query must be rewritten exactly once.' );
			$this->assertStringNotContainsString( 'LOWER( LOWER', $where, 'The rewrite must not nest on later queries.' );
		}
	}

	public function test_rewrite_ignores_queries_it_did_not_condition() {
		$widget = new \GV\Widget_A_Z_Entry_Filter();

		add_filter( 'gravityview/az_filter/collation', static function () {
			return 'utf8mb4_bin';
		} );

		$sql = array( 'where' => "`m1`.`meta_value` LIKE 'b%'" );

		// No letter in the request.
		$this->assertSame( $sql, $widget->collate_letter_conditions( $sql ) );

		// A letter is set, but this widget queued no comparisons for the query.
		$_GET['letter'] = 'b';
		$this->assertSame( $sql, $widget->collate_letter_conditions( $sql ) );
	}

	public function test_created_by_lookup_lowercases_and_collates_display_name() {
		add_filter( 'gravityview/az_filter/collation', static function () {
			return 'utf8mb4_bin';
		} );

		$captured = array();

		$this->wpdb_spy = static function ( $q ) use ( &$captured ) {
			if ( false !== stripos( $q, 'display_name' ) ) {
				global $wpdb;

				$captured[] = $wpdb->remove_placeholder_escape( $q );
			}

			return $q;
		};
		add_filter( 'query', $this->wpdb_spy );

		$widget = new \GV\Widget_A_Z_Entry_Filter();
		$widget->get_user_ids_by_first_letter( array( 'b' ) );

		$this->assertNotEmpty( $captured );
		$this->assertStringContainsString(
			"LOWER( display_name ) COLLATE utf8mb4_bin LIKE 'b%'",
			end( $captured ),
			'The display-name lookup must lowercase and collate like the letter conditions do.'
		);
	}

	public function test_created_by_filtering_respects_collation_override() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		// A capitalized name the lowercased comparison must still match, and an accented
		// name the binary collation must exclude.
		$alice    = $this->factory->user->create( array( 'display_name' => 'Alice Smith' ) );
		$accented = $this->factory->user->create( array( 'display_name' => 'Ástríður Jónsdóttir' ) );
		$bob      = $this->factory->user->create( array( 'display_name' => 'Bob Jones' ) );

		foreach ( array( $alice, $accented, $bob ) as $user_id ) {
			$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', 'created_by' => $user_id, '16' => 'x' ) );
		}

		$view = $this->make_view( $form, 'created_by' );

		add_filter( 'gravityview/az_filter/collation', static function () {
			return 'utf8mb4_bin';
		} );

		$_GET['letter'] = 'a';

		$entries = $view->get_entries( gravityview()->request );

		$this->assertEquals( 1, $entries->total(), 'With a binary collation, "a" must match "Alice Smith" but not "Ástríður".' );

		$rows = $entries->all();
		$this->assertSame( (string) $alice, (string) $rows[0]['created_by'] );
	}
}

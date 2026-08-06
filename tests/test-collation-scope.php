<?php

defined( 'DOING_GRAVITYVIEW_TESTS' ) || exit;

/**
 * The collation rewrite is registered once and consumed per query, so building a View
 * query must leave the `gform_gf_query_sql` registry exactly as it found it, and the
 * same View rendered twice on one page must not wrap its comparison twice.
 *
 * @see https://linear.app/gravitykit/issue/GVAZ-11
 */
class GV_AZ_Collation_Scope_Test extends GV_UnitTestCase {

	/**
	 * @var callable|null Spy on the generated SQL, removed in tearDown.
	 */
	private $sql_spy;

	public function setUp(): void {
		parent::setUp();

		$_GET  = array();
		$_POST = array();
	}

	public function tearDown(): void {
		$_GET  = array();
		$_POST = array();

		remove_all_filters( 'gravityview/az_filter/collation' );

		if ( $this->sql_spy ) {
			remove_filter( 'gform_gf_query_sql', $this->sql_spy, 100 );
			$this->sql_spy = null;
		}

		parent::tearDown();
	}

	private function make_view_post( $form ) {
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
					array( 'id' => 'az_filter', 'filter_field' => '16' ),
				),
			),
			'settings'    => array( 'page_size' => 25, 'show_only_approved' => false ),
		) );

		return $post;
	}

	/**
	 * Counts every callback registered on a hook, across all priorities.
	 */
	private function count_hook_callbacks( $hook ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return 0;
		}

		$total = 0;

		foreach ( $wp_filter[ $hook ]->callbacks as $priority_callbacks ) {
			$total += count( $priority_callbacks );
		}

		return $total;
	}

	private function spy_on_sql( array &$captured ) {
		$this->sql_spy = function ( $sql ) use ( &$captured ) {
			global $wpdb;

			$captured[] = $wpdb->remove_placeholder_escape( $sql['where'] );

			return $sql;
		};

		add_filter( 'gform_gf_query_sql', $this->sql_spy, 100 );
	}

	public function test_query_building_leaves_the_sql_filter_registry_at_its_baseline() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', '16' => 'Boston' ) );

		$_GET['letter'] = 'b';

		$baseline = $this->count_hook_callbacks( 'gform_gf_query_sql' );

		\GV\View::from_post( $this->make_view_post( $form ) )->get_entries( gravityview()->request );

		$this->assertSame(
			$baseline,
			$this->count_hook_callbacks( 'gform_gf_query_sql' ),
			'Building a View query must not leave an extra gform_gf_query_sql callback behind.'
		);

		// A second View on the same request must not accumulate either.
		\GV\View::from_post( $this->make_view_post( $form ) )->get_entries( gravityview()->request );

		$this->assertSame(
			$baseline,
			$this->count_hook_callbacks( 'gform_gf_query_sql' ),
			'The rewrite must be registered once, not once per View query.'
		);
	}

	/**
	 * Finds the widget instance that is actually registered on the rewrite hook, so the
	 * assertion runs against the object that conditioned the query rather than a fresh
	 * one with an empty queue.
	 */
	private function get_registered_widget() {
		global $wp_filter;

		foreach ( $wp_filter['gform_gf_query_sql']->callbacks as $priority_callbacks ) {
			foreach ( $priority_callbacks as $callback ) {
				$function = $callback['function'];

				if ( is_array( $function ) && $function[0] instanceof \GV\Widget_A_Z_Entry_Filter ) {
					return $function[0];
				}
			}
		}

		return null;
	}

	public function test_spent_letter_expressions_do_not_rewrite_a_later_query() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', '16' => 'Boston' ) );
		$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', '16' => 'Austin' ) );

		$_GET['letter'] = 'b';

		$captured = array();
		$this->spy_on_sql( $captured );

		$entries = \GV\View::from_post( $this->make_view_post( $form ) )->get_entries( gravityview()->request );

		$this->assertEquals( 1, $entries->total() );
		$this->assertCount( 1, $captured );
		$this->assertSame( 1, substr_count( $captured[0], 'LOWER( `m' ), 'The conditioned query is lowercased exactly once.' );

		$widget = $this->get_registered_widget();

		$this->assertInstanceOf( \GV\Widget_A_Z_Entry_Filter::class, $widget, 'The widget must be registered on the rewrite hook.' );

		// A later render in the same request reaches the rewrite with the first query's
		// comparisons already spent, so its SQL must pass through untouched. Feeding it
		// SQL that is already lowercased is what a second, stacked rewrite would see.
		$already_rewritten = array( 'where' => "LOWER( `m1`.`meta_value` ) LIKE 'b%'" );

		$this->assertSame(
			$already_rewritten,
			$widget->collate_letter_conditions( $already_rewritten ),
			'A query the widget did not condition must not be rewritten again.'
		);
	}
}

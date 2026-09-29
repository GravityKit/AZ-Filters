<?php

use GV\Search\Querying\Search_Request;

defined( 'DOING_GRAVITYVIEW_TESTS' ) || exit;

/**
 * The A-Z Entry Filter widget filters entries by first letter through the
 * `letter` GET parameter. GravityView core's search-request detection only
 * recognizes the Search Bar keys, so it does not treat `?letter=` as a search.
 *
 * The visible consequence: a View set to "Hide entries until search" stays
 * hidden when a visitor clicks a letter, because the renderer gates entry
 * loading on gravityview()->request->is_search() (which is Search_Request
 * under the hood). The widget must register its parameter with core so an
 * active A-Z filter counts as a search.
 */
class GV_AZ_Search_Request_Test extends GV_UnitTestCase {

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
	 * Resolves the widget's letter parameter the way the widget does.
	 */
	private function az_parameter(): string {
		$parameter = apply_filters( 'gravityview_az_filter_parameter', 'letter' );
		$parameter = apply_filters( 'gravityview/az_filter/parameter', $parameter );

		return ! empty( $parameter ) ? (string) $parameter : 'letter';
	}

	public function test_az_letter_is_recognized_as_a_search_request() {
		$parameter = $this->az_parameter();

		$this->assertNull(
			Search_Request::from_arguments( array() ),
			'Baseline: a request with no arguments is not a search.'
		);

		$request = Search_Request::from_arguments( array( $parameter => 'B' ) );

		$this->assertNotNull(
			$request,
			sprintf( 'An active A-Z filter (?%s=B) must be recognized as a search request.', $parameter )
		);
	}

	/**
	 * The letter parameter is registered only so the request counts as a search; it is
	 * not a form field, so it must not survive into the built filters (the widget applies
	 * its own GF_Query condition). Otherwise core would try to filter a "letter" field.
	 */
	public function test_az_letter_does_not_become_a_field_filter() {
		$parameter = $this->az_parameter();

		$captured = null;
		add_filter(
			'gk/gravityview/search/request/filters',
			static function ( $filters ) use ( &$captured ) {
				$captured = $filters;

				return $filters;
			},
			99 // After the widget's remove_letter_filter() at priority 10.
		);

		$request = Search_Request::from_arguments( array( $parameter => 'B' ) );
		$this->assertNotNull( $request );

		// Building the filters fires gk/gravityview/search/request/filters.
		$request->to_filter();

		$this->assertIsArray( $captured, 'The filters hook should have run.' );

		foreach ( $captured as $filter ) {
			$this->assertNotSame( $parameter, $filter['key'] ?? null, 'letter must not become a field filter.' );
			$this->assertNotSame( $parameter, $filter['field_id'] ?? null, 'letter must not become a field filter.' );
		}
	}

	/**
	 * Builds a View on a form with a text field, optionally with an A-Z widget filtering by it.
	 *
	 * @param bool $with_az_filter Whether the View has an A-Z widget.
	 *
	 * @return \GV\View The View.
	 */
	private function create_view( bool $with_az_filter ): \GV\View {
		$form = $this->factory->form->create_and_get( array(
			'fields' => array( array( 'id' => 1, 'type' => 'text', 'label' => 'Name' ) ),
		) );

		$widgets = $with_az_filter ? array(
			'header_top' => array(
				wp_generate_password( 4, false ) => array(
					'id'           => 'az_filter',
					'filter_field' => '1',
					'localization' => 'en_US',
				),
			),
		) : array();

		$view_post = $this->factory->view->create_and_get( array(
			'form_id'     => $form['id'],
			'template_id' => 'table',
			'widgets'     => $widgets,
		) );

		return \GV\View::by_id( $view_post->ID );
	}

	/**
	 * An A-Z letter searches only a View that has an A-Z filter for it, so it cannot reveal
	 * a hidden View that has none.
	 */
	public function test_az_letter_is_a_search_only_for_a_view_with_an_az_filter() {
		$parameter = $this->az_parameter();
		$register  = static function ( \GV\View $view, string $letter ) use ( $parameter ): array {
			return (array) apply_filters( 'gk/gravityview/search/request/search-arguments', array(), array( $parameter => $letter ), $view );
		};

		$this->assertArrayHasKey( $parameter, $register( $this->create_view( true ), 'B' ), 'A View with an A-Z filter is searched by the letter.' );
		$this->assertArrayNotHasKey( $parameter, $register( $this->create_view( false ), 'B' ), 'A View without an A-Z filter is not searched by the letter.' );
		$this->assertArrayNotHasKey( $parameter, $register( $this->create_view( true ), '%' ), 'A character outside the alphabet searches nothing.' );
	}

	/**
	 * "Hide entries until search" must reveal a View when an A-Z letter is active. This
	 * path (the hide_until_searched filter, @since 1.5.4) also covers GravityView versions
	 * older than the search-request pipeline.
	 */
	public function test_az_letter_reveals_hide_until_searched_view() {
		$parameter = $this->az_parameter();

		$_GET = array();
		$this->assertTrue(
			(bool) apply_filters( 'gravityview/widget/hide_until_searched', true, null ),
			'Without an A-Z letter, hide-until-searched stays on.'
		);

		$_GET = array( $parameter => 'B' );
		$this->assertFalse(
			(bool) apply_filters( 'gravityview/widget/hide_until_searched', true, null ),
			'An active A-Z filter must reveal a hide-until-searched View.'
		);

		$_GET = array();
	}
}

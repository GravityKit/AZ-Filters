<?php

defined( 'DOING_GRAVITYVIEW_TESTS' ) || exit;

/**
 * How accented display names bucket depends on the collation in force. Under the
 * database default, which folds accents, an accented name groups under its base
 * letter. Under a binary collation override, alphabets that treat accented
 * characters as separate letters keep them separate.
 *
 * @see https://linear.app/gravitykit/issue/GVAZ-12
 */
class GV_AZ_Accent_Collation_Test extends GV_UnitTestCase {

	public function setUp(): void {
		parent::setUp();

		$_GET  = array();
		$_POST = array();
	}

	public function tearDown(): void {
		$_GET  = array();
		$_POST = array();

		remove_all_filters( 'gravityview/az_filter/collation' );

		parent::tearDown();
	}

	private function make_view( $form, $filter_field, $localization = 'en_US' ) {
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
					array( 'id' => 'az_filter', 'filter_field' => $filter_field, 'localization' => $localization ),
				),
			),
			'settings'    => array( 'page_size' => 25, 'show_only_approved' => false ),
		) );

		return \GV\View::from_post( $post );
	}

	public function test_accented_display_name_groups_under_its_base_letter() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		$emile   = $this->factory->user->create( array( 'display_name' => 'Émile Zola' ) );
		$edward  = $this->factory->user->create( array( 'display_name' => 'Edward King' ) );
		$bob     = $this->factory->user->create( array( 'display_name' => 'Bob Jones' ) );

		foreach ( array( $emile, $edward, $bob ) as $user_id ) {
			$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', 'created_by' => $user_id, '16' => 'x' ) );
		}

		$view = $this->make_view( $form, 'created_by' );

		$_GET['letter'] = 'e';

		$entries = $view->get_entries( gravityview()->request );

		$this->assertEquals( 2, $entries->total(), 'With no collation override, "É" folds to "E" so both names group under E.' );

		$matched = array();

		foreach ( $entries->all() as $entry ) {
			$matched[] = (int) $entry['created_by'];
		}

		sort( $matched );

		$expected = array( $emile, $edward );
		sort( $expected );

		$this->assertSame( $expected, $matched, '"Émile Zola" must bucket with "Edward King", not with "Bob Jones".' );
	}

	public function test_swedish_a_ring_and_umlauts_stay_separate_letters_under_a_binary_collation() {
		$form = $this->factory->form->import_and_get( 'complete.json' );

		// Swedish sorts å, ä and ö as their own letters after z, so a search for "ä"
		// must not fall back to the names starting with a plain "a".
		$arla = $this->factory->user->create( array( 'display_name' => 'Ärla Nilsson' ) );
		$ake  = $this->factory->user->create( array( 'display_name' => 'Åke Berg' ) );
		$anna = $this->factory->user->create( array( 'display_name' => 'Anna Svensson' ) );

		foreach ( array( $arla, $ake, $anna ) as $user_id ) {
			$this->factory->entry->create_and_get( array( 'form_id' => $form['id'], 'status' => 'active', 'created_by' => $user_id, '16' => 'x' ) );
		}

		$view = $this->make_view( $form, 'created_by', 'sv_SE' );

		add_filter( 'gravityview/az_filter/collation', static function () {
			return 'utf8mb4_bin';
		} );

		$_GET['letter'] = 'ä';

		$entries = $view->get_entries( gravityview()->request );

		$this->assertEquals( 1, $entries->total(), 'A binary collation must match only the "ä" name, not "Åke" or "Anna".' );

		$rows = $entries->all();
		$this->assertSame( (string) $arla, (string) $rows[0]['created_by'] );
	}
}

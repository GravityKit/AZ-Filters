<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * A-Z Entry Filter Widget Extension
 *
 * @extends GravityView_Widget
 */
class GravityView_Widget_A_Z_Entry_Filter extends GravityView_Widget {

	/**
	 * Does this get displayed on a single entry?
	 * @var boolean
	 */
	protected $show_on_single = false;

	function __construct() {
		$postID = isset($_GET['post']) ? intval($_GET['post']) : NULL;
		$formid = gravityview_get_form_id( $postID );

		$default_values = array( 'header' => 1, 'footer' => 0 );

		$settings = array(
			'show_all_letters' => array(
				'type' => 'checkbox',
				'label' => __( 'Show all letters even if entries are empty.', 'gravity-view-az-entry-filter' ),
				'default' => true
			),
			'filter_field' => array(
				'type' => 'select',
				'choices' => $this->get_filter_fields( $formid ),
				'label' => __( 'Which field do you wish to filter?', 'gravity-view-az-entry-filter' ),
				'default' => ''
			),
			'localization' => array(
				'type' => 'select',
				'choices' => apply_filters( 'gravity_view-az-entry-filter_widget_localization', array(
					'en' => __( 'English', 'gravity-view-az-entry-filter' ),
					'fr' => __( 'French', 'gravity-view-az-entry-filter' ),
				) ),
				'label' => __( 'Localization', 'gravity-view-az-entry-filter' ),
				'default' => 'en'
			),
			'uppercase' => array(
				'type' => 'checkbox',
				'label' => __( 'Uppercase A-Z', 'gravity-view-az-entry-filter' ),
				'default' => true
			),

		);

		add_filter( 'gravityview_fe_search_criteria', array( $this, 'filter_entries' ) );

		// add field options (specific for this widget)
		//add_filter('gravityview_template_field_options', array( $this, 'assign_field_options' ), 10, 4 );

		parent::__construct( __( 'A-Z Entry Filter', 'gravity-view-az-entry-filter' ), 'page_letters', $default_values, $settings );
	}

	function get_filter_fields( $formid ) {
		// Get fields with sub-inputs and no parent
		$fields = gravityview_get_form_fields( $formid, true, false );

		$default_output = array(
			'' => __( 'Default', 'gravity-view-az-entry-filter' ),
			'date_created' => __( 'Date Created', 'gravity-view-az-entry-filter' )
		);

		$output = array();

		if( !empty( $fields ) ) {

			$blacklist_field_types = apply_filters( 'gravityview_blacklist_field_types', array( 'list', 'textarea' ) );

			foreach( $fields as $id => $field ) {
				if( in_array( $field['type'], $blacklist_field_types ) ) { continue; }

				$output[$id] = esc_attr( $field['label'] );
			}

			$output = array_merge($default_output, $output);

		}
		else{
			$output = $default_output;
		}

		$output = array_merge($default_output, $output);

		return $output;
	}

	function filter_entries( $search_criteria ) {
		global $gravityview_view;

		if( !empty( $_GET['letter'] ) ) {
			$search_criteria['field_filters'][] = array(
				'key' => null, // The field ID to search
				'value' => esc_attr( rgget('letter') ), // The value to search
				'operator' => 'contains', // What to search in. Options: `is` or `contains`
			);
		}

		// add specific fields search
		//$search_filters = $this->get_search_filters();

		if( !empty( $search_filters ) && is_array( $search_filters ) ) {

			foreach( $search_filters as $k => $filter ) {

				if( !empty( $filter['value'] ) ) {

					if( false === strpos('.', $filter['key'] ) && ( $this->settings['filter_field'] === $filter['type'] ) ) {
						unset($filter['type']);

						$words = explode( ' ', $filter['value'] );

						foreach( $words as $word ) {

							if( !empty( $word ) && strlen( $word ) > 1 ) {

								// Keep the same key, label for each filter
								$filter['value'] = $word;

								// Add a search for the value
								$search_criteria['field_filters'][] = $filter;

							}

						}

						// next field
						continue;
					}

					unset( $filter['type'] );

					$search_criteria['field_filters'][] = $filter;
				}
			}
		}

		return $search_criteria;
	}

	public function render_frontend( $widget_args, $content = '', $context = '') {
		global $gravityview_view;

		if( empty( $gravityview_view ) ) {
			do_action('gravityview_log_debug', sprintf( '%s[render_frontend]: $gravityview_view not instantiated yet.', get_class( $this ) ) );
			return;
		}

		$atts = shortcode_atts( array(
			'show_all_letters' => !empty( $this->settings['show_all_letters']['default'] )
		), $widget_args, 'gravityview_widget_a_z_entry_filter' );

		$show_all_letters = $widget_args['show_all_letters'];
		$localization = $widget_args['localization'];
		$uppercase = $widget_args['uppercase'];

		$curr_letter = empty( $_GET['letter'] ) ? '' : $_GET['letter'];

		$letter_links = array(
			'current_letter' => $curr_letter,
			'show_all' => !empty( $atts['show_all_letters'] ),
		);

		$letter_links = $this->render_alphabet_letters( $letter_links, $show_all_letters, $localization, $uppercase);

		if( !empty( $letter_links ) ) {
			echo '<div class="gv-widget-letter-links">' . $letter_links . '</div>';
		} else {
			do_action( 'gravityview_log_debug', 'GravityView_Widget_A_Z_Entry_Filter[render_frontend] No letter links; render_alphabet_letters() returned empty response.' );
		}
	}

	// Renders the alphabet letters
	function render_alphabet_letters( $args = '', $show_all_letters = true, $localization = 'en', $uppercase = true ) {
		global $gravityview_view;

		$defaults = array(
			'base' => add_query_arg('letter','%#%'),
			'format' => '&letter=%#%',
			'add_args' => array(), //
			'prev_text' => '&laquo;',
			'next_text' => '&raquo;',
			'current_letter' => $this->get_first_letter_localized( $localization ),
			'show_all' => false,
			'before_first_letter' => '',
			'after_first_letter' => '',
			'before_last_letter' => '',
			'after_last_letter' => '',
			'first_letter' => $this->get_first_letter_localized( $localization ),
			'last_letter' => $this->get_last_letter_localized( $localization ),
		);

		$args = wp_parse_args( $args, $defaults );
		extract($args, EXTR_SKIP);

		$output = '<ul class="gravityview-alphabet-filter" style="list-style-type: none;">';

		$output .= '<li style="float: left; margin-left: 0%;">';

		if( empty( $current_letter ) || $current_letter == $first_letter ) {
			$output .= '<span class="previous-letter gv-disabled">';
		}
		else{
			$output .= '<span class="previous-letter"><a href=" ' . add_query_arg('letter', $this->previous_letter( $current_letter ) ) . '">';
		}

		$output .= $prev_text . '</a></span></li>';

		$alphabets = $this->get_localized_alphabet( $localization );

		//foreach( range( $first_letter, $last_letter ) as $char ) { // I think this only works for the english alphabet.
		foreach( $alphabets as $char ) { // This is more suited for any alphabet

			$class = '';
			$link = '#';

			$entries = $this->find_entries_under_letter( $char ); // This checks if there are any entries under the letter.

			// If all letters are set to show even if the entries are empty then we add a class to disable the linked letter.
			if( empty( $entries ) || $entries < 1 && $show_all_letters == true )
				$class = ' class="gv-disabled"';
				$link = add_query_arg('letter', $char);

			// Outputs the letter to filter the results on click.
			$output .= '<li style="float: left; margin-left: 1.4%;"><span' . $class . '><a href="' . $link . '">';
			if( $uppercase ) {
				$output .= ucwords( __( $char, 'gravity-view-az-entry-filter' ) );
			}
			else{
				$output .= __( $char, 'gravity-view-az-entry-filter' );
			}
			$output .= '</a></span></li>';
		}

		$output .= '<li style="float: left; margin-left: 1.4%;">';

		if( $current_letter == $last_letter ) {
			$output .= '<span class="next-letter gv-disabled">';
		}
		else{
			$output .= '<span class="next-letter"><a href=" ' . add_query_arg('letter', $this->next_letter( $current_letter ) ) . '">';
		}

		$output .= $next_text . '</a></span></li>';

		$output .= '<li class="last" style="float: left; margin-left: 1.4%; margin-right: 0%;"><span class="show-all"><a href="' . remove_query_arg('letter') . '">' . __( 'Show All', 'gravity-view-az-entry-filter' ) . '</a></span></li>';

		$output .= '</ul>';

		return $output;
	}

	function get_localized_alphabet( $charset ) {
		include( GV_AZ_Entry_Filter_Plugin_Dir_Path . 'alphabets/alphabets-' . $charset . '.php' );
		return $alphabets;
	}

	function get_first_letter_localized( $charset ) {
		include( GV_AZ_Entry_Filter_Plugin_Dir_Path . 'alphabets/alphabets-' . $charset . '.php' );
		return $first_letter;
	}

	function get_last_letter_localized( $charset ) {
		include( GV_AZ_Entry_Filter_Plugin_Dir_Path . 'alphabets/alphabets-' . $charset . '.php' );
		return $last_letter;
	}

	/* This fetches the previous letter. - This only works for English */
	function previous_letter( $letter ) {
		switch ( $letter ) {
			case 'b':
			case 'B':
				$letter = 'A';
			break;
			case 'c':
			case 'C':
				$letter = 'B';
			break;
			case 'd':
			case 'D':
				$letter = 'C';
			break;
			case 'e':
			case 'e':
				$letter = 'D';
			break;
			case 'f':
			case 'F':
				$letter = 'E';
			break;
			case 'g':
			case 'G':
				$letter = 'F';
			break;
			case 'h':
			case 'H':
				$letter = 'G';
			break;
			case 'i':
			case 'I':
				$letter = 'H';
			break;
			case 'j':
			case 'J':
				$letter = 'I';
			break;
			case 'k':
			case 'K':
				$letter = 'J';
			break;
			case 'l':
			case 'L':
				$letter = 'K';
			break;
			case 'm':
			case 'M':
				$letter = 'L';
			break;
			case 'n':
			case 'N':
				$letter = 'M';
			break;
			case 'o':
			case 'O':
				$letter = 'N';
			break;
			case 'p':
			case 'P':
				$letter = 'O';
			break;
			case 'q':
			case 'Q':
				$letter = 'P';
			break;
			case 'r':
			case 'R':
				$letter = 'Q';
			break;
			case 's':
			case 'S':
				$letter = 'R';
			break;
			case 't':
			case 'T':
				$letter = 'S';
			break;
			case 'u':
			case 'U':
				$letter = 'T';
			break;
			case 'v':
			case 'V':
				$letter = 'U';
			break;
			case 'w':
			case 'W':
				$letter = 'V';
			break;
			case 'x':
			case 'X':
				$letter = 'W';
			break;
			case 'y':
			case 'Y':
				$letter = 'X';
			break;
			case 'z':
			case 'Z':
				$letter = 'Y';
			break;
		}
		return $letter;
	}

	/* This fetches the next letter. */
	function next_letter( $letter ) {
		$letter++;
		return $letter;
	}

	function find_entries_under_letter( $char = '' ){
	}

	function filter_entries_under_letter( $char = '' ){
	}

} // GravityView_Widget_A_Z_Entry_Filter
new GravityView_Widget_A_Z_Entry_Filter;

?>
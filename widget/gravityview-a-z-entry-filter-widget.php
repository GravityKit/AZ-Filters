<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * A-Z Entry Filter Widget Extension
 *
 * @extends GravityView_Widget
 */
class GravityView_Widget_A_Z_Entry_Filter extends GravityView_Widget {

	private $search_filters = array();

	function __construct() {
		$postID = isset($_GET['post']) ? intval($_GET['post']) : NULL;
		$formid = gravityview_get_form_id( $postID );

		$widget_label = __( 'A-Z Entry Filter', 'gravity-view-az-entry-filter' );

		$widget_id = 'page_letters';

		$default_values = array( 'header' => 1, 'footer' => 1 );

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
				'choices' => $this->load_localization(),
				'label' => __( 'Localization', 'gravity-view-az-entry-filter' ),
				'default' => get_locale()
			),
			'uppercase' => array(
				'type' => 'checkbox',
				'label' => __( 'Uppercase A-Z', 'gravity-view-az-entry-filter' ),
				'default' => true
			),

		);

		add_filter( 'gravityview_fe_search_criteria', array( $this, 'filter_entries' ) );

		parent::__construct( $widget_label, $widget_id, $default_values, $settings );
	}

	// This loads the languages we can display the alphabets in.
	function load_localization() {
		$local = apply_filters( 'gravityview_az_entry_filter_localization', array(
			'' => __( 'English', 'gravity-view-az-entry-filter' ),
			'de_DE' => __( 'German', 'gravity-view-az-entry-filter' ),
			'es_ES' => __( 'Spanish', 'gravity-view-az-entry-filter' ),
			'fr_FR' => __( 'French', 'gravity-view-az-entry-filter' ),
			'it_IT' => __( 'Italian', 'gravity-view-az-entry-filter' ),
			'ru_RU' => __( 'Russian', 'gravity-view-az-entry-filter' ),
		) );

		return $local;
	}

	function get_filter_fields( $formid ) {
		// Get fields with sub-inputs and no parent
		$fields = gravityview_get_form_fields( $formid, true, false );

		$default_output = array(
			'' => __( 'Select a Field', 'gravity-view-az-entry-filter' ),
			'date_created' => __( 'Date Created', 'gravity-view-az-entry-filter' )
		);

		$output = array();

		if( !empty( $fields ) ) {

			$blacklist_field_types = apply_filters( 'gravityview_blacklist_field_types', array( 'list', 'textarea' ) );

			foreach( $fields as $id => $field ) {
				if( in_array( $field['type'], $blacklist_field_types ) ) { continue; }

				$output[$id] = esc_attr( $field['label'] );
			}

			$output = $default_output + $output;

		}
		else{
			$output = $default_output;
		}

		$output = $default_output + $output;

		return $output;
	}

	function filter_entries( $search_criteria ) {
		global $gravityview_view;

		// Search by Number
		if( !empty( $_GET['number'] ) ) {

			$numbers = explode( ' ', $_GET['number'] );

			foreach( $numbers as $number ) {
				$search_criteria['field_filters'][] = array(
					'key' => NULL, // The field ID to search
					'value' => esc_attr( $number ), // The value to search
					'operator' => 'contains', // What to search in. Options: `is`, `isnot`, `>`, `<`, `contains`
				);
			}
		}

		// Search by Letter
		if( !empty( $_GET['letter'] ) ) {
			$search_criteria['field_filters'][] = array(
				'key' => $this->settings['filter_field'], // The field ID to search e.g. 1.3 is the First Name
				'value' => esc_attr( $_GET['letter'] ), // The value to search
				'operator' => 'contains', // What to search in. Options: `is`, `isnot`, `>`, `<`, `contains`
			);
		}

		// Add specific fields search
		$search_filters = $this->get_search_filters();

		if( !empty( $search_filters ) && is_array( $search_filters ) ) {

			foreach( $search_filters as $l => $filter ) {

				if( !empty( $filter['value'] ) ) {

					if( false === strpos('.', $filter['key'] ) && ( $this->settings['filter_field'] === $filter['type'] ) ) {
						unset($filter['type']);

						$value = $filter['value'];

						if( strlen( $value ) > 1 ) {

							$numbers = explode( ' ', $value );

							foreach( $numbers as $number ) {

								if( !empty( $number ) && strlen( $number ) == 1 ) {

									// Keep the same key, label for each filter
									$filter['value'] = $letter;

									// Add a search for the value
									$search_criteria['field_filters'][] = $filter;

								}

							}

						}
						else{

							$letter = $value;

							if( !empty( $letter ) && strlen( $letter ) == 1 ) {

								// Keep the same key, label for each filter
								$filter['value'] = $letter;

								// Add a search for the value
								$search_criteria['field_filters'][] = $filter;

							}

						}

						// Next field
						continue;

					}

					unset( $filter['type'] );

					$search_criteria['field_filters'][] = $filter;
				}
			}
		}

		return $search_criteria;
	}

	// Displays the A-Z Filter
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
		$filter_field = $widget_args['filter_field'];
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
	function render_alphabet_letters( $args = '', $show_all_letters = 1, $charset = '', $uppercase = true ) {
		global $gravityview_view, $post;

		$form_id = gravityview_get_form_id( $post->ID );

		if( empty($charset) ) { $charset = 'en_US'; } // Loads 'en_US' by default.

		$defaults = array(
			'base' => add_query_arg('letter','%#%'),
			'format' => '&letter=%#%',
			'add_args' => array(), //
			'current_letter' => $this->get_first_letter_localized( $charset ),
			'show_all' => false,
			'before_first_letter' => apply_filters('gravityview_az_entry_filter_before_first_letter', NULL),
			'after_last_letter' => apply_filters('gravityview_az_entry_filter_after_last_letter', NULL),
			'first_letter' => $this->get_first_letter_localized( $charset ),
			'last_letter' => $this->get_last_letter_localized( $charset ),
		);

		$args = wp_parse_args( $args, $defaults );
		extract($args, EXTR_SKIP);

		// First we check that we have entries to begin with.
		$total = $gravityview_view->total_entries;

		// No Entries?
		if( empty( $total ) ) {
			do_action('gravityview_log_debug', sprintf( '%s[render_frontend]: No entries.', get_class($this)) );
		}

		$entries = $gravityview_view->entries; // Fetches all entries.

		$output = '<ul class="gravityview-alphabet-filter">';

		$output .= $before_first_letter;

		$other_chars = apply_filters( 'gravityview_az_entry_filter_other_chars', array( '&#35;' ) );
		$alphabet_chars = $this->get_localized_alphabet( $charset );
		$alphabet_chars = array_merge( $other_chars, $alphabet_chars );

		foreach( $alphabet_chars as $char ) { // This is more suited for any alphabet

			$class = '';
			$link = '&#35;'; // Hashtag

			// If hashtag '#' = '&#35;'
			if( $char == '&#35;' ) {
				$numbers = array( 1, 2, 3, 4, 5, 6, 7, 8, 9 );
				$number = implode( ",", $numbers );
				// If entries exist then change the link for the number.
				if( $entries > 0 ) $link = remove_query_arg('letter', add_query_arg('number', $number) );
			}
			else{
				// If entries exist then change the link for the letter.
				if( $entries > 0 ) $link = remove_query_arg('number', add_query_arg('letter', $char) );
			}

			// Leave class empty unless there are no entries.
			$class = '';
			// If entries are empty or less than 1
			if( empty( $entries ) || $entries < 1 ) {
				// All letters are set to show, disable linked letter.
if( $show_all_letters == 1 ) {
					$class = ' class="gv-disabled"';
				}
				// All letters are NOT set to show, hide the linked letter.
				else if( $show_all_letters == 0 ) {
					$class = ' class="gv-hide"';
				}
			}

			// Outputs the letter to filter the results on click.
			$output .= '<li' . $class . '><a href="' . $link . '">';

			if( $uppercase ) {
				$char = mb_strtoupper( $char );
			}

			// If the current letter matches then put it in bold.
			if( $current_letter == $char ) $char = '<strong>' . $char . '</strong>';

			$output .= $char; // Returns the letter after it's modifications.

			$output .= '</a></li>';
		}

		$output .= $after_last_letter;

		$output .= '<li class="last"><span class="show-all"><a href="' . remove_query_arg('number', remove_query_arg('letter') ) . '">' . __( 'Show All', 'gravity-view-az-entry-filter' ) . '</a></span></li>';

		$output .= '</ul>';

		return $output;
	}

	private function get_search_filters() {
		global $gravityview_view;

		if( !empty( $this->search_filters ) ) {
			return $this->search_filters;
		}

		if( empty( $gravityview_view ) ) { return; }

		// Get configured search filters (fields)
		$search_filters = array();
		$view_fields = $gravityview_view->fields;
		$form = $gravityview_view->form;

		if( !empty( $view_fields ) && is_array( $view_fields ) ) {
			foreach( $view_fields as $t => $fields ) {
				foreach( $fields as $field ) {
					if( !empty( $field['search_filter'] ) ) {
						$key = str_replace( '.', '_', $field['id'] ); // If the field [id] has a dot, replace it with a underscore.
						$value = esc_attr( rgget('filter_' . $key ) ); // Returns e.g. filter_1_3 for `First Name`
						$form_field = gravityview_get_field( $form, $field['id'] );

						// Only return the selected field to filter by
						if( $field['id'] == $this->settings['filter_field'] ) {
							$search_filters[] = array( 
								'key' => $field['id'], 
								'label' => $field['label'], 
								'value' => $value, 
								'type' => $form_field['type']
							);
						}
					}
				}
			}
		}

		$this->search_filters = $search_filters;

		return $search_filters;
	}

	// Returns the letters of the alphabets from the localization chosen or set by default.
	function get_localized_alphabet( $charset ) {
		$alphabets = apply_filters( 'gravityview_alphabets', array(
			'en_US' => array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'),
			'en_GB' => array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'),
			'es_ES' => array('a', 'b', 'c', 'ch', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'll', 'm', 'n', 'ñ', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'),
			'fr_FR' => array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'),
			'de_DE' => array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'),
			'it_IT' => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'z'),
			'ru_RU' => array('а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я'),
		) );

		if( isset( $alphabets[ $charset ] ) ) {
			$alphabet = $alphabets[ $charset ];
		} else {
			$alphabet = $alphabets['en_US'];
		}
 
		return $alphabet;
	}

	// Returns the first letter of the alphabet.
	function get_first_letter_localized( $charset ) {
		$alphabet = $this->get_localized_alphabet( $charset );
 
		return array_shift( $alphabet );
	}

	// Returns the last letter of the alphabet.
	function get_last_letter_localized( $charset ) {
		$alphabet = $this->get_localized_alphabet( $charset );
 
		return array_pop( $alphabet );
	}

} // GravityView_Widget_A_Z_Entry_Filter
new GravityView_Widget_A_Z_Entry_Filter;

?>
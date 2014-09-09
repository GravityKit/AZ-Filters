<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * A-Z Entry Filter Widget Extension
 *
 * @extends GravityView_Widget
 */
class GravityView_Widget_A_Z_Entry_Filter extends GravityView_Widget {

	private $letter = false;

	function __construct() {

		/**
		 * @todo Fix the fetching of the filter fields. Make it ajax.
		 */
		$postID = isset($_GET['post']) ? intval($_GET['post']) : NULL;

		$formid = gravityview_get_form_id( $postID );

		$widget_label = __( 'A-Z Entry Filter', 'gravity-view-az-entry-filter' );

		$widget_id = 'page_letters';

		$default_values = array( 'header' => 1, 'footer' => 1 );

		$settings = array(
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

	function get_filter_letter() {

		$param = apply_filters( 'gravityview_az_filter_parameter', 'letter' );

		if( !empty( $_GET[ $param ] ) ) {
			return esc_attr( $_GET[ $param ] );
		}

		return false;

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

	function query( $query ) {
		global $wpdb;

		$letter = $this->get_filter_letter();

		if( false !== $letter && preg_match( '/rg_lead_detail/', $query ) ) {

			if( in_array( $letter, $this->alphabet ) ) {

				$query = str_replace( "value like '%[REPLACEGV_AZ_FILTER]{$letter}%'", "value like '{$letter}%'", $query );

			} else if( $letter === '0-9' ) {

				$query = str_replace( "value like '%[REPLACEGV_AZ_FILTER]0-9%'", "value REGEXP '[0-9]'", $query );

			}

		}

		return $query;
	}

	function filter_entries( $search_criteria ) {
		global $gravityview_view;

		$letter = $this->get_filter_letter();

		// No search
		if( empty( $letter ) ) { return $search_criteria; }

		foreach ($gravityview_view->widgets as $zone => $areas) {

			$widgets = wp_list_filter( $areas, array('id' => 'page_letters' ));

			foreach ( $widgets as $uniqueid => $widget ) {

				$this->alphabet = $this->get_localized_alphabet( $widget['localization'] );

				add_filter( 'query', array( $this, 'query') );

				$search_criteria['field_filters'][] = array(
					'key' => $widget['filter_field'], // The field ID to search e.g. 1.3 is the First Name
					'value' => '[REPLACEGV_AZ_FILTER]'.$letter, // The value to search
					'operator' => 'like', // What to search in. Options: `is`, `isnot`, `>`, `<`, `contains`
				);

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

		$filter_field = $widget_args['filter_field'];
		$localization = $widget_args['localization'];
		$uppercase = $widget_args['uppercase'];

		/**
		 * @todo Clean up this - just pass the args directly to the render_alphabet_letters() method.
		 */
		$letter_links = array(
			'current_letter' => $this->get_filter_letter()
		);

		$letter_links = $this->render_alphabet_letters( $letter_links, $localization, $uppercase);

		if( !empty( $letter_links ) ) {
			echo '<div class="gv-widget-letter-links">' . $letter_links . '</div>';
		} else {
			do_action( 'gravityview_log_debug', 'GravityView_Widget_A_Z_Entry_Filter[render_frontend] No letter links; render_alphabet_letters() returned empty response.' );
		}
	}

	// Renders the alphabet letters
	function render_alphabet_letters( $args = '', $charset = '', $uppercase = true ) {
		global $gravityview_view, $post;

		$form_id = gravityview_get_form_id( $post->ID );

		if( empty($charset) ) { $charset = 'en_US'; } // Loads 'en_US' by default.

		$defaults = array(
			'base' => add_query_arg('letter','%#%'),
			'format' => '&letter=%#%',
			'add_args' => array(), //
			'current_letter' => NULL,
			'number_character' => _x('#', 'Character representing numbers', 'gravity-view'),
			'show_all_text' => __( 'Show All', 'gravity-view-az-entry-filter' ),
			'link_title_number' => __('Show entries starting with a number', 'gravity-view' ),
			'link_title_letter' => __('Show entries starting with the letter %s', 'gravity-view' ),
			'before_first_letter' => NULL,
			'after_last_letter' => NULL,
			'first_letter' => $this->get_first_letter_localized( $charset ),
			'last_letter' => $this->get_last_letter_localized( $charset ),
		);

		$args = apply_filters('gravityview_az_entry_args', wp_parse_args( $args, $defaults ) );

		extract($args, EXTR_SKIP);

		// No Entries?
		if( empty( $gravityview_view->total_entries ) ) {

			do_action('gravityview_log_debug', sprintf( '%s[render_frontend]: No entries.', get_class($this)) );

		}

		$output = '<ul class="gravityview-alphabet-filter">';

		$output .= $args['before_first_letter'];

		$alphabet_chars = $this->get_localized_alphabet( $charset );

		// Add the number character to the beginning of the array
		array_unshift($alphabet_chars, $args['number_character'] );

		foreach( $alphabet_chars as $char ) { // This is more suited for any alphabet

			$class = '';
			$link = '#'; // Internal anchor

			// If entries exist then change the link for the letter.

			if( $char === $args['number_character'] ) {
				$link = add_query_arg( array( 'letter' => '0-9' ) );
				$title = $args['link_title_number'];
			} else {
				$link = add_query_arg( array( 'letter' => $char ) );
				$title = sprintf( $args['link_title_letter'], $char );
			}

			// Leave class empty unless there are no entries.
			$classes = array();

			// If the current letter matches then put it in bold.
			if( $current_letter === $char || ( $current_letter === '0-9' && $char === $args['number_character'] ) ) {
				$classes[] = 'gv-active';
			}

			if( $uppercase ) {
				$classes[] = 'gv-uppercase';
			}

			// Outputs the letter to filter the results on click.
			$output .= '<li class="' . gravityview_sanitize_html_class( $classes ) . '"><a href="' . $link . '" title="'.esc_attr( $title ).'">' . $char . '</a></li>';
		}

		$output .= $args['after_last_letter'];

		$show_all_text = $uppercase ? $args['show_all_text'] : mb_strtolower( $args['show_all_text'] );

		$output .= '<li class="last"><span class="show-all"><a href="' . remove_query_arg('number', remove_query_arg('letter') ) . '">' . esc_html( $show_all_text ) . '</a></span></li>';

		$output .= '</ul>';

		return $output;
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


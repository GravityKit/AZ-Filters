<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * A-Z Entry Filter Widget Extension
 *
 * @extends GravityView_Widget
 */
class GravityView_Widget_A_Z_Entry_Filter extends GravityView_Widget {

	private $letter = false;

	private $letter_parameter;

	protected $widget_description;

	function __construct() {

		/**
		 * Modify the URL parameter used to filter the alphabet by.
		 *
		 * For example, you could use `starts_with` as the parameter, and the link would be `/view/example/?starts_with=a` instead of `/view/example/?letter=a`
		 *
		 * @var string
		 */
		$parameter = apply_filters( 'gravityview_az_filter_parameter', 'letter' );

		$this->letter_parameter = !empty( $parameter ) ? esc_attr( $parameter ) : 'letter';

		$postID = isset($_GET['post']) ? intval($_GET['post']) : NULL;

		$formid = gravityview_get_form_id( $postID );

		$widget_label = __( 'A-Z Entry Filter', 'gravityview-az-filters' );

		$this->widget_description = __('Alphabet links that filter entries by their first letter.', 'gravityview-az-filters');

		$widget_id = 'az_filter';

		$default_values = array( 'header' => 1, 'footer' => 1 );

		$settings = array(
			'filter_field' => array(
				'type' => 'select',
				'choices' => $this->get_filter_fields( $formid ),
				'label' => esc_attr__( 'Use this field to filter entries:', 'gravityview-az-filters' ),
				'desc'	=> sprintf( esc_attr__('Entries will be filtered based on the first character of this field. %sLearn more%s.', 'gravityview-az-filters' ), '<a href="http://docs.gravityview.co/article/198-the-use-this-field-to-filter-entries-setting" rel="external">', '</a>' ),
				'value' => ''
			),
			'localization' => array(
				'type' => 'select',
				'choices' => $this->load_localization(),
				'label' => __( 'Alphabet', 'gravityview-az-filters' ),
				'desc' => __('What alphabet should be used?', 'gravityview-az-filters' ),
				'value' => get_locale()
			),
			'uppercase' => array(
				'type' => 'checkbox',
				'label' => __( 'Use Uppercase Letters?', 'gravityview-az-filters' ),
				'value' => true,
				'desc' => __('Should the alphabet links be capitalized?', 'gravityview-az-filters' ),
			),

		);

		add_action( 'gravityview_search_widget_fields', array( $this, 'modify_search_widget_fields' ) );

		add_filter( 'gravityview_fe_search_criteria', array( $this, 'filter_entries' ) );

		parent::__construct( $widget_label, $widget_id, $default_values, $settings );
	}

	/**
	 * Define the default fields for the widget. Overwritten by the Javascript, but necessary to pass settings.
	 *
	 * Unsets fields that are inappropriate for filtering by letter.
	 *
	 * @param  int $formid Current Gravity Forms form ID
	 * @return array         Array of fields
	 */
	function get_filter_fields( $formid ) {

		$output = array();

		// Get fields with sub-inputs and no parent
		$fields = gravityview_get_form_fields( $formid, true, false );

		if( !empty( $fields ) ) {

			$blacklist_field_types = apply_filters( 'gravityview_blacklist_field_types', array( 'list', 'textarea', 'checkbox', 'radio', 'likert' ) );

			foreach( $fields as $id => $field ) {
				if( in_array( $field['type'], $blacklist_field_types ) ) {
					unset( $fields[ $id ] );
					continue;
				}
			}
		}

		foreach ( $fields as $key => $field ) {
			$output[ $key ] = $field['label'];
		}

		return $output;
	}

	/**
	 * Include the current letter in the search widget so it is included in search results.
	 *
	 * Requires GravityView 1.1.7
	 *
	 * @param string $search_fields Current HTML field output
	 * @return string If filter letter exists, adds a hidden input to the fields. Otherwise, returns original fields.
	 */
	function modify_search_widget_fields( $search_fields ) {

		if( $this->get_filter_letter() ) {
			$search_fields .= '<input type="hidden" name="'.$this->letter_parameter.'" value="'.$this->get_filter_letter().'" />';
		}

		return $search_fields;
	}

	/**
	 * Get the currently searched-for letter
	 *
	 * @return string|boolean If search being performed, return the letter being filtered by. Otherwise, return false.
	 */
	function get_filter_letter() {

		if( !empty( $_GET[ $this->letter_parameter ] ) ) {
			return esc_sql( $_GET[ $this->letter_parameter ] );
		}

		return false;

	}

	/**
	 * This loads the languages we can display the alphabets in.
	 *
	 * @return array Array of languages available, using the WordPress locale string as the key and the language as the value
	 */
	function load_localization() {
		$local = apply_filters( 'gravityview_az_entry_filter_localization', array(
			'en_US' => __( 'English', 'gravityview-az-filters' ),
			'fi'	=> __( 'Finnish', 'gravityview-az-filters' ),
			'fr_FR' => __( 'French', 'gravityview-az-filters' ),
			'de_DE' => __( 'German', 'gravityview-az-filters' ),
			'it_IT' => __( 'Italian', 'gravityview-az-filters' ),
			'nn_NO'	=> __( 'Norwegian', 'gravityview-az-filters' ),
			'ro_RO'	=> __( 'Romanian', 'gravityview-az-filters' ),
			'ru_RU' => __( 'Russian', 'gravityview-az-filters' ),
			'es_ES' => __( 'Spanish', 'gravityview-az-filters' ),
			'tr_TR'	=> __( 'Turkish', 'gravityview-az-filters' ),
			'bn_BN'	=> __( 'Bengali', 'gravityview-az-filters' ),
		) );

		return $local;
	}

	/**
	 * Modifies the query performed by GravityView to allow for "starts with" queries.
	 *
	 * This is a major hack, but necessary for the plugin to work without core GF changes.
	 *
	 * Because Gravity Forms doesn't have a way to search entries using "starts with", we add a placeholder so that when the query comes up in the filter, we can identify it. That placeholder is `[GravityView_Widget_A_Z_Entry_Filter]`. The query is modified from a wildcard "LIKE" search, which allows for anything before or after the query to a query where the search allows a wildcard after the first letter matches.
	 *
	 * Here's a basic example for the letter "a":
	 *
	 * - Before: `value LIKE %a%` - note the `%` wildcard both before and after. This would match "apple" and "face"
	 * - After: `value LIKE a%` - The `%` wildcard is now only after the `a`, which would match "apple", not "face"
	 *
	 * Here's an actual sample from a real query:
	 *
	 * - Before: `WHERE ((value like '%[GRAVITYVIEW_AZ_FILTER_REPLACE]a%'))` - note the wildcard before and after the `GRAVITYVIEW_AZ_FILTER_REPLACE`
	 * - After: `WHERE ((value like 'a%'))` - Now the wildcard and the placeholder get replaced with just the letter
	 *
	 * The code for the numbers query is a bit complex because we need to process multi-byte strings.
	 *
	 * MySQL regex doesn't work for unicode characters, so we need to generate a query string that goes through each number with a `LIKE` statement instead.
	 *
	 * The code looks like:
	 *
	 * `(value like '0%' OR value like '1%' [...] OR value like '9%')`
	 *
	 * and in Bengali:
	 *
	 * `(value like '০%' OR value like '1%' [...] OR value like '৯%')`
	 *
	 * There's a nice side effect: it's faster than `REGEXP` anyway.
	 *
	 * @param  string $query MySQL query passed to the database
	 * @return string        If the query contains `GRAVITYVIEW_AZ_FILTER_REPLACE`, it will be a modified query. Otherwise, the original query will be returned.
	 */
	function query( $query ) {
		global $wpdb;

		// Get the letter to filter by. Already sanitized.
		$letter = $this->get_filter_letter();

		// Make sure the query is the correct, modified query. We don't want to modify any other queries!
		if( false !== $letter && preg_match( '/rg_lead_detail/', $query ) && preg_match('/GRAVITYVIEW_AZ_FILTER_REPLACE/', $query ) ) {

			do_action( 'gravityview_log_debug', 'GravityView_Widget_A_Z_Entry_Filter[query]: Before filtering by character '.$letter, $query );

			$replace_sql = '';

			if( in_array( $letter, $this->alphabet ) ) {

				$replace_sql = $wpdb->prepare("value like %s", $letter.'%' );

			} else if( $letter === '0-9' ) {

				/**
				 * See docBlock for more information on why we're doing it like this.
				 */

				// Open number LIKE statement
				$replace_sql = '(';

				foreach ($this->numbers as $key => $value) {

					// Sanitize the request
					$replace_sql .= $wpdb->prepare( "value LIKE %s", $value.'%' );

					// If there's another number, join the statement
					if( isset($this->numbers[ ($key + 1) ] ) ) {
						$replace_sql .= ' OR ';
					}
				}

				// Close LIKE statement
				$replace_sql .= ')';

			}

			// Replace the placeholder with the actual query
			$query = str_replace( "value like '%[GRAVITYVIEW_AZ_FILTER_REPLACE]{$letter}%'", $replace_sql, $query );

			unset( $replace_sql );

			do_action( 'gravityview_log_debug', 'GravityView_Widget_A_Z_Entry_Filter[query]: After filtering by character '.$letter, $query );
		}

		return $query;
	}

	/**
	 * Add search criteria to the GravityView search that fetches entries from Gravity Forms
	 * @param  array $search_criteria Existing search criteria
	 * @return array                  Modified search criteria
	 */
	function filter_entries( $search_criteria ) {
		global $gravityview_view;

		$letter = $this->get_filter_letter();

		// No search
		if( empty( $letter ) ) {

			do_action( 'gravityview_log_debug', 'GravityView_Widget_A_Z_Entry_Filter[filter_entries]: Not adding search criteria.' );

			return $search_criteria;
		}

		foreach ($gravityview_view->widgets as $zone => $areas) {

			// Get all the widgets that have ID of `az_filter`
			$widgets = wp_list_filter( $areas, array('id' => 'az_filter' ));

			// For each widget...
			foreach ( $widgets as $uniqueid => $widget ) {

				if( empty( $widget['filter_field'] ) ) {

					do_action( 'gravityview_log_error', 'GravityView_Widget_A_Z_Entry_Filter[filter_entries]: No filter field has been set.', $widget );
					continue;
				}

				$this->alphabet = $this->get_localized_alphabet( $widget['localization'] );

				$this->numbers = $this->get_localized_numbers( $widget['localization'] );

				/**
				 * Modifies the query performed by GravityView. As in, the ACTUAL SQL.
				 *
				 * @see  GravityView_Widget_A_Z_Entry_Filter::query()
				 * @hack
				 */
				add_filter( 'query', array( $this, 'query') );

				$filter = array(
					'key' => $widget['filter_field'], // The field ID to search e.g. 1.3 is the First Name

					'value' => '[GRAVITYVIEW_AZ_FILTER_REPLACE]'.$letter, // The value to search
					'operator' => 'like', // Will use wildcard `%search%` format
				);

				$search_criteria['field_filters'][] = $filter;

				do_action( 'gravityview_log_debug', 'GravityView_Widget_A_Z_Entry_Filter[filter_entries]: Adding search criteria.', $filter );

				unset( $filter );

			}

		}

		return $search_criteria;
	}

	/**
	 * Output the HTML for the widget
	 * @param  array $widget_args Widget settings
	 * @param  string $content     [description]
	 * @param  string $context     [description]
	 * @return [type]              [description]
	 */
	public function render_frontend( $widget_args, $content = '', $context = '') {
		global $gravityview_view;

		if( empty( $gravityview_view ) ) {
			do_action('gravityview_log_debug', sprintf( '%s[render_frontend]: $gravityview_view not instantiated yet.', get_class( $this ) ) );
			return;
		}

		$defaults = array(
			'filter_field' => 0,
			'localization' => 'en_US',
			'uppercase' => true
		);

		$widget_args = wp_parse_args( $widget_args, $defaults );

		$filter_field = $widget_args['filter_field'];
		$localization = $widget_args['localization'];
		$uppercase = $widget_args['uppercase'];

		$args = array(
			'current_letter' => $this->get_filter_letter()
		);

		$letter_links = $this->render_alphabet_letters( $args, $localization, $uppercase);

		if( !empty( $letter_links ) ) {
			echo '<div class="gv-widget-letter-links">' . $letter_links . '</div>';
		} else {
			do_action( 'gravityview_log_debug', 'GravityView_Widget_A_Z_Entry_Filter[render_frontend] No letter links; render_alphabet_letters() returned empty response.' );
		}
	}

	/**
	 * Renders the HTML output of the letter links
	 * @param  array|string  $args     List of arguments for how to display the linked list. By default, only `current_letter` is passed, but others can be used. See the $defaults array in the code.
	 * @param  string  $charset   Language to use, using the WordPress Locale code (see {@link http://wpcentral.io/internationalization/})
	 * @param  boolean $uppercase Whether to show as uppercase or not
	 * @return string             HTML output of links
	 */
	function render_alphabet_letters( $args = '', $charset = 'en_US', $uppercase = true ) {
		global $gravityview_view, $post;

		$form_id = gravityview_get_form_id( $post->ID );

		if( empty($charset) ) { $charset = 'en_US'; } // Loads 'en_US' by default.

		$alphabet_chars = $this->get_localized_alphabet( $charset );

		$defaults = array(
			'base' => add_query_arg( $this->letter_parameter ,'%#%'),
			'format' => '&'.$this->letter_parameter.'=%#%',
			'add_args' => array(), //
			'current_letter' => NULL,
			'number_character' => _x('#', 'Character representing numbers', 'gravity-view'),
			'show_all_text' => __( 'Show All', 'gravityview-az-filters' ),
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

		$output = '<ul class="gravityview-az-filter">';

		$output .= $args['before_first_letter'];

		// Add the number character to the beginning of the array
		array_unshift($alphabet_chars, $args['number_character'] );

		foreach( $alphabet_chars as $char ) { // This is more suited for any alphabet

			$class = '';
			$link = '#'; // Internal anchor

			// If entries exist then change the link for the letter.

			if( $char === $args['number_character'] ) {
				$link = add_query_arg( array( $this->letter_parameter => '0-9' ) );
				$title = $args['link_title_number'];
			} else {
				$link = add_query_arg( array( $this->letter_parameter => $char ) );
				$title = sprintf( $args['link_title_letter'], $char );
			}

			// Leave class empty unless there are no entries.
			$classes = array();

			// If the current letter matches then put it in bold.
			if( $current_letter === $char || ( $current_letter === '0-9' && $char === $args['number_character'] ) ) {
				$classes[] = 'gv-active';
			}

			// If wanting uppercase letters, give them uppercase letters
			if( $uppercase ) {
				$classes[] = 'gv-uppercase';
			}

			// Outputs the letter to filter the results on click.
			$output .= '<li class="' . gravityview_sanitize_html_class( $classes ) . '">';
			$output .= '<a href="' . esc_url( $link ) . '" title="'.esc_attr( $title ).'">' . $char . '</a>';
			$output .= '</li>';

		}

		$output .= $args['after_last_letter'];

		/**
		 * Only show "Show All" link if there's a filter.
		 */
		if( $this->get_filter_letter() ) {

			$show_all_text = $uppercase ? $args['show_all_text'] : mb_strtolower( $args['show_all_text'] );

			$output .= '<li class="last"><span class="show-all"><a href="' . remove_query_arg('number', remove_query_arg( $this->letter_parameter ) ) . '">' . esc_html( $show_all_text ) . '</a></span></li>';

		}

		$output .= '</ul>';

		return $output;
	}

	/**
	 * Support non-latin numbers
	 * @since  1.0.1
	 * @param  string $charset Language code used by WordPress, such as `en_US` and `de_DE`
	 * @return array          Array of numbers in the language
	 */
	function get_localized_numbers( $charset = 'en_US' ) {

		$numbers = apply_filters( 'gravityview_numbers', array(
			'default' => array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ),
			'bn_BN' => array( '০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯' )
		));

		// If the alphabet exists, use it. Otherwise, use latin numerals.
		$number = isset( $numbers[ $charset ] ) ? $numbers[ $charset ] : $numbers['default'];

		return $number;
	}

	/**
	 * Returns the letters of the alphabets from the localization chosen or set by default.
	 * @param  string $charset Language code used by WordPress, such as `en_US` and `de_DE`
	 * @return [type]          [description]
	 */
	function get_localized_alphabet( $charset = 'en_US' ) {

		$charset = empty( $charset ) ? 'en_US' : $charset;

		$alphabets = apply_filters( 'gravityview_alphabets', array(
			'en_US' => array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'),
			'es_ES' => array('a', 'b', 'c', 'ch', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'll', 'm', 'n', 'ñ', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'),
			'de_DE' => array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'),
			'it_IT' => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'z'),
			'ru_RU' => array('а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я'),
			'nn_NO' => array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'æ', 'ø', 'å' ),
			'fi' => array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'x', 'y', 'z', 'å', 'ä', 'ö' ),
			'ro_RO' => array('a', 'ă', 'â', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'î', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 'ș', 't', 'ț', 'u', 'v', 'w', 'x', 'y', 'z' ),
			'tr_TR' => array( 'a', 'b', 'c', 'ç', 'd', 'e', 'f', 'g', 'ğ', 'h', 'ı', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'ö', 'p', 'r', 's', 'ş', 't', 'u', 'ü', 'v', 'y', 'z' ),
			'bn_BN' => array( 'অ', 'আ', 'ই', 'ঈ', 'উ', 'ঊ', 'ঋ', 'এ', 'ঐ', 'ও', 'ঔ', 'ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ', 'ড', 'ঢ', 'ণ', 'ত', 'থ', 'দ', 'ধ', 'ন', 'প', 'ফ', 'ব', 'ভ', 'ম', 'য', 'র', 'ল', 'শ', 'ষ', 'স', 'হ', 'ড়', 'ঢ়', 'য়' ),
		) );

		// If the alphabet exists, use it. Otherwise, use English alphabet.
		$alphabet = !empty( $alphabets[ $charset ] ) ? $alphabets[ $charset ] : $alphabets['en_US'];

		return $alphabet;
	}

	/**
	 * Returns the first letter of the alphabet.
	 * @param  string $charset WordPress locale string
	 * @return string          First letter of the alphabet
	 */
	function get_first_letter_localized( $charset ) {
		$alphabet = $this->get_localized_alphabet( $charset );

		return array_shift( $alphabet );
	}

	/**
	 * Returns the last letter of the alphabet.
	 * @param  string $charset WordPress locale string
	 * @return string          Last letter of the alphabet
	 */
	function get_last_letter_localized( $charset ) {
		$alphabet = $this->get_localized_alphabet( $charset );

		return array_pop( $alphabet );
	}

} // GravityView_Widget_A_Z_Entry_Filter

new GravityView_Widget_A_Z_Entry_Filter;


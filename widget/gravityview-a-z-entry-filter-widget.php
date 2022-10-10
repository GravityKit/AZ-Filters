<?php
namespace GV;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * A-Z Entry Filter Widget Extension
 *
 * @extends \GV\Widget
 */
class Widget_A_Z_Entry_Filter extends \GV\Widget {

	private $letter_parameter;

	protected $widget_description;

	public function __construct() {
		/**
		 * Modify the URL parameter used to filter the alphabet by.
		 * For example, you could use `starts_with` as the parameter, and the link would be `/view/example/?starts_with=a` instead of `/view/example/?letter=a`
		 * @deprecated Use `gravityview/az_filter/parameter` instead
		 */
		$parameter = apply_filters( 'gravityview_az_filter_parameter', 'letter' );

		/**
		 * @filter `gravityview/az_filter/parameter`
		 * @param string $parameter The URL parameter used to filter the alphabet by.
		 * For example, you could use `starts_with` as the parameter, and the link would be `/view/example/?starts_with=a` instead of `/view/example/?letter=a`
		 * @return string
		 */
		$parameter = apply_filters( 'gravityview/az_filter/parameter', $parameter );

		$this->letter_parameter = ! empty( $parameter ) ? esc_attr( $parameter ) : 'letter';

		$formid = gravityview_get_form_id( Utils::_GET( 'post' ) );

		$widget_label = __( 'A-Z Entry Filter', 'gravityview-az-filters' );

		$this->widget_description = __( 'Alphabet links that filter entries by their first letter.', 'gravityview-az-filters' );

		$widget_id = 'az_filter';

		$default_values = array( 'header' => 1, 'footer' => 1 );

		$settings = array(
			'filter_field' => array(
				'type' => 'select',
				'choices' => $this->get_filter_fields( $formid ),
				'label' => esc_attr__( 'Use this field to filter entries:', 'gravityview-az-filters' ),
				'desc'	=> sprintf( esc_attr__( 'Entries will be filtered based on the first character of this field. %sLearn more%s.', 'gravityview-az-filters' ), '<a href="https://docs.gravityview.co/article/198-the-use-this-field-to-filter-entries-setting" rel="external">', '</a>' ),
				'value' => ''
			),
			'localization' => array(
				'type' => 'select',
				'choices' => $this->load_localization(),
				'label' => __( 'Alphabet', 'gravityview-az-filters' ),
				'desc' => __( 'What alphabet should be used?', 'gravityview-az-filters' ),
				'value' => get_locale()
			),
			'uppercase' => array(
				'type' => 'checkbox',
				'label' => __( 'Use Uppercase Letters?', 'gravityview-az-filters' ),
				'value' => true,
				'desc' => __( 'Should the alphabet links be capitalized?', 'gravityview-az-filters' ),
			),

		);

		if ( ! $this->is_registered() ) {
			add_action( 'gravityview_search_widget_fields', array( $this, 'modify_search_widget_fields' ) );
			add_filter( 'gravityview_fe_search_criteria', array( $this, 'filter_entries' ), 10, 3 );
		}

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
	public function get_filter_fields( $formid ) {

		$output = array();

		// Get fields with sub-inputs and no parent
		$fields = gravityview_get_form_fields( $formid, true, false );

		if ( ! empty( $fields ) ) {

			/**
			 * @since 1.3
			 * @param array $blocklist_field_types Array of fields not to be filtered due to storage types (JSON, serialized).
			 */
			$blocklist_field_types = apply_filters( 'gravityview_blocklist_field_types', array( 'list', 'textarea', 'checkbox', 'radio', 'likert' ) );

			/**
			 * @deprecated 1.3
			 */
			$blocklist_field_types = apply_filters_deprecated( 'gravityview_blacklist_field_types', array( $blocklist_field_types ), '1.3', 'gravityview_blocklist_field_types' );

			foreach ( $fields as $id => $field ) {
				if ( in_array( $field['type'], $blocklist_field_types ) ) {
					unset( $fields[ $id ] );
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
	public function modify_search_widget_fields( $search_fields ) {
		if ( $letter = $this->get_filter_letter( true ) ) {
			$search_fields .= sprintf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $this->letter_parameter ), esc_attr( $letter ) );
		}
		return $search_fields;
	}

	/**
	 * Get the currently searched-for letter.
	 *
	 * @return string|boolean If search being performed, return the letter being filtered by. Otherwise, return false.
	 */
	public function get_filter_letter( $lowercase = false ) {

		$letter = Utils::_GET( $this->letter_parameter, false );

		if ( ! $letter ) {
			return false;
		}

		return $lowercase ? mb_strtolower( $letter ) : $letter;
	}

	/**
	 * This loads the languages we can display the alphabets in.
	 *
	 * @return array Array of languages available, using the WordPress locale string as the key and the language as the value
	 */
	public function load_localization() {
		$local = apply_filters( 'gravityview_az_entry_filter_localization', array(
			'en_US' => __( 'English', 'gravityview-az-filters' ),
			'fi'    => __( 'Finnish', 'gravityview-az-filters' ),
			'fr_FR' => __( 'French', 'gravityview-az-filters' ),
			'de_DE' => __( 'German', 'gravityview-az-filters' ),
			'it_IT' => __( 'Italian', 'gravityview-az-filters' ),
			'nn_NO' => __( 'Norwegian', 'gravityview-az-filters' ),
			'ro_RO' => __( 'Romanian', 'gravityview-az-filters' ),
			'ru_RU' => __( 'Russian', 'gravityview-az-filters' ),
			'es_ES' => __( 'Spanish', 'gravityview-az-filters' ),
			'tr_TR' => __( 'Turkish', 'gravityview-az-filters' ),
			'bn_BN' => __( 'Bengali', 'gravityview-az-filters' ),
			'is_IS' => __( 'Icelandic', 'gravityview-az-filters' ),
			'sv_SE' => __( 'Swedish (Sweden)', 'gravityview-az-filters' ),
			'sv_FI' => __( 'Swedish (Finland)', 'gravityview-az-filters' ),
			'sv'    => __( 'Swedish', 'gravityview-az-filters' ),
			'pl_PL' => __( 'Polish', 'gravityview-az-filters' ),
			'uk'    => __( 'Ukrainian', 'gravityview-az-filters' ),
			'el_GR' => __( 'Greek', 'gravityview-az-filters' ),
		) );

		return $local;
	}

	/**
	 * Add search criteria to the GravityView search that fetches entries from Gravity Forms
	 * @param array $search_criteria Existing search criteria
	 * @param array $form_id The main form ID
	 * @param array $args The View settings
	 * @return array Modified search criteria
	 */
	public function filter_entries( $search_criteria, $form_id, $args ) {

		if ( ! gravityview()->plugin->supports( \GV\Plugin::FEATURE_GFQUERY ) ) {
			return $search_criteria;
		}

		static $filter_added = false;

        if ( $filter_added ) {
	        return $search_criteria;
        }

		/**
		 * If GF_Query is available, we can construct custom conditions with nested
		 * booleans on the query, giving up the old ways of flat search_criteria field_filters.
		 */
		$filter_added = add_action( 'gravityview/view/query', array( $this, 'gf_query_filter' ), 10, 3 );

		return $search_criteria; // Return the original criteria, GF_Query modification kicks in later
	}

	/**
	 * Filters the \GF_Query with advanced logic.
	 *
	 * Dropin for the legacy flat filters when \GF_Query is available.
	 *
	 * @param \GF_Query $query The current query object reference
	 * @param \GV\View $this The current view object
	 * @param \GV\Request $request The request object
	 */
	public function gf_query_filter( &$query, $view, $request ) {
		$letter = $this->get_filter_letter( true );

		// No search
		if ( false === $letter ) {
			gravityview()->log->debug( 'Widget_A_Z_Entry_Filter[filter_entries]: Not adding search criteria.' );
			return;
		}

		$conditions = array();

		foreach ( $view->widgets->by_id( $this->get_widget_id() )->all() as $widget ) {
			$filter_field = $widget->configuration->get( 'filter_field' );

			if ( empty( $filter_field ) ) {
				gravityview()->log->error( 'Widget_A_Z_Entry_Filter[filter_entries]: No filter field has been set.', array( 'data' => $widget ) );
				continue;
			}

			$localization = $widget->configuration->get( 'localization' );
			$alphabet = $this->get_localized_alphabet( $localization );
			$numbers = $this->get_localized_numbers( $localization );
			$zero_through_nine = $this->get_zero_through_nine( $localization );

			if ( in_array( $letter, $alphabet ) ) {
				$conditions[] = new \GF_Query_Condition(
					new \GF_Query_Column( $filter_field ),
					\GF_Query_Condition::LIKE,
					new \GF_Query_Literal( "$letter%" )
				);
			} elseif( $zero_through_nine === $letter ) {
				/**
				 * For numbers 0-9 we need to add every condition separately.
				 */
				foreach ( $numbers as $value ) {
					$conditions[] = new \GF_Query_Condition(
						new \GF_Query_Column( $filter_field ),
						\GF_Query_Condition::LIKE,
						new \GF_Query_Literal( "$value%" )
					);
				}
			}
		}

		if ( $conditions ) {
			$query_parts = $query->_introspect();

			/**
			 * Tack on the AZ filter conditions.
			 */
			$query->where(
				\GF_Query_Condition::_and( $query_parts['where'], call_user_func_array( 'GF_Query_Condition::_or', $conditions ) )
			);
		}

		/**
		 * Override the Gravity Forms SQL directly to search lowercase values and possibly define custom collation.
		 *
		 * Requires Gravity Forms 2.4.3 or newer.
		 */
		add_filter( 'gform_gf_query_sql', function ( $sql ) {

			$where = $sql['where'];

			/**
			 * Override the default query collation for the letter comparison.
			 * @since 1.3
			 * @param string $collation_override The collation override for the query. May be necessary to limit results with non-latin characters containing accents. Return a valid collation to override, like 'utf8mb4_bin'.
			 * @param string $query The MySQL query passed to the database.
			 */
			$collation_override = apply_filters( 'gravityview/az_filter/collation', '', $where );

			// If the collation is set, add the COLLATE command.
			if ( $collation_override ) {
				$collation_override = esc_sql( ' COLLATE ' . $collation_override );
			}

			// Replace GF_Query meta value statements with only lowercase search in case the collation gives a strict match.
			// Also, adds the COLLATE statement if defined.
			$where = preg_replace( '/(`m[0-9]+?`\.`meta_value`)/ism', 'LOWER( $1 )' . $collation_override, $where );

			$sql['where'] = $where;

			return $sql;
		});
	}

	/**
	 * @inheritDoc
	 */
	public function render_frontend( $widget_args, $content = '', $context = '') {
		if ( ! $context instanceof Template_Context ) {
			gravityview()->log->debug( sprintf( '%s[render_frontend]: No context.', get_class( $this ) ) );
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
			'current_letter' => $this->get_filter_letter( true )
		);

		$letter_links = $this->render_alphabet_letters( $args, $localization, $uppercase, $context );

		if( ! empty( $letter_links ) ) {
			$custom_class = ! empty( $widget_args['custom_class'] ) ? ' ' . gravityview_sanitize_html_class( $widget_args['custom_class'] ) : '';
			echo '<div class="gv-widget-letter-links'.$custom_class.'">' . $letter_links . '</div>';
		} else {
			gravityview()->log->debug( 'Widget_A_Z_Entry_Filter[render_frontend] No letter links; render_alphabet_letters() returned empty response.' );
		}
	}

	/**
	 * Renders the HTML output of the letter links
	 *
	 * @param  array|string  $args     List of arguments for how to display the linked list. By default, only `current_letter` is passed, but others can be used. See the $defaults array in the code.
	 * @param  string  $charset   Language to use, using the WordPress Locale code (see {@link http://wpcentral.io/internationalization/})
	 * @param  boolean $uppercase Whether to show as uppercase or not
	 * @param  \GV\Template_Context $context The View context
	 *
	 * @return string             HTML output of links
	 */
	public function render_alphabet_letters( $args = '', $charset = 'en_US', $uppercase = true, $context = null ) {
		// Load 'en_US' by default.
		if ( empty( $charset ) ) {
			$charset = 'en_US';
		}

		$alphabet_chars = $this->get_localized_alphabet( $charset );

		$defaults = array(
			'base' => add_query_arg( $this->letter_parameter ,'%#%'),
			'format' => '&'.$this->letter_parameter.'=%#%',
			'add_args' => array(), //
			'current_letter' => null,
			'number_character' => _x( '#', 'Character representing numbers', 'gravityview-az-filters' ),
			'show_all_text' => __( 'Show All', 'gravityview-az-filters' ),
			'link_title_number' => __( 'Show entries starting with a number', 'gravityview-az-filters' ),
			'link_title_letter' => __( 'Show entries starting with the letter %s', 'gravityview-az-filters' ),
			'before_first_letter' => null,
			'after_last_letter' => null,
			'first_letter' => $this->get_first_letter_localized( $charset ),
			'last_letter' => $this->get_last_letter_localized( $charset ),
		);

		$args = apply_filters( 'gravityview_az_entry_args', wp_parse_args( $args, $defaults ), $context );

		extract( $args, EXTR_SKIP );

		// No Entries?
		if ( ! Utils::get( $context, 'entries' ) || ! $context->entries->count() ) {
			gravityview()->log->debug( sprintf( '%s[render_frontend]: No entries.', get_class( $this ) ) );
		}

		static $az_widget_counter;

		// Auto-increment the ID attribute based on number of displayed widgets.
		$az_widget_counter = isset( $az_widget_counter ) ? $az_widget_counter + 1 : 1;

		$widget_id_attr = sprintf( 'gv-widget-%s-%d', $this->get_widget_id(), $az_widget_counter );

		/**
		 * Modifies the anchor ID added to the end of the letter filter links. Return empty string to remove.
		 * @param string $az_widget_anchor The anchor in the format `#gv-widget-az_filter-{integer widget counter}`
		 * @param \GV\Template_Context $context The View context
		 */
		$az_widget_anchor = apply_filters( 'gravityview/az_filter/anchor', '#' . $widget_id_attr, $context );

		$ul_classes = array(
			'gravityview-az-filter',
		);

		if ( defined( 'ET_CORE_VERSION' ) ) {
			$ul_classes[] = 'et_smooth_scroll_disabled';
		}

		$output = '<ul class="' . gravityview_sanitize_html_class( $ul_classes ) . '" id="' . esc_attr( $widget_id_attr ) . '">';

		$output .= $args['before_first_letter'];

		// Add the number character to the beginning of the array
		array_unshift( $alphabet_chars, $args['number_character'] );

		$pagenum_parameter = 'pagenum';

		$current_letter = $this->get_filter_letter( true );

		$zero_through_nine = $this->get_zero_through_nine( $charset );

		$anchor_classes = array();

		if ( defined( 'KADENCE_VERSION' ) ) {
			$anchor_classes[] = 'scroll-ignore';
		}

		foreach ( $alphabet_chars as $char ) { // This is more suited for any alphabet

			// If entries exist then change the link for the letter.
			if ( $char === $args['number_character'] ) {
				$link = add_query_arg( array( $this->letter_parameter => $zero_through_nine ) );
				$title = $args['link_title_number'];
			} else {
				$link = add_query_arg( array( $this->letter_parameter => $char ) );
				$title = sprintf( $args['link_title_letter'], $char );
			}

			// Remove pagination if switching letters
			if ( $current_letter !== mb_strtolower( $char ) ) {
				$link = remove_query_arg( $pagenum_parameter, $link );
			}

			$link .= $az_widget_anchor;

			// Leave class empty unless there are no entries.
			$classes = array();

			// If the current letter matches then put it in bold.
			if ( $current_letter === mb_strtolower( $char ) || ( $current_letter === $zero_through_nine && $char === $args['number_character'] ) ) {
				$classes[] = 'gv-active';
			}

			// If wanting uppercase letters, give them uppercase letters
			if ( $uppercase ) {
				$classes[] = 'gv-uppercase';
			}

			// Outputs the letter to filter the results on click.
			$output .= '<li class="' . gravityview_sanitize_html_class( $classes ) . '">';
			$output .= '<a href="' . esc_url( $link ) . '" title="' . esc_attr( $title ) . '" class="' . gravityview_sanitize_html_class( $anchor_classes ) . '">' . esc_html( $char ) . '</a>';
			$output .= '</li>';

		}

		$output .= $args['after_last_letter'];

		/**
		 * Only show "Show All" link if there's a filter.
		 */
		if( $current_letter ) {

			$show_all_text = $args['show_all_text'];

			if( ! $uppercase && function_exists( 'mb_strtolower') ) {
				$show_all_text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $args['show_all_text'] ) : strtolower( $args['show_all_text'] );
			}

			$output .= '<li class="last"><span class="show-all"><a href="' . esc_url( remove_query_arg( $pagenum_parameter, remove_query_arg( $this->letter_parameter ) ) . $az_widget_anchor ) . '" class="' . gravityview_sanitize_html_class( $anchor_classes ) . '">' . esc_html( $show_all_text ) . '</a></span></li>';
		}

		$output .= '</ul>';

		return $output;
	}

	/**
	 * Get the localized version of 0-9 to use in links.
	 *
	 * Also used to determine whether numeric query or text.
	 *
	 * @since 1.3
	 *
	 * @param $charset
	 *
	 * @return string
	 */
	private function get_zero_through_nine( $charset ) {
		$numbers = $this->get_localized_numbers( $charset );

		$zero = reset( $numbers );
		$nine = end( $numbers );

		return $zero . '-' . $nine;
	}

	/**
	 * Support non-latin numbers
	 * @since  1.0.1
	 * @param  string $charset Language code used by WordPress, such as `en_US` and `de_DE`
	 * @return array          Array of numbers in the language
	 */
	public function get_localized_numbers( $charset = 'en_US' ) {

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
	 * @return array The alphabet as an array
	 */
	public function get_localized_alphabet( $charset = 'en_US' ) {

		$charset = empty( $charset ) ? 'en_US' : $charset;

		$alphabets = apply_filters( 'gravityview_alphabets', array(
			'en_US' => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z' ),
			'es_ES' => array( 'a', 'b', 'c', 'ch', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'll', 'm', 'n', 'ñ', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z' ),
			'de_DE' => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z' ),
			'it_IT' => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'z' ),
			'ru_RU' => array( 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я' ),
			'nn_NO' => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'æ', 'ø', 'å' ),
			'fi'    => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'x', 'y', 'z', 'å', 'ä', 'ö' ),
			'ro_RO' => array( 'a', 'ă', 'â', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'î', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 'ș', 't', 'ț', 'u', 'v', 'w', 'x', 'y', 'z' ),
			'tr_TR' => array( 'a', 'b', 'c', 'ç', 'd', 'e', 'f', 'g', 'ğ', 'h', 'ı', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'ö', 'p', 'r', 's', 'ş', 't', 'u', 'ü', 'v', 'y', 'z' ),
			'bn_BN' => array( 'অ', 'আ', 'ই', 'ঈ', 'উ', 'ঊ', 'ঋ', 'এ', 'ঐ', 'ও', 'ঔ', 'ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ', 'ড', 'ঢ', 'ণ', 'ত', 'থ', 'দ', 'ধ', 'ন', 'প', 'ফ', 'ব', 'ভ', 'ম', 'য', 'র', 'ল', 'শ', 'ষ', 'স', 'হ', 'ড়', 'ঢ়', 'য়' ),
			'is_IS' => array( 'a','á','b','d','ð','e','é','f','g','h','i','í','j','k','l','m','n','o','ó','p','r','s','t','u','ú','v','x','y','ý','þ','æ','ö' ),
			'sv_FI' => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'å', 'ä', 'ö' ),
			'sv_SE' => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'å', 'ä', 'ö' ),
			'sv'    => array( 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'å', 'ä', 'ö' ),
			'pl_PL' => array( 'a', 'ą', 'b', 'c', 'ć', 'd', 'e', 'ę', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'ł', 'm', 'n', 'ń', 'o', 'ó', 'p', 'r', 's', 'ś', 't', 'u', 'w', 'y', 'z', 'ź', 'ż' ),
			'uk'    => array( 'а', 'б', 'в', 'г', 'ґ', 'д', 'е', 'є', 'ж', 'з', 'и', 'і', 'ї', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ю', 'я' ),
			'el_GR' => array( 'α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ', 'ι', 'κ', 'λ', 'μ', 'ν', 'ξ', 'ο', 'π', 'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ', 'ω' ),
		) );

		// If the alphabet exists, use it. Otherwise, use English alphabet.
		$alphabet = Utils::get( $alphabets, $charset, Utils::get( $alphabets, 'en_US' ) );

		return $alphabet;
	}

	/**
	 * Returns the first letter of the alphabet.
	 * @param  string $charset WordPress locale string
	 * @return string          First letter of the alphabet
	 */
	public function get_first_letter_localized( $charset ) {
		$alphabet = $this->get_localized_alphabet( $charset );
		return array_shift( $alphabet );
	}

	/**
	 * Returns the last letter of the alphabet.
	 * @param  string $charset WordPress locale string
	 * @return string          Last letter of the alphabet
	 */
	public function get_last_letter_localized( $charset ) {
		$alphabet = $this->get_localized_alphabet( $charset );
		return array_pop( $alphabet );
	}

} // Widget_A_Z_Entry_Filter

new Widget_A_Z_Entry_Filter;

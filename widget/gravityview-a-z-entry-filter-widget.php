<?php

namespace GV;

use GF_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * A-Z Entry Filter Widget Extension.
 *
 * @extends Widget
 */
class Widget_A_Z_Entry_Filter extends Widget {
	private $letter_parameter;

	/**
	 * The letter comparisons queued for the current query, keyed by alias and literal.
	 *
	 * Filled by {@see gf_query_filter()}, consumed by {@see collate_letter_conditions()},
	 * so the LOWER()/COLLATE rewrite only ever touches the widget's own conditions.
	 *
	 * @since $ver$
	 *
	 * @var array[]
	 */
	private $letter_expressions = [];

	protected $widget_description;

	public $icon = 'data:image/svg+xml,%3Csvg%20fill=%22none%22%20height=%2248%22%20viewBox=%220%200%2048%2048%22%20width=%2248%22%20xmlns=%22http://www.w3.org/2000/svg%22%3E%3Cg%20stroke=%22%23000000%22%20stroke-linecap=%22round%22%20stroke-linejoin=%22round%22%20stroke-width=%223%22%3E%3Cg%20stroke-miterlimit=%2210%22%3E%3Cpath%20d=%22m4.5%2020.5%205.5-16h2l5.5%2016%22/%3E%3Cpath%20d=%22m5.875%2016.5h10.25%22/%3E%3Cpath%20d=%22m24.5%2034.5%2010%2010%2010-10%22/%3E%3C/g%3E%3Cpath%20d=%22m34.5%2044.5v-41%22/%3E%3Cpath%20d=%22m5.5%2028.5h11v1l-11%2014v1h11%22%20stroke-miterlimit=%2210%22/%3E%3C/g%3E%3C/svg%3E';

	public function __construct() {
		add_filter( 'gravityview/common/sortable_fields', [ $this, 'update_list_of_filterable_fields' ] );

		/**
		 * Modify the URL parameter used to filter the alphabet by.
		 * For example, you could use `starts_with` as the parameter, and the link would be `/view/example/?starts_with=a` instead of `/view/example/?letter=a`
		 *
		 * @deprecated Use `gravityview/az_filter/parameter` instead
		 */
		$parameter = apply_filters( 'gravityview_az_filter_parameter', 'letter' );

		/**
		 * @filter `gravityview/az_filter/parameter`
		 *
		 * @param string $parameter The URL parameter used to filter the alphabet by.
		 *                          For example, you could use `starts_with` as the parameter, and the link would be `/view/example/?starts_with=a` instead of `/view/example/?letter=a`
		 *
		 * @return string
		 */
		$parameter = apply_filters( 'gravityview/az_filter/parameter', $parameter );

		$this->letter_parameter = ! empty( $parameter ) ? esc_attr( $parameter ) : 'letter';

		$form_id = gravityview_get_form_id( Utils::_GET( 'post' ) );

		$widget_label = __( 'A-Z Entry Filter', 'gravityview-az-filters' );

		$this->widget_description = __( 'Alphabet links that filter entries by their first letter.', 'gravityview-az-filters' );

		$widget_id = 'az_filter';

		$default_values = [
			'header' => 1,
			'footer' => 1,
		];

		$settings = [
			'filter_field' => [
				'type'    => 'select',
				'choices' => $this->get_filter_fields( $form_id ),
				'label'   => esc_attr__( 'Use this field to filter entries:', 'gravityview-az-filters' ),
				'desc'    => sprintf( esc_attr__( 'Entries will be filtered based on the first character of this field. %sLearn more%s.', 'gravityview-az-filters' ), '<a href="https://www.gravitykit.com/docs/gravityview-pro/a-z-filters/the-use-this-field-to-filter-entries-setting/" rel="external">', '</a>' ),
				'value'   => '',
			],
			'localization' => [
				'type'    => 'select',
				'choices' => $this->load_localization(),
				'label'   => __( 'Alphabet', 'gravityview-az-filters' ),
				'desc'    => __( 'What alphabet should be used?', 'gravityview-az-filters' ),
				'value'   => get_locale(),
			],
			'uppercase'    => [
				'type'  => 'checkbox',
				'label' => __( 'Use Uppercase Letters?', 'gravityview-az-filters' ),
				'value' => true,
				'desc'  => __( 'Should the alphabet links be capitalized?', 'gravityview-az-filters' ),
			],

		];

		if ( ! $this->is_registered() ) {
			add_action( 'gravityview_search_widget_fields', [ $this, 'modify_search_widget_fields' ] );
		}

		// Register the query filter once, regardless of how many widget instances exist.
		static $query_filter_added = false;

		if ( ! $query_filter_added ) {
			$query_filter_added = add_action( 'gravityview/view/query', [ $this, 'gf_query_filter' ], 10, 2 );

			// Registered here (once) rather than per query, so the collation rewrite does not
			// stack on pages with more than one View.
			add_filter( 'gform_gf_query_sql', [ $this, 'collate_letter_conditions' ] );
		}

		// Make an active A-Z filter (?letter=B) count as a search so "Hide entries until
		// search" reveals it. Two layers for version coverage: the search-request filters
		// feed GravityView 3.0+'s is_search() (which also gates entry loading); the
		// hide_until_searched filter (@since 1.5.4) covers older GravityView. The parameter
		// is stripped from the built filters (remove_letter_filter()) so core does not treat
		// `letter` as a form field; gf_query_filter() applies the actual filtering.
		static $search_request_registered = false;

		if ( ! $search_request_registered ) {
			add_filter( 'gravityview/widget/hide_until_searched', [ $this, 'reveal_when_filtering_by_letter' ] );
			add_filter( 'gk/gravityview/search/request/search-arguments', [ $this, 'register_search_argument' ], 10, 2 );
			add_filter( 'gk/gravityview/search/request/filters', [ $this, 'remove_letter_filter' ], 10 );
			$search_request_registered = true;
		}

		parent::__construct( $widget_label, $widget_id, $default_values, $settings );
	}

	/**
	 * Registers the A-Z letter parameter with GravityView's search-request detection.
	 *
	 * Makes an active A-Z filter (`?letter=B`) count as a search so anything gated on
	 * `gravityview()->request->is_search()` behaves correctly, most notably the "Hide
	 * entries until search" View setting. The letter is not a form field, so the actual
	 * filtering happens in {@see self::gf_query_filter()} and the parameter is removed
	 * from the built filters in {@see self::remove_letter_filter()}.
	 *
	 * @since $ver$
	 *
	 * @param array $search_arguments The parsed search arguments, keyed by request key.
	 * @param array $arguments        The raw request arguments.
	 *
	 * @return array The search arguments, with the letter parameter added when present.
	 */
	public function register_search_argument( $search_arguments, $arguments ) {
		if ( ! is_array( $search_arguments ) ) {
			$search_arguments = [];
		}

		$letter = is_array( $arguments ) ? ( $arguments[ $this->letter_parameter ] ?? '' ) : '';

		if ( '' !== (string) $letter ) {
			$search_arguments[ $this->letter_parameter ] = [ 'value' => $letter ];
		}

		return $search_arguments;
	}

	/**
	 * Removes the A-Z letter parameter from GravityView's built search filters.
	 *
	 * The parameter is registered as a search argument only so the request counts as a
	 * search (see {@see self::register_search_argument()}). It is not a form field, so
	 * core must not build a filter for it; the letter filtering is applied separately in
	 * {@see self::gf_query_filter()}.
	 *
	 * @since $ver$
	 *
	 * @param array $filters The normalized filters.
	 *
	 * @return array The filters without the letter parameter.
	 */
	public function remove_letter_filter( $filters ) {
		if ( ! is_array( $filters ) ) {
			return $filters;
		}

		$parameter = $this->letter_parameter;

		$without_letter = array_filter(
			$filters,
			static function ( $filter ) use ( $parameter ) {
				$key      = is_array( $filter ) ? ( $filter['key'] ?? null ) : null;
				$field_id = is_array( $filter ) ? ( $filter['field_id'] ?? null ) : null;

				return $parameter !== $key && $parameter !== $field_id;
			}
		);

		return array_values( $without_letter );
	}

	/**
	 * Keeps a View visible when it is being filtered by an A-Z letter.
	 *
	 * "Hide entries until search" withholds a View until the visitor searches. An active
	 * A-Z filter is such a search, so this un-hides the View. It also covers GravityView
	 * versions older than the search-request pipeline, where the hide_until_searched filter
	 * (@since 1.5.4) is the available lever.
	 *
	 * @since $ver$
	 *
	 * @param bool $hide_until_searched Whether to hide the View until a search is performed.
	 *
	 * @return bool
	 */
	public function reveal_when_filtering_by_letter( $hide_until_searched ) {
		if ( $hide_until_searched && false !== $this->get_filter_letter() ) {
			return false;
		}

		return $hide_until_searched;
	}

	/**
	 * Adds extra fields to the list of fields that can be used for filtering.
	 *
	 * Called by {@see self::get_filter_fields()} and `gravityview/common/sortable_fields` filter.
	 *
	 * @since 1.4
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function update_list_of_filterable_fields( $fields ) {
		$fields['created_by'] = [
			'label' => __( 'Created By (User)', 'gravityview-az-filters' ),
			'type' => 'created_by',
		];

		return $fields;
	}

	/**
	 * Defines the default fields for the widget. Overwritten by the Javascript, but necessary to pass settings.
	 *
	 * Unsets fields that are inappropriate for filtering by letter.
	 *
	 * @param int $form_id Gravity Forms form ID.
	 *
	 * @return array         Array of fields.
	 */
	public function get_filter_fields( $form_id ) {
		$output = [];

		// Get fields with sub-inputs and no parent
		$fields = $this->update_list_of_filterable_fields( gravityview_get_form_fields( $form_id, true, false ) );

		/**
		 * @since 1.3
		 *
		 * @param array $blocklist_field_types Array of fields not to be filtered due to storage types (JSON, serialized).
		 */
		$blocklist_field_types = apply_filters( 'gravityview_blocklist_field_types', [ 'list', 'textarea', 'checkbox', 'radio', 'likert' ] );

		/**
		 * @deprecated 1.3
		 */
		$blocklist_field_types = apply_filters_deprecated( 'gravityview_blacklist_field_types', [ $blocklist_field_types ], '1.3', 'gravityview_blocklist_field_types' );

		foreach ( $fields as $id => $field ) {
			if ( in_array( $field['type'], $blocklist_field_types ) ) {
				continue;
			}

			$output[ $id ] = $field['label'];
		}

		return $output;
	}

	/**
	 * Includes the current letter in the search widget so it is included in search results.
	 *
	 * Requires GravityView 1.1.7
	 *
	 * @param string $search_fields Current HTML field output
	 *
	 * @return string If filter letter exists, adds a hidden input to the fields. Otherwise, returns original fields.
	 */
	public function modify_search_widget_fields( $search_fields ) {
		if ( $letter = $this->get_filter_letter( true ) ) {
			$search_fields .= sprintf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $this->letter_parameter ), esc_attr( $letter ) );
		}

		return $search_fields;
	}

	/**
	 * Gets the currently searched-for letter.
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
		$local = apply_filters(
			'gravityview_az_entry_filter_localization',
			[
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
			]
		);

		return $local;
	}

	/**
	 * Filters the GF_Query with advanced logic.
	 *
	 * Dropin for the legacy flat filters when GF_Query is available.
	 *
	 * @param GF_Query $query The current query object reference
	 * @param View     $this  The current view object
	 */
	public function gf_query_filter( &$query, $view ) {
		// Aliases and literals are per-query; comparisons queued for a previous query must not leak into this one.
		$this->letter_expressions = [];

		$letter = $this->get_filter_letter( true );

		// No search
		if ( false === $letter ) {
			gravityview()->log->debug( 'Widget_A_Z_Entry_Filter[filter_entries]: Not adding search criteria.' );

			return;
		}

		$conditions = [];

		foreach ( $view->widgets->by_id( $this->get_widget_id() )->all() as $widget ) {
			$filter_field = $widget->configuration->get( 'filter_field' );

			if ( empty( $filter_field ) ) {
				gravityview()->log->error( 'Widget_A_Z_Entry_Filter[filter_entries]: No filter field has been set.', [ 'data' => $widget ] );
				continue;
			}

			$localization      = $widget->configuration->get( 'localization' );
			$alphabet          = $this->get_localized_alphabet( $localization );
			$numbers           = $this->get_localized_numbers( $localization );
			$zero_through_nine = $this->get_zero_through_nine( $localization );

			if ( in_array( $letter, $alphabet ) ) {
				$prefixes = [ $letter ];
			} elseif ( $zero_through_nine === $letter ) {
				$prefixes = $numbers;
			} else {
				$prefixes = [];
			}

			if ( ! $prefixes ) {
				continue;
			}

			if ( 'created_by' === $filter_field ) {
				// created_by stores the author user ID, so match the users whose display
				// name starts with the letter (or any digit, for the 0-9 bucket).
				foreach ( $this->get_user_ids_by_first_letter( $prefixes ) as $user_id ) {
					$conditions[] = new \GF_Query_Condition(
						new \GF_Query_Column( $filter_field ),
						\GF_Query_Condition::EQ,
						new \GF_Query_Literal( $user_id )
					);
				}
			} else {
				foreach ( $prefixes as $prefix ) {
					$like = new \GF_Query_Literal( "$prefix%" );

					$conditions[] = new \GF_Query_Condition(
						new \GF_Query_Column( $filter_field ),
						\GF_Query_Condition::LIKE,
						$like
					);

					// GF_Query memoizes aliases, so resolving the field's meta alias now yields the
					// alias used in the final SQL: the rewrite can target exactly this comparison.
					$alias    = $query->_alias( $filter_field, 0, 'm' );
					$like_sql = $like->sql( $query );

					$this->letter_expressions[ $alias . ' ' . $like_sql ] = [
						'column' => sprintf( '`%s`.`meta_value`', $alias ),
						'like'   => $like_sql,
					];
				}
			}
		}

		if ( ! $conditions ) {
			return;
		}

		$query_parts = $query->_introspect();

		$query->where(
			\GF_Query_Condition::_and( $query_parts['where'], call_user_func_array( 'GF_Query_Condition::_or', $conditions ) )
		);
	}

	/**
	 * Lowercases the letter comparisons (and applies the optional collation override) so
	 * first-letter matching is case-insensitive even on case-sensitive column collations.
	 *
	 * Registered once, and rewrites only the comparisons queued by {@see gf_query_filter()}:
	 * other conditions in the query (e.g. a Search Bar search) keep their own matching.
	 *
	 * @since $ver$
	 *
	 * @param array $sql The Gravity Forms query SQL parts.
	 *
	 * @return array
	 */
	public function collate_letter_conditions( $sql ) {
		if ( empty( $this->letter_expressions ) || empty( $sql['where'] ) ) {
			return $sql;
		}

		/**
		 * Override the default query collation for the letter comparison.
		 *
		 * @since 1.3
		 *
		 * @param string $collation_override A valid collation to force, e.g. 'utf8mb4_bin'. Empty for none.
		 * @param string $where              The query WHERE clause.
		 */
		$collation_override = apply_filters( 'gravityview/az_filter/collation', '', $sql['where'] );

		$collate = '';

		if ( $collation_override ) {
			$collate = esc_sql( ' COLLATE ' . $collation_override );
		}

		$where = $sql['where'];

		foreach ( $this->letter_expressions as $expression ) {
			$search  = $expression['column'] . ' LIKE ' . $expression['like'];
			$replace = 'LOWER( ' . $expression['column'] . ' )' . $collate . ' LIKE ' . $expression['like'];

			$where = str_replace( $search, $replace, $where );
		}

		$has_rewritten = $where !== $sql['where'];

		if ( $has_rewritten ) {
			// The queued comparisons belong to one query; once spent, later queries in the
			// same request must not be rewritten.
			$this->letter_expressions = [];

			$sql['where'] = $where;
		}

		return $sql;
	}

	/**
	 * Returns user IDs by display name that starts with a given letter.
	 *
	 * @since 1.4
	 *
	 * @param string $letter
	 *
	 * @return array
	 */
	public function get_user_ids_by_first_letter( $letters ) {
		global $wpdb;

		$letters = array_values( array_filter( (array) $letters, static function ( $letter ) {
			return '' !== (string) $letter;
		} ) );

		if ( ! $letters ) {
			return [ PHP_INT_MAX ];
		}

		/** This filter is documented in collate_letter_conditions(). */
		$collation_override = apply_filters( 'gravityview/az_filter/collation', '', '' );

		$column = 'display_name';

		if ( $collation_override ) {
			// Mirror the letter conditions: lowercase the column, then force the collation,
			// so display names match the same way field values do.
			$column = 'LOWER( display_name )' . esc_sql( ' COLLATE ' . $collation_override );
		}

		$clauses      = implode( ' OR ', array_fill( 0, count( $letters ), "{$column} LIKE %s" ) );
		$placeholders = array_map( static function ( $letter ) {
			return $letter . '%';
		}, $letters );

		$query  = $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE {$clauses}", $placeholders );
		$result = $wpdb->get_col( $query );

		// A non-existent ID so an empty match returns no entries instead of all.
		return ! empty( $result ) ? $result : [ PHP_INT_MAX ];
	}

	/**
	 * @inheritDoc
	 */
	public function render_frontend( $widget_args, $content = '', $context = '' ) {
		if ( ! $context instanceof Template_Context ) {
			gravityview()->log->debug( sprintf( '%s[render_frontend]: No context.', get_class( $this ) ) );

			return;
		}

		$defaults = [
			'filter_field' => 0,
			'localization' => 'en_US',
			'uppercase'    => true,
		];

		$widget_args = wp_parse_args( $widget_args, $defaults );

		$localization = $widget_args['localization'];
		$uppercase    = $widget_args['uppercase'];

		$args = [
			'current_letter' => $this->get_filter_letter( true ),
		];

		$letter_links = $this->render_alphabet_letters( $args, $localization, $uppercase, $context );

		if ( ! empty( $letter_links ) ) {
			$custom_class = ! empty( $widget_args['custom_class'] ) ? ' ' . gravityview_sanitize_html_class( $widget_args['custom_class'] ) : '';
			echo '<div class="gv-widget-letter-links' . $custom_class . '">' . $letter_links . '</div>';
		} else {
			gravityview()->log->debug( 'Widget_A_Z_Entry_Filter[render_frontend] No letter links; render_alphabet_letters() returned empty response.' );
		}
	}

	/**
	 * Renders the HTML output of the letter links
	 *
	 * @param array|string         $args      List of arguments for how to display the linked list. By default, only `current_letter` is passed, but others can be used. See the $defaults array in the code.
	 * @param string               $charset   Language to use, using the WordPress Locale code (see {@link http://wpcentral.io/internationalization/})
	 * @param boolean              $uppercase Whether to show as uppercase or not
	 * @param \GV\Template_Context $context   The View context
	 *
	 * @return string             HTML output of links
	 */
	public function render_alphabet_letters( $args = '', $charset = 'en_US', $uppercase = true, $context = null ) {
		// Load 'en_US' by default.
		if ( empty( $charset ) ) {
			$charset = 'en_US';
		}

		$alphabet_chars = $this->get_localized_alphabet( $charset );

		$defaults = [
			'base'                => add_query_arg( $this->letter_parameter, '%#%' ),
			'format'              => '&' . $this->letter_parameter . '=%#%',
			'add_args'            => [],
			'current_letter'      => null,
			'number_character'    => _x( '#', 'Character representing numbers', 'gravityview-az-filters' ),
			'show_all_text'       => __( 'Show All', 'gravityview-az-filters' ),
			'link_title_number'   => __( 'Show entries starting with a number', 'gravityview-az-filters' ),
			'link_title_letter'   => __( 'Show entries starting with the letter %s', 'gravityview-az-filters' ),
			'before_first_letter' => null,
			'after_last_letter'   => null,
			'first_letter'        => $this->get_first_letter_localized( $charset ),
			'last_letter'         => $this->get_last_letter_localized( $charset ),
		];

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
		 *
		 * @param string               $az_widget_anchor The anchor in the format `#gv-widget-az_filter-{integer widget counter}`
		 * @param \GV\Template_Context $context          The View context
		 */
		$az_widget_anchor = apply_filters( 'gravityview/az_filter/anchor', '#' . $widget_id_attr, $context );

		$ul_classes = [
			'gravityview-az-filter',
		];

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

		$anchor_classes = [];

		if ( defined( 'KADENCE_VERSION' ) ) {
			$anchor_classes[] = 'scroll-ignore';
		}

		foreach ( $alphabet_chars as $char ) { // This is more suited for any alphabet

			// If entries exist then change the link for the letter.
			if ( $char === $args['number_character'] ) {
				$link  = add_query_arg( [ $this->letter_parameter => $zero_through_nine ] );
				$title = $args['link_title_number'];
			} else {
				$link  = add_query_arg( [ $this->letter_parameter => $char ] );
				$title = sprintf( $args['link_title_letter'], $char );
			}

			// Remove pagination if switching letters
			if ( $current_letter !== mb_strtolower( $char ) ) {
				$link = remove_query_arg( $pagenum_parameter, $link );
			}

			$link .= $az_widget_anchor;

			// Leave class empty unless there are no entries.
			$classes = [];

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
		if ( $current_letter ) {

			$show_all_text = $args['show_all_text'];

			if ( ! $uppercase && function_exists( 'mb_strtolower' ) ) {
				$show_all_text = function_exists( 'mb_strtolower' ) ? mb_strtolower( $args['show_all_text'] ) : strtolower( $args['show_all_text'] );
			}

			$output .= '<li class="last"><span class="show-all"><a href="' . esc_url( remove_query_arg( $pagenum_parameter, remove_query_arg( $this->letter_parameter ) ) . $az_widget_anchor ) . '" class="' . gravityview_sanitize_html_class( $anchor_classes ) . '">' . esc_html( $show_all_text ) . '</a></span></li>';
		}

		$output .= '</ul>';

		return $output;
	}

	/**
	 * Gets the localized version of 0-9 to use in links.
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
	 * Returns localized number.
	 *
	 * @since  1.0.1
	 *
	 * @param string $charset Language code used by WordPress, such as `en_US` and `de_DE`
	 *
	 * @return array          Array of numbers in the language
	 */
	public function get_localized_numbers( $charset = 'en_US' ) {
		$numbers = apply_filters(
			'gravityview_numbers',
			[
				'default' => [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ],
				'bn_BN'   => [ '০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯' ],
			]
		);

		// If the alphabet exists, use it. Otherwise, use latin numerals.
		$number = isset( $numbers[ $charset ] ) ? $numbers[ $charset ] : $numbers['default'];

		return $number;
	}

	/**
	 * Returns the letters of the alphabets from the localization chosen or set by default.
	 *
	 * @param string $charset Language code used by WordPress, such as `en_US` and `de_DE`
	 *
	 * @return array The alphabet as an array
	 */
	public function get_localized_alphabet( $charset = 'en_US' ) {
		$charset = empty( $charset ) ? 'en_US' : $charset;

		$alphabets = apply_filters(
			'gravityview_alphabets',
			[
				'en_US' => [ 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z' ],
				'es_ES' => [ 'a', 'b', 'c', 'ch', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'll', 'm', 'n', 'ñ', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z' ],
				'de_DE' => [ 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z' ],
				'it_IT' => [ 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'z' ],
				'ru_RU' => [ 'а', 'б', 'в', 'г', 'д', 'е', 'ё', 'ж', 'з', 'и', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ы', 'ь', 'э', 'ю', 'я' ],
				'nn_NO' => [ 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'æ', 'ø', 'å' ],
				'fi'    => [ 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'x', 'y', 'z', 'å', 'ä', 'ö' ],
				'ro_RO' => [ 'a', 'ă', 'â', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'î', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 'ș', 't', 'ț', 'u', 'v', 'w', 'x', 'y', 'z' ],
				'tr_TR' => [ 'a', 'b', 'c', 'ç', 'd', 'e', 'f', 'g', 'ğ', 'h', 'ı', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'ö', 'p', 'r', 's', 'ş', 't', 'u', 'ü', 'v', 'y', 'z' ],
				'bn_BN' => [ 'অ', 'আ', 'ই', 'ঈ', 'উ', 'ঊ', 'ঋ', 'এ', 'ঐ', 'ও', 'ঔ', 'ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ', 'ড', 'ঢ', 'ণ', 'ত', 'থ', 'দ', 'ধ', 'ন', 'প', 'ফ', 'ব', 'ভ', 'ম', 'য', 'র', 'ল', 'শ', 'ষ', 'স', 'হ', 'ড়', 'ঢ়', 'য়' ],
				'is_IS' => [ 'a', 'á', 'b', 'd', 'ð', 'e', 'é', 'f', 'g', 'h', 'i', 'í', 'j', 'k', 'l', 'm', 'n', 'o', 'ó', 'p', 'r', 's', 't', 'u', 'ú', 'v', 'x', 'y', 'ý', 'þ', 'æ', 'ö' ],
				'sv_FI' => [ 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'å', 'ä', 'ö' ],
				'sv_SE' => [ 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'å', 'ä', 'ö' ],
				'sv'    => [ 'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z', 'å', 'ä', 'ö' ],
				'pl_PL' => [ 'a', 'ą', 'b', 'c', 'ć', 'd', 'e', 'ę', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'ł', 'm', 'n', 'ń', 'o', 'ó', 'p', 'r', 's', 'ś', 't', 'u', 'w', 'y', 'z', 'ź', 'ż' ],
				'uk'    => [ 'а', 'б', 'в', 'г', 'ґ', 'д', 'е', 'є', 'ж', 'з', 'и', 'і', 'ї', 'й', 'к', 'л', 'м', 'н', 'о', 'п', 'р', 'с', 'т', 'у', 'ф', 'х', 'ц', 'ч', 'ш', 'щ', 'ъ', 'ю', 'я' ],
				'el_GR' => [ 'α', 'β', 'γ', 'δ', 'ε', 'ζ', 'η', 'θ', 'ι', 'κ', 'λ', 'μ', 'ν', 'ξ', 'ο', 'π', 'ρ', 'σ', 'τ', 'υ', 'φ', 'χ', 'ψ', 'ω' ],
			]
		);

		// If the alphabet exists, use it. Otherwise, use English alphabet.
		$alphabet = Utils::get( $alphabets, $charset, Utils::get( $alphabets, 'en_US' ) );

		return $alphabet;
	}

	/**
	 * Returns the first letter of the alphabet.
	 *
	 * @param string $charset WordPress locale string
	 *
	 * @return string          First letter of the alphabet
	 */
	public function get_first_letter_localized( $charset ) {
		$alphabet = $this->get_localized_alphabet( $charset );

		return array_shift( $alphabet );
	}

	/**
	 * Returns the last letter of the alphabet.
	 *
	 * @param string $charset WordPress locale string
	 *
	 * @return string          Last letter of the alphabet
	 */
	public function get_last_letter_localized( $charset ) {
		$alphabet = $this->get_localized_alphabet( $charset );

		return array_pop( $alphabet );
	}
} // Widget_A_Z_Entry_Filter

new Widget_A_Z_Entry_Filter();

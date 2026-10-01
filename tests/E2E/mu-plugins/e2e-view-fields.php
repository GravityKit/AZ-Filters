<?php
/**
 * E2E View Fields MU-Plugin
 *
 * REST endpoint for seeding individual field configurations into a View's
 * zones, bypassing the editor UI. `createViewViaApi` (the e2e-fixtures
 * /configure-view route) fills a View with PresetFieldGenerator defaults, but
 * some front-end regressions only appear for a specific field placed in a
 * specific zone (a Custom Content field in the List title zone, a column
 * flagged as a row header). Those field configs are not part of the generated
 * defaults, so this route lets a test add or tweak them directly.
 *
 * Endpoint: POST /wp-json/gk-e2e/v1/view-field
 * Auth:     Header X-E2E-TEST-TOKEN: gravitykit-e2e-test
 *           (matches @gravitykit/e2e-fixtures so the JS api module authenticates
 *           without any extra setup.)
 *
 * Body:
 *   view_id  (int, required)  The GravityView post ID.
 *   zone     (string, required) Zone key, e.g. `directory_table-columns`,
 *                               `directory_list-title`, `single_table-columns`.
 *   op       (string, required) One of:
 *            - `set`      Replace the zone with `fields` (array of field configs).
 *            - `append`   Push `field` (one field config) onto the zone.
 *            - `merge_at` Merge `settings` into the field at `index` in the zone.
 *   fields   (array)   For `set`: the field configs to place in the zone.
 *   field    (array)   For `append`: the field config to add.
 *   index    (int)     For `merge_at`: the zero-based position in the zone (default 0).
 *   settings (array)   For `merge_at`: the settings merged into that field.
 *   meta_key (string)  Fields meta key (default `_gravityview_directory_fields`,
 *                      which stores every zone keyed by `{context}_{zone}`).
 *
 * @package GravityView\E2E
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers a custom alphabet so a test can prove the localization filter reaches the
 * widget. Deliberately short and out of alphabetical order, so a View using it cannot
 * be confused with any built-in alphabet.
 */
add_filter(
	'gravityview_alphabets',
	function ( $alphabets ) {
		$alphabets['e2e_custom'] = array( 'x', 'y', 'z' );

		return $alphabets;
	}
);

/**
 * Forces the binary collation when `?e2e_collation=bin` is present. The letter rewrite
 * only applies a COLLATE clause when this filter returns one, so this is the lever a
 * front-end test needs to prove the rewrite stays inside its own conditions: under a
 * binary collation, a rewrite that leaked into a Search Bar condition would stop that
 * search matching.
 */
add_filter(
	'gravityview/az_filter/collation',
	function ( $collation ) {
		$requested = isset( $_GET['e2e_collation'] ) ? sanitize_text_field( wp_unslash( $_GET['e2e_collation'] ) ) : '';

		return 'bin' === $requested ? 'utf8mb4_bin' : $collation;
	}
);

add_action( 'rest_api_init', 'gv_e2e_register_view_field_routes' );

function gv_e2e_register_view_field_routes() {
	register_rest_route(
		'gk-e2e/v1',
		'/view-field',
		array(
			'methods'             => 'POST',
			'callback'            => 'gv_e2e_view_field',
			'permission_callback' => 'gv_e2e_view_field_check_token',
		)
	);

	register_rest_route(
		'gk-e2e/v1',
		'/entry-approval',
		array(
			'methods'             => 'POST',
			'callback'            => 'gv_e2e_set_entry_approval',
			'permission_callback' => 'gv_e2e_view_field_check_token',
		)
	);

	register_rest_route(
		'gk-e2e/v1',
		'/page',
		array(
			'methods'             => 'POST',
			'callback'            => 'gv_e2e_create_page',
			'permission_callback' => 'gv_e2e_view_field_check_token',
		)
	);

	register_rest_route(
		'gk-e2e/v1',
		'/entry-author',
		array(
			'methods'             => 'POST',
			'callback'            => 'gv_e2e_set_entry_author',
			'permission_callback' => 'gv_e2e_view_field_check_token',
		)
	);
}

/**
 * Publishes a page with arbitrary content, so a test can put more than one View
 * shortcode on a single page. The fixtures API creates one View per call and
 * exposes only that View's own permalink, which cannot express a page where two
 * Views render in the same request.
 *
 * Body: title (string, optional), content (string, required).
 */
function gv_e2e_create_page( $request ) {
	$params  = (array) $request->get_json_params();
	$content = (string) ( $params['content'] ?? '' );

	if ( '' === $content ) {
		return new WP_REST_Response( array( 'error' => 'content is required.' ), 400 );
	}

	$page_id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => (string) ( $params['title'] ?? 'E2E Page' ),
			'post_content' => $content,
		)
	);

	if ( is_wp_error( $page_id ) ) {
		return new WP_REST_Response( array( 'error' => $page_id->get_error_message() ), 500 );
	}

	return new WP_REST_Response(
		array(
			'success'  => true,
			'page_id'  => $page_id,
			'page_url' => get_permalink( $page_id ),
		)
	);
}

/**
 * Creates a user (when `display_name` is given) and assigns entries to an author,
 * so Created By filtering has authors with known display names. The fixtures API
 * seeds entry field values but always attributes entries to the requesting user.
 *
 * Body: display_name (string, optional — creates the user and returns its ID),
 * entry_ids (int[], optional), user_id (int, optional — used when display_name
 * is omitted).
 */
function gv_e2e_set_entry_author( $request ) {
	$params       = (array) $request->get_json_params();
	$display_name = (string) ( $params['display_name'] ?? '' );
	$user_id      = (int) ( $params['user_id'] ?? 0 );

	if ( '' !== $display_name ) {
		$login = 'e2e_' . wp_generate_password( 8, false );

		$user_id = wp_insert_user(
			array(
				'user_login'   => $login,
				'user_pass'    => wp_generate_password( 12, false ),
				'user_email'   => $login . '@example.com',
				'display_name' => $display_name,
				'role'         => 'subscriber',
			)
		);

		if ( is_wp_error( $user_id ) ) {
			return new WP_REST_Response( array( 'error' => $user_id->get_error_message() ), 500 );
		}

		// wp_insert_user derives display_name from the login unless it is set again.
		wp_update_user( array( 'ID' => $user_id, 'display_name' => $display_name ) );
	}

	if ( ! $user_id ) {
		return new WP_REST_Response( array( 'error' => 'Provide display_name or user_id.' ), 400 );
	}

	$entry_ids = isset( $params['entry_ids'] ) && is_array( $params['entry_ids'] ) ? $params['entry_ids'] : array();

	foreach ( $entry_ids as $entry_id ) {
		GFAPI::update_entry_property( (int) $entry_id, 'created_by', $user_id );
	}

	return new WP_REST_Response(
		array(
			'success'      => true,
			'user_id'      => $user_id,
			'display_name' => $display_name,
			'entry_ids'    => array_map( 'intval', $entry_ids ),
		)
	);
}

/**
 * Sets a GravityView approval status on an entry, so approval-search tests have
 * entries with known statuses. The fixtures API seeds entry field values but not
 * the `is_approved` meta GravityView filters on.
 *
 * Body: entry_id (int, required), status (int: 1 approved, 2 disapproved,
 * 3 unapproved; default 1), form_id (int, optional — resolved from the entry
 * when omitted).
 */
function gv_e2e_set_entry_approval( $request ) {
	$params   = (array) $request->get_json_params();
	$entry_id = (int) ( $params['entry_id'] ?? 0 );
	$status   = (int) ( $params['status'] ?? 1 );

	if ( ! $entry_id ) {
		return new WP_REST_Response( array( 'error' => 'entry_id is required.' ), 400 );
	}

	if ( ! class_exists( 'GravityView_Entry_Approval' ) ) {
		return new WP_REST_Response( array( 'error' => 'GravityView_Entry_Approval unavailable; is GravityView active?' ), 500 );
	}

	$entry   = GFAPI::get_entry( $entry_id );
	$form_id = (int) ( $params['form_id'] ?? ( is_array( $entry ) ? $entry['form_id'] : 0 ) );

	if ( ! $form_id ) {
		return new WP_REST_Response( array( 'error' => 'Could not resolve form_id for the entry.' ), 400 );
	}

	GravityView_Entry_Approval::update_approved( $entry_id, $status, $form_id );

	return new WP_REST_Response(
		array(
			'success'  => true,
			'entry_id' => $entry_id,
			'status'   => $status,
		)
	);
}

function gv_e2e_view_field_check_token( $request ) {
	return $request->get_header( 'X-E2E-TEST-TOKEN' ) === 'gravitykit-e2e-test';
}

/**
 * Assigns a unique key to each field config, mirroring how GravityView keys the
 * fields within a zone (the editor uses `uniqid()` per field).
 *
 * @param array $configs Flat array of field configs.
 *
 * @return array Keyed by unique id.
 */
function gv_e2e_view_field_key_configs( array $configs ) {
	$keyed = array();

	foreach ( $configs as $config ) {
		$keyed[ uniqid( '', true ) ] = (array) $config;
	}

	return $keyed;
}

function gv_e2e_view_field( $request ) {
	$params   = (array) $request->get_json_params();
	$view_id  = (int) ( $params['view_id'] ?? 0 );
	$zone     = (string) ( $params['zone'] ?? '' );
	$op       = sanitize_key( $params['op'] ?? '' );
	$meta_key = (string) ( $params['meta_key'] ?? '_gravityview_directory_fields' );

	if ( ! $view_id || 'gravityview' !== get_post_type( $view_id ) ) {
		return new WP_REST_Response( array( 'error' => 'A valid GravityView view_id is required.' ), 400 );
	}

	if ( '' === $zone ) {
		return new WP_REST_Response( array( 'error' => 'zone is required.' ), 400 );
	}

	$fields = get_post_meta( $view_id, $meta_key, true );
	$fields = is_array( $fields ) ? $fields : array();

	if ( ! isset( $fields[ $zone ] ) || ! is_array( $fields[ $zone ] ) ) {
		$fields[ $zone ] = array();
	}

	switch ( $op ) {
		case 'set':
			$configs         = isset( $params['fields'] ) && is_array( $params['fields'] ) ? $params['fields'] : array();
			$fields[ $zone ] = gv_e2e_view_field_key_configs( $configs );
			break;

		case 'append':
			$field                              = isset( $params['field'] ) && is_array( $params['field'] ) ? $params['field'] : array();
			$fields[ $zone ][ uniqid( '', true ) ] = $field;
			break;

		case 'merge_at':
			$index    = (int) ( $params['index'] ?? 0 );
			$settings = isset( $params['settings'] ) && is_array( $params['settings'] ) ? $params['settings'] : array();
			$keys     = array_keys( $fields[ $zone ] );

			if ( ! isset( $keys[ $index ] ) ) {
				return new WP_REST_Response(
					array( 'error' => sprintf( 'No field at index %d in zone %s.', $index, $zone ) ),
					400
				);
			}

			$key                    = $keys[ $index ];
			$fields[ $zone ][ $key ] = array_merge( $fields[ $zone ][ $key ], $settings );
			break;

		default:
			return new WP_REST_Response( array( 'error' => 'op must be one of: set, append, merge_at.' ), 400 );
	}

	update_post_meta( $view_id, $meta_key, $fields );

	return new WP_REST_Response(
		array(
			'success'   => true,
			'view_id'   => $view_id,
			'zone'      => $zone,
			'op'        => $op,
			'field_ids' => array_values( wp_list_pluck( $fields[ $zone ], 'id' ) ),
		)
	);
}

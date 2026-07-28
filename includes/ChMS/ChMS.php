<?php
/**
 * ChMS integrations base functionality to be extended by specific integrations
 *
 * @package CP_Sync
 */

namespace CP_Sync\ChMS;

use CP_Sync\Admin\Settings;
use CP_Sync\Setup\RateLimiter;

/**
 * The base ChMS class must have the following abstractions:
 * - handle aggregating the full list of data from the ChMS to be sent to the integration handler
 * - Provide Rest API routes for the integration authentication
 * 
 * The child ChMS class must provide the following:
 * - A method to get all ChMS data based on a set of filters
 * - A method to format a single ChMS record into a standard format
 * - A method to authenticate with the ChMS
 * - A method to check the connection to the ChMS
 */

/**
 * Base class for ChMS integrations
 */
abstract class ChMS {

	/**
	 * Class instance
	 *
	 * @var self
	 */
	protected static $_instance;

	/**
	 * Unique ID for this integration
	 *
	 * @var self
	 */
	public $id;

	/**
	 * Type of integration
	 *
	 * @var string
	 */
	public $label;

	/**
	 * Settings key for this integration
	 *
	 * @var string
	 */
	public $settings_key = '';

	/**
	 * Array of supported integrations
	 *
	 * @var array
	 */
	protected $supported_integrations = [];

	/**
	 * Image cache directory
	 *
	 * @var string
	 */
	protected $image_cache_dir = '';

	/**
	 * Only make one instance of PostType
	 *
	 * @return self
	 */
	public static function get_instance() {
		$class = get_called_class();

		if ( ! self::$_instance instanceof $class ) {
			self::$_instance = new $class();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor.
	 *
	 * Registers the schema-driven at-rest encryption filters. They attach here —
	 * rather than in setup()/load() — because a ChMS's settings can be read and
	 * written even when it is not the active ChMS ( see the settings REST routes in
	 * ChMS\_Init ), so encryption must apply on every request regardless of state.
	 */
	protected function __construct() {
		$this->register_schema_option_filters();
	}

	/**
	 * Invoke the ChMS
	 */
	public function load() {
		$this->setup();

		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		foreach ( $this->supported_integrations as $integration_type => $_args ) {
			add_filter( "cp_sync_pull_$integration_type", [ $this, 'get_formatted_data' ], 10, 2 );
		}
	}
	
	/**
	 * Get group filter config for frontend.
	 */
	public function get_formatted_filter_config() {
		$output = [];

		foreach( $this->supported_integrations as $integration_type => $args ) {
			if( $args['filter_config'] && is_callable( $args['filter_config'] ) ) {
				$config = $args['filter_config']();

				$formatted = [];

				foreach( $config as $key => $filter ) {
					$config_item = [
						'label'    => $filter['label'],
						'type'     => $filter['type'] ?? 'text',
						'supports' => $filter['supports'] ?? [],
					];

					if ( isset( $filter['options'] ) ) {
						if ( is_array( $filter['options'] ) ) {
							$config_item['options'] = $filter['options'];
						} else if ( is_callable( $filter['options'] ) ) {
							// give path to REST endpoint
							$config_item['optionsFetcher'] = [
								'endpoint' => "/cp-sync/v1/$this->id/selector/$integration_type/$key",
								'args'     => $filter['args'] ?? [],
							];
						}
					}

					$formatted[ $key ] = $config_item;
				}

				$output[ $integration_type ] = $formatted;
			}
		}

		return $output;	
	}

	/**
	 * Declare the per-ChMS settings screens as PHP schema data.
	 *
	 * Returns a map of `screenKey => screen`, where a screen is:
	 *   [ 'label' => string, 'sections' => [ [ 'title'?, 'description'?, 'fields' => [ fieldKey => FieldDef ] ] ] ]
	 *
	 * A FieldDef is `[ 'type', 'label', 'help'?, 'default'?, 'options'?, 'show_if'?, ...typeSpecific ]`.
	 * Every `fieldKey` MUST equal the exact stored settings key the corresponding
	 * screen persists ( screens are keyed by the settings group the tab writes to ).
	 *
	 * Screens/sections/fields must be pure, JSON-serializable data. The ONLY closure
	 * permitted anywhere in the tree is a field's `options` value ( a dynamic option
	 * list ); the serializer ( get_formatted_settings_schema() ) converts it into a
	 * REST `optionsFetcher` descriptor and never lets it escape to the client.
	 *
	 * The base returns an empty map; concrete integrations ( PCO, CCB ) override this.
	 *
	 * @since 0.4.0
	 * @return array
	 */
	public function get_settings_schema() {
		return [];
	}

	/**
	 * Serialize the declared settings schema into the client-safe JSON projection.
	 *
	 * Generalizes get_formatted_filter_config(): walks the schema declared by
	 * get_settings_schema() and, for every field, strips server-only attributes
	 * ( `sanitize`, `validate`, `encrypt` — the client never needs them ), converts a
	 * callable `options` into an `optionsFetcher` REST descriptor, passes plain
	 * arrays/scalars through, and guarantees the output contains no closures ( so it
	 * is always json_encode-able ).
	 *
	 * @since 0.4.0
	 * @return array
	 */
	public function get_formatted_settings_schema() {
		$output = [];

		foreach ( $this->get_settings_schema() as $screen_key => $screen ) {
			$formatted_screen = [
				'label'    => $screen['label'] ?? '',
				'sections' => [],
			];

			foreach ( $screen['sections'] ?? [] as $section ) {
				$formatted_section = [];

				if ( isset( $section['title'] ) ) {
					$formatted_section['title'] = $section['title'];
				}

				if ( isset( $section['description'] ) ) {
					$formatted_section['description'] = $section['description'];
				}

				$formatted_fields = [];

				foreach ( $section['fields'] ?? [] as $field_key => $field ) {
					$formatted_fields[ $field_key ] = $this->format_schema_field( $screen_key, $field_key, $field );
				}

				$formatted_section['fields'] = $formatted_fields;

				$formatted_screen['sections'][] = $formatted_section;
			}

			$output[ $screen_key ] = $formatted_screen;
		}

		return $output;
	}

	/**
	 * Project a single schema field into its client-safe form.
	 *
	 * @since 0.4.0
	 * @param string $screen_key The screen ( settings group ) the field belongs to.
	 * @param string $field_key  The stored settings key for the field.
	 * @param array  $field      The declared FieldDef.
	 * @return array
	 */
	protected function format_schema_field( $screen_key, $field_key, $field ) {
		// Attributes that exist only to drive server-side save handling ( Increment 3 ).
		$server_only = [ 'sanitize', 'validate', 'encrypt' ];

		$formatted = [];

		foreach ( $field as $attr => $value ) {
			if ( in_array( $attr, $server_only, true ) ) {
				continue;
			}

			if ( 'options' === $attr ) {
				if ( is_array( $value ) ) {
					// Static option list — passes straight through.
					$formatted['options'] = $value;
				} elseif ( is_callable( $value ) ) {
					// Dynamic option list — hand the client a REST descriptor and
					// drop the closure ( it is served by register_filter_endpoints() ).
					$formatted['optionsFetcher'] = [
						'endpoint' => "/cp-sync/v1/{$this->id}/selector/{$screen_key}/{$field_key}",
						'args'     => $field['args'] ?? [],
					];
				}
				continue;
			}

			// Defence in depth: never allow a closure to reach json_encode().
			if ( $value instanceof \Closure ) {
				continue;
			}

			$formatted[ $attr ] = $value;
		}

		return $formatted;
	}

	/* ------------------------------------------------------------------ *
	 * Schema-driven save handling ( Increment 3 )                        *
	 *                                                                    *
	 * The settings schema ( get_settings_schema() ) is the single        *
	 * declaration of per-field server behavior via three server-only      *
	 * attributes:                                                         *
	 *   - `sanitize` : named cleaning rule applied on the REST save walk. *
	 *   - `validate` : named validation rule ( REST walk returns a 400,   *
	 *                  option layer strips as defence in depth ).         *
	 *   - `encrypt`  : at-rest encryption, enforced at the option layer   *
	 *                  ( so non-REST writers — WP-CLI, update_setting() —  *
	 *                  never store plaintext ).                           *
	 * ------------------------------------------------------------------ */

	/**
	 * Generic recursive sanitizer for settings data.
	 *
	 * Walks nested arrays and applies sanitize_text_field() to string leaf values.
	 * Non-string scalars ( bool, int, float ) and null are preserved untouched so
	 * legitimately typed values are not corrupted. This is the fallback used for
	 * settings the schema deliberately does not declare ( custom-widget keys such as
	 * `facets`, `date_range_mode`, `date_start`, `date_end`, `enrollment_*` ) — such
	 * keys are never dropped or rejected.
	 *
	 * REST request params are not slash-escaped ( unlike $_POST ), so no wp_unslash()
	 * is applied here.
	 *
	 * @since 0.4.0
	 * @param mixed $value The value to sanitize.
	 * @return mixed The sanitized value.
	 */
	public static function sanitize_settings_recursive( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = [];
			foreach ( $value as $key => $item ) {
				// Preserve integer ( list ) keys; sanitize string keys defensively.
				$clean_key             = is_string( $key ) ? sanitize_text_field( $key ) : $key;
				$sanitized[ $clean_key ] = self::sanitize_settings_recursive( $item );
			}
			return $sanitized;
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		// Preserve bools, ints, floats and null as-is.
		return $value;
	}

	/**
	 * Flatten a settings schema into a `screenKey => fieldKey => FieldDef` index.
	 *
	 * @since 0.4.0
	 * @param array $schema The schema returned by get_settings_schema().
	 * @return array
	 */
	public static function index_schema_fields( $schema ) {
		$index = [];

		if ( ! is_array( $schema ) ) {
			return $index;
		}

		foreach ( $schema as $screen_key => $screen ) {
			foreach ( ( $screen['sections'] ?? [] ) as $section ) {
				foreach ( ( $section['fields'] ?? [] ) as $field_key => $field ) {
					$index[ $screen_key ][ $field_key ] = $field;
				}
			}
		}

		return $index;
	}

	/**
	 * Schema-driven sanitize + validate walk for an incoming settings payload.
	 *
	 * For every top-level group ( screen key ) and field:
	 *   - If the field IS declared in the schema, dispatch on its `sanitize` attribute
	 *     ( or a type-inferred default ) and then run its `validate` rule. A validation
	 *     failure returns a WP_Error ( HTTP 400 ) so the caller can surface a real,
	 *     field-specific server error.
	 *   - If the field is NOT declared ( custom-widget keys ), fall back to the generic
	 *     recursive sanitizer. Unknown keys are never dropped.
	 *   - Top-level groups that are not schema screens ( or non-array group values ) are
	 *     passed through the generic recursive sanitizer.
	 *
	 * Pure logic ( only WP helpers sanitize_text_field / sanitize_key are touched ), so
	 * it is unit-testable without booting WordPress.
	 *
	 * @since 0.4.0
	 * @param array $schema The schema returned by get_settings_schema().
	 * @param array $data   The incoming settings payload.
	 * @return array|\WP_Error The sanitized payload, or a WP_Error on validation failure.
	 */
	public static function sanitize_settings_by_schema( $schema, $data ) {
		$index  = self::index_schema_fields( $schema );
		$result = [];

		foreach ( $data as $group_key => $group_value ) {
			$clean_group = is_string( $group_key ) ? sanitize_text_field( $group_key ) : $group_key;

			// Not a declared screen, or a non-array group: generic fallback.
			if ( ! isset( $index[ $group_key ] ) || ! is_array( $group_value ) ) {
				$result[ $clean_group ] = self::sanitize_settings_recursive( $group_value );
				continue;
			}

			$sanitized_group = [];

			foreach ( $group_value as $field_key => $field_value ) {
				$clean_field = is_string( $field_key ) ? sanitize_text_field( $field_key ) : $field_key;

				// Undeclared custom-widget key — generic fallback, never dropped.
				if ( ! isset( $index[ $group_key ][ $field_key ] ) ) {
					$sanitized_group[ $clean_field ] = self::sanitize_settings_recursive( $field_value );
					continue;
				}

				$field           = $index[ $group_key ][ $field_key ];
				$sanitized_value = self::sanitize_field_value( $field, $field_value );

				$validation = self::validate_field_value( $field, $field_key, $sanitized_value );
				if ( is_wp_error( $validation ) ) {
					return $validation;
				}

				$sanitized_group[ $clean_field ] = $sanitized_value;
			}

			$result[ $clean_group ] = $sanitized_group;
		}

		return $result;
	}

	/**
	 * Sanitize a single field value according to its declared ( or inferred ) rule.
	 *
	 * @since 0.4.0
	 * @param array $field The declared FieldDef.
	 * @param mixed $value The incoming value.
	 * @return mixed
	 */
	public static function sanitize_field_value( $field, $value ) {
		$rule = isset( $field['sanitize'] )
			? $field['sanitize']
			: self::default_sanitize_for_type( $field['type'] ?? 'text' );

		return self::apply_sanitize_rule( $rule, $value );
	}

	/**
	 * The default sanitize rule inferred from a field `type` when none is declared.
	 *
	 * @since 0.4.0
	 * @param string $type The field type.
	 * @return string A named sanitize rule.
	 */
	public static function default_sanitize_for_type( $type ) {
		switch ( $type ) {
			case 'checkbox':
			case 'toggle':
				return 'bool';

			case 'number':
				return 'int';

			case 'filter-builder':
			case 'async-multiselect':
			case 'multiselect':
				return 'recurse';

			default:
				return 'text';
		}
	}

	/**
	 * Apply a named sanitize rule to a value.
	 *
	 * Rule table:
	 *   - `text`           : sanitize_text_field() ( arrays fall back to the recursive
	 *                        sanitizer ). The default for text-ish fields.
	 *   - `raw_credential` : strip only control characters ( \x00-\x1F, \x7F ). Preserves
	 *                        every printable character so API passwords ( tags, %-octets,
	 *                        whitespace ) survive intact. The Phase-1 credential carve-out.
	 *   - `key`            : sanitize_key().
	 *   - `bool`           : cast to bool.
	 *   - `int`            : cast to int.
	 *   - `recurse`        : the generic recursive sanitizer ( nested structures ).
	 *
	 * @since 0.4.0
	 * @param string $rule  The named rule.
	 * @param mixed  $value The value to clean.
	 * @return mixed
	 */
	public static function apply_sanitize_rule( $rule, $value ) {
		switch ( $rule ) {
			case 'raw_credential':
				// Control-char strip only — defence against header/CRLF injection while
				// preserving every printable character of a credential.
				return is_string( $value ) ? preg_replace( '/[\x00-\x1F\x7F]/', '', $value ) : $value;

			case 'key':
				return is_string( $value ) ? sanitize_key( $value ) : $value;

			case 'bool':
				return (bool) $value;

			case 'int':
				return (int) $value;

			case 'recurse':
				return self::sanitize_settings_recursive( $value );

			case 'text':
			default:
				return is_string( $value )
					? sanitize_text_field( $value )
					: self::sanitize_settings_recursive( $value );
		}
	}

	/**
	 * Validate a single ( already sanitized ) field value against its declared rule.
	 *
	 * @since 0.4.0
	 * @param array  $field     The declared FieldDef.
	 * @param string $field_key The field key ( included in the error data ).
	 * @param mixed  $value     The sanitized value to validate.
	 * @return true|\WP_Error True when valid, WP_Error ( status 400 ) otherwise.
	 */
	public static function validate_field_value( $field, $field_key, $value ) {
		if ( empty( $field['validate'] ) ) {
			return true;
		}

		switch ( $field['validate'] ) {
			case 'subdomain':
				// Empty is allowed ( not-yet-configured ); any non-empty value must be a
				// bare DNS label. Upgrades the Phase-1 silent-strip into a real 400 error.
				if ( is_string( $value ) && '' !== $value && ! preg_match( '/^[a-zA-Z0-9-]+\z/', $value ) ) {
					return new \WP_Error(
						'invalid_subdomain',
						__( 'Invalid subdomain. Subdomains may contain only letters, numbers, and hyphens.', 'cp-sync' ),
						[ 'status' => 400, 'field' => $field_key ]
					);
				}
				return true;
		}

		return true;
	}

	/**
	 * Collect the schema fields matching a predicate, with their group/field paths.
	 *
	 * @since 0.4.0
	 * @param callable $predicate Receives a FieldDef, returns bool.
	 * @return array List of [ 'group' => screenKey, 'field' => fieldKey, 'def' => FieldDef ].
	 */
	protected function collect_schema_fields( callable $predicate ) {
		$out = [];

		foreach ( $this->get_settings_schema() as $screen_key => $screen ) {
			foreach ( ( $screen['sections'] ?? [] ) as $section ) {
				foreach ( ( $section['fields'] ?? [] ) as $field_key => $field ) {
					if ( $predicate( $field ) ) {
						$out[] = [ 'group' => $screen_key, 'field' => $field_key, 'def' => $field ];
					}
				}
			}
		}

		return $out;
	}

	/**
	 * Registered settings keys, guarding against double-registration of the option
	 * encrypt/decrypt filters ( which would double-decrypt and corrupt credentials ).
	 *
	 * Keyed by settings_key so distinct ChMS integrations never clash while the same
	 * one cannot register twice.
	 *
	 * @var array<string,bool>
	 */
	private static $schema_option_filters_registered = [];

	/**
	 * Register schema-driven at-rest encryption ( and option-layer validation ) filters.
	 *
	 * Reads get_settings_schema(), finds every `encrypt: true` field, and registers the
	 * `pre_update_option_{settings_key}` / `option_{settings_key}` pair so credentials are
	 * encrypted on write and transparently decrypted on read — at the OPTION layer, so
	 * non-REST writers ( WP-CLI Tests, update_setting() ) cannot store plaintext. Fields
	 * carrying a `validate` rule are also stripped ( keep-prior-valid-or-blank ) at the
	 * same point as defence in depth.
	 *
	 * Called from the base constructor so the filters attach on every request regardless
	 * of which ChMS is active ( the settings routes read/write inactive ChMS options ).
	 *
	 * @since 0.4.0
	 * @return void
	 */
	public function register_schema_option_filters() {
		if ( empty( $this->settings_key ) || ! empty( self::$schema_option_filters_registered[ $this->settings_key ] ) ) {
			return;
		}

		// Mark before wiring so a re-entrant construction can never double-register.
		self::$schema_option_filters_registered[ $this->settings_key ] = true;

		$has_encrypt  = ! empty( $this->collect_schema_fields( static fn( $f ) => ! empty( $f['encrypt'] ) ) );
		$has_validate = ! empty( $this->collect_schema_fields( static fn( $f ) => ! empty( $f['validate'] ) ) );

		if ( $has_encrypt || $has_validate ) {
			add_filter( 'pre_update_option_' . $this->settings_key, [ $this, 'pre_update_settings' ], 10, 2 );
		}

		if ( $has_encrypt ) {
			add_filter( 'option_' . $this->settings_key, [ $this, 'decrypt_settings' ] );
		}
	}

	/**
	 * Filter: validate-strip and encrypt the settings before they are persisted.
	 *
	 * Generic replacement for CCB's bespoke pre_update_settings(): drives both steps
	 * from the schema's `validate` / `encrypt` attributes.
	 *
	 * @since 0.4.0
	 * @param mixed $value     The settings array about to be saved.
	 * @param mixed $old_value The previously stored ( already decrypted ) settings.
	 * @return mixed
	 */
	public function pre_update_settings( $value, $old_value = false ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		// Validation ( defence in depth ): strip a malformed value so it never lands in
		// the database, keeping the prior valid value or blank. The user-facing error is
		// surfaced by the REST save walk / check_connection().
		foreach ( $this->collect_schema_fields( static fn( $f ) => ! empty( $f['validate'] ) ) as $f ) {
			$group = $f['group'];
			$field = $f['field'];

			if ( ! isset( $value[ $group ][ $field ] ) ) {
				continue;
			}

			$prior = ( is_array( $old_value ) && isset( $old_value[ $group ][ $field ] ) )
				? $old_value[ $group ][ $field ]
				: null;

			$value[ $group ][ $field ] = $this->validate_strip_at_option(
				$f['def']['validate'],
				$value[ $group ][ $field ],
				$prior
			);
		}

		// Encrypt marked credentials at rest.
		foreach ( $this->collect_schema_fields( static fn( $f ) => ! empty( $f['encrypt'] ) ) as $f ) {
			$group = $f['group'];
			$field = $f['field'];

			if ( isset( $value[ $group ][ $field ] ) && is_string( $value[ $group ][ $field ] ) && '' !== $value[ $group ][ $field ] ) {
				$value[ $group ][ $field ] = Encryption::encrypt( $value[ $group ][ $field ] );
			}
		}

		return $value;
	}

	/**
	 * Filter: transparently decrypt the encrypted settings on read.
	 *
	 * Legacy plaintext values ( stored before encryption existed ) are detected by
	 * Encryption::decrypt() and returned unchanged, then re-encrypted on the next save,
	 * so existing installs are never locked out.
	 *
	 * @since 0.4.0
	 * @param mixed $value The raw stored settings array.
	 * @return mixed
	 */
	public function decrypt_settings( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		foreach ( $this->collect_schema_fields( static fn( $f ) => ! empty( $f['encrypt'] ) ) as $f ) {
			$group = $f['group'];
			$field = $f['field'];

			if ( isset( $value[ $group ][ $field ] ) && is_string( $value[ $group ][ $field ] ) ) {
				$value[ $group ][ $field ] = Encryption::decrypt( $value[ $group ][ $field ] );
			}
		}

		return $value;
	}

	/**
	 * Apply an option-layer validation strip for a single field value.
	 *
	 * @since 0.4.0
	 * @param string $rule  The named validate rule.
	 * @param mixed  $value The value about to be saved.
	 * @param mixed  $prior The previously stored value for the same field.
	 * @return mixed The kept value ( original when valid, prior-valid or blank otherwise ).
	 */
	protected function validate_strip_at_option( $rule, $value, $prior ) {
		switch ( $rule ) {
			case 'subdomain':
				if ( is_string( $value ) && '' !== $value && ! preg_match( '/^[a-zA-Z0-9-]+\z/', $value ) ) {
					// Fallback notice for non-React consumers ( legacy admin ).
					update_option(
						'cp_settings_message',
						[
							'message' => esc_html__( 'Invalid subdomain. Subdomains may contain only letters, numbers, and hyphens.', 'cp-sync' ),
							'type'    => 'error',
						]
					);

					return ( is_string( $prior ) && preg_match( '/^[a-zA-Z0-9-]+\z/', $prior ) ) ? $prior : '';
				}
				return $value;
		}

		return $value;
	}

	/**
	 * Build REST endpoints based on filter configs
	 */
	public function register_filter_endpoints() {
		foreach ( $this->supported_integrations as $integration_type => $args ) {
			if ( ! isset( $args['filter_config'] ) ) {
				continue;
			}

			$filters = $args['filter_config']();

			foreach( $filters as $key => $filter) {
				if ( ! isset( $filter['options'] ) || ! is_callable( $filter['options'] ) ) {
					continue;
				}
	
				register_rest_route(
					'cp-sync/v1',
					"$this->id/selector/$integration_type/$key",
					[
						'methods'             => 'GET',
						'callback'            => $filter['options'],
						'args'                => $filter['rest_args'] ?? [],
						'permission_callback' => function () {
							return current_user_can( 'manage_options' );
						},
					]
				);
			}
		}
	}

	/**
	 * Save the token
	 */
	public function save_token( $token, $refresh_token = '' ) {
		$this->update_setting( 'token', $token, 'auth' );
		$this->update_setting( 'last_token_refresh', time(), 'auth' );

		if ( $refresh_token ) {
			$this->update_setting( 'refresh_token', $refresh_token, 'auth' );
		}
	}

	/**
	 * Get the token
	 */
	public function get_token() {
		return $this->get_setting( 'token', '', 'auth' );
	}

	/**
	 * Remove the token
	 *
	 * @author Tanner Moushey, 5/19/24
	 */
	public function remove_token() {
		$this->update_setting( 'token', '', 'auth' );
		$this->update_setting( 'last_token_refresh', '', 'auth' );
		$this->update_setting( 'refresh_token', '', 'auth' );
	}

	/**
	 * Get a single setting
	 * 
	 * @param string $key The key to get.
	 * @param mixed $default The default value to return.
	 * @param string|false $group The group to get the setting from.
	 */
	public function get_setting( $key, $default = '', $group = false ) {
		$data = get_option( $this->settings_key, [] );

		if ( $group ) {
			$data = isset( $data[ $group ] ) ? $data[ $group ] : [];
		}

		return isset( $data[$key] ) ? $data[$key] : $default; 
	}

	/**
	 * Set the provided option
	 *
	 * @param $key
	 * @param $value
	 *
	 * @return bool|string
	 *
	 * @author Tanner Moushey, 5/19/24
	 */
	public function update_setting( $key, $value, $group = false ) {
		if ( empty( $this->settings_key ) ) {
			return '';
		}

		$existing = get_option( $this->settings_key, [] );

		if ( $group ) {
			$existing[ $group ] = isset( $existing[ $group ] ) ? $existing[ $group ] : [];
			$existing[ $group ][ $key ] = $value;
		} else {
			$existing[ $key ] = $value;
		}

		return update_option( $this->settings_key, $existing );
	}

	/**
	 * Return the associated location id for the congregation id
	 *
	 * @param int $congregation_id The congregation id from PCO.
	 *
	 * @return false|mixed
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_location_term( $congregation_id ) {
		$map = $this->get_congregation_map();

		if ( isset( $map[ $congregation_id ] ) ) {
			return $map[ $congregation_id ];
		}

		return false;
	}

	/**
	 * A map of values to associate the ChMS congregation ID with the correct Location
	 *
	 * @return mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function get_congregation_map() {
		return apply_filters( 'cp_sync_congregation_map', [] );
	}

	/**
	 * Utility to turn an aribratray string into a useable slug
	 *
	 * @param string $string The string to convert.
	 * @return string
	 */
	public static function string_to_slug( $string ) {
		return str_replace( ' ', '_', strtolower( $string ) );
	}

	/**
	 * Get preview feed
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 * @since 1.0.0
	 */
	public function get_data_preview( $request ) {
		// integration type
		$integration_type = $request->get_param( 'type' );

		if ( ! $this->supports( $integration_type ) ) {
			return new ChMSError( 'unsupported_integration_type', 'The integration type is not supported' );
		}

		$data = $this->get_formatted_data( null, $integration_type, 10 );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// format data into normalized response
		$preview = [];

		foreach ( $data['posts'] as $item ) {
			$preview_item = [
				'chmsID'    => $item['chms_id'] ?? '',
				'title'     => $item['post_title'] ?? '',
				'content'   => $item['post_content'] ?? '',
				'thumbnail' => $item['thumbnail_url'] ?? '',
				'meta'      => [],
				'fields'    => []
			];

			// Add taxonomy terms
			foreach ( $item['tax_input'] as $taxonomy => $term_ids ) {
				$tax_label = $data['taxonomies'][ $taxonomy ]['plural_label'];
				$tax_terms = $data['taxonomies'][ $taxonomy ]['terms'];

				$preview_item['meta'][ $tax_label ] = array_map( fn( $term_id ) => $tax_terms[ $term_id ], $term_ids );
			}

			// Add relevant meta fields based on integration type
			if ( 'groups' === $integration_type ) {
				// Groups: show leader, meeting info, capacity, childcare
				$meta_input = $item['meta_input'] ?? [];

				if ( ! empty( $meta_input['leader'] ) ) {
					$preview_item['fields']['Leader'] = $meta_input['leader'];
				}

				if ( ! empty( $meta_input['leader_email'] ) ) {
					$preview_item['fields']['Email'] = $meta_input['leader_email'];
				}

				if ( ! empty( $meta_input['time_desc'] ) ) {
					$preview_item['fields']['Meeting Time'] = $meta_input['time_desc'];
				}

				if ( ! empty( $meta_input['meeting_day'] ) ) {
					$preview_item['fields']['Meeting Day'] = $meta_input['meeting_day'];
				}

				// Childcare indicator
				if ( isset( $meta_input['kid_friendly'] ) && 'on' === $meta_input['kid_friendly'] ) {
					$preview_item['fields']['Childcare'] = '✓ Available';
				}

				// Group full indicator
				if ( isset( $meta_input['is_group_full'] ) && 'on' === $meta_input['is_group_full'] ) {
					$preview_item['fields']['Status'] = '🔒 Full';
				}

			} elseif ( 'events' === $integration_type ) {
				// Events: show date, time, location, duration
				if ( ! empty( $item['EventStartDate'] ) ) {
					$start_date = strtotime( $item['EventStartDate'] );
					$start_date_formatted = date( 'D, M j, Y', $start_date );

					// Check if we have an end date
					if ( ! empty( $item['EventEndDate'] ) ) {
						$end_date = strtotime( $item['EventEndDate'] );
						$end_date_formatted = date( 'D, M j, Y', $end_date );

						// Check if it's a multi-day event
						if ( date( 'Y-m-d', $start_date ) !== date( 'Y-m-d', $end_date ) ) {
							// Multi-day event: show date range
							$preview_item['fields']['Dates'] = $start_date_formatted . ' - ' . $end_date_formatted;
						} else {
							// Single-day event: just show start date
							$preview_item['fields']['Date'] = $start_date_formatted;
						}

						// Show time range
						$preview_item['fields']['Time'] = date( 'g:i A', $start_date ) . ' - ' . date( 'g:i A', $end_date );

						// Calculate duration
						$duration = ( $end_date - $start_date ) / 60; // minutes
						if ( $duration >= 60 ) {
							$hours = floor( $duration / 60 );
							$mins = $duration % 60;
							$duration_str = $hours . 'h';
							if ( $mins > 0 ) {
								$duration_str .= ' ' . $mins . 'm';
							}
							$preview_item['fields']['Duration'] = $duration_str;
						} else {
							$preview_item['fields']['Duration'] = $duration . ' min';
						}
					} else {
						// No end date, just show start
						$preview_item['fields']['Date'] = $start_date_formatted;
						$preview_item['fields']['Time'] = date( 'g:i A', $start_date );
					}
				}

				// Event venue
				if ( ! empty( $item['EventVenue']['venue'] ) ) {
					$preview_item['fields']['Location'] = $item['EventVenue']['venue'];
				}

				// Event organizer
				if ( ! empty( $item['EventOrganizer']['organizer'] ) ) {
					$preview_item['fields']['Organizer'] = $item['EventOrganizer']['organizer'];
				}

				// Registration URL
				$meta_input = $item['meta_input'] ?? [];
				if ( ! empty( $meta_input['registration_url'] ) ) {
					$preview_item['fields']['Registration'] = '✓ Required';
				}
			}

			$preview[] = $preview_item;
		}

		$response = [
			'items' => $preview,
			'count' => $data['count'] ?? 0,
		];

		// Add date range info for events
		if ( 'events' === $integration_type && method_exists( $this, 'get_active_date_range' ) ) {
			$date_range = $this->get_active_date_range();
			$response['date_start'] = $date_range['start'];
			$response['date_end'] = $date_range['end'];
			$response['date_mode'] = $date_range['mode'];
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Setup integrations
	 *
	 * @since 1.0.0
	 */
	public function setup() {}

	/**
	 * Check the connection to the ChMS
	 *
	 * @since  1.0.4
	 *
	 * @return bool | array
	 */
	public function check_connection() {
		return false;
	}

	/**
	 * Register ChMS specific rest routes
	 *
	 * @since 1.1.0
	 */
	public function register_rest_routes() {
		$this->register_filter_endpoints();

		$this->add_rest_route(
			"preview/(?P<type>[a-zA-Z0-9-]+)",
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_data_preview' ],
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			],

		);

		$this->add_rest_route(
			'check-connection',
			[
				'methods'  => 'GET',
				'callback' => function () {
					$data = $this->check_connection();

					if ( ! $data ) {
						return rest_ensure_response( [ 'connected' => false, 'message' => __( 'No connection data found', 'cp-sync' ) ] );
					}

					return rest_ensure_response(
						[
							'connected' => 'success' === $data['status'],
							'message'   => $data['message'],
						]
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			]
		);
		
		$this->add_rest_route(
			'disconnect',
			[
				'methods'  => 'POST',
				'callback' => function ( $request ) {
					try {
						$this->remove_token();
						return rest_ensure_response( [ 'success' => true ] );
					} catch ( \Exception $e ) {
						return new \WP_Error( 'authentication_failed', $e->getMessage(), [ 'status' => 401 ] );
					}
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				}
			]
		);

		$this->add_rest_route(
			'sync-status',
			[
				'methods'  => 'GET',
				'callback' => function ( $request ) {
					$type = $request->get_param( 'type' );
					$is_syncing = $this->is_sync_in_progress( $type );

					$response = [
						'is_syncing' => $is_syncing,
						'type'       => $type,
					];

					// If checking specific type and it's syncing, add more details
					if ( $is_syncing && $type ) {
						$response['message'] = sprintf(
							/* translators: %s: the integration type being synced (e.g. Groups, Events) */
							__( '%s sync is currently in progress', 'cp-sync' ),
							ucfirst( $type )
						);
					} elseif ( $is_syncing ) {
						$response['message'] = __( 'A sync is currently in progress', 'cp-sync' );
					}

					return rest_ensure_response( $response );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args' => [
					'type' => [
						'description' => __( 'The integration type to check (groups, events, etc.)', 'cp-sync' ),
						'type'        => 'string',
						'required'    => false,
					],
				],
			]
		);

		$this->add_rest_route(
			'cancel-sync',
			[
				'methods'  => 'POST',
				'callback' => function ( $request ) {
					$type = $request->get_param( 'type' );
					$cancelled = $this->cancel_sync( $type );

					$response = [
						'success'   => $cancelled,
						'type'      => $type,
					];

					if ( $cancelled ) {
						if ( $type ) {
							$response['message'] = sprintf(
								/* translators: %s: the integration type being synced (e.g. Groups, Events) */
								__( '%s sync has been cancelled', 'cp-sync' ),
								ucfirst( $type )
							);
						} else {
							$response['message'] = __( 'All syncs have been cancelled', 'cp-sync' );
						}
					} else {
						$response['message'] = __( 'No sync found to cancel', 'cp-sync' );
					}

					return rest_ensure_response( $response );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'args' => [
					'type' => [
						'description' => __( 'The integration type to cancel (groups, events, etc.)', 'cp-sync' ),
						'type'        => 'string',
						'required'    => false,
					],
				],
			]
		);
	}

	/**
	 * Check if a sync is currently in progress
	 *
	 * @param string|null $integration_type Optional. Check specific integration type (groups, events). If null, checks all.
	 * @return bool True if sync is in progress, false otherwise
	 */
	public function is_sync_in_progress( $integration_type = null ) {
		global $wpdb;

		if ( $integration_type ) {
			$action = "pull_{$integration_type}";
			$identifier = "wp_{$action}";

			// Check for background process batches first (most reliable indicator)
			// Format: wp_pull_{type}_batch_{id}
			$key = $wpdb->esc_like( "{$identifier}_batch_" ) . '%';
			$count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
				$key
			) );

			if ( $count > 0 ) {
				return true;
			}

			// Check for process lock transient (indicates active processing)
			if ( get_site_transient( "{$identifier}_process_lock" ) ) {
				return true;
			}

			// Check if cron job is scheduled (indicates queued sync)
			$cron_hook = "{$identifier}_cron";
			if ( wp_next_scheduled( $cron_hook ) !== false ) {
				return true;
			}

			return false;
		} else {
			// Check all supported integration types
			$types = array_keys( $this->supported_integrations );

			if ( empty( $types ) ) {
				return false;
			}

			// First check for any batch jobs (most reliable)
			$patterns = [];
			foreach ( $types as $type ) {
				$action = "pull_{$type}";
				$patterns[] = $wpdb->esc_like( "wp_{$action}_batch_" ) . '%';
			}

			$where_clauses = array_fill( 0, count( $patterns ), 'option_name LIKE %s' );
			$where = implode( ' OR ', $where_clauses );

			$count = $wpdb->get_var( $wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $where is a constructed fragment of hardcoded "option_name LIKE %s" placeholders (no user input); actual LIKE values are passed as $patterns to prepare(). $wpdb->options is a WP core table property.
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE {$where}",
				...$patterns
			) );

			if ( $count > 0 ) {
				return true;
			}

			// Check for any process lock transients
			foreach ( $types as $type ) {
				$action = "pull_{$type}";
				if ( get_site_transient( "wp_{$action}_process_lock" ) ) {
					return true;
				}
			}

			// Check for any scheduled cron jobs
			foreach ( $types as $type ) {
				$action = "pull_{$type}";
				$cron_hook = "wp_{$action}_cron";
				if ( wp_next_scheduled( $cron_hook ) !== false ) {
					return true;
				}
			}

			return false;
		}
	}

	/**
	 * Cancel a sync in progress
	 *
	 * @param string|null $integration_type Optional. Cancel specific integration type (groups, events). If null, cancels all.
	 * @return bool True if sync was cancelled, false otherwise
	 */
	public function cancel_sync( $integration_type = null ) {
		global $wpdb;

		// Delete batch jobs from wp_options
		// Format: wp_pull_{type}_batch_{id}
		if ( $integration_type ) {
			$action = "pull_{$integration_type}";
			$identifier = "wp_{$action}";
			$key = $wpdb->esc_like( "{$identifier}_batch_" ) . '%';

			$deleted = $wpdb->query( $wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$key
			) );

			// Delete the process lock transient
			delete_site_transient( "{$identifier}_process_lock" );

			// Clear the cron healthcheck to stop processing
			$cron_hook = "{$identifier}_cron";
			wp_clear_scheduled_hook( $cron_hook );

			return $deleted > 0 || wp_next_scheduled( $cron_hook ) === false;
		} else {
			// Cancel all integration types
			$deleted = 0;
			$cancelled = false;

			foreach ( array_keys( $this->supported_integrations ) as $type ) {
				$action = "pull_{$type}";
				$identifier = "wp_{$action}";
				$key = $wpdb->esc_like( "{$identifier}_batch_" ) . '%';

				$deleted += $wpdb->query( $wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$key
				) );

				// Delete the process lock transient
				delete_site_transient( "{$identifier}_process_lock" );

				// Clear the cron healthcheck to stop processing
				$cron_hook = "{$identifier}_cron";
				wp_clear_scheduled_hook( $cron_hook );

				if ( wp_next_scheduled( $cron_hook ) === false ) {
					$cancelled = true;
				}
			}

			return $deleted > 0 || $cancelled;
		}
	}

	/**
	 * Shortcut for registering a rest route
	 *
	 * @param string $route The route to register.
	 * @param array  $args The args to register the route with.
	 */
	public function add_rest_route( $path, $args ) {
		register_rest_route( 'cp-sync/v1', "/$this->id/$path", $args );
	}

	/**
	 * Add support for an integration type
	 *
	 * @param string $integration_type The integration type to add support for.
	 * @param array  $args The args to register handlers for the integration.
	 */
	public function add_support( $integration_type, $args ) {
		$this->supported_integrations[ $integration_type ] = $args;
	}

	/**
	 * Check if the ChMS supports the integration type
	 *
	 * @param string $integration_type The integration type to check.
	 * @return bool
	 */
	public function supports( $integration_type ) {
		return isset( $this->supported_integrations[ $integration_type ] );
	}

	/**
	 * Get all data from the ChMS
	 *
	 * @param array|null $existing_data The existing data that has been pulled.
	 * @param string $integration_type The integration type to get data for.
	 * @param number $limit The number of items to pull.
	 * @return array|ChMSError
	 */
	public function get_formatted_data( $existing_data, $integration_type, $limit = 0 ) {
		$integration_args = $this->supported_integrations[ $integration_type ];

		if ( ! is_callable( $integration_args['fetch_callback'] ) ) {
			return new ChMSError( 'fetch_callback_not_set', 'The fetch callback is not set' );
		}

		if ( ! is_callable( $integration_args['format_callback'] ) ) {
			return new ChMSError( 'format_callback_not_set', 'The format callback is not set' );
		}

		$data = call_user_func( $integration_args['fetch_callback'], $limit );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$formatted_items = [];

		$items      = $data['items'];
		$context    = $data['context'];
		$taxonomies = $data['taxonomies'] ?? [];

		$item_count = count( $items );

		for ( $i = 0; $i < $item_count; $i++ ) {
			$item = $items[ $i ];

			if ( $limit > 0 && count( $formatted_items ) >= $limit ) {
				break;
			}

			try {
				$data = call_user_func( $integration_args['format_callback'], $item, $context );
			} catch ( ChMSException $e ) {

				// check if it is a rate limit error
				if ( 429 === $e->getCode() ) {
					$seconds = $e->getData()['wait'] ?? 5;

					sleep( $seconds ); // wait for the rate limit to reset

					// reprocess the item
					$i--;
					continue;
				} else {
					error_log( $e->getMessage() );
					continue; // skip the item
				}
			}

			if ( $data ) {
				$formatted_items[] = $data;
			}			
		}

		return [
			'posts'      => $formatted_items,
			'taxonomies' => $taxonomies,
			'count'      => $item_count,
		];
	}
}

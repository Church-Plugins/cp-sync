<?php
/**
 * Utility class for managing and filtering a list of data based on a set of filters
 *
 * @package CP_Connect
 */

namespace CP_Connect\Setup;

/**
 * DataFilter class
 *
 * @since 1.1.0
 */
class DataFilter {

	/**
	 * The filter type. Can be 'all' or 'any'.
	 *
	 * @var string
	 */
	protected $type = 'all';

	/**
	 * The filter conditions.
	 *
	 * @var array
	 */
	protected $conditions = array();

	/**
	 * Definitions for the filter types
	 *
	 * @var array
	 */
	protected $filter_config = array();

	/**
	 * Relational data.
	 *
	 * @var array
	 */
	protected $relational_data = array();

	/**
	 * Class constructor
	 *
	 * @param string $type Filter type.
	 * @param array  $conditions Filter conditions.
	 * @param array  $filter_config Filter config.
	 * @param array  $relational_data Relational data.
	 */
	public function __construct( $type = 'all', $conditions = [], $filter_config = [], $relational_data = [] ) {
		$this->type            = $type;
		$this->conditions      = $conditions;
		$this->relational_data = $relational_data;
		$this->filter_config   = $filter_config;
	}

	/**
	 * Get the comparison options
	 *
	 * @return array The comparison options.
	 */
	public static function get_compare_options() {
		return [
			'is' => [
				'label'   => __( 'Is', 'cp-connect' ),
				'compare' => fn( $data, $value ) => $data === $value,
				'type'    => 'inherit',
 			],
			'is_not' => [
				'label'  	=> __( 'Is Not', 'cp-connect' ),
				'compare' => fn( $data, $value ) => $data !== $value,
				'type'    => 'inherit',
			],
			'contains' => [
				'label'  	=> __( 'Contains', 'cp-connect' ),
				'compare' => fn( $data, $value ) => strpos( $data, $value ) !== false,
				'type'    => 'text',
			],
			'does_not_contain' => [
				'label'  	=> __( 'Does Not Contain', 'cp-connect' ),
				'compare' => fn( $data, $value ) => strpos( $data, $value ) === false,
				'type'    => 'text',
			],
			'is_greater_than' => [
				'label'  	=> __( 'Is Greater Than', 'cp-connect' ),
				'compare' => fn( $data, $value ) => $data > $value,
				'type'    => 'text',
			],
			'is_less_than' => [
				'label'  	=> __( 'Is Less Than', 'cp-connect' ),
				'compare' => fn( $data, $value ) => $data < $value,
				'type'    => 'text',
			],
			'is_empty' => [
				'label'  	=> __( 'Is Empty', 'cp-connect' ),
				'compare' => fn( $data, $value ) => empty( $data ),
				'type'    => 'bool',
			],
			'is_not_empty' => [
				'label'  	=> __( 'Is Not Empty', 'cp-connect' ),
				'compare' => fn( $data, $value ) => ! empty( $data ),
				'type'    => 'bool',
			],
			'is_in'           => [
				'label'  	=> __( 'Is in', 'cp-connect' ),
				'compare' => fn( $data, $value ) => in_array( $data, $value, true ),
				'type'    => 'multi',
			]
		];
	}

	/**
	 * Apply the filter to the data
	 *
	 * @param array $data The data to filter.
	 * @return array|\WP_Error The filtered data or WP_Error on error.
	 */
	public function apply( &$items ) {
		if ( 'all' === $this->type ) {
			// Filter the results with the all filter types
			foreach ( $this->conditions as $condition ) {
				$item_count = count( $items );
				for ( $i = 0; $i < $item_count; $i++ ) {
					$item = $items[ $i ];

					$pass = $this->passes_condition( $item, $condition );

					if ( is_wp_error( $pass ) ) {
						return $pass;
					}
					
					// remove items on each pass that don't meet the condition
					if ( ! $pass ) {
						array_splice( $items, $i--, 1 );
						$item_count--;
					}
				}
			}
		} else if ( 'any' === $this->type ) {
			$filtered = [];

			foreach ( $this->conditions as $condition ) {
				$item_count = count( $items );
				for ( $i = 0; $i < $item_count; $i++ ) {
					$item = $items[ $i ];

					$pass = $this->passes_condition( $item, $condition );

					if ( is_wp_error( $pass ) ) {
						return $pass;
					}

					if ( $pass ) {
						$filtered[] = $item;
						array_splice( $items, $i--, 1 );
						$item_count--;
						break;
					}
				}
			}

			$items = $filtered; // reset items to the filtered array
		}
	}

	/**
	 * Check if an item passes the filter
	 *
	 * @param array $item The item to check.
	 * @return bool|\WP_Error True if the item passes the filter, false otherwise. WP_Error on error.
	 */
	public function check( $item ) {
		foreach ( $this->conditions as $condition ) {
			$pass = $this->passes_condition( $item, $condition );

			if ( is_wp_error( $pass ) ) {
				return $pass;
			}

			if ( $pass && 'any' === $this->type ) {
				return true; // return true if any of the conditions passed
			}

			if ( ! $pass && 'all' === $this->type ) {
				return false; // if we failed a condition when they all should pass
			}
		}

		// the only way we get here as 'all' is if all conditions passed
		// the only we get here as 'any' is if none of the conditions passed
		// therefore, return true for 'all' and false for 'any'
		return 'all' === $this->type;
	}

	/**
	 * Check if an item passes a single condition
	 *
	 * @param array $item The item to check.
	 * @param array $condition The condition to check.
	 * @return bool|\WP_Error True if the item passes the condition, false otherwise. WP_Error on error.
	 */
	protected function passes_condition( $item, $condition ) {
		$compare_options = self::get_compare_options();
		$compare         = $condition['compare'] ?? null;
		$value           = $condition['value'] ?? null;
		$type            = $condition['type'] ?? null;
		$path            = $this->filter_config[ $type ]['path'] ?? null;
		$relation        = $this->filter_config[ $type ]['relation'] ?? false;
		$relation_path   = $this->filter_config[ $type ]['relation_path'] ?? null;

		if ( null === $compare || null === $type ) {
			return new \WP_Error( 'invalid_condition', __( 'Invalid condition', 'cp-connect' ) );
		}

		if ( null === $path ) {
			return new \WP_Error( 'invalid_filter_config', __( 'Invalid filter config: path not specified', 'cp-connect' ) );
		}

		$compare_fn = $compare_options[ $compare ]['compare']; // get the comparison callback
		$data       = $this->get_val( $item, $path );

		// parse related data
		if ( $relation ) { 
			if ( null === $relation_path ) {
				return new \WP_Error( 'invalid_filter_config', __( 'Invalid filter config: missing a relational data path', 'cp-connect' ) );
			}

			if ( ! isset( $this->relational_data[ $relation ][ $data ] ) ) {
				return new \WP_Error( 'invalid_filter_config', __( 'Invalid filter config: missing relational data', 'cp-connect' ) );
			}

			// the data should be the related item's ID
			$relation_data = $this->relational_data[ $relation ][ $data ];
			$data          = $this->get_val( $relation_data, $relation_path );
		}

		return (bool) $compare_fn( $data, $value );
	}

	/**
	 * Get a value from a nested array
	 *
	 * @param mixed  $data The data to search.
	 * @param string $path The path to the value.
	 * @param mixed  $default The default value to return if the path is not found.
	 * @return mixed The value at the path or the default value.
	 */
	public function get_val( $data, $path, $default = null ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$parts = explode( '.', $path );

		foreach ( $parts as $part ) {
			if ( ! isset( $data[ $part ] ) ) {
				return $default;
			}

			$data = $data[ $part ];
		}

		return $data;
	}
}

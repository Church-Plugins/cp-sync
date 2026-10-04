<?php
/**
 * A helper class for interacting with the CCB API
 *
 * @package CP_Sync
 * @since 1.2.0
 */

namespace CP_Sync\ChMS\API;

/**
 * CCB class
 */
class CCB {
	/**
	 * Base URL for the CCB API
	 *
	 * @var string
	 */
	public $base_url = '';

	/**
	 * Username for Basic Authentication
	 *
	 * @var string
	 */
	private $username = '';

	/**
	 * Password for Basic Authentication
	 *
	 * @var string
	 */
	private $password = '';
	
	/**
	 * Error
	 *
	 * @var WP_Error|null
	 */
	private $error = null;

	/**
	 * The request data
	 *
	 * @var array|null
	 */
	protected $response = null;

	/**
	 * Get the error
	 *
	 * @return WP_Error|null
	 */
	public function get_error() {
		return $this->error;
	}

	/**
	 * Set the API credentials
	 *
	 * The subdomain is interpolated into the API base URL, so it is validated
	 * against a strict allowlist ( letters, numbers, hyphens ) before use. This
	 * prevents an attacker-controlled subdomain from redirecting requests to an
	 * arbitrary host ( SSRF ) via values such as "evil.com/x#", "evil.com/",
	 * "@evil.com", or path-traversal sequences. Validation also happens where the
	 * setting is saved; this is the defensive last line before the value is used.
	 *
	 * @param string $username The API username
	 * @param string $password The API password
	 * @param string $subdomain The CCB subdomain
	 * @return void
	 */
	public function set_credentials( $username, $password, $subdomain ) {
		$this->username = $username;
		$this->password = $password;

		if ( self::is_valid_subdomain( $subdomain ) ) {
			$this->base_url = "https://{$subdomain}.ccbchurch.com/api.php";
			$this->error    = null;
		} else {
			// Refuse to build a URL from an invalid subdomain. request() will bail.
			$this->base_url = '';

			// Only flag an explicit error for a non-empty (i.e. malformed) value;
			// an empty subdomain simply means credentials are not configured yet.
			if ( is_string( $subdomain ) && '' !== $subdomain ) {
				$this->error = new \WP_Error(
					'ccb_invalid_subdomain',
					'Invalid CCB subdomain. Subdomains may contain only letters, numbers, and hyphens.'
				);
			}
		}
	}

	/**
	 * Validate a CCB subdomain.
	 *
	 * CCB subdomains are simple DNS labels, so anything outside the allowed
	 * character set is rejected.
	 *
	 * @param string $subdomain The subdomain to validate.
	 * @return bool
	 */
	public static function is_valid_subdomain( $subdomain ) {
		return is_string( $subdomain ) && (bool) preg_match( '/^[a-zA-Z0-9-]+\z/', $subdomain );
	}

	/**
	 * Make a request to the CCB API
	 *
	 * @param string $method The request method
	 * @param string $service The service name (maps to 'srv' parameter)
	 * @param array $args The arguments to pass to the request
	 * @return array|WP_Error
	 */
	public function request( $method, $service, $args = [] ) {
		// Refuse to make a request when the base URL was not built from a valid
		// subdomain. This closes the SSRF vector at the point of use, even if an
		// invalid subdomain somehow reached set_credentials().
		if ( empty( $this->base_url ) ) {
			$error = $this->error instanceof \WP_Error
				? $this->error
				: new \WP_Error( 'ccb_no_base_url', 'CCB API base URL is not configured' );

			$this->error = $error;
			return $error;
		}

		// CCB API uses query parameters with 'srv' for the service
		if ( ! empty( $service ) ) {
			$args['srv'] = $service;
		}

		// Build URL with query parameters
		$url = add_query_arg( $args, $this->base_url );

		$this->error = null;

		$response = wp_remote_request( $url, [
			'method'  => $method,
			'timeout' => 30, // Increase timeout for large event lists
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $this->username . ':' . $this->password ),
			],
		] );

		$this->response = $response;

		if ( is_wp_error( $response ) ) {
			$this->error = $response;
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			$error_message = "CCB API request failed with status code: {$response_code}";
			$this->error = new \WP_Error( 'ccb_api_error', $error_message );
			return $this->error;
		}

		$body = wp_remote_retrieve_body( $response );

		// CCB API returns XML, not JSON
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NOCDATA );

		if ( false === $xml ) {
			$this->error = new \WP_Error( 'ccb_xml_parse_error', 'Failed to parse CCB API XML response' );
			return $this->error;
		}

		// Convert XML to array, preserving attributes
		// json_encode on SimpleXML loses attributes, so we need custom conversion
		$data = $this->simplexml_to_array( $xml );

		return $data;
	}

	/**
	 * Convert SimpleXMLElement to array, preserving attributes
	 *
	 * @param \SimpleXMLElement $xml The SimpleXML element
	 * @return array|string
	 */
	private function simplexml_to_array( $xml ) {
		$array = [];

		// Get attributes
		$attr_obj = $xml->attributes();
		if ( $attr_obj && count( $attr_obj ) > 0 ) {
			$array['@attributes'] = [];
			foreach ( $attr_obj as $key => $value ) {
				$array['@attributes'][ (string) $key ] = (string) $value;
			}
		}

		// Get child elements - iterate through actual SimpleXML children
		foreach ( $xml->children() as $child_name => $child_node ) {
			$child_array = $this->simplexml_to_array( $child_node );

			// Handle multiple children with the same name
			if ( isset( $array[ $child_name ] ) ) {
				// Already exists - convert to numeric array if not already
				if ( ! isset( $array[ $child_name ][0] ) ) {
					$array[ $child_name ] = [ $array[ $child_name ] ];
				}
				$array[ $child_name ][] = $child_array;
			} else {
				$array[ $child_name ] = $child_array;
			}
		}

		// Handle text content
		$text = trim( (string) $xml );
		$has_attributes = isset( $array['@attributes'] );
		$has_children = count( $array ) > ( $has_attributes ? 1 : 0 );

		// Element with no children and no attributes - return plain text
		if ( ! $has_children && ! $has_attributes ) {
			return ! empty( $text ) ? $text : '';
		}

		// Element with text and attributes but no child elements
		if ( ! $has_children && $has_attributes && ! empty( $text ) ) {
			$array['@value'] = $text;
		}

		// Element with only attributes and no text - just return array with attributes
		// Element with children - return array as-is

		return $array;
	}	

	/**
	 * Make a GET request to the CCB API
	 *
	 * @param string $path The path to request
	 * @param array $args The arguments to pass to the request
	 * @return mixed
	 */
	public function get( $path, $args = [] ) {
		return $this->request( 'GET', $path, $args );
	}

	/**
	 * Make a POST request to the CCB API
	 *
	 * @param string $path The path to request
	 * @param array $args The arguments to pass to the request
	 * @return mixed
	 */
	public function post( $path, $args = [] ) {
		return $this->request( 'POST', $path, $args );
	}

}

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
	 * @param string $username The API username
	 * @param string $password The API password
	 * @param string $subdomain The CCB subdomain
	 * @return void
	 */
	public function set_credentials( $username, $password, $subdomain ) {
		$this->username = $username;
		$this->password = $password;
		$this->base_url = "https://{$subdomain}.ccbchurch.com/api.php";
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
		$xml = simplexml_load_string( $body );

		if ( false === $xml ) {
			$this->error = new \WP_Error( 'ccb_xml_parse_error', 'Failed to parse CCB API XML response' );
			return $this->error;
		}

		// Convert XML to array for easier handling
		$json = json_encode( $xml );
		$data = json_decode( $json, true );

		return $data;
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

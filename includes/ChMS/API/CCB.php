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
	protected $base_url = 'https://api.ccbchurch.com/';

	/**
	 * Access token
	 *
	 * @var string
	 */
	private $access_token = '';
	
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
	 * Set the access token
	 *
	 * @param string $access_token The access token
	 * @return void
	 */
	public function set_access_token( $access_token ) {
		$this->access_token = $access_token;
	}

	/**
	 * Make a request to the CCB API
	 *
	 * @param string $method The request method
	 * @param string $path The path to request
	 * @param array $args The arguments to pass to the request
	 * @return array|WP_Error
	 */
	public function request( $method, $path, $args = [] ) {
		$url = $this->base_url . $path;

		$this->error = null;

		$response = wp_remote_request( $url, [
			'method'  => $method,
			'headers' => [
				'Authorization' => 'Bearer ' . $this->access_token,
			],
			'body'    => $args,
		] );

		$this->response = $response;

		if ( is_wp_error( $response ) ) {
			$this->error = $response;
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );

		return json_decode( $body, true );
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

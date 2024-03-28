<?php
namespace CP_Connect\Setup;

/**
 * Convenience helpers for the plugin
 *
 * @since 1.0.16
 * @author costmo
 */
class Convenience {

	/**
	 * @var
	 */
	protected static $_instance;

	/**
	 * Only make one instance of _Init
	 *
	 * @since 1.0.16
	 * @return Convenience
	 */
	public static function get_instance() {
		if ( ! self::$_instance instanceof Convenience ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Class constructor: Largely for academic completeness
	 *
	 * @since 1.0.16
	 * @return void
	 */
	protected function __construct() {

	}

	/**
	 * Force writing a message to a unique log file.
	 *
	 * Useful for when we've /dev/null'd stdout (e.g. AJAX and cron requests) or the PHP error log is too chatty
	 *
	 * Writes to wp-content/uploads/[hostname]-[date].log
	 *
	 * Can also write to query-monitor for easier front-end debugging (requires activated query-monitor plugin)
	 *
	 * @param String $message
	 * @since 1.0.16
	 * @return void
	 * @author costmo
	 */
	public static function log( $message )
	{
		// return; // Uncomment to disable logging for production

		// Enable to also watch from the browser through the Query Monitor plugin
		$also_qm_debug = false;

		// Generate a file name and output path
		$parsed = parse_url( get_site_url() );
		$file_name = self::slugify( $parsed['host'], '_' ) . '-' . date( 'Y-m-d' ) . ".log";
		$output_path = WP_CONTENT_DIR . '/uploads/' . $file_name;

		// Add a timestamp to the message...
		// ... and normalize to string and format improvment(s)
		if( empty( $message ) || (!is_string( $message ) && !is_numeric( $message )) ) { //
			$message = "[" . date( 'Y-m-d H:i:s' ) . "]\n" . var_export( $message, true );
		} else {
			$message = "[" . date( 'Y-m-d H:i:s' ) . "] " . $message;
		}

		// Write the message to file
		$fp = @fopen( $output_path, 'a' ); // costmo
		if( false !== $fp ) {
			fwrite( $fp, $message . "\n" );
			fclose( $fp );
		}

		// If we're watching from the browser (doesn't work for AJAX requests)
		if( $also_qm_debug ) {
			self::qmd( $message, 'debug' );
		}
	}

	/**
	 * Send a debug message to Query Monitor's Log panel
	 *
	 * Query Monitor log actions don't trigger on AJAX requests
	 *
	 * @param mixed $input			Typically, the message to log (string, array, etc.)
	 * @param string $level			The alert level. Default is `debug`
	 * @return void
	 * @since 1.0.16
	 * @author costmo
	 * @see https://querymonitor.com/docs/logging-variables/
	 */
	public static function qmd( $message, $level = 'debug' ) {
		// To match a Query Monitor action
		$valid_alert_levels = [ 'emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug' ];

		// Sanity check and normalize possible input
		if( empty( $level ) || !is_string( $level ) || !in_array( $level, $valid_alert_levels ) ) {
			$level = 'debug';
		}

		// If query-monitor is not present, this will silently do nothing
		do_action( "qm/" . $level, $message );
	}

	/**
	 * Turn a normal string into a slug
	 *
	 * @param String $input					// Input to sanitize
	 * @param String $replacement_char		// Character to use in place of invalid chars
	 * @return String
	 * @since 1.0.16
	 * @author costmo
	 */
	public static function slugify( $input, $replacement_char = "-" ) {
		$slug = strtolower( stripslashes( strip_tags( $input ) ) );

		// Sanity check and normalize
		if( !empty( $replacement_char ) && is_string( $replacement_char ) && strlen( $replacement_char ) === 1 ) {
			$replacement_char = $replacement_char;
		} else {
			$replacement_char = "-";
		}

		return preg_replace( "/[^a-z0-9\-]/", $replacement_char, $slug );
	}

	/**
	 * Convert a slug (or slug-like string) to a human-friendly string
	 *
	 * @param String $slug					// Input to munge
	 * @return String $replacement_char		// Character to use in place of commonly used slug chars
	 * @since 1.0.16
	 * @return String
	 * @author costmo
	 */
	public static function de_slugify( $slug, $replacement_char = " " ) {
		$slug = stripslashes( strip_tags( $slug ) );

		// Sanity check and normalize
		if( !empty( $replacement_char ) && is_string( $replacement_char ) && strlen( $replacement_char ) === 1 ) {
			$replacement_char = $replacement_char;
		} else {
			$replacement_char = " ";
		}

		return preg_replace( "/[\-\_]/", $replacement_char, $slug );
	}

}
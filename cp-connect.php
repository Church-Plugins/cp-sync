<?php
/**
 * Plugin Name: CP Connect
 * Plugin URL: https://churchplugins.com
 * Description: ChurchPlugins integration plugin for ChMS
 * Version: 1.0.3
 * Author: Church Plugins
 * Author URI: https://churchplugins.com
 * Text Domain: cp-connect
 * Domain Path: languages
 */

if( !defined( 'CP_CONNECT_PLUGIN_VERSION' ) ) {
	 define ( 'CP_CONNECT_PLUGIN_VERSION',
	 	'1.0.3'
	);
}

require_once( dirname( __FILE__ ) . "/includes/Constants.php" );

require_once( CP_CONNECT_PLUGIN_DIR . "/includes/ChurchPlugins/init.php" );
require_once( CP_CONNECT_PLUGIN_DIR . 'vendor/autoload.php' );


use CP_Connect\_Init as Init;

/**
 * @var CP_Connect\_Init
 */
global $cp_connect;
$cp_connect = cp_connect();

/**
 * @return CP_Connect\_Init
 */
function cp_connect() {
	return Init::get_instance();
}

/**
 * Load plugin text domain for translations.
 *
 * @return void
 */
function cp_connect_load_textdomain() {

	// Traditional WordPress plugin locale filter
	$get_locale = get_user_locale();

	/**
	 * Defines the plugin language locale used in RCP.
	 *
	 * @var string $get_locale The locale to use. Uses get_user_locale()` in WordPress 4.7 or greater,
	 *                  otherwise uses `get_locale()`.
	 */
	$locale        = apply_filters( 'plugin_locale',  $get_locale, 'cp-connect' );
	$mofile        = sprintf( '%1$s-%2$s.mo', 'cp-connect', $locale );

	// Setup paths to current locale file
	$mofile_global = WP_LANG_DIR . '/cp-connect/' . $mofile;

	if ( file_exists( $mofile_global ) ) {
		// Look in global /wp-content/languages/cp-connect folder
		load_textdomain( 'cp-connect', $mofile_global );
	}

}
add_action( 'init', 'cp_connect_load_textdomain' );

<?php
/**
 * Plugin Name: CP Sync
 * Plugin URL: https://churchplugins.com
 * Description: ChurchPlugins integration plugin for ChMS
 * Version: 0.2.0
 * Author: Church Plugins
 * Author URI: https://churchplugins.com
 * Text Domain: cp-sync
 * Domain Path: languages
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Tested up to: 6.7.1
 * Requires PHP: 7.4
 */

if( !defined( 'CP_SYNC_PLUGIN_VERSION' ) ) {
	 define ( 'CP_SYNC_PLUGIN_VERSION',
	 	'0.2.0'
	);
}

require_once( dirname( __FILE__ ) . "/includes/Constants.php" );
require_once( CP_SYNC_PLUGIN_DIR . "/includes/ChurchPlugins/init.php" );
require_once( CP_SYNC_PLUGIN_DIR . 'vendor/autoload.php' );


use CP_Sync\_Init as Init;

/**
 * @var CP_Sync\_Init
 */
global $cp_sync;
$cp_sync = cp_sync();

/**
 * @return CP_Sync\_Init
 */
function cp_sync() {
	return Init::get_instance();
}

/**
 * Load plugin text domain for translations.
 *
 * @return void
 */
function cp_sync_load_textdomain() {

	// Traditional WordPress plugin locale filter
	$get_locale = get_user_locale();

	/**
	 * Defines the plugin language locale used in RCP.
	 *
	 * @var string $get_locale The locale to use. Uses get_user_locale()` in WordPress 4.7 or greater,
	 *                  otherwise uses `get_locale()`.
	 */
	$locale        = apply_filters( 'plugin_locale',  $get_locale, 'cp-sync' );
	$mofile        = sprintf( '%1$s-%2$s.mo', 'cp-sync', $locale );

	// Setup paths to current locale file
	$mofile_global = WP_LANG_DIR . '/cp-sync/' . $mofile;

	if ( file_exists( $mofile_global ) ) {
		// Look in global /wp-content/languages/cp-sync folder
		load_textdomain( 'cp-sync', $mofile_global );
	}

}
add_action( 'init', 'cp_sync_load_textdomain' );

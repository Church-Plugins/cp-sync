<?php
/**
 * Plugin constants
 */

/**
 * Setup/config constants
 */
if( !defined( 'CP_CONNECT_PLUGIN_FILE' ) ) {
	 define ( 'CP_CONNECT_PLUGIN_FILE',
	 	dirname( dirname( __FILE__ ) ) . "/cp-connect.php"
	);
}
if( !defined( 'CP_CONNECT_PLUGIN_DIR' ) ) {
	 define ( 'CP_CONNECT_PLUGIN_DIR',
	 	plugin_dir_path( CP_CONNECT_PLUGIN_FILE )
	);
}
if( !defined( 'CP_CONNECT_PLUGIN_URL' ) ) {
	 define ( 'CP_CONNECT_PLUGIN_URL',
	 	plugin_dir_url( CP_CONNECT_PLUGIN_FILE )
	);
}
if( !defined( 'CP_CONNECT_INCLUDES' ) ) {
	 define ( 'CP_CONNECT_INCLUDES',
	 	plugin_dir_path( dirname( __FILE__ ) ) . 'includes'
	);
}
if( !defined( 'CP_CONNECT_PREFIX' ) ) {
	define ( 'CP_CONNECT_PREFIX',
		'cpc'
   );
}
if( !defined( 'CP_CONNECT_TEXT_DOMAIN' ) ) {
	 define ( 'CP_CONNECT_TEXT_DOMAIN',
		'cp-connect'
   );
}
if( !defined( 'CP_CONNECT_DIST' ) ) {
	 define ( 'CP_CONNECT_DIST',
		CP_CONNECT_PLUGIN_URL . "/dist/"
   );
}

/**
 * Licensing constants
 */
if( !defined( 'CP_CONNECT_STORE_URL' ) ) {
	 define ( 'CP_CONNECT_STORE_URL',
	 	'https://churchplugins.com'
	);
}
if( !defined( 'CP_CONNECT_ITEM_NAME' ) ) {
	 define ( 'CP_CONNECT_ITEM_NAME',
	 	'CP Connect'
	);
}

/**
 * App constants
 */
if( !defined( 'CP_CONNECT_APP_PATH' ) ) {
	 define ( 'CP_CONNECT_APP_PATH',
	 	plugin_dir_path( dirname( __FILE__ ) ) . 'app'
	);
}
if( !defined( 'CP_CONNECT_ASSET_MANIFEST' ) ) {
	 define ( 'CP_CONNECT_ASSET_MANIFEST',
	 	plugin_dir_path( dirname( __FILE__ ) ) . 'app/build/asset-manifest.json'
	);
}

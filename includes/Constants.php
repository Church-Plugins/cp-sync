<?php
/**
 * Plugin constants
 */

/**
 * Setup/config constants
 */
if( !defined( 'CP_SYNC_PLUGIN_FILE' ) ) {
	 define ( 'CP_SYNC_PLUGIN_FILE',
	 	dirname( dirname( __FILE__ ) ) . "/cp-sync.php"
	);
}
if( !defined( 'CP_SYNC_PLUGIN_DIR' ) ) {
	 define ( 'CP_SYNC_PLUGIN_DIR',
	 	plugin_dir_path( CP_SYNC_PLUGIN_FILE )
	);
}
if( !defined( 'CP_SYNC_PLUGIN_URL' ) ) {
	 define ( 'CP_SYNC_PLUGIN_URL',
	 	plugin_dir_url( CP_SYNC_PLUGIN_FILE )
	);
}
if( !defined( 'CP_SYNC_INCLUDES' ) ) {
	 define ( 'CP_SYNC_INCLUDES',
	 	plugin_dir_path( dirname( __FILE__ ) ) . 'includes'
	);
}
if( !defined( 'CP_SYNC_PREFIX' ) ) {
	define ( 'CP_SYNC_PREFIX',
		'cps'
   );
}
if( !defined( 'CP_SYNC_TEXT_DOMAIN' ) ) {
	 define ( 'CP_SYNC_TEXT_DOMAIN',
		'cp-sync'
   );
}
if( !defined( 'CP_SYNC_DIST' ) ) {
	 define ( 'CP_SYNC_DIST',
		CP_SYNC_PLUGIN_URL . "/dist/"
   );
}

/**
 * Licensing constants
 */
if( !defined( 'CP_SYNC_STORE_URL' ) ) {
	 define ( 'CP_SYNC_STORE_URL',
	 	'https://churchplugins.com'
	);
}
if( !defined( 'CP_SYNC_ITEM_NAME' ) ) {
	 define ( 'CP_SYNC_ITEM_NAME',
	 	'CP Sync'
	);
}

/**
 * App constants
 */
if( !defined( 'CP_SYNC_APP_PATH' ) ) {
	 define ( 'CP_SYNC_APP_PATH',
	 	plugin_dir_path( dirname( __FILE__ ) ) . 'app'
	);
}
if( !defined( 'CP_SYNC_ASSET_MANIFEST' ) ) {
	 define ( 'CP_SYNC_ASSET_MANIFEST',
	 	plugin_dir_path( dirname( __FILE__ ) ) . 'app/build/asset-manifest.json'
	);
}

/**
 * OAuth URL
 */
if( !defined( 'CP_SYNC_OAUTH_URL' ) ) {
	define ( 'CP_SYNC_OAUTH_URL', 'http://churchplugins.local' );
}

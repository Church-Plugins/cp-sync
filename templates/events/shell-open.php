<?php
/**
 * Opens the event page.
 *
 * Classic themes use the theme header. Block themes print only the header
 * template part. The block canvas still owns the document, so wp_head() runs
 * after this part and can print the scripts and layout styles it registered.
 *
 * Override from the theme at cp-sync/events/shell-open.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cp_sync_block_theme = function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();

if ( $cp_sync_block_theme ) {
	if ( function_exists( 'block_template_part' ) ) {
		block_template_part( 'header' );
	}
} else {
	get_header();
}
?>
<main id="cp-pg-template" class="cp-pg-template cp-sync-events cp-sync-display">

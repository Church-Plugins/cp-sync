<?php
/**
 * Opens the event page. Block themes get their header template part.
 * Classic themes use the theme header.
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
	?><!DOCTYPE html>
	<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<?php wp_head(); ?>
	</head>
	<body <?php body_class(); ?>>
	<?php
	wp_body_open();

	if ( function_exists( 'block_header_area' ) ) {
		block_header_area();
	}
} else {
	get_header();
}
?>
<main id="cp-sync-events" class="cp-sync-events cp-sync-display">

<?php
/**
 * One date in a list.
 *
 * Expects the global post to be a cp_event. Set $show_series_link to false
 * on a series page. Every card has a linked title and one button.
 *
 * Override from the theme at cp-sync/events/card.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cp_sync_post_id = get_the_ID();
$cp_sync_when    = \CP_Sync\Events\EventFormatter::format_when(
	(string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::START_UTC, true ),
	(string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::END_UTC, true ),
	(string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::TIMEZONE, true ),
	'1' === (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::ALL_DAY, true )
);
$cp_sync_image = '';

if ( has_post_thumbnail() ) {
	$cp_sync_thumb = get_the_post_thumbnail_url( $cp_sync_post_id, 'large' );
	$cp_sync_image = is_string( $cp_sync_thumb ) ? $cp_sync_thumb : '';
}

if ( '' === $cp_sync_image ) {
	$cp_sync_image = (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::IMAGE_URL, true );
}

$cp_sync_description = has_excerpt() ? get_the_excerpt() : get_post_field( 'post_content', $cp_sync_post_id );
$cp_sync_series      = (int) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::SERIES_ID, true );
$cp_sync_series_url  = '';
$cp_sync_series_name = '';

if ( $cp_sync_series && 'publish' === get_post_status( $cp_sync_series ) ) {
	$cp_sync_series_url  = (string) get_permalink( $cp_sync_series );
	$cp_sync_series_name = (string) get_the_title( $cp_sync_series );
}

echo \CP_Sync\Events\EventCard::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventCard::render() escapes every value.
	array(
		'title'            => get_the_title(),
		'permalink'        => (string) get_permalink(),
		'registration_url' => (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::REGISTRATION_URL, true ),
		'image'            => $cp_sync_image,
		'description'      => $cp_sync_description,
		'when'             => $cp_sync_when['text'],
		'all_day'          => $cp_sync_when['all_day'],
		'iso'              => $cp_sync_when['iso'],
		'location'         => (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::LOCATION, true ),
		'series_title'     => $cp_sync_series_name,
		'series_url'       => $cp_sync_series_url,
	),
	array(
		'show_series_link' => ! isset( $show_series_link ) || $show_series_link,
	)
);

<?php
/**
 * One series on the series archive.
 *
 * No event date. The series page's image, title, description, and Register
 * link are the fields this card uses. Learn more opens the series when
 * there is no registration URL.
 *
 * Override from the theme at cp-sync/events/series-card.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cp_sync_post_id = get_the_ID();
$cp_sync_image   = '';

if ( has_post_thumbnail() ) {
	$cp_sync_thumb = get_the_post_thumbnail_url( $cp_sync_post_id, 'large' );
	$cp_sync_image = is_string( $cp_sync_thumb ) ? $cp_sync_thumb : '';
}

if ( '' === $cp_sync_image ) {
	$cp_sync_image = (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::IMAGE_URL, true );
}

$cp_sync_description = has_excerpt() ? get_the_excerpt() : get_post_field( 'post_content', $cp_sync_post_id );

echo \CP_Sync\Events\SeriesCard::render( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SeriesCard::render() escapes every value.
	array(
		'title'            => get_the_title(),
		'permalink'        => (string) get_permalink(),
		'registration_url' => (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::REGISTRATION_URL, true ),
		'image'            => $cp_sync_image,
		'description'      => $cp_sync_description,
	)
);

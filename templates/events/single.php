<?php
/**
 * One event date.
 *
 * Override from the theme at cp-sync/events/single.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CP_Sync\Events\EventDetail;
use CP_Sync\Events\Meta;
use CP_Sync\Events\PostTypes;

\CP_Sync\get_template_part( 'events/shell-open' );

while ( have_posts() ) {
	the_post();

	$cp_sync_post_id = get_the_ID();
	$cp_sync_post    = get_post();
	$cp_sync_image   = '';

	if ( has_post_thumbnail() ) {
		$cp_sync_thumb = get_the_post_thumbnail_url( $cp_sync_post_id, 'large' );
		$cp_sync_image = is_string( $cp_sync_thumb ) ? $cp_sync_thumb : '';
	}

	if ( '' === $cp_sync_image ) {
		$cp_sync_image = (string) get_post_meta( $cp_sync_post_id, Meta::IMAGE_URL, true );
	}

	$cp_sync_series      = (int) get_post_meta( $cp_sync_post_id, Meta::SERIES_ID, true );
	$cp_sync_series_name = '';
	$cp_sync_series_url  = '';

	if ( $cp_sync_series && 'publish' === get_post_status( $cp_sync_series ) ) {
		$cp_sync_series_name = (string) get_the_title( $cp_sync_series );
		$cp_sync_series_url  = (string) get_permalink( $cp_sync_series );
	}

	$cp_sync_terms = array();

	if ( function_exists( 'get_the_terms' ) ) {
		$cp_sync_found = get_the_terms( $cp_sync_post_id, PostTypes::CATEGORY );

		if ( is_array( $cp_sync_found ) ) {
			foreach ( $cp_sync_found as $cp_sync_term ) {
				if ( is_object( $cp_sync_term ) && isset( $cp_sync_term->name ) ) {
					$cp_sync_terms[] = (string) $cp_sync_term->name;
				}
			}
		}
	}

	$cp_sync_view = EventDetail::present(
		array(
			'post_id'          => $cp_sync_post_id,
			'post_type'        => PostTypes::EVENT,
			'post_status'      => is_object( $cp_sync_post ) && isset( $cp_sync_post->post_status ) ? (string) $cp_sync_post->post_status : (string) get_post_status( $cp_sync_post_id ),
			'post_password'    => is_object( $cp_sync_post ) && isset( $cp_sync_post->post_password ) ? (string) $cp_sync_post->post_password : '',
			'title'            => get_the_title(),
			'permalink'        => (string) get_permalink(),
			'excerpt'          => has_excerpt() ? (string) get_the_excerpt() : '',
			'content'          => (string) get_post_field( 'post_content', $cp_sync_post_id ),
			'image'            => $cp_sync_image,
			'start_utc'        => (string) get_post_meta( $cp_sync_post_id, Meta::START_UTC, true ),
			'end_utc'          => (string) get_post_meta( $cp_sync_post_id, Meta::END_UTC, true ),
			'timezone'         => (string) get_post_meta( $cp_sync_post_id, Meta::TIMEZONE, true ),
			'all_day'          => '1' === (string) get_post_meta( $cp_sync_post_id, Meta::ALL_DAY, true ),
			'location'         => (string) get_post_meta( $cp_sync_post_id, Meta::LOCATION, true ),
			'registration_url' => (string) get_post_meta( $cp_sync_post_id, Meta::REGISTRATION_URL, true ),
			'price'            => (string) get_post_meta( $cp_sync_post_id, Meta::REGISTRATION_PRICE, true ),
			'categories'       => $cp_sync_terms,
			'series_title'     => $cp_sync_series_name,
			'series_url'       => $cp_sync_series_url,
			'now_utc'          => gmdate( 'Y-m-d H:i:s' ),
		),
		'event'
	);
	?>
	<article <?php post_class( 'cp-sync-event' ); ?>>
		<?php
		\CP_Sync\get_template_part(
			'events/detail-header',
			array(
				'view' => $cp_sync_view,
			)
		);
		\CP_Sync\get_template_part(
			'events/details',
			array(
				'view' => $cp_sync_view,
			)
		);
		?>
	</article>
	<?php
}

\CP_Sync\get_template_part( 'events/shell-close' );

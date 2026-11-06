<?php
/**
 * One event series: description, image, upcoming dates, and Register.
 *
 * Override from the theme at cp-sync/events/series.php.
 * Dates come from the existing upcoming query. This page does not invent a
 * recurrence, and it does not offer a series calendar file.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CP_Sync\Events\EventDetail;
use CP_Sync\Events\EventsShortcode;
use CP_Sync\Events\Meta;

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

	$cp_sync_view = EventDetail::present(
		array(
			'post_id'          => $cp_sync_post_id,
			'post_type'        => \CP_Sync\Events\PostTypes::SERIES,
			'post_status'      => is_object( $cp_sync_post ) && isset( $cp_sync_post->post_status ) ? (string) $cp_sync_post->post_status : (string) get_post_status( $cp_sync_post_id ),
			'post_password'    => is_object( $cp_sync_post ) && isset( $cp_sync_post->post_password ) ? (string) $cp_sync_post->post_password : '',
			'title'            => get_the_title(),
			'permalink'        => (string) get_permalink(),
			'excerpt'          => has_excerpt() ? (string) get_the_excerpt() : '',
			'content'          => (string) get_post_field( 'post_content', $cp_sync_post_id ),
			'image'            => $cp_sync_image,
			'registration_url' => (string) get_post_meta( $cp_sync_post_id, Meta::REGISTRATION_URL, true ),
			'now_utc'          => gmdate( 'Y-m-d H:i:s' ),
		),
		'series'
	);
	$cp_sync_query = new WP_Query(
		EventsShortcode::query_args(
			array(
				'limit'    => -1,
				'category' => '',
				'series'   => '',
			),
			gmdate( 'Y-m-d H:i:s' ),
			$cp_sync_post_id
		)
	);
	?>
	<article <?php post_class( 'cp-sync-series' ); ?>>
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
		<section class="cp-sync-series__dates" aria-labelledby="cp-sync-series-dates-<?php echo esc_attr( (string) $cp_sync_post_id ); ?>">
			<h2 id="cp-sync-series-dates-<?php echo esc_attr( (string) $cp_sync_post_id ); ?>"><?php esc_html_e( 'Upcoming dates', 'cp-sync' ); ?></h2>
			<?php
			\CP_Sync\get_template_part(
				'events/series-dates',
				array(
					'events' => $cp_sync_query->posts,
				)
			);
			?>
		</section>
	</article>
	<?php
	wp_reset_postdata();
}

\CP_Sync\get_template_part( 'events/shell-close' );

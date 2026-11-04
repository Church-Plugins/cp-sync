<?php
/**
 * One event series: description, image, upcoming dates, and Register.
 *
 * Override from the theme at cp-sync/events/series.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\CP_Sync\get_template_part( 'events/shell-open' );

while ( have_posts() ) {
	the_post();

	$cp_sync_post_id = get_the_ID();
	$cp_sync_url     = (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::REGISTRATION_URL, true );
	$cp_sync_image   = (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::IMAGE_URL, true );
	$cp_sync_query   = new WP_Query(
		\CP_Sync\Events\EventsShortcode::query_args(
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
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="cp-sync-series__image">
				<?php
				the_post_thumbnail(
					'large',
					array(
						'alt' => the_title_attribute( array( 'echo' => false ) ),
					)
				);
				?>
			</figure>
		<?php elseif ( '' !== $cp_sync_image ) : ?>
			<figure class="cp-sync-series__image">
				<img src="<?php echo esc_url( $cp_sync_image ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
			</figure>
		<?php endif; ?>

		<header class="cp-sync-series__header">
			<h1><?php echo esc_html( get_the_title() ); ?></h1>
		</header>

		<div class="cp-sync-series__content">
			<?php echo wp_kses_post( apply_filters( 'the_content', get_post_field( 'post_content', $cp_sync_post_id ) ) ); ?>
		</div>

		<?php if ( '' !== $cp_sync_url ) : ?>
			<p class="cp-sync-event__register">
				<a href="<?php echo esc_url( $cp_sync_url ); ?>"><?php esc_html_e( 'Register', 'cp-sync' ); ?></a>
			</p>
		<?php endif; ?>

		<section class="cp-sync-series__dates" aria-labelledby="cp-sync-series-dates">
			<h2 id="cp-sync-series-dates"><?php esc_html_e( 'Upcoming dates', 'cp-sync' ); ?></h2>
			<?php
			\CP_Sync\get_template_part(
				'events/list',
				array(
					'events'           => $cp_sync_query->posts,
					'show_series_link' => false,
				)
			);
			?>
		</section>

		<?php $cp_sync_archive = get_post_type_archive_link( \CP_Sync\Events\PostTypes::EVENT ); ?>
		<?php if ( is_string( $cp_sync_archive ) && '' !== $cp_sync_archive ) : ?>
			<p class="cp-sync-events__back">
				<a href="<?php echo esc_url( $cp_sync_archive ); ?>"><?php esc_html_e( 'All events', 'cp-sync' ); ?></a>
			</p>
		<?php endif; ?>
	</article>
	<?php
	wp_reset_postdata();
}

\CP_Sync\get_template_part( 'events/shell-close' );

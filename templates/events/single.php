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

\CP_Sync\get_template_part( 'events/shell-open' );

while ( have_posts() ) {
	the_post();

	$cp_sync_post_id  = get_the_ID();
	$cp_sync_when     = \CP_Sync\Events\EventFormatter::format_when(
		(string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::START_UTC, true ),
		(string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::END_UTC, true ),
		(string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::TIMEZONE, true ),
		'1' === (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::ALL_DAY, true )
	);
	$cp_sync_location = (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::LOCATION, true );
	$cp_sync_url      = (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::REGISTRATION_URL, true );
	$cp_sync_image    = (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::IMAGE_URL, true );
	$cp_sync_series   = (int) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::SERIES_ID, true );
	?>
	<article <?php post_class( 'cp-sync-event' ); ?>>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="cp-sync-event__image">
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
			<figure class="cp-sync-event__image">
				<img src="<?php echo esc_url( $cp_sync_image ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
			</figure>
		<?php endif; ?>

		<header class="cp-sync-event__header">
			<h1><?php echo esc_html( get_the_title() ); ?></h1>
			<?php if ( '' !== $cp_sync_when['text'] ) : ?>
				<p class="cp-sync-event__when">
					<time datetime="<?php echo esc_attr( $cp_sync_when['iso'] ); ?>"><?php echo esc_html( $cp_sync_when['text'] ); ?></time>
					<?php if ( $cp_sync_when['all_day'] ) : ?>
						<span class="cp-sync-event__all-day"><?php esc_html_e( '(All day)', 'cp-sync' ); ?></span>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<?php if ( '' !== $cp_sync_location ) : ?>
				<p class="cp-sync-event__location"><?php echo esc_html( $cp_sync_location ); ?></p>
			<?php endif; ?>
		</header>

		<div class="cp-sync-event__content">
			<?php echo wp_kses_post( apply_filters( 'the_content', get_post_field( 'post_content', $cp_sync_post_id ) ) ); ?>
		</div>

		<?php if ( '' !== $cp_sync_url ) : ?>
			<p class="cp-sync-event__register">
				<a href="<?php echo esc_url( $cp_sync_url ); ?>"><?php esc_html_e( 'Register', 'cp-sync' ); ?></a>
			</p>
		<?php endif; ?>

		<?php if ( $cp_sync_series && 'publish' === get_post_status( $cp_sync_series ) ) : ?>
			<p class="cp-sync-event__series">
				<?php esc_html_e( 'Part of', 'cp-sync' ); ?>
				<a href="<?php echo esc_url( get_permalink( $cp_sync_series ) ); ?>"><?php echo esc_html( get_the_title( $cp_sync_series ) ); ?></a>
			</p>
		<?php endif; ?>

		<?php $cp_sync_archive = get_post_type_archive_link( \CP_Sync\Events\PostTypes::EVENT ); ?>
		<?php if ( is_string( $cp_sync_archive ) && '' !== $cp_sync_archive ) : ?>
			<p class="cp-sync-events__back">
				<a href="<?php echo esc_url( $cp_sync_archive ); ?>"><?php esc_html_e( 'All events', 'cp-sync' ); ?></a>
			</p>
		<?php endif; ?>
	</article>
	<?php
}

\CP_Sync\get_template_part( 'events/shell-close' );

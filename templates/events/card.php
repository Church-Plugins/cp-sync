<?php
/**
 * One date in a list.
 *
 * Expects the global post to be a cp_event. Set $show_series_link to false
 * on a series page.
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
$cp_sync_location = (string) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::LOCATION, true );
$cp_sync_series   = (int) get_post_meta( $cp_sync_post_id, \CP_Sync\Events\Meta::SERIES_ID, true );
$cp_sync_show_series = ! isset( $show_series_link ) || $show_series_link;
?>
<article class="cp-sync-event-card">
	<h2 class="cp-sync-event-card__title">
		<a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
	</h2>
	<?php if ( '' !== $cp_sync_when['text'] ) : ?>
		<p class="cp-sync-event-card__when">
			<time datetime="<?php echo esc_attr( $cp_sync_when['iso'] ); ?>"><?php echo esc_html( $cp_sync_when['text'] ); ?></time>
			<?php if ( $cp_sync_when['all_day'] ) : ?>
				<span class="cp-sync-event-card__all-day"><?php esc_html_e( '(All day)', 'cp-sync' ); ?></span>
			<?php endif; ?>
		</p>
	<?php endif; ?>
	<?php if ( '' !== $cp_sync_location ) : ?>
		<p class="cp-sync-event-card__location"><?php echo esc_html( $cp_sync_location ); ?></p>
	<?php endif; ?>
	<?php if ( $cp_sync_show_series && $cp_sync_series && 'publish' === get_post_status( $cp_sync_series ) ) : ?>
		<p class="cp-sync-event-card__series">
			<?php esc_html_e( 'Part of', 'cp-sync' ); ?>
			<a href="<?php echo esc_url( get_permalink( $cp_sync_series ) ); ?>"><?php echo esc_html( get_the_title( $cp_sync_series ) ); ?></a>
		</p>
	<?php endif; ?>
</article>

<?php
/**
 * Date, location, and description under the hero. The price is in the hero.
 *
 * Expects $view from EventDetail::present(). A series skips the fact strip.
 * Override from the theme at cp-sync/events/details.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CP_Sync\Events\EventDetail;

if ( empty( $view ) || ! is_array( $view ) ) {
	return;
}

$cp_sync_event = ! isset( $view['context'] ) || 'series' !== $view['context'];
?>
<?php if ( $cp_sync_event ) : ?>
	<section class="cp-sync-event__facts">
		<div class="cp-sync-event__fact cp-sync-event__fact--date">
			<h2><?php esc_html_e( 'Date & time', 'cp-sync' ); ?></h2>
			<?php if ( ! empty( $view['date_confirmed'] ) ) : ?>
				<p>
					<time datetime="<?php echo esc_attr( $view['iso'] ); ?>"><?php echo EventDetail::esc( $view['when_text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?></time>
					<?php if ( ! empty( $view['all_day'] ) ) : ?>
						<span class="cp-sync-event__all-day"><?php esc_html_e( 'All day', 'cp-sync' ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $view['timezone_verified'] ) ) : ?>
						<span class="cp-sync-event__timezone"><?php echo EventDetail::esc( $view['timezone_label'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?></span>
					<?php else : ?>
						<span class="cp-sync-event__timezone"><?php esc_html_e( 'Time zone unavailable', 'cp-sync' ); ?></span>
					<?php endif; ?>
				</p>
			<?php else : ?>
				<p><?php esc_html_e( 'Date to be confirmed', 'cp-sync' ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( ! empty( $view['location'] ) ) : ?>
			<div class="cp-sync-event__fact cp-sync-event__fact--location">
				<h2><?php esc_html_e( 'Location', 'cp-sync' ); ?></h2>
				<p><?php echo wp_kses_post( nl2br( EventDetail::esc( $view['location'] ), false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped before nl2br, then limited to post HTML. ?></p>
				<?php if ( ! empty( $view['map_url'] ) ) : ?>
					<p><a href="<?php echo esc_url( $view['map_url'] ); ?>"><?php esc_html_e( 'View on map', 'cp-sync' ); ?></a></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</section>
<?php endif; ?>
<?php if ( ! empty( $view['has_content'] ) ) : ?>
	<section class="cp-sync-event__about">
		<h2><?php echo esc_html( $cp_sync_event ? __( 'About this event', 'cp-sync' ) : __( 'About this series', 'cp-sync' ) ); ?></h2>
		<div class="cp-sync-event__content">
			<?php echo wp_kses_post( apply_filters( 'the_content', $view['content'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same content contract as before: the_content, then wp_kses_post(). ?>
		</div>
	</section>
<?php endif; ?>
<?php if ( $cp_sync_event && ! empty( $view['series_url'] ) && ! empty( $view['series_title'] ) ) : ?>
	<p class="cp-sync-event__series">
		<?php esc_html_e( 'Part of', 'cp-sync' ); ?>
		<a href="<?php echo esc_url( $view['series_url'] ); ?>"><?php echo EventDetail::esc( $view['series_title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?></a>
	</p>
<?php endif; ?>

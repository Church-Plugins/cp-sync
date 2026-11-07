<?php
/**
 * Hero for an event date or a series.
 *
 * Expects $view from EventDetail::present(). Override from the theme at
 * cp-sync/events/detail-header.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CP_Sync\Events\EventDetail;
use CP_Sync\Events\PostTypes;

if ( empty( $view ) || ! is_array( $view ) ) {
	return;
}

$cp_sync_archive = get_post_type_archive_link( PostTypes::EVENT );
$cp_sync_image   = ! empty( $view['image'] );
?>
<?php if ( is_string( $cp_sync_archive ) && '' !== $cp_sync_archive ) : ?>
	<p class="cp-sync-events__back">
		<a href="<?php echo esc_url( $cp_sync_archive ); ?>"><?php esc_html_e( 'All events', 'cp-sync' ); ?></a>
	</p>
<?php endif; ?>
<?php if ( ! empty( $view['ended'] ) ) : ?>
	<p class="cp-sync-event__notice" role="status"><?php esc_html_e( 'This event has ended.', 'cp-sync' ); ?></p>
<?php endif; ?>
<section class="cp-sync-event__hero<?php echo $cp_sync_image ? '' : ' cp-sync-event__hero--no-image'; ?>">
	<div class="cp-sync-event__intro">
		<?php if ( ! empty( $view['categories'] ) && is_array( $view['categories'] ) ) : ?>
			<p class="cp-sync-event__terms">
				<?php foreach ( $view['categories'] as $cp_sync_index => $cp_sync_category ) : ?>
					<?php if ( $cp_sync_index > 0 ) : ?>
						<span aria-hidden="true"> · </span>
					<?php endif; ?>
					<span class="cp-sync-event__term"><?php echo EventDetail::esc( $cp_sync_category ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?></span>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>
		<?php if ( ! empty( $view['date_confirmed'] ) && ! empty( $view['badge']['valid'] ) ) : ?>
			<div class="cp-sync-event__heading">
				<p class="cp-sync-event__badge" aria-hidden="true">
					<span class="cp-sync-event__badge-weekday"><?php echo EventDetail::esc( isset( $view['badge']['weekday'] ) ? $view['badge']['weekday'] : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?></span>
					<span class="cp-sync-event__badge-month"><?php echo EventDetail::esc( $view['badge']['month'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?></span>
					<strong class="cp-sync-event__badge-day"><?php echo EventDetail::esc( $view['badge']['day'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?></strong>
				</p>
				<h1 class="cp-sync-event__title"><?php echo EventDetail::esc( $view['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?></h1>
			</div>
		<?php else : ?>
			<h1 class="cp-sync-event__title"><?php echo EventDetail::esc( $view['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?></h1>
		<?php endif; ?>
		<?php if ( 'event' === $view['context'] && empty( $view['date_confirmed'] ) ) : ?>
			<p class="cp-sync-event__unscheduled"><?php esc_html_e( 'Date to be confirmed', 'cp-sync' ); ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $view['excerpt'] ) ) : ?>
			<p class="cp-sync-event__excerpt"><?php echo EventDetail::esc( $view['excerpt'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?></p>
		<?php endif; ?>
		<?php if ( ! empty( $view['price'] ) || ! empty( $view['show_register'] ) || ! empty( $view['calendar'] ) ) : ?>
			<div class="cp-sync-event__actions">
				<?php if ( ! empty( $view['price'] ) ) : ?>
					<strong class="cp-sync-event__price"><?php echo EventDetail::esc( $view['price'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?></strong>
				<?php endif; ?>
				<?php if ( ! empty( $view['show_register'] ) ) : ?>
					<a class="cp-sync-event__button cp-button is-large" href="<?php echo esc_url( $view['registration_url'] ); ?>"><?php esc_html_e( 'Register', 'cp-sync' ); ?></a>
				<?php endif; ?>
				<?php
				if ( ! empty( $view['calendar'] ) ) {
					\CP_Sync\get_template_part(
						'events/calendar-links',
						array(
							'view' => $view,
						)
					);
				}
				?>
			</div>
		<?php endif; ?>
	</div>
	<?php if ( $cp_sync_image ) : ?>
		<div class="cp-sync-event__media">
			<img src="<?php echo esc_url( $view['image'] ); ?>" alt="<?php echo EventDetail::esc( $view['image_alt'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- EventDetail::esc() escapes. ?>">
		</div>
	<?php endif; ?>
</section>

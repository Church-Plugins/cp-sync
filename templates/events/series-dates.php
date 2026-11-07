<?php
/**
 * Published upcoming dates for one series.
 *
 * Expects $events to already be the upcoming query result. Does not invent
 * dates that query did not return. Override from the theme at
 * cp-sync/events/series-dates.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CP_Sync\Events\EventDetail;
use CP_Sync\Events\EventFormatter;
use CP_Sync\Events\Meta;

$cp_sync_dates = array();

if ( ! empty( $events ) && is_array( $events ) ) {
	foreach ( $events as $cp_sync_event ) {
		if ( ! $cp_sync_event instanceof WP_Post ) {
			continue;
		}

		if ( isset( $cp_sync_event->post_status ) && 'publish' !== $cp_sync_event->post_status ) {
			continue;
		}

		$cp_sync_dates[] = $cp_sync_event;
	}
}

if ( ! $cp_sync_dates ) {
	echo '<p class="cp-sync-events__empty">' . esc_html__( 'No upcoming dates listed.', 'cp-sync' ) . '</p>';
	return;
}
?>
<ol class="cp-sync-series__schedule">
	<?php foreach ( $cp_sync_dates as $cp_sync_event ) : ?>
		<?php
		$cp_sync_id   = (int) $cp_sync_event->ID;
		$cp_sync_when = EventFormatter::format_when(
			(string) get_post_meta( $cp_sync_id, Meta::START_UTC, true ),
			(string) get_post_meta( $cp_sync_id, Meta::END_UTC, true ),
			(string) get_post_meta( $cp_sync_id, Meta::TIMEZONE, true ),
			'1' === (string) get_post_meta( $cp_sync_id, Meta::ALL_DAY, true )
		);
		?>
		<li class="cp-sync-series__date">
			<?php
			echo EventDetail::occurrence_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- occurrence_html() escapes every value.
				array(
					'title'            => get_the_title( $cp_sync_event ),
					'permalink'        => (string) get_permalink( $cp_sync_event ),
					'registration_url' => (string) get_post_meta( $cp_sync_id, Meta::REGISTRATION_URL, true ),
					'badge'            => EventFormatter::badge(
						(string) get_post_meta( $cp_sync_id, Meta::START_UTC, true ),
						(string) get_post_meta( $cp_sync_id, Meta::TIMEZONE, true )
					),
					'when'             => $cp_sync_when['text'],
					'all_day'          => $cp_sync_when['all_day'],
					'iso'              => $cp_sync_when['iso'],
					'location'         => (string) get_post_meta( $cp_sync_id, Meta::LOCATION, true ),
				)
			);
			?>
		</li>
	<?php endforeach; ?>
</ol>

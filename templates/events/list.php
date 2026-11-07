<?php
/**
 * List of event posts.
 *
 * @var WP_Post[] $events
 * @var bool      $show_series_link
 *
 * Override from the theme at cp-sync/events/list.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $events ) || ! is_array( $events ) ) {
	echo '<p class="cp-sync-events__empty">' . esc_html__( 'No upcoming events.', 'cp-sync' ) . '</p>';
	return;
}
?>
<ul class="cp-sync-events__list">
	<?php
	foreach ( $events as $cp_sync_event ) {
		if ( ! $cp_sync_event instanceof WP_Post ) {
			continue;
		}
		// setup_postdata() does not assign the global post. Template tags read it.
		$GLOBALS['post'] = $cp_sync_event; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $cp_sync_event );
		echo '<li class="cp-sync-events__item">';
		\CP_Sync\get_template_part(
			'events/card',
			array(
				'show_series_link' => ! isset( $show_series_link ) || $show_series_link,
			)
		);
		echo '</li>';
	}
	wp_reset_postdata();
	?>
</ul>

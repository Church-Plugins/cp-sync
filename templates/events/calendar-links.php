<?php
/**
 * Local calendar download for one published event date.
 *
 * Expects $view['calendar_url']. Override from the theme at
 * cp-sync/events/calendar-links.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $view ) || ! is_array( $view ) || empty( $view['calendar_url'] ) ) {
	return;
}
?>
<a class="cp-sync-event__calendar cp-button is-transparent" href="<?php echo esc_url( $view['calendar_url'] ); ?>"><?php esc_html_e( 'Add to calendar', 'cp-sync' ); ?></a>

<?php
/**
 * Month calendar. The script fills these elements. Without JavaScript, the
 * archive shows the list instead.
 *
 * Override from the theme at cp-sync/events/calendar.php. Keep the data-cp-sync
 * attributes, because the script looks for them.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="cp-sync-calendar" data-cp-sync-calendar>
	<p class="cp-sync-calendar__status" data-cp-sync-status role="status" hidden></p>
	<div class="cp-sync-calendar__grid" data-cp-sync-grid aria-label="<?php esc_attr_e( 'Events calendar', 'cp-sync' ); ?>"></div>
	<section class="cp-sync-calendar__day" data-cp-sync-day aria-live="polite" hidden>
		<h2 class="cp-sync-calendar__day-title" data-cp-sync-day-title></h2>
		<p class="cp-sync-calendar__day-empty" data-cp-sync-day-empty hidden></p>
		<ul class="cp-sync-calendar__day-list" data-cp-sync-day-list></ul>
	</section>
</div>

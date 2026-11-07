<?php
/**
 * Event series archive at /event-series/.
 *
 * Loaded for the cp_event_series archive, not the events list. One card
 * per published series. Series with only past dates stay on this list.
 * Cards do not show an event date.
 *
 * Override from the theme at cp-sync/events/series-archive.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\CP_Sync\get_template_part( 'events/shell-open' );

$cp_sync_title = post_type_archive_title( '', false );

if ( ! is_string( $cp_sync_title ) || '' === $cp_sync_title ) {
	$cp_sync_title = __( 'Event Series', 'cp-sync' );
}
?>
<header class="cp-sync-events__header">
	<h1 class="page-title"><?php echo esc_html( $cp_sync_title ); ?></h1>
</header>
<?php
$cp_sync_series = array();

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		$cp_sync_one = get_post();

		if ( $cp_sync_one instanceof WP_Post && \CP_Sync\Events\PostTypes::SERIES === $cp_sync_one->post_type ) {
			$cp_sync_series[] = $cp_sync_one;
		}
	}
}

if ( empty( $cp_sync_series ) ) {
	echo '<p class="cp-sync-events__empty">' . esc_html__( 'No event series.', 'cp-sync' ) . '</p>';
} else {
	echo '<ul class="cp-sync-events__list">';

	foreach ( $cp_sync_series as $cp_sync_series_post ) {
		// setup_postdata() does not assign the global post. Template tags read it.
		$GLOBALS['post'] = $cp_sync_series_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $cp_sync_series_post );
		echo '<li class="cp-sync-events__item">';
		\CP_Sync\get_template_part( 'events/series-card' );
		echo '</li>';
	}

	wp_reset_postdata();
	echo '</ul>';
}

the_posts_pagination(
	array(
		'mid_size'  => 1,
		'prev_text' => __( 'Previous', 'cp-sync' ),
		'next_text' => __( 'Next', 'cp-sync' ),
	)
);

\CP_Sync\get_template_part( 'events/shell-close' );

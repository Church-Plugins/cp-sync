<?php
/**
 * Event series archive at /event-series/.
 *
 * Each series uses the same card as a date: a linked title, one button,
 * and slots for the image and description.
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
	<h1><?php echo esc_html( $cp_sync_title ); ?></h1>
</header>
<?php
$cp_sync_series = array();

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		$cp_sync_series[] = get_post();
	}
}

\CP_Sync\get_template_part(
	'events/list',
	array(
		'events'           => $cp_sync_series,
		'show_series_link' => false,
	)
);

the_posts_pagination(
	array(
		'mid_size'  => 1,
		'prev_text' => __( 'Previous', 'cp-sync' ),
		'next_text' => __( 'Next', 'cp-sync' ),
	)
);

\CP_Sync\get_template_part( 'events/shell-close' );

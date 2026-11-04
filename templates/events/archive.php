<?php
/**
 * Upcoming events archive.
 *
 * Override from the theme at cp-sync/events/archive.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\CP_Sync\get_template_part( 'events/shell-open' );
?>
<header class="cp-sync-events__header">
	<h1>
		<?php
		// post_type_archive_title() is empty on /event-category/ and other term archives.
		if (
			( function_exists( 'is_tax' ) && is_tax() )
			|| ( function_exists( 'is_category' ) && is_category() )
			|| ( function_exists( 'is_tag' ) && is_tag() )
		) {
			$cp_sync_heading = single_term_title( '', false );
		} else {
			$cp_sync_heading = post_type_archive_title( '', false );
		}
		echo esc_html( is_string( $cp_sync_heading ) ? $cp_sync_heading : '' );
		?>
	</h1>
</header>
<?php
$cp_sync_events = array();

if ( have_posts() ) {
	while ( have_posts() ) {
		the_post();
		$cp_sync_events[] = get_post();
	}
}

\CP_Sync\get_template_part(
	'events/list',
	array(
		'events'           => $cp_sync_events,
		'show_series_link' => true,
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

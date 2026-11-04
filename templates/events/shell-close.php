<?php
/**
 * Closes the event page.
 *
 * Override from the theme at cp-sync/events/shell-close.php.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
</main>
<?php
if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
	if ( function_exists( 'block_footer_area' ) ) {
		block_footer_area();
	}
	wp_footer();
	echo '</body></html>';
} else {
	get_footer();
}

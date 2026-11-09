<?php
/**
 * Closes the event page.
 *
 * Classic themes use the theme footer. Block themes print only the footer
 * template part. The block canvas prints wp_footer() after that part.
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
	if ( function_exists( 'block_template_part' ) ) {
		block_template_part( 'footer' );
	}
} else {
	get_footer();
}

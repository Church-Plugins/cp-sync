<?php
/**
 * Single group.
 *
 * Copy this file to your-theme/cp-sync/groups/single.php to override it.
 * Works in a classic theme (header and footer) and a block theme (template parts).
 *
 * @package CP_Sync
 */

use CP_Sync\Groups\Display;

if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
	block_template_part( 'header' );
} else {
	get_header();
}

$archive = get_post_type_archive_link( 'cp_group' );
?>
<main id="cp-sync-group" class="cp-sync-groups">
	<?php
	while ( have_posts() ) :
		the_post();
		echo Display::render_post( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_post() escapes every value.
			get_the_ID(),
			[
				'summary'    => false,
				'heading'    => 'h1',
				'link_title' => false,
			]
		);
	endwhile;
	wp_reset_postdata();
	?>
	<?php if ( is_string( $archive ) && '' !== $archive ) : ?>
		<p class="cp-sync-groups__back">
			<a href="<?php echo esc_url( $archive ); ?>"><?php esc_html_e( 'All groups', 'cp-sync' ); ?></a>
		</p>
	<?php endif; ?>
</main>
<?php
if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
	block_template_part( 'footer' );
} else {
	get_footer();
}

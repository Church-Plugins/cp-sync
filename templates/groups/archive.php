<?php
/**
 * Groups archive. Matches CP Groups' templates/archive.php, without the filter column.
 *
 * Copy this file to your-theme/cp-sync/groups/archive.php to override it.
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

$title = ( function_exists( 'is_tax' ) && is_tax() ) ? single_term_title( '', false ) : post_type_archive_title( '', false );

if ( ! is_string( $title ) || '' === $title ) {
	$title = __( 'Groups', 'cp-sync' );
}
?>
<main id="cp-pg-template" class="cp-pg-template cp-sync-display">
	<div class="cp-groups-archive cp-archive">
		<h1 class="page-title"><?php echo Display::protect_block_template_html( esc_html( $title ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped here. ?></h1>
		<?php
		$cp_sync_intro = function_exists( 'get_the_archive_description' ) ? get_the_archive_description() : '';
		$cp_sync_intro = is_string( $cp_sync_intro ) ? trim( $cp_sync_intro ) : '';

		if ( '' !== $cp_sync_intro && '' !== trim( wp_strip_all_tags( $cp_sync_intro ) ) ) {
			echo '<div class="cp-groups-archive--intro">' . Display::protect_block_template_html( wp_kses_post( $cp_sync_intro ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses_post() above.
		}
		?>
		<div class="cp-groups-archive--list">
			<?php if ( have_posts() ) : ?>
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<div class="cp-groups-archive--list--item">
						<?php
						echo Display::render_post( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_post() escapes every value.
							get_the_ID(),
							[
								'summary'    => true,
								'heading'    => 'h2',
								'link_title' => true,
							]
						);
						?>
					</div>
					<?php
				endwhile;
				wp_reset_postdata();
				?>
			<?php else : ?>
				<p><?php esc_html_e( 'No groups found.', 'cp-sync' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
		the_posts_pagination(
			[
				'mid_size'  => 1,
				'prev_text' => __( 'Previous groups', 'cp-sync' ),
				'next_text' => __( 'Next groups', 'cp-sync' ),
			]
		);
		?>
	</div>
</main>
<?php
if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
	block_template_part( 'footer' );
} else {
	get_footer();
}

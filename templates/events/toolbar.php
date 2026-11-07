<?php
/**
 * Category filter and the Calendar / List switch.
 *
 * The filter is hidden when a site has no event categories. Both controls work
 * without JavaScript. Override from the theme at cp-sync/events/toolbar.php.
 *
 * @var string $view calendar or list.
 *
 * @package CP_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CP_Sync\Events\Display;
use CP_Sync\Events\PostTypes;

$cp_sync_view     = ( isset( $view ) && 'list' === $view ) ? 'list' : 'calendar';
$cp_sync_options  = Display::category_options();
$cp_sync_selected = Display::current_category();
$cp_sync_archive  = get_post_type_archive_link( PostTypes::EVENT );
$cp_sync_views    = array(
	'calendar' => __( 'Calendar', 'cp-sync' ),
	'list'     => __( 'List', 'cp-sync' ),
);
?>
<div class="cp-sync-events__toolbar">
	<?php if ( $cp_sync_options && is_string( $cp_sync_archive ) ) : ?>
		<form class="cp-sync-events__filter" method="get" action="<?php echo esc_url( $cp_sync_archive ); ?>">
			<label class="cp-sync-events__filter-label" for="cp-sync-event-category"><?php esc_html_e( 'Category', 'cp-sync' ); ?></label>
			<select id="cp-sync-event-category" name="<?php echo esc_attr( PostTypes::CATEGORY ); ?>">
				<option value=""><?php esc_html_e( 'All categories', 'cp-sync' ); ?></option>
				<?php foreach ( $cp_sync_options as $cp_sync_slug => $cp_sync_name ) : ?>
					<option value="<?php echo esc_attr( $cp_sync_slug ); ?>" <?php selected( $cp_sync_selected, $cp_sync_slug ); ?>><?php echo esc_html( $cp_sync_name ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php if ( 'list' === $cp_sync_view ) : ?>
				<input type="hidden" name="view" value="list">
			<?php endif; ?>
			<noscript><button class="cp-button is-small" type="submit"><?php esc_html_e( 'Filter', 'cp-sync' ); ?></button></noscript>
		</form>
	<?php endif; ?>
	<nav class="cp-sync-events__views" aria-label="<?php esc_attr_e( 'Event view', 'cp-sync' ); ?>">
		<?php foreach ( $cp_sync_views as $cp_sync_key => $cp_sync_label ) : ?>
			<a class="cp-button<?php echo $cp_sync_key === $cp_sync_view ? '' : ' is-light'; ?>" href="<?php echo esc_url( Display::view_url( $cp_sync_key ) ); ?>"<?php echo $cp_sync_key === $cp_sync_view ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $cp_sync_label ); ?></a>
		<?php endforeach; ?>
	</nav>
</div>

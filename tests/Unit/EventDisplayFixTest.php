<?php
/**
 * Review fixes for the series date link and the footer image script.
 *
 * @package CP_Sync
 */

namespace CP_Sync\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Locks the display fixes that a browser check also measures.
 */
class EventDisplayFixTest extends TestCase {

	public function test_series_date_link_is_a_flex_tap_target() {
		$css   = file_get_contents( dirname( __DIR__, 2 ) . '/src/events-display/_list.scss' );
		$rule  = $this->rule( $css, '.cp-sync-events__back a' );

		$this->assertNotSame( '', $rule );
		$this->assertStringContainsString( 'display: inline-flex', $rule );
		$this->assertStringContainsString( 'min-height: 44px', $rule );
		$this->assertStringContainsString( 'min-width: 44px', $rule );
		$this->assertStringContainsString( 'overflow-wrap: anywhere', $rule );
		$this->assertDoesNotMatchRegularExpression( '/display\s*:\s*inline\s*;/', $rule );
	}

	public function test_event_image_script_checks_images_that_already_failed() {
		$js = file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/events-display.js' );

		$this->assertIsString( $js );
		$this->assertStringContainsString( "addEventListener(\n\t\t'error'", $js );
		$this->assertStringContainsString( 'querySelectorAll', $js );
		$this->assertStringContainsString( 'image.complete', $js );
		$this->assertStringContainsString( 'naturalWidth === 0', $js );
		$this->assertLessThan( strpos( $js, 'querySelectorAll' ), strpos( $js, "addEventListener" ) );
	}

	public function test_event_image_script_only_touches_media_slots() {
		$js = file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/events-display.js' );

		$this->assertIsString( $js );
		$this->assertStringNotContainsString( "'.cp-sync-events", $js );

		foreach ( array( 'cp-sync-event__media', 'cp-sync-event-card__media', 'cp-sync-series-card__media' ) as $slot ) {
			$this->assertStringContainsString( '.' . $slot . ' img', $js );
		}
	}

	public function test_failed_list_card_image_leaves_no_empty_slot() {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/js/events-display.js' );

		$this->assertStringContainsString( "contains('cp-sync-event-card__media')", $js );
		$this->assertStringContainsString( 'media.remove()', $js );
	}

	public function test_failed_image_placeholder_is_styled_with_a_cp_token() {
		$css = file_get_contents( dirname( __DIR__, 2 ) . '/src/events-display/_detail.scss' );

		$rule = $this->rule( (string) $css, '.cp-sync-event__placeholder' );

		$this->assertStringContainsString( 'background: var(--cp-color-ui-inverted-light)', $rule );
	}

	public function test_styles_use_cp_tokens_without_important_or_hardcoded_colors() {
		foreach ( array( 'src/frontend-shared/_base.scss', 'src/events-display/_list.scss', 'src/events-display/_detail.scss', 'src/events-calendar/index.scss', 'src/groups-display/_lite.scss' ) as $file ) {
			$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $file );

			$this->assertStringNotContainsString( '!important', $css, $file );
			$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,8}\b/', $css, $file );
			$this->assertStringNotContainsString( '--cp-sync-', $css, $file );
		}
	}

	public function test_calendar_script_never_writes_chms_text_as_html() {
		$js = (string) file_get_contents( dirname( __DIR__, 2 ) . '/src/events-calendar/index.js' );

		foreach ( array( 'innerHTML', 'outerHTML', 'insertAdjacentHTML', 'document.write', 'html:' ) as $sink ) {
			$this->assertStringNotContainsString( $sink, $js );
		}

		$this->assertStringContainsString( 'textContent', $js );
		$this->assertStringContainsString( 'domNodes', $js );
	}

	/**
	 * The declaration block for one selector.
	 *
	 * @param string $css      Stylesheet.
	 * @param string $selector Selector.
	 * @return string
	 */
	private function rule( $css, $selector ) {
		$pattern = '/' . preg_quote( $selector, '/' ) . '\s*\{([^}]*)\}/';

		if ( ! preg_match( $pattern, (string) $css, $match ) ) {
			return '';
		}

		return $match[1];
	}
}

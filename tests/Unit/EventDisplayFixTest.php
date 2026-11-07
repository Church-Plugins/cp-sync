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
		$css   = file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/events.css' );
		$rule  = $this->rule( $css, '.cp-sync-series__date-title a' );

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

	public function test_placeholder_has_a_color_outside_the_page_wrapper() {
		$css = file_get_contents( dirname( __DIR__, 2 ) . '/assets/css/events.css' );

		$this->assertMatchesRegularExpression( '/background: var\(--cp-sync-placeholder, #[0-9a-f]+\)/', (string) $css );
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

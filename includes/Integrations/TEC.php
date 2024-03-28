<?php

namespace CP_Sync\Integrations;

use CP_Sync\Exception;

class TEC extends Integration {

	public $id = 'tec';

	public $type = 'events';

	public $label = 'Events';

	public function actions() {
		parent::actions();
		add_action( 'tribe_events_single_event_before_the_content', [ $this, 'maybe_add_registration_button'] );
	}

	public function update_item( $item ) {

		if ( $id = $this->get_chms_item_id( $item['chms_id'] ) ) {
			$item['ID'] = $id;
		}

		unset( $item['chms_id'] );

		// Organizer does not ignore duplicates by default, so we are handling that
		if ( isset( $item['Organizer'] ) ) {
			$item['Organizer']['OrganizerID'] = \Tribe__Events__Organizer::instance()->create( $item['Organizer'], 'publish', true );

			if ( is_wp_error( $item['Organizer']['OrganizerID'] ) ) {
				unset( $item['Organizer'] );
				error_log( $item['Organizer']['OrganizerID']->get_error_message() );
			}
		}

		$id = tribe_create_event( $item );

		if ( ! $id ) {
			throw new Exception( 'Event could not be created' );
		}

		// TEC categories
		$categories = [];
		foreach( $item['event_category'] as $slug => $name ) {
			if ( is_int( $slug ) ) {
				$slug = sanitize_title( $name );
			}

			if ( ! $term = term_exists( $slug, 'tribe_events_cat' ) ) {
				$term = wp_insert_term( $name, 'tribe_events_cat', [ 'slug' => $slug ] );
			}

			if ( ! is_wp_error( $term ) ) {
				$categories[] = $term['term_id'];
			}
		}

		wp_set_post_terms( $id, $categories, 'tribe_events_cat' );

		return $id;
	}

	public function register_taxonomy($taxonomy, $args) {
		register_taxonomy( $taxonomy, 'tribe_events', $args );
	}

	public function maybe_add_registration_button() {

		if ( ! apply_filters( 'cp_sync_show_event_registration_button', false, get_the_ID() ) ) {
			return;
		}

		if ( ! $registration_url = get_post_meta( get_the_ID(), 'registration_url', true ) ) {
			return;
		}

		$button_text = __( 'Register', 'cp-sync' );
		$button_class = 'tribe-common-c-btn';

		if ( get_post_meta( get_the_ID(), 'registration_sold_out', true ) ) {
			$button_text = __( 'Sold Out', 'cp-sync' );
			$button_class .= ' disabled';
		}

		?>
		<div class="tribe-common cp-sync--register-cont">
			<a href="<?php echo esc_url( $registration_url ); ?>" class="<?php echo esc_attr( $button_class ); ?>"><?php echo esc_html( $button_text ); ?></a>
		</div>

		<style>
			.cp-sync--register-cont {
				margin-bottom: var(--tec-spacer-7);
				text-align: right;
			}

			.tribe-common.cp-sync--register-cont .tribe-common-c-btn {
				width: auto;
			}

			.cp-sync--register-cont .disabled {
				opacity: 0.5;
				pointer-events: none;
				cursor: default;
			}
		</style>
		<?php
	}

}
<?php

namespace CP_Sync\Integrations;

use CP_Sync\Exception;
use TEC\Events\Custom_Tables\V1\Models\Occurrence;

class TEC extends Integration {

	public $id = 'tec';

	public $type = 'events';

	public $label = 'Events';

	protected $post_type = 'tribe_events';

	public function actions() {
		parent::actions();
		 add_action( 'tribe_events_single_event_before_the_content', [ $this, 'maybe_add_registration_button'] );
	}

	public function update_item( $item ) {
		$existing = $this->get_chms_item_id( $item['chms_id'] );

		cp_sync()->logging->log( 'Updating event: ' . $item['post_title'] );

		$event = [];

		// Organizer
		if ( $organizer = $item['EventOrganizer'] ?? false ) {
			$existing_organizer = get_posts(
				[
					'post_type'   => 'tribe_organizer',
					'title'       => $organizer['organizer'],
					'numberposts' => 1,
				]
			);

			if ( ! empty( $existing_organizer) ) {
				$organizer = $existing_organizer[0];
			} else {
				$organizer = tribe_organizers()
					->set_args(
						[
							'organizer' => $organizer['organizer'],
							'email'     => $organizer['email'],
							'phone'     => $organizer['phone'],
						]
					)
					->create();
			}
			
			if ( $organizer ) {
				$event['organizer'] = $organizer->ID;
			} else {
				cp_sync()->logging->log( 'Error assigning organizer to event: ' . $item['post_title'] );
			}
		}

		// Venue
		if ( $venue = $item['EventVenue'] ?? false ) {
			$existing_venue = get_posts(
				[
					'post_type'   => 'tribe_venue',
					'title'       => $venue['venue'],
					'numberposts' => 1,
				]
			);

			if ( $existing_venue ) {
				$venue = $existing_venue[0];
			} else {
				$venue = tribe_venues()
					->set_args(
						[
							'venue'   => $venue['venue'],
							'status'  => 'publish',
							'address' => $venue['address'],
							'city'    => $venue['city'],
							'state'   => $venue['state'],
							'zip'     => $venue['zip'],
						]
					)
					->create();
			}

			if ( $venue ) {
				$event['venue'] = $venue->ID;
			} else {
				cp_sync()->logging->log( 'Error creating venue for post: ' . $item['post_title'] );
			}
		}

		$event['post_status'] = 'publish';
		$event['title'] = $item['post_title'] ?? '';
		$event['content'] = $item['post_content'] ?? '';
		$event['post_content'] = $item['post_content'] ?? '';
		$event['image'] = $item['thumbnail_url'] ?? '';
		$event['start_date'] = $item['EventStartDate'] ?? '';
		$event['end_date'] = $item['EventStartDate'] ?? '';
		$event['timezone'] = $item['EventTimezone'] ?? '';
		$event['url'] = $item['EventURL'] ?? '';
		$event['recurrence'] = $item['EventRecurrence'] ?? '';
		$event = array_filter( $event );

		if ( $existing ) {
			tribe_update_event( $existing, $item );
//			tribe_events()->where( 'id', absint( $existing ) )->set_args( $event )->save();
			$id = $existing;
		} else {
			$post = tribe_events()->set_args( $event )->create();
			if ( $post ) {
				$id = $post->ID;
			} else {
				throw new Exception( 'Event could not be created: ' . print_r( $event, true ) );
			}
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
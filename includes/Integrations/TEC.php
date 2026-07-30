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
		// Append via the_content rather than the legacy
		// `tribe_events_single_event_before_the_content` action: TEC's V2 single-event
		// views ( default since TEC 5 ) do not fire the legacy action, so it never
		// rendered. the_content is used by both V2 and classic single templates.
		add_filter( 'the_content', [ $this, 'maybe_add_registration_button' ] );
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
				// Capture the id to link AFTER the event is saved. Passing
				// `venue` to tribe_update_event() does NOT link the venue in
				// current TEC ( verified 6.15.13 — the ORM ignores it on update ),
				// so we set _EventVenueID directly below, the same way the CCB
				// integration does. Still set $event['venue'] for the create path.
				$venue_id       = $venue->ID;
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
		$event['end_date'] = $item['EventEndDate'] ?? '';
		$event['timezone'] = $item['EventTimezone'] ?? '';
		$event['url'] = $item['EventURL'] ?? '';
		$event['recurrence'] = $item['EventRecurrence'] ?? '';
		$event = array_filter( $event );

		if ( $existing ) {
			cp_sync()->logging->log( 'Updating existing event ID: ' . $existing );
			try {
				tribe_update_event( $existing, $event );
				$id = $existing;
				cp_sync()->logging->log( 'Successfully updated event ID: ' . $id );
			} catch ( \Exception $e ) {
				cp_sync()->logging->log( 'ERROR updating event: ' . $e->getMessage() );
				throw $e;
			}
		} else {
			cp_sync()->logging->log( 'Creating new event with args: ' . json_encode( array_keys( $event ) ) );
			try {
				$post = tribe_events()->set_args( $event )->create();
				if ( $post ) {
					$id = $post->ID;
					cp_sync()->logging->log( 'Successfully created event ID: ' . $id );
				} else {
					cp_sync()->logging->log( 'ERROR: tribe_events()->create() returned null/false' );
					throw new Exception( 'Event could not be created: ' . print_r( $event, true ) );
				}
			} catch ( \Exception $e ) {
				cp_sync()->logging->log( 'ERROR creating event: ' . $e->getMessage() );
				throw $e;
			}
		}

		// Set venue + website meta directly. tribe_update_event()/create() do not
		// reliably persist the `venue`/`url` args on update ( verified TEC 6.15.13 —
		// the ORM ignores them ), so write the meta explicitly once we have the event
		// id — matching the CCB integration's approach.
		if ( ! empty( $id ) ) {
			if ( ! empty( $venue_id ) ) {
				update_post_meta( $id, '_EventVenueID', $venue_id );
				cp_sync()->logging->log( "Linked venue {$venue_id} to event {$id}" );
			}

			// Post excerpt ( e.g. the PCO calendar `summary` short blurb ). Not a TEC
			// ORM field, so tribe_update_event()/create() drop it — write the core
			// post column directly. Left untouched when the source provides none, so
			// WordPress can still auto-generate one from the content.
			if ( isset( $item['post_excerpt'] ) && '' !== trim( (string) $item['post_excerpt'] ) ) {
				wp_update_post( [ 'ID' => $id, 'post_excerpt' => $item['post_excerpt'] ] );
			}

			// Event website ( TEC "Event Website" field ). Set from the source's public
			// URL when available, and CLEARED when absent so removing it at the source
			// ( or switching a feed that no longer provides one ) propagates instead of
			// leaving a stale link.
			if ( ! empty( $item['EventURL'] ) ) {
				update_post_meta( $id, '_EventURL', esc_url_raw( $item['EventURL'] ) );
			} else {
				delete_post_meta( $id, '_EventURL' );
			}

			// Arbitrary post meta the formatter asked us to persist ( e.g. the
			// registration_url that drives the Register button ). tribe_update_event()
			// only handles its own known fields, so meta_input was silently dropped
			// before — write it here.
			if ( ! empty( $item['meta_input'] ) && is_array( $item['meta_input'] ) ) {
				foreach ( $item['meta_input'] as $meta_key => $meta_value ) {
					update_post_meta( $id, $meta_key, $meta_value );
				}
			}
		}

		// TEC categories
		$categories = [];
		if ( empty( $item['event_category'] ) || ! is_array( $item['event_category'] ) ) {
			cp_sync()->logging->log( 'No event categories to process' );
			$item['event_category'] = [];
		}
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

	/**
	 * Append the Register button to a single event's content.
	 *
	 * Runs on the_content ( see actions() for why not the legacy action ). Guarded to
	 * the main single-event query so it never leaks into feeds, excerpts, or secondary
	 * loops. Visibility is driven by the global `showEventRegisterButton` setting
	 * ( default on ), still overridable via the `cp_sync_show_event_registration_button`
	 * filter. Renders only when the event carries a registration_url.
	 *
	 * @param string $content The post content.
	 * @return string
	 */
	public function maybe_add_registration_button( $content ) {
		if ( ! is_singular( 'tribe_events' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();

		// Visibility is the active ChMS's per-events-tab "Show Register button" setting
		// ( default on ). The events settings group name differs per ChMS, so ask the
		// ChMS for it. Still overridable via the filter.
		$active = \CP_Sync\ChMS\_Init::get_instance()->get_active_chms_class();
		$show   = $active
			? (bool) $active->get_setting( 'show_register_button', true, $active->get_events_settings_group() )
			: true;

		if ( ! apply_filters( 'cp_sync_show_event_registration_button', $show, $post_id ) ) {
			return $content;
		}

		$registration_url = get_post_meta( $post_id, 'registration_url', true );

		if ( ! $registration_url ) {
			return $content;
		}

		$button_text  = __( 'Register', 'cp-sync' );
		$button_class = 'tribe-common-c-btn';

		if ( get_post_meta( $post_id, 'registration_sold_out', true ) ) {
			$button_text   = __( 'Sold Out', 'cp-sync' );
			$button_class .= ' disabled';
		}

		$button = sprintf(
			'<div class="tribe-common cp-sync--register-cont"><a href="%1$s" class="%2$s">%3$s</a></div>',
			esc_url( $registration_url ),
			esc_attr( $button_class ),
			esc_html( $button_text )
		);

		$button .= '<style>
			.cp-sync--register-cont { margin-bottom: var(--tec-spacer-7); text-align: right; }
			.tribe-common.cp-sync--register-cont .tribe-common-c-btn { width: auto; }
			.cp-sync--register-cont .disabled { opacity: 0.5; pointer-events: none; cursor: default; }
		</style>';

		return $content . $button;
	}

}
<?php

namespace CP_Connect\ChMS;

use CP_Connect\Admin\Settings;
use PlanningCenterAPI\PlanningCenterAPI;

/**
 * Planning Center Online implementation
 *
 * @author costmo
 */
class PCO extends \CP_Connect\ChMS\ChMS {

	/**
	 * Options table key
	 *
	 * @var string
	 */
	public $settings_key = 'cpc_pco_options';

	/**
	 * Convenience reference to an external API connection
	 *
	 * @var PlanningCenterAPI
	 * @author costmo
	 */
	public $api = null;

	/**
	 * REST namespace for this integration
	 *
	 * @var string
	 */
	public $rest_namespace = '/pco';

	/**
	 * List of all formatted events
	 *
	 * @var array
	 */
	protected $events = [];

	/**
	 * List of all formatted campuses
	 *
	 * @var array
	 */
	protected $campuses = [];

	/**
	 * Load up, if possible
	 *
	 * @return void
	 * @author costmo
	 */
	public function integrations() {

		// If this integration is not configured, do not add our pull filters
		if( true === $this->load_connection_parameters() ) {
			$this->maybe_setup_taxonomies();

			$events_enabled = $this->get_option( 'events_enabled', 1, 'cpc_pco_connect' );
			if ( 1 == $events_enabled ) {
				add_action( 'cp_connect_pull_events', [ $this, 'pull_events' ] );
			} elseif ( 2 == $events_enabled ) {
				add_action( 'cp_connect_pull_events', [ $this, 'pull_registrations' ] );
			}

			if ( $this->get_option( 'groups_enabled', 1, 'cpc_pco_connect' ) ) {
				add_action( 'cp_connect_pull_groups', [ $this, 'pull_groups' ] );
			}
		}

		$this->setup_taxonomies( false );

		add_action( 'cp_tec_update_item_after', [ $this, 'event_taxonomies' ], 10, 2 );
		add_filter( 'cp_connect_show_event_registration_button', [ $this, 'show_event_registration_button' ] );
	}

	public function check_connection() {

		// make sure we have all required parameters
		foreach( [ 'app_id', 'secret' ] as $option ) {
			if ( ! Settings::get( $option, '', 'cpc_pco_connect' ) ) {
				return false;
			}
		}

		$raw = $this->api()
		            ->module( 'groups' )
		            ->get();

		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );

			return [
				'status'  => 'failed',
				'message' => $this->api()->errorMessage()['errors'][0]['detail'],
			];
		}

		return [
			'status'  => 'success',
			'message' => __( 'Connection successful', 'cp-connect' ),
		];

	}

	public function check_auth( $data ) {
		$data = $this->api()->module( 'people' )->table( 'me' )->get();

		if ( ! empty( $this->api()->errorMessage() ) ) {
			throw new \Exception( esc_html( $this->api()->errorMessage()['errors'][0]['detail'] ) );
		}

		return true;
	}

	public function get_auth_api_args() {
		return [
			'app_id' => [
				'type'        => 'string',
				'description' => 'The App ID for the Planning Center Online API',
				'required'    => true
			],
			'secret' => [
				'type'        => 'string',
				'description' => 'The Secret for the Planning Center Online API',
				'required'    => true
			]
		];
	}

	public function event_taxonomies( $item, $id ) {
		$taxonomies = [ 'Ministry Group', 'Ministry Leader', 'Frequency', 'cp_ministry' ];
		foreach( $taxonomies as $tax ) {
			$tax_slug = \CP_Connect\ChMS\ChMS::string_to_slug( $tax );
			$categories = [];

			if( !empty( $item['tax_input'][$tax_slug] ) ) {
				$term_value = '';
				if( is_string( $item['tax_input'][$tax_slug] ) ) {

					$term_value = $item['tax_input'][$tax_slug];
					if( ! $term = term_exists( $term_value, $tax_slug ) ) {
						$term = wp_insert_term( $term_value, $tax_slug );
					}
					if( !is_wp_error( $term ) ) {
						$categories[] = $term['term_id'];
					}

				} else if( is_array( $item['tax_input'][$tax_slug] ) ) {

					foreach( $item['tax_input'][$tax_slug] as $term_value ) {

						if ( !$term = term_exists( $term_value, $tax_slug ) ) {
							$term = wp_insert_term( $term_value, $tax_slug );
						}
						if( !is_wp_error( $term ) ) {
							$categories[] = $term_value;
						}
					}

				}

				wp_set_post_terms( $id, $categories, $tax_slug );
			}
		}
	}

	public function add_supports() {
		$this->add_support(
			'cp_groups',
			[
				'fetch_callback'           => [ $this, 'fetch_groups' ],
				'contextual_data_callback' => [ $this, 'group_contextual_data' ]
			]
		);

	}

	/**
	 * Setup foundational taxonomy data from the remote source if we do not have any such data locally
	 *
	 * @return void
	 * @author costmo
	 */
	private function maybe_setup_taxonomies() {
		// We have no data, populate it now
		if( get_option( 'cp_pco_event_taxonomies', false ) === false ) {
			$this->setup_taxonomies( true );
		}
	}

	/**
	 * Setup and register taxonomies for incoming data
	 *
	 *
	 * @param boolean $add_data		Set true to import remote data
	 * @return void
	 * @author costmo
	 */
	public function setup_taxonomies( $add_data = false ) {

		// Do not query the remote source on every page load
		// TODO: This needs to be more dynamic, but less heavy
		$taxonomies = apply_filters( 'cp_pco_event_taxonomies', get_option( 'cp_pco_event_taxonomies', [
			'Campus' 			=> false,
			'Ministry Group' 	=> false,
			'Event Type' 		=> false,
			'Frequency' 		=> false,
			'Ministry Leader' 	=> false
		] ) );

		if( $add_data ) {
			$raw_groups =
				$this->api()
					->module('calendar')
					->table('tag_groups')
					->get();

			if( !empty( $this->api()->errorMessage() ) ) {
				error_log( var_export( $this->api()->errorMessage(), true ) );
				return [];
			}

			if ( empty( $raw_groups['data'] ) ) {
				return [];
			}

			$taxonomies = [];
			foreach( $raw_groups['data'] as $group ) {
				$group_name = $group['attributes']['name'];
				$group_id = $group['id'];
				$taxonomies[ $group_name ] = $group_id;
			}
		}

		$output = [];

		foreach( $taxonomies as $group_name => $group_id ) {

			if( !array_key_exists( $group_name, $output ) ) {
				$output[ $group_name ] = [];
			}

			if( $add_data && !empty( $group_id ) ) {
				$raw_tags =
					$this->api()
						->module('calendar')
						->table('tag_groups')
						->id( $group_id )
						->includes('tags')
						->get();

				foreach( $raw_tags['included'] as $tag_data ) {

					if( !in_array( $tag_data['attributes']['name'], $output[ $group_name ] ) ) {
						$output[ $group_name ][] = $tag_data['attributes']['name'];
					}
				}

				update_option( 'cp_pco_event_taxonomies', $output );
			}
		}

		foreach( $output as $tax_name => $tax_data ) {

			$tax_slug = ChMS::string_to_slug( $tax_name );
			$labels = [
				'name'              => ucwords( $tax_name ),
				'singular_name'     => ucwords( $tax_name ),
				'search_items'      => 'Search ' . ucwords( $tax_name ),
				'all_items'         => 'All ' . ucwords( $tax_name ),
				'parent_item'       => 'Parent ' . ucwords( $tax_name ),
				'parent_item_colon' => 'Parent ' . ucwords( $tax_name ),
				'edit_item'         => 'Edit ' . ucwords( $tax_name ),
				'update_item'       => 'Update ' . ucwords( $tax_name ),
				'add_new_item'      => 'Add New ' . ucwords( $tax_name ),
				'new_item_name'     => 'New ' . ucwords( $tax_name ),
				'menu_name'         => ucwords( $tax_name ),
				'not_found'         => 'No ' . ucwords( $tax_name ) . " found",
				'no_terms'          => 'No ' . ucwords( $tax_name )
			];

			$args   = [
				'public'            => true,
				'hierarchical'      => false,
				'labels'            => $labels,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'query_var'         => true,
				'show_in_menu'      => false,
				'show_in_nav_menus' => false,
				'show_tag_cloud'    => true,
				'show_admin_column' => false,
				'slug'				=> $tax_slug
			];

			register_taxonomy( $tax_slug, 'tribe_events', $args );

			if( $add_data && !empty( $tax_data ) ) {
				foreach( $tax_data as $term ) {
					wp_insert_term( $term, $tax_slug );
				}
			}
		}
	}

	/**
	 * Get the details of a specific campus/location
	 *
	 * Names do not always match exactly, so we also try `$campus_name . ' campus'` and `$campus_name . ' campuses'`
	 *
	 * @param string $campus_name			The campus name to find
	 * @return void
	 * @author costmo
	 */
	public function pull_campus_details( $campus_name ) {

		if ( empty( $this->campuses ) ) {
			// The PCO API seemingly ignores all input to get a specific record
			$this->campuses =
				$this->api()
				     ->module( 'people' )
				     ->table( 'campuses' )
				     ->get();
			if ( ! empty( $this->api()->errorMessage() ) ) {
				error_log( var_export( $this->api()->errorMessage(), true ) );

				return [];
			}
		}


		if( !empty( $this->campuses ) && is_array( $this->campuses ) && !empty( $this->campuses['data'] ) ) {
			foreach( $this->campuses['data'] as $index => $location_data ) {
				$normal_incoming = strtolower( $campus_name );
				$normal_loop = strtolower( $location_data['attributes']['name'] ?? '' );

				if( $normal_incoming == $normal_loop ||
					$normal_incoming . ' campus' == $normal_loop ||
					$normal_incoming . ' campuses' == $normal_loop ) {

						$return_value = $location_data['attributes'];
						$return_value['id'] = $location_data['id'];
						// If we found one, return it now - no need to keep iterating the loop
						return $return_value;
				}
			}
		}

		return [];
	}

	/**
	 * Pull tags for an event instance
	 *
	 * @param array $event
	 * @return array
	 * @author costmo
	 */
	public function pull_event_tags( $event ) {
		if ( empty( $event['relationships'] ) || empty( $event['relationships']['tags'] ) ) {
			return [];
		}

		if ( empty( $this->events['included'] ) ) {
			return [];
		}

		$tags = [];
		foreach( $this->events['included'] as $include ) {
			if ( $include['type'] != 'Tag' ) {
				continue;
			}

			$tags[ $include['id'] ] = $include;
		}

		$event_tags = [];
		foreach( $event['relationships']['tags']['data'] as $tag ) {
			if ( ! empty( $tags[ $tag['id'] ] ) ) {
				$event_tags[] = $tags[ $tag['id'] ];
			}
		}

		return $event_tags;
	}

	/**
	 * Pull all future published/public events from the ChMS and massage the data for WP insert
	 *
	 * @param array $events
	 * @param bool $show_progress		true to show progress on the CLI
	 * @return array
	 * @author costmo
	 */
	public function pull_events( $integration, $show_progress = false ) {

		error_log( "PULL EVENTS STARTED " . date( 'Y-m-d H:i:s' ) );

		// Pull upcoming events
		$raw =
			$this->api()
				->module('calendar')
				->table('event_instances')
				->includes('event,event_times')
				->filter('future')
				->order('starts_at')
				->get();

		$this->events =
			$this->api()
				->module( 'calendar' )
				->table('events')
				->includes('tags' )
				->where('visible_in_church_center', '=', 'true' )
				->get();

		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );
			return [];
		}

		// Give massaged data somewhere to go - the return variable
		$formatted = [];

		// Collapse and normalize the response
		$items = [];
		if( !empty( $raw ) && is_array( $raw ) && !empty( $raw['data'] ) && is_array( $raw['data'] ) ) {
			$items = $raw['data'];
		}

		if( $show_progress ) {
			$progress = \WP_CLI\Utils\make_progress_bar( "Importing " . count( $this->events['data'] ) . " events", count( $this->events['data'] ) );
		}

		$counter = 0;
		// Iterate the received events for processing
		foreach ( $items as $event_instance ) {

			if( $show_progress ) {
				$progress->tick();
			}

			// Sanith check the event
			$event_id = $event_instance['relationships']['event']['data']['id'] ?? 0;
			if( empty( $event_id ) ) {
				continue;
			}

			// Pull top-level event details
			if ( ! $event = $this->pull_event( $event_id ) ) {
				continue;
			}

			$time_ids = wp_list_pluck( $event_instance['relationships']['event_times']['data'], 'id' );
			$start_date = $end_date = false;

			foreach( $raw['included'] as $include ) {
				if ( ! in_array( $include['id'], $time_ids ) ) {
					continue;
				}

				if ( empty( $include['attributes']['visible_on_kiosks'] ) ) {
					continue;
				}

				$start_date = new \DateTime( $include['attributes']['starts_at'] );
				$end_date   = new \DateTime( $include['attributes']['ends_at'] );
				break;
			}

			if ( ! $start_date || ! $end_date ) {
				continue;
			}

			$start_date->setTimezone( wp_timezone() );
			$end_date->setTimezone( wp_timezone() );

			// Begin stuffing the output
			$args = [
				'chms_id'        => $event_instance['id'],
				'post_status'    => 'publish',
				'post_title'     => $event['attributes']['name'] ?? '',
				'post_content'   => $event['attributes']['description'] ?? '',
				'post_excerpt'   => $event['attributes']['summary'] ?? '',
				'tax_input'      => [],
				'event_category' => [],
				'thumbnail_url'  => '',
				'meta_input'     => [
					'registration_url' => $event['attributes']['registration_url'] ?? '',
				],
				'EventStartDate'        => $start_date->format( 'Y-m-d' ),
				'EventEndDate'          => $end_date->format( 'Y-m-d' ),
				// 'EventAllDay'           => $event[''],
				'EventStartHour'        => $start_date->format( 'G' ),
				'EventStartMinute'      => $start_date->format( 'i' ),
				// 'EventStartMeridian'    => $event[''],
				'EventEndHour'          => $end_date->format( 'G' ),
				'EventEndMinute'        => $end_date->format( 'i' ),
				// 'EventEndMeridian'      => $event[''],
				// 'EventHideFromUpcoming' => $event[''],
				// 'EventShowMapLink'      => $event[''],
				// 'EventShowMap'          => $event[''],
				// 'EventCost'             => $event[''],
				// 'EventURL'              => $event[''],
				// 'FeaturedImage'         => $event['attributes']['image_url'] ?? '',
			];

			// Fesatured image
			if ( ! empty( $event['attributes']['image_url'] ) ) {
				$url = explode( '?', $event['attributes']['image_url'], 2 );
				$args['thumbnail_url'] = $url[0] . '?tecevent-' . sanitize_title( $args['post_title'] ) . '.jpeg&' . $url[1];
			}

			// Generic location - a long string with an entire address
			// if ( ! empty( $event_instance['attributes']['location'] ) ) {
				// $args['tax_input']['cp_location'] = $event_instance['attributes']['location'];
			// }

			// Get the event's tags and pair them with appropriate taxonomies
			$tags = $this->pull_event_tags( $event );

			$tag_output = [];
			if ( ! empty( $tags ) && is_array( $tags ) ) {
				foreach( $tags as $tag ) {
					$tag_id = $tag['id'] ?? 0;
					$tag_value = $tag['attributes']['name'] ?? '';
					$tag_output[ $tag_value ] = $this->taxonomies_for_tag( $tag_value );
				}
			}

			// $tax_output = [];
			// // Segragate the "tags" that have non-tag data
			// foreach( $tag_output as $tag_text => $included_taxonomies ) {

			// 	// This "tag" is a campus/location
			// 	if( in_array( 'campus', $included_taxonomies ) ) {

			// 		$campus =  $this->pull_campus_details( $tag_text );

			// 		// Map to Location/Venue for TEC
			// 		if( !empty( $campus ) && is_array( $campus ) ) {
			// 			$args['Venue'] = [
			// 				'Venue'    => $tag_text,
			// 				'Country'  => $campus['country'] ?? '',
			// 				'Address'  => $campus['street'] ?? '',
			// 				'City'     => $campus['city'] ?? '',
			// 				'State'    => $campus['state'] ?? '',
			// 				// 'Province' => $campus[''],
			// 				'Zip'      => $campus['zip'],
			// 				// 'Phone'    => $campus[''],
			// 			];
			// 		}

			// 	} else if( in_array( 'event_type', $included_taxonomies ) ) {
			// 		// This is a TEC Event Category
			// 		$args['event_category'][] = $tag_text;
			// 	} else if( in_array( 'ministry_group', $included_taxonomies ) ) {
			// 		// This is a TEC Event Category
			// 		$args['tax_input']['cp_ministry'] = $tag_text;
			// 	} else {

			// 		// This is something else - we're assuming that it's a taxonomy and that the taxonomy is already registered
			// 		foreach( $included_taxonomies as $loop_tax ) {
			// 			$args['tax_input'][ $loop_tax ] = $tag_text;
			// 		}
			// 	}
			// }

			// // Add event contact info
			// if( !empty( $event['contact'] ) ) {

			// 	// Normalize variables so they're easier to work with
			// 	$contact_email = $event['contact']['contact_data']['email_addresses'][0]['address'] ?? '';
			// 	$contact_phone = $event['contact']['contact_data']['phone_numbers'][0]['number'] ?? '';
			// 	$first_name = $event['contact']['first_name'] ?? '';
			// 	$last_name = $event['contact']['last_name'] ?? '';
			// 	$use_name = $first_name . ' ' . $last_name;

			// 	// Only include if the person is not "empty" (depending on the ChMS definition of "empty")
			// 	if( !empty( $first_name ) && 'no owner' !== strtolower( $use_name ) ) {
			// 		$args['Organizer'] = [
			// 			'Organizer' => $use_name,
			// 			'Email'     => $contact_email,
			// 			// 'Website'   => $event[''],
			// 			'Phone'     => $contact_phone,
			// 		];
			// 	}

			// }

			// Add the data to our output
			// $formatted[] = apply_filters( 'cp_connect_pco_event_args', $args, $event_instance, $event, $tags );

//			$counter++;
//			if( $counter > 20 ) {
//				return $formatted;
//			}
		}
		if( $show_progress ) {
			$progress->finish();
		}

		error_log( "PULL EVENTS FINISHED " . date( 'Y-m-d H:i:s') );

		$integration->process( $formatted );
	}

	/**
	 * Pull events from PCO Registrations
	 *
	 * @since  1.1.0
	 *
	 * @param $integration
	 * @param $show_progress
	 *
	 * @return array|void
	 * @throws \Exception
	 * @author Tanner Moushey, 12/20/23
	 */
	public function pull_registrations( $integration, $show_progress = false ) {
		error_log( "PULL EVENTS STARTED " . date( 'Y-m-d H:i:s' ) );

		$raw = $this->api()
		            ->module( 'registrations' )
		            ->table( 'events' )
		            ->includes( 'categories,event_location,event_times' )
		            ->order( 'starts_at' )
		            ->filter( 'unarchived,published' )
		            ->get();

		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );
			return [];
		}

		// Give massaged data somewhere to go - the return variable
		$formatted = [];

		// Collapse and normalize the response
		$items = [];
		if( !empty( $raw ) && is_array( $raw ) && !empty( $raw['data'] ) && is_array( $raw['data'] ) ) {
			$items = $raw['data'];
		}

		if( $show_progress ) {
			$progress = \WP_CLI\Utils\make_progress_bar( "Importing " . count( $this->events['data'] ) . " events", count( $this->events['data'] ) );
		}

		$counter = 0;
		// Iterate the received events for processing
		foreach ( $items as $event ) {

			if( $show_progress ) {
				$progress->tick();
			}

			// Sanity check the event
			$event_id = $event['id'] ?? 0;
			if( empty( $event_id ) ) {
				continue;
			}

			// Begin stuffing the output
			$args = [
				'chms_id'        => $event_id,
				'post_status'    => 'publish',
				'post_title'     => $event['attributes']['name'] ?? '',
				'post_content'   => $event['attributes']['description'] ?? '',
//				'post_excerpt'   => $event['attributes']['summary'] ?? '',
				'tax_input'      => [],
				'event_category' => [],
				'thumbnail_url'  => '',
				'meta_input'     => [
					'registration_url' => '',
				],
//				'EventStartDate'        => $start_date->format( 'Y-m-d' ),
//				'EventEndDate'          => $end_date->format( 'Y-m-d' ),
//				'EventAllDay'           => true,
//				'EventStartHour'        => $start_date->format( 'G' ),
//				'EventStartMinute'      => $start_date->format( 'i' ),
//				'EventStartMeridian'    => $event[''],
//				'EventEndHour'          => $end_date->format( 'G' ),
//				'EventEndMinute'        => $end_date->format( 'i' ),
//				'EventEndMeridian'      => $event[''],
//				'EventHideFromUpcoming' => $event[''],
//				'EventShowMapLink'      => $event[''],
//				'EventShowMap'          => $event[''],
//				'EventCost'             => $event[''],
//				'EventURL'              => $event[''],
//				'FeaturedImage'         => $event['attributes']['image_url'] ?? '',
			];

			if ( 'none' !== $event['attributes']['registration_type'] ) {
				$args['meta_input']['registration_url'] = trailingslashit( $event['attributes']['public_url'] ) . 'reservations/new/';
				$args['meta_input']['registration_sold_out'] = false;

				if ( ! empty( $event['attributes']['at_maximum_capacity'] ) ) {
					$args['meta_input']['registration_sold_out'] = true;
				}
			}

			// handle event times
			$times = $this->get_relationship_data( 'event_times', $event, $raw );

			$start_date = $end_date = false;

			if ( empty( $times[0] ) ) {
				continue;
			}

			$times      = reset( $times );
			$all_day    = boolval( $times['all_day'] );
			$start_date = new \DateTime( $times['starts_at'] );
			$start_date->setTimezone( wp_timezone() );

			if ( ! empty( $times['ends_at'] ) ) {
				$end_date   = new \DateTime( $times['ends_at'] );
				$end_date->setTimezone( wp_timezone() );
			} else {
				$all_day = true;
			}

			$args['EventStartDate'] = $start_date->format( 'Y-m-d' );
			$args['EventStartHour'] = $start_date->format( 'G' );
			$args['EventStartMinute'] = $start_date->format( 'i' );

			if ( $all_day ) {
				$args['EventAllDay'] = true;
			}

			if ( $end_date ) {
				$args['EventEndDate'] = $end_date->format( 'Y-m-d' );
				$args['EventEndHour'] = $end_date->format( 'G' );
				$args['EventEndMinute'] = $end_date->format( 'i' );
			}

			// Featured image
			if ( ! empty( $event['attributes']['logo_url'] ) ) {
				$args['thumbnail_url'] = $event['attributes']['logo_url'];
			}

			$categories = wp_list_pluck( $this->get_relationship_data( 'categories', $event, $raw ), 'name', 'slug' );

			if ( ! empty( $categories ) ) {
				$args['event_category'] = $categories;
			}

			$locations = $this->get_relationship_data( 'event_location', $event, $raw );

			if ( ! empty( $locations ) ) {
				$locations = reset( $locations );
				$location  = $this->get_location_details( $locations );

				if ( ! empty( $location['Venue'] ) ) {
					$args['Venue'] = $location;
				}
			}

			// Add the data to our output
			$formatted[] = apply_filters( 'cp_connect_pco_event_args', $args, $event, $raw );

		}

		if( $show_progress ) {
			$progress->finish();
		}

		error_log( "PULL EVENTS FINISHED " . date( 'Y-m-d H:i:s') );

		$integration->process( $formatted );
	}

	/**
	 * Pulls the relationship data from the included resources
	 *
	 * @since  1.1.0
	 *
	 * @param $object
	 * @param $item
	 * @param $data
	 *
	 * @return array
	 * @author Tanner Moushey, 12/20/23
	 */
	protected function get_relationship_data( $object, $item, $data ) {
		if ( empty( $item['relationships'] )
		     || empty( $item['relationships'][ $object ] )
		     || empty( $item['relationships'][ $object ]['data'] )
		     || empty( $data['included'] )
		) {
			return [];
		}

		$return = [];

		if ( isset( $item['relationships'][ $object ]['data']['id'] ) ) {
			$item['relationships'][ $object ]['data'] = [ $item['relationships'][ $object ]['data'] ];
		}

		foreach ( $item['relationships'][ $object ]['data'] as $relationship ) {
			if ( empty( $relationship['type'] ) || empty( $relationship['id'] ) ) {
				continue;
			}


			foreach ( $data['included'] as $include ) {
				if ( $include['id'] == $relationship['id'] && $include['type'] == $relationship['type'] ) {
					$return[] = $include['attributes'];
				}
			}
		}

		return $return;
	}

	/**
	 * Get location details for provided location
	 *
	 * @since  1.1.0
	 *
	 * @param $location
	 *
	 * @return array
	 * @author Tanner Moushey, 12/20/23
	 */
	protected function get_location_details( $location_data ) {
		if ( empty( $location_data['address_data'] ) || empty( $location_data['name'] ) ) {
			return [];
		}

		$location = [
			'Venue'   => $location_data['name'],
			'Country' => '',
			'Address' => '',
			'City'    => '',
			'State'   => '',
			'Zip'     => '',
		];

		foreach( $location_data['address_data'] as $part ) {
			if ( empty( $part['types'] ) ) {
				continue;
			}

			if ( in_array( 'street_number', $part['types'] ) ) {
				$location['Address'] = $part['long_name'] . $location['Address'];
			}

			if ( in_array( 'route', $part['types'] ) ) {
				$location['Address'] .= $part['long_name'];
			}

			if ( in_array( 'locality', $part['types'] ) ) {
				$location['City'] = $part['long_name'];
			}

			if ( in_array( 'administrative_area_level_1', $part['types'] ) ) {
				$location['State'] = $part['long_name'];
			}

			if ( in_array( 'country', $part['types'] ) ) {
				$location['Country'] = $part['long_name'];
			}

			if ( in_array( 'postal_code', $part['types'] ) ) {
				$location['Zip'] = $part['long_name'];
			}
		}

		return $location;
	}

	/**
	 * Pull details about a specific group from PCO
	 *
	 * @param int $group_id
	 * @return array
	 * @author costmo
	 */
	public function pull_group_details( $group, $included ) {
		$details = [];

		foreach ( $group['relationships'] as $relationship ) {
			foreach ( $included as $include ) {
				if ( empty( $include['id'] ) || empty( $relationship['data'] ) || empty( $relationship['data']['id'] ) ) {
					continue;
				}

				if ( $include['id'] == $relationship['data']['id'] ) {
					$details[] = $include;
				}
			}
		}

		return $details;
	}

	/**
	 * Get all groups from PCO
	 *
	 * @param int $event_id
	 * @return array
	 * @author costmo
	 */
	public function pull_groups( $integration ) {

		error_log( "PULL GROUPS STARTED" );

		// Pull groups here
		$raw =
			$this->api()
				->module('groups')
				->table('groups')
				->includes('location,group_type,enrollment')
				->get();

		if( !empty( $this->api()->errorMessage() ) ) {
			error_log( var_export( $this->api()->errorMessage(), true ) );
		}

		// Give massaged data somewhere to go - the return variable
		$formatted = [];

		// Collapse and normalize the response
		$items = [];
		if( !empty( $raw ) && is_array( $raw ) && !empty( $raw['data'] ) && is_array( $raw['data'] ) ) {
			$items = $raw['data'];
		}

		$formatted = [];

		$enrollment_status = $this->get_option( 'groups_enrollment_status', [ 'open' ] );
		$enrollment_strategies = $this->get_option( 'groups_enrollment_strategy', [ 'open_signup', 'request_to_join' ] );

		$counter = 0;
		foreach( $items as $group ) {

			$include    = true;
			$enrollment = $this->get_relationship_data( 'enrollment', $group, $raw );

			if ( ! empty( $enrollment_status ) || ! empty( $enrollment_strategies ) ) {
				$include = false;

				foreach ( $enrollment as $e ) {
					if ( empty( $e['strategy'] ) ) {
						continue;
					}

					$strategy_check = empty( $enrollment_strategies ) || in_array( $e['strategy'], $enrollment_strategies );
					$status_check   = empty( $enrollment_status ) || in_array( $e['status'], $enrollment_status );

					if ( $strategy_check && $status_check ) {
						$include = true;
					}
				}
			}

			if ( ! $include ) {
				continue;
			}

			$start_date = strtotime( $group['attributes']['created_at'] ?? null );
			$end_date   = strtotime( $group['attributes']['archived_at'] ?? null );

			$args = [
				'chms_id'       => $group['id'],
				'post_status'   => 'publish',
				'post_title'    => $group['attributes']['name'] ?? '',
				'post_content'  => $group['attributes']['description'] ?? '',
				'tax_input'     => [],
				// 'group_category'   => [],
				'group_type'    => [],
				// 'group_life_stage' => [],
				'meta_input'    => [
					'leader'     => $group['attributes']['contact_email'] ?? '',
					'start_date' => date( 'Y-m-d', $start_date ),
					'end_date'   => ! empty( $end_date ) ? date( 'Y-m-d', $end_date ) : null,
					'public_url' => $group['attributes']['public_church_center_web_url'] ?? '',
				],
				'thumbnail_url' => $group['attributes']['header_image']['original'] ?? '',
			];

			if ( ! empty( $group['attributes']['schedule'] ) ) {
				$args['meta_input']['frequency'] = $group['attributes']['schedule'];
			}

			$item_details = $this->pull_group_details( $group, $raw['included'] );

			$is_visible = true;
			foreach( $item_details as $index => $item_data ) {

				$type = $item_data['type'] ?? '';

				if( 'GroupType' === $type ) {
					$args['group_type'][] = $item_data['attributes']['name'] ?? '';

					if ( empty( $item_data['attributes']['church_center_visible'] ) ) {
						$is_visible = false;
					}
				} else if( 'Location' === $type ) {
					// TODO: Maybe pull location information from $group['relationships']['location']['data']['id']
					if ( 'exact' == $item_data['attributes']['display_preference'] )  {
						$args['meta_input']['location'] = $item_data['attributes']['full_formatted_address'] ?? '';
					} else {
						$args['meta_input']['location'] = $item_data['attributes']['name'] ?? '';
					}
				}
			}

			if ( ! $is_visible ) {
				continue;
			}

			// TODO: Look this up
			 if ( ! empty( $group['attributes']['schedule'] ) ) {
			 	$args['meta_input']['time_desc'] = $group['attributes']['schedule'];

			// 	if ( ! empty( $group['Meeting_Day'] ) ) {
			// 		$args['meta_input']['time_desc'] = $group['Meeting_Day'] . 's at ' . $args['meta_input']['time_desc'];
			// 		$args['meta_input']['meeting_day'] = $group['Meeting_Day'];
			// 	}
			 }

			// TODO: Look this up
			// if ( ! empty( $group['Congregation_ID'] ) ) {
			// 	if ( $location = $this->get_location_term( $group['Congregation_ID'] ) ) {
			// 		$args['tax_input']['cp_location'] = $location;
			// 	}
			// }

			// Not for PCO
			$args['group_category'] = []; // Is this different than group_type for PCO?

			// Not for PCO
			$args['group_life_stage'] = [];

			$formatted[] = $args;

			$counter++;
			// if( $counter > 5 ) {
			// 	return $formatted;
			// }
		}

		$integration->process( $formatted );
	}

	
	/**
	 * Register the settings fields
	 *
	 * @since  1.0.5
	 *
	 * @param $cmb2 \CMB2 object
	 *
	 * @author Tanner Moushey, 11/30/23
	 */
	public function api_settings( $cmb2 ) {

		// handle legacy options
		if ( $existing_options = get_option( 'pco_plugin_options' ) ) {
			foreach( [ 'PCO_APPLICATION_ID' => 'pco_app_id', 'PCO_SECRET' => 'pco_secret' ] as $old_option => $new_option ) {
				if ( ! empty( $existing_options[ $old_option ] ) ) {
					Settings::set( $new_option, $existing_options[ $old_option ] );
				}
			}

			delete_option( 'pco_plugin_options' );
		}

		if ( $existing_options = get_option( 'pco_main_options' ) ) {
			foreach( [ 'pco_app_id' => 'app_id', 'pco_secret' => 'secret' ] as $old_option => $new_option ) {
				if ( ! empty( $existing_options[ $old_option ] ) ) {
					Settings::set( $new_option, $existing_options[ $old_option ], 'cpc_pco_connect' );
				}
			}

			delete_option( 'pco_main_options' );
		}

		$cmb2->add_field( [
			'name' => 'PCO API Configuration',
			'id'   => 'pco_api_title',
			'desc' => '<p>The following parameters are required to authenticate to the API and then execute API calls to Planning Center Online.</p><p>You can get your authentication credentials by <a target="_blank" href="https://api.planningcenteronline.com/oauth/applications">clicking here</a> and scrolling down to "Personal Access Tokens"</p>',
			'type' => 'title',
		] );

		$cmb2->add_field( [
			'name' => 'Event',
			'id'   => 'pco_app_id',
			'type' => 'text',
		] );

		$cmb2->add_field( [
			'name' => 'Application ID',
			'id'   => 'pco_app_id',
			'type' => 'text',
		] );

		$cmb2->add_field( [
			'name' => 'Application Secret',
			'id'   => 'pco_secret',
			'type' => 'text',
		] );
	}

	/**
	 * Add PCO Settings Tab
	 *
	 * @since  1.1.0
	 *
	 * @author Tanner Moushey, 12/20/23
	 */
	public function api_settings_tab() {
		$args = array(
			'id'           => 'cpc_pco_page',
			'title'        => 'PCO Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => $this->settings_key,
			'parent_slug'  => 'cpc_main_options',
			'tab_group'    => 'cpc_main_options',
			'tab_title'    => 'Settings',
			'display_cb'   => [ $this, 'options_display_with_tabs' ]
		);

		$settings = new_cmb2_box( $args );

		$settings->add_field( [
			'name' => 'PCO Data Settings',
			'id'   => 'pco_data_title',
			'desc' => __( 'The following settings control how data is retrieved from Planning Center Online.', 'cp-connect' ),
			'type' => 'title',
		] );

		$settings->add_field( array(
			'name'    => __( 'Enable Events' ),
			'id'      => 'events_enabled',
			'type'    => 'radio_inline',
			'default' => 1,
			'options' => [
				1 => __( 'Pull from Calendar', 'cp-library' ),
				2 => __( 'Pull from Registrations (beta)', 'cp-library' ),
				0 => __( 'Do not pull', 'cp-library' ),
			]
		) );

		$settings->add_field( array(
			'name'    => __( 'Event Register Button' ),
			'id'      => 'events_register_button_enabled',
			'type'    => 'radio_inline',
			'default' => 0,
			'options' => [
				1 => __( 'Show', 'cp-library' ),
				0 => __( 'Hide', 'cp-library' ),
			]
		) );

		$settings->add_field( array(
			'name'    => __( 'Enable Groups' ),
			'id'      => 'groups_enabled',
			'type'    => 'radio_inline',
			'default' => 1,
			'options' => [
				1 => __( 'Pull from Groups', 'cp-library' ),
				0 => __( 'Do not pull', 'cp-library' ),
			]
		) );

		$settings->add_field( array(
			'name'    => __( 'Group Enrollment Status' ),
			'desc'    => __( 'Select the enrollment status options to include in the group sync.', 'cp-connect' ),
			'id'      => 'groups_enrollment_status',
			'type'    => 'multicheck',
			'default' => [ 'open' ],
			'options' => [
				'open'    => __( 'Open - strategy is not closed and no limits have been reached', 'cp-library' ),
				'closed'  => __( 'Closed - strategy is closed or limits have been reached', 'cp-library' ),
				'full'    => __( 'Full - member limit has been reached', 'cp-library' ),
				'private' => __( 'Private - group is unlisted', 'cp-library' ),
			]
		) );

		$settings->add_field( array(
			'name'    => __( 'Group Enrollment Strategy' ),
			'desc'    => __( 'Select the enrollment strategy options to include in the group sync.', 'cp-connect' ),
			'id'      => 'groups_enrollment_strategy',
			'type'    => 'multicheck_inline',
			'default' => [ 'request_to_join', 'open_signup' ],
			'options' => [
				'request_to_join' => __( 'Request to Join', 'cp-library' ),
				'open_signup'     => __( 'Open Signup', 'cp-library' ),
				'closed'          => __( 'Closed', 'cp-library' ),
			]
		) );
	}

	/**
	 * Get oAuth and API connection parameters from the database
	 *
	 * @return bool
	 */
	public function load_connection_parameters( $option_slug = '' ) {
		$app_id = Settings::get( 'app_id', '', 'cpc_pco_connect' );
		$secret = Settings::get( 'secret', '', 'cpc_pco_connect' );

		if( empty( $app_id ) || empty( $secret ) ) {
			return false;
		}

		putenv( "PCO_APPLICATION_ID=$app_id" );
		putenv( "PCO_SECRET=$secret" );

		return true;
	}

	/**
	 * Singleton instance of the third-party API client
	 *
	 * @return PlanningCenterAPI\PlanningCenterAPI
	 * @author costmo
	 */
	public function api() {
		if( empty( $this->api ) ) {
			$this->api = new PlanningCenterAPI();
		}

		return $this->api;
	}

	/**
	 * Return the PCO-related taxonomies that hold the input value
	 *
	 * @param string $tag			The tag/term to find
	 * @return void
	 * @author costmo
	 */
	protected function taxonomies_for_tag( $tag ) {

		// TODO: This needs to be more dynamic
		$taxonomies = [
			'campus' 			=> false,
			'ministry_group' 	=> false,
			'event_type' 		=> false,
			'frequency' 		=> false,
			'ministry_leader' 	=> false
		];

		$in_tax = [];
		foreach( $taxonomies as $tax => $ignored ) {

			$tax = strtolower( $tax );
			$exists = term_exists( $tag, $tax );
			if( !empty( $exists ) ) {
				$in_tax[] = $tax;
			}

		}

		return $in_tax;
	}

	/**
	 * Whether or not to show the event registration button
	 *
	 * @since  1.1.0
	 *
	 * @param $show
	 *
	 * @return mixed|string|void
	 * @author Tanner Moushey, 12/20/23
	 */
	public function show_event_registration_button( $show = false ) {
		return $this->get_option( 'events_register_button_enabled', $show );
	}

	/**
	 * Register PCO specific rest routes
	 */
	public function register_rest_routes() {
		parent::register_rest_routes();

		register_rest_route(
			"/cp-connect/v1/$this->rest_namespace/group_types",
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_group_types' ],
				'permission_callback' => [ $this, 'check_permissions' ],
			]
		);
	}
}
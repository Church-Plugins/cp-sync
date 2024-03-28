<?php

namespace CP_Sync\ChMS;

use ChurchCommunityBuilderAPI\API as CCB_API;
use CP_Sync\Admin\Settings;

class ChurchCommunityBuilder extends ChMS {

	public $api = null;

	public $rest_namespace = '/ccb';

	public function check_auth( $data ) {
		return true;
	}

	public function get_auth_api_args() {
		return [
			'api_prefix' => [
				'type' => 'string',
			],
			'api_user' => [
				'type' => 'string',
			],
			'api_pass' => [
				'type' => 'string',
			],
		];
	}

	public function integrations() {
		add_action( 'cp_sync_pull_groups', [ $this, 'pull_groups' ] );
		add_action( 'cp_update_item_after', [ $this, 'load_group_image' ], 10, 3 );
		add_filter( 'cp_group_get_thumbnail', [ $this, 'get_group_image' ], 10, 2 );
	}


	public function api() {

		if( empty( $this->api ) ) {
			$this->api = new CCB_API();
		}

		return $this->api;
	}

	public function check_connection() {

		// make sure we have all required parameters
		foreach( [ 'api_prefix', 'api_user', 'api_pass' ] as $option ) {
			if ( ! Settings::get( $option, false, 'cps_ccb_connect' ) ) {
				return false;
			}
		}

		try {
			$response = $this->api()->test();

			if ( 'success' == $response ) {
				return [
					'status' => 'success',
					'message' => __( 'Connection successful', 'cp-sync' ),
				];
			}

			return [
				'status' => 'failed',
				'message' => $response,
			];
		} catch ( \Exception $e ) {
			return [
				'status' => 'failed',
				'message' => $e->getMessage(),
			];
		}
	}

	public function pull_groups_2() {
		$args = [
			'query_string' => [
				'srv'                  => 'group_profiles',
				'include_participants' => 'false',
				'include_image_link'   => 'true',
				// 'per_page' => 25,
			],
			'refresh_cache' => 1,
		];

		$groups = $this->api()->get( $args ); //todo test this and return early if not what we want

		$groups = json_decode( json_encode( $groups->response->groups ), false );
	}

	public function pull_groups( $integration ) {

		$args = [
			'query_string' => [
				'srv'                  => 'group_profiles',
				'include_participants' => 'false',
				'include_image_link'   => 'true',
				// 'per_page' => 25,
			],
			'refresh_cache' => 1,
		];

		$groups = $this->api()->get( $args ); //todo test this and return early if not what we want

		$groups = json_decode( json_encode( $groups->response->groups ), false );

		$formatted = [];

		foreach ( $groups->group as $group ) {

			// Skip inactive and general groups
			// @TODO ... this should be a filter so it isn't specific to ChristPres
			if ( ! $group->inactive ) { continue; }
			if ( "Church - General" == $group->department ) { continue; }

			// Only process if it's a connect group type
			if ( "Connect Group" !== $group->group_type ) { continue; }
			// Client may also want small groups but leaving out for now
			// if ( "Small Group" !== $group->group_type ) { continue; }

			$args = [
				'chms_id'          => $group->{'@attributes'}->id,
				'post_status'      => 'publish',
				'post_title'       => $group->name,
				'post_content'     => '',
				'tax_input'        => [],
				'group_category'   => [],
				'group_type'       => [],
				'group_life_stage' => [],
				'meta_input'       => [
					'leader'           => $group->main_leader->full_name,
					'leader_email'     => $group->main_leader->email,
					'public_url'       => false,
					'registration_url' => $this->api()->get_base_url( 'group_detail.php?group_id=' . esc_attr( $group->{'@attributes'}->id ) ),
					'is_group_full'    => 0,
				],
				'thumbnail_url'    => '',
			];

			if ( is_string( $group->description ) && ! empty( $group->description ) ) {
				$args['post_content'] = $group->description;
			}

			if ( 'string' === gettype( $group->image ) && ! empty( $group->image ) ) {
				$thumb_url = $group->image;
				$args['thumbnail_url'] = $thumb_url . '#.png';
			}

			if ( ( $capacity = intval( $group->group_capacity ) ) && ( $current_members = intval( $group->current_members ) ) ) {
				if ( $capacity <= $current_members ) {
					$args['meta_input']['is_group_full'] = 'on';
				}
			}

			$address_city = ( !empty( $group->addresses->address->city ) && 'string' == gettype( $group->addresses->address->city ) ) ? $group->addresses->address->city : '';
			$address_state = ( !empty( $group->addresses->address->state ) && 'string' == gettype( $group->addresses->address->state ) ) ? $group->addresses->address->state : '';
			$address_zip = ( !empty( $group->addresses->address->zip ) && 'string' == gettype( $group->addresses->address->zip ) ) ? $group->addresses->address->zip : '';

			if ( !empty( $address_city ) ) {
				$args['meta_input']['location'] = sprintf( "%s, %s %s", $address_city, $address_state, $address_zip );
			} else {
				$args['meta_input']['location'] = sprintf( "%s %s", $address_state, $address_zip );
			}

			if ( !empty( $group->meeting_time ) && 'string' == gettype( $group->meeting_time ) ) {
				$args['meta_input']['time_desc'] = date( 'g:ia', strtotime( $group->meeting_time ) );

				if ( ! empty( $group->meeting_day ) && 'string' == gettype( $group->meeting_day ) ) {
					$args['meta_input']['time_desc'] = $group->meeting_day . 's at ' . $args['meta_input']['time_desc'];
					$args['meta_input']['meeting_day'] = $group->meeting_day;
				}
			}

			$args['meta_input']['kid_friendly'] = ( ( 'true' == $group->childcare_provided ) && 'string' == gettype( $group->childcare_provided ) ) ? 'on' : 0;

			if ( ! empty( $group->campus ) ) {
				$args['cp_location'] = $group->campus;
			}

			if ( ! empty( $group->group_type ) ) {
				$args['group_type'][] = $group->group_type;
			}

			$formatted[] = apply_filters( 'cp_sync_ccb_pull_groups_group', $args, $group );
		}

		$integration->process( $formatted );
	}


	public function load_group_image( $item, $post_id, $integration ) {

		if ( !empty( $item['thumbnail_url'] ) ) {
			$cached = $this->api()->cache_image( $item['thumbnail_url'], $item['chms_id'], 'group' );

			if ( $cached ) {
				$upload_dir = wp_upload_dir();
				$upload_dir_url = trailingslashit( $upload_dir['baseurl'] );
				$cached_url = $upload_dir_url . $this->api()->image_cache_dir . '/cache/group-' . $item['chms_id'] . '.jpg';

				update_post_meta( $post_id, '_thumbnail_url', $item['thumbnail_url'] );
				update_post_meta( $post_id, '_cached_thumbnail_url', $cached_url );
			}
		}

	}


	public function get_group_image( $value, $group ) {
		if ( $url = get_post_meta( $group->post->ID, '_cached_thumbnail_url', true ) ) {
			return $url;
		}

		return $value;
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
		if ( $existing_options = get_option( 'ccb_plugin_options' ) ) {
			foreach( [ 'api_prefix', 'api_user', 'api_pass' ] as $option ) {
				if ( ! empty( $existing_options[ $option ] ) ) {
					Settings::set( $option, $existing_options[ $option ], 'cps_ccb_connect' );
				}
			}

			delete_option( 'ccb_plugin_options' );
		}

		$cmb2->add_field( [
			'name'   => __( 'Your CCB Website', 'cp-sync' ),
			'id'     => 'api_prefix',
//			'desc'   => __( 'The URL you use to access your Church Community Builder site.', 'cp-sync' ),
			'type'   => 'text',
			'before_field' => '<code>https://</code>',
			'after_field'  => '<code>.ccbchurch.com</code><p class="cmb2-metabox-description">' . __( 'The URL you use to access your Church Community Builder site.', 'cp-sync' ) . '</p>',
		] );

		$cmb2->add_field( [
			'name' => __( 'API Username', 'cp-sync' ),
			'id'   => 'api_user',
			'type' => 'text',
			'desc' => __( 'This is different from the login you use for Church Community Builder.', 'cp-sync' ),
		] );

		$cmb2->add_field( [
			'name' => __( 'API Password', 'cp-sync' ),
			'id'   => 'api_pass',
			'type' => 'text',
			'attributes' => [
				'type' => 'password',
			],
		] );
	}

}

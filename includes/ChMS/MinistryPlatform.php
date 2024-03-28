<?php

namespace CP_Sync\ChMS;

use MinistryPlatformAPI\MinistryPlatformTableAPI as MP;
use CP_Sync\Admin\Settings;

/**
 * Ministry Platform Integration provider
 *
 * TODO: Localize strings
 *
 */
class MinistryPlatform extends ChMS {

	public $settings_key = 'cps_mp_options';

	public $rest_namespace = '/ministry-platform';

	public function check_auth( $data ) {
		// load connection parameters if they aren't already loaded
		if ( ! getenv( 'MP_API_ENDPOINT' ) ) {
			$this->mpLoadConnectionParameters();
		}

		$mp = new MP();

		// Authenticate to get access token required for API calls
		if ( ! $mp->authenticate() ) {
			throw new \Exception( 'Failed to authenticate with Ministry Platform' );
		}

		return true;
	}

	public function get_auth_api_args() {
		return [
			'api_endpoint'             => [
				'type'     => 'string',
				'required' => true,
			],
			'oauth_discovery_endpoint' => [
				'type'     => 'string',
				'required' => true,
			],
			'client_id'                => [
				'type'     => 'string',
				'required' => true,
			],
			'client_secret'            => [
				'type'     => 'string',
				'required' => true,
			],
			'api_scope'                => [
				'type'     => 'string',
				'required' => true,
			],
		];
	}

	public function integrations() {
		$this->mpLoadConnectionParameters();
		add_action( 'cp_sync_pull_events', [ $this, 'pull_events' ] );
		add_action( 'cp_sync_pull_groups', [ $this, 'pull_groups' ] );
		add_action( 'cmb2_render_mp_fields', [ $this, 'render_field_select' ], 10, 5 );
		add_action( 'admin_init', [ $this, 'maybe_update_options' ] );
	}

	/**
	 * Update options to new version.
	 *
	 * @since 1.1.0
	 */
	public function maybe_update_options() {
		// migrate legacy options

		$mp_api_config = get_option( 'ministry_platform_api_config' );

		// if ministry platform hasn't been configured or we've already migrated, exit.
		if ( ! $mp_api_config ) {
			return;
		}

		$custom_group_mapping = get_option( 'cp_group_custom_field_mapping' );
		$group_mapping        = get_option( 'ministry_platform_group_mapping' );
		$cps_mp_options       = get_option( 'cps_mp_options', array() );
		
		update_option( 'cps_main_options', array( 'chms' => 'mp' ) );
		update_option( 'cps_mp_connect', array(
			'api_endpoint'             => $mp_api_config['MP_API_ENDPOINT'],
			'oauth_discovery_endpoint' => $mp_api_config['MP_OAUTH_DISCOVERY_ENDPOINT'],
			'client_id'                => $mp_api_config['MP_CLIENT_ID'],
			'client_secret'            => $mp_api_config['MP_CLIENT_SECRET'],
			'api_scope'                => $mp_api_config['MP_API_SCOPE'],
		) );
		
		$mp_conf = array();

		if ( $group_mapping ) {
			$mp_conf['group_fields']        = array_merge( $this->get_default_fields( 'group' ), array_values( $group_mapping['fields'] ) );
			$mp_conf['group_field_mapping'] = $group_mapping['mapping'];
			$mp_conf['valid_fields']        = array_unique( array_values( $mp_conf['group_field_mapping'] ) );
		}

		if ( $custom_group_mapping ) {
			$mapping = array();

			foreach ( $custom_group_mapping as $field => $name ) {
				$slug = preg_replace( '/[\-\s]+/', '_', strtolower( $name ) );
				$slug = preg_replace( '/\W/', '', $slug );
				
				$mapping[$slug] = array(
					'name'  => $name,
					'value' => $field,
				);
			}

			$mp_conf['custom_field_mapping'] = $mapping;
		}
		
		update_option( 'cps_mp_configuration', $mp_conf );

		delete_option( 'ministry_platform_api_config' );
		delete_option( 'ministry_platform_group_mapping' );
		delete_option( 'cp_group_custom_field_mapping' );
	}

	/**
	 * Registers main options
	 */
	public function api_settings( $cmb2 ) {
		$cmb2->add_field( [
			'name' => __( 'API Configuration', 'cp-sync' ),
			'type' => 'title',
			'id'   => 'mp_api_config_title',
		] );

		$cmb2->add_field( [
			'name' => __( 'API Endpoint', 'cp-sync' ),
			'desc' => __( 'ex: https://my.mychurch.org/ministryplatformapi', 'cp-sync' ),
			'id'   => 'mp_api_endpoint',
			'type' => 'text',
		] );

		$cmb2->add_field( [
			'name' => __( 'Oauth Discovery Endpoint', 'cp-sync' ),
			'desc' => __( 'ex: https://my.mychurch.org/ministryplatform/oauth', 'cp-sync' ),
			'id'   => 'mp_oauth_discovery_endpoint',
			'type' => 'text',
		] );

		$cmb2->add_field( [
			'name' => __( 'Client ID', 'cp-sync' ),
			'desc' => __( 'The Client ID is defined in MP on the API Client page.', 'cp-sync' ),
			'id'   => 'mp_client_id',
			'type' => 'text',
		] );

		$cmb2->add_field( [
			'name' => __( 'Client Secret', 'cp-sync' ),
			'desc' => __( 'The Client Secret is defined in MP on the API Client page.', 'cp-sync' ),
			'id'   => 'mp_client_secret',
			'type' => 'text',
		] );

		$cmb2->add_field( [
			'name' => __( 'Scope', 'cp-sync' ),
			'desc' => __( 'Will usually be http://www.thinkministry.com/dataplatform/scopes/all', 'cp-sync' ),
			'id'   => 'mp_api_scope',
			'type' => 'text',
		] );
	}

	/**
	 * Registers options in the Settings tab
	 */
	public function api_settings_tab() {
		$args = array(
			'id'           => 'cps_mp_page',
			'title'        => 'Ministry Platform Settings',
			'object_types' => array( 'options-page' ),
			'option_key'   => $this->settings_key,
			'parent_slug'  => 'cps_main_options',
			'tab_group'    => 'cps_main_options',
			'tab_title'    => 'Settings',
			'display_cb'   => [ $this, 'options_display_with_tabs' ]
		);

		$settings = new_cmb2_box( $args );

		$settings->add_field( [
			'name'        => __( 'Group Fields', 'cp-sync' ),
			'type'        => 'title',
			'id'          => 'group_fields_title',
		] );

		$settings->add_field( [
			'name'  => __( 'Group Fields', 'cp-sync' ),
			'type'  => 'mp_fields',
			'id'    => 'group_fields', // id must be in the format of {object_type}_fields
			'table' => 'Groups',
			'desc'  => __( 'These fields are pulled from Ministry Platform when pulling groups', 'cp-sync' )
		] );

		$settings->add_field( [
			'name' => __( 'Group Field Mapping', 'cp-sync' ),
			'type' => 'title',
			'id'   => 'group_mapping_title',
			'desc' => __( 'The following parameters are used to map Ministry Platform groups to the CP Groups plugin', 'cp-sync' ),
		] );

		$mapping      = $this->get_default_object_mapping( 'group' );
		$valid_fields = $this->get_valid_fields( 'group', 'Groups' );
		$names        = $this->get_field_labels( 'group' );

		if ( empty( $valid_fields ) ) {
			return;
		}

		$assoc_fields = array();
		foreach ( $valid_fields as $field ) {
			if ( ! is_string( $field ) || empty( $field ) ) {
				continue;
			}
			$assoc_fields[ $field ] = $field;
		}

		foreach( $mapping as $key => $value ) {
			$name = isset( $names[ $key ] ) ? $names[ $key ] : '';

			$settings->add_field( [
				'name'             => $name,
				'desc'             => '',
				'id'               => 'group_mapping_' . $key,
				'type'             => 'select',
				'show_option_none' => true,
				'options'          => $assoc_fields,
				'default'          => $value,
			] );
		}

		$settings->add_field( [
			'name' => __( 'Custom Field Mapping', 'cp-sync' ),
			'type' => 'title',
			'id'   => 'custom_group_mapping_title',
			'desc' => __( 'Adding fields below will create custom meta fields that will be added to groups. The information will be saved in a meta key called `cp_sync_{key}` where key is the field name converted to slug format.', 'cp-sync' ),
		] );

		$instance = $this;

		$settings->add_field( [
			'name' => __( 'Custom Field Mapping', 'cp-sync' ),
			'type' => 'text',
			'id'   => 'group_mapping',
			'render_row_cb' => function( $field_args, $field ) use ($instance) {
				$instance->display_custom_mapping_fields( $field_args, $field );
			},
			'table' => 'Groups',
		] );
	}

	/**
	 * Gets an object with data and a mapping array, and returns the object values associated with the mapping keys
	 *
	 * @param array $data The data to map
	 * @param array $mapping The mapping array
	 */
	function get_mapped_values( $data, $mapping ) {
		$mapped_values = array();

		foreach( $mapping as $key => $value ) {
			if( isset( $data[ $value ] ) ) {
				$mapped_values[ $key ] = $data[ $value ];
			}
		}

		return $mapped_values;
	}

	/**
	 * Render a interface to select additional fields to grab from the API
	 *
	 * @param string $option_id The option id.
	 */
	public function render_field_select( $field, $escaped_value, $object_id, $object_type, $field_type_object ) {
		preg_match( '/^([a-z]+)_fields$/', $field->args['id'], $matches );

		$object_type = $matches[1];

		if ( ! $object_type ) {
			return;
		}

		$mp = new MP();

		try {
			$mp->authenticate();
			$table = $mp->table( $field->args['table'] );

			// makes a dummy request just to get any error messages from user specified fields.
			$table->select( implode( ',', $this->get_all_fields( $object_type ) ) )->top( 1 )->get();

			$error = $table->errorMessage() ? json_decode( (string) $table->errorMessage(), true ) : false;
		} catch ( \Exception | \InvalidArgumentException | \Error $e ) {
			$error = array( 'Message' => $e->getMessage() );
		}

		$default_fields = json_encode( $this->get_default_fields( $object_type ) );
		$initial_data   = json_encode( $this->get_custom_fields( $object_type ) );

		?>
		<div
			class="cp-sync-field-select"
			data-object-type="<?php echo esc_attr( $object_type ); ?>"
			data-default-fields="<?php echo esc_attr( $default_fields ); ?>"
		>
			<code class="cp-sync-fields-preview"></code>
			<br style="height: 2rem" />
			<ul class="cp-sync-field-select__options"></ul>
			<div class="cp-sync-field-select__add">
				<input class="cp-sync-field-select__add-input" type="text" placeholder="Table_Name.Field_Name" />
				<button class="cp-sync-field-select__add-button button button-primary" type="button">Add</button>
			</div>
			<input
				type="hidden"
				name="<?php echo esc_attr( $field->args['id'] ); ?>"
				id="<?php echo esc_attr( $field->args['id'] ); ?>"
				value="<?php echo esc_attr( $initial_data ); ?>"
			/>
			<!-- displays an error message if one exists -->
			<?php if ( $error ) : ?>
				<div>
					<h4>Ministry Platform API Error</h4>
					<code><?php echo $error['Message']; ?></code>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get valid field names from the MP API.
	 *
	 * @param string $object_type The object type to get fields for.
	 * @param string $table The MP table to check.
	 *
	 * @return array
	 */
	protected function get_valid_fields( $object_type, $table ) {
		// initialize the MP API wrapper.
		$mp = new MP();

		$valid_fields = array();

		try {
			$mp->authenticate();
		} catch ( \Exception | \InvalidArgumentException $e ) {
			return $valid_fields;
		}

		$fields = $this->get_all_fields( $object_type );

		// get the list of fields from the Groups table.
		$table = $mp->table( $table );

		// gets a group from API just to verify that all specified fields exist.

		try {
			$group = $table->select( implode( ',', $fields ) )->top( 1 )->get();
		} catch ( \Exception | \InvalidArgumentException | \Error $e ) {
			$group = false;
		}

		if ( $table->errorMessage() || ! $group ) {
			return $valid_fields;
		}

		if ( $group && count( $group ) > 0 ) {
			$group = $group[0];
		}

		// adds column names from group response to the available fields.
		if ( ! empty( $group ) ) {
			$valid_fields = array_merge( $valid_fields, array_keys( $group ) );
		}

		return $valid_fields;
	}

	/**
	 * Gets field labels for an object type.
	 *
	 * @param string $object_type The object type to get labels for
	 *
	 * @return array
	 */
	protected function get_field_labels( $object_type ) {
		switch( $object_type ) {
			case 'group':
				return array(
					'chms_id'             => 'Group ID',
					'post_title'          => 'Group Name',
					'post_content'        => 'Description',
					'leader'              => 'Group Leader',
					'start_date'          => 'Start Date',
					'end_date'            => 'End Date',
					'thumbnail_url'       => 'Image ID',
					'frequency'           => 'Meeting Frequency',
					'location'            => 'Congregation ID',
					'city'                => 'City',
					'state_or_region'     => 'State/Region',
					'postal_code'         => 'Postal Code',
					'meeting_time'        => 'Meeting Time',
					'meeting_day'         => 'Meeting Day',
					'cp_location'         => 'Group Campus',
					'group_category'      => 'Group Focus',
					'group_type'          => 'Group Type',
					'group_life_stage'    => 'Life Stage',
					'kid_friendly'        => 'Child Friendly',
					'handicap_accessible' => 'Accessible',
					'virtual'             => 'Virtual',
				);
			default:
				return array();
		}
	}

	/**
	 * Returns any custom user-created object mappings
	 *
	 * @param string $object_type The object type to get mappings for
	 *
	 * @return array
	 */
	public function get_custom_fields( $object_type ) {
		return json_decode( $this->get_option( $object_type . '_fields', '[]' ), true );
	}

	/**
	 * Returns all object mapping fields
	 */
	protected function get_all_fields( $object_type ) {
		return array_merge( $this->get_default_fields( $object_type ), $this->get_custom_fields( $object_type ) );
	}

	/**
	 * The default object mapping fields to fetch from the MP API
	 *
	 * @param string $object_type The object type to get mappings for
	 */
	protected function get_default_fields( $object_type ) {
		switch( $object_type ) {
			case 'group':
				return array(
					'Group_ID',
					'Group_Name',
					'Group_Type_ID_Table.[Group_Type]',
					'Groups.Congregation_ID',
					'Primary_Contact_Table.[First_Name]',
					'Primary_Contact_Table.[Last_Name]',
					'Groups.Description',
					'Groups.Start_Date',
					'Groups.End_Date',
					'Life_Stage_ID_Table.[Life_Stage]',
					'Group_Focus_ID_Table.[Group_Focus]',
					'Offsite_Meeting_Address_Table.[Postal_Code]',
					'Offsite_Meeting_Address_Table.[Address_Line_1]',
					'Offsite_Meeting_Address_Table.[City]',
					'Offsite_Meeting_Address_Table.[State/Region]',
					'Meeting_Time',
					'Meeting_Day_ID_Table.[Meeting_Day]',
					'Meeting_Frequency_ID_Table.[Meeting_Frequency]',
					'dp_fileUniqueId as Image_ID',
					'Primary_Contact_Table.Display_Name',
					'Child_Friendly_Group',
					'Accessible',
					'Meets_Online',
				);
			default:
				return array();
		}
	}

	/**
	 * The default object field to MP field mapping
	 *
	 * @param string $object_type The object type to get mappings for
	 *
	 * @return array
	 */
	protected function get_default_object_mapping( $object_type ) {
		switch( $object_type ) {
			case 'group':
				return array(
					'chms_id'             => 'Group_ID',
					'post_title'          => 'Group_Name',
					'post_content'        => 'Description',
					'leader'              => 'Display_Name',
					'start_date'          => 'Start_Date',
					'end_date'            => 'End_Date',
					'thumbnail_url'       => 'Image_ID',
					'frequency'           => 'Meeting_Frequency',
					'city'                => 'City',
					'state_or_region'     => 'State/Region',
					'postal_code'         => 'Postal_Code',
					'meeting_time'        => 'Meeting_Time',
					'meeting_day'         => 'Meeting_Day',
					'cp_location'         => 'Congregation_ID',
					'group_category'      => 'Group_Focus',
					'group_type'          => 'Group_Type',
					'group_life_stage'    => 'Life_Stage',
					'kid_friendly'        => 'Child_Friendly_Group',
					'handicap_accessible' => 'Accessible',
					'virtual'             => 'Meets_Online',
				);
			default:
				return array();
		}
		
	}

	/**
	 * Gets custom object mapping data
	 *
	 * @param string $object_type The object type to get mappings for.
	 *
	 * @return array
	 */
	protected function get_custom_object_mapping( $object_type ) {
		$mapping_data = $this->get_option( $object_type . '_mapping', '[]' );
		return json_decode( $mapping_data, true );
	}

	/**
	 * Returns all fields mapped to MP data
	 *
	 * @param string $object_type The object type to get mappings for
	 *
	 * @return array
	 */
	protected function get_object_mapping( $object_type ) {
		$object_key_search = $object_type . '_mapping_';
		$settings          = get_option( $this->settings_key );

		$mapping = array();
		foreach ( $settings as $key => $value ) {
			if ( strpos( $key, $object_key_search ) === 0 ) {
				$key = str_replace( $object_key_search, '', $key );
				$mapping[ $key ] = $value;
			}
		}

		return $mapping;
	}


	/**
	 * Render the custom field mappings
	 *
	 * @return void
	 * @author costmo
	 */
	protected function display_custom_mapping_fields( $field_args, $field ) {
		preg_match( '/^([a-z]+)_mapping$/', $field_args['id'], $matches );

		$object_type = $matches[1];
		$table       = $field_args['table'];

		if ( ! $object_type ) {
			return;
		}

		$field_template = <<<EOT
			<template class="cp-sync-custom-mapping-template">
				<div class="cmb-row cmb-type-select cps-custom-mapping--row">
					<div class="cmb-th">
						<input type="text" placeholder="Additional Field" class="cps-custom-mapping--meta-key regular-text" />
					</div>
					<div class="cmb-td">
						<select class="cps-custom-mapping--field-name">%s</select>
						<button class="cps-custom-mapping--remove button button-secondary" type="button">Remove</button>
					</div>
				</div>
			</template>
		EOT;

		$options = $this->get_valid_fields( $object_type, $table );
		$options = implode( '', array_map( function( $val ) {
			return sprintf( '<option value="%s">%s</option>', esc_attr( $val ), esc_html( $val ) );
		}, $options ) );

		$field_template = sprintf(
			$field_template,
			$options
		);

		$custom_mapping_data = json_encode( $this->get_custom_object_mapping( $object_type ) );

		?>
		<div
			class="cmb-row cmb-type-select cps-custom-mapping"
			data-object-type="<?php echo esc_attr( $object_type ); ?>"
			data-mapping="<?php echo esc_attr( $custom_mapping_data ); ?>"
			style="padding: 0;"
		>
			<?php echo $field_template; ?>
			<div class="cps-custom-mapping--rows cmb2-metabox"></div>
			<button class="cps-custom-mapping--add button button-primary" type="button">Add</button>
			<input
				type="hidden"
				name="<?php echo esc_attr( $field_args['id'] ); ?>"
				value="<?php echo esc_attr( $custom_mapping_data ); ?>"
			/>
		</div>
		<?php
	}

	function get_option_value( $key, $options = false ) {

		if ( ! $options ) {
			$options = get_option( 'ministry_platform_api_config' );
		}

		// If the options don't exist, return empty string
		if ( ! is_array( $options ) ) {
			return '';
		}

		// If the key is in the array, return the value, else return empty string.

		return array_key_exists( $key, $options ) ? $options[ $key ] : '';

	}

	/**
	 * Get oAuth and API connection parameters from the database
	 *
	 */
	function mpLoadConnectionParameters() {
		$options = array(
			'MP_API_ENDPOINT'             => Settings::get( 'api_endpoint', '', 'cps_mp_connect' ),
			'MP_OAUTH_DISCOVERY_ENDPOINT' => Settings::get( 'oauth_discovery_endpoint', '', 'cps_mp_connect' ),
			'MP_CLIENT_ID'                => Settings::get( 'client_id', '', 'cps_mp_connect' ),
			'MP_CLIENT_SECRET'            => Settings::get( 'client_secret', '', 'cps_mp_connect' ),
			'MP_API_SCOPE'                => Settings::get( 'api_scope', '', 'cps_mp_connect' ),
		);

		// if there are unset options, exit
		if ( array_filter( $options ) !== $options ) {
			return;
		}

		foreach ( $options as $option => $value ) {
			putenv( "$option=$value" );
		}
	}

	/**
	 * Handles pulling events from Ministry Platform
	 *
	 * @param \CP_Sync\Integrations\TEC $integration The integration to pull events for.
	 */
	public function pull_events( $integration ) {
		$mp = new MP();

		// Authenticate to get access token required for API calls
		if ( ! $mp->authenticate() ) {
			return false;
		}

		$events = $mp->table( 'Events' )
								->select( "Event_ID, Event_Title, Events.Congregation_ID, Event_Type_ID_Table.[Event_Type],
								Congregation_ID_Table.[Congregation_Name], Events.Location_ID, Location_ID_Table.[Location_Name],
								Location_ID_Table_Address_ID_Table.[Address_Line_1], Location_ID_Table_Address_ID_Table.[Address_Line_2],
								Location_ID_Table_Address_ID_Table.[City], Location_ID_Table_Address_ID_Table.[State/Region],
								Location_ID_Table_Address_ID_Table.[Postal_Code], Meeting_Instructions, Events.Description, Events.Program_ID,
								Program_ID_Table.[Program_Name], Events.Primary_Contact, Primary_Contact_Table.[First_Name],
								Primary_Contact_Table.[Last_Name], Primary_Contact_Table.[Email_Address], Event_Start_Date, Event_End_Date,
								Visibility_Level_ID, Featured_On_Calendar, Events.Show_On_Web, Online_Registration_Product, Registration_Form,
								Registration_Start, Registration_End, Registration_Active, _Web_Approved, dp_fileUniqueId as Image_ID" )
								->filter( "Events.Show_On_Web = 'TRUE' AND Events._Web_Approved = 'TRUE' AND Events.Visibility_Level_ID = 4 AND Events.Event_End_Date >= getdate()" )
								->get();

		$formatted = [];

		foreach ( $events as $event ) {
			$start_date = strtotime( $event['Event_Start_Date'] );
			$end_date   = strtotime( $event['Event_End_Date'] );

			$args = [
				'chms_id'          => $event['Event_ID'],
				'post_status'      => 'publish',
				'post_title'       => $event['Event_Title'],
				'post_content'     => $event['Description'] . '<br />' . $event['Meeting_Instructions'],
				// 'post_excerpt'     => $event['Description'],
				'tax_input'        => [],
				'event_category'   => [],
				'thumbnail_url'    => '',
				'meta_input'       => [
					'cp_registration_form'   => $event['Registration_Form'],
					'cp_registration_start'  => $event['Registration_Start'],
					'cp_registration_end'    => $event['Registration_End'],
					'cp_registration_active' => $event['Registration_Active'],
				],
				'EventStartDate'   => date( 'Y-m-d', $start_date ),
				'EventEndDate'     => date( 'Y-m-d', $end_date ),
				// 'EventAllDay'           => $event[''],
				'EventStartHour'   => date( 'G', $start_date ),
				'EventStartMinute' => date( 'i', $start_date ),
				// 'EventStartMeridian'    => $event[''],
				'EventEndHour'     => date( 'G', $end_date ),
				'EventEndMinute'   => date( 'i', $end_date ),
				// 'EventEndMeridian'      => $event[''],
				// 'EventHideFromUpcoming' => $event[''],
				// 'EventShowMapLink'      => $event[''],
				// 'EventShowMap'          => $event[''],
				// 'EventCost'             => $event[''],
				// 'EventURL'              => $event[''],
				// 'FeaturedImage'         => $event[''],
			];

			if ( ! empty( $event['Image_ID'] ) ) {
				$args['thumbnail_url'] = $this->get_option_value( 'MP_API_ENDPOINT' ) . '/files/' . $event['Image_ID'] . '?mpevent-' . sanitize_title( $args['post_title'] ) . '.jpeg';
			}

			if ( ! empty( $event['Congregation_ID'] ) ) {
				if ( $location = $this->get_location_term( $event['Congregation_ID'] ) ) {
					$args['cp_location'] = $location;
				}
			}

			if ( ! empty( $event['Event_Type'] ) ) {
				$args['event_category'][] = $event['Event_Type'];
			}

			if ( ! empty( $event['Program_Name'] ) ) {
				$args['event_category'][] = $event['Program_Name'];
			}

			if ( ! empty( $event['First_Name'] ) ) {
				$args['Organizer'] = [
					'Organizer' => $event['First_Name'] . ' ' . $event['Last_Name'],
					'Email'     => $event['Email_Address'],
					// 'Website'   => $event[''],
					// 'Phone'     => $event[''],
				];
			}

			if ( ! empty( $event['Location_Name'] ) ) {
				$args['Venue'] = [
					'Venue'    => $event['Location_Name'],
					// 'Country'  => $event[''],
					'Address'  => $event['Address_Line_1'],
					'City'     => $event['City'],
					'State'    => $event['State/Region'],
					// 'Province' => $event[''],
					'Zip'      => $event['Postal_Code'],
					// 'Phone'    => $event[''],
				];
			}

			$formatted[] = $args;
		}

		$integration->process( $formatted );
	}

	/**
	 * Performs a pull of groups from Ministry Platform
	 *
	 * @param \CP_Sync\Integrations\CP_Groups $integration The integration object.
	 */
	public function pull_groups( $integration ) {
		$mp = new MP();

		// Authenticate to get access token required for API calls
		if ( ! $mp->authenticate() ) {
			return false;
		}

		$filter_query = 'Groups.End_Date >= getdate() OR Groups.End_Date IS NULL';
		$filter       = apply_filters( 'cp_sync_chms_mp_groups_filter', $filter_query );

		$fields = Settings::get( 'group_fields', array(), 'cps_mp_configuration' );

		$table  = $mp->table( 'Groups' );
		$groups = $table
			->select( implode( ',', $fields ) )
			->filter( $filter )
			->get();

		if( $table->errorMessage() ) {
			return false;
		}

		// format the custom mapping data
		$custom_mapping_option = Settings::get( 'custom_group_field_mapping', array(), 'cps_mp_configuration' );
		$custom_mapping        = array();
		foreach ( $custom_mapping_option as $key => $data ) {
			$custom_mapping[ $data['value'] ] = $data['name'];
		}

		$group_mapping       = Settings::get( 'group_field_mapping', array(), 'cps_mp_configuration' );
		$custom_mapping_data = array();

		$formatted = [];

		foreach ( $groups as $group ) {
			$mapped_values = $this->get_mapped_values( $group, $group_mapping );

			$args = array(
				'chms_id'          => '',
				'post_status'      => 'publish',
				'post_title'       => '',
				'post_content'     => '',
				'tax_input'        => array(),
				'group_category'   => array(),
				'group_type'       => array(),
				'group_life_stage' => array(),
				'meta_input'       => array(),
				'thumbnail_url'    => '',
				'break'            => 11,
			);

			if ( isset( $mapped_values['chms_id'] ) ) {
				$args['chms_id']      = $mapped_values['chms_id'];
			}

			if ( isset( $mapped_values['post_content'] ) ) {
				$args['post_content'] = $mapped_values['post_content'];
			}

			if ( isset( $mapped_values['post_title'] ) ) {
				$args['post_title'] = $mapped_values['post_title'];
			}

			if ( isset( $mapped_values['leader'] ) ) {
				$args['meta_input']['leader'] = $mapped_values['leader'];
			}

			if ( isset( $mapped_values['start_date'] ) ) {
				$args['meta_input']['start_date'] = date( 'Y-m-d', strtotime( $mapped_values['start_date'] ) );
			}

			if ( isset( $mapped_values['end_date'] ) ) {
				$args['meta_input']['end_date'] = date( 'Y-m-d', strtotime( $mapped_values['end_date'] ) );
			}

			if ( isset( $mapped_values['thumbnail_url'] ) ) {
				$url = get_option( 'ministry_platform_api_config' );
				$url = isset( $url[ 'MP_API_ENDPOINT' ] ) ? $url[ 'MP_API_ENDPOINT' ] : '';
				$args['thumbnail_url'] = $url . '/files/' . $mapped_values['thumbnail_url'] . '?mpgroup-' . sanitize_title( $args['post_title'] ) . '.jpeg';
			}

			if ( isset( $mapped_values['frequency'] ) ) {
				$args['meta_input']['frequency'] = $mapped_values['frequency'];
			}

			if ( isset( $mapped_values['city'] ) ) {
				$state_or_region = isset( $mapped_values['state_or_region'] ) ? $mapped_values['state_or_region'] : '';
				$postal_code = isset( $mapped_values['postal_code'] ) ? $mapped_values['postal_code'] : '';
				$args['meta_input']['location'] = sprintf( "%s, %s %s", $mapped_values['city'], $state_or_region, $postal_code );
			}

			if ( isset( $mapped_values['time_desc'] ) ) {
				$args['meta_input']['time_desc'] = $mapped_values['time_desc'];
			}

			if ( isset( $mapped_values['meeting_time'] ) ) {
				$args['meta_input']['time_desc'] = gmdate( 'g:ia', strtotime( $mapped_values['meeting_time'] ) );

				if ( ! empty( $mapped_values['meeting_day'] ) ) {
					$args['meta_input']['time_desc']   = $mapped_values['meeting_day'] . 's at ' . $args['meta_input']['time_desc'];
					$args['meta_input']['meeting_day'] = $mapped_values['meeting_day'];
				}
			}

			if ( isset( $mapped_values['cp_location'] ) ) {
				$location = $this->get_location_term( $mapped_values['cp_location'] );
				if ( $location ) {
					$args['cp_location'] = $location;
				}
			}

			if ( isset( $mapped_values['group_category'] ) ) {
				$args['group_category'][] = $mapped_values['group_category'];
			}

			if ( isset( $mapped_values['group_type'] ) ) {
				$args['group_type'][] = $mapped_values['group_type'];
			}

			if ( isset( $mapped_values['group_life_stage'] ) ) {
				$args['group_life_stage'][] = $mapped_values['group_life_stage'];
			}

			if ( isset( $mapped_values['kid_friendly'] ) ) {
				$args['meta_input']['kid_friendly'] = (bool) $mapped_values['kid_friendly'] ? 'on' : false;
			}

			if ( isset( $mapped_values['handicap_accessible'] ) ) {
				$args['meta_input']['handicap_accessible'] = (bool) $mapped_values['handicap_accessible'] ? 'on' : false;
			}

			if ( isset( $mapped_values['virtual'] ) ) {
				$args['meta_input']['virtual'] = (bool) $mapped_values['virtual'] ? 'on' : false;
			}

			/**
			 * Builds the custom data needed for getting available group options in metadata
			 */
			foreach ( array_keys( $group ) as $key ) {
				if ( ! isset( $custom_mapping[ $key ] ) ) {
					continue;
				}
				if ( ! $group[ $key ] ) {
					continue;
				}

				if ( ! ( isset( $custom_mapping_data[ $key ] ) && $custom_mapping_data[ $key ] ) ) {
					$custom_mapping_data[ $key ] = array(
						'field_name'   => $key,
						'display_name' => $custom_mapping[ $key ],
						'slug'         => 'cp_sync_' . sanitize_title( $custom_mapping[ $key ] ),
						'options'      => array(),
					);
				}

				// no duplicate options.
				if ( ! in_array( $group[ $key ], $custom_mapping_data[ $key ]['options'] ) ) {
					$option_slug = sanitize_title( $group[ $key ] );
					$custom_mapping_data[ $key ]['options'][ $option_slug ] = $group[ $key ];
				}
			}

			foreach ( $custom_mapping as $field => $display_name ) {
				if ( ! isset( $group[ $field ] ) || ! $group[ $field ] ) {
					continue;
				}

				$slug          = 'cp_sync_' . sanitize_title( $display_name );
				$original_slug = $slug;
				$suffix        = 1;

				while ( isset( $args['meta_input'][ $slug ] ) ) {
					$slug = $original_slug . '-' . $suffix;
					$suffix++;
				}

				$args['meta_input'][ $slug ] = sanitize_title( $group[ $field ] );
			}

			$formatted[] = $args;
		}

		update_option( 'cp_group_custom_meta_mapping', $custom_mapping_data, 'no' );

		$integration->process( $formatted );
	}
}


import { __ } from '@wordpress/i18n';
import SettingsTab from './settings-tab';
import ConnectTab from './connect-tab';
import GroupsTab from './groups-tab';
import EventsTab from './events-tab';

// Ministry platform data
export default {
	name: 'Planning Center Online',
	tabs: [
		{
			name: __( 'Connect', 'cp-sync' ),
			component: (props) => <ConnectTab {...props} />,
			group: 'connect',
			defaultData: {},
		},
		// {
		// 	name: __( 'Settings', 'cp-sync' ),
		// 	component: (props) => <SettingsTab {...props} />,
		// 	group: 'settings',
		// 	defaultData: {
		// 		events_enabled: 0,
		// 		events_register_button_enabled: 0,
		// 		groups_enabled: 1
		// 	}
		// },
		{
			name: __( 'Groups', 'cp-sync' ),
			component: (props) => <GroupsTab {...props} />,
			group: 'cp_groups',
			defaultData: {
				types: [],
				tag_groups: [],
				visibility: 'public',
				enrollment_status: [],
				enrollment_strategies: [],
				facets: [],
				filter: {
					type: 'all',
					conditions: [],
				}
			}
		},
		{
			name: __( 'Events', 'cp-sync' ),
			component: (props) => <EventsTab {...props} />,
			group: 'ecp',
			defaultData: {
				visibility: 'public',
				tag_groups: [],
				filter: {
					type: 'all',
					conditions: [],
				},
				source: 'calendar'
			}
		}
	]
}

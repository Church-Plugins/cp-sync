
import { __ } from '@wordpress/i18n';
import ConnectTab from './connect-tab';
import GroupsTab from './groups-tab';
import EventsTab from './events-tab';
import SermonsTab from './sermons-tab';

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
		{
			name: __( 'Groups', 'cp-sync' ),
			component: (props) => <GroupsTab {...props} />,
			group: 'cp_groups',
			type: 'groups',
			defaultData: {
				tag_groups: [],
				visibility: 'public',
				enrollment_status: [],
				enrollment_strategies: [],
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
			type: 'events',
			defaultData: {
				visibility: 'public',
				tag_groups: [],
				filter: {
					type: 'all',
					conditions: [],
				},
				source: 'calendar'
			}
		},
		{
			name: __( 'Sermons', 'cp-sync' ),
			component: (props) => <SermonsTab {...props} />,
			group: 'cp_library',
			type: 'sermons',
			defaultData: {
				filter: {
					type: 'all',
					conditions: [],
				}
			}
		}
	]
}

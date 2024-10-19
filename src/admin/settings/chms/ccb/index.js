import { __ } from '@wordpress/i18n';
import ConnectTab from './connect-tab';
import GroupsTab from './groups-tab';

export default {
	name: 'Church Community Builder',
	tabs: [
		{
			name: __( 'Connect', 'cp-sync' ),
			component: (props) => <ConnectTab {...props} />,
			group: 'connect',
			defaultData: {
				subdomain: '',
				clientId: '',
				clientSecret: ''
			}
		},
		{
			name: __( 'Groups', 'cp-sync' ),
			component: (props) => <GroupsTab {...props} />,
			group: 'groups',
			defaultData: {
				filter: {
					type: 'all',
					conditions: [],
				}
			}
		}
	]
}

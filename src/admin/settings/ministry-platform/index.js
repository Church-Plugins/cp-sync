import ConfigureTab from "./configure-tab";
import ConnectTab from "./connect-tab";
import { __ } from '@wordpress/i18n';

// Ministry platform data
export default {
	name: 'Ministry Platform',
	tabs: [
		{
			name: __( 'Connect', 'cp-sync' ),
			component: (props) => <ConnectTab {...props} />,
			optionGroup: 'connect',
			defaultData: {
				api_endpoint: '',
				oauth_discovery_endpoint: '',
				client_id: '',
				client_secret: '',
				api_scope: '',
			}
		},
		{
			name: __( 'Configure', 'cp-sync' ),
			component: (props) => <ConfigureTab {...props} />,
			optionGroup: 'configuration',
			defaultData: {
				group_fields: [],
				custom_fields: [],
				group_field_mapping: {},
				custom_group_field_mapping: {},
			}
		}
	]
}
import { __ } from '@wordpress/i18n';
import ConnectTab from './connect-tab';

export default {
	name: 'Church Community Builder (Coming Soon!)',
	tabs: [
		{
			name: __( 'Connect', 'cp-sync' ),
			component: (props) => <ConnectTab {...props} />,
			optionGroup: 'connect',
			defaultData: {
				api_prefix: '',
				api_user: '',
				api_pass: '',
			}
		}
	]
}

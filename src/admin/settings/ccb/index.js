import { __ } from '@wordpress/i18n';
import ConnectTab from './connect-tab';

export default {
	name: 'Church Community Builder',
	tabs: [
		{
			name: __( 'Connect', 'cp-connect' ),
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

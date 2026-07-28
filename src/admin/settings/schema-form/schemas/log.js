import { __ } from '@wordpress/i18n';

/**
 * Log screen schema (plain JSON-serializable data).
 *
 * Only the debug-mode radio is a pure setting. The log viewer (fetch/clear a log
 * file, readonly display) stays a custom component in `components/log-tab.js`.
 *
 * The `debugMode` key maps 1:1 to the stored global settings key — do not rename.
 * Option values are the strings `'1'`/`'0'` exactly as the previous RadioGroup
 * wrote them.
 */
const logSchema = {
	label: __( 'Log', 'cp-sync' ),
	sections: [
		{
			fields: {
				debugMode: {
					type: 'radio',
					label: __( 'Enable Debug Mode', 'cp-sync' ),
					default: '0',
					options: [
						{ value: '1', label: __( 'Enable', 'cp-sync' ) },
						{ value: '0', label: __( 'Disable', 'cp-sync' ) },
					],
				},
			},
		},
	],
};

export default logSchema;

import { __ } from '@wordpress/i18n';

/**
 * License screen schema (plain JSON-serializable data).
 *
 * Only the pure-setting portion lives here: the beta-updates toggle. The license
 * key entry + activate/deactivate flow is an ACTION widget (REST calls with
 * status feedback), so it stays a custom component composed alongside this form
 * in `components/license-tab.js`.
 *
 * The `beta` key maps 1:1 to the stored global settings key — do not rename.
 */
const licenseSchema = {
	label: __( 'License', 'cp-sync' ),
	sections: [
		{
			fields: {
				beta: {
					type: 'toggle',
					label: __( 'Enable beta updates', 'cp-sync' ),
					default: false,
				},
			},
		},
	],
};

export default licenseSchema;

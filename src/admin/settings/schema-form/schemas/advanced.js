import { __ } from '@wordpress/i18n';

/**
 * Advanced screen schema (plain JSON-serializable data).
 *
 * The `updateInterval` select is the only pure setting. The manual pull/import
 * action button stays a custom component in `components/advanced-tab.js`.
 *
 * The `updateInterval` key maps 1:1 to the stored global settings key — do not
 * rename. Values (`hourly`/`daily`/`weekly`) match the previous MUI menu items.
 */
const advancedSchema = {
	label: __( 'Advanced', 'cp-sync' ),
	sections: [
		{
			fields: {
				updateInterval: {
					type: 'select',
					label: __( 'Update Interval', 'cp-sync' ),
					default: 'hourly',
					options: [
						{ value: 'hourly', label: __( 'Hourly', 'cp-sync' ) },
						{ value: 'daily', label: __( 'Daily', 'cp-sync' ) },
						{ value: 'weekly', label: __( 'Weekly', 'cp-sync' ) },
					],
				},
			},
		},
	],
};

export default advancedSchema;

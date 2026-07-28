import { __ } from '@wordpress/i18n';

/**
 * Advanced screen schema (plain JSON-serializable data).
 *
 * The `updateInterval` select is the only pure setting. The manual pull/import
 * action button stays a custom component in `components/advanced-tab.js`.
 *
 * The `updateInterval` key maps 1:1 to the stored global settings key — do not
 * rename. Values (`hourly`/`daily`/`weekly`) match the previous MUI menu items.
 *
 * `deleteDataOnUninstall` is a plain boolean toggle that also maps 1:1 to a stored
 * global settings key. It has no runtime effect while the plugin is active — it is
 * only read (self-contained, without booting the plugin) by `uninstall.php` when
 * WordPress deletes the plugin. Off by default, so uninstalling keeps all data.
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
				deleteDataOnUninstall: {
					type: 'toggle',
					label: __(
						'Delete all data on uninstall',
						'cp-sync'
					),
					default: false,
					help: __(
						'When the plugin is uninstalled, permanently delete everything it stored: settings, ChMS connection, sync state, and all imported content (groups, events, terms, and sideloaded images). Off by default — uninstalling normally leaves your data untouched.',
						'cp-sync'
					),
				},
			},
		},
	],
};

export default advancedSchema;

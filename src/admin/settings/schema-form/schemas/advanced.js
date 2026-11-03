import { __ } from '@wordpress/i18n';

/**
 * Advanced screen schemas (plain JSON-serializable data).
 *
 * Split into TWO schema slices so the tab can interleave its action widgets in
 * the intended visual order: groups display toggle → interval select → Pull
 * Now button → uninstall toggle → Danger Zone (see `components/advanced-tab.js`).
 * Both slices render through the same `<SchemaForm values={globalSettings}>`, so
 * they share one values object and save path.
 *
 * `displayGroupsWithSync` maps 1:1 to the stored global settings key read by
 * Groups Lite. Do not rename it. On until an admin turns it off.
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
			title: __( 'Groups', 'cp-sync' ),
			fields: {
				displayGroupsWithSync: {
					type: 'toggle',
					label: __( 'Display groups with CP Sync', 'cp-sync' ),
					// On until an admin turns it off. PHP treats a missing key the same way
					// when CP Groups is absent. The key must match Groups\Lite::DISPLAY_SETTING.
					default: true,
					help: __(
						'CP Groups adds filters, a map, and a contact form.',
						'cp-sync'
					),
				},
			},
		},
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

export const advancedUninstallSchema = {
	label: __( 'Uninstall', 'cp-sync' ),
	sections: [
		{
			fields: {
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

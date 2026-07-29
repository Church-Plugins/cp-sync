import { __, sprintf } from '@wordpress/i18n';
import { SelectControl, Notice } from '@wordpress/components';
import platforms from '../platforms';
import { useSettings } from '../contexts/settingsContext';
import SyncToggles from './sync-toggles';

/**
 * Merged Connect tab.
 *
 * One onboarding surface: the ChMS picker at the top (absorbed from the old
 * standalone picker tab), with the selected platform's connect component
 * rendered directly below it.
 *
 * The picker persists the ChMS switch immediately via
 * `updateGlobalSettings('chms', value)` — the store's resolvers then fetch the
 * new platform's settings/connection lazily. The picker stays enabled even
 * while connected (owner decision); a hint below it explains that switching
 * does not disconnect the current platform.
 *
 * The embedded platform connect component is resolved from the `platforms`
 * registry (the tab whose `group === 'connect'`) and receives the SAME
 * `{ data, updateField, save }` contract that `DynamicTab` gives it today —
 * `data` scoped to the active ChMS's `connect` slice (with the platform tab's
 * `defaultData` as the base), `updateField` scoped to the `connect` group, and
 * the global `save`.
 */
function ConnectTab() {
	const {
		globalSettings,
		updateGlobalSettings,
		settings,
		updateField,
		save,
		isConnected,
	} = useSettings();

	const chms = globalSettings.chms;
	const platform = platforms[ chms ];

	const options = [
		{ label: __( 'Select', 'cp-sync' ), value: '' },
		...Object.keys( platforms ).map( ( key ) => ( {
			label: platforms[ key ].name,
			value: key,
		} ) ),
	];

	// Resolve the active platform's connect screen from the registry. Its
	// `defaultData` is the base for the `connect` slice, mirroring how
	// DynamicTab scopes a tab's data.
	const connectTabDef = platform
		? platform.tabs.find( ( tab ) => tab.group === 'connect' )
		: null;

	return (
		<div className="cps-connect-tab">
			<div className="cps-chms-picker">
				<SelectControl
					className="cps-chms-select"
					label={ __( 'ChMS', 'cp-sync' ) }
					value={ chms }
					options={ options }
					onChange={ ( value ) =>
						updateGlobalSettings( 'chms', value )
					}
					__nextHasNoMarginBottom
				/>
				<Notice
					status="info"
					isDismissible={ false }
					className="cps-chms-notice"
				>
					{ __( 'More platforms coming soon!', 'cp-sync' ) }
				</Notice>
				{ isConnected && platform && (
					<p className="cps-chms-hint">
						{ sprintf(
							/* translators: %s: the name of the currently connected ChMS platform. */
							__(
								'Switching platforms does not disconnect %s — its connection and settings are preserved.',
								'cp-sync'
							),
							platform.name
						) }
					</p>
				) }
			</div>

			{ connectTabDef && (
				<div className="cps-connect-platform">
					{ connectTabDef.component( {
						data: {
							...connectTabDef.defaultData,
							...settings[ 'connect' ],
						},
						updateField: ( field, value ) =>
							updateField( 'connect', field, value ),
						save,
					} ) }
				</div>
			) }

			{ /* Sync feed toggles only make sense once a connection exists. */ }
			{ platform && isConnected && (
				<div className="cps-connect-sync">
					<SyncToggles
						chms={ chms }
						values={ settings[ 'connect' ] }
						updateField={ ( field, value ) =>
							updateField( 'connect', field, value )
						}
					/>
				</div>
			) }
		</div>
	);
}

export const connectTab = {
	name: __( 'Connect', 'cp-sync' ),
	component: ( props ) => <ConnectTab { ...props } />,
};

import { createRoot, useState, useEffect } from '@wordpress/element';
import { Button, Card, CardBody, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import './index.scss';
import platforms from './platforms';
import globalStore from './store/globalStore';
import { connectTab } from './components/connect-tab';
import { licenseTab } from './components/license-tab';
import { logTab } from './components/log-tab';
import { advancedTab } from './components/advanced-tab';
import { SyncStatusIndicator } from './components/sync-status';
import SettingsProvider, { useSettings } from './contexts/settingsContext';

/**
 * Renders a single tab's registered component.
 *
 * The DynamicTab contract is unchanged: the registered `component` receives
 * `{ data, updateField, save }` scoped to the tab's `group`. Tabs without a
 * settings slice (the merged connectTab has no `group` and derives its own
 * scoping from the active ChMS; logTab's `logTab` group has no slice) are
 * handled gracefully — spreading `settings[undefined]` / `settings.logTab` is
 * a harmless no-op and those tabs read the global store directly via
 * `useSettings()`.
 *
 * @param {Object} props
 * @param {Object} props.tab The tab registration.
 */
function DynamicTab( { tab } ) {
	const { group, defaultData = {}, component } = tab;

	const { settings, save, updateField, isDirty } = useSettings();

	// Warn on navigation away while there are unsaved changes.
	useEffect( () => {
		if ( isDirty ) {
			const handleBeforeUnload = ( e ) => {
				e.preventDefault();
				return false;
			};

			window.addEventListener( 'beforeunload', handleBeforeUnload );

			return () => {
				window.removeEventListener(
					'beforeunload',
					handleBeforeUnload
				);
			};
		}
	}, [ isDirty ] );

	return (
		<Card className="cps-tab-panel">
			<CardBody>
				{ component( {
					data: { ...defaultData, ...settings[ group ] },
					updateField: ( field, value ) =>
						updateField( group, field, value ),
					save,
				} ) }
			</CardBody>
		</Card>
	);
}

function Settings() {
	const {
		globalSettings,
		settings,
		save,
		isSaving,
		isDirty,
		error,
		isConnected,
	} = useSettings();

	const chmsData = platforms[ globalSettings.chms ] || { tabs: [] };

	// The served `connect` screen schema for the active ChMS. Its per-field
	// `disabled` flag is the availability signal for the sync toggles (set when the
	// companion plugin is inactive). Selecting it also triggers the schema resolver.
	const connectSchema = useSelect(
		( select ) =>
			globalSettings.chms
				? select( globalStore ).getSchema( globalSettings.chms )?.connect
				: undefined,
		[ globalSettings.chms ]
	);

	// A per-feed tab (one carrying a `type`) is shown only when its feed is BOTH
	// enabled (the `connect.sync_<type>` setting is not explicitly false —
	// default-true semantics) AND available (its schema toggle is not `disabled`).
	// A missing schema is treated as available so tabs are not hidden mid-load.
	const connectValues = settings.connect || {};

	const isTypeVisible = ( type ) => {
		if ( connectValues[ 'sync_' + type ] === false ) {
			return false;
		}

		if ( connectSchema && Array.isArray( connectSchema.sections ) ) {
			for ( const section of connectSchema.sections ) {
				const field = section.fields?.[ 'sync_' + type ];
				if ( field && field.disabled ) {
					return false;
				}
			}
		}

		return true;
	};

	// The merged Connect tab owns the picker + the active platform's connect
	// screen, so the platform's own `connect` tab is never surfaced separately.
	// The remaining per-feed tabs (groups, events, …) only appear once
	// connected, and each typed feed tab is additionally gated on enabled+available.
	const platformTabs = isConnected
		? chmsData.tabs
				.filter( ( tab ) => tab.group !== 'connect' )
				.filter( ( tab ) => ! tab.type || isTypeVisible( tab.type ) )
		: [];

	// SLUG-KEYED tab list. A tab's identity is a stable slug (not its numeric
	// array position), so `?tab=` URLs round-trip correctly and stay valid even
	// if tabs are reordered. The merged Connect tab takes the `connect` slug
	// (legacy `?tab=select` aliases to it — see getInitialSlug); logTab keeps
	// the historical `log` slug (its `group` is `logTab`).
	const allTabs = [
		{
			slug: 'connect',
			label: __( 'Connect', 'cp-sync' ),
			tab: connectTab,
		},
		...platformTabs.map( ( tab ) => ( {
			slug: tab.group,
			label: tab.name,
			tab,
		} ) ),
		{ slug: 'log', label: __( 'Log', 'cp-sync' ), tab: logTab },
		{ slug: 'license', label: __( 'License', 'cp-sync' ), tab: licenseTab },
		{
			slug: 'advanced',
			label: __( 'Advanced', 'cp-sync' ),
			tab: advancedTab,
		},
	];

	const slugs = allTabs.map( ( t ) => t.slug );

	const getInitialSlug = () => {
		const url = new URL( window.location.href );
		let tab = url.searchParams.get( 'tab' );
		// Legacy bookmarks used the standalone picker slug; it now lives inside
		// the merged Connect tab.
		if ( tab === 'select' ) {
			tab = 'connect';
		}
		return tab && slugs.includes( tab ) ? tab : 'connect';
	};

	const [ currentTab, setCurrentTab ] = useState( getInitialSlug );

	const openTab = ( slug ) => {
		const url = new URL( window.location.href );
		url.searchParams.set( 'tab', slug );
		window.history.pushState( {}, '', url );
		setCurrentTab( slug );
	};

	// Connection state changes the set of available tabs. If the active slug is
	// no longer valid (e.g. we were on a per-feed tab and just disconnected),
	// re-derive it from the URL — reorder/length independent, unlike the old
	// index-based reset.
	useEffect( () => {
		if ( ! slugs.includes( currentTab ) ) {
			setCurrentTab( getInitialSlug() );
		}
	}, [ isConnected ] );

	const activeEntry =
		allTabs.find( ( t ) => t.slug === currentTab ) || allTabs[ 0 ];

	return (
		<div className="cps-app">
			<h1 className="cps-app__title">CP Sync</h1>
			<SyncStatusIndicator chms={ globalSettings.chms } />

			<div className="cps-tab-bar" role="tablist">
				{ allTabs.map( ( { slug, label } ) => (
					<Button
						key={ slug }
						role="tab"
						aria-selected={ currentTab === slug }
						className={
							'cps-tab-bar__tab' +
							( currentTab === slug ? ' is-active' : '' )
						}
						onClick={ () => openTab( slug ) }
					>
						{ label }
					</Button>
				) ) }
			</div>

			<div className="cps-tab-content">
				<DynamicTab tab={ activeEntry.tab } key={ activeEntry.slug } />
			</div>

			{ error && (
				<Notice
					status="error"
					isDismissible={ false }
					className="cps-app__error"
				>
					{ error }
				</Notice>
			) }

			<Button
				className="cps-save"
				variant="primary"
				onClick={ save }
				disabled={ isSaving || ! isDirty }
			>
				{ isSaving
					? __( 'Saving...', 'cp-sync' )
					: __( 'Save all Settings', 'cp-sync' ) }
			</Button>
		</div>
	);
}

document.addEventListener( 'DOMContentLoaded', function () {
	const root = document.querySelector( '.cp_settings_root.cp-sync' );

	if ( root ) {
		const globalSettings = JSON.parse( root.dataset.settings ); // get the initial data from the root element
		const compareOptions = JSON.parse( root.dataset.compareOptions );

		createRoot( root ).render(
			<SettingsProvider
				globalSettings={ globalSettings }
				compareOptions={ compareOptions }
			>
				<Settings />
			</SettingsProvider>
		);
	}
} );

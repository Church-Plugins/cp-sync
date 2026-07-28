import { createRoot, useState, useEffect } from '@wordpress/element';
import { Button, Card, CardBody, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './index.scss';
import platforms from './platforms';
import { chmsTab } from './components/chms-tab';
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
 * settings slice (chmsTab has no `group`; logTab's `logTab` group has no
 * slice) are handled gracefully — spreading `settings[undefined]` /
 * `settings.logTab` is a harmless no-op and those tabs read the global
 * store directly via `useSettings()`.
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
	const { globalSettings, save, isSaving, isDirty, error, isConnected } =
		useSettings();

	const chmsData = platforms[ globalSettings.chms ] || { tabs: [] };

	// When not connected, only the ChMS's `connect`-group tabs are offered.
	const platformTabs = chmsData.tabs.filter( ( tab ) =>
		isConnected ? true : tab.group === 'connect'
	);

	// SLUG-KEYED tab list. A tab's identity is a stable slug (not its numeric
	// array position), so `?tab=` URLs round-trip correctly and stay valid even
	// if tabs are reordered. The ChMS picker keeps the historical `select` slug;
	// logTab keeps the historical `log` slug (its `group` is `logTab`).
	const allTabs = [
		{
			slug: 'select',
			label: __( 'Select a ChMS', 'cp-sync' ),
			tab: chmsTab,
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
		const tab = url.searchParams.get( 'tab' );
		return tab && slugs.includes( tab ) ? tab : 'select';
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

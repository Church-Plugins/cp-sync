import {
	Button,
	Notice,
	Spinner,
	RadioControl,
	TextControl,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * The five reset levels, in ascending order of destructiveness.
 *
 * `destructive` levels require the user to retype the level name before the
 * reset button unlocks. The non-destructive levels (`queue`, `state`) are
 * recoverable — the next sync rebuilds them — so a single window.confirm is
 * enough. The server still requires `confirm === level` for ALL levels, so the
 * request body always sends it regardless of the client-side gate.
 */
const RESET_LEVELS = [
	{
		value: 'queue',
		title: __( 'Queue', 'cp-sync' ),
		description: __(
			'Clear the background sync queue (unstick a stalled sync).',
			'cp-sync'
		),
		destructive: false,
	},
	{
		value: 'state',
		title: __( 'Sync state', 'cp-sync' ),
		description: __(
			'Forget sync state so the next pull re-imports everything (keeps existing posts; they update in place).',
			'cp-sync'
		),
		destructive: false,
	},
	{
		value: 'content',
		title: __( 'Content', 'cp-sync' ),
		description: __( 'Delete all imported posts and terms.', 'cp-sync' ),
		destructive: true,
	},
	{
		value: 'connection',
		title: __( 'Connection', 'cp-sync' ),
		description: __(
			'Remove ChMS credentials and connection settings.',
			'cp-sync'
		),
		destructive: true,
	},
	{
		value: 'all',
		title: __( 'Everything', 'cp-sync' ),
		description: __(
			'Full reset of everything CP Sync has stored — sync state, imported content, ChMS connection, and plugin settings; uninstalling the plugin only deletes this data if the "Delete all data on uninstall" toggle above is enabled.',
			'cp-sync'
		),
		destructive: true,
	},
];

// Levels whose success invalidates the loaded connection/settings state, so a
// full page reload is the honest way to resync the SPA with the server.
const RELOAD_AFTER = [ 'connection', 'all' ];

/**
 * Render a single summary value generically. The server's `summary` is an
 * object of step -> count/detail whose shape may vary, so coerce whatever comes
 * back into a readable string.
 *
 * @param {*} value Raw summary value.
 * @return {string} Human-readable representation.
 */
const renderSummaryValue = ( value ) => {
	if ( Array.isArray( value ) ) {
		return value.length ? value.join( ', ' ) : __( 'none', 'cp-sync' );
	}
	if ( value !== null && typeof value === 'object' ) {
		return JSON.stringify( value );
	}
	if ( typeof value === 'boolean' ) {
		return value ? __( 'yes', 'cp-sync' ) : __( 'no', 'cp-sync' );
	}
	return String( value );
};

/**
 * Destructive "Danger Zone" panel: reset install data at a selectable level via
 * POST /cp-sync/v1/reset.
 *
 * ACTION widget (destructive REST action), composed alongside <SchemaForm> in
 * the Advanced tab — not a schema field.
 */
function DangerZone() {
	const [ level, setLevel ] = useState( 'queue' );
	const [ confirmText, setConfirmText ] = useState( '' );
	const [ isRunning, setIsRunning ] = useState( false );
	const [ summary, setSummary ] = useState( null );
	const [ error, setError ] = useState( null );

	const selected = RESET_LEVELS.find( ( l ) => l.value === level );
	const isDestructive = selected.destructive;

	// Destructive levels unlock only on an exact retype; non-destructive levels
	// are gated by a window.confirm inside the handler instead.
	const confirmMatches = confirmText === level;
	const canRun =
		! isRunning && ( ! isDestructive || confirmMatches );

	// Switching levels resets the confirm field and any prior result so stale
	// confirmation can never carry over to a different (possibly worse) level.
	const handleLevelChange = ( next ) => {
		setLevel( next );
		setConfirmText( '' );
		setSummary( null );
		setError( null );
	};

	const runReset = () => {
		if ( ! isDestructive ) {
			// eslint-disable-next-line no-alert
			const ok = window.confirm(
				sprintf(
					/* translators: %s: reset level description. */
					__( 'Run this reset now?\n\n%s', 'cp-sync' ),
					selected.description
				)
			);
			if ( ! ok ) {
				return;
			}
		}

		setIsRunning( true );
		setSummary( null );
		setError( null );

		apiFetch( {
			path: '/cp-sync/v1/reset',
			method: 'POST',
			data: { level, confirm: level },
		} )
			.then( ( res ) => {
				setSummary( res.summary || {} );
				setConfirmText( '' );

				if ( RELOAD_AFTER.includes( level ) ) {
					// Connection/settings state in the SPA is now stale; reload
					// after a short beat so the success notice is visible first.
					setTimeout( () => window.location.reload(), 2500 );
				}
			} )
			.catch( ( err ) => {
				setError(
					err.message ||
						__( 'The reset request failed.', 'cp-sync' )
				);
			} )
			.finally( () => {
				setIsRunning( false );
			} );
	};

	const options = RESET_LEVELS.map( ( l ) => ( {
		value: l.value,
		// RadioControl renders each option's `label` as-is in JSX, so a rich
		// node keeps the title + description on their own lines.
		label: (
			<span className="cps-danger-zone__option">
				<span className="cps-danger-zone__option-title">
					{ l.title }
				</span>
				<span className="cps-danger-zone__option-desc">
					{ l.description }
				</span>
			</span>
		),
	} ) );

	return (
		<div className="cps-danger-zone">
			<h3 className="cps-danger-zone__title">
				{ __( 'Danger Zone', 'cp-sync' ) }
			</h3>

			<Notice status="warning" isDismissible={ false }>
				{ __(
					'Reset CP Sync install data. These actions cannot be undone. Choose a level and confirm below.',
					'cp-sync'
				) }
			</Notice>

			<RadioControl
				label={ __( 'Reset level', 'cp-sync' ) }
				selected={ level }
				options={ options }
				onChange={ handleLevelChange }
			/>

			{ isDestructive && (
				<TextControl
					label={ sprintf(
						/* translators: %s: the reset level keyword to retype. */
						__(
							'Type "%s" to confirm this destructive reset',
							'cp-sync'
						),
						level
					) }
					value={ confirmText }
					onChange={ setConfirmText }
					disabled={ isRunning }
					autoComplete="off"
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			) }

			{ summary && (
				<Notice
					status="success"
					isDismissible={ false }
					className="cps-danger-zone__result"
				>
					<p>
						{ RELOAD_AFTER.includes( level )
							? __(
									'Reset complete. Reloading…',
									'cp-sync'
							  )
							: __( 'Reset complete.', 'cp-sync' ) }
					</p>
					{ Object.keys( summary ).length > 0 && (
						<dl className="cps-danger-zone__summary">
							{ Object.entries( summary ).map(
								( [ key, value ] ) => (
									<div key={ key }>
										<dt>{ key }</dt>
										<dd>
											{ renderSummaryValue( value ) }
										</dd>
									</div>
								)
							) }
						</dl>
					) }
				</Notice>
			) }

			{ error && (
				<Notice
					status="error"
					isDismissible={ false }
					className="cps-danger-zone__result"
				>
					{ error }
				</Notice>
			) }

			<div className="cps-danger-zone__actions">
				<Button
					variant="primary"
					isDestructive
					onClick={ runReset }
					disabled={ ! canRun }
					__next40pxDefaultSize
				>
					{ isRunning && <Spinner /> }
					{ isRunning
						? __( 'Resetting…', 'cp-sync' )
						: sprintf(
								/* translators: %s: reset level title. */
								__( 'Reset: %s', 'cp-sync' ),
								selected.title
						  ) }
				</Button>
			</div>
		</div>
	);
}

export default DangerZone;

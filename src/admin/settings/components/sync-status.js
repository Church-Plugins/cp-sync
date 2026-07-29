import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice, Button, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

// Poll fast only while a sync is actually running; the indicator renders
// nothing when idle, so idle polling is pure background noise — especially on
// slow sites. (The previous implementation ALSO had an interval-churn bug: the
// poll callback depended on the state it set, so every response re-created the
// interval and immediately fired the next request — an effective rate of one
// request per round-trip instead of the intended 5s.)
const ACTIVE_POLL_MS = 5000;
const IDLE_POLL_MS = 30000;

export function SyncStatusIndicator( { chms } ) {
	const [ syncStatus, setSyncStatus ] = useState( null );
	const [ isCancelling, setIsCancelling ] = useState( false );
	const [ showAlert, setShowAlert ] = useState( true );

	// Mutable poll-loop state lives in refs so `checkSyncStatus` keeps a stable
	// identity (deps: [chms] only) and the timer is scheduled exactly once.
	const syncStatusRef = useRef( null );
	const lastSyncTypeRef = useRef( null );
	const inFlightRef = useRef( false );

	const checkSyncStatus = useCallback( async () => {
		if ( ! chms ) return;

		// Never stack requests on a slow server, and don't poll a hidden tab.
		if ( inFlightRef.current || document.hidden ) return;

		inFlightRef.current = true;

		try {
			const response = await apiFetch( {
				path: `/cp-sync/v1/${ chms }/sync-status`,
				method: 'GET',
			} );

			const prev = syncStatusRef.current;

			// If a new sync started (was not syncing, now is syncing, or type changed), reset the alert
			if (
				response.is_syncing &&
				( ! prev?.is_syncing ||
					response.type !== lastSyncTypeRef.current )
			) {
				setShowAlert( true );
				lastSyncTypeRef.current = response.type;
			}

			// If sync stopped, clear the last sync type
			if ( ! response.is_syncing && prev?.is_syncing ) {
				lastSyncTypeRef.current = null;
			}

			syncStatusRef.current = response;

			// Skip the state write (and re-render) when nothing changed.
			if (
				! prev ||
				prev.is_syncing !== response.is_syncing ||
				prev.type !== response.type ||
				prev.message !== response.message
			) {
				setSyncStatus( response );
			}
		} catch ( error ) {
			console.error( 'Failed to check sync status:', error );
		} finally {
			inFlightRef.current = false;
		}
	}, [ chms ] );

	const cancelSync = async ( type = null ) => {
		if ( ! chms ) return;

		setIsCancelling( true );

		try {
			const path = type
				? `/cp-sync/v1/${ chms }/cancel-sync?type=${ type }`
				: `/cp-sync/v1/${ chms }/cancel-sync`;

			await apiFetch( {
				path,
				method: 'POST',
			} );

			// Refresh status after cancelling
			setTimeout( () => {
				checkSyncStatus();
				setIsCancelling( false );
			}, 1000 );
		} catch ( error ) {
			console.error( 'Failed to cancel sync:', error );
			setIsCancelling( false );
		}
	};

	useEffect( () => {
		let timeout;
		let stopped = false;

		// Self-rescheduling loop (not setInterval) so the cadence can adapt:
		// fast while a sync is running, slow when idle.
		const tick = async () => {
			await checkSyncStatus();

			if ( stopped ) return;

			const delay = syncStatusRef.current?.is_syncing
				? ACTIVE_POLL_MS
				: IDLE_POLL_MS;

			timeout = setTimeout( tick, delay );
		};

		tick();

		return () => {
			stopped = true;
			clearTimeout( timeout );
		};
	}, [ checkSyncStatus ] );

	// Don't render anything if no sync is running or alert is dismissed
	if ( ! syncStatus?.is_syncing || ! showAlert ) {
		return null;
	}

	return (
		<Notice
			status="info"
			className="cps-sync-status"
			isDismissible={ true }
			onRemove={ () => setShowAlert( false ) }
		>
			<div className="cps-sync-status__body">
				<Spinner />
				<span className="cps-sync-status__message">
					{ syncStatus.message ||
						__( 'A sync is currently in progress', 'cp-sync' ) }
				</span>
				<Button
					variant="secondary"
					onClick={ () => cancelSync( syncStatus.type ) }
					disabled={ isCancelling }
				>
					{ isCancelling
						? __( 'Cancelling...', 'cp-sync' )
						: __( 'Cancel Sync', 'cp-sync' ) }
				</Button>
			</div>
		</Notice>
	);
}

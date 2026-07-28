import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Notice, Button, Spinner } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

export function SyncStatusIndicator( { chms } ) {
	const [ syncStatus, setSyncStatus ] = useState( null );
	const [ isCancelling, setIsCancelling ] = useState( false );
	const [ showAlert, setShowAlert ] = useState( true );
	const [ lastSyncType, setLastSyncType ] = useState( null );

	const checkSyncStatus = useCallback( async () => {
		if ( ! chms ) return;

		try {
			const response = await apiFetch( {
				path: `/cp-sync/v1/${ chms }/sync-status`,
				method: 'GET',
			} );

			// If a new sync started (was not syncing, now is syncing, or type changed), reset the alert
			if (
				response.is_syncing &&
				( ! syncStatus?.is_syncing || response.type !== lastSyncType )
			) {
				setShowAlert( true );
				setLastSyncType( response.type );
			}

			// If sync stopped, clear the last sync type
			if ( ! response.is_syncing && syncStatus?.is_syncing ) {
				setLastSyncType( null );
			}

			setSyncStatus( response );
		} catch ( error ) {
			console.error( 'Failed to check sync status:', error );
		}
	}, [ chms, syncStatus, lastSyncType ] );

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
		checkSyncStatus();

		// Poll every 5 seconds
		const interval = setInterval( () => {
			checkSyncStatus();
		}, 5000 );

		return () => clearInterval( interval );
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

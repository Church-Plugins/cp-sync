import { __, sprintf } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { useSettings } from '../../contexts/settingsContext';
import globalStore from '../../store/globalStore';
import { launchOauth } from '../../util/oauth';

/**
 * PCO Connect tab.
 *
 * All action widgets — the OAuth connect flow, connection status display, and
 * disconnect. No persisted schema fields (the `connect` screen is intentionally
 * empty). Rebuilt on @wordpress/components; the OAuth URL construction, token
 * handling, and store dispatches are preserved exactly.
 */
export default function ConnectTab() {
	const { isConnected, save } = useSettings();
	const [ authLoading, setAuthLoading ] = useState( false );
	const [ authError, setAuthError ] = useState( null );
	const { invalidateResolutionForStoreSelector, setIsConnected } =
		useDispatch( globalStore );

	// Which PCO account this connection belongs to ({ person, organization }),
	// from the check-connection response. May be undefined mid-resolution.
	const account = useSelect(
		( select ) => select( globalStore ).getConnectionDetails( 'pco' )?.account,
		[]
	);

	const initiateOAuth = () => {
		save();

		setAuthLoading( true );
		setAuthError( null );

		const oauthURL = new URL( window.cpSync.oauthURL );
		oauthURL.pathname = `/wp-content/themes/churchplugins/oauth/pco/`;
		oauthURL.searchParams.set( 'action', 'authorize' );
		oauthURL.searchParams.set(
			'redirect_url',
			window.cpSync.adminUrl + '?cp_sync_oauth=1'
		);
		oauthURL.searchParams.set( '_nonce', window.cpSync.nonce );

		launchOauth( oauthURL.toString() )
			.then( () => {
				setIsConnected( 'pco', true );
			} )
			.catch( ( err ) => {
				console.error( 'errolaunghing oauth', err );
				setAuthError( err );
			} )
			.finally( () => {
				setAuthLoading( false );
			} );
	};

	const disconnectOAuth = () => {
		setAuthLoading( true );
		setAuthError( null );

		apiFetch( {
			path: '/cp-sync/v1/pco/disconnect',
			method: 'POST',
		} )
			.then( ( data ) => {
				if ( data.success ) {
					setIsConnected( 'pco', false );
					invalidateResolutionForStoreSelector( 'getIsConnected' );
				} else {
					setAuthError( __( 'Failed to disconnect', 'cp-sync' ) );
				}
			} )
			.catch( ( error ) => {
				setAuthError( error.message );
			} )
			.finally( () => {
				setAuthLoading( false );
			} );
	};

	return (
		// No Card wrapper: this component is embedded in the merged Connect tab,
		// which already renders inside the shell's Card — a nested Card doubled
		// the padding/border.
		<div className="cps-pco-connect">
			<h2 style={ { marginTop: 0 } }>
				{ __( 'PCO API Configuration', 'cp-sync' ) }
			</h2>
				{ ! isConnected && (
					<>
						<p>
							{ __(
								'Click the button below to initiate the OAuth flow and connect to Planning Center Online.',
								'cp-sync'
							) }
						</p>
						{ authError && (
							<Notice status="error" isDismissible={ false }>
								{ authError }
							</Notice>
						) }
						<div style={ { marginTop: '1rem' } }>
							<Button
								variant="primary"
								onClick={ initiateOAuth }
								disabled={ authLoading || isConnected }
							>
								{ authLoading && <Spinner /> }
								{ __( 'Connect', 'cp-sync' ) }
							</Button>
						</div>
					</>
				) }
				{ isConnected && (
					<div>
						<Notice status="success" isDismissible={ false }>
							{ account?.organization && account?.person
								? sprintf(
										/* translators: 1: PCO organization name, 2: connected person's name. */
										__( 'Connected to %1$s as %2$s', 'cp-sync' ),
										account.organization,
										account.person
								  )
								: account?.organization
								? sprintf(
										/* translators: %s: PCO organization name. */
										__( 'Connected to %s', 'cp-sync' ),
										account.organization
								  )
								: __( 'Connected', 'cp-sync' ) }
						</Notice>
						<div style={ { marginTop: '1rem' } }>
							<Button
								variant="primary"
								onClick={ disconnectOAuth }
								disabled={ authLoading }
							>
								{ authLoading && <Spinner /> }
								{ __( 'Disconnect', 'cp-sync' ) }
							</Button>
						</div>
					</div>
				) }
		</div>
	);
}

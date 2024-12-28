import Alert from '@mui/material/Alert'
import { __ } from '@wordpress/i18n'
import { useSettings } from '../../contexts/settingsContext'
import Button from '@mui/material/Button'
import Box from '@mui/material/Box'
import { launchOauth } from '../../util/oauth'
import { useState } from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'
import TextField from '@mui/material/TextField'
import globalStore from '../../store/globalStore'
import { useDispatch } from '@wordpress/data'

export default function ConnectTab({ data, updateField }) {
	const { isConnected, save } = useSettings()
	const [authLoading, setAuthLoading] = useState(false)
	const [authError, setAuthError] = useState(null)
	const { invalidateResolutionForStoreSelector, setIsConnected } = useDispatch(globalStore)

	const initiateOAuth = () => {
		save();

		setAuthLoading(true);
		setAuthError(null);

		const oauthURL = new URL(window.cpSync.oauthURL);
		oauthURL.pathname = `/wp-content/themes/churchplugins/oauth/pushpay/`;
		oauthURL.searchParams.set('action', 'authorize');
		oauthURL.searchParams.set('redirect_url', window.location.origin + '/wp-admin/?cp_sync_oauth=1')
		oauthURL.searchParams.set('_nonce', window.cpSync.nonce);
		oauthURL.searchParams.set('subdomain', data.subdomain);

		launchOauth(oauthURL.toString())
		.then(() => {
			setIsConnected('ccb', true);
		}).catch(err => {
			setAuthError(err);
		}).finally(() => {
			setAuthLoading(false);
		})
	};

	const disconnectOAuth = () => {
		setAuthLoading(true);
		setAuthError(null);

		apiFetch({
			path: '/cp-sync/v1/ccb/disconnect',
			method: 'POST',
		}).then((data) => {
			if (data.success) {
				setIsConnected('ccb', false); // optimistic update
				// refresh just to make sure
				invalidateResolutionForStoreSelector('getIsConnected')
			} else {
				setAuthError(__('Failed to disconnect', 'cp-sync'));
			}
		}).catch((error) => {
			setAuthError(error.message);
		}).finally(() => {
			setAuthLoading(false);
		});
	};

	return (
		<Box display="flex" flexDirection="column" gap={2} alignItems="start">
			<h2 style={{ marginTop: 0 }}>Connect to Church Community Builder</h2>

			{
				authError &&
				<Alert severity="error" sx={{ mt: 2 }}>{authError}</Alert>
			}
			
			<TextField
				label={__( 'CCB Subdomain' )}
				value={data.subdomain}
				onChange={(e) => updateField('subdomain', e.target.value)}
				sx={{ width: '350px' }}
				disabled={isConnected}
			/>

			{
				authLoading ? (
					<Button variant="contained" color="info" disabled>
						{__( 'Loading...', 'cp-sync' )}
					</Button>
				) : isConnected ? (
				<Button variant="contained" color="info" onClick={disconnectOAuth}>
					{__( 'Disconnect', 'cp-sync' )}
				</Button> ) : (
					!!data.subdomain &&
					<Button variant="contained" color="primary" onClick={initiateOAuth}>
						{__( 'Login with CCB', 'cp-sync' )}
					</Button>
				)
			}
		</Box>
	)
}


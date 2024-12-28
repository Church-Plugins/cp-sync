import { __ } from '@wordpress/i18n'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Typography from '@mui/material/Typography'
import { useState } from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'
import CircularProgress from '@mui/material/CircularProgress'
import { useSettings } from '../../contexts/settingsContext'
import { useDispatch } from '@wordpress/data'
import globalStore from '../../store/globalStore'
import { launchOauth } from '../../util/oauth'

export default function ConnectTab() {
	const { isConnected, save } = useSettings()
	const [authLoading, setAuthLoading] = useState(false)
	const [authError, setAuthError] = useState(null)
	const { invalidateResolutionForStoreSelector, setIsConnected } = useDispatch(globalStore)

	const initiateOAuth = () => {
		save();

		setAuthLoading(true);
		setAuthError(null);

		const oauthURL = new URL(window.cpSync.oauthURL);
		oauthURL.pathname = `/wp-content/themes/churchplugins/oauth/pco/`;
		oauthURL.searchParams.set('action', 'authorize');
		oauthURL.searchParams.set('redirect_url', window.location.origin + '/wp-admin/?cp_sync_oauth=1')
		oauthURL.searchParams.set('_nonce', window.cpSync.nonce);

		launchOauth(oauthURL.toString())
			.then(() => {
				setIsConnected('pco', true);
			})
			.catch(err => {
				console.error('errolaunghing oauth', err);
				setAuthError(err);
			})
			.finally(() => {
				setAuthLoading(false);
			})
	};

	const disconnectOAuth = () => {
		setAuthLoading(true);
		setAuthError(null);

		apiFetch({
			path: '/cp-sync/v1/pco/disconnect',
			method: 'POST',
		}).then((data) => {
			if (data.success) {
				setIsConnected('pco', false);
				invalidateResolutionForStoreSelector('getIsConnected')
			} else {
				setAuthError(__('Failed to disconnect', 'cp-sync'));
			}
		}).catch((error) => {
			setAuthError(error.message);
		}).finally(() => {
			setAuthLoading(false);
		})
	};

	return (
		<div>
			<Typography variant="h5">{__('PCO API Configuration', 'cp-sync')}</Typography>
			{
				!isConnected &&
				<>
					{__('Click the button below to initiate the OAuth flow and connect to Planning Center Online.', 'cp-sync')}
					{
						authError &&
						<Alert severity="error" sx={{ mt: 2 }}>{authError}</Alert>
					}
					<div style={{ marginTop: '1rem' }}>
						<Button
							variant="contained"
							color="primary"
							onClick={initiateOAuth}
							disabled={authLoading || isConnected}
							sx={{ display: 'inline-flex', gap: 2 }}
						>
							{
								authLoading &&
								<CircularProgress size={20} color="info" />
							}
							{__('Connect', 'cp-sync')}
						</Button>
					</div>
				</>
			}
			{
				isConnected &&
				<div>
					<Alert severity="success" sx={{ mt: 2 }}>{__('Connected', 'cp-sync')}</Alert>
					<Button
						variant="contained"
						color="primary"
						sx={{ mt: 4, alignSelf: 'flex-start' }}
						onClick={disconnectOAuth}
						disabled={authLoading}>
						{
							authLoading &&
							<CircularProgress size={20} color="info" />
						}
						{__('Disconnect', 'cp-sync')}
					</Button>
				</div>
			}
		</div>
	);
}
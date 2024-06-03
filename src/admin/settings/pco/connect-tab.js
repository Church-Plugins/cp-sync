import { __ } from '@wordpress/i18n'
import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import Typography from '@mui/material/Typography'
import { useState, useEffect } from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'
import CircularProgress from '@mui/material/CircularProgress'
import { useDispatch, useSelect } from '@wordpress/data'
import optionsStore from '../store'

export default function ConnectTab({ data, updateField }) {
	const { step: activeStep, authorized } = data
	const [authLoading, setAuthLoading] = useState(false)
	const [authError, setAuthError] = useState(null)
	const [isDirty, setIsDirty] = useState(false)
	const { setIsConnected: dispatchSetIsConnected } = useDispatch(optionsStore)
	const isConnected = useSelect((select) => select(optionsStore).isConnected);

	const initiateOAuth = () => {
		setAuthLoading(true)
		setAuthError(null)

		const redirectUrl = encodeURIComponent(window.location.origin + '/wp-admin/')
		const authUrl = `https://churchplugins.com/wp-content/themes/churchplugins/oauth/pco/?action=authorize&redirect_url=${redirectUrl}&_nonce=${cpSync.nonce}`

		// Open OAuth login window
		const authWindow = window.open(authUrl, '_blank', 'width=500,height=600');

		const checkAuthWindow = setInterval(() => {
			apiFetch({
				path: '/cp-sync/v1/pco/check-connection',
				method: 'GET',
			}).then((data) => {
				if (data.connected) {
					authWindow.close();
					setAuthLoading(false)
					dispatchSetIsConnected(true);
					clearInterval(checkAuthWindow)
				}
			}).catch((error) => {
				setAuthLoading(false);
				setAuthError(error.message);
				clearInterval(checkAuthWindow);
			})
		}, 500)
	}

	const disconnectOAuth = () => {
		setAuthLoading(true)
		setAuthError(null)
		apiFetch({
			path: '/cp-sync/v1/pco/disconnect',
			method: 'POST',
		}).then((data) => {
			if (data.success) {
				setAuthLoading(false);
				dispatchSetIsConnected(false);
			}
		}).catch((error) => {
			setAuthLoading(false);
			setAuthError(error.message);
		})

	}

	// Check if the user is connected on initial load
	useEffect(() => {
		apiFetch({
			path: '/cp-sync/v1/pco/check-connection',
			method: 'GET',
		}).then((data) => {
			if (data.connected) {
				dispatchSetIsConnected(true);
			}
		}).catch((error) => {
			setAuthError(error.message);
		})
	}, [dispatchSetIsConnected])

	return (
		<div>
			<Typography variant="h5">{ __( 'PCO API Configuration', 'cp-sync' ) }</Typography>
				{
					!isConnected &&
					<>
						{ __( 'Click the button below to initiate the OAuth flow and connect to Planning Center Online.', 'cp-sync' ) }
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
								{ __( 'Connect', 'cp-sync' ) }
							</Button>
						</div>
					</>
				}
			{
				isConnected &&
				<div>
					<Alert severity="success" sx={{ mt: 2 }}>{ __( 'Connected', 'cp-sync' ) }</Alert>
					<Button variant="text" color="primary" sx={{ ml: 1 }} onClick={disconnectOAuth}>
						{ __( 'Disconnect', 'cp-sync' ) }
					</Button>
				</div>
			}
		</div>
	)
}
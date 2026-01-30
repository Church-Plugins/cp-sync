import Alert from '@mui/material/Alert'
import { __ } from '@wordpress/i18n'
import { useSettings } from '../../contexts/settingsContext'
import Button from '@mui/material/Button'
import Box from '@mui/material/Box'
import { useState, useEffect } from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'
import TextField from '@mui/material/TextField'
import Typography from '@mui/material/Typography'
import InputAdornment from '@mui/material/InputAdornment'
import IconButton from '@mui/material/IconButton'
import Visibility from '@mui/icons-material/Visibility'
import VisibilityOff from '@mui/icons-material/VisibilityOff'
import globalStore from '../../store/globalStore'
import { useDispatch } from '@wordpress/data'

export default function ConnectTab({ data, updateField }) {
	const { isConnected, save, settings } = useSettings()
	const [authLoading, setAuthLoading] = useState(false)
	const [authError, setAuthError] = useState(null)
	const [showMigrationNotice, setShowMigrationNotice] = useState(false)
	const [showPassword, setShowPassword] = useState(true)
	const { invalidateResolutionForStoreSelector, setIsConnected, persistSettings, setSettings } = useDispatch(globalStore)

	// Check if user has old OAuth token (migration detection)
	useEffect(() => {
		const hasOldToken = data.token && data.token.length > 0
		if (hasOldToken && !data.username) {
			setShowMigrationNotice(true)
		}
	}, [data.token, data.username])

	// Hide password when connected, show when disconnected
	useEffect(() => {
		if (isConnected) {
			setShowPassword(false)
		} else {
			setShowPassword(true)
		}
	}, [isConnected])

	const handleConnect = async () => {
		if (!data.subdomain || !data.username || !data.password) {
			setAuthError(__('Please fill in all fields', 'cp-sync'))
			return
		}

		setAuthLoading(true)
		setAuthError(null)

		try {
			// Save credentials first and wait for it to complete
			await persistSettings('ccb', {
				...settings,
				connect: data
			})

			// Then test connection
			const response = await apiFetch({
				path: '/cp-sync/v1/ccb/check-connection',
				method: 'GET',
			})

			if (response.connected) {
				setIsConnected('ccb', true)
				setShowMigrationNotice(false)
			} else {
				setAuthError(response.message || __('Connection failed', 'cp-sync'))
			}
		} catch (error) {
			setAuthError(error.message || __('Connection failed', 'cp-sync'))
		} finally {
			setAuthLoading(false)
		}
	}

	const handleDisconnect = async () => {
		setAuthLoading(true)
		setAuthError(null)

		try {
			const response = await apiFetch({
				path: '/cp-sync/v1/ccb/disconnect',
				method: 'POST',
			})

			if (response.success) {
				// Update Redux store directly with cleared values
				setSettings('ccb', {
					...settings,
					connect: {
						...settings.connect,
						username: '',
						password: '',
						subdomain: ''
					}
				})

				setIsConnected('ccb', false)
				invalidateResolutionForStoreSelector('getIsConnected')
			} else {
				setAuthError(__('Failed to disconnect', 'cp-sync'))
			}
		} catch (error) {
			setAuthError(error.message)
		} finally {
			setAuthLoading(false)
		}
	}

	const canConnect = data.subdomain && data.username && data.password

	return (
		<Box display="flex" flexDirection="column" gap={2} alignItems="start">
			<h2 style={{ marginTop: 0 }}>Connect to Church Community Builder</h2>

			{showMigrationNotice && (
				<Alert severity="warning" sx={{ mb: 2 }}>
					<strong>{__('Authentication Update Required', 'cp-sync')}</strong>
					<p>{__('CCB has updated their API authentication. Please enter your CCB API username and password below to reconnect.', 'cp-sync')}</p>
					<p>{__('Your existing sync filters and settings will be preserved.', 'cp-sync')}</p>
				</Alert>
			)}

			{authError && (
				<Alert severity="error" sx={{ mb: 2 }}>{authError}</Alert>
			)}

			<Box sx={{ mb: 2 }}>
				<Typography variant="body2" color="text.secondary" sx={{ mb: 2 }}>
					{__('Enter your CCB subdomain and API credentials. ', 'cp-sync')}
					<a
						href="https://support.pushpay.com/s/article/How-to-Create-and-Manage-API-Users"
						target="_blank"
						rel="noopener noreferrer"
					>
						{__('Learn how to create API users', 'cp-sync')}
					</a>
				</Typography>
			</Box>

			<Box display="flex" alignItems="center" gap={1} sx={{ width: '100%', maxWidth: '500px' }}>
				<Typography variant="body1" sx={{ whiteSpace: 'nowrap' }}>
					https://
				</Typography>
				<TextField
					label={__('Subdomain', 'cp-sync')}
					value={data.subdomain || ''}
					onChange={(e) => updateField('subdomain', e.target.value)}
					sx={{ flex: 1 }}
					disabled={isConnected}
					placeholder="yourchurch"
				/>
				<Typography variant="body1" sx={{ whiteSpace: 'nowrap' }}>
					.ccbchurch.com/
				</Typography>
			</Box>

			<TextField
				label={__('API Username', 'cp-sync')}
				value={data.username || ''}
				onChange={(e) => updateField('username', e.target.value)}
				sx={{ width: '500px' }}
				disabled={isConnected}
				helperText={__('Your CCB API user username', 'cp-sync')}
			/>

			<TextField
				label={__('API Password', 'cp-sync')}
				type={showPassword ? 'text' : 'password'}
				value={data.password || ''}
				onChange={(e) => updateField('password', e.target.value)}
				sx={{ width: '500px' }}
				disabled={isConnected}
				helperText={__('Your CCB API user password', 'cp-sync')}
				InputProps={!isConnected ? {
					endAdornment: (
						<InputAdornment position="end">
							<IconButton
								aria-label="toggle password visibility"
								onClick={() => setShowPassword(!showPassword)}
								onMouseDown={(e) => e.preventDefault()}
								edge="end"
							>
								{showPassword ? <VisibilityOff /> : <Visibility />}
							</IconButton>
						</InputAdornment>
					)
				} : undefined}
			/>

			{authLoading ? (
				<Button variant="contained" color="info" disabled>
					{__('Loading...', 'cp-sync')}
				</Button>
			) : isConnected ? (
				<Button variant="contained" color="info" onClick={handleDisconnect}>
					{__('Disconnect', 'cp-sync')}
				</Button>
			) : (
				<Button
					variant="contained"
					color="primary"
					onClick={handleConnect}
					disabled={!canConnect}
				>
					{__('Connect to CCB', 'cp-sync')}
				</Button>
			)}
		</Box>
	)
}

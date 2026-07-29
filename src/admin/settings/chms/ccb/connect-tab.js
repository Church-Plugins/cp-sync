import { Button, Notice, Spinner } from '@wordpress/components'
import { __ } from '@wordpress/i18n'
import { useState, useEffect } from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'
import { useSelect, useDispatch } from '@wordpress/data'
import { useSettings } from '../../contexts/settingsContext'
import globalStore from '../../store/globalStore'
import { SchemaForm } from '../../schema-form'
import { omitSyncSchema } from '../../components/sync-toggles'

/**
 * CCB Connect tab.
 *
 * The credential fields (subdomain, username, password) render through
 * <SchemaForm> using the PHP-declared `connect` screen. Everything else — the
 * client-side subdomain-format validation, the save-then-check-connection flow,
 * the old-token migration notice, and the connect/disconnect actions with their
 * store dispatches — stays custom, composed on `@wordpress/components`.
 */
export default function ConnectTab({ data, updateField }) {
	const { isConnected, settings } = useSettings()
	// The sync toggles are declared on the `connect` screen too, but they are
	// rendered by the merged Connect tab (always editable, even once connected), so
	// strip them here to avoid rendering them twice in the credential form.
	const connectSchema = useSelect(
		(select) => omitSyncSchema(select(globalStore).getSchema('ccb')?.connect),
		[]
	)
	const [authLoading, setAuthLoading] = useState(false)
	const [authError, setAuthError] = useState(null)
	const [showMigrationNotice, setShowMigrationNotice] = useState(false)
	const { invalidateResolutionForStoreSelector, setIsConnected, persistSettings, setSettings } = useDispatch(globalStore)

	// Check if user has old OAuth token (migration detection)
	useEffect(() => {
		const hasOldToken = data.token && data.token.length > 0
		if (hasOldToken && !data.username) {
			setShowMigrationNotice(true)
		}
	}, [data.token, data.username])

	const handleConnect = async () => {
		if (!data.subdomain || !data.username || !data.password) {
			setAuthError(__('Please fill in all fields', 'cp-sync'))
			return
		}

		// Subdomains are simple labels. Validate before saving so a malformed
		// value (which the server rejects for SSRF safety) surfaces a clear
		// message here instead of a generic connection failure.
		if (!/^[a-zA-Z0-9-]+$/.test(data.subdomain)) {
			setAuthError(__('Invalid subdomain. Subdomains may contain only letters, numbers, and hyphens.', 'cp-sync'))
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
		<div className="cps-settings-screen cps-ccb-connect">
			<h2 style={{ marginTop: 0 }}>{__('Connect to Church Community Builder', 'cp-sync')}</h2>

			{showMigrationNotice && (
				<Notice status="warning" isDismissible={false}>
					<strong>{__('Authentication Update Required', 'cp-sync')}</strong>
					<p>{__('CCB has updated their API authentication. Please enter your CCB API username and password below to reconnect.', 'cp-sync')}</p>
					<p>{__('Your existing sync filters and settings will be preserved.', 'cp-sync')}</p>
				</Notice>
			)}

			{authError && (
				<Notice status="error" isDismissible={false}>{authError}</Notice>
			)}

			<p>
				{__('Enter your CCB subdomain and API credentials. ', 'cp-sync')}
				<a
					href="https://support.pushpay.com/s/article/How-to-Create-and-Manage-API-Users"
					target="_blank"
					rel="noopener noreferrer"
				>
					{__('Learn how to create API users', 'cp-sync')}
				</a>
			</p>

			{connectSchema ? (
				<SchemaForm
					schema={connectSchema}
					values={data}
					onChange={updateField}
					disabled={isConnected}
				/>
			) : (
				<Spinner />
			)}

			<div className="cps-ccb-connect__actions">
				{authLoading ? (
					<Button variant="primary" disabled>
						<Spinner />
						{__('Loading...', 'cp-sync')}
					</Button>
				) : isConnected ? (
					<Button variant="secondary" onClick={handleDisconnect}>
						{__('Disconnect', 'cp-sync')}
					</Button>
				) : (
					<Button
						variant="primary"
						onClick={handleConnect}
						disabled={!canConnect}
					>
						{__('Connect to CCB', 'cp-sync')}
					</Button>
				)}
			</div>
		</div>
	)
}

import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import FormControlLabel from '@mui/material/FormControlLabel'
import Switch from '@mui/material/Switch'
import TextField from '@mui/material/TextField'
import { __ } from '@wordpress/i18n'
import apiFetch from '@wordpress/api-fetch'
import { useState } from '@wordpress/element'
import { useSettings } from './settingsProvider'

function LicenseTab({ save }) {
	const [pending, setPending] = useState(false)
	const [error, setError] = useState(null)
	const [success, setSuccess] = useState(false)
	const { globalSettings, updateGlobalSettings } = useSettings()

	const { license, status, beta } = globalSettings

	const activateLicense = () => {
		setSuccess(null)
		setError(null)
		setPending(true)
		apiFetch({
			path: '/churchplugins/v1/license/cps_license',
			method: 'POST',
			data: { license: globalSettings.license }
		}).then(data => {
			updateGlobalSettings('status', data.status)
			setSuccess(data.message)
			setError(null)
			save()
		}).catch(e => {
			setError(e.message)
		}).finally(() => {
			setPending(false)
		})
	}

	const deactivateLicense = () => {
		setSuccess(null)
		setError(null)
		setPending(true)
		apiFetch({
			path: '/churchplugins/v1/license/cps_license',
			method: 'DELETE'
		}).then(data => {
			updateGlobalSettings('status', data.status)
			setSuccess(data.message)
			setError(null)
			save()
		}).catch(e => {
			setError(e.message)
		}).finally(() => {
			setPending(false)
		})
	}

	return (
		<Box display="flex" flexDirection="column" gap={2}>
			{
				!!error &&
				<Alert severity="error">{error}</Alert>
			}

			{
				success &&
				<Alert severity="success">{success}</Alert>
			}

			<Box display="flex" alignItems="center" gap={2}>
				<TextField
					label="License Key"
					value={license}
					onChange={(e) => updateGlobalSettings('license', e.target.value)}
					variant="outlined"
					disabled={status === 'valid'}
					sx={{ width: '300px' }}
				/>
				<Button disabled={pending} onClick={status === 'valid' ? deactivateLicense : activateLicense}>
					{
						pending ?
						__( 'Processing', 'cp-sync' ) :
						status === 'valid' ?
						__( 'Deactivate', 'cp-sync' ) :
						__( 'Activate', 'cp-sync' )
					}
				</Button>
			</Box>
			
			<FormControlLabel
				control={<Switch checked={beta} onChange={(e) => updateGlobalSettings('beta', e.target.checked)} />}
				label={__( 'Enable beta updates', 'cp-sync' )}
				sx={{ maxWidth: 'fit-content' }}
			/>
		</Box>		
	)
}

export const licenseTab = {
	name: 'License',
	group: 'license',
	component: (props) => <LicenseTab {...props} />,
}

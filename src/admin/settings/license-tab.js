import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import FormControlLabel from '@mui/material/FormControlLabel'
import Switch from '@mui/material/Switch'
import TextField from '@mui/material/TextField'
import { __ } from '@wordpress/i18n'
import apiFetch from '@wordpress/api-fetch'
import { useState } from '@wordpress/element'

function LicenseTab({ data, updateField, save }) {
	const [pending, setPending] = useState(false)
	const [error, setError] = useState(null)
	const [success, setSuccess] = useState(false)

	const { license, status } = data

	const activateLicense = () => {
		setSuccess(null)
		setError(null)
		setPending(true)
		apiFetch({
			path: '/churchplugins/v1/license/cpc_license',
			method: 'POST',
			data: { license: data.license }
		}).then(data => {
			updateField('status', data.status)
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
			path: '/churchplugins/v1/license/cpc_license',
			method: 'DELETE'
		}).then(data => {
			updateField('status', data.status)
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
					onChange={(e) => updateField('license', e.target.value)}
					variant="outlined"
					disabled={status === 'valid'}
					sx={{ width: '300px' }}
				/>
				<Button disabled={pending} onClick={status === 'valid' ? deactivateLicense : activateLicense}>
					{
						pending ?
						__( 'Processing', 'cp-connect' ) :
						status === 'valid' ?
						__( 'Deactivate', 'cp-connect' ) :
						__( 'Activate', 'cp-connect' )
					}
				</Button>
			</Box>
			
			<FormControlLabel
				control={<Switch checked={data.beta} onChange={(e) => updateField('beta', e.target.checked)} />}
				label={__( 'Enable beta updates', 'cp-connect' )}
				sx={{ maxWidth: 'fit-content' }}
			/>
		</Box>		
	)
}

export const licenseTab = {
	name: 'License',
	component: (props) => <LicenseTab {...props} />,
	optionGroup: 'license',
	defaultData: {
		license: '',
		status: false,
		beta: false,
	}
}

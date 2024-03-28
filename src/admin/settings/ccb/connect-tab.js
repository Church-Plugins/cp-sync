import { __ } from '@wordpress/i18n'
import Alert from '@mui/material/Alert'
import TextField from '@mui/material/TextField'
import Button from '@mui/material/Button'
import { useState } from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'

export default function ConnectTab({ data, updateField }) {
	const [isImporting, setIsImporting] = useState(false)
	const [success, setSuccess] = useState(false)
	const [error, setError] = useState(null)

	const startImport = () => {
		apiFetch({
			path: '/cp-connect/v1/ccb/pull',
			method: 'POST',
		}).then(data => {
			if(data.success) {
				setSuccess(true)
			} else {
				setError(data.message)
			}
		}).catch(e => {
			setError(e.message)
		}).finally(() => {
			setIsImporting(false)
		})
	}

	return (
		<div>
			{
				!!error &&
				<Alert severity="error" sx={{ mb: 2 }}>{error}</Alert>
			}

			{
				!!success &&
				<Alert severity="success" sx={{ mb: 2 }}>{ __( 'Import started', 'cp-connect' ) }</Alert>
			}

			<div style={{ marginTop: '1rem' }}>
				<TextField
					sx={{ width: '400px' }}
					label={__( 'Your CCB website subdomain', 'cp-connect' )}
					helperText={__( 'This is the subdomain of your CCB website. For example, if your website is "https://mychurch.ccbchurch.com", then your subdomain is "mychurch".', 'cp-connect' )}
					value={data.api_prefix}
					onChange={(e) => updateField('api_prefix', e.target.value)}
					variant="outlined"
				/>
			</div>
			<div style={{ marginTop: '1rem' }}>
				<TextField sx={{ width: '400px' }} label={__( 'API username', 'cp-connect' )} value={data.api_user} onChange={(e) => updateField('api_user', e.target.value)} variant="outlined" />
			</div>
			<div style={{ marginTop: '1rem' }}>
				<TextField sx={{ width: '400px' }} label={__( 'API password', 'cp-connect' )} value={data.api_pass} onChange={(e) => updateField('api_pass', e.target.value)} variant="outlined" />
			</div>
			<div style={{ marginTop: '1rem' }}>
				<Button variant="contained" color="primary" onClick={startImport} disabled={success || isImporting}>{
					success ? __( 'Import started', 'cp-connect' ) : isImporting ? __( 'Starting import', 'cp-connect' ) : __( 'Pull Now', 'cp-connect' )
				}</Button>
			</div>
		</div>
	)
}


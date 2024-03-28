import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import TextField from '@mui/material/TextField';
import { __ } from '@wordpress/i18n';
import Api from './api';
import { useState } from '@wordpress/element';

export default function ConnectTab({ data, updateField, isDirty }) {
	const [authSuccess, setAuthSuccess] = useState(false)
	const [authError, setAuthError] = useState(null)

	const testAuth = async () => {
		const api = new Api(data)
		setAuthError(null)
		setAuthSuccess(false)
		try {
			await api.getAccessToken()
			setAuthSuccess(true)
		} catch(e) {
			setAuthError(e.message)
		}
	}
		
	return (
		<Box display="flex" flexDirection="column" gap={2} maxWidth="350px">
			<TextField
				label={__( 'API Endpoint', 'cp-connect' )}
				value={data.api_endpoint}
				onChange={(e) => updateField('api_endpoint', e.target.value)}
				variant="outlined"
			/>
			<TextField
				label={__( 'OAuth Discovery Endpoint', 'cp-connect' )}
				value={data.oauth_discovery_endpoint}
				onChange={(e) => updateField('oauth_discovery_endpoint', e.target.value)}
				variant="outlined"
			/>
			<TextField
				label={__( 'Client ID', 'cp-connect' )}
				value={data.client_id}
				onChange={(e) => updateField('client_id', e.target.value)}
				variant="outlined"
			/>
			<TextField
				label={__( 'Client Secret', 'cp-connect' )}
				value={data.client_secret}
				onChange={(e) => updateField('client_secret', e.target.value)}
				variant="outlined"
			/>
			<TextField
				label={__( 'API Scope', 'cp-connect' )}
				value={data.api_scope}
				onChange={(e) => updateField('api_scope', e.target.value)}
				variant="outlined"
			/>

			{
				authError ?
				<Alert severity="error">{authError}</Alert> :
				authSuccess ?
				<Alert severity="success">{__( 'Authentication Successful', 'cp-connect' )}</Alert> :
				null
			}

			{
				isDirty &&
				<Button variant='contained' color="info" onClick={testAuth}>{__( 'Authenticate', 'cp-connect' )}</Button>
			}

		</Box>
	)
}

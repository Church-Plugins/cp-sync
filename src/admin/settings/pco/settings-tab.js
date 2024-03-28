import Alert from '@mui/material/Alert'
import Button from '@mui/material/Button'
import FormControl from '@mui/material/FormControl'
import FormLabel from '@mui/material/FormLabel'
import RadioGroup from '@mui/material/RadioGroup'
import FormControlLabel from '@mui/material/FormControlLabel'
import Radio from '@mui/material/Radio'
import { __ } from '@wordpress/i18n'
import { useState } from '@wordpress/element'
import apiFetch from '@wordpress/api-fetch'

export default function SettingsTab({ data, updateField }) {
	const [isImporting, setIsImporting] = useState(false)
	const [error, setError] = useState(null)
	const [success, setSuccess] = useState(false)

	const startImport = () => {
		setIsImporting(true)

		apiFetch({
			path: '/cp-connect/v1/pull',
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
				<Alert severity="error" sx={{ mb: 2 }}>
					<div dangerouslySetInnerHTML={{ __html: error }}></div>
				</Alert>
			}

			{
				!!success &&
				<Alert severity="success" sx={{ mb: 2 }}>{ __( 'Import started', 'cp-connect' ) }</Alert>
			}

			<Button onClick={startImport} disabled={isImporting || success} variant="contained" color="primary">
				{ success ? __( 'Import started', 'cp-connect' ) : isImporting ? __( 'Importing...', 'cp-connect' ) : __( 'Start import', 'cp-connect' ) }
			</Button>

			<div style={{ marginTop: '1rem' }}>
				<FormControl>
					<FormLabel id="enable-events-radio-group-label">{ __( 'Enable Events', 'cp-connect' ) }</FormLabel>
					<RadioGroup
						aria-labelledby="enable-events-radio-group-label"
						value={data.events_enabled}
						onChange={(e) => updateField('events_enabled', e.target.value)}
					>
						<FormControlLabel value={0} control={<Radio />} label={ __( 'Pull from Calendar', 'cp-connect' ) } />
						<FormControlLabel value={1} control={<Radio />} label={ __( 'Pull from Registrations (beta)', 'cp-connect' ) } />
						<FormControlLabel value={2} control={<Radio />} label={ __( 'Do not pull', 'cp-connect' )} />
					</RadioGroup>
				</FormControl>
			</div>

			<div style={{ marginTop: '1rem' }}>
				<FormControl>
					<FormLabel id="enable-register-button-radio-group-label">{ __( 'Event Register Button', 'cp-connect' ) }</FormLabel>
					<RadioGroup
						aria-labelledby="enable-register-button-radio-group-label"
						value={data.events_register_button_enabled}
						onChange={(e) => updateField('events_register_button_enabled', e.target.value)}
					>
						<FormControlLabel value={0} control={<Radio />} label={ __( 'Show', 'cp-connect' ) } />
						<FormControlLabel value={1} control={<Radio />} label={ __( 'Hide', 'cp-connect' ) } />
					</RadioGroup>
				</FormControl>
			</div>

			<div style={{ marginTop: '1rem' }}>
				<FormControl>
					<FormLabel id="enable-groups-radio-group-label">{ __( 'Enable Groups', 'cp-connect' ) }</FormLabel>
					<RadioGroup
						aria-labelledby="enable-groups-radio-group-label"
						value={data.groups_enabled}
						onChange={(e) => updateField('groups_enabled', e.target.value)}
					>
						<FormControlLabel value={1} control={<Radio />} label={ __( 'Pull from Groups', 'cp-connect' ) } />
						<FormControlLabel value={0} control={<Radio />} label={ __( 'Do not pull', 'cp-connect' ) } />
					</RadioGroup>
				</FormControl>
			</div>
		</div>
	)
}

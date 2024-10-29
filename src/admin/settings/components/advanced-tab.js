import Alert from '@mui/material/Alert'
import Box from '@mui/material/Box'
import Button from '@mui/material/Button'
import FormControl from '@mui/material/FormControl'
import InputLabel from '@mui/material/InputLabel'
import MenuItem from '@mui/material/MenuItem'
import Select from '@mui/material/Select'
import CircularProgress from '@mui/material/CircularProgress'
import { __ } from '@wordpress/i18n'
import { useState } from '@wordpress/element'
import { useSettings } from '../contexts/settingsContext'
import apiFetch from '@wordpress/api-fetch'

/**
 * Render the advanced tab
 *
 * @param {Object} props
 * @param {Function} props.save - The save handler
 * @returns 
 */
function AdvancedTab({ save }) {
	const [isStartingPull, setIsStartingPull] = useState(false)
	const [success, setSuccess] = useState(false)
	const [error, setError] = useState(false)
	const { globalSettings, updateGlobalSettings, isDirty } = useSettings()

	const { updateInterval = 'hourly' } = globalSettings

	const handleHardPull = async () => {
		setIsStartingPull(true)

		if(isDirty) {
			await save()
		}

		apiFetch({
			path:   '/cp-sync/v1/pull',
			method: 'POST',
		}).then((res) => {
			if(res.success) {
				setSuccess(true)
				setError(false)
			}
		}).catch((err) => {
			console.error(err)
			setError(err.message)
		}).finally(() => {
			setIsStartingPull(false)
		})
	}

	return (
		<Box display="flex" flexDirection="column" gap={2}>
			{
				success && (
					<Alert severity="success">{ __( 'Hard pull started successfully', 'cp-sync' ) }</Alert>
				)
			}
			{
				error && (
					<Alert severity="error">{ error }</Alert>
				)
			}
			<FormControl sx={{ maxWidth: '300px' }}>
				<InputLabel id="update-interval-label">{__( 'Update Interval', 'cp-sync' )}</InputLabel>
				<Select
					value={updateInterval}
					onChange={(e) => updateGlobalSettings('updateInterval', e.target.value)}
					label={__( 'Update Interval', 'cp-sync' )}
				>
					<MenuItem value="hourly">Hourly</MenuItem>
					<MenuItem value="daily">Daily</MenuItem>
					<MenuItem value="weekly">Weekly</MenuItem>
				</Select>
			</FormControl>
			<Button
				sx={{ width: 'max-content' }}
				variant="contained"
				onClick={handleHardPull}
				disabled={isStartingPull}>
				{ isStartingPull && <CircularProgress size={16} color="inherit" sx={{ mr: 1 }} /> }
				{ isStartingPull ? __( 'Starting hard pull', 'cp-sync' ) : __( 'Pull now', 'cp-sync' ) }
			</Button>
		</Box>
	)
}

export const advancedTab = {
	name: 'Advanced',
	group: 'advanced',
	component: (props) => <AdvancedTab {...props} />,
}

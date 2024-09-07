import Box from '@mui/material/Box'
import FormControl from '@mui/material/FormControl'
import InputLabel from '@mui/material/InputLabel'
import Select from '@mui/material/Select'
import MenuItem from '@mui/material/MenuItem'
import { __ } from '@wordpress/i18n'
import platforms from '../platforms'
import Alert from '@mui/material/Alert';
import { useSettings } from '../contexts/settingsContext'

function ChMSTab() {
	const { globalSettings, updateGlobalSettings } = useSettings()

	return (
		<Box>
			<FormControl>
				<InputLabel id="chms-select-label">ChMS</InputLabel>
				<Select
					labelId="chms-select-label"
					label={__( 'ChMS', 'cp-sync' )}
					value={globalSettings.chms}
					onChange={(e) => updateGlobalSettings('chms', e.target.value)}
					placeholder={__( 'Select ChMS', 'cp-sync' )}
					sx={{ minWidth: "300px" }}
				>
					<MenuItem value="" sx={{ opacity: 0.5 }} >{__( 'Select', 'cp-sync' )}</MenuItem>
					{Object.keys(platforms).map((key) => (
						<MenuItem disabled={'mp' === key} key={key} value={key}>{platforms[key].name}</MenuItem>
					))}
				</Select>
			</FormControl>
			<Alert sx={{ marginTop: '1rem' }} severity="info">{ __( 'More platforms coming soon!', 'cp-sync' ) }</Alert>
		</Box>
	)
}

export const chmsTab = {
	name: __( 'ChMS', 'cp-sync' ),
	component: (props) => <ChMSTab {...props} />,
}

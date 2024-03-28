import Box from '@mui/material/Box'
import FormControl from '@mui/material/FormControl'
import InputLabel from '@mui/material/InputLabel'
import Select from '@mui/material/Select'
import MenuItem from '@mui/material/MenuItem'
import { __ } from '@wordpress/i18n'
import platforms from './platforms'

function ChMSTab({ data, updateField }) {
	const { chms } = data

	return (
		<Box>
			<FormControl>
				<InputLabel id="chms-select-label">ChMS</InputLabel>
				<Select
					labelId="chms-select-label"
					label={__( 'ChMS', 'cp-sync' )}
					value={chms}
					onChange={(e) => updateField('chms', e.target.value)}
					placeholder={__( 'Select', 'cp-sync' )}	
					sx={{ minWidth: "300px" }}
				>
					<MenuItem value="" sx={{ opacity: 0.5 }} >{__( 'Select', 'cp-sync' )}</MenuItem>
					{Object.keys(platforms).map((key) => (
						<MenuItem key={key} value={key}>{platforms[key].name}</MenuItem>
					))}
				</Select>
			</FormControl>
		</Box>	
	)
}

export const chmsTab = {
	name: __( 'ChMS', 'cp-sync' ),
	component: (props) => <ChMSTab {...props} />,
	optionGroup: 'main_options',
	defaultData: {
		chms: ''
	},
}
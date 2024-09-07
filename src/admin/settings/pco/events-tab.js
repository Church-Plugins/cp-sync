import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import FormControl from '@mui/material/FormControl';
import FormControlLabel from '@mui/material/FormControlLabel';
import FormLabel from '@mui/material/FormLabel';
import Radio from '@mui/material/Radio';
import RadioGroup from '@mui/material/RadioGroup';
import Typography from '@mui/material/Typography';
import CloudOutlined from '@mui/icons-material/CloudOutlined';
import FilterAltOutlined from '@mui/icons-material/FilterAltOutlined';
import FormHelperText from '@mui/material/FormHelperText';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useState } from '@wordpress/element';
import Filters from '../filters/filters';
import Preview from './preview';
import AsyncSelect from './async-select';

/**
 * Events tab component.
 * 
 * Manages pulling and filtering events from PCO.
 *
 * @param {Object} props
 * @param {Object} props.data the current settings data.
 * @param {Function} props.updateField the function to call when a field is updated.
 * @returns {JSX.Element}
 * @since 1.0.0
 */
export default function EventsTab({ data, updateField }) {
	const [pulling, setPulling] = useState(false)
	const [pullSuccess, setPullSuccess] = useState(false)
	const [error, setError] = useState(null)

	const updateFilters = (newData) => {
		updateField('filter', {
			...data.filter,
			...newData
		})
	}

	const handlePull = () => {
		setPulling(true)
		apiFetch({
			path: '/cp-sync/v1/pull/events',
			method: 'POST',
		}).then(response => {
			if(response.success) {
				setPullSuccess(true)
			} else {
				setError(response.message)
			}
		}).finally(() => {
			setPulling(false)
		})
	}

	return (
		<Box sx={{ display: 'flex' }} gap={2}>
			<div style={{ flexGrow: '3' }}>
				<FormControl>
					<FormLabel id="enable-events-radio-group-label">{ __( 'Event source', 'cp-sync' ) }</FormLabel>
					<RadioGroup
						aria-labelledby="enable-events-radio-group-label"
						value={data.source}
						onChange={(e) => updateField('source', e.target.value)}
					>
						<FormControlLabel value='calendar' control={<Radio />} label={ __( 'Pull from Calendar', 'cp-sync' ) } />
						<FormControlLabel value='registrations' control={<Radio />} label={ __( 'Pull from Registrations (beta)', 'cp-sync' ) } />
						<FormControlLabel value='none' control={<Radio />} label={ __( 'Do not pull', 'cp-sync' )} />
					</RadioGroup>
				</FormControl>
				
				{
					data.source === 'calendar' ?
					<>
					<Typography variant="h6" sx={{ display: 'flex', alignItems: 'center', mt: 2 }}>
						<CloudOutlined sx={{ mr: 1 }} />
						{ __( 'Select data to pull from PCO', 'cp-sync' ) }
					</Typography>
					<AsyncSelect
						apiPath="/cp-sync/v1/pco/events/tag_groups"
						value={data.tag_groups}
						onChange={data => updateField('tag_groups', data)}
						label={__( 'Tag groups' )}
						sx={{ mt: 2, width: 500 }}
					/>
					<FormHelperText>{__( 'Pull these tag groups as separate taxonomies for The Events Calendar.', 'cp-sync' )}</FormHelperText>
					<Typography variant="h6" sx={{ mt: 4, display: 'flex', alignItems: 'center' }}>
						<FilterAltOutlined sx={{ mr: 1 }} />
						{ __( 'Filters', 'cp-sync' ) }
					</Typography>
					<FormControl sx={{ mt: 2 }}>
						<FormLabel id="visibility-filter-label">{ __( 'Visibility', 'cp-sync' ) }</FormLabel>
						<RadioGroup
							aria-labelledby='visibility-filter-label'
							value={data.visibility}
							onChange={(e) => updateField('visibility', e.target.value)}
						>
							<FormControlLabel value="all" control={<Radio />} label={__( 'Show All' )} />
							<FormControlLabel value="public" control={<Radio />} label={__( 'Only Visible in Church Center' )} />
						</RadioGroup>
					</FormControl>
					</> :
					data.source === 'registrations' ?
					false :
					false
				}

				<Filters
					label={__('Events', 'cp-sync')}
					filterGroup='events'
					filter={data.filter}
					onChange={updateFilters}
				/>

				{
					data.source !== 'none' &&
					<Button
						variant="contained"
						sx={{ mt: 2 }}
						disabled={pulling}
						onClick={handlePull}
					>
						{ pulling ? __( 'Starting import', 'cp-sync' ) : __( 'Pull Now', 'cp-sync' ) }
					</Button>
				}
				
				{
					pullSuccess &&
					<Alert severity="success" sx={{ mt: 2 }}>{ __( 'Import started', 'cp-sync' ) }</Alert>
				}
				{
					error &&
					<Alert severity="error" sx={{ mt: 2 }}>{
						<div dangerouslySetInnerHTML={{ __html: error }} />
					}</Alert>
				}
			</div>
			<Box sx={{ flexGrow: 2 }}>
				<Preview type="events" />
			</Box>
		</Box>
	)
}

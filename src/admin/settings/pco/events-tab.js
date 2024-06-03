import Alert from '@mui/material/Alert';
import Autocomplete from '@mui/material/Autocomplete';
import Button from '@mui/material/Button';
import FormControl from '@mui/material/FormControl';
import FormControlLabel from '@mui/material/FormControlLabel';
import FormLabel from '@mui/material/FormLabel';
import Radio from '@mui/material/Radio';
import RadioGroup from '@mui/material/RadioGroup';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import CloudOutlined from '@mui/icons-material/CloudOutlined';
import FilterAltOutlined from '@mui/icons-material/FilterAltOutlined';
import FormHelperText from '@mui/material/FormHelperText';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { useState } from '@wordpress/element';
import Filters from './filters';
import AsyncSelect from './async-select';
import store from './store';

const EVENT_RECURRENCE_OPTIONS = [
	{ value: 'None', label: __( 'None' ) },
	{ value: 'Daily', label: __( 'Daily' ) },
	{ value: 'Weekly', label: __( 'Weekly' ) },
	{ value: 'Monthly', label: __( 'Monthly' ) },
]

export default function EventsTab({ data, updateField, globalData }) {
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
			path: '/cp-sync/v1/pull/tec',
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

	const filterConfig = {
		start_date: {
			label: globalData.pco.event_filter_options.start_date,
			type: 'date'
		},
		end_date: {
			label: globalData.pco.event_filter_options.end_date,
			type: 'date'
		},
	}

	if(data.events_enabled === 'registrations') {
		filterConfig.registration_category = {
			label: __( 'Category', 'cp-sync' ),
			type: 'select',
			optionsSelector: (preFilters) => {
				return {
					store: store,
					selector: 'getData',
					args: [ '/cp-sync/v1/pco/events/registration_categories' ],
					format: (data) => data.map(category => ({ value: category.id, label: category.name }))
				}
			}
		}
	} else if(data.events_enabled === 'calendar') {
		filterConfig.recurrence = {
			label: globalData.pco.event_filter_options.recurrence,
			type: 'select',
			options: EVENT_RECURRENCE_OPTIONS,
		};
		filterConfig.recurrence_description = {
			label: globalData.pco.event_filter_options.recurrence_description,
			type: 'text',
		};
	}

	return (
		<div>
			<FormControl>
				<FormLabel id="enable-events-radio-group-label">{ __( 'Event source', 'cp-sync' ) }</FormLabel>
				<RadioGroup
					aria-labelledby="enable-events-radio-group-label"
					value={data.events_enabled}
					onChange={(e) => updateField('events_enabled', e.target.value)}
				>
					<FormControlLabel value='calendar' control={<Radio />} label={ __( 'Pull from Calendar', 'cp-sync' ) } />
					<FormControlLabel value='registrations' control={<Radio />} label={ __( 'Pull from Registrations (beta)', 'cp-sync' ) } />
					<FormControlLabel value='none' control={<Radio />} label={ __( 'Do not pull', 'cp-sync' )} />
				</RadioGroup>
			</FormControl>
			
			{
				data.events_enabled === 'calendar' ?
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
				data.events_enabled === 'registrations' ?
				false :
				false
			}

			<Filters
				label={__('Events', 'cp-sync')}
				filterConfig={filterConfig}
				filter={data.filter}
				compareOptions={globalData.pco.compare_options}
				onChange={updateFilters}
			/>

			{
				data.events_enabled !== 'none' &&
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
	)
}

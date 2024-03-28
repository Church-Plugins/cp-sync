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
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

import Filters from './filters';
import AsyncSelect from './async-select';

const ENROLLMENT_STATUS_OPTIONS = {
	'open': __( 'Open' ),
	'closed': __( 'Closed' ),
	'full': __( 'Full' ),
	'private': __( 'Private' ),
}

const ENROLLMENT_STRATEGY_OPTIONS = {
	'request_to_join': __( 'Request to Join' ),
	'open_signup': __( 'Open Signup' ),
}

export default function GroupsTab({ data, updateField, globalData }) {
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
			path: '/cp-connect/v1/pull/cp_groups',
			method: 'POST',
		}).then(response => {
			if(response.success) {
				setPullSuccess(true)
			} else {
				setError(response.message)
			}
		}).catch(err => {
			setError(err.message)
		}).finally(() => {
			setPulling(false)
		})
	}

	useEffect(() => {
		// remove facets that don't have a corresponding tag
		const newData = data.facets.filter(facet => data.tag_groups.find(tag => tag.id === facet.id))
		
		if(newData.length !== data.facets.length) {
			updateField('facets', data.facets.filter(facet => data.tag_groups.find(tag => tag.id === facet.id)))
		}
	}, [data.tag_groups])

	const filterConfig = {
		label: __( 'Groups', 'cp-connect' ),
		enrollment_status: {
			label: __( 'Enrollment Status' ),
			options: Object.keys(ENROLLMENT_STATUS_OPTIONS).map(key => ({ value: key, label: ENROLLMENT_STATUS_OPTIONS[key] }))
		},
		group_type: {
			label: __( 'Group Type' ),
			options: data.types.map(type => ({ value: type.id, label: type.name }))
		},
	}

	return (
		<div>
			<Typography variant="h6" sx={{ display: 'flex', alignItems: 'center' }}>
				<CloudOutlined sx={{ mr: 1 }} />
				{ __( 'Select data to pull from PCO', 'cp-connect' ) }
			</Typography>
			<AsyncSelect
				apiPath="/cp-connect/v1/pco/groups/types"
				value={data.types}
				onChange={data => updateField('types', data)}
				label={__( 'Group Types' )}
				sx={{ mt: 2, width: 500 }}
			/>
			<AsyncSelect
				apiPath="/cp-connect/v1/pco/groups/tag_groups"
				value={data.tag_groups}
				onChange={data => updateField('tag_groups', data)}
				label={__( 'Relevant Tag Groups to Include' )}
				sx={{ mt: 2, width: 500 }}
			/>
			<FormHelperText>{__( 'Pull these tag groups as separate taxonomies for CP Groups.', 'cp-connect' )}</FormHelperText>
			<Autocomplete
				value={data.facets}
				onChange={(e, newValue) => updateField('facets', newValue)}
				renderInput={(params) => (
					<TextField
						{...params}
						label={__( 'Facets', 'cp-connect' )}
					/>
				)}
				multiple
				options={data.tag_groups}
				getOptionLabel={(option) => option.name}
				isOptionEqualToValue={(option, value) => option.id === value.id}
				noOptionsText={__( 'Select some tag types to add here', 'cp-connect' )}
				sx={{ width: 500, mt: 2 }}
			/>
			<FormHelperText>{__( 'Only these taxonomies will be filterable as facets on the groups archive page.', 'cp-connect' )}</FormHelperText>
			<Typography variant="h6" sx={{ mt: 4, display: 'flex', alignItems: 'center' }}>
				<FilterAltOutlined sx={{ mr: 1 }} />
				{ __( 'Filters', 'cp-connect' ) }
			</Typography>
			<Autocomplete
				value={data.enrollment_status}
				onChange={(e, newValue) => updateField('enrollment_status', newValue)}
				multiple
				sx={{ width: 500, mt: 2 }}
				renderInput={(params) => (
					<TextField
						{...params}
						label={__( 'Enrollment Status' )}
					/>
				)}
				options={Object.keys(ENROLLMENT_STATUS_OPTIONS)}
				getOptionLabel={(option) => ENROLLMENT_STATUS_OPTIONS[option]}
			/>
			<Autocomplete
				value={data.enrollment_strategies}
				onChange={(e, newValue) => updateField('enrollment_strategies', newValue)}
				multiple
				sx={{ width: 500, mt: 2 }}
				renderInput={(params) => (
					<TextField
						{...params}
						label={__( 'Enrollment Strategies' )}
					/>
				)}
				options={Object.keys(ENROLLMENT_STRATEGY_OPTIONS)}
				getOptionLabel={(option) => ENROLLMENT_STRATEGY_OPTIONS[option]}
			/>
			<FormControl sx={{ mt: 2 }}>
				<FormLabel id="visibility-filter-label">{ __( 'Visibility', 'cp-connect' ) }</FormLabel>
				<RadioGroup
					aria-labelledby='visibility-filter-label'
					value={data.visibility}
					onChange={(e) => updateField('visibility', e.target.value)}
				>
					<FormControlLabel value="all" control={<Radio />} label={__( 'Show All' )} />
					<FormControlLabel value="public" control={<Radio />} label={__( 'Only Visible in Church Center' )} />
				</RadioGroup>
			</FormControl>
			<Filters filterConfig={filterConfig} filter={data.filter} compareOptions={globalData.pco.compare_options} onChange={updateFilters} />
			<Button
				variant="contained"
				sx={{ mt: 2 }}
				onClick={handlePull}
				disabled={pulling}
			>
				{ pulling ? __( 'Starting import', 'cp-connect' ) : __( 'Pull Now', 'cp-connect' ) }
			</Button>
			{
				pullSuccess &&
				<Alert severity='success' sx={{ mt: 2 }}>
					{ __( 'Import started', 'cp-connect' ) }
				</Alert>
			}
			{
				error &&
				<Alert severity='error' sx={{ mt: 2 }}>
					<div dangerouslySetInnerHTML={{ __html: error }} />
				</Alert>
			}
		</div>
	)
}

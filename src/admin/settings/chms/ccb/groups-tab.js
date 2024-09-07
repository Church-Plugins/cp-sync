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
import Box from '@mui/material/Box';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import Filters from '../../components/filters';
import AsyncSelect from '../../components/async-select';
import Preview from '../../components/preview';
import { useSettings } from '../../contexts/settingsContext';
import Divider from '@mui/material/Divider';

export default function GroupsTab({ data, updateField }) {
	const [pulling, setPulling] = useState(false)
	const [pullSuccess, setPullSuccess] = useState(false)
	const [error, setError] = useState(null)
	const { globalData } = useSettings()

	const updateFilters = (newData) => {}

	const handlePull = () => {}

	return (
		<Box sx={{ display: 'flex', minHeight: '30rem' }} gap={2}>
			<Box sx={{ flex: '3 1 auto' }}>
				<Typography variant="h6" sx={{ display: 'flex', alignItems: 'center' }}>
					<CloudOutlined sx={{ mr: 1 }} />
					{ __( 'Select data to pull from PCO', 'cp-sync' ) }
				</Typography>
				<AsyncSelect
					apiPath="/cp-sync/v1/pco/groups/types"
					value={data.types}
					onChange={data => updateField('types', data)}
					label={__( 'Group Types' )}
					sx={{ mt: 2, width: 500 }}
				/>
				<AsyncSelect
					apiPath="/cp-sync/v1/pco/groups/tag_groups"
					value={data.tag_groups}
					onChange={data => updateField('tag_groups', data)}
					label={__( 'Relevant Tag Groups to Include' )}
					sx={{ mt: 2, width: 500 }}
				/>
				<FormHelperText>{__( 'Pull these tag groups as separate taxonomies for CP Groups.', 'cp-sync' )}</FormHelperText>
				<Autocomplete
					value={data.facets}
					onChange={(e, newValue) => updateField('facets', newValue)}
					renderInput={(params) => (
						<TextField
							{...params}
							label={__( 'Facets', 'cp-sync' )}
						/>
					)}
					multiple
					options={data.tag_groups}
					getOptionLabel={(option) => option.name}
					isOptionEqualToValue={(option, value) => option.id === value.id}
					noOptionsText={__( 'Select some tag types to add here', 'cp-sync' )}
					sx={{ width: 500, mt: 2 }}
				/>
				<FormHelperText>{__( 'Only these taxonomies will be filterable as facets on the groups archive page.', 'cp-sync' )}</FormHelperText>
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
				<Filters
					label={__( 'Groups', 'cp-sync' )}
					filterGroup="groups"
					filter={data.filter}
					onChange={updateFilters}
				/>

				<Divider sx={{ my: 2 }} />

				<Button
					variant="contained"
					sx={{ mt: 2 }}
					onClick={handlePull}
					disabled={pulling}
				>
					{ pulling ? __( 'Starting import', 'cp-sync' ) : __( 'Pull Now', 'cp-sync' ) }
				</Button>
				{
					pullSuccess &&
					<Alert severity='success' sx={{ mt: 2 }}>
						{ __( 'Import started', 'cp-sync' ) }
					</Alert>
				}
				{
					error &&
					<Alert severity='error' sx={{ mt: 2 }}>
						<div dangerouslySetInnerHTML={{ __html: error }} />
					</Alert>
				}
			</Box>
			<Box sx={{ flex: '2 1 50%', background: '#eee', p: 2 }}>
				<Preview type="groups" optionGroup="groups" />
			</Box>
		</Box>
	)
}

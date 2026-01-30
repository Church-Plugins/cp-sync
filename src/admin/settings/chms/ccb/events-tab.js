import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import CloudOutlined from '@mui/icons-material/CloudOutlined';
import FilterAltOutlined from '@mui/icons-material/FilterAltOutlined';
import DateRangeOutlined from '@mui/icons-material/DateRangeOutlined';
import DeleteOutlined from '@mui/icons-material/DeleteOutlined';
import Box from '@mui/material/Box';
import FormControlLabel from '@mui/material/FormControlLabel';
import Checkbox from '@mui/material/Checkbox';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import Filters from '../../components/filters';
import Preview from '../../components/preview';
import DateRange from '../../components/date-range';
import apiFetch from '@wordpress/api-fetch';

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

	const updateDateRange = (newData) => {
		if (newData.mode !== undefined) {
			updateField('date_range_mode', newData.mode);
		}
		if (newData.startDate !== undefined) {
			updateField('date_start', newData.startDate);
		}
		if (newData.endDate !== undefined) {
			updateField('date_end', newData.endDate);
		}
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
		}).catch(err => {
			setError(err.message)
		}).finally(() => {
			setPulling(false)
		})
	}

	return (
		<Box sx={{ display: 'flex', minHeight: '30rem' }} gap={2}>
			<Box sx={{ flex: '3 1 auto' }}>
				<Typography variant="h6" sx={{ display: 'flex', alignItems: 'center' }}>
					<CloudOutlined sx={{ mr: 1 }} />
					{ __( 'Select data to pull from Church Community Builder', 'cp-sync' ) }
				</Typography>

				<Typography variant="h6" sx={{ mt: 4, display: 'flex', alignItems: 'center' }}>
					<DateRangeOutlined sx={{ mr: 1 }} />
					{ __( 'Date Range', 'cp-sync' ) }
				</Typography>

				<DateRange
					mode={data.date_range_mode}
					startDate={data.date_start}
					endDate={data.date_end}
					onChange={updateDateRange}
				/>

				<Typography variant="h6" sx={{ mt: 4, display: 'flex', alignItems: 'center' }}>
					<DeleteOutlined sx={{ mr: 1 }} />
					{ __( 'Cleanup Options', 'cp-sync' ) }
				</Typography>

				<Box sx={{ mb: 3 }}>
					<Alert severity="info" sx={{ mb: 2 }}>
						{__('By default, events outside the configured date range are preserved. Enable this option to remove events that fall outside the date range.', 'cp-sync')}
					</Alert>

					<FormControlLabel
						control={
							<Checkbox
								checked={data.remove_events_outside_range || false}
								onChange={(e) => updateField('remove_events_outside_range', e.target.checked)}
							/>
						}
						label={__('Remove events outside the date range', 'cp-sync')}
					/>
				</Box>

				<Typography variant="h6" sx={{ mt: 4, display: 'flex', alignItems: 'center' }}>
					<FilterAltOutlined sx={{ mr: 1 }} />
					{ __( 'Filters', 'cp-sync' ) }
				</Typography>

				<Filters
					label={__( 'Events', 'cp-sync' )}
					filterGroup="events"
					filter={data.filter}
					onChange={updateFilters}
				/>

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
				<Preview type="events" optionGroup="events" />
			</Box>
		</Box>
	)
}

import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Typography from '@mui/material/Typography';
import CloudOutlined from '@mui/icons-material/CloudOutlined';
import FilterAltOutlined from '@mui/icons-material/FilterAltOutlined';
import Box from '@mui/material/Box';
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import Filters from '../../components/filters';
import Preview from '../../components/preview';
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

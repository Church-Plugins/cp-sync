import Box from '@mui/material/Box';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import Alert from '@mui/material/Alert';
import Radio from '@mui/material/Radio';
import RadioGroup from '@mui/material/RadioGroup';
import FormControlLabel from '@mui/material/FormControlLabel';
import FormControl from '@mui/material/FormControl';
import { __ } from '@wordpress/i18n';

/**
 * Date Range component for setting event fetch date range
 *
 * @param {Object} props
 * @param {string} props.mode - The date range mode (preset or custom)
 * @param {string} props.startDate - The start date value (for custom mode)
 * @param {string} props.endDate - The end date value (for custom mode)
 * @param {Function} props.onChange - Change handler
 * @returns {React.ReactElement}
 */
function DateRange({ mode = 'current_upcoming', startDate, endDate, onChange }) {
	const today = new Date().toISOString().split('T')[0];
	const oneYearOut = new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toISOString().split('T')[0];

	const handleModeChange = (e) => {
		onChange({ mode: e.target.value });
	};

	const handleStartDateChange = (e) => {
		const newStartDate = e.target.value;
		onChange({ startDate: newStartDate });

		// If end date is before new start date, adjust it
		if (endDate && newStartDate > endDate) {
			onChange({ startDate: newStartDate, endDate: newStartDate });
		}
	};

	const handleEndDateChange = (e) => {
		const newEndDate = e.target.value;

		// Prevent end date before start date
		if (startDate && newEndDate < startDate) {
			console.warn('End date cannot be before start date');
			return;
		}

		onChange({ endDate: newEndDate });
	};

	const presetDescriptions = {
		current_upcoming: __('Syncs events from today through 1 year in the future. Updates automatically as time passes.', 'cp-sync'),
		include_past_30: __('Syncs events from 30 days ago through 1 year in the future. Useful for recently passed events.', 'cp-sync'),
		all_future: __('Syncs all events from today onwards with no end date. May take longer for organizations with many events.', 'cp-sync'),
		custom: __('Set specific start and end dates. You will need to update these manually over time.', 'cp-sync'),
	};

	return (
		<Box sx={{ mb: 3 }}>
			<Alert severity="info" sx={{ mb: 2 }}>
				{__('Choose which events to sync from CCB. Events outside the selected range will not be synced.', 'cp-sync')}
			</Alert>

			<FormControl component="fieldset" fullWidth>
				<RadioGroup value={mode} onChange={handleModeChange}>
					<FormControlLabel
						value="current_upcoming"
						control={<Radio />}
						label={
							<Box>
								<Typography variant="body2" sx={{ fontWeight: 500 }}>
									{__('Current and upcoming events (Recommended)', 'cp-sync')}
								</Typography>
								<Typography variant="caption" color="text.secondary">
									{presetDescriptions.current_upcoming}
								</Typography>
							</Box>
						}
						sx={{ mb: 1.5, alignItems: 'flex-start', '& .MuiRadio-root': { mt: 0.5 } }}
					/>
					<FormControlLabel
						value="include_past_30"
						control={<Radio />}
						label={
							<Box>
								<Typography variant="body2" sx={{ fontWeight: 500 }}>
									{__('Include past 30 days', 'cp-sync')}
								</Typography>
								<Typography variant="caption" color="text.secondary">
									{presetDescriptions.include_past_30}
								</Typography>
							</Box>
						}
						sx={{ mb: 1.5, alignItems: 'flex-start', '& .MuiRadio-root': { mt: 0.5 } }}
					/>
					<FormControlLabel
						value="all_future"
						control={<Radio />}
						label={
							<Box>
								<Typography variant="body2" sx={{ fontWeight: 500 }}>
									{__('All future events', 'cp-sync')}
								</Typography>
								<Typography variant="caption" color="text.secondary">
									{presetDescriptions.all_future}
								</Typography>
							</Box>
						}
						sx={{ mb: 1.5, alignItems: 'flex-start', '& .MuiRadio-root': { mt: 0.5 } }}
					/>
					<FormControlLabel
						value="custom"
						control={<Radio />}
						label={
							<Box>
								<Typography variant="body2" sx={{ fontWeight: 500 }}>
									{__('Custom date range', 'cp-sync')}
								</Typography>
								<Typography variant="caption" color="text.secondary">
									{presetDescriptions.custom}
								</Typography>
							</Box>
						}
						sx={{ mb: 1.5, alignItems: 'flex-start', '& .MuiRadio-root': { mt: 0.5 } }}
					/>
				</RadioGroup>
			</FormControl>

			{mode === 'custom' && (
				<Box sx={{ mt: 2, pl: 4 }}>
					<Box sx={{ display: 'flex', gap: 2 }}>
						<Box sx={{ flex: 1 }}>
							<Typography variant="body2" sx={{ mb: 0.5, fontWeight: 500 }}>
								{__('Start Date', 'cp-sync')}
							</Typography>
							<TextField
								type="date"
								size="small"
								fullWidth
								value={startDate || today}
								onChange={handleStartDateChange}
								InputLabelProps={{
									shrink: true,
								}}
							/>
						</Box>

						<Box sx={{ flex: 1 }}>
							<Typography variant="body2" sx={{ mb: 0.5, fontWeight: 500 }}>
								{__('End Date', 'cp-sync')}
							</Typography>
							<TextField
								type="date"
								size="small"
								fullWidth
								value={endDate || oneYearOut}
								onChange={handleEndDateChange}
								InputLabelProps={{
									shrink: true,
								}}
							/>
						</Box>
					</Box>
					{startDate && endDate && endDate < startDate && (
						<Alert severity="error" sx={{ mt: 1 }}>
							{__('End date must be after start date', 'cp-sync')}
						</Alert>
					)}
				</Box>
			)}
		</Box>
	);
}

export default DateRange;

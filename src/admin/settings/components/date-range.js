import { Notice, RadioControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Date Range component for setting event fetch date range.
 *
 * Ported off MUI to `@wordpress/components`. The prop signature and the STORED
 * values are unchanged: `mode` is one of the preset slugs (or `custom`), and
 * `startDate` / `endDate` are `YYYY-MM-DD` strings. The parent maps these onto
 * the `date_range_mode` / `date_start` / `date_end` settings keys.
 *
 * @param {Object}   props
 * @param {string}   props.mode      - The date range mode (preset or custom).
 * @param {string}   props.startDate - The start date value (for custom mode).
 * @param {string}   props.endDate   - The end date value (for custom mode).
 * @param {Function} props.onChange  - Change handler.
 * @return {React.ReactElement} The date range control.
 */
function DateRange( {
	mode = 'current_upcoming',
	startDate,
	endDate,
	onChange,
} ) {
	const today = new Date().toISOString().split( 'T' )[ 0 ];
	const oneYearOut = new Date(
		new Date().setFullYear( new Date().getFullYear() + 1 )
	)
		.toISOString()
		.split( 'T' )[ 0 ];

	const handleModeChange = ( newMode ) => {
		onChange( { mode: newMode } );
	};

	const handleStartDateChange = ( newStartDate ) => {
		// If end date is before new start date, adjust it in the same update.
		if ( endDate && newStartDate > endDate ) {
			onChange( { startDate: newStartDate, endDate: newStartDate } );
			return;
		}

		onChange( { startDate: newStartDate } );
	};

	const handleEndDateChange = ( newEndDate ) => {
		// Prevent end date before start date.
		if ( startDate && newEndDate < startDate ) {
			return;
		}

		onChange( { endDate: newEndDate } );
	};

	const presetDescriptions = {
		current_upcoming: __(
			'Syncs events from today through 1 year in the future. Updates automatically as time passes.',
			'cp-sync'
		),
		include_past_30: __(
			'Syncs events from 30 days ago through 1 year in the future. Useful for recently passed events.',
			'cp-sync'
		),
		all_future: __(
			'Syncs all events from today onwards with no end date. May take longer for organizations with many events.',
			'cp-sync'
		),
		custom: __(
			'Set specific start and end dates. You will need to update these manually over time.',
			'cp-sync'
		),
	};

	// RadioControl renders each option's `label` as-is in JSX, so a rich node
	// (title + description) preserves the original two-line presentation.
	const renderOptionLabel = ( title, description ) => (
		<span className="cps-date-range__option">
			<span className="cps-date-range__option-title">{ title }</span>
			<span className="cps-date-range__option-desc">{ description }</span>
		</span>
	);

	const options = [
		{
			value: 'current_upcoming',
			label: renderOptionLabel(
				__( 'Current and upcoming events (Recommended)', 'cp-sync' ),
				presetDescriptions.current_upcoming
			),
		},
		{
			value: 'include_past_30',
			label: renderOptionLabel(
				__( 'Include past 30 days', 'cp-sync' ),
				presetDescriptions.include_past_30
			),
		},
		{
			value: 'all_future',
			label: renderOptionLabel(
				__( 'All future events', 'cp-sync' ),
				presetDescriptions.all_future
			),
		},
		{
			value: 'custom',
			label: renderOptionLabel(
				__( 'Custom date range', 'cp-sync' ),
				presetDescriptions.custom
			),
		},
	];

	return (
		<div className="cps-date-range">
			<Notice status="info" isDismissible={ false }>
				{ __(
					'Choose which events to sync from CCB. Events outside the selected range will not be synced.',
					'cp-sync'
				) }
			</Notice>

			<RadioControl
				selected={ mode }
				options={ options }
				onChange={ handleModeChange }
			/>

			{ mode === 'custom' && (
				<div className="cps-date-range__custom">
					<div className="cps-date-range__fields">
						<TextControl
							label={ __( 'Start Date', 'cp-sync' ) }
							type="date"
							value={ startDate || today }
							onChange={ handleStartDateChange }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
						<TextControl
							label={ __( 'End Date', 'cp-sync' ) }
							type="date"
							value={ endDate || oneYearOut }
							onChange={ handleEndDateChange }
							__nextHasNoMarginBottom
							__next40pxDefaultSize
						/>
					</div>
					{ startDate && endDate && endDate < startDate && (
						<Notice status="error" isDismissible={ false }>
							{ __(
								'End date must be after start date',
								'cp-sync'
							) }
						</Notice>
					) }
				</div>
			) }
		</div>
	);
}

export default DateRange;

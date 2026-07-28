import { Button, Notice, Spinner } from '@wordpress/components'
import { __ } from '@wordpress/i18n'
import { useState } from '@wordpress/element'
import { useSelect } from '@wordpress/data'
import apiFetch from '@wordpress/api-fetch'
import globalStore from '../../store/globalStore'
import { SchemaForm } from '../../schema-form'
import Preview from '../../components/preview'
import DateRange from '../../components/date-range'

/**
 * CCB Events tab.
 *
 * `remove_events_outside_range` and `filter` render through <SchemaForm> using
 * the PHP-declared `events` screen. The DateRange widget stays custom (its flat
 * stored keys `date_range_mode` / `date_start` / `date_end` don't fit a single
 * schema field) and writes those keys exactly as before. Pull action + <Preview>
 * stay custom, composed on `@wordpress/components`.
 */
export default function EventsTab({ data, updateField }) {
	const eventsSchema = useSelect(
		(select) => select(globalStore).getSchema('ccb')?.events,
		[]
	)
	const [pulling, setPulling] = useState(false)
	const [pullSuccess, setPullSuccess] = useState(false)
	const [error, setError] = useState(null)

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
			if (response.success) {
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
		<div className="cps-feed-tab" style={{ display: 'flex', gap: '16px', minHeight: '30rem' }}>
			<div className="cps-settings-screen" style={{ flex: '3 1 auto' }}>
				<h3>{__('Select data to pull from Church Community Builder', 'cp-sync')}</h3>

				<h3>{__('Date Range', 'cp-sync')}</h3>
				<DateRange
					mode={data.date_range_mode}
					startDate={data.date_start}
					endDate={data.date_end}
					onChange={updateDateRange}
				/>

				{eventsSchema ? (
					<SchemaForm
						schema={eventsSchema}
						values={data}
						onChange={updateField}
					/>
				) : (
					<Spinner />
				)}

				<div className="cps-feed-tab__actions">
					<Button
						variant="primary"
						onClick={handlePull}
						disabled={pulling}
					>
						{pulling ? __('Starting import', 'cp-sync') : __('Pull Now', 'cp-sync')}
					</Button>
				</div>

				{pullSuccess && (
					<Notice status="success" isDismissible={false}>
						{__('Import started', 'cp-sync')}
					</Notice>
				)}

				{error && (
					<Notice status="error" isDismissible={false}>
						<div dangerouslySetInnerHTML={{ __html: error }} />
					</Notice>
				)}
			</div>
			<div style={{ flex: '2 1 50%', background: '#eee', padding: '16px' }}>
				<Preview type="events" optionGroup="events" />
			</div>
		</div>
	)
}

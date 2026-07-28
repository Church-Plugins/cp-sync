import { Button, Notice, Spinner } from '@wordpress/components'
import { __ } from '@wordpress/i18n'
import { useState } from '@wordpress/element'
import { useSelect } from '@wordpress/data'
import apiFetch from '@wordpress/api-fetch'
import globalStore from '../../store/globalStore'
import { SchemaForm } from '../../schema-form'
import Preview from '../../components/preview'

/**
 * CCB Groups tab.
 *
 * The `filter` setting renders through <SchemaForm> using the PHP-declared
 * `groups` screen (a single `filter-builder` field). The pull action and the
 * <Preview> panel stay custom, composed on `@wordpress/components`.
 */
export default function GroupsTab({ data, updateField }) {
	const groupsSchema = useSelect(
		(select) => select(globalStore).getSchema('ccb')?.groups,
		[]
	)
	const [pulling, setPulling] = useState(false)
	const [pullSuccess, setPullSuccess] = useState(false)
	const [error, setError] = useState(null)

	const handlePull = () => {
		setPulling(true)
		apiFetch({
			path: '/cp-sync/v1/pull/groups',
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

				{groupsSchema ? (
					<SchemaForm
						schema={groupsSchema}
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
				<Preview type="groups" optionGroup="groups" />
			</div>
		</div>
	)
}

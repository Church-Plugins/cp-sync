import { Spinner } from '@wordpress/components'
import { __ } from '@wordpress/i18n'
import { useSelect } from '@wordpress/data'
import globalStore from '../../store/globalStore'
import { SchemaForm } from '../../schema-form'
import Preview from '../../components/preview'
import PullNow from '../../components/pull-now'

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
					<PullNow type="groups" />
				</div>
			</div>
			<div style={{ flex: '2 1 50%', background: '#eee', padding: '16px' }}>
				<Preview type="groups" optionGroup="groups" />
			</div>
		</div>
	)
}

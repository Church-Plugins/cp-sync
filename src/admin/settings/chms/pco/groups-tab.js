import { useSelect } from '@wordpress/data';

import { SchemaForm } from '../../schema-form';
import globalStore from '../../store/globalStore';
import Preview from '../../components/preview';
import PullNow from '../../components/pull-now';

/**
 * PCO Groups tab.
 *
 * Schema-driven: the `cp_groups` screen (tag_groups, visibility, filter)
 * renders through <SchemaForm>. Synced tag groups are surfaced as facets on
 * the CP Groups archive automatically — there is no facet picker here. The
 * pull-now action button and the <Preview> pane are composed alongside.
 */
export default function GroupsTab( { data, updateField } ) {
	const schema = useSelect(
		( select ) => select( globalStore ).getSchema( 'pco' ),
		[]
	);
	const screen = schema?.cp_groups;

	return (
		<div style={ { display: 'flex', gap: '1rem', minHeight: '30rem' } }>
			<div style={ { flex: '3 1 auto' } }>
				<SchemaForm
					schema={ screen }
					values={ data }
					onChange={ updateField }
				/>

				<hr style={ { margin: '1.5rem 0' } } />

				<PullNow type="groups" />
			</div>
			<div style={ { flex: '2 1 50%', background: '#eee', padding: '1rem' } }>
				<Preview type="groups" optionGroup="groups" />
			</div>
		</div>
	);
}

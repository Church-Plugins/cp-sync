import { useSelect } from '@wordpress/data';

import { SchemaForm } from '../../schema-form';
import globalStore from '../../store/globalStore';
import Preview from '../../components/preview';
import PullNow from '../../components/pull-now';

/**
 * PCO Events tab.
 *
 * Schema-driven: the `ecp` screen (source, tag_groups, visibility, filter)
 * renders through <SchemaForm>. The source=calendar conditionals are expressed
 * by the schema's `show_if` on tag_groups/visibility/filter, so no manual
 * conditional wrapper is needed. The pull-now action button and the <Preview>
 * pane are composed alongside.
 */
export default function EventsTab( { data, updateField } ) {
	const schema = useSelect(
		( select ) => select( globalStore ).getSchema( 'pco' ),
		[]
	);
	const screen = schema?.ecp;

	return (
		<div style={ { display: 'flex', gap: '1rem', minHeight: '30rem' } }>
			<div style={ { flexGrow: 3 } }>
				<SchemaForm
					schema={ screen }
					values={ data }
					onChange={ updateField }
				/>

				<hr style={ { margin: '1.5rem 0' } } />

				{ /* Any real source is active ( `both` included ). `none` is gone
				     from the UI, but still gates un-migrated stored data. */ }
				{ data.source !== 'none' && <PullNow type="events" /> }
			</div>
			<div style={ { flex: '2 1 50%', background: '#eee', padding: '1rem' } }>
				<Preview type="events" />
			</div>
		</div>
	);
}

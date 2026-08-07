import { useSelect } from '@wordpress/data';

import { SchemaForm } from '../../schema-form';
import globalStore from '../../store/globalStore';
import Preview from '../../components/preview';
import PullNow from '../../components/pull-now';

/**
 * PCO Sermons tab.
 *
 * Schema-driven: the `cp_library` screen (a single sermons filter-builder) renders
 * through <SchemaForm>. Only sermons published to the Church Center library are pulled,
 * so there is no visibility control. The pull-now action button and the <Preview> pane
 * are composed alongside, mirroring the Groups/Events tabs.
 */
export default function SermonsTab( { data, updateField } ) {
	const schema = useSelect(
		( select ) => select( globalStore ).getSchema( 'pco' ),
		[]
	);
	const screen = schema?.cp_library;

	return (
		<div style={ { display: 'flex', gap: '1rem', minHeight: '30rem' } }>
			<div style={ { flexGrow: 3 } }>
				<SchemaForm
					schema={ screen }
					values={ data }
					onChange={ updateField }
				/>

				<hr style={ { margin: '1.5rem 0' } } />

				<PullNow type="sermons" />
			</div>
			<div style={ { flex: '2 1 50%', background: '#eee', padding: '1rem' } }>
				<Preview type="sermons" />
			</div>
		</div>
	);
}

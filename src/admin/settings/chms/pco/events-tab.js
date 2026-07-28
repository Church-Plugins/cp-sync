import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';

import { SchemaForm } from '../../schema-form';
import globalStore from '../../store/globalStore';
import Preview from '../../components/preview';

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
	const [ pulling, setPulling ] = useState( false );
	const [ pullSuccess, setPullSuccess ] = useState( false );
	const [ error, setError ] = useState( null );

	const schema = useSelect(
		( select ) => select( globalStore ).getSchema( 'pco' ),
		[]
	);
	const screen = schema?.ecp;

	const handlePull = () => {
		setPulling( true );
		apiFetch( {
			path: '/cp-sync/v1/pull/events',
			method: 'POST',
		} )
			.then( ( response ) => {
				if ( response.success ) {
					setPullSuccess( true );
				} else {
					setError( response.message );
				}
			} )
			.finally( () => {
				setPulling( false );
			} );
	};

	return (
		<div style={ { display: 'flex', gap: '1rem', minHeight: '30rem' } }>
			<div style={ { flexGrow: 3 } }>
				<SchemaForm
					schema={ screen }
					values={ data }
					onChange={ updateField }
				/>

				<hr style={ { margin: '1.5rem 0' } } />

				{ data.source !== 'none' && (
					<Button
						variant="primary"
						onClick={ handlePull }
						disabled={ pulling }
					>
						{ pulling
							? __( 'Starting import', 'cp-sync' )
							: __( 'Pull Now', 'cp-sync' ) }
					</Button>
				) }

				{ pullSuccess && (
					<Notice
						status="success"
						isDismissible={ false }
						className="cps-pco-notice"
					>
						{ __( 'Import started', 'cp-sync' ) }
					</Notice>
				) }
				{ error && (
					<Notice
						status="error"
						isDismissible={ false }
						className="cps-pco-notice"
					>
						<div dangerouslySetInnerHTML={ { __html: error } } />
					</Notice>
				) }
			</div>
			<div style={ { flex: '2 1 50%', background: '#eee', padding: '1rem' } }>
				<Preview type="events" />
			</div>
		</div>
	);
}

import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';

import { SchemaForm } from '../../schema-form';
import globalStore from '../../store/globalStore';
import Preview from '../../components/preview';

/**
 * PCO Sermons tab.
 *
 * Schema-driven: the `cp_library` screen (a single sermons filter-builder) renders
 * through <SchemaForm>. Only sermons published to the Church Center library are pulled,
 * so there is no visibility control. The pull-now action button and the <Preview> pane
 * are composed alongside, mirroring the Groups/Events tabs.
 */
export default function SermonsTab( { data, updateField } ) {
	const [ pulling, setPulling ] = useState( false );
	const [ pullSuccess, setPullSuccess ] = useState( false );
	const [ error, setError ] = useState( null );

	const schema = useSelect(
		( select ) => select( globalStore ).getSchema( 'pco' ),
		[]
	);
	const screen = schema?.cp_library;

	const handlePull = () => {
		setPulling( true );
		setError( null );
		apiFetch( {
			path: '/cp-sync/v1/pull/sermons',
			method: 'POST',
		} )
			.then( ( response ) => {
				if ( response.success ) {
					setPullSuccess( true );
				} else {
					setError( response.message );
				}
			} )
			.catch( ( err ) => {
				setError( err.message );
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

				<Button
					variant="primary"
					onClick={ handlePull }
					disabled={ pulling }
				>
					{ pulling
						? __( 'Starting import', 'cp-sync' )
						: __( 'Pull Now', 'cp-sync' ) }
				</Button>

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
						<div>{ error }</div>
					</Notice>
				) }
			</div>
			<div style={ { flex: '2 1 50%', background: '#eee', padding: '1rem' } }>
				<Preview type="sermons" />
			</div>
		</div>
	);
}

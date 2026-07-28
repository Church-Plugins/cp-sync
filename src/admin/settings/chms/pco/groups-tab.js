import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';

import { SchemaForm } from '../../schema-form';
import globalStore from '../../store/globalStore';
import MultiTokenField from '../../components/multi-token-field';
import Preview from '../../components/preview';

/**
 * PCO Groups tab.
 *
 * Schema-driven: the `cp_groups` screen (types, tag_groups, visibility, filter)
 * renders through <SchemaForm>. The `facets` control is NOT a schema field — its
 * options are derived at runtime from the selected `tag_groups`, so it stays a
 * custom widget (rebuilt on @wordpress/components via MultiTokenField). The
 * pull-now action button and the <Preview> pane are composed alongside.
 */
export default function GroupsTab( { data, updateField } ) {
	const [ pulling, setPulling ] = useState( false );
	const [ pullSuccess, setPullSuccess ] = useState( false );
	const [ error, setError ] = useState( null );

	const schema = useSelect(
		( select ) => select( globalStore ).getSchema( 'pco' ),
		[]
	);
	const screen = schema?.cp_groups;

	const handlePull = () => {
		setPulling( true );
		apiFetch( {
			path: '/cp-sync/v1/pull/groups',
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

	useEffect( () => {
		// Remove facets whose tag group is no longer selected.
		const pruned = data.facets.filter( ( facet ) =>
			data.tag_groups.find( ( tag ) => tag.id === facet.id )
		);

		if ( pruned.length !== data.facets.length ) {
			updateField( 'facets', pruned );
		}
	}, [ data.tag_groups ] );

	return (
		<div style={ { display: 'flex', gap: '1rem', minHeight: '30rem' } }>
			<div style={ { flex: '3 1 auto' } }>
				<SchemaForm
					schema={ screen }
					values={ data }
					onChange={ updateField }
				/>

				<div style={ { marginTop: '1rem' } }>
					<MultiTokenField
						label={ __( 'Facets', 'cp-sync' ) }
						help={ __(
							'Only these taxonomies will be filterable as facets on the groups archive page.',
							'cp-sync'
						) }
						value={ data.facets }
						options={ data.tag_groups }
						onChange={ ( newValue ) =>
							updateField( 'facets', newValue )
						}
						valueKey="id"
						labelKey="name"
					/>
				</div>

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
				<Preview type="groups" optionGroup="groups" />
			</div>
		</div>
	);
}

import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import MultiTokenField from './multi-token-field';

/**
 * AsyncSelect component.
 *
 * A multiselect that fetches its option list from a REST endpoint and stores an
 * array of the selected option objects. Ported off MUI Autocomplete to
 * `@wordpress/components` `FormTokenField` (via `MultiTokenField`).
 *
 * STORED VALUE SHAPE (unchanged): an array of option objects `{ id, name, ... }`
 * — identical to what the MUI version wrote. Options are matched by `id`, shown
 * by `name`.
 *
 * @param {Object}   props
 * @param {string}   props.apiPath  The REST path to fetch options from.
 * @param {Array}    props.value    The current value (array of `{ id, name }`).
 * @param {Function} props.onChange Called with the updated array of options.
 * @param {string}   props.label    The field label.
 * @return {JSX.Element} The async multiselect.
 */
export default function AsyncSelect( { apiPath, value, onChange, label } ) {
	const [ data, setData ] = useState( [] );
	const [ error, setError ] = useState( null );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		let cancelled = false;

		setLoading( true );
		apiFetch( { path: apiPath } )
			.then( ( response ) => {
				if ( cancelled ) {
					return;
				}
				if ( response.success ) {
					setData( response.data );
				} else {
					setError( response.message );
				}
			} )
			.catch( ( e ) => {
				if ( ! cancelled ) {
					setError( e.message );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ apiPath ] );

	return (
		<div className="cps-async-select">
			<MultiTokenField
				label={ label }
				value={ value }
				options={ data }
				onChange={ onChange }
				disabled={ loading }
				valueKey="id"
				labelKey="name"
			/>
			{ error && (
				<p className="cps-field__error" style={ { color: '#cc1818' } }>
					{ typeof error === 'string'
						? error
						: JSON.stringify( error ) }
				</p>
			) }
		</div>
	);
}

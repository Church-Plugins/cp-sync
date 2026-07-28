/**
 * Complex field-type components for the schema form.
 *
 * These are the heavier, reused controls (filter builder, async/static multi-
 * selects) promoted to schema-form registry entries. They follow the same
 * uniform prop contract as the base fields ({ field, value, onChange, disabled })
 * and are registered as a side effect of importing this module (done by
 * `schema-form/index.js`).
 */

import { useSelect } from '@wordpress/data';
import globalStore from '../store/globalStore';
import { registerFieldType } from './registry';
import Filters from '../components/filters';
import MultiTokenField from '../components/multi-token-field';

/**
 * `filter-builder` — wraps the ported `<Filters>` condition builder.
 *
 * FieldDef: `{ type: 'filter-builder', label?, filterGroup: 'groups'|'events' }`.
 * `<Filters>` pulls `filterConfig` (via `useSettings().getFilterConfig`) and
 * `compareOptions` from context itself, exactly as the tabs do today.
 *
 * STORED value: the whole filter object `{ type: 'all'|'any', conditions: [...] }`.
 * `onChange` receives the entire updated object. Defaults to
 * `{ type: 'all', conditions: [] }` when the stored value is undefined.
 *
 * @param {Object}   props
 * @param {Object}   props.field
 * @param {Object}   props.value
 * @param {Function} props.onChange
 * @return {JSX.Element} The filter builder field.
 */
function FilterBuilderField( { field, value, onChange } ) {
	const filter = value ?? { type: 'all', conditions: [] };

	return (
		<Filters
			label={ field.label }
			filterGroup={ field.filterGroup }
			filter={ filter }
			onChange={ onChange }
		/>
	);
}

/**
 * `async-multiselect` — a multiselect whose options are resolved from a REST
 * endpoint through the store's `getOptions( endpoint )` selector/resolver.
 *
 * FieldDef: `{ type: 'async-multiselect', label, help?, endpoint }`.
 *
 * STORED value (byte-identical to the legacy AsyncSelect): an array of the
 * selected option objects `{ id, name, ... }` — matched by `id`, shown by `name`.
 *
 * @param {Object}   props
 * @param {Object}   props.field
 * @param {Array}    props.value
 * @param {Function} props.onChange
 * @param {boolean}  props.disabled
 * @return {JSX.Element} The async multiselect field.
 */
function AsyncMultiselectField( { field, value, onChange, disabled } ) {
	const { options, loading } = useSelect(
		( select ) => {
			const store = select( globalStore );
			return {
				options: store.getOptions( field.endpoint ) || [],
				loading:
					store.getResolutionState( 'getOptions', [ field.endpoint ] )
						?.status === 'resolving',
			};
		},
		[ field.endpoint ]
	);

	return (
		<MultiTokenField
			label={ field.label }
			help={ field.help }
			value={ value }
			options={ options }
			onChange={ onChange }
			disabled={ disabled || loading }
			valueKey="id"
			labelKey="name"
		/>
	);
}

/**
 * `multiselect` — a multiselect whose options are static (`field.options`).
 *
 * FieldDef: `{ type: 'multiselect', label, help?, options }` where each option
 * is `{ value, label }` (or `{ id, name }` — set `valueKey`/`labelKey` in the
 * FieldDef if it differs from the default value/label keys).
 *
 * STORED value: an array of the selected option objects (same shape as options).
 *
 * @param {Object}   props
 * @param {Object}   props.field
 * @param {Array}    props.value
 * @param {Function} props.onChange
 * @param {boolean}  props.disabled
 * @return {JSX.Element} The static multiselect field.
 */
function MultiselectField( { field, value, onChange, disabled } ) {
	return (
		<MultiTokenField
			label={ field.label }
			help={ field.help }
			value={ value }
			options={ field.options || [] }
			onChange={ onChange }
			disabled={ disabled }
			valueKey={ field.valueKey || 'value' }
			labelKey={ field.labelKey || 'label' }
		/>
	);
}

registerFieldType( 'filter-builder', FilterBuilderField );
registerFieldType( 'async-multiselect', AsyncMultiselectField );
registerFieldType( 'multiselect', MultiselectField );

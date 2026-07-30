/**
 * <SchemaForm> — the schema-driven form renderer.
 *
 * Consumes a pure, JSON-serializable screen schema (see `schema-form/schemas/*`)
 * and renders it as sections of typed controls, resolving each control through
 * the field-type registry. It holds NO store state of its own: the owning tab
 * passes `values` and a single `onChange(fieldKey, value)` callback (wired to
 * `useSettings()` in Increment 1; to the store directly later).
 *
 * Schema contract:
 *   {
 *     label,                       // screen label (unused here; used by nav)
 *     sections: [
 *       {
 *         title?,                  // optional section heading
 *         description?,            // optional section description
 *         fields: {                // ordered map of fieldKey -> FieldDef
 *           [fieldKey]: {
 *             type,                // registry key, e.g. 'text' | 'select'
 *             label, help?,        // presentation
 *             default?,           // applied when value is absent
 *             options?,           // for select/radio/etc.
 *             show_if?,           // { field: 'dot.path', is: value | value[] } conditional
 *                                 //   - `is` scalar → strict equality
 *                                 //   - `is` array  → in-list match (value is one of)
 *             ...typeSpecific
 *           }
 *         }
 *       }
 *     ]
 *   }
 */

import { getFieldType } from './registry';

/**
 * Read a possibly-dotted path out of a flat/nested values object.
 *
 * @param {Object} values The current values.
 * @param {string} path   Field key or dot path (e.g. `date_range.mode`).
 * @return {*} The resolved value, or undefined.
 */
function getByPath( values, path ) {
	if ( ! path ) {
		return undefined;
	}
	return path.split( '.' ).reduce( ( acc, key ) => acc?.[ key ], values );
}

/**
 * Evaluate a field's `show_if` conditional against the current values.
 *
 * `is` may be a single value (strict equality) or an array (in-list match: the
 * field renders when the current value is one of the listed values).
 *
 * @param {Object} showIf The `{ field, is }` descriptor (or undefined).
 * @param {Object} values The current values.
 * @return {boolean} Whether the field should render.
 */
function isVisible( showIf, values ) {
	if ( ! showIf ) {
		return true;
	}
	const current = getByPath( values, showIf.field );
	if ( Array.isArray( showIf.is ) ) {
		return showIf.is.includes( current );
	}
	return current === showIf.is;
}

// Dev-facing error box for an unknown field type. Renders instead of crashing.
function UnknownFieldType( { type, fieldKey } ) {
	return (
		<div
			className="cps-schema-form__error"
			role="alert"
			style={ {
				border: '1px solid #cc1818',
				background: '#f7e4e4',
				color: '#8a1f1f',
				padding: '8px 12px',
				borderRadius: '4px',
				fontFamily: 'monospace',
				fontSize: '13px',
			} }
		>
			{ `SchemaForm: no field type registered for "${ type }" (field "${ fieldKey }").` }
		</div>
	);
}

function Field( { fieldKey, field, value, onChange, disabled } ) {
	const Component = getFieldType( field.type );

	if ( ! Component ) {
		return <UnknownFieldType type={ field.type } fieldKey={ fieldKey } />;
	}

	// A field can declare its own `disabled` in the schema (e.g. a sync toggle whose
	// companion plugin is inactive). Merge it with the form-level `disabled` here, in
	// the wrapper, so every registry component gets per-field disabling for free.
	const effectiveDisabled = disabled || !! field.disabled;

	return (
		<div className="cps-schema-form__field">
			<Component
				field={ field }
				value={ value }
				onChange={ ( next ) => onChange( fieldKey, next ) }
				disabled={ effectiveDisabled }
			/>
		</div>
	);
}

function Section( { section, values, onChange, disabled } ) {
	const fields = section.fields || {};

	return (
		<div className="cps-schema-form__section">
			{ section.title && (
				<h3 className="cps-schema-form__section-title">
					{ section.title }
				</h3>
			) }
			{ section.description && (
				<p className="cps-schema-form__section-description">
					{ section.description }
				</p>
			) }
			{ Object.keys( fields ).map( ( fieldKey ) => {
				const field = fields[ fieldKey ];

				if ( ! isVisible( field.show_if, values ) ) {
					return null;
				}

				const raw = values ? values[ fieldKey ] : undefined;
				const value = raw === undefined ? field.default : raw;

				return (
					<Field
						key={ fieldKey }
						fieldKey={ fieldKey }
						field={ field }
						value={ value }
						onChange={ onChange }
						disabled={ disabled }
					/>
				);
			} ) }
		</div>
	);
}

/**
 * @param {Object}   props
 * @param {Object}   props.schema     The screen schema.
 * @param {Object}   props.values     The current values (flat map of fieldKey -> value).
 * @param {Function} props.onChange   (fieldKey, value) => void
 * @param {boolean}  [props.disabled]
 */
export default function SchemaForm( {
	schema,
	values = {},
	onChange,
	disabled = false,
} ) {
	if ( ! schema || ! Array.isArray( schema.sections ) ) {
		return null;
	}

	return (
		<div className="cps-schema-form">
			{ schema.sections.map( ( section, index ) => (
				<Section
					key={ index }
					section={ section }
					values={ values }
					onChange={ onChange }
					disabled={ disabled }
				/>
			) ) }
		</div>
	);
}

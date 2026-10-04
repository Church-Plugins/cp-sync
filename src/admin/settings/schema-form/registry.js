/**
 * Field-type registry for the schema-driven form renderer.
 *
 * A field "type" (a string in the JSON schema, e.g. `text`, `select`) maps to a
 * React component that knows how to render that control. Components are looked up
 * at render time by `<SchemaForm>`, so new control types are added by calling
 * `registerFieldType()` once at module load — never by editing the renderer.
 *
 * Uniform prop contract every field component receives:
 *   {
 *     field,     // the FieldDef from the schema (label, help, options, ...)
 *     value,     // current value for this field (default already applied)
 *     onChange,  // (nextValue) => void  — write this field's value
 *     disabled,  // boolean
 *   }
 */

const registry = {};

/**
 * Register (or override) the component used to render a schema field `type`.
 *
 * @param {string}   type      The schema field type, e.g. 'text'.
 * @param {Function} Component A React component matching the field prop contract.
 */
export function registerFieldType( type, Component ) {
	registry[ type ] = Component;
}

/**
 * Look up the component for a field type. Returns `undefined` for unknown types
 * so the renderer can show a dev-facing error instead of crashing.
 *
 * @param {string} type The schema field type.
 * @return {Function|undefined} The registered component, if any.
 */
export function getFieldType( type ) {
	return registry[ type ];
}

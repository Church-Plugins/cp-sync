/**
 * Built-in field-type components for the schema form.
 *
 * Each maps a schema `type` to an `@wordpress/components` control and adapts it
 * to the uniform field prop contract ({ field, value, onChange, disabled }).
 * These are registered as a side effect of importing this module (done by
 * `schema-form/index.js`), so importing the module wires up the base types.
 */

import {
	TextControl,
	SelectControl,
	RadioControl,
	ToggleControl,
	CheckboxControl,
} from '@wordpress/components';
import { registerFieldType } from './registry';

/**
 * Normalize a FieldDef `options` array into the `{ label, value }` shape the
 * `@wordpress/components` selection controls expect. Accepts either that shape
 * already, or a bare array of strings.
 *
 * @param {Array} options Raw options from the schema.
 * @return {Array} Normalized `{ label, value }` options.
 */
const normalizeOptions = ( options = [] ) =>
	options.map( ( opt ) =>
		typeof opt === 'object' && opt !== null
			? { label: opt.label ?? String( opt.value ), value: opt.value }
			: { label: String( opt ), value: opt }
	);

function TextField( { field, value, onChange, disabled } ) {
	return (
		<TextControl
			label={ field.label }
			help={ field.help }
			type={ field.inputType || 'text' }
			value={ value ?? '' }
			onChange={ onChange }
			disabled={ disabled }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
}

function SelectField( { field, value, onChange, disabled } ) {
	return (
		<SelectControl
			label={ field.label }
			help={ field.help }
			value={ value }
			options={ normalizeOptions( field.options ) }
			onChange={ onChange }
			disabled={ disabled }
			__nextHasNoMarginBottom
			__next40pxDefaultSize
		/>
	);
}

function RadioField( { field, value, onChange, disabled } ) {
	// Radio values are inherently strings in the DOM; coerce the selected value
	// so a numeric stored value (e.g. debugMode `0`) still matches option `'0'`.
	return (
		<RadioControl
			label={ field.label }
			help={ field.help }
			selected={
				value === undefined || value === null ? '' : String( value )
			}
			options={ normalizeOptions( field.options ) }
			onChange={ onChange }
			disabled={ disabled }
		/>
	);
}

function ToggleField( { field, value, onChange, disabled } ) {
	return (
		<ToggleControl
			label={ field.label }
			help={ field.help }
			checked={ !! value }
			onChange={ onChange }
			disabled={ disabled }
			__nextHasNoMarginBottom
		/>
	);
}

function CheckboxField( { field, value, onChange, disabled } ) {
	return (
		<CheckboxControl
			label={ field.label }
			help={ field.help }
			checked={ !! value }
			onChange={ onChange }
			disabled={ disabled }
			__nextHasNoMarginBottom
		/>
	);
}

registerFieldType( 'text', TextField );
registerFieldType( 'select', SelectField );
registerFieldType( 'radio', RadioField );
registerFieldType( 'toggle', ToggleField );
registerFieldType( 'checkbox', CheckboxField );

import { FormTokenField } from '@wordpress/components';

/**
 * MultiTokenField
 *
 * A thin wrapper over `@wordpress/components` `FormTokenField` that preserves an
 * object-shaped STORED value (an array of option objects such as
 * `{ id, name }` or `{ value, label }`) while speaking the string tokens that
 * `FormTokenField` requires.
 *
 * The stored value shape is never flattened: tokens are derived from the label
 * key for display, and on change each token string is translated back to the
 * matching option object (or the already-selected item, so tokens survive while
 * async options are still loading). Unmatched free-text tokens are dropped,
 * mirroring the old MUI Autocomplete (no `freeSolo`).
 *
 * @param {Object}   props
 * @param {string}   [props.label]
 * @param {string}   [props.help]
 * @param {Array}    [props.value]     Stored value: array of option objects.
 * @param {Array}    [props.options]   Available options (same object shape).
 * @param {Function} props.onChange    Receives the updated array of option objects.
 * @param {boolean}  [props.disabled]
 * @param {string}   [props.valueKey]  Identity key on each option (default `value`).
 * @param {string}   [props.labelKey]  Display key on each option (default `label`).
 * @param {string}   [props.className]
 * @return {JSX.Element} The token field.
 */
export default function MultiTokenField( {
	label,
	help,
	value = [],
	options = [],
	onChange,
	disabled,
	valueKey = 'value',
	labelKey = 'label',
	className,
} ) {
	const selected = Array.isArray( value ) ? value : [];
	const opts = Array.isArray( options ) ? options : [];

	const labelOf = ( item ) => String( item?.[ labelKey ] ?? '' );
	const idOf = ( item ) => item?.[ valueKey ];

	// Tokens rendered come from the STORED value, so they display even before the
	// (possibly async) option list has resolved.
	const tokens = selected.map( labelOf );
	const suggestions = opts.map( labelOf );

	const handleChange = ( nextTokens ) => {
		const resolved = [];
		const seen = new Set();

		nextTokens.forEach( ( token ) => {
			// FormTokenField hands back strings for typed/selected tokens, but may
			// echo objects for tokens it was given as objects — handle both.
			const asLabel =
				token && typeof token === 'object'
					? labelOf( token )
					: String( token );

			const match =
				opts.find( ( o ) => labelOf( o ) === asLabel ) ||
				selected.find( ( s ) => labelOf( s ) === asLabel );

			if ( ! match ) {
				return; // drop free text with no matching option
			}

			const key = idOf( match );
			if ( seen.has( key ) ) {
				return; // dedupe by identity key
			}
			seen.add( key );
			resolved.push( match );
		} );

		onChange( resolved );
	};

	return (
		<div className={ className }>
			<FormTokenField
				label={ label }
				value={ tokens }
				suggestions={ suggestions }
				onChange={ handleChange }
				disabled={ disabled }
				__experimentalExpandOnFocus
				__nextHasNoMarginBottom
			/>
			{ help && <p className="cps-field__help">{ help }</p> }
		</div>
	);
}

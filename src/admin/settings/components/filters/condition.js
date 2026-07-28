import { useEffect } from '@wordpress/element';
import {
	Button,
	SelectControl,
	TextControl,
	Dropdown,
	DateTimePicker,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import useFilters from './useFilters';
import MultiTokenField from '../multi-token-field';

/**
 * Sanitizes a value to be a number, specifically for an input[type="number"]
 *
 * @param {*} value The raw input value.
 * @return {number} The sanitized number.
 */
const numberUpdate = ( value ) => {
	if ( typeof value === 'string' ) {
		value = value.replace( /[^\d.]/g, '' ).replace( /^0(?!\.)+/, '' );
	}

	if ( isNaN( value ) ) {
		return 0;
	}

	return Number( value );
};

// The empty/default value to write when the compare option's underlying VALUE
// TYPE changes (so a stale value of the wrong shape isn't left behind).
const emptyValueForType = ( fieldType ) => {
	if ( fieldType === 'multi' ) {
		return [];
	}
	if ( fieldType === 'number' ) {
		return 0;
	}
	return '';
};

/**
 * React component for rendering a single condition.
 *
 * Ported off MUI / MUI-X to `@wordpress/components`. The STORED condition shape
 * is unchanged: `{ id, selector, compare, value, preFilters }`. In particular a
 * `date` value is still serialized as an integer Unix timestamp (seconds), and a
 * `multi` value is still an array of `{ value, label }` option objects.
 *
 * @param {Object}   props
 * @param {Object}   props.condition      - The current condition settings.
 * @param {Function} props.onChange       - The change handler.
 * @param {Function} props.onRemove       - The remove handler.
 * @param {Object}   props.filterConfig   - The global filter configuration.
 * @param {Array}    props.compareOptions - The possible comparison options.
 * @return {React.ReactElement} The condition row.
 */
export default function Condition( {
	condition = {},
	onChange,
	onRemove,
	filterConfig,
	compareOptions = [],
} ) {
	// Guard: `compareOptions` may be empty on first paint; never destructure
	// `compareOptions[0].value` directly (that crashed the old component).
	const defaultCompare = compareOptions[ 0 ]?.value;

	const {
		selector = Object.keys( filterConfig )[ 0 ],
		compare = defaultCompare,
		value = '',
		preFilters = {},
	} = condition;

	useEffect( () => {
		const populate = {};

		if ( ! condition.compare ) {
			populate.compare = defaultCompare;
		}

		if ( condition.value === undefined ) {
			populate.value = '';
		}

		if ( ! condition.selector ) {
			populate.selector = Object.keys( filterConfig )[ 0 ];
		}

		handleChange( populate ); // populate the condition with defaults
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const config = filterConfig[ selector ];

	const { options, type: filterType } = useFilters( config, preFilters );

	const { supports = [] } = config;

	/**
	 * Resolve the rendered value TYPE (text/number/date/select/multi/bool) for a
	 * given compare option value. Handles the `inherit` indirection (a compare of
	 * type `inherit` takes the filter's own type, falling back to the option's
	 * declared `default`).
	 *
	 * @param {string} compareValue The compare option key.
	 * @return {string} The resolved field type.
	 */
	const resolveValueType = ( compareValue ) => {
		const opt = compareOptions.find(
			( o ) => o.value === compareValue
		) || {
			type: 'text',
		};

		return opt.type === 'inherit' ? filterType || opt.default : opt.type;
	};

	const fieldType = resolveValueType( compare );

	const handleChange = ( newData ) => {
		if ( Object.keys( newData ).length === 0 ) {
			return; // prevent empty updates
		}

		onChange( {
			...condition,
			...newData,
		} );
	};

	const updateSelector = ( newSelector ) => {
		const updatedCondition = {
			...condition,
			selector: newSelector,
		};

		// reset preFilters when the selector changes
		delete updatedCondition.preFilters;

		onChange( updatedCondition );
	};

	const updateCompare = ( newCompare ) => {
		const updatedCondition = { compare: newCompare };

		// Only clear the value when the underlying VALUE TYPE actually changes.
		// (The old code compared a string against a useMemo OBJECT, so this reset
		// never behaved correctly — it cleared on every compare change.)
		const prevType = resolveValueType( compare );
		const nextType = resolveValueType( newCompare );

		if ( prevType !== nextType ) {
			updatedCondition.value = emptyValueForType( nextType );
		}

		handleChange( updatedCondition );
	};

	const selectorOptions = Object.keys( filterConfig ).map( ( key ) => ( {
		value: key,
		label: filterConfig[ key ].label,
	} ) );

	const compareControlOptions = compareOptions
		.filter( ( option ) =>
			supports.length ? supports.includes( option.value ) : true
		)
		.map( ( option ) => ( { value: option.value, label: option.label } ) );

	const dateLabel =
		typeof value === 'number'
			? new Date( value * 1000 ).toLocaleString()
			: __( 'Select date…', 'cp-sync' );

	return (
		<div className="cps-filters__condition">
			<SelectControl
				className="cps-filters__field"
				label={ __( 'Selector' ) }
				value={ selector }
				options={ selectorOptions }
				onChange={ updateSelector }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			{ Object.entries( config.preFilters || {} ).map(
				( [ key, preFilter ] ) => (
					<SelectControl
						key={ key }
						className="cps-filters__field"
						label={ preFilter.label }
						value={ preFilters[ key ] }
						options={ ( preFilter.options || [] ).map(
							( option ) => ( {
								value: option.value,
								label: option.label,
							} )
						) }
						onChange={ ( newValue ) =>
							handleChange( {
								preFilters: {
									...preFilters,
									[ key ]: newValue,
								},
							} )
						}
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				)
			) }
			<SelectControl
				className="cps-filters__field"
				label={ __( 'Compare' ) }
				value={ compare }
				options={ compareControlOptions }
				onChange={ updateCompare }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>
			{ fieldType === 'bool' ? null : fieldType === 'number' ? (
				<TextControl
					className="cps-filters__field"
					label={ __( 'Value' ) }
					type="number"
					value={ numberUpdate( value ).toString() }
					onChange={ ( val ) =>
						handleChange( {
							value: ! val ? 0 : numberUpdate( val ),
						} )
					}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			) : fieldType === 'text' ? (
				<TextControl
					className="cps-filters__field"
					label={ __( 'Value' ) }
					value={ value }
					onChange={ ( val ) => handleChange( { value: val } ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			) : fieldType === 'date' ? (
				<div className="cps-filters__field">
					<Dropdown
						renderToggle={ ( { isOpen, onToggle } ) => (
							<Button
								variant="secondary"
								onClick={ onToggle }
								aria-expanded={ isOpen }
								__next40pxDefaultSize
							>
								{ dateLabel }
							</Button>
						) }
						renderContent={ () => (
							<DateTimePicker
								currentDate={
									typeof value === 'number'
										? new Date( value * 1000 )
										: new Date()
								}
								onChange={ ( newDate ) =>
									handleChange( {
										value: Math.floor(
											new Date( newDate ).getTime() / 1000
										),
									} )
								}
							/>
						) }
					/>
				</div>
			) : fieldType === 'select' ? (
				<SelectControl
					className="cps-filters__field"
					label={ __( 'Value' ) }
					value={ value }
					options={ ( options || [] ).map( ( option ) => ( {
						value: option.value,
						label: option.label,
					} ) ) }
					onChange={ ( val ) => handleChange( { value: val } ) }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			) : fieldType === 'multi' ? (
				<MultiTokenField
					className="cps-filters__field"
					label={ __( 'Value' ) }
					value={ value || [] }
					options={ options || [] }
					onChange={ ( val ) => handleChange( { value: val } ) }
					valueKey="value"
					labelKey="label"
				/>
			) : null }
			<Button
				className="cps-filters__remove"
				icon="trash"
				label={ __( 'Remove' ) }
				onClick={ onRemove }
			/>
		</div>
	);
}

import { Button, SelectControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import Condition from './condition';
import './index.scss';

import { useSettings } from '../../contexts/settingsContext';

/**
 * @typedef {Object} FilterData
 * @property {string}       type    - The type of the filter, e.g. 'select', 'text'
 * @property {Array|false}  options - The options for the filter
 * @property {boolean}      loading - Whether the options are loading
 */

// The possible condition compounding types
const MATCH_TYPE_OPTIONS = [
	{ value: 'all', label: __( 'All' ) },
	{ value: 'any', label: __( 'Any' ) },
];

// Robust unique id for a condition. Prefer crypto.randomUUID; fall back to a
// monotonic counter so collisions can't happen even without the crypto API
// (the old `Math.random().toString(36).substring(7)` was collision-prone).
let conditionIdCounter = 0;
const uniqueConditionId = () => {
	if (
		typeof crypto !== 'undefined' &&
		typeof crypto.randomUUID === 'function'
	) {
		return crypto.randomUUID();
	}
	conditionIdCounter += 1;
	return `cond_${ Date.now().toString( 36 ) }_${ conditionIdCounter }`;
};

/**
 * Filters component
 *
 * @param {Object}   props
 * @param {string}   props.label       - The label for the filter
 * @param {string}   props.filterGroup - The filter group to use, e.g. 'groups' or 'events'
 * @param {Object}   props.filter      - The current filter settings ({ type, conditions })
 * @param {Function} props.onChange    - The change handler (receives the whole filter object)
 * @return {React.ReactElement} The filter builder.
 */
function Filters( { label, filterGroup, filter, onChange = () => {} } ) {
	const { getFilterConfig, compareOptions } = useSettings();

	const filterConfig = getFilterConfig( filterGroup );

	if ( ! filterConfig ) {
		return <div>{ __( 'Loading filters…', 'cp-sync' ) }</div>;
	}

	const { conditions = [], type = 'all' } = filter || {};

	const handleChange = ( newData ) => {
		onChange( {
			...filter,
			...newData,
		} );
	};

	const handleConditionChange = ( id, condition ) => {
		handleChange( {
			conditions: conditions.map( ( c ) =>
				c.id === id ? condition : c
			),
		} );
	};

	const handleConditionRemove = ( id ) => {
		handleChange( {
			conditions: conditions.filter( ( c ) => c.id !== id ),
		} );
	};

	const handleConditionAdd = () => {
		handleChange( {
			conditions: [
				...conditions,
				{
					id: uniqueConditionId(),
					selector: Object.keys( filterConfig )[ 0 ],
				},
			],
		} );
	};

	const visibleConditions = conditions.filter(
		( condition ) => condition.selector in filterConfig
	);

	return (
		<div className="cps-filters">
			<div className="cps-filters__match">
				<span>{ sprintf( __( 'Pull %s where' ), label ) }</span>
				<SelectControl
					value={ type }
					options={ MATCH_TYPE_OPTIONS }
					onChange={ ( newType ) =>
						handleChange( { type: newType } )
					}
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<span>{ __( 'of the following match' ) }</span>
			</div>
			{ visibleConditions.map( ( condition ) => (
				<Condition
					key={ condition.id }
					filterConfig={ filterConfig }
					condition={ condition }
					onChange={ ( newFilter ) =>
						handleConditionChange( condition.id, newFilter )
					}
					onRemove={ () => handleConditionRemove( condition.id ) }
					compareOptions={ compareOptions }
				/>
			) ) }
			<div className="cps-filters__add">
				<Button
					variant="secondary"
					onClick={ () => handleConditionAdd() }
				>
					{ __( 'Add Condition' ) }
				</Button>
			</div>
		</div>
	);
}

export default Filters;

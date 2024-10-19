import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import FormControl from '@mui/material/FormControl';
import MenuItem from '@mui/material/MenuItem';
import Select from '@mui/material/Select';
import Typography from '@mui/material/Typography';
import { __ } from '@wordpress/i18n';
import Condition from './condition';

import { useSettings } from '../../contexts/settingsContext';

/**
 * @typedef {Object} FilterData
 * @property {string} type - The type of the filter, e.g. 'select', 'text'
 * @property {Array|false} options - The options for the filter
 * @property {boolean} loading - Whether the options are loading
 */

// The possible condition compounding types
const MATCH_TYPE_OPTIONS = [
	{ value: 'all', label: __( 'All' ) },
	{ value: 'any', label: __( 'Any' ) },
]

/**
 * Filters component
 *
 * @param {Object} props
 * @param {string} props.label - The label for the filter
 * @param {string} props.filterGroup - The filter group to use, e.g. 'groups' or 'events'
 * @param {Object} props.filter - The current filter settings
 * @param {Function} props.onChange - The change handler
 * @returns {React.ReactElement}
 */
function Filters({ label, filterGroup, filter, onChange = () => {},  }) {
	const { getFilterConfig, compareOptions } = useSettings();

	const filterConfig = getFilterConfig(filterGroup);

	if (!filterConfig) {
		return null;
	}

	const { conditions = [], type = 'all' } = filter

	const handleChange = (newData) => {
		onChange({
			...filter,
			...newData
		})
	}

	const handleConditionChange = (id, condition) => {
		handleChange({ conditions: conditions.map((c) => c.id === id ? condition : c) })
	}

	const handleConditionRemove = (id) => {
		handleChange({ conditions: conditions.filter((c) => c.id !== id) })
	}

	const handleConditionAdd = () => {
		handleChange({
			conditions: [
				...conditions,
				{
					id: Math.random().toString(36).substring(7),
					selector: Object.keys(filterConfig)[0],
				}
			]
		})
	}

	const visibleConditions = conditions.filter(condition => condition.selector in filterConfig)

	return (
		<div>
		<Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mt: 2 }}>
			<Typography>{ sprintf( __( 'Pull %s where' ), label ) }</Typography>
			<FormControl>
				<Select
					size="small"
					value={type}
					onChange={(e) => handleChange({ type: e.target.value })}
					sx={{ width: 150 }}
				>
					{MATCH_TYPE_OPTIONS.map(option => (
						<MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
					))}
				</Select>
			</FormControl>
			<Typography>{ __( 'of the following match' ) }</Typography>		
		</Box>
		{
			visibleConditions.map((condition) => (
				<Condition
					key={condition.id}
					filterConfig={filterConfig}
					condition={condition}
					onChange={(newFilter) => handleConditionChange(condition.id, newFilter)}
					onRemove={() => handleConditionRemove(condition.id)}
					compareOptions={compareOptions}
				/>
			))
		}
		<Button variant="outlined" onClick={() => handleConditionAdd()} sx={{ mt: 2 }}>{ __( 'Add Condition' ) }</Button>
		</div>
	)
}

export default Filters;

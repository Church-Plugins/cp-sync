import Autocomplete from '@mui/material/Autocomplete';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import { DateTimePicker } from '@mui/x-date-pickers/DateTimePicker';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import FormControl from '@mui/material/FormControl';
import IconButton from '@mui/material/IconButton';
import InputLabel from '@mui/material/InputLabel';
import MenuItem from '@mui/material/MenuItem';
import Select from '@mui/material/Select';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { __ } from '@wordpress/i18n';
import AddIcon from '@mui/icons-material/Add';
import RemoveIcon from '@mui/icons-material/Remove';
import { useSelect } from '@wordpress/data';
import { useMemo } from '@wordpress/element';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider/LocalizationProvider';
import dayjs from 'dayjs';
import { useEffect } from '@wordpress/element';

const MATCH_TYPE_OPTIONS = [
	{ value: 'all', label: __( 'All' ) },
	{ value: 'any', label: __( 'Any' ) },
]

/**
 * Sanitizes a value to be a number, specifically for an input[type="number"]
 *
 * @param {*} value 
 * @return {number}
 */
const numberUpdate = (value) => {
	if(typeof value === 'string') {
		value = value.replace(/[^\d\.]/g, '').replace(/^0(?!\.)+/, '')
	}

	if (isNaN(value)) {
		return 0
	}

	return Number(value)
}

const useFilters = (filter, currentPreFilters) => {
	const { options, loading } = useSelect((select) => {
		if(filter.options) { // if options are provided directly, use them
			return { options: filter.options, loading: false }
		}

		if(filter.optionsSelector) {
			const { args, store, selector, format } = filter.optionsSelector(currentPreFilters)

			return {
				options: format(select(store)[selector](...args) || []),
				loading: select(store).getIsResolving(selector, ...args)
			}
		}

		return { options: false, loading: false }
	}, [currentPreFilters])

	return { options, loading, type: filter.type }
}

function Condition({
	condition = {},
	onChange,
	onAdd,
	onRemove,
	filterConfig,
	compareOptions = [],
}) {
	const {
		type = Object.keys(filterConfig)[0],
		compare = compareOptions[0].value,
		value = '',
		preFilters = {},
	} = condition

	useEffect(() => {
		const populate = {}

		if(!condition.compare) {
			populate.compare = compareOptions[0].value
		}

		if(condition.value === undefined) {
			populate.value = ''
		}

		if(!condition.type) {
			populate.type = Object.keys(filterConfig)[0]
		}

		handleChange(populate) // populate the condition with defaults
	}, [])

	const config = filterConfig[type || Object.keys(filterConfig)[0]]

	const { options, type: filterType } = useFilters(config, preFilters)

	const valueType = useMemo(() => {
		return compareOptions.find(({ value }) => value === compare)?.type || 'string'
	}, [compare])

	const handleChange = (newData) => {
		if(Object.keys(newData).length === 0) return; // prevent empty updates

		onChange({
			...condition,
			...newData
		})
	}

	const updateType = (newType) => {
		// reset preFilters when the type changes
		const updatedCondition = {
			...condition,
			type: newType,
		}

		delete updatedCondition.preFilters

		onChange(updatedCondition)
	}

	const updateCompare = (newCompare) => {
		const updatedCondition = {
			compare: newCompare
		}

		const newData = compareOptions.find(({ value }) => value === newCompare)

		const { type = 'select' } =  newData

		if (type !== valueType) {
			updatedCondition.value = '' // clear the value when the comparison changes
		}

		handleChange(updatedCondition)
	}

	const fieldType = (
		valueType === 'multi' ? 'multi' :
		valueType === 'inherit' ?
		filterType :
		valueType
	)

	return (
		<Box sx={{ display: 'flex', alignItems: 'center', gap: 2, mt: 2 }}>
			<FormControl>
				<InputLabel id="filter-type-label">{ __( 'Filter Type' ) }</InputLabel>
				<Select
					value={type}
					onChange={(e) => updateType(e.target.value)}
					sx={{ width: 200 }}
					defaultValue={Object.keys(filterConfig)[0]}
				>
					{Object.keys(filterConfig).map(key => (
						<MenuItem key={key} value={key}>{filterConfig[key].label}</MenuItem>
					))}
				</Select>
			</FormControl>
			{
				Object.entries(config.preFilters || {}).map(([key, preFilter]) => (
					<FormControl key={key}>
						<InputLabel id={`filter-sub-option-label-${key}`}>{ preFilter.label }</InputLabel>
						<Select
							value={preFilters[key]}
							onChange={(e) => handleChange({ preFilters: { ...preFilters, [key]: e.target.value } })}
							sx={{ width: 200 }}
						>
							{preFilter.options.map(option => (
								<MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
							))}
						</Select>
					</FormControl>
				))
			}
			<FormControl>
				<InputLabel id="filter-compare-label">{ __( 'Compare' ) }</InputLabel>
				<Select
					value={compare}
					onChange={(e) => updateCompare(e.target.value)}
					sx={{ width: 200 }}
					defaultValue='is'
				>
					{compareOptions.map(option => (
						<MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
					))}
				</Select>
			</FormControl>
			{
				fieldType === 'bool' ?
				null : // no value input
				fieldType === 'number' ?
				<TextField
					value={numberUpdate(value).toString()}
					onChange={(e) => handleChange({ value: !e.target.value ? 0 : numberUpdate(e.target.value) })}
					sx={{
						width: 200,
						height: '56px',
						display: 'flex',
						alignSelf: 'stretch',
						'& .MuiOutlinedInput-root, & input': {
							height: '100%',
							border: 'none'
						},
					}}
					type="number"
				/> :
				fieldType === 'text' ?
				<TextField
					label={__( 'Value' )}
					value={value}
					onChange={(e) => handleChange({ value: e.target.value })}
					sx={{ width: 200 }}
				/> :
				fieldType === 'date' ?
				<LocalizationProvider dateAdapter={AdapterDayjs}>
					<DateTimePicker
						label={__( 'Value' )}
						value={value ? dayjs(value) : dayjs()}
						onChange={(newValue) => handleChange({ value: dayjs(newValue).toISOString() })}
						viewRenderers={{
							hours: null,
							minutes: null,
							seconds: null,
						}}
					/>
				</LocalizationProvider> :
				fieldType === 'select' ?
				<FormControl>
					<InputLabel id="filter-value-label">{ __( 'Value' ) }</InputLabel>
					<Select
						value={value}
						onChange={(e) => handleChange({ value: e.target.value })}
						sx={{ width: 200 }}
						defaultValue='is'
					>
						{options.map(option => (
							<MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
						))}
					</Select>
				</FormControl> :
				fieldType === 'multi' ?
				<Autocomplete
					value={value}
					onChange={(e, newValue) => handleChange({ value: newValue })}
					sx={{ width: 200 }}
					multiple
					options={options || []}
					getOptionLabel={(option) => option.label}
					renderInput={(params) => <TextField {...params} label={__( 'Value' )} />}
					isOptionEqualToValue={(option, value) => (
						!options ? true : option.value === value.value
					)}
				/> :
				null
			}
			<IconButton aria-label="add" onClick={onAdd}>
				<AddIcon />
			</IconButton>
			<IconButton aria-label="remove" onClick={onRemove}>
				<RemoveIcon />
			</IconButton>
		</Box>
	)
}

/**
 * @param {
 * 		filterConfig: {*}
 * 		filter: {*}
 * 		onChange: Function
 * } props
 */
function Filters({ filterConfig, filter, compareOptions, onChange = () => {} }) {
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

	const handleConditionAdd = (id) => {
		const index = conditions.findIndex((c) => c.id === id)
		const newConditions = [...conditions]
		newConditions.splice(index + 1, 0, { id: Math.random().toString(36).substring(7) })
		handleChange({ conditions: newConditions })
	}

	return (
		<div>
		<Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mt: 2 }}>
			<Typography>{ sprintf( __( 'Pull %s where' ), filterConfig.label ) }</Typography>
			<FormControl>
				<InputLabel id="match-type-label">{ __( 'Match Type' ) }</InputLabel>
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
			conditions.map((condition, index) => (
				<Condition
					key={condition.id}
					filterConfig={filterConfig}
					condition={condition}
					onChange={(newFilter) => handleConditionChange(condition.id, newFilter)}
					onRemove={() => handleConditionRemove(condition.id)}
					onAdd={() => handleConditionAdd(condition.id)}
					compareOptions={compareOptions}
				/>
			))
		}
		{
			!conditions.length &&
			<Button variant="outlined" onClick={() => handleConditionAdd(-1)} sx={{ mt: 2 }}>{ __( 'Add Condition' ) }</Button>
		}
		</div>
	)
}

export default Filters;
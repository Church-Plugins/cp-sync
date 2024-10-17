import { useEffect, useMemo } from '@wordpress/element'
import Autocomplete from '@mui/material/Autocomplete'
import Box from '@mui/material/Box'
import FormControl from '@mui/material/FormControl'
import InputLabel from '@mui/material/InputLabel'
import Select from '@mui/material/Select'
import MenuItem from '@mui/material/MenuItem'
import TextField from '@mui/material/TextField'
import IconButton from '@mui/material/IconButton'
import RemoveIcon from '@mui/icons-material/Remove'
import { __ } from '@wordpress/i18n'
import useFilters from './useFilters'
import dayjs from 'dayjs'
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider/LocalizationProvider';
import { DateTimePicker } from '@mui/x-date-pickers/DateTimePicker';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';

/**
 * Sanitizes a value to be a number, specifically for an input[type="number"]
 *
 * @param {any} value 
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

/**
 * React component for rendering a single condition
 *
 * @param {Object} props
 * @param {Object} props.condition - The current condition settings
 * @param {Function} props.onChange - The change handler
 * @param {Function} props.onAdd - The add handler
 * @param {Function} props.onRemove - The remove handler
 * @param {Object} props.filterConfig - The global filter configuration
 * @param {Array} props.compareOptions - The possible comparison options
 * @returns {React.ReactElement}
 */
export default function Condition({
	condition = {},
	onChange,
	onAdd,
	onRemove,
	filterConfig,
	compareOptions = [],
}) {
	const {
		selector   = Object.keys(filterConfig)[0],
		compare    = compareOptions[0].value,
		value      = '',
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

		if(!condition.selector) {
			populate.selector = Object.keys(filterConfig)[0]
		}

		handleChange(populate) // populate the condition with defaults
	}, [])

	const config = filterConfig[selector]
	
	const { options, type: filterType } = useFilters(config, preFilters)

	const { supports = [] } = config

	const valueType = useMemo(() => {
		const typeConfig = compareOptions.find(({ value }) => value === compare)

		if(typeConfig) {
			return typeConfig
		}else {
			return { type: 'text' }
		}
	}, [compare])

	const handleChange = (newData) => {
		if(Object.keys(newData).length === 0) return; // prevent empty updates

		onChange({
			...condition,
			...newData
		})
	}

	const updateSelector = (newSelector) => {
		const updatedCondition = {
			...condition,
			selector: newSelector,
		}

		// reset preFilters when the selector changes
		delete updatedCondition.preFilters

		onChange(updatedCondition)
	}

	const updateCompare = (newCompare) => {
		const updatedCondition = {
			compare: newCompare
		}

		const newData = compareOptions.find(({ value }) => value === newCompare)

		const { type = 'select' } = newData

		if (type !== valueType) {
			updatedCondition.value = '' // clear the value when the comparison changes
		}

		handleChange(updatedCondition)
	}

	const fieldType = (
		valueType.type === 'inherit' ?
		(filterType || valueType.default) :
		valueType.type
	)

	return (
		<Box sx={{ display: 'flex', alignItems: 'center', gap: 2, mt: 2 }}>
			<FormControl>
				<InputLabel id="filter-selector-label" htmlFor="filter-selector">{ __( 'Selector' ) }</InputLabel>
				<Select
					value={selector}
					onChange={(e) => updateSelector(e.target.value)}
					sx={{ width: 200 }}
					defaultValue={Object.keys(filterConfig)[0]}
					id="filter-selector"
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
					{compareOptions.filter(option => supports.length ? supports.includes(option.value) : true).map(option => (
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
						{(options || []).map(option => (
							<MenuItem key={option.value} value={option.value}>{option.label}</MenuItem>
						))}
					</Select>
				</FormControl> :
				fieldType === 'multi' ?
				<Autocomplete
					value={value || []}
					onChange={(e, newValue) => handleChange({ value: newValue })}
					sx={{ width: 200 }}
					multiple
					options={options || []}
					getOptionLabel={(option) => option.label}
					renderInput={(params) => <TextField {...params} label={__( 'Value' )} />}
					isOptionEqualToValue={(option, item) => (
						!options ? true : option.value === item.value
					)}
				/> :
				null
			}
			<IconButton aria-label="remove" onClick={onRemove}>
				<RemoveIcon />
			</IconButton>
		</Box>
	)
}

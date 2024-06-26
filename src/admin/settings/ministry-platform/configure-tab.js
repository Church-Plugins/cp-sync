import Alert from '@mui/material/Alert';
import Autocomplete from '@mui/material/Autocomplete';
import Chip from '@mui/material/Chip';
import TextField from '@mui/material/TextField';
import FormControl from '@mui/material/FormControl';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Button from '@mui/material/Button';
import { __, sprintf } from '@wordpress/i18n';
import { useState, useMemo, useEffect } from '@wordpress/element';
import useApi from './useApi';
import apiFetch from '@wordpress/api-fetch';
import { debounce, useDebounce } from '@wordpress/compose';
import Check from '@mui/icons-material/Check';
import Error from '@mui/icons-material/Error';
import CircularProgress from '@mui/material/CircularProgress';
import { lighten } from '@mui/system/colorManipulator';
import { styled } from '@mui/system';
import { useTheme } from '@mui/material/styles';

const labels = {
	'chms_id':             __( 'Group ID', 'cp-sync' ),
	'post_title':          __( 'Group Name', 'cp-sync' ),
	'post_content':        __( 'Description', 'cp-sync' ),
	'leader':              __( 'Group Leader', 'cp-sync' ),
	'start_date':          __( 'Start Date', 'cp-sync' ),
	'end_date':            __( 'End Date', 'cp-sync' ),
	'thumbnail_url':       __( 'Image ID', 'cp-sync' ),
	'frequency':           __( 'Meeting Frequency', 'cp-sync' ),
	'city':					       __( 'City', 'cp-sync' ),
	'state_or_region':     __( 'State/Region', 'cp-sync' ),
	'postal_code':         __( 'Postal Code', 'cp-sync' ),
	'meeting_time':        __( 'Meeting Time', 'cp-sync' ),
	'meeting_day':         __( 'Meeting Day', 'cp-sync' ),
	'cp_location':         __( 'Group Campus', 'cp-sync' ),
	'group_category':      __( 'Group Focus', 'cp-sync' ),
	'group_type':          __( 'Group Type', 'cp-sync' ),
	'group_life_stage':    __( 'Group Life Stage', 'cp-sync' ),
	'kid_friendly':        __( 'Kid Friendly', 'cp-sync' ),
	'handicap_accessible': __( 'Accessible', 'cp-sync' ),
	'virtual':             __( 'Virtual', 'cp-sync' ),
}

function MPFields({ data, updateField, usedFields }) {
	const theme = useTheme()
	const [fieldError, setFieldError] = useState(null)
	const [inputValue, setInputValue] = useState('')
	const [validCustomField, setValidCustomField] = useState(null)
	const api = useApi()

	const { group_fields } = data

	const validateField = useMemo(() => {
		return debounce((value) => {
			if(!api) return
			setValidCustomField('loading')
			api.getGroups({
				top: 1,
				select: value
			}).then(() => {
				setValidCustomField('valid')
			}).catch(() => {
				setValidCustomField('invalid')
			})
		}, 500)
	}, [api])

	useEffect(() => {
		return () => validateField.cancel()
	}, [validateField])

	const testCurrentFields = () => {
		if(!api) return
		setFieldError(null)
		api.getGroups({
			top: 1,
			select: group_fields.join(',')
		}).then(groups => {
			const fields = Object.keys(groups[0])

			updateField('valid_fields', fields)
		}).catch((e) => {
			setFieldError(e.message)
		})
	}

	return (
		<>
			<Typography variant="h5">{ __( 'Fields', 'cp-sync' ) }</Typography>
			<Autocomplete
				multiple
				value={group_fields}
				onChange={(_, value) => {
					updateField('group_fields', value.map(v => typeof v === 'string' ? v : v.value))
				}}
				inputValue={inputValue}
				getOptionDisabled={(option) => validCustomField !== 'valid'}
				onInputChange={(e, value) => {
					// const options = value.split(',').map(v => v.trim()).filter(v => v.length)
					
					setInputValue(value)
					validateField(value)

					// if(options.length > 1 || value.endsWith(',')) {
					// 	updateField('group_fields', group_fields.concat(options))
					// 	setInputValue('')
					// } else {
					// 	setInputValue(value)
					// }
				}}
				options={[]}
				freeSolo
				selectOnFocus
				clearOnBlur
				handleHomeEndKeys
				renderTags={(value, getTagProps) => (
					value.map((option, index) => (
						<Chip
							label={typeof option === 'string' ? option : option.value}
							color={usedFields.includes(option) ? 'success' : 'default'}
							{...getTagProps({ index })}
						/>
					))
				)}
				renderOption={(props, option) => (
					<Box component="li" {...props} flex alignItems="center" gap={2}>
						{
							validCustomField === 'invalid' ?
							<Error color="error" /> :
							validCustomField === 'valid' ?
							<Check color="success" /> :
							validCustomField === 'loading' || !validCustomField ?
							<CircularProgress size={20} /> :
							null
						}

						{
							validCustomField === 'loading' || !validCustomField ?
							__( 'Checking field validity', 'cp-sync' ):
							validCustomField === 'invalid' ?
							/* translators: %s: field name */
							sprintf( __( 'Invalid field: "%s"', 'cp-sync' ), option.label ) :
							/* translators: %s: field name */
							sprintf( __( 'Add "%s" field', 'cp-sync' ), option.label )
						}
					</Box>
				)}
				getOptionLabel={(option) => (
					typeof option === 'string' ? option : option.label
				)}
				filterOptions={(_options, params) => (params.inputValue.length ? [
					{
						value: params.inputValue,
						label: params.inputValue
					}
				] : [])}
				renderInput={(params) => (
					<TextField {...params} label={__( 'Group Fields', 'cp-sync' )} />
				)}
				sx={{
					maxWidth: '1200px'
					
				}}
			/>
			{
				fieldError && <Alert severity="error">
					{__( 'There is an error with the fields you have selected.', 'cp-sync' )}
					<br />
					<br />
					<pre><code>{fieldError}</code></pre>
				</Alert>
			}
		</>
	)
}

export default function ConfigureTab({ data, updateField }) {
	const api = useApi()
	const [newLabel, setNewLabel] = useState('')
	const [newValue, setNewValue] = useState('')
	const [addingCustomField, setAddingCustomField] = useState(false)
	const [importStarted, setImportStarted] = useState(false)
	const [importError, setImportError] = useState(null)
	const [importPending, setImportPending] = useState(false)

	const {
		group_field_mapping,
		custom_group_field_mapping,
		group_fields,
		custom_fields,
		valid_fields = []
	} = data

	const allFields = [...group_fields, ...custom_fields]

	const updateMappingField = (key, value) => {
		const newMapping = { ...group_field_mapping }
		newMapping[key] = value

		updateField('group_field_mapping', newMapping)
	}

	const createCustomMappingField = (name, value) => {
		const slug = name.toLowerCase().replace(/[\s\-]/g, '_').replace(/[^\w]/g, '')
		const newMapping = { ...custom_group_field_mapping }
		newMapping[slug] = { name, value }
		updateField('custom_group_field_mapping', newMapping)
	}

	const updateCustomMappingField = (key, { name, value }) => {
		const newMapping = { ...custom_group_field_mapping }
		newMapping[key] = { name, value }
		updateField('custom_group_field_mapping', newMapping)
	}

	const startImport = () => {
		setImportPending(true)
		apiFetch({
			path: '/cp-sync/v1/ministry-platform/groups/pull',
			method: 'POST',
			data: {}
		}).then((response) => {
			setImportStarted(true)
		}).catch((e) => {
			setImportError(e.message)
		}).finally(() => {
			setImportPending(false)
		})
	}

	if (!api) {
		return (
			<Box>
				{__( 'Authenticating with Ministry Platform...', 'cp-sync' )}
			</Box>
		)
	}

	if(api && !api.isAuthenticated()) {
		return (
			<Alert severity="warning">
				{__( 'You need to authenticate with Ministry Platform to test fields.', 'cp-sync' )}
			</Alert>
		)
	}

	return (
		<Box display="flex" flexDirection="column" gap={2}>
			
			{importStarted && <Alert severity="success">{__( 'Import started', 'cp-sync' )}</Alert>}

			{importError && <Alert severity="error">{__( 'Error when starting import: ', 'cp-sync' )}{importError}</Alert>}

			<Button disabled={importStarted || importPending} variant="contained" color="secondary" sx={{ alignSelf: 'start' }} onClick={startImport}>
				{importPending ? __( 'Starting Import' ) : importStarted ? __( 'Import Started' ) : __( 'Start import', 'cp-sync' )}
			</Button>

			<MPFields data={data} updateField={updateField} usedFields={Object.values(data.group_field_mapping)} />

			<Typography variant="h5">{ __( 'Field mapping',	'cp-sync' ) }</Typography>

			<Alert severity='warning'>
				WARGNING
			</Alert>

			{Object.entries(labels).map(([key, label]) => (
				<FormControl key={key}>
					<Autocomplete
						value={data.group_field_mapping[key] || ''}
						onChange={(e, option) => {
							updateMappingField(key, option?.value || '')
						}}
						color="warning"
						sx={{ width: '300px', borderColor: "" }}
						options={[
							{ label: '--Ignore--', value: '' },
							...valid_fields.map((field) => ({ label: field, value: field }))
						]}
						renderInput={(params) => <TextField {...params} label={label} variant="outlined" color="warning" />}
						isOptionEqualToValue={(option, value) => option.value === value}
					/>
				</FormControl>
			))}

			<Typography variant="h5">{ __( 'Custom field mapping', 'cp-sync' ) }</Typography>
			{Object.entries(custom_group_field_mapping).map(([key, { name, value }]) => (
				<Box key={key} display="flex" gap={1} alignItems="center">
					<TextField
						label={__( 'Field Name', 'cp-sync' )}
						value={name}
						onChange={(e) => updateCustomMappingField(key, { name: e.target.value, value: value })}
						variant="outlined"
						sx={{ width: '300px' }}
					/>
					<Autocomplete
						value={value}
						onChange={(e, option) => updateCustomMappingField(key, { name, value: option?.value || '' })}
						sx={{ width: '300px' }}
						options={valid_fields.map((field) => ({ label: field, value: field }))}
						renderInput={(params) => <TextField {...params} label={__( 'Field', 'cp-sync' )} variant="outlined" />}
						isOptionEqualToValue={(option, value) => option.value === value}
					/>
				</Box>
			))}

			{addingCustomField && (
				<Box display="flex" gap={1} alignItems="center">
					<TextField
						label={__( 'Field Name', 'cp-sync' )}
						variant="outlined"
						value={newLabel}
						onChange={(e) => setNewLabel(e.target.value)}
					/>
					<FormControl>
						<Autocomplete
							value={newValue}
							onChange={(e, option) => setNewValue(option?.value || '')}
							sx={{ width: '300px' }}
							options={valid_fields.map((field) => ({ label: field, value: field }))}
							renderInput={(params) => <TextField {...params} label={__( 'Field', 'cp-sync' )} variant="outlined" />}
							isOptionEqualToValue={(option, value) => option.value === value}
						/>
					</FormControl>
					<Button
						onClick={() => {
							setAddingCustomField(false)
							createCustomMappingField(newLabel, newValue)
							setNewLabel('')
							setNewValue('')
						}}
					>
						{ __( 'Create', 'cp-sync' ) }
					</Button>
				</Box>
			)}

			<Button
				onClick={() => setAddingCustomField(!addingCustomField)}
				variant="outlined"
				sx={{ width: '200px' }}
			>
				{ __( 'Add Custom Field', 'cp-sync' ) }
			</Button>
		</Box>
	)
}

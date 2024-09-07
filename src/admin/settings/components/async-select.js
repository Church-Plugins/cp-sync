import Autocomplete from '@mui/material/Autocomplete';
import TextField from '@mui/material/TextField';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * AsyncSelect component.
 * 
 * A wrapper for the MUI multiselect Autocomplete component that fetches options from an API.
 * @param {Object} props
 * @param {string} props.apiPath the path to the API endpoint.
 * @param {Object} props.value the current value of the select.
 * @param {Function} props.onChange the function to call when the value changes.
 * @param {string} props.label the label for the select.
 *
 * @returns {JSX.Element}
 */
export default function AsyncSelect({ apiPath, value, onChange, label, ...props }) {
	const [data, setData] = useState([]);
	const [error, setError] = useState(null);
	const [loading, setLoading] = useState(true);

	useEffect(() => {
		setLoading(true);
		apiFetch({
			path: apiPath,
		}).then((response) => {
			if (response.success) {
				setData(response.data);
			} else {
				console.log('res error', response)
				setError(response.message);
			}
		}).catch((e) => {
			console.log('caught error', e)
			setError(e.message);
		}).finally(() => {
			setLoading(false);
		});
	}, [apiPath]);

	return (
		<Autocomplete
			value={value}
			onChange={(e, newValue) => onChange(newValue)}
			sx={{ width: 500 }}
			renderInput={(params) => (
				<TextField
					{...params}
					label={label}
					error={!!error}
					helperText={error ? JSON.stringify(error) : ''}
				/>
			)}
			loading={loading}
			multiple
			options={data}
			getOptionLabel={(option) => option.name}
			isOptionEqualToValue={(option, value) => loading || option.id === value.id}
			{...props}
		/>
	);
}

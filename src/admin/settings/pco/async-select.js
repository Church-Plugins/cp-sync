import Autocomplete from '@mui/material/Autocomplete';
import TextField from '@mui/material/TextField';
import { __ } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function AsyncSelect({ apiPath, value, onChange, label, ...props }) {
	const [data, setData] = useState([])
	const [error, setError] = useState(null)
	const [loading, setLoading] = useState(true)

	useEffect(() => {
		setLoading(true)
		apiFetch({
			path: apiPath,
		}).then(response => {
			if(response.success) {
				setData(response.data)
			} else {
				setError(response.message)
			}
		}).finally(() => {
			setLoading(false)
		})
	}, [])

	return (
		<Autocomplete
			value={value}
			onChange={(e, newValue) => onChange(newValue)}
			sx={{ width: 500 }}
			renderInput={(params) => (
				<TextField
					{...params}
					label={label}
				/>
			)}
			loading={loading}
			multiple
			options={data}
			getOptionLabel={(option) => option.name}
			isOptionEqualToValue={(option, value) => loading || option.id === value.id}
			{...props}
		/>
	)
}

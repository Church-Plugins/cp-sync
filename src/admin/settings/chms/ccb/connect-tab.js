import { __ } from '@wordpress/i18n'
import { useSettings } from '../../contexts/settingsContext'

export default function ConnectTab({ data, updateField }) {
	const { isConnected } = useSettings()

	return (
		<div>
			<h2>Connect to PCO</h2>
			<div>Connected: {isConnected.toString()}</div>
		</div>
	)
}


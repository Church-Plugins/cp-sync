import { useState, useEffect } from '@wordpress/element'
import { useSelect } from '@wordpress/data'
import Api from './api'
import settingsStore from '../settingsStore'

export default function useApi() {
	const [api, setApi] = useState(null)

	const { authConfig } = useSelect((select) => {
		return {
			authConfig: select(settingsStore).getOptionGroup('mp_connect')
		}
	}, [])

	const initializeApi = (authConfig) => {
		const apiInstance = new Api(authConfig)

		apiInstance.authenticate().finally(() => {
			setApi(apiInstance)
		})

		return apiInstance
	}

	useEffect(() => {
		if (authConfig && !api) {
			const api = initializeApi(authConfig)

			return () => {
				api.abort()
			}
		}
	}, [authConfig])

	return api
}
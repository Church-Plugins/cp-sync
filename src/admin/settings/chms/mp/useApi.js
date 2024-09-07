import { useState, useEffect } from '@wordpress/element'
import { useSelect } from '@wordpress/data'
import Api from './api'
import globalStore from '../../store/globalStore'

export default function useApi() {
	const [api, setApi] = useState(null)

	const { authConfig } = useSelect((select) => {
		return {
			authConfig: select(globalStore).getOptionGroup('mp_connect')
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
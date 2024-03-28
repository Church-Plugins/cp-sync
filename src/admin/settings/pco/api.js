import optionsStore from "../store";
import { useSelect } from "@wordpress/data";

class Api {
	base_url = 'https://api.planningcenteronline.com';

	constructor(app_id, secret) {
		this.app_id = app_id
		this.secret = secret
	}

	getAllGroupTypes() {
		return this.get('/groups/v2/group_types')
	}
	
	async get(endpoint, options = {}) {
		const response = await fetch(this.base_url + endpoint, {
			headers: {
				'Authorization': `Basic ${btoa(`${this.app_id}:${this.secret}`)}`,
				'Content-Type': 'application/json',
				...options.headers
			},
			...options
		}).then(res => res.json())

		if(response.errors) {
			throw new Error(response.errors[0].detail)
		}

		return response
	}
}

export default Api

export function useApi(path) {
	const { authConfig } = useSelect((select) => {
		return {
			authConfig: select(optionsStore).getOptionGroup('pco_connect')
		}
	}, [])

	if(!authConfig) {
		return null
	}

	return new Api(authConfig.app_id, authConfig.secret)
}

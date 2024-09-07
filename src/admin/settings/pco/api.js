import settingsStore from "../settingsStore";
import { useSelect } from "@wordpress/data";

/**
 * Basic wrapper for the PCO API.
 */
class Api {
	base_url = 'https://api.planningcenteronline.com';

	constructor(app_id, secret) {
		this.app_id = app_id;
		this.secret = secret;
	}

	getAllGroupTypes() {
		return this.get('/groups/v2/group_types');
	}

	async get(endpoint, options = {}) {
		try {
			const response = await fetch(this.base_url + endpoint, {
				headers: {
					'Authorization': `Basic ${btoa(`${this.app_id}:${this.secret}`)}`,
					'Content-Type': 'application/json',
					...options.headers,
				},
				...options,
			}).then((res) => res.json());

			if (response.errors) {
				throw new Error(response.errors[0].detail);
			}

			return response;
		} catch (error) {
			throw new Error(`Network error: ${error.message}`);
		}
	}
}

export default Api;

/**
 * React hook to get the PCO API instance.
 *
 * @returns {Api|null}
 */
export function useApi() {
	const { authConfig } = useSelect((select) => {
		return {
			authConfig: select(settingsStore).getOptionGroup('pco_connect'),
		};
	}, []);

	if (!authConfig) {
		return null;
	}

	return new Api(authConfig.app_id, authConfig.secret);
}

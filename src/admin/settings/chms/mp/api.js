
class Api {
	constructor({
		api_endpoint,
		oauth_discovery_endpoint,
		client_id,
		client_secret,
		api_scope,
	}) {
		this.api_endpoint = api_endpoint
		this.oauth_discovery_endpoint = oauth_discovery_endpoint
		this.client_id = client_id
		this.client_secret = client_secret
		this.api_scope = api_scope

		this.token_endpoint = null
		this.access_token = localStorage.getItem('mp_access_token') || null
		this.token_expires = localStorage.getItem('mp_token_expires') || null

		this.abortController = new AbortController()
	}

	abort() {
		this.abortController.abort()
	}

	isAuthenticated() {
		return !!this.token_expires && Date.now() < this.token_expires
	}

	getAuthEndpoint() {
		let url = this.oauth_discovery_endpoint;
  	url += '?response_type=code';
  	url += `&client_id=${this.client_id}`;
    url += `&scope=${this.api_scope}`;
    url += `&redirect_uri=${this.mpRedirectURL}`;
		return url
	}

	async getAccessToken() {
		const authData = await fetch(this.getAuthEndpoint(), { signal: this.abortController.signal })
		.then(response => response.json())
		
		
		const { token_endpoint } = authData

		const tokenData = await fetch(token_endpoint, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded'
			},
			body: new URLSearchParams({
				grant_type: 'client_credentials',
				scope: this.api_scope,
				client_id: this.client_id,
				client_secret: this.client_secret,
			}),
			signal: this.abortController.signal
		})
		.then(response => response.json())
		
		if (tokenData.error) {
			localStorage.removeItem('mp_access_token')
			localStorage.removeItem('mp_token_expires')
			throw new Error(`Authentication failed: ${tokenData.error_description}`)
		}

		this.access_token = tokenData.access_token

		// give the token a 10 second buffer
		this.token_expires = Date.now() + (tokenData.expires_in - 10) * 1000

		localStorage.setItem('mp_access_token', this.access_token)
		localStorage.setItem('mp_token_expires', this.token_expires)
	}

	async authenticate() {
		if (this.isAuthenticated()) {
			return
		}

		await this.getAccessToken()		
	}

	async getGroups(config) {
		await this.authenticate()

		const url = new URL(`${this.api_endpoint}/tables/groups`)

		if(config) {
			for(const key in config) {
				url.searchParams.append(`$${key}`, config[key])
			}
		}

		let error = false

		const groups = await fetch(url, {
			headers: {
				'Authorization': `Bearer ${this.access_token}`
			},
			signal: this.abortController.signal
		})
		.then(response => {
			if(!response.ok) {
				error = true
			}
			return response.json()
		})

		if(error) {
			throw new Error(groups.Message)
		}

		return groups
	}
}

export default Api;

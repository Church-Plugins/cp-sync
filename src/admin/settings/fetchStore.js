import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const INITIAL_STATE = {}

const actions = {
	fetchData(endpoint, args = {}) {
		return {
			type: 'FETCH',
			path: endpoint,
			...args
		}
	},
	setData(endpoint, data) {
		return {
			type: 'SET_DATA',
			endpoint,
			data
		}
	}
}

/**
 * A basic store for fetching and caching data from an arbitrary rest endpoint.
 */
const fetchStore = createReduxStore( 'cp-sync/api', {
	reducer: ( state = INITIAL_STATE, action ) => {
		switch ( action.type ) {
			case 'SET_DATA':
				return {
					...state,
					[ action.endpoint ]: action.data
				}
			default:
				return state;
		}
	},
	actions,
	selectors: {
		getData( state, endpoint ) {
			return state[ endpoint ]
		}
	},
	controls: {
		FETCH: ( args ) => apiFetch( args )
	},
	resolvers: {
		*getData( endpoint, args ) {
			const response = yield actions.fetchData( endpoint, args );
			return actions.setData( endpoint, response.data )
		}
	}
} )

register( fetchStore )

export default fetchStore;

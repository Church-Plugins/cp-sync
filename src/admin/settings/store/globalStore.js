import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const INITIAL_STATE = {
	settingsCache: {},
	error: null,
	isSaving: false,
	isDirty: false,
	isLoading: true,
	connectedChMS: {},
	filters: {},
	compareOptions: {},
}

const actions = {
	setSettings( chms, data, options ) {
		return {
			type: 'SET_SETTINGS',
			chms,
			data,
			...options
		}
	},
	setIsConnected( chms, value ) {
		return {
			type: 'SET_IS_CONNECTED',
			chms,
			value
		}
	},
	setError( message ) {
		return {
			type: 'SET_ERROR',
			message
		}
	},
	setFilters( chms, filters ) {
		return {
			type: 'SET_FILTERS',
			chms,
			filters
		}
	},
	fetch( path, options = {} ) {
		return {
			type: 'FETCH',
			path,
			...options
		}
	},
	*persistSettings(chms, data) {
		yield { type: 'IS_SAVING', value: true }

		try {
			const response = yield actions.fetch( `/cp-sync/v1/${chms}/settings`, { data: { data }, method: 'POST' } );

			if ( response ) {
				yield { type: 'SETTINGS_UPDATE_SUCCESS' }
			} else {
				yield actions.setError( __( 'Settings were not saved.', 'cp-sync' ) )
			}
		} catch ( e ) {
			return actions.setError( e.message )
		} finally {
			return { type: 'IS_SAVING', value: false }
		}		
	},
}

const controls = {
	FETCH: ( { type, ...args } ) => apiFetch( args ),
}

const resolvers = {
	*getSettings( chms ) {
		try {
			const settings = yield actions.fetch( `/cp-sync/v1/${chms}/settings` )
			return actions.setSettings( chms, settings, { hydrate: true })
		} catch ( e ) {
			return actions.setError( e.message )
		}
	},
	*getIsConnected( chms ) {
		try {
			const response = yield actions.fetch( `/cp-sync/v1/${chms}/check-connection` )
			return actions.setIsConnected( chms, response.connected )
		} catch ( e ) {
			return actions.setError( e.message )
		}
	},
	*getFilters( chms ) {
		try {
			const response = yield actions.fetch( `/cp-sync/v1/${chms}/filters` )
			return actions.setFilters( chms, response )
		} catch ( e ) {
			return actions.setError( e.message )
		}
	}
}

const selectors = {
	getSettings: ( state, chms ) => state.settingsCache[chms],
	getError: state => state.error,
	getIsSaving: state => state.isSaving,
	getIsDirty: state => state.isDirty,
	getIsLoading: state => state.isLoading,
	getIsConnected: (state, chms) => !!state.connectedChMS[chms],
	getFilters: (state, chms) => state.filters[chms],
}

const reducer = ( state = INITIAL_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_SETTINGS':
			return {
				...state,
				settingsCache: {
					...state.settingsCache,
					[action.chms]: action.data
				},
				isDirty: !action.hydrate,
			}
		case 'SET_ERROR':
			return {
				...state,
				error: action.error
			}
		case 'SETTINGS_UPDATE_SUCCESS':
			return {
				...state,
				error: null,
				isSaving: false,
				isDirty: false
			}
		case 'IS_SAVING':
			return {
				...state,
				isSaving: action.value
			}
		case 'SET_IS_CONNECTED':
			return {
				...state,
				connectedChMS: {
					...state.connectedChMS,
					[action.chms]: action.value
				}
			}
		case 'SET_FILTERS':
			return {
				...state,
				filters: {
					...state.filters,
					[action.chms]: action.filters
				}
			}
		default:
			return state
	}
}

const globalStore = createReduxStore(
	'cp-sync/global-settings',
	{
		reducer,
		actions,
		controls,
		resolvers,
		selectors,
	}
)

register( globalStore )

export default globalStore;

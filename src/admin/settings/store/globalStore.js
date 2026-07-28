import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * The single canonical settings store for the admin SPA.
 *
 * Store name is kept as `cp-sync/global-settings` intentionally: every consumer
 * already selects/dispatches against this name, so renaming would churn every
 * file for no behavioral gain. As of the Increment-0 store collapse this store
 * now owns ALL settings state:
 *
 *   - values        one tree for global + per-ChMS settings:
 *                   `{ global: {...}, [chms]: {...} }`
 *                   (folds in what used to be React `useState` globalSettings and
 *                    the old `settingsCache[chms]`).
 *   - connection    per-ChMS connected flag (was `connectedChMS`).
 *   - filters       per-ChMS filter config (unchanged).
 *   - optionsCache  `{ [endpoint]: data }` async option lists — folds in the role
 *                   of the retired `store/settingsStore.js` (`cp-sync/settings`).
 *   - ui            exactly one each of isSaving / isDirty / isLoading / error.
 *
 * There is a SINGLE dirty flag and a SINGLE save entry point — the old split
 * (global dirtiness in Context `useState`, per-ChMS dirtiness in the store) is gone.
 */
const INITIAL_STATE = {
	values: { global: {} },
	connection: {},
	filters: {},
	optionsCache: {},
	ui: {
		isSaving: false,
		isDirty: false,
		isLoading: true,
		error: null,
	},
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
	// Seed the global slice from the localized page data (does NOT mark dirty).
	setGlobalSettings( data ) {
		return {
			type: 'SET_GLOBAL_SETTINGS',
			data,
		}
	},
	// Update a single global field (marks the store dirty).
	setGlobalField( field, value ) {
		return {
			type: 'SET_GLOBAL_FIELD',
			field,
			value,
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
	setOptions( endpoint, data ) {
		return {
			type: 'SET_OPTIONS',
			endpoint,
			data
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
	*persistGlobalSettings(data) {
		yield { type: 'IS_SAVING', value: true }

		try {
			const response = yield actions.fetch( `/cp-sync/v1/settings`, { data: { data }, method: 'POST' } );

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
	/**
	 * The single save entry point. Persists the global slice and (when a ChMS is
	 * active) that ChMS's slice, then clears the one dirty flag. Request shapes
	 * match the legacy `persistGlobalSettings` / `persistSettings` bodies exactly.
	 */
	*save( globalValues, chms, chmsValues ) {
		yield { type: 'IS_SAVING', value: true }

		try {
			yield actions.fetch( `/cp-sync/v1/settings`, { data: { data: globalValues }, method: 'POST' } );

			if ( chms ) {
				yield actions.fetch( `/cp-sync/v1/${chms}/settings`, { data: { data: chmsValues }, method: 'POST' } );
			}

			yield { type: 'SETTINGS_UPDATE_SUCCESS' }
		} catch ( e ) {
			yield actions.setError( e.message )
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
	},
	// Folds in the retired settingsStore.getData resolver: fetch an arbitrary
	// endpoint and cache `response.data` keyed by endpoint.
	*getOptions( endpoint ) {
		const response = yield actions.fetch( endpoint )
		return actions.setOptions( endpoint, response.data )
	},
}

const selectors = {
	getSettings: ( state, chms ) => state.values[chms],
	getGlobalSettings: ( state ) => state.values.global,
	getError: state => state.ui.error,
	getIsSaving: state => state.ui.isSaving,
	getIsDirty: state => state.ui.isDirty,
	getIsLoading: state => state.ui.isLoading,
	getIsConnected: (state, chms) => !!state.connection[chms],
	getFilters: (state, chms) => state.filters[chms],
	getOptions: (state, endpoint) => state.optionsCache[endpoint],
}

const reducer = ( state = INITIAL_STATE, action ) => {
	switch ( action.type ) {
		case 'SET_SETTINGS':
			return {
				...state,
				values: {
					...state.values,
					[action.chms]: action.data
				},
				ui: {
					...state.ui,
					isDirty: !action.hydrate,
				},
			}
		case 'SET_GLOBAL_SETTINGS':
			return {
				...state,
				values: {
					...state.values,
					global: action.data,
				},
			}
		case 'SET_GLOBAL_FIELD':
			return {
				...state,
				values: {
					...state.values,
					global: {
						...state.values.global,
						[action.field]: action.value,
					},
				},
				ui: {
					...state.ui,
					isDirty: true,
				},
			}
		case 'SET_ERROR':
			// NOTE: preserves legacy behavior byte-for-byte — the action carries
			// `message` but the reducer reads `action.error`, so `error` is left
			// falsy and the error Alert never renders. Do NOT "fix" here: doing so
			// would surface error UI that the app never showed (a visible change).
			return {
				...state,
				ui: {
					...state.ui,
					error: action.error,
				},
			}
		case 'SETTINGS_UPDATE_SUCCESS':
			return {
				...state,
				ui: {
					...state.ui,
					error: null,
					isSaving: false,
					isDirty: false,
				},
			}
		case 'IS_SAVING':
			return {
				...state,
				ui: {
					...state.ui,
					isSaving: action.value,
				},
			}
		case 'SET_IS_CONNECTED':
			return {
				...state,
				connection: {
					...state.connection,
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
		case 'SET_OPTIONS':
			return {
				...state,
				optionsCache: {
					...state.optionsCache,
					[action.endpoint]: action.data
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

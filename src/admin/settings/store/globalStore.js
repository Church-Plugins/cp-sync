import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const INITIAL_STATE = {
	settingsCache: {},
	error: null,
	isSaving: false,
	isDirty: false,
	isLoading: true,
	connectedChMS: {}
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
	fetchSettings({ chms, ...args }) {
		return {
			type: 'FETCH',
			path: `/cp-sync/v1/${chms}/settings`,
			...args
		}
	},
	*persistSettings(chms, data) {
		yield { type: 'IS_SAVING', value: true }

		let response;
		try {
			response = yield actions.fetchSettings({ chms, data: { data }, method: 'POST' });
		} catch ( e ) {
			return {
				type: 'SETTINGS_UPDATE_ERROR',
				message: e.message
			}
		}

		if ( response ) {
			return { type: 'SETTINGS_UPDATE_SUCCESS' }
		}

		return { type: 'SETTINGS_UPDATE_ERROR', message: __( 'Settings were not saved.', 'cp-sync' ) }
	},
	setIsConnected( chms, value ) {
		return {
			type: 'SET_IS_CONNECTED',
			chms,
			value
		}
	}
}

const settingsStore = createReduxStore( 'cp-sync/global-settings', {
	reducer: ( state = INITIAL_STATE, action ) => {
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
			case 'SETTINGS_UPDATE_ERROR':
				return {
					...state,
					error: action.message,
					isSaving: false
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
			default:
				return state
		}
	},
	actions,
	selectors: {
		getSettings: ( state, chms ) => state.settingsCache[chms],
		getError: state => state.error,
		getIsSaving: state => state.isSaving,
		getIsDirty: state => state.isDirty,
		getIsLoading: state => state.isLoading,
		getIsConnected: (state, chms) => !!state.connectedChMS[chms],
	},
	controls: {
		FETCH: ( args ) => apiFetch( args ),
	},
	resolvers: {
		*getSettings( chms ) {
			if(!chms) return;

			const settings = yield actions.fetchSettings({ chms })

			if(settings) {
				return actions.setSettings( chms, settings, { hydrate: true })
			}

			return;
		},
		*getIsConnected( chms ) {
			if(!chms) return;
			
			const response = yield actions.fetchSettings({ chms, path: `/cp-sync/v1/${chms}/check-connection` })

			if(response) {
				return actions.setIsConnected( chms, response.connected )
			}

			return;
		}
	}
} )

register( globalStore )

export default globalStore;

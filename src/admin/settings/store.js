import { createReduxStore, register } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

const INITIAL_STATE = {
	optionGroups: {},
	error: null,
	isSaving: false,
	dirtyGroups: {},
	isConnected: false,
}

const actions = {
	setOptionGroup(group, data, hydrate = false) {
		return {
			type: 'SET_OPTION_GROUP',
			group,
			data,
			hydrate
		};
	},
	fetchOptionGroup(group, args = {}) {
		return {
			type: 'FETCH',
			path: '/cp-sync/v1/options/' + group,
			group,
			...args
		};
	},
	*persistOptionGroup(group, data) {
		yield { type: 'IS_SAVING', value: true };

		let response;
		try {
			response = yield actions.fetchOptionGroup(group, { method: 'POST', data });
		} catch (e) {
			return {
				type: 'OPTIONS_UPDATE_ERROR',
				message: e.message
			};
		}

		if (response) {
			return { type: 'OPTIONS_UPDATE_SUCCESS', data: response, group };
		}

		return { type: 'OPTIONS_UPDATE_ERROR', message: __('Settings were not saved.', 'cp-sync') };
	},
	setIsConnected(isConnected) {
		return {
			type: 'SET_IS_CONNECTED',
			isConnected,
		};
	}
};

const optionsStore = createReduxStore('cp-sync/options', {
	reducer: (state = INITIAL_STATE, action) => {
		switch (action.type) {
			case 'SET_OPTION_GROUP':
				return {
					...state,
					optionGroups: {
						...state.optionGroups,
						[action.group]: action.data
					},
					dirtyGroups: {
						...state.dirtyGroups,
						[action.group]: action.hydrate ? false : true
					}
				};
			case 'OPTIONS_UPDATE_SUCCESS':
				return {
					...state,
					error: null,
					isSaving: false,
					dirtyGroups: {
						...state.dirtyGroups,
						[action.group]: false
					}
				};
			case 'OPTIONS_UPDATE_ERROR':
				return {
					...state,
					error: action.message,
					isSaving: false
				};
			case 'IS_SAVING':
				return {
					...state,
					isSaving: action.value
				};
			case 'SET_IS_CONNECTED':
				return {
					...state,
					isConnected: action.isConnected
				};
			default:
				return state;
		}
	},
	actions,
	selectors: {
		getOptionGroup: (state, group) => state.optionGroups[group],
		isSaving: (state) => state.isSaving,
		isDirty: (state, group) => state.dirtyGroups[group],
		getError: (state) => state.error,
		isConnected: (state) => state.isConnected,
	},
	controls: {
		FETCH: (args) => apiFetch(args)
	},
	resolvers: {
		*getOptionGroup(group) {
			const response = yield actions.fetchOptionGroup(group);
			return actions.setOptionGroup(group, response, true);
		}
	}
});

register(optionsStore);

export { actions };
export default optionsStore;
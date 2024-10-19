import { createContext, useContext, useState, useEffect } from 'react'
import globalStore from '../store/globalStore'
import { useDispatch, useSelect } from '@wordpress/data'
import apiFetch from '@wordpress/api-fetch'

const SettingsContext = createContext({
	chms: null,
	debugMode: null,
	setChms: () => {},
	isConnected: false,
	isSaving: false,
	isDirty: false,
	settings: {},
	updateSettings: () => {},
	updateField: () => {},
	getField: () => {},
	getFilterConfig: (filterGroup) => {},
	save: () => {},
	globalSettings: {},
	updateGlobalSettings: () => {},
	compareOptions: {},
})

const defaultGlobalSettings = {
	chms: 'pco',
	debugMode: 0,
	license: '',
	beta: false,
	status: '',
}

export const useSettings = () => {
	const context = useContext(SettingsContext)
	if (!context) {
		throw new Error('useSettings must be used within a SettingsProvider')
	}
	return context
}

export default function SettingsProvider({ globalSettings: initialGlobalSettings, children, compareOptions }) {
	const [globalSettings, setGlobalSettings] = useState({ ...defaultGlobalSettings, ...initialGlobalSettings })
	const [globalUnsavedChanges, setGlobalUnsavedChanges] = useState(false)
	const [isReady, setIsReady] = useState(false)

	const { isConnected, isConnectionLoaded, isSaving, isDirty, settings, error, filterConfig } = useSelect((select) => {
		return {
			settings: select(globalStore).getSettings(globalSettings.chms) || {},
			isConnected: select(globalStore).getIsConnected(globalSettings.chms),
			isConnectionLoaded: select(globalStore).hasFinishedResolution('getIsConnected', [globalSettings.chms]),
			isLoading: select(globalStore).getResolutionState('getSettings', [globalSettings.chms])?.status === 'resolving',
			isSaving: select(globalStore).getIsSaving(),
			isDirty: select(globalStore).getIsDirty(),
			error: select(globalStore).getError(),
			filterConfig: select(globalStore).getFilters(globalSettings.chms) || {},
		}
	})

	const { persistSettings, setSettings } = useDispatch(globalStore)

	const saveGlobal = async (data = false) => {
		if (!globalUnsavedChanges && !data) {
			return
		}

		try {
			await apiFetch({
				path: '/cp-sync/v1/settings',
				method: 'POST',
				data: { data: data || globalSettings }
			})
			setGlobalUnsavedChanges(false)
		}catch(err) {
			setError(err.message)
		}
	}

	const save = () => {
		if(globalSettings.chms) {
			persistSettings(globalSettings.chms, settings)
		}
		saveGlobal()
	}

	const updateGlobalSettings = async (field, value) => {
		// when switching ChMS, we want to re-save immediately
		if(field === 'chms' && value !== globalSettings.chms) {
			await saveGlobal({ ...globalSettings, [field]: value })
		} else {
			setGlobalUnsavedChanges(true)
		}

		setGlobalSettings({
			...globalSettings,
			[field]: value
		})
	}

	const updateSettings = (newSettings) => {
		setSettings(globalSettings.chms, {
			...settings,
			...newSettings
		})
	}

	const updateField = (group, field, value) => {
		updateSettings({
			[group]: {
				...settings[group],
				[field]: value
			}
		})
	}

	const getField = (group, field) => {
		return settings[group][field]
	}

	/**
	 * Gets the filter config for a filter group, e.g. 'groups' or 'events'
	 * @param {*} filterGroup 
	 */
	const getFilterConfig = (filterGroup) => {
		return filterConfig[filterGroup] || false
	}

	const value = {
		chms: globalSettings.chms,
		error,
		isConnected,
		isSaving,
		isDirty: isDirty || globalUnsavedChanges,
		settings,
		updateSettings,
		updateField,
		getField,
		getFilterConfig,
		save,
		saveGlobal,
		globalUnsavedChanges,
		globalSettings,
		updateGlobalSettings,
		compareOptions,
	}

	useEffect(() => {
		saveGlobal()
	}, [globalSettings.chms])

	useEffect(() => {
		if(isConnectionLoaded) {
			setIsReady(true)
		}
	}, [isConnectionLoaded])

	return (
		<SettingsContext.Provider value={value}>
			{isReady && children}
		</SettingsContext.Provider>
	)
}